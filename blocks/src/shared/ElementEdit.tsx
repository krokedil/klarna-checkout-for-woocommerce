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
				<Disabled>
					<ServerSideRender block={ name } attributes={ attributes } />
				</Disabled>
			</div>
		</>
	);
};

// Dynamic block, rendered by PHP.
export const ElementSave = (): null => null;
