<?php
/**
 * GET  /wp-json/aipo/v1/settings
 * POST /wp-json/aipo/v1/settings
 * POST /wp-json/aipo/v1/settings/flush-cache
 *
 * Uses a flat-key contract that maps 1:1 with the React admin UI.
 * Provider API keys are encrypted via KeyEncryption before storage and
 * are never returned in responses — only a boolean `has_key` flag per provider.
 *
 * @package AIProductOptimizer\Api\Endpoints
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Api\Endpoints;

use AIProductOptimizer\Api\RestController;
use AIProductOptimizer\Cache\CacheManager;
use AIProductOptimizer\Security\KeyEncryption;

/**
 * Class SettingsEndpoint
 */
class SettingsEndpoint {

	/**
	 * Simple scalar option keys exposed directly via GET / accepted via POST.
	 *
	 * @var string[]
	 */
	private const SCALAR_KEYS = array(
		'aipo_enabled',
		'aipo_onboarding_complete',
		'aipo_active_provider',
		'aipo_fallback_provider',
		'aipo_name_max_chars',
		'aipo_search_keyword_count',
		'aipo_alt_text_auto_apply',
		'aipo_search_boost_enabled',
		'aipo_prompt_name',
		'aipo_prompt_short_desc',
		'aipo_prompt_long_desc',
		'aipo_prompt_seo_package',
		'aipo_prompt_search_keywords',
		'aipo_prompt_alt_text',
		'aipo_cron_schedule',
		'aipo_batch_size',
		'aipo_stale_threshold_days',
		'aipo_skip_unchanged',
		'aipo_auto_generate_on_publish',
		'aipo_auto_task_name',
		'aipo_auto_task_short_desc',
		'aipo_auto_task_long_desc',
		'aipo_auto_task_seo_package',
		'aipo_auto_task_search_keywords',
		'aipo_auto_task_alt_text',
		'aipo_cache_ttl',
		'aipo_rate_limit_per_minute',
		'aipo_circuit_breaker_threshold',
		'aipo_yoast_bridge_enabled',
		'aipo_yoast_override_existing',
		'aipo_rank_math_bridge_enabled',
		'aipo_rank_math_override_existing',
		'aipo_debug_logging',
		'aipo_log_retention_days',
		'aipo_delete_data_on_uninstall',
	);

	/** @var string[] */
	private const PROVIDER_SLUGS = array(
		'openai', 'anthropic', 'gemini', 'grok', 'ollama',
	);

	// -----------------------------------------------------------------------

	public function register(): void {
		register_rest_route(
			RestController::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		register_rest_route(
			RestController::NAMESPACE,
			'/settings/flush-cache',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'flush_cache' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	/**
	 * Return all settings as a flat JSON object. API keys are never returned.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings(): \WP_REST_Response {
		$response = array();

		foreach ( self::SCALAR_KEYS as $key ) {
			$response[ $key ] = get_option( $key );
		}

		// Provider info: expose per-provider has_key + config as flat keys.
		$providers_blob = (array) get_option( 'aipo_providers', array() );
		foreach ( self::PROVIDER_SLUGS as $slug ) {
			$config = $providers_blob[ $slug ] ?? array();
			$response[ "aipo_provider_{$slug}_has_key" ]  = ! empty( $config['api_key_enc'] );
			$response[ "aipo_provider_{$slug}_model" ]    = $config['model'] ?? '';
			$response[ "aipo_provider_{$slug}_endpoint" ] = $config['endpoint'] ?? '';
		}

		return new \WP_REST_Response( $response, 200 );
	}

	/**
	 * Persist settings. Handles encrypted API keys inline.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_settings( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return new \WP_Error(
				'aipo_invalid_body',
				__( 'Invalid request body.', 'ai-product-optimizer' ),
				array( 'status' => 400 )
			);
		}

		$providers_blob  = (array) get_option( 'aipo_providers', array() );
		$providers_dirty = false;
		$allowed_scalar  = array_flip( self::SCALAR_KEYS );

		foreach ( $body as $key => $value ) {
			$matched = false;

			foreach ( self::PROVIDER_SLUGS as $slug ) {
				if ( $key === "aipo_provider_{$slug}_key" ) {
					$raw = sanitize_text_field( (string) $value );
					if ( '' !== $raw ) {
						$providers_blob[ $slug ]['api_key_enc'] = KeyEncryption::encrypt( $raw );
						$providers_dirty = true;
					}
					$matched = true;
					break;
				}

				if ( $key === "aipo_provider_{$slug}_model" ) {
					$providers_blob[ $slug ]['model'] = sanitize_text_field( (string) $value );
					$providers_dirty = true;
					$matched = true;
					break;
				}

				if ( $key === "aipo_provider_{$slug}_endpoint" ) {
					$providers_blob[ $slug ]['endpoint'] = esc_url_raw( (string) $value );
					$providers_dirty = true;
					$matched = true;
					break;
				}
			}

			if ( ! $matched && isset( $allowed_scalar[ $key ] ) ) {
				update_option( $key, $value, false );
			}
		}

		if ( $providers_dirty ) {
			update_option( 'aipo_providers', $providers_blob, false );
		}

		return new \WP_REST_Response( array( 'updated' => true ), 200 );
	}

	/**
	 * Flush all AI content cache entries.
	 *
	 * @return \WP_REST_Response
	 */
	public function flush_cache(): \WP_REST_Response {
		$cache   = new CacheManager();
		$deleted = $cache->flush_all();

		return new \WP_REST_Response(
			array(
				'flushed' => true,
				'deleted' => $deleted,
			),
			200
		);
	}

	public function check_permission(): bool|\WP_Error {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error( 'aipo_forbidden', __( 'Forbidden.', 'ai-product-optimizer' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public function check_admin_permission(): bool|\WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'aipo_forbidden', __( 'Forbidden.', 'ai-product-optimizer' ), array( 'status' => 403 ) );
		}
		return true;
	}
}
