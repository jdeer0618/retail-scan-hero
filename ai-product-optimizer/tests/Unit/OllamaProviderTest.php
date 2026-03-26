<?php
/**
 * Unit tests for OllamaProvider.
 *
 * Covers:
 * - SSRF protection (localhost allowed, public IPs blocked, RFC-1918 allowed)
 * - Dynamic model discovery (success + fallback)
 * - HTTP response parsing
 * - API-key-free operation
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Exceptions\ProviderException;
use AIProductOptimizer\Providers\OllamaProvider;
use AIProductOptimizer\Queue\JobLogger;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class OllamaProviderTest
 */
class OllamaProviderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// SSRF tests
	// -----------------------------------------------------------------------

	/** @dataProvider allowed_endpoints_provider */
	public function test_allowed_endpoints_do_not_throw( string $endpoint ): void {
		$provider = $this->make_provider( array( 'endpoint' => $endpoint ) );

		// build_http_request is called internally; we just verify no exception is thrown
		// by calling get_available_models which hits the endpoint internally.
		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( array( 'response' => array( 'code' => 200 ), 'body' => '{"models":[]}' ) );

		Functions\expect( 'is_wp_error' )->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->andReturn( '{"models":[]}' );

		// Should NOT throw.
		$models = $provider->get_available_models();
		$this->assertIsArray( $models );
	}

	/** @return array<array{string}> */
	public static function allowed_endpoints_provider(): array {
		return array(
			array( 'http://localhost:11434' ),
			array( 'http://127.0.0.1:11434' ),
			array( 'http://10.0.0.5:11434' ),
			array( 'http://10.255.255.255:11434' ),
			array( 'http://172.16.0.1:11434' ),
			array( 'http://172.31.255.254:11434' ),
			array( 'http://192.168.1.50:11434' ),
			array( 'http://192.168.254.254:11434' ),
		);
	}

	/** @dataProvider blocked_endpoints_provider */
	public function test_blocked_endpoints_throw_provider_exception( string $endpoint ): void {
		$provider = $this->make_provider( array( 'endpoint' => $endpoint ) );

		// apply_filters must return false for the custom-host filter.
		Functions\expect( 'apply_filters' )
			->with( 'aipo_ollama_allowed_host', false, \Mockery::type( 'string' ) )
			->andReturn( false );

		$this->expectException( ProviderException::class );
		$this->expectExceptionMessageMatches( '/private network/' );

		// generate() will call build_http_request() which calls get_endpoint().
		// We trigger it via test_connection() to hit the path.
		Functions\expect( 'wp_remote_get' )->never();

		// Force the path that calls get_endpoint() — use reflection.
		$reflection = new \ReflectionMethod( $provider, 'get_endpoint' );
		$reflection->setAccessible( true );
		$reflection->invoke( $provider );
	}

	/** @return array<array{string}> */
	public static function blocked_endpoints_provider(): array {
		return array(
			array( 'http://1.2.3.4:11434' ),
			array( 'http://8.8.8.8:11434' ),
			array( 'http://93.184.216.34' ),       // example.com IP.
			array( 'http://169.254.169.254:11434' ),// AWS metadata endpoint.
		);
	}

	// -----------------------------------------------------------------------
	// Model discovery
	// -----------------------------------------------------------------------

	public function test_get_available_models_returns_discovered_models(): void {
		$provider = $this->make_provider( array( 'endpoint' => 'http://localhost:11434' ) );

		$body = json_encode( array(
			'models' => array(
				array( 'name' => 'llama3.2' ),
				array( 'name' => 'mistral' ),
				array( 'name' => 'phi3' ),
			),
		) );

		Functions\expect( 'wp_remote_get' )->once()->andReturn( array() );
		Functions\expect( 'is_wp_error' )->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->andReturn( $body );

		$models = $provider->get_available_models();

		$this->assertArrayHasKey( 'llama3.2', $models );
		$this->assertArrayHasKey( 'mistral', $models );
		$this->assertArrayHasKey( 'phi3', $models );
		$this->assertCount( 3, $models );
	}

	public function test_get_available_models_returns_default_on_connection_failure(): void {
		$provider = $this->make_provider( array( 'endpoint' => 'http://localhost:11434' ) );

		Functions\expect( 'wp_remote_get' )->once()->andReturn( new \WP_Error( 'http_error', 'Connection refused' ) );
		Functions\expect( 'is_wp_error' )->andReturn( true );

		$models = $provider->get_available_models();

		$this->assertArrayHasKey( 'llama3.2', $models );
	}

	public function test_get_available_models_returns_default_on_non_200_response(): void {
		$provider = $this->make_provider( array( 'endpoint' => 'http://localhost:11434' ) );

		Functions\expect( 'wp_remote_get' )->once()->andReturn( array() );
		Functions\expect( 'is_wp_error' )->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 503 );

		$models = $provider->get_available_models();

		$this->assertArrayHasKey( 'llama3.2', $models );
	}

	// -----------------------------------------------------------------------
	// test_connection
	// -----------------------------------------------------------------------

	public function test_test_connection_returns_true_on_200(): void {
		$provider = $this->make_provider( array( 'endpoint' => 'http://localhost:11434' ) );

		Functions\expect( 'wp_remote_get' )->once()->andReturn( array() );
		Functions\expect( 'is_wp_error' )->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 200 );

		$this->assertTrue( $provider->test_connection() );
	}

	public function test_test_connection_returns_false_on_wp_error(): void {
		$provider = $this->make_provider( array( 'endpoint' => 'http://localhost:11434' ) );

		Functions\expect( 'wp_remote_get' )->once()->andReturn( new \WP_Error( 'timeout', 'Connection timed out' ) );
		Functions\expect( 'is_wp_error' )->andReturn( true );

		$this->assertFalse( $provider->test_connection() );
	}

	// -----------------------------------------------------------------------
	// Response parsing
	// -----------------------------------------------------------------------

	public function test_parse_response_returns_trimmed_content(): void {
		$provider   = $this->make_provider( array( 'endpoint' => 'http://localhost:11434' ) );
		$reflection = new \ReflectionMethod( $provider, 'parse_response' );
		$reflection->setAccessible( true );

		$result = $reflection->invoke( $provider, array( 'response' => "  Hello world  \n" ) );

		$this->assertSame( 'Hello world', $result );
	}

	public function test_parse_response_throws_on_missing_response_key(): void {
		$provider   = $this->make_provider( array( 'endpoint' => 'http://localhost:11434' ) );
		$reflection = new \ReflectionMethod( $provider, 'parse_response' );
		$reflection->setAccessible( true );

		$this->expectException( ProviderException::class );
		$reflection->invoke( $provider, array( 'choices' => array() ) ); // Wrong key.
	}

	// -----------------------------------------------------------------------
	// Slug / display name
	// -----------------------------------------------------------------------

	public function test_get_slug_returns_ollama(): void {
		$provider = $this->make_provider( array() );
		$this->assertSame( 'ollama', $provider->get_slug() );
	}

	public function test_get_display_name_is_non_empty(): void {
		$provider = $this->make_provider( array() );
		$this->assertNotEmpty( $provider->get_display_name() );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * @param array<string, mixed> $config
	 */
	private function make_provider( array $config ): OllamaProvider {
		$logger = $this->createMock( JobLogger::class );
		return new OllamaProvider( $config, $logger );
	}
}
