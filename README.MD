# BuddyPress XML-RPC Receiver 
Contributors: nuprn1, duduweiland, yuttadhammo
Tags: buddypress, xmlrpc, xml-rpc, activity stream, activity, remote access
Requires at least: PHP 5.2, WordPress 3.4.0, BuddyPress 1.5.6
Tested up to: PHP 5.4.4, WordPress 3.5.1, BuddyPress 1.6.1
Stable tag: 0.5.10
License: GPLv3+

This plugin allows remote access to BuddyPress networks through an XML-RPC API.


##Description

This plugin allows remote access to BuddyPress networks through an XML-RPC API.

A client application is required to connect to this BuddyPress XML-RPC plugin.
This could be anything from a standalone WordPress plugin to an iPhone or
Android app.

An Android app designed for use with this plugin is available on Google Play:

https://play.google.com/store/apps/details?id=org.yuttadhammo.buddydroid

For more information on using this plugin, please read the FAQ and About Page.

## Related Links:

* [Source code](http://plugins.svn.wordpress.org/buddypress-xml-rpc-receiver/ "SVN repository")


## Installation 

1. Upload the full directory into your wp-content/plugins directory
2. Activate the plugin at the plugin administration page
3. Adjust settings via the WP-Admin BuddyPress XML-RPC page


## Frequently Asked Questions 

# How does it work? 

Allow your BuddyPress members to access certain BuddyPress features via XML-RPC.
You may restrict settings on a wp_cap level. 
You can select which RPC commands to allow as well.

# How do members retrieve data? 

A client is required to send XML-RPC commands. You can build one yourself or try
an existing one. For Android, there is [BuddyDroid](https://play.google.com/store/apps/details?id=org.yuttadhammo.buddydroid&hl=en) that works with this plugin.

# What commands and data are returned? =

*Available methods:*

* bp.updateProfileStatus: send an activity_update

    params: array ($username, $password, $data['status'] )

    returns: array (activity_id,message,confirmation,url)

* bp.postComment: submit a comment on a given post

    params: array ($username, $password, $data['comment'], $data['activity_id'] )

    returns: array (activity_id,message,confirmation,url)

* bp.deleteProfileStatus: delete an activity_update

    params: array ($username, $password, $data['activity_id'] )

    returns: array (activity_id,message,confirmation,url)

* bp.getActivity: get various activity stream items

    params: array ($username, $password, $data['scope','max','user_data','action','action_id','action_data'] )

    returns: array (activities,message,confirmation)

    sending the 'scope' parameter allows you to filter the results as per the plugin's presets (favorites, friends, groups, mentions, sitewide, just-me, my-groups, following) or by a specific BP action type (e.g. activity_update)

    sending the 'action' parameter makes it perform a specified action on an activity id ('action_id'), viz. 'delete' or 'comment' (put comment text in 'action_data').

    sending the 'user_data' parameter makes it include a list with the user's notifications, etc.

    returns: array (confirmation, message)

* bp.getMemberInfo: get info for a given user id

    params: array ($username, $password, $data['user_id','action','action_id','action_data'] )

    returns: array (confirmation, message)

    sending the 'action' parameter makes it perform a specified action on a user id ('action_id') (not yet implemented).

* bp.deleteMember: deletes member for given user id (must be admin, or it does nothing)

    params: array ($username, $password, $data['user_id'] )

    returns: array (confirmation, message)

* bp.updateExternalBlogPostStatus: send an activity stream update filed under blogs

* bp.deleteExternalBlogPostStatus: delete the activity update related to an already posted activity record (ie, if unpublishing a blog post)

* bp.getMyFriends: get a list of friends

* bp.getGroups: get a list of groups

* bp.getNotifications: member adminbar notifications (new message, new friend, follower, etc)

    params: array ($username, $password)

    returns: array (confirmation, total, message)

* bp.getMessages: get latest message in each thread

    params: array ($username, $password, $data['box','type','page_num','pag_page','search_terms','action','action_id','action_data'])

    returns: array (confirmation, total, message)

    sending the 'action' parameter makes it perform a specified action on a thread id ('action_id'), viz. 'delete', 'read', 'unread', or 'reply' (put reply text in 'action_data').

* bp.verifyConnection: check if connection works

    params: array ($username, $password)

    returns: array (confirmation, message)

= How do I use this plugin to redirect users? =

Direct them to the following url:

http://www.yoursite.com/index.php?bp_xmlrpc=true&bp_xmlrpc_redirect=<redirect>

where <redirect> is one of the following:

login
register
settings (user settings)
notifications
messages
friends
groups
favorites
mentions
stream (main site activity stream)
site (site home page)

= My question isn't answered here =



== Changelog ==

= 0.5.9 =

* added ability to post to groups

= 0.5.8 =

* minor notification fix 

= 0.5.7 =

* added friendship withdrawal
* group creation
* bug fixes

= 0.5.6 =

* bug fixes

= 0.5.5 =

* new admin layout, ability to allow per user
* bug fixes

= 0.5.4 =

* added active component info call

= 0.5.3 =

* added friend info to users
* standardized output (may break old clients)
* bug fixes

= 0.5.2 =

* register redirect
* fixed escaping

= 0.5.1 = 

* tweaked member info, added delete member

= 0.5 = 

* added get member info, removed show hidden

= 0.4.1 = 

* don't show hidden by default

= 0.4 = 

* switched to use password instead of api key - now incompatible with older clients that use api key

= 0.3 =

* message retrieval, delete, reply, mark read/unread
* ability to perform actions before refreshing stream / messages

= 0.2.2 =

* new redirect method using query_vars

= 0.2.1 =

* added user info to stream update
* various bug fixes

= 0.2 =

* Added comment, delete methods
* Various bug fixes

= 0.1.2 =

* fixed int casting
* fixed max stream entries
* removed need to access plugin directory

= 0.1.1 =

* Updated for Wordpress 3.4 compatibility

= 0.1.0 =

* First [BETA] version (originally created by nuprn1, unmaintained)


== Screenshots ==

None yet.


== Upgrade Notice ==

