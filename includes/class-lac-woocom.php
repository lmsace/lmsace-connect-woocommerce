<?php

/**
 * LACONN - Woocommerce object.
 *
 * Contains woocommerce actions, user enrolment, My courses and woocommerce related functions.
 */
class LACONN_Woocom extends LACONN_Main {

	/**
	 * LACONN_User class object.
	 * @var object
	 */
	public $USER;

	/**
	 * LACONN_woocomm class instance object.
	 *
	 * @var LACONN_Woocomm
	 */
	public $instance;

	/**
	 * Returns an instance of the plugin object
	 *
	 * @return LACONN_Woocomm Main instance
	 *
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof LACONN_Woocom ) ) {
			self::$instance = new LACONN_Woocom;
		}
		return self::$instance;
	}

	/**
	 * Register woocommerce related actions.
	 * @return null
	 */
	public function lac_register_actions() {
		global $LACONN;

		// Observer the order status changed to completed. then create user on LMS.
		add_action( 'woocommerce_order_status_completed', array($this, 'process_order_completed'), 10, 1 );
		// Unenrol the user form LMS after the order refunded.
		add_action( 'woocommerce_order_status_changed', array($this, 'process_order_refund' ), 10, 1);
		// add_action('woocommerce_order_refunded', array($this, 'process_order_refund' ), 10, 2);

		// Warning message to disable the guest checkout on woocommerce.
		add_action( 'admin_notices', array( $this, 'warn_disable_guest_checkout' ) );
		// Add user enrolment status metabox on admin backend order page.
		add_action( 'add_meta_boxes', array( $this, 'add_user_enrollment_metabox' ) );
		// Ajax observer for create enrolment manually.
		add_action( 'wp_ajax_lac_user_enrollment', array( $this, 'create_order_enrollment') );
		// Rearrange the menu order on user dashboard.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'my_account_menu_order') );

		// Add My courses url end point with my account page.
		add_action( 'init', array(  $this, 'mycourses_endpoints' ) );

		// Add Mycourses into permalinks. if does not works save the permalinks on setting pages.
		register_activation_hook( __FILE__, array(  $this, 'mycourses_flush_rewrite_rules' ) );
		register_deactivation_hook( __FILE__, array(  $this, 'mycourses_flush_rewrite_rules' ) );
		// My course list of user enrolled courses.
		add_action( 'woocommerce_account_mycourses_endpoint', array( $this, 'mycourses_endpoint_content' ) );

		// Prevent the quantity increment for the course products.
		add_filter( 'woocommerce_quantity_input_args', array( $this, 'lac_min_product_quantity' ), 10, 2 );
		// Prevent add to cart more than one for the course products.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'lac_prevent_twice_course_product' ), 10, 2 );

		add_action( 'wp_enqueue_scripts',  array( $this, 'enqueue_accordion_scripts' ) );

		add_action( 'wp_footer', array( $this, 'initialize_accordion_script' ) );

	}

	// Enqueue the necessary scripts and styles.
	public function enqueue_accordion_scripts() {
		wp_enqueue_script('jquery-ui-accordion');
		wp_enqueue_style('jquery-ui-style', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
		wp_enqueue_style('lmsace-connect-style', $this->wwwroot . 'styles.css');
	}

	/**
	 * Initialize the script for accordion.
	 *
	 * @return void
	 */
	function initialize_accordion_script() {
		echo '<script type="text/javascript">
			jQuery(document).ready(function($) {
				$("#lmsace-connect-summary-accord").accordion({
					collapsible: true
				});
			});
		</script>';
	}

	/**
	 * Prevent add course product twice in the cart.
	 * @param  [type] $validation [description]
	 * @param  [type] $product_id [description]
	 * @return [type]             [description]
	 */
	public function lac_prevent_twice_course_product( $validation, $product_id ) {

		$products = [];

		$md_course_id = $this->get_product_moodle_id( $product_id );
		// Product not a course linked no need to prevent.
		if ( !$md_course_id ) {
			return $validation;
		}

		foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
			$product = $cart_item['data'];
			$products[] = $product->get_id();
		}
		// Course Product already added to the cart.
		if ( in_array($product_id, array_values($products)) ) {
			return false;
		}

		return $validation;
	}

	/**
	 * Prevent the max course product quantity to 1.
	 *
	 * @param WC_Product $args
	 * @param stdclass $product
	 * @return array
	 */
	public function lac_min_product_quantity( $args, $product ) {
		if ( $md_course_id = $this->get_product_moodle_id( $product->get_id() )) {
			$args['max_value'] = 1;
			$args['input_value'] = 1;
		}
		return $args;
	}

