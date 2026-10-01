/**
 * External dependencies
 */
import * as React from 'react';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
// @ts-ignore - The package does not ship type declarations.
import { InspectorControls, useBlockProps, __experimentalGetSpacingClassesAndStyles as getSpacingClassesAndStyles } from '@wordpress/block-editor';
import { Disabled, PanelBody, TextControl } from '@wordpress/components';
// @ts-ignore - The package does not ship type declarations.
import ServerSideRender from '@wordpress/server-side-render';

type ElementAttributes = {
	include: string;
	exclude: string;
	style?: Record< string, unknown >;
};

type ElementEditProps = {
	name: string;
	attributes: ElementAttributes;
	setAttributes: ( attributes: Partial< ElementAttributes > ) => void;
};

/**
 * Shared edit component for the Kustom Elements blocks. Renders the element server side, the same way as on the frontend.
 *
 * @param {ElementEditProps} props The block edit props.
 */
export const ElementEdit = ( { name, attributes, setAttributes }: ElementEditProps ) => {
	const blockProps = useBlockProps();

	// The margin is not serialized on the wrapper (see block.json), apply it to an inner element like the PHP render does.
	const marginStyle = Object.fromEntries(
		Object.entries( getSpacingClassesAndStyles( attributes ).style || {} ).filter( ( [ property ] ) => property.startsWith( 'margin' ) )
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'klarna-checkout-for-woocommerce' ) }>
					<TextControl
						label={ __( 'Include', 'klarna-checkout-for-woocommerce' ) }
						help={ __( 'Optional. Comma-separated list of methods to show.', 'klarna-checkout-for-woocommerce' ) }
						value={ attributes.include }
						onChange={ ( include: string ) => setAttributes( { include } ) }
					/>
					<TextControl
						label={ __( 'Exclude', 'klarna-checkout-for-woocommerce' ) }
						help={ __( 'Optional. Comma-separated list of methods to hide.', 'klarna-checkout-for-woocommerce' ) }
						value={ attributes.exclude }
						onChange={ ( exclude: string ) => setAttributes( { exclude } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div style={ marginStyle }>
					<Disabled>
						<ServerSideRender block={ name } attributes={ attributes } skipBlockSupportAttributes />
					</Disabled>
				</div>
			</div>
		</>
	);
};

// Dynamic block, rendered by PHP.
export const ElementSave = (): null => null;
