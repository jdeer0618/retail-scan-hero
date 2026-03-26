<?php
/**
 * Unit tests for GrokProvider.
 *
 * Grok uses an OpenAI-compatible API so the response shape is identical
 * to OpenAI. Tests verify the correct endpoint URL and slug are used.
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Exceptions\ProviderException;
use AIProductOptimizer\Providers\GrokProvider;
use AIProductOptimizer\Queue\JobLogger;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class GrokProviderTest
 */
class GrokProviderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Slug / model list
	// -----------------------------------------------------------------------

	public function test_get_slug(): void {
		$this->assertSame( 'grok', $this->make_provider()->get_slug() );
	}

	public function test_get_available_models_contains_grok_model(): void {
		$models = $this->make_provider()->get_available_models();
		// Should contain at least one grok model.
		$has_grok_model = false;
		foreach ( array_keys( $models ) as $key ) {
			if ( str_starts_with( $key, 'grok' ) ) {
				$has_grok_model = true;
				break;
			}
		}
		$this->assertTrue( $has_grok_model );
	}

	// -----------------------------------------------------------------------
	// Successful generation
	// -----------------------------------------------------------------------

	public function test_generate_returns_content_on_200(): void {
		$this->mock_encrypted_key( 'xai-test-key' );
		$this->mock_http_response( 200, $this->openai_compat_success_body( 'Great product name' ) );
		$this->mock_circuit_breaker_clear();

		$result = $this->make_provider()->generate( 'Generate a product name.' );

		$this->assertSame( 'Great product name', $result );
	}

	public function test_generate_trims_whitespace_from_content(): void {
		$this->mock_encrypted_key( 'xai-test-key' );
		$this->mock_http_response( 200, $this->openai_compat_success_body( "  Trimmed Name\n  " ) );
		$this->mock_circuit_breaker_clear();

		$result = $this->make_provider()->generate( 'prompt' );

		$this->assertSame( 'Trimmed Name', $result );
	}

	// -----------------------------------------------------------------------
	// Endpoint verification
	// -----------------------------------------------------------------------

	public function test_generate_posts_to_xai_endpoint(): void {
		$this->mock_encrypted_key( 'xai-test-key' );
		$this->mock_circuit_breaker_clear();

		$request_url = null;
		Functions\expect( 'wp_remote_post' )
			->andReturnUsing( static function ( string $url, array $args ) use ( &$request_url ): array {
				$request_url = $url;
				return array();
			} );
		Functions\expect( 'is_wp_error' )->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )
			->andReturn( $this->openai_compat_success_body( 'ok' ) );

		$this->make_provider()->generate( 'prompt' );

		$this->assertStringContainsString( 'api.x.ai', $request_url );
	}

	// -----------------------------------------------------------------------
	// HTTP errors
	// -----------------------------------------------------------------------

	public function test_generate_throws_on_401_unauthorized(): void {
		$this->mock_encrypted_key( 'xai-invalid' );
		$this->mock_http_response( 401, '{"error":{"message":"Invalid API key"}}' );
		$this->mock_circuit_breaker_increment();

		$this->expectException( ProviderException::class );
		$this->make_provider()->generate( 'prompt' );
	}

	public function test_generate_throws_on_429_rate_limit(): void {
		$this->mock_encrypted_key( 'xai-test-key' );

		Functions\expect( 'get_transient' )->andReturn( false );
		Functions\expect( 'wp_remote_post' )->andReturn( array() );
		Functions\expect( 'is_wp_error' )->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 429 );
		Functions\expect( 'wp_remote_retrieve_header' )
			->with( \Mockery::any(), 'retry-after' )
			->andReturn( '30' );

		Functions\expect( 'set_transient' )->andReturn( true );
		Functions\expect( 'get_option' )
			->with( 'aipo_circuit_breaker_threshold', 10 )
			->andReturn( 100 );
		Functions\expect( 'do_action' )->andReturn( null );

		$this->expectException( ProviderException::class );
		$this->make_provider()->generate( 'prompt' );
	}

	public function test_generate_throws_on_wp_error(): void {
		$this->mock_encrypted_key( 'xai-test-key' );

		Functions\expect( 'get_transient' )->andReturn( false );
		Functions\expect( 'wp_remote_post' )
			->andReturn( new \WP_Error( 'http_request_failed', 'connection refused' ) );
		Functions\expect( 'is_wp_error' )->andReturn( true );

		Functions\expect( 'set_transient' )->andReturn( true );
		Functions\expect( 'get_option' )
			->with( 'aipo_circuit_breaker_threshold', 10 )
			->andReturn( 100 );
		Functions\expect( 'do_action' )->andReturn( null );

		$this->expectException( ProviderException::class );
		$this->make_provider()->generate( 'prompt' );
	}

	// -----------------------------------------------------------------------
	// Response parsing edge cases
	// -----------------------------------------------------------------------

	public function test_generate_throws_on_missing_choices_key(): void {
		$this->mock_encrypted_key( 'xai-test-key' );
		$this->mock_http_response( 200, '{"id":"xxx","object":"chat.completion"}' );
		$this->mock_circuit_breaker_increment();

		$this->expectException( ProviderException::class );
		$this->make_provider()->generate( 'prompt' );
	}

	public function test_generate_throws_on_non_json_body(): void {
		$this->mock_encrypted_key( 'xai-test-key' );
		$this->mock_http_response( 200, 'not-json-at-all' );
		$this->mock_circuit_breaker_increment();

		$this->expectException( ProviderException::class );
		$this->make_provider()->generate( 'prompt' );
	}

	// -----------------------------------------------------------------------
	// API key
	// -----------------------------------------------------------------------

	public function test_generate_throws_when_no_api_key_configured(): void {
		Functions\expect( 'get_transient' )->andReturn( false );

		$this->expectException( ProviderException::class );
		$this->expectExceptionMessageMatches( '/No API key configured/' );

		$provider = new GrokProvider( array(), $this->createMock( JobLogger::class ) );
		$provider->generate( 'prompt' );
	}

	// -----------------------------------------------------------------------
	// build_http_request options
	// -----------------------------------------------------------------------

	public function test_generate_uses_configured_model(): void {
		$this->mock_encrypted_key( 'xai-test-key' );
		$this->mock_circuit_breaker_clear();

		$request_body = null;
		Functions\expect( 'wp_remote_post' )
			->andReturnUsing( static function ( string $url, array $args ) use ( &$request_body ): array {
				$request_body = json_decode( $args['body'], true );
				return array();
			} );
		Functions\expect( 'is_wp_error' )->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )
			->andReturn( $this->openai_compat_success_body( 'ok' ) );

		$provider = new GrokProvider(
			array( 'api_key_enc' => 'enc', 'model' => 'grok-2' ),
			$this->createMock( JobLogger::class )
		);

		$this->mock_encrypted_key( 'xai-test-key' );
		$provider->generate( 'prompt' );

		$this->assertSame( 'grok-2', $request_body['model'] ?? null );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function make_provider(): GrokProvider {
		$logger = $this->createMock( JobLogger::class );
		$logger->method( 'error' )->willReturn( null );
		$logger->method( 'warning' )->willReturn( null );
		return new GrokProvider( array( 'api_key_enc' => 'encrypted-key' ), $logger );
	}

	private function mock_encrypted_key( string $plaintext ): void {
		Functions\expect( 'openssl_cipher_iv_length' )->andReturn( 16 );
		Functions\expect( 'hash' )->andReturnUsing( 'hash' );
		Functions\expect( 'openssl_decrypt' )->andReturn( $plaintext );
		Functions\expect( 'base64_decode' )->andReturnUsing( 'base64_decode' );
	}

	private function mock_http_response( int $code, string $body ): void {
		Functions\expect( 'wp_remote_post' )->andReturn( array() );
		Functions\expect( 'is_wp_error' )->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( $code );
		Functions\expect( 'wp_remote_retrieve_body' )->andReturn( $body );
	}

	private function mock_circuit_breaker_clear(): void {
		Functions\expect( 'get_transient' )->andReturn( false );
		Functions\expect( 'delete_transient' )->andReturn( true );
	}

	private function mock_circuit_breaker_increment(): void {
		Functions\expect( 'get_transient' )->andReturn( 0 );
		Functions\expect( 'set_transient' )->andReturn( true );
		Functions\expect( 'get_option' )
			->with( 'aipo_circuit_breaker_threshold', 10 )
			->andReturn( 100 );
		Functions\expect( 'do_action' )->andReturn( null );
	}

	/**
	 * OpenAI-compatible response body (used by both OpenAI and Grok).
	 */
	private function openai_compat_success_body( string $content ): string {
		return json_encode( array(
			'choices' => array(
				array( 'message' => array( 'content' => $content ) ),
			),
		) );
	}
}
