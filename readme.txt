=== Two Factor Authentication ===
Tags: auth, two factor auth, login, security, authenticate, password, security, woocommerce, google authenticator, authy, two factor, 2fa
Requires at least: 3.2
Tested up to: 4.2
Stable tag: 1.1
Author: DavidAnderson
Contributors: DavidAnderson, DNutbourne
Donate link: http://david.dw-perspective.org.uk/donate
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Secure WordPress login with Two Factor Authentication - supports WooCommerce, front-end configuration, HOTP + TOTP (Google Authenticator, Authy, etc.)

== Description ==

Secure WordPress login with this two factor authentication (TFA). Users for whom it is enabled will require a one-time code in order to log in. From the authors of <a href="https://updraftplus.com/">UpdraftPlus - WP's #1 backup/restore plugin</a>, with over 400,000 active installs.

Features (see the "Screenshots" for more information):

* Supports standard TOTP + HOTP protocols (and so supports Google Authenticator, Authy, and many others)
* Displays graphical QR codes for easy scanning into apps on your phone
* TFA can be made available on a per-role basis (e.g. available for admins, but not for subscribers)
* TFA can be turned on or off by each user
* Supports front-end editing of settings, via [twofactor_user_settings] shortcode (i.e. users don't need access to the WP dashboard).
* Includes support for the WooCommerce login form
* WP Multisite compatible (plugin should be network activated)
* Simplified user interface and code base for ease of use and performance
* Added a number of extra security checks to the original forked code
* Removed the "email" authentication method (email is not truly two-factor, unless you have two separate email accounts for which neither's "lost login" link takes you to the other).
* Emergency codes (Premium version, release imminent)

= Why? =

Read this! http://www.wired.com/2012/08/apple-amazon-mat-honan-hacking/

= How Does It Work? =

This plugin uses the industry standard algorithm [TOTP](http://en.wikipedia.org/wiki/Time-based_One-time_Password_Algorithm) or [HOTP](http://en.wikipedia.org/wiki/HMAC-based_One-time_Password_Algorithm) for creating One Time Passwords. These are used by Google Authenticator, Authy, and many other OTP applications that you can deploy on your phone etc.

A TOTP code is valid for a certain time and after that a new code has to be entered.

= Plugin Notes =

To display graphical QR codes, this plugin will tell the browser to display images from https://chart.googleapis.com.

This plugin is a friendly fork and enhancement of Oscar Hane's https://wordpress.org/plugins/two-factor-auth/

== Installation ==

This plugin requires PHP version 5.3 or higher and support for [PHP mcrypt](http://www.php.net/manual/en/mcrypt.installation.php). The vast majority of PHP setups will have these. If not, ask your hosting company.

1. Search for 'Two Factor Authentication' in the 'Plugins' menu in WordPress.
2. Click the 'Install' button. (Make sure you picks the right one)
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Find site-wide settings in Settings -> Two Factor Authentication ; find your own user settings in the top-level menu entry "Two Factor Auth".

== Frequently Asked Questions ==

= What is the shortcode to use for front-end settings? =

[twofactor_user_settings]

= Oops, I lost my device that has passcodes. What to do? =

If you have FTP or cPanel access to your web hosting space, you can de-activate the plugin; see this article: https://updraftplus.com/understanding-wordpress-installs-plugins/

== Screenshots ==

1. Site-wide settings

2. User settings (dashboard)

3. User settings (front-end, via shortcode)

4. Regular WP login form requesting OTP code (after successful username/password entry)

5. WooCommerce login form requesting OTP code

6. What the user sees if opening a wrong OTP code on the regular WP login form

7. What the user sees if opening a wrong OTP code on the WooCommerce login form

8. Where to find the site-wide settings in the dashboard menu

9. Where to find the user's personal settings in the dashboard menu


== Changelog ==

= 1.1 - 30/Mar/2015 =

* Support added for multisite installs. (Plugin should be network-activated).
* Tested + compatible on upcoming WP 4.2 (tested on Beta 3)
* Re-add option to require 2FA over XMLRPC (without specific code, XMLRPC clients don't/can't use 2FA - but requiring it effectively blocks hackers who want to crack your password by using this weakness in XMLRPC)

= 1.0 - 20/Mar/2015 =

* First version, forked from Oskar Hane's https://wordpress.org/plugins/two-factor-auth/
* Support for email "two-factor" removed (email isn't really a second factor, unless you have multiple email accounts and guard where your "lost login" emails go to)
* WooCommerce support added to the main plugin. Load WooCommerce JavaScript only on pages where it is needed.
* Use AJAX to refresh current code (rather than reloading the whole page)
* Added WordPress nonces and user permission checks in relevant places
* Shortcode twofactor_user_settings added, for front-end settings
* User interface simplified/de-cluttered


== Upgrade Notice ==
* Add support for multisite installs. Add XMLRPC checking.
