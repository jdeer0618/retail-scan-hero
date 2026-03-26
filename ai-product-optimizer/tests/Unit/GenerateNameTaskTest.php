<?php
/**
 * Unit tests for GenerateNameTask.
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Generation\Tasks\GenerateNameTask;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class GenerateNameTaskTest
 */
class GenerateNameTaskTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_slug_returns_name(): void {
		$task = new GenerateNameTask();
		$this->assertSame( 'name', $task->get_slug() );
	}

	public function test_get_meta_keys_contains_name_keys(): void {
		$task = new GenerateNameTask();
		$keys = $task->get_meta_keys();

		$this->assertContains( '_ai_optimizer_name', $keys );
		$this->assertContains( '_ai_optimizer_name_variants', $keys );
	}

	public function test_save_result_parses_newline_variants(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_name_max_chars', 70 )
			->andReturn( 70 );

		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );

		$update_calls = array();
		Functions\expect( 'update_post_meta' )
			->andReturnUsing( static function ( int $id, string $key, string $val ) use ( &$update_calls ): bool {
				$update_calls[ $key ] = $val;
				return true;
			} );

		$task = new GenerateNameTask();
		$ref  = new \ReflectionMethod( $task, 'save_result' );
		$ref->setAccessible( true );

		$raw = "Premium Leather Wallet for Men\n2. Classic Brown Bifold Wallet\n- Full-Grain Leather Wallet";
		$ref->invoke( $task, 42, $raw );

		$this->assertArrayHasKey( '_ai_optimizer_name', $update_calls );
		$this->assertArrayHasKey( '_ai_optimizer_name_variants', $update_calls );

		// Primary name should be the first variant (strip numbering).
		$this->assertSame( 'Premium Leather Wallet for Men', $update_calls['_ai_optimizer_name'] );

		// Variants JSON should contain all 3.
		$variants = json_decode( $update_calls['_ai_optimizer_name_variants'], true );
		$this->assertCount( 3, $variants );
	}

	public function test_save_result_enforces_max_chars(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_name_max_chars', 70 )
			->andReturn( 10 ); // Very short limit.

		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );
		Functions\expect( 'update_post_meta' )
			->andReturnUsing( static function ( int $id, string $key, string $val ) use ( &$primary ): bool {
				if ( '_ai_optimizer_name' === $key ) { $primary = $val; }
				return true;
			} );

		$task = new GenerateNameTask();
		$ref  = new \ReflectionMethod( $task, 'save_result' );
		$ref->setAccessible( true );

		$primary = '';
		$ref->invoke( $task, 1, 'A Very Long Product Name That Exceeds The Limit' );

		$this->assertSame( 10, strlen( $primary ) );
	}

	public function test_save_result_does_nothing_with_empty_content(): void {
		Functions\expect( 'get_option' )->with( 'aipo_name_max_chars', 70 )->andReturn( 70 );
		Functions\expect( 'update_post_meta' )->never();

		$task = new GenerateNameTask();
		$ref  = new \ReflectionMethod( $task, 'save_result' );
		$ref->setAccessible( true );
		$ref->invoke( $task, 1, '' );
	}
}
