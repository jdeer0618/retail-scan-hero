/**
 * Masked API-key input with inline "Test Connection" button.
 *
 * @package AIProductOptimizer
 */

import { useState, useId } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Notice } from '../../shared/Notice';
import { Spinner } from '../../shared/Spinner';

/**
 * @param {Object}   props
 * @param {string}   props.providerSlug   e.g. 'openai'
 * @param {string}   props.label          Field label.
 * @param {boolean}  props.hasKey         Whether a key is already stored.
 * @param {string}   props.value          Current (unsaved) key value.
 * @param {Function} props.onChange       Called with new plaintext value.
 */
export const ApiKeyField = ( { providerSlug, label, hasKey, value, onChange } ) => {
	const inputId       = useId();
	const [ testing, setTesting ]   = useState( false );
	const [ testResult, setResult ] = useState( null ); // { ok: bool, message: string }

	const handleTest = async () => {
		setTesting( true );
		setResult( null );
		try {
			const res = await apiFetch( {
				path:   `/aipo/v1/providers/${ providerSlug }/test`,
				method: 'POST',
			} );
			setResult( { ok: true, message: res.message || __( 'Connection successful.', 'ai-product-optimizer' ) } );
		} catch ( err ) {
			setResult( { ok: false, message: err.message || __( 'Connection failed.', 'ai-product-optimizer' ) } );
		} finally {
			setTesting( false );
		}
	};

	return (
		<div className="aipo-api-key-field">
			<label htmlFor={ inputId } className="aipo-api-key-field__label">
				{ label }
			</label>

			<div className="aipo-api-key-field__row">
				<input
					id={ inputId }
					type="password"
					className="regular-text aipo-api-key-field__input"
					value={ value }
					onChange={ ( e ) => onChange( e.target.value ) }
					placeholder={ hasKey
						? __( '••••••••  (stored — enter to replace)', 'ai-product-optimizer' )
						: __( 'Enter API key', 'ai-product-optimizer' )
					}
					autoComplete="new-password"
					aria-describedby={ `${ inputId }-status` }
				/>

				<button
					type="button"
					className="button aipo-api-key-field__test-btn"
					onClick={ handleTest }
					disabled={ testing || ( ! value && ! hasKey ) }
					aria-busy={ testing }
				>
					{ testing
						? <Spinner label={ __( 'Testing…', 'ai-product-optimizer' ) } />
						: __( 'Test', 'ai-product-optimizer' )
					}
				</button>
			</div>

			{ hasKey && ! value && (
				<p className="aipo-api-key-field__hint description" id={ `${ inputId }-status` }>
					{ __( 'API key is configured. Leave blank to keep existing.', 'ai-product-optimizer' ) }
				</p>
			) }

			{ testResult && (
				<Notice
					type={ testResult.ok ? 'success' : 'error' }
					message={ testResult.message }
					isDismissible={ true }
				/>
			) }
		</div>
	);
};

export default ApiKeyField;
