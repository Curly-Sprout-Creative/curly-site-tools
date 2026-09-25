<?php
/**
 * Host Google Fonts locally (Breakdance / Oxygen).
 *
 * Breakdance registers Google Fonts and prints a fonts.googleapis.com stylesheet
 * link on the front end. This include intercepts that URL, downloads the CSS and
 * its woff2 files into wp-content/uploads/curly-site-tools/fonts/, and serves the
 * local copy instead, so no visitor data is sent to Google.
 *
 * The build runs automatically the first time the Tools > Curly Site Tools page
 * is opened after enabling the toggle, and can be re-run with the
 * "Fetch / refresh fonts" button (e.g. after changing fonts). On non-Oxygen
 * sites the breakdance_google_fonts_url filter simply never fires.
 *
 * @package CurlySiteTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CURLY_SITE_TOOLS_GF_REMOTE   = 'curly_site_tools_google_fonts_remote_url';
const CURLY_SITE_TOOLS_GF_LOCAL    = 'curly_site_tools_google_fonts_local_url';
const CURLY_SITE_TOOLS_GF_BUILT_AT = 'curly_site_tools_google_fonts_built_at';
const CURLY_SITE_TOOLS_GF_ACTION   = 'curly_site_tools_refresh_fonts';

curly_site_tools_register_toggle(
	'local_google_fonts',
	__( 'Host Google Fonts locally', 'curly-site-tools' ),
	__( 'Downloads the Google Fonts CSS + woff2 files into uploads and serves them from this site, so visitors never contact fonts.googleapis.com.', 'curly-site-tools' ),
	false
);

/**
 * Uploads subdirectory for the localized font assets.
 *
 * @return array{path:string,url:string}
 */
function curly_site_tools_fonts_dir() {
	$upload = wp_upload_dir();
	$path   = trailingslashit( $upload['basedir'] ) . 'curly-site-tools/fonts';
	$url    = trailingslashit( $upload['baseurl'] ) . 'curly-site-tools/fonts';

	if ( ! file_exists( $path ) ) {
		wp_mkdir_p( $path );
	}

	return array(
		'path' => $path,
		'url'  => $url,
	);
}

/**
 * Browser user agent used when asking Google for the CSS. A modern UA makes
 * Google return woff2 variable fonts rather than legacy ttf/woff.
 *
 * @return string
 */
function curly_site_tools_fonts_user_agent() {
	return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36';
}

/**
 * Capture the current remote Google Fonts URL.
 *
 * The filter receives the remote URL on every front end request, so it is
 * normally already stored. As a fallback, request the home page once so the
 * filter fires and records it.
 *
 * @return string
 */
function curly_site_tools_capture_google_fonts_url() {
	$stored = (string) get_option( CURLY_SITE_TOOLS_GF_REMOTE, '' );
	if ( '' !== $stored ) {
		return $stored;
	}

	$response = wp_remote_get(
		add_query_arg( 'curly_fonts_probe', time(), home_url( '/' ) ),
		array(
			'timeout'     => 20,
			'redirection' => 3,
		)
	);

	if ( is_wp_error( $response ) ) {
		return '';
	}

	// The first get_option() above may have cached an empty value in this
	// request; drop it so the loopback request's stored URL is read.
	wp_cache_delete( CURLY_SITE_TOOLS_GF_REMOTE, 'options' );
	$stored = (string) get_option( CURLY_SITE_TOOLS_GF_REMOTE, '' );
	if ( '' !== $stored ) {
		return $stored;
	}

	// The loopback may have been answered from a page cache, in which case our
	// filter never ran. The cached HTML still contains the Google Fonts <link>,
	// so read the URL straight out of it.
	$body = wp_remote_retrieve_body( $response );
	if ( preg_match( '#https://fonts\.googleapis\.com/css2[^"\'<>\s]+#', $body, $matches ) ) {
		$url = html_entity_decode( $matches[0], ENT_QUOTES );
		update_option( CURLY_SITE_TOOLS_GF_REMOTE, $url, false );
		return $url;
	}

	return '';
}

/**
 * Download the Google Fonts CSS + woff2 files and store a local stylesheet.
 *
 * @return true|\WP_Error
 */
function curly_site_tools_localize_google_fonts() {
	$remote = curly_site_tools_capture_google_fonts_url();

	if ( '' === $remote || false === strpos( $remote, 'fonts.googleapis.com' ) ) {
		return new \WP_Error(
			'no_url',
			__( 'Could not determine the Google Fonts URL. Enable the toggle, load the site once, then try again.', 'curly-site-tools' )
		);
	}

	$response = wp_remote_get(
		$remote,
		array(
			'timeout' => 20,
			'headers' => array(
				'User-Agent' => curly_site_tools_fonts_user_agent(),
				'Accept'     => 'text/css,*/*;q=0.1',
			),
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return new \WP_Error( 'fetch_css', __( 'Could not download the Google Fonts stylesheet.', 'curly-site-tools' ) );
	}

	$css = wp_remote_retrieve_body( $response );

	if ( ! preg_match_all( '#url\(\s*[\'"]?(https://fonts\.gstatic\.com/[^\'")\s]+)[\'"]?\s*\)#', $css, $matches ) ) {
		return new \WP_Error( 'no_fonts', __( 'No font files were found in the Google Fonts stylesheet.', 'curly-site-tools' ) );
	}

	$dir   = curly_site_tools_fonts_dir();
	$urls  = array_unique( $matches[1] );
	$saved = 0;

	foreach ( $urls as $url ) {
		$basename = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$filename = substr( md5( $url ), 0, 8 ) . '-' . sanitize_file_name( $basename );
		$dest     = $dir['path'] . '/' . $filename;

		if ( ! file_exists( $dest ) ) {
			$font = wp_remote_get(
				$url,
				array(
					'timeout' => 20,
					'headers' => array( 'User-Agent' => curly_site_tools_fonts_user_agent() ),
				)
			);

			if ( is_wp_error( $font ) || 200 !== (int) wp_remote_retrieve_response_code( $font ) ) {
				continue;
			}

			file_put_contents( $dest, wp_remote_retrieve_body( $font ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		if ( file_exists( $dest ) ) {
			$css = str_replace( $url, $dir['url'] . '/' . $filename, $css );
			$saved++;
		}
	}

	if ( 0 === $saved ) {
		return new \WP_Error( 'save_failed', __( 'None of the font files could be downloaded.', 'curly-site-tools' ) );
	}

	file_put_contents( $dir['path'] . '/fonts.css', $css ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

	update_option( CURLY_SITE_TOOLS_GF_LOCAL, $dir['url'] . '/fonts.css' );
	update_option( CURLY_SITE_TOOLS_GF_BUILT_AT, time() );

	return true;
}

// Serve the local stylesheet and record the remote URL whenever it is known.
add_filter(
	'breakdance_google_fonts_url',
	function ( $url ) {
		if ( ! curly_site_tools_is_enabled( 'local_google_fonts' ) ) {
			return $url;
		}

		if ( false !== strpos( $url, 'fonts.googleapis.com' ) ) {
			update_option( CURLY_SITE_TOOLS_GF_REMOTE, $url, false );
		}

		$local = (string) get_option( CURLY_SITE_TOOLS_GF_LOCAL, '' );
		if ( '' !== $local ) {
			$built_at = (int) get_option( CURLY_SITE_TOOLS_GF_BUILT_AT, 0 );
			return $local . ( $built_at ? '?ver=' . $built_at : '' );
		}

		return $url;
	}
);

// Auto-build when the plugin settings page is opened and no local CSS exists.
add_action(
	'admin_init',
	function () {
		if ( ! curly_site_tools_is_enabled( 'local_google_fonts' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'curly-site-tools' !== $page ) {
			return;
		}

		if ( '' !== (string) get_option( CURLY_SITE_TOOLS_GF_LOCAL, '' ) ) {
			return;
		}

		if ( get_transient( 'curly_site_tools_fonts_auto_try' ) ) {
			return;
		}

		set_transient( 'curly_site_tools_fonts_auto_try', 1, 5 * MINUTE_IN_SECONDS );
		curly_site_tools_localize_google_fonts();
	}
);

// Refresh handler.
add_action(
	'admin_post_' . CURLY_SITE_TOOLS_GF_ACTION,
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'curly-site-tools' ) );
		}

		check_admin_referer( CURLY_SITE_TOOLS_GF_ACTION );

		$result = curly_site_tools_localize_google_fonts();

		$args = is_wp_error( $result )
			? array(
				'cst_fonts' => 'error',
				'cst_msg'   => rawurlencode( $result->get_error_message() ),
			)
			: array( 'cst_fonts' => 'ok' );

		wp_safe_redirect( add_query_arg( $args, admin_url( 'tools.php?page=curly-site-tools' ) ) );
		exit;
	}
);

// Admin UI: refresh button + status, rendered after the toggle form.
add_action(
	'curly_site_tools_admin_after_form',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$local    = (string) get_option( CURLY_SITE_TOOLS_GF_LOCAL, '' );
		$built_at = (int) get_option( CURLY_SITE_TOOLS_GF_BUILT_AT, 0 );
		?>
		<hr />
		<h2><?php esc_html_e( 'Local Google Fonts', 'curly-site-tools' ); ?></h2>
		<p>
			<?php if ( '' !== $local ) : ?>
				<?php
				printf(
					/* translators: %s: human-readable time difference. */
					esc_html__( 'Last built %s ago.', 'curly-site-tools' ),
					esc_html( human_time_diff( $built_at, time() ) )
				);
				?>
			<?php else : ?>
				<?php esc_html_e( 'Not built yet. Enable the toggle and click the button below.', 'curly-site-tools' ); ?>
			<?php endif; ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( CURLY_SITE_TOOLS_GF_ACTION ); ?>" />
			<?php wp_nonce_field( CURLY_SITE_TOOLS_GF_ACTION ); ?>
			<?php submit_button( __( 'Fetch / refresh fonts', 'curly-site-tools' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}
);

// Result notice.
add_action(
	'admin_notices',
	function () {
		if ( ! isset( $_GET['cst_fonts'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$state = sanitize_key( wp_unslash( $_GET['cst_fonts'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'ok' === $state ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Google Fonts were downloaded and are now served locally.', 'curly-site-tools' ) . '</p></div>';
		} elseif ( 'error' === $state ) {
			$msg = isset( $_GET['cst_msg'] ) ? rawurldecode( sanitize_text_field( wp_unslash( $_GET['cst_msg'] ) ) ) : __( 'The fonts could not be downloaded.', 'curly-site-tools' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}
);
