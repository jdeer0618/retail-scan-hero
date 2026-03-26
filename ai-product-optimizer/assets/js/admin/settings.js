/**
 * AI Product Optimizer — Settings page entry point.
 *
 * @package AIProductOptimizer
 */

import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { SettingsApp } from './components/settings/SettingsApp';

const { restUrl, nonce } = window.aipoSettings || {};

// Bootstrap apiFetch with WP REST credentials.
if ( nonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( nonce ) );
}
if ( restUrl ) {
	apiFetch.use( apiFetch.createRootURLMiddleware( restUrl + '/' ) );
}

// Mount settings React app.
document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'aipo-settings-app' )
		?? document.getElementById( 'aipo-onboarding-app' );

	if ( ! container ) {
		return;
	}

	const isOnboarding = !! document.getElementById( 'aipo-onboarding-app' );
	const initialTab   = isOnboarding ? 'providers' : 'general';

	// Clear server-rendered placeholder text.
	container.innerHTML = '';

	const root = createRoot( container );
	root.render( <SettingsApp initialTab={ initialTab } /> );
} );
