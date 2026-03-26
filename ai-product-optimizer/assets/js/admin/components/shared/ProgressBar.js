/**
 * Progress bar component with live ARIA attributes.
 *
 * @package AIProductOptimizer
 */

/**
 * @param {Object} props
 * @param {number} props.pct        Percentage complete 0–100.
 * @param {string} [props.label]    Screen-reader label.
 * @param {boolean} [props.animate] Animate the bar (striped).
 */
export const ProgressBar = ( { pct, label = 'Processing…', animate = true } ) => {
	const clamped = Math.min( 100, Math.max( 0, Math.round( pct ) ) );

	return (
		<div
			className={ `aipo-progress${ animate && clamped < 100 ? ' aipo-progress--animated' : '' }` }
			role="progressbar"
			aria-valuenow={ clamped }
			aria-valuemin={ 0 }
			aria-valuemax={ 100 }
			aria-label={ label }
		>
			<div
				className="aipo-progress__bar"
				style={ { width: `${ clamped }%` } }
			/>
			<span className="aipo-progress__label" aria-hidden="true">
				{ clamped }%
			</span>
		</div>
	);
};

export default ProgressBar;
