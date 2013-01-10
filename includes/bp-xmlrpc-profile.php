<?php
//add profile admin options to disable on a per user basis
function bp_xmlrpc_set_disabled_status() {
    global $bp;

    if ( !is_super_admin() || !bp_is_user() || bp_is_my_profile() )
        return;

    if ( 'admin' !== $bp->current_component )
        return;

    if ( 'disable-xmlrpc' == $bp->current_action ) {

        // Check the nonce
        check_admin_referer( 'bp_xmlrpc_disable_key' );

        bp_core_add_message( __( 'Remote access has been disabled.', 'bp-xmlrpc' ) );

        // set the access
        update_user_meta( $bp->displayed_user->id, 'bp_xmlrpc_disabled', true );

        // inform the user
        //bp_xmlrpc_apikey_disabled_message();

        bp_core_redirect( wp_get_referer() );

    } else if ( 'enable-xmlrpc' == $bp->current_action ) {

        // Check the nonce
        check_admin_referer( 'bp_xmlrpc_enable_key' );

        bp_core_add_message( __( 'Remote access has been enabled.', 'bp-xmlrpc' ) );

        // restore access
        delete_user_meta( $bp->displayed_user->id, 'bp_xmlrpc_disabled' );

        // re-inform the user
        //bp_xmlrpc_apikey_enable_message();

        bp_core_redirect( wp_get_referer() );
    }

}
//add_action( 'wp', 'bp_xmlrpc_set_disabled_status', 3 );

/**
 * Manipulates services approvals, rejections and removes.
 */
function bp_xmlrpc_service_set_allowed() {
    global $bp;

    if ( !is_super_admin() && !bp_is_my_profile() )
        return;

    if ( 'settings' !== $bp->current_component )
        return;

    $serviceId = isset( $bp->action_variables[0] ) ? $bp->action_variables[0] : null;
    $services  = bp_xmlrpc_get_services( $bp->displayed_user->id );

    // no service, do nothing
    if ( !$serviceId )
        return;

    // approve a service
    if ( 'remote-access-approve' == $bp->current_action ) {
        $name = '';
        // find the service by ID
        foreach ( $services as &$service ) {
            if ( (int)$serviceId === (int)$service['id'] ) {
                $service['allowed'] = true;
                $name = $service['name'];
            }
        }

        bp_xmlrpc_set_services( $bp->displayed_user->id, $services );

        bp_core_add_message( sprintf( __( 'Remote service %s has been approved.', 'bp-xmlrpc' ), $name ) );
        bp_core_redirect( wp_get_referer() );
    }
    // reject a service
    elseif ( 'remote-access-reject' == $bp->current_action ) {
        $name = '';
        // find the service by ID
        foreach ( $services as $index => $service ) {
            if ( (int)$serviceId === (int)$service['id'] ) {
                $name = $service['name'];
                unset( $services[$index] );
            }
        }

        $services = array_values( $services );
        bp_xmlrpc_set_services( $bp->displayed_user->id, $services );

        bp_core_add_message( sprintf( __( 'Remote service %s has been rejected.', 'bp-xmlrpc' ), $name ) );
        bp_core_redirect( wp_get_referer() );
    }
    // remove a service that have been allowed
    elseif ( 'remote-access-remove' == $bp->current_action ) {
        $name = '';
        // find the service by ID
        foreach ( $services as $index => $service ) {
            if ( (int)$serviceId === (int)$service['id'] ) {
                $name = $service['name'];
                unset( $services[$index] );
            }
        }

        $services = array_values( $services );
        bp_xmlrpc_set_services( $bp->displayed_user->id, $services );

        bp_core_add_message( sprintf( __( 'Remote service %s has been removed.', 'bp-xmlrpc' ), $name ) );
        bp_core_redirect( wp_get_referer() );
    }
}
add_action( 'wp', 'bp_xmlrpc_service_set_allowed', 3 );

/**
 * Add settings link in adminbar when viewing user profiles (only for admins).
 *
 * @return void
 */
function bp_xmlrpc_adminbar_menu_items() {
    global $bp, $wp_admin_bar;

    // only show for admins on user pages
    if ( !is_super_admin() || !bp_is_user() || bp_is_my_profile() )
        return;

    // Edit Member > Remote access
    $wp_admin_bar->add_menu( array(
        'parent' => $bp->user_admin_menu_id,
        'id'     => $bp->user_admin_menu_id . '-remote-access',
        'title'  => __( 'Remote access', 'bp-xmlrpc' ),
        'href'   => bp_displayed_user_domain() . 'settings/remote-access/'
    ) );
}
add_action( 'admin_bar_menu', 'bp_xmlrpc_adminbar_menu_items', 100 );

/**
 * Add 'Remote access' option on user's settings page.
 *
 * @return void
 */
function bp_xmlrpc_xprofile_setup_nav() {
    global $bp;

    // XMLRPC disabled site-wide
    if ( !get_option( 'bp_xmlrpc_enabled' ) )
        return;

    // this is not a user settings page
    if ( !bp_is_user() )
        return;

    // loggedin as the admin should be able to override the settings
    if ( !is_super_admin() && get_user_meta( $bp->displayed_user->id, 'bp_xmlrpc_disabled' ) )
        return;

    // disabled by capability
    if ( !current_user_can( get_option( 'bp_xmlrpc_cap_low' ) ) )
        return;

    bp_core_new_subnav_item( array( 'name'            => __( 'Remote access', 'bp-xmlrpc' ),
                                    'slug'            => 'remote-access',
                                    'parent_url'      => $bp->displayed_user->domain . $bp->settings->slug . '/',
                                    'parent_slug'     => $bp->settings->slug,
                                    'screen_function' => 'bp_xmlrpc_xprofile_screen_apikey',
                                    'position'        => 30,
                                    'user_has_access' => bp_is_my_profile() || is_super_admin() ) );
}
add_action( 'bp_setup_nav', 'bp_xmlrpc_xprofile_setup_nav', 30 );


