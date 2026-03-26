<?php
/**
 * Unit tests for GenerateSEOPackageTask.
 *
 * Focuses on JSON extraction / character-limit enforcement / meta persistence,
 * since those are the task's most complex responsibilities.
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Generation\Tasks\GenerateSEOPackageTask;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class GenerateSEOPackageTaskTest
 */
class GenerateSEOPackageTaskTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Slug / keys
	// -----------------------------------------------------------------------

	public function test_get_slug(): void {
		$this->assertSame( 'seo_package', ( new GenerateSEOPackageTask() )->get_slug() );
	}

	public function test_get_meta_keys_covers_all_seo_fields(): void {
		$keys = ( new GenerateSEOPackageTask() )->get_meta_keys();

		foreach ( array(
			'_ai_optimizer_seo_title',
			'_ai_optimizer_meta_desc',
			'_ai_optimizer_focus_kw',
			'_ai_optimizer_secondary_kws',
			'_ai_optimizer_og_title',
			'_ai_optimizer_og_desc',
			'_ai_optimizer_schema_hints',
		) as $expected ) {
			$this->assertContains( $expected, $keys );
		}
	}

	// -----------------------------------------------------------------------
	// JSON extraction
	// -----------------------------------------------------------------------

	public function test_save_result_parses_clean_json(): void {
		$this->mock_seo_bridges();

		$saved = array();
		Functions\expect( 'update_post_meta' )
			->andReturnUsing( static function ( int $id, string $key, mixed $val ) use ( &$saved ): bool {
				$saved[ $key ] = $val;
				return true;
			} );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );

		$json = json_encode( array(
			'seo_title'         => 'Buy Premium Leather Wallet',
			'meta_description'  => 'Shop our handcrafted full-grain leather wallet.',
			'focus_keyword'     => 'leather wallet',
			'secondary_keywords' => array( 'bifold wallet', 'men\'s wallet' ),
			'og_title'          => 'Premium Leather Wallet',
			'og_description'    => 'Discover our premium collection.',
			'schema_hints'      => array( 'brand' => 'ACME', 'material' => 'leather' ),
		) );

		$task = new GenerateSEOPackageTask();
		$ref  = new \ReflectionMethod( $task, 'save_result' );
		$ref->setAccessible( true );
		$ref->invoke( $task, 1, $json );

		$this->assertArrayHasKey( '_ai_optimizer_seo_title', $saved );
		$this->assertArrayHasKey( '_ai_optimizer_focus_kw', $saved );
		$this->assertSame( 'Buy Premium Leather Wallet', $saved['_ai_optimizer_seo_title'] );
		$this->assertSame( 'leather wallet', $saved['_ai_optimizer_focus_kw'] );
	}

	public function test_save_result_strips_markdown_fences(): void {
		$this->mock_seo_bridges();

		$saved = array();
		Functions\expect( 'update_post_meta' )
			->andReturnUsing( static function ( int $id, string $key, mixed $val ) use ( &$saved ): bool {
				$saved[ $key ] = $val;
				return true;
			} );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );

		$content = "```json\n" . json_encode( array(
			'seo_title'        => 'Fenced JSON Title',
			'meta_description' => 'Meta from fenced JSON.',
		) ) . "\n```";

		$task = new GenerateSEOPackageTask();
		$ref  = new \ReflectionMethod( $task, 'save_result' );
		$ref->setAccessible( true );
		$ref->invoke( $task, 1, $content );

		$this->assertSame( 'Fenced JSON Title', $saved['_ai_optimizer_seo_title'] );
	}

	public function test_save_result_enforces_seo_title_60_char_limit(): void {
		$this->mock_seo_bridges();

		$saved = array();
		Functions\expect( 'update_post_meta' )
			->andReturnUsing( static function ( int $id, string $key, mixed $val ) use ( &$saved ): bool {
				$saved[ $key ] = $val;
				return true;
			} );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );

		$long_title = str_repeat( 'A', 80 ); // 80 chars — exceeds 60 limit.
		$json       = json_encode( array( 'seo_title' => $long_title ) );

		$task = new GenerateSEOPackageTask();
		$ref  = new \ReflectionMethod( $task, 'save_result' );
		$ref->setAccessible( true );
		$ref->invoke( $task, 1, $json );

		$this->assertSame( 60, strlen( $saved['_ai_optimizer_seo_title'] ) );
	}

	public function test_save_result_enforces_meta_description_160_char_limit(): void {
		$this->mock_seo_bridges();

		$saved = array();
		Functions\expect( 'update_post_meta' )
			->andReturnUsing( static function ( int $id, string $key, mixed $val ) use ( &$saved ): bool {
				$saved[ $key ] = $val;
				return true;
			} );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );

		$long_desc = str_repeat( 'B', 200 );
		$json      = json_encode( array( 'meta_description' => $long_desc ) );

		$task = new GenerateSEOPackageTask();
		$ref  = new \ReflectionMethod( $task, 'save_result' );
		$ref->setAccessible( true );
		$ref->invoke( $task, 1, $json );

		$this->assertSame( 160, strlen( $saved['_ai_optimizer_meta_desc'] ) );
	}

	public function test_save_result_logs_error_on_invalid_json(): void {
		// Should not throw — should log and return gracefully.
		Functions\expect( 'update_post_meta' )->never();
		Functions\expect( 'wc_get_logger' )
			->andReturn( $this->createMock( \WC_Logger_Interface::class ) );

		$task = new GenerateSEOPackageTask();
		$ref  = new \ReflectionMethod( $task, 'save_result' );
		$ref->setAccessible( true );
		$ref->invoke( $task, 1, 'this is not json at all' );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function mock_seo_bridges(): void {
		// YoastBridge::sync() and RankMathBridge::sync() call class_exists
		// which returns false in unit tests (no Yoast/RM installed).
		// No special mocking needed — they'll just return early.
	}
}
