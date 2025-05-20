<?php
/**
 * Helpher - Admin backend progress request via JS.
 *
 * @package lmsace-connect
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin request for testing connection and import courses.
 */
class LACONN_Admin_request extends LACONN_Main {

	/**
	 * LMSACE Connect - Course class instance.
	 *
	 * @var instance
	 */
	public $courseSync;

	/**
	 * Call the main class contructor to prepare basic data.
	 */
	function __construct() {
		parent::__construct();
	}

	/**
	 * Test the moodle connection is working with given token and site url.
	 *
	 * @param string $site_url Moodle LMS Site url to connect.
	 * @param mixed $site_token Token generated in Moodle
	 * @return void $result Site connection status.
	 */
	public function test_connection($site_url, $site_token) {
		global $LACONN;

		$response = $LACONN->Client->request(LACONN::services('get_user_roles'), array(), false, $site_url, $site_token);

		if ($LACONN->Client->is_valid_response($response['response_code']) ) {
			if ($LACONN->Client->hasException($response)) {
				$error = true;
				$message = $LACONN->Client->responseBody($response)->message;
			} else {
				$error = false;
				$message = __('<span class="connection-success"> Connection created successfully! Start to import the courses. </span>', LAC_TEXTDOMAIN);
			}
			$result = array('error' => $error, 'message' => $message, 'response_body' => $response['response_body'] );
		} else {
			$result = $LACONN->Client->getResponseError($response);
		}
		return $result;
	}

	/**
	 * Import selected courses as woocommerce product.
	 * courses like 2,3,4,5. import_options= ['course_category', 'make_draft', 'update_existing']
	 *
	 * @param array $import_option
	 * @param string $courses
	 * @return void
	 */
	public function import_courses($import_option = array(), $courses=null ) {
		global $LACONN;

		if (!empty($import_option)) {
			// create the course sync class object.
			$this->courseSync = $LACONN->Course;
			$this->client = $LACONN->Client;

			// Check need to make the course as draft.
			$make_draft = ( in_array( 'course_draft', $import_option ) ) ? true : false;

			// Check need to make the course as draft.
			$update_existing = ( in_array( 'update_existing', $import_option ) ) ? true : false;

			/* Import Categories */
			$assign_category = in_array( 'course_category', $import_option) ? true : false;

			// $others = in_array( 'other', $import_option) ? $import_option['other'] : [];

			$selectedcourses = explode(',', $courses);

			$service = 'get_courses_by_field';

			$service = apply_filters('lmsace_connect_course_import_service', $service, $import_option);

			if ( !empty($courses) && count($selectedcourses) < LACONN_IMPORT_LIMIT) {
				/* Retrive Courses from Moodle  */
				$courses = (array) $this->client->request(LACONN::services($service),
					array(
						'field' => 'ids',
						'value' => $courses
					)
				);

				if (!empty($courses) && isset($courses['courses'])) {
					$courses = $courses['courses'];

					// Create imported courses as product on WP.
					$status = $this->courseSync->create_courses( $courses, $assign_category, $make_draft, $update_existing, $import_option );
					if (isset($status['response_body'])) {
						$counts = $status['response_body'];
						$count = (count($counts['created'])) ? count($counts['created']).' courses created, ' : ' No courses created, ';
						$count .= (count($counts['updated'])) ? count($counts['updated']).' courses updated, ' : ' No courses updated, ';
						$count .= (count($counts['existing'])) ? count($counts['existing']).' courses exists. ' : '';					
					}

					$result = array('error' => false, 'message' => '<span class="connection-success">'.
						sprintf( esc_html( __( 'Courses import completed - %s ', LAC_TEXTDOMAIN ) ), $count).'</span>'
					);
				} else {
					$result = array('error' => true, 'message' => '<span class="connection-error">'. esc_html(__( 'Courses not found on connected LMS site', LAC_TEXTDOMAIN )).'</span>');
				}

			} else {
				/* Retrive Courses from Moodle  */
				$count = $this->client->request( LACONN::services('get_courses_count'), array());
				if (!empty($count)) {
					$split = array_chunk($selectedcourses, LACONN_IMPORT_LIMIT);
					$this->courseSync->set_schedule_course_import( $split, $assign_category, $make_draft, $update_existing, $import_option );
					$message = esc_html( __('Courses import process running in background. Please staty logged in.', LAC_TEXTDOMAIN));
					$result = array('error' => false, 'message' => '<span class="connection-info">'.$message.'</span>' );
					$LACONN->set_admin_notices('info', $message, 'import', true);
				}
			}

		}
		return isset($result) ? $result : [];
	}

	/**
	 * Fetch available courses as list from moodle to datatable.
	 *
	 * @param int $from
	 * @param int $limit
	 * @param string $search
	 * @return void
	 */
	public function get_courses_list($from, $limit, $search='') {
		global $LACONN;
		$data = $LACONN->Course->get_courses_import_table($from, $limit);
		wp_send_json(['data' => $data]);
	}
}
