<?php
/**
 * XML-RPC protocol support for BuddyPress
 */

/**
 * Whether this is a XMLRPC Request
 *
 * @var bool
 */
define( 'XMLRPC_REQUEST', true );

// Some browser-embedded clients send cookies. We don't want them.
$_COOKIE = array();

// A bug in PHP < 5.2.2 makes $HTTP_RAW_POST_DATA not set by default,
// but we can do it ourself.
if ( !isset( $HTTP_RAW_POST_DATA ) ) {
	$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );
}

// fix for cases where xml isn't on the very first line
if ( isset( $HTTP_RAW_POST_DATA ) )
	$HTTP_RAW_POST_DATA = trim( $HTTP_RAW_POST_DATA );

if ( isset( $_GET['rsd'] ) ) { // http://archipelago.phrasewise.com/rsd
header( 'Content-Type: text/xml; charset=' . get_option( 'blog_charset' ), true );
?>
<?php echo '<?xml version="1.0" encoding="'.get_option( 'blog_charset' ).'"?'.'>'; ?>
<rsd version="1.0" xmlns="http://archipelago.phrasewise.com/rsd">
  <service>
	<engineName>BuddyPress</engineName>
	<engineLink>http://buddypress.org/</engineLink>
	<homePageLink><?php bloginfo_rss( 'url' ) ?></homePageLink>
  </service>
  <apis>
	<api name="BuddyPress" blogID="1" preferred="true" apiLink="<?php echo BP_XMLRPC_URL ?>">
	  <settings>
		<docs>http://github.com/yuttadhammo/buddypress-xmlrpc-receiver/wiki</docs>
	  </settings>
	</api>
  </apis>
</rsd>
<?php
exit;
}

include_once( ABSPATH . WPINC . '/class-IXR.php' );

require_once( dirname( __FILE__ ) . '/includes/bp-xmlrpc-functions.php' );

/**
 * BuddyPress XMLRPC server implementation.
 *
 * @todo Use default Wordpress XMLRPC server implementation ?
 */
class bp_xmlrpc_server extends IXR_Server {

	/**
	 * Register all of the XMLRPC methods that XMLRPC server understands.
	 *
	 * @return bp_xmlrpc_server
	 */
	function bp_xmlrpc_server() {
		$this->methods = array(

			// add blogs status new_xmlrpc_blog_post
			'bp.updateExternalBlogPostStatus'	=> 'this:bp_xmlrpc_call_update_blog_post_status',
			'bp.deleteExternalBlogPostStatus'	=> 'this:bp_xmlrpc_call_delete_blog_post_status',

			// add profile status activity_update
			'bp.updateProfileStatus'			=> 'this:bp_xmlrpc_call_update_profile_status',
			'bp.deleteProfileStatus'			=> 'this:bp_xmlrpc_call_delete_profile_status',
			'bp.postComment'					=> 'this:bp_xmlrpc_call_update_post_comment',

			// get lists
			'bp.getMyFriends'					=> 'this:bp_xmlrpc_call_get_my_friends',
			'bp.getGroups'						=> 'this:bp_xmlrpc_call_get_groups',

			// messages / notifications
			'bp.getNotifications'				=> 'this:bp_xmlrpc_call_get_notifications',
			'bp.getMessages'					=> 'this:bp_xmlrpc_call_get_messages',
			'bp.sendMessage'					=> 'this:bp_xmlrpc_call_send_message',

			// get recent statuses
			'bp.getActivity'					=> 'this:bp_xmlrpc_call_get_activity',

			// members
			'bp.getMemberInfo'					=> 'this:bp_xmlrpc_call_get_member_info',
			'bp.deleteMember'					=> 'this:bp_xmlrpc_call_delete_member',

			// for services connecting: verify it
			'bp.verifyConnection'				=> 'this:bp_xmlrpc_call_verify_connection',

			// Maintain by Sarath <sarathtvmala@gmail.com>
			// 1. for accept or reject friend request
			'bp.getFriendRequestList'			=> 'this:bp_xmlrpc_call_get_friend_request_list',
			'bp.acceptFriendRequest'			=>	'this:bp_xmlrpc_call_accept_friend_request',
			'bp.rejectFriendRequest'			=> 'this:bp_xmlrpc_call_reject_friend_request'
			// </> //
		);

		$this->methods = apply_filters( 'bp_xmlrpc_methods', $this->methods );
	}

	function serve_request() {
		$this->IXR_Server( $this->methods );
	}

	/**
	 * Verify xmlrpc handshake
	 *
	 * @param array $args ($username, $password)
	 * @return array (success, message);
	 */
	function bp_xmlrpc_call_verify_connection( $args ) {
		global $bp;

		// Parse the arguments, assuming they're in the correct order
		$username = $this->escape( $args[0] );
		$password  = $this->escape( $args[1] );

		if ( !$this->login( $username, $password ) )
			return $this->error;

		return array(
			'confirmation' => true,
			'message'	  => 'Hello '. bp_core_get_user_displayname( $bp->loggedin_user->id )
		);
	}

	/**
	 * Get notifications.
	 *
	 * @param array $args ($username, $password, $data['type'], $data['status'])
	 * @return array (notifications);
	 * 
	 * type = simple/object
	 * status = is_new
	 */
	function bp_xmlrpc_call_get_notifications( $args ) {
		global $bp;

		//check options if this is callable
		$call = (array) maybe_unserialize( get_option( 'bp_xmlrpc_enabled_calls' ) );
		if ( !bp_xmlrpc_calls_enabled_check( 'bp.getNotifications', $call ) )
			return new IXR_Error( 405, __( 'XML-RPC call bp.getNotifications is disabled. ', 'bp-xmlrpc' ) );

		// Parse the arguments, assuming they're in the correct order
		$username = $this->escape( $args[0] );
		$password   = $this->escape( $args[1] );
		$data = @$args[2];

		if ( !$this->login( $username, $password ) )
			return $this->error;

		$user_id = $bp->loggedin_user->id;
		
		$ungrouped_notifications = BP_Core_Notification::get_all_for_user( $user_id, @$data['status'] );
		
		$grouped_notifications = array();
		foreach ( $ungrouped_notifications as $notification )
			$grouped_notifications[$notification->component_name][$notification->component_action][] = $notification;
			
		foreach($grouped_notifications as $component_name => $action_arrays) {

			// Skip if group is empty
			if ( empty( $action_arrays ) )
				continue;

			// Skip inactive components
			if ( !bp_is_active( $component_name ) )
				continue;

			foreach($action_arrays as $component_action_name => $component_action_items) {
						
				// Get the number of actionable items
				$action_item_count = count( $component_action_items );

				// Skip if the count is less than 1
				if ( $action_item_count < 1 )
					continue;

				// Callback function exists
				if ( isset( $bp->{$component_name}->notification_callback ) && function_exists( $bp->{$component_name}->notification_callback ) ) {

					// Function should return an object
					if ( 'object' == @$data['type'] ) {

						// Retrieve the content of the notification using the callback
						$content = call_user_func(
							$bp->{$component_name}->notification_callback,
							$component_action_name,
							$component_action_items[0]->item_id,
							$component_action_items[0]->secondary_item_id,
							$action_item_count,
							'array'
						);

						// Create the object to be returned
						$notification_object = new stdClass;

						// Minimal backpat with non-compatible notification
						// callback functions
						if ( is_string( $content ) ) {
							$notification_object->content = $content;
							$notification_object->href    = bp_loggedin_user_domain();
						} else {
							$notification_object->content = $content['text'];
							$notification_object->href    = $content['link'];
						}

						$notification_object->id = $component_action_items[0]->id;
						$notification_object->component = $component_name;
						$notification_object->action = $component_action_name;
						$notification_object->total = count($notification_array);

						$notifications[] = $notification_object;
						$count++;
					// Return an array of content strings
					} else {
						$content      = call_user_func( $bp->{$component_name}->notification_callback, $component_action_name, $component_action_items[0]->item_id, $component_action_items[0]->secondary_item_id, $action_item_count );
						$notifications[] = $content;
					}
				// @deprecated format_notification_function - 1.5
				} elseif ( isset( $bp->{$component_name}->format_notification_function ) && function_exists( $bp->{$component_name}->format_notification_function ) ) {
					$notifications[] = call_user_func( $bp->{$component_name}->format_notification_function, $component_action_name, $component_action_items[0]->item_id, $component_action_items[0]->secondary_item_id, $action_item_count );
				}
				
			}
		}

		$count = count($notifications);
		$output = array(
			'confirmation' => true,
			'total' => $count,
			);

		if ( $count > 0 )
				$output['message'] = (array) $notifications;	
		else
			$output['message'] = __( 'No new notifications.', 'bp-xmlrpc' );

		if(isset($data['active_components']))
			$output['active_components'] = $this->get_active_components();

		return $output;

	}


