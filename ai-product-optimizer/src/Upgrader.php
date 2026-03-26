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
		// '1.1.0' => [ self::class, 'migrate_1_1_0' ],
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
	// Migration methods — add below as needed.
	// -----------------------------------------------------------------------

	// phpcs:disable
	// Example:
	// private static function migrate_1_1_0(): void { /* ... */ }
	// phpcs:enable
}
