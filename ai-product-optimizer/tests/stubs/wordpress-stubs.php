<?php
/**
 * WordPress function stubs for PHPStan static analysis.
 *
 * These are minimal stubs only — the full stubs come from the
 * php-stubs/wordpress-stubs Composer package. This file adds
 * plugin-specific constants that PHPStan needs to resolve.
 *
 * @package AIProductOptimizer\Tests
 */

// Plugin constants (also declared in tests/bootstrap.php, but phpstan
// reads this file directly without running the bootstrap).
if ( ! defined( 'ABSPATH' ) )              { define( 'ABSPATH', '/tmp/wordpress/' ); }
if ( ! defined( 'AIPO_VERSION' ) )         { define( 'AIPO_VERSION', '1.0.0' ); }
if ( ! defined( 'AIPO_PLUGIN_FILE' ) )     { define( 'AIPO_PLUGIN_FILE', __DIR__ . '/../../ai-product-optimizer.php' ); }
if ( ! defined( 'AIPO_PLUGIN_DIR' ) )      { define( 'AIPO_PLUGIN_DIR', __DIR__ . '/../../' ); }
if ( ! defined( 'AIPO_PLUGIN_URL' ) )      { define( 'AIPO_PLUGIN_URL', 'http://localhost/wp-content/plugins/ai-product-optimizer/' ); }
if ( ! defined( 'AIPO_PLUGIN_BASENAME' ) ) { define( 'AIPO_PLUGIN_BASENAME', 'ai-product-optimizer/ai-product-optimizer.php' ); }
if ( ! defined( 'AIPO_MIN_PHP' ) )         { define( 'AIPO_MIN_PHP', '8.3' ); }
if ( ! defined( 'AIPO_MIN_WP' ) )          { define( 'AIPO_MIN_WP', '6.9' ); }
if ( ! defined( 'AIPO_MIN_WC' ) )          { define( 'AIPO_MIN_WC', '10.6.1' ); }
if ( ! defined( 'AIPO_AS_HOOK' ) )         { define( 'AIPO_AS_HOOK', 'aipo_generate_product' ); }
if ( ! defined( 'AIPO_TEXT_DOMAIN' ) )     { define( 'AIPO_TEXT_DOMAIN', 'ai-product-optimizer' ); }
if ( ! defined( 'AUTH_KEY' ) )             { define( 'AUTH_KEY', 'stub-auth-key' ); }
if ( ! defined( 'SECURE_AUTH_KEY' ) )      { define( 'SECURE_AUTH_KEY', 'stub-secure-auth-key' ); }
if ( ! defined( 'SECURE_AUTH_SALT' ) )     { define( 'SECURE_AUTH_SALT', 'stub-secure-auth-salt' ); }
if ( ! defined( 'DAY_IN_SECONDS' ) )       { define( 'DAY_IN_SECONDS', 86400 ); }
if ( ! defined( 'OPENSSL_RAW_DATA' ) )     { define( 'OPENSSL_RAW_DATA', 1 ); }
