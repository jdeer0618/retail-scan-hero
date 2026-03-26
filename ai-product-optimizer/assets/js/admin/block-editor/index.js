/**
 * AI Product Optimizer — Gutenberg sidebar plugin entry point.
 *
 * Registers the "AI Optimizer" PluginSidebar in the block editor
 * with per-task generate buttons and status feedback.
 *
 * @package AIProductOptimizer
 */

import { useState, useEffect } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar } from '@wordpress/edit-post';
import { PanelBody, Button, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

const TASKS = [
	{ slug: 'name',            label: __( 'Product Name',      'ai-product-optimizer' ) },
	{ slug: 'short_desc',      label: __( 'Short Description', 'ai-product-optimizer' ) },
	{ slug: 'long_desc',       label: __( 'Long Description',  'ai-product-optimizer' ) },
	{ slug: 'seo_package',     label: __( 'SEO Package',       'ai-product-optimizer' ) },
	{ slug: 'search_keywords', label: __( 'Search Keywords',   'ai-product-optimizer' ) },
	{ slug: 'alt_text',        label: __( 'Image Alt Text',    'ai-product-optimizer' ) },
];

const POLL_MS = 2000;

/**
 * Sidebar panel with per-task generate buttons.
 */
const AiOptimizerSidebar = () => {
	const postId   = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostId(), [] );
	const postType = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostType(), [] );

	const [ taskState, setTaskState ] = useState( {} ); // { slug: 'idle'|'running'|'done'|'error' }
	const [ notice, setNotice ]       = useState( null ); // { type, message }

	// Only show for product post type.
	if ( postType !== 'product' ) {
		return null;
	}

	const runTask = async ( slug ) => {
		setTaskState( ( prev ) => ( { ...prev, [ slug ]: 'running' } ) );
		setNotice( null );

		try {
			const { batch_id } = await apiFetch( {
				path:   '/aipo/v1/generate',
				method: 'POST',
				data:   { product_ids: [ postId ], tasks: [ slug ] },
			} );

			const poll = setInterval( async () => {
				try {
					const progress = await apiFetch( { path: `/aipo/v1/progress/${ batch_id }` } );
					if ( progress.pct >= 100 ) {
						clearInterval( poll );
						const failed = progress.failed ?? 0;
						if ( failed > 0 ) {
							setTaskState( ( prev ) => ( { ...prev, [ slug ]: 'error' } ) );
							setNotice( { type: 'error', message: __( 'Generation failed. Check WooCommerce logs.', 'ai-product-optimizer' ) } );
						} else {
							setTaskState( ( prev ) => ( { ...prev, [ slug ]: 'done' } ) );
							setNotice( { type: 'success', message: __( 'Generated! Save the post to see updates.', 'ai-product-optimizer' ) } );
						}
					}
				} catch {
					clearInterval( poll );
					setTaskState( ( prev ) => ( { ...prev, [ slug ]: 'error' } ) );
				}
			}, POLL_MS );
		} catch ( err ) {
			setTaskState( ( prev ) => ( { ...prev, [ slug ]: 'error' } ) );
			setNotice( { type: 'error', message: err.message ?? __( 'Request failed.', 'ai-product-optimizer' ) } );
		}
	};

	const runAll = () => {
		TASKS.forEach( ( t ) => runTask( t.slug ) );
	};

	return (
		<PluginSidebar
			name="aipo-sidebar"
			title={ __( 'AI Optimizer', 'ai-product-optimizer' ) }
			icon="buddicons-activity"
		>
			{ notice && (
				<Notice
					status={ notice.type }
					isDismissible
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			<PanelBody
				title={ __( 'Generate Content', 'ai-product-optimizer' ) }
				initialOpen={ true }
			>
				{ TASKS.map( ( task ) => {
					const state = taskState[ task.slug ] ?? 'idle';
					return (
						<div key={ task.slug } className="aipo-sidebar-task">
							<Button
								variant="secondary"
								className="aipo-sidebar-task__btn"
								onClick={ () => runTask( task.slug ) }
								disabled={ state === 'running' }
								isBusy={ state === 'running' }
							>
								{ state === 'running' && <Spinner /> }
								{ task.label }
							</Button>
							{ state === 'done'  && <span className="aipo-sidebar-task__done" aria-label={ __( 'Done', 'ai-product-optimizer' ) }>✓</span> }
							{ state === 'error' && <span className="aipo-sidebar-task__error" aria-label={ __( 'Error', 'ai-product-optimizer' ) }>✕</span> }
						</div>
					);
				} ) }
			</PanelBody>

			<PanelBody
				title={ __( 'Bulk Actions', 'ai-product-optimizer' ) }
				initialOpen={ false }
			>
				<Button
					variant="primary"
					className="aipo-sidebar-generate-all"
					onClick={ runAll }
					disabled={ TASKS.some( ( t ) => ( taskState[ t.slug ] ?? 'idle' ) === 'running' ) }
				>
					{ __( 'Generate All', 'ai-product-optimizer' ) }
				</Button>
			</PanelBody>
		</PluginSidebar>
	);
};

registerPlugin( 'aipo-sidebar', { render: AiOptimizerSidebar } );
