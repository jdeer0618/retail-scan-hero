<?php
/**
 * Structured job logger.
 *
 * Writes generation job events to the {prefix}aipo_job_log table and to the
 * WooCommerce logger (if available) for easier admin visibility.
 *
 * @package AIProductOptimizer\Queue
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Queue;

/**
 * Class JobLogger
 */
class JobLogger {

	/**
	 * WooCommerce logger handle.
	 */
	private const WC_LOG_HANDLE = 'ai-product-optimizer';

	/**
	 * Log an informational message.
	 *
	 * @param string $message Log message.
	 * @param array<string, mixed> $context Optional context data.
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( 'info', $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message Log message.
	 * @param array<string, mixed> $context Optional context data.
	 * @return void
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message Log message.
	 * @param array<string, mixed> $context Optional context data.
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}

	/**
	 * Update a job row in the job log table.
	 *
	 * @param string               $batch_id   Batch UUID.
	 * @param int                  $product_id Product ID.
	 * @param string               $task_slug  Task identifier.
	 * @param string               $status     New status value.
	 * @param array<string, mixed> $extra      Optional additional columns (provider, model, tokens_used, error_message).
	 * @return void
	 */
	public function update_job(
		string $batch_id,
		int $product_id,
		string $task_slug,
		string $status,
		array $extra = array()
	): void {
		global $wpdb;

		$now  = current_time( 'mysql', true );
		$data = array_merge(
			array(
				'status'     => $status,
				'updated_at' => $now,
			),
			array_intersect_key(
				$extra,
				array_flip( array( 'provider', 'model', 'tokens_used', 'error_message' ) )
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$wpdb->prefix . 'aipo_job_log',
			$data,
			array(
				'batch_id'   => $batch_id,
				'product_id' => $product_id,
				'task_slug'  => $task_slug,
			),
			null,
			array( '%s', '%d', '%s' )
		);
	}

	/**
	 * Insert a new job row into the job log table.
	 *
	 * @param string $batch_id   Batch UUID.
	 * @param int    $product_id Product ID.
	 * @param string $task_slug  Task identifier.
	 * @return void
	 */
	public function insert_job( string $batch_id, int $product_id, string $task_slug ): void {
		global $wpdb;

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$wpdb->prefix . 'aipo_job_log',
			array(
				'batch_id'   => $batch_id,
				'product_id' => $product_id,
				'task_slug'  => $task_slug,
				'status'     => 'queued',
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Internal log dispatcher.
	 *
	 * @param string               $level   PSR-3 level string.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Context data.
	 * @return void
	 */
	private function log( string $level, string $message, array $context ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->{ $level }( $message, array_merge( array( 'source' => self::WC_LOG_HANDLE ), $context ) );
		}
	}
}
