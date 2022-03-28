<?php
/**
 * Helpher - Admin backend progress.
 *
 * @package lmsace-connect
 */

defined( 'ABSPATH' ) || exit;

class LACONN_Admin extends LACONN_Main {

	/**
	 * Admin capability.
	 *
	 * @var integer
	 */
	public $admin_capability = 9;

	/**
	 * Admin Instance object.
	 *
	 * @var object Admin class instance object.
	 */
	public $instance;

	/**
	 * Returns an instance of the plugin object
	 *
	 * @return instance LACONN Admin instance
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof LACONN_User ) ) {
			self::$instance = new LACONN_User;
		}
		return self::$instance;
	}

	/**
	 * Admin LACONN config tabs.
	 *
	 * @return array
	 */
	public function get_tabs() {
		return [
			'lac-connection-options' => __( 'Connection Setup' ),
			'lac-general-options' => __('General Setup'),
			'lac-import-courses' => __( 'Import Courses' ),
		];
	}

	/**
	 * WP register admin actions for this class instance.
	 *
	 * @return void
	 */
	public function lac_register_admin_actions() {
		// Add Admin menu.
		add_action( 'admin_menu', array($this, 'admin_settings'), $this->admin_capability );
		// Add admin scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'lac_admin_scripts') );
		// Admin ajax scripts - callback.
		add_action( 'wp_ajax_lac_admin_client', array( $this, 'lac_admin_client') );

		// Add LAConn tabs in product page.
		add_filter( 'woocommerce_product_data_tabs', 'lac_custom_product_tabs' );
		// Add course selector select box in LAConn tab on product page.
		add_filter( 'woocommerce_product_data_panels', 'lac_moodle_courses_product_tab_content' );
		// Save course id for the prodct when the product Updated.
		add_action( 'woocommerce_process_product_meta_simple', 'lac_save_moodlecourse_option_fields'  );
		add_action( 'woocommerce_process_product_meta_variable', 'lac_save_moodlecourse_option_fields'  );

	}

	/**
	 * Scripts and styles for admin backend.
	 *
	 * @return void
	 */
	public function lac_admin_scripts() {

		wp_enqueue_script( 'jquery-form' );

		wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css' );
		wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js', array('jquery') );

		// Data table js file from CDN.
		wp_enqueue_style('jquery-datatables-css','//cdn.datatables.net/1.10.22/css/jquery.dataTables.min.css');
        wp_enqueue_script('jquery-datatables-js','//cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js',array('jquery'));
		// Select addon for datatable.js
		wp_enqueue_script('jquery-datatables-select-js','//cdn.datatables.net/select/1.3.4/js/dataTables.select.min.js');
		wp_enqueue_style('jquery-datatables-select-css','https://cdn.datatables.net/select/1.3.4/css/select.dataTables.min.css');

		// Plugin styles for admin.
		wp_enqueue_style('LACONN', $this->wwwroot.'/assets/css/styles.css' );
		// Adminjs script inclusion.
		wp_enqueue_script('lac-admin-scripts', $this->wwwroot . '/assets/js/admin.js', array('jquery', 'select2'));

		$jsdata = array(
			'admin_ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce(NONCEKEY),
			'loaderurl' => admin_url('images/loading.gif'),
			'strings' => [
				'allcategories' => __('All Categories', 'lmsace-connect'),
				'reloadcourses' => __('Reload Courses', 'lmsace-connect'),
				'filtercategories' => __('Filter category', 'lmsace-connect'),
				'settingssaved' => __('Settings saved successfully', 'lmsace-connect'),
			]
		);

		wp_localize_script('lac-admin-scripts', 'lac_jsdata', $jsdata );
	}

	/**
	 * Admin ajax callback function for LMSACE connect - methods are called from here.
	 *
	 * @return void
	 */
	public function lac_admin_client() {
		global $LACONN;

		// Enable ajax script debug for development.
		if (LMSCONF_DEBUG) {
			error_reporting(E_ALL);
			ini_set('display_errors', true);
		}
		// check_ajax_referer(NONCEKEY, 'nonce_ajax');
		$result = array('error' => true, 'msg' => '');
		if (isset($_REQUEST)) {
			// Load the class-lac-admin-request.php and create the object.
			$this->admin_request = $LACONN->get_handler('admin-request', 'admin');

			if (isset($_REQUEST['callback']) && !empty($_REQUEST['callback']) ) {
				// Callback function from ajax request.
				$callback = $_REQUEST['callback'];
				// Check method exsits on client.
				$args = isset($_REQUEST['args']) ? $_REQUEST['args'] : array();

				if (method_exists($this->admin_request, $callback )) {
					// Call the callback method from the client.
					$result = call_user_func_array(array($this->admin_request, $callback), $args );
				} else {
					$result['msg'] = __('Callback function not available.', 'lmsace_lac');
				}
			} else {
				$result['msg'] = __('Callback function not added.', 'lmsace_lac');
			}
		}

		$result = ($result) ? $result : array('error' => true, 'msg' => 'Error on sending response'  );

		// Result the result to ajax call.
		echo json_encode($result);

		die(); // Exit the ajax script.
	}

