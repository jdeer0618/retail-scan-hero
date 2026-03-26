/**
 * Webpack configuration for AI Product Optimizer admin assets.
 *
 * Entry points compile to assets/build/. The output filenames match the
 * handles registered via wp_enqueue_script() in PHP.
 */

const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		'settings':       './assets/js/admin/settings.js',
		'product-editor': './assets/js/admin/product-editor.js',
		'bulk-progress':  './assets/js/admin/bulk-progress.js',
		'block-editor':   './assets/js/admin/block-editor/index.js',
	},
	output: {
		path: path.resolve( __dirname, 'assets/build' ),
		filename: '[name].js',
		chunkFilename: '[name].chunk.js',
		clean: true,
	},
	externals: {
		// Map @wordpress/* packages to the global wp.* object.
		'@wordpress/element':    [ 'wp', 'element' ],
		'@wordpress/components': [ 'wp', 'components' ],
		'@wordpress/i18n':       [ 'wp', 'i18n' ],
		'@wordpress/data':       [ 'wp', 'data' ],
		'@wordpress/plugins':    [ 'wp', 'plugins' ],
		'@wordpress/edit-post':  [ 'wp', 'editPost' ],
		'@wordpress/api-fetch':  [ 'wp', 'apiFetch' ],
		'@wordpress/hooks':      [ 'wp', 'hooks' ],
		'@wordpress/notices':    [ 'wp', 'notices' ],
		'@wordpress/compose':    [ 'wp', 'compose' ],
		react:                   'React',
		'react-dom':             'ReactDOM',
	},
};
