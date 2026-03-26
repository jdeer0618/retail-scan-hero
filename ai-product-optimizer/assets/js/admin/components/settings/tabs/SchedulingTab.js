/**
 * Scheduling settings tab — background queue configuration.
 *
 * @package AIProductOptimizer
 */

import { __ } from '@wordpress/i18n';
import { SaveButton } from '../shared/SaveButton';

const CRON_PRESETS = [
	{ value: 'hourly',      label: __( 'Every hour', 'ai-product-optimizer' ) },
	{ value: 'twicedaily',  label: __( 'Twice daily', 'ai-product-optimizer' ) },
	{ value: 'daily',       label: __( 'Once daily', 'ai-product-optimizer' ) },
	{ value: 'weekly',      label: __( 'Once weekly', 'ai-product-optimizer' ) },
	{ value: 'disabled',    label: __( 'Disabled (manual only)', 'ai-product-optimizer' ) },
];

/**
 * @param {Object}   props
 * @param {Object}   props.settings
 * @param {Function} props.onChange
 * @param {boolean}  props.saving
 * @param {boolean}  props.saved
 * @param {Function} props.onSave
 */
export const SchedulingTab = ( { settings, onChange, saving, saved, onSave } ) => {
	const s = settings;

	return (
		<div className="aipo-tab-panel aipo-tab-scheduling">
			<h2>{ __( 'Scheduling', 'ai-product-optimizer' ) }</h2>
			<p className="description">
				{ __( 'Configure automatic background generation via Action Scheduler.', 'ai-product-optimizer' ) }
			</p>

			<table className="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label htmlFor="aipo-cron-schedule">
								{ __( 'Auto-generate Schedule', 'ai-product-optimizer' ) }
							</label>
						</th>
						<td>
							<select
								id="aipo-cron-schedule"
								value={ s.aipo_cron_schedule ?? 'daily' }
								onChange={ ( e ) => onChange( 'aipo_cron_schedule', e.target.value ) }
							>
								{ CRON_PRESETS.map( ( p ) => (
									<option key={ p.value } value={ p.value }>{ p.label }</option>
								) ) }
							</select>
							<p className="description">
								{ __( 'How often to automatically regenerate stale product content.', 'ai-product-optimizer' ) }
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label htmlFor="aipo-auto-generate-tasks">
								{ __( 'Tasks to Auto-generate', 'ai-product-optimizer' ) }
							</label>
						</th>
						<td>
							{ [
								{ slug: 'name',            label: __( 'Product Name', 'ai-product-optimizer' ) },
								{ slug: 'short_desc',      label: __( 'Short Description', 'ai-product-optimizer' ) },
								{ slug: 'long_desc',       label: __( 'Long Description', 'ai-product-optimizer' ) },
								{ slug: 'seo_package',     label: __( 'SEO Package', 'ai-product-optimizer' ) },
								{ slug: 'search_keywords', label: __( 'Search Keywords', 'ai-product-optimizer' ) },
								{ slug: 'alt_text',        label: __( 'Image Alt Text', 'ai-product-optimizer' ) },
							].map( ( task ) => {
								const key = `aipo_auto_task_${ task.slug }`;
								return (
									<label key={ task.slug } className="aipo-checkbox-label">
										<input
											type="checkbox"
											checked={ s[ key ] !== false }
											onChange={ ( e ) => onChange( key, e.target.checked ) }
										/>
										<span>{ task.label }</span>
									</label>
								);
							} ) }
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label htmlFor="aipo-batch-size">
								{ __( 'Batch Size', 'ai-product-optimizer' ) }
							</label>
						</th>
						<td>
							<input
								id="aipo-batch-size"
								type="number"
								className="small-text"
								min={ 1 }
								max={ 500 }
								value={ s.aipo_batch_size ?? 50 }
								onChange={ ( e ) => onChange( 'aipo_batch_size', parseInt( e.target.value, 10 ) ) }
							/>
							<p className="description">
								{ __( 'Max products processed per scheduled run (1–500).', 'ai-product-optimizer' ) }
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label htmlFor="aipo-stale-days">
								{ __( 'Regenerate After (days)', 'ai-product-optimizer' ) }
							</label>
						</th>
						<td>
							<input
								id="aipo-stale-days"
								type="number"
								className="small-text"
								min={ 1 }
								max={ 365 }
								value={ s.aipo_stale_threshold_days ?? 30 }
								onChange={ ( e ) => onChange( 'aipo_stale_threshold_days', parseInt( e.target.value, 10 ) ) }
							/>
							<p className="description">
								{ __( 'Products last generated more than this many days ago are eligible for auto-regeneration.', 'ai-product-optimizer' ) }
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							{ __( 'Skip Unchanged Products', 'ai-product-optimizer' ) }
						</th>
						<td>
							<fieldset>
								<label>
									<input
										type="checkbox"
										checked={ !! s.aipo_skip_unchanged }
										onChange={ ( e ) => onChange( 'aipo_skip_unchanged', e.target.checked ) }
									/>
									<span>
										{ __( 'Skip products whose content has not changed since last generation (recommended)', 'ai-product-optimizer' ) }
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

export default SchedulingTab;
