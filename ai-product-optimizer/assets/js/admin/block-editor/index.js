/**
 * AI Product Optimizer — Gutenberg sidebar plugin entry point.
 *
 * Registers the "AI Optimizer" PluginSidebar in the block editor.
 *
 * Full implementation: Phase 4.
 *
 * @package AIProductOptimizer
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar } from '@wordpress/edit-post';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const AiOptimizerSidebar = () => (
	<PluginSidebar
		name="aipo-sidebar"
		title={ __( 'AI Optimizer', 'ai-product-optimizer' ) }
		icon="buddicons-activity"
	>
		<PanelBody>
			<p>{ __( 'AI generation controls coming in Phase 4.', 'ai-product-optimizer' ) }</p>
		</PanelBody>
	</PluginSidebar>
);

registerPlugin( 'aipo-sidebar', { render: AiOptimizerSidebar } );
