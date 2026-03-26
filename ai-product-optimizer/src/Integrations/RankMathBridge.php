<?php
/**
 * Rank Math SEO integration bridge.
 *
 * @package AIProductOptimizer\Integrations
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Integrations;

/**
 * Class RankMathBridge
 */
class RankMathBridge {

	/**
	 * Field mapping: AI meta key → Rank Math meta key.
	 *
	 * @var array<string, string>
	 */
	private const FIELD_MAP = array(
		'_ai_optimizer_seo_title' => 'rank_math_title',
		'_ai_optimizer_meta_desc' => 'rank_math_description',
		'_ai_optimizer_focus_kw'  => 'rank_math_focus_keyword',
	);

	/**
	 * Sync AI-generated SEO fields to Rank Math's meta storage.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public static function sync( int $product_id ): void {
		if ( ! self::is_active() ) {
			return;
		}

		if ( ! (bool) get_option( 'aipo_rankmath_bridge_enabled', true ) ) {
			return;
		}

		$override = (bool) get_option( 'aipo_rankmath_override_existing', false );

		foreach ( self::FIELD_MAP as $ai_key => $rm_key ) {
			$ai_value = get_post_meta( $product_id, $ai_key, true );

			if ( empty( $ai_value ) ) {
				continue;
			}

			$existing = get_post_meta( $product_id, $rm_key, true );

			if ( ! empty( $existing ) && ! $override ) {
				continue;
			}

			update_post_meta( $product_id, $rm_key, sanitize_text_field( $ai_value ) );
		}
	}

	/**
	 * Whether Rank Math is installed and active.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return class_exists( 'RankMath' );
	}
}
