<?php

/**
 * Course_sync class object - Link LMS course as product on woocommerce.
 */
class LACONN_Course extends LACONN_Main {

	public static $instance;

	/**
	 * Current logged in user.
	 * @var Object
	 */
	public $user;

	function __construct() {
		parent::__construct();
		// Get current logged in user data object.
		// $this->user = wp_get_current_user();
		// Add admin class object.
		$this->admin = $this->get_handler('admin', 'admin');
		// Add woocommerce class object.
		$this->woocom = $this->get_handler('woocom');
	}

	/**
	 * Get current loggedin user data object.
	 *
	 * @return void
	 */
	public function get_user() {
		$this->user = wp_get_current_user();
	}

	/**
	 * Register WP  actions for this class instance.
	 *
	 * @return void
	 */
	public function lac_register_actions() {
		add_filter( 'cron_schedules', array( $this, 'lac_cron_definer' ) );
		add_action( 'lac_import_courses_action', array($this, 'lac_import_courses') );
		add_shortcode( 'lmsace_connect_summary', array($this, 'summary_shortcode'));
	}

	public function summary_shortcode( $atts, $content ) {
		return $content;
	}

	/**
	 * Returns an instance of the plugin object
	 *
	 * @return LACONN Main instance
	 *
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof LACONN_Course ) ) {
			self::$instance = new LACONN_Course;
		}
		return self::$instance;
	}

	/**
	 * Import the courses in background.
	 *
	 * @return void
	 */
	public function lac_import_courses() {
		global $LACONN;
		$courses = $this->get_session_data('LAC_import_selected_courses');
		$assign_category = $this->get_session_data('LAC_import_assign_category');
		$make_draft = $this->get_session_data('LAC_import_make_draft');
		$update_existing = $this->get_session_data('LAC_import_update_existing');

		if (!empty($courses)) {
			$importcourses = array_shift($courses);
			$courseids = implode(',', $importcourses);
			$LACONN->logger()->add( 'course', esc_html(__('Init course import for :'.$courseids, LAC_TEXTDOMAIN) ));

			$response = (array) $LACONN->Client->request(LACONN::services('get_courses_by_field'),
				array(
					'field' => 'ids',
					'value' => $courseids
				)
			);

			if (isset($response['courses']) && !empty($response['courses'])) {
				// Create course.
				$result = $this->create_courses($response['courses'], $assign_category, $make_draft, $update_existing );
				$this->set_session_data('LAC_import_selected_courses', $courses);
				$this->set_session_data('course_import_info', esc_html( __('Course import process running in background!', LAC_TEXTDOMAIN )) );
			}
		} else {
			$this->set_session_data('course_import_completes', esc_html( __('All courses are imported successfully!', LAC_TEXTDOMAIN) ) );
			$this->set_session_data('LAC_import_selected_courses', false);
			$this->set_session_data('course_import_info', false);
			wp_clear_scheduled_hook('lac_import_courses_action');
		}

	}

	/**
	 * Setup the cron definition - adding additional schedule time out..
	 *
	 * @param array $schedules
	 * @return void
	 */
	function lac_cron_definer($schedules) {
		$schedules['minutes'] = array(
			'interval'=> 30,
			'display'=> esc_html( __('Once Every 30 seconds') )
		);
		return $schedules;
	}
	/**
	 * Set background import courses notices.
	 *
	 * @return void
	 */
	public function set_import_notices() {
		global $LACONN;

		if ( $data = $this->get_session_data('course_import_info') ) {
			$LACONN->set_admin_notices('info', $data, 'import', true);
			$this->set_session_data('course_import_info', false);
		}

		if ( $data = $this->get_session_data('course_import_completes') ) {
			$LACONN->set_admin_notices( 'success', $data, 'import', true );
			$this->set_session_data('course_import_completes', false);
		}
	}

	/**
	 * Set the schedule for course import. Setup the options and selected courses in session.
	 *
	 * @param string $selectedcourses Selecteed courses.
	 * @param bool $assign_category
	 * @param bool $make_draft
	 * @param bool $update_existing
	 * @return void
	 */
	public function set_schedule_course_import( $selectedcourses, $assign_category, $make_draft, $update_existing ) {
		// print_r($selectedcourses);
		$this->set_session_data('LAC_import_selected_courses', $selectedcourses);
		$this->set_session_data('LAC_import_assign_category', $assign_category);
		$this->set_session_data('LAC_import_make_draft', $make_draft);
		$this->set_session_data('LAC_import_update_existing', $update_existing);

		wp_schedule_event(time(), 'minutes', 'lac_import_courses_action');

	}

