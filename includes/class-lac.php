<?php

/**
 * Woocommerce moodle integration - Main class. Includes files, classes, trigger actions.
 */
class LACONN {

	public static $instance;

	/**
	 * Web url for the plugin.
	 * @var string
	 */
	public $wwwroot;

	/**
	 * Absolute directory plugin path.
	 * @var string
	 */
	public $dirroot;

	/**
	 * Connection helpher.
	 * @var LACONN_Client
	 */
	public $client;

	/**
	 * LMS Site webserice token.
	 * @var string
	 */
	public $site_token;

	/**
	 * LMS Site connection URL.
	 * @var string
	 */
	public $site_url;

	public $handlers;

	public $options;

	public const KEY = 'LACONN_';

	/**
	 * Returns an instance of the plugin object
	 *
	 * @return LACONN Main instance
	 *
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof LACONN ) ) {
			self::$instance = new LACONN();
		}
		return self::$instance;
	}

	/**
	 * Construct method contains site connection details.
	 *
	 * @param boolean $helpers [description]
	 */
	public function __construct() {
		$this->define();
		$this->setup_config();
		$this->includes();
	}

	public function setup_config() {
		$this->dirroot = dirname( LAC_PLUGIN_FILE );
		$this->wwwroot = plugin_dir_url(__DIR__);

		$options = get_option( 'lac_connection_settings' );
		$generaloptions = get_option( 'lac_general_settings' );
		$this->options = (is_array($options)) ? $options : [];
		if (is_array($generaloptions)) {
			$this->options = array_merge($this->options, $generaloptions);
		}
		$this->site_token = isset($options['site_token']) ? $options['site_token'] : '';
		$this->site_url = isset($options['site_url']) ? $options['site_url'] : '';
		if ( $this->site_url != '' && strrev($this->site_url)[0] !== '/' ) {
			$this->site_url .= '/';
		}
	}

	/**
	 * Plugin constant definitions.
	 *
	 * @return void
	 */
	protected function define() {

		$upload_dir = wp_upload_dir( null, false );
		$params = [
			'LAC_TEXTDOMAIN'           => 'lmsace-connect',
			'NONCEKEY'             => 'lmsace-connect-nonce',
			'LACONN_MOODLE_COURSE_ID' => 'lac_moodle_courses',
			'LMSCONF_DEBUG'        => true,
			'ENROLLED'             => 1,
			'NOTENROLLED'          => 0,
			'LACONN_SETTINGS'         => 'lac_connection_settings',
			'LACONN_SUSPEND'          => 'suspend',
			'LACONN_UNENROL'          => 'unenrol',
			'LACONN_LOG_DIR'		   => $upload_dir['basedir']. '/lac-logs/',
			'LACONN_IMPORT_LIMIT'	   => 25
		];
		$this->define_params( $params );
	}

	/**
	 * Define the params.
	 *
	 * @param array $params
	 * @return void
	 */
	public function define_params( $params=array() ): void {
		foreach ($params as $key => $value) {
			if ( !defined($key) ) {
				define( $key, $value);
			}
		}
	}

	/**
	 * Trigger plugin to register actions.
	 *
	 * @return null
	 */
	public function init() {
		if ( $this->is_woocommerce_installed() ) {
			$this->register_actions();
		}

		if (is_admin()) {
			add_action( 'woocommerce_loaded', array($this, 'woocommerce_version_check'));
			add_action( 'admin_notices', array($this, 'display_admin_notices') );
		}


		$this->load_submodules();
	}

	/**
	 * load all of its submodules.
	 * Read directory "modules" and check the function "laconn_mod_PLUGINNAME" function exists. Init the functions.
	 *
	 * @return void
	 */
	public function load_submodules() {

		$dir = scandir( $this->dirroot.'/modules' );
		$modules = array_diff( $dir, array('.', '..') );
		foreach ( $modules as $mod ) {
			$file = $this->dirroot.'/modules/'.$mod.'/module.php';
			if (file_exists($file)) {
				require_once($file);
			}
		}
	}