	/**
	 * Get course image url with connected token.
	 * @param  object $course course data form api.
	 * @return string Course image url or default image url.
	 */
	public function get_course_image($course) {

		$defaultimage = $this->wwwroot.'assets/images/defaultimage.png';
		if (isset($course->overviewfiles) && !empty($course->overviewfiles)) {
			foreach ($course->overviewfiles as $key => $file) {
				if (isset($file->fileurl) && !empty($file->fileurl) ) {
					$fileurl = $file->fileurl.'?token='.$this->site_token;
				}
				return (isset($fileurl)) ? $fileurl : $defaultimage;
			}
		}
		return $defaultimage;
	}

	/**
	 * Update the course summary image from moodle to WP and replace the summay with wp images.
	 *
	 * @param int $post_id WP Post id.
	 * @param object $course Course data object form api
	 * @return string Updated course summary.
	 */
	public function replace_coursesummary_images($post_id, $course) {
		global $LACONN;
		$doc = new DOMDocument();
		if (empty($course->summary)) {
			return $course->summary;
		}
		@$doc->loadHTML($course->summary);
		$tags = $doc->getElementsByTagName('img');
		$images = $src = [];
		foreach ($tags as $tag) {
			$url = $tag->getAttribute('src');
			$src[] = $url; // Used on replace.
			$url .= '?token='.$this->site_token;
			$images[] = $url;
		}
		if (!empty($images)) {
			$updated = $LACONN->Course->upload_product_image($post_id, $images, 'course_summary');
			$summary = strtr($course->summary, array_combine($src, $updated));
			return $summary;
		}
		return $course->summary;
	}

	/**
	 * LMSACE Connect admin settings options for connection and selective import.
	 *
	 * @return void
	 */
	public function admin_settings() {

	    add_menu_page(
	        __('LMSACE Connect', LAC_TEXTDOMAIN),
	        __('LMSACE Connect', LAC_TEXTDOMAIN),
	        'manage_options',
	        'lac-admin-settings',
	        array( $this, 'admin_setting_tabs'),
	        $this->wwwroot . 'assets/images/lac.ico', 55
	    );

		add_submenu_page(
			"lac-admin-settings",
			__('Connection', LAC_TEXTDOMAIN),
			__('Connection', LAC_TEXTDOMAIN),
			'manage_options',
			"lac-connection-options",
			array( $this, 'admin_setting_connectionsetup')
		);

		add_submenu_page(
			"lac-admin-settings",
			__('General', LAC_TEXTDOMAIN),
			__('General', LAC_TEXTDOMAIN),
			'manage_options',
			"lac-general-options",
			array( $this, 'admin_setting_generaloptions')
		);

		add_submenu_page(
			"lac-admin-settings",
			__('Import courses', LAC_TEXTDOMAIN),
			__('Import courses', LAC_TEXTDOMAIN),
			'manage_options',
			"lac-import-courses",
			array( $this, 'admin_setting_importcourses')
		);

		remove_submenu_page('lac-admin-settings', 'lac-admin-settings' );

	    // Call register settings function.
		add_action( 'admin_init', array($this, 'register_lac_settings') );


	}

	public function admin_setting_connectionsetup() {
		$this->admin_setting_tabs( 'lac-connection-options', 'sitedetails' );
	}

	public function admin_setting_generaloptions() {
		$this->admin_setting_tabs( 'lac-general-options', 'generaloptions' );
	}

	public function admin_setting_importcourses() {
		$this->admin_setting_tabs( 'lac-import-courses', 'importcourses' );
	}

