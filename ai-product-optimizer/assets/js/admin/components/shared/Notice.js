/**
 * Reusable dismissible notice component.
 *
 * @package AIProductOptimizer
 */

import { useState } from '@wordpress/element';

/**
 * @param {Object}  props
 * @param {'success'|'error'|'info'|'warning'} props.type
 * @param {string}  props.message
 * @param {boolean} [props.isDismissible]
 */
export const Notice = ( { type = 'info', message, isDismissible = true } ) => {
	const [ dismissed, setDismissed ] = useState( false );

	if ( ! message || dismissed ) {
		return null;
	}

	const classMap = {
		success: 'notice-success',
		error:   'notice-error',
		info:    'notice-info',
		warning: 'notice-warning',
	};

	return (
		<div
			className={ `notice ${ classMap[ type ] ?? 'notice-info' }${ isDismissible ? ' is-dismissible' : '' } aipo-notice` }
			role={ type === 'error' ? 'alert' : 'status' }
		>
			<p>{ message }</p>
			{ isDismissible && (
				<button
					type="button"
					className="notice-dismiss"
					aria-label="Dismiss this notice"
					onClick={ () => setDismissed( true ) }
				>
					<span className="screen-reader-text">Dismiss this notice.</span>
				</button>
			) }
		</div>
	);
};

export default Notice;
