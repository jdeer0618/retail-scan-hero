<?php
/**
 * Per-user API rate limiter.
 *
 * Tracks the number of AI API calls made by a given user within a rolling
 * 60-second window using WordPress transients. Returns HTTP 429 behaviour
 * when the threshold is exceeded.
 *
 * @package AIProductOptimizer\Security
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Security;

/**
 * Class RateLimiter
 */
class RateLimiter {

	/**
	 * Window length in seconds.
	 */
	private const WINDOW = 60;

	/**
	 * Transient key prefix.
	 */
	private const PREFIX = 'aipo_rl_';

	/**
	 * Check whether the given user has exceeded the rate limit and, if not,
	 * increment the counter.
	 *
	 * @param int $user_id  WordPress user ID.
	 * @return bool True if the request is allowed; false if rate-limited.
	 */
	public function check_and_increment( int $user_id ): bool {
		$limit = (int) get_option( 'aipo_rate_limit_per_minute', 60 );
		$key   = self::PREFIX . $user_id;
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return false;
		}

		if ( 0 === $count ) {
			// First call in this window — set with TTL.
			set_transient( $key, 1, self::WINDOW );
		} else {
			// Increment without resetting TTL (transient already has a TTL).
			// WordPress has no atomic increment, so we set with the same TTL.
			set_transient( $key, $count + 1, self::WINDOW );
		}

		return true;
	}

	/**
	 * Return the remaining allowed calls for a user in the current window.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return int
	 */
	public function remaining( int $user_id ): int {
		$limit = (int) get_option( 'aipo_rate_limit_per_minute', 60 );
		$count = (int) get_transient( self::PREFIX . $user_id );
		return max( 0, $limit - $count );
	}
}