	/**
	 * Get list of courses from the connected LMS.
	 *
	 * @return array courseslist
	 */
	public function get_courses() {
		global $LACONN;
		/* Retrive Courses from Moodle  */
		$result = $LACONN->Client->request( LACONN::services('get_courses'), array() );
		if ( $result ) {
			return $result;
		}
	}

	public function get_courses_byid( array $ids ) {
		global $LACONN;
		$courses = $LACONN->Client->request( LACONN::services('get_courses_by_field'),
			array(
				'field' => 'ids',
				'value' => implode(',', $ids)
			)
		);
		if ( isset($courses->courses) ) {
			$courses = $courses->courses;
			$updatedlist = [];
			foreach ( $courses as $course) {
				$updatedlist[$course->id] = $course;
			}
			return $updatedlist;
		}
		return [];
	}

	/**
	 * Get courses for import table.
	 *
	 * @param int $from
	 * @param int $limit
	 * @return void
	 */
	public function get_courses_import_table($from=0, $limit=0) {
		global $LACONN;

		$response = (array) $LACONN->Client->request( LACONN::services('get_limit_courses'),
			array('from' => $from, 'limit' => $limit));
		if (!isset($response['courses'])) {
			return [];
		}

		$fields = ['id', 'fullname', 'shortname', 'categoryname', 'idnumber', 'visible'];
		$filteredcourses = $this->filter_manual_courses( $response['courses'] );
		foreach ( $filteredcourses as $id => $course) {
			if ($course->id == 1) continue;
			$check = '<input type="checkbox" name="import_course" value="'.esc_attr($course->id).'"/>';
			$course->visible = ($course->visible) ? esc_html( __('Visible', LAC_TEXTDOMAIN) ) : esc_html( __('Hidden', LAC_TEXTDOMAIN) );
			$courses[] = ['select' => ''] + array_intersect_key((array) $course, array_flip($fields));
		}
		// print_r($courses);
		return isset($courses) ? $courses : [];
	}

	/**
	 * Create taxanomy on Wordpress from connected LMS categories.
	 *
	 * @param  array  $categories list of LMS categories
	 * @return null
	 */
	public function create_categories($categories = array()) {

		if (!empty($categories)) {
			foreach ($categories as $category) {
				if (!empty($category)) {
					// parent_category[wp category id] =  moodle parent category id.
					if ( $wp_category = $this->get_term_from_moodle_categoryid($category->id) ) {
						$termid = $this->update_category($category, end($wp_category));
					} else {
						$termid = $this->create_category($category);
					}

					$parent_category[$termid] = $category->parent;
				}
			}

			$this->assign_parent_category($parent_category);
		}
	}

	/**
	 * Assign child category to the parent.
	 *
	 * @param  object $parent_category
	 * @return null
	 */
	public function assign_parent_category($parent_category) {

		if ( !empty($parent_category) ) {
			foreach ( $parent_category as $term_id => $parent ) {
				$parent_term = $this->get_term_from_moodle_categoryid($parent);
				if ( !empty($parent_term) ) {
					$parent_term = end($parent_term);
					wp_update_term( $term_id, 'product_cat', ['parent' => $parent_term->term_id ] );
				}
			}
		}
	}

	/**
	 * Create taxanomy from LMS category.
	 *
	 * @param  [type] $category [description]
	 * @return [type]           [description]
	 */
	public function create_category( $category ) {

		if ( !empty($category->name) ) {
			if ( !term_exists($category->name, 'product_cat') ) {
				$term = wp_insert_term( $category->name, 'product_cat', [
					'description' => $category->description,
					'slug' => str_replace(array(' ', '-'), '_', strtolower($category->name))
					]
				);
				add_term_meta( $term['term_id'], 'lac_moodle_category_id', $category->id );

				LACONN()->logger()->add('course', 'Category created - '.$category->name.'');

				return $term['term_id'];
			} else {
				$term = get_term_by( 'name', $category->name, 'product_cat' );

				update_term_meta( $term->term_id, 'lac_moodle_category_id', $category->id );
				return $term->term_id;
			}

		}
		return false;
	}

