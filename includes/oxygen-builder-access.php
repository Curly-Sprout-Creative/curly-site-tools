<?php
/**
 * Oxygen Builder access for the Site Admin role.
 *
 * Grants the "Site Admin" role the builder's native "Edit Content Interface
 * Only" access so clients can edit page content in the Oxygen Builder without
 * full (administrator) control.
 *
 * As of Oxygen 6.2 beta 9 the builder enforces that level in the app: an `edit`
 * user can change text, links, and images, but adding, removing, copying,
 * dragging/reordering elements, and templates / headers / footers / global
 * blocks / global settings all require `full` and are locked to administrators.
 *
 * This toggle is the GRANT, not the restriction: without it the permission map
 * has no `site_admin` entry, and Oxygen falls back to `none` (no builder access
 * at all). Disabling it removes the entry so access is revoked.
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
	__( 'Grant the "Site Admin" role "Edit Content Interface Only" access in the Oxygen Builder: edit page text, links, and images. Adding, removing, copying, dragging/reordering elements, and templates / global settings require full administrator access.', 'curly-site-tools' ),
	true
);

/**
 * Maintain the Site Admin builder permission when the toggle state changes.
 *
 * Runs on `breakdance_loaded` (i.e. during `plugins_loaded`). We write the
 * `oxygen_settings_permissions` option DIRECTLY via WordPress rather than
 * calling `\Breakdance\Permissions\getRolesPermissions()`: that function's
 * internal `_getRoles()` instantiates `new WP_Roles()` inside the
 * `Breakdance\Permissions` namespace, which PHP resolves to the non-existent
 * `\Breakdance\Permissions\WP_Roles` and fatals whenever `$wp_roles` isn't yet
 * initialized (front-end requests and wp-cli). Oxygen stores this option as
 * plain JSON via `set_global_option()`, so writing it with `update_option()`
 * is byte-for-byte equivalent and safe in every context.
 *
 * Gated to admin requests (the builder only runs in wp-admin) so front-end
 * page loads never pay for the option read/write. Degrades gracefully on sites
 * without Oxygen (no action, no error).
 */
add_action(
	'breakdance_loaded',
	function () {
		if ( ! defined( '__BREAKDANCE_VERSION' ) ) {
			return;
		}

		if ( ! is_admin() ) {
			return;
		}

		$enabled = curly_site_tools_is_enabled( 'oxygen_site_admin_builder_edit' );

		$raw   = get_option( 'oxygen_settings_permissions', '' );
		$roles = ( is_string( $raw ) && '' !== $raw ) ? json_decode( $raw, true ) : array();
		if ( ! is_array( $roles ) ) {
			$roles = array();
		}

		if ( $enabled ) {
			// Administrators are always forced to full by Oxygen; only the
			// Site Admin entry needs maintaining here.
			if ( ( $roles['site_admin'] ?? null ) === 'edit' ) {
				return;
			}
			$roles['site_admin'] = 'edit';
		} else {
			// No-op if the entry is already gone.
			if ( ! array_key_exists( 'site_admin', $roles ) ) {
				return;
			}
			unset( $roles['site_admin'] );
		}

		if ( $roles ) {
			update_option( 'oxygen_settings_permissions', wp_json_encode( $roles ), false );
		} else {
			delete_option( 'oxygen_settings_permissions' );
		}
	},
	20
);
