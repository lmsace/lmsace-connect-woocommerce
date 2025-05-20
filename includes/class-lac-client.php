<?php
/**
 * Helper - Client api connector.
 *
 * @package lmsace-connect
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handle the connection between Moodle and WordPress.
 */
class LACONN_Client {

	/**
	 * Client class instance object.
	 *
	 * @var object
	 */
	public static $instance;

	/**
	 * Moodle LMS Site URL.
	 *
	 * @var string
	 */
	public $site_url;

	/**
	 * Token to connect with moodle LMS.
	 *
	 * @var mixed Alphanum
	 */
	public $site_token;

	/**
	 * constructor.
	 */
	function __construct() {
		// Get plugins options list.
		$options = get_option( LACONN_SETTINGS );
		// Connected LMS site URL.
		$this->site_url = isset($options['site_url']) ? $options['site_url'] : '';
		// Connected LMS site access token.
		$this->site_token = isset($options['site_token']) ? $options['site_token'] : '';
 	}

	/**
	 * Returns an instance of the plugin object
	 *
	 * @return object LACONN Main instance
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof LACONN_Client ) ) {
			self::$instance = new LACONN_Client;
		}
		return self::$instance;
	}

	/**
	 * Test the request response code is valid or not.
	 *
	 * @param string|int $code Respons code returned from moodle webservice request.
	 * @return bool
	 */
 	public function is_valid_response($code) {
 		// List of valid responses code.
		$validResponse = array(200, 303, 300 );
		return (in_array($code, $validResponse)) ? true : false;
	}

	/**
	 * Check is reponse contains any exceptions.
	 *
	 * @param array $response
	 * @return bool
	 */
	public function hasException($response) {
		if (isset($response['response_body'])) {
			// Decode the response body to check the LMS returns any webservice exceptions.
			$body = json_decode($response['response_body']);
			if (isset($body->exception) && !empty($body->exception) )  {
				return $body->message; // Return the exception from the LMS Webservice.
			}
		}
		return false;
	}

	/**
	 * Get response error.
	 *
	 * @param array $response
	 * @return array status of reponse, error true or false with message.
	 */
	public function getResponseError($response) {
		global $LACONN;
		if (isset($response['response_code'])) {
			// Define the Not found error for the 404 error codes.
			if ($response['response_code'] == 404) {
				$response['response_message'] = esc_html( __(' URL Not Found, Please check your url', 'lmsace-connect'));
			} else if ($response['response_code'] == 403) {
				$response['response_message'] = wp_kses( __(' Forbidden. <ul> <li> Please check your webservice settings on Your LMS </li> </ul>', 'lmsace-connect'), $LACONN->allowed_tags() );
			}
			// Returns the request error.
			return array( 'error' => true, 'message' => $response['response_message'] );
		}
		return array( 'error' => false, 'message' => '' );
	}

	/**
	 * Get reponse body content
	 *
	 * @param array $response
	 * @return array|bool
	 */
	public function responseBody($response) {

		if (isset($response['response_body'])) {
			// Decode the LMS response body to use further needs.
			return json_decode($response['response_body']);
		}
		return false;
	}

	/**
	 * Send request to moodle wbserivce.
	 *
	 * @param string $service webservice function name.
	 * @param array $data Data for webservice.
	 * @param bool $fetchResult Retrive the response body and return the result.
	 * @param string $site_url Site URL if need to connect with others.
	 * @param string $site_token Site token for connection.
	 * @return array response.
	 */
	public function request($service, $data = array(), $fetchResult=true, $site_url='', $site_token='') {

		// Argumentes passed to the LMS webservice.
		$args = array( 'body' => $data );
		// Connected LMS site URL.
		$site_url = (!empty($site_url)) ? rtrim($site_url, '/') : $this->site_url;
		// Connected LMS site access token.
		$site_token = (!empty($site_token)) ? $site_token : $this->site_token;

		// URL parameters for LMS webservice url access tokens and webservice function.
		$urlParams = array(
			'wstoken' => $site_token,
			'moodlewsrestformat' => 'json',
			'wsfunction' => $service
		);
		// Final Webservice url to Connected the LMS.
		$serviceUrl =  $site_url.'/webservice/rest/server.php?'.http_build_query($urlParams);
		// Wordpress cURL post metod to send or request data.
		$response = wp_remote_post($serviceUrl, $args );

		if ( is_wp_error($response) ) {
			// Returns the error response to display on User interface.
			error_log($response->get_error_message());
			LACONN()->logger()->add( 'remote-request-error', ' Request end with error - message: '. $response->get_error_message());

			return array('response_code' => $response->get_error_code(), 'response_message' => $response->get_error_message() );
		} else {
			$code = wp_remote_retrieve_response_code($response);
			if (!$this->is_valid_response($code)) {
				return false;
			}
			// Result of the cURL request return response.
			$result = array(
				'response_code'  => wp_remote_retrieve_response_code($response),
				'response_body' =>  wp_remote_retrieve_body($response),
				'response_message' => wp_remote_retrieve_response_message($response)
			);
			// Check the called function wants the Curl response. else returns the specfic response content.
			return ($fetchResult) ? $this->retrieve_result($result) : $result;
		}

	}

	/**
	 * Retrive the result from the response.
	 *
	 * @param array $response Webservice request response.
	 * @param boolean $body Retrive the body.
	 * @param boolean $notice if error send notice.
	 * @return array|string
	 */
	public function retrieve_result(array $response, $body=true, $notice=true) {

		// Check the response has the valid response code.
		if ( $this->is_valid_response($response['response_code']) ) {

			// Check the response has the moodle webservice exceptions.
			if ( $this->hasException($response) ) {
				// Result of the moodle exception for the request webservice.
				$body = $this->responseBody($response);
				$result = array(
					'error' => true,
					'message' => $body->errorcode.': '.$body->message,
					'response_body' => false,
					// Exceptions message from the LMS web service.
				);

				if ($notice) {
					LACONN()->set_admin_notices('error', $result['message'], 'wsexception');
				}

			} else {
				// Result from the LMS webservice without any exceptions.
				$result = array(
					'error' => false,
					'message' => '',
					'response_body' => $this->responseBody($response)
				);
			}
		} else {
			// Result of the CURL request error.
			$result = $this->getResponseError($response);
			if (isset($result['response_message']) && $result['response_message'] != '') {
				error_log($result['response_message']);
				LACONN()->set_admin_notices('error', $result['response_message']);
			}
		}


		if ($body) {
			return (isset($result['response_body']) ? $result['response_body'] : '' );
		}

		return $result;
	}



}
