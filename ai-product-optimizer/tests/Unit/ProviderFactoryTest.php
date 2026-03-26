<?php
/**
 * Unit tests for ProviderFactory.
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Exceptions\ProviderException;
use AIProductOptimizer\Providers\AnthropicProvider;
use AIProductOptimizer\Providers\GeminiProvider;
use AIProductOptimizer\Providers\GrokProvider;
use AIProductOptimizer\Providers\OllamaProvider;
use AIProductOptimizer\Providers\OpenAIProvider;
use AIProductOptimizer\Providers\ProviderFactory;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class ProviderFactoryTest
 */
class ProviderFactoryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// make() — built-in slugs
	// -----------------------------------------------------------------------

	/** @dataProvider built_in_provider_map */
	public function test_make_returns_correct_class_for_built_in_slug( string $slug, string $expected_class ): void {
		Functions\expect( 'apply_filters' )
			->with( 'aipo_registered_providers', \Mockery::type( 'array' ) )
			->andReturnFirstArg();

		Functions\expect( 'get_option' )
			->with( 'aipo_providers', array() )
			->andReturn( array() );

		Functions\expect( 'wc_get_logger' )->andReturn( $this->createMock( \WC_Logger_Interface::class ) );

		$provider = ProviderFactory::make( $slug );

		$this->assertInstanceOf( $expected_class, $provider );
	}

	/** @return array<array{string, class-string}> */
	public static function built_in_provider_map(): array {
		return array(
			array( 'openai',    OpenAIProvider::class ),
			array( 'anthropic', AnthropicProvider::class ),
			array( 'gemini',    GeminiProvider::class ),
			array( 'grok',      GrokProvider::class ),
			array( 'ollama',    OllamaProvider::class ),
		);
	}

	// -----------------------------------------------------------------------
	// make() — unknown slug
	// -----------------------------------------------------------------------

	public function test_make_throws_for_unknown_slug(): void {
		Functions\expect( 'apply_filters' )
			->with( 'aipo_registered_providers', \Mockery::type( 'array' ) )
			->andReturnFirstArg();

		$this->expectException( ProviderException::class );
		$this->expectExceptionMessageMatches( '/Unknown AI provider slug/' );

		ProviderFactory::make( 'nonexistent-provider' );
	}

	// -----------------------------------------------------------------------
	// make() — custom provider via filter
	// -----------------------------------------------------------------------

	public function test_make_returns_custom_provider_registered_via_filter(): void {
		Functions\expect( 'apply_filters' )
			->with( 'aipo_registered_providers', \Mockery::type( 'array' ) )
			->andReturnUsing( static function ( array $providers ): array {
				$providers['fakeprovider'] = FakeProvider::class;
				return $providers;
			} );

		Functions\expect( 'get_option' )
			->with( 'aipo_providers', array() )
			->andReturn( array() );

		Functions\expect( 'wc_get_logger' )->andReturn( $this->createMock( \WC_Logger_Interface::class ) );

		$provider = ProviderFactory::make( 'fakeprovider' );

		$this->assertInstanceOf( FakeProvider::class, $provider );
	}

	// -----------------------------------------------------------------------
	// for_task() — per-task override
	// -----------------------------------------------------------------------

	public function test_for_task_uses_per_task_model_override(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_task_models', array() )
			->andReturn( array( 'seo_package' => array( 'provider' => 'anthropic', 'model' => 'claude-sonnet-4-6' ) ) );

		Functions\expect( 'apply_filters' )
			->with( 'aipo_registered_providers', \Mockery::type( 'array' ) )
			->andReturnFirstArg();

		Functions\expect( 'get_option' )
			->with( 'aipo_providers', array() )
			->andReturn( array() );

		Functions\expect( 'wc_get_logger' )->andReturn( $this->createMock( \WC_Logger_Interface::class ) );

		$provider = ProviderFactory::for_task( 'seo_package' );

		$this->assertInstanceOf( AnthropicProvider::class, $provider );
	}

	public function test_for_task_falls_back_to_active_provider(): void {
		Functions\expect( 'get_option' )
			->with( 'aipo_task_models', array() )
			->andReturn( array() ); // No per-task override.

		Functions\expect( 'get_option' )
			->with( 'aipo_active_provider', 'openai' )
			->andReturn( 'openai' );

		Functions\expect( 'apply_filters' )
			->with( 'aipo_registered_providers', \Mockery::type( 'array' ) )
			->andReturnFirstArg();

		Functions\expect( 'get_option' )
			->with( 'aipo_providers', array() )
			->andReturn( array() );

		Functions\expect( 'wc_get_logger' )->andReturn( $this->createMock( \WC_Logger_Interface::class ) );

		$provider = ProviderFactory::for_task( 'name' );

		$this->assertInstanceOf( OpenAIProvider::class, $provider );
	}

	public function test_for_task_falls_back_to_fallback_provider_on_failure(): void {
		// Active provider slug is a bad one; fallback is ollama.
		Functions\expect( 'get_option' )
			->with( 'aipo_task_models', array() )
			->andReturn( array() );

		Functions\expect( 'get_option' )
			->with( 'aipo_active_provider', 'openai' )
			->andReturn( 'bad-slug' );

		Functions\expect( 'get_option' )
			->with( 'aipo_fallback_provider', 'ollama' )
			->andReturn( 'ollama' );

		Functions\expect( 'apply_filters' )
			->with( 'aipo_registered_providers', \Mockery::type( 'array' ) )
			->andReturnFirstArg();

		Functions\expect( 'get_option' )
			->with( 'aipo_providers', array() )
			->andReturn( array() );

		Functions\expect( 'wc_get_logger' )->andReturn( $this->createMock( \WC_Logger_Interface::class ) );

		$provider = ProviderFactory::for_task( 'name' );

		$this->assertInstanceOf( OllamaProvider::class, $provider );
	}
}
