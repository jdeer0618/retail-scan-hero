<?php
/**
 * Unit tests for GeminiProvider.
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Exceptions\ProviderException;
use AIProductOptimizer\Providers\GeminiProvider;
use AIProductOptimizer\Queue\JobLogger;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class GeminiProviderTest
 */
class GeminiProviderTest extends TestCase {

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
		$this->assertSame( 'gemini', $this->make_provider()->get_slug() );
	}

	public function test_get_available_models_contains_gemini_pro(): void {
		$models = $this->make_provider()->get_available_models();
		$this->assertArrayHasKey( 'gemini-1.5-pro', $models );
	}

	public function test_get_available_models_contains_gemini_flash(): void {
		$models = $this->make_provider()->get_available_models();
		$this->assertArrayHasKey( 'gemini-1.5-flash', $models );
	}

	// -----------------------------------------------------------------------
	// Successful generation
	// -----------------------------------------------------------------------

	public function test_generate_returns_content_on_200(): void {
		$this->mock_encrypted_key( 'gemini-api-key' );
		$this->mock_http_response( 200, $this->gemini_success_body( 'Great product name' ) );
		$this->mock_circuit_breaker_clear();

		$result = $this->make_provider()->generate( 'Generate a product name.' );

		$this->assertSame( 'Great product name', $result );
	}

	public function test_generate_trims_whitespace_from_content(): void {
		$this->mock_encrypted_key( 'gemini-api-key' );
		$this->mock_http_response( 200, $this->gemini_success_body( "  Trimmed Name\n  " ) );
		$this->mock_circuit_breaker_clear();

		$result = $this->make_provider()->generate( 'prompt' );

		$this->assertSame( 'Trimmed Name', $result );
	}

	// -----------------------------------------------------------------------
	// API key appended to URL
	// -----------------------------------------------------------------------

	public function test_generate_appends_api_key_to_url(): void {
		$this->mock_encrypted_key( 'my-gemini-key' );
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
			->andReturn( $this->gemini_success_body( 'ok' ) );

		$this->make_provider()->generate( 'prompt' );

		$this->assertStringContainsString( 'key=my-gemini-key', $request_url );
	}

	// -----------------------------------------------------------------------
	// HTTP errors
	// -----------------------------------------------------------------------

	public function test_generate_throws_on_400_bad_request(): void {
		$this->mock_encrypted_key( 'gemini-api-key' );
		$this->mock_http_response( 400, '{"error":{"code":400,"message":"Invalid request","status":"INVALID_ARGUMENT"}}' );
		$this->mock_circuit_breaker_increment();

		$this->expectException( ProviderException::class );
		$this->make_provider()->generate( 'prompt' );
	}

	public function test_generate_throws_on_429_rate_limit(): void {
		$this->mock_encrypted_key( 'gemini-api-key' );

		Functions\expect( 'get_transient' )->andReturn( false );
		Functions\expect( 'wp_remote_post' )->andReturn( array() );
		Functions\expect( 'is_wp_error' )->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 429 );
		Functions\expect( 'wp_remote_retrieve_header' )
			->with( \Mockery::any(), 'retry-after' )
			->andReturn( '' );

		Functions\expect( 'set_transient' )->andReturn( true );
		Functions\expect( 'get_option' )
			->with( 'aipo_circuit_breaker_threshold', 10 )
			->andReturn( 100 );
		Functions\expect( 'do_action' )->andReturn( null );

		$this->expectException( ProviderException::class );
		$this->make_provider()->generate( 'prompt' );
	}

	public function test_generate_throws_on_wp_error(): void {
		$this->mock_encrypted_key( 'gemini-api-key' );

		Functions\expect( 'get_transient' )->andReturn( false );
		Functions\expect( 'wp_remote_post' )
			->andReturn( new \WP_Error( 'http_request_failed', 'timeout' ) );
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

	public function test_generate_throws_on_missing_candidates_key(): void {
		$this->mock_encrypted_key( 'gemini-api-key' );
		$this->mock_http_response( 200, '{"promptFeedback":{"blockReason":"SAFETY"}}' );
		$this->mock_circuit_breaker_increment();

		$this->expectException( ProviderException::class );
		$this->expectExceptionMessageMatches( '/Unexpected Gemini response/' );
		$this->make_provider()->generate( 'prompt' );
	}

	public function test_generate_throws_on_non_json_body(): void {
		$this->mock_encrypted_key( 'gemini-api-key' );
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

		$provider = new GeminiProvider( array(), $this->createMock( JobLogger::class ) );
		$provider->generate( 'prompt' );
	}

	// -----------------------------------------------------------------------
	// build_http_request options
	// -----------------------------------------------------------------------

	public function test_generate_uses_configured_model_in_url(): void {
		$this->mock_encrypted_key( 'gemini-api-key' );
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
			->andReturn( $this->gemini_success_body( 'ok' ) );

		$provider = new GeminiProvider(
			array( 'api_key_enc' => 'enc', 'model' => 'gemini-1.5-flash' ),
			$this->createMock( JobLogger::class )
		);

		$this->mock_encrypted_key( 'gemini-api-key' );
		$provider->generate( 'prompt' );

		$this->assertStringContainsString( 'gemini-1.5-flash', $request_url );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function make_provider(): GeminiProvider {
		$logger = $this->createMock( JobLogger::class );
		$logger->method( 'error' )->willReturn( null );
		$logger->method( 'warning' )->willReturn( null );
		return new GeminiProvider( array( 'api_key_enc' => 'encrypted-key' ), $logger );
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

	private function gemini_success_body( string $content ): string {
		return json_encode( array(
			'candidates' => array(
				array(
					'content' => array(
						'parts' => array(
							array( 'text' => $content ),
						),
					),
				),
			),
		) );
	}
}