	/**
	 * Include the needed helper classes.
	 *
	 * @return void
	 */
	public function includes() {
		include_once( $this->dirroot.'/includes/class-lac-main.php' );

        $this->handlers['LACONN_Client'] = include_once($this->dirroot.'/includes/class-lac-client.php');
        $this->handlers['LACONN_User'] = include_once($this->dirroot.'/includes/class-lac-user.php');
		$this->handlers['LACONN_Course'] = require_once($this->dirroot.'/includes/class-lac-course.php');
        $this->handlers['LACONN_Woocom'] = include_once($this->dirroot.'/includes/class-lac-woocom.php');
        $this->handlers['LACONN_Log'] = include_once($this->dirroot.'/includes/class-lac-log.php');

		// Only for the admin side.
		if ( is_admin() ) {
            $this->handlers['LACONN_Admin'] = include_once($this->dirroot.'/includes/admin/class-lac-admin.php');
			include_once( $this->dirroot.'/includes/admin/class-lac-product-settings.php' );
        }

		$this->register_handlers('LACONN_');
    }

	/**
	 * Build the handlers class instance and setup class variables. Replaced the key from class name.
	 * Final handlers instance are looks $this->Admin, $this->Client.
	 *
	 * @return void
	 */
	public function register_handlers() {

		foreach ( array_keys($this->handlers) as $k => $handler ) {
			$handler_class = ( new $handler() );
			$handler = str_replace( self::KEY, '', $handler );
			$this->{$handler} = $handler_class;
		}
	}

	/**
	 * Client instance.
	 *
	 * @return LACONN_Client
	 */
	public function client() {
		return LACONN_Client::instance();
	}

	/**
	 * Admin instance.
	 *
	 * @return LACONN_Admin
	 */
	public function admin() {
		return LACONN_Admin::instance();
	}

	/**
	 * WooCommerce instance.
	 *
	 * @return LACONN_WooCom
	 */
	public function woocom() {
		return LACONN_WooCom::instance();
	}

	/**
	 * Course class instance.
	 *
	 * @return LACONN_Course
	 */
	public function course() {
		return LACONN_Course::instance();
	}

	/**
	 * User class instance
	 *
	 * @return LACONN_User
	 */
	public function user() {
		return LACONN_User::instance();
	}

	/**
	 * Logged class instance.
	 *
	 * @return LACONN_Log
	 */
	public function logger() {
		return LACONN_Log::instance();
	}

	/**
	 * Check is LAC Pro plugin installed.
	 */
	public static function has_pro() {
		if ( is_plugin_active( 'lmsace-connect-pro/lmsace-connect-pro.php' ) ) {
			$path = WP_PLUGIN_DIR.'/lmsace-connect-pro/includes/class-lacpro.php';
			require_once( $path );
			return false; // true;
		}
	}

	/**
	 * Include required class file and create the class object. Class name must have same as file * name with plugin file keyword.
	 *
	 * @param string  $class  Classname.
	 * @param boolean $is_sub If file is in sub directory.
	 * @param array   $params Parameters for the class contructor.
	 */
	public function get_handler( $class='', $is_sub=false, $params=array() ) {
		$path = $this->dirroot.'/includes/';
		$path .= ($is_sub) ? $is_sub.'/' : '';
		$path .= 'class-lac-'.$class.'.php';
		require_once($path);
		$class = self::KEY.ucwords(str_replace( '-', '_', $class));
		return (new ReflectionClass ( $class ))->newInstanceArgs ( $params );
	}

	/**
	 * Fetch lmsace-connect options list.
	 *
	 * @return void
	 */
	public function get_options() {
		$options = get_option( 'lac_connection_settings' );
		$generaloptions = get_option( 'lac_general_settings' );
		$this->options =  (is_array($options)) ? $options : [];
		if (is_array($generaloptions)) {
			$this->options = array_merge($this->options, $generaloptions);
		}
		return $this->options;
	}