	/**
	 * Admin settings tab.
	 *
	 * @return void
	 */
	public function admin_setting_tabs( $tab = '', $content = '' ) {

		$tabs = $this->get_tabs();

		$current = (!empty($tab)) ? $tab : 'lac-connection-options';

		echo '<h1>'.esc_html( get_admin_page_title() ) .'</h1>';

		echo  '<div class="wrap lmsace-connect-admin-config">';

	 	echo '<h2 class="nav-tab-wrapper">';
	    foreach ( $tabs as $tab => $name ){
	        $class = ( $tab == $current ) ? ' nav-tab-active' : '';
	        echo "<a class='nav-tab $class' href='?page=$tab'>$name</a>";
	    }
	    echo '</h2>';

		echo '<div class="tab-content">';
		$tabcontent = ($content) ? $content.'_admin_tabcontent' : 'sitedetails_admin_tabcontent';
		if (method_exists($this,  $tabcontent)) {
			echo $this->$tabcontent();
		}
		echo '</div>';
	}

	/**
	 * General site connection and process details tab.
	 *
	 * @return void
	 */
	public function sitedetails_admin_tabcontent() {
		
	?>
	<?php settings_errors(); ?>
		<div class="lac-results"></div>
		<div class="wrap">
		 	<form method="post" action="options.php" id="lac-connection-form">
			    <?php settings_fields( 'lac-site-settings' ); ?>
			    <?php do_settings_sections( 'lac-site-settings' ); ?>
		    	<?php // submit_button(); ?>
			</form>
		 </div>
	<?php
	}

	public function generaloptions_admin_tabcontent() {
		settings_errors();
	?>
		<div class="wrap">
		 	<form method="post" action="options.php">
			    <?php settings_fields( 'lac-general-settings' ); ?>
			    <?php do_settings_sections( 'lac-general-settings' ); ?>
		    	<?php submit_button(); ?>
			</form>
		 </div>
	<?php
	}

	/**
	 * Import selective courses admin tab content.
	 *
	 * @return void
	 */
	public function importcourses_admin_tabcontent() {
		settings_errors();
	?>
		<div class="wrap">
		 	<!-- <h2> <?php echo __('Selective Courses import', 'lmsace-connect'); ?> </h2> -->
			<div class="import-courses">
				<h2> <?php echo __('Selective courses import', LAC_TEXTDOMAIN); ?> </h2>
				<p> <?php echo __('Select the courses in the table and click the button "Start import courses" in the bottom to start the courses import ', LAC_TEXTDOMAIN); ?> </p>
				<p> <?php echo sprintf( __('If you try to import more than %s courses in single import, LMSACE Connect will import the courses in background.', LAC_TEXTDOMAIN), LACONN_IMPORT_LIMIT); ?> </p>
				<?php $this->import_courses_list(); ?>
			</div>
		 	<form method="post" action="options.php">
			    <?php settings_fields( 'lac-import-settings' ); ?>
			    <?php do_settings_sections( 'lac-import-settings' ); ?>
			</form>
		</div>
	<?php
	}

	/**
	 * Connection settings validation data save check its connected.
	 *
	 * @param array $data
	 * @return array $data
	 */
	public function lac_connection_settings_validate( $data ) {
		global $LACONN;
		if (!empty($data) && isset($data['site_url'])) {

			$result = LACONN()->get_handler('admin-request', 'admin')
				->test_connection($data['site_url'], $data['site_token']);

			if (isset($result['error']) && $result['error'] == true) {
				LACONN()->set_admin_notices('error', $result['message'], 'connection');
			} else {
				LACONN()->remove_admin_notices('connection');
			}
		} else {
			$request = $LACONN->Client->request(LACONN::services('get_user_roles'), array(), false);
			if (!empty($request)) {
				$exception = $LACONN->Client->hasException($request, true, false) ||
					!$LACONN->Client->is_valid_response($request['response_code']);
				$this->roles = (array) $LACONN->Client->retrieve_result($request, true, false);
				return $exception;
			}
			return false;
		}
		return $data;
	}

