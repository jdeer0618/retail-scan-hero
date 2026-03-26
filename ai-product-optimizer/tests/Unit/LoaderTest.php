<?php
/**
 * Unit tests for the Loader class.
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Loader;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Class LoaderTest
 */
class LoaderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_add_action_stores_entry(): void {
		$loader    = new Loader();
		$component = new \stdClass();

		$loader->add_action( 'init', $component, 'my_method' );

		$actions = $loader->get_actions();
		$this->assertCount( 1, $actions );
		$this->assertSame( 'init', $actions[0]['hook'] );
		$this->assertSame( $component, $actions[0]['component'] );
		$this->assertSame( 'my_method', $actions[0]['callback'] );
		$this->assertSame( 10, $actions[0]['priority'] );
		$this->assertSame( 1, $actions[0]['accepted_args'] );
	}

	public function test_add_filter_stores_entry(): void {
		$loader    = new Loader();
		$component = new \stdClass();

		$loader->add_filter( 'the_content', $component, 'filter_content', 5, 2 );

		$filters = $loader->get_filters();
		$this->assertCount( 1, $filters );
		$this->assertSame( 'the_content', $filters[0]['hook'] );
		$this->assertSame( 5, $filters[0]['priority'] );
		$this->assertSame( 2, $filters[0]['accepted_args'] );
	}

	public function test_run_calls_add_action_for_each_entry(): void {
		$loader    = new Loader();
		$component = new \stdClass();

		$loader->add_action( 'init', $component, 'on_init' );
		$loader->add_action( 'admin_menu', $component, 'on_menu' );

		Functions\expect( 'add_action' )->twice();
		Functions\expect( 'add_filter' )->never();

		$loader->run();
	}

	public function test_run_calls_add_filter_for_each_entry(): void {
		$loader    = new Loader();
		$component = new \stdClass();

		$loader->add_filter( 'the_title', $component, 'filter_title' );

		Functions\expect( 'add_action' )->never();
		Functions\expect( 'add_filter' )->once();

		$loader->run();
	}

	public function test_multiple_actions_and_filters_run_correctly(): void {
		$loader    = new Loader();
		$component = new \stdClass();

		$loader->add_action( 'init', $component, 'a' );
		$loader->add_action( 'shutdown', $component, 'b' );
		$loader->add_filter( 'the_content', $component, 'c' );

		Functions\expect( 'add_action' )->twice();
		Functions\expect( 'add_filter' )->once();

		$loader->run();

		$this->assertCount( 2, $loader->get_actions() );
		$this->assertCount( 1, $loader->get_filters() );
	}
}
