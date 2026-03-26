<?php
/**
 * GET  /wp-json/aipo/v1/providers
 * POST /wp-json/aipo/v1/providers/{slug}/test
 * GET  /wp-json/aipo/v1/providers/{slug}/models
 *
 * @package AIProductOptimizer\Api\Endpoints
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Api\Endpoints;

use AIProductOptimizer\Api\RestController;
use AIProductOptimizer\Providers\ProviderFactory;

/**
 * Class ProvidersEndpoint
 */
class ProvidersEndpoint {

	public function register(): void {
		register_rest_route(
			RestController::NAMESPACE,
			'/providers',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_providers' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			RestController::NAMESPACE,
			'/providers/(?P<slug>[a-z0-9_-]+)/test',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_provider' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			RestController::NAMESPACE,
			'/providers/(?P<slug>[a-z0-9_-]+)/models',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_models' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function list_providers( \WP_REST_Request $request ): \WP_REST_Response {
		$slugs           = array( 'openai', 'anthropic', 'gemini', 'grok', 'ollama' );
		$all_configs     = (array) get_option( 'aipo_providers', array() );
		$active_provider = (string) get_option( 'aipo_active_provider', 'openai' );
		$providers       = array();

		foreach ( $slugs as $slug ) {
			try {
				$provider    = ProviderFactory::make( $slug );
				$config      = $all_configs[ $slug ] ?? array();
				$has_key     = ! empty( $config['api_key_enc'] ) || 'ollama' === $slug;
				$providers[] = array(
					'slug'         => $slug,
					'name'         => $provider->get_display_name(),
					'is_active'    => $active_provider === $slug,
					'is_configured' => $has_key,
				);
			} catch ( \Throwable $e ) {
				// Skip providers whose class is missing (shouldn't happen).
			}
		}

		return new \WP_REST_Response( $providers, 200 );
	}

	public function test_provider( \WP_REST_Request $request ): \WP_REST_Response {
		$slug = sanitize_key( $request->get_param( 'slug' ) );

		try {
			$provider = ProviderFactory::make( $slug );
			$success  = $provider->test_connection();
		} catch ( \Throwable $e ) {
			$success = false;
		}

		return new \WP_REST_Response( array( 'success' => $success ), 200 );
	}

	public function get_models( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$slug = sanitize_key( $request->get_param( 'slug' ) );

		try {
			$provider = ProviderFactory::make( $slug );
			$models   = $provider->get_available_models();
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'aipo_provider_error', $e->getMessage(), array( 'status' => 400 ) );
		}

		return new \WP_REST_Response( $models, 200 );
	}

	public function check_permission(): bool|\WP_Error {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error( 'aipo_forbidden', __( 'Forbidden.', 'ai-product-optimizer' ), array( 'status' => 403 ) );
		}
		return true;
	}
}
