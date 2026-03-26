/**
 * AI Product Optimizer — Bulk action progress modal entry point.
 *
 * Polls /progress/{batch_id} and renders a live progress bar + cancel
 * button inside the admin notice injected by BulkActions::show_bulk_result_notice().
 *
 * @package AIProductOptimizer
 */

import { createRoot, useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { ProgressBar } from './components/shared/ProgressBar';

const { restUrl, nonce } = window.aipoBulk || {};

if ( nonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( nonce ) );
}
if ( restUrl ) {
	apiFetch.use( apiFetch.createRootURLMiddleware( restUrl + '/' ) );
}

const POLL_MS = 2000;

/**
 * @param {Object} props
 * @param {string} props.batchId
 * @param {Element} props.notice   The admin notice DOM node (for dismiss on complete).
 */
const BulkProgressApp = ( { batchId, notice } ) => {
	const [ pct, setPct ]         = useState( 0 );
	const [ stats, setStats ]     = useState( null );
	const [ done, setDone ]       = useState( false );
	const [ cancelled, setCancelled ] = useState( false );
	const [ error, setError ]     = useState( null );

	useEffect( () => {
		if ( done || cancelled ) return;

		const id = setInterval( async () => {
			try {
				const data = await apiFetch( { path: `/aipo/v1/progress/${ batchId }` } );
				setPct( data.pct ?? 0 );
				setStats( data );
				if ( data.pct >= 100 ) {
					clearInterval( id );
					setDone( true );
				}
			} catch ( err ) {
				clearInterval( id );
				setError( err.message ?? __( 'Progress check failed.', 'ai-product-optimizer' ) );
			}
		}, POLL_MS );

		return () => clearInterval( id );
	}, [ batchId, done, cancelled ] );

	const handleCancel = async () => {
		try {
			await apiFetch( {
				path:   `/aipo/v1/progress/${ batchId }`,
				method: 'DELETE',
			} );
			setCancelled( true );
		} catch ( err ) {
			setError( err.message ?? __( 'Cancel failed.', 'ai-product-optimizer' ) );
		}
	};

	if ( cancelled ) {
		return (
			<p>{ __( 'Batch cancelled.', 'ai-product-optimizer' ) }</p>
		);
	}

	if ( error ) {
		return (
			<p className="aipo-progress-error" role="alert">{ error }</p>
		);
	}

	return (
		<div className="aipo-bulk-progress">
			<ProgressBar pct={ pct } label={ __( 'AI generation progress', 'ai-product-optimizer' ) } />

			{ stats && (
				<p className="aipo-bulk-progress__stats" aria-live="polite">
					{ done
						? __( 'Generation complete!', 'ai-product-optimizer' )
						: `${ stats.completed ?? 0 } / ${ stats.total ?? 0 } ${ __( 'products processed', 'ai-product-optimizer' ) }`
					}
					{ stats.failed > 0 && (
						<span className="aipo-bulk-progress__failed">
							{ ' ' }{ stats.failed } { __( 'failed — check logs.', 'ai-product-optimizer' ) }
						</span>
					) }
				</p>
			) }

			{ ! done && (
				<button
					type="button"
					className="button aipo-bulk-cancel-btn"
					onClick={ handleCancel }
				>
					{ __( 'Cancel', 'ai-product-optimizer' ) }
				</button>
			) }
		</div>
	);
};

document.addEventListener( 'DOMContentLoaded', () => {
	const notice  = document.getElementById( 'aipo-bulk-notice' );
	if ( ! notice ) return;

	const batchId = notice.dataset.batchId;
	if ( ! batchId ) return;

	// Append progress UI after existing notice text.
	const mount = document.createElement( 'div' );
	notice.appendChild( mount );

	const root = createRoot( mount );
	root.render( <BulkProgressApp batchId={ batchId } notice={ notice } /> );
} );
