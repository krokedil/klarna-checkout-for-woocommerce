/**
 * Wordpress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import { ElementEdit, ElementSave } from '../shared/ElementEdit';

// The block metadata comes from block.json, registered server side in src/Elements/Block.php.
registerBlockType( 'kustom/payment-element', {
	edit: ElementEdit,
	save: ElementSave,
} as any ); // eslint-disable-line @typescript-eslint/no-explicit-any
