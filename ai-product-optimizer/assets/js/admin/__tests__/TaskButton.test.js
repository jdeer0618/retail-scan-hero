/**
 * Jest tests for the TaskButton component.
 *
 * @package AIProductOptimizer
 */

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { TaskButton } from '../components/product/TaskButton';

// ── Mocks ────────────────────────────────────────────────────────────

const mockApiFetch = jest.fn();
jest.mock( '@wordpress/api-fetch', () => {
	const fn = ( ...args ) => mockApiFetch( ...args );
	fn.use = jest.fn();
	return fn;
} );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
} ) );

jest.mock( '@wordpress/element', () => ( {
	...jest.requireActual( 'react' ),
} ) );

// ── Helpers ──────────────────────────────────────────────────────────

function makeProgressResponse( pct, failed = 0 ) {
	return { pct, completed: Math.round( pct ), total: 100, failed };
}

// ── Tests ─────────────────────────────────────────────────────────────

describe( 'TaskButton', () => {
	beforeEach( () => {
		jest.useFakeTimers();
		mockApiFetch.mockReset();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	it( 'renders the task label', () => {
		render( <TaskButton task="name" label="Product Name" productId={ 42 } /> );
		expect( screen.getByRole( 'button', { name: /Product Name/i } ) ).toBeInTheDocument();
	} );

	it( 'button is enabled initially', () => {
		render( <TaskButton task="name" label="Name" productId={ 1 } /> );
		expect( screen.getByRole( 'button' ) ).not.toBeDisabled();
	} );

	it( 'POSTs to /generate with correct payload on click', async () => {
		const user = userEvent.setup( { advanceTimers: jest.advanceTimersByTime } );

		mockApiFetch
			.mockResolvedValueOnce( { batch_id: 'batch-123' } )          // generate
			.mockResolvedValue( makeProgressResponse( 100 ) );            // progress

		render( <TaskButton task="seo_package" label="SEO" productId={ 7 } /> );

		await user.click( screen.getByRole( 'button' ) );

		expect( mockApiFetch ).toHaveBeenCalledWith( expect.objectContaining( {
			path:   '/aipo/v1/generate',
			method: 'POST',
			data:   { product_ids: [ 7 ], tasks: [ 'seo_package' ] },
		} ) );
	} );

	it( 'disables button while running', async () => {
		const user = userEvent.setup( { advanceTimers: jest.advanceTimersByTime } );

		// Keep the generate promise pending to hold "running" state.
		mockApiFetch.mockReturnValue( new Promise( () => {} ) );

		render( <TaskButton task="name" label="Name" productId={ 1 } /> );
		await user.click( screen.getByRole( 'button' ) );

		expect( screen.getByRole( 'button' ) ).toBeDisabled();
	} );

	it( 'shows "done" message after successful completion', async () => {
		const user = userEvent.setup( { advanceTimers: jest.advanceTimersByTime } );

		mockApiFetch
			.mockResolvedValueOnce( { batch_id: 'batch-abc' } )
			.mockResolvedValue( makeProgressResponse( 100 ) );

		render( <TaskButton task="name" label="Name" productId={ 1 } /> );
		await user.click( screen.getByRole( 'button' ) );

		// Fast-forward the polling interval.
		jest.advanceTimersByTime( 3000 );

		await waitFor( () => {
			expect( screen.getByText( /Done!/ ) ).toBeInTheDocument();
		} );
	} );

	it( 'shows error message when generation fails (failed > 0)', async () => {
		const user = userEvent.setup( { advanceTimers: jest.advanceTimersByTime } );

		mockApiFetch
			.mockResolvedValueOnce( { batch_id: 'batch-fail' } )
			.mockResolvedValue( makeProgressResponse( 100, 1 ) );

		render( <TaskButton task="name" label="Name" productId={ 1 } /> );
		await user.click( screen.getByRole( 'button' ) );

		jest.advanceTimersByTime( 3000 );

		await waitFor( () => {
			expect( screen.getByRole( 'alert' ) ).toBeInTheDocument();
		} );
	} );

	it( 'shows error when generate request throws', async () => {
		const user = userEvent.setup( { advanceTimers: jest.advanceTimersByTime } );

		mockApiFetch.mockRejectedValueOnce( new Error( 'Network error' ) );

		render( <TaskButton task="name" label="Name" productId={ 1 } /> );
		await user.click( screen.getByRole( 'button' ) );

		await waitFor( () => {
			expect( screen.getByRole( 'alert' ) ).toBeInTheDocument();
		} );
	} );

	it( 'calls onDone callback after successful completion', async () => {
		const user    = userEvent.setup( { advanceTimers: jest.advanceTimersByTime } );
		const onDone  = jest.fn();

		mockApiFetch
			.mockResolvedValueOnce( { batch_id: 'batch-done' } )
			.mockResolvedValue( makeProgressResponse( 100 ) );

		render( <TaskButton task="name" label="Name" productId={ 1 } onDone={ onDone } /> );
		await user.click( screen.getByRole( 'button' ) );

		jest.advanceTimersByTime( 3000 );

		await waitFor( () => {
			expect( onDone ).toHaveBeenCalledTimes( 1 );
		} );
	} );
} );