	/**
	 * Get messages.
	 *
	 * @param array $args ($username, $password, $data['box','type','full','page_num','pag_page','search_terms', 'action','action_id','action_data'])
	 * 
	 * if $data['full'] get all messages in thread, otherwise get latest
	 * 
	 * @return array (confirmation, total, message);
	 */
	function bp_xmlrpc_call_get_messages( $args ) {
		global $bp;

		//check options if this is callable
		$call = (array) maybe_unserialize( get_option( 'bp_xmlrpc_enabled_calls' ) );
		if ( !bp_xmlrpc_calls_enabled_check( 'bp.getMessages', $call ) )
			return new IXR_Error( 405, __( 'XML-RPC call bp.getMessages is disabled. ', 'bp-xmlrpc' ) );

		if ( !bp_is_active( 'messages' ) )
			return new IXR_Error( 1553, __( 'Messages Component Not Activated', 'bp-xmlrpc' ) );

		// Parse the arguments, assuming they're in the correct order
		$username = $this->escape( $args[0] );
		$password  = $this->escape( $args[1] );
		$data	 = $args[2];

		if ( !$this->login( $username, $password ) )
			return $this->error;
		
		$user_id = $bp->loggedin_user->id;

		// delete these notifications
		
		bp_core_delete_notifications_by_type(bp_loggedin_user_id(), $bp->messages->id, 'new_message');

		// actions
		
		if(isset($data['action'])) {
			$thread_id = (int) $data['action_id'];
			switch($data['action']) {
				case 'delete':
					messages_delete_thread( $thread_id );
					break;
				case 'reply':
					$content = $this->escape( $data['action_data'] );
					messages_new_message( array( 'thread_id' => $thread_id, 'content' =>  $content ) );
					break;
				case 'read':
					messages_mark_thread_read( $thread_id );
					break;
				case 'unread':
					messages_mark_thread_unread( $thread_id );
					break;
			}
		}
		
		$box = $this->escape( $data['box'] );
		$type = $this->escape( $data['type'] );
		$pag_num = $this->escape( $data['pag_num'] );
		$pag_page = $this->escape( $data['pag_page'] );
		$search_terms = $this->escape( $data['search_terms'] );

		$threads = BP_Messages_Thread::get_current_threads_for_user( $user_id, $box, $type, $pag_num, $pag_page, $search_terms);

		$output = array(
			'confirmation' => true,
			'total' => @$threads["total"]?$threads["total"]:0,
		);

		if ( $threads && $threads["total"] > 0 ) {
			
			if(@$data['full'])
				$output['message'] = $threads;
			else {
				foreach ($threads["threads"] as $idx => $thread) {
					$messages = (array)$thread->messages;
					$last = $messages[count($messages)-1];
					$msgs[$idx] = array(
						'thread_id' => $thread->thread_id,
						'count' => count($messages),
						'unread_count' => $thread->unread_count,
						'primary_link' => trailingslashit( bp_loggedin_user_domain() . $bp->messages->slug . '/view/' . $thread->thread_id ),
						'from' => bp_core_get_user_displayname($last->sender_id),
						'subject' => $last->subject,
						'message' => $last->message,
						'date_sent' => $last->date_sent,
					);
				}
				
				$output['message'] = $msgs;
				$output['total'] = count($threads["threads"]);
			}
		}
		else 
			$output['message'] = __( 'No messages.', 'bp-xmlrpc' );

		if(isset($data['user_data']))
			$output['user_data'] = $this->get_current_user_info();

		if(isset($data['active_components']))
			$output['active_components'] = $this->get_active_components();

		return $output;

	}


	/**
	 * send message.
	 *
	 * @param array $args ($username, $password, $data['thread_id','recipients','subject','content'])
	 * @return array (confirmation, message);
	 */
	function bp_xmlrpc_call_send_message( $args ) {
		global $bp;

		//check options if this is callable
		$call = (array) maybe_unserialize( get_option( 'bp_xmlrpc_enabled_calls' ) );
		if ( !bp_xmlrpc_calls_enabled_check( 'bp.sendMessage', $call ) )
			return new IXR_Error( 405, __( 'XML-RPC call bp.sendMessage is disabled. ', 'bp-xmlrpc' ) );

		if ( !bp_is_active( 'messages' ) )
			return new IXR_Error( 1553, __( 'Messages Component Not Activated', 'bp-xmlrpc' ) );

		// Parse the arguments, assuming they're in the correct order
		$username = $this->escape( $args[0] );
		$password  = $this->escape( $args[1] );
		$data	 = $args[2];

		if ( !$this->login( $username, $password ) )
			return $this->error;
		
		$user_id = $bp->loggedin_user->id;

		$thread_id = $this->escape( $data['thread_id'] );
		$subject = $this->escape( $data['subject'] );		
		$recipients = $this->escape( $data['recipients'] );		
		$content = $this->escape( $data['content'] );		
		
		if($thread_id == 'false')
			$thread_id = false;
		else $thread_id = (int)$thread_id;

		if ( messages_new_message( array( 'thread_id' => $thread_id, 'recipients' => $recipients, 'subject' => $subject, 'content' => $content ) ) ) {
			$output = array(
				'confirmation' => true,
				'message' => __( 'Message sent.', 'bp-xmlrpc' )
			);
		} 
		else {
			$output = array(
				'confirmation' => false,
				'message' => __( 'Error sending message.', 'bp-xmlrpc' )
			);
		}
		if(isset($data['active_components']))
			$output['active_components'] = $this->get_active_components();

		return $output;		
	}


