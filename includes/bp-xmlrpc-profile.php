<?php
//add profile admin options to disable on a per user basis
function bp_xmlrpc_set_disabled_status() {
	global $bp;

	if ( !is_site_admin() || bp_is_my_profile() || !$bp->displayed_user->id )
		return;

	if ( 'admin' == $bp->current_component && 'disable-xmlrpc' == $bp->current_action ) {
		
		/* Check the nonce */
		check_admin_referer( 'bp_xmlrpc_disable_key' );
		
		bp_core_add_message( __( 'Remote Access has been disabled.', 'bp-xmlrpc' ) );
			
		//set the access
		delete_usermeta( $bp->displayed_user->id, "bp_xmlrpc_apikey");
		update_usermeta( $bp->displayed_user->id, "bp_xmlrpc_disabled", true);
			
		//inform the user
		bp_xmlrpc_apikey_disabled_message();
			
		bp_core_redirect( wp_get_referer() );

	} else if ( 'admin' == $bp->current_component &&  'enable-xmlrpc' == $bp->current_action ) {

		/* Check the nonce */
		check_admin_referer( 'bp_xmlrpc_enable_key' );

		bp_core_add_message( __( 'Remote Access has been enabled.', 'bp-xmlrpc' ) );
			
		//restore access
		//update_usermeta( $bp->displayed_user->id, "bp_xmlrpc_disabled", false);
		delete_usermeta( $bp->displayed_user->id, "bp_xmlrpc_disabled");
			
		//re-inform the user
		bp_xmlrpc_apikey_enable_message();
			
		bp_core_redirect( wp_get_referer() );
	}
	
}
add_action( 'wp', 'bp_xmlrpc_set_disabled_status', 3 );

//add profile admin options to disable on a per user basis
function bp_xmlrpc_adminbar_menu_items() {
	global $bp;
	
	if ( !is_site_admin() )
		return;

	if ( !get_usermeta( $bp->displayed_user->id, 'bp_xmlrpc_disabled') ) { ?>
		<li><a href="<?php echo wp_nonce_url( $bp->displayed_user->domain . 'admin/disable-xmlrpc/', 'bp_xmlrpc_disable_key' ) ?>" class="confirm"><?php _e( "Disable XML-RPC", 'buddypress' ) ?></a></li>
	<?php } else if ( get_usermeta( $bp->displayed_user->id, 'bp_xmlrpc_disabled') ) { ?>
		<li><a href="<?php echo wp_nonce_url( $bp->displayed_user->domain . 'admin/enable-xmlrpc/', 'bp_xmlrpc_enable_key' ) ?>" class="confirm"><?php _e( "Enable XML-RPC", 'buddypress' ) ?></a></li>
	<?php }

}
add_action( 'xprofile_adminbar_menu_items', 'bp_xmlrpc_adminbar_menu_items' );

//add apikey link to xprofile page
function bp_xmlrpc_xprofile_setup_nav() {
	global $bp;
	
	if ( !get_option( 'bp_xmlrpc_enabled' ) )
		return false;
	
	//loggedin as the admin should be able to override the settings
	if ( !is_site_admin() && get_usermeta( $bp->displayed_user->id, 'bp_xmlrpc_disabled') )
		return false;
	
	if ( !current_user_can( get_option('bp_xmlrpc_cap_low') ) )
		return;
	
	bp_core_new_subnav_item( array( 'name' => __( 'XML-RPC APIKey', 'bp-xmlrpc' ), 'slug' => 'xmlrpcapikey', 'parent_url' => $bp->loggedin_user->domain . $bp->profile->slug . '/', 'parent_slug' => $bp->profile->slug, 'screen_function' => 'bp_xmlrpc_xprofile_screen_apikey', 'position' => 30, 'user_has_access' => bp_is_my_profile()  ) );

	//$settings_link = $bp->loggedin_user->domain . $bp->settings->slug . '/';
	//bp_core_new_subnav_item( array( 'name' => __( 'ba', 'buddypress' ), 'slug' => 'ba', 'parent_url' => $settings_link, 'parent_slug' => $bp->settings->slug, 'screen_function' => 'bp_xmlrpc_xprofile_screen_apikey', 'position' => 30, 'user_has_access' => bp_is_my_profile() ) );

}
add_action( 'xprofile_setup_nav', 'bp_xmlrpc_xprofile_setup_nav',4 );	


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
			delete_usermeta( $bp->displayed_user->id, "bp_xmlrpc_apikey");
		}
	}
	
	if ( isset( $_POST['xmlrpc-apikey-remove-submit'] ) && check_admin_referer( 'bp_xmlrpc_remove_key' ) ) {
		bp_core_add_message( __( 'APIKey removed! - Remote Access has been disabled.', 'bp-xmlrpc' ) );
		delete_usermeta( $bp->displayed_user->id, "bp_xmlrpc_apikey");
	}

	add_action( 'bp_template_title', 'bp_xmlrpc_xprofile_screen_title' );
	add_action( 'bp_template_content', 'bp_xmlrpc_xprofile_screen_content' );
	
	bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
}

function bp_xmlrpc_xprofile_screen_title() {
	__( 'XMLRPC APIKey', 'bp-xmlrpc' );
}

function bp_xmlrpc_xprofile_screen_content() {
	global $bp;
?>

	<h4><?php _e( 'XML-RPC APIKey', 'bp-xmlrpc' ) ?></h4>

	<p><?php _e( 'Your XML-RPC APIKey enables remote access to features of BuddyPress by use of third-party tools.', 'bp-xmlrpc'); ?></p>

	<p><?php _e( 'A new APIKey is required if your login credentials have changed.', 'bp-xmlrpc'); ?></p>

	
	<?php $key = get_usermeta( $bp->displayed_user->id, 'bp_xmlrpc_apikey');
	if ($key) { ?>
		<h5><?php _e( 'Remove APIKey', 'bp-xmlrpc' ); ?></h5>
		<p><?php _e( 'Remove the APIKey to disable the service.', 'bp-xmlrpc'); ?></p>
		<form method="post" id="bp-xmlrpc-remove-form" name="bp-xmlrpc-remove-form" class="standard-form" action="">

			<div class="clear"></div>

			<?php wp_nonce_field( 'bp_xmlrpc_remove_key' ); ?>

			<div class="clear"></div>

			<input style="color:red" type="submit" name="xmlrpc-apikey-remove-submit" value="<?php echo __( 'Remove XML-RPC APIKey', 'bp-xmlrpc' ); ?>">

		</form>
	<?php } else { ?>
		<h5><?php _e( 'Generate APIKey', 'bp-xmlrpc' ); ?></h5>
		<p><?php _e( 'An email confirmation will be sent with connection details.', 'bp-xmlrpc'); ?></p>
		<form method="post" id="bp-xmlrpc-form" name="bp-xmlrpc-form" class="standard-form" action="">

			<div class="clear"></div>

			<?php wp_nonce_field( 'bp_xmlrpc_key' ); ?>

			<div class="clear"></div>

			<input style="color:green" type="submit" name="xmlrpc-apikey-submit" value="<?php echo __( 'Generate new XML-RPC APIKey', 'bp-xmlrpc' ); ?>">

		</form>
	
	<?php } ?>
	
	
	<div class="clear"></div>
	
	<h5><?php _e( 'Connection Details', 'bp-xmlrpc' ); ?></h5>

	<table class="form-table">
		<tr>
			<th>Url</th>
			<td><?php echo BP_XMLRPC_URL; ?></td>
		</tr>
	</table>
	
	<?php

}
?>