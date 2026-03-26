/**
 * Models settings tab — per-task model selection.
 *
 * @package AIProductOptimizer
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { SaveButton } from '../shared/SaveButton';
import { Spinner } from '../../shared/Spinner';
import { Notice } from '../../shared/Notice';

const TASKS = [
	{ slug: 'name',            label: __( 'Product Name', 'ai-product-optimizer' ) },
	{ slug: 'short_desc',      label: __( 'Short Description', 'ai-product-optimizer' ) },
	{ slug: 'long_desc',       label: __( 'Long Description', 'ai-product-optimizer' ) },
	{ slug: 'seo_package',     label: __( 'SEO Package', 'ai-product-optimizer' ) },
	{ slug: 'search_keywords', label: __( 'Search Keywords', 'ai-product-optimizer' ) },
	{ slug: 'alt_text',        label: __( 'Image Alt Text', 'ai-product-optimizer' ) },
];

const PROVIDER_LABELS = {
	openai:    'OpenAI',
	anthropic: 'Anthropic',
	gemini:    'Google Gemini',
	grok:      'xAI Grok',
	ollama:    'Ollama',
};

/**
 * @param {Object}   props
 * @param {Object}   props.settings
 * @param {Function} props.onChange
 * @param {boolean}  props.saving
 * @param {boolean}  props.saved
 * @param {Function} props.onSave
 */
export const ModelsTab = ( { settings, onChange, saving, saved, onSave } ) => {
	const [ providers, setProviders ]     = useState( [] );
	const [ modelMap, setModelMap ]       = useState( {} ); // { slug: { modelSlug: label } }
	const [ loading, setLoading ]         = useState( true );
	const [ modelLoading, setMLoading ]   = useState( {} );
	const [ error, setError ]             = useState( null );

	const s = settings;

	useEffect( () => {
		apiFetch( { path: '/aipo/v1/providers' } )
			.then( ( data ) => setProviders( data.filter( ( p ) => p.is_configured ) ) )
			.catch( ( err ) => setError( err.message ) )
			.finally( () => setLoading( false ) );
	}, [] );

	const loadModels = ( slug ) => {
		if ( modelMap[ slug ] ) return;
		setMLoading( ( prev ) => ( { ...prev, [ slug ]: true } ) );
		apiFetch( { path: `/aipo/v1/providers/${ slug }/models` } )
			.then( ( data ) => setModelMap( ( prev ) => ( { ...prev, [ slug ]: data } ) ) )
			.catch( () => setModelMap( ( prev ) => ( { ...prev, [ slug ]: {} } ) ) )
			.finally( () => setMLoading( ( prev ) => ( { ...prev, [ slug ]: false } ) ) );
	};

	if ( loading ) {
		return <Spinner label={ __( 'Loading providers…', 'ai-product-optimizer' ) } />;
	}

	if ( error ) {
		return <Notice type="error" message={ error } />;
	}

	if ( providers.length === 0 ) {
		return (
			<Notice
				type="warning"
				isDismissible={ false }
				message={ __( 'No providers are configured yet. Add API keys in the Providers tab first.', 'ai-product-optimizer' ) }
			/>
		);
	}

	return (
		<div className="aipo-tab-panel aipo-tab-models">
			<h2>{ __( 'Model Selection', 'ai-product-optimizer' ) }</h2>
			<p className="description">
				{ __( 'Set the model used globally and optionally override per-task.', 'ai-product-optimizer' ) }
			</p>

			{ providers.map( ( p ) => (
				<div key={ p.slug } className="aipo-model-provider-section">
					<h3>{ PROVIDER_LABELS[ p.slug ] ?? p.slug }</h3>

					<table className="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label htmlFor={ `aipo-model-${ p.slug }` }>
										{ __( 'Default Model', 'ai-product-optimizer' ) }
									</label>
								</th>
								<td>
									<div className="aipo-model-select-row">
										<select
											id={ `aipo-model-${ p.slug }` }
											value={ s[ `aipo_provider_${ p.slug }_model` ] ?? '' }
											onChange={ ( e ) => onChange( `aipo_provider_${ p.slug }_model`, e.target.value ) }
											onFocus={ () => loadModels( p.slug ) }
										>
											<option value="">{ __( '— Provider default —', 'ai-product-optimizer' ) }</option>
											{ modelLoading[ p.slug ] && (
												<option disabled>{ __( 'Loading models…', 'ai-product-optimizer' ) }</option>
											) }
											{ modelMap[ p.slug ] && Object.entries( modelMap[ p.slug ] ).map( ( [ id, label ] ) => (
												<option key={ id } value={ id }>{ label }</option>
											) ) }
										</select>
										{ modelLoading[ p.slug ] && (
											<Spinner label={ __( 'Loading models…', 'ai-product-optimizer' ) } />
										) }
									</div>
								</td>
							</tr>
						</tbody>
					</table>

					<details className="aipo-per-task-models">
						<summary>{ __( 'Per-task model overrides', 'ai-product-optimizer' ) }</summary>
						<table className="form-table aipo-per-task-table" role="presentation">
							<tbody>
								{ TASKS.map( ( task ) => {
									const optKey = `aipo_task_${ task.slug }_provider_${ p.slug }_model`;
									return (
										<tr key={ task.slug }>
											<th scope="row">
												<label htmlFor={ `${ optKey }-select` }>
													{ task.label }
												</label>
											</th>
											<td>
												<select
													id={ `${ optKey }-select` }
													value={ s[ optKey ] ?? '' }
													onChange={ ( e ) => onChange( optKey, e.target.value ) }
													onFocus={ () => loadModels( p.slug ) }
												>
													<option value="">{ __( '— Same as default —', 'ai-product-optimizer' ) }</option>
													{ modelMap[ p.slug ] && Object.entries( modelMap[ p.slug ] ).map( ( [ id, label ] ) => (
														<option key={ id } value={ id }>{ label }</option>
													) ) }
												</select>
											</td>
										</tr>
									);
								} ) }
							</tbody>
						</table>
					</details>
				</div>
			) ) }

			<p className="submit">
				<SaveButton saving={ saving } saved={ saved } onClick={ onSave } />
			</p>
		</div>
	);
};

export default ModelsTab;
