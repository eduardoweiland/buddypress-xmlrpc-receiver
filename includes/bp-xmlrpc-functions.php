<?php

function bp_xmlrpc_calls_enabled_check( $type, $currenttypes ) {
    return in_array( $type, $currenttypes );
}

/**
 * Verify if the passed APIKey is valid for the passed login.
 *
 * @param string $password   User's password
 * @param string $apikey     User's generated apikey
 * @param string $user_login User's username
 * @return boolean true if is the login is valid, false otherwise
 */
function bp_xmlrpc_login_apikey_check( $password, $apikey, $user_login ) {
    $hash = hash_hmac( 'md5', $user_login, $password );

    return ( $hash == $apikey );
}

function bp_xmlrpc_get_service_enabled( $services, $name ) {
    foreach ( $services as $key => $value ) {
        if ( $name === $value['name'] ) {
            return $key;
        }
    }

    return -1;
}

/**
 * Generates a new ApiKey for the user.
 *
 * @param int $user_id The user's ID.
 * @return string A new generated key
 */
function bp_xmlrpc_generate_apikey( $user_id, $service ) {
    global $wp_hasher;

    if ( !$user_id || !$service )
        return false;

    $user = get_user_by( 'id', $user_id );

    if ( !$user )
        return false;

    $pass_frag  = substr( $user->user_pass, 8, 4 );
    $created_at = gmdate( 'c' );

    $key = wp_hash( $user->user_login . '+' . $service . '|bp-xmlrpc|' . $pass_frag . $created_at );
    $apikey = hash_hmac( 'md5', $user->user_login . $service, $key );

    $enabled = get_user_meta( $user_id, 'bp_xmlrpc_services' );
    // get_user_meta puts all into index 0, so we're getting it back
    $enabled = isset( $enabled[0] ) ? $enabled[0] : array();

    $index = bp_xmlrpc_get_service_enabled( $enabled, $service );

    // struct that we'll save in the database
    $info = array(
        'name'       => $service,
        'apikey'     => $apikey,
        'created_at' => $created_at,
        'active'     => false        // the user still need to activate this service
    );

    if ( $index > -1 ) {
        $enabled[$index] = $info;
    }
    else {
        array_push( $enabled, $info );
    }

    update_user_meta( $user_id, 'bp_xmlrpc_services', $enabled );

    return $key;
}

function bp_xmlrpc_apikey_notification_message( $key ) {
    global $bp;

    //send email confirmation with userlogin, key, url and details how to use it.
    $user_login = stripslashes( $bp->displayed_user->userdata->user_login );
    $user_email = stripslashes( $bp->displayed_user->userdata->user_email );
    $user_name  = stripslashes( bp_core_get_user_displayname( $bp->displayed_user->id ) );

    $api_link = $bp->displayed_user->domain .  $bp->profile->slug . '/xmlrpcapikey/';

    // Set up and send the message
    $subject = '[' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . '] ' . __( 'XML-RPC APIKey', 'bp-xmlrpc' );

    $message = __( 'Thank you for creating an APIKey for XML-RPC access to this site.', 'bp-xmlrpc' ) . "\r\n\r\n";

    $message .= __( 'Remote API Connection details - please save this information for use in a third-party client:', 'bp-xmlrpc' ) . "\r\n\r\n";
    $message .= sprintf( __( 'Username: %s', 'bp-xmlrpc' ), $user_name ) . "\r\n";
    $message .= sprintf( __( 'APIKey (password): %s', 'bp-xmlrpc' ), $key ) . "\r\n";
    $message .= sprintf( __( 'XML-RPC Url: %s', 'bp-xmlrpc' ), BP_XMLRPC_URL ) . "\r\n\r\n";

    $message .= sprintf( __( 'You may change these settings at: %s', 'bp-xmlrpc' ), $api_link ) . "\r\n";

    /* Send the message */
    $message = apply_filters( 'bp_xmlrpc_apikey_notification_message', $message, $user_name, $key, BP_XMLRPC_URL, $api_link );

    return wp_mail( $user_email, $subject, $message );

}

function bp_xmlrpc_apikey_disabled_message( ) {
    global $bp;

    //send email confirmation with userlogin, key, url and details how to use it.
    $user_login = stripslashes( $bp->displayed_user->userdata->user_login );
    $user_email = stripslashes( $bp->displayed_user->userdata->user_email );
    $user_name = stripslashes( bp_core_get_user_displayname( $bp->displayed_user->id ) );

    // Set up and send the message
    $subject = '[' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . '] ' . __( 'Disabled XML-RPC APIKey', 'bp-xmlrpc' );

    $message = __( 'The site administrator has disabled your remote access via XML-RPC to this site.', 'bp-xmlrpc' ) . "\r\n\r\n";

    /* Send the message */
    $message = apply_filters( 'bp_xmlrpc_apikey_disabled_message', $message );

    return wp_mail( $user_email, $subject, $message );

}

function bp_xmlrpc_apikey_enable_message( ) {
    global $bp;

    //send email confirmation with userlogin, key, url and details how to use it.
    $user_login = stripslashes( $bp->displayed_user->userdata->user_login );
    $user_email = stripslashes( $bp->displayed_user->userdata->user_email );
    $user_name = stripslashes( bp_core_get_user_displayname( $bp->displayed_user->id ) );

    $api_link = $bp->displayed_user->domain .  $bp->profile->slug . '/xmlrpcapikey/';


    // Set up and send the message
    $subject = '[' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . '] ' . __( 'Enabled XML-RPC APIKey', 'bp-xmlrpc' );

    $message = __( 'The site administrator has re-enabled your remote access via XML-RPC to this site.', 'bp-xmlrpc' ) . "\r\n\r\n";

    $message .= sprintf( __( 'You may generate a new APIKey at: %s', 'bp-xmlrpc' ), $api_link ) . "\r\n";

    /* Send the message */
    $message = apply_filters( 'bp_xmlrpc_apikey_disabled_message', $message, $api_link );

    return wp_mail( $user_email, $subject, $message );

}

function bp_xmlrpc_get_followers_recently_active( $user_id, $per_page = false, $page = false, $filter ) {
    return apply_filters( 'followers_get_recently_active', BP_Core_User::get_users( 'active', $per_page, $page, $user_id, $filter ) );
}

function bp_xmlrpc_get_following_recently_active( $user_id, $per_page = false, $page = false, $filter ) {
    return apply_filters( 'following_get_recently_active', BP_Core_User::get_users( 'active', $per_page, $page, $user_id, $filter ) );
}
?>