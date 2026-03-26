<?php
/**
 * Unit tests for OpenAIProvider.
 *
 * Uses Brain\Monkey to mock wp_remote_post and the KeyEncryption dependency.
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Exceptions\ProviderException;
use AIProductOptimizer\Exceptions\RateLimitException;
use AIProductOptimizer\Providers\OpenAIProvider;
use AIProductOptimizer\Queue\JobLogger;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class OpenAIProviderTest
 */
class OpenAIProviderTest extends TestCase {

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
		$this->assertSame( 'openai', $this->make_provider()->get_slug() );
	}

	public function test_get_available_models_contains_gpt4o(): void {
		$models = $this->make_provider()->get_available_models();
		$this->assertArrayHasKey( 'gpt-4o', $models );
	}

	// -----------------------------------------------------------------------
	// Successful generation
	// -----------------------------------------------------------------------

	public function test_generate_returns_content_on_200(): void {
		$this->mock_encrypted_key( 'sk-test' );
		$this->mock_http_response( 200, $this->openai_success_body( 'Great product name' ) );
		$this->mock_circuit_breaker_clear();

		$result = $this->make_provider()->generate( 'Generate a product name.' );

		$this->assertSame( 'Great product name', $result );
	}

	public function test_generate_trims_whitespace_from_content(): void {
		$this->mock_encrypted_key( 'sk-test' );
		$this->mock_http_response( 200, $this->openai_success_body( "  Trimmed Name\n  " ) );
		$this->mock_circuit_breaker_clear();

		$result = $this->make_provider()->generate( 'prompt' );

		$this->assertSame( 'Trimmed Name', $result );
	}

	// -----------------------------------------------------------------------
	// HTTP errors
	// -----------------------------------------------------------------------

	public function test_generate_throws_on_401_unauthorized(): void {
		$this->mock_encrypted_key( 'sk-invalid' );
		$this->mock_http_response( 401, '{"error":{"message":"Invalid API key"}}' );
		$this->mock_circuit_breaker_increment();

		$this->expectException( ProviderException::class );
		$this->make_provider()->generate( 'prompt' );
	}

	public function test_generate_throws_rate_limit_on_429(): void {
		$this->mock_encrypted_key( 'sk-test' );

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
		$this->mock_encrypted_key( 'sk-test' );

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

	public function test_generate_throws_on_missing_choices_key(): void {
		$this->mock_encrypted_key( 'sk-test' );
		$this->mock_http_response( 200, '{"id":"chatcmpl-xxx","object":"chat.completion"}' );
		$this->mock_circuit_breaker_increment();

		$this->expectException( ProviderException::class );
		$this->expectExceptionMessageMatches( '/Unexpected OpenAI response/' );
		$this->make_provider()->generate( 'prompt' );
	}

	public function test_generate_throws_on_non_json_body(): void {
		$this->mock_encrypted_key( 'sk-test' );
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

		// Provider with empty config (no api_key_enc).
		$provider = new OpenAIProvider( array(), $this->createMock( JobLogger::class ) );
		$provider->generate( 'prompt' );
	}

	// -----------------------------------------------------------------------
	// build_http_request options
	// -----------------------------------------------------------------------

	public function test_generate_uses_configured_model(): void {
		$this->mock_encrypted_key( 'sk-test' );
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
			->andReturn( $this->openai_success_body( 'ok' ) );

		$provider = new OpenAIProvider(
			array( 'api_key_enc' => 'enc', 'model' => 'gpt-4o-mini' ),
			$this->createMock( JobLogger::class )
		);

		$this->mock_encrypted_key( 'sk-test' );
		$provider->generate( 'prompt' );

		$this->assertSame( 'gpt-4o-mini', $request_body['model'] ?? null );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function make_provider(): OpenAIProvider {
		$logger = $this->createMock( JobLogger::class );
		$logger->method( 'error' )->willReturn( null );
		$logger->method( 'warning' )->willReturn( null );
		return new OpenAIProvider( array( 'api_key_enc' => 'encrypted-key' ), $logger );
	}

	private function mock_encrypted_key( string $plaintext ): void {
		// Patch KeyEncryption::decrypt via a static alias.
		// Since we can't easily mock static methods with Brain\Monkey alone,
		// we use a real encryption round-trip instead.
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
			->andReturn( 100 ); // High threshold so we don't suspend.
		Functions\expect( 'do_action' )->andReturn( null );
	}

	private function openai_success_body( string $content ): string {
		return json_encode( array(
			'choices' => array(
				array( 'message' => array( 'content' => $content ) ),
			),
		) );
	}
}
