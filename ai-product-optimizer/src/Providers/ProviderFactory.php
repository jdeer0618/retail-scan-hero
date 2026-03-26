<?php
/**
 * AI Provider factory.
 *
 * Resolves the correct provider instance for a given task slug, honouring
 * the per-task model overrides and the global active/fallback provider settings.
 *
 * @package AIProductOptimizer\Providers
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Providers;

use AIProductOptimizer\Exceptions\ProviderException;
use AIProductOptimizer\Providers\Contracts\AIProviderInterface;
use AIProductOptimizer\Queue\JobLogger;

/**
 * Class ProviderFactory
 */
class ProviderFactory {

	/**
	 * Built-in provider class map (slug → FQCN).
	 * Third-party providers register via the aipo_registered_providers filter.
	 *
	 * @var array<string, class-string<AIProviderInterface>>
	 */
	private static array $built_in = array(
		'openai'    => OpenAIProvider::class,
		'anthropic' => AnthropicProvider::class,
		'gemini'    => GeminiProvider::class,
		'grok'      => GrokProvider::class,
		'ollama'    => OllamaProvider::class,
	);

	/**
	 * Return the correct provider for a generation task.
	 *
	 * Resolution order:
	 * 1. Per-task provider override (aipo_task_models option).
	 * 2. Global active provider (aipo_active_provider option).
	 * 3. Fallback provider (aipo_fallback_provider option).
	 *
	 * @param string $task_slug Task identifier.
	 * @return AIProviderInterface
	 * @throws ProviderException If no valid provider can be resolved.
	 */
	public static function for_task( string $task_slug ): AIProviderInterface {
		$task_models = (array) get_option( 'aipo_task_models', array() );
		$slug        = $task_models[ $task_slug ]['provider']
			?? (string) get_option( 'aipo_active_provider', 'openai' );

		try {
			return self::make( $slug );
		} catch ( ProviderException $e ) {
			// Try the fallback.
			$fallback = (string) get_option( 'aipo_fallback_provider', 'ollama' );

			if ( $fallback !== $slug ) {
				return self::make( $fallback );
			}

			throw $e;
		}
	}

	/**
	 * Instantiate a provider by slug.
	 *
	 * @param string $slug Provider slug.
	 * @return AIProviderInterface
	 * @throws ProviderException If the slug is unknown or the class doesn't implement the interface.
	 */
	public static function make( string $slug ): AIProviderInterface {
		/**
		 * Filter the registered provider class map.
		 *
		 * @param array<string, class-string<AIProviderInterface>> $providers
		 */
		$providers = (array) apply_filters( 'aipo_registered_providers', self::$built_in );

		if ( ! isset( $providers[ $slug ] ) ) {
			throw new ProviderException(
				sprintf( 'Unknown AI provider slug: "%s".', $slug )
			);
		}

		$class = $providers[ $slug ];

		if ( ! class_exists( $class ) ) {
			throw new ProviderException(
				sprintf( 'Provider class "%s" not found.', $class )
			);
		}

		$all_configs = (array) get_option( 'aipo_providers', array() );
		$config      = $all_configs[ $slug ] ?? array();
		$logger      = new JobLogger();

		$instance = new $class( $config, $logger );

		if ( ! $instance instanceof AIProviderInterface ) {
			throw new ProviderException(
				sprintf( 'Provider class "%s" must implement AIProviderInterface.', $class )
			);
		}

		return $instance;
	}
}
