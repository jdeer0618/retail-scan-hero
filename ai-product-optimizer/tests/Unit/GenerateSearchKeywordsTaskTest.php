<?php
/**
 * Unit tests for GenerateSearchKeywordsTask.
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Generation\Tasks\GenerateSearchKeywordsTask;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class GenerateSearchKeywordsTaskTest
 */
class GenerateSearchKeywordsTaskTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_slug(): void {
		$this->assertSame( 'search_keywords', ( new GenerateSearchKeywordsTask() )->get_slug() );
	}

	public function test_get_meta_keys(): void {
		$this->assertContains( '_ai_search_keywords', ( new GenerateSearchKeywordsTask() )->get_meta_keys() );
	}

	public function test_save_result_joins_keywords_with_newlines(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_search_keyword_count', 20 )
			->andReturn( 20 );

		$saved = '';
		Functions\expect( 'update_post_meta' )
			->once()
			->with( 1, '_ai_search_keywords', \Mockery::type( 'string' ) )
			->andReturnUsing( static function ( int $id, string $key, string $val ) use ( &$saved ): bool {
				$saved = $val;
				return true;
			} );

		$raw = "leather wallet\nbifold wallet\nmen's leather wallet\nslim card holder";

		$task = new GenerateSearchKeywordsTask();
		$ref  = new \ReflectionMethod( $task, 'save_result' );
		$ref->setAccessible( true );
		$ref->invoke( $task, 1, $raw );

		$keywords = explode( "\n", $saved );
		$this->assertCount( 4, $keywords );
	}

	public function test_save_result_deduplicates_keywords(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_search_keyword_count', 20 )
			->andReturn( 20 );

		$saved = '';
		Functions\expect( 'update_post_meta' )
			->andReturnUsing( static function ( int $id, string $key, string $val ) use ( &$saved ): bool {
				$saved = $val;
				return true;
			} );

		$raw = "leather wallet\nleather wallet\nbifold wallet\nleather wallet";

		$task = new GenerateSearchKeywordsTask();
		$ref  = new \ReflectionMethod( $task, 'save_result' );
		$ref->setAccessible( true );
		$ref->invoke( $task, 1, $raw );

		$keywords = array_filter( explode( "\n", $saved ) );
		$this->assertCount( 2, $keywords );
	}

	public function test_save_result_respects_keyword_count_limit(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_search_keyword_count', 20 )
			->andReturn( 3 ); // Only 3 allowed.

		$saved = '';
		Functions\expect( 'update_post_meta' )
			->andReturnUsing( static function ( int $id, string $key, string $val ) use ( &$saved ): bool {
				$saved = $val;
				return true;
			} );

		// Feed 10 unique keywords.
		$raw = implode( "\n", array_map( static fn( int $i ) => "keyword $i", range( 1, 10 ) ) );

		$task = new GenerateSearchKeywordsTask();
		$ref  = new \ReflectionMethod( $task, 'save_result' );
		$ref->setAccessible( true );
		$ref->invoke( $task, 1, $raw );

		$keywords = array_filter( explode( "\n", $saved ) );
		$this->assertCount( 3, $keywords );
	}

	public function test_save_result_lowercases_keywords(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_search_keyword_count', 20 )
			->andReturn( 20 );

		$saved = '';
		Functions\expect( 'update_post_meta' )
			->andReturnUsing( static function ( int $id, string $key, string $val ) use ( &$saved ): bool {
				$saved = $val;
				return true;
			} );

		$task = new GenerateSearchKeywordsTask();
		$ref  = new \ReflectionMethod( $task, 'save_result' );
		$ref->setAccessible( true );
		$ref->invoke( $task, 1, "Leather Wallet\nPREMIUM BIFOLD" );

		$this->assertStringNotContainsString( 'L', $saved );
		$this->assertStringNotContainsString( 'P', $saved );
		$this->assertStringContainsString( 'leather wallet', $saved );
	}

	public function test_save_result_does_nothing_with_empty_content(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_search_keyword_count', 20 )
			->andReturn( 20 );
		Functions\expect( 'update_post_meta' )->never();

		$task = new GenerateSearchKeywordsTask();
		$ref  = new \ReflectionMethod( $task, 'save_result' );
		$ref->setAccessible( true );
		$ref->invoke( $task, 1, '' );
	}
}
