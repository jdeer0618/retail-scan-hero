<?php
/**
 * Anthropic Claude provider (claude-opus-4-6, claude-sonnet-4-6, etc.).
 *
 * @package AIProductOptimizer\Providers
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Providers;

use AIProductOptimizer\Exceptions\ProviderException;

/**
 * Class AnthropicProvider
 */
class AnthropicProvider extends AbstractProvider {

	private const API_URL     = 'https://api.anthropic.com/v1/messages';
	private const API_VERSION = '2023-06-01';

	public function get_slug(): string        { return 'anthropic'; }
	public function get_display_name(): string { return 'Anthropic Claude'; }

	public function get_available_models(): array {
		return array(
			'claude-opus-4-6'    => 'Claude Opus 4.6',
			'claude-sonnet-4-6'  => 'Claude Sonnet 4.6',
			'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
		);
	}

	protected function build_http_request( string $prompt, array $options ): array {
		$model      = $options['model'] ?? $this->config['model'] ?? 'claude-sonnet-4-6';
		$max_tokens = $options['max_tokens'] ?? $this->config['max_tokens'] ?? 1024;

		return array(
			'url'     => self::API_URL,
			'headers' => array(
				'x-api-key'         => $this->get_api_key(),
				'anthropic-version' => self::API_VERSION,
				'Content-Type'      => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'model'      => $model,
				'max_tokens' => (int) $max_tokens,
				'messages'   => array(
					array( 'role' => 'user', 'content' => $prompt ),
				),
			) ),
			'timeout' => 60,
		);
	}

	protected function parse_response( array $raw ): string {
		$content = $raw['content'][0]['text'] ?? null;

		if ( null === $content ) {
			throw new ProviderException( 'Unexpected Anthropic response structure.' );
		}

		return trim( (string) $content );
	}
}
