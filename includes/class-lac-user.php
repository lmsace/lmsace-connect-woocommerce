<?php
/**
 * User helper - Process user creation and update user data.
 *
 * @package lmsace-connect
 */

defined( 'ABSPATH' ) || exit;

class LACONN_User extends LACONN_Main {

	/**
	 * Logged in user object
	 *
	 * @var WC_customer
	 */
	public $logged_user;

	/**
	 * Instance of LAConn user object.
	 *
	 * @var LACONN_User
	 */
	public static $instance;

	/**
	 * Returns an instance of the plugin object
	 *
	 * @return LACONN_User Main instance
	 *
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof LACONN_User ) ) {
			self::$instance = new LACONN_User;
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @param array $logged_user
	 */
	function __construct( $logged_user=array() ) {
		parent::__construct();
		$this->logged_user = $logged_user;
	}

	/**
	 * Get current logged in user.
	 *
	 * @return wp_user
	 */
	public function get_user() {
		return wp_get_current_user();
	}

	/**
	 * Find the user is register with moodle using userid.
	 *
	 * @param int $user_id User id.
	 * @param bool $create_user Create the user in moodle if not exists.
	 * @param array $user_data User data to create in moodle.
	 * @return bool
	 */
	public function is_moodle_user( $user_id, $create_user=false, $user_data = array() ) {

		if ( !empty($user_id) ) {
			// Check wordpress user assigned with moodle user id.
			if ( $md_user_id = $this->get_user_moodle_id($user_id) ) {
				// echo $md_user_id; exit;
				if ( $moodle_user = $this->is_moodle_user_exits( $md_user_id ) ) {
					return $md_user_id;
				} else if ( $create_user ) {
					return $this->create_moodle_user( $user_data );
				} else {
					return false;
				}
			} else if ($create_user) {
				return $this->create_moodle_user( $user_data );
			}
		}
		return false;
	}

	/**
	 * Check the moodle user id is esists in moodle.
	 *
	 * @param int $md_user_id
	 * @return bool
	 */
	public function is_moodle_user_exits( $md_user_id ) {
		if (!empty($md_user_id)) {
			$values = (is_array($md_user_id) ? $md_user_id : [$md_user_id] );
			$args = array( 'field' => 'id', 'values' => $values );
			$users = LACONN()->Client->request( LACONN::services('get_user_by_field'), $args );
			if ( !empty($users) ) {
				$newusers = [];
				foreach ($users as $user) {
					$newusers[$user->id] = $user;
				}
				return $newusers;
			} else {
				return false;
			}
		}
		return false;
	}

	/**
	 * find the username is exists in moodle.
	 *
	 * @param string $username
	 * @return bool
	 */
	public function is_username_exits($username) {

		if (!empty($username)) {
			$args = array( 'field' => 'username', 'values' => array( sanitize_user($username) ) );
			$users = LACONN()->Client->request( LACONN::services('get_user_by_field'), $args );
			if ( !empty($users) ) {
				return $users;
			}
			return false;
		}
		return false;
	}

	/**
	 * Check is the user email id exists in moodle.
	 *
	 * @param string $email
	 * @return bool|stdclass status of mail existence
	 */
	public function is_useremail_exits($email) {
		global $LACONN;
		if ( !empty($email) ) {
			$args = array( 'field' => 'email', 'values' => array($email) );
			$users = $LACONN->Client->request( LACONN::services('get_user_by_field'), $args);
			if ( !empty($users) ) {
				return $users;
			}
		}
		return false;
	}

	/**
	 * Create user in moodle.
	 *
	 * @param stdclass $user_data
	 * @return void
	 */
	public function create_moodle_user( $user_data ) {
		global $LACONN;

		if (is_array($user_data)) {
			$user_data = (object) $user_data;
		}
		if (!isset($user_data->email)) {
			return null;
		}
		$moo_users = $woo_users = array();

		if ( $md_users = $this->is_useremail_exits( $user_data->email ) ) {

			$this->set_admin_notices('warning', ' User Email ID exists on LMS ');

			// Users with same email id from moodle.
			foreach ( $md_users as $user ) {
				// If user email exists just add the moodle user id on wp user object.
				$md_user_id = $user->id;
			}
			$LACONN->logger()->add( 'order', 'User email id"'.$user_data->email.'" exists in Moodle. WPuserid - '.$md_user_id.' ');
			// Returns the reterived moodle user ids.
			return isset($md_user_id) ? $md_user_id : false;

		} else {
			$append = time();
			// Store the wp user id with associated user name.
			$user_data->user_login = isset($user_data->user_login) ? $user_data->user_login : $user_data->email;

			while ( $this->is_username_exits( $user_data->user_login ) ) {
				$LACONN->logger()->add( 'order', 'Create user in moodle, Username "'.$user_data->user_login.'" exists in Moodle. Continue with next name');
				$user_data->user_login .= '_'.$append; // Create the new user name.
				$append++;
			}
			// Store the wp user id with updated username for add user meta.
			$woo_users[$user_data->ID] = $user_data->email;
			// User not exists on the moodle. then create the user on moodle.
			$moo_users[] = $this->create_user_data($user_data);
		}

		if (!empty($moo_users)) {
			// Send the request to connected moodle to create users.
			$userdata = array( 'users' => $moo_users );
			// Filter the data to make the SSO changes.
			$userdata = apply_filters( 'lmsace_connect_create_userdata', $userdata );

			$moodle_users = $LACONN->Client->request( LACONN::services('create_users'), $userdata );

			$moodle_users = apply_filters( 'lmsace_connect_user_create_results', $moodle_users );

			if ( !empty($moodle_users) ) {

				foreach ( $moodle_users as $moodle_user ) {
					// Add moodle userid with the associated wp user.
					$this->add_user_moodle_id( $user_data->ID, $moodle_user->id );
					$LACONN->logger()->add( 'order', 'User "'.$moodle_user->username.'" (WPuserid - '. $user_data->ID.') created in moodle and synced with WP');
					$this->set_admin_notices( 'success', esc_html( __('User created successfully on LMS', 'lmsace-connect') ));
					$md_user_id = $moodle_user->id;
				}
				// Returns the reterived moodle user ids.
				return $md_user_id;
			}
		}
		$this->set_admin_notices( 'error', esc_html( __( "Oops! User can't created on LMS for this order. <br> %s", 'lmsace-connect' ) ), $moodle_users );

		return false;
	}

