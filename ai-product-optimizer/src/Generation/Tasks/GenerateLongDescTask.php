<?php
/**
 * Long description generation task.
 *
 * Produces structured HTML: opening paragraph → Key Features h2+ul →
 * Why You'll Love It h2+paragraph → CTA sentence.
 * Passed through wp_kses_post before storage.
 *
 * @package AIProductOptimizer\Generation\Tasks
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Generation\Tasks;

/**
 * Class GenerateLongDescTask
 */
class GenerateLongDescTask extends AbstractTask {

	public function get_slug(): string {
		return 'long_desc';
	}

	public function get_meta_keys(): array {
		return array( '_ai_optimizer_long_desc' );
	}

	protected function get_generation_options(): array {
		return array( 'temperature' => 0.7, 'max_tokens' => 2048 );
	}

	/**
	 * Store kses-sanitized HTML in our meta key.
	 * Does NOT auto-apply to WooCommerce post_content to avoid overwriting
	 * manually crafted content — the UI "Apply" button handles that.
	 *
	 * {@inheritdoc}
	 */
	protected function save_result( int $product_id, string $content ): void {
		$clean = wp_kses_post( $this->strip_markdown_fences( $content ) );
		update_post_meta( $product_id, '_ai_optimizer_long_desc', $clean );
	}

	/**
	 * Remove ```html ... ``` fences that some models wrap their output in.
	 *
	 * @param string $content Raw AI output.
	 * @return string
	 */
	private function strip_markdown_fences( string $content ): string {
		return trim( preg_replace( '/^```[a-z]*\n?|```$/im', '', $content ) );
	}
}
