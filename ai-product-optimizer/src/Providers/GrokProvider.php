<?php
/**
 * xAI Grok provider.
 *
 * @package AIProductOptimizer\Providers
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Providers;

use AIProductOptimizer\Exceptions\ProviderException;

/**
 * Class GrokProvider
 */
class GrokProvider extends AbstractProvider {

	private const API_URL = 'https://api.x.ai/v1/chat/completions';

	public function get_slug(): string        { return 'grok'; }
	public function get_display_name(): string { return 'xAI Grok'; }

	public function get_available_models(): array {
		return array(
			'grok-2'      => 'Grok 2',
			'grok-2-mini' => 'Grok 2 Mini',
		);
	}

	protected function build_http_request( string $prompt, array $options ): array {
		$model      = $options['model'] ?? $this->config['model'] ?? 'grok-2';
		$max_tokens = $options['max_tokens'] ?? $this->config['max_tokens'] ?? 1024;

		return array(
			'url'     => self::API_URL,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->get_api_key(),
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'model'      => $model,
				'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
				'max_tokens' => (int) $max_tokens,
			) ),
			'timeout' => 60,
		);
	}

	protected function parse_response( array $raw ): string {
		$content = $raw['choices'][0]['message']['content'] ?? null;

		if ( null === $content ) {
			throw new ProviderException( 'Unexpected Grok response structure.' );
		}

		return trim( (string) $content );
	}
}
