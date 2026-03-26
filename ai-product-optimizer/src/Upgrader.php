<?php
/**
 * Database and option migration handler.
 *
 * Runs pending migrations in sequence whenever the stored plugin version
 * is lower than the current AIPO_VERSION constant. Each migration is
 * idempotent — running it twice must produce the same result.
 *
 * @package AIProductOptimizer
 */

declare( strict_types=1 );

namespace AIProductOptimizer;

/**
 * Class Upgrader
 */
class Upgrader {

	/**
	 * Migration registry: version string => callable.
	 *
	 * Add new migrations here in ascending version order.
	 *
	 * @var array<string, callable>
	 */
	private static array $migrations = array(
		'1.0.1' => array( self::class, 'migrate_1_0_1' ),
	);

	/**
	 * Run all migrations whose version is higher than $from_version.
	 *
	 * @param string $from_version The currently installed plugin version.
	 * @return void
	 */
	public static function run_migrations( string $from_version ): void {
		foreach ( self::$migrations as $version => $callable ) {
			if ( version_compare( $from_version, $version, '<' ) ) {
				call_user_func( $callable );
			}
		}
	}

	// -----------------------------------------------------------------------
	// Migration methods
	// -----------------------------------------------------------------------

	/**
	 * 1.0.1: Rename option keys for consistency with the React UI contract.
	 *
	 * - aipo_rankmath_bridge_enabled    → aipo_rank_math_bridge_enabled
	 * - aipo_rankmath_override_existing → aipo_rank_math_override_existing
	 * - aipo_cache_ttl_days             → aipo_cache_ttl (seconds)
	 * - aipo_schedule_cron              → aipo_cron_schedule
	 * - aipo_regenerate_after_days      → aipo_stale_threshold_days
	 *
	 * @return void
	 */
	private static function migrate_1_0_1(): void {
		$renames = array(
			'aipo_rankmath_bridge_enabled'    => 'aipo_rank_math_bridge_enabled',
			'aipo_rankmath_override_existing' => 'aipo_rank_math_override_existing',
			'aipo_schedule_cron'              => 'aipo_cron_schedule',
			'aipo_regenerate_after_days'      => 'aipo_stale_threshold_days',
		);

		foreach ( $renames as $old_key => $new_key ) {
			$old_value = get_option( $old_key );
			if ( false !== $old_value && false === get_option( $new_key ) ) {
				update_option( $new_key, $old_value, false );
			}
			delete_option( $old_key );
		}

		// Migrate cache_ttl_days → cache_ttl (seconds).
		$old_ttl_days = get_option( 'aipo_cache_ttl_days' );
		if ( false !== $old_ttl_days && false === get_option( 'aipo_cache_ttl' ) ) {
			update_option( 'aipo_cache_ttl', (int) $old_ttl_days * DAY_IN_SECONDS, false );
			delete_option( 'aipo_cache_ttl_days' );
		}
	}
}
