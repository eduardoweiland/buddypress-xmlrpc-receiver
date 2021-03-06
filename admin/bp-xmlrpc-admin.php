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
 * Checks if a specific capability has been given to at least one role.
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
    $calls = array( 
		'bp.getNotifications',
		'bp.getMessages',
		'bp.sendMessage',
		'bp.updateExternalBlogPostStatus',
		'bp.deleteExternalBlogPostStatus',
		'bp.updateProfileStatus',
		'bp.deleteProfileStatus',
		'bp.postComment',
		'bp.getMyFriends',
		'bp.getGroups',
		'bp.getActivity',
		'bp.getMemberInfo',
		'bp.deleteMember',
    );
    return $calls;
}

function bp_xmlrpc_admin_calls_check( $type, $currenttypes ) {
    if ( in_array( $type, $currenttypes ) )
        echo 'checked';

    return;
}

function bp_xmlrpc_caps_options() {
	$out = '';
	
	$selected = get_option( 'bp_xmlrpc_cap_low' );
	$caps = bp_xmlrpc_admin_get_role_capabilities();
	
	foreach($caps as $cap => $val) {
		$out .= '<option value="'.$cap.'"'.($cap==$selected?' selected':'').'>'.$cap.'</option>';
	}
	return $out;
}

function bp_xmlrpc_admin_tabs( $current = 'main' ) {
    $tabs = array( 'main' => 'Settings', 'access' => 'Access' );
    echo '<div id="icon-options-general" class="icon32"><br></div>';
    echo '<h2 class="nav-tab-wrapper">';
    foreach( $tabs as $tab => $name ){
        $class = ( $tab == $current ) ? ' nav-tab-active' : '';
        echo "<a class='nav-tab$class' href='?page=bp-xmlrpc-settings&tab=$tab'>$name</a>";

    }
    echo '</h2>';
}

