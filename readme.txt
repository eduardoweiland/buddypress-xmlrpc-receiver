=== BuddyPress XML-RPC Receiver ===
Contributors: nuprn1, duduweiland, yuttadhammo
Tags: buddypress, xmlrpc, xml-rpc, activity stream, activity, remote access
Requires at least: PHP 5.2, WordPress 3.4.0, BuddyPress 1.5.6
Tested up to: PHP 5.4.4, WordPress 3.5.1, BuddyPress 1.6.1
Stable tag: 0.1.2
License: GPLv3

This plugin allows remote access to BuddyPress networks through an XML-RPC API.


== Description ==

This plugin allows remote access to BuddyPress networks through an XML-RPC API.

A client application is required to connect to this BuddyPress XML-RPC plugin.
This could be anything from a standalone WordPress plugin to an iPhone or
Android app.

Please read the FAQ and About Page.

= Related Links: = 

* [Bug tracker](https://github.com/yuttadhammo/buddypress-xmlrpc-receiver/issues "Report bugs")
* [Wiki](https://github.com/yuttadhammo/buddypress-xmlrpc-receiver/wiki "Project wiki")
* [Source code](https://github.com/yuttadhammo/buddypress-xmlrpc-receiver "GitHub repository")


== Installation ==

1. Upload the full directory into your wp-content/plugins directory
2. Activate the plugin at the plugin administration page
3. Adjust settings via the WP-Admin BuddyPress XML-RPC page


== Frequently Asked Questions ==

= How does it work? =

Allow your BuddyPress members to access certain BuddyPress features via XML-RPC.
You may restrict settings on a wp_cap level and per member. Each member will
need to generate an apikey (instead of using their password) to connect to your
BuddyPress XML-RPC service. You can select which RPC commands to allow as well.

= How do members retrieve data? =

A client is required to send XML-RPC commands. You can build one yourself or try
an existing one. For Android, there is [BuddyDroid](https://play.google.com/store/apps/details?id=org.yuttadhammo.buddydroid&hl=en) that works with this plugin.

= What commands and data is returned? =

Please read the [Wiki](https://github.com/duduweiland/buddypress-xmlrpc-receiver/wiki/Function%20Reference)
for a full list of commands and expected data.

= My question isn't answered here =

Please contact the developers on

* [Project Page](https://github.com/yuttadhammo/buddypress-xmlrpc-receiver "BuddyPress XML-RPC Receiver - GitHub")


== Changelog ==

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

This version is completely incompatible with the previous one.
