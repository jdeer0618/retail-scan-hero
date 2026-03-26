<?php
/**
 * Unit tests for RateLimiter.
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Security\RateLimiter;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class RateLimiterTest
 */
class RateLimiterTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_first_call_is_allowed(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'aipo_rate_limit_per_minute', 60 )
			->andReturn( 60 );

		Functions\expect( 'get_transient' )
			->once()
			->andReturn( false ); // No existing count.

		Functions\expect( 'set_transient' )
			->once()
			->andReturn( true );

		$limiter = new RateLimiter();
		$this->assertTrue( $limiter->check_and_increment( 1 ) );
	}

	public function test_call_below_limit_is_allowed(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'aipo_rate_limit_per_minute', 60 )
			->andReturn( 10 );

		Functions\expect( 'get_transient' )
			->once()
			->andReturn( 5 ); // 5 calls so far, limit is 10.

		Functions\expect( 'set_transient' )->once()->andReturn( true );

		$limiter = new RateLimiter();
		$this->assertTrue( $limiter->check_and_increment( 1 ) );
	}

	public function test_call_at_limit_is_blocked(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'aipo_rate_limit_per_minute', 60 )
			->andReturn( 10 );

		Functions\expect( 'get_transient' )
			->once()
			->andReturn( 10 ); // Already at limit.

		Functions\expect( 'set_transient' )->never();

		$limiter = new RateLimiter();
		$this->assertFalse( $limiter->check_and_increment( 1 ) );
	}

	public function test_remaining_returns_correct_count(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'aipo_rate_limit_per_minute', 60 )
			->andReturn( 10 );

		Functions\expect( 'get_transient' )->once()->andReturn( 3 );

		$limiter = new RateLimiter();
		$this->assertSame( 7, $limiter->remaining( 1 ) );
	}

	public function test_remaining_never_goes_below_zero(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'aipo_rate_limit_per_minute', 60 )
			->andReturn( 5 );

		Functions\expect( 'get_transient' )->once()->andReturn( 99 );

		$limiter = new RateLimiter();
		$this->assertSame( 0, $limiter->remaining( 1 ) );
	}
}
