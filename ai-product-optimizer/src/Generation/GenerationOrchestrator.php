<?php
/**
 * Generation orchestrator.
 *
 * Coordinates all generation tasks for a single product. Resolves the
 * correct AI provider for each task, calls the PromptBuilder, executes
 * the generation, and persists the results to post meta.
 *
 * @package AIProductOptimizer\Generation
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Generation;

use AIProductOptimizer\Cache\CacheManager;
use AIProductOptimizer\Integrations\RankMathBridge;
use AIProductOptimizer\Integrations\YoastBridge;
use AIProductOptimizer\Providers\Contracts\AIProviderInterface;
use AIProductOptimizer\Providers\ProviderFactory;
use AIProductOptimizer\Queue\JobLogger;

/**
 * Class GenerationOrchestrator
 */
class GenerationOrchestrator {

	/**
	 * Map of task slug → post meta key(s) for saving results.
	 *
	 * @var array<string, string|array<string>>
	 */
	private const TASK_META_MAP = array(
		'name'            => '_ai_optimizer_name',
		'short_desc'      => '_ai_optimizer_short_desc',
		'long_desc'       => '_ai_optimizer_long_desc',
		'seo_package'     => array(
			'seo_title'          => '_ai_optimizer_seo_title',
			'meta_description'   => '_ai_optimizer_meta_desc',
			'focus_keyword'      => '_ai_optimizer_focus_kw',
			'secondary_keywords' => '_ai_optimizer_secondary_kws',
			'og_title'           => '_ai_optimizer_og_title',
			'og_description'     => '_ai_optimizer_og_desc',
			'schema_hints'       => '_ai_optimizer_schema_hints',
		),
		'search_keywords' => '_ai_search_keywords',
		'alt_text'        => '_ai_optimizer_alt_texts',
	);

	private PromptBuilder $prompt_builder;
	private CacheManager $cache;
	private ContentHasher $hasher;
	private JobLogger $logger;

	public function __construct() {
		$this->prompt_builder = new PromptBuilder();
		$this->cache          = new CacheManager();
		$this->hasher         = new ContentHasher();
		$this->logger         = new JobLogger();
	}

	/**
	 * Run a single generation task for a product and persist results.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $task_slug  Task identifier.
	 * @return array{provider: string, model: string, tokens_used: int|null}
	 * @throws \AIProductOptimizer\Exceptions\ProviderException On generation failure.
	 */
	public function run_task( int $product_id, string $task_slug ): array {
		$provider = ProviderFactory::for_task( $task_slug );
		$prompt   = $this->prompt_builder->build( $task_slug, $product_id );

		// Check cache first.
		$hash      = $this->hasher->compute( $product_id );
		$cache_key = $this->cache->build_key( $product_id, $task_slug, $hash );
		$cached    = $this->cache->get( $cache_key );

		if ( false !== $cached ) {
			$this->save_task_result( $product_id, $task_slug, (string) $cached );
			return array( 'provider' => 'cache', 'model' => 'cache', 'tokens_used' => null );
		}

		// Execute generation.
		$raw_content = $provider->generate( $prompt );

		/**
		 * Filter generated content before it is saved.
		 *
		 * @param string $raw_content Generated text.
		 * @param string $task_slug   Task identifier.
		 * @param int    $product_id  Product ID.
		 */
		$content = (string) apply_filters( 'aipo_generated_content', $raw_content, $task_slug, $product_id );

		// Cache and persist.
		$this->cache->set( $cache_key, $content );
		$this->save_task_result( $product_id, $task_slug, $content );

		// Update generation timestamp.
		update_post_meta( $product_id, '_ai_optimizer_generated_at', gmdate( 'c' ) );
		update_post_meta( $product_id, '_ai_optimizer_provider_used', $provider->get_slug() );
		update_post_meta( $product_id, '_ai_optimizer_model_used', $this->get_model_for_task( $task_slug ) );

		/**
		 * Fires after a generation task's meta has been saved.
		 *
		 * @param int    $product_id  Product ID.
		 * @param string $task_slug   Task slug.
		 * @param string $content     Generated content.
		 */
		do_action( 'aipo_after_save_meta', $product_id, $task_slug, $content );

		// SEO plugin bridges.
		if ( 'seo_package' === $task_slug ) {
			YoastBridge::sync( $product_id );
			RankMathBridge::sync( $product_id );
		}

		return array(
			'provider'    => $provider->get_slug(),
			'model'       => $this->get_model_for_task( $task_slug ),
			'tokens_used' => null, // Populated by providers that expose token counts.
		);
	}

	/**
	 * Run all standard tasks for a product in sequence.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public function run_full_package( int $product_id ): void {
		$tasks = array( 'name', 'short_desc', 'long_desc', 'seo_package', 'search_keywords' );

		foreach ( $tasks as $task ) {
			$this->run_task( $product_id, $task );
		}
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Persist generated content to the appropriate post meta key(s).
	 *
	 * For the seo_package task, the content is JSON and is decoded into
	 * individual meta fields.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $task_slug  Task slug.
	 * @param string $content    Generated content (plain text or JSON string).
	 * @return void
	 */
	private function save_task_result( int $product_id, string $task_slug, string $content ): void {
		/**
		 * Fires before meta is saved.
		 *
		 * @param int    $product_id Product ID.
		 * @param string $task_slug  Task slug.
		 * @param string $content    Content about to be saved.
		 */
		do_action( 'aipo_before_save_meta', $product_id, $task_slug, $content );

		$meta_map = self::TASK_META_MAP[ $task_slug ] ?? null;

		if ( null === $meta_map ) {
			return;
		}

		if ( is_string( $meta_map ) ) {
			// Single meta key.
			$sanitized = in_array( $task_slug, array( 'long_desc' ), true )
				? wp_kses_post( $content )
				: sanitize_text_field( $content );

			update_post_meta( $product_id, $meta_map, $sanitized );
			return;
		}

		// seo_package: decode JSON and map to individual keys.
		$decoded = json_decode( $content, true );
		if ( ! is_array( $decoded ) ) {
			$this->logger->error( "seo_package JSON decode failed for product {$product_id}: {$content}" );
			return;
		}

		foreach ( $meta_map as $json_key => $meta_key ) {
			if ( ! isset( $decoded[ $json_key ] ) ) {
				continue;
			}

			$value = is_array( $decoded[ $json_key ] )
				? wp_json_encode( $decoded[ $json_key ] )
				: sanitize_text_field( (string) $decoded[ $json_key ] );

			update_post_meta( $product_id, $meta_key, $value );
		}
	}

	/**
	 * Return the configured model ID for a given task.
	 *
	 * @param string $task_slug Task slug.
	 * @return string
	 */
	private function get_model_for_task( string $task_slug ): string {
		$task_models = (array) get_option( 'aipo_task_models', array() );
		return $task_models[ $task_slug ]['model'] ?? 'default';
	}
}
