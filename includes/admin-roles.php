<?php
/**
 * Site Admin role creation + enforcement.
 *
 * Snippet 1 (role create) runs ONCE on plugin activation, not on every page
 * load. Snippet 19 (enforcement) is gated behind the "site_admin_role" toggle.
 *
 * @package CurlySiteTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

curly_site_tools_register_toggle(
	'site_admin_role',
	__( 'Site Admin role & enforcement', 'curly-site-tools' ),
	__( 'Create a limited "Site Admin" role (Editor + non-admin user management + AI1WM export + Menus access) and restrict it from editing administrators.', 'curly-site-tools' ),
	true
);

/**
 * Create/refresh the Site Admin role. Called on plugin activation.
 */
function curly_site_tools_create_role() {
	// Rebuild the role so capability tweaks apply immediately.
	if ( get_role( 'site_admin' ) ) {
		remove_role( 'site_admin' );
	}

	$editor = get_role( 'editor' );
	if ( ! $editor ) {
		return;
	}

	$site_admin_caps = $editor->capabilities;

	// Manage (non-admin) users.
	$site_admin_caps['list_users']    = true;
	$site_admin_caps['edit_users']    = true;
	$site_admin_caps['create_users']  = true;
	$site_admin_caps['promote_users'] = true;

	// AI1WM export.
	$site_admin_caps['export']       = true;
	$site_admin_caps['ai1wm_export'] = true;

	// Statistics.
	$site_admin_caps['view_burst_statistics'] = true;

	// Required for Menus screen (Appearance > Menus). This also unlocks the
	// Customizer/Site Editor entry points, so those menus are hidden below.
	$site_admin_caps['edit_theme_options'] = true;

	add_role( 'site_admin', 'Site Admin', $site_admin_caps );
}

/**
 * Enforcement hooks, only when the toggle is enabled.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! curly_site_tools_is_enabled( 'site_admin_role' ) ) {
			return;
		}

		// Block Site Admins from editing/promoting Administrators.
		add_filter(
			'map_meta_cap',
			function ( $caps, $cap, $user_id, $args ) {
				if ( in_array( $cap, array( 'edit_user', 'promote_user' ), true ) ) {
					$target_user_id = isset( $args[0] ) ? (int) $args[0] : 0;
					if ( $target_user_id && user_can( $target_user_id, 'administrator' ) ) {
						$current_user = wp_get_current_user();
						if ( in_array( 'site_admin', (array) $current_user->roles, true ) ) {
							$caps[] = 'do_not_allow';
						}
					}
				}
				return $caps;
			},
			10,
			4
		);

		// Hide the Administrator role from Site Admins.
		add_filter(
			'editable_roles',
			function ( $roles ) {
				if ( current_user_can( 'site_admin' ) && ! current_user_can( 'administrator' ) ) {
					unset( $roles['administrator'] );
				}
				return $roles;
			}
		);

		// Admin menu adjustments for Site Admins.
		add_action(
			'admin_menu',
			function () {
				if ( current_user_can( 'site_admin' ) && ! current_user_can( 'administrator' ) ) {
					remove_submenu_page( 'themes.php', 'themes.php' );
					remove_submenu_page( 'themes.php', 'customize.php' );
					remove_submenu_page( 'themes.php', 'site-editor.php' );
					remove_submenu_page( 'themes.php', 'widgets.php' );
					remove_submenu_page( 'themes.php', 'theme-editor.php' );
					remove_submenu_page( 'themes.php', 'theme-install.php' );

					add_menu_page(
						'Menus',
						'Menus',
						'edit_theme_options',
						'nav-menus.php',
						'',
						'dashicons-menu',
						59
					);
				}
			},
			99
		);

		// AI1WM menu label tweak for Site Admins.
		add_action(
			'admin_menu',
			function () {
				if ( current_user_can( 'site_admin' ) && ! current_user_can( 'manage_options' ) ) {
					remove_menu_page( 'ai1wm_export' );
					add_menu_page(
						'Site Export and Backup',
						'Site Export and Backup',
						'ai1wm_export',
						'ai1wm_export',
						'',
						'dashicons-download',
						80
					);
				}
			},
			100
		);

		// Hide premium AI1WM links for Site Admins.
		add_action(
			'admin_head',
			function () {
				if ( current_user_can( 'site_admin' ) && ! current_user_can( 'manage_options' ) ) {
					echo '<style>a[href*="ai1wm_reset"], a[href*="ai1wm_schedules"]{display:none!important;}</style>';
				}
			}
		);
	},
	20
);
