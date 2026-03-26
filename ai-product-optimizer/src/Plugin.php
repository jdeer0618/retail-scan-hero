<?php
/**
 * Main plugin class.
 *
 * Singleton that owns the plugin lifecycle: registers all hooks via the
 * Loader, instantiates every module, and wires them together.
 *
 * @package AIProductOptimizer
 */

declare( strict_types=1 );

namespace AIProductOptimizer;

use AIProductOptimizer\Admin\AdminNotices;
use AIProductOptimizer\Admin\BulkActions;
use AIProductOptimizer\Admin\OnboardingWizard;
use AIProductOptimizer\Admin\ProductMetaBox;
use AIProductOptimizer\Admin\SettingsPage;
use AIProductOptimizer\Api\RestController;
use AIProductOptimizer\Cli\CliCommands;
use AIProductOptimizer\Integrations\SearchBoost;
use AIProductOptimizer\Queue\QueueManager;

/**
 * Class Plugin
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Hook loader.
	 *
	 * @var Loader
	 */
	private Loader $loader;

	/**
	 * Private constructor — use get_instance().
	 */
	private function __construct() {
		$this->loader = new Loader();
	}

	/**
	 * Retrieve (or create) the singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Bootstrap all plugin modules.
	 *
	 * Called once from the plugins_loaded hook in the main plugin file.
	 *
	 * @return void
	 */
	public function boot(): void {
		$this->run_upgrades();
		$this->load_textdomain();
		$this->register_post_meta();
		$this->init_modules();
		$this->loader->run();
	}

	// -----------------------------------------------------------------------
	// Private bootstrap helpers
	// -----------------------------------------------------------------------

	/**
	 * Run any pending database / option migrations.
	 *
	 * @return void
	 */
	private function run_upgrades(): void {
		$installed_version = get_option( 'aipo_version', '0.0.0' );

		if ( version_compare( $installed_version, AIPO_VERSION, '<' ) ) {
			Upgrader::run_migrations( $installed_version );
			update_option( 'aipo_version', AIPO_VERSION, false );
		}
	}

	/**
	 * Load the plugin text domain for i18n.
	 *
	 * @return void
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			AIPO_TEXT_DOMAIN,
			false,
			dirname( AIPO_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Register custom post meta keys so they are available via REST API
	 * and properly sanitized on save.
	 *
	 * @return void
	 */
	private function register_post_meta(): void {
		$string_meta_keys = array(
			'_ai_optimizer_name',
			'_ai_optimizer_short_desc',
			'_ai_optimizer_long_desc',
			'_ai_optimizer_seo_title',
			'_ai_optimizer_meta_desc',
			'_ai_optimizer_focus_kw',
			'_ai_optimizer_secondary_kws',
			'_ai_optimizer_og_title',
			'_ai_optimizer_og_desc',
			'_ai_optimizer_schema_hints',
			'_ai_optimizer_alt_texts',
			'_ai_search_keywords',
			'_ai_optimizer_content_hash',
			'_ai_optimizer_generated_at',
			'_ai_optimizer_provider_used',
			'_ai_optimizer_model_used',
		);

		foreach ( $string_meta_keys as $key ) {
			register_post_meta(
				'product',
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => static fn() => current_user_can( 'edit_products' ),
					'show_in_rest'      => false, // Managed via our own REST endpoints.
				)
			);
		}

		$bool_meta_keys = array(
			'_ai_optimizer_lock_name',
			'_ai_optimizer_lock_search',
			'_ai_optimizer_excluded',
		);

		foreach ( $bool_meta_keys as $key ) {
			register_post_meta(
				'product',
				$key,
				array(
					'type'              => 'boolean',
					'single'            => true,
					'sanitize_callback' => 'rest_sanitize_boolean',
					'auth_callback'     => static fn() => current_user_can( 'edit_products' ),
					'show_in_rest'      => false,
				)
			);
		}
	}

	/**
	 * Instantiate and wire all plugin modules.
	 *
	 * @return void
	 */
	private function init_modules(): void {
		// REST API.
		$rest = new RestController();
		$this->loader->add_action( 'rest_api_init', $rest, 'register_routes' );

		// Admin modules (only in admin context).
		if ( is_admin() ) {
			$settings = new SettingsPage();
			$settings->register( $this->loader );

			$meta_box = new ProductMetaBox();
			$meta_box->register( $this->loader );

			$bulk = new BulkActions();
			$bulk->register( $this->loader );

			$notices = new AdminNotices();
			$notices->register( $this->loader );

			$wizard = new OnboardingWizard();
			$wizard->register( $this->loader );
		}

		// Queue manager (needed for both admin and cron contexts).
		$queue = new QueueManager();
		$queue->register( $this->loader );

		// Front-end search boost.
		$search_boost = new SearchBoost();
		$search_boost->register( $this->loader );

		// WP-CLI commands.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			CliCommands::register();
		}

		/**
		 * Fires after all core modules are initialised.
		 * Third-party code can use this to register additional modules.
		 *
		 * @param Loader $loader The plugin hook loader.
		 */
		do_action( 'aipo_init', $this->loader );
	}
}
