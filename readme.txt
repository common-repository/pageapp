=== PageApp ===
Contributors: jamesdlow
Tags: pageapp, wp-json, relevanssi, search, api, rest api, post, meta, post meta
Requires at least: 3.0
Tested up to: 6.5.4
Stable tag: 1.4.3
License: © 2024 Thireen32 Pty Ltd
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=K6VKWB3HZB2T2&item_name=Donation%20to%20jameslow%2ecom&currency_code=USD&bn=PP%2dDonationsBF&charset=UTF%2d8

Extensions to Wordpress wp-json for the PageApp API and mobile framework

== Description ==

Extensions to Wordpress wp-json for the PageApp API and mobile framework:
* Whitelist meta values for the Wordpress rest api
* Enable Relevanssi over the Wordpress rest api
* PageApp compatiable API using wp-json
* Helper functions and utilities

== Installation ==

1. Upload entire `pageapp` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Set settings in WP-Admin->PageApp->Settings
4. Whitelist post meta keys WP-Admin->PageApp->Settings

== Frequently Asked Questions ==

== Changelog ==

= 1.4.3 =
* Add optional API key authentication for WP JSON API

= 1.4.2 =
* Fix for when Relevanssi enabled in PageApp but not installed

= 1.4.1 =
* Check for blanks in Restlib::get_param()

= 1.4.0 =
* Escape more properties in SettingsLib

= 1.3.9 =
* Escape nounces in SettingsLib

= 1.3.8 =
* Strip slahes SettingsLib

= 1.3.7 =
* Fix carriage returns in SettingsLib

= 1.3.6 =
* Add row class to SettingsLib

= 1.3.5 =
* Add title section to SettingsLib

= 1.3.4 =
* SettingsLib allows underscore or hyphen for concatenation

= 1.3.3 =
* Add option to remove username, add password, login and redirect on user registration (useful for OAuth)

= 1.3.2 =
* Fix Restlib getParam when running on Wordpress

= 1.3.1 =
* Settingslib private functions (logged in) working on PHP 8.0+

= 1.3.0 =
* Restlib private functions (logged in) working on PHP 8.0+

= 1.2.9 =
* Reorganize code, add library files

= 1.2.8 =
* Function to allow setup of cron for caching of Roku/Amazon links

= 1.2.7 =
* Cache Roku/Amazon vimeo links for longer and add randomisation so they're not all queried at once

= 1.2.6 =
* Add utilslib.php

= 1.2.5 =
* Fix including cachelib.php

= 1.2.4 =
* Remove debug statement

= 1.2.3 =
* Fix Roku for PHP 8

= 1.2.2 =
* Fix for xml parsing

= 1.2.1 =
* Cache Roku/Amazon vimeo links

= 1.2.0 =
* Fix to getting featured thumbnail

= 1.1.9 =
* Account for warnings in PHP 8.0

= 1.1.8 =
* Fix meta types

= 1.1.7 =
* Further fixes to settings saving

= 1.1.6 =
* Fix whitelist post meta saving

= 1.1.5 =
* Add apikey to Roku/FireTV joiners for 
* Check for both "movies" and "shortFormVideos" for Roku

= 1.1.4 =
* Add MRSS joiner for Fire TV

= 1.1.3 =
* Updated settings library

= 1.1.2 =
* Fix for content-type header in httplib

= 1.1.1 =
* Fix select drop down for non-associative arrays

= 1.1.0 =
* Select drop down for Roku genre

= 1.0.9 =
* Use generic settings library

= 1.0.8 =
* Use generic wp-json library

= 1.0.7 =
* Vimeo Roku feed joiner

= 1.0.6 =
* Fix some PHP warnings

= 1.0.5 =
* Bug fix for WPVS featured images

= 1.0.4 =
* Add featured image from children to terms

= 1.0.3 =
* Add term details to JSON
* Add setting for increasing max result

= 1.0.2 =
* Add authentication API

= 1.0.1 =
* Add featured_image_urls to posts and custom post types

= 1.0.0 =
* Initial Version
