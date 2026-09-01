<?php
/**
 * Completely disable comments everywhere.
 *
 * Snippet 9.
 *
 * @package CurlySiteTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

curly_site_tools_register_toggle(
	'disable_comments',
	__( 'Completely disable comments', 'curly-site-tools' ),
	__( 'Removes comments and trackbacks everywhere — admin pages, menus, post-type support, and the front end.', 'curly-site-tools' ),
	true
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! curly_site_tools_is_enabled( 'disable_comments' ) ) {
			return;
		}

		add_action(
			'admin_init',
			function () {
				global $pagenow;

				// Redirect any user trying to access the comments page.
				if ( 'edit-comments.php' === $pagenow ) {
					wp_safe_redirect( admin_url() );
					exit;
				}

				// Remove the comments metabox from the dashboard.
				remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );

				// Disable support for comments and trackbacks in post types.
				foreach ( get_post_types() as $post_type ) {
					if ( post_type_supports( $post_type, 'comments' ) ) {
						remove_post_type_support( $post_type, 'comments' );
						remove_post_type_support( $post_type, 'trackbacks' );
					}
				}
			}
		);

		// Close comments on the front end.
		add_filter( 'comments_open', '__return_false', 20, 2 );
		add_filter( 'pings_open', '__return_false', 20, 2 );

		// Hide existing comments.
		add_filter( 'comments_array', '__return_empty_array', 10, 2 );

		// Remove the comments page from the menu.
		add_action( 'admin_menu', function () {
			remove_menu_page( 'edit-comments.php' );
		} );

		// Remove comments links from the admin bar.
		add_action(
			'init',
			function () {
				if ( is_admin_bar_showing() ) {
					remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );
				}
			}
		);
	},
	20
);
