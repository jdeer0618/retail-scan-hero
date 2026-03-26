/**
 * AI Product Optimizer — Classic Editor product meta box entry point.
 *
 * @package AIProductOptimizer
 */

import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { MetaBoxApp } from './components/product/MetaBoxApp';

const { restUrl, nonce, productId } = window.aipoEditor || {};

if ( nonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( nonce ) );
}
if ( restUrl ) {
	apiFetch.use( apiFetch.createRootURLMiddleware( restUrl + '/' ) );
}

document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'aipo-product-meta-box' );
	if ( ! container || ! productId ) {
		return;
	}

	// Find the "Loading AI controls…" placeholder paragraph and replace it.
	const placeholder = container.querySelector( 'p > em' );
	const mountPoint  = placeholder ? placeholder.parentElement : document.createElement( 'div' );

	if ( placeholder ) {
		mountPoint.innerHTML = '';
	} else {
		container.appendChild( mountPoint );
	}

	const root = createRoot( mountPoint );
	root.render(
		<MetaBoxApp
			productId={ productId }
			restUrl={ restUrl }
			nonce={ nonce }
		/>
	);
} );
