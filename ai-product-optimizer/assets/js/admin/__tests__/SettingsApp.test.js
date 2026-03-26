/**
 * Jest tests for the SettingsApp React component.
 *
 * @package AIProductOptimizer
 */

import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { SettingsApp } from '../components/settings/SettingsApp';

// ── Mock @wordpress/api-fetch ───────────────────────────────────────

const mockApiFetch = jest.fn();
jest.mock( '@wordpress/api-fetch', () => {
	const fn = ( ...args ) => mockApiFetch( ...args );
	fn.use = jest.fn();
	return fn;
} );

// ── Mock @wordpress/i18n (passthrough) ────────────────────────────

jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
	_n: ( single, plural, n ) => ( n === 1 ? single : plural ),
} ) );

// ── Mock @wordpress/element (use real React under the hood) ────────

jest.mock( '@wordpress/element', () => ( {
	...jest.requireActual( 'react' ),
	createRoot: jest.requireActual( 'react-dom/client' ).createRoot,
} ) );

// ── Helpers ─────────────────────────────────────────────────────────

const MOCK_SETTINGS = {
	aipo_enabled:                  true,
	aipo_search_boost_enabled:     true,
	aipo_search_keyword_count:     20,
	aipo_name_max_chars:           70,
	aipo_alt_text_auto_apply:      false,
	aipo_active_provider:          'openai',
	aipo_fallback_provider:        '',
	aipo_cache_ttl:                86400,
	aipo_circuit_breaker_threshold: 10,
};

const MOCK_PROVIDERS = [
	{ slug: 'openai',    is_configured: true  },
	{ slug: 'anthropic', is_configured: false },
	{ slug: 'gemini',    is_configured: false },
	{ slug: 'grok',      is_configured: false },
	{ slug: 'ollama',    is_configured: false },
];

function setup() {
	mockApiFetch.mockImplementation( ( { path } ) => {
		if ( path === '/aipo/v1/settings' ) {
			return Promise.resolve( { ...MOCK_SETTINGS } );
		}
		if ( path === '/aipo/v1/providers' ) {
			return Promise.resolve( [ ...MOCK_PROVIDERS ] );
		}
		return Promise.resolve( {} );
	} );
}

// ── Tests ────────────────────────────────────────────────────────────

