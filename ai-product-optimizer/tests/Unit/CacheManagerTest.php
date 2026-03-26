<?php
/**
 * Unit tests for CacheManager.
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Cache\CacheManager;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class CacheManagerTest
 */
class CacheManagerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// build_key
	// -----------------------------------------------------------------------

	public function test_build_key_format(): void {
		$cache = new CacheManager();
		$key   = $cache->build_key( 42, 'seo_package', 'abcdef1234567890' );

		$this->assertSame( 'aipo_42_seo_package_abcdef12', $key );
	}

	public function test_build_key_uses_first_8_chars_of_hash(): void {
		$cache = new CacheManager();
		$key   = $cache->build_key( 1, 'name', '0123456789abcdef' );

		$this->assertStringEndsWith( '_01234567', $key );
	}

	// -----------------------------------------------------------------------
	// get
	// -----------------------------------------------------------------------

	public function test_get_returns_value_from_object_cache_l1(): void {
		Functions\expect( 'wp_cache_get' )
			->once()
			->with( 'test-key', 'aipo' )
			->andReturn( 'cached-value' );

		// Should NOT hit L2 when L1 has a hit.
		Functions\expect( 'get_transient' )->never();

		$cache = new CacheManager();
		$this->assertSame( 'cached-value', $cache->get( 'test-key' ) );
	}

	public function test_get_falls_back_to_transient_l2_on_cache_miss(): void {
		Functions\expect( 'wp_cache_get' )->andReturn( false );
		Functions\expect( 'get_transient' )->once()->andReturn( 'transient-value' );

		// L2 hit should warm L1.
		Functions\expect( 'wp_cache_set' )->once();
		Functions\expect( 'get_option' )
			->with( 'aipo_cache_ttl_days', 7 )
			->andReturn( 7 );

		$cache = new CacheManager();
		$this->assertSame( 'transient-value', $cache->get( 'test-key' ) );
	}

	public function test_get_returns_false_on_total_miss(): void {
		Functions\expect( 'wp_cache_get' )->andReturn( false );
		Functions\expect( 'get_transient' )->andReturn( false );

		$cache = new CacheManager();
		$this->assertFalse( $cache->get( 'miss-key' ) );
	}

	// -----------------------------------------------------------------------
	// set
	// -----------------------------------------------------------------------

	public function test_set_writes_to_both_tiers(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_cache_ttl_days', 7 )
			->andReturn( 3 );

		Functions\expect( 'wp_cache_set' )
			->once()
			->with( 'my-key', 'my-value', 'aipo', 3 * DAY_IN_SECONDS );

		Functions\expect( 'set_transient' )
			->once()
			->with( 'aipo_cache_my-key', 'my-value', 3 * DAY_IN_SECONDS );

		$cache = new CacheManager();
		$cache->set( 'my-key', 'my-value' );
	}

	// -----------------------------------------------------------------------
	// delete
	// -----------------------------------------------------------------------

	public function test_delete_removes_from_both_tiers(): void {
		Functions\expect( 'wp_cache_delete' )->once()->with( 'del-key', 'aipo' );
		Functions\expect( 'delete_transient' )->once()->with( 'aipo_cache_del-key' );

		$cache = new CacheManager();
		$cache->delete( 'del-key' );
	}

	// -----------------------------------------------------------------------
	// invalidate_product
	// -----------------------------------------------------------------------

	public function test_invalidate_product_deletes_all_task_keys(): void {
		// Should delete one key per known task slug (6 tasks).
		Functions\expect( 'wp_cache_delete' )->times( 6 );
		Functions\expect( 'delete_transient' )->times( 6 );

		$cache = new CacheManager();
		$cache->invalidate_product( 123 );
	}

	// -----------------------------------------------------------------------
	// TTL edge cases
	// -----------------------------------------------------------------------

	public function test_ttl_defaults_to_7_days_when_option_missing(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_cache_ttl_days', 7 )
			->andReturn( null );

		// null → 0 days → max(1, 0) → 1 day (floor).
		Functions\expect( 'wp_cache_set' )
			->once()
			->with( \Mockery::any(), \Mockery::any(), 'aipo', DAY_IN_SECONDS );

		Functions\expect( 'set_transient' )->once();

		$cache = new CacheManager();
		$cache->set( 'key', 'value' );
	}
}
