<?php

/**
 * LMSACE Connect - SSO setup.
 * Includes handler files, services and define constants which are used in SSO.
 *
 * @package LMSACE Connect
 * @subpackage SSO
 * @copyright  2023 LMSACE DEV TEAM <info@lmsace.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class LACONNMOD_SSO extends LACONN {

	/**
	 * Instance of this class instance.
	 *
	 * @var LACONNMOD_SSO
	 */
    public static $instance;

	public const KEY = 'LACONNMOD_SSO_';

    /**
	 * Returns an instance of the plugin object
	 *
	 * @return LACONNMOD_SSO Main instance
	 *
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof LACONNMOD_SSO ) ) {
			self::$instance = new LACONNMOD_SSO();
		}
		return self::$instance;
	}

	/**
	 * Define moodle session name to store.
	 *
	 * @return void
	 */
    protected function define() {

		$params = [
			'LACONN_MOODLESESSION' => 'MoodleSession'
		];
		$this->define_params( $params );
    }

	/**
	 * Include the files.
	 *
	 * @return void
	 */
    public function includes() {

        $this->handlers['LACONNMOD_SSO_User'] = include_once($this->dirroot.'/modules/sso/includes/class-lacsso-user.php');
		// Only for the admin side.
		if ( is_admin() ) {
            $this->handlers['LACONNMOD_SSO_Admin'] = include_once($this->dirroot.'/modules/sso/includes/admin/class-lacsso-admin.php');
        }

		// Register the handlers, create class instance and create class variable, $this->User, $this->Admin.
		$this->register_handlers();
    }

	/**
	 * WooCommerce instance.
	 *
	 * @return LACONNMOD_SSO_Admin
	 */
	public function admin() {
		return LACONNMOD_SSO_Admin::instance();
	}

    /**
	 * Call the plugins classes and register the actions.
	 *
	 * @return null
	 */
	public function register_actions() {
		parent::register_actions();
		add_filter( 'lmsace_connect_get_services', array($this, 'get_services' ));
	}

	/**
	 * Add pro services to the services.
	 *
	 *
	 * @param [type] $services
	 * @return void
	 */
	public function get_services( $services ) {
		$services += [
			'get_courses_detail_by_field' => 'lacpro_coursedata_get_courses_detail_by_field',
			'generate_userloginkey' => 'auth_lmsace_connect_generate_userloginkey',
			'is_loggedin' => 'auth_lmsace_connect_is_userloggedin'
		];

		$options = get_option('lac_sso_settings');

		if (isset($options['auth_method']) && $options['auth_method'] == 'lmsace_connect') {
			$services['create_users'] = 'auth_lmsace_connect_generate_userloginkey';
		}
		return $services;
	}
}

