<?php
/**
 * Exception thrown when an AI provider returns HTTP 429 (Too Many Requests).
 *
 * Carries the Retry-After header value so callers can honour the provider's
 * requested wait period.
 *
 * @package AIProductOptimizer\Exceptions
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Exceptions;

/**
 * Class RateLimitException
 */
class RateLimitException extends ProviderException {

	/**
	 * Seconds to wait before retrying, as indicated by the Retry-After header.
	 * Null if the header was not present.
	 *
	 * @var int|null
	 */
	private ?int $retry_after;

	/**
	 * Constructor.
	 *
	 * @param string   $message     Exception message.
	 * @param int|null $retry_after Retry-After value in seconds (or null).
	 */
	public function __construct( string $message = '', ?int $retry_after = null ) {
		parent::__construct( $message );
		$this->retry_after = $retry_after;
	}

	/**
	 * Return the Retry-After value in seconds, or null if not available.
	 *
	 * @return int|null
	 */
	public function get_retry_after(): ?int {
		return $this->retry_after;
	}
}
