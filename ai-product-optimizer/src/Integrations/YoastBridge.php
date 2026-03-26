<?php
/**
 * Yoast SEO integration bridge.
 *
 * Copies AI-generated SEO fields into Yoast SEO's post meta storage when
 * Yoast is active. Bridge is non-destructive by default: existing Yoast
 * values are only overwritten when "Override existing" is enabled.
 *
 * @package AIProductOptimizer\Integrations
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Integrations;

/**
 * Class YoastBridge
 */
class YoastBridge {

	/**
	 * Field mapping: AI meta key → Yoast meta key.
	 *
	 * @var array<string, string>
	 */
	private const FIELD_MAP = array(
		'_ai_optimizer_seo_title' => '_yoast_wpseo_title',
		'_ai_optimizer_meta_desc' => '_yoast_wpseo_metadesc',
		'_ai_optimizer_focus_kw'  => '_yoast_wpseo_focuskw',
	);

	/**
	 * Sync AI-generated SEO fields to Yoast's meta storage for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public static function sync( int $product_id ): void {
		if ( ! self::is_active() ) {
			return;
		}

		if ( ! (bool) get_option( 'aipo_yoast_bridge_enabled', true ) ) {
			return;
		}

		$override = (bool) get_option( 'aipo_yoast_override_existing', false );

		foreach ( self::FIELD_MAP as $ai_key => $yoast_key ) {
			$ai_value = get_post_meta( $product_id, $ai_key, true );

			if ( empty( $ai_value ) ) {
				continue;
			}

			$existing = get_post_meta( $product_id, $yoast_key, true );

			if ( ! empty( $existing ) && ! $override ) {
				continue;
			}

			update_post_meta( $product_id, $yoast_key, sanitize_text_field( $ai_value ) );
		}
	}

	/**
	 * Whether Yoast SEO is installed and active.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return class_exists( 'WPSEO_Options' );
	}
}
