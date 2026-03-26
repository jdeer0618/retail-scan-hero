<?php
/**
 * Product editor meta box (Classic Editor).
 *
 * Full implementation: Phase 4.
 *
 * @package AIProductOptimizer\Admin
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Admin;

use AIProductOptimizer\Loader;

/**
 * Class ProductMetaBox
 */
class ProductMetaBox {

	public function register( Loader $loader ): void {
		$loader->add_action( 'add_meta_boxes', $this, 'add_meta_box' );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_assets' );
		$loader->add_action( 'enqueue_block_editor_assets', $this, 'enqueue_block_editor_assets' );
		$loader->add_action( 'save_post_product', $this, 'handle_product_save', 10, 1 );
	}

	public function add_meta_box(): void {
		add_meta_box(
			'aipo-product-meta-box',
			__( 'AI Product Optimizer', 'ai-product-optimizer' ),
			array( $this, 'render' ),
			'product',
			'side',
			'default'
		);
	}

	public function render( \WP_Post $post ): void {
		$generated_at = get_post_meta( $post->ID, '_ai_optimizer_generated_at', true );
		$provider     = get_post_meta( $post->ID, '_ai_optimizer_provider_used', true );
		$locked_name  = (bool) get_post_meta( $post->ID, '_ai_optimizer_lock_name', true );
		$excluded     = (bool) get_post_meta( $post->ID, '_ai_optimizer_excluded', true );

		wp_nonce_field( 'aipo_meta_box_' . $post->ID, 'aipo_meta_box_nonce' );

		echo '<div id="aipo-product-meta-box" '
			. 'data-product-id="' . absint( $post->ID ) . '" '
			. 'data-rest-url="' . esc_url( rest_url( 'aipo/v1' ) ) . '" '
			. 'data-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '">';

		echo '<p class="aipo-meta">';
		if ( $generated_at ) {
			/* translators: %1$s: human time diff, %2$s: provider slug */
			printf(
				esc_html__( 'Last generated %1$s ago via %2$s', 'ai-product-optimizer' ),
				esc_html( human_time_diff( strtotime( $generated_at ) ) ),
				esc_html( $provider ?: 'AI' )
			);
		} else {
			esc_html_e( 'No AI content generated yet.', 'ai-product-optimizer' );
		}
		echo '</p>';

		// Render placeholder — JS will hydrate this into the full meta box UI.
		echo '<p><em>' . esc_html__( 'Loading AI controls…', 'ai-product-optimizer' ) . '</em></p>';

		echo '<p>';
		echo '<label>';
		echo '<input type="checkbox" name="aipo_lock_name" value="1" ' . checked( $locked_name, true, false ) . '> ';
		esc_html_e( 'Lock name from batch updates', 'ai-product-optimizer' );
		echo '</label>';
		echo '</p>';

		echo '<p>';
		echo '<label>';
		echo '<input type="checkbox" name="aipo_excluded" value="1" ' . checked( $excluded, true, false ) . '> ';
		esc_html_e( 'Exclude from all batch operations', 'ai-product-optimizer' );
		echo '</label>';
		echo '</p>';

		echo '</div>';
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		global $post;
		if ( ! $post || 'product' !== $post->post_type ) {
			return;
		}

		wp_enqueue_script(
			'aipo-product-editor',
			AIPO_PLUGIN_URL . 'assets/build/product-editor.js',
			array( 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch', 'jquery' ),
			AIPO_VERSION,
			true
		);

		wp_localize_script( 'aipo-product-editor', 'aipoEditor', array(
			'restUrl'   => esc_url_raw( rest_url( 'aipo/v1' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'productId' => absint( $post->ID ),
		) );
	}

	public function enqueue_block_editor_assets(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'aipo-block-editor',
			AIPO_PLUGIN_URL . 'assets/build/block-editor.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch', 'wp-data' ),
			AIPO_VERSION,
			true
		);
	}

	/**
	 * Save meta box checkbox values on product save.
	 *
	 * @param int $post_id Product post ID.
	 * @return void
	 */
	public function handle_product_save( int $post_id ): void {
		if ( ! isset( $_POST['aipo_meta_box_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_POST['aipo_meta_box_nonce'] ), 'aipo_meta_box_' . $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, '_ai_optimizer_lock_name', isset( $_POST['aipo_lock_name'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_ai_optimizer_excluded', isset( $_POST['aipo_excluded'] ) ? '1' : '0' );
	}
}
