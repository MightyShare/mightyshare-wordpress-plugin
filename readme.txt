=== MightyShare - Auto-Generated Social Media Images ===
Contributors: someguy9
Donate link: https://www.buymeacoffee.com/someguy
Tags: Social Preview, Open Graph, Social Media, Twitter Card, Open Graph Images
Requires at least: 5.4
Tested up to: 6.0
Requires PHP: 7.0
Stable tag: 1.0.7
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html

Automatically generate social share preview images with MightyShare!

== Description ==

Automatically generate social share preview images with [MightyShare](https://mightyshare.io/)! MightyShare takes your post title and featured image to generate a beautiful share image for your content. Compatible with posts, pages, and custom post types your social shares will look stunning in no time.

**How Does it Work?**

MightyShare sends your post's title, featured image, and post meta data to [our server](https://mightyshare.io/) to generate a social share image on the fly.


**Features**

* Automatically Generate Social Share Images for Posts and Pages!
* Robust Customization
* Adds Open Graph Meta Tags if You Don't Have an SEO Plugin
* SEO Plugin Compatibility: Yoast SEO, RankMath, All in One SEO, and The SEO Framework.
* Multiple Templates to Choose From

== Installation ==

To install this plugin:

1. Download the plugin
2. Upload the plugin to the wp-content/plugins directory,
3. Go to "plugins" in your WordPress admin, then click activate.
4. Add a MightyShare.io API key into settings > MightyShare.
5. Ensure each post type is enabled you want MightyShare enabled on.

== Frequently Asked Questions ==

=How does image generate work?=

The image generation process is performed on our servers. This is done by the plugin creating a unique signed URL that allows your API Key to generate a social share image on our server. The URL is then put in your head tag to be used as an og:image. If you have Yoast SEO or RankMath installed the plugin will automatically write their og:images if enabled. Otherwise the plugin will create an og:image meta tag with your unique URL. [Read our Privacy Policy](https://mightyshare.io/privacy-policy/)

=When is an image rendered?=

The MightyShare plugin places a signed MightShare image URL onto your meta tags. Images aren't rendered until that URL is visited (either by a user or crawler). Typically you'll see a 5-10 second delay in a social preview image loading when it's first shared.

== Screenshots ==

== Changelog ==

= 1.0.7 =
* 3 New templates added (8bit-1, bold-1 and bold-2).
* Bug fixes causing MightyShare not to display.

= 1.0.6 =
* New template added (clean-3).

= 1.0.5 =
* Bug fix preventing posts from showing the correct MightyShare state.

= 1.0.4 =
* New template added (mighty-3).
* Bug fix MightyShare not working on custom post types.
* Bug fix for logo display in settings.

= 1.0.3 =
* Added compatibility with All in One SEO Pack and The SEO Framework.
* 2 new template added (standard-3 and clean-1).

= 1.0.2 =
* PHP bug fix.
* Show detected SEO plugin on settings page.

= 1.0.1 =
* Bug fix for plugin install message.

= 1.0.0 =
* Initial Release.
