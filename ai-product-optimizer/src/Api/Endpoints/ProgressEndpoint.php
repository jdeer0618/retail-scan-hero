<?php
/**
 * GET  /wp-json/aipo/v1/progress/{batch_id}
 * DELETE /wp-json/aipo/v1/progress/{batch_id}
 *
 * @package AIProductOptimizer\Api\Endpoints
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Api\Endpoints;

use AIProductOptimizer\Api\RestController;
use AIProductOptimizer\Queue\QueueManager;

/**
 * Class ProgressEndpoint
 */
class ProgressEndpoint {

	public function register(): void {
		register_rest_route(
			RestController::NAMESPACE,
			'/progress/(?P<batch_id>[0-9a-f\-]{36})',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_progress' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'cancel_batch' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	public function get_progress( \WP_REST_Request $request ): \WP_REST_Response {
		$batch_id = sanitize_text_field( $request->get_param( 'batch_id' ) );
		$queue    = new QueueManager();
		$progress = $queue->get_batch_progress( $batch_id );

		return new \WP_REST_Response( $progress, 200 );
	}

	public function cancel_batch( \WP_REST_Request $request ): \WP_REST_Response {
		$batch_id = sanitize_text_field( $request->get_param( 'batch_id' ) );
		$queue    = new QueueManager();
		$queue->cancel_batch( $batch_id );

		return new \WP_REST_Response( array( 'cancelled' => true ), 200 );
	}

	public function check_permission(): bool|\WP_Error {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error( 'aipo_forbidden', __( 'Forbidden.', 'ai-product-optimizer' ), array( 'status' => 403 ) );
		}
		return true;
	}
}
