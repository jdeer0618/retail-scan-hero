/**
 * AI Product Optimizer — Bulk action progress modal entry point.
 *
 * Polls the /progress/{batch_id} endpoint and updates a progress bar
 * in the admin notice after a bulk generation is submitted.
 *
 * Full implementation: Phase 4.
 *
 * @package AIProductOptimizer
 */

import apiFetch from '@wordpress/api-fetch';

const { restUrl, nonce } = window.aipoBulk || {};

apiFetch.use( apiFetch.createNonceMiddleware( nonce ) );
apiFetch.use( apiFetch.createRootURLMiddleware( restUrl + '/' ) );

document.addEventListener( 'DOMContentLoaded', () => {
	const notice = document.getElementById( 'aipo-bulk-notice' );
	if ( ! notice ) return;

	const batchId = notice.dataset.batchId;
	if ( ! batchId ) return;

	// Phase 4: render a full React progress modal.
	// For now, poll and log progress.
	const poll = setInterval( async () => {
		try {
			const data = await apiFetch( { path: `/aipo/v1/progress/${ batchId }` } );
			console.log( '[AIPO] Progress:', data ); // eslint-disable-line no-console
			if ( data.pct >= 100 ) {
				clearInterval( poll );
			}
		} catch ( err ) {
			console.error( '[AIPO] Progress poll error:', err ); // eslint-disable-line no-console
			clearInterval( poll );
		}
	}, 2000 );
} );
