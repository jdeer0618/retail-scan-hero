<?php
/**
 * Action Scheduler queue manager.
 *
 * Wraps Action Scheduler to provide a clean API for enqueueing, cancelling,
 * and querying AI generation jobs.
 *
 * @package AIProductOptimizer\Queue
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Queue;

use AIProductOptimizer\Loader;

/**
 * Class QueueManager
 */
class QueueManager {

	/**
	 * Action Scheduler group name.
	 */
	public const GROUP = 'aipo';

	/**
	 * Register Action Scheduler hooks via the Loader.
	 *
	 * @param Loader $loader Plugin hook loader.
	 * @return void
	 */
	public function register( Loader $loader ): void {
		$runner = new BatchRunner( new JobLogger() );

		// The AS hook fires with (product_id, task_slug, batch_id).
		$loader->add_action( AIPO_AS_HOOK, $runner, 'process_job', 10, 3 );
		$loader->add_action( 'aipo_scheduled_batch', $this, 'run_scheduled_batch' );
	}

	/**
	 * Enqueue a batch of generation jobs.
	 *
	 * @param int[]    $product_ids  Array of WooCommerce product IDs.
	 * @param string[] $task_slugs   Array of task slugs to run per product.
	 * @return string                Batch UUID.
	 */
	public function enqueue_batch( array $product_ids, array $task_slugs ): string {
		$batch_id = wp_generate_uuid4();
		$logger   = new JobLogger();

		foreach ( $product_ids as $product_id ) {
			foreach ( $task_slugs as $task_slug ) {
				$logger->insert_job( $batch_id, (int) $product_id, $task_slug );

				as_enqueue_async_action(
					AIPO_AS_HOOK,
					array(
						'product_id' => (int) $product_id,
						'task_slug'  => $task_slug,
						'batch_id'   => $batch_id,
					),
					self::GROUP
				);
			}
		}

		return $batch_id;
	}

	/**
	 * Cancel all pending jobs belonging to a batch.
	 *
	 * @param string $batch_id Batch UUID.
	 * @return void
	 */
	public function cancel_batch( string $batch_id ): void {
		global $wpdb;

		// Fetch all pending AS action IDs for this batch.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$action_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT a.id
				 FROM {$wpdb->prefix}actionscheduler_actions a
				 INNER JOIN {$wpdb->prefix}actionscheduler_groups g ON a.group_id = g.group_id
				 WHERE g.slug = %s
				   AND a.hook = %s
				   AND a.status = 'pending'
				   AND a.args LIKE %s",
				self::GROUP,
				AIPO_AS_HOOK,
				'%' . $wpdb->esc_like( $batch_id ) . '%'
			)
		);

		foreach ( $action_ids as $action_id ) {
			as_unschedule_action( AIPO_AS_HOOK, null, self::GROUP );
		}

		// Mark remaining queued jobs as cancelled in our log.
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'aipo_job_log',
			array( 'status' => 'cancelled', 'updated_at' => current_time( 'mysql', true ) ),
			array( 'batch_id' => $batch_id, 'status' => 'queued' ),
			array( '%s', '%s' ),
			array( '%s', '%s' )
		);
	}

	/**
	 * Return progress stats for a batch.
	 *
	 * @param string $batch_id Batch UUID.
	 * @return array{total: int, completed: int, failed: int, skipped: int, cancelled: int, pct: int}
	 */
	public function get_batch_progress( string $batch_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS cnt
				 FROM {$wpdb->prefix}aipo_job_log
				 WHERE batch_id = %s
				 GROUP BY status",
				$batch_id
			),
			ARRAY_A
		);

		$counts = array(
			'total'     => 0,
			'completed' => 0,
			'failed'    => 0,
			'skipped'   => 0,
			'cancelled' => 0,
			'running'   => 0,
		);

		foreach ( $rows as $row ) {
			$counts[ $row['status'] ] = (int) $row['cnt'];
			$counts['total']         += (int) $row['cnt'];
		}

		$done = $counts['completed'] + $counts['failed'] + $counts['skipped'] + $counts['cancelled'];
		$pct  = $counts['total'] > 0 ? (int) round( ( $done / $counts['total'] ) * 100 ) : 0;

		return array_merge( $counts, array( 'pct' => $pct ) );
	}

	/**
	 * Handler for the scheduled batch Action Scheduler hook.
	 *
	 * Queues generation for all products that are missing content or
	 * whose content is older than the configured threshold.
	 *
	 * @return void
	 */
	public function run_scheduled_batch(): void {
		if ( ! (bool) get_option( 'aipo_schedule_enabled', false ) ) {
			return;
		}

		$product_ids = $this->get_products_needing_generation();

		if ( empty( $product_ids ) ) {
			return;
		}

		$this->enqueue_batch( $product_ids, array( 'name', 'short_desc', 'long_desc', 'seo_package', 'search_keywords' ) );
	}

	/**
	 * Return an array of product IDs that need (re-)generation.
	 *
	 * @return int[]
	 */
	private function get_products_needing_generation(): array {
		$exclude_cats = (array) get_option( 'aipo_exclude_categories', array() );
		$regen_days   = (int) get_option( 'aipo_regenerate_after_days', 0 );

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_ai_optimizer_excluded',
					'compare' => 'NOT EXISTS',
				),
			),
		);

		if ( ! empty( $exclude_cats ) ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $exclude_cats,
					'operator' => 'NOT IN',
				),
			);
		}

		if ( $regen_days > 0 ) {
			$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$regen_days} days" ) );
			$args['meta_query'][] = array(
				'relation' => 'OR',
				array(
					'key'     => '_ai_optimizer_generated_at',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_ai_optimizer_generated_at',
					'value'   => $cutoff,
					'compare' => '<',
					'type'    => 'DATETIME',
				),
			);
		}

		return (array) get_posts( $args );
	}
}
