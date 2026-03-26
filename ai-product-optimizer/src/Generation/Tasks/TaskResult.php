<?php
/**
 * Immutable value object returned by every generation task.
 *
 * @package AIProductOptimizer\Generation\Tasks
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Generation\Tasks;

/**
 * Class TaskResult
 */
final class TaskResult {

	/**
	 * Constructor.
	 *
	 * @param string   $task_slug    Task identifier (e.g. 'seo_package').
	 * @param int      $product_id   WooCommerce product ID.
	 * @param string   $raw_content  Raw text / JSON returned by the AI provider.
	 * @param string   $provider     Provider slug (or 'cache' if served from cache).
	 * @param string   $model        Model identifier used for this generation.
	 * @param int|null $tokens_used  Token count reported by the provider (if available).
	 * @param bool     $from_cache   Whether the result was served from cache.
	 * @param bool     $skipped      Whether the job was skipped (no change detected).
	 */
	public function __construct(
		public readonly string $task_slug,
		public readonly int $product_id,
		public readonly string $raw_content,
		public readonly string $provider = '',
		public readonly string $model = '',
		public readonly ?int $tokens_used = null,
		public readonly bool $from_cache = false,
		public readonly bool $skipped = false,
	) {}

	/**
	 * Create a "skipped" result (content unchanged, no generation needed).
	 *
	 * @param string $task_slug  Task slug.
	 * @param int    $product_id Product ID.
	 * @return self
	 */
	public static function skipped( string $task_slug, int $product_id ): self {
		return new self(
			task_slug:  $task_slug,
			product_id: $product_id,
			raw_content: '',
			skipped:    true,
		);
	}

	/**
	 * Create a result served from cache.
	 *
	 * @param string $task_slug   Task slug.
	 * @param int    $product_id  Product ID.
	 * @param string $content     Cached content.
	 * @return self
	 */
	public static function from_cache( string $task_slug, int $product_id, string $content ): self {
		return new self(
			task_slug:   $task_slug,
			product_id:  $product_id,
			raw_content: $content,
			provider:    'cache',
			model:       'cache',
			from_cache:  true,
		);
	}

	/**
	 * Serialise to a simple array for logging / REST responses.
	 *
	 * @return array{task_slug: string, product_id: int, provider: string, model: string, tokens_used: int|null, from_cache: bool, skipped: bool}
	 */
	public function to_array(): array {
		return array(
			'task_slug'   => $this->task_slug,
			'product_id'  => $this->product_id,
			'provider'    => $this->provider,
			'model'       => $this->model,
			'tokens_used' => $this->tokens_used,
			'from_cache'  => $this->from_cache,
			'skipped'     => $this->skipped,
		);
	}
}
