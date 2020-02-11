<?php
/**
Plugin Name: BuddyMeet
Plugin URI:
Description: Adds a meeting room with video and audio capabilities to BuddyPress. Powered by <a target="_blank" href="https://jitsi.org/"> Jitsi Meet </a>.
Version: 1.0.0
Requires at least: 4.6.0
Tags: buddypress
License: GPL V2
Author: Themis Dakanalis <tdakanalis@cytech,gr>
Author URI: https://www.cytechmobile.com/employee/themis-dakanalis/
Text Domain: buddymeet
Domain Path: /languages
*/

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BuddyMeet' ) ) :
/**
 * Main BuddyMeet Class
 */
class BuddyMeet {

    const OPTION_PREFIX_MEET_ROOM = 'buddymeet_room_';

	private static $instance;

	/**
	 * Required BuddyPress version for the plugin.
	 *
	 * @package BuddyMeet
	 * @since 1.0.0
	 *
	 * @var  string
	 */
	public static $required_bp_version = '2.5.0';

	/**
	 * BuddyPress config.
	 *
	 * @package BuddyMeet
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public static $bp_config = array();

	/**
	 * Main BuddyMeet Instance
	 *
	 * Avoids the use of a global
	 *
	 * @package BuddyMeet
	 * @since 1.0.0
	 *
	 * @uses BuddyMeet::setup_globals() to set the global needed
	 * @uses BuddyMeet::includes() to include the required files
	 * @uses BuddyMeet::setup_actions() to set up the hooks
	 * @return object the instance
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new BuddyMeet;
			self::$instance->setup_globals();
			self::$instance->includes();
			self::$instance->setup_actions();
		}
		return self::$instance;
	}


	private function __construct() { /* Do nothing here */ }

	/**
	 * Some usefull vars
	 *
	 * @package BuddyMeet
	 * @since 1.0.0
	 *
	 * @uses plugin_basename()
	 * @uses plugin_dir_path() to build BuddyMeet plugin path
	 * @uses plugin_dir_url() to build BuddyMeet plugin url
	 */
	private function setup_globals() {
		$this->version    = '1.0.0';

		// Setup some base path and URL information
		$this->file       = __FILE__;
		$this->basename   = apply_filters( 'buddymeet_plugin_basename', plugin_basename( $this->file ) );
		$this->plugin_dir = apply_filters( 'buddymeet_plugin_dir_path', plugin_dir_path( $this->file ) );
		$this->plugin_url = apply_filters( 'buddymeet_plugin_dir_url',  plugin_dir_url ( $this->file ) );

		// Includes
		$this->includes_dir = apply_filters( 'buddymeet_includes_dir', trailingslashit( $this->plugin_dir . 'includes'  ) );
		$this->includes_url = apply_filters( 'buddymeet_includes_url', trailingslashit( $this->plugin_url . 'includes'  ) );

		// Languages
		$this->lang_dir  = apply_filters( 'buddymeet_lang_dir', trailingslashit( $this->plugin_dir . 'languages' ) );

		// BuddyMeet slug and name
		$this->buddymeet_slug = apply_filters( 'buddymeet_slug', 'buddymeet' );
		$this->buddymeet_name = apply_filters( 'buddymeet_name', 'BuddyMeet' );

		$this->domain           = 'buddymeet';
		$this->errors           = new WP_Error(); // Feedback
	}

	/**
	 * Ιncludes the needed files
	 *
	 * @package BuddyMeet
	 * @since 1.0.0
	 *
	 * @uses is_admin() for the settings files
	 */
	private function includes() {
		require( $this->includes_dir . 'buddymeet-actions.php'         );
		require( $this->includes_dir . 'buddymeet-functions.php'       );

		//TODO CHECK ADMIN INTERFACES
		/*if( is_admin() ){
			require( $this->includes_dir . 'admin/buddymeet-admin.php' );
		}*/
	}


	/**
	 * The main hook used is bp_include to load our custom BuddyPress component
     *
     * @package BuddyMeet
	 * @since 1.0.0
	 */
	private function setup_actions() {
		// Add actions to plugin activation and deactivation hooks
		add_action( 'activate_'   . $this->basename, 'buddymeet_activation'   );
		add_action( 'deactivate_' . $this->basename, 'buddymeet_deactivation' );

		add_action( 'bp_loaded',  array( $this, 'load_textdomain' ) );
		add_action( 'bp_include', array( $this, 'load_component'  ) );

        add_action( 'bp_setup_nav', array($this, 'set_default_groups_nav'), 20 );

		do_action_ref_array( 'buddymeet_after_setup_actions', array( &$this ) );
	}

    public function set_default_groups_nav() {
        bp_core_new_nav_default (
            array(
                'parent_slug'       => buddymeet(),
                'subnav_slug'       => 'members',
                'screen_function'   => 'buddymeet_screen_members'
            )
        );
    }

	/**
	 * Loads the translation
	 *
	 * @package BuddyMeet
	 * @since 1.0.0
	 * @uses get_locale()
	 * @uses load_textdomain()
	 */
	public function load_textdomain() {
		$locale = apply_filters( 'buddymeet_load_textdomain_get_locale', get_locale(), $this->domain );
		$mofile = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );
		$mofile_global = WP_LANG_DIR . '/buddymeet/' . $mofile;

		if ( ! load_textdomain( $this->domain, $mofile_global ) ) {
			load_plugin_textdomain( $this->domain, false, basename( $this->plugin_dir ) . '/languages' );
		}
	}

	/**
	 * Finally, Load the component
	 *
	 * @package BuddyMeet
	 * @since 1.0.0
	 */
	public function load_component() {
		if ( self::bail() ) {
			add_action( self::$bp_config['network_admin'] ? 'network_admin_notices' : 'admin_notices', array( $this, 'warning' ) );
		} else {
			require( $this->includes_dir . 'buddymeet-component-class.php' );
		}
	}

	/**
	 * Checks BuddyPress version
	 *
	 * @package BuddyMeet
	 * @since 1.0.0
	 */
	public static function version_check() {
		// taking no risk
		if ( ! defined( 'BP_VERSION' ) )
			return false;

		return version_compare( BP_VERSION, self::$required_bp_version, '>=' );
	}

	/**
	 * Checks if your plugin's config is similar to BuddyPress
	 *
	 * @package BuddyMeet
	 * @since 1.0.0
	 */
	public static function config_check() {
		/**
		 * blog_status    : true if your plugin is activated on the same blog
		 * network_active : true when your plugin is activated on the network
		 * network_status : BuddyPress & your plugin share the same network status
		 */
		self::$bp_config = array(
			'blog_status'    => false,
			'network_active' => false,
			'network_status' => true,
			'network_admin'  => false
		);

		$buddypress = false;

		if ( function_exists( 'buddypress' ) ) {
			$buddypress = buddypress()->basename;
		}

		if ( $buddypress && get_current_blog_id() == bp_get_root_blog_id() ) {
			self::$bp_config['blog_status'] = true;
		}

		$network_plugins = get_site_option( 'active_sitewide_plugins', array() );

		// No Network plugins
		if ( empty( $network_plugins ) )
			return self::$bp_config;

		$buddymeet = plugin_basename( __FILE__ );

		// Looking for BuddyMeet
		$check = array( $buddymeet );

		// And for BuddyPress if set
		if ( ! empty( $buddypress ) )
			$check = wp_parse_args($check, $buddypress);

		// Are they active on the network ?
		$network_active = array_diff( $check, array_keys( $network_plugins ) );

		// If result is 1, your plugin is network activated
		// and not BuddyPress or vice & versa. Config is not ok
		if ( count( $network_active ) == 1 )
			self::$bp_config['network_status'] = false;

		self::$bp_config['network_active'] = isset( $network_plugins[ $buddymeet ] );

		// We need to know if the BuddyPress is network activated to choose the right
		// notice ( admin or network_admin ) to display the warning message.
		self::$bp_config['network_admin']  = ! empty( $buddypress ) && isset( $network_plugins[ $buddypress ] );

		return self::$bp_config;
	}

	/**
	 * Bail if BuddyPress config is different than this plugin
	 *
	 * @package BuddyMeet
	 * @since 1.0.0
	 */
	public static function bail() {
		$retval = false;

		$config = self::config_check();

		if ( ! self::version_check() || ! $config['blog_status'] || ! $config['network_status'] )
			$retval = true;

		return $retval;
	}

	/**
	 * Display a warning message to admin
	 *
	 * @package BuddyMeet
	 * @since 1.0.0
	 */
	public function warning() {
		$warnings = $resolve = array();

		if ( ! self::version_check() ) {
			$warnings[] = sprintf( esc_html__( 'BuddyMeet requires at least version %s of BuddyPress.', 'buddymeet' ), self::$required_bp_version );
			$resolve[]  = sprintf( esc_html__( 'Upgrade BuddyPress to at least version %s', 'buddymeet' ), self::$required_bp_version );
		}

		if ( ! empty( self::$bp_config ) ) {
			$config = self::$bp_config;
		} else {
			$config = self::config_check();
		}

		if ( ! $config['blog_status'] ) {
			$warnings[] = esc_html__( 'BuddyMeet requires to be activated on the blog where BuddyPress is activated.', 'buddymeet' );
			$resolve[]  = esc_html__( 'Activate BuddyMeet on the same blog than BuddyPress', 'buddymeet' );
		}

		if ( ! $config['network_status'] ) {
			$warnings[] = esc_html__( 'BuddyMeet and BuddyPress need to share the same network configuration.', 'buddymeet' );
			$resolve[]  = esc_html__( 'Make sure BuddyMeet is activated at the same level than BuddyPress on the network', 'buddymeet' );
		}

		if ( ! empty( $warnings ) ) {
			// Give some more explanations to administrator
			if ( is_super_admin() ) {
				$deactivate_link = ! empty( $config['network_active'] ) ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' );
				$deactivate_link = '<a href="' . esc_url( $deactivate_link ) . '">' . esc_html__( 'deactivate', 'buddymeet' ) . '</a>';
				$resolve_message = '<ol><li>' . sprintf( __( 'You should %s BuddyMeet', 'buddymeet' ), $deactivate_link ) . '</li>';

				foreach ( (array) $resolve as $step ) {
					$resolve_message .= '<li>' . $step . '</li>';
				}

				if ( $config['network_status'] && $config['blog_status']  )
					$resolve_message .= '<li>' . esc_html__( 'Once done try to activate BuddyMeet again.', 'buddymeet' ) . '</li></ol>';

				$warnings[] = $resolve_message;
			}

		?>
		<div id="message" class="error">
			<?php foreach ( $warnings as $warning ) : ?>
				<p><?php echo $warning; ?></p>
			<?php endforeach ; ?>
		</div>
		<?php
		}
	}
}

function buddymeet() {
	return buddymeet::instance();
}

buddymeet();

/**
 * BuddyMeet unistall Hook registration
 */
register_uninstall_hook( __FILE__, 'buddymeet_uninstall' );

endif;
