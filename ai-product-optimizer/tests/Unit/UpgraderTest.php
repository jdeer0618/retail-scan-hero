<?php
/**
 * Unit tests for Upgrader migrations.
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Upgrader;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class UpgraderTest
 */
class UpgraderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_run_migrations_skips_when_version_is_current(): void {
		Functions\expect( 'get_option' )->never();
		Functions\expect( 'update_option' )->never();

		// Version equal — no migrations should run.
		Upgrader::run_migrations( '1.0.1' );
	}

	public function test_run_migrations_1_0_1_renames_rankmath_keys(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_rankmath_bridge_enabled' )
			->andReturn( true );
		Functions\expect( 'get_option' )
			->with( 'aipo_rank_math_bridge_enabled' )
			->andReturn( false );
		Functions\expect( 'update_option' )
			->once()
			->with( 'aipo_rank_math_bridge_enabled', true, false );
		Functions\expect( 'delete_option' )
			->with( 'aipo_rankmath_bridge_enabled' );

		Functions\expect( 'get_option' )
			->with( 'aipo_rankmath_override_existing' )
			->andReturn( false );
		Functions\expect( 'get_option' )
			->with( 'aipo_rank_math_override_existing' )
			->andReturn( false ); // already false = not set yet (treat as absent for test)
		Functions\expect( 'update_option' )
			->with( 'aipo_rank_math_override_existing', false, false )
			->andReturn( true );
		Functions\expect( 'delete_option' )
			->with( 'aipo_rankmath_override_existing' );

		// Other rename pairs + cache_ttl migration — stub them.
		Functions\expect( 'get_option' )->andReturn( false );
		Functions\expect( 'delete_option' )->andReturn( true );
		Functions\expect( 'update_option' )->andReturn( true );

		Upgrader::run_migrations( '1.0.0' );
	}

	public function test_run_migrations_1_0_1_converts_cache_ttl_days_to_seconds(): void {
		// Stub all the rename pairs to "not present".
		Functions\expect( 'get_option' )
			->andReturnUsing( function ( string $key ) {
				if ( 'aipo_cache_ttl_days' === $key ) {
					return 7; // 7 days.
				}
				if ( 'aipo_cache_ttl' === $key ) {
					return false; // Not set yet.
				}
				return false;
			} );

		Functions\expect( 'update_option' )
			->with( 'aipo_cache_ttl', 7 * DAY_IN_SECONDS, false )
			->once();
		Functions\expect( 'delete_option' )
			->with( 'aipo_cache_ttl_days' )
			->once();

		Functions\expect( 'update_option' )->andReturn( true );
		Functions\expect( 'delete_option' )->andReturn( true );

		Upgrader::run_migrations( '1.0.0' );
	}
}
