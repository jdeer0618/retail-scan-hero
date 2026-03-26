<?php
/**
 * Google Gemini provider (gemini-2.0-pro / gemini-1.5-pro / etc.).
 *
 * @package AIProductOptimizer\Providers
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Providers;

use AIProductOptimizer\Exceptions\ProviderException;

/**
 * Class GeminiProvider
 */
class GeminiProvider extends AbstractProvider {

	private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

	public function get_slug(): string        { return 'gemini'; }
	public function get_display_name(): string { return 'Google Gemini'; }

	public function get_available_models(): array {
		return array(
			'gemini-2.0-pro'      => 'Gemini 2.0 Pro',
			'gemini-1.5-pro'      => 'Gemini 1.5 Pro',
			'gemini-1.5-flash'    => 'Gemini 1.5 Flash',
		);
	}

	protected function build_http_request( string $prompt, array $options ): array {
		$model      = $options['model'] ?? $this->config['model'] ?? 'gemini-2.0-pro';
		$max_tokens = $options['max_tokens'] ?? $this->config['max_tokens'] ?? 1024;
		$api_key    = $this->get_api_key();

		return array(
			'url'     => self::API_BASE . $model . ':generateContent?key=' . rawurlencode( $api_key ),
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array(
				'contents'         => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ),
				'generationConfig' => array( 'maxOutputTokens' => (int) $max_tokens ),
			) ),
			'timeout' => 60,
		);
	}

	protected function parse_response( array $raw ): string {
		$content = $raw['candidates'][0]['content']['parts'][0]['text'] ?? null;

		if ( null === $content ) {
			throw new ProviderException( 'Unexpected Gemini response structure.' );
		}

		return trim( (string) $content );
	}
}
