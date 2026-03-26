/**
 * Single-task generate button with inline status.
 *
 * @package AIProductOptimizer
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Spinner } from '../shared/Spinner';

const POLL_INTERVAL_MS = 2000;

/**
 * @param {Object}   props
 * @param {string}   props.task       Task slug.
 * @param {string}   props.label      Button label.
 * @param {number}   props.productId  Product ID.
 * @param {Function} [props.onDone]   Called when task completes.
 */
export const TaskButton = ( { task, label, productId, onDone } ) => {
	const [ status, setStatus ]   = useState( 'idle' ); // idle | queuing | running | done | error
	const [ message, setMessage ] = useState( '' );

	const run = async () => {
		setStatus( 'queuing' );
		setMessage( '' );
		try {
			const { batch_id } = await apiFetch( {
				path:   '/aipo/v1/generate',
				method: 'POST',
				data:   { product_ids: [ productId ], tasks: [ task ] },
			} );

			setStatus( 'running' );

			// Poll for completion.
			const poll = setInterval( async () => {
				try {
					const progress = await apiFetch( { path: `/aipo/v1/progress/${ batch_id }` } );
					if ( progress.pct >= 100 ) {
						clearInterval( poll );
						const failed = progress.failed ?? 0;
						if ( failed > 0 ) {
							setStatus( 'error' );
							setMessage( __( 'Generation failed. Check logs.', 'ai-product-optimizer' ) );
						} else {
							setStatus( 'done' );
							setMessage( __( 'Done! Reload to see updated content.', 'ai-product-optimizer' ) );
							onDone?.();
						}
					}
				} catch {
					clearInterval( poll );
					setStatus( 'error' );
					setMessage( __( 'Lost contact with server.', 'ai-product-optimizer' ) );
				}
			}, POLL_INTERVAL_MS );

		} catch ( err ) {
			setStatus( 'error' );
			setMessage( err.message ?? __( 'Request failed.', 'ai-product-optimizer' ) );
		}
	};

	const isRunning = status === 'queuing' || status === 'running';

	return (
		<div className={ `aipo-task-btn-wrapper aipo-task-btn-wrapper--${ status }` }>
			<button
				type="button"
				className="button aipo-task-btn"
				onClick={ run }
				disabled={ isRunning }
				aria-busy={ isRunning }
			>
				{ isRunning && <Spinner label={ __( 'Generating…', 'ai-product-optimizer' ) } /> }
				{ label }
			</button>

			{ status === 'done'  && <span className="aipo-task-status aipo-task-status--done" aria-live="polite">{ message }</span> }
			{ status === 'error' && <span className="aipo-task-status aipo-task-status--error" role="alert">{ message }</span> }
			{ status === 'running' && (
				<span className="aipo-task-status" aria-live="polite">
					{ __( 'Generating in background…', 'ai-product-optimizer' ) }
				</span>
			) }
		</div>
	);
};

export default TaskButton;
