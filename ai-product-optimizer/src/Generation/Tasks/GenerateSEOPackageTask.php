<?php
/**
 * SEO package generation task.
 *
 * Generates a complete SEO content package in a single AI call.
 * The prompt instructs the model to return strict JSON; this task
 * decodes that JSON and fans the values out to individual meta keys.
 * Automatically triggers Yoast / Rank Math bridges after save.
 *
 * @package AIProductOptimizer\Generation\Tasks
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Generation\Tasks;

use AIProductOptimizer\Integrations\RankMathBridge;
use AIProductOptimizer\Integrations\YoastBridge;

/**
 * Class GenerateSEOPackageTask
 */
class GenerateSEOPackageTask extends AbstractTask {

	/**
	 * JSON key → post meta key mapping for the SEO package fields.
	 *
	 * @var array<string, string>
	 */
	private const FIELD_MAP = array(
		'seo_title'          => '_ai_optimizer_seo_title',
		'meta_description'   => '_ai_optimizer_meta_desc',
		'focus_keyword'      => '_ai_optimizer_focus_kw',
		'secondary_keywords' => '_ai_optimizer_secondary_kws',
		'og_title'           => '_ai_optimizer_og_title',
		'og_description'     => '_ai_optimizer_og_desc',
		'schema_hints'       => '_ai_optimizer_schema_hints',
	);

	/**
	 * Character limits enforced on string fields.
	 *
	 * @var array<string, int>
	 */
	private const CHAR_LIMITS = array(
		'seo_title'       => 60,
		'meta_description' => 160,
		'og_title'        => 70,
		'og_description'  => 200,
	);

	public function get_slug(): string {
		return 'seo_package';
	}

	public function get_meta_keys(): array {
		return array_values( self::FIELD_MAP );
	}

	/**
	 * Lower temperature for structured JSON output.
	 *
	 * {@inheritdoc}
	 */
	protected function get_generation_options(): array {
		return array( 'temperature' => 0.3 );
	}

	/**
	 * Decode JSON, enforce character limits, save to individual meta keys,
	 * and trigger SEO plugin bridges.
	 *
	 * {@inheritdoc}
	 */
	protected function save_result( int $product_id, string $content ): void {
		$decoded = $this->extract_json( $content );

		if ( null === $decoded ) {
			$this->logger->error(
				sprintf( 'SEO package JSON decode failed for product %d. Raw: %s', $product_id, substr( $content, 0, 200 ) )
			);
			return;
		}

		foreach ( self::FIELD_MAP as $json_key => $meta_key ) {
			$value = $decoded[ $json_key ] ?? null;
			if ( null === $value ) {
				continue;
			}

			if ( is_array( $value ) ) {
				update_post_meta( $product_id, $meta_key, wp_json_encode( $value ) );
				continue;
			}

			$value = (string) $value;

			// Enforce character limits.
			if ( isset( self::CHAR_LIMITS[ $json_key ] ) ) {
				$value = substr( $value, 0, self::CHAR_LIMITS[ $json_key ] );
			}

			update_post_meta( $product_id, $meta_key, sanitize_text_field( $value ) );
		}

		// Sync to active SEO plugins.
		YoastBridge::sync( $product_id );
		RankMathBridge::sync( $product_id );
	}

	/**
	 * Extract and decode JSON from the AI response.
	 *
	 * Handles cases where the model wraps JSON in markdown fences.
	 *
	 * @param string $content Raw AI output.
	 * @return array<string, mixed>|null Decoded array or null on failure.
	 */
	private function extract_json( string $content ): ?array {
		$content = trim( $content );

		// Strip ```json ... ``` or ``` ... ``` fences.
		if ( preg_match( '/```(?:json)?\s*([\s\S]*?)```/i', $content, $matches ) ) {
			$content = trim( $matches[1] );
		}

		// Find the first { ... } block if there's surrounding text.
		if ( ! str_starts_with( $content, '{' ) ) {
			if ( preg_match( '/\{[\s\S]*\}/m', $content, $matches ) ) {
				$content = $matches[0];
			}
		}

		$decoded = json_decode( $content, true );

		return is_array( $decoded ) ? $decoded : null;
	}
}
