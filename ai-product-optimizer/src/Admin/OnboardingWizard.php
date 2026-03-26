<?php
/**
 * First-run onboarding wizard.
 *
 * Full implementation: Phase 4.
 *
 * @package AIProductOptimizer\Admin
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Admin;

use AIProductOptimizer\Loader;

/**
 * Class OnboardingWizard
 */
class OnboardingWizard {

	public function register( Loader $loader ): void {
		$loader->add_action( 'admin_menu', $this, 'add_wizard_page' );
		$loader->add_action( 'admin_init', $this, 'maybe_redirect_to_wizard' );
	}

	public function add_wizard_page(): void {
		add_submenu_page(
			null, // Hidden — not shown in menu.
			__( 'AI Optimizer Setup', 'ai-product-optimizer' ),
			__( 'AI Optimizer Setup', 'ai-product-optimizer' ),
			'manage_woocommerce',
			'aipo-onboarding',
			array( $this, 'render' )
		);
	}

	/**
	 * Redirect to the wizard on first activation.
	 *
	 * @return void
	 */
	public function maybe_redirect_to_wizard(): void {
		if ( get_option( 'aipo_onboarding_complete' ) ) {
			return;
		}

		if ( ! get_transient( 'aipo_activation_redirect' ) ) {
			return;
		}

		delete_transient( 'aipo_activation_redirect' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=aipo-onboarding' ) );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-product-optimizer' ) );
		}

		echo '<div class="wrap" id="aipo-onboarding-app">';
		echo '<h1>' . esc_html__( 'Welcome to AI Product Optimizer', 'ai-product-optimizer' ) . '</h1>';
		echo '<p>' . esc_html__( 'Loading setup wizard…', 'ai-product-optimizer' ) . '</p>';
		echo '</div>';

		wp_enqueue_script(
			'aipo-settings',
			AIPO_PLUGIN_URL . 'assets/build/settings.js',
			array( 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' ),
			AIPO_VERSION,
			true
		);

		wp_localize_script( 'aipo-settings', 'aipoSettings', array(
			'restUrl'        => esc_url_raw( rest_url( 'aipo/v1' ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'isOnboarding'   => true,
		) );
	}
}
