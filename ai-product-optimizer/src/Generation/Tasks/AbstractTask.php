<?php
/**
 * Abstract base class for AI generation tasks.
 *
 * Handles the cache check → provider call → meta save pipeline.
 * Concrete tasks override save_result() for custom persistence logic
 * (e.g. SEOPackageTask decodes JSON into multiple meta keys).
 *
 * @package AIProductOptimizer\Generation\Tasks
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Generation\Tasks;

use AIProductOptimizer\Cache\CacheManager;
use AIProductOptimizer\Exceptions\ProviderException;
use AIProductOptimizer\Generation\ContentHasher;
use AIProductOptimizer\Generation\PromptBuilder;
use AIProductOptimizer\Generation\Tasks\Contracts\GenerationTaskInterface;
use AIProductOptimizer\Providers\ProviderFactory;
use AIProductOptimizer\Queue\JobLogger;

/**
 * Class AbstractTask
 */
abstract class AbstractTask implements GenerationTaskInterface {

	protected PromptBuilder $prompt_builder;
	protected CacheManager  $cache;
	protected ContentHasher $hasher;
	protected JobLogger     $logger;

	public function __construct() {
		$this->prompt_builder = new PromptBuilder();
		$this->cache          = new CacheManager();
		$this->hasher         = new ContentHasher();
		$this->logger         = new JobLogger();
	}

	// -----------------------------------------------------------------------
	// GenerationTaskInterface — run()
	// -----------------------------------------------------------------------

	/**
	 * {@inheritdoc}
	 */
	final public function run( int $product_id ): TaskResult {
		// Cache check.
		$hash      = $this->hasher->compute( $product_id );
		$cache_key = $this->cache->build_key( $product_id, $this->get_slug(), $hash );
		$cached    = $this->cache->get( $cache_key );

		if ( false !== $cached && '' !== $cached ) {
			$this->save_result( $product_id, (string) $cached );
			return TaskResult::from_cache( $this->get_slug(), $product_id, (string) $cached );
		}

		// Resolve provider & build prompt.
		$provider = ProviderFactory::for_task( $this->get_slug() );
		$prompt   = $this->prompt_builder->build( $this->get_slug(), $product_id );

		// Generate.
		$raw_content = $provider->generate( $prompt, $this->get_generation_options() );

		/**
		 * Filter the raw generated content before post-processing and save.
		 *
		 * @param string $raw_content  Raw AI output.
		 * @param string $task_slug    Task slug.
		 * @param int    $product_id   Product ID.
		 */
		$raw_content = (string) apply_filters(
			'aipo_generated_content',
			$raw_content,
			$this->get_slug(),
			$product_id
		);

		// Cache the raw content.
		$this->cache->set( $cache_key, $raw_content );

		// Persist.
		/**
		 * Fires before meta is saved for this task.
		 *
		 * @param int    $product_id Product ID.
		 * @param string $task_slug  Task slug.
		 * @param string $content    Content about to be saved.
		 */
		do_action( 'aipo_before_save_meta', $product_id, $this->get_slug(), $raw_content );

		$this->save_result( $product_id, $raw_content );

		// Stamp generation metadata.
		update_post_meta( $product_id, '_ai_optimizer_generated_at', gmdate( 'c' ) );
		update_post_meta( $product_id, '_ai_optimizer_provider_used', $provider->get_slug() );
		update_post_meta( $product_id, '_ai_optimizer_model_used', $this->resolve_model_id() );

		/**
		 * Fires after meta has been saved for this task.
		 *
		 * @param int    $product_id Product ID.
		 * @param string $task_slug  Task slug.
		 * @param string $content    Saved content.
		 */
		do_action( 'aipo_after_save_meta', $product_id, $this->get_slug(), $raw_content );

		return new TaskResult(
			task_slug:   $this->get_slug(),
			product_id:  $product_id,
			raw_content: $raw_content,
			provider:    $provider->get_slug(),
			model:       $this->resolve_model_id(),
		);
	}

	// -----------------------------------------------------------------------
	// Abstract — must be implemented by each concrete task
	// -----------------------------------------------------------------------

	/**
	 * Persist the (optionally post-processed) content to post meta.
	 *
	 * The default implementation writes a sanitized string to the first
	 * key returned by get_meta_keys(). Override for tasks that produce
	 * structured output (e.g. SEO package JSON → multiple fields).
	 *
	 * @param int    $product_id Product ID.
	 * @param string $content    Raw AI output.
	 * @return void
	 */
	protected function save_result( int $product_id, string $content ): void {
		$keys = $this->get_meta_keys();
		if ( ! empty( $keys ) ) {
			update_post_meta( $product_id, $keys[0], sanitize_text_field( $content ) );
		}
	}

	// -----------------------------------------------------------------------
	// Optional override — provider options per task
	// -----------------------------------------------------------------------

	/**
	 * Return provider generation options for this task.
	 * Override to customise temperature, max_tokens etc. per task.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_generation_options(): array {
		return array();
	}

	// -----------------------------------------------------------------------
	// Shared helper
	// -----------------------------------------------------------------------

	/**
	 * Resolve the configured model ID for this task.
	 *
	 * @return string
	 */
	private function resolve_model_id(): string {
		$task_models = (array) get_option( 'aipo_task_models', array() );
		return (string) ( $task_models[ $this->get_slug() ]['model'] ?? 'default' );
	}
}