	/**
	 * Register LMSACE Connect settings page.
	 *
	 * @return void
	 */
	public function register_lac_settings() {

		// Site import settings.
		register_setting( 'lac-import-settings', 'lac_import_settings' );

		// Site general settings.
		register_setting( 'lac-general-settings', 'lac_general_settings' );

		// Site connection settings.
		register_setting( 'lac-site-settings', 'lac_connection_settings', [$this, 'lac_connection_settings_validate'] );

		// Import course tab fields.
		add_settings_section(
	        'import_settings_section',
	        '',
	        array($this, 'section_import_settings'),
	        'lac-import-settings'
	    );

		// Import selected courses - hidden field
	    add_settings_field(
	        'lac_courses',
	        '',
	        array($this, 'course_selector'),
	        'lac-import-settings',
	        'import_settings_section'
	    );

		// Import options like create category, make draft.
	    add_settings_field(
	        'lac_import_options',
	        __( 'Import options', LAC_TEXTDOMAIN ),
	        array($this, 'course_import_options'),
	        'lac-import-settings',
	        'import_settings_section'
	    );
		// start import button.
	    add_settings_field(
	        'import_button',
	        '',
	        array($this, 'start_import_btn'),
	        'lac-import-settings',
	        'import_settings_section'
	    );

	    /* Site connection details */
	    add_settings_section(
	        'connection_settings_section',
	        __( 'LMS Connection Setup', LAC_TEXTDOMAIN ),
	        array($this, 'section_connection_settings'),
	        'lac-site-settings'
	    );
		// Site Connection URL.
	    add_settings_field(
	        'site_url',
	        __( 'Moodle LMS Site URL', LAC_TEXTDOMAIN ),
	        array($this, 'siteUrl'),
	        'lac-site-settings',
	        'connection_settings_section'
	    );
		//  Moodle token to connect.
	    add_settings_field(
	        'site_token',
	        __( 'Moodle LMS Access Token', LAC_TEXTDOMAIN ),
	       array($this, 'siteToken'),
	        'lac-site-settings',
	        'connection_settings_section'
	    );

		// Test connection button.
		add_settings_field(
	        'test_connection',
	        '',
	        array($this, 'test_connection'),
			'lac-site-settings',
	        'connection_settings_section'
	    );

		// Import selected courses - hidden field
	    add_settings_section(
	        'lac_general_options',
			__('General options', 'lmsace-connect'),
	        array($this, 'general_config_section'),
	        'lac-general-settings'
	    );
		// General config section heading.
		/* add_settings_field(
	        'general_config_section',
	        __('General settings', 'lmsace-connect'),
	        array($this, 'general_config_section'),
			'lac-site-settings',
	        'connection_settings_section'
	    ); */
		// User order refund method in moodle enrollment.
	    add_settings_field(
	        'refund_suspend',
	        __( 'User enrolment status on Order Refund/Cancellation', 'lmsace-connect' ),
	        array($this, 'refund_suspend'),
	        'lac-general-settings',
	        'lac_general_options'
	    );
		// Customer role in moodle course. - default student.
		add_settings_field(
	        'student_role',
	        __( 'Select a role for the participants in Moodle LMS', 'lmsace-connect' ),
	        array($this, 'student_role_config'),
	        'lac-general-settings',
	        'lac_general_options'
	    );

		LACONN()->is_setup_completed();
	}

	/**
	 * General section heading.
	 *
	 * @return void
	 */
	public function general_config_section() {
	?>
		<div class="general-config">
			<!-- <h3>General settings</h3> -->
			<!-- <p> Select the below options to setup LMSACE Connect default behaviours</p> -->
		</div>
	<?php
	}

	/**
	 * Section import settings description.
	 *
	 * @return void
	 */
	public function section_import_settings() {
		echo 'Select any of the options to import the courses as product.';
	}

	/**
	 * Connection section setting descriptions.
	 *
	 * @return void
	 */
	public function section_connection_settings() {
		echo 'Details to connect your Moodle LMS site with WooCommerce using webservice.';
	}

	/**
	 * Site URL config field.
	 *
	 * @return void
	 */
	public function siteUrl() {
		$options = get_option( 'lac_connection_settings' );
    ?>
	    <input type="text" name='lac_connection_settings[site_url]' class="form" value="<?php echo isset($options['site_url']) ? $options['site_url'] : ''; ?>" >
    <?php
	}

	/**
	 * Site token field.
	 *
	 * @return void
	 */
	public function siteToken() {
		$options = get_option( 'lac_connection_settings' );
    ?>
	    <input type="text" name='lac_connection_settings[site_token]' class="form" value="<?php echo isset($options['site_token']) ? $options['site_token'] : ''; ?>" >
		<p class="text-dimmed">You can read how to generate token in <a href="https://lmsace.com/docs/lmsace-connect#generate-webservice"> Creating Webservice Token </a> </p>
	<?php
	}

