<?php
/**
 * LMSACE Connect Module
 *
 * ModuleName: Single sign on (sso)
 * Description: This module create connection between moodle and wordpress, Creates user sesion in moodle when the user logged in Wordpress.
 * Version: 1.0
 *
 * @package LMSACE Connect
 * @subpackage SSO
 * @copyright  2023 LMSACE DEV TEAM <info@lmsace.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Denied the file exection, If this file called directly.
if ( ! defined( 'WPINC' ) ) {
    die( 'No direct Access!' );
}

if ( ! defined( 'LACPRO_PLUGIN_FILE' ) ) {
	define( 'LACPRO_PLUGIN_FILE', __FILE__ );
}

require_once( __DIR__ .'/includes/class-lacsso.php' );

/**
 * Create Main class object.
 *
 * @return LACONN_Main object
 */
function LACONN_MOD_SSO() {
    return LACONNMOD_SSO::instance();
}

// Initialize the plugin intial function to register actions.
global $LACONN;

$LACONN_MOD_SSO = LACONN_MOD_SSO();
$LACONN_MOD_SSO->init();
