<<<<<<< HEAD
<?php
/**
 * Plugin Name: LMSACE Connect - WooCommerce Moodle™ LMS Integration
 * Plugin URI: http://lmsace.com/product/lmsace-connect
 * Description: This plugin connects the Moodle™ LMS + WooCommerce. Helps course creators to sell their Moodle™ LMS courses via WooCommerce.
 * Version: 3.0
 * Author: LMSACE
 * Author URI: https://www.lmsace.com/
 * Requires at least: 4.6+
 * Tested up to: 6.3.8
 * Requires PHP: 5.6
 *
 * WC requires at least: 3.0
 * WC tested up to: 9.8.5
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Denied the file exection, If this file called directly.
if ( ! defined( 'WPINC' ) ) {
    die( 'No direct Access!' );
}

if ( ! defined( 'LAC_PLUGIN_FILE' ) ) {
	define( 'LAC_PLUGIN_FILE', __FILE__ );
}

if ( !class_exists('LACONN_Main') ) {

	// Initialize LACONN components.
	// Contains all the functional parts inclusion.
	require_once( __DIR__ .'/includes/class-lac.php' );

	// Load and translate language content.
	function lac_load_textdomain() {
		load_plugin_textdomain( 'lmsace-connect', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
	add_action('plugins_loaded', 'lac_load_textdomain');

	/**
	 * Plugins admin settings.
	 *
	 * @param array $links
	 * @return void
	 */
	function lmsace_connect_settings_link($links) {
		$connection = admin_url().'admin.php?page=lac-connection-options';
		$settings_link = '<a href="'.$connection.'">'.esc_html( __('Settings', 'lmsace-connect') ).'</a>';
		array_unshift($links, $settings_link);
		return $links;
	}
	$plugin = plugin_basename( __FILE__ );
	add_filter( "plugin_action_links_$plugin", 'lmsace_connect_settings_link' );

	/**
	 * Create log files and folders to store the logs.
	 *
	 * @return void
	 */
	function install_lmsace_lac() {
		if( !class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			LACONN()->is_woocommerce_installed( true );
		}
		LACONN_Log::create_files();
	}
	register_activation_hook( __FILE__, 'install_lmsace_lac');

	/**
	 * Format the strings returned from the Moodle™.
	 *
	 * @param string $text
	 * @param string $lang
	 * @return string
	 */
	function lac_format_string( $text, $lang='en' ) {
		$return = '';
		$document = new DOMDocument();
		$document->loadHTML($text);
		$xpath = new DOMXpath($document);
		$elems = $xpath->query('//span[contains(@lang, "'.$lang.'")]');

		if (!empty($elems)) {
			foreach ($elems as $elem) {
				$return .= $elem->nodeValue;
			}
		}

		return ($return) ? $return : $text;
	}

	// Delete the plugin data when the plugin was deleted.
	if (function_exists('register_uninstall_hook')) {
    	register_uninstall_hook(__FILE__, 'uninstall_lmsace_lac');
	}

	/**
	 * Remove the LACONN plugin data from Wordpress during on the plugin deletion.
	 *
	 * @return void
	 */
	function uninstall_lmsace_lac() {
		$options = array(
			'lac_connection_settings',
			'lac_import_settings',
			'lac_general_settings',
		);

		foreach ($options as $optionname) {
			delete_option($optionname);
		}
	}

	/**
	 * Create Main class object.
	 *
	 * @return LACONN object
	 */
	function LACONN() {
		// Checks if there's any open session and close it for security issues
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_write_close();
		}

		// Create the LACONN_Main
		return LACONN::instance();
	}

	// Initialize the plugin intial function to register actions.
	global $LACONN;
	$LACONN = LACONN();
	$LACONN->init();

	// TODO: Update the course product meta to array.
}


