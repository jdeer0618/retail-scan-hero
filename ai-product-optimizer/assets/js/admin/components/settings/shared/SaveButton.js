/**
 * Save / submit button with loading + success states.
 *
 * @package AIProductOptimizer
 */

import { __ } from '@wordpress/i18n';
import { Spinner } from '../../shared/Spinner';

/**
 * @param {Object}   props
 * @param {boolean}  props.saving     Whether a save is in progress.
 * @param {boolean}  props.saved      Whether the last save succeeded.
 * @param {Function} props.onClick    Click handler.
 * @param {string}   [props.label]    Default button label.
 */
export const SaveButton = ( { saving, saved, onClick, label } ) => {
	const buttonLabel = saving
		? __( 'Saving…', 'ai-product-optimizer' )
		: saved
			? __( 'Saved!', 'ai-product-optimizer' )
			: ( label || __( 'Save Settings', 'ai-product-optimizer' ) );

	return (
		<button
			type="button"
			className={ `button button-primary aipo-save-btn${ saved ? ' aipo-save-btn--saved' : '' }` }
			onClick={ onClick }
			disabled={ saving }
			aria-busy={ saving }
		>
			{ saving && <Spinner label={ __( 'Saving settings…', 'ai-product-optimizer' ) } /> }
			{ buttonLabel }
		</button>
	);
};

export default SaveButton;
