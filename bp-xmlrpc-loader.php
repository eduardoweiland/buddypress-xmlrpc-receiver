<?php
/*
 Plugin Name: BuddyPress XMLRPC Receiver
 Plugin URI: http://github.com/duduweiland/buddypress-xmlrpc-receiver/
 Description: Allow remote connection via XML-RPC for BuddyPress
 Author: Eduardo Weiland
 Author URI: http://eduardoweiland.tk
 License: GNU General Public License v3 http://www.gnu.org/licenses/gpl.txt
 Version: 0.1.1
 Text Domain: bp-xmlrpc
 Network: true
*/


if ( !defined( 'BP_XMLRPC_URL' ) )
    define( 'BP_XMLRPC_URL', '/index.php?bp_xmlrpc=true' );

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
 * Load translation files.
 *
 * @return void
 */
function bp_xmlrpc_load_translation() {
    load_plugin_textdomain( 'bp-xmlrpc', false, basename( dirname( __FILE__ ) ) .'/languages/' );
}
add_action( 'plugins_loaded', 'bp_xmlrpc_load_translation' );


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

    add_options_page( __( 'BuddyPress XML-RPC', 'bp-xmlrpc' ),
                      __( 'BuddyPress XML-RPC', 'bp-xmlrpc' ),
                      'manage_options',
                      'bp-xmlrpc-settings',
                      'bp_xmlrpc_admin' );

    // set up defaults
    add_option( 'bp_xmlrpc_cap_low', 'upload_files' ); // author
}
add_action( 'admin_menu', 'bp_xmlrpc_admin_add_admin_menu' );

// Add rewrite rule and flush on plugin activation
register_activation_hook( __FILE__, 'bp_xmlrpc_activate' );
function bp_xmlrpc_activate() {
    bp_xmlrpc_rewrite();
    flush_rewrite_rules();
}
 
// Flush on plugin deactivation
register_deactivation_hook( __FILE__, 'bp_xmlrpc_deactivate' );
function bp_xmlrpc_deactivate() {
    flush_rewrite_rules();
}
 
// Create new rewrite rule
add_action( 'init', 'bp_xmlrpc_rewrite' );
function bp_xmlrpc_rewrite() {
    add_rewrite_rule( 'bpxmlrpc/?$', 'index.php?bp_xmlrpc=true', 'top' );
}

// But WordPress has a whitelist of variables it allows, so we must put it on that list
add_action( 'query_vars', 'bp_xmlrpc_query_vars' );
function bp_xmlrpc_query_vars( $query_vars )
{
    $query_vars[] = 'bp_xmlrpc';
    return $query_vars;
}

// If this is done, we can access it later
// This example checks very early in the process:
// if the variable is set, we include our page and stop execution after it
add_action( 'parse_request', 'bp_xmlrpc_parse_request' );
function bp_xmlrpc_parse_request( &$wp )
{
    if ( array_key_exists( 'bp_xmlrpc', $wp->query_vars ) ) {
        include( dirname( __FILE__ ) . '/bp-xmlrpc.php' );
        exit();
    }
}
?>
