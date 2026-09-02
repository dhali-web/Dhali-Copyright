<?php
// This file is generated. Do not modify it manually.
return array(
	'dhali-copyright' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'create-block/dhali-copyright',
		'version' => '0.1.0',
		'title' => 'Dhali Copyright',
		'category' => 'widgets',
		'icon' => 'privacy',
		'description' => 'Example block scaffolded with Create Block tool.',
		'example' => array(
			
		),
		'attributes' => array(
			'companyName' => array(
				'type' => 'string',
				'default' => '<Client Name>'
			)
		),
		'supports' => array(
			'html' => false,
			'color' => array(
				'text' => true,
				'background' => true,
				'link' => true
			),
			'spacing' => array(
				'padding' => true,
				'margin' => true
			),
			'align' => array(
				'left',
				'center',
				'right',
				'wide',
				'full'
			),
			'typography' => array(
				'fontSize' => true,
				'fontFamily' => true,
				'fontWeight' => true,
				'fontStyle' => true,
				'lineHeight' => true,
				'textTransform' => true,
				'letterSpacing' => true,
				'textDecoration' => true
			)
		),
		'textdomain' => 'dhali-copyright',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php',
		'viewScript' => 'file:./view.js'
	)
);