	/**
	 * Update the category content.
	 *
	 * @param object $wp_term Wordpress term
	 * @param stdclass $md_category Moodle category object.
	 * @return bool|int term id.
	 */
	public function update_category( $wp_term, $md_category ) {

		if ( !empty($wp_term) ) {
			if ( !term_exists($md_category->name, 'product_cat') ) {
				wp_update_term($wp_term->term_id, 'product_cat', [
					'description' => $md_category->description,
					'slug' => str_replace(array(' ', '-'), '_', strtolower($md_category->name))
				]);
				return $wp_term->term_id;
			} else {
				$term = get_term_by('name', $md_category->name, 'product_cat');
				update_term_meta($term->term_id, 'lac_moodle_category_id', $md_category->id);
				return $term->term_id;
			}
		}
		return false;
	}

	/**
	 * Filter the manual enrolment enabled courses.
	 *
	 * @param array $courses
	 * @return array
	 */
	public function filter_manual_courses( $courses ) {
		if ( !empty($courses) ) {
			return array_filter( $courses, function($course) {
				if ( $course->enrollmentmethods && !empty($course->enrollmentmethods) ) {
					return (in_array('manual', $course->enrollmentmethods)) ? true : false;
				}
				return false;
			});
		}
	}

	/**
	 * Import the select courses.
	 *
	 * @param array $courseslist List of selected course object returned from API.
	 * @param bool $assign_category Assign the course to category.
	 * @param bool $make_draft Make the imported course as draft state.
	 * @param bool $update_existing Update the existing linked product.
	 * @return array result of connection
	 */
	public function create_courses($courseslist = array(), $assign_category=false, $make_draft=false, $update_existing=false, $options=[]) {
		global $LACONN;
		$this->get_user();
		if ( !empty($courseslist) ) {

			$courseslist = apply_filters( 'lmsace_connect_before_course_update', $courseslist);

			$successUpdate = $errorUpdate = $successCourses = $errorCourses = $errorExist = array();
			foreach ( $courseslist as $key => $course ) {
				if ( !empty($course) && ($course->id != 1 && $course->format != 'site') ) {
					if ($posts = $LACONN->Woocom->get_product_from_course_id($course->id)) {
						if ($update_existing) {
							foreach ($posts as $post) {
								// Update existing courses.
								if ($this->update_course($post, $course, $assign_category, $make_draft, $options) ) {
									$successUpdate[$course->id] = $course->fullname;
									LACONN()->logger()->add('course', 'Course product updated - '.$course->fullname.'');
								} else {
									$errorUpdate[$course->id] = $course->fullname;
								}
							}
						} else {
							$errorExist[$course->id] = $course->fullname;
						}

					} else {
						LACONN()->logger()->add('course', 'Assign-category - '.$assign_category);
						// Create courses.
						if ( $this->create_course($course, $assign_category, $make_draft, $options) ) {
							$successCourses[$course->id] = $course->fullname;
							LACONN()->logger()->add('course', 'Course created as product - '.$course->fullname.'');
						} else {
							$errorCourses[$course->id] = $course->fullname;
						}
					}
				}
			}

			$error = ($errorCourses || $errorUpdate) ? true : false;

			$response_body = array(
				'created'    => $successCourses,
				'updated'    => $successUpdate,
				'existing'   => $errorExist,
				'notupdated' => $errorUpdate,
				'notcreated' => $errorCourses,
			);

			return array(
				'error'         => $error ? false : true,
				'message'       => ($error) ? esc_html( __( 'Some courses not successfully imported', LAC_TEXTDOMAIN ) ) : __( 'Courses imported successfully', LAC_TEXTDOMAIN ),
				'response_body' => $response_body
			);
		}

		return array('error' => true, 'message' => esc_html( __('Courses Not Found On Connected Site', LAC_TEXTDOMAIN) ) );
	}

