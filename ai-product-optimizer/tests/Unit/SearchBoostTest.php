<?php
/**
 * Unit tests for SearchBoost.
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
 * Class SearchBoostTest
 */
class SearchBoostTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// boost_wp_search — disabled
	// -----------------------------------------------------------------------

	public function test_boost_skips_when_setting_disabled(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_search_boost_enabled', true )
			->andReturn( false );

		$query = $this->mock_search_query( 'red shoes' );

		// set() should never be called.
		$query->expects( $this->never() )->method( 'set' );

		$boost = new SearchBoost();
		$boost->boost_wp_search( $query );
	}

	// -----------------------------------------------------------------------
	// boost_wp_search — not a search query
	// -----------------------------------------------------------------------

	public function test_boost_skips_when_not_a_search(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_search_boost_enabled', true )
			->andReturn( true );

		Functions\expect( 'is_admin' )->andReturn( false );
		Functions\expect( 'wp_doing_ajax' )->andReturn( false );

		$query = $this->createMock( \WP_Query::class );
		$query->method( 'is_search' )->willReturn( false );
		$query->expects( $this->never() )->method( 'set' );

		$boost = new SearchBoost();
		$boost->boost_wp_search( $query );
	}

	// -----------------------------------------------------------------------
	// boost_wp_search — empty search string
	// -----------------------------------------------------------------------

	public function test_boost_skips_when_search_term_empty(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_search_boost_enabled', true )
			->andReturn( true );

		Functions\expect( 'is_admin' )->andReturn( false );
		Functions\expect( 'wp_doing_ajax' )->andReturn( false );

		$query = $this->createMock( \WP_Query::class );
		$query->method( 'is_search' )->willReturn( true );
		$query->method( 'get' )->with( 's' )->willReturn( '' );
		$query->expects( $this->never() )->method( 'set' );

		$boost = new SearchBoost();
		$boost->boost_wp_search( $query );
	}

	// -----------------------------------------------------------------------
	// boost_wp_search — injects meta query
	// -----------------------------------------------------------------------

	public function test_boost_injects_meta_query_clause_on_search(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_search_boost_enabled', true )
			->andReturn( true );

		Functions\expect( 'is_admin' )->andReturn( false );
		Functions\expect( 'wp_doing_ajax' )->andReturn( false );

		Functions\expect( 'apply_filters' )
			->with( 'aipo_search_meta_query', \Mockery::type( 'array' ), \Mockery::type( \WP_Query::class ) )
			->andReturnFirstArg();

		$set_calls = array();

		$query = $this->createMock( \WP_Query::class );
		$query->method( 'is_search' )->willReturn( true );
		$query->method( 'get' )
			->willReturnMap( array(
				array( 's', 'red shoes' ),
				array( 'meta_query', array() ),
			) );

		$query->expects( $this->once() )
			->method( 'set' )
			->with(
				'meta_query',
				$this->callback( static function ( array $mq ): bool {
					// Must contain the OR relation and a LIKE clause on _ai_search_keywords.
					foreach ( $mq as $clause ) {
						if ( is_array( $clause ) && ( $clause['key'] ?? '' ) === '_ai_search_keywords' ) {
							return true;
						}
					}
					return false;
				} )
			);

		$boost = new SearchBoost();
		$boost->boost_wp_search( $query );
	}

	// -----------------------------------------------------------------------
	// boost_wp_search — skips admin (non-AJAX)
	// -----------------------------------------------------------------------

	public function test_boost_skips_admin_non_ajax_requests(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_search_boost_enabled', true )
			->andReturn( true );

		Functions\expect( 'is_admin' )->andReturn( true );
		Functions\expect( 'wp_doing_ajax' )->andReturn( false );

		$query = $this->createMock( \WP_Query::class );
		$query->method( 'is_search' )->willReturn( true );
		$query->expects( $this->never() )->method( 'set' );

		$boost = new SearchBoost();
		$boost->boost_wp_search( $query );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Create a WP_Query mock pre-configured as a search with a search string.
	 *
	 * @param string $search_term The 's' query var value.
	 * @return \WP_Query&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function mock_search_query( string $search_term ): \WP_Query {
		$query = $this->createMock( \WP_Query::class );
		$query->method( 'is_search' )->willReturn( true );
		$query->method( 'get' )->with( 's' )->willReturn( $search_term );
		return $query;
	}
}
