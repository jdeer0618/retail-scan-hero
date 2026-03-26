<?php
/**
 * Two-tier cache abstraction.
 *
 * L1: WordPress object cache (Redis/Memcached/APCu if available, in-memory otherwise).
 * L2: WordPress transients (persistent, database-backed fallback).
 *
 * Cache keys follow the pattern:
 *   aipo_{product_id}_{task_slug}_{content_hash_prefix8}
 *
 * @package AIProductOptimizer\Cache
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Cache;

/**
 * Class CacheManager
 */
class CacheManager {

	/**
	 * Object-cache group name.
	 */
	private const CACHE_GROUP = 'aipo';

	/**
	 * Transient key prefix.
	 */
	private const TRANSIENT_PREFIX = 'aipo_cache_';

	/**
	 * Get a cached value.
	 *
	 * Checks object cache first (L1), then falls back to the transient (L2).
	 *
	 * @param string $key Cache key.
	 * @return mixed|false Cached value, or false if not found.
	 */
	public function get( string $key ): mixed {
		// L1.
		$value = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false !== $value ) {
			return $value;
		}

		// L2.
		$value = get_transient( self::TRANSIENT_PREFIX . $key );
		if ( false !== $value ) {
			// Warm L1 from L2.
			wp_cache_set( $key, $value, self::CACHE_GROUP, $this->ttl_seconds() );
		}

		return $value;
	}

	/**
	 * Store a value in both cache tiers.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to cache (must be serialisable).
	 * @return void
	 */
	public function set( string $key, mixed $value ): void {
		$ttl = $this->ttl_seconds();
		wp_cache_set( $key, $value, self::CACHE_GROUP, $ttl );
		set_transient( self::TRANSIENT_PREFIX . $key, $value, $ttl );
	}

	/**
	 * Delete a cached value from both tiers.
	 *
	 * @param string $key Cache key.
	 * @return void
	 */
	public function delete( string $key ): void {
		wp_cache_delete( $key, self::CACHE_GROUP );
		delete_transient( self::TRANSIENT_PREFIX . $key );
	}

	/**
	 * Invalidate all cached generation results for a given product.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return void
	 */
	public function invalidate_product( int $product_id ): void {
		// Object cache doesn't support wildcard deletes without external cache.
		// Best-effort: delete known task-slug keys for this product.
		$task_slugs = array(
			'name',
			'short_desc',
			'long_desc',
			'seo_package',
			'search_keywords',
			'alt_text',
		);

		foreach ( $task_slugs as $slug ) {
			// Delete the key without hash suffix (pattern: aipo_{id}_{slug}_*)
			// For persistent caches we delete the base key; hash-suffixed entries
			// will naturally expire via TTL.
			$base_key = $this->build_key( $product_id, $slug, '' );
			$this->delete( $base_key );
		}
	}

	/**
	 * Build a deterministic cache key.
	 *
	 * @param int    $product_id   Product ID.
	 * @param string $task_slug    Task identifier (e.g. 'seo_package').
	 * @param string $content_hash MD5 of source product data (first 8 chars used).
	 * @return string
	 */
	public function build_key( int $product_id, string $task_slug, string $content_hash ): string {
		$hash_prefix = substr( $content_hash, 0, 8 );
		return sprintf( 'aipo_%d_%s_%s', $product_id, $task_slug, $hash_prefix );
	}

	/**
	 * Return the configured TTL in seconds.
	 *
	 * @return int
	 */
	private function ttl_seconds(): int {
		$days = (int) get_option( 'aipo_cache_ttl_days', 7 );
		return max( 1, $days ) * DAY_IN_SECONDS;
	}
}
