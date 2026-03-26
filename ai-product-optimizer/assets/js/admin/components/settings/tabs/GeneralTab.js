/**
 * General settings tab — global enable/disable + defaults.
 *
 * @package AIProductOptimizer
 */

import { __ } from '@wordpress/i18n';
import { SaveButton } from '../shared/SaveButton';

/**
 * @param {Object}   props
 * @param {Object}   props.settings   Current settings object.
 * @param {Function} props.onChange   (key, value) updater.
 * @param {boolean}  props.saving
 * @param {boolean}  props.saved
 * @param {Function} props.onSave
 */
export const GeneralTab = ( { settings, onChange, saving, saved, onSave } ) => {
	const s = settings;

	return (
		<div className="aipo-tab-panel aipo-tab-general">
			<h2>{ __( 'General', 'ai-product-optimizer' ) }</h2>

			<table className="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label htmlFor="aipo-enabled">
								{ __( 'Enable AI Optimizer', 'ai-product-optimizer' ) }
							</label>
						</th>
						<td>
							<fieldset>
								<label>
									<input
										id="aipo-enabled"
										type="checkbox"
										checked={ !! s.aipo_enabled }
										onChange={ ( e ) => onChange( 'aipo_enabled', e.target.checked ) }
									/>
									<span>
										{ __( 'Enable AI-powered product content generation', 'ai-product-optimizer' ) }
									</span>
								</label>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label htmlFor="aipo-search-boost-enabled">
								{ __( 'Native Search Boost', 'ai-product-optimizer' ) }
							</label>
						</th>
						<td>
							<fieldset>
								<label>
									<input
										id="aipo-search-boost-enabled"
										type="checkbox"
										checked={ !! s.aipo_search_boost_enabled }
										onChange={ ( e ) => onChange( 'aipo_search_boost_enabled', e.target.checked ) }
									/>
									<span>
										{ __( 'Boost WooCommerce search results using AI-generated keywords', 'ai-product-optimizer' ) }
									</span>
								</label>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label htmlFor="aipo-search-keyword-count">
								{ __( 'Keywords per Product', 'ai-product-optimizer' ) }
							</label>
						</th>
						<td>
							<input
								id="aipo-search-keyword-count"
								type="number"
								className="small-text"
								min={ 5 }
								max={ 50 }
								value={ s.aipo_search_keyword_count ?? 20 }
								onChange={ ( e ) => onChange( 'aipo_search_keyword_count', parseInt( e.target.value, 10 ) ) }
							/>
							<p className="description">
								{ __( 'Number of search keywords to generate per product (5–50).', 'ai-product-optimizer' ) }
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label htmlFor="aipo-name-max-chars">
								{ __( 'Max Product Name Length', 'ai-product-optimizer' ) }
							</label>
						</th>
						<td>
							<input
								id="aipo-name-max-chars"
								type="number"
								className="small-text"
								min={ 20 }
								max={ 200 }
								value={ s.aipo_name_max_chars ?? 70 }
								onChange={ ( e ) => onChange( 'aipo_name_max_chars', parseInt( e.target.value, 10 ) ) }
							/>
							<p className="description">
								{ __( 'Maximum characters for generated product names (20–200).', 'ai-product-optimizer' ) }
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label htmlFor="aipo-alt-text-auto-apply">
								{ __( 'Auto-apply Alt Text', 'ai-product-optimizer' ) }
							</label>
						</th>
						<td>
							<fieldset>
								<label>
									<input
										id="aipo-alt-text-auto-apply"
										type="checkbox"
										checked={ !! s.aipo_alt_text_auto_apply }
										onChange={ ( e ) => onChange( 'aipo_alt_text_auto_apply', e.target.checked ) }
									/>
									<span>
										{ __( 'Automatically write generated alt text to image attachments', 'ai-product-optimizer' ) }
									</span>
								</label>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>

			<p className="submit">
				<SaveButton saving={ saving } saved={ saved } onClick={ onSave } />
			</p>
		</div>
	);
};

export default GeneralTab;