	/**
	 * Add activity stream profile status.
	 *
	 * @param array $args ($username, $password, $data['status'] )
	 * @return array (activity_id,message,confirmation,url);
	 */
	function bp_xmlrpc_call_update_profile_status( $args ) {
		global $bp;

		//check options if this is callable
		$call = (array) maybe_unserialize( get_option( 'bp_xmlrpc_enabled_calls' ) );
		if ( !bp_xmlrpc_calls_enabled_check( 'bp.updateProfileStatus', $call ) )
			return new IXR_Error( 405, __( 'XML-RPC call bp.updateProfileStatus is disabled. ', 'bp-xmlrpc' ) );


		// Parse the arguments, assuming they're in the correct order
		$username = $this->escape( $args[0] );
		$password   = $this->escape( $args[1] );
		$data	 = $args[2];

		if ( !$this->login( $username, $password ) )
			return $this->error;

		if ( !$data['status'] )
			return new IXR_Error( 1550, __( 'Invalid Request - Missing content', 'bp-xmlrpc' ) );

		// Record this in activity streams
		$output['activity_id'] = bp_activity_post_update( array(
			'content' => apply_filters( 'bp_xmlrpc_update_profile_status_content', $this->escape( $data['status'] ) )
		) );

		if ( $output['activity_id'] ) {
			$output['message'] = __( 'Profile Update Posted!', 'bp-xmlrpc' );
			$output['confirmation'] = true;
			$output['url'] = bp_activity_get_permalink( $activity['activity_id'] );

			if(isset($data['active_components']))
				$output['active_components'] = $this->get_active_components();

			return $output;
		}

		return new IXR_Error( 1500, __( 'There was an error when posting your update, please try again.', 'bp-xmlrpc' ) );
	}


	/**
	 * Delete activity stream profile status
	 *
	 *
	 * @param array $args ($username, $password, $data['activity_id'] )
	 * @return array (activity_id,message,confirmation);
	 */
	function bp_xmlrpc_call_delete_profile_status( $args ) {
		global $bp;

		//check options if this is callable
		$call = (array) maybe_unserialize( get_option( 'bp_xmlrpc_enabled_calls' ) );
		if ( !bp_xmlrpc_calls_enabled_check( 'bp.deleteProfileStatus', $call ) )
			return new IXR_Error( 405, __( 'XML-RPC call bp.deleteProfileStatus is disabled. ', 'bp-xmlrpc' ) );

		// Parse the arguments, assuming they're in the correct order
		$username = $this->escape( $args[0] );
		$password  = $this->escape( $args[1] );
		$data	 = $args[2];

		if ( !$user = $this->login( $username, $password ) )
			return $this->error;
		
		if ( !@$data['activity_id'] && @$data['activityid'] ) // legacy
			$data['activity_id'] == $data['activityid'];
			
		if ( !@$data['activity_id'] )
			return new IXR_Error( 1553, __( 'Invalid Request - Missing content', 'bp-xmlrpc' ) );

		$activity = new BP_Activity_Activity( (int) $data['activity_id'] );

		// Check access
		if ( empty( $activity->user_id ) || ! bp_activity_user_can_delete( $activity ) )
			return new IXR_Error( 1554, __( 'Not allowed to delete activity!', 'bp-xmlrpc' ) );

		// Call the action before the delete so plugins can still fetch information about it
		do_action( 'bp_activity_before_action_delete_activity', $activity->id, $activity->user_id );

		$output['confirmation'] = bp_activity_delete( array( 'id' => $activity->id, 'user_id' => $activity->user_id ) );
		if ( ! $output['confirmation'])
			return new IXR_Error( 1554, __( 'Activity not found', 'bp-xmlrpc' ) );

		do_action( 'bp_activity_action_delete_activity', $activity->id, $activity->user_id );

		$output['message'] = __( 'Activity removed!', 'bp-xmlrpc' );
		
		if(isset($data['active_components']))
			$output['active_components'] = $this->get_active_components();
			
		return $output;

	}


	/**
	 * Add comment to activity stream item.
	 *
	 * @param array $args ($username, $password, $data['comment'], $data['activity_id'] )
	 * @return array (activity_id,message,confirmation,url);
	 *
	 *  TODO: add ability to reply to comments
	 */
	function bp_xmlrpc_call_update_post_comment( $args ) {
		global $bp;

		//check options if this is callable
		$call = (array) maybe_unserialize( get_option( 'bp_xmlrpc_enabled_calls' ) );
		if ( !bp_xmlrpc_calls_enabled_check( 'bp.postComment', $call ) )
			return new IXR_Error( 405, __( 'XML-RPC call bp.postComment is disabled. ', 'bp-xmlrpc' ) );


		// Parse the arguments, assuming they're in the correct order
		$username = $this->escape( $args[0] );
		$password  = $this->escape( $args[1] );
		$data	 = $args[2];

		if ( !$this->login( $username, $password ) )
			return $this->error;

		if ( !$data['comment'] || !$data['activity_id'] )
			return new IXR_Error( 1550, __( 'Invalid Request - Missing content', 'bp-xmlrpc' ) );

		// Record this in activity streams
		$output['activity_id'] = bp_activity_new_comment( array(
			'activity_id' => (int)$data['activity_id'],
			'content'	 => apply_filters( 'bp_xmlrpc_update_post_comment', $this->escape( $data['comment'] ) ),
			'parent_id'   => (int)$data['activity_id'], 
		) );

		if ( $output['activity_id'] ) {
			$output['message'] = __( 'Comment Posted!', 'bp-xmlrpc' );
			$output['confirmation'] = true;
			$output['url'] = bp_activity_get_permalink( $activity['activity_id'] );

			if(isset($data['active_components']))
				$output['active_components'] = $this->get_active_components();

			return $output;
		}

		return new IXR_Error( 1500, __( 'There was an error when posting your update, please try again.', 'bp-xmlrpc' ) );
	}