	/**
	 * Create course in WooCommerce product.
	 *
	 * @param object $course
	 * @param bool $assign_category
	 * @param bool $make_draft
	 * @return bool
	 */
	public function create_course($course, $assign_category=false, $make_draft=false, $options=[]) {
		global $LACONN;

		$post = array(
			'post_author' => $this->user->ID,
			'post_title' => $course->fullname,
			'post_content' => $course->summary,
			'post_status' => ($course->visible && !$make_draft) ? 'publish' : 'draft',
			'post_type' => 'product',
		);
		// Insert courses as product post.
		if ($post_id = wp_insert_post($post, true) ) {
			// Update the course summary.
			$summary = $this->admin->replace_coursesummary_images($post_id, $course);
			$postcontent = [ 'ID' => $post_id, 'post_content' => $summary ];

			wp_update_post( $postcontent, true );

			wp_set_object_terms( $post_id, 'simple', 'product_type' );

			if ($assign_category) {

				$terms = $this->get_term_from_moodle_categoryid($course->categoryid);
				if (!empty($terms) && $terms != null) {
					foreach ($terms as $key => $term) {
						$categories[] = $term->name;
					}
					wp_set_object_terms( $post_id, $categories, 'product_cat' );
				} else {
					// Retrieve the list of categories from the Moodle.
					$result = $LACONN->Client->request(LACONN::services('get_categories'), array(
						'criteria' => array([
							'key' => 'id',
							'value' => $course->categoryid
						])
					));
					// Test the result has issue and Retrieve the result from the category fetch response.
					// Check response has not have any issues.
					if (!empty($result)) {
						// Create fetched categories into wp terms.
						$this->create_categories($result);
					}

					$terms = $this->get_term_from_moodle_categoryid($course->categoryid);

					if (!empty($terms) ) {
						foreach ($terms as $key => $term) {
							$categories[] = $term->name;
						}
						wp_set_object_terms( $post_id, $categories, 'product_cat' );
					}
				}
			}

			$this->update_course_meta($post_id, $course, $make_draft);
			// Create course images into product.
			$image = $this->admin->get_course_image($course);
			$this->upload_product_image($post_id, [$image]);

			return true;
		}

		return false;
	}

	/**
	 * Update the already linked course product with current course content.
	 *
	 * @param int $post_id
	 * @param object $course
	 * @param bool $assign_category
	 * @param bool $make_draft
	 * @return void
	 */
	public function update_course($post_id, $course, $assign_category=false, $make_draft=false, $options=[]) {
		global $LACONN;
		$post = array(
			'ID' => $post_id,
			'post_author' => $this->user->ID,
			'post_title' => $course->fullname,
			'post_content' => $course->summary,
			'post_status' => ($course->visible && !$make_draft) ? 'publish' : 'draft',
			'post_type' => 'product',
		);

		$summary = $this->admin->replace_coursesummary_images($post_id, $course);
		$summary = '[lmsace_connect_summary]'.$summary.'[/lmsace_connect_summary]';


		$currentpost = get_post($post_id);
		if ($currentpost != '') {
			$postcontent = $currentpost->post_content;
			if (has_shortcode($postcontent, 'lmsace_connect_summary')) {
				$pattern = get_shortcode_regex();
				$summary = preg_replace('/'. $pattern .'/s', $summary, $postcontent);
			}
		}
		$post['post_content'] = $summary;

        // update course details in product post.
		$postid = wp_update_post($post, true);

		if ( is_wp_error($post_id) ) {
			return false;
		} else {

			wp_set_object_terms( $post_id, 'simple', 'product_type' );

			if ($assign_category) {
				$terms = $this->get_term_from_moodle_categoryid($course->categoryid);
				if (!empty($terms)) {
					foreach ($terms as $key => $term) {
						$categories[] = $term->name;
					}
					wp_set_object_terms( $post_id, $categories, 'product_cat' );
				} else {
					// Retrieve the list of categories from the Moodle.
					$result = $LACONN->Client->request(LACONN::services('get_categories'), array(
						'criteria' => array([
							'key' => 'id',
							'value' => $course->categoryid
						])
					));
					// Test the result has issue and Retrieve the result from the category fetch response.
					// Check response has not have any issues.
					if (!empty($result)) {
						// Create fetched categories into wp terms.
						$this->create_categories($result);
					}

					$terms = $this->get_term_from_moodle_categoryid($course->categoryid);
					if (!empty($terms) ) {
						foreach ($terms as $key => $term) {
							$categories[] = $term->name;
						}
						wp_set_object_terms( $post_id, $categories, 'product_cat' );
					}
				}
			}

			$this->update_course_meta($post_id, $course, $make_draft);
			// update product images.
			$image = $this->admin->get_course_image($course);
			$this->upload_product_image($post_id, [$image]);

			return true;
		}
	}

