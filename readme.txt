=== WP Multi Network ===
Contributors: johnjamesjacoby, ddean, BrianLayman, rmccue
Tags: network, networks, blog, blogs, site, sites, domain, domains, mapping, domain mapping, fun
Requires at least: 4.9
Tested up to: 5.0
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=9Q4F4EL5YJ62J

== Description ==

Turn your WordPress Multisite installation into many multisite networks, surrounding one global set of users.

WP Multi Network allows global administrators to create new networks with their own sites and domain arrangements.

== Installation ==

* Download and install using the built in WordPress plugin installer.
* Activate in the "Plugins" network admin panel using the "Network Activate" link.
* Comment out the `DOMAIN_CURRENT_SITE` line in your `wp-config.php` file. If you don't have this line, you probably need to <a href="https://codex.wordpress.org/Create_A_Network">enable multisite</a>.
* Start planning and creating your networks.

== Frequently Asked Questions ==

= Can each network have a different domain? =

Yes you can. That is what this plugin does best.

Think of how WordPress.org works:

* wordpress.org
* buddypress.org
* bbpress.org
* wordcamp.org

Users are global, and can login to any of those networks with the same credentials.

= Will this work on standard WordPress? =

You can activate it, but it won't do anything. You need to have the multisite functionality enabled and working first.

= Where can I get support? =

The WordPress support forums: https://wordpress.org/support/plugin/wp-multi-network/

= What about multisite constants? =

For maximum flexibility, use something like...

`
// Multisite
define( 'MULTISITE',           true                  );
define( 'SUBDOMAIN_INSTALL',   false                 );
define( 'PATH_CURRENT_SITE',   '/'                   );
define( 'DOMAIN_CURRENT_SITE', $_SERVER['HTTP_HOST'] );

// Likely not needed anymore (your config may vary)
//define( 'SITE_ID_CURRENT_SITE', 1 );
//define( 'BLOG_ID_CURRENT_SITE', 1 );

// Uncomment and change to a URL to funnel no-site-found requests to
//define( 'NOBLOGREDIRECT', '/404/' );

/**
 * These are purposely set for maximum compliance with multisite and
 * multinetwork. Your config may vary.
 */
define( 'WP_HOME',    'http://' . $_SERVER['HTTP_HOST'] );
define( 'WP_SITEURL', 'http://' . $_SERVER['HTTP_HOST'] );
`

= What about cookies? =

Use something like this to allow cookies to work across networks...

`
// Cookies
define( 'COOKIEHASH',         md5( 'yourrootdomain.com' ) );
define( 'COOKIE_DOMAIN',      'yourrootdomain.com'        );
define( 'ADMIN_COOKIE_PATH',  '/' );
define( 'COOKIEPATH',         '/' );
define( 'SITECOOKIEPATH',     '/' );
define( 'TEST_COOKIE',        'thing_test_cookie' );
define( 'AUTH_COOKIE',        'thing_'          . COOKIEHASH );
define( 'USER_COOKIE',        'thing_user_'     . COOKIEHASH );
define( 'PASS_COOKIE',        'thing_pass_'     . COOKIEHASH );
define( 'SECURE_AUTH_COOKIE', 'thing_sec_'      . COOKIEHASH );
define( 'LOGGED_IN_COOKIE',   'thing_logged_in' . COOKIEHASH );
`

= Uploads? =

As of version 3.5, new WordPress multisite installs use a more efficient way to serve uploaded files.
Unfortunately, this doesn't play well with multiple networks (yet). Installs that upgraded from 3.4 or below are not affected.

WP Multi-Network needs to be running to help set the upload path for new sites, so all networks created with this plugin will have it network activated.
If you disable it on one of your networks, any new site you create on that network will store its uploaded files under that network's main site's uploads folder. It's not pretty.

Just leave this plugin network-activated (or in mu-plugins) and it will take care of everything.

= Can I achieve a multi-level URL path structure domain/network/site with subfolder network? =

To achieve nested folder paths in this fashion network1/site1, network1/site2 etc,
please follow the steps in https://paulund.co.uk/wordpress-multisite-nested-paths to construct a custom sunrise.php (Thanks to https://paulund.co.uk for providing these steps).

= Where can I find documentation? =

Not much to talk about really. Check the code for details!

== Changelog ==

= 2.2.0 =
* WordPress 4.9 minimum version bump
* Fix bug preventing sites from being moved
* Tweak some CSS styling
* Use more WordPress core functions for sites & networks

= 2.1.0 =
* Add nonce checks to forms
* Add validation & output sanitization to form fields

= 2.0.0 =
* WordPress 4.6 minimum version bump
* Caching improvements for WordPress 4.6
* Refactor list tables & admin method code

= 1.8.1 =
* Fix site reassignment metabox from moving sites incorrectly

= 1.8.0 =
* Support for core compat functions
* Fix bug causing site moves to break
* Fix bug allowing duplicate site URLs
* Remove _network_option() functions
* Remove network.zero placeholder
* WordPress 4.5 & 4.6 compatibility updates

= 1.7.0 =
* WordPress 4.4 compatibility updates
* Metabox overhaul
* network.zero improvements
* Fix site assignments
* Various UI improvements
* Global, class, function, and method cleanup

= 1.6.1 =
* WordPress 4.3 UI compatibility updates
* Remove site "Actions" column integration

= 1.6.0 =
* Move inclusion to muplugins_loaded
* Introduce network switching API
* Introduce network options API
* Update action links to better match sites list
* Better support for domain mapping
* Refactor file names & locations
* Deprecate wpmn_fix_subsite_upload_path()
* Include basic WPCLI support
* Escaped gettext output
* Fix bulk network deletion
* Scrutinized code formatting

= 1.5.1 =
* Fix debug notices when creating networks
* Fix incorrect variable usage causing weird output
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
* Fix multiple minor issues;
* Add Site Admin and Network Admin to Network lists
* Add various security and bullet proofing

= 1.1 =
* Better WordPress 3.0 compatibility

= 1.0 =
Getting started
