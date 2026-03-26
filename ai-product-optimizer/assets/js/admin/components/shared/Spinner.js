/**
 * Inline spinner / loading indicator.
 *
 * @package AIProductOptimizer
 */

/**
 * @param {Object} props
 * @param {string} [props.label] Screen-reader text.
 */
export const Spinner = ( { label = 'Loading…' } ) => (
	<span className="aipo-spinner" aria-label={ label } role="status">
		<span className="aipo-spinner__ring" aria-hidden="true" />
		<span className="screen-reader-text">{ label }</span>
	</span>
);

export default Spinner;
