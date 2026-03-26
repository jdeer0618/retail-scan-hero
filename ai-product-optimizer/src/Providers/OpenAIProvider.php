<?php
/**
 * OpenAI provider (GPT-4o / o1 / etc.).
 *
 * @package AIProductOptimizer\Providers
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Providers;

use AIProductOptimizer\Exceptions\ProviderException;
use AIProductOptimizer\Exceptions\RateLimitException;

/**
 * Class OpenAIProvider
 */
class OpenAIProvider extends AbstractProvider {

	private const API_URL = 'https://api.openai.com/v1/chat/completions';

	public function get_slug(): string        { return 'openai'; }
	public function get_display_name(): string { return 'OpenAI'; }

	public function get_available_models(): array {
		return array(
			'gpt-4o'              => 'GPT-4o',
			'gpt-4o-mini'         => 'GPT-4o Mini',
			'o1'                  => 'o1',
			'o1-mini'             => 'o1 Mini',
			'gpt-4-turbo'         => 'GPT-4 Turbo',
		);
	}

	protected function build_http_request( string $prompt, array $options ): array {
		$model       = $options['model'] ?? $this->config['model'] ?? 'gpt-4o';
		$temperature = $options['temperature'] ?? $this->config['temperature'] ?? 0.7;
		$max_tokens  = $options['max_tokens'] ?? $this->config['max_tokens'] ?? 1024;

		return array(
			'url'     => self::API_URL,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->get_api_key(),
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'model'       => $model,
				'messages'    => array(
					array( 'role' => 'user', 'content' => $prompt ),
				),
				'temperature' => (float) $temperature,
				'max_tokens'  => (int) $max_tokens,
			) ),
			'timeout' => 60,
		);
	}

	protected function parse_response( array $raw ): string {
		$content = $raw['choices'][0]['message']['content'] ?? null;

		if ( null === $content ) {
			throw new ProviderException( 'Unexpected OpenAI response structure.' );
		}

		return trim( (string) $content );
	}
}