function bp_xmlrpc_admin() {
    global $bp;

	if ( isset ( $_GET['tab'] ) )
		$tab = $_GET['tab'];
	else
		$tab = 'main';

    /* If the form has been submitted and the admin referrer checks out, save the settings */
    if ( isset( $_POST['submit'] ) && check_admin_referer( 'bp_xmlrpc_admin' ) ) {

		switch ( $tab ){
			case 'main' :
				if( isset( $_POST['ab_xmlrpc_enable'] ) && !empty( $_POST['ab_xmlrpc_enable'] ) ) {
					update_option( 'bp_xmlrpc_enabled', true );
				} else {
					update_option( 'bp_xmlrpc_enabled', false );
				}

				if ( isset( $_POST['ab_xmlrpc_calls'] ) && !empty( $_POST['ab_xmlrpc_calls'] ) ) {
					update_option( 'bp_xmlrpc_enabled_calls', $_POST['ab_xmlrpc_calls'] );
				} else {
					update_option( 'bp_xmlrpc_enabled_calls', '' );
				}

				if ( isset( $_POST['ab_xmlrpc_more_info'] ) ) {
					update_option( 'bp_xmlrpc_more_info', preg_replace('|\\"|','"',$_POST['ab_xmlrpc_more_info']) );
				}
				break;
			case 'access' :
			
				//check for valid cap and update - if not keep old.
				if( isset( $_POST['cap_low'] ) && !empty( $_POST['cap_low'] ) ) {
					if ( bp_xmlrpc_admin_check_for_cap( $_POST['cap_low'] ) ) {
						update_option( 'bp_xmlrpc_cap_low', $_POST['cap_low'] );
					} else {
						echo '<div id="message" class="error"><p>' . __( 'Invalid wordpress capability - please see <a href="http://codex.wordpress.org/Roles_and_Capabilities#Capability_vs._Role_Table">WP Roles and Capabilities</a>.', 'bp-xmlrpc' ) . '</p></div>';
					}
				} else {
					update_option( 'bp_xmlrpc_cap_low', 'read' );

					echo '<div id="message" class="updated fade"><p>' . __( 'Capability was left blank - this is required - assuming \'read\' (author).', 'bp-xmlrpc' ) . '</p></div>';
				}
				if( isset( $_POST['require_approval'] ) && !empty( $_POST['require_approval'] ) ) {
					update_option( 'bp_xmlrpc_require_approval', true );
				} else {
					update_option( 'bp_xmlrpc_require_approval', false );
				}

				if( isset( $_POST['allowed_users'] ) )
					update_option( 'bp_xmlrpc_allowed_users', $_POST['allowed_users'] );

				break;
		}

        $updated = true;
    }
    
    $allowed_users = explode("\n",get_option('bp_xmlrpc_allowed_users'));
    
	// if adding access

    if ( isset ( $_GET['add_access'] ) && !in_array($_GET['add_access'],$allowed_users)) {
		$allowed_users[] = $_GET['add_access'];
		$added = true;
	}
    
?>
    <div class="wrap">
        <h2><?php _e( 'XML-RPC Options', 'bp-xmlrpc' ); ?></h2>
        <?php if ( isset ( $_GET['tab'] ) ) bp_xmlrpc_admin_tabs($_GET['tab']); else bp_xmlrpc_admin_tabs(); ?>

        <?php if ( isset( $updated ) ) { echo '<div id="message" class="updated fade"><p>' . __( 'Settings Updated.', 'bp-xmlrpc' ) . '</p></div>'; } ?>
        <?php if ( isset( $added ) ) { echo '<div id="message" class="updated fade"><p>' . __( 'User added to list - save options to commit.', 'bp-xmlrpc' ) . '</p></div>'; } ?>

        <form action="<?php admin_url( 'options-general.php?page=bp-xmlrpc-settings&tab='.$tab ); ?>" name="bp-xmlrpc-settings-form" id="bp-xmlrpc-settings-form" method="post">
		<?php
				switch ( $tab ){
					case 'main' :
						$enabled = get_option( 'bp_xmlrpc_enabled' ); 
		?>
            <h3><?php _e( 'Enable Plugin:', 'bp-xmlrpc' ); ?></h3>

            <table class="form-table">
                <tr valign="top">
                    <th><label for="ab_xmlrpc_enable"><?php _e( 'Enable remote XML-RPC BuddyPress functions', 'bp-xmlrpc' ) ?></label></th>
                    <td><input id="ab_xmlrpc_enable" type="checkbox" <?php if ( $enabled ) echo 'checked'; ?> name="ab_xmlrpc_enable" value="1" /></td>
                </tr>

            </table>

            <h3><?php _e( 'Enable remote functions:', 'bp-xmlrpc' ); ?></h3>

            <table class="form-table">
                <?php

                $enabledcalls = (array) get_option( 'bp_xmlrpc_enabled_calls' );
                $totalcalls = bp_xmlrpc_admin_calls();

                foreach ( $totalcalls as $call ) { ?>
                    <tr>
                        <th><label for="type-<?php echo $call ?>"><?php echo $call ?></label></th>
                        <td><input id="type-<?php echo $call ?>" type="checkbox" <?php bp_xmlrpc_admin_calls_check( $call, $enabledcalls ); ?> name="ab_xmlrpc_calls[]" value="<?php echo $call ?>" /></td>
                    </tr>
                <?php } ?>
            </table>

            <h3><?php _e( 'Additional information:', 'bp-xmlrpc' ); ?></h3>

            <p><?php _e( 'Some useful information for the users when they activate remote access (like a client they can use to connect).', 'bp-xmlrpc' ); ?></p>

            <p><?php _e( 'By default, only the URL that the user should use to connect is displayed.', 'bp-xmlrpc' ); ?></p>

            <textarea id="ab_xmlrpc_more_info" name="ab_xmlrpc_more_info" cols="80" rows="10" style="font-family: monospace"><?php echo preg_replace("/\\\+(['\"])/","$1",get_option( 'bp_xmlrpc_more_info' )); ?></textarea>

			<?php
						break;
					case 'access':
			?>
            <h3><?php _e( 'Access Restrictions:', 'bp-xmlrpc' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="cap_low"><?php _e( 'WordPress capability required to access XML-RPC services', 'bp-xmlrpc' ) ?></label></th>
                    <td><select name="cap_low" id="cap_low"><?php echo bp_xmlrpc_caps_options(); ?>" </select></td>
                </tr>
                <tr valign="top">
                    <th><label for="require_approval"><?php _e( 'Require per user admin approval', 'bp-xmlrpc' ) ?></label></th>
                    <td><input id="require_approval" type="checkbox" <?php if ( get_option( 'bp_xmlrpc_require_approval' ) ) echo 'checked'; ?> name="require_approval" value="1" /></td>
                </tr>
            </table>

            <h3><?php _e( 'Allowed Users:', 'bp-xmlrpc' ); ?></h3>
            <table class="form-table">
				<tr>
					<td>
						Add allowed usernames, one per line<br/>
						<textarea id="allowed_users" name="allowed_users" cols="40" rows="20" ><?php echo esc_html( stripslashes( implode( "\n", $allowed_users ) ) ); ?></textarea>
					</td>
				</tr>
            </table>
			<?php
						break;
				}
			
			?>

		<?php wp_nonce_field( 'bp_xmlrpc_admin' ); ?>
		<p class="submit"><input type="submit" name="submit" value="Save Settings"/></p>

        <h3><?php _e( 'Your client should be configured to connect to this URL:', 'bp-xmlrpc' ); ?></h3>
        <div>
            <code><?php echo BP_XMLRPC_URL; ?></code>
        </div>

        <h3><?php _e( 'About', 'bp-xmlrpc' ); ?></h3>
        <div id="bp-xmlrpc-admin-tips" style="margin-left:15px;">
            <p><?php _e( 'This program is free software: you can redistribute it '   .
            'and/or modify it under the terms of the GNU General Public License '    .
            'as published by the Free Software Foundation, either version 3 of the ' .
            'License, or (at your option) any later version.', 'bp-xmlrpc' ); ?></p>

            <p><?php _e( 'This program is distributed in the hope that it will ' .
            'be useful, but WITHOUT ANY WARRANTY; without even the implied '     .
            'warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.'   .
            'See the GNU General Public License for more details. ', 'bp-xmlrpc' ); ?></p>

            <p><a href="http://www.gnu.org/licenses/gpl.txt"><?php _e( 'Full license text', 'bp-xmlrpc' ); ?></a></p>

        </div>

    </div>
<?php
}

?>