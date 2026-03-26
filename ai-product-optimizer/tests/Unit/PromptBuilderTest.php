<?php
/**
 * Unit tests for PromptBuilder.
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Generation\PromptBuilder;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class PromptBuilderTest
 */
class PromptBuilderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Set up the common WP function mocks needed for PromptBuilder::build().
	 *
	 * @param int $product_id
	 * @return void
	 */
	private function mock_product_context( int $product_id ): void {
		// Product retrieval — return null so we hit the fallback paths.
		Functions\expect( 'wc_get_product' )
			->with( $product_id )
			->andReturn( false );

		Functions\expect( 'get_option' )->andReturnMap( array(
			array( 'aipo_prompt_templates', array(), array() ),
			array( 'aipo_brand_voice', '', 'Premium brand' ),
			array( 'aipo_brand_affix', '', '' ),
			array( 'aipo_default_tone', 'professional', 'professional' ),
			array( 'aipo_custom_tone', '', '' ),
			array( 'aipo_output_length', 'medium', 'medium' ),
			array( 'aipo_custom_word_count', 300, 300 ),
			array( 'aipo_name_max_chars', 70, 70 ),
			array( 'aipo_name_variants', 3, 3 ),
			array( 'aipo_search_keyword_count', 20, 20 ),
		) );

		Functions\expect( 'get_locale' )->andReturn( 'en_US' );
		Functions\expect( 'get_post_meta' )->andReturn( '' );
		Functions\expect( 'wp_json_encode' )->andReturnFirstArg();

		Functions\expect( 'apply_filters' )
			->with( \Mockery::pattern( '/aipo_prompt_template_/' ), \Mockery::type( 'string' ), $product_id )
			->andReturnFirstArg();

		Functions\expect( 'apply_filters' )
			->with( 'aipo_prompt_context', \Mockery::type( 'array' ), $product_id )
			->andReturnFirstArg();
	}

	// -----------------------------------------------------------------------
	// System prompt is always prepended
	// -----------------------------------------------------------------------

	public function test_build_prepends_system_prompt(): void {
		$this->mock_product_context( 1 );

		$builder = new PromptBuilder();
		$prompt  = $builder->build( 'name', 1 );

		$this->assertStringContainsString( 'e-commerce copywriter', $prompt );
		$this->assertStringContainsString( 'Brand voice:', $prompt );
	}

	// -----------------------------------------------------------------------
	// Token substitution
	// -----------------------------------------------------------------------

	public function test_build_substitutes_brand_voice_token(): void {
		$this->mock_product_context( 2 );

		$builder = new PromptBuilder();
		$prompt  = $builder->build( 'name', 2 );

		$this->assertStringContainsString( 'Premium brand', $prompt );
		$this->assertStringNotContainsString( '{brand_voice}', $prompt );
	}

	public function test_build_substitutes_tone_token(): void {
		$this->mock_product_context( 3 );

		$builder = new PromptBuilder();
		$prompt  = $builder->build( 'short_desc', 3 );

		$this->assertStringContainsString( 'professional', $prompt );
		$this->assertStringNotContainsString( '{tone}', $prompt );
	}

	public function test_build_substitutes_locale_token(): void {
		$this->mock_product_context( 4 );

		$builder = new PromptBuilder();
		$prompt  = $builder->build( 'seo_package', 4 );

		$this->assertStringContainsString( 'en_US', $prompt );
		$this->assertStringNotContainsString( '{locale}', $prompt );
	}

	// -----------------------------------------------------------------------
	// Custom template override via option
	// -----------------------------------------------------------------------

	public function test_build_uses_custom_template_from_option(): void {
		$product_id = 5;

		Functions\expect( 'wc_get_product' )->andReturn( false );
		Functions\expect( 'get_locale' )->andReturn( 'en_US' );
		Functions\expect( 'get_post_meta' )->andReturn( '' );
		Functions\expect( 'wp_json_encode' )->andReturnFirstArg();

		Functions\expect( 'get_option' )->andReturnMap( array(
			array( 'aipo_prompt_templates', array(), array( 'name' => 'My custom template for {product_name}' ) ),
			array( 'aipo_brand_voice', '', 'Brand' ),
			array( 'aipo_brand_affix', '', '' ),
			array( 'aipo_default_tone', 'professional', 'professional' ),
			array( 'aipo_custom_tone', '', '' ),
			array( 'aipo_output_length', 'medium', 'medium' ),
			array( 'aipo_custom_word_count', 300, 300 ),
			array( 'aipo_name_max_chars', 70, 70 ),
			array( 'aipo_name_variants', 3, 3 ),
			array( 'aipo_search_keyword_count', 20, 20 ),
		) );

		Functions\expect( 'apply_filters' )
			->with( 'aipo_prompt_template_name', \Mockery::type( 'string' ), $product_id )
			->andReturnFirstArg(); // Return the user template.

		Functions\expect( 'apply_filters' )
			->with( 'aipo_prompt_context', \Mockery::type( 'array' ), $product_id )
			->andReturnFirstArg();

		$builder = new PromptBuilder();
		$prompt  = $builder->build( 'name', $product_id );

		$this->assertStringContainsString( 'My custom template for', $prompt );
	}

	// -----------------------------------------------------------------------
	// task-specific prompts exist for all supported tasks
	// -----------------------------------------------------------------------

	/** @dataProvider task_slug_provider */
	public function test_build_returns_non_empty_prompt_for_all_tasks( string $task ): void {
		$this->mock_product_context( 99 );

		$builder = new PromptBuilder();
		$prompt  = $builder->build( $task, 99 );

		$this->assertNotEmpty( $prompt );
	}

	/** @return array<array{string}> */
	public static function task_slug_provider(): array {
		return array(
			array( 'name' ),
			array( 'short_desc' ),
			array( 'long_desc' ),
			array( 'seo_package' ),
			array( 'search_keywords' ),
			array( 'alt_text' ),
		);
	}
}