    /**
	 * Call the plugins classes and register the actions.
	 *
	 * @return null
	 */
	public function register_actions() {

		add_action('init', array($this, 'register_lac_session'));
		foreach ( array_keys($this->handlers) as $key => $handler ) {
			$handler_class = (new $handler());
			$handler = str_replace( self::KEY, '', $handler);
            $this->{$handler} = $handler_class;
            if ( method_exists( $handler_class, 'lac_register_actions' ) ) {
                $handler_class->lac_register_actions();
            }

			// Register admin side based actions.
			if ( is_admin() && method_exists( $this->{$handler}, 'lac_register_admin_actions' ) ) {
				$this->{$handler}->lac_register_admin_actions();
			}
		}
	}

	/**
	 * Get config from plugin settings.
	 *
	 * @param string $key
	 * @return mixed|null
	 */
    public function get_config(string $key ) {
		if ( isset( $this->options[ $key ] ) ) {
			return $this->options[ $key ];
		}
		return null;
	}

	/**
	 * Find the woocommerce is installed on WP. Otherwise notice the issue to admin.
	 *
	 * @return bool result of woocommerce availability.
	 */
	public function is_woocommerce_installed( $die = false) {
		$error = false;
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		if ( !file_exists( WP_PLUGIN_DIR.'/woocommerce/woocommerce.php' ) ) {
			$error = true;
			$message = esc_html(__( 'You must install and activate WooCommerce plugin to use this LMSACE Connect plugin. ', 'lmsace-connect', 'dependencycheck' ));
		} else if ( !is_plugin_active('woocommerce/woocommerce.php') ) {
			$error = true;
			$message = esc_html(__('You must activate WooCommerce plugin to use this LMSACE Connect plugin.', 'lmsace-connect', 'dependencycheck'));
		}
		if ($error) {
			if ( $die ) {
				deactivate_plugins( 'lmsace-connect' );
				wp_die( $message, 'Plugin dependency check', array( 'back_link' => true ) );
			} else {
				$this->set_admin_notices('error', $message, 'dependencycheck');
			}
		} else {
			$this->remove_admin_notices('dependencycheck');
		}

		return !($error);
	}