	/**
	 * Test connection button content.
	 *
	 * @return void
	 */
	public function test_connection() {
		$exception = $this->lac_connection_settings_validate([]);
		if (empty($exception)) {
			$connection = '<span class="connection-success"> '.__(' Connected successfully ', 'lmsace-connect').'</span>';
		} else if (!empty($this->options['site_token'])) {
			$connection = '<span class="connection-error"> '. __('Connection Failed', 'lmsace-connect').'</span>';
		}
	?>
		<p class="test-connection">
			<input type="button" class="button secondary" id="test_connection"  value="<?php echo __('Connect', 'lmsace-connect'); ?>" >
			<span class="result"> <?php echo isset($connection) ? $connection : ''; ?> </span>
		</p>
	<?php
	}

	/**
	 * Refund status seleector field.
	 *
	 * @return void
	 */
	public function refund_suspend() {
		$options = get_option( 'lac_general_settings' );
		?>
		<select name='lac_general_settings[refund_suspend]' class="form" >
			<option value="<?php echo LACONN_SUSPEND;?>" <?php echo (isset($options['refund_suspend']) && $options['refund_suspend'] == LACONN_SUSPEND)  ? 'selected':'';?> >
				<?php echo  __('Suspend', LAC_TEXTDOMAIN);?>
			</option>
			<option value="<?php echo LACONN_UNENROL;?>" <?php echo (isset($options['refund_suspend']) && $options['refund_suspend'] == LACONN_UNENROL)  ? 'selected':'';?> >
				<?php echo  __('Unenrol', LAC_TEXTDOMAIN);?>
			</option>
		<?php
	}

	/**
	 * Student role config field.
	 *
	 * @return void
	 */
	public function student_role_config() {
		global $LACONN;
		$options = get_option( 'lac_general_settings' );

		if (!isset($this->roles)) {
			$request = $LACONN->Client->request(LACONN::services('get_user_roles'), array(), false);
			$roles = (array) $LACONN->Client->retrieve_result($request, true, false);
		} else {
			$roles = $this->roles;
		}

		if (!isset($options['student_role']) || (isset($options['student_role']) && empty($options['student_role'])) ) {
			$options['student_role'] = 5;
		}
		?>
		<select name='lac_general_settings[student_role]' class="form" >
			<?php foreach (array_reverse($roles) as $role) :
				if (isset($role->name)) {
				?>
				<option value="<?php echo $role->id;?>" <?php echo (isset($options['student_role']) && ($role->id == $options['student_role']) ) ? 'selected="selected"' : ''; ?> > <?php echo $role->name; ?></option>;
			<?php  }
			endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Start import button content.
	 *
	 * @return void
	 */
	public function start_import_btn() {
		?>
		<button type="button" name="start_import" class="button primary"> Start import courses </button>
		<span class="result"> </span>
		<?php
	}

	/**
	 * Selective import course list table loaded via datatable ajax.
	 *
	 * @return void
	 */
	public function import_courses_list() {

		?>
		<table id="import-course-list" class="striped" style="width:100%;">
			<thead>
				<th><input type="checkbox" name="import_select_all" /></th>
				<th>Course ID</th>
				<th >Course name</th>
				<th >Course short name</th>
				<th>Category</th>
				<th>Course IDNumber</th>
				<th >Visiblity</th>

			</thead>
			<tbody></tbody>
		</table>
		<?php
	}

	/**
	 * Selected import course input hidden field - updated via datatable select method.
	 *
	 * @return void
	 */
	public function course_selector() {
	?>
		<input type="hidden" name="lac_courses" class="form" />
	<?php
	}

	/**
	 * Coruse import options settings content.
	 *
	 * @return void
	 */
	public function course_import_options() {
		$options = get_option( 'lac_import_settings' );
    ?>
	    <p>
	    	<input type="checkbox" name="lac_import_settings[import_options][]" value="course" checked disabled>
	    	<?php echo __('Import selected courses as WooCommerce product.');  ?>
		</p>
		<p>
	    	<input type="checkbox" name="lac_import_settings[import_options][]" value="course_draft" >
	    	<?php echo __('Import selected courses as product and save as draft.'); ?>
	    </p>
		<p>
	    	<input type="checkbox" name="lac_import_settings[import_options][]" value="update_existing" >
	    	<?php echo __('Update the existing linked product data with current course content.'); ?>
	    </p>
	    <p>
	    	<input type="checkbox" name="lac_import_settings[import_options][]" value="course_category" >
	    	<?php echo __('Import selected courses with its category.'); ?>
		</p>
    <?php
	}
}

