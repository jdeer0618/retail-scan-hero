/**
 * AI Product Optimizer — Settings page entry point.
 *
 * Full React implementation: Phase 4.
 *
 * @package AIProductOptimizer
 */

import { render } from '@wordpress/element';

// Placeholder until Phase 4 renders the full settings UI.
const SettingsApp = () => {
	const { restUrl, nonce, isOnboarding } = window.aipoSettings || {};

	return (
		<div className="aipo-settings-wrapper">
			<h2>{ isOnboarding ? 'Welcome — Setup Wizard' : 'AI Product Optimizer Settings' }</h2>
			<p>Settings UI coming in Phase 4.</p>
			<p>REST API: <code>{ restUrl }</code></p>
		</div>
	);
};

const container = document.getElementById( isOnboarding ? 'aipo-onboarding-app' : 'aipo-settings-app' );

if ( container ) {
	render( <SettingsApp />, container );
}
