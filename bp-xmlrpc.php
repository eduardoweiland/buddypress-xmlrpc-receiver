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
        <docs>http://github.com/duduweiland/buddypress-xmlrpc-receiver/wiki</docs>
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
            'bp.updateExternalBlogPostStatus'   => 'this:bp_xmlrpc_call_update_blog_post_status',
            'bp.deleteExternalBlogPostStatus'   => 'this:bp_xmlrpc_call_delete_blog_post_status',

            // add profile status activity_update
            'bp.updateProfileStatus'            => 'this:bp_xmlrpc_call_update_profile_status',
            //'bp.postComment'                  => 'this:bp_xmlrpc_call_update_post_comment',

            // get mylists
            'bp.getMyFriends'                   => 'this:bp_xmlrpc_call_get_my_friends',
            'bp.getMyFollowers'                 => 'this:bp_xmlrpc_call_get_my_followers',
            'bp.getMyFollowing'                 => 'this:bp_xmlrpc_call_get_my_following',
            'bp.getMyGroups'                    => 'this:bp_xmlrpc_call_get_my_groups',

            // get notifications
            'bp.getNotifications'               => 'this:bp_xmlrpc_call_get_notifications',

            // get recent statuses
            'bp.getActivity'                    => 'this:bp_xmlrpc_call_get_activity',

            // for services connecting: request ApiKey and verify it
            'bp.requestApiKey'                  => 'this:bp_xmlrpc_call_request_apikey',
            'bp.verifyConnection'               => 'this:bp_xmlrpc_call_verify_connection',
        );

        $this->methods = apply_filters( 'bp_xmlrpc_methods', $this->methods );
    }

    function serve_request() {
        $this->IXR_Server( $this->methods );
    }

    /**
     * Allow a remote service to request an ApiKey to connect.
     *
     * @param array $args An array with 2 values:<br>
     * <ul>
     *   <li>The first should be the user's username</li>
     *   <li>The second value is the service name, which will be displayed in the
     *   user settings page.</li>
     * </ul>
     * @return array An associative array with the following indices:<br>
     * <ul>
     *   <li><strong>confirmation</strong> (boolean): succcess or not</li>
     *   <li><strong>apikey</strong> (string): the ApiKey</li>
     * </ul>
     */
    function bp_xmlrpc_call_request_apikey( $args ) {
        global $bp;

        // parse the arguments, assuming they're in the correct order
        $username = $this->escape( $args[0] );
        $service  = $this->escape( $args[1] );

        // verify if the user exists
        $userdata = get_user_by( 'login', $username );

        if ( !$userdata ) {
            $this->error = new IXR_Error( 1510, __( 'Invalid Request - User', 'bp-xmlrpc' ) );
            return false;
        }

        $key = bp_xmlrpc_generate_apikey( $userdata->ID, $service );

        if ( $key ) {
            return array( 'confirmation' => true, 'apikey' => $key );
        }

        return new IXR_Error( 1500, __( 'There was an error connecting, please try again.', 'bp-xmlrpc' ) );
    }

    /**
     * Verify xmlrpc handshake
     *
     * @param array $args ($username, $service, $apikey)
     * @return array (confirmation, message);
     */
    function bp_xmlrpc_call_verify_connection( $args ) {
        global $bp;

        // Parse the arguments, assuming they're in the correct order
        $username = $this->escape( $args[0] );
        $service  = $this->escape( $args[1] );
        $apikey   = $this->escape( $args[2] );

        if ( !$this->login( $username, $service, $apikey ) )
            return $this->error;

        if ( $bp->loggedin_user->id ) {
            return array(
                'confirmation' => true,
                'message'      => 'Hello '. bp_core_get_user_displayname( $bp->loggedin_user->id )
            );
        }

        return new IXR_Error( 1500, __( 'There was an error connecting, please try again.', 'bp-xmlrpc' ) );
    }

    /**
     * Get notifications.
     *
     * @param array $args ($username, $password)
     * @return array (notifications);
     */
    function bp_xmlrpc_call_get_notifications( $args ) {
        global $bp;

        //check options if this is callable
        $call = (array) maybe_unserialize( get_option( 'bp_xmlrpc_enabled_calls' ) );
        if ( !bp_xmlrpc_calls_enabled_check( 'bp.getNotifications', $call ) )
            return new IXR_Error( 405, __( 'XML-RPC call bp.getNotifications is disabled. ', 'bp-xmlrpc' ) );

        // Parse the arguments, assuming they're in the correct order
        $username = $this->escape( $args[0] );
        $service  = $this->escape( $args[1] );
        $apikey   = $this->escape( $args[2] );

        if ( !$this->login( $username, $service, $apikey ) )
            return $this->error;

        $notifications = (array) bp_core_get_notifications_for_user( $bp->loggedin_user->id );

        if ( $notifications ) {
            return array(
                'confirmation' => true,
                'message' => (array) $notifications
            );
        } else {
            return array(
                'confirmation' => true,
                'message' => __( 'No new notifications.', 'bp-xmlrpc' )
            );
        }

        return new IXR_Error( 1500, __( 'There was an error connecting, please try again.', 'bp-xmlrpc' ) );
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
        $service  = $this->escape( $args[1] );
        $apikey   = $this->escape( $args[2] );
        $data     = $args[3];

        if ( !$this->login( $username, $service, $apikey ) )
            return $this->error;

        if ( !$data['status'] )
            return new IXR_Error( 1550, __( 'Invalid Request - Missing content', 'bp-xmlrpc' ) );

        // Record this in activity streams
        $activity['activity_id'] = bp_activity_post_update( array(
            'content' => apply_filters( 'bp_xmlrpc_update_profile_status_content', $this->escape( $data['status'] ) )
        ) );

        if ( $activity['activity_id'] ) {
            $activity['message'] = __( 'Profile Update Posted!', 'bp-xmlrpc' );
            $activity['confirmation'] = true;
            $activity['url'] = bp_activity_get_permalink( $activity['activity_id'] );

            return $activity;
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
        $service  = $this->escape( $args[1] );
        $apikey   = $this->escape( $args[2] );
        $data     = $args[3];

        if ( !$user = $this->login( $username, $service, $apikey ) )
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
        $activity['activity_id'] = bp_blogs_record_activity( array(
            'user_id'           => $bp->loggedin_user->id,
            'action'            => apply_filters( 'bp_xmlrpc_blog_new_post_action', $activity_action ),
            'content'           => apply_filters( 'bp_xmlrpc_blog_new_post_content', $activity_content ),
            'primary_link'      => $post_permalink,
            'type'              => 'new_xmlrpc_blog_post',
            'secondary_item_id' => $this->escape( $data['blogpostid'] )
        ) );

        if ( $activity['activity_id'] ) {

            $activity['message'] = __( 'Blog Update Posted!', 'bp-xmlrpc' );
            $activity['confirmation'] = true;
            $activity['url'] = bp_activity_get_permalink( $activity['activity_id'] );

            return $activity;
        }

        return new IXR_Error( 1500, __( 'There was an error connecting, please try again.', 'bp-xmlrpc' ) );

    }

    /**
     * Delete activity stream profile status
     *
     *
     * @param array $args ($username, $password, $data['blogpostid'] )
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
        $service  = $this->escape( $args[1] );
        $apikey   = $this->escape( $args[2] );
        $data     = $args[3];

        if ( !$user = $this->login( $username, $service, $apikey ) )
            return $this->error;


        if ( !$data['blogpostid'] )
            return new IXR_Error( 1553, __( 'Invalid Request - Missing content', 'bp-xmlrpc' ) );

        if ( !$data['activityid'] )
            return new IXR_Error( 1553, __( 'Invalid Request - Missing content', 'bp-xmlrpc' ) );

        /* Record this in activity streams */
        $activity['confirmation'] = bp_activity_delete( array(
            'id'                => $this->escape( $data['activityid'] ),
            'user_id'           => $bp->loggedin_user->id,
            'secondary_item_id' => $this->escape( $data['blogpostid'] ),
            'component'         => $bp->blogs->id,
            'type'              => 'new_xmlrpc_blog_post'
        ) );

        if ( $activity['confirmation'] ) {
            $activity['message'] = __( 'Update Removed!', 'bp-xmlrpc' );
            return $activity;
        } else {
            return new IXR_Error( 1554, __( 'Activity not found', 'bp-xmlrpc' ) );
        }

        return new IXR_Error( 1500, __( 'There was an error connecting, please try again.', 'bp-xmlrpc' ) );

    }

    /**
     * Get a list of user's friends
     *
     *
     * @param array $args ($username, $password )
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
        $service  = $this->escape( $args[1] );
        $apikey   = $this->escape( $args[2] );

        if ( !$user = $this->login( $username, $service, $apikey ) )
            return $this->error;

        if ( $friends = friends_get_recently_active( $bp->loggedin_user->id, $per_page = 35, $page = 1 ) ) {

            //loop and cleanse
            foreach ( (array)$friends['users'] as $key => $user ) {

                $user = (array)$user;

                //add some new stuff
                $user['user_atmention'] = apply_filters( 'bp_get_displayed_user_username', bp_core_get_username( $user['id'], $user['user_nicename'], $user['user_login'] ) );
                $user['user_domain'] = bp_core_get_user_domain( $user['id'] ) ;
                $user['user_avatar'] = bp_core_fetch_avatar( array( 'item_id' => $user['id'], 'type' => 'thumb', 'email' => $user['user_email'] ) );
                //$user['user_id'] = $user['id'];

                //dump this other stuff we don't need
                unset( $user['id'] );
                unset( $user['user_email'] );
                unset( $user['user_login'] );
                unset( $user['user_nicename'] );
                unset( $user['user_registered'] );
                unset( $user['is_friend'] );
                unset( $user['total_friend_count'] );

                $friends['users'][$key] = $user;

            }

            return $friends;

        } else {
            // not a true error - just lonely.
            $activity['confirmation'] = true;
            $activity['message'] = __( 'You haven\'t added any friend connections yet.', 'bp-xmlrpc' );
            return $activity;
        }

        return new IXR_Error( 1500, __( 'There was an error connecting, please try again.', 'bp-xmlrpc' ) );

    }

    /**
     * Get a list of user's following
     *
     *
     * @param array $args ( $username, $password )
     * @return array friends;
     */
    function bp_xmlrpc_call_get_my_following( $args ) {
        global $bp;

        //check options if this is callable
        $call = (array) maybe_unserialize( get_option( 'bp_xmlrpc_enabled_calls' ) );
        if ( !bp_xmlrpc_calls_enabled_check( 'bp.getMyFollowing', $call ) )
            return new IXR_Error( 405, __( 'XML-RPC call bp.getMyFollowing is disabled.', 'bp-xmlrpc' ) );

        if ( !function_exists( 'bp_get_following_ids' ) )
            return new IXR_Error( 405, __( 'BuddyPress Followers plugin is not activated.', 'bp-xmlrpc' ) );

        // Parse the arguments, assuming they're in the correct order
        $username = $this->escape( $args[0] );
        $service  = $this->escape( $args[1] );
        $apikey   = $this->escape( $args[2] );

        if ( !$user = $this->login( $username, $service, $apikey ) )
            return $this->error;

        if ( $following = bp_xmlrpc_get_following_recently_active( $bp->loggedin_user->id, $per_page = 35, $page = 1, $filter = implode( ',', (array)BP_Follow::get_following( $bp->loggedin_user->id ) ) ) ) {

            //loop and cleanse
            foreach ( (array)$following['users'] as $key => $user ) {

                $user = (array)$user;

                //add some new stuff
                $user['user_atmention'] = apply_filters( 'bp_get_displayed_user_username', bp_core_get_username( $user['id'], $user['user_nicename'], $user['user_login'] ) );
                $user['user_domain'] = bp_core_get_user_domain( $user['id'] ) ;
                $user['user_avatar'] = bp_core_fetch_avatar( array( 'item_id' => $user['id'], 'type' => 'thumb', 'email' => $user['user_email'] ) );
                //$user['user_id'] = $user['id'];

                //dump this other stuff we don't need
                unset( $user['id'] );
                unset( $user['user_email'] );
                unset( $user['user_login'] );
                unset( $user['user_nicename'] );
                unset( $user['user_registered'] );
                unset( $user['total_friend_count'] );

                $following['users'][$key] = $user;

            }

            return $following;

        } else {
            //not a true error - just lonely.
            $activity['confirmation'] = true;
            $activity['message'] = __( 'Sorry, this member has no followers.', 'bp-xmlrpc' );
            return $activity;
        }

        return new IXR_Error( 1500, __( 'There was an error connecting, please try again.', 'bp-xmlrpc' ) );

    }

    /**
     * Get a list of user's following
     *
     *
     * @param array $args ($username, $password )
     * @return array friends;
     */
    function bp_xmlrpc_call_get_my_followers( $args ) {
        global $bp;

        //check options if this is callable
        $call = (array) maybe_unserialize( get_option( 'bp_xmlrpc_enabled_calls' ) );
        if ( !bp_xmlrpc_calls_enabled_check( 'bp.getMyFollowers', $call ) )
            return new IXR_Error( 405, __( 'XML-RPC call bp.getMyFollowers is disabled.', 'bp-xmlrpc' ) );

        if ( !function_exists( 'bp_get_follower_ids' ) )
            return new IXR_Error( 405, __( 'BuddyPress Followers is not activated.', 'bp-xmlrpc' ) );

        // Parse the arguments, assuming they're in the correct order
        $username = $this->escape( $args[0] );
        $service  = $this->escape( $args[1] );
        $apikey   = $this->escape( $args[2] );

        if ( !$user = $this->login( $username, $service, $apikey ) )
            return $this->error;

        if ( $followers = bp_xmlrpc_get_following_recently_active( $bp->loggedin_user->id, $per_page = 35, $page = 1, $filter = implode( ',', (array)BP_Follow::get_followers( $bp->loggedin_user->id ) ) ) ) {

            //loop and cleanse
            foreach ( (array)$followers['users'] as $key => $user ) {

                $user = (array)$user;

                //add some new stuff
                $user['user_atmention'] = apply_filters( 'bp_get_displayed_user_username', bp_core_get_username( $user['id'], $user['user_nicename'], $user['user_login'] ) );
                $user['user_domain'] = bp_core_get_user_domain( $user['id'] ) ;
                $user['user_avatar'] = bp_core_fetch_avatar( array( 'item_id' => $user['id'], 'type' => 'thumb', 'email' => $user['user_email'] ) );
                //$user['user_id'] = $user['id'];

                //dump this other stuff we don't need
                unset( $user['id'] );
                unset( $user['user_email'] );
                unset( $user['user_login'] );
                unset( $user['user_nicename'] );
                unset( $user['user_registered'] );
                unset( $user['total_friend_count'] );

                $followers['users'][$key] = $user;

            }

            return $followers;

        } else {
            //not a true error - just lonely.
            $activity['confirmation'] = true;
            $activity['message'] = __( 'Sorry, this member has no following.', 'bp-xmlrpc' );
            return $activity;
        }

        return new IXR_Error( 1500, __( 'There was an error connecting, please try again.', 'bp-xmlrpc' ) );

    }

    /**
     * Get a list of user's groups
     *
     *
     * @param array $args ($username, $password )
     * @return array groups;
     */
    function bp_xmlrpc_call_get_my_groups( $args ) {
        global $bp;

        if ( !bp_is_active( 'groups' ) )
            return new IXR_Error( 405, __( 'BuddyPress Groups is not activated.', 'bp-xmlrpc' ) );

        //check options if this is callable
        $call = (array) maybe_unserialize( get_option( 'bp_xmlrpc_enabled_calls' ) );
        if ( !bp_xmlrpc_calls_enabled_check( 'bp.getMyFriends', $call ) )
            return new IXR_Error( 405, __( 'XML-RPC call bp.getMyFriends is disabled.', 'bp-xmlrpc' ) );

        // Parse the arguments, assuming they're in the correct order
        $username = $this->escape( $args[0] );
        $service  = $this->escape( $args[1] );
        $apikey   = $this->escape( $args[2] );

        if ( !$user = $this->login( $username, $service, $apikey ) )
            return $this->error;

        if ( $groups = groups_get_groups( array( 'user_id' => $bp->loggedin_user->id, 'per_page' => 35, 'page' => 1, 'populate_extras' => false ) ) ) {

            //loop and cleanse
            foreach ( (array)$groups['groups'] as $key => $group ) {

                $group = (array)$group;

                //add some new stuff
                $group['group_domain'] = apply_filters( 'bp_get_group_permalink', bp_core_get_root_domain() . '/' . $bp->groups->slug . '/' . $group['slug'] . '/' );
                $group['group_avatar'] = bp_core_fetch_avatar( array( 'item_id' => $group['id'], 'object' => 'group', 'type' => 'thumb', 'avatar_dir' => 'group-avatars', 'alt' => __( 'Group Avatar', 'bp-xmlrpc' ) ) );
                $group['group_id'] = $group['id'];

                //dump this other stuff we don't need
                unset( $group['id'] );
                unset( $group['creator_id'] );
                unset( $group['date_created'] );
                unset( $group['slug'] );
                unset( $group['status'] );
                unset( $group['enable_forum'] );

                $groups['groups'][$key] = $group;

            }

            return $groups;

        } else {
            //not a true error - just lonely.
            $activity['confirmation'] = true;
            $activity['message'] = __( 'There were no groups found.', 'bp-xmlrpc' );
            return $activity;
        }



        return new IXR_Error( 1500, __( 'There was an error connecting, please try again.', 'bp-xmlrpc' ) );

    }

    /**
     * Get an user's activity stream
     *
     *
     * @param array $args ($username, $password, $data['scope'] )
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
        $service  = $this->escape( $args[1] );
        $apikey   = $this->escape( $args[2] );
        $data     = $args[3];

        if ( !$user = $this->login( $username, $service, $apikey ) )
            return $this->error;

        if ( !$data['scope'] )
            return new IXR_Error( 1553, __( 'Invalid Request - Missing content', 'bp-xmlrpc' ) );

        $data['scope'] = $this->escape( $data['scope'] );

        if ( $data['max'] ) {
            if ( $data['max'] > 35 ) $max = 35;
        } else {
            $max = 35;
        }


        //set up our scopes of the activity stream to fetch
        if ( $data['scope'] == 'favorites' ) {
            $include = implode( ',', (array)bp_activity_get_user_favorites( $bp->loggedin_user->id ) );
            $filter['user_id'] = $bp->loggedin_user->id;
        } else if ( $data['scope'] == 'friends' ) {
            $filter['user_id'] = implode( ',', (array)friends_get_friend_user_ids( $bp->loggedin_user->id ) );
        } else if ( $data['scope'] == 'groups' ) {
            $filter['object'] = $bp->groups->id;
            if ( $data['primary_id'] ) $defaults['primary_id'] = $data['primary_id'];
        } else if ( $data['scope'] == 'mentions' ) {
            $search_terms = '@' . bp_core_get_username( $bp->loggedin_user->id, $bp->loggedin_user->userdata->user_nicename, $bp->loggedin_user->userdata->user_login );
        } else if ( $data['scope'] == 'sitewide' ) {

        } else if ( $data['scope'] == 'just-me' ) {
            $filter['user_id'] = $bp->loggedin_user->id;
        } else if ( $data['scope'] == 'my-groups' ) {
            $groups = groups_get_user_groups( $bp->loggedin_user->id );
            $group_ids = implode( ',', $groups['groups'] );

            $filter['object'] = $bp->groups->id;
            $filter['primary_id'] = $group_ids;
        } else if ( $data['scope'] == 'following' ) {
            $filter['user_id'] = implode( ',', (array)BP_Follow::get_following( $bp->loggedin_user->id ) );
        }

        $show_hidden = ( $scope != 'friends' ) ? 1 : 0;

        if ( $include ) {
            $activities = bp_activity_get_specific( array( 'activity_ids' => explode( ',', $include ), 'max' => $max, 'per_page' => $max, 'page' => 1, 'sort' => 'DESC', 'display_comments' => false ) );
        } else {
            $activities = bp_activity_get( array( 'display_comments' => false, 'max' => $max, 'per_page' => $max, 'page' => 1, 'sort' => 'DESC', 'search_terms' => $search_terms, 'filter' => $filter, 'show_hidden' => $show_hidden ) );
        }


        if ( $activities ) {

            //something weird - need to look into. return total says 35 but returning all activities.
            //$activities = array_slice($activities, 0, 35);

            //loop and cleanse
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

                //dump this other stuff we don't need
                unset( $activity['id'] );
                unset( $activity['user_id'] );
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

                $activities['activities'][$key] = $activity;
            }

            return $activities;

        } else {
            //not a true error - just lonely.
            $activity['confirmation'] = true;
            $activity['message'] = __( 'There were no activity stream items found.', 'bp-xmlrpc' );
            return $activity;
        }

        return new IXR_Error( 1500, __( 'There was an error connecting, please try again.', 'bp-xmlrpc' ) );

    }

    /**
     * Log user in.
     *
     * @param string $username User's username.
     * @param string $service  The service trying to connect.
     * @param string $apikey   User's apikey.
     * @return mixed WP_User object if authentication passed, false otherwise
     */
    function login( $username, $service, $apikey ) {
        global $bp;

        if ( !get_option( 'bp_xmlrpc_enabled' ) ) {
            $this->error = new IXR_Error( 405, __( 'XML-RPC services disabled on this blog.', 'bp-xmlrpc' ) );
            return false;
        }

        if ( empty( $username ) || empty( $apikey ) ) {
            $this->error = new IXR_Error( 403, __( 'Bad login/pass combination.', 'bp-xmlrpc' ) );
            return false;
        }

        $username = sanitize_user( $username );
        $apikey = trim( $apikey );

        $userdata = get_user_by( 'login', $username );

        if ( !$userdata ) {
            $this->error = new IXR_Error( 1510, __( 'Invalid Request - User', 'bp-xmlrpc' ) );
            return false;
        }

        //high level disable
        if ( get_user_meta( $userdata->ID, 'bp_xmlrpc_disabled' ) ) {
            $this->error = new IXR_Error( 405, __( 'XML-RPC services disabled on this user.', 'bp-xmlrpc' ) );
            return false;
        }

        //match the keys
        if ( !bp_xmlrpc_login_apikey_check( $userdata->ID, $service, $apikey ) ) {
            $this->error = new IXR_Error( 403, __( 'Bad login/pass combination.', 'bp-xmlrpc' ) );
            return false;
        }

        $user = new WP_User( $userdata->ID );

        if ( !$user ) {
            $this->error = new IXR_Error( 1511, __( 'Invalid Request - User', 'bp-xmlrpc' ) );
            return false;
        }

        wp_set_current_user( $user->ID );

        if ( !current_user_can( get_option( 'bp_xmlrpc_cap_low' ) ) ) {
            $this->error = new IXR_Error( 405, __( 'XML-RPC services disabled on this user capability.', 'bp-xmlrpc' ) );
            return false;
        }

        //check for attempts limits
        //if ( $this->login_attempt_check( $user->ID ) )
        //    return $this->error;

        // awaken bp
        if ( !defined( BP_VERSION ) )
            do_action( 'bp_init' );

        if ( !$bp->loggedin_user->id ) {
            $this->error = new IXR_Error( 1512, __( 'Invalid Request - User', 'bp-xmlrpc' ) );
            return false;
        }

        return $user;
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
