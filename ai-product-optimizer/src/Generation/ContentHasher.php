<?php
/**
 * Product content hasher.
 *
 * Generates an MD5 hash of the product's source data (name, short description,
 * attributes, categories). This hash is stored in _ai_optimizer_content_hash
 * and compared before each batch job to skip products that haven't changed
 * since their last generation.
 *
 * @package AIProductOptimizer\Generation
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Generation;

/**
 * Class ContentHasher
 */
class ContentHasher {

	/**
	 * Post meta key where the hash is persisted.
	 */
	public const META_KEY = '_ai_optimizer_content_hash';

	/**
	 * Compute a content hash for a WooCommerce product.
	 *
	 * @param int $product_id WooCommerce product post ID.
	 * @return string MD5 hex hash.
	 */
	public function compute( int $product_id ): string {
		$product = wc_get_product( $product_id );

		if ( ! $product instanceof \WC_Product ) {
			return md5( (string) $product_id );
		}

		$data = array(
			'name'        => $product->get_name(),
			'short_desc'  => $product->get_short_description(),
			'description' => wp_strip_all_tags( $product->get_description() ),
			'sku'         => $product->get_sku(),
			'categories'  => implode( ',', $product->get_category_ids() ),
			'attributes'  => wp_json_encode( $product->get_attributes() ),
		);

		return md5( wp_json_encode( $data ) ?: '' );
	}

	/**
	 * Retrieve the stored content hash for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return string Stored hash, or empty string if none.
	 */
	public function get_stored( int $product_id ): string {
		return (string) get_post_meta( $product_id, self::META_KEY, true );
	}

	/**
	 * Persist the computed hash for a product.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $hash       MD5 hash from compute().
	 * @return void
	 */
	public function store( int $product_id, string $hash ): void {
		update_post_meta( $product_id, self::META_KEY, $hash );
	}

	/**
	 * Return whether the product's source data has changed since last generation.
	 *
	 * @param int $product_id Product ID.
	 * @return bool True if the content has changed (or no hash stored yet).
	 */
	public function has_changed( int $product_id ): bool {
		$stored  = $this->get_stored( $product_id );
		$current = $this->compute( $product_id );
		return $stored !== $current;
	}
}
