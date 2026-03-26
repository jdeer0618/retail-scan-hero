<?php
/**
 * Integration test: Activator creates the database table and seeds options.
 *
 * Requires a real WordPress installation. Run with:
 *   PHPUNIT_TESTSUITE=Integration ./vendor/bin/phpunit --testsuite Integration
 *
 * @package AIProductOptimizer\Tests\Integration
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Integration;

use AIProductOptimizer\Activator;
use WP_UnitTestCase;

/**
 * Class ActivatorIntegrationTest
 */
class ActivatorIntegrationTest extends WP_UnitTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		// Ensure plugin constants are defined.
		if ( ! defined( 'AIPO_PLUGIN_BASENAME' ) ) {
			define( 'AIPO_PLUGIN_BASENAME', 'ai-product-optimizer/ai-product-optimizer.php' );
		}
	}

	public function test_activation_creates_job_log_table(): void {
		global $wpdb;

		Activator::activate();

		$table  = $wpdb->prefix . 'aipo_job_log';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		$this->assertSame( $table, $exists, "Table {$table} should exist after activation." );
	}

	public function test_activation_seeds_default_options(): void {
		Activator::activate();

		$this->assertSame( 'professional', get_option( 'aipo_default_tone' ) );
		$this->assertSame( 'openai', get_option( 'aipo_active_provider' ) );
		$this->assertTrue( (bool) get_option( 'aipo_yoast_bridge_enabled' ) );
		$this->assertTrue( (bool) get_option( 'aipo_search_boost_enabled' ) );
		$this->assertSame( 20, (int) get_option( 'aipo_search_keyword_count' ) );
	}

	public function test_activation_does_not_overwrite_existing_options(): void {
		// Pre-set an option with a custom value.
		update_option( 'aipo_brand_voice', 'My existing brand voice' );

		Activator::activate();

		// add_option() used in seeding — should NOT overwrite.
		$this->assertSame( 'My existing brand voice', get_option( 'aipo_brand_voice' ) );
	}

	public function test_deactivation_does_not_drop_table(): void {
		global $wpdb;

		Activator::activate();
		Activator::deactivate();

		$table  = $wpdb->prefix . 'aipo_job_log';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		$this->assertSame( $table, $exists, 'Table should NOT be dropped on deactivation.' );
	}

	protected function tearDown(): void {
		global $wpdb;

		// Clean up: drop the table after each test so tests are idempotent.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aipo_job_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		delete_option( 'aipo_version' );
		delete_option( 'aipo_brand_voice' );

		parent::tearDown();
	}
}
