<?php

/**
 * PHP file to use when rendering the block type on the server to show on the front end.
 *
 * The following variables are exposed to the file:
 *     $attributes (array): The block attributes.
 *     $content (string): The block default content.
 *     $block (WP_Block): The block instance.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

$company_name = ! empty($attributes['companyName']) ? $attributes['companyName'] : 'twistnflip';
$current_year = date('Y');

// Build the safe HTML link for Dhali
$dhali_link = '<a href="https://dhali.com" target="_blank" rel="nofollow noopener"
	style="text-decoration: none; color: inherit;">Designed by Dhali</a>';
?>

<p <?php echo get_block_wrapper_attributes(); ?>>
	<?php
	printf(
		/* translators: 1: Current Year, 2: Client Company Name, 3: Agency Link HTML */
		__('&copy; %1$s · %2$s · All rights reserved. %3$s.', 'dhali-copyright'),
		esc_html($current_year),
		esc_html($company_name),
		$dhali_link // Notice this is NOT wrapped in esc_html(), otherwise the <a> tag will print as raw text!
	);
	?>
</p>
