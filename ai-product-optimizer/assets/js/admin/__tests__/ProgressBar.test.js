/**
 * Jest tests for the ProgressBar component.
 *
 * @package AIProductOptimizer
 */

import { render, screen } from '@testing-library/react';
import { ProgressBar } from '../components/shared/ProgressBar';

jest.mock( '@wordpress/element', () => ( {
	...jest.requireActual( 'react' ),
} ) );

describe( 'ProgressBar', () => {
	it( 'renders with role=progressbar', () => {
		render( <ProgressBar pct={ 50 } /> );
		expect( screen.getByRole( 'progressbar' ) ).toBeInTheDocument();
	} );

	it( 'sets aria-valuenow to the clamped percentage', () => {
		render( <ProgressBar pct={ 42 } /> );
		expect( screen.getByRole( 'progressbar' ) ).toHaveAttribute( 'aria-valuenow', '42' );
	} );

	it( 'clamps values above 100 to 100', () => {
		render( <ProgressBar pct={ 150 } /> );
		expect( screen.getByRole( 'progressbar' ) ).toHaveAttribute( 'aria-valuenow', '100' );
	} );

	it( 'clamps negative values to 0', () => {
		render( <ProgressBar pct={ -10 } /> );
		expect( screen.getByRole( 'progressbar' ) ).toHaveAttribute( 'aria-valuenow', '0' );
	} );

	it( 'shows the percentage label', () => {
		render( <ProgressBar pct={ 75 } /> );
		expect( screen.getByText( '75%' ) ).toBeInTheDocument();
	} );

	it( 'adds animated class when pct < 100 and animate=true', () => {
		const { container } = render( <ProgressBar pct={ 50 } animate /> );
		expect( container.querySelector( '.aipo-progress--animated' ) ).not.toBeNull();
	} );

	it( 'does not add animated class when pct === 100', () => {
		const { container } = render( <ProgressBar pct={ 100 } animate /> );
		expect( container.querySelector( '.aipo-progress--animated' ) ).toBeNull();
	} );

	it( 'sets aria-label from label prop', () => {
		render( <ProgressBar pct={ 30 } label="Custom label" /> );
		expect( screen.getByRole( 'progressbar' ) ).toHaveAttribute( 'aria-label', 'Custom label' );
	} );
} );
