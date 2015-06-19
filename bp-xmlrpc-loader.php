<?php
/*
 Plugin Name: BuddyPress XML-RPC Receiver
 Plugin URI: http://wordpress.org/extend/plugins/buddypress-xml-rpc-receiver/
 Description: Allow remote connection via XML-RPC for BuddyPress
 Author: Various
 Author URI: http://wordpress.org/extend/plugins/buddypress-xml-rpc-receiver/
 License: GNU General Public License v3 http://www.gnu.org/licenses/gpl.txt
 Version: 0.5.10
 Text Domain: bp-xmlrpc
 Network: true
*/


if ( !defined( 'BP_XMLRPC_URL' ) )
    define( 'BP_XMLRPC_URL', get_bloginfo('url').'/index.php?bp_xmlrpc=true' );

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

// add custom variable to redirect
add_action( 'query_vars', 'bp_xmlrpc_query_vars' );
function bp_xmlrpc_query_vars( $query_vars )
{
    $query_vars[] = 'bp_xmlrpc';
    $query_vars[] = 'bp_xmlrpc_redirect';
    return $query_vars;
}

// if the variable is set, we take over
add_action( 'parse_request', 'bp_xmlrpc_parse_request' );
function bp_xmlrpc_parse_request( &$wp )
{
    if ( array_key_exists( 'bp_xmlrpc', $wp->query_vars ) ) {
		
		// redirect
		if ( array_key_exists( 'bp_xmlrpc_redirect', $wp->query_vars ) ) {
			$redirect = $wp->query_vars['bp_xmlrpc_redirect'];
			
			if($redirect == "register") {
				wp_redirect(bp_get_signup_page());
				exit;
			}
			if($redirect == "login") {
				wp_redirect( wp_login_url() );
				exit;
			}
			if(strpos($redirect,"add_user_") === 0) {
				$user = preg_replace("|^add_user_|","",$redirect);
				wp_redirect( admin_url( 'options-general.php?page=bp-xmlrpc-settings&tab=access&add_user='.$user ) );
				exit;
			}
			
			if(!is_user_logged_in()) {
				wp_redirect( wp_login_url( site_url('/index.php?bp_xmlrpc=true&bp_xmlrpc_redirect='.$redirect)));
				exit;	
			}
			
			$which = bp_loggedin_user_domain();
			
			switch($redirect) {
				case 'settings':
					$which .= 'settings/';
					break;
				case 'notifications':
					break;
				case 'messages':
					$which .= 'messages/';
					break;
				case 'friends':
					$which .= bp_get_activity_slug() . '/friends/feed/';
					break;
				case 'groups':
					$which .= bp_get_activity_slug() . '/groups/feed/';
					break;
				case 'favorites':
					$which .= bp_get_activity_slug() . '/favorites/feed/';
					break;
				case 'mentions':
					$which .= bp_get_activity_slug() . '/mentions/feed/';
					break;
				case 'stream':
					$which = site_url('/'.bp_get_activity_slug().'/');
					break;
				case 'site':
				default:
					$which = site_url();
					break;
			}
			
			wp_redirect( $which );
			exit;	
			
		}
		else {
			include( dirname( __FILE__ ) . '/bp-xmlrpc.php' );
			exit();
		}
    }
}