	/**
	 * Check the dependented version of woocommerce installed.
	 *
	 * @return void
	 */
	public function woocommerce_version_check() {
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$message = esc_html(__('Not compatible with installed woocommerce plugin, Please upgrade your woocommerce', 'lmsace-connect'));
			$this->set_admin_notices('error', $message);
		}
	}

	/**
	 * Check the connection setup is completed.
	 *
	 * @return bool
	 */
	public function is_setup_completed() {
		global $LACONN;

		$options = $this->get_options();
		if (!isset($options['site_url']) || empty($options['site_url'])) {
			$this->set_admin_notices('warning', '<h4> LMSACE Connect </h4>'.sprintf( wp_kses(__(' <p> <b>You need to specify a Moodle LMS domain and a Moodle LMS Access token.</b> You must <a href="%s">enter your domain and API key</a> for it to work.</p> ', 'lmsace-connect'), $LACONN->allowed_tags()), 'admin.php?page=lac-admin-settings'), 'connection', true);
		} else {
			$request = $this->Client->request(self::services('get_user_roles'), array(), false);
			if ($request) {
				$exception = $this->Client->hasException($request, true, false) ||
					!$this->Client->is_valid_response($request['response_code']);
			}
			if (empty($request) || (isset($exception) && $exception)) {
				$this->set_admin_notices('error', '<h4> LMSACE Connect </h4>'. esc_html(__('Moodle and WooCommerce connection failed! Please check your details ', 'lmsace-connect')), 'connection', true);
			}
		}
	}

	/**
	 * Register LACONN sessions.
	 *
	 * @return void
	 */
	public function register_lac_session() {
		if ( !session_id() ) {
			session_start();
		}
	}

	/**
	 * Display session flash notices to admin.
	 *
	 * @return void.
	 */
	public function display_admin_notices() {
		if ( !session_id() ) {
  			session_start();
		}
		// Add import courses background notifications.
		$this->Course()->set_import_notices();

		if (!isset($_SESSION['lac_admin_flash']) ) {
			$_SESSION['lac_admin_flash'] = array();
		}

		$flash_message = [];

		array_walk( $_SESSION['lac_admin_flash'], function($value, $key) use (&$flash_message) {
			$flash_message[$key] = array_map( 'esc_attr', $value );
		});

		apply_filters( 'lac_admin_notices', $flash_message );

		foreach ($flash_message as $key => $message) {

			if ($message['type'] == 'error') {
				?>
				<div class="notice notice-error is-dismissible lmsace-notice">
					<p> <?php echo esc_attr( $message['message'] ); ?> </p>
				</div>
				<?php
			} else {
				?>
				<div class="notice notice-<?php echo esc_attr( $message['type'] ); ?> is-dismissible lmsace-notice">
					<p> <?php echo esc_attr( $message['message'] ); ?> </p>
				</div>
				<?php
			}
			if ( !isset($message['remove']) || (isset($message['remove']) && $message['remove']) ) {
				unset($_SESSION['lac_admin_flash'][$key]);
			}
		}
	}

	/**
	 * Store notices to display in notification for admin users using session.
	 *
	 * @param string $type  error or sucess
	 * @param string $message Message to display on notice
	 * @param string $name Key of the message used in sesion. if uses empty by default.
	 * @param bool $remove Remove the notice from session once it displayed.
	 */
	public function set_admin_notices($type, $message, $name='', $remove=true) {

		if ( !session_id() ) {
			session_start();
		}

		if ( !isset($_SESSION['lac_admin_flash']) ) {
			$_SESSION['lac_admin_flash'] = array();
		}

		$_SESSION['lac_admin_flash'][$name] = array( 'type' => $type, 'message' => sanitize_text_field( $message ), 'remove' => $remove );
	}

	/**
	 * Removed the admin notifce flash messages.
	 *
	 * @param string $name
	 * @return void
	 */
	public function remove_admin_notices($name) {
		if (isset($_SESSION['lac_admin_flash'][$name]))
			unset($_SESSION['lac_admin_flash'][$name]);
	}

	/**
	 * Allowed html tags to kses.
	 *
	 * @return void
	 */
	public function allowed_tags() {
		return [
			'a'       => array(
				'href'   => array(),
				'title'  => array(),
				'target' => array(),
			),
			'em'      => array(),
			'div'     => array(),
			'p'       => array(),
			'ul'      => array(),
			'ol'      => array(),
			'li'      => array(),
			'h1'      => array(),
			'h2'      => array(),
			'h3'      => array(),
			'h4'      => array(),
			'h5'      => array(),
			'h6'      => array(),
			'img'     => array(
				'src'   => array(),
				'class' => array(),
				'alt'   => array(),
			),
		];
	}

	/**
	 * Moodle lms web service functions list.
	 *
	 * @param string $key service keyword.
	 * @return string Service name to use.
	 */
	public static function services($key = '') {

		$services = [
			'get_courses' => 'core_course_get_courses',
			'create_users' => 'core_user_create_users',
			'get_user_by_field' => 'core_user_get_users_by_field',
			'enrol_users' => 'enrol_manual_enrol_users',
			'unenrol_users' => 'enrol_manual_unenrol_users',
			'get_categories' => 'core_course_get_categories',
			'get_courses_by_field' => 'core_course_get_courses_by_field',
			'get_users_courses' => 'core_enrol_get_users_courses',
			'get_user_roles' => 'local_lmsace_connect_user_roles',
			'get_limit_courses' => 'local_lmsace_connect_limit_courses',
			'get_courses_count' => 'local_lmsace_connect_get_courses_count',
			'get_courses_detail_by_field' => 'lacpro_coursedata_get_courses_detail_by_field'
		];

		$services = apply_filters( 'lmsace_connect_get_services', $services );

		return isset($services[$key]) ? $services[$key] : $services;
	}


}