/**
 * @deprecated
 */
//xprofile page to change user signature
function bp_xmlrpc_xprofile_screen_apikey() {
    global $bp;

    if ( isset( $_POST['xmlrpc-apikey-submit'] ) && check_admin_referer( 'bp_xmlrpc_key' ) ) {

        $key = bp_xmlrpc_generate_apikey( $bp->displayed_user->id );

        if ( $key ) {
            bp_core_add_message( __( 'New APIKey Generated: '. $key, 'bp-xmlrpc' ) );

            //bp_xmlrpc_apikey_notification_message( $key );

        } else {
            bp_core_add_message( __( 'Error: APIKey removed', 'bp-xmlrpc' ) );
            delete_user_meta( $bp->displayed_user->id, 'bp_xmlrpc_apikey' );
        }
    }

    if ( isset( $_POST['xmlrpc-apikey-remove-submit'] ) && check_admin_referer( 'bp_xmlrpc_remove_key' ) ) {
        bp_core_add_message( __( 'APIKey removed! - Remote Access has been disabled.', 'bp-xmlrpc' ) );
        delete_user_meta( $bp->displayed_user->id, 'bp_xmlrpc_apikey' );
    }

    add_action( 'bp_template_title', 'bp_xmlrpc_xprofile_screen_title' );
    add_action( 'bp_template_content', 'bp_xmlrpc_xprofile_screen_content' );

    bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
}

function bp_xmlrpc_xprofile_screen_title() {
    _e( 'Remote access', 'bp-xmlrpc' );
}

function bp_xmlrpc_xprofile_screen_content() {
    global $bp;
?>

    <p><?php _e( 'You can access BuddyPress features by use of third-party tools and services.', 'bp-xmlrpc' ); ?></p>

    <?php
    $pending = bp_xmlrpc_get_pending_services( $bp->displayed_user->id );

    if ( !empty( $pending ) ) { ?>

    <h4><?php _e( 'Services waiting approval', 'bp-xmlrpc' ); ?></h4>

    <p><?php _e( 'These services are awaiting your approval before they can connect to your account.', 'bp-xmlrpc' ); ?></p>

    <table class="form-table" style="text-align: left;">
        <thead>
            <tr>
                <th><?php _e( 'Service', 'bp-xmlrpc' ); ?></th>
                <th><?php _e( 'Added on', 'bp-xmlrpc' ); ?></th>
                <th><?php _e( 'Action', 'bp-xmlrpc' ); ?></th>
            </tr>
        </thead>
        <tbody>

        <?php foreach ( $pending as $index => $ps ) { ?>

            <?php printf( '<tr %s>', ( $index % 2 === 1 ) ? 'class="alt"' : '' ); ?>

                <td><?php echo $ps['name']; ?></td>
                <td><?php echo date_i18n( get_option( 'date_format' ), $ps['created_at'] ); ?></td>
                <td>
                    <a href="<?php echo wp_nonce_url( $bp->displayed_user->domain .
                        $bp->settings->slug . '/remote-access-approve/' . $ps['id'] ); ?>"
                        class="button"><?php _e( 'Approve', 'bp-xmlrpc' ); ?></a>
                    <a href="<?php echo wp_nonce_url( $bp->displayed_user->domain .
                        $bp->settings->slug . '/remote-access-reject/' . $ps['id'] ); ?>"
                        class="button"><?php _e( 'Reject',  'bp-xmlrpc' ); ?></a>
                </td>
            </tr>

        <?php } ?>

        </tbody>
    </table>

    <?php
    }   // $pending

    $services = bp_xmlrpc_get_allowed_services( $bp->displayed_user->id );

    if ( empty( $services ) ) {
        echo '<br/><p>' . __( "You don't have any connected service yet.", 'bp-xmlrpc' ) . '</p>';
    }
    else { ?>

    <h4><?php _e( 'Allowed services', 'bp-xmlrpc' ); ?></h4>

    <p><?php _e( 'You have granted remote access to your account to the following services.', 'bp-xmlrpc' ); ?></p>

    <table class="form-table" style="text-align: left;">
        <thead>
            <tr>
                <th><?php _e( 'Service', 'bp-xmlrpc' ); ?></th>
                <th><?php _e( 'Added on', 'bp-xmlrpc' ); ?></th>
                <th><?php _e( 'Remove', 'bp-xmlrpc' ); ?></th>
            </tr>
        </thead>
        <tbody>

        <?php foreach( $services as $service ) { ?>

            <tr>
                <td><?php echo $service['name']; ?></td>
                <td><?php echo date_i18n( get_option( 'date_format' ), $service['created_at'] ); ?></td>
                <td><a href="<?php echo wp_nonce_url( $bp->displayed_user->domain .
                    $bp->settings->slug . '/remote-access-remove/' . $service['id'] ); ?>"
                    class="button confirm"><?php _e( 'Remove', 'bp-xmlrpc' ); ?></a>
                </td>
            </tr>

        <?php } ?>

        </tbody>
    </table>

    <?php } ?>

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
