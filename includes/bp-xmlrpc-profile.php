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