	/**
	 * Add activity stream blog status
	 *
	 * @param array $args ($username, $password, $data['status'], $data['blogtitle'], $data['blogurl'], $data['blogpostid'] )
	 * @return array (activity_id,message,confirmation,url);
	 */
	function bp_xmlrpc_call_update_blog_post_status( $args ) {
		global $bp;

		//check options if this is callable
		$call = (array) maybe_unserialize( get_option( 'bp_xmlrpc_enabled_calls' ) );
		if ( !bp_xmlrpc_calls_enabled_check( 'bp.updateExternalBlogPostStatus', $call ) )
			return new IXR_Error( 405, __( 'XML-RPC call bp.updateExternalBlogPostStatus is disabled. ', 'bp-xmlrpc' ) );

		// Parse the arguments, assuming they're in the correct order
		$username = $this->escape( $args[0] );
		$password  = $this->escape( $args[1] );
		$data	 = $args[2];

		if ( !$user = $this->login( $username, $password ) )
			return $this->error;


		if ( !$data['status'] )
			return new IXR_Error( 1550, __( 'Invalid Request - Missing content', 'bp-xmlrpc' ) );

		if ( !$data['blogtitle'] )
			return new IXR_Error( 1551, __( 'Invalid Request - Missing content', 'bp-xmlrpc' ) );

		if ( !$data['blogurl'] )
			return new IXR_Error( 1552, __( 'Invalid Request - Missing content', 'bp-xmlrpc' ) );

		if ( !$data['blogpostpermalink'] )
			return new IXR_Error( 1552, __( 'Invalid Request - Missing content', 'bp-xmlrpc' ) );

		if ( !$data['blogpostid'] )
			return new IXR_Error( 1553, __( 'Invalid Request - Missing content', 'bp-xmlrpc' ) );

		//need a blacklist or whitelist of urls to check


		$post_permalink = $this->escape( $data['blogpostpermalink'] );

		$activity_action = sprintf( __( '%s wrote a new blog post: %s', 'bp-xmlrpc' ),
			bp_core_get_userlink( $bp->loggedin_user->id ),
			'<a href="' . $post_permalink . '">' . $this->escape( apply_filters( 'bp_xmlrpc_blog_new_post_title', $data['blogtitle'] ) ) . '</a>' );

		$activity_content = $this->escape( $data['status'] );

		/* Record this in activity streams */
		$output['activity_id'] = bp_blogs_record_activity( array(
			'user_id'		   => $bp->loggedin_user->id,
			'action'			=> apply_filters( 'bp_xmlrpc_blog_new_post_action', $activity_action ),
			'content'		   => apply_filters( 'bp_xmlrpc_blog_new_post_content', $activity_content ),
			'primary_link'	  => $post_permalink,
			'type'			  => 'new_xmlrpc_blog_post',
			'secondary_item_id' => $this->escape( $data['blogpostid'] )
		) );

		if ( $output['activity_id'] ) {

			$output['message'] = __( 'Blog Update Posted!', 'bp-xmlrpc' );
			$output['confirmation'] = true;
			$output['url'] = bp_activity_get_permalink( $activity['activity_id'] );

			if(isset($data['active_components']))
				$output['active_components'] = $this->get_active_components();

			return $output;
		}

		return new IXR_Error( 1500, __( 'There was an error connecting, please try again.', 'bp-xmlrpc' ) );

	}

	/**
	 * Delete activity stream blog status
	 *
	 *
	 * @param array $args ($username, $password, $data['blogpostid','activity_id'] )
	 * @return array (activity_id,message,confirmation);
	 */
	function bp_xmlrpc_call_delete_blog_post_status( $args ) {
		global $bp;

		//check options if this is callable
		$call = (array) maybe_unserialize( get_option( 'bp_xmlrpc_enabled_calls' ) );
		if ( !bp_xmlrpc_calls_enabled_check( 'bp.deleteExternalBlogPostStatus', $call ) )
			return new IXR_Error( 405, __( 'XML-RPC call bp.deleteExternalBlogPostStatus is disabled. ', 'bp-xmlrpc' ) );

		// Parse the arguments, assuming they're in the correct order
		$username = $this->escape( $args[0] );
		$password  = $this->escape( $args[1] );
		$data	 = $args[2];

		if ( !$user = $this->login( $username, $password ) )
			return $this->error;


		if ( !$data['blogpostid'] )
			return new IXR_Error( 1553, __( 'Invalid Request - Missing content', 'bp-xmlrpc' ) );

		if ( !$data['activity_id'] )
			return new IXR_Error( 1553, __( 'Invalid Request - Missing content', 'bp-xmlrpc' ) );

		/* Record this in activity streams */
		$output['confirmation'] = bp_activity_delete( array(
			'id'				=> $this->escape( $data['activity_id'] ),
			'user_id'		   => $bp->loggedin_user->id,
			'secondary_item_id' => $this->escape( $data['blogpostid'] ),
			'component'		 => $bp->blogs->id,
			'type'			  => 'new_xmlrpc_blog_post'
		) );

		if ( $output['confirmation'] ) {
			$output['message'] = __( 'Update Removed!', 'bp-xmlrpc' );

			if(isset($data['active_components']))
				$output['active_components'] = $this->get_active_components();

			return $output;
		} else {
			return new IXR_Error( 1554, __( 'Activity not found', 'bp-xmlrpc' ) );
		}

		return new IXR_Error( 1500, __( 'There was an error connecting, please try again.', 'bp-xmlrpc' ) );

	}

	/**
	 * Get a list of user's friends
	 *
	 *
	 * @param array $args ($username, $password, $data['max'], $data['requests'])
	 * 
	 * requests shows only friend requests, otherwise show only friends
	 * 
	 * @return array friends;
	 */
	function bp_xmlrpc_call_get_my_friends( $args ) {
		global $bp;

		//check options if this is callable
		$call = (array) maybe_unserialize( get_option( 'bp_xmlrpc_enabled_calls' ) );
		if ( !bp_xmlrpc_calls_enabled_check( 'bp.getMyFriends', $call ) )
			return new IXR_Error( 405, __( 'XML-RPC call bp.getMyFriends is disabled.', 'bp-xmlrpc' ) );

		// Parse the arguments, assuming they're in the correct order
		$username = $this->escape( $args[0] );
		$password  = $this->escape( $args[1] );
		$data  = $args[2];

		if ( !$user = $this->login( $username, $password ) )
			return $this->error;

		$action_data = $data['action_data'];
		$action_id = $data['action_id'];

		// actions
		
		if(isset($data['action'])) {
			switch($data['action']) {
				case 'unfriend':
					friends_remove_friend( $bp->loggedin_user->id, $action_id );
					break;
				case 'request':
					friends_add_friend( $bp->loggedin_user->id, $action_id );
					break;
				case 'cancel':
					friends_withdraw_friendship( $bp->loggedin_user->id, $action_id );
					break;
				case 'reject':
					friends_reject_friendship( $action_id ); // friendship_id
					break;
				case 'accept':
					friends_accept_friendship( $action_id ); // friendship_id
					break;
			}
		}


		if(@$data['requests']) {
			bp_core_delete_notifications_by_type( bp_loggedin_user_id(), $bp->friends->id, 'friendship_request' );
			$friends = BP_Core_User::get_users( 'active', @$data['max']?$data['max']:0, 1, 0, bp_get_friendship_requests($bp->loggedin_user->id) );
		}
		else {
			// delete this type of notification
			bp_core_delete_notifications_by_type( bp_loggedin_user_id(), $bp->friends->id, 'friendship_accepted' );
			
			$friends = BP_Core_User::get_users( 'active', @$data['max']?$data['max']:0, 1, $bp->loggedin_user->id);
		}		

		if ( $friends ) {

			//loop and cleanse
			foreach ( (array)$friends['users'] as $key => $user ) {

				$user = (array)$user;

				//add some new stuff
				$user['user_atmention'] = apply_filters( 'bp_get_displayed_user_username', bp_core_get_username( $user['id'], $user['user_nicename'], $user['user_login'] ) );
				$user['user_domain'] = bp_core_get_user_domain( $user['id'] ) ;
				$user['avatar'] = array(
					'full' => bp_core_fetch_avatar( array( 'item_id' => $user['id'], 'type' => 'full', 'width' => false, 'height' => false, 'html' => false, 'alt' => '' ) ), 
					'thumb' => bp_core_fetch_avatar( array( 'item_id' => $user['id'], 'type' => 'thumb', 'width' => false, 'height' => false, 'html' => false, 'alt' => '' ) ) 
				);

				// parse update
				$update = maybe_unserialize( $user['latest_update'] );
				if($update)
					$user['latest_update'] = $update['content'];

				// add friendship_id
				
				if ( !$friendship_id = wp_cache_get( 'friendship_id_' . $user['id'] . '_' . bp_loggedin_user_id() ) ) {
					$friendship_id = friends_get_friendship_id( $user['id'], bp_loggedin_user_id() );
					wp_cache_set( 'friendship_id_' . $user['id'] . '_' . bp_loggedin_user_id(), $friendship_id, 'bp' );
				}
				$user['friendship_id'] = $friendship_id;

				//dump this other stuff we don't need
				//unset( $user['id'] );
				unset( $user['user_email'] );
				unset( $user['user_login'] );
				unset( $user['user_nicename'] );
				unset( $user['user_registered'] );
				unset( $user['is_friend'] );
				unset( $user['total_friend_count'] );

				$output['confirmation'] = true;
				$output['message'][$key] = $user;
			}
		} else {
			// not a true error - just lonely.
			$output['confirmation'] = true;
			$output['message'] = __( 'You haven\'t added any friend connections yet.', 'bp-xmlrpc' );
		}

		if(isset($data['user_data']))
			$output['user_data'] = $this->get_current_user_info();
		if(isset($data['active_components']))
			$output['active_components'] = $this->get_active_components();

		return $output;

	}


