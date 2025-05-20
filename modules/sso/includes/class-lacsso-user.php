<?php

/**
 * USER based actions and data procress, user registrion and login on LMS defined.
 *
 * @package LMSACE Connect
 * @subpackage SSO
 * @copyright  2023 LMSACE DEV TEAM <info@lmsace.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class LACONNMOD_SSO_User extends LACONN_Main {

    /**
	 * LACONNPRO_User class instance object.
	 *
	 * @var LACONNPRO_User
	 */
	public $instance;

	/**
	 * Returns an instance of the plugin object
	 *
	 * @return LACONNPRO_User Main instance
	 *
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof LACONNPRO_User ) ) {
			self::$instance = new LACONNPRO_User;
		}
		return self::$instance;
	}

	/**
	 * Register the user side actions and filters for SSO.
	 *
	 * @return void
	 */
    public function lac_register_actions() {
		global $LACONN;
		$options = get_option( 'lac_sso_settings' );

		if (isset($options['login_lms']) && $options['login_lms'] == 'login') {
        	add_filter( 'wp_login', array( $this, 'user_loggedin' ), 1, 2 );
		}
		add_action( 'user_register', array( $this, 'user_registered' ), 10, 1 );
		add_filter( 'lmsace_connect_courseurl', [$this, 'user_courseaccess'] );

		// Add filter for user creation on orders.
		if (isset($options['auth_method']) && $options['auth_method'] == 'lmsace_connect') {
			add_filter( 'lmsace_connect_create_userdata', array($this, 'create_user_data') );
			add_filter( 'lmsace_connect_user_create_results', array($this, 'update_user_result') );
		}

    }

	/**
	 * Prepare the data for user creation.
	 *
	 * @param string $email
	 * @return void
	 */
	public function fetch_user_creationdata( $email ) {
		global $LACONN;
		$user = get_user_by( 'email', $email );

		$userdata = $LACONN->User->create_user_data( $user->data );
		$defaultname = explode('@', $email)[0];
		$data = [
			'firstname' => $userdata['firstname'] ?: $defaultname,
			'lastname' => $userdata['lastname'] ?: $defaultname,
			'username' => $userdata['username'],
			'email' => $email,
			'autocreate' => 1
		];
		return $data;
	}

	/**
	 * Get the authentication method.
	 *
	 * @return void
	 */
	public function create_user_data( $data ) {

		$user = current($data['users']);

		$user_fields = array(
			'autocreate' => true,
			'username' => $user['username'],
			'firstname' => $user['firstname'],
			'lastname' => $user['lastname'],
			'email' => $user['email']
		);

		return ['user' => $user_fields];
	}

	/**
	 * Update the users created result to enrol.
	 *
	 * @param stdclass $result
	 * @return array
	 */
	public function update_user_result( $result ) {

		$user = new stdclass();
		if (isset($result->userid)) {
			$user->id = $result->userid;
		}

		if (isset($result->username)) {
			$user->username = $result->username;
		}

		return [ $user ];
	}

	/**
	 * Hook for user registered in wordpress action, observe the event and trigger the user creation in LMS process.
	 *
	 * @param [type] $userid
	 * @return void
	 */
	public function user_registered( $userid ) {
		$options = get_option( 'lac_sso_settings' );
		if (isset($options['create_onregister']) && $options['create_onregister'] == 1) {
			$user = get_user_by( 'id', $userid );
			$this->user_loggedin($user->data->user_login, $user);
		}
	}

	/**
	 * Create user login URL for LMS, apply the URL for course products to make the user loggedin to LMS when they tried to access courses.
	 *
	 * @param [type] $courseurl
	 * @return void
	 */
	public function user_courseaccess( $courseurl ) {
		global $LACONN;
		$loggedin = $this->verify_isuserlogged_moodle();
		if ( !$loggedin ) {
			$user = $LACONN->User->get_user();
			$user = get_user_by( 'id', $user->ID );

			$response = $this->sso_request( $user->data->user_login, $user );
			if (isset($response->loginkey) && !empty($response->loginkey)) {
				$key = $response->loginkey;
				$authdata = [
					'wstoken' => $this->site_token,
				];
				$mood_login_url = $this->site_url.'auth/lmsace_connect/land.php?'. http_build_query($authdata);
				$mood_login_url .= '&key='.$key.'&wpredirect=';
				return $mood_login_url;
			}
		}
		return $courseurl;
	}

	/**
	 * Send the login request to connected LMS.
	 *
	 * @param string $user_login
	 * @param stdclass $user
	 * @return object
	 */
	public function sso_request( $user_login, $user ) {
		global $LACONN;
		static $response;
		if ( $response == '' ) {
			$user_email = $user->data->user_email;
			$data = $this->fetch_user_creationdata( $user_email );

			if ( empty($user_email) ) {
				return;
			}

			$response = $LACONN->Client->request( LACONN::services('generate_userloginkey'), ['user' => $data] );
			if ( isset($response->loginkey) && !empty($response->loginkey) ) {
				// Add moodle user id for that user.
				$LACONN->User->add_user_moodle_id( $user->data->ID, $response->userid );
			}
		}

		return $response;
	}

	/**
	 * Make the session for current wp user in connected moodle. using redirect method or create Session directly.
	 *
	 * @param string $user_login EMAIL or Username
	 * @param stdclass $user
	 * @return void
	 */
    public function user_loggedin( $user_login, $user ) {
		global $LACONN;

		$options = get_option( 'lac_sso_settings' );

		// Demo User.
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

		$response = $this->sso_request( $user_login, $user );
		if ( isset($response->loginkey) && !empty($response->loginkey) ) {
			$key = $response->loginkey;
			$authdata = [
				'wstoken' => $this->site_token,
			];

			$mood_login_url = $this->site_url.'auth/lmsace_connect/land.php?'. http_build_query($authdata);
			if ( isset($options['redirectless_login']) && $options['redirectless_login'] == 1 ) {
				$result = wp_remote_post($mood_login_url, [ 'body' => array('key' => $key) ]);
				$cookie = wp_remote_retrieve_cookie($result, LACONN_MOODLESESSION);
				if (!empty($cookie)) {
					// Setup the moodle session for the user.
					setcookie('MoodleSession', $cookie->value, $cookie->expires, $cookie->path);
					$this->set_session_data('moodlelms_loggedin', true);
				}

			} else {
				$myaccount = get_permalink( wc_get_page_id( 'myaccount' ) );
				$mood_login_url .= '&key='.$key.'&wpredirect='.$myaccount;
				$this->set_session_data('moodlelms_loggedin', true);
				wp_redirect($mood_login_url);
				exit;
			}
		} else {
			// TODO: need to manage the execeptions.
		}
    }

	/**
	 * Verify the current user is loggedin on connected moodle LMS.
	 *
	 * @return void
	 */
	public function verify_isuserlogged_moodle() {
		global $LACONN;

		$user_id = $LACONN->User->get_user()->ID;
		$md_user_id = $LACONN->User->get_user_moodle_id($user_id);
		$data = ($md_user_id) ? ['userid' => $md_user_id] : [];
		$response = $LACONN->Client->request( LACONN::services('is_loggedin'), $data);

		return $response;
	}
}
