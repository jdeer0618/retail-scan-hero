<?php
/**
 * Abstract AI Provider base class.
 *
 * Provides shared infrastructure for all concrete provider implementations:
 * - Exponential back-off retry (3 attempts: 1 s, 4 s, 16 s).
 * - Rate-limit detection and Retry-After header handling.
 * - Circuit breaker: suspends a provider after N consecutive failures.
 * - Structured logging via JobLogger.
 * - API key decryption via KeyEncryption.
 *
 * Concrete providers must implement:
 *   build_http_request( string $prompt, array $options ): array
 *   parse_response( array $raw ): string
 *   get_slug(): string
 *   get_display_name(): string
 *   get_available_models(): array
 *
 * @package AIProductOptimizer\Providers
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Providers;

use AIProductOptimizer\Exceptions\ProviderException;
use AIProductOptimizer\Exceptions\RateLimitException;
use AIProductOptimizer\Providers\Contracts\AIProviderInterface;
use AIProductOptimizer\Queue\JobLogger;
use AIProductOptimizer\Security\KeyEncryption;

/**
 * Class AbstractProvider
 */
abstract class AbstractProvider implements AIProviderInterface {

	/**
	 * Retry delays in seconds (exponential back-off).
	 *
	 * @var int[]
	 */
	private const RETRY_DELAYS = array( 1, 4, 16 );

	/**
	 * Transient key prefix for the circuit-breaker failure counter.
	 */
	private const CB_TRANSIENT_PREFIX = 'aipo_cb_';

	/**
	 * Transient key prefix for the circuit-breaker suspended flag.
	 */
	private const CB_SUSPENDED_PREFIX = 'aipo_cb_suspended_';

	/**
	 * Circuit-breaker suspension window in seconds (5 minutes).
	 */
	private const CB_SUSPEND_TTL = 300;

	/**
	 * Provider configuration array (from plugin options).
	 *
	 * @var array<string, mixed>
	 */
	protected array $config;

	/**
	 * Job logger instance.
	 *
	 * @var JobLogger
	 */
	protected JobLogger $logger;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $config Provider config from plugin options.
	 * @param JobLogger            $logger Job logger for structured logging.
	 */
	public function __construct( array $config, JobLogger $logger ) {
		$this->config = $config;
		$this->logger = $logger;
	}

	// -----------------------------------------------------------------------
	// AIProviderInterface implementation
	// -----------------------------------------------------------------------