	/**
	 * Get a list of groups
	 *
	 *
	 * @param array $args ($username, $password, $data['max', 'user', 'action', 'action_id'] )
	 * 
	 * max = max to return
	 * user = true?for logged in user
	 * action performs some action on action_id first
	 * 
	 * @return array groups;
	 */
	function bp_xmlrpc_call_get_groups( $args ) {
		global $bp;

		if ( !bp_is_active( 'groups' ) )
			return new IXR_Error( 405, __( 'BuddyPress Groups is not activated.', 'bp-xmlrpc' ) );

		//check options if this is callable
		$call = (array) maybe_unserialize( get_option( 'bp_xmlrpc_enabled_calls' ) );
		if ( !bp_xmlrpc_calls_enabled_check( 'bp.getGroups', $call ) )
			return new IXR_Error( 405, __( 'XML-RPC call bp.getGroups is disabled.', 'bp-xmlrpc' ) );

		// Parse the arguments, assuming they're in the correct order
		$username = $this->escape( $args[0] );
		$password  = $this->escape( $args[1] );
		$data  = $args[2];

		if ( !$user = $this->login( $username, $password ) )
			return $this->error;

		$action_id = $this->escape(@$data['action_id']);
		$action_data = $this->escape(@$data['action_data']);

		// actions
		
		if(isset($data['action'])) {
			switch($data['action']) {
				case 'join':
					groups_join_group( $action_id, $bp->loggedin_user->id );
					break;
				case 'leave':
					groups_leave_group( $action_id, $bp->loggedin_user->id );
					break;
				case 'delete':
					groups_delete_group( $action_id );
					break;
				case 'post':
					groups_post_update( array(
						'content'  => $action_data,
						'user_id'  => bp_loggedin_user_id(),
						'group_id' => $action_id
					) );
					break;
				case 'create':
					if(!isset($data['group']))
						break;
					$group = $data['group'];
					if($group_id = groups_create_group( 
						array(
							'group_id' => 0,
							'name' => $group['name'],
							'description' => $group['desc'],
							'slug' => groups_check_slug( sanitize_title( esc_attr( $group['name'] ) ) ),
							'date_created' => bp_core_current_time(),
							'status' => $group['status']
						) 
					) ) {
						groups_update_groupmeta( $group_id, 'total_member_count', 1 );
						groups_update_groupmeta( $group_id, 'last_activity', bp_core_current_time() );
					}
					break;
			}
		}


		$max = @$data['max']?(int)$data['max']:0;

		// remove this type of notification
		if(@$data['user']) {
			bp_core_delete_notifications_by_type( bp_loggedin_user_id(), $bp->groups->id, 'membership_request_accepted' );
			bp_core_delete_notifications_by_type( bp_loggedin_user_id(), $bp->groups->id, 'member_promoted_to_mod'      );
			bp_core_delete_notifications_by_type( bp_loggedin_user_id(), $bp->groups->id, 'member_promoted_to_admin'    );
		}
		else {
			bp_core_delete_notifications_by_type( bp_loggedin_user_id(), $bp->groups->id, 'membership_request_rejected' );
		}

		if ( $groups = groups_get_groups( 
			array( 
				'user_id' => (@$data['user']?$bp->loggedin_user->id:false), 
				'per_page' => $max, 
				'page' => 1, 
				'populate_extras' => true 
			) 
		) ) {

			//loop and cleanse
			foreach ( (array)$groups['groups'] as $key => $group ) {

				$group = (array)$group;

				//add some new stuff
				$group['group_domain'] = apply_filters( 'bp_get_group_permalink', bp_core_get_root_domain() . '/' . $bp->groups->slug . '/' . $group['slug'] . '/' );
				$group['avatar'] = array(
					'full' => bp_core_fetch_avatar( array( 'item_id' => $group['id'], 'object' => 'group', 'avatar_dir' => 'group-avatars', 'alt' => __( 'Group Avatar', 'bp-xmlrpc' ), 'type' => 'full', 'width' => false, 'height' => false, 'html' => false ) ), 
					'thumb' => bp_core_fetch_avatar( array( 'item_id' => $group['id'], 'object' => 'group', 'avatar_dir' => 'group-avatars', 'alt' => __( 'Group Avatar', 'bp-xmlrpc' ), 'type' => 'thumb', 'width' => false, 'height' => false, 'html' => false ) ) 
				);

				//dump this other stuff we don't need
				//unset( $group['id'] );
				unset( $group['creator_id'] );
				//unset( $group['date_created'] );
				//unset( $group['slug'] );
				//unset( $group['status'] );
				unset( $group['enable_forum'] );

				$output['message'][$key] = $group;

			}

			$output['confirmation'] = true;

		} else {
			//not a true error - just lonely.
			$output['confirmation'] = true;
			$output['message'] = __( 'There were no groups found.', 'bp-xmlrpc' );
		}

		if(isset($data['user_data']))
			$output['user_data'] = $this->get_current_user_info();

		if(isset($data['active_components']))
			$output['active_components'] = $this->get_active_components();

		return $output;
	}

