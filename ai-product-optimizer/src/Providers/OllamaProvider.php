<?php
/**
 * Ollama self-hosted provider.
 *
 * Supports any model available on the configured Ollama instance
 * (default: http://localhost:11434).
 *
 * @package AIProductOptimizer\Providers
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Providers;

use AIProductOptimizer\Exceptions\ProviderException;

/**
 * Class OllamaProvider
 */
class OllamaProvider extends AbstractProvider {

	private const DEFAULT_ENDPOINT = 'http://localhost:11434';

	public function get_slug(): string        { return 'ollama'; }
	public function get_display_name(): string { return 'Ollama (Self-hosted)'; }

	/**
	 * {@inheritdoc}
	 *
	 * Ollama doesn't require an API key — get_api_key() is not called.
	 */
	public function test_connection(): bool {
		$endpoint = $this->get_endpoint();
		$response = wp_remote_get( $endpoint . '/api/tags', array( 'timeout' => 2 ) );
		return ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response );
	}

	/**
	 * Discover available models from the Ollama instance.
	 *
	 * @return array<string, string>
	 */
	public function get_available_models(): array {
		$endpoint = $this->get_endpoint();
		$response = wp_remote_get( $endpoint . '/api/tags', array( 'timeout' => 5 ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array( 'llama3.2' => 'Llama 3.2 (default)' );
		}

		$body    = json_decode( wp_remote_retrieve_body( $response ), true );
		$models  = $body['models'] ?? array();
		$result  = array();

		foreach ( $models as $model ) {
			$name           = $model['name'] ?? '';
			$result[ $name ] = $name;
		}

		return $result ?: array( 'llama3.2' => 'Llama 3.2 (default)' );
	}

	protected function build_http_request( string $prompt, array $options ): array {
		$endpoint   = $this->get_endpoint();
		$model      = $options['model'] ?? $this->config['model'] ?? 'llama3.2';
		$max_tokens = $options['max_tokens'] ?? $this->config['max_tokens'] ?? 1024;

		return array(
			'url'     => $endpoint . '/api/generate',
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array(
				'model'   => $model,
				'prompt'  => $prompt,
				'stream'  => false,
				'options' => array( 'num_predict' => (int) $max_tokens ),
			) ),
			'timeout' => 120, // Local models can be slower.
		);
	}

	protected function parse_response( array $raw ): string {
		$content = $raw['response'] ?? null;

		if ( null === $content ) {
			throw new ProviderException( 'Unexpected Ollama response structure.' );
		}

		return trim( (string) $content );
	}

	/**
	 * Validate and return the configured Ollama endpoint.
	 *
	 * Only localhost and RFC-1918 private IPs are allowed by default to
	 * prevent SSRF to public hosts.
	 *
	 * @return string
	 * @throws ProviderException If the endpoint is a public IP.
	 */
	private function get_endpoint(): string {
		$endpoint = rtrim( (string) ( $this->config['endpoint'] ?? self::DEFAULT_ENDPOINT ), '/' );

		if ( ! $this->is_allowed_endpoint( $endpoint ) ) {
			throw new ProviderException(
				'Ollama endpoint must be a localhost or private network address.'
			);
		}

		return $endpoint;
	}

	/**
	 * Check whether the endpoint URL points to an allowed (private/local) host.
	 *
	 * @param string $url Endpoint URL.
	 * @return bool
	 */
	private function is_allowed_endpoint( string $url ): bool {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! $host ) {
			return false;
		}

		// Localhost names.
		if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
			return true;
		}

		// RFC-1918 ranges: 10.x.x.x, 172.16-31.x.x, 192.168.x.x.
		if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$long = ip2long( $host );
			return (
				( $long >= ip2long( '10.0.0.0' )     && $long <= ip2long( '10.255.255.255' ) ) ||
				( $long >= ip2long( '172.16.0.0' )   && $long <= ip2long( '172.31.255.255' ) ) ||
				( $long >= ip2long( '192.168.0.0' )  && $long <= ip2long( '192.168.255.255' ) )
			);
		}

		/**
		 * Filter to allow custom trusted Ollama hostnames.
		 *
		 * @param bool   $allowed Whether the host is allowed.
		 * @param string $host    Hostname or IP being checked.
		 */
		return (bool) apply_filters( 'aipo_ollama_allowed_host', false, $host );
	}
}
