# WP Multi Network on Subdomains

Extending the great work started on stutter/wp-multi-network plugin. We needed multi-network features exclusively for subdomain networks. With this version of the plugin you can only add a network on a subdomain and subdirectory sites. 

`subdomain1`.rootdomain.com/`site1`

This allows us to move sites between networks more reliably:

`subdomain1`.rootdomain.com/`site1` => `subdomain2`.rootdomain.com/`site1`

Renaming the subdomain is now handled automatically.

A front-end network registration form is being developed as well. Coming soon.

Network permissions updated. Introduces two super admins:
1. Root domain super admin (Global Admin)
2. Subdomain super admin (Network Admin)

Global Admins can move sites between networks, delete and create networks, and add sites to networks. 

Network Admins can delete their network, change its name, and add sites and users to their network.

# Installation

* Clone the plugin in your plugins dir.
* Activate in the "Plugins" network admin panel using the "Network Activate" link.
* Comment out the `DOMAIN_CURRENT_SITE` line in your `wp-config.php` file. If you don't have this line, you probably need to enable multisite.

### Shared Cookies

Stash something similar to this in your `wp-config.php` to share cookies across all sites & networks.
```
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
_these will work with subdomain version as well_

Stash something similar to this in your `wp-config.php` to make new site/network/domain creation and resolution as flexible as possible. You'll likely need some server configuration outside of WordPress to help with this (documentation pending.)
```
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
