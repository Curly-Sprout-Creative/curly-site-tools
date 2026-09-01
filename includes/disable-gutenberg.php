<?php
/**
 * Disable the Gutenberg/block editor, forcing the Classic editor.
 *
 * Snippet 5.
 *
 * @package CurlySiteTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

curly_site_tools_register_toggle(
	'disable_gutenberg',
	__( 'Disable Gutenberg (use Classic editor)', 'curly-site-tools' ),
	__( 'Force the Classic editor instead of the block editor for all post types.', 'curly-site-tools' ),
	true
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! curly_site_tools_is_enabled( 'disable_gutenberg' ) ) {
			return;
		}
		add_filter( 'gutenberg_can_edit_post', '__return_false', 5 );
		add_filter( 'use_block_editor_for_post', '__return_false', 5 );
	},
	20
);
