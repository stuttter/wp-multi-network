[![WordPress plugin](https://img.shields.io/wordpress/plugin/v/wp-multi-network.svg)](https://wordpress.org/plugins/wp-multi-network/)
[![WordPress](https://img.shields.io/wordpress/v/wp-multi-network.svg)](https://wordpress.org/plugins/wp-multi-network/)
[![Build Status](https://api.travis-ci.org/stuttter/wp-multi-network.png?branch=master)](https://travis-ci.org/stuttter/wp-multi-network)
[![Latest Stable Version](https://poser.pugx.org/stuttter/wp-multi-network/version)](https://packagist.org/packages/stuttter/wp-multi-network)
[![License](https://poser.pugx.org/stuttter/wp-multi-network/license)](https://packagist.org/packages/stuttter/wp-multi-network)

# WP Multi Network

A Network Management UI for global admins in a WordPress Multisite environment

Turn your multi-site installation of WordPress into many multi-site networks, all surrounding one central user base.

WP Multi Network allows cape wearing super-admins to create new networks of sites, allowing for infinitely extensible site, network, and domain arrangements.

# Installation

* Download and install using the built in WordPress plugin installer.
* Activate in the "Plugins" network admin panel using the "Network Activate" link.
* Comment out the `DOMAIN_CURRENT_SITE` line in your `wp-config.php` file. If you don't have this line, you probably need to enable multisite.

### Shared Cookies

Stash something similar to this in your `wp-config.php` to share cookies across all sites & networks.
```php
// Cookies
define( 'COOKIEHASH',        md5( 'yourdomain.com' ) );
define( 'COOKIE_DOMAIN',     'yourdomain.com'        );
define( 'ADMIN_COOKIE_PATH', '/' );
define( 'COOKIEPATH',        '/' );
define( 'SITECOOKIEPATH',    '/' );
define( 'TEST_COOKIE',        'thing_test_cookie' );
define( 'AUTH_COOKIE',        'thing_'          . COOKIEHASH );
define( 'USER_COOKIE',        'thing_user_'     . COOKIEHASH );
define( 'PASS_COOKIE',        'thing_pass_'     . COOKIEHASH );
define( 'SECURE_AUTH_COOKIE', 'thing_sec_'      . COOKIEHASH );
define( 'LOGGED_IN_COOKIE',   'thing_logged_in' . COOKIEHASH );
```

### Domain/Sub-domain flexibility

Stash something similar to this in your `wp-config.php` to make new site/network/domain creation and resolution as flexible as possible. You'll likely need some server configuration outside of WordPress to help with this (documentation pending.)
```php
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
```

### Single Sign-on

Single Sign-on is a way to keep registered users signed into your installation regardless of what domain, subdomain, and path they are viewing. This functionality is outside the scope of what WP Multi Network hopes to provide, but a dedicated SSO plugin made specifically for WP Multi Network is in development.

# FAQ

### Can I have separate domains?

Yes you can. That is what this plugin does best.

### Will this work on standard WordPress?

You need to have multi-site functionality enabled before using this plugin. https://codex.wordpress.org/Create_A_Network

### Where can I get support?

Create a GitHub [issue](https://github.com/stuttter/wp-multi-network/issues).

### What's up with uploads?

WP Multi-Network needs to be running to set the upload path for new sites. As such, all new networks created with this plugin will have it network activated. If you do disable it on one of your networks, any new site on that network will upload files to that network's root site, effectively causing them to be broken.

(TL;DR - Leave this plugin activated and it will make sure uploads go where they're suppose do.)

### Can I achieve a multi-level URL path structure domain/network/site with subfolder network?

To achieve nested folder paths in this fashion `network1/site1`, `network1/site2` etc, please follow the steps in this [article](https://github.com/stuttter/wp-multi-network/wiki/WordPress-Multisite-With-Nested-Folder-Paths) to construct a custom `sunrise.php` (Thanks to [Paul Underwood](https://paulund.co.uk) for providing these steps).

### Can I contribute?

Please! The number of users needing multiple WordPress networks is growing fast. Having an easy-to-use interface and powerful set of functions is critical to managing complex WordPress installations. If this is your thing, please help us out! Read more in the [plugin contributing guidelines](https://github.com/stuttter/wp-multi-network/blob/master/CONTRIBUTING.md).