	/**
	 * Creat enrollment for order - not in use.
	 *
	 * @return void
	 */
	public function create_order_enrollment() {

		if (isset($_POST['order_id']) && !empty($_POST['order_id'])) {
			$result = $this->process_order_completed( sanitize_text_field( $_POST['order_id'] ) );
		}

		echo esc_html( json_encode(['error' => !$result ]) );
		die();
	}

	// Admin Notice to disable the guest checkout.
	public function warn_disable_guest_checkout() {
		$checkout = WC_Admin_Settings::get_option('woocommerce_enable_guest_checkout');
		if ($checkout == 'yes') {
			?>
		    <div class="notice notice-error is-dismissible lmsace-notice">
				<h4> <?php echo esc_html('LMSACE Connect', 'lmsace-connect'); ?> </h4>
		        <p><?php esc_html_e( 'Disable the guest checkout on woocommerce..', 'lmsace-connect' ); ?></p>
		    </div>
		    <?php
		}
	}

	/**
	 * Create new user using email id.
	 *
	 * @param string $email
	 * @return void
	 */
	public function wp_create_user( $email ) {
		return wc_create_new_customer( $email );
	}

	/**
	 * Callback of Process the order completed hook. - Create the user in moodle and enrol the user in moodle course.
	 *
	 * @param string $order_id
	 * @return void
	 */
	public function process_order_completed( $order_id='' ) {
		global $LACONN;

		if ( $order_id != '' ) {

			// Get the order details like user product and other order meta.
			$details = $this->get_order_details($order_id);

			if (!$this->is_product_hascourse($details['order'])) {
				return true;
			}

			$LACONN->logger()->add( 'order', ' Order completed, LACONN create user enrolment init - OrderID: '.$order_id.' ');

			if ( $details['user'] ) {
				// Check the customer on moodle, else it's creates the user in moodle.
				// Moodle user id from connected moodle site.
				$LACONN->logger()->add( 'order', ' Creating moodle user... ');

				$user_id = $details['order']->get_user_id();

				// Guest checkout support. if user_id is empty then use the useremail as userid.
				if ( !$user_id ) {
					$user_id = $details['user']->email;
				}

				// Check the order user is moodle user or already created in moodle.
				$md_user_id = $LACONN->User->is_moodle_user( $user_id, true, $details['user'] );

				if ( $md_user_id ) {
					// Enrol the user in each orderer item/course.
					$metaenrols = [];
					$enrolments = [];
					foreach ( $details['products'] as $product ) {
						// Get post id of the order item.
						$product_id = $product->get_product_id();
						// Get course id related to the moodle for the current item in order.
						$md_courses = $this->get_product_moodle_id($product_id);

						$md_courses = (!is_array($md_courses)) ? array($md_courses) : $md_courses;

						foreach ( $md_courses as $md_course_id ) {
							// Enrol the user in the course in lms environment.
							if ( !empty($md_course_id) ) {
								$enrols = array(
									'roleid' => $this->get_student_role(),
									'userid' => $md_user_id,
									'courseid' => $md_course_id
								);

								$metaenrols[] = array_merge($enrols, ['productid' => $product_id]);
								$enrolments[] = $enrols;

								$LACONN->logger()->add( 'order',
								' Preparing enrolment - "'.$product_id.'" ('.json_encode( $enrolments ).') ' );
							}
						}
					}

					if ( isset($enrolments) && !empty($enrolments) ) {

						$result = $this->enrol_user_moodle($enrolments);
						if ( $result != null ) {
							$details['order']->update_meta_data( 'lac_enrolments', $metaenrols );
							$LACONN->logger()->add( 'order', ' Updated enrolments in order meta - '.json_encode($enrolments));
							foreach ( $enrolments as $enrolment ) {
								if ( isset( $enrolment['courseid'] ) ) {
									$LACONN->set_admin_notices( 'success', sprintf ( esc_html( __('User %1s enrolled on the courses', 'lmsace-connect') ), $details['user']->user_login ) );
								}
							}
						}
					}
				} else {
					$details['order']->update_meta_data( 'lac_enrolments', [] );
					$LACONN->set_admin_notices( 'error', esc_html( __('Can\'t able to create user in Moodle', 'lmsace-connect') ) );
				}
			} else {
				$LACONN->set_admin_notices( 'error', esc_html( __('Order user not created in WordPress', 'lmsace-connect')) );
			}
			$t = $details['order']->save();
		}
	}

