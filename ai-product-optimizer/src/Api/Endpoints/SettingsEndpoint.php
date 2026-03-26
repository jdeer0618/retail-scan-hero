<?php
/**
 * GET  /wp-json/aipo/v1/settings
 * POST /wp-json/aipo/v1/settings
 *
 * @package AIProductOptimizer\Api\Endpoints
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Api\Endpoints;

use AIProductOptimizer\Api\RestController;
use AIProductOptimizer\Security\KeyEncryption;

/**
 * Class SettingsEndpoint
 */
class SettingsEndpoint {

	/**
	 * Option keys that are safe to expose via the GET endpoint (no sensitive data).
	 *
	 * @var string[]
	 */
	private const READABLE_KEYS = array(
		'aipo_active_provider',
		'aipo_fallback_provider',
		'aipo_task_models',
		'aipo_brand_voice',
		'aipo_default_tone',
		'aipo_custom_tone',
		'aipo_output_length',
		'aipo_custom_word_count',
		'aipo_prompt_templates',
		'aipo_name_max_chars',
		'aipo_name_variants',
		'aipo_brand_affix',
		'aipo_search_keyword_count',
		'aipo_auto_generate_on_publish',
		'aipo_auto_generate_types',
		'aipo_schedule_enabled',
		'aipo_schedule_cron',
		'aipo_schedule_offset_hours',
		'aipo_exclude_categories',
		'aipo_regenerate_after_days',
		'aipo_cache_ttl_days',
		'aipo_queue_concurrency',
		'aipo_yoast_bridge_enabled',
		'aipo_rankmath_bridge_enabled',
		'aipo_yoast_override_existing',
		'aipo_rankmath_override_existing',
		'aipo_search_boost_enabled',
		'aipo_rate_limit_per_minute',
		'aipo_log_retention_days',
		'aipo_onboarding_complete',
	);

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
	}

	public function get_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$settings = array();
		foreach ( self::READABLE_KEYS as $key ) {
			$settings[ $key ] = get_option( $key );
		}

		// Add masked API key indicators (last 4 chars only).
		$providers    = (array) get_option( 'aipo_providers', array() );
		$provider_info = array();
		foreach ( $providers as $slug => $config ) {
			$provider_info[ $slug ] = array(
				'has_key'       => ! empty( $config['api_key_enc'] ),
				'key_masked'    => empty( $config['api_key_enc'] ) ? '' : '••••' . substr( $slug, -4 ),
				'endpoint'      => $config['endpoint'] ?? '',
				'model'         => $config['model'] ?? '',
			);
		}
		$settings['providers'] = $provider_info;

		return new \WP_REST_Response( $settings, 200 );
	}

	public function update_settings( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return new \WP_Error( 'aipo_invalid_body', __( 'Invalid request body.', 'ai-product-optimizer' ), array( 'status' => 400 ) );
		}

		// Handle API key updates separately (encrypt before storing).
		if ( isset( $body['provider_key'] ) && is_array( $body['provider_key'] ) ) {
			$providers = (array) get_option( 'aipo_providers', array() );
			foreach ( $body['provider_key'] as $slug => $raw_key ) {
				$slug = sanitize_key( $slug );
				if ( ! empty( $raw_key ) ) {
					$providers[ $slug ]['api_key_enc'] = KeyEncryption::encrypt( sanitize_text_field( $raw_key ) );
				}
			}
			update_option( 'aipo_providers', $providers, false );
			unset( $body['provider_key'] );
		}

		// Handle provider endpoint/model updates.
		if ( isset( $body['provider_config'] ) && is_array( $body['provider_config'] ) ) {
			$providers = (array) get_option( 'aipo_providers', array() );
			foreach ( $body['provider_config'] as $slug => $config ) {
				$slug = sanitize_key( $slug );
				$providers[ $slug ]['endpoint'] = esc_url_raw( $config['endpoint'] ?? '' );
				$providers[ $slug ]['model']    = sanitize_text_field( $config['model'] ?? '' );
			}
			update_option( 'aipo_providers', $providers, false );
			unset( $body['provider_config'] );
		}

		// Update remaining whitelisted settings.
		$allowed = array_flip( self::READABLE_KEYS );
		foreach ( $body as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) {
				continue;
			}
			update_option( $key, $value, false );
		}

		return new \WP_REST_Response( array( 'updated' => true ), 200 );
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