	/**
	 * Get an user's activity stream
	 *
	 *
	 * @param array $args ($username, $password, $data['scope','user_data','max','action','action_id','action_data'])
	 * 
	 * - scope filters the stream
	 * - if user_data is set, will include user data
	 * - if active_components is set, will include list of active components 
	 * - max is maximum number of items to return
	 * - action performs some action on action_id first
	 * 
	 * @return array activity
	 */
	function bp_xmlrpc_call_get_activity( $args ) {
		global $bp;

		if ( !bp_is_active( 'activity' ) )
			return new IXR_Error( 405, __( 'BuddyPress Activity Stream is not activated.', 'bp-xmlrpc' ) );

		//check options if this is callable
		$call = (array) maybe_unserialize( get_option( 'bp_xmlrpc_enabled_calls' ) );
		if ( !bp_xmlrpc_calls_enabled_check( 'bp.getActivity', $call ) )
			return new IXR_Error( 405, __( 'XML-RPC call bp.getActivity is disabled.', 'bp-xmlrpc' ) );


		// Parse the arguments, assuming they're in the correct order
		$username = $this->escape( $args[0] );
		$password  = $this->escape( $args[1] );
		$data	 = $args[2];

		if ( !$user = $this->login( $username, $password ) )
			return $this->error;

		if ( !$data['scope'] )
			return new IXR_Error( 1553, __( 'Invalid Request - Missing content', 'bp-xmlrpc' ) );

		$action_data = $data['action_data'];
		$action_id = $data['action_id'];

		// actions
		
		if(isset($data['action'])) {
			switch($data['action']) {
				case 'update':
					$this->bp_xmlrpc_call_update_profile_status(array($args[0],$args[1],array('status' => $action_data)));
					break;
				case 'delete':
					$this->bp_xmlrpc_call_delete_profile_status(array($args[0],$args[1],array('activity_id' => $action_id)));
					break;
				case 'comment':
					$this->bp_xmlrpc_call_update_post_comment(array($args[0],$args[1],array('activity_id' => $action_id, 'comment' => $action_data)));
					break;
			}
		}

		$data['scope'] = $this->escape( $data['scope'] );

		$max = @$data['max']?$this->escape($data['max']):20;
		
		$filter = array();
		
		$include = null;
		
		$search_terms = null;
		
		$show_hidden = 0;
		
		//set up our scopes of the activity stream to fetch
		switch(@$data['scope']) {
			case 'favorites':
				$include = implode( ',', (array)bp_activity_get_user_favorites( $bp->loggedin_user->id ) );
				if(strlen($include) == 0)
					$include = true;
				break;
			case 'friends':
				if(!bp_is_active( 'friends' ))
					return new IXR_Error( 405, __( 'Friends Component Not Activated', 'bp-xmlrpc' ) );

				$filter['user_id'] = implode( ',', (array)friends_get_friend_user_ids( $bp->loggedin_user->id ) );
				break;
			case 'groups':
				if(!bp_is_active( 'groups' ))
					return new IXR_Error( 405, __( 'Groups Component Not Activated', 'bp-xmlrpc' ) );

				$filter['object'] = $bp->groups->id;
				if ( $data['primary_id'] ) $defaults['primary_id'] = $data['primary_id'];
				break;
			case 'mentions':
				if(!bp_is_active( 'activity' ))
					return new IXR_Error( 405, __( 'Activity Component Not Activated', 'bp-xmlrpc' ) );
				
				// delete these notifications
				bp_core_delete_notifications_by_type( bp_loggedin_user_id(), $bp->activity->id, 'new_at_mention' );
			
				$search_terms = '@' . bp_core_get_username( $bp->loggedin_user->id, $bp->loggedin_user->userdata->user_nicename, $bp->loggedin_user->userdata->user_login );
				break;
			case 'sitewide':
				break;
			case 'just_me':
			case 'just-me':
				$show_hidden = 1;
				$filter['user_id'] = $bp->loggedin_user->id;
				break;
			case 'my_groups':
			case 'my-groups':
				if(!bp_is_active( 'groups' ))
					return new IXR_Error( 405, __( 'Groups Component Not Activated', 'bp-xmlrpc' ) );
				
				// delete these notifications
				bp_core_delete_all_notifications_by_type( bp_loggedin_user_id(), $bp->groups->id);	
				
				$show_hidden = 1;
				
				$groups = groups_get_user_groups( $bp->loggedin_user->id );
				$group_ids = implode( ',', $groups['groups'] );

				$filter['object'] = $bp->groups->id;
				$filter['primary_id'] = $group_ids;
				break;
			default:
				if ( is_string($data['scope']) )
					$filter['action'] = $this->escape($data['scope']);
				break;
		}

		$output = array();
		$output['confirmation'] = true;

		if ( $include ) {
			if($include === true) // nothing to include
				$activities = array();
			else
				$activities = bp_activity_get_specific( array( 'activity_ids' => explode( ',', $include ), 'max' => $max, 'per_page' => $max, 'page' => 1, 'sort' => 'DESC', 'display_comments' => 'threaded' ) );
		} else {
			$activities = bp_activity_get( array( 'display_comments' => 'threaded', 'max' => $max, 'per_page' => $max, 'page' => 1, 'sort' => 'DESC', 'search_terms' => $search_terms, 'filter' => $filter, 'show_hidden' => $show_hidden ) );
		}


		if ( @$activities['activities'] && !empty($activities['activities']) ) {

			//loop
			foreach ( $activities['activities'] as $key => $activity ) {

				$activity = (array)$activity;
				
				//add some new stuff
				$activity['user_atmention'] = apply_filters( 'bp_get_displayed_user_username', bp_core_get_username( $activity['user_id'], $activity['user_nicename'], $activity['user_login'] ) );
				$activity['user_domain'] = bp_core_get_user_domain( $activity['user_id'] ) ;
				$activity['user_avatar'] = bp_core_fetch_avatar( array( 'item_id' => $activity['user_id'], 'type' => 'thumb', 'email' => $activity['user_email'] ) );
				$activity['activity_id'] = $activity['id'];

				if ( 'new_blog_post' == $activity['type'] || 'new_blog_comment' == $activity['type'] || 'new_forum_topic' == $activity['type'] || 'new_forum_post' == $activity['type'] ) {

				} else {
					if ( 'activity_comment' == $activity['type'] ) {
						$activity['primary_link'] = bp_core_get_root_domain() . '/' . BP_ACTIVITY_SLUG . '/p/' . $activity['item_id'] . '/';
					} else {
						$activity['primary_link'] = bp_core_get_root_domain() . '/' . BP_ACTIVITY_SLUG . '/p/' . $activity['id'] . '/';
					}
				}

				// check if self
				
				$activity['self'] = $activity['user_id'] === $bp->loggedin_user->id;

				//dump this other stuff we don't need
				unset( $activity['id'] );
				//unset( $activity['user_id'] );
				unset( $activity['user_email'] );
				unset( $activity['user_login'] );
				unset( $activity['user_nicename'] );
				unset( $activity['mptt_left'] );
				unset( $activity['mptt_right'] );
				unset( $activity['hide_sitewide'] );
				unset( $activity['item_id'] );
				unset( $activity['secondary_item_id'] );
				unset( $activity['component'] );
				unset( $activity['type'] );

				$output['message'][$key] = $activity;
			}
			
		} else {
			//not a true error - just lonely.
			$output['message'] = __( 'There were no activity stream items found.', 'bp-xmlrpc' );
		}

		if(isset($data['user_data']))
			$output['user_data'] = $this->get_current_user_info();

		if(isset($data['active_components']))
			$output['active_components'] = $this->get_active_components();
		
		return $output;

	}
	
