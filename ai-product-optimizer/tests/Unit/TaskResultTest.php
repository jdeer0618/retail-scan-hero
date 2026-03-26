<?php
/**
 * Unit tests for TaskResult value object.
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Generation\Tasks\TaskResult;
use PHPUnit\Framework\TestCase;

/**
 * Class TaskResultTest
 */
class TaskResultTest extends TestCase {

	public function test_constructor_sets_all_properties(): void {
		$result = new TaskResult(
			task_slug:   'seo_package',
			product_id:  42,
			raw_content: '{"seo_title":"Test"}',
			provider:    'openai',
			model:       'gpt-4o',
			tokens_used: 512,
			from_cache:  false,
			skipped:     false,
		);

		$this->assertSame( 'seo_package', $result->task_slug );
		$this->assertSame( 42, $result->product_id );
		$this->assertSame( '{"seo_title":"Test"}', $result->raw_content );
		$this->assertSame( 'openai', $result->provider );
		$this->assertSame( 'gpt-4o', $result->model );
		$this->assertSame( 512, $result->tokens_used );
		$this->assertFalse( $result->from_cache );
		$this->assertFalse( $result->skipped );
	}

	public function test_skipped_factory_sets_skipped_true(): void {
		$result = TaskResult::skipped( 'name', 99 );

		$this->assertTrue( $result->skipped );
		$this->assertFalse( $result->from_cache );
		$this->assertSame( '', $result->raw_content );
		$this->assertSame( 'name', $result->task_slug );
		$this->assertSame( 99, $result->product_id );
	}

	public function test_from_cache_factory_sets_from_cache_true(): void {
		$result = TaskResult::from_cache( 'long_desc', 7, '<p>Cached content</p>' );

		$this->assertTrue( $result->from_cache );
		$this->assertFalse( $result->skipped );
		$this->assertSame( 'cache', $result->provider );
		$this->assertSame( 'cache', $result->model );
		$this->assertSame( '<p>Cached content</p>', $result->raw_content );
	}

	public function test_to_array_contains_expected_keys(): void {
		$result = new TaskResult( 'name', 1, 'content', 'anthropic', 'claude-opus-4-6', 256 );
		$arr    = $result->to_array();

		$this->assertArrayHasKey( 'task_slug',   $arr );
		$this->assertArrayHasKey( 'product_id',  $arr );
		$this->assertArrayHasKey( 'provider',    $arr );
		$this->assertArrayHasKey( 'model',       $arr );
		$this->assertArrayHasKey( 'tokens_used', $arr );
		$this->assertArrayHasKey( 'from_cache',  $arr );
		$this->assertArrayHasKey( 'skipped',     $arr );
	}

	public function test_to_array_does_not_expose_raw_content(): void {
		$result = new TaskResult( 'name', 1, 'secret content', 'openai', 'gpt-4o' );
		$arr    = $result->to_array();

		// raw_content should NOT be in the to_array() output (for REST responses).
		$this->assertArrayNotHasKey( 'raw_content', $arr );
	}

	public function test_result_is_immutable(): void {
		$result = new TaskResult( 'name', 1, 'content', 'openai', 'gpt-4o' );

		// Readonly properties cannot be reassigned — verify via reflection.
		$ref = new \ReflectionClass( $result );
		foreach ( $ref->getProperties() as $prop ) {
			$this->assertTrue( $prop->isReadOnly(), "Property {$prop->getName()} should be readonly." );
		}
	}
}
