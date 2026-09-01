<?php
/**
 * Oxygen Builder access for the Site Admin role.
 *
 * Grants the "Site Admin" role the builder's native "Edit Content Interface
 * Only" access so clients can edit page content in the Oxygen Builder without
 * full (administrator) control. Templates, headers, footers, popups and global
 * blocks stay locked to administrators server-side by Oxygen itself.
 *
 * This only writes the permission *data* Oxygen already reads (the same values
 * the hidden "User Access" settings tab would write); it does not touch any
 * plugin internals, so it is safe across Oxygen updates. Revisit when Oxygen
 * ships its official client-control feature and remove if superseded.
 *
 * @package CurlySiteTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

curly_site_tools_register_toggle(
	'oxygen_site_admin_builder_edit',
	__( 'Site Admin Oxygen Builder access', 'curly-site-tools' ),
	__( 'Grant the "Site Admin" role "Edit Content Interface Only" access in the Oxygen Builder (edit page text, links, images, and rearrange/duplicate elements; templates and global settings stay locked to admins).', 'curly-site-tools' ),
	true
);

/**
 * Apply the Site Admin builder permission when the toggle is enabled.
 *
 * Runs on `breakdance_loaded` so the Breakdance\Permissions API exists, but
 * degrades gracefully on sites without Oxygen (no action, no error).
 */
add_action(
	'breakdance_loaded',
	function () {
		if ( ! curly_site_tools_is_enabled( 'oxygen_site_admin_builder_edit' ) ) {
			return;
		}

		if ( ! function_exists( '\Breakdance\Permissions\setRolesPermissions' ) ) {
			return;
		}

		$roles = \Breakdance\Permissions\getRolesPermissions();

		// Keep administrators on full access.
		$roles['administrator'] = 'full';

		// Grant the Site Admin role edit-content builder access.
		if ( array_key_exists( 'site_admin', $roles ) ) {
			$roles['site_admin'] = 'edit';
		}

		\Breakdance\Permissions\setRolesPermissions( $roles );
	},
	20
);
