/**
 * External dependencies
 */
import * as React from 'react';

/**
 * WordPress dependencies
 *
 * These packages are externalised at build time by the WooCommerce dependency
 * extraction plugin and loaded from the wp.* globals, so TypeScript cannot
 * resolve their declarations — suppressed the same way the Checkout block does.
 */
// @ts-ignore - resolved from the wp globals at runtime, not from node_modules.
// eslint-disable-next-line import/no-unresolved
import { registerBlockType } from '@wordpress/blocks';
// @ts-ignore - resolved from the wp globals at runtime, not from node_modules.
// eslint-disable-next-line import/no-unresolved
import { useBlockProps } from '@wordpress/block-editor';
// @ts-ignore - resolved from the wp globals at runtime, not from node_modules.
// eslint-disable-next-line import/no-unresolved
import { Placeholder } from '@wordpress/components';

/**
 * Data localised from PHP onto every Kustom Elements editor script.
 *
 * `locale` is the globally configured Kustom Elements locale and is baked into
 * the saved markup as a frozen attribute (the web components require it).
 * `logoUrl` is used purely for the editor placeholder.
 */
declare global {
	interface Window {
		kcoKustomElements?: {
			locale?: string;
			logoUrl?: string;
		};
	}
}

/**
 * Returns the globally configured Kustom Elements locale.
 *
 * Read at block-registration time so it can seed the block's default `locale`
 * attribute. Settings::get_locale() on the PHP side always resolves to a value
 * (worst case en-GB), so this is never empty in practice.
 *
 * @return {string} The configured locale, or an empty string if unavailable.
 */
const getConfiguredLocale = (): string => window.kcoKustomElements?.locale || '';

/**
 * Configuration for a single Kustom Elements display block.
 *
 * @property {string} name            Block name, e.g. 'kco/payment-method-display'.
 * @property {string} title           Human-readable block title shown in the inserter.
 * @property {string} description     Short description shown in the inserter.
 * @property {string} tag             Custom element tag rendered on the frontend.
 * @property {string} icon            Dashicon slug used as the block icon.
 * @property {string} placeholderText Text shown in the editor placeholder.
 */
type ElementBlockConfig = {
	name: string;
	title: string;
	description: string;
	tag: string;
	icon: string;
	placeholderText: string;
};

/**
 * Registers a static Kustom Elements display block.
 *
 * The block carries no editor controls — it simply writes the configured
 * web component tag (with the globally configured locale) into post content
 * via save(). The frontend serves that markup as-is; the Kustom Elements
 * loader script (injected separately) wakes the component up.
 *
 * @param {ElementBlockConfig} config Per-element configuration.
 * @return {void}
 */
export function registerKustomElementBlock( config: ElementBlockConfig ): void {
	// Cast to a loose signature — the bundled @wordpress/blocks types model a
	// stricter BlockConfiguration than we need for this static block.
	const register = registerBlockType as ( name: string, settings: object ) => void;

	// Capitalised so ESLint's rules-of-hooks recognises these as components
	// (useBlockProps is a hook). config is captured from the closure.

	/**
	 * Editor view — a static placeholder; the real element renders on the frontend.
	 *
	 * @return {JSX.Element} The block's editor placeholder.
	 */
	const Edit = (): JSX.Element => {
		const blockProps = useBlockProps( {
			className: 'kco-kustom-element-placeholder',
		} );
		const logoUrl = window.kcoKustomElements?.logoUrl;

		return (
			<div { ...blockProps }>
				<Placeholder
					label={ config.title }
					instructions={ config.placeholderText }
				>
					{ logoUrl ? (
						<img
							src={ logoUrl }
							alt="Kustom"
							style={ { maxHeight: 24, width: 'auto' } }
						/>
					) : null }
				</Placeholder>
			</div>
		);
	};

	/**
	 * Saved markup written to post content.
	 *
	 * @param {Object} root0                   Block props supplied by the editor.
	 * @param {Object} root0.attributes        The block's stored attributes.
	 * @param {string} root0.attributes.locale The frozen locale for the web component.
	 * @return {JSX.Element} The serialized web component wrapper.
	 */
	const Save = ( { attributes }: { attributes: { locale: string } } ): JSX.Element => {
		const blockProps = useBlockProps.save();

		// `locale` comes purely from the stored attribute so save() stays
		// deterministic and the block validates across environments.
		return (
			<div { ...blockProps }>
				{ React.createElement( config.tag, { locale: attributes.locale } ) }
			</div>
		);
	};

	register( config.name, {
		apiVersion: 2,
		title: config.title,
		description: config.description,
		icon: config.icon,
		category: 'woocommerce',
		attributes: {
			// Frozen at insert time from the global setting; required by the web component.
			locale: {
				type: 'string',
				default: getConfiguredLocale(),
			},
		},
		// Renders Edit as the inserter preview.
		example: {},
		edit: Edit,
		save: Save,
	} );
}