	/**
	 * Get a user's info
	 *
	 *
	 * @param array $args ($username, $password, $data['user_id','action','action_id','action_data'])
	 * 
	 * - action performs some action on action_id first (still not used)
	 * 
	 * @return array user
	 */
	function bp_xmlrpc_call_get_member_info( $args ) {
		global $bp;

		//check options if this is callable
		$call = (array) maybe_unserialize( get_option( 'bp_xmlrpc_enabled_calls' ) );
		if ( !bp_xmlrpc_calls_enabled_check( 'bp.getMemberInfo', $call ) )
			return new IXR_Error( 405, __( 'XML-RPC call bp.getMemberInfo is disabled.', 'bp-xmlrpc' ) );


		// Parse the arguments, assuming they're in the correct order
		$username = $this->escape( $args[0] );
		$password  = $this->escape( $args[1] );
		$data	 = $args[2];

		if ( !$user = $this->login( $username, $password ) )
			return $this->error;

		if ( !$data['user_id'] )
			return new IXR_Error( 1553, __( 'Invalid Request - Missing content', 'bp-xmlrpc' ) );

		$user_id = $this->escape($data['user_id']);

		// actions
		
		if(isset($data['action'])) {
			switch($data['action']) {
				case 'friend':
					if ( !bp_is_active( 'friends' ) )
						break;

					if(friends_check_friendship( bp_loggedin_user_id(), $user_id ))
						break;
					
					friends_add_friend( bp_loggedin_user_id(), $user_id );
					
					break;
				case 'unfriend':
					if ( !bp_is_active( 'friends' ) )
						break;

					if(!friends_check_friendship( bp_loggedin_user_id(), $user_id ))
						break;
					
					friends_remove_friend( bp_loggedin_user_id(), $user_id );
					
					break;
			}
		}

		$user = $this->get_member_info($user_id);

		$output = array(
			'confirmation' => true,
			'message' => $user,
		);		

		if(isset($data['active_components']))
			$output['active_components'] = $this->get_active_components();

		return $output;
	}

	/**
	 * Delete a member
	 *
	 *
	 * @param array $args ($username, $password, $data['user_id'])
	 * 
	 * 
	 * @return array message
	 */
	function bp_xmlrpc_call_delete_member( $args ) {
		global $bp;

		//check options if this is callable
		$call = (array) maybe_unserialize( get_option( 'bp_xmlrpc_enabled_calls' ) );
		if ( !bp_xmlrpc_calls_enabled_check( 'bp.deleteMember', $call ) )
			return new IXR_Error( 405, __( 'XML-RPC call bp.deleteMember is disabled.', 'bp-xmlrpc' ) );


		// Parse the arguments, assuming they're in the correct order
		$username = $this->escape( $args[0] );
		$password  = $this->escape( $args[1] );
		$data	 = $args[2];

		if ( !$user = $this->login( $username, $password ) )
			return $this->error;

		if ( !$data['user_id'] )
			return new IXR_Error( 1553, __( 'Invalid Request - Missing content', 'bp-xmlrpc' ) );

		$user_id = $this->escape($data['user_id']);

		$output['confirmation'] = bp_core_delete_account( $user_id );
		if ( ! $output['confirmation'])
			return new IXR_Error( 1554, __( 'Unable to delete member', 'bp-xmlrpc' ) );

		$output['message'] = __( 'Member deleted!', 'bp-xmlrpc' );
		if(isset($data['active_components']))
			$output['active_components'] = $this->get_active_components();

		return $output;

	}

	//** Maintain By Sarath <sarathtvmala@gmail.com >**//

	function bp_xmlrpc_call_get_friend_request_list( $args ) {
		global $bp;

		//check options if this is callable
		$call = (array) maybe_unserialize( get_option( 'bp_xmlrpc_enabled_calls' ) );
		if ( !bp_xmlrpc_calls_enabled_check( 'bp.deleteMember', $call ) )
			return new IXR_Error( 405, __( 'XML-RPC call bp.deleteMember is disabled.', 'bp-xmlrpc' ) );

		// Parse the arguments, assuming they're in the correct order
		$username = $this->escape( $args[0] );
		$password  = $this->escape( $args[1] );
		$data	 = $args[2];

		if ( !$user = $this->login( $username, $password ) )
			return $this->error;

		/*if ( !$data['user_id'] )
			return new IXR_Error( 1553, __( 'Invalid Request - Missing content', 'bp-xmlrpc' ) );

		$user_id = $this->escape($data['user_id']);

		*/
		// Call bp Friend Requsert Functions (friends_accept_friendship or friends_reject_friendship)

		$user_id = $bp->loggedin_user->id;
		$request_ids=friends_get_friendship_request_user_ids($user_id); //bp-friends-functions.php
		$output = array(
			'confirmation' => true,
			'message' => []
		);
		$requests=[];
		if(!empty($request_ids)){
			foreach($request_ids as $key=>$req_id){
				$tmp=[];
				$tmp['user_id']=$req_id;
				$tmp['display_name']=bp_core_get_user_displayname($req_id);//bp-members-functions.php
				//$tmp['avatar']=bp_core_fetch_avatar ( array( 'item_id' => $req_id, 'type' => 'full' ) );
				$tmp['avatar']=bp_core_fetch_avatar ( array( 'item_id' => $req_id, 'type' => 'thumb' ) );
				$output['message'][$key]=$tmp;

			}
		}


		

		return $output;
	}



	//Accept Friend Request
	//$data['initiator_user_id']

	function bp_xmlrpc_call_accept_friend_request($args){
		global $bp;
		//check options if this is callable
		$call = (array) maybe_unserialize( get_option( 'bp_xmlrpc_enabled_calls' ) );
		if ( !bp_xmlrpc_calls_enabled_check( 'bp.deleteMember', $call ) )
			return new IXR_Error( 405, __( 'XML-RPC call bp.deleteMember is disabled.', 'bp-xmlrpc' ) );

		// Parse the arguments, assuming they're in the correct order
		$username = $this->escape( $args[0] );
		$password  = $this->escape( $args[1] );
		$data	 = $args[2];

		if ( !$user = $this->login( $username, $password ) )
			return $this->error;

		
		if ( !$data['initiator_user_id'] )
			return new IXR_Error( 1553, __( 'Invalid Request - Missing content', 'bp-xmlrpc' ) );
		
		$output = array(
			'confirmation' => true,
			'message' => "Error Accepting Friend Request"
		);

		
		if(friends_accept_friendship($data['initiator_user_id'])){

			$output['message']="Friend Request Accepted";

		}

		return $output;

	}

	//Accept Friend Request
	//$data['initiator_user_id']

	function bp_xmlrpc_call_reject_friend_request($args){
		global $bp;
		//check options if this is callable
		$call = (array) maybe_unserialize( get_option( 'bp_xmlrpc_enabled_calls' ) );
		if ( !bp_xmlrpc_calls_enabled_check( 'bp.deleteMember', $call ) )
			return new IXR_Error( 405, __( 'XML-RPC call bp.deleteMember is disabled.', 'bp-xmlrpc' ) );

		// Parse the arguments, assuming they're in the correct order
		$username = $this->escape( $args[0] );
		$password  = $this->escape( $args[1] );
		$data	 = $args[2];

		if ( !$user = $this->login( $username, $password ) )
			return $this->error;

		
		if ( !$data['initiator_user_id'] )
			return new IXR_Error( 1553, __( 'Invalid Request - Missing content', 'bp-xmlrpc' ) );
		
		$output = array(
			'confirmation' => true,
			'message' => "Error Rejecting Friend Request"
		);

		
		if(friends_reject_friendship($data['initiator_user_id'])){

			$output['message']="Friend Request Rejected";

		}

		return $output;

	}



	//** ** //


	/**
	 * Log user in.
	 *
	 * @param string $username  user's username.
	 * @param string $password  user's password.
	 * @return mixed WP_User object if authentication passed, false otherwise
	 */

