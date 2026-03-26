<?php
/**
 * Unit tests for GenerationOrchestrator.
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Generation\GenerationOrchestrator;
use AIProductOptimizer\Generation\Tasks\Contracts\GenerationTaskInterface;
use AIProductOptimizer\Generation\Tasks\TaskResult;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Minimal stub task for orchestrator testing.
 */
final class StubTask implements GenerationTaskInterface {

	private bool $should_throw;
	public int $run_called = 0;

	public function __construct( bool $should_throw = false ) {
		$this->should_throw = $should_throw;
	}

	public function run( int $product_id ): TaskResult {
		++$this->run_called;
		if ( $this->should_throw ) {
			throw new \AIProductOptimizer\Exceptions\ProviderException( 'Stub failure' );
		}
		return new TaskResult( 'stub', $product_id, 'stub content', 'openai', 'gpt-4o' );
	}

	public function get_slug(): string       { return 'stub'; }
	public function get_meta_keys(): array   { return array( '_ai_stub' ); }
}

/**
 * Class GenerationOrchestratorTest
 */
class GenerationOrchestratorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// get_registered_slugs
	// -----------------------------------------------------------------------

	public function test_get_registered_slugs_contains_all_built_in_tasks(): void {
		Functions\expect( 'apply_filters' )
			->with( 'aipo_registered_tasks', \Mockery::type( 'array' ) )
			->andReturnFirstArg();

		$orch  = new GenerationOrchestrator();
		$slugs = $orch->get_registered_slugs();

		foreach ( array( 'name', 'short_desc', 'long_desc', 'seo_package', 'search_keywords', 'alt_text' ) as $slug ) {
			$this->assertContains( $slug, $slugs );
		}
	}

	// -----------------------------------------------------------------------
	// resolve_task
	// -----------------------------------------------------------------------

	public function test_resolve_task_throws_for_unknown_slug(): void {
		Functions\expect( 'apply_filters' )
			->with( 'aipo_registered_tasks', \Mockery::type( 'array' ) )
			->andReturnFirstArg();

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/Unknown generation task slug/' );

		( new GenerationOrchestrator() )->resolve_task( 'not_a_real_task' );
	}

	public function test_resolve_task_returns_custom_task_via_filter(): void {
		Functions\expect( 'apply_filters' )
			->with( 'aipo_registered_tasks', \Mockery::type( 'array' ) )
			->andReturnUsing( static function ( array $tasks ): array {
				$tasks['stub'] = StubTask::class;
				return $tasks;
			} );

		$task = ( new GenerationOrchestrator() )->resolve_task( 'stub' );

		$this->assertInstanceOf( StubTask::class, $task );
	}

	public function test_resolve_task_caches_instance_between_calls(): void {
		Functions\expect( 'apply_filters' )
			->with( 'aipo_registered_tasks', \Mockery::type( 'array' ) )
			->andReturnUsing( static function ( array $tasks ): array {
				$tasks['stub'] = StubTask::class;
				return $tasks;
			} );

		$orch = new GenerationOrchestrator();

		$t1 = $orch->resolve_task( 'stub' );
		$t2 = $orch->resolve_task( 'stub' );

		$this->assertSame( $t1, $t2, 'Same instance should be returned on second call.' );
	}

	// -----------------------------------------------------------------------
	// run_task — delegates to task object
	// -----------------------------------------------------------------------

	public function test_run_task_delegates_to_resolved_task(): void {
		$stub = new StubTask();

		Functions\expect( 'apply_filters' )
			->with( 'aipo_registered_tasks', \Mockery::type( 'array' ) )
			->andReturnUsing( static function ( array $tasks ) use ( $stub ): array {
				// Override 'name' task with our stub.
				$tasks['name'] = StubTask::class;
				return $tasks;
			} );

		// ContentHasher::compute + store calls.
		Functions\expect( 'wc_get_product' )->andReturn( false );
		Functions\expect( 'wp_json_encode' )->andReturnFirstArg();
		Functions\expect( 'update_post_meta' )->andReturn( true );

		// We can't easily inject the stub instance here since resolve_task
		// instantiates by class name. Verify the result type instead.
		$orch   = new GenerationOrchestrator();

		// Inject stub directly by pre-populating via resolve (white-box).
		$ref = new \ReflectionProperty( $orch, 'task_instances' );
		$ref->setAccessible( true );
		$ref->setValue( $orch, array( 'name' => $stub ) );

		$result = $orch->run_task( 42, 'name' );

		$this->assertSame( 1, $stub->run_called );
		$this->assertInstanceOf( TaskResult::class, $result );
		$this->assertSame( 42, $result->product_id );
	}

	// -----------------------------------------------------------------------
	// run_package — continues on failure
	// -----------------------------------------------------------------------

	public function test_run_package_continues_on_provider_exception(): void {
		$failing_stub    = new StubTask( true );
		$succeeding_stub = new StubTask( false );

		Functions\expect( 'apply_filters' )
			->with( 'aipo_registered_tasks', \Mockery::type( 'array' ) )
			->andReturnFirstArg();

		Functions\expect( 'wc_get_product' )->andReturn( false );
		Functions\expect( 'wp_json_encode' )->andReturnFirstArg();
		Functions\expect( 'update_post_meta' )->andReturn( true );

		$orch = new GenerationOrchestrator();

		$ref = new \ReflectionProperty( $orch, 'task_instances' );
		$ref->setAccessible( true );
		$ref->setValue( $orch, array(
			'name'       => $failing_stub,
			'short_desc' => $succeeding_stub,
		) );

		$results = $orch->run_package( 10, array( 'name', 'short_desc' ) );

		$this->assertCount( 2, $results );
		$this->assertSame( 'error', $results['name']->provider );
		$this->assertSame( 'openai', $results['short_desc']->provider );
	}

	public function test_run_package_uses_default_slugs_when_none_provided(): void {
		Functions\expect( 'apply_filters' )
			->with( 'aipo_registered_tasks', \Mockery::type( 'array' ) )
			->andReturnFirstArg();

		Functions\expect( 'wc_get_product' )->andReturn( false );
		Functions\expect( 'wp_json_encode' )->andReturnFirstArg();
		Functions\expect( 'update_post_meta' )->andReturn( true );

		$stub = new StubTask();
		$orch = new GenerationOrchestrator();

		// Inject stub for all default slugs.
		$ref = new \ReflectionProperty( $orch, 'task_instances' );
		$ref->setAccessible( true );
		$ref->setValue( $orch, array(
			'name'            => $stub,
			'short_desc'      => $stub,
			'long_desc'       => $stub,
			'seo_package'     => $stub,
			'search_keywords' => $stub,
		) );

		$results = $orch->run_package( 1 );

		// Default package is 5 tasks.
		$this->assertCount( 5, $results );
		$this->assertSame( 5, $stub->run_called );
	}
}
