/**
 * Prompts settings tab — per-task prompt template editor.
 *
 * @package AIProductOptimizer
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { SaveButton } from '../shared/SaveButton';

const TASKS = [
	{
		slug:        'name',
		label:       __( 'Product Name', 'ai-product-optimizer' ),
		description: __( 'Generate compelling, SEO-friendly product names.', 'ai-product-optimizer' ),
		tokens:      [ '{product_name}', '{description}', '{sku}', '{price}', '{categories}', '{attributes}' ],
	},
	{
		slug:        'short_desc',
		label:       __( 'Short Description', 'ai-product-optimizer' ),
		description: __( 'Generate a punchy short description (shown in search results & listings).', 'ai-product-optimizer' ),
		tokens:      [ '{product_name}', '{description}', '{sku}', '{price}', '{categories}', '{attributes}' ],
	},
	{
		slug:        'long_desc',
		label:       __( 'Long Description', 'ai-product-optimizer' ),
		description: __( 'Generate the full HTML product description.', 'ai-product-optimizer' ),
		tokens:      [ '{product_name}', '{description}', '{sku}', '{price}', '{categories}', '{attributes}', '{image_urls}' ],
	},
	{
		slug:        'seo_package',
		label:       __( 'SEO Package', 'ai-product-optimizer' ),
		description: __( 'Generate JSON: seo_title, meta_desc, focus_kw, secondary_kws, og_title, og_desc, schema_hints.', 'ai-product-optimizer' ),
		tokens:      [ '{product_name}', '{description}', '{categories}', '{attributes}' ],
	},
	{
		slug:        'search_keywords',
		label:       __( 'Search Keywords', 'ai-product-optimizer' ),
		description: __( 'Generate one keyword phrase per line for WooCommerce native search boost.', 'ai-product-optimizer' ),
		tokens:      [ '{product_name}', '{description}', '{categories}', '{attributes}', '{keyword_count}' ],
	},
	{
		slug:        'alt_text',
		label:       __( 'Image Alt Text', 'ai-product-optimizer' ),
		description: __( 'Generate a JSON array of alt text strings (one per product image).', 'ai-product-optimizer' ),
		tokens:      [ '{product_name}', '{description}', '{image_urls}' ],
	},
];

/**
 * @param {Object}   props
 * @param {Object}   props.settings
 * @param {Function} props.onChange
 * @param {boolean}  props.saving
 * @param {boolean}  props.saved
 * @param {Function} props.onSave
 */
export const PromptsTab = ( { settings, onChange, saving, saved, onSave } ) => {
	const [ openTask, setOpenTask ] = useState( null );

	const s = settings;

	return (
		<div className="aipo-tab-panel aipo-tab-prompts">
			<h2>{ __( 'Prompt Templates', 'ai-product-optimizer' ) }</h2>
			<p className="description">
				{ __( 'Customise the system prompt sent to the AI for each task. Leave blank to use built-in defaults.', 'ai-product-optimizer' ) }
			</p>

			{ TASKS.map( ( task ) => {
				const optKey  = `aipo_prompt_${ task.slug }`;
				const isOpen  = openTask === task.slug;

				return (
					<div key={ task.slug } className={ `aipo-prompt-card${ isOpen ? ' aipo-prompt-card--open' : '' }` }>
						<button
							type="button"
							className="aipo-prompt-card__header"
							aria-expanded={ isOpen }
							onClick={ () => setOpenTask( isOpen ? null : task.slug ) }
						>
							<strong>{ task.label }</strong>
							<span className="aipo-prompt-card__desc">{ task.description }</span>
							<span className="aipo-prompt-card__chevron" aria-hidden="true">{ isOpen ? '▲' : '▼' }</span>
						</button>

						{ isOpen && (
							<div className="aipo-prompt-card__body">
								<label
									htmlFor={ `aipo-prompt-${ task.slug }` }
									className="screen-reader-text"
								>
									{ task.label } { __( 'prompt template', 'ai-product-optimizer' ) }
								</label>
								<textarea
									id={ `aipo-prompt-${ task.slug }` }
									className="large-text aipo-prompt-textarea"
									rows={ 10 }
									value={ s[ optKey ] ?? '' }
									onChange={ ( e ) => onChange( optKey, e.target.value ) }
									placeholder={ __( 'Leave blank to use the built-in default prompt.', 'ai-product-optimizer' ) }
								/>

								<p className="description aipo-prompt-tokens">
									{ __( 'Available tokens:', 'ai-product-optimizer' ) }{ ' ' }
									{ task.tokens.map( ( tok, i ) => (
										<span key={ tok }>
											<code>{ tok }</code>
											{ i < task.tokens.length - 1 ? ', ' : '' }
										</span>
									) ) }
								</p>

								{ s[ optKey ] && (
									<button
										type="button"
										className="button button-link-delete aipo-prompt-reset"
										onClick={ () => onChange( optKey, '' ) }
									>
										{ __( 'Reset to default', 'ai-product-optimizer' ) }
									</button>
								) }
							</div>
						) }
					</div>
				);
			} ) }

			<p className="submit">
				<SaveButton saving={ saving } saved={ saved } onClick={ onSave } />
			</p>
		</div>
	);
};

export default PromptsTab;
