<?php
/**
 * Gets an array of capabilities according to each user role.  Each role will return its caps,
 * which are then added to the overall $capabilities array.
 *
 * Note that if no role has the capability, it technically no longer exists.  Since this could be
 * a problem with folks accidentally deleting the default WordPress capabilities, the
 * members_default_capabilities() will return those all the defaults.
 *
 * @since 0.1
 * @return $capabilities array All the capabilities of all the user roles.
 * @global $wp_roles array Holds all the roles for the installation.
 */
function bp_xmlrpc_admin_get_role_capabilities() {
    global $wp_roles;

    $capabilities = array();

    /* Loop through each role object because we need to get the caps. */
    foreach ( $wp_roles->role_objects as $key => $role ) {

        /* Roles without capabilities will cause an error, so we need to check if $role->capabilities is an array. */
        if ( is_array( $role->capabilities ) ) {

            /* Loop through the role's capabilities and add them to the $capabilities array. */
            foreach ( $role->capabilities as $cap => $grant )
                $capabilities[$cap] = $cap;
        }
    }

    /* Return the capabilities array. */
    return $capabilities;
}

/**
 * Checks if a specific capability has been given to at least one role. If it has,
 * return true. Else, return false.
 *
 * @since 0.1
 * @uses members_get_role_capabilities() Checks for capability in array of role caps.
 * @param $cap string Name of the capability to check for.
 * @return true|false bool Whether the capability has been given to a role.
 */
function bp_xmlrpc_admin_check_for_cap( $cap = '' ) {

    /* Without a capability, we have nothing to check for.  Just return false. */
    if ( !$cap )
        return false;

    /* Gets capabilities that are currently mapped to a role. */
    $caps = bp_xmlrpc_admin_get_role_capabilities();

    /* If the capability has been given to at least one role, return true. */
    if ( in_array( $cap, $caps ) )
        return true;

    /* If no role has been given the capability, return false. */
    return false;
}


function bp_xmlrpc_admin_calls( ) {
    $calls = array( 'bp.getNotifications', 'bp.updateExternalBlogPostStatus', 'bp.deleteExternalBlogPostStatus', 'bp.updateProfileStatus', 'bp.getMyFriends', 'bp.getMyGroups', 'bp.getMyFollowing', 'bp.getMyFollowers', 'bp.getActivity' );
    return $calls;
}

function bp_xmlrpc_admin_calls_check( $type, $currenttypes ) {
    if ( in_array( $type, $currenttypes) )
        echo 'checked';

    return;
}

function bp_xmlrpc_admin() {
    global $bp;

    /* If the form has been submitted and the admin referrer checks out, save the settings */
    if ( isset( $_POST['submit'] ) && check_admin_referer('bp_xmlrpc_admin') ) {

        if( isset($_POST['ab_xmlrpc_enable'] ) && !empty($_POST['ab_xmlrpc_enable']) ) {
            update_option( 'bp_xmlrpc_enabled', true );
        } else {
            update_option( 'bp_xmlrpc_enabled', false );
        }

        //check for valid cap and update - if not keep old.
        if( isset($_POST['cap_low'] ) && !empty($_POST['cap_low']) ) {
            if ( bp_xmlrpc_admin_check_for_cap( $_POST['cap_low'] ) ) {
                update_option( 'bp_xmlrpc_cap_low', $_POST['cap_low'] );
            } else {
                echo '<div id="message" class="error"><p>Invalid wordpress capability - please see <a href="http://codex.wordpress.org/Roles_and_Capabilities#Capability_vs._Role_Table">WP Roles and Capabilities</a>.</p></div>';
            }
        } else {
            update_option( 'bp_xmlrpc_cap_low', 'upload_files' );

            echo '<div id="message" class="updated fade"><p>Capability was left blank - this is required - assuming \'upload_files\' (author).</p></div>';
        }

        if( isset($_POST['ab_xmlrpc_calls'] ) && !empty($_POST['ab_xmlrpc_calls']) ) {
            update_option( 'bp_xmlrpc_enabled_calls', $_POST['ab_xmlrpc_calls'] );
        } else {
            update_option( 'bp_xmlrpc_enabled_calls', '' );
        }

        $updated = true;
    }
?>
    <div class="wrap">
        <h2><?php _e( 'XML-RPC Options', 'bp-xmlrpc' ); ?></h2>

        <?php if ( isset($updated) ) : echo "<div id='message' class='updated fade'><p>" . __( 'Settings Updated.', 'bp-xmlrpc' ) . "</p></div>"; endif; ?>

        <form action="<?php echo site_url() . '/wp-admin/admin.php?page=bp-xmlrpc-settings' ?>" name="bp-xmlrpc-settings-form" id="bp-xmlrpc-settings-form" method="post">

            <h4><?php _e( 'Enable XMLRPC:', 'bp-xmlrpc' ); ?></h4>
            <?php $enabled = get_option( 'bp_xmlrpc_enabled' ); ?>

            <table class="form-table">
                <tr valign="top">
                    <th><label for="ab_xmlrpc_enable"><?php _e( 'Enable remote XML-RPC BuddyPress functions', 'bp-xmlrpc' ) ?></label></th>
                    <td><input id="ab_xmlrpc_enable" type="checkbox" <?php if ($enabled) echo 'checked'; ?> name="ab_xmlrpc_enable" value="1" /></td>
                </tr>

                <tr>
                    <th scope="row"><label for="cap_low"><?php _e( 'XML-RPC WordPress capability (what level can access)', 'bp-xmlrpc' ) ?></label></th>
                    <td><input type="text" name="cap_low" id="cap_low" value="<?php echo get_option( 'bp_xmlrpc_cap_low'); ?>" /></td>
                </tr>

            </table>

            <h4><?php _e( 'Enable remote functions:', 'bp-xmlrpc' ); ?></h4>

            <table class="form-table">
                <?php

                $enabledcalls = (array) get_option( 'bp_xmlrpc_enabled_calls');
                $totalcalls = bp_xmlrpc_admin_calls();

                foreach ($totalcalls as $call) { ?>
                    <tr>
                        <th><label for="type-<?php echo $call ?>"><?php echo $call ?></label></th>
                        <td><input id="type-<?php echo $call ?>" type="checkbox" <?php bp_xmlrpc_admin_calls_check( $call, $enabledcalls ); ?> name="ab_xmlrpc_calls[]" value="<?php echo $call ?>" /></td>
                    </tr>
                <?php } ?>
            </table>

            <?php wp_nonce_field( 'bp_xmlrpc_admin' ); ?>

            <p class="submit"><input type="submit" name="submit" value="Save Settings"/></p>

        <h3>Your client should be configured to connect to this URL:</h3>
        <div>
            <code><?php echo BP_XMLRPC_URL; ?></code>
        </div>

        <h3>License:</h3>
        <div id="bp-xmlrpc-admin-tips" style="margin-left:15px;">
            <p>Copyright &copy 2012 Eduardo Weiland</p>

            <p>
                This program is free software: you can redistribute it and/or modify
                it under the terms of the GNU General Public License as published by
                the Free Software Foundation, either version 3 of the License, or
                (at your option) any later version.
            </p>

            <p>
                This program is distributed in the hope that it will be useful,
                but WITHOUT ANY WARRANTY; without even the implied warranty of
                MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
                GNU General Public License for more details.
            </p>
        
            <p><a href="http://www.gnu.org/licenses/gpl.txt">Full license text</a></p>
            <p><a href="http://github.com/duduweiland/buddypress-xmlrpc-receiver">Project page</a></p>

        </div>

    </div>
<?php
}

?>