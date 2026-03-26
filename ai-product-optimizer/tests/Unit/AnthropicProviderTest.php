<?php
/**
 * Unit tests for AnthropicProvider.
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Exceptions\ProviderException;
use AIProductOptimizer\Providers\AnthropicProvider;
use AIProductOptimizer\Queue\JobLogger;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class AnthropicProviderTest
 */
class AnthropicProviderTest extends TestCase {

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
		$this->assertSame( 'anthropic', $this->make_provider()->get_slug() );
	}

	public function test_get_available_models_contains_claude_opus(): void {
		$models = $this->make_provider()->get_available_models();
		$this->assertArrayHasKey( 'claude-opus-4-6', $models );
	}

	public function test_get_available_models_contains_claude_sonnet(): void {
		$models = $this->make_provider()->get_available_models();
		$this->assertArrayHasKey( 'claude-sonnet-4-6', $models );
	}

	// -----------------------------------------------------------------------
	// Successful generation
	// -----------------------------------------------------------------------

	public function test_generate_returns_content_on_200(): void {
		$this->mock_encrypted_key( 'sk-ant-test' );
		$this->mock_http_response( 200, $this->anthropic_success_body( 'Great product name' ) );
		$this->mock_circuit_breaker_clear();

		$result = $this->make_provider()->generate( 'Generate a product name.' );

		$this->assertSame( 'Great product name', $result );
	}

	public function test_generate_trims_whitespace_from_content(): void {
		$this->mock_encrypted_key( 'sk-ant-test' );
		$this->mock_http_response( 200, $this->anthropic_success_body( "  Trimmed Name\n  " ) );
		$this->mock_circuit_breaker_clear();

		$result = $this->make_provider()->generate( 'prompt' );

		$this->assertSame( 'Trimmed Name', $result );
	}

	// -----------------------------------------------------------------------
	// HTTP errors
	// -----------------------------------------------------------------------

	public function test_generate_throws_on_401_unauthorized(): void {
		$this->mock_encrypted_key( 'sk-ant-invalid' );
		$this->mock_http_response( 401, '{"error":{"type":"authentication_error","message":"Invalid API key"}}' );
		$this->mock_circuit_breaker_increment();

		$this->expectException( ProviderException::class );
		$this->make_provider()->generate( 'prompt' );
	}

	public function test_generate_throws_on_429_rate_limit(): void {
		$this->mock_encrypted_key( 'sk-ant-test' );

		Functions\expect( 'get_transient' )->andReturn( false );
		Functions\expect( 'wp_remote_post' )->andReturn( array() );
		Functions\expect( 'is_wp_error' )->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 429 );
		Functions\expect( 'wp_remote_retrieve_header' )
			->with( \Mockery::any(), 'retry-after' )
			->andReturn( '60' );

		Functions\expect( 'set_transient' )->andReturn( true );
		Functions\expect( 'get_option' )
			->with( 'aipo_circuit_breaker_threshold', 10 )
			->andReturn( 100 );
		Functions\expect( 'do_action' )->andReturn( null );

		$this->expectException( ProviderException::class );
		$this->make_provider()->generate( 'prompt' );
	}

	public function test_generate_throws_on_wp_error(): void {
		$this->mock_encrypted_key( 'sk-ant-test' );

		Functions\expect( 'get_transient' )->andReturn( false );
		Functions\expect( 'wp_remote_post' )
			->andReturn( new \WP_Error( 'http_request_failed', 'cURL error' ) );
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

	public function test_generate_throws_on_missing_content_key(): void {
		$this->mock_encrypted_key( 'sk-ant-test' );
		$this->mock_http_response( 200, '{"id":"msg-xxx","type":"message"}' );
		$this->mock_circuit_breaker_increment();

		$this->expectException( ProviderException::class );
		$this->expectExceptionMessageMatches( '/Unexpected Anthropic response/' );
		$this->make_provider()->generate( 'prompt' );
	}

	public function test_generate_throws_on_non_json_body(): void {
		$this->mock_encrypted_key( 'sk-ant-test' );
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

		$provider = new AnthropicProvider( array(), $this->createMock( JobLogger::class ) );
		$provider->generate( 'prompt' );
	}

	// -----------------------------------------------------------------------
	// build_http_request options
	// -----------------------------------------------------------------------

	public function test_generate_uses_configured_model(): void {
		$this->mock_encrypted_key( 'sk-ant-test' );
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
			->andReturn( $this->anthropic_success_body( 'ok' ) );

		$provider = new AnthropicProvider(
			array( 'api_key_enc' => 'enc', 'model' => 'claude-haiku-4-5-20251001' ),
			$this->createMock( JobLogger::class )
		);

		$this->mock_encrypted_key( 'sk-ant-test' );
		$provider->generate( 'prompt' );

		$this->assertSame( 'claude-haiku-4-5-20251001', $request_body['model'] ?? null );
	}

	public function test_generate_sends_anthropic_version_header(): void {
		$this->mock_encrypted_key( 'sk-ant-test' );
		$this->mock_circuit_breaker_clear();

		$request_headers = null;
		Functions\expect( 'wp_remote_post' )
			->andReturnUsing( static function ( string $url, array $args ) use ( &$request_headers ): array {
				$request_headers = $args['headers'] ?? array();
				return array();
			} );
		Functions\expect( 'is_wp_error' )->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )
			->andReturn( $this->anthropic_success_body( 'ok' ) );

		$this->make_provider()->generate( 'prompt' );

		$this->assertArrayHasKey( 'anthropic-version', $request_headers );
		$this->assertSame( '2023-06-01', $request_headers['anthropic-version'] );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function make_provider(): AnthropicProvider {
		$logger = $this->createMock( JobLogger::class );
		$logger->method( 'error' )->willReturn( null );
		$logger->method( 'warning' )->willReturn( null );
		return new AnthropicProvider( array( 'api_key_enc' => 'encrypted-key' ), $logger );
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

	private function anthropic_success_body( string $content ): string {
		return json_encode( array(
			'content' => array(
				array( 'type' => 'text', 'text' => $content ),
			),
		) );
	}
}