	/**
	 * Update the product meta data based on the course object.
	 *
	 * @param int $post_id
	 * @param object $course
	 * @return void
	 */
	public function update_course_meta($post_id, $course) {

	    update_post_meta( $post_id, '_product_attributes',  array('term_id' => $course->categoryid) );
	    update_post_meta( $post_id, '_sku', $course->shortname );
		update_post_meta( $post_id, '_visibility', ($course->visible) ? 'visible' : 'hide' );
		// LAConnect support multiple courses link with single product.
		$courses = array( $course->id );
		if ( ! metadata_exists('post', $post_id, LACONN_MOODLE_COURSE_ID) ) {
	    	add_post_meta( $post_id, LACONN_MOODLE_COURSE_ID, $courses);
		} else {
	    	update_post_meta( $post_id, LACONN_MOODLE_COURSE_ID, $courses);
		}

	    update_post_meta( $post_id, '_stock_status', 'instock');
	    update_post_meta( $post_id, 'total_sales', '0' );
	    update_post_meta( $post_id, '_downloadable', 'no' );
	    update_post_meta( $post_id, '_virtual', 'yes' );
	    update_post_meta( $post_id, '_regular_price', '00' );
	    update_post_meta( $post_id, '_sale_price', '' );
	    update_post_meta( $post_id, '_featured', 'no' );
	    update_post_meta( $post_id, '_sold_individually', '' );
	    update_post_meta( $post_id, '_manage_stock', 'no' );
	    update_post_meta( $post_id, '_backorders', 'no' );
	    update_post_meta( $post_id, '_stock', '' );
	}

	/**
	 * Upload the course image as product image.
	 *
	 * @param int $post_id
	 * @param array $images
	 * @param string $type
	 * @return array
	 */
	function upload_product_image($post_id, $images=array(), $type='course image') {

		if (empty($images)) return true;
		// Get the path to the upload directory.
		$wp_upload_dir = wp_upload_dir();

		foreach($images as $image) {

			$url = parse_url(urldecode($image));
			$name = basename($url['path']);
			$image_url = $image;
			$url_array = explode('/', $image);
			$image_name = $url_array[count($url_array)-1];
			$request = wp_remote_request($image_url);
			// print_object($request);
			$name = str_replace(' ', '_', $name );

			if (!isset($request['body'])) {
				return false;
			}
			$image_data = $request['body'];
			if( wp_mkdir_p( $wp_upload_dir['path'] ) ) {
				$file = $wp_upload_dir['path'] . '/' . $name;
			} else {
				$file = $wp_upload_dir['basedir'] . '/' . $name;
			}
			// Create the image file on the server
			file_put_contents( $file, $image_data );

			// Check image file type
			$wp_filetype = wp_check_filetype( $name, null );

			$attachment = array(
				'guid'=> $wp_upload_dir['url'] . '/' . basename( $name ),
				'post_mime_type' => $wp_filetype['type'],
				'post_title' => sanitize_file_name( $name ),
				'post_content' => $type,
				'post_status' => 'inherit'
			);

			$image_id = wp_insert_attachment($attachment, $file, $post_id);
			// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
			require_once( ABSPATH . 'wp-admin/includes/image.php' );

			// Generate the metadata for the attachment, and update the database record.
			$attach_data = wp_generate_attachment_metadata( $image_id, $file );


			wp_update_attachment_metadata( $image_id, $attach_data );

			if ($type == 'course_summary') {
				$updatedimages[] = wp_get_attachment_image_url( $image_id );
			} else {
				// Assign to feature image
				// And finally assign featured image to post
				set_post_thumbnail( $post_id, $image_id );
			}
		}

		return isset($updatedimages) ? $updatedimages : [];
	}
}
