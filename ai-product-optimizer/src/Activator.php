<?php
/**
 * Plugin activation and deactivation handler.
 *
 * Performs version checks, creates the custom DB table, seeds default
 * options, and schedules the Action Scheduler cron group.
 *
 * @package AIProductOptimizer
 */

declare( strict_types=1 );

namespace AIProductOptimizer;

/**
 * Class Activator
 */
class Activator {

	/**
	 * Run on plugin activation.
	 *
	 * Aborts with wp_die() if minimum requirements are not met so the
	 * plugin is never activated in an incompatible environment.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::check_requirements();
		self::create_tables();
		self::seed_options();
		self::schedule_cron();
		flush_rewrite_rules();
	}

	/**
	 * Run on plugin deactivation.
	 *
	 * Clears scheduled cron events but leaves all data intact.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		self::unschedule_cron();
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Verify PHP, WordPress, WooCommerce, and PHP extension requirements.
	 *
	 * @return void
	 */
	private static function check_requirements(): void {
		$errors = array();

		if ( version_compare( PHP_VERSION, AIPO_MIN_PHP, '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: required PHP version, 2: current PHP version */
				__( 'AI Product Optimizer requires PHP %1$s or higher. You are running PHP %2$s.', 'ai-product-optimizer' ),
				AIPO_MIN_PHP,
				PHP_VERSION
			);
		}

		global $wp_version;
		if ( isset( $wp_version ) && version_compare( $wp_version, AIPO_MIN_WP, '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: required WP version, 2: current WP version */
				__( 'AI Product Optimizer requires WordPress %1$s or higher. You are running WordPress %2$s.', 'ai-product-optimizer' ),
				AIPO_MIN_WP,
				$wp_version
			);
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			$errors[] = __( 'AI Product Optimizer requires WooCommerce to be installed and active.', 'ai-product-optimizer' );
		} elseif ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, AIPO_MIN_WC, '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: required WC version, 2: current WC version */
				__( 'AI Product Optimizer requires WooCommerce %1$s or higher. You are running WooCommerce %2$s.', 'ai-product-optimizer' ),
				AIPO_MIN_WC,
				WC_VERSION
			);
		}

		foreach ( array( 'curl', 'json', 'openssl', 'mbstring' ) as $ext ) {
			if ( ! extension_loaded( $ext ) ) {
				$errors[] = sprintf(
					/* translators: %s: PHP extension name */
					__( 'AI Product Optimizer requires the PHP "%s" extension.', 'ai-product-optimizer' ),
					$ext
				);
			}
		}

		if ( ! empty( $errors ) ) {
			// Prevent activation and show all errors.
			deactivate_plugins( AIPO_PLUGIN_BASENAME );
			wp_die(
				'<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $errors ) ) . '</li></ul>',
				esc_html__( 'AI Product Optimizer — Activation Error', 'ai-product-optimizer' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Create the custom job log database table using dbDelta().
	 *
	 * @return void
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'aipo_job_log';

		$sql = "CREATE TABLE {$table_name} (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			batch_id      VARCHAR(36)     NOT NULL,
			product_id    BIGINT UNSIGNED NOT NULL,
			task_slug     VARCHAR(64)     NOT NULL,
			status        ENUM('queued','running','completed','failed','cancelled','skipped') NOT NULL DEFAULT 'queued',
			provider      VARCHAR(32)     NULL,
			model         VARCHAR(64)     NULL,
			tokens_used   INT UNSIGNED    NULL,
			error_message TEXT            NULL,
			created_at    DATETIME        NOT NULL,
			updated_at    DATETIME        NOT NULL,
			PRIMARY KEY (id),
			INDEX idx_batch_id   (batch_id),
			INDEX idx_product_id (product_id),
			INDEX idx_status     (status),
			INDEX idx_created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Seed default plugin options on first activation.
	 * Uses add_option() so existing values are never overwritten.
	 *
	 * @return void
	 */
	private static function seed_options(): void {
		$defaults = array(
			'aipo_version'                    => AIPO_VERSION,
			'aipo_onboarding_complete'        => false,
			'aipo_active_provider'            => 'openai',
			'aipo_fallback_provider'          => 'ollama',
			'aipo_providers'                  => array(),
			'aipo_task_models'                => array(),
			'aipo_brand_voice'                => '',
			'aipo_default_tone'               => 'professional',
			'aipo_custom_tone'                => '',
			'aipo_output_length'              => 'medium',
			'aipo_custom_word_count'          => 300,
			'aipo_prompt_templates'           => array(),
			'aipo_name_max_chars'             => 70,
			'aipo_name_variants'              => 3,
			'aipo_brand_affix'                => '',
			'aipo_search_keyword_count'       => 20,
			'aipo_auto_generate_on_publish'   => false,
			'aipo_auto_generate_types'        => array( 'simple', 'variable' ),
			'aipo_schedule_enabled'           => false,
			'aipo_schedule_cron'              => 'daily',
			'aipo_schedule_offset_hours'      => 2,
			'aipo_exclude_categories'         => array(),
			'aipo_regenerate_after_days'      => 0,
			'aipo_cache_ttl_days'             => 7,
			'aipo_queue_concurrency'          => 3,
			'aipo_yoast_bridge_enabled'       => true,
			'aipo_rankmath_bridge_enabled'    => true,
			'aipo_yoast_override_existing'    => false,
			'aipo_rankmath_override_existing' => false,
			'aipo_search_boost_enabled'       => true,
			'aipo_rate_limit_per_minute'      => 60,
			'aipo_circuit_breaker_threshold'  => 10,
			'aipo_log_retention_days'         => 30,
			'aipo_delete_data_on_uninstall'   => false,
		);

		foreach ( $defaults as $key => $value ) {
			add_option( $key, $value, '', false );
		}
	}

	/**
	 * Schedule the Action Scheduler recurring batch group.
	 *
	 * @return void
	 */
	private static function schedule_cron(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		if ( ! as_has_scheduled_action( 'aipo_scheduled_batch', array(), 'aipo' ) ) {
			as_schedule_recurring_action(
				strtotime( 'tomorrow 02:00:00' ),
				DAY_IN_SECONDS,
				'aipo_scheduled_batch',
				array(),
				'aipo',
				true
			);
		}
	}

	/**
	 * Unschedule the Action Scheduler recurring batch group on deactivation.
	 *
	 * @return void
	 */
	private static function unschedule_cron(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'aipo_scheduled_batch', array(), 'aipo' );
		}
	}
}
