<?php

/**
 * Helpher - Admin backend progress. Enables the adminstration config for SSO.
 *
 * @package LMSACE Connect
 * @subpackage SSO
 * @copyright  2023 LMSACE DEV TEAM <info@lmsace.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined( 'ABSPATH' ) || exit;

/**
 * SSO - admin side config and process are defined.
 */
class LACONNMOD_SSO_Admin extends LACONN_Admin {

    /**
	 * Admin Instance object.
	 *
	 * @var LACONNMOD_SSO_Admin Admin class instance object.
	 */
	public $instance;

	/**
	 * Returns an instance of the plugin object
	 *
	 * @return instance LACONN Admin instance
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof LACONNPRO_Admin ) ) {
			self::$instance = new LACONNPRO_Admin;
		}
		return self::$instance;
	}

	/**
	 * Register the actions for admin after the lmsace connect actions are registered.
	 *
	 * @return void
	 */
    public function lac_register_admin_actions() {
		add_action( 'lmsace_connect_register_setting_fields', array( $this, 'register_setting_fields' ) );
	}

	/**
	 * Add sso configuration options in general settings section.
	 *
	 * @return void
	 */
	public function general_admin_setting_menu() {
		settings_fields( 'lac-general-settings' );
		do_settings_sections( 'lac-general-settings' );
	}

	/**
	 * Register the settings form fields for SSO.
	 *
	 * @return void
	 */
	public function register_setting_fields() {
		// Register the SSO settings in general settings tab.
		register_setting('lac-general-settings', 'lac_sso_settings');

		add_settings_section(
	        'lac_sso_options',
			esc_html(__('Single Sign On', 'lmsace-connect')),
	        array($this, 'sso_config_section'),
	        'lac-general-settings'
	    );

		add_settings_field(
			'login_lms',
			esc_html( __('SSO Activation Method', 'lmsace-connect') ),
			array( $this, 'login_lms' ),
			'lac-general-settings',
			'lac_sso_options'
		);

		add_settings_field(
			'redirectless_config',
			esc_html( __( 'Redirectless login', 'lmsace-connect' ) ),
			array( $this, 'redirectless_config' ),
			'lac-general-settings',
			'lac_sso_options'
		);

		add_settings_field(
			'create_registration_config',
			esc_html( __('Create users in Moodle on new registration', 'lmsace-connect') ),
			array( $this, 'create_registration_config' ),
			'lac-general-settings',
			'lac_sso_options'
		);

		add_settings_field(
			'auth_method',
			'Authentication type',
			array( $this, 'auth_method_config' ),
			'lac-general-settings',
			'lac_sso_options'
		);

	}

	/**
	 * Redirect less login, which means sso will be working using curl create moodle session directly.
	 *
	 * @return void
	 */
	public function redirectless_config() {
		$options = get_option('lac_sso_settings');
		?>
			<input type="checkbox" id="redirectless_login" name='lac_sso_settings[redirectless_login]' class="form" value="1" <?php echo isset($options['redirectless_login']) && $options['redirectless_login'] ? "checked" : ''; ?> >
			<label for="redirectless_login"> Enable </label>
		<?php
	}

	/**
	 * Config for user creation, user in moodle when they created in wordpress.
	 *
	 * @return void
	 */
	public function create_registration_config() {
		$options = get_option('lac_sso_settings');
		?>
			<input type="checkbox" id="create_onregister" name='lac_sso_settings[create_onregister]' class="form" value="1" <?php echo isset($options['create_onregister']) && $options['create_onregister'] ? "checked" : ''; ?> >
			<label for="create_onregister"> Enable </label>
		<?php
	}

	/**
	 * Config for login method, Login the user to lms during the course access or login in wordpress.
	 *
	 * @return void
	 */
	public function login_lms() {
		$options = get_option('lac_sso_settings');
		?>
		<select  id="login_lms" name='lac_sso_settings[login_lms]'>
			<option value="login" <?php echo isset($options['login_lms']) && $options['login_lms'] == "login" ? "selected" : ''; ?> >
				<?php echo __('Login on WP', 'lmsace-connect'); ?>
			</option>
			<option value="course" <?php echo isset($options['login_lms']) && $options['login_lms'] == "course" ? "selected" : ''; ?> >
				<?php echo __('Access the course', 'lmsace-connect'); ?>
			</option>
		</select>
		<?php
	}

	/**
	 * Config for the auth method user need to create.
	 *
	 * @return void
	 */
	public function auth_method_config() {
		$options = get_option('lac_sso_settings');
		?>
		<select  id="auth_method" name='lac_sso_settings[auth_method]'>
			<option value="manual" <?php echo isset($options['auth_method']) && $options['auth_method'] == "manual" ? "selected" : ''; ?> >
				<?php echo __('Manual'); ?>
			</option>
			<option value="lmsace_connect" <?php echo isset($options['auth_method']) && $options['auth_method'] == "lmsace_connect" ? "selected" : ''; ?> >
				<?php echo __('LMSACE Connect'); ?>
			</option>
		</select>
		<ul class="auth-method-info" style="max-width: 700px">
			<li> <p> <?php echo __(' The <b> Manual method </b> allows users to log in to Moodle via WordPress and automatically creates a Moodle user with a new password.
			The user\'s password is then sent to them via email, enabling them to log in to Moodle directly using the shared credentials.
			Also it allows users to login via SSO', 'lmsace-connect'); ?> </p> </li>

			<li> <p> <?php echo __('The <b>LMSACE Connect </b> method offers SSO integration with Moodle LMS using the auth_lmsace_connect authentication method.
			With this method, LMSACE Connect creates a new user without a password in Moodle.
			Users can only access the Moodle site using SSO, and direct login into Moodle is disabled.', 'lmsace-connect'); ?> </p> </li>
		</ul>
		<?php
	}

	/**
	 * SSO config section heading.
	 *
	 * @return void
	 */
	public function sso_config_section() {
		esc_html_e('Configure SSO to sign in to LMS automatically with WordPress login', 'lmsace-connect');
	}

}
