<?php
/*
 Plugin Name: BuddyPress XMLRPC Receiver
 Plugin URI: http://github.com/duduweiland/buddypress-xmlrpc-receiver/
 Description: Allow remote connection via XML-RPC for BuddyPress
 Author: Eduardo Weiland
 Author URI: http://github.com/duduweiland/
 License: GNU General Public License v3 http://www.gnu.org/licenses/gpl.txt
 Version: 0.1.1
 Text Domain: bp-xmlrpc
 Network: true
*/


if ( !defined( 'BP_XMLRPC_URL' ) )
    define( 'BP_XMLRPC_URL', WP_PLUGIN_URL .'/'. basename( dirname( __FILE__ ) ) .'/bp-xmlrpc.php' );

/**
 * Load code that needs BuddyPress to run once BP is loaded and initialized.
 *
 * @return void
 */
function bp_xmlrpc_init() {

    require_once( dirname( __FILE__ ) . '/includes/bp-xmlrpc-functions.php' );
    require_once( dirname( __FILE__ ) . '/includes/bp-xmlrpc-profile.php' );

}
add_action( 'bp_core_loaded', 'bp_xmlrpc_init' );


/**
 * Load language files.
 *
 * @return void
 */
function bp_xmlrpc_load_languages() {
    load_plugin_textdomain( 'bp-xmlrpc', false, basename( dirname( __FILE__ ) ) .'/languages/' );
}
add_action('plugins_loaded', 'bp_xmlrpc_load_languages');


/**
 * Adds the options page under "Settings" menu for site administrators.
 *
 * @return void
 */
function bp_xmlrpc_admin_add_admin_menu() {
    global $bp;

    if ( !is_super_admin() )
        return false;

    // Add the component's administration tab under the "Settings" menu for site administrators
    require ( dirname( __FILE__ ) . '/admin/bp-xmlrpc-admin.php' );

    add_options_page( __( 'BuddyPress XML-RPC', 'bp-xmlrpc' ), __( 'BuddyPress XML-RPC', 'bp-xmlrpc' ), 'manage_options', 'bp-xmlrpc-settings', 'bp_xmlrpc_admin' );

    // set up defaults
    add_option('bp_xmlrpc_cap_low', 'upload_files' ); // author
}
add_action( 'admin_menu', 'bp_xmlrpc_admin_add_admin_menu' );

?>
