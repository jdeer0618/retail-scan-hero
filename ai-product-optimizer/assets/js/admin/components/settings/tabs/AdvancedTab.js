/**
 * Advanced settings tab — cache, rate limits, circuit breaker, debug.
 *
 * @package AIProductOptimizer
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { SaveButton } from '../shared/SaveButton';
import { Notice } from '../../shared/Notice';

/**
 * @param {Object}   props
 * @param {Object}   props.settings
 * @param {Function} props.onChange
 * @param {boolean}  props.saving
 * @param {boolean}  props.saved
 * @param {Function} props.onSave
 */
export const AdvancedTab = ( { settings, onChange, saving, saved, onSave } ) => {
	const [ cacheFlushState, setCacheFlushState ] = useState( 'idle' ); // idle | flushing | done | error
	const s = settings;

	const flushCache = async () => {
		setCacheFlushState( 'flushing' );
		try {
			await apiFetch( { path: '/aipo/v1/settings/flush-cache', method: 'POST' } );
			setCacheFlushState( 'done' );
		} catch {
			setCacheFlushState( 'error' );
		}
	};

	return (
		<div className="aipo-tab-panel aipo-tab-advanced">
			<h2>{ __( 'Advanced', 'ai-product-optimizer' ) }</h2>

			{ /* ── Cache ── */ }
			<h3>{ __( 'Cache', 'ai-product-optimizer' ) }</h3>
			<table className="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label htmlFor="aipo-cache-ttl">
								{ __( 'Cache TTL (seconds)', 'ai-product-optimizer' ) }
							</label>
						</th>
						<td>
							<input
								id="aipo-cache-ttl"
								type="number"
								className="small-text"
								min={ 60 }
								max={ 604800 }
								value={ s.aipo_cache_ttl ?? 86400 }
								onChange={ ( e ) => onChange( 'aipo_cache_ttl', parseInt( e.target.value, 10 ) ) }
							/>
							<p className="description">
								{ __( 'How long generated content is cached (60s–7 days). Default: 86400 (24 h).', 'ai-product-optimizer' ) }
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">{ __( 'Flush Cache', 'ai-product-optimizer' ) }</th>
						<td>
							<button
								type="button"
								className="button"
								onClick={ flushCache }
								disabled={ cacheFlushState === 'flushing' }
							>
								{ cacheFlushState === 'flushing'
									? __( 'Flushing…', 'ai-product-optimizer' )
									: __( 'Flush All AI Content Cache', 'ai-product-optimizer' )
								}
							</button>
							{ cacheFlushState === 'done' && (
								<Notice type="success" message={ __( 'Cache flushed successfully.', 'ai-product-optimizer' ) } />
							) }
							{ cacheFlushState === 'error' && (
								<Notice type="error" message={ __( 'Cache flush failed. Check server logs.', 'ai-product-optimizer' ) } />
							) }
						</td>
					</tr>
				</tbody>
			</table>

			{ /* ── Rate Limiting ── */ }
			<h3>{ __( 'Rate Limiting', 'ai-product-optimizer' ) }</h3>
			<table className="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label htmlFor="aipo-rate-limit">
								{ __( 'API Requests per Minute', 'ai-product-optimizer' ) }
							</label>
						</th>
						<td>
							<input
								id="aipo-rate-limit"
								type="number"
								className="small-text"
								min={ 1 }
								max={ 500 }
								value={ s.aipo_rate_limit_per_minute ?? 60 }
								onChange={ ( e ) => onChange( 'aipo_rate_limit_per_minute', parseInt( e.target.value, 10 ) ) }
							/>
							<p className="description">
								{ __( 'Max API requests per user per 60-second window.', 'ai-product-optimizer' ) }
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			{ /* ── Circuit Breaker ── */ }
			<h3>{ __( 'Circuit Breaker', 'ai-product-optimizer' ) }</h3>
			<table className="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label htmlFor="aipo-cb-threshold">
								{ __( 'Failure Threshold', 'ai-product-optimizer' ) }
							</label>
						</th>
						<td>
							<input
								id="aipo-cb-threshold"
								type="number"
								className="small-text"
								min={ 1 }
								max={ 100 }
								value={ s.aipo_circuit_breaker_threshold ?? 10 }
								onChange={ ( e ) => onChange( 'aipo_circuit_breaker_threshold', parseInt( e.target.value, 10 ) ) }
							/>
							<p className="description">
								{ __( 'Consecutive failures before suspending a provider for 5 minutes.', 'ai-product-optimizer' ) }
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			{ /* ── Yoast / Rank Math ── */ }
			<h3>{ __( 'SEO Plugin Bridges', 'ai-product-optimizer' ) }</h3>
			<table className="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">{ __( 'Yoast SEO', 'ai-product-optimizer' ) }</th>
						<td>
							<fieldset>
								<label>
									<input
										type="checkbox"
										checked={ !! s.aipo_yoast_bridge_enabled }
										onChange={ ( e ) => onChange( 'aipo_yoast_bridge_enabled', e.target.checked ) }
									/>
									<span>{ __( 'Sync SEO meta to Yoast SEO fields', 'ai-product-optimizer' ) }</span>
								</label>
								<br />
								<label>
									<input
										type="checkbox"
										checked={ !! s.aipo_yoast_override_existing }
										onChange={ ( e ) => onChange( 'aipo_yoast_override_existing', e.target.checked ) }
										disabled={ ! s.aipo_yoast_bridge_enabled }
									/>
									<span>{ __( 'Overwrite existing Yoast data', 'ai-product-optimizer' ) }</span>
								</label>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row">{ __( 'Rank Math', 'ai-product-optimizer' ) }</th>
						<td>
							<fieldset>
								<label>
									<input
										type="checkbox"
										checked={ !! s.aipo_rank_math_bridge_enabled }
										onChange={ ( e ) => onChange( 'aipo_rank_math_bridge_enabled', e.target.checked ) }
									/>
									<span>{ __( 'Sync SEO meta to Rank Math fields', 'ai-product-optimizer' ) }</span>
								</label>
								<br />
								<label>
									<input
										type="checkbox"
										checked={ !! s.aipo_rank_math_override_existing }
										onChange={ ( e ) => onChange( 'aipo_rank_math_override_existing', e.target.checked ) }
										disabled={ ! s.aipo_rank_math_bridge_enabled }
									/>
									<span>{ __( 'Overwrite existing Rank Math data', 'ai-product-optimizer' ) }</span>
								</label>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>

			{ /* ── Debug ── */ }
			<h3>{ __( 'Debug', 'ai-product-optimizer' ) }</h3>
			<table className="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">{ __( 'Debug Logging', 'ai-product-optimizer' ) }</th>
						<td>
							<fieldset>
								<label>
									<input
										type="checkbox"
										checked={ !! s.aipo_debug_logging }
										onChange={ ( e ) => onChange( 'aipo_debug_logging', e.target.checked ) }
									/>
									<span>
										{ __( 'Log detailed provider request/response data to WooCommerce logs', 'ai-product-optimizer' ) }
									</span>
								</label>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>

			<div className="aipo-wpcli-hint">
				<h3>{ __( 'WP-CLI', 'ai-product-optimizer' ) }</h3>
				<p>{ __( 'Available commands:', 'ai-product-optimizer' ) }</p>
				<pre className="aipo-code-block">
{ `wp ai-optimizer generate --product_ids=1,2,3 --tasks=name,seo_package
wp ai-optimizer queue --status=all` }
				</pre>
			</div>

			<p className="submit">
				<SaveButton saving={ saving } saved={ saved } onClick={ onSave } />
			</p>
		</div>
	);
};

export default AdvancedTab;
