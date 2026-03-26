<?php
/**
 * Generation orchestrator.
 *
 * Maintains a registry of all generation tasks and provides a unified
 * entry-point for running one task or a full package for a product.
 *
 * The task registry is extensible via the aipo_registered_tasks filter,
 * allowing third-party code to add custom generation tasks without
 * modifying core files.
 *
 * @package AIProductOptimizer\Generation
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Generation;

use AIProductOptimizer\Exceptions\ProviderException;
use AIProductOptimizer\Generation\Tasks\Contracts\GenerationTaskInterface;
use AIProductOptimizer\Generation\Tasks\GenerateAltTextTask;
use AIProductOptimizer\Generation\Tasks\GenerateLongDescTask;
use AIProductOptimizer\Generation\Tasks\GenerateNameTask;
use AIProductOptimizer\Generation\Tasks\GenerateSEOPackageTask;
use AIProductOptimizer\Generation\Tasks\GenerateSearchKeywordsTask;
use AIProductOptimizer\Generation\Tasks\GenerateShortDescTask;
use AIProductOptimizer\Generation\Tasks\TaskResult;

/**
 * Class GenerationOrchestrator
 */
class GenerationOrchestrator {

	/**
	 * Built-in task registry: slug → FQCN.
	 *
	 * @var array<string, class-string<GenerationTaskInterface>>
	 */
	private const BUILT_IN_TASKS = array(
		'name'            => GenerateNameTask::class,
		'short_desc'      => GenerateShortDescTask::class,
		'long_desc'       => GenerateLongDescTask::class,
		'seo_package'     => GenerateSEOPackageTask::class,
		'search_keywords' => GenerateSearchKeywordsTask::class,
		'alt_text'        => GenerateAltTextTask::class,
	);

	/**
	 * Task instance cache (instantiated on demand).
	 *
	 * @var array<string, GenerationTaskInterface>
	 */
	private array $task_instances = array();

	// -----------------------------------------------------------------------
	// Public API
	// -----------------------------------------------------------------------

	/**
	 * Run a single generation task for a product.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $task_slug  Task identifier (must be registered).
	 * @return TaskResult
	 * @throws \InvalidArgumentException   If task slug is unknown.
	 * @throws ProviderException           If the AI provider fails.
	 */
	public function run_task( int $product_id, string $task_slug ): TaskResult {
		$task = $this->resolve_task( $task_slug );
		return $task->run( $product_id );
	}

	/**
	 * Run all standard tasks for a product in sequence.
	 * Skips alt_text unless explicitly included — it requires image URLs.
	 *
	 * @param int      $product_id    Product ID.
	 * @param string[] $task_slugs    Optional explicit list; defaults to full package.
	 * @return TaskResult[]           One result per task.
	 */
	public function run_package( int $product_id, array $task_slugs = array() ): array {
		if ( empty( $task_slugs ) ) {
			$task_slugs = array( 'name', 'short_desc', 'long_desc', 'seo_package', 'search_keywords' );
		}

		$results = array();

		foreach ( $task_slugs as $slug ) {
			try {
				$results[ $slug ] = $this->run_task( $product_id, $slug );
			} catch ( ProviderException $e ) {
				// Record failure but continue with remaining tasks.
				$results[ $slug ] = new TaskResult(
					task_slug:   $slug,
					product_id:  $product_id,
					raw_content: '',
					provider:    'error',
				);
			}
		}

		// Store the content hash after a successful package run so
		// subsequent batch passes skip this product until data changes.
		$hasher = new ContentHasher();
		$hasher->store( $product_id, $hasher->compute( $product_id ) );

		return $results;
	}

	/**
	 * Return all registered task slugs.
	 *
	 * @return string[]
	 */
	public function get_registered_slugs(): array {
		return array_keys( $this->get_task_registry() );
	}

	/**
	 * Return the task object for a given slug (cached after first resolution).
	 *
	 * @param string $task_slug Task slug.
	 * @return GenerationTaskInterface
	 * @throws \InvalidArgumentException On unknown slug.
	 */
	public function resolve_task( string $task_slug ): GenerationTaskInterface {
		if ( isset( $this->task_instances[ $task_slug ] ) ) {
			return $this->task_instances[ $task_slug ];
		}

		$registry = $this->get_task_registry();

		if ( ! isset( $registry[ $task_slug ] ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Unknown generation task slug: "%s". Registered: %s', $task_slug, implode( ', ', array_keys( $registry ) ) )
			);
		}

		$class = $registry[ $task_slug ];

		if ( ! class_exists( $class ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Task class "%s" not found for slug "%s".', $class, $task_slug )
			);
		}

		$instance = new $class();

		if ( ! $instance instanceof GenerationTaskInterface ) {
			throw new \InvalidArgumentException(
				sprintf( 'Task class "%s" must implement GenerationTaskInterface.', $class )
			);
		}

		$this->task_instances[ $task_slug ] = $instance;

		return $instance;
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Return the merged task registry (built-ins + third-party via filter).
	 *
	 * @return array<string, class-string<GenerationTaskInterface>>
	 */
	private function get_task_registry(): array {
		/**
		 * Filter the generation task registry.
		 *
		 * Third-party code can register new tasks or replace built-in ones:
		 *
		 *   add_filter( 'aipo_registered_tasks', function( array $tasks ): array {
		 *       $tasks['my_task'] = \MyPlugin\MyCustomTask::class;
		 *       return $tasks;
		 *   } );
		 *
		 * @param array<string, class-string<GenerationTaskInterface>> $tasks
		 */
		return (array) apply_filters( 'aipo_registered_tasks', self::BUILT_IN_TASKS );
	}
}
