[![WordPress plugin](https://img.shields.io/wordpress/plugin/v/wp-multi-network.svg)](https://wordpress.org/plugins/wp-multi-network/)
[![WordPress](https://img.shields.io/wordpress/v/wp-multi-network.svg)](https://wordpress.org/plugins/wp-multi-network/)
[![Latest Stable Version](https://poser.pugx.org/stuttter/wp-multi-network/version)](https://packagist.org/packages/stuttter/wp-multi-network)
[![License](https://poser.pugx.org/stuttter/wp-multi-network/license)](https://packagist.org/packages/stuttter/wp-multi-network)

# WP Multi Network

Provides a Network Management Interface for global administrators in WordPress Multisite installations.

Turn your WordPress Multisite installation into many multisite networks, surrounding one global set of users.

* Reveals hidden WordPress Multisite functionality.
* Includes a "Networks" top-level Network-Admin menu.
* Includes a List Table for viewing available networks.
* Allows moving subsites between networks.
* Allows global administrators to create new networks with their own sites and domain arrangements.
* Group sites into logical networks using nearly any combination of domain (example.org) and path (/site/).

# Installation

* Download and install using the built in WordPress plugin installer.
* Activate in the "Plugins" network admin panel using the "Network Activate" link.
* Comment out the `DOMAIN_CURRENT_SITE` line in your `wp-config.php` file. If you don't have this line, you probably need to enable multisite.

### Cookie Configuration

Stash something like this in your `wp-config.php` to use a single cookie configuration across all sites & networks.

Replace `example.com` with the domain for the main site in your primary network.

```php
// Cookies
define( 'COOKIEHASH',        md5( 'example.com' ) );
define( 'COOKIE_DOMAIN',     'example.com'        );
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

Stash something like this in your `wp-config.php` to make new site/network/domain creation and resolution as flexible as possible.

You'll likely need some server configuration outside of WordPress to help with this (documentation pending.)

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
define( 'WP_HOME',    'https://' . $_SERVER['HTTP_HOST'] );
define( 'WP_SITEURL', 'https://' . $_SERVER['HTTP_HOST'] );
```

### Single Sign-on

Single Sign-on is a way to keep registered users signed into your installation regardless of what domain, subdomain, and path they are viewing. This functionality is outside the scope of what WP Multi Network hopes to provide, but a dedicated SSO plugin made specifically for WP Multi Network is in development.

# FAQ

### Can I have separate domains?

Yes you can. That is what this plugin does best.

### Will this work on standard WordPress?

You need to have WordPress Multisite enabled before using this plugin.

See: https://codex.wordpress.org/Create_A_Network

### Where can I get support?

Community: https://wordpress.org/support/plugin/wp-multi-network

Development: https://github.com/stuttter/wp-multi-network/discussions

### What's up with uploads?

WP Multi-Network needs to be running to set the upload path for new sites. As such, all new networks created with this plugin will have it network activated. If you do disable it on one of your networks, any new site on that network will upload files to that network's root site, effectively causing them to be broken.

Leave this plugin activated, and it will make sure uploads go where they are expected to.

### Can I achieve a multi-level URL path structure domain/network/site with subfolder network?

To achieve nested folder paths in this fashion `network1/site1`, `network1/site2` etc, please follow the steps in this [article](https://github.com/stuttter/wp-multi-network/wiki/WordPress-Multisite-With-Nested-Folder-Paths) to construct a custom `sunrise.php` (Thanks to [Paul Underwood](https://paulund.co.uk) for providing these steps).

### Can I contribute?

Yes! Having an easy-to-use interface and powerful set of functions is critical to managing complex WordPress installations. If this is your thing, please help us out! Read more in the [plugin contributing guidelines](https://github.com/stuttter/wp-multi-network/blob/master/CONTRIBUTING.md).