	/**
	 * process_order_refund
	 *
	 * @param  mixed $order_id
	 * @param string $force Force the unenrolment.
	 * @return void
	 */
	public function process_order_refund( $order_id, $force = false ) {
		global $LACONN;

		if (!empty($order_id)) {
			// Get the refuned order details to unenroll the user from moodle.
			$details = $this->get_order_details( $order_id );

			if (!$this->is_product_hascourse($details['order'])) {
				return true;
			}

			$order_enrolments = $details['order']->get_meta( 'lac_enrolments' );

			$order_enrolments = apply_filters( 'lmsace_connect_order_enrolments', $order_enrolments, $details['order'] );

			// User id connected with moodle.
			$user_id = $details['order']->get_user_id();

			$data = $details['order']->get_data();

			$refund = isset($this->options['refund_suspend']) ? $this->options['refund_suspend'] : LACONN_SUSPEND;

			if ( ($force || $data['status'] != 'completed') && $refund && !empty($order_enrolments) ) {

				$LACONN->logger()->add( 'order', ' Order refund init - orderID: '.$order_id);
				// Enrol the user in each orderer item/course.
				foreach ( $order_enrolments as $enrol ) {
					$meta = [];
					if (isset($enrol['productid'])) {
						$meta['productid'] = $enrol['productid'];
						unset($enrol['productid']);
						unset($enrol['suspend']); // Remove suspend from meta due to the unenrol param issue.
					}
					if ($refund == LACONN_SUSPEND) {
						$enrol['suspend'] = 1;
						$meta['suspend'] = 1;
					}

					$enrolments[] = $enrol;
					$metaenrols[] = array_merge($enrol, $meta);
					// Un enrol the user from course in LMS environment.
				}
				if ( isset($enrolments) && !empty($enrolments) ) {

					if ($refund == LACONN_SUSPEND) {
						$LACONN->logger()->add( 'order', ' Order enrolments suspend init - '. json_encode($enrolments));
						$result = $this->enrol_user_moodle($enrolments);
						$details['order']->update_meta_data( 'lac_enrolments', $metaenrols );
					} else {
						$LACONN->logger()->add( 'order', ' Order enrolments unenrol init - '. json_encode($enrolments));
						$result = $this->unenrol_user_moodle($enrolments);
						$details['order']->update_meta_data( 'lac_enrolments', [] );
					}
					$details['order']->save();

					if ( $result ) {
						foreach ($enrolments as $enrolment) {
							// $LACONN->set_admin_notices( 'success', sprintf ( esc_html( __('User %1s enrolment suspended in the course ', 'lmsace-connect') ), $user->user_login, $md_course_id ) );
						}
					}
				}
			}
		}
	}

	/**
	 * Get user and product details from order data.
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function get_order_details( $order_id ) {

		// Get an instance of the order object (same as before)
		$order = new WC_Order( $order_id );

		// Get order data like date created, customer id, price etc..
		$orderData = $order->get_data();

		// Get list of courses purchased on the order.
		$products = $order->get_items();

		// Order User object.
		$orderuser = $order->get_user();
		// Get the user data from order object.
		$user = $orderuser ? $orderuser->data : new stdclass();

		// Ensure firstname and lastname are set for registered users.
		if ( $orderuser ) {
			$user_id = $order->get_user()->ID;
			$user->firstname = get_user_meta($user_id, 'first_name', true) ?: $order->get_billing_first_name();
			$user->lastname = get_user_meta($user_id, 'last_name', true) ?: $order->get_billing_last_name();
		}

		if (!isset($user->email) && isset($user->user_email) && !empty($user->user_email)) {
			$user->email = $user->user_email;
		}

		// Adding guest user support.
		if (!isset($user->email) || $user->email == '') {
			// Set the billing email as user email if it's empty.
			$user->email = $order->get_billing_email();
			$user->ID = 'guest';
			$user->firstname = $order->get_billing_first_name();
			$user->lastname = $order->get_billing_last_name();
			$user->user_login = $order->get_billing_email();
		}

		return array( 'order' => $order, 'products' => $products, 'user' => $user );
	}

	/**
	 * Enrol user into Moodle LMS.
	 *
	 * @param array $enrol List of enrolments.
	 * @return bool
	 */
	public function enrol_user_moodle( $enrol ) {
		global $LACONN;

		$service_data = array('enrolments' => $enrol );
		$result = $LACONN->Client->request( LACONN::services('enrol_users'), $service_data );
		// Enrolment webservice does not return any value.
		return (is_null($result) || $result !== false) ? true : false;
	}

	/**
	 * Unenrol the student from moodle course.
	 *
	 * @param array $enrol user enrolment data.
	 * @return bool
	 */
	public function unenrol_user_moodle( $enrol ) {
		global $LACONN;
		$service_data = array( 'enrolments' => $enrol );
		$result = $LACONN->Client->request( LACONN::services('unenrol_users'), $service_data );
		return ($result == null) ? true : false;
	}

