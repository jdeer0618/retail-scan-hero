<?php
/**
 * AI Provider contract.
 *
 * Every AI provider (OpenAI, Anthropic, Gemini, Grok, Ollama, …) must
 * implement this interface. Concrete classes live in
 * src/Providers/ and extend AbstractProvider, which handles the shared
 * retry / rate-limit / circuit-breaker logic.
 *
 * @package AIProductOptimizer\Providers\Contracts
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Providers\Contracts;

use AIProductOptimizer\Exceptions\ProviderException;

/**
 * Interface AIProviderInterface
 */
interface AIProviderInterface {

	/**
	 * Execute a text-generation request.
	 *
	 * @param string               $prompt  Fully-assembled prompt string.
	 * @param array<string, mixed> $options Provider-specific options
	 *                                      (e.g. temperature, max_tokens).
	 * @return string Generated text content.
	 * @throws ProviderException On unrecoverable API errors.
	 */
	public function generate( string $prompt, array $options = array() ): string;

	/**
	 * Verify provider credentials and connectivity.
	 *
	 * Should return true when the provider is reachable and the API key
	 * (if required) is valid. Should not throw — catch internally and
	 * return false on failure.
	 *
	 * @return bool
	 */
	public function test_connection(): bool;

	/**
	 * Return available model identifiers for this provider.
	 *
	 * @return array<string, string>  [ 'model_id' => 'Human-Readable Label' ]
	 */
	public function get_available_models(): array;

	/**
	 * Return the unique machine-readable slug for this provider.
	 *
	 * Must match the key used in plugin options (e.g. 'openai', 'anthropic').
	 *
	 * @return string
	 */
	public function get_slug(): string;

	/**
	 * Return the human-readable display name for this provider.
	 *
	 * @return string
	 */
	public function get_display_name(): string;
}
