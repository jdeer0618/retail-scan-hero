/**
 * Classic Editor product meta box React app.
 *
 * Hydrates the #aipo-product-meta-box container with per-task
 * generate buttons and a "Generate All" option.
 *
 * @package AIProductOptimizer
 */

import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { TaskButton } from './TaskButton';

const TASKS = [
	{ slug: 'name',            label: __( 'Name',            'ai-product-optimizer' ) },
	{ slug: 'short_desc',      label: __( 'Short Desc',      'ai-product-optimizer' ) },
	{ slug: 'long_desc',       label: __( 'Long Desc',       'ai-product-optimizer' ) },
	{ slug: 'seo_package',     label: __( 'SEO Package',     'ai-product-optimizer' ) },
	{ slug: 'search_keywords', label: __( 'Keywords',        'ai-product-optimizer' ) },
	{ slug: 'alt_text',        label: __( 'Alt Text',        'ai-product-optimizer' ) },
];

/**
 * @param {Object}  props
 * @param {number}  props.productId
 * @param {string}  props.restUrl
 * @param {string}  props.nonce
 */
export const MetaBoxApp = ( { productId, restUrl, nonce } ) => {
	// Configure apiFetch middleware once (idempotent).
	apiFetch.use( apiFetch.createNonceMiddleware( nonce ) );
	apiFetch.use( apiFetch.createRootURLMiddleware( restUrl + '/' ) );

	const generateAll = async () => {
		await apiFetch( {
			path:   '/aipo/v1/generate',
			method: 'POST',
			data:   {
				product_ids: [ productId ],
				tasks: TASKS.map( ( t ) => t.slug ),
			},
		} );
	};

	return (
		<div className="aipo-meta-box-app">
			<div className="aipo-task-list">
				{ TASKS.map( ( task ) => (
					<TaskButton
						key={ task.slug }
						task={ task.slug }
						label={ task.label }
						productId={ productId }
					/>
				) ) }
			</div>

			<hr className="aipo-divider" />

			<button
				type="button"
				className="button button-primary aipo-generate-all-btn"
				onClick={ generateAll }
			>
				{ __( 'Generate All', 'ai-product-optimizer' ) }
			</button>
		</div>
	);
};

export default MetaBoxApp;
