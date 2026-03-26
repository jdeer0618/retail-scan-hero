/**
 * AI Product Optimizer — Classic Editor product meta box entry point.
 *
 * Full implementation: Phase 4.
 *
 * @package AIProductOptimizer
 */

import apiFetch from '@wordpress/api-fetch';

const { restUrl, nonce, productId } = window.aipoEditor || {};

apiFetch.use( apiFetch.createNonceMiddleware( nonce ) );
apiFetch.use( apiFetch.createRootURLMiddleware( restUrl + '/' ) );

// Phase 4: hydrate the meta box container with React components.
console.log( '[AIPO] Product editor loaded for product', productId ); // eslint-disable-line no-console
