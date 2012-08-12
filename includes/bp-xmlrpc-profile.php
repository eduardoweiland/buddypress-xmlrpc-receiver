<?php
//add profile admin options to disable on a per user basis
function bp_xmlrpc_set_disabled_status() {
    global $bp;

    if ( !is_super_admin() || bp_is_my_profile() || !isset( $bp->displayed_user->id ) )
        return;

    if ( 'admin' == $bp->current_component && 'disable-xmlrpc' == $bp->current_action ) {

        /* Check the nonce */
        check_admin_referer( 'bp_xmlrpc_disable_key' );

        bp_core_add_message( __( 'Remote access has been disabled.', 'bp-xmlrpc' ) );

        //set the access
        delete_user_meta( $bp->displayed_user->id, "bp_xmlrpc_apikey");
        update_user_meta( $bp->displayed_user->id, "bp_xmlrpc_disabled", true );

        //inform the user
        bp_xmlrpc_apikey_disabled_message();

        bp_core_redirect( wp_get_referer() );

    } else if ( 'admin' == $bp->current_component &&  'enable-xmlrpc' == $bp->current_action ) {

        /* Check the nonce */
        check_admin_referer( 'bp_xmlrpc_enable_key' );

        bp_core_add_message( __( 'Remote access has been enabled.', 'bp-xmlrpc' ) );

        //restore access
        delete_user_meta( $bp->displayed_user->id, "bp_xmlrpc_disabled" );

        //re-inform the user
        bp_xmlrpc_apikey_enable_message();

        bp_core_redirect( wp_get_referer() );
    }

}
add_action( 'wp', 'bp_xmlrpc_set_disabled_status', 3 );

//add profile admin options to disable on a per user basis
function bp_xmlrpc_adminbar_menu_items() {
    global $bp, $wp_admin_bar;

    if ( !is_super_admin() || !isset( $bp->displayed_user->id ) )
        return;
    
    if ( get_user_meta( $bp->displayed_user->id, 'bp_xmlrpc_disabled') ) { ?>
        <li><a href="<?php echo wp_nonce_url( $bp->displayed_user->domain . 'admin/enable-xmlrpc/', 'bp_xmlrpc_enable_key' ) ?>" class="confirm"><?php _e( "Enable XML-RPC", 'buddypress' ) ?></a></li>
    <?php } else { ?>
        <li><a href="<?php echo wp_nonce_url( $bp->displayed_user->domain . 'admin/disable-xmlrpc/', 'bp_xmlrpc_disable_key' ) ?>" class="confirm"><?php _e( "Disable XML-RPC", 'buddypress' ) ?></a></li>
    <?php }
}
add_action( 'bp_members_adminbar_admin_menu', 'bp_xmlrpc_adminbar_menu_items', 60 );
// add_action( 'admin_bar_menu', 'bp_xmlrpc_adminbar_menu_items', 401 );

// add apikey link to xprofile page
function bp_xmlrpc_xprofile_setup_nav() {
    global $bp;

    if ( !get_option( 'bp_xmlrpc_enabled' ) )
        return false;

    if ( !isset( $bp->displayed_user->id ) )
        return false;

    // loggedin as the admin should be able to override the settings
    if ( !is_super_admin() && get_user_meta( $bp->displayed_user->id, 'bp_xmlrpc_disabled') )
        return false;

    if ( !current_user_can( get_option('bp_xmlrpc_cap_low') ) )
        return;

    bp_core_new_subnav_item( array( 'name'            => __( 'Remote access', 'bp-xmlrpc' ),
                                    'slug'            => 'remote-access',
                                    'parent_url'      => $bp->displayed_user->domain . $bp->settings->slug . '/',
                                    'parent_slug'     => $bp->settings->slug,
                                    'screen_function' => 'bp_xmlrpc_xprofile_screen_apikey',
                                    'position'        => 30,
                                    'user_has_access' => bp_is_my_profile() || is_super_admin() ) );
}
add_action( 'bp_setup_nav', 'bp_xmlrpc_xprofile_setup_nav', 4 );


//xprofile page to change user signature
function bp_xmlrpc_xprofile_screen_apikey() {
    global $bp;

    if ( isset( $_POST['xmlrpc-apikey-submit'] ) && check_admin_referer( 'bp_xmlrpc_key' ) ) {

        $key = bp_xmlrpc_generate_apikey( $bp->displayed_user->id );

        if ( $key ) {
            bp_core_add_message( __( 'New APIKey Generated: '. $key, 'bp-xmlrpc' ) );

            bp_xmlrpc_apikey_notification_message( $key );

        } else {
            bp_core_add_message( __( 'Error: APIKey removed', 'bp-xmlrpc' ) );
            delete_user_meta( $bp->displayed_user->id, "bp_xmlrpc_apikey");
        }
    }

    if ( isset( $_POST['xmlrpc-apikey-remove-submit'] ) && check_admin_referer( 'bp_xmlrpc_remove_key' ) ) {
        bp_core_add_message( __( 'APIKey removed! - Remote Access has been disabled.', 'bp-xmlrpc' ) );
        delete_user_meta( $bp->displayed_user->id, "bp_xmlrpc_apikey");
    }

    add_action( 'bp_template_title', 'bp_xmlrpc_xprofile_screen_title' );
    add_action( 'bp_template_content', 'bp_xmlrpc_xprofile_screen_content' );

    bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
}

function bp_xmlrpc_xprofile_screen_title() {
    __( 'Remote access', 'bp-xmlrpc' );
}

function bp_xmlrpc_xprofile_screen_content() {
    global $bp;
?>

    <h3><?php _e( 'Remote access', 'bp-xmlrpc' ); ?></h3>

    <p><?php _e( 'You can access to features of BuddyPress by use of third-party tools.', 'bp-xmlrpc' ); ?></p>

    <p><?php _e( 'Here you can manage what services can access your account remotely.', 'bp-xmlrpc' ); ?></p>

    <div class="clear"></div>

    <?php $moreinfo = get_option( 'bp_xmlrpc_more_info' );

    if ( empty( $moreinfo ) ) { ?>

        <h4><?php _e( 'Connection Details', 'bp-xmlrpc' ); ?></h4>

        <p><?php _e( 'You can send XML-RPC requests to this URL:', 'bp-xmlrpc' ); ?></p>

        <code style="background: #EAEAEA;"><?php echo BP_XMLRPC_URL; ?></code>

    <?php } else {

        // format output and add links for at-mentions
        echo apply_filters( 'the_content', wpautop( $moreinfo ) );

    } ?>

    <div class="clear"></div>

    <?php
}
?>