	function login( $username, $password ) {
		
		global $bp;

		if ( !get_option( 'bp_xmlrpc_enabled' ) ) {
			$this->error = new IXR_Error( 405, __( 'XML-RPC services disabled on this blog.', 'bp-xmlrpc' ) );
			return false;
		}

		$user = wp_authenticate($username, $password);

		if (is_wp_error($user)) {
			$this->error = new IXR_Error( 403, __( 'Incorrect username or password.' ) );
			$this->error = apply_filters( 'xmlrpc_login_error', $this->error, $user );
			return false;
		}

		wp_set_current_user( $user->ID );

		if ( !current_user_can( get_option( 'bp_xmlrpc_cap_low' ) ) ) {
			$this->error = new IXR_Error( 405, __( 'XML-RPC services disabled on this user capability.', 'bp-xmlrpc' ) );
			return false;
		}

		// awaken bp
		if ( !defined( BP_VERSION ) )
			do_action( 'bp_init' );

		if ( !$bp->loggedin_user->id ) {
			$this->error = new IXR_Error( 1512, __( 'Invalid Request - User', 'bp-xmlrpc' ) );
			return false;
		}

		if(!$this->check_user_allowed( $username )) {
			$this->error = array(
				'confirmation' => false,
				'need_access' => true,
				'message'	  => __( 'XML-RPC services not allowed for this user. Please request access from admin.', 'bp-xmlrpc' )
			);
			return false;
		}

		return $user;
	}


	/**
	 * Check User Allowed
	 *
	 * @param string $username  user's username.
	 * @return true if user is allowed to access functions (or per user allowance not true), false otherwise
	 */

	function check_user_allowed( $username ) {
		
		if(!get_option('bp_xmlrpc_require_approval'))
			return true;
		
		$users = explode("\n",get_option('bp_xmlrpc_allowed_users'));
		if(in_array($username,$users))
			return true;

		return false;
	}

	/**
	 * Actually get a user's info (internal function
	 *
	 *
	 * @param $user_id
	 * 
	 * @return array user
	 */
	function get_member_info( $user_id ) {

		$ud = get_userdata( $user_id ); 
		
		if(!$ud)
			return new IXR_Error( 1553, __( 'Invalid Request - User not found', 'bp-xmlrpc' ) );
		
		$user = array();

		$user['display_name'] = $ud->display_name;
		$user['user_nicename'] = $ud->user_nicename;
		$user['last_active'] = bp_get_last_activity( $user_id );
		$user['last_status'] = bp_get_activity_latest_update( $user_id );
		$user['primary_link'] = bp_core_get_user_domain($user_id) . $bp->profile->slug;
		if ( bp_is_active( 'friends' ) && (int)bp_loggedin_user_id() !== (int)$user_id )
			$user['friendship'] = BP_Friends_Friendship::check_is_friend( bp_loggedin_user_id(), $user_id );
		$user['avatar'] = array(
			'full' => bp_core_fetch_avatar( array( 'item_id' => $user_id, 'type' => 'full', 'width' => false, 'height' => false, 'html' => false, 'alt' => '' ) ), 
			'thumb' => bp_core_fetch_avatar( array( 'item_id' => $user_id, 'type' => 'thumb', 'width' => false, 'height' => false, 'html' => false, 'alt' => '' ) ) 
		);

		$idx = 0;

		if ( bp_is_active( 'xprofile' ) ) {

			if ( bp_has_profile(array('user_id' => $user_id)) ) {
				
				while ( bp_profile_groups() ) : bp_the_profile_group();

					if ( bp_profile_group_has_fields() ) :
						$user['profile_groups'][$idx] = array(
							'label' => bp_get_the_profile_group_name(),
							'edit_link' => ($bp->loggedin_user->id === $user_id ? bp_loggedin_user_domain() . $bp->profile->slug . '/edit/group/'.($idx+1) :''),
						);

						while ( bp_profile_fields() ) : bp_the_profile_field();

							if ( bp_field_has_data() ) :

								$user['profile_groups'][$idx]['fields'][] = array(
									'label' => bp_get_the_profile_field_name(),
									'value' => bp_get_the_profile_field_value()
								);										

							endif;

						endwhile;


					endif;
					
					$idx++;
					
				endwhile;
			}
		}
		
		// Display WordPress profile (fallback)
		else {

			if ( $ud->display_name ) 
				$user['profile_groups'][0][] = array(
					'label' => _e( 'Name', 'buddypress' ),
					'value' => $ud->display_name
				);
			
			if ( $ud->user_description )
				$user['profile_groups'][0][] = array(
					'label' => _e( 'About Me', 'buddypress' ),
					'value' => $ud->user_description
				);

			if ( $ud->user_url )
				$user['profile_groups'][0][] = array(
					'label' => _e( 'Website', 'buddypress' ),
					'value' => $ud->user_url
				);
			
			if ( $ud->jabber )
				$user['profile_groups'][0][] = array(
					'label' => _e( 'Jabber', 'buddypress' ),
					'value' => $ud->jabber
				);
			
			if ( $ud->aim )
				$user['profile_groups'][0][] = array(
					'label' => _e( 'AOL Messenger', 'buddypress' ),
					'value' => $ud->aim
				);
			
			if ( $ud->yim )
				$user['profile_groups'][0][] = array(
					'label' => _e( 'Yahoo Messenger', 'buddypress' ),
					'value' => $ud->yim
				);
		}

		$user['can_delete_user'] = bp_current_user_can( 'delete_users' ) && !bp_disable_account_deletion();

		return $user;
	}


	/**
	 * Get Current User Info
	 *
	 * @return array with info about current user
	 */

	function get_current_user_info() {
		
		global $bp;

		$array = array();

		$array['display_name'] = $bp->loggedin_user->display_name;
		$array['message_count'] = messages_get_unread_count($bp->loggedin_user->id);
		if(function_exists('bp_friend_get_total_requests_count'))
			$array['friend_request_count'] = bp_friend_get_total_requests_count();
		$array['domain'] = bp_loggedin_user_domain();
		$array['notifications'] = (array) bp_core_get_notifications_for_user( $bp->loggedin_user->id );
		$array['can_delete_user'] = bp_current_user_can( 'delete_users' ) && !bp_disable_account_deletion();
		$array['can_moderate'] = bp_current_user_can( 'bp_moderate' );

		return $array;
	}

	/**
	 * Turn associative array into indexed list of active components
	 *
	 * @return array
	 */
	function get_active_components() {
		$output = array();
		
		$comps = apply_filters( 'bp_active_components', bp_get_option( 'bp-active-components' ));
		foreach($comps as $comp => $active) {
			$output[] = $comp;
		}
		sort($output);
		return $output;
	}

	/**
	 * Sanitize string or array of strings for database.
	 *
	 * @param string|array $array Sanitize single string or array of strings.
	 * @return void
	 */
	function escape( &$array ) {
		global $wpdb;

		if( !is_array( $array ) ) {
			return( $wpdb->escape( $array ) );
		}
		else {
			foreach ( (array) $array as $key => $value ) {
				if ( is_array( $value ) ) {
					$this->escape( $array[$key] );
				}
				else if ( is_object( $value ) ) {
					//skip
				}
				else {
					$array[$key] = $wpdb->escape( $value );
				}
			}
		}
	}

}



// start the server
$bp_xmlrpc_server = new bp_xmlrpc_server();
$bp_xmlrpc_server->serve_request();
