# WP Multi Network

[![WordPress plugin](https://img.shields.io/wordpress/plugin/v/wp-multi-network.svg)](https://wordpress.org/plugins/wp-multi-network/)
[![WordPress](https://img.shields.io/wordpress/v/wp-multi-network.svg)](https://wordpress.org/plugins/wp-multi-network/)
[![Latest Stable Version](https://poser.pugx.org/stuttter/wp-multi-network/version)](https://packagist.org/packages/stuttter/wp-multi-network)
[![License](https://poser.pugx.org/stuttter/wp-multi-network/license)](https://packagist.org/packages/stuttter/wp-multi-network)

Provides a Network Management Interface for global administrators in WordPress Multisite installations.

Turn your WordPress Multisite installation into many multisite networks, surrounding one global set of users.

* Reveals hidden WordPress Multisite functionality.
* Includes a "Networks" top-level Network-Admin menu.
* Includes a List Table for viewing available networks.
* Allows moving subsites between networks.
* Allows global administrators to create new networks with their own sites and domain arrangements.
* Group sites into logical networks using nearly any combination of domain (example.org) and path (/site/).

## Installation

Requires WordPress 6.4 or newer and PHP 7.4 or newer.

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

## Single Sign-on

Single Sign-on is a way to keep registered users signed into your installation regardless of what domain, subdomain, and path they are viewing. This functionality is outside the scope of what WP Multi Network hopes to provide, but a dedicated SSO plugin made specifically for WP Multi Network is in development.

## Repairing existing doubled upload paths

The `wp wp-multi-network repair-uploads` WP-CLI command inspects every site by default. Run it with `--url` set to a network main site, not a subsite: WordPress fixes the content URL during bootstrap, so subsite context can produce a different upload plan. Sites belonging to other networks are reported as requiring manual review; rerun with that network's main-site `--url` to plan or execute their repair. It reports the stored upload options, effective and proposed paths, file collisions, and stored references for the known modern `/sites/{id}/sites/{id}` problem. Reference counts are advisory: they search that site's posts (content and GUID), postmeta, options, comments, and commentmeta, but not usermeta, termmeta, network metadata, or custom tables. A zero count is not proof that no stored references exist. Inspection makes no changes. The dry run walks site IDs in batches and excludes sites created after it starts; rerun it to include newly created sites. Use `--site-id=<id>` or `--network-id=<id>` to narrow it. In JSON output, `bootstrap_target_baseurl` is the URL observed from the main-site CLI context, not a promise about the subsite's own URL. Check the site's attachment URLs in its own context after repair; never use that field for bulk URL replacement.

To execute a repair, first save the JSON dry run outside the web root and review it:

```sh
wp wp-multi-network repair-uploads --format=json > /secure/path/upload-repair-plan.json
wp --user=superadmin wp-multi-network repair-uploads --execute --plan-file=/secure/path/upload-repair-plan.json --all
```

Omit `--all` when the saved plan contains only one site. The JSON plan includes each proposed file copy's relative path, size, and SHA-256 hash; do not edit it before execution. Execution creates and keeps a per-site lock file in `wp-content/.wpmn-upload-repair-locks`, outside the writable upload tree, takes its filesystem lock, then rechecks each site's settings and files against the saved plan before changing it. A concurrent execution for the same site stops without changing its journal or options. If `wp-content` is read-only or is not shared by every host that can run repairs, pre-create an absolute, writable, shared directory outside uploads and define its path as `WPMN_UPLOAD_REPAIR_LOCK_DIR` in `wp-config.php` or another file loaded by WP-CLI. The command refuses a missing configured directory or one that resolves inside uploads. Run execution from only one host when the lock directory or its advisory locks are host-local. Advisory reference counts may change without invalidating the plan and are not rescanned during execution. It copies affected files without overwriting targets, verifies the copies, then corrects the options. It leaves old files in place and saves the original options, file-manifest digest, and plan fingerprint in a per-site `wpmn_upload_repair_*` option. A rerun verifies the retained originals and copied files before reporting an already-completed repair. A fresh dry run recognizes verified copies only when a matching copying journal exists. Other collisions, legacy rewriting, custom layouts, upload constants, or filters require manual review and are not changed. Stored absolute URLs are reported but never rewritten, including serialized content.

For rollback, first verify that the retained old directory still has the original files. Read the backup option named in the command result for the affected site, then restore its `old_upload_path` and `old_upload_url_path` values with WP-CLI under that site's URL. Verify the effective upload directory and existing media before making the site writable again. Rollback does not remove copied files; do not delete either directory until the media and stored URL references have been reviewed. Run execution as the same operating-system user as the WordPress web server so copied files and newly created directories retain usable ownership. Perform execution and rollback during a maintenance window with uploads paused, since another process can write a file between a filesystem check and a copy.

## FAQ

### Can I have separate domains?

Yes you can. That is what this plugin does best.

### Will this work on standard WordPress?

You need to have WordPress Multisite enabled before using this plugin.

See: https://codex.wordpress.org/Create_A_Network

### Does switching blogs also switch networks?

No. By default, `switch_to_blog()` and `restore_current_blog()` retain WordPress's existing behavior and leave the current network context alone. Installations that explicitly want the network to follow temporary blog switches can define `WPMN_SYNC_NETWORK_ON_BLOG_SWITCH` as `true` in `wp-config.php` before this plugin loads. This opt-in pairs network restoration with each blog restoration, including nested switches. If you also switch networks manually inside a blog-switch scope, restore those manual network switches before restoring the blog.

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
