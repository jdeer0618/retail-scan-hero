<?php
/**
 * Admin settings page controller.
 *
 * Registers the "AI Optimizer" menu item under WooCommerce and renders
 * the tab-based settings interface.
 *
 * Full implementation: Phase 4.
 *
 * @package AIProductOptimizer\Admin
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Admin;

use AIProductOptimizer\Loader;

/**
 * Class SettingsPage
 */
class SettingsPage {

	/**
	 * Register hooks via Loader.
	 *
	 * @param Loader $loader Plugin hook loader.
	 * @return void
	 */
	public function register( Loader $loader ): void {
		$loader->add_action( 'admin_menu', $this, 'add_menu_page' );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_assets' );
	}

	/**
	 * Register the submenu page under WooCommerce.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'AI Optimizer', 'ai-product-optimizer' ),
			__( 'AI Optimizer', 'ai-product-optimizer' ),
			'manage_woocommerce',
			'aipo-settings',
			array( $this, 'render' )
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-product-optimizer' ) );
		}

		echo '<div class="wrap" id="aipo-settings-app">';
		echo '<h1>' . esc_html__( 'AI Product Optimizer', 'ai-product-optimizer' ) . '</h1>';
		echo '<p>' . esc_html__( 'Loading settings…', 'ai-product-optimizer' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Enqueue settings page assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'woocommerce_page_aipo-settings' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'aipo-settings',
			AIPO_PLUGIN_URL . 'assets/build/settings.js',
			array( 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' ),
			AIPO_VERSION,
			true
		);

		wp_enqueue_style(
			'aipo-admin',
			AIPO_PLUGIN_URL . 'assets/css/admin.css',
			array( 'wp-components' ),
			AIPO_VERSION
		);

		wp_set_script_translations( 'aipo-settings', AIPO_TEXT_DOMAIN, AIPO_PLUGIN_DIR . 'languages' );

		wp_localize_script(
			'aipo-settings',
			'aipoSettings',
			array(
				'restUrl' => esc_url_raw( rest_url( 'aipo/v1' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'version' => AIPO_VERSION,
			)
		);
	}
}
