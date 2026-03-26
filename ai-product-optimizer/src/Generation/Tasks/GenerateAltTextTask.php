<?php
/**
 * Image alt text generation task.
 *
 * Generates descriptive alt text for each product gallery image.
 * The prompt returns a JSON array of strings (one per image URL, in order).
 * Results are stored in _ai_optimizer_alt_texts as a JSON object
 * keyed by WordPress attachment ID.
 *
 * @package AIProductOptimizer\Generation\Tasks
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Generation\Tasks;

/**
 * Class GenerateAltTextTask
 */
class GenerateAltTextTask extends AbstractTask {

	/**
	 * Alt text character limits per WCAG 2.1 / SEO best practice.
	 */
	private const MAX_ALT_CHARS = 125;
	private const MIN_ALT_CHARS = 10;

	public function get_slug(): string {
		return 'alt_text';
	}

	public function get_meta_keys(): array {
		return array( '_ai_optimizer_alt_texts' );
	}

	protected function get_generation_options(): array {
		return array( 'temperature' => 0.3 );
	}

	/**
	 * Decode JSON array, pair with attachment IDs, enforce length limits,
	 * and store as a JSON object keyed by attachment ID.
	 *
	 * Also optionally updates each attachment's _wp_attachment_image_alt meta
	 * if the "Auto-apply alt text" setting is enabled.
	 *
	 * {@inheritdoc}
	 */
	protected function save_result( int $product_id, string $content ): void {
		$decoded = $this->extract_json_array( $content );

		if ( null === $decoded ) {
			$this->logger->error( sprintf( 'Alt text JSON decode failed for product %d.', $product_id ) );
			return;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		// Build ordered list of attachment IDs (featured image first).
		$ids = array_filter( array_merge(
			array( (int) $product->get_image_id() ),
			array_map( 'intval', $product->get_gallery_image_ids() )
		) );
		$ids = array_values( $ids );

		$alt_map = array();
		$auto_apply = (bool) get_option( 'aipo_auto_apply_alt_text', false );

		foreach ( $ids as $i => $attachment_id ) {
			$raw_alt = $decoded[ $i ] ?? '';
			$alt     = $this->sanitize_alt( (string) $raw_alt );

			if ( '' === $alt ) {
				continue;
			}

			$alt_map[ $attachment_id ] = $alt;

			if ( $auto_apply && $attachment_id > 0 ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
			}
		}

		if ( ! empty( $alt_map ) ) {
			update_post_meta( $product_id, '_ai_optimizer_alt_texts', wp_json_encode( $alt_map ) );
		}
	}

	/**
	 * Sanitize and enforce length constraints on a single alt text string.
	 *
	 * @param string $alt Raw alt text.
	 * @return string
	 */
	private function sanitize_alt( string $alt ): string {
		$alt = sanitize_text_field( $alt );
		$alt = substr( $alt, 0, self::MAX_ALT_CHARS );

		if ( mb_strlen( $alt ) < self::MIN_ALT_CHARS ) {
			return '';
		}

		return $alt;
	}

	/**
	 * Extract a JSON array from the AI response, stripping markdown fences.
	 *
	 * @param string $content Raw AI output.
	 * @return array<int, string>|null
	 */
	private function extract_json_array( string $content ): ?array {
		$content = trim( $content );

		// Strip ```json ... ``` or ``` ... ``` fences.
		if ( preg_match( '/```(?:json)?\s*([\s\S]*?)```/i', $content, $matches ) ) {
			$content = trim( $matches[1] );
		}

		// Find [ ... ] array block.
		if ( ! str_starts_with( $content, '[' ) ) {
			if ( preg_match( '/\[[\s\S]*\]/m', $content, $matches ) ) {
				$content = $matches[0];
			}
		}

		$decoded = json_decode( $content, true );

		return is_array( $decoded ) ? $decoded : null;
	}
}