=======
<?php
/**
 * Plugin Name: LMSACE Connect - WooCommerce Moodle™ LMS Integration
 * Plugin URI: http://lmsace.com/product/lmsace-connect
 * Description: This plugin connects the Moodle™ LMS + WooCommerce. Helps course creators to sell their Moodle™ LMS courses via WooCommerce.
 * Version: 2.0
 * Author: LMSACE
 * Author URI: https://www.lmsace.com/
 * Requires at least: 4.6+
 * Tested up to: 6.2.2
 * Requires PHP: 5.6
 *
 * WC requires at least: 3.0
 * WC tested up to: 7.8.0
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Denied the file exection, If this file called directly.
if ( ! defined( 'WPINC' ) ) {
    die( 'No direct Access!' );
}

if ( ! defined( 'LAC_PLUGIN_FILE' ) ) {
	define( 'LAC_PLUGIN_FILE', __FILE__ );
}

if ( !class_exists('LACONN_Main') ) {

	// Initialize LACONN components.
	// Contains all the functional parts inclusion.
	require_once( __DIR__ .'/includes/class-lac.php' );

	// Load and translate language content.
	function lac_load_textdomain() {
		load_plugin_textdomain( 'lmsace-connect', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
	add_action('plugins_loaded', 'lac_load_textdomain');

	/**
	 * Plugins admin settings.
	 *
	 * @param array $links
	 * @return void
	 */
	function lmsace_connect_settings_link($links) {
		$connection = admin_url().'admin.php?page=lac-connection-options';
		$settings_link = '<a href="'.$connection.'">'.esc_html( __('Settings', 'lmsace-connect') ).'</a>';
		array_unshift($links, $settings_link);
		return $links;
	}
	$plugin = plugin_basename( __FILE__ );
	add_filter( "plugin_action_links_$plugin", 'lmsace_connect_settings_link' );

	/**
	 * Create log files and folders to store the logs.
	 *
	 * @return void
	 */
	function install_lmsace_lac() {
		if( !class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			LACONN()->is_woocommerce_installed( true );
		}
		LACONN_Log::create_files();
	}
	register_activation_hook( __FILE__, 'install_lmsace_lac');

	/**
	 * Format the strings returned from the Moodle™.
	 *
	 * @param string $text
	 * @param string $lang
	 * @return string
	 */
	function lac_format_string( $text, $lang='en' ) {
		$return = '';
		$document = new DOMDocument();
		$document->loadHTML($text);
		$xpath = new DOMXpath($document);
		$elems = $xpath->query('//span[contains(@lang, "'.$lang.'")]');

		if (!empty($elems)) {
			foreach ($elems as $elem) {
				$return .= $elem->nodeValue;
			}
		}

		return ($return) ? $return : $text;
	}

	// Delete the plugin data when the plugin was deleted.
	if (function_exists('register_uninstall_hook')) {
    	register_uninstall_hook(__FILE__, 'uninstall_lmsace_lac');
	}

	/**
	 * Remove the LACONN plugin data from Wordpress during on the plugin deletion.
	 *
	 * @return void
	 */
	function uninstall_lmsace_lac() {
		$options = array(
			'lac_connection_settings',
			'lac_import_settings',
			'lac_general_settings',
		);

		foreach ($options as $optionname) {
			delete_option($optionname);
		}
	}

	/**
	 * Create Main class object.
	 *
	 * @return LACONN_Main object
	 */
	function LACONN() {
		return LACONN::instance();
	}



	function print_object($data) {
		echo '<pre>';
		print_r($data);
		echo '</pre>';
	}

	// Initialize the plugin intial function to register actions.
	global $LACONN;
	$LACONN = LACONN();
	$LACONN->init();

	// TODO: Update the course product meta to array.

	// HPOS compatibility declaration for WooCommerce 7.1+
	add_action( 'before_woocommerce_init', function() {
	    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
	        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	    }
	} );
}


>>>>>>> 55d6727 (Added woocommerce HPOS support and improved the sso module)