	/**
	 * Fetch the linked product data from the course id.
	 * Return the product details for the given courseid - moodle basedid.
	 * @param int $courseid
	 * @return void
	 */
	public function get_product_from_course_id($courseid) {

		$args = array(
		   'fields' => 'ids',
		   'post_type'   => 'product',
		   'meta_query'  => array(
		     	array(
		     		'key' => LACONN_MOODLE_COURSE_ID,
		     		'value' => serialize([$courseid])
		     	)
		   	)
		);
		$product = new WP_Query( $args );

		return ($product->have_posts()) ? $product->get_posts() : false;
	}

	/**
	 * Returns the course product image. if not found return the default course image.
	 *
	 * @param object $product
	 * @return void
	 */
	public function get_product_image($product) {
		$dummyimage = $this->wwwroot.'/assets/images/defaultimage.png';
		$imageurl = wp_get_attachment_url( $product->get_image_id() );
		return ($imageurl) ?: $dummyimage;
	}

	/**
	 * Fetch the list of courses linked with the product.
	 *
	 * @param int $product_id
	 * @return void
	 */
	public function get_product_moodle_id( $product_id ) {
		return get_post_meta($product_id, LACONN_MOODLE_COURSE_ID, true);
	}

	/**
	 * Order Metabox to display the enrolment status for the order.
	 *
	 * @return void
	 */
	public function add_user_enrollment_metabox() {

		add_meta_box(
			'lac_order_enrollment', // Unique ID.
			esc_html(__('LMSACE Connect user enrollment notes')), // Title.
			array( $this, 'user_enrollment_meta_callback' ), // Callback.
			'shop_order', // Screen
			'side',
			'default'
		);
	}

	public function user_enrollment_meta_callback( $post ) {
		global $LACONN;
		$order_id = $post->ID;
		$details = wc_get_order($order_id);
		if (method_exists($details, 'get_meta')) {
			$enrolments = $details->get_meta('lac_enrolments');
		} else {
			$enrolments = get_post_meta($order_id, 'lac_enrolments', true);
		}
		if (empty($enrolments)) {
			echo esc_html_e("Order doesn't contain any enrolments in LMS", LAC_TEXTDOMAIN);
			return false;
		}
		$users = array_column($enrolments, 'userid');
		$courses = array_column($enrolments, 'courseid');
		$users = $LACONN->User->is_moodle_user_exits($users);
		$courses = $LACONN->Course->get_courses_byid($courses);
		$list = '';
		foreach ($enrolments as $enrol) {
			$moodle_userid = $enrol['userid'];
			$moodle_courseid = $enrol['courseid'];
			if (isset($users[$moodle_userid]) && isset($courses[$moodle_courseid])) {
				$email = $users[$moodle_userid]->email;
				$coursename = $courses[$moodle_courseid]->fullname;
				$list .= '<div class="enrolment-status-item">';
				$list .= '<p class="enrolment-status-message">';
				if (isset($enrol['suspend']) && $enrol['suspend']) {
					$list .= sprintf( esc_html( __("User with email %s, suspended in the course %s ", 'lmsace-connect') ), $email, $coursename);
				} else {
					$list .= sprintf( esc_html( __("User with email %s, enrolled in the course %s ", 'lmsace-connect') ), $email, $coursename);
				}
				$list .= '<a href="'.$this->site_url.'course/view.php?id='.$moodle_courseid.'">'. esc_html( __( '( View course on LMS )', 'lmsace-connect')).'</a>';
				$list .= '</p>';
				$list .= '</div>';
			}
		}
		echo wp_kses($list, $LACONN->allowed_tags());
	}

	/**
	 * User courses template endpoints.
	 *
	 * @return void
	 */
	public function mycourses_endpoints() {
		add_rewrite_endpoint( 'mycourses', EP_ROOT | EP_PAGES );
	}

	/**
	 * Flush rewrite rules on plugin activation.
	 */
	function mycourses_flush_rewrite_rules() {
		add_rewrite_endpoint( 'mycourses', EP_ROOT | EP_PAGES );
		flush_rewrite_rules();
	}

	/**
	  * Get new endpoint content.
	  */
	function mycourses_endpoint_content() {
		$template = locate_template('mycourses1.php');
		wp_enqueue_style('front-laconn', $this->wwwroot.'/assets/css/front.css');
	    include($this->dirroot.'/templates/mycourses.php');
	}

	/**
	  * Edit my account menu order.
	  */
	function my_account_menu_order($items) {
		$position = array_search('downloads', array_keys($items));
		$mycourses = array('mycourses' => esc_html( __('My Courses', 'lmsace-connect') ) );
	 	$items = array_merge( array_slice($items, 0, $position, true), $mycourses, array_slice($items, $position, null, true) );
	 	return $items;
	}
}