describe( 'SettingsApp', () => {
	beforeEach( () => {
		mockApiFetch.mockReset();
		setup();
	} );

	it( 'renders a loading spinner while fetching settings', () => {
		// Keep promise pending.
		mockApiFetch.mockReturnValue( new Promise( () => {} ) );
		render( <SettingsApp /> );
		expect( screen.getByRole( 'status' ) ).toBeInTheDocument();
	} );

	it( 'renders General tab by default after settings load', async () => {
		render( <SettingsApp /> );
		await waitFor( () => expect( screen.queryByRole( 'status' ) ).not.toBeInTheDocument() );
		expect( screen.getByText( 'Enable AI Optimizer' ) ).toBeInTheDocument();
	} );

	it( 'renders all 6 tab buttons', async () => {
		render( <SettingsApp /> );
		await waitFor( () => expect( screen.queryByRole( 'status' ) ).not.toBeInTheDocument() );

		const tabs = screen.getAllByRole( 'tab' );
		expect( tabs ).toHaveLength( 6 );
		expect( tabs.map( ( t ) => t.textContent ) ).toEqual( [
			'General', 'Providers', 'Models', 'Prompts', 'Scheduling', 'Advanced',
		] );
	} );

	it( 'switches to the Providers tab on click', async () => {
		const user = userEvent.setup();
		render( <SettingsApp /> );
		await waitFor( () => expect( screen.queryByRole( 'status' ) ).not.toBeInTheDocument() );

		await user.click( screen.getByRole( 'tab', { name: 'Providers' } ) );

		expect( screen.getByText( 'AI Providers' ) ).toBeInTheDocument();
	} );

	it( 'active tab has aria-selected=true', async () => {
		render( <SettingsApp initialTab="scheduling" /> );
		await waitFor( () => expect( screen.queryByRole( 'status' ) ).not.toBeInTheDocument() );

		const schedulingTab = screen.getByRole( 'tab', { name: 'Scheduling' } );
		expect( schedulingTab ).toHaveAttribute( 'aria-selected', 'true' );

		const generalTab = screen.getByRole( 'tab', { name: 'General' } );
		expect( generalTab ).toHaveAttribute( 'aria-selected', 'false' );
	} );

	it( 'calls POST /aipo/v1/settings when Save is clicked', async () => {
		const user = userEvent.setup();
		mockApiFetch.mockImplementation( ( { path, method } ) => {
			if ( path === '/aipo/v1/settings' && method === 'POST' ) {
				return Promise.resolve( {} );
			}
			return Promise.resolve( MOCK_SETTINGS );
		} );

		render( <SettingsApp /> );
		await waitFor( () => expect( screen.queryByRole( 'status' ) ).not.toBeInTheDocument() );

		await user.click( screen.getByRole( 'button', { name: /Save Settings/i } ) );

		await waitFor( () => {
			const calls = mockApiFetch.mock.calls;
			const saveCall = calls.find( ( [ args ] ) => args.method === 'POST' && args.path === '/aipo/v1/settings' );
			expect( saveCall ).toBeDefined();
		} );
	} );

	it( 'shows "Saved!" feedback after successful save', async () => {
		const user = userEvent.setup();
		mockApiFetch.mockImplementation( ( { path, method } ) => {
			if ( path === '/aipo/v1/settings' && method === 'POST' ) {
				return Promise.resolve( {} );
			}
			return Promise.resolve( MOCK_SETTINGS );
		} );

		render( <SettingsApp /> );
		await waitFor( () => expect( screen.queryByRole( 'status' ) ).not.toBeInTheDocument() );

		await user.click( screen.getByRole( 'button', { name: /Save Settings/i } ) );

		await waitFor( () => {
			expect( screen.getByText( 'Saved!' ) ).toBeInTheDocument();
		} );
	} );

	it( 'shows an error notice when save fails', async () => {
		const user = userEvent.setup();
		mockApiFetch.mockImplementation( ( { path, method } ) => {
			if ( method === 'POST' ) {
				return Promise.reject( new Error( 'Server error' ) );
			}
			return Promise.resolve( MOCK_SETTINGS );
		} );

		render( <SettingsApp /> );
		await waitFor( () => expect( screen.queryByRole( 'status' ) ).not.toBeInTheDocument() );

		await user.click( screen.getByRole( 'button', { name: /Save Settings/i } ) );

		await waitFor( () => {
			expect( screen.getByText( 'Server error' ) ).toBeInTheDocument();
		} );
	} );

	it( 'shows a fetch-error notice when initial load fails', async () => {
		mockApiFetch.mockImplementation( ( { path } ) => {
			if ( path === '/aipo/v1/settings' ) {
				return Promise.reject( new Error( 'Network timeout' ) );
			}
			return Promise.resolve( {} );
		} );

		render( <SettingsApp /> );

		await waitFor( () => {
			expect( screen.getByText( 'Network timeout' ) ).toBeInTheDocument();
		} );
	} );

	it( 'renders the Advanced tab with WP-CLI hint', async () => {
		const user = userEvent.setup();
		render( <SettingsApp /> );
		await waitFor( () => expect( screen.queryByRole( 'status' ) ).not.toBeInTheDocument() );

		await user.click( screen.getByRole( 'tab', { name: 'Advanced' } ) );

		expect( screen.getByText( 'WP-CLI' ) ).toBeInTheDocument();
		expect( screen.getByText( /wp ai-optimizer generate/ ) ).toBeInTheDocument();
	} );

	it( 'initially opens on providers tab when initialTab=providers', async () => {
		render( <SettingsApp initialTab="providers" /> );
		await waitFor( () => expect( screen.queryByRole( 'status' ) ).not.toBeInTheDocument() );

		expect( screen.getByRole( 'tab', { name: 'Providers' } ) ).toHaveAttribute( 'aria-selected', 'true' );
	} );
} );
