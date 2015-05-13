=== Two Factor Authentication ===
Tags: auth, two factor auth, login, security, authenticate, password, security, woocommerce, google authenticator, authy, two factor, 2fa
Requires at least: 3.2
Tested up to: 4.2
Stable tag: 1.1.15
Author: DavidAnderson
Contributors: DavidAnderson, DNutbourne
Donate link: http://david.dw-perspective.org.uk/donate
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Secure WordPress login with Two Factor Authentication - supports WooCommerce, front-end configuration, HOTP + TOTP (Google Authenticator, Authy, etc.)

== Description ==

Secure WordPress login with this two factor authentication (TFA) plugin. Users for whom it is enabled will require a one-time code in order to log in. From the authors of <a href="https://updraftplus.com/">UpdraftPlus - WP's #1 backup/restore plugin</a>, with over 400,000 active installs.

Are you completely new to TFA? <a href="https://wordpress.org/plugins/two-factor-authentication/faq/">If so, please see our FAQ</a>.

Features (please see the "Screenshots" for more information):

* Supports standard TOTP + HOTP protocols (and so supports Google Authenticator, Authy, and many others).
* Displays graphical QR codes for easy scanning into apps on your phone/tablet
* TFA can be made available on a per-role basis (e.g. available for admins, but not for subscribers)
* TFA can be turned on or off by each user
* Supports front-end editing of settings, via [twofactor_user_settings] shortcode (i.e. users don't need access to the WP dashboard). (The <a href="https://www.simbahosting.co.uk/s3/product/two-factor-authentication/">Premium version</a> allows custom designing of any layout you wish).
* Works together with "Theme My Login" (https://wordpress.org/plugins/theme-my-login/)
* Includes support for the WooCommerce login form
* Does not mention or request second factor until the user has been identified as one with TFA enabled (i.e. nothing is shown to users who do not have it enabled)
* WP Multisite compatible (plugin should be network activated)
* Simplified user interface and code base for ease of use and performance
* Added a number of extra security checks to the original forked code
* Emergency codes for when you lose your phone/tablet (<a href="https://www.simbahosting.co.uk/s3/product/two-factor-authentication/">Premium version</a>)
* Administrators can access other users' codes, and turn them on/off when needed (<a href="https://www.simbahosting.co.uk/s3/product/two-factor-authentication/">Premium version</a>)

= Why? =

Read this! http://www.wired.com/2012/08/apple-amazon-mat-honan-hacking/

= How Does It Work? =

This plugin uses the industry standard algorithm [TOTP](http://en.wikipedia.org/wiki/Time-based_One-time_Password_Algorithm) or [HOTP](http://en.wikipedia.org/wiki/HMAC-based_One-time_Password_Algorithm) for creating One Time Passwords. These are used by Google Authenticator, Authy, and many other OTP applications that you can deploy on your phone etc.

A TOTP code is valid for a certain time. Whatever program you use (i.e. Google Authenticator, etc.) will show a different code every so often.

= Plugin Notes =

To display graphical QR codes, this plugin will tell the browser to display images from https://chart.googleapis.com.

This plugin began life as a friendly fork and enhancement of Oscar Hane's https://wordpress.org/plugins/two-factor-auth/

== Installation ==

This plugin requires PHP version 5.3 or higher and support for [PHP mcrypt](http://www.php.net/manual/en/mcrypt.installation.php). The vast majority of PHP setups will have these. If not, ask your hosting company.

1. Search for 'Two Factor Authentication' in the 'Plugins' menu in WordPress.
2. Click the 'Install' button. (Make sure you picks the right one)
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Find site-wide settings in Settings -> Two Factor Authentication ; find your own user settings in the top-level menu entry "Two Factor Auth".

If you want to add a section to the front-end of your site where users can configure their two-factor authentication settings, use this shortcode: [twofactor_user_settings]

== Frequently Asked Questions ==

= What is two factor authentication? =

Basically, it's to do with securing your logins, so that there's more than one link in the chain needing to be broken before an unwanted intruder can get in your website.

By default, your WordPress accounts are protected by only one thing: your password. If that's broken, then everything's wide open.

"Two factor" means adding a second requirement. Usually, this is a code that comes to a device you own (e.g. phone, tablet) - so, someone can't get into your website without getting hold of your device. <a href="https://en.wikipedia.org/wiki/Two_factor_authentication">You can get a longer answer from Wikipedia.</a>

Sometimes it is also called multi-factor authentication instead of two-factor - because someone could secure their systems with as many factors as they like.

= Why should I care? =

Read this: http://www.wired.com/2012/08/apple-amazon-mat-honan-hacking/

= How does two factor authentication work? =

Since "two factor authentication" just means "a second something is necessary to get in", this answer depends upon the particular set-up. In the most common case, a numeric code is shown on your phone, tablet or other device. This code be sent via an SMS; this then depends on the mobile phone network working. This plugin does not uses that method. Instead, it uses a standard mathematical algorithm to generate codes that are only valid once each, or for only for 30 seconds (depending on which algorithm you choose). Your phone or tablet can know the code after it has been set up once (often, by just scanning a bar-code off the screen).

= What do I need to set up on my phone/tablet (etc.) in order to generate the codes? =

This depends on your particular make of phone, and your preferences. Google have produced a popular app called "Google Authenticator", which is a preferred option for many people because it is easy to use and can be set up via just scanning a bar code off your screen - <a href="https://support.google.com/accounts/answer/1066447"> follow this link, and ignore the first paragraph that is talking about 2FA on your Google account</a> (rather than being relevant to this plugin).

= What if I do not have a phone or tablet? =

Many and various devices and programs can generate the codes. One option is an add-on for your web browser; for example, <a href="https://chrome.google.com/webstore/search/authenticator">here are some apps and add-ons for Google Chrome</a>. Wikipedia <a href="https://en.wikipedia.org/wiki/Time-based_One-time_Password_Algorithm#Client_implementations">lists various programs for different computers</a>.

= I lost my device that has pass-codes - or, they don't work. What to do? =

If your pass-code used to work, but no longer does, then check that the time on your device that generates them is accurate.

If you cannot get in and need to disable two-factor authentication, then add this to your wp-config.php file, using FTP or the file manager in your hosting control panel:

define('TWO_FACTOR_DISABLE', true);

Add it next to where any other line beginning with "define" is.

Alternatively, if you have FTP or cPanel access to your web hosting space, you can de-activate the plugin; see this article: https://updraftplus.com/understanding-wordpress-installs-plugins/

= What are HOTP and TOTP? =

These are the names of the two mathematical algorithms that are used to create the special codes. These are industry-standard algorithms, devised by expert cryptographers. HOTP is less popular, but the device that generates the codes does not need to know the correct time (instead, the codes are generated in a precise sequence). TOTP is much more popular, and generates codes that are only valid for 30 seconds (and so your device needs to know the time). I'd recommend TOTP, as HOTP can be annoying if something causes the sequences to get out of sync.

= What is the shortcode to use for front-end settings? =

[twofactor_user_settings]

== Screenshots ==

1. Site-wide settings

2. User settings (dashboard)

3. User settings (front-end, via shortcode)

4. Regular WP login form requesting OTP code (after successful username/password entry)

5. WooCommerce login form requesting OTP code (after successful username/password entry)

6. What the user sees if opening a wrong OTP code on the regular WP login form

7. What the user sees if opening a wrong OTP code on the WooCommerce login form

8. Where to find the site-wide settings in the dashboard menu

9. Where to find the user's personal settings in the dashboard menu

10. Emergency codes (Premium version)

11. Adjusting other users' settings as an admin (Premium version)

12. Building your own design for the page with custom short-codes (Premium version)

== Changelog ==

= 1.1.15 - 13/May/2015 =

* FIX: Fix conflict with 'reset password' form with "Theme My Login" plugin

= 1.1.14 - 12/May/2015 =

* FIX: Add TFA support to the WooCommerce login-on-checkout form (previously, TFA-enabled users could not log in using it)

= 1.1.13 - 11/May/2015 =

* TWEAK: Use jquery-qrcode to generate QR codes, replacing external dependency on Google
* TWEAK: Update bundled select2 library to 4.0.0 release (was rc2)

= 1.1.12 - 22/Apr/2015 =

* FIX: Fix corner-case where the user's login looked like an email address, but wasn't the account address. In this case, a OTP password was always requested.
* FIX: When the username does not exist, front-end should not request TFA code.

= 1.1.11 - 21/Apr/2015 =

* TWEAK: Prevent PHP notice if combining with bbPress
* TWEAK: Added more console logging if TFA AJAX request fails
* TWEAK: Add some measures to overcome extraneous PHP output breaking the AJAX conversation (e.g. when using strict debugging)

= 1.1.10 - 20/Apr/2015 =

* SECURITY: Fix possible non-persistent XSS issue in admin area (https://blog.sucuri.net/2015/04/security-advisory-xss-vulnerability-affecting-multiple-wordpress-plugins.html)
* FIX: Don't get involved on "lost password" forms (intermittent issue with "Theme My Login")

= 1.1.9 - 15/Apr/2015 =

* TESTING: Tested with "Theme My Login" - http://wordpress.org/plugins/theme-my-login/ - no issues
* TWEAK: Do a little bit of status logging to the browser's developer console on login forms, to help debugging any issues
* TWEAK: Add a spinner on login forms whilst TFA status is being checked (WP 3.8+)
* TWEAK: Make sure that scripts are versionned, to prevent updates not being immediately effective
* TWEAK: Make sure OTP field on WooCommerce login form receives focus automatically

= 1.1.8 - 14/Apr/2015 =

* FIX: Fix an issue on sites that forced SSL access to admin area, but not to front-end, whereby AJAX functions could fail (e.g. showing latest code)
* FIX: Version number was not shown correctly in admin screen since 1.1.5
* TWEAK: Show proper plugin URI

= 1.1.7 - 10/Apr/2015 =

* FIX: Fix plugin compatibility with PHP 5.6
* FIX: TFA was always made active on XMLRPC, even when the user turned it off

= 1.1.6 - 09/Apr/2015 =

* TWEAK: Change various wordings to make things clearer for new-comers to two-factor authentication.

= 1.1.5 - 07/Apr/2015 =

* FEATURE: Admin users (Premium version) can show codes belonging to other users, and activate or de-activate TFA for other users.
* PREMIUM: Premium version has now been released: https://www.simbahosting.co.uk/s3/product/two-factor-authentication/. Features emergency codes, personal support, and more short-codes allowing you to custom-design your own front-end page for users.
* TWEAK: Premium version now contains support link to the proper place (not to wordpress.org's free forum)
* TWEAK: Added a constant, TWO_FACTOR_DISABLE. Define this in your wp-config.php to disable all TFA requirements.
* FIX: Fix a bug introduced in version 1.1.2 that could prevent logins on SSL-enabled sites on the WooCommerce form when not accessed over SSL

= 1.1.3 - 04/Apr/2015 =

* TWEAK: Provide "Settings saved" notice when user's settings are saved in the admin area (otherwise the user may be wondering).

= 1.1.2 - 03/Apr/2015 =

* FIX: Include blockUI JavaScript (the lack of which caused front-end options not to save if you did not have WooCommerce or another plugin that already used blockUI installed)
* FEATURE: Don't show anything on the WooCommerce login form unless user is using 2FA (i.e. behave like WP login form)
* FEATURE: Added 9 new shortcodes for custom-designed front-end screens (Premium - forthcoming)

= 1.1.1 - 30/Mar/2015 =

* Support added for multisite installs. (Plugin should be network-activated).
* Support added for super-admin role (it's not a normal WP role internally, so needs custom handling)
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
* 1.1.15 : Fix 'reset password' form with "Theme My Login" plugin
