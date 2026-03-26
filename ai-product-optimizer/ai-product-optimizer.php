<?php
/**
 * Plugin Name:       AI Product Optimizer
 * Plugin URI:        https://github.com/jdeer0618/retail-scan-hero
 * Description:       Multi-model AI engine for WooCommerce. Generates optimized product names, descriptions, SEO metadata, and populates a native WordPress search-boost field — all in the background using Action Scheduler.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      8.3
 * Author:            jdeer0618
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-product-optimizer
 * Domain Path:       /languages
 *
 * WC requires at least: 10.6.1
 * WC tested up to:      10.6.9
 *
 * @package AIProductOptimizer
 */

declare( strict_types=1 );

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin version.
define( 'AIPO_VERSION', '1.0.0' );

// Absolute path to the plugin file.
define( 'AIPO_PLUGIN_FILE', __FILE__ );

// Absolute path to the plugin directory (with trailing slash).
define( 'AIPO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Public URL to the plugin directory (with trailing slash).
define( 'AIPO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Plugin basename (e.g. "ai-product-optimizer/ai-product-optimizer.php").
define( 'AIPO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Minimum version requirements.
define( 'AIPO_MIN_PHP', '8.3' );
define( 'AIPO_MIN_WP', '6.9' );
define( 'AIPO_MIN_WC', '10.6.1' );

// Action Scheduler hook name used for queued generation jobs.
define( 'AIPO_AS_HOOK', 'aipo_generate_product' );

// Text domain constant for i18n.
define( 'AIPO_TEXT_DOMAIN', 'ai-product-optimizer' );

/**
 * Autoloader — PSR-4 via Composer, with a manual fallback during development.
 */
if ( file_exists( AIPO_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once AIPO_PLUGIN_DIR . 'vendor/autoload.php';
}

// -------------------------------------------------------------------------
// Activation / Deactivation / Uninstall hooks
// -------------------------------------------------------------------------

register_activation_hook(
	__FILE__,
	static function (): void {
		// Require Activator only when the hook fires (avoids eager class load).
		require_once AIPO_PLUGIN_DIR . 'src/Activator.php';
		\AIProductOptimizer\Activator::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		require_once AIPO_PLUGIN_DIR . 'src/Activator.php';
		\AIProductOptimizer\Activator::deactivate();
	}
);

// -------------------------------------------------------------------------
// Bootstrap on plugins_loaded so WooCommerce is already available.
// -------------------------------------------------------------------------

add_action(
	'plugins_loaded',
	static function (): void {
		// Guard: WooCommerce must be active and meet minimum version.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p>'
						. esc_html__( 'AI Product Optimizer requires WooCommerce to be installed and active.', 'ai-product-optimizer' )
						. '</p></div>';
				}
			);
			return;
		}

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, AIPO_MIN_WC, '<' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					/* translators: %s: minimum WooCommerce version required */
					$message = sprintf(
						esc_html__( 'AI Product Optimizer requires WooCommerce %s or higher.', 'ai-product-optimizer' ),
						esc_html( AIPO_MIN_WC )
					);
					echo '<div class="notice notice-error"><p>' . $message . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			);
			return;
		}

		// Declare HPOS (High-Performance Order Storage) compatibility.
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}

		// Boot the plugin.
		\AIProductOptimizer\Plugin::get_instance()->boot();
	},
	10
);
