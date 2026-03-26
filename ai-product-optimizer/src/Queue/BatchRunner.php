<?php
/**
 * Action Scheduler job processor.
 *
 * Executes a single product-generation job. Called by Action Scheduler
 * when the AIPO_AS_HOOK fires. Each job processes one product × one task.
 *
 * @package AIProductOptimizer\Queue
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Queue;

use AIProductOptimizer\Generation\ContentHasher;
use AIProductOptimizer\Generation\GenerationOrchestrator;

/**
 * Class BatchRunner
 */
class BatchRunner {

	/**
	 * Job logger.
	 *
	 * @var JobLogger
	 */
	private JobLogger $logger;

	/**
	 * Constructor.
	 *
	 * @param JobLogger $logger Job logger.
	 */
	public function __construct( JobLogger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Process a single queued generation job.
	 *
	 * This method is registered as the Action Scheduler callback for AIPO_AS_HOOK.
	 *
	 * @param int    $product_id WooCommerce product ID.
	 * @param string $task_slug  Generation task slug.
	 * @param string $batch_id   Batch UUID.
	 * @return void
	 */
	public function process_job( int $product_id, string $task_slug, string $batch_id ): void {
		$this->logger->update_job( $batch_id, $product_id, $task_slug, 'running' );

		// Check memory before proceeding.
		if ( ! $this->has_sufficient_memory() ) {
			$this->logger->update_job(
				$batch_id,
				$product_id,
				$task_slug,
				'failed',
				array( 'error_message' => 'Insufficient memory to process job.' )
			);
			return;
		}

		// Skip if product is marked excluded.
		if ( get_post_meta( $product_id, '_ai_optimizer_excluded', true ) ) {
			$this->logger->update_job( $batch_id, $product_id, $task_slug, 'skipped' );
			return;
		}

		// Skip if content hash is unchanged (unless forced via meta flag).
		$hasher = new ContentHasher();
		if ( ! $hasher->has_changed( $product_id ) ) {
			$forced = (bool) get_post_meta( $product_id, '_ai_optimizer_force_regenerate', true );
			if ( ! $forced ) {
				$this->logger->update_job( $batch_id, $product_id, $task_slug, 'skipped' );
				return;
			}
		}

		try {
			$orchestrator = new GenerationOrchestrator();
			$result       = $orchestrator->run_task( $product_id, $task_slug );

			$this->logger->update_job(
				$batch_id,
				$product_id,
				$task_slug,
				'completed',
				array(
					'provider'    => $result['provider'] ?? null,
					'model'       => $result['model'] ?? null,
					'tokens_used' => $result['tokens_used'] ?? null,
				)
			);

			// Update the content hash after successful generation.
			$hasher->store( $product_id, $hasher->compute( $product_id ) );

		} catch ( \Throwable $e ) {
			$this->logger->update_job(
				$batch_id,
				$product_id,
				$task_slug,
				'failed',
				array( 'error_message' => $e->getMessage() )
			);
		}
	}

	/**
	 * Check whether the current PHP process has enough memory to safely
	 * run a generation job (target: leave at least 32 MB headroom).
	 *
	 * @return bool
	 */
	private function has_sufficient_memory(): bool {
		$limit   = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
		$used    = memory_get_usage( true );
		$headroom = 32 * 1024 * 1024; // 32 MB.

		return $limit <= 0 || ( $limit - $used ) >= $headroom;
	}
}
