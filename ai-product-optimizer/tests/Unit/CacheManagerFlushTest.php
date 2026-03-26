<?php
/**
 * Unit tests for CacheManager::flush_all() and updated TTL.
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
 * Class CacheManagerFlushTest
 */
class CacheManagerFlushTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_ttl_reads_aipo_cache_ttl_in_seconds(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_cache_ttl', \Mockery::any() )
			->andReturn( 3600 );

		Functions\expect( 'wp_cache_get' )->andReturn( false );
		Functions\expect( 'get_transient' )->andReturn( false );

		// set() should be called with TTL = 3600.
		Functions\expect( 'wp_cache_set' )
			->once()
			->with( \Mockery::any(), 'value', \Mockery::any(), 3600 );
		Functions\expect( 'set_transient' )
			->once()
			->with( \Mockery::any(), 'value', 3600 );

		$cache = new CacheManager();
		$cache->set( 'test_key', 'value' );
	}

	public function test_ttl_clamps_below_minimum_to_60(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_cache_ttl', \Mockery::any() )
			->andReturn( 0 );

		Functions\expect( 'wp_cache_get' )->andReturn( false );
		Functions\expect( 'get_transient' )->andReturn( false );
		Functions\expect( 'wp_cache_set' )
			->once()
			->with( \Mockery::any(), \Mockery::any(), \Mockery::any(), 60 );
		Functions\expect( 'set_transient' )
			->once()
			->with( \Mockery::any(), \Mockery::any(), 60 );

		$cache = new CacheManager();
		$cache->set( 'key', 'val' );
	}

	public function test_flush_all_runs_delete_query_and_returns_count(): void {
		global $wpdb;

		// Set up a minimal $wpdb mock.
		$wpdb = new class {
			public string $options  = 'wp_options';
			public function prepare( string $sql, ...$args ): string {
				return vsprintf( str_replace( '%s', "'%s'", $sql ), $args );
			}
			public function esc_like( string $text ): string {
				return addcslashes( $text, '_%\\' );
			}
			public function query( string $sql ): int {
				return 4; // Simulates 2 transients × 2 rows (value + timeout).
			}
		};

		Functions\expect( 'wp_cache_flush_group' )
			->once()
			->with( 'aipo' );

		$cache = new CacheManager();
		$count = $cache->flush_all();

		$this->assertSame( 2, $count ); // 4 rows / 2 = 2 transients deleted.
	}
}
