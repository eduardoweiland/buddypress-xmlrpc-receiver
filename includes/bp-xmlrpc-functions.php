<?php

function bp_xmlrpc_calls_enabled_check( $type, $currenttypes ) {
    return in_array( $type, $currenttypes );
}

/**
 * Check if some service has already an ApiKey associated.
 * 
 * @param array $services The services saved on the database.
 * @param string $name The service name to check.
 * @return int The index of @a $name in @a $services or -1 if not found.
 */
function bp_xmlrpc_get_service_exists( $services, $name ) {
    foreach ( $services as $key => $value ) {
        if ( $name === $value['name'] ) {
            return $key;
        }
    }

    return -1;
}

function bp_xmlrpc_get_services( $user_id ) {
    $services = get_user_meta( $user_id, 'bp_xmlrpc_services' );

    // get_user_meta puts all into index 0, so we're getting it back
    return isset( $services[0] ) ? $services[0] : array();
}

function bp_xmlrpc_set_services( $user_id, $services ) {
    return update_user_meta( $user_id, 'bp_xmlrpc_services', $services );
}

/**
 * Gets a list of all allowed services.
 * These are the services the user have explicitly allowed to connect.
 *
 * @param int $user_id The user's ID.
 * @return array The array of allowed services.
 */
function bp_xmlrpc_get_allowed_services( $user_id ) {
    if ( !$user_id ) {
        return false;
    }

    $services = bp_xmlrpc_get_services( $user_id );
    $allowed  = array();

    foreach ( $services as $service ) {
        if ( isset( $service['allowed'] ) && $service['allowed'] ) {
            array_push( $allowed, $service );
        }
    }

    return $allowed;
}

/**
 * Gets a list of all pending services.
 * Pending services are the ones that have requested an ApiKey but the user 
 * haven't allowed it yet.
 *
 * @param int $user_id The user's ID.
 * @return array The array of pending services.
 */
function bp_xmlrpc_get_pending_services( $user_id ) {
    if ( !$user_id ) {
        return false;
    }

    $services = bp_xmlrpc_get_services( $user_id );
    $pending  = array();

    foreach ( $services as $service ) {
        if ( !isset( $service['allowed'] ) || !$service['allowed'] ) {
            array_push( $pending, $service );
        }
    }

    return $pending;
}

/**
 * Verify if the passed APIKey is valid for the passed login.
 *
 * @param string $user_id The user's ID.
 * @param string $service Name of the service which is trying to connect.
 * @param string $key     Key informed by the service.
 * @return boolean true if is the login is valid, false otherwise
 */
function bp_xmlrpc_login_apikey_check( $user_id, $service, $key ) {
    $user    = get_user_by( 'id', $user_id );
    $enabled = bp_xmlrpc_get_allowed_services( $user_id );
    $index   = bp_xmlrpc_get_service_exists( $enabled, $service );

    // unknown user or service
    if ( !$user || $index === -1 ) {
        return false;
    }

    $apikey = $enabled[$index]['apikey'];

    $hash = hash_hmac( 'md5', $user->user_login . $service, $key );

    return ( $hash === $apikey );
}

/**
 * Generates a new ApiKey for the user.
 * Each service gets one unique ApiKey.
 *
 * @param int $user_id The user's ID.
 * @param string $service The service name. Must be unique per user.
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
    $created_at = current_time( 'timestamp' );

    $key = wp_hash( $user->user_login . '+' . $service . '|bp-xmlrpc|' . $pass_frag . $created_at );
    $apikey = hash_hmac( 'md5', $user->user_login . $service, $key );

    $services = bp_xmlrpc_get_services( $user_id );

    // struct that we'll save in the database
    $info = array(
        'id'         => 1,
        'name'       => $service,
        'apikey'     => $apikey,
        'created_at' => $created_at,
        'allowed'    => false        // the user still need to allow this service
    );

    $index = bp_xmlrpc_get_service_exists( $services, $service );

    // update existing service
    if ( $index > -1 ) {
        $info['id'] = $services[$index]['id'];   // use the same ID  
        $services[$index] = $info;
    }
    // or add a new one
    else {
        $size = count( $services );
        $info['id'] = $size > 0 ? $services[$size - 1]['id'] + 1 : 1;  // get the last used ID +1 
        array_push( $services, $info );
    }

    bp_xmlrpc_set_services( $user_id, $services );

    return $key;
}

function bp_xmlrpc_get_followers_recently_active( $user_id, $per_page = false, $page = false, $filter = '' ) {
    return apply_filters( 'followers_get_recently_active', BP_Core_User::get_users( 'active', $per_page, $page, $user_id, $filter ) );
}

function bp_xmlrpc_get_following_recently_active( $user_id, $per_page = false, $page = false, $filter = '' ) {
    return apply_filters( 'following_get_recently_active', BP_Core_User::get_users( 'active', $per_page, $page, $user_id, $filter ) );
}
?>