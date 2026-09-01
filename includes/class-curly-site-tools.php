<?php
/**
 * Core class for Curly Site Tools.
 *
 * Registers the central toggle registry, exposes the admin Tools page, and
 * loads each feature include. Features register themselves into the registry
 * via curly_site_tools_register_toggle() and gate their hooks behind
 * curly_site_tools_is_enabled().
 *
 * @package CurlySiteTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Curly_Site_Tools {

	const OPTION_GROUP = 'curly_site_tools_group';
	const OPTION_NAME  = 'curly_site_tools_enabled';

	/** @var Curly_Site_Tools|null */
	private static $instance = null;

	/** @var array[] Registry of toggles, keyed by toggle id. */
	private $toggles = array();

	/** @var bool Whether the toggle registry has been locked. */
	private $locked = false;

	/** @var bool Whether settings/options have been initialized. */
	private $options_loaded = false;

	/** @var string[] Loaded option values, keyed by toggle id. */
	private $option_values = array();

	/** @var bool Whether the admin page has been registered. */
	private $admin_page_registered = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( CURLY_SITE_TOOLS_FILE, array( $this, 'on_activation' ) );

		add_action( 'plugins_loaded', array( $this, 'load_includes' ), 5 );
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Register the JS toggles and enqueue the matching front-end assets.
	 */
	public function enqueue_frontend_assets() {
		if ( curly_site_tools_is_enabled( 'ios_background_fix' ) ) {
			wp_enqueue_script(
				'curly-site-tools-ios-background-fix',
				CURLY_SITE_TOOLS_URL . 'assets/js/ios-background-fix.js',
				array(),
				CURLY_SITE_TOOLS_VERSION,
				true
			);
		}

		if ( curly_site_tools_is_enabled( 'external_links_new_tab' ) ) {
			wp_enqueue_script(
				'curly-site-tools-external-links',
				CURLY_SITE_TOOLS_URL . 'assets/js/external-links.js',
				array(),
				CURLY_SITE_TOOLS_VERSION,
				true
			);
		}
	}

	/**
	 * Load the feature include files.
	 */
	public function load_includes() {
		// Front-end JS toggles (assets, not PHP includes).
		$this->register_toggle(
			'ios_background_fix',
			__( 'iOS background-attachment fix', 'curly-site-tools' ),
			__( 'On iOS, swap .fixed-bg elements to .scroll-bg (parallax fix).', 'curly-site-tools' ),
			true
		);
		$this->register_toggle(
			'external_links_new_tab',
			__( 'Open offsite links in a new tab', 'curly-site-tools' ),
			__( 'Front-end script: open links to other domains in a new tab with rel="noopener noreferrer".', 'curly-site-tools' ),
			true
		);

		$includes = array(
			'admin-roles.php',
			'disable-comments.php',
			'disable-gutenberg.php',
			'disable-update-emails.php',
			'media-handling.php',
			'post-utilities.php',
		);

		foreach ( $includes as $include ) {
			require_once CURLY_SITE_TOOLS_DIR . 'includes/' . $include;
		}

		$this->locked = true;
	}

	/**
	 * No-op placeholder so features can register toggles after includes load
	 * but before the registry is read. Kept for future load-order flexibility.
	 */
	public function load_toggle_registrations() {
		// Intentionally empty — includes self-register on include.
	}

	/**
	 * Register a toggle. Called by feature includes at load time.
	 *
	 * @param string $id          Unique slug for the toggle (also the option suffix).
	 * @param string $label       Short checkbox label.
	 * @param string $description Longer description shown under the label.
	 * @param bool   $default     Default enabled state.
	 */
	public function register_toggle( $id, $label, $description, $default = false ) {
		if ( $this->locked || isset( $this->toggles[ $id ] ) ) {
			return;
		}
		$this->toggles[ $id ] = array(
			'id'          => sanitize_key( $id ),
			'label'       => $label,
			'description' => $description,
			'default'     => (bool) $default,
		);
	}

	/**
	 * Whether a given toggle is enabled.
	 *
	 * @param string $id Toggle id.
	 * @return bool
	 */
	public function is_enabled( $id ) {
		$this->maybe_load_options();
		if ( ! isset( $this->option_values[ $id ] ) ) {
			// Fall back to the registered default.
			return isset( $this->toggles[ $id ] ) ? $this->toggles[ $id ]['default'] : false;
		}
		return (bool) $this->option_values[ $id ];
	}

	/**
	 * Get all registered toggles.
	 *
	 * @return array[]
	 */
	public function get_toggles() {
		return $this->toggles;
	}

	/**
	 * Load enabled-toggle option values from the DB (single autoloaded option).
	 */
	private function maybe_load_options() {
		if ( $this->options_loaded ) {
			return;
		}
		$this->options_loaded   = true;
		$stored                 = get_option( self::OPTION_NAME, array() );
		$this->option_values    = is_array( $stored ) ? $stored : array();
	}

	/**
	 * Activation: create the Site Admin role once and seed default toggles.
	 */
	public function on_activation() {
		// Ensure includes (and thus toggle registrations) are loaded even in the
		// activation context, which may not run a normal plugins_loaded cycle.
		$this->load_includes();

		// Create the Site Admin role on activation only (not every page load).
		if ( function_exists( 'curly_site_tools_create_role' ) ) {
			curly_site_tools_create_role();
		}

		// Seed the enabled-options array so defaults apply before first save.
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			$defaults = array();
			foreach ( $this->toggles as $id => $toggle ) {
				$defaults[ $id ] = $toggle['default'];
			}
			update_option( self::OPTION_NAME, $defaults, true );
		}
	}

	/**
	 * Register the Tools submenu page.
	 */
	public function register_admin_page() {
		if ( $this->admin_page_registered ) {
			return;
		}
		$this->admin_page_registered = true;
		add_management_page(
			__( 'Curly Site Tools', 'curly-site-tools' ),
			__( 'Curly Site Tools', 'curly-site-tools' ),
			'manage_options',
			'curly-site-tools',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Register the settings for the toggle checkboxes.
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_toggles' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize the submitted toggle array against the registry.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize_toggles( $input ) {
		$clean = array();
		$input = is_array( $input ) ? $input : array();

		foreach ( $this->toggles as $id => $toggle ) {
			$clean[ $id ] = ! empty( $input[ $id ] ) ? 1 : 0;
		}
		return $clean;
	}

	/**
	 * Render the admin page.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$this->maybe_load_options();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Curly Site Tools', 'curly-site-tools' ); ?></h1>
			<p><?php esc_html_e( 'Enable or disable each site-level change below. Changes apply site-wide.', 'curly-site-tools' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>
				<?php
				// Ensure the option is always submitted so unchecking every box
				// still saves (otherwise the settings API would skip the update).
				?>
				<input type="hidden" name="<?php echo esc_attr( self::OPTION_NAME ); ?>" value="" />
				<table class="form-table" role="presentation">
					<tbody>
					<?php foreach ( $this->toggles as $id => $toggle ) : ?>
						<?php $enabled = $this->is_enabled( $id ); ?>
						<tr>
							<th scope="row">
								<label for="curly-site-tools-<?php echo esc_attr( $id ); ?>">
									<?php echo esc_html( $toggle['label'] ); ?>
								</label>
							</th>
							<td>
								<label for="curly-site-tools-<?php echo esc_attr( $id ); ?>">
									<input
										type="checkbox"
										id="curly-site-tools-<?php echo esc_attr( $id ); ?>"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $id ); ?>]"
										value="1"
										<?php checked( $enabled ); ?>
									/>
									<span class="description">
										<?php echo esc_html( $toggle['description'] ); ?>
									</span>
								</label>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button( __( 'Save Changes', 'curly-site-tools' ) ); ?>
			</form>
		</div>
		<?php
	}
}

/**
 * Register a toggle (procedural helper for includes).
 *
 * @param string $id          Toggle id.
 * @param string $label       Label.
 * @param string $description Description.
 * @param bool   $default     Default enabled state.
 */
function curly_site_tools_register_toggle( $id, $label, $description, $default = false ) {
	Curly_Site_Tools::instance()->register_toggle( $id, $label, $description, $default );
}

/**
 * Whether a toggle is enabled (procedural helper for includes).
 *
 * @param string $id Toggle id.
 * @return bool
 */
function curly_site_tools_is_enabled( $id ) {
	return Curly_Site_Tools::instance()->is_enabled( $id );
}
