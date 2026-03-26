<?php
/**
 * WP-CLI command registration.
 *
 * Registers the `wp ai-optimizer` command group.
 *
 * Full implementation: Phase 4.
 *
 * @package AIProductOptimizer\Cli
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Cli;

use AIProductOptimizer\Queue\QueueManager;

/**
 * Class CliCommands
 */
class CliCommands {

	/**
	 * Register commands with WP-CLI.
	 *
	 * @return void
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'ai-optimizer', self::class );
	}

	/**
	 * Generate AI content for one or all products.
	 *
	 * ## OPTIONS
	 *
	 * [--product-id=<id>]
	 * : Generate for a specific product ID.
	 *
	 * [--all]
	 * : Generate for all published products.
	 *
	 * [--type=<type>]
	 * : Content type: full|seo|desc|name. Default: full.
	 *
	 * [--dry-run]
	 * : Preview what would be generated without actually doing it.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ai-optimizer generate --all --type=full
	 *     wp ai-optimizer generate --product-id=123 --type=seo
	 *     wp ai-optimizer generate --all --dry-run
	 *
	 * @subcommand generate
	 * @param array<string> $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function generate( array $args, array $assoc_args ): void {
		$task_map = array(
			'full' => array( 'name', 'short_desc', 'long_desc', 'seo_package', 'search_keywords' ),
			'seo'  => array( 'seo_package', 'search_keywords' ),
			'desc' => array( 'short_desc', 'long_desc' ),
			'name' => array( 'name' ),
		);

		$type     = $assoc_args['type'] ?? 'full';
		$tasks    = $task_map[ $type ] ?? $task_map['full'];
		$dry_run  = isset( $assoc_args['dry-run'] );

		if ( isset( $assoc_args['product-id'] ) ) {
			$product_ids = array( absint( $assoc_args['product-id'] ) );
		} elseif ( isset( $assoc_args['all'] ) ) {
			$product_ids = get_posts( array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			) );
		} else {
			\WP_CLI::error( 'Please specify --product-id=<id> or --all.' );
			return;
		}

		$count = count( (array) $product_ids );

		if ( $dry_run ) {
			\WP_CLI::success( sprintf( 'Dry run: would queue %d product(s) for tasks: %s', $count, implode( ', ', $tasks ) ) );
			return;
		}

		$queue    = new QueueManager();
		$batch_id = $queue->enqueue_batch( (array) $product_ids, $tasks );

		\WP_CLI::success(
			sprintf( 'Queued %d product(s) in batch %s. Run `wp action-scheduler run` to process immediately.', $count, $batch_id )
		);
	}

	/**
	 * Show queue status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ai-optimizer queue --status
	 *
	 * @subcommand queue
	 * @param array<string> $args Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function queue( array $args, array $assoc_args ): void {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			\WP_CLI::error( 'Action Scheduler is not available.' );
			return;
		}

		$pending = as_get_scheduled_actions( array(
			'hook'     => AIPO_AS_HOOK,
			'status'   => \ActionScheduler_Store::STATUS_PENDING,
			'per_page' => -1,
		) );

		\WP_CLI::line( sprintf( 'Pending AIPO jobs: %d', count( $pending ) ) );
	}
}