	/**
	 * {@inheritdoc}
	 */
	public function generate( string $prompt, array $options = array() ): string {
		if ( $this->is_suspended() ) {
			throw new ProviderException(
				sprintf(
					/* translators: %s: provider display name */
					esc_html__( 'AI provider "%s" is temporarily suspended due to repeated failures.', 'ai-product-optimizer' ),
					$this->get_display_name()
				)
			);
		}

		$last_exception = null;

		foreach ( self::RETRY_DELAYS as $attempt => $delay ) {
			try {
				$request  = $this->build_http_request( $prompt, $options );
				$raw      = $this->execute_http_request( $request );
				$content  = $this->parse_response( $raw );

				// Success — reset the circuit-breaker failure counter.
				$this->reset_circuit_breaker();

				return $content;

			} catch ( RateLimitException $e ) {
				// Honour the Retry-After value if present, else use our delay.
				$wait = $e->get_retry_after() ?? $delay;
				$this->logger->warning(
					sprintf( 'Rate limit on %s (attempt %d). Waiting %d s.', $this->get_slug(), $attempt + 1, $wait )
				);
				sleep( $wait );
				$last_exception = $e;

			} catch ( ProviderException $e ) {
				$this->logger->error(
					sprintf( 'Provider error on %s (attempt %d): %s', $this->get_slug(), $attempt + 1, $e->getMessage() )
				);
				$last_exception = $e;

				if ( $attempt < count( self::RETRY_DELAYS ) - 1 ) {
					sleep( $delay );
				}
			}
		}

		// All retries exhausted — increment the circuit breaker.
		$this->increment_circuit_breaker();

		throw $last_exception ?? new ProviderException(
			sprintf( 'All retries exhausted for provider "%s".', $this->get_display_name() )
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection(): bool {
		try {
			$this->generate( 'Respond with the single word: OK', array( 'max_tokens' => 10 ) );
			return true;
		} catch ( \Throwable $e ) {
			$this->logger->error( sprintf( 'Connection test failed for %s: %s', $this->get_slug(), $e->getMessage() ) );
			return false;
		}
	}

	// -----------------------------------------------------------------------
	// Abstract methods — implemented by concrete providers
	// -----------------------------------------------------------------------

	/**
	 * Build the HTTP request array for this provider's API.
	 *
	 * Return format matches wp_remote_post() $args parameter:
	 * [ 'headers' => […], 'body' => '…', 'timeout' => 30, … ]
	 * plus a top-level 'url' key for the endpoint.
	 *
	 * @param string               $prompt  Assembled prompt.
	 * @param array<string, mixed> $options Generation options.
	 * @return array<string, mixed>
	 */
	abstract protected function build_http_request( string $prompt, array $options ): array;

	/**
	 * Parse the raw HTTP response body into a clean string.
	 *
	 * @param array<string, mixed> $raw Decoded JSON response.
	 * @return string
	 * @throws ProviderException On unexpected response structure.
	 */
	abstract protected function parse_response( array $raw ): string;

	// -----------------------------------------------------------------------
	// Shared HTTP execution
	// -----------------------------------------------------------------------

	/**
	 * Execute the HTTP request using wp_remote_post().
	 *
	 * @param array<string, mixed> $request Request array including 'url'.
	 * @return array<string, mixed> Decoded JSON response body.
	 * @throws RateLimitException On HTTP 429.
	 * @throws ProviderException  On HTTP errors or malformed responses.
	 */
	protected function execute_http_request( array $request ): array {
		$url  = (string) $request['url'];
		$args = $request;
		unset( $args['url'] );

		/** @var array|\WP_Error $response */
		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new ProviderException(
				sprintf( 'HTTP request failed: %s', $response->get_error_message() )
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( 429 === $status_code ) {
			$retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );
			throw new RateLimitException( 'Rate limit exceeded.', $retry_after > 0 ? $retry_after : null );
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			throw new ProviderException(
				sprintf( 'API returned HTTP %d: %s', $status_code, wp_strip_all_tags( $body ) )
			);
		}

		$decoded = json_decode( $body, true );

		if ( ! is_array( $decoded ) ) {
			throw new ProviderException( 'API response was not valid JSON.' );
		}

		return $decoded;
	}

	// -----------------------------------------------------------------------
	// Key encryption helper
	// -----------------------------------------------------------------------

	/**
	 * Retrieve and decrypt the API key for this provider.
	 *
	 * @return string Plaintext API key.
	 * @throws ProviderException If no key is configured.
	 */
	protected function get_api_key(): string {
		$encrypted = $this->config['api_key_enc'] ?? '';

		if ( empty( $encrypted ) ) {
			throw new ProviderException(
				sprintf(
					/* translators: %s: provider display name */
					esc_html__( 'No API key configured for provider "%s".', 'ai-product-optimizer' ),
					$this->get_display_name()
				)
			);
		}

		return KeyEncryption::decrypt( (string) $encrypted );
	}

	// -----------------------------------------------------------------------
	// Circuit breaker
	// -----------------------------------------------------------------------

	/**
	 * Whether the circuit breaker has suspended this provider.
	 *
	 * @return bool
	 */
	private function is_suspended(): bool {
		return (bool) get_transient( self::CB_SUSPENDED_PREFIX . $this->get_slug() );
	}

	/**
	 * Increment the circuit-breaker failure counter.
	 * Suspends the provider when the threshold is reached.
	 *
	 * @return void
	 */
	private function increment_circuit_breaker(): void {
		$key     = self::CB_TRANSIENT_PREFIX . $this->get_slug();
		$count   = (int) get_transient( $key ) + 1;
		$threshold = (int) get_option( 'aipo_circuit_breaker_threshold', 10 );

		set_transient( $key, $count, self::CB_SUSPEND_TTL );

		if ( $count >= $threshold ) {
			set_transient( self::CB_SUSPENDED_PREFIX . $this->get_slug(), true, self::CB_SUSPEND_TTL );
			$this->logger->error(
				sprintf( 'Circuit breaker tripped for provider "%s" after %d failures.', $this->get_slug(), $count )
			);

			/**
			 * Fires when a provider is suspended by the circuit breaker.
			 *
			 * @param string $slug  Provider slug.
			 * @param int    $count Number of consecutive failures.
			 */
			do_action( 'aipo_provider_suspended', $this->get_slug(), $count );
		}
	}

	/**
	 * Reset the circuit-breaker failure counter on success.
	 *
	 * @return void
	 */
	private function reset_circuit_breaker(): void {
		delete_transient( self::CB_TRANSIENT_PREFIX . $this->get_slug() );
		delete_transient( self::CB_SUSPENDED_PREFIX . $this->get_slug() );
	}
}
