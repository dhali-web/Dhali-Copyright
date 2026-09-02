/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from "@wordpress/i18n";

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps, InspectorControls } from "@wordpress/block-editor";
import { TextControl, PanelBody } from "@wordpress/components";

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import "./editor.scss";

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
	const { companyName } = attributes;
	return (
		<div {...useBlockProps()}>
			{/* 1. InspectorControls moves this entire section to the right sidebar */}
			<InspectorControls>
				<PanelBody
					title={__("Copyright Settings", "dhali-copyright")}
					initialOpen={true}
				>
					<TextControl
						label="Company Name"
						value={companyName}
						onChange={(newName) => setAttributes({ companyName: newName })}
						help="Enter the company name to display in the copyright."
					/>
				</PanelBody>
			</InspectorControls>
			<p>
				© {new Date().getFullYear()} · {companyName || "Client Name"} · All
				rights reserved. Designed by Dhali.
			</p>
		</div>
	);
}
