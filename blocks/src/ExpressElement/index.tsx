/**
 * External dependencies
 */
import * as React from 'react';

/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
// @ts-ignore - The package does not ship type declarations.
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Disabled, PanelBody, SelectControl } from '@wordpress/components';
// @ts-ignore - The package does not ship type declarations.
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Internal dependencies
 */
import { ElementSave } from '../shared/ElementEdit';

type ExpressAttributes = {
	context: string;
};

type ExpressEditProps = {
	name: string;
	attributes: ExpressAttributes;
	setAttributes: (attributes: Partial<ExpressAttributes>) => void;
};

/**
 * Edit component for the Kustom Express Element block, following the shared ElementEdit pattern.
 *
 * @param {ExpressEditProps} props The block edit props.
 */
const ExpressEdit = ({ name, attributes, setAttributes }: ExpressEditProps) => {
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__('Settings', 'klarna-checkout-for-woocommerce')}
				>
					<SelectControl
						label={__(
							'What to buy',
							'klarna-checkout-for-woocommerce'
						)}
						help={__(
							'Automatic buys the product on a product page with product express enabled, and the cart everywhere else.',
							'klarna-checkout-for-woocommerce'
						)}
						value={attributes.context}
						options={[
							{
								label: __(
									'Automatic',
									'klarna-checkout-for-woocommerce'
								),
								value: 'auto',
							},
							{
								label: __(
									'The cart',
									'klarna-checkout-for-woocommerce'
								),
								value: 'cart',
							},
							{
								label: __(
									'The product',
									'klarna-checkout-for-woocommerce'
								),
								value: 'product',
							},
						]}
						onChange={(context: string) =>
							setAttributes({ context })
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<Disabled>
					<ServerSideRender block={name} attributes={attributes} />
				</Disabled>
			</div>
		</>
	);
};

// The block metadata comes from block.json, registered server side in src/Elements/Block.php.
registerBlockType('kustom/express-element', {
	edit: ExpressEdit,
	save: ElementSave,
} as any); // eslint-disable-line @typescript-eslint/no-explicit-any
