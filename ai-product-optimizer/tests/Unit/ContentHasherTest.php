<?php
/**
 * Unit tests for ContentHasher.
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Generation\ContentHasher;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class ContentHasherTest
 */
class ContentHasherTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_stored_returns_empty_string_when_no_meta(): void {
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, ContentHasher::META_KEY, true )
			->andReturn( '' );

		$hasher = new ContentHasher();
		$this->assertSame( '', $hasher->get_stored( 42 ) );
	}

	public function test_get_stored_returns_existing_hash(): void {
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, ContentHasher::META_KEY, true )
			->andReturn( 'abc123def456' );

		$hasher = new ContentHasher();
		$this->assertSame( 'abc123def456', $hasher->get_stored( 42 ) );
	}

	public function test_store_calls_update_post_meta(): void {
		Functions\expect( 'update_post_meta' )
			->once()
			->with( 42, ContentHasher::META_KEY, 'newhash123' );

		$hasher = new ContentHasher();
		$hasher->store( 42, 'newhash123' );
	}

	public function test_has_changed_returns_true_when_no_stored_hash(): void {
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, ContentHasher::META_KEY, true )
			->andReturn( '' );

		// compute() calls wc_get_product — mock it to return false.
		Functions\expect( 'wc_get_product' )
			->once()
			->with( 42 )
			->andReturn( false );

		Functions\expect( 'wp_json_encode' )->andReturnFirstArg();

		$hasher = new ContentHasher();
		// Stored hash is '' but computed is md5(42) — they differ.
		$this->assertTrue( $hasher->has_changed( 42 ) );
	}

	public function test_has_changed_returns_false_when_hash_unchanged(): void {
		// First mock compute() to return the same hash as stored.
		Functions\expect( 'wc_get_product' )
			->once()
			->with( 42 )
			->andReturn( false );

		Functions\expect( 'wp_json_encode' )->andReturnFirstArg();

		$computed = md5( (string) 42 );

		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, ContentHasher::META_KEY, true )
			->andReturn( $computed );

		$hasher = new ContentHasher();
		$this->assertFalse( $hasher->has_changed( 42 ) );
	}
}
