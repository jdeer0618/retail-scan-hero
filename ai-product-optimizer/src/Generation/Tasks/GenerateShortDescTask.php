<?php
/**
 * Short description generation task.
 *
 * Produces a benefit-led, plain-text short description (1–3 sentences).
 * The output is sanitized and stored in WooCommerce's post_excerpt field
 * (used as the short description) as well as our own meta key.
 *
 * @package AIProductOptimizer\Generation\Tasks
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Generation\Tasks;

/**
 * Class GenerateShortDescTask
 */
class GenerateShortDescTask extends AbstractTask {

	public function get_slug(): string {
		return 'short_desc';
	}

	public function get_meta_keys(): array {
		return array( '_ai_optimizer_short_desc' );
	}

	protected function get_generation_options(): array {
		return array( 'temperature' => 0.6 );
	}

	/**
	 * Save to our meta key AND update the WooCommerce short description
	 * (post_excerpt) so WordPress native search also indexes the text.
	 *
	 * {@inheritdoc}
	 */
	protected function save_result( int $product_id, string $content ): void {
		$sanitized = sanitize_text_field( $content );

		update_post_meta( $product_id, '_ai_optimizer_short_desc', $sanitized );

		// Sync to WC short description (post_excerpt) so the excerpt is searchable
		// by WordPress's built-in LIKE query without any plugin hooks.
		wp_update_post( array(
			'ID'           => $product_id,
			'post_excerpt' => $sanitized,
		) );
	}
}
