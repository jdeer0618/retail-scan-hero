<?php
/**
 * Admin notice manager.
 *
 * @package AIProductOptimizer\Admin
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Admin;

use AIProductOptimizer\Loader;

/**
 * Class AdminNotices
 */
class AdminNotices {

	public function register( Loader $loader ): void {
		$loader->add_action( 'admin_notices', $this, 'show_onboarding_notice' );
		$loader->add_action( 'admin_notices', $this, 'show_circuit_breaker_notices' );
		$loader->add_action( 'aipo_provider_suspended', $this, 'record_suspension', 10, 2 );
	}

	/**
	 * Show the onboarding prompt if wizard not yet completed.
	 *
	 * @return void
	 */
	public function show_onboarding_notice(): void {
		if ( get_option( 'aipo_onboarding_complete' ) ) {
			return;
		}

		$wizard_url = admin_url( 'admin.php?page=aipo-onboarding' );

		printf(
			'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Welcome to AI Product Optimizer!', 'ai-product-optimizer' ),
			esc_url( $wizard_url ),
			esc_html__( 'Complete the setup wizard to get started →', 'ai-product-optimizer' )
		);
	}

	/**
	 * Show notices for circuit-breaker suspended providers.
	 *
	 * @return void
	 */
	public function show_circuit_breaker_notices(): void {
		$suspended = (array) get_option( 'aipo_suspended_providers', array() );

		foreach ( $suspended as $slug => $info ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'AI Product Optimizer:', 'ai-product-optimizer' ),
				sprintf(
					/* translators: %s: provider slug */
					esc_html__( 'Provider "%s" has been temporarily suspended due to repeated failures. It will auto-resume in 5 minutes.', 'ai-product-optimizer' ),
					esc_html( $slug )
				)
			);
		}
	}

	/**
	 * Record a provider suspension when circuit breaker fires.
	 *
	 * @param string $slug  Provider slug.
	 * @param int    $count Failure count.
	 * @return void
	 */
	public function record_suspension( string $slug, int $count ): void {
		$suspended          = (array) get_option( 'aipo_suspended_providers', array() );
		$suspended[ $slug ] = array(
			'count' => $count,
			'time'  => time(),
		);
		update_option( 'aipo_suspended_providers', $suspended, false );
	}
}
