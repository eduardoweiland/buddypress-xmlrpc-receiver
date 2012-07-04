Plugin do Doode para comunicação com aplicação Android...
@duduweiland e @vitormicillo


=== Plugin Name ===
Contributors: nuprn1, duduweiland
Donate link:
Tags: buddypress, xmlrpc, xml-rpc, activity stream, activity
Requires at least: PHP 5.2, WordPress 3.4.0, BuddyPress 1.5.6
Tested up to: PHP 5.4.4, WordPress 3.4.1, BuddyPress 1.5.6
Stable tag: 0.1.0

This plugin allows certain XML-RPC commands for BuddyPress

== Description ==

** BACK TO WORK **

This plugin allows certain XML-RPC commands for BuddyPress (Requires a client!)

A client application is required to connect to this BuddyPress XML-RPC - Receiver plugin. This could be anything from a standalone WordPress plugin to an iPhone or Android app.

Please read the FAQ and About Page.

= Related Links: = 

* <a href="https://github.com/duduweiland/buddypress-xmlrpc-receiver/issues" title="Report bugs">Bug tracker</a>
* <a href="https://github.com/duduweiland/buddypress-xmlrpc-receiver/wiki" title="Project wiki">Wiki</a>


== Installation ==

1. Upload the full directory into your wp-content/plugins directory
2. Activate the plugin at the plugin administration page
3. Adjust settings via the WP-Admin BuddyPress XML-RPC page

== Frequently Asked Questions ==

= How does it work? =

Allow your BuddyPress members to access certain BuddyPress features via XML-RPC. You may restrict settings on a wp_cap level and per member. Each member will need to generate an apikey (instead of using their password) to connect to your BuddyPress XML-RPC service. You can select which RPC commands to allow as well.

= How do members retrieve data? =

A client is required to send XML-RPC commands. If you have built a client (from standalone WordPress, iPhone, Firefox extensions, etc) please let me know and I'll list it here.

= What commands and data is returned? =

Please read the Wiki for a full list of commands and expected data.

* What commands are available?

bp.updateProfileStatus
- send an activity_update 

bp.getActivity
- get various activity stream items

bp.updateExternalBlogPostStatus
- send an activity stream update filed under blogs

bp.deleteExternalBlogPostStatus
- delete the activity update related to an already posted activity record (ie, if unpublishing a blog post)

bp.getMyFriends
- get a list of friends

bp.getMyFollowers (if plugin is installed)
- get a list of followers

bp.getMyFollowing (if plugin is installed)
- get a list of following

bp.getMyGroups
- get a list of groups

bp.getNotifications
- member adminbar notifications (new message, new friend, follower, etc)

bp.verifyConnection
- check if connection works


= My question isn't answered here =

Please contact the developers on

* <a href="https://github.com/duduweiland/buddypress-xmlrpc-receiver" title="BuddyPress XML-RPC Receiver - GitHub">Project Page</a>
* <a href="http://twitter.com/duduweiland" title="Twitter">Twitter</a>


== Changelog ==

= 0.1.0 =

* First [BETA] version


== Upgrade Notice ==



== Extra Configuration ==

