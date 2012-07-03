<?php
/*
 Plugin Name: BuddyPress XMLRPC Receiver
 Plugin URI: http://github.com/duduweiland/buddypress-xmlrpc-receiver/
 Description: Allow remote XML-RPC for BuddyPress
 Author: duduweiland
 Author URI: http://github.com/duduweiland/
 License: GNU GENERAL PUBLIC LICENSE 3.0 http://www.gnu.org/licenses/gpl.txt
 Version: 0.1.0
 Text Domain: bp-xmlrpc
 Site Wide Only: true
*/

//need limit rate
//need max results (right now hardcoded at 35 per request)
//oauth it?
//more rpc commands?


if ( !defined( 'BP_XMLRPC_URL' ) )
    define( 'BP_XMLRPC_URL', WP_PLUGIN_URL .'/'. basename( dirname( __FILE__ ) ) .'/bp-xmlrpc.php' );

/* Only load code that needs BuddyPress to run once BP is loaded and initialized. */
function bp_xmlrpc_init() {

    require_once( dirname( __FILE__ ) . '/includes/bp-xmlrpc-functions.php' );
    require( dirname( __FILE__ ) . '/includes/bp-xmlrpc-profile.php' );

}
add_action( 'bp_core_loaded', 'bp_xmlrpc_init' );


//add admin_menu page
function bp_xmlrpc_admin_add_admin_menu() {
    global $bp;

    if ( !is_super_admin() )
        return false;

    //Add the component's administration tab under the "BuddyPress" menu for site administrators
    require ( dirname( __FILE__ ) . '/admin/bp-xmlrpc-admin.php' );

    add_submenu_page( 'bp-general-settings', __( 'XML-RPC', 'bp-xmlrpc' ), __( 'XML-RPC', 'bp-xmlrpc' ), 'manage_options', 'bp-xmlrpc-settings', 'bp_xmlrpc_admin' );

    //set up defaults
    add_option('bp_xmlrpc_cap_low', 'upload_files' ); //author
}

//loader file never works - as it doesn't hook the admin_menu
if ( defined( 'BP_VERSION' ) ) {
    add_action( 'admin_menu', 'bp_xmlrpc_admin_init' );
} else {
    add_action( 'bp_init', 'bp_xmlrpc_admin_init');
}

function bp_xmlrpc_admin_init() {
    add_action( 'admin_menu', 'bp_xmlrpc_admin_add_admin_menu', 25 );
}

?>