	/**
	 * Create user data.
	 *
	 * @param stdclass $user_data
	 * @return void
	 */
	public function create_user_data( $user_data ) {

		$firstname = isset($user_data->firstname) ? $user_data->firstname : get_user_meta( $user_data->ID, 'first_name', true );
		$lastname = isset($user_data->lastname) ? $user_data->lastname : get_user_meta( $user_data->ID, 'last_name', true );

		$user_fields = array(
			'createpassword' => true,
			'username' => $this->cleanusername($user_data->user_login),
			'auth' => 'manual',
			'firstname' => $firstname,
			'lastname' => $lastname,
			'email' => $user_data->email
		);



		return $user_fields;
	}

	/**
	 * Clean username to moodle standard.
	 * @param  string $username Wordpress username.
	 * @return string $username Cleaned username in moodle standard.
	 */
	public function cleanusername($username) {
		$username = sanitize_text_field($username);
		$username = trim($username);
		$username = stripslashes($username);
		$username = strtolower($username);
		$username = str_replace('^', '', $username);
		$username = preg_replace("/[^a-zA-Z0-9\@\.\-\_]/", "", $username);
		return $username;
	}

	/**
	 * Add user moodle id in user meta.
	 *
	 * @param int $wp_user_id Wordpress userid
	 * @param int $md_user_id Moodle userid.
	 * @return void
	 */
	public function add_user_moodle_id($wp_user_id, $md_user_id) {

		if ( metadata_exists('user', $wp_user_id, 'moodle_user_id') ) {
			error_log("add-update => $wp_user_id");
			$this->update_user_moodle_id($wp_user_id, $md_user_id);
		} else {
			error_log("add-userid => $wp_user_id");
			add_user_meta($wp_user_id, 'moodle_user_id', $md_user_id);
		}
	}

	/**
	 * Update the user moodle ID in user meta.
	 *
	 * @param int $wp_user_id
	 * @param int $md_user_id
	 * @return void
	 */
	public function update_user_moodle_id($wp_user_id, $md_user_id) {

		if ( !metadata_exists('user', $wp_user_id, 'moodle_user_id') ) {
			error_log("update-add => $wp_user_id");
			$this->add_user_moodle_id($wp_user_id, $md_user_id);
		} else {
			error_log("update-user => $wp_user_id");
			update_user_meta($wp_user_id, 'moodle_user_id', $md_user_id);
		}
	}

	/**
	 * Get user moodle id.
	 *
	 * @param string $user_id
	 * @param bool $email Get based on email.
	 * @return void
	 */
	public function get_user_moodle_id( $user_id = '', $email=false ) {
		global $LACONN;
		if (!empty($user_id)) {
			$user = ($email || is_email($user_id)) ? get_user_by( 'email', $user_id ) : get_userdata( $user_id );
			if ( !empty($user) ) {
				$args = array( 'field' => 'email', 'values' => array( sanitize_user($user->user_email) ) );
				$md_user = $LACONN->Client->request( LACONN::services('get_user_by_field'), $args );
				return isset($md_user[0]->id) ? $md_user[0]->id : false;
			}
		}
		return false;
	}

	/**
	 * Get list of current user orderer.
	 * @param  int $userid USER ID
	 * @return array        include courses
	 */
	public function get_user_orders( $userid = null, $includecourse = true ) {

		$userid = ($userid) ? $userid : $this->get_user()->ID;
		// Getting current customer orders.
		$customer_orders = wc_get_orders(
			array(
				'meta_key' => '_customer_user',
				'meta_value' => $userid,
				'numberposts' => -1
			)
		);

		// Get an instance of the WC_Customer Object from the user ID
		$customer = new WC_Customer( $userid );

		$guest_orders = wc_get_orders(
			array(
				'meta_key' => '_billing_email',
				'meta_value' => ($customer->get_email()) ? sanitize_email( $customer->get_email() ) : sanitize_email( $customer->get_billing_email() ),
				'numberposts' => -1
			)
		);

		$orders = array_merge($customer_orders, $guest_orders);

		// Filter the unique orders.
		$filterorder = [];
		foreach ( $orders as $key => $order ) {
			if (!in_array($order->id, $filterorder)) {
				$filterorder[] = $order->id;
				$finalorders[] = $order;
			}
		}
		return (isset($finalorders)) ? $finalorders : [];
	}

	/**
	 * Get list of current user orderer.
	 * @param  int $userid User ID
	 * @return array       List user enrolled courses via Wordpress.se
	 */
	public function get_user_courses( $user ) {

		if ( $md_user_id = $this->is_moodle_user( $user->ID )) {
			$enrolments = $list = [];
			if ( $orders = $this->get_user_orders( $user->ID, true ) ) {
				foreach ( $orders as $key => $order ) {
					$orderdata = $order->get_data();
					// Moodle course id synced with the selected product.
					if ( $orderdata['status'] == 'completed' ) {
						$courses = get_post_meta($order->id, 'lac_enrolments', true);
						$enrolments = array_merge($enrolments, $courses);
					}
				}
			}
			return $enrolments;
		}
		return [];
	}


}
