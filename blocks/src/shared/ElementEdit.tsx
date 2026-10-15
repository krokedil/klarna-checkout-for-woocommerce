/**
 * External dependencies
 */
import * as React from 'react';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
// @ts-ignore - The package does not ship type declarations.
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Disabled, PanelBody, TextControl } from '@wordpress/components';
// @ts-ignore - The package does not ship type declarations.
import ServerSideRender from '@wordpress/server-side-render';

type ElementAttributes = {
	include: string;
	exclude: string;
	style?: {
		spacing?: {
			margin?: Partial<Record<MarginSide, string>>;
		};
	};
};

type MarginSide = 'top' | 'right' | 'bottom' | 'left';

type ElementEditProps = {
	name: string;
	attributes: ElementAttributes;
	setAttributes: (attributes: Partial<ElementAttributes>) => void;
};

/**
 * Convert a spacing value to CSS, e.g. a preset reference "var:preset|spacing|40" to "var(--wp--preset--spacing--40)".
 *
 * @param {string} value The spacing value from the block attributes.
 * @return {string} The CSS value.
 */
const toCssValue = (value: string): string =>
	value.startsWith('var:')
		? `var(--wp--${value.slice(4).split('|').join('--')})`
		: value;

/**
 * Get the inline margin style from the block's spacing attributes.
 *
 * @param {ElementAttributes} attributes The block attributes.
 * @return {React.CSSProperties} The margin style, e.g. { marginLeft: '40px' }.
 */
const getMarginStyle = (attributes: ElementAttributes): React.CSSProperties => {
	const margin = attributes.style?.spacing?.margin || {};
	const style: React.CSSProperties = {};
	const properties: Record<MarginSide, keyof React.CSSProperties> = {
		top: 'marginTop',
		right: 'marginRight',
		bottom: 'marginBottom',
		left: 'marginLeft',
	};

	(Object.keys(properties) as MarginSide[]).forEach((side) => {
		const value = margin[side];
		if (typeof value === 'string' && value !== '') {
			(style as Record<string, string>)[properties[side]] =
				toCssValue(value);
		}
	});

	return style;
};

/**
 * Shared edit component for the Kustom Elements blocks. Renders the element server side, the same way as on the frontend.
 *
 * @param {ElementEditProps} props The block edit props.
 */
export const ElementEdit = ({
	name,
	attributes,
	setAttributes,
}: ElementEditProps) => {
	const blockProps = useBlockProps();

	// The margin is not serialized on the wrapper (see block.json), apply it to an inner element like the PHP render does.
	const marginStyle = getMarginStyle(attributes);

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__('Settings', 'klarna-checkout-for-woocommerce')}
				>
					<TextControl
						label={__('Include', 'klarna-checkout-for-woocommerce')}
						help={__(
							'Optional. Comma-separated list of methods to show.',
							'klarna-checkout-for-woocommerce'
						)}
						value={attributes.include}
						onChange={(include: string) =>
							setAttributes({ include })
						}
					/>
					<TextControl
						label={__('Exclude', 'klarna-checkout-for-woocommerce')}
						help={__(
							'Optional. Comma-separated list of methods to hide.',
							'klarna-checkout-for-woocommerce'
						)}
						value={attributes.exclude}
						onChange={(exclude: string) =>
							setAttributes({ exclude })
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<div style={marginStyle}>
					<Disabled>
						<ServerSideRender
							block={name}
							attributes={attributes}
							skipBlockSupportAttributes
						/>
					</Disabled>
				</div>
			</div>
		</>
	);
};

// Dynamic block, rendered by PHP.
/**
 *
 */
export const ElementSave = (): null => null;
