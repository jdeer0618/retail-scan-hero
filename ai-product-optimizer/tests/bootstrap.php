<?php
/**
 * PHPUnit bootstrap for AI Product Optimizer.
 *
 * - Unit tests: uses Brain\Monkey to mock WordPress functions.
 * - Integration tests: boots the full WordPress + WooCommerce test environment.
 *
 * @package AIProductOptimizer\Tests
 */

declare( strict_types=1 );

// Composer autoloader (includes Brain\Monkey and WP stubs).
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Detect whether we are running Unit or Integration tests by checking the
// testsuite name passed via --testsuite, or default to Unit.
$testsuite = getenv( 'PHPUNIT_TESTSUITE' ) ?: 'Unit';

if ( 'Integration' === $testsuite ) {
	// ------------------------------------------------------------------
	// Integration bootstrap: real WordPress + WooCommerce environment.
	// ------------------------------------------------------------------
	$wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';

	if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
		echo "Could not find WordPress test suite at: {$wp_tests_dir}\n";
		echo "Run bin/install-wp-tests.sh to set up the integration test environment.\n";
		exit( 1 );
	}

	// Load the WP test bootstrap.
	require_once $wp_tests_dir . '/includes/functions.php';

	tests_add_filter(
		'muplugins_loaded',
		static function (): void {
			// Activate WooCommerce.
			require_once getenv( 'WC_PLUGIN_DIR' ) . '/woocommerce.php';

			// Activate our plugin.
			require_once dirname( __DIR__ ) . '/ai-product-optimizer.php';
		}
	);

	require $wp_tests_dir . '/includes/bootstrap.php';
} else {
	// ------------------------------------------------------------------
	// Unit bootstrap: Brain\Monkey mocks for WordPress functions.
	// ------------------------------------------------------------------

	// Define constants that the plugin assumes exist.
	if ( ! defined( 'ABSPATH' ) )              { define( 'ABSPATH', '/tmp/wordpress/' ); }
	if ( ! defined( 'AIPO_VERSION' ) )         { define( 'AIPO_VERSION', '1.0.0' ); }
	if ( ! defined( 'AIPO_PLUGIN_FILE' ) )     { define( 'AIPO_PLUGIN_FILE', dirname( __DIR__ ) . '/ai-product-optimizer.php' ); }
	if ( ! defined( 'AIPO_PLUGIN_DIR' ) )      { define( 'AIPO_PLUGIN_DIR', dirname( __DIR__ ) . '/' ); }
	if ( ! defined( 'AIPO_PLUGIN_URL' ) )      { define( 'AIPO_PLUGIN_URL', 'http://localhost/wp-content/plugins/ai-product-optimizer/' ); }
	if ( ! defined( 'AIPO_PLUGIN_BASENAME' ) ) { define( 'AIPO_PLUGIN_BASENAME', 'ai-product-optimizer/ai-product-optimizer.php' ); }
	if ( ! defined( 'AIPO_MIN_PHP' ) )         { define( 'AIPO_MIN_PHP', '8.3' ); }
	if ( ! defined( 'AIPO_MIN_WP' ) )          { define( 'AIPO_MIN_WP', '6.9' ); }
	if ( ! defined( 'AIPO_MIN_WC' ) )          { define( 'AIPO_MIN_WC', '10.6.1' ); }
	if ( ! defined( 'AIPO_AS_HOOK' ) )         { define( 'AIPO_AS_HOOK', 'aipo_generate_product' ); }
	if ( ! defined( 'AIPO_TEXT_DOMAIN' ) )     { define( 'AIPO_TEXT_DOMAIN', 'ai-product-optimizer' ); }
	if ( ! defined( 'AUTH_KEY' ) )             { define( 'AUTH_KEY', 'test-auth-key-for-unit-tests-only' ); }
	if ( ! defined( 'SECURE_AUTH_KEY' ) )      { define( 'SECURE_AUTH_KEY', 'test-secure-auth-key-for-unit-tests' ); }
	if ( ! defined( 'SECURE_AUTH_SALT' ) )     { define( 'SECURE_AUTH_SALT', 'test-secure-auth-salt-for-unit-tests' ); }
	if ( ! defined( 'DAY_IN_SECONDS' ) )       { define( 'DAY_IN_SECONDS', 86400 ); }
}
