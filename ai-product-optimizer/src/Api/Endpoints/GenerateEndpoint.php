<?php
/**
 * POST /wp-json/aipo/v1/generate
 *
 * Enqueues a generation batch for one or more products and returns a
 * batch_id the client can poll against the progress endpoint.
 *
 * @package AIProductOptimizer\Api\Endpoints
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Api\Endpoints;

use AIProductOptimizer\Api\RestController;
use AIProductOptimizer\Queue\QueueManager;
use AIProductOptimizer\Security\RateLimiter;

/**
 * Class GenerateEndpoint
 */
class GenerateEndpoint {

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register(): void {
		register_rest_route(
			RestController::NAMESPACE,
			'/generate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'product_ids' => array(
						'required'          => true,
						'type'              => 'array',
						'items'             => array( 'type' => 'integer' ),
						'sanitize_callback' => static fn( array $ids ) => array_map( 'absint', $ids ),
					),
					'tasks' => array(
						'required'          => false,
						'type'              => 'array',
						'items'             => array( 'type' => 'string' ),
						'default'           => array( 'name', 'short_desc', 'long_desc', 'seo_package', 'search_keywords' ),
						'sanitize_callback' => static fn( array $tasks ) => array_map( 'sanitize_key', $tasks ),
					),
				),
			)
		);
	}

	/**
	 * Handle the generate request.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user_id = get_current_user_id();

		// Rate limiting.
		$limiter = new RateLimiter();
		if ( ! $limiter->check_and_increment( $user_id ) ) {
			return new \WP_Error(
				'aipo_rate_limited',
				__( 'Too many requests. Please wait before generating more content.', 'ai-product-optimizer' ),
				array(
					'status'      => 429,
					'retry_after' => 60,
				)
			);
		}

		$product_ids = (array) $request->get_param( 'product_ids' );
		$tasks       = (array) $request->get_param( 'tasks' );

		// Validate that product_ids belong to actual products the user can edit.
		$valid_ids = array_filter(
			$product_ids,
			static function ( int $id ): bool {
				return 'product' === get_post_type( $id ) && current_user_can( 'edit_post', $id );
			}
		);

		if ( empty( $valid_ids ) ) {
			return new \WP_Error(
				'aipo_no_valid_products',
				__( 'No valid products found in the provided IDs.', 'ai-product-optimizer' ),
				array( 'status' => 400 )
			);
		}

		$queue    = new QueueManager();
		$batch_id = $queue->enqueue_batch( array_values( $valid_ids ), $tasks );

		return new \WP_REST_Response(
			array(
				'batch_id'     => $batch_id,
				'queued_count' => count( $valid_ids ) * count( $tasks ),
				'product_ids'  => array_values( $valid_ids ),
				'tasks'        => $tasks,
			),
			202
		);
	}

	/**
	 * Verify the request has sufficient permissions.
	 *
	 * @return bool|\WP_Error
	 */
	public function check_permission(): bool|\WP_Error {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error(
				'aipo_forbidden',
				__( 'You do not have permission to generate AI content.', 'ai-product-optimizer' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}
}
