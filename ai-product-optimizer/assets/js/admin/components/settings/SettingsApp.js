/**
 * Root settings page React application.
 *
 * Manages global settings state, REST API persistence, and tab routing.
 *
 * @package AIProductOptimizer
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import { GeneralTab }    from './tabs/GeneralTab';
import { ProvidersTab }  from './tabs/ProvidersTab';
import { ModelsTab }     from './tabs/ModelsTab';
import { PromptsTab }    from './tabs/PromptsTab';
import { SchedulingTab } from './tabs/SchedulingTab';
import { AdvancedTab }   from './tabs/AdvancedTab';
import { Notice }        from '../shared/Notice';
import { Spinner }       from '../shared/Spinner';

const TABS = [
	{ id: 'general',    label: __( 'General',    'ai-product-optimizer' ) },
	{ id: 'providers',  label: __( 'Providers',  'ai-product-optimizer' ) },
	{ id: 'models',     label: __( 'Models',     'ai-product-optimizer' ) },
	{ id: 'prompts',    label: __( 'Prompts',    'ai-product-optimizer' ) },
	{ id: 'scheduling', label: __( 'Scheduling', 'ai-product-optimizer' ) },
	{ id: 'advanced',   label: __( 'Advanced',   'ai-product-optimizer' ) },
];

/**
 * @param {Object} props
 * @param {string} [props.initialTab] Tab ID to open on mount.
 */
export const SettingsApp = ( { initialTab = 'general' } ) => {
	const [ activeTab, setActiveTab ]   = useState( initialTab );
	const [ settings, setSettings ]     = useState( {} );
	const [ loading, setLoading ]       = useState( true );
	const [ saving, setSaving ]         = useState( false );
	const [ saved, setSaved ]           = useState( false );
	const [ fetchError, setFetchError ] = useState( null );
	const [ saveError, setSaveError ]   = useState( null );

	// Fetch settings on mount.
	useEffect( () => {
		apiFetch( { path: '/aipo/v1/settings' } )
			.then( ( data ) => setSettings( data ) )
			.catch( ( err ) => setFetchError( err.message ?? __( 'Failed to load settings.', 'ai-product-optimizer' ) ) )
			.finally( () => setLoading( false ) );
	}, [] );

	// Update a single setting key.
	const handleChange = useCallback( ( key, value ) => {
		setSettings( ( prev ) => ( { ...prev, [ key ]: value } ) );
		setSaved( false );
		setSaveError( null );
	}, [] );

	// Persist settings to REST API.
	const handleSave = useCallback( async () => {
		setSaving( true );
		setSaved( false );
		setSaveError( null );
		try {
			await apiFetch( { path: '/aipo/v1/settings', method: 'POST', data: settings } );
			setSaved( true );
			setTimeout( () => setSaved( false ), 4000 );
		} catch ( err ) {
			setSaveError( err.message ?? __( 'Failed to save settings.', 'ai-product-optimizer' ) );
		} finally {
			setSaving( false );
		}
	}, [ settings ] );

	if ( loading ) {
		return (
			<div className="aipo-settings-loading">
				<Spinner label={ __( 'Loading settings…', 'ai-product-optimizer' ) } />
			</div>
		);
	}

	if ( fetchError ) {
		return <Notice type="error" message={ fetchError } isDismissible={ false } />;
	}

	const tabProps = { settings, onChange: handleChange, saving, saved, onSave: handleSave };

	return (
		<div className="aipo-settings-app">
			{ /* Header */ }
			<div className="aipo-settings-header">
				<h1 className="aipo-settings-header__title">
					{ __( 'AI Product Optimizer', 'ai-product-optimizer' ) }
				</h1>
				<span className="aipo-settings-header__version">
					{ window.aipoSettings?.version && `v${ window.aipoSettings.version }` }
				</span>
			</div>

			{ saveError && <Notice type="error" message={ saveError } /> }

			{ /* Tab Navigation */ }
			<nav className="aipo-tab-nav" aria-label={ __( 'Settings sections', 'ai-product-optimizer' ) }>
				<ul className="aipo-tab-nav__list" role="tablist">
					{ TABS.map( ( tab ) => (
						<li key={ tab.id } role="presentation">
							<button
								role="tab"
								type="button"
								className={ `aipo-tab-nav__btn${ activeTab === tab.id ? ' aipo-tab-nav__btn--active' : '' }` }
								aria-selected={ activeTab === tab.id }
								aria-controls={ `aipo-tabpanel-${ tab.id }` }
								id={ `aipo-tab-${ tab.id }` }
								onClick={ () => setActiveTab( tab.id ) }
							>
								{ tab.label }
							</button>
						</li>
					) ) }
				</ul>
			</nav>

			{ /* Tab Panels */ }
			<div className="aipo-tab-panels">
				{ activeTab === 'general'    && (
					<div id="aipo-tabpanel-general"    role="tabpanel" aria-labelledby="aipo-tab-general">
						<GeneralTab { ...tabProps } />
					</div>
				) }
				{ activeTab === 'providers'  && (
					<div id="aipo-tabpanel-providers"  role="tabpanel" aria-labelledby="aipo-tab-providers">
						<ProvidersTab { ...tabProps } />
					</div>
				) }
				{ activeTab === 'models'     && (
					<div id="aipo-tabpanel-models"     role="tabpanel" aria-labelledby="aipo-tab-models">
						<ModelsTab { ...tabProps } />
					</div>
				) }
				{ activeTab === 'prompts'    && (
					<div id="aipo-tabpanel-prompts"    role="tabpanel" aria-labelledby="aipo-tab-prompts">
						<PromptsTab { ...tabProps } />
					</div>
				) }
				{ activeTab === 'scheduling' && (
					<div id="aipo-tabpanel-scheduling" role="tabpanel" aria-labelledby="aipo-tab-scheduling">
						<SchedulingTab { ...tabProps } />
					</div>
				) }
				{ activeTab === 'advanced'   && (
					<div id="aipo-tabpanel-advanced"   role="tabpanel" aria-labelledby="aipo-tab-advanced">
						<AdvancedTab { ...tabProps } />
					</div>
				) }
			</div>
		</div>
	);
};

export default SettingsApp;
