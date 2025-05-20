<?php

/**
 * Woocommerce moodle integration - Main class. Includes files, classes, trigger actions.
 */
class LACONN_Main {

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
	 * @var [type]
	 */
	public $client;

	/**
	 * LMS Site webserice token.
	 * @var string
	 */
	public $site_token;

	/**
	 * LMS Site connection URL.
	 * @var [type]
	 */
	public $site_url;

	/**
	 * Construct method contains site connection details.
	 *
	 * @param boolean $helpers [description]
	 */
	public function __construct() {

		$this->dirroot = dirname( LAC_PLUGIN_FILE );

		$this->wwwroot = plugin_dir_url(__DIR__);

		$options = get_option( 'lac_connection_settings' );

		$generaloptions = get_option( 'lac_general_settings' );

		$this->options =  (is_array($options)) ? $options : [];
		if (is_array($generaloptions)) {
			$this->options = array_merge($this->options, $generaloptions);
		}

		$this->site_url = isset($this->options['site_url']) ? $this->options['site_url'] : '';

		if ( $this->site_url != '' && strrev($this->site_url)[0] !== '/' ) {
			$this->site_url .= '/';
		}

		$this->site_token = isset($this->options['site_token']) ? $this->options['site_token'] : '';
	}

	/**
	 * Include required class file and create the class object. Class name must have same as file * name with plugin file keyword.
	 *
	 * @param string  $class  Classname.
	 * @param boolean $is_sub If file is in sub directory.
	 * @param array   $params Parameters for the class contructor.
	 */
	public function get_handler($class='', $is_sub=false, $params=array()) {

		$path = $this->dirroot.'/includes/';
		$path .= ($is_sub) ? $is_sub.'/' : '';
		$path .= 'class-lac-'.$class.'.php';
		require_once($path);
		$class = 'LACONN_'.ucwords(str_replace( '-', '_', $class));
		return (new ReflectionClass ( $class ))->newInstanceArgs ( $params );
	}

	/**
	 * Get value of config.
	 *
	 * @param string $key
	 * @return void
	 */
    public function get_config( $key ) {
		if ( isset( $this->options[ $key ] ) ) {
			return $this->options[ $key ];
		}
		return null;
	}

	/**
	 * Generate course view page url for LMS.
	 *
	 * @param  int $courseid Linked course id.
	 * @return string Course LMS url
	 */
	public function get_course_url($courseid=null) {
		$url = $this->site_url.'course/view.php';
		return ($courseid) ? $url.'?'. http_build_query(['id' => $courseid]) : $url;
	}

	/**
	 * Find the order contain the course product.
	 *
	 * @param wc_order $order
	 * @return bool
	 */
	public function is_product_hascourse( $order ) {
		$products = $order->get_items();
		foreach ($products as $product) {
			$id = get_post_meta( $product->get_product_id(), LACONN_MOODLE_COURSE_ID, true );
			// print_r($id);
			if (!empty($id)) {
				return true;
			}
		}
		return false;
	}

	// Get wordpress category details , for the given moodle category id.
	public function get_term_from_moodle_categoryid($categoryid) {
		$args = array(
			'hide_empty' => false,
			'taxonomy'   => 'product_cat',
			'meta_query' => array(
		     	array(
			     	'key'   => "lac_moodle_category_id",
			     	'value' => $categoryid
		     	)
		   	)
		);
		$category = get_terms( $args );

		return (!empty($category)) ? $category : false;
	}

	/**
	 * Get the selected student role from plugin settings.
	 *
	 * @return int
	 */
	public function get_student_role() {
		global $LACONN;
		if ( isset( $this->options['student_role'] ) && !empty( $this->options['student_role'] )  ) {
			$role = $this->options['student_role'];
		} else {
			$request = $LACONN->Client->request( LACONN::services('get_user_roles'), array() );
			if (!empty($request)) {
				foreach ($request as $rolearr) {
					if ($rolearr->id == 5) {
						$roleid = $rolearr->id;
					}
				}
				if (!isset($roleid)) {
					$rolearr = end($request);
					$roleid = (isset($rolearr->id)) ? $rolearr->id : '5';
				}
			}
		}
		return ($role != '' ) ? $role : 5;
	}

	/**
	 * Set admin notice using session.
	 * @param string $type  error or sucess
	 * @param string $message Message to display on notice
	 */
	public function set_admin_notices($type, $message) {

		if ( !isset($_SESSION['lac_admin_flash']) ) {
			$_SESSION['lac_admin_flash'] = array();
		}

		$_SESSION['lac_admin_flash'][] = array( 'type' => $type, 'message' => sanitize_text_field( $message) );
	}

	/**
	 * Set the session data.
	 *
	 * @param string $config config name
	 * @param mixed $value data value.
	 * @return void
	 */
	public function set_session_data($config, $value) {
		$config = 'LACONN_session_'.$config;
		set_transient($config, $value);
	}

	/**
	 * Get session data.
	 */
	public function get_session_data($key) {
		$key = 'LACONN_session_'.$key;
		return get_transient($key);
	}
}
