<?php
/**
 * Hook registration loader.
 *
 * Collects all action and filter registrations before committing them to
 * WordPress. This pattern makes unit-testing hook wiring straightforward
 * (inspect the collected arrays) without actually calling add_action /
 * add_filter.
 *
 * @package AIProductOptimizer
 */

declare( strict_types=1 );

namespace AIProductOptimizer;

/**
 * Class Loader
 */
class Loader {

	/**
	 * Collected action registrations.
	 *
	 * @var array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}>
	 */
	private array $actions = array();

	/**
	 * Collected filter registrations.
	 *
	 * @var array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}>
	 */
	private array $filters = array();

	/**
	 * Register an action hook to be added on run().
	 *
	 * @param string $hook          The WordPress action hook name.
	 * @param object $component     The object that owns the callback.
	 * @param string $callback      Method name on $component.
	 * @param int    $priority      Optional. Hook priority. Default 10.
	 * @param int    $accepted_args Optional. Number of arguments. Default 1.
	 * @return void
	 */
	public function add_action(
		string $hook,
		object $component,
		string $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Register a filter hook to be added on run().
	 *
	 * @param string $hook          The WordPress filter hook name.
	 * @param object $component     The object that owns the callback.
	 * @param string $callback      Method name on $component.
	 * @param int    $priority      Optional. Hook priority. Default 10.
	 * @param int    $accepted_args Optional. Number of arguments. Default 1.
	 * @return void
	 */
	public function add_filter(
		string $hook,
		object $component,
		string $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Commit all collected hooks to WordPress.
	 *
	 * Called once, at the end of Plugin::boot().
	 *
	 * @return void
	 */
	public function run(): void {
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}

	/**
	 * Return all registered actions (for testing).
	 *
	 * @return array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}>
	 */
	public function get_actions(): array {
		return $this->actions;
	}

	/**
	 * Return all registered filters (for testing).
	 *
	 * @return array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}>
	 */
	public function get_filters(): array {
		return $this->filters;
	}
}
