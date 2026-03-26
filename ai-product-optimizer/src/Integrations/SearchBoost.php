<?php
/**
 * Native WordPress search boost integration.
 *
 * Hooks into pre_get_posts and woocommerce_product_query to include
 * the _ai_search_keywords meta field in WP's default LIKE search,
 * making products findable by AI-generated synonym and keyword phrases
 * without any third-party search plugin.
 *
 * @package AIProductOptimizer\Integrations
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Integrations;

use AIProductOptimizer\Loader;

/**
 * Class SearchBoost
 */
class SearchBoost {

	/**
	 * Register hooks via the Loader.
	 *
	 * @param Loader $loader Plugin hook loader.
	 * @return void
	 */
	public function register( Loader $loader ): void {
		$loader->add_filter( 'pre_get_posts', $this, 'boost_wp_search', 10, 1 );
		$loader->add_filter( 'woocommerce_product_query', $this, 'boost_product_query', 10, 1 );
	}

	/**
	 * Inject _ai_search_keywords into WordPress search queries.
	 *
	 * Only activates when:
	 * - Search boost is enabled in settings.
	 * - The query is a true search (is_search()).
	 * - The query is the main query OR a WooCommerce product query.
	 *
	 * @param \WP_Query $query The current query object.
	 * @return void
	 */
	public function boost_wp_search( \WP_Query $query ): void {
		if ( ! $this->should_boost( $query ) ) {
			return;
		}

		$search_term = $query->get( 's' );

		if ( empty( $search_term ) ) {
			return;
		}

		$existing_meta_query = $query->get( 'meta_query' ) ?: array();

		$keywords_clause = array(
			'key'     => '_ai_search_keywords',
			'value'   => $search_term,
			'compare' => 'LIKE',
		);

		/**
		 * Filter the meta query clause appended for search boost.
		 *
		 * @param array     $keywords_clause The meta_query clause.
		 * @param \WP_Query $query           The current query.
		 */
		$keywords_clause = (array) apply_filters( 'aipo_search_meta_query', $keywords_clause, $query );

		// Wrap the new clause and any existing meta query in an OR relation
		// so that products matching either the standard WP title/content search
		// OR the keywords field will be returned.
		$query->set(
			'meta_query',
			array_merge(
				array( 'relation' => 'OR' ),
				$existing_meta_query,
				array( $keywords_clause )
			)
		);
	}

	/**
	 * Apply the search boost to WooCommerce product queries (AJAX search, widgets).
	 *
	 * @param \WP_Query $query The product query.
	 * @return void
	 */
	public function boost_product_query( \WP_Query $query ): void {
		$this->boost_wp_search( $query );
	}

	/**
	 * Whether this query should have the search boost applied.
	 *
	 * @param \WP_Query $query Query object.
	 * @return bool
	 */
	private function should_boost( \WP_Query $query ): bool {
		if ( ! (bool) get_option( 'aipo_search_boost_enabled', true ) ) {
			return false;
		}

		if ( ! $query->is_search() ) {
			return false;
		}

		// Avoid applying to admin queries or queries without a search string.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}

		return true;
	}
}
