<?php
/**
 * Product name generation task.
 *
 * Generates 1–3 SEO-optimised product name variants.
 * The AI returns variants one per line; we store all variants as a JSON array
 * in _ai_optimizer_name_variants and the top-ranked single name in
 * _ai_optimizer_name (ready for one-click "Apply").
 *
 * @package AIProductOptimizer\Generation\Tasks
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Generation\Tasks;

/**
 * Class GenerateNameTask
 */
class GenerateNameTask extends AbstractTask {

	public function get_slug(): string {
		return 'name';
	}

	public function get_meta_keys(): array {
		return array( '_ai_optimizer_name', '_ai_optimizer_name_variants' );
	}

	/**
	 * Low temperature for names — we want precise, not creative output.
	 *
	 * {@inheritdoc}
	 */
	protected function get_generation_options(): array {
		return array( 'temperature' => 0.4 );
	}

	/**
	 * Parse variants from the newline-delimited response and store them.
	 *
	 * {@inheritdoc}
	 */
	protected function save_result( int $product_id, string $content ): void {
		// Split by newline; strip numbering ("1. ", "- "), trim whitespace.
		$lines    = array_filter(
			array_map(
				static fn( string $l ) => trim( preg_replace( '/^[\d\.\-\*]+\s*/', '', $l ) ),
				explode( "\n", $content )
			)
		);
		$variants = array_values( $lines );

		if ( empty( $variants ) ) {
			return;
		}

		$max_chars = (int) get_option( 'aipo_name_max_chars', 70 );

		// Enforce character limit on each variant.
		$variants = array_map(
			static fn( string $v ) => substr( $v, 0, $max_chars ),
			$variants
		);

		// Top-ranked name is the first variant.
		$primary = $variants[0];

		update_post_meta( $product_id, '_ai_optimizer_name', sanitize_text_field( $primary ) );
		update_post_meta( $product_id, '_ai_optimizer_name_variants', wp_json_encode( $variants ) );
	}
}
