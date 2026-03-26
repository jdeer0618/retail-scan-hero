<?php
/**
 * Unit tests for YoastBridge.
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Integrations\YoastBridge;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class YoastBridgeTest
 */
class YoastBridgeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_sync_skips_when_yoast_not_active(): void {
		// WPSEO_Options class does NOT exist (Yoast not installed).
		// class_exists returns false automatically in unit tests.
		Functions\expect( 'get_option' )->never();
		Functions\expect( 'update_post_meta' )->never();

		YoastBridge::sync( 42 );
	}

	public function test_sync_skips_when_bridge_disabled(): void {
		// Simulate Yoast active by defining the class stub.
		if ( ! class_exists( 'WPSEO_Options' ) ) {
			eval( 'class WPSEO_Options {}' ); // phpcs:ignore Squiz.PHP.Eval
		}

		Functions\expect( 'get_option' )
			->with( 'aipo_yoast_bridge_enabled', true )
			->andReturn( false );

		Functions\expect( 'update_post_meta' )->never();

		YoastBridge::sync( 42 );
	}

	public function test_sync_does_not_overwrite_non_empty_yoast_field_by_default(): void {
		if ( ! class_exists( 'WPSEO_Options' ) ) {
			eval( 'class WPSEO_Options {}' ); // phpcs:ignore Squiz.PHP.Eval
		}

		Functions\expect( 'get_option' )
			->with( 'aipo_yoast_bridge_enabled', true )
			->andReturn( true );

		Functions\expect( 'get_option' )
			->with( 'aipo_yoast_override_existing', false )
			->andReturn( false ); // Do NOT override.

		// AI has a value.
		Functions\expect( 'get_post_meta' )
			->with( 42, '_ai_optimizer_seo_title', true )
			->andReturn( 'AI SEO Title' );

		// Yoast ALREADY has a value.
		Functions\expect( 'get_post_meta' )
			->with( 42, '_yoast_wpseo_title', true )
			->andReturn( 'Existing Yoast Title' );

		// Should NOT update.
		Functions\expect( 'update_post_meta' )->never();

		YoastBridge::sync( 42 );
	}

	public function test_sync_overwrites_yoast_field_when_override_enabled(): void {
		if ( ! class_exists( 'WPSEO_Options' ) ) {
			eval( 'class WPSEO_Options {}' ); // phpcs:ignore Squiz.PHP.Eval
		}

		Functions\expect( 'get_option' )
			->with( 'aipo_yoast_bridge_enabled', true )
			->andReturn( true );

		Functions\expect( 'get_option' )
			->with( 'aipo_yoast_override_existing', false )
			->andReturn( true ); // Override ON.

		Functions\expect( 'get_post_meta' )
			->with( 42, '_ai_optimizer_seo_title', true )
			->andReturn( 'New AI SEO Title' );

		Functions\expect( 'get_post_meta' )
			->with( 42, '_yoast_wpseo_title', true )
			->andReturn( 'Old Yoast Title' );

		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();

		// SHOULD update.
		Functions\expect( 'update_post_meta' )
			->once()
			->with( 42, '_yoast_wpseo_title', 'New AI SEO Title' );

		YoastBridge::sync( 42 );
	}

	public function test_sync_writes_when_yoast_field_empty(): void {
		if ( ! class_exists( 'WPSEO_Options' ) ) {
			eval( 'class WPSEO_Options {}' ); // phpcs:ignore Squiz.PHP.Eval
		}

		Functions\expect( 'get_option' )
			->with( 'aipo_yoast_bridge_enabled', true )
			->andReturn( true );

		Functions\expect( 'get_option' )
			->with( 'aipo_yoast_override_existing', false )
			->andReturn( false );

		Functions\expect( 'get_post_meta' )
			->with( 42, '_ai_optimizer_seo_title', true )
			->andReturn( 'AI SEO Title' );

		Functions\expect( 'get_post_meta' )
			->with( 42, '_yoast_wpseo_title', true )
			->andReturn( '' ); // Empty — should write.

		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();

		Functions\expect( 'update_post_meta' )
			->once()
			->with( 42, '_yoast_wpseo_title', 'AI SEO Title' );

		YoastBridge::sync( 42 );
	}
}
