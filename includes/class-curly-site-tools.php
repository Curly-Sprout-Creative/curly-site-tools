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
			'local-google-fonts.php',
			'media-handling.php',
			'oxygen-builder-access.php',
			'post-utilities.php',
			'turnstile.php',
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
	 * @param array  $args        Optional. Extra settings for the toggle. Supports
	 *                            a 'fields' array of companion inputs. Each field is
	 *                            an array with: type (number|text|password), label,
	 *                            option, placeholder, description, min, step, unit,
	 *                            default. The legacy single 'field' argument is
	 *                            still accepted and treated as one number field.
	 */
	public function register_toggle( $id, $label, $description, $default = false, $args = array() ) {
		if ( $this->locked || isset( $this->toggles[ $id ] ) ) {
			return;
		}

		$fields = array();
		if ( isset( $args['fields'] ) && is_array( $args['fields'] ) ) {
			$fields = $args['fields'];
		} elseif ( isset( $args['field'] ) && is_array( $args['field'] ) ) {
			$fields = array( $args['field'] );
		}

		$normalized = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$field = wp_parse_args(
				$field,
				array(
					'type'        => 'number',
					'label'       => '',
					'option'      => '',
					'placeholder' => '',
					'description' => '',
					'min'         => 1,
					'step'        => 1,
					'unit'        => '',
					'default'     => null,
				)
			);

			if ( '' === (string) $field['option'] ) {
				continue;
			}

			$field['option'] = sanitize_key( $field['option'] );
			$field['type']   = in_array( $field['type'], array( 'number', 'text', 'password' ), true ) ? $field['type'] : 'text';

			if ( null === $field['default'] ) {
				$field['default'] = ( 'number' === $field['type'] ) ? 1 : '';
			}

			$normalized[] = $field;
		}

		$this->toggles[ $id ] = array(
			'id'          => sanitize_key( $id ),
			'label'       => $label,
			'description' => $description,
			'default'     => (bool) $default,
			'field'       => isset( $normalized[0] ) ? $normalized[0] : array(),
			'fields'      => $normalized,
		);
	}

	/**
	 * Get a toggle's stored value. For a toggle with a companion numeric field
	 * this returns the field's option value; otherwise it returns the boolean
	 * enabled state.
	 *
	 * @param string     $id      Toggle id.
	 * @param mixed|null $default Value to return when the option is unset.
	 * @return mixed
	 */
	public function get_value( $id, $default = null ) {
		if ( isset( $this->toggles[ $id ]['field']['option'] ) && '' !== $this->toggles[ $id ]['field']['option'] ) {
			$field    = $this->toggles[ $id ]['field'];
			$fallback = null !== $default ? $default : $field['default'];
			return get_option( $field['option'], $fallback );
		}
		return $this->is_enabled( $id );
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

		// Register the companion option for each toggle field.
		foreach ( $this->toggles as $toggle ) {
			$fields = isset( $toggle['fields'] ) && is_array( $toggle['fields'] ) ? $toggle['fields'] : array();
			foreach ( $fields as $field ) {
				if ( empty( $field['option'] ) ) {
					continue;
				}
				$is_number = ( 'number' === $field['type'] );
				register_setting(
					self::OPTION_GROUP,
					$field['option'],
					array(
						'type'              => $is_number ? 'integer' : 'string',
						'sanitize_callback' => $is_number ? array( $this, 'sanitize_number_field' ) : array( $this, 'sanitize_text_option' ),
						'default'           => $field['default'],
					)
				);
			}
		}
	}

	/**
	 * Sanitize a numeric toggle field: whole numbers, minimum of 1.
	 *
	 * @param mixed $value Raw input.
	 * @return int
	 */
	public function sanitize_number_field( $value ) {
		return max( 1, absint( $value ) );
	}

	/**
	 * Sanitize a text/password toggle field (API keys, IDs, labels).
	 *
	 * @param mixed $value Raw input.
	 * @return string
	 */
	public function sanitize_text_option( $value ) {
		return is_string( $value ) ? trim( wp_strip_all_tags( $value ) ) : '';
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
						<?php
						$enabled = $this->is_enabled( $id );
						$fields  = isset( $toggle['fields'] ) && is_array( $toggle['fields'] ) ? $toggle['fields'] : array();
						?>
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
								<?php foreach ( $fields as $field ) : ?>
									<p class="curly-site-tools-field">
										<?php if ( '' !== $field['label'] ) : ?>
											<label for="curly-site-tools-<?php echo esc_attr( $field['option'] ); ?>">
												<?php echo esc_html( $field['label'] ); ?>
											</label>
										<?php endif; ?>
										<?php if ( 'number' === $field['type'] ) : ?>
											<input
												type="number"
												id="curly-site-tools-<?php echo esc_attr( $field['option'] ); ?>"
												class="small-text"
												name="<?php echo esc_attr( $field['option'] ); ?>"
												value="<?php echo esc_attr( get_option( $field['option'], $field['default'] ) ); ?>"
												min="<?php echo esc_attr( $field['min'] ); ?>"
												step="<?php echo esc_attr( $field['step'] ); ?>"
												data-curly-site-tools-toggle="curly-site-tools-<?php echo esc_attr( $id ); ?>"
											/>
										<?php else : ?>
											<input
												type="<?php echo esc_attr( 'password' === $field['type'] ? 'password' : 'text' ); ?>"
												id="curly-site-tools-<?php echo esc_attr( $field['option'] ); ?>"
												class="regular-text"
												name="<?php echo esc_attr( $field['option'] ); ?>"
												value="<?php echo esc_attr( get_option( $field['option'], $field['default'] ) ); ?>"
												placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
												autocomplete="<?php echo esc_attr( 'password' === $field['type'] ? 'new-password' : 'off' ); ?>"
												data-curly-site-tools-toggle="curly-site-tools-<?php echo esc_attr( $id ); ?>"
											/>
										<?php endif; ?>
										<?php if ( '' !== $field['unit'] ) : ?>
											<span class="description"><?php echo esc_html( $field['unit'] ); ?></span>
										<?php endif; ?>
										<?php if ( '' !== $field['description'] ) : ?>
											<span class="description"><?php echo esc_html( $field['description'] ); ?></span>
										<?php endif; ?>
									</p>
								<?php endforeach; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button( __( 'Save Changes', 'curly-site-tools' ) ); ?>
			</form>
			<?php do_action( 'curly_site_tools_admin_after_form', $this ); ?>
			<script>
			( function () {
				var fields = document.querySelectorAll( '[data-curly-site-tools-toggle]' );
				Array.prototype.forEach.call( fields, function ( field ) {
					var toggle = document.getElementById( field.getAttribute( 'data-curly-site-tools-toggle' ) );
					if ( ! toggle ) {
						return;
					}
					var sync = function () {
						field.disabled = ! toggle.checked;
					};
					toggle.addEventListener( 'change', sync );
					sync();
				} );
			}() );
			</script>
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
 * @param array  $args        Optional. Extra settings (e.g. a companion numeric 'field').
 */
function curly_site_tools_register_toggle( $id, $label, $description, $default = false, $args = array() ) {
	Curly_Site_Tools::instance()->register_toggle( $id, $label, $description, $default, $args );
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

/**
 * Get a toggle's stored value (procedural helper for includes).
 *
 * @param string     $id      Toggle id.
 * @param mixed|null $default Value to return when the option is unset.
 * @return mixed
 */
function curly_site_tools_get_value( $id, $default = null ) {
	return Curly_Site_Tools::instance()->get_value( $id, $default );
}
