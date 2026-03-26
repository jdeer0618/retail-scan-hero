<?php
/**
 * Unit tests for SearchBoost::add_distinct() deduplication.
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Integrations\SearchBoost;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class SearchBoostDistinctTest
 */
class SearchBoostDistinctTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_add_distinct_returns_distinct_for_active_search_query(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_search_boost_enabled', true )
			->andReturn( true );

		Functions\expect( 'is_admin' )->andReturn( false );
		Functions\expect( 'wp_doing_ajax' )->andReturn( false );

		$query = \Mockery::mock( \WP_Query::class );
		$query->shouldReceive( 'is_search' )->andReturn( true );

		$boost  = new SearchBoost();
		$result = $boost->add_distinct( '', $query );

		$this->assertSame( 'DISTINCT', $result );
	}

	public function test_add_distinct_passes_through_when_boost_disabled(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_search_boost_enabled', true )
			->andReturn( false );

		$query = \Mockery::mock( \WP_Query::class );
		$query->shouldReceive( 'is_search' )->andReturn( true );

		$boost  = new SearchBoost();
		$result = $boost->add_distinct( '', $query );

		$this->assertSame( '', $result );
	}

	public function test_add_distinct_passes_through_when_not_search_query(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_search_boost_enabled', true )
			->andReturn( true );

		$query = \Mockery::mock( \WP_Query::class );
		$query->shouldReceive( 'is_search' )->andReturn( false );

		Functions\expect( 'is_admin' )->andReturn( false );
		Functions\expect( 'wp_doing_ajax' )->andReturn( false );

		$boost  = new SearchBoost();
		$result = $boost->add_distinct( 'existing', $query );

		$this->assertSame( 'existing', $result );
	}

	public function test_add_distinct_preserves_existing_value_for_non_boosted_query(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_search_boost_enabled', true )
			->andReturn( true );

		Functions\expect( 'is_admin' )->andReturn( true );
		Functions\expect( 'wp_doing_ajax' )->andReturn( false );

		$query = \Mockery::mock( \WP_Query::class );
		$query->shouldReceive( 'is_search' )->andReturn( true );

		$boost  = new SearchBoost();
		$result = $boost->add_distinct( 'DISTINCT', $query );

		// Admin non-ajax → should NOT boost → passes original value through.
		$this->assertSame( 'DISTINCT', $result );
	}
}
