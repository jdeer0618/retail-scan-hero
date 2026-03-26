<?php
/**
 * Contract for all AI generation tasks.
 *
 * Each concrete task (name, short_desc, long_desc, seo_package, etc.) must
 * implement this interface. Tasks are responsible for:
 *   1. Resolving their AI provider.
 *   2. Building the prompt via PromptBuilder.
 *   3. Checking the cache.
 *   4. Calling the provider.
 *   5. Post-processing the raw output (e.g. JSON decode for seo_package).
 *   6. Persisting results to post meta.
 *   7. Returning a TaskResult.
 *
 * @package AIProductOptimizer\Generation\Tasks\Contracts
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Generation\Tasks\Contracts;

use AIProductOptimizer\Generation\Tasks\TaskResult;

/**
 * Interface GenerationTaskInterface
 */
interface GenerationTaskInterface {

	/**
	 * Execute the generation task for a single product.
	 *
	 * @param int $product_id WooCommerce product post ID.
	 * @return TaskResult
	 * @throws \AIProductOptimizer\Exceptions\ProviderException On unrecoverable AI failure.
	 */
	public function run( int $product_id ): TaskResult;

	/**
	 * Return the unique machine-readable slug for this task.
	 *
	 * @return string  e.g. 'seo_package', 'name', 'search_keywords'
	 */
	public function get_slug(): string;

	/**
	 * Return the post meta keys that this task writes to.
	 * Used for cache invalidation and batch progress tracking.
	 *
	 * @return string[]
	 */
	public function get_meta_keys(): array;
}
