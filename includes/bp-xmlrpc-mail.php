/**
 * @deprecated
 */
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

/**
 * @deprecated
 */
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

/**
 * @deprecated
 */
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