/**
 * Providers settings tab — API keys + active provider selection.
 *
 * @package AIProductOptimizer
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { ApiKeyField } from '../shared/ApiKeyField';
import { SaveButton } from '../shared/SaveButton';
import { Notice } from '../../shared/Notice';
import { Spinner } from '../../shared/Spinner';

const PROVIDER_LABELS = {
	openai:    'OpenAI (GPT-4o / o1)',
	anthropic: 'Anthropic (Claude)',
	gemini:    'Google Gemini',
	grok:      'xAI Grok',
	ollama:    'Ollama (self-hosted)',
};

/**
 * @param {Object}   props
 * @param {Object}   props.settings
 * @param {Function} props.onChange
 * @param {boolean}  props.saving
 * @param {boolean}  props.saved
 * @param {Function} props.onSave
 */
export const ProvidersTab = ( { settings, onChange, saving, saved, onSave } ) => {
	const [ providers, setProviders ]   = useState( [] );
	const [ loading, setLoading ]       = useState( true );
	const [ fetchError, setFetchError ] = useState( null );
	const [ localKeys, setLocalKeys ]   = useState( {} ); // { slug: plaintext }

	useEffect( () => {
		apiFetch( { path: '/aipo/v1/providers' } )
			.then( ( data ) => setProviders( data ) )
			.catch( ( err ) => setFetchError( err.message ) )
			.finally( () => setLoading( false ) );
	}, [] );

	const handleSave = () => {
		// Merge locally-edited keys into settings before saving.
		Object.entries( localKeys ).forEach( ( [ slug, key ] ) => {
			if ( key.trim() ) {
				onChange( `aipo_provider_${ slug }_key`, key.trim() );
			}
		} );
		// Slight delay so onChange state propagates before onSave fires.
		setTimeout( onSave, 0 );
	};

	if ( loading ) {
		return <Spinner label={ __( 'Loading providers…', 'ai-product-optimizer' ) } />;
	}

	if ( fetchError ) {
		return <Notice type="error" message={ fetchError } />;
	}

	const s = settings;

	return (
		<div className="aipo-tab-panel aipo-tab-providers">
			<h2>{ __( 'AI Providers', 'ai-product-optimizer' ) }</h2>

			<table className="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label htmlFor="aipo-active-provider">
								{ __( 'Default Provider', 'ai-product-optimizer' ) }
							</label>
						</th>
						<td>
							<select
								id="aipo-active-provider"
								value={ s.aipo_active_provider ?? 'openai' }
								onChange={ ( e ) => onChange( 'aipo_active_provider', e.target.value ) }
							>
								{ providers.map( ( p ) => (
									<option key={ p.slug } value={ p.slug }>
										{ PROVIDER_LABELS[ p.slug ] ?? p.slug }
										{ ! p.is_configured ? __( ' (not configured)', 'ai-product-optimizer' ) : '' }
									</option>
								) ) }
							</select>
							<p className="description">
								{ __( 'Provider used when no per-task override is set.', 'ai-product-optimizer' ) }
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							{ __( 'Fallback Provider', 'ai-product-optimizer' ) }
						</th>
						<td>
							<select
								value={ s.aipo_fallback_provider ?? '' }
								onChange={ ( e ) => onChange( 'aipo_fallback_provider', e.target.value ) }
							>
								<option value="">{ __( '— None —', 'ai-product-optimizer' ) }</option>
								{ providers.map( ( p ) => (
									<option key={ p.slug } value={ p.slug }>
										{ PROVIDER_LABELS[ p.slug ] ?? p.slug }
									</option>
								) ) }
							</select>
							<p className="description">
								{ __( 'Used when the default provider fails or is circuit-broken.', 'ai-product-optimizer' ) }
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<hr />
			<h3>{ __( 'API Keys', 'ai-product-optimizer' ) }</h3>
			<p className="description">
				{ __( 'Keys are encrypted with AES-256-CBC before storage. Leave blank to keep existing.', 'ai-product-optimizer' ) }
			</p>

			<div className="aipo-provider-list">
				{ providers.filter( ( p ) => p.slug !== 'ollama' ).map( ( p ) => (
					<div key={ p.slug } className="aipo-provider-card">
						<h4>{ PROVIDER_LABELS[ p.slug ] ?? p.slug }</h4>

						<ApiKeyField
							providerSlug={ p.slug }
							label={ __( 'API Key', 'ai-product-optimizer' ) }
							hasKey={ p.is_configured }
							value={ localKeys[ p.slug ] ?? '' }
							onChange={ ( val ) => setLocalKeys( ( prev ) => ( { ...prev, [ p.slug ]: val } ) ) }
						/>
					</div>
				) ) }

				{ /* Ollama gets an endpoint field instead of an API key */ }
				{ providers.some( ( p ) => p.slug === 'ollama' ) && (
					<div className="aipo-provider-card">
						<h4>{ __( 'Ollama (self-hosted)', 'ai-product-optimizer' ) }</h4>
						<table className="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row">
										<label htmlFor="aipo-ollama-endpoint">
											{ __( 'Endpoint URL', 'ai-product-optimizer' ) }
										</label>
									</th>
									<td>
										<input
											id="aipo-ollama-endpoint"
											type="url"
											className="regular-text"
											value={ s.aipo_ollama_endpoint ?? 'http://localhost:11434' }
											onChange={ ( e ) => onChange( 'aipo_ollama_endpoint', e.target.value ) }
											placeholder="http://localhost:11434"
										/>
										<p className="description">
											{ __( 'Must be localhost or a private-network address (RFC 1918).', 'ai-product-optimizer' ) }
										</p>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				) }
			</div>

			<p className="submit">
				<SaveButton saving={ saving } saved={ saved } onClick={ handleSave } />
			</p>
		</div>
	);
};

export default ProvidersTab;
