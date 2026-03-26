<?php
/**
 * WooCommerce Products list bulk action handler.
 *
 * Full implementation: Phase 4.
 *
 * @package AIProductOptimizer\Admin
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Admin;

use AIProductOptimizer\Loader;
use AIProductOptimizer\Queue\QueueManager;

/**
 * Class BulkActions
 */
class BulkActions {

	public function register( Loader $loader ): void {
		$loader->add_filter( 'bulk_actions-edit-product', $this, 'register_bulk_actions' );
		$loader->add_filter( 'handle_bulk_actions-edit-product', $this, 'handle_bulk_action', 10, 3 );
		$loader->add_action( 'admin_notices', $this, 'show_bulk_result_notice' );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_assets' );
	}

	/**
	 * Add "Generate AI Content" to the bulk actions dropdown.
	 *
	 * @param array<string, string> $actions Existing bulk actions.
	 * @return array<string, string>
	 */
	public function register_bulk_actions( array $actions ): array {
		$actions['aipo_generate_full']    = __( 'AI: Generate Full Package', 'ai-product-optimizer' );
		$actions['aipo_generate_seo']     = __( 'AI: Generate SEO Package Only', 'ai-product-optimizer' );
		$actions['aipo_generate_desc']    = __( 'AI: Generate Descriptions Only', 'ai-product-optimizer' );
		$actions['aipo_generate_name']    = __( 'AI: Generate Names Only', 'ai-product-optimizer' );
		return $actions;
	}

	/**
	 * Handle a submitted bulk action.
	 *
	 * @param string   $redirect_url The redirect URL.
	 * @param string   $action       The action key.
	 * @param int[]    $post_ids     Selected product IDs.
	 * @return string                Updated redirect URL.
	 */
	public function handle_bulk_action( string $redirect_url, string $action, array $post_ids ): string {
		$task_map = array(
			'aipo_generate_full' => array( 'name', 'short_desc', 'long_desc', 'seo_package', 'search_keywords' ),
			'aipo_generate_seo'  => array( 'seo_package', 'search_keywords' ),
			'aipo_generate_desc' => array( 'short_desc', 'long_desc' ),
			'aipo_generate_name' => array( 'name' ),
		);

		if ( ! isset( $task_map[ $action ] ) ) {
			return $redirect_url;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $redirect_url;
		}

		$queue    = new QueueManager();
		$batch_id = $queue->enqueue_batch( array_map( 'absint', $post_ids ), $task_map[ $action ] );

		return add_query_arg(
			array(
				'aipo_batch_id'    => rawurlencode( $batch_id ),
				'aipo_bulk_queued' => count( $post_ids ),
			),
			$redirect_url
		);
	}

	/**
	 * Display an admin notice after a bulk action is submitted.
	 *
	 * @return void
	 */
	public function show_bulk_result_notice(): void {
		if ( empty( $_GET['aipo_bulk_queued'] ) ) {
			return;
		}

		$count    = absint( $_GET['aipo_bulk_queued'] );
		$batch_id = sanitize_text_field( wp_unslash( $_GET['aipo_batch_id'] ?? '' ) );

		printf(
			'<div class="notice notice-info is-dismissible" id="aipo-bulk-notice" data-batch-id="%s"><p>%s</p></div>',
			esc_attr( $batch_id ),
			sprintf(
				/* translators: %d: number of products queued */
				esc_html( _n(
					'AI generation queued for %d product. Generating in the background.',
					'AI generation queued for %d products. Generating in the background.',
					$count,
					'ai-product-optimizer'
				) ),
				absint( $count )
			)
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'edit.php' !== $hook ) {
			return;
		}

		if ( ! isset( $_GET['post_type'] ) || 'product' !== $_GET['post_type'] ) {
			return;
		}

		wp_enqueue_script(
			'aipo-bulk-progress',
			AIPO_PLUGIN_URL . 'assets/build/bulk-progress.js',
			array( 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' ),
			AIPO_VERSION,
			true
		);

		wp_localize_script( 'aipo-bulk-progress', 'aipoBulk', array(
			'restUrl' => esc_url_raw( rest_url( 'aipo/v1' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		) );
	}
}
