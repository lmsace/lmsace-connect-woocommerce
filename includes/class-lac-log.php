<?php
/**
 * Log helper - stores the process log in wp-uploads as text file..
 *
 * @package lmsace-connect
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create log file and store the progress as txt file.
 */
class LACONN_Log extends LACONN_Main {

	/**
	 * Log type handler.
	 *
	 * @var array
	 */
    public $handles = array();

	/**
	 * Logger instance object
	 *
	 * @var LACONN_Log
	 */
    public static $instance;

	/**
	 * Constructor.
	 */
    public function __construct() {
        parent::__construct();
        $wp_upload_dir = wp_upload_dir();
        $this->logpath = $wp_upload_dir['basedir']. '/lac-logs/';
    }

    /**
	 * Returns an instance of the plugin object
	 *
	 * @return LACONN_Log Main instance
	 *
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof LACONN_Log ) ) {
			self::$instance = new LACONN_Log;
		}
		return self::$instance;
	}

	/**
	 * Remove the handlers once its completed.
	 */
    public function __destruct() {
        foreach ( $this->handles as $handle ) {
			if ( is_string( $handle ) ) {
				fclose( escapeshellarg( $handle ) );
			}
		}
    }


    /**
	 * Create files/directories.
	 */
	public static function create_files() {

		$files = array(
			array(
				'base'    => LACONN_LOG_DIR,
				'file'    => '.htaccess',
				'content' => 'deny from all',
			),
			array(
				'base'    => LACONN_LOG_DIR,
				'file'    => 'index.html',
				'content' => '',
			)
		);

		foreach ( $files as $file ) {
			if ( wp_mkdir_p( $file['base'] ) && ! file_exists( trailingslashit( $file['base'] ) . $file['file'] ) ) {
				$file_handle = @fopen( trailingslashit( $file['base'] ) . $file['file'], 'w' );
				if ( $file_handle ) {
					fwrite( $file_handle, $file['content'] );
					fclose( $file_handle );
				}
			}
		}
	}

	/**
	 * Get filepath for the log method error, course, request.
	 *
	 * @param string $method
	 * @return string
	 */
    public function get_filepath( $method ) {
        $filename = $method.'-'.sanitize_file_name( wp_hash( $method ) ).'.log';
        return $this->logpath.$filename;
    }

	/**
	 * Create handler for the log method.
	 *
	 * @param string $method
	 * @return bool
	 */
    public function create( $method ) {
        if ( isset($this->handles[$method])) {
            return true;
        }
        $this->handles[ $method ] = fopen( $this->get_filepath( $method ), 'a' );
        return true;
    }

	/**
	 * Check log file is writable.
	 *
	 * @param string $method
	 * @return bool result
	 */
    public function is_writable( $method ) {
        $file = $this->get_filepath( $method );
        return ( is_file($file) && is_writable($file) ) ? true : false;
    }

	/**
	 * Add the log to the method handeler file.
	 *
	 * @param string $method course ,error, order, request-error
	 * @param string $message
	 * @return void
	 */
    public function add( $method, $message ) {

        if ( $this->create( $method ) && $this->is_writable( $method ) ) {
            $time = date_i18n( 'm-d-Y @ H:i:s -' );
            fwrite( $this->handles[$method], $time . ': '. sanitize_text_field( $message ) . " \n " );
        }
    }
}
