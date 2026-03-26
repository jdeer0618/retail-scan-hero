<?php
/**
 * Unit tests for SettingsEndpoint.
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Api\Endpoints\SettingsEndpoint;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class SettingsEndpointTest
 */
class SettingsEndpointTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// GET /settings
	// -----------------------------------------------------------------------

	public function test_get_settings_returns_scalar_options(): void {
		Functions\expect( 'get_option' )->andReturnUsing( function ( string $key, $default = false ) {
			$map = array(
				'aipo_enabled'         => true,
				'aipo_active_provider' => 'openai',
				'aipo_cache_ttl'       => 86400,
				'aipo_providers'       => array(),
			);
			return $map[ $key ] ?? $default;
		} );

		Functions\expect( 'register_rest_route' )->andReturn( true );

		$endpoint = new SettingsEndpoint();
		$response = $endpoint->get_settings();

		$data = $response->get_data();

		$this->assertTrue( $data['aipo_enabled'] );
		$this->assertSame( 'openai', $data['aipo_active_provider'] );
		$this->assertSame( 86400, $data['aipo_cache_ttl'] );
	}

	public function test_get_settings_exposes_provider_has_key_flag(): void {
		Functions\expect( 'get_option' )->andReturnUsing( function ( string $key ) {
			if ( 'aipo_providers' === $key ) {
				return array(
					'openai'    => array( 'api_key_enc' => 'encrypted-value', 'model' => 'gpt-4o' ),
					'anthropic' => array(),
				);
			}
			return false;
		} );

		$endpoint = new SettingsEndpoint();
		$data     = $endpoint->get_settings()->get_data();

		$this->assertTrue( $data['aipo_provider_openai_has_key'] );
		$this->assertFalse( $data['aipo_provider_anthropic_has_key'] );
		$this->assertSame( 'gpt-4o', $data['aipo_provider_openai_model'] );
	}

	public function test_get_settings_never_returns_raw_api_key(): void {
		Functions\expect( 'get_option' )->andReturnUsing( function ( string $key ) {
			if ( 'aipo_providers' === $key ) {
				return array(
					'openai' => array( 'api_key_enc' => 'super-secret' ),
				);
			}
			return false;
		} );

		$endpoint = new SettingsEndpoint();
		$data     = $endpoint->get_settings()->get_data();

		$encoded = json_encode( $data );
		$this->assertStringNotContainsString( 'super-secret', $encoded );
		$this->assertStringNotContainsString( 'api_key_enc', $encoded );
	}

	// -----------------------------------------------------------------------
	// POST /settings — scalar keys
	// -----------------------------------------------------------------------

	public function test_update_settings_saves_scalar_key(): void {
		Functions\expect( 'get_option' )->andReturn( array() );
		Functions\expect( 'update_option' )
			->once()
			->with( 'aipo_active_provider', 'anthropic', false );

		$request = $this->mock_request( array( 'aipo_active_provider' => 'anthropic' ) );
		$endpoint = new SettingsEndpoint();
		$response = $endpoint->update_settings( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['updated'] );
	}

	public function test_update_settings_ignores_unknown_key(): void {
		Functions\expect( 'get_option' )->andReturn( array() );
		Functions\expect( 'update_option' )->never();

		$request = $this->mock_request( array( 'evil_key' => 'hack' ) );
		$endpoint = new SettingsEndpoint();
		$endpoint->update_settings( $request );
	}

	public function test_update_settings_returns_400_on_invalid_body(): void {
		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_json_params' )->andReturn( null );

		Functions\expect( 'update_option' )->never();

		$endpoint = new SettingsEndpoint();
		$result   = $endpoint->update_settings( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'aipo_invalid_body', $result->get_error_code() );
	}

	// -----------------------------------------------------------------------
	// POST /settings — provider API key encryption
	// -----------------------------------------------------------------------

	public function test_update_settings_encrypts_provider_api_key(): void {
		Functions\expect( 'get_option' )->with( 'aipo_providers' )->andReturn( array() );
		Functions\expect( 'update_option' )
			->once()
			->with( 'aipo_providers', \Mockery::type( 'array' ), false );

		Functions\expect( 'openssl_cipher_iv_length' )->andReturn( 16 );
		Functions\expect( 'hash' )->andReturnUsing( 'hash' );
		Functions\expect( 'openssl_encrypt' )->andReturn( 'encryptedvalue' );
		Functions\expect( 'base64_encode' )->andReturnUsing( 'base64_encode' );

		$request = $this->mock_request( array( 'aipo_provider_openai_key' => 'sk-test-key' ) );
		$endpoint = new SettingsEndpoint();
		$endpoint->update_settings( $request );
	}

	public function test_update_settings_stores_provider_model(): void {
		$stored = array();
		Functions\expect( 'get_option' )->with( 'aipo_providers' )->andReturn( array() );
		Functions\expect( 'update_option' )
			->once()
			->andReturnUsing( function ( $key, $value ) use ( &$stored ) {
				$stored = $value;
				return true;
			} );

		$request = $this->mock_request( array( 'aipo_provider_anthropic_model' => 'claude-opus-4-6' ) );
		$endpoint = new SettingsEndpoint();
		$endpoint->update_settings( $request );

		$this->assertSame( 'claude-opus-4-6', $stored['anthropic']['model'] ?? null );
	}

	public function test_update_settings_blank_key_not_stored(): void {
		Functions\expect( 'get_option' )->with( 'aipo_providers' )->andReturn( array() );
		// Should NOT call update_option for providers since key is blank.
		Functions\expect( 'update_option' )
			->with( 'aipo_providers', \Mockery::any(), false )
			->never();

		$request = $this->mock_request( array( 'aipo_provider_openai_key' => '' ) );
		$endpoint = new SettingsEndpoint();
		$endpoint->update_settings( $request );
	}

	// -----------------------------------------------------------------------
	// Permissions
	// -----------------------------------------------------------------------

	public function test_check_permission_returns_wp_error_when_unauthorized(): void {
		Functions\expect( 'current_user_can' )->with( 'manage_woocommerce' )->andReturn( false );

		$endpoint = new SettingsEndpoint();
		$result   = $endpoint->check_permission();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	public function test_check_permission_returns_true_when_authorized(): void {
		Functions\expect( 'current_user_can' )->with( 'manage_woocommerce' )->andReturn( true );

		$endpoint = new SettingsEndpoint();
		$this->assertTrue( $endpoint->check_permission() );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function mock_request( array $params ): \WP_REST_Request {
		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_json_params' )->andReturn( $params );
		return $request;
	}
}
