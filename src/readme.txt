=== WP Multi Network ===
Contributors: johnjamesjacoby, ddean, BrianLayman
Tags: network, networks, blog, blogs, site, sites, domain, domains, mapping, domain mapping, fun
Requires at least: 3.6
Tested up to: 4.1
Stable tag: 1.5.1

== Description ==

Turn your multi-site installation of WordPress into many multi-site networks, all surrounding one central user base.

WP Multi Network allows cape wearing super-admins to create new networks of sites, allowing for infinitely extensible site, network, and domain arrangements.

== Installation ==

Download and install using the built in WordPress plugin installer.

Activate in the "Plugins" network admin panel using the "Network Activate" link.

Comment out the `DOMAIN_CURRENT_SITE` line in your `wp-config.php` file. 
If you don't have this line, you probably need to <a href="http://codex.wordpress.org/Create_A_Network">enable multisite</a>.

Start planning and creating your networks.

== Frequently Asked Questions ==

= Can I have separate domains? =

Yes you can. That is what this plugin does best.

= Will this work on standard WordPress? =

Yes, but it won't do anything. You need to have the multi-site functionality turned on and working before using this plugin.

= Where can I get support? =

The WordPress support forums: http://wordpress.org/tags/wp-multi-network/

= What's up with uploads? =

As of version 3.5, new WordPress multisite installs use a more efficient way to serve uploaded files. 
Unfortunately, this doesn't play well with multiple networks (yet). Installs that upgraded from 3.4 or below are not affected.

WP Multi-Network needs to be running to help set the upload path for new sites, so all networks created with this plugin will have it network activated.
If you disable it on one of your networks, any new site you create on that network will store its uploaded files under that network's main site's uploads folder. It's not pretty.

But just leave this plugin activated and it will take care of everything. :)

Thanks to RavanH for the suggestion!

= Where can I find documentation? =

Not much to talk about really. Check the code for details!

== Changelog ==

= 1.5.1 =
* Fixes debug notices when creating networks
* Fixes incorrect variable usage causing weird output
* Adds default path when creating new networks

= 1.5 =
* Support for WordPress 3.8
* Finally, a menu icon!
* Improved output sanitization

= 1.4.1 =
* Fix issue when changing network domain or path - contributed by mgburns
* Improve support for native uploaded file handling

= 1.4 =
* Fix admin pages (let us know if you find something broken)
* Add support for WP 3.5+ upload handling - thanks, RavanH (see notes: "What's up with uploads?")

= 1.3.1 =
* Fix prepare() usages
* Fix some debug notices

= 1.3 =
* Refactor into smaller pieces
* Add phpdoc
* Deprecate functions for friendlier core-style functions
* Code clean-up
* Remove inline JS

= 1.2 =
* Implemented 3.1 Network Admin Menu, backwards compatiblity maintained.
* Fixed multiple minor issues;
* Added Site Admin and Network Admin to Network lists
* Added various security and bullet proofing

= 1.1 =
* Better WordPress 3.0 compatibility

= 1.0 =
Getting started
