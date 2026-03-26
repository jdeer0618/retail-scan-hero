<?php
/**
 * REST API route registration.
 *
 * Registers all /wp-json/aipo/v1/ endpoints and delegates to the
 * appropriate endpoint handler classes.
 *
 * @package AIProductOptimizer\Api
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Api;

use AIProductOptimizer\Api\Endpoints\GenerateEndpoint;
use AIProductOptimizer\Api\Endpoints\ProgressEndpoint;
use AIProductOptimizer\Api\Endpoints\ProvidersEndpoint;
use AIProductOptimizer\Api\Endpoints\SettingsEndpoint;

/**
 * Class RestController
 */
class RestController {

	public const NAMESPACE = 'aipo/v1';

	/**
	 * Register all plugin REST routes.
	 * Called on rest_api_init.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		( new GenerateEndpoint() )->register();
		( new ProgressEndpoint() )->register();
		( new ProvidersEndpoint() )->register();
		( new SettingsEndpoint() )->register();
	}
}
