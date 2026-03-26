<?php
/**
 * Search keyword generation task.
 *
 * Generates 15–30 search-optimised keyword phrases and stores them
 * in the _ai_search_keywords meta field, which the SearchBoost
 * integration adds to WordPress's native LIKE search query.
 *
 * @package AIProductOptimizer\Generation\Tasks
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Generation\Tasks;

/**
 * Class GenerateSearchKeywordsTask
 */
class GenerateSearchKeywordsTask extends AbstractTask {

	public function get_slug(): string {
		return 'search_keywords';
	}

	public function get_meta_keys(): array {
		return array( '_ai_search_keywords' );
	}

	/**
	 * Balanced temperature — creative synonyms but still on-topic.
	 *
	 * {@inheritdoc}
	 */
	protected function get_generation_options(): array {
		return array( 'temperature' => 0.5 );
	}

	/**
	 * Parse the newline-delimited keyword list, deduplicate, enforce a
	 * maximum count, and store as a newline-separated string.
	 *
	 * The newline format is intentional: the SearchBoost LIKE query runs a
	 * single `meta_value LIKE '%{term}%'` comparison against the whole
	 * string. Newlines prevent unintentional partial-word matches across
	 * adjacent keywords.
	 *
	 * {@inheritdoc}
	 */
	protected function save_result( int $product_id, string $content ): void {
		$max = (int) get_option( 'aipo_search_keyword_count', 20 );

		$keywords = array_unique(
			array_filter(
				array_map(
					'trim',
					explode( "\n", $content )
				)
			)
		);

		// Limit to the configured maximum.
		$keywords = array_slice( array_values( $keywords ), 0, $max );

		if ( empty( $keywords ) ) {
			return;
		}

		// Store as lowercase newline-joined string for case-insensitive LIKE matches.
		$value = implode( "\n", array_map( 'mb_strtolower', $keywords ) );

		update_post_meta( $product_id, '_ai_search_keywords', $value );
	}
}
