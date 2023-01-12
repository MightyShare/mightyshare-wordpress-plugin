=== MightyShare - Auto-Generated Social Media Images ===
Contributors: someguy9
Donate link: https://www.buymeacoffee.com/someguy
Tags: Social Preview, Open Graph, Social Media, Twitter Card, Open Graph Images
Requires at least: 5.4
Tested up to: 6.1
Requires PHP: 7.4
Stable tag: 1.3.4
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html

Automatically generate social share preview images with MightyShare!

== Description ==

### MIGHTYSHARE: GENERATE SOCIAL IMAGES

Automatically generate social share preview images with **[MightyShare](https://mightyshare.io/)**! MightyShare takes your post title and featured image to generate a beautiful share image for your content. Compatible with posts, pages, and custom post types your social shares will look stunning in no time. Customize [templates](https://mightyshare.io/templates/) with your brand colors, logo, and more.

To use the plugin you'll need to [create a free MightyShare account](https://mightyshare.io/register/).

### HOW DOES IT WORK?

MightyShare sends your post's title, featured image, and post meta data to our server to generate a social share image on the fly using your API Key.


### FEATURES

* **Automatically generate social share images** for posts and pages!
* Perfect solution for branded social images.
* Robust customization.
* SEO plugin compatibility: Yoast SEO, RankMath, All in One SEO, The SEO Framework, and Slim SEO.
* Adds open graph meta tags if you don’t have an SEO plugin.
* **Multiple [templates](https://mightyshare.io/templates/)** to choose from.
* New templates monthly!
* Works with custom post types.
* [Developer hooks](https://mightyshare.io/docs/filters/) for creating just about anything!
* Pick any Google Font to use in templates for paid plans.

== Installation ==

To install this plugin:

1. Download the plugin
2. Upload the plugin to the wp-content/plugins directory,
3. Go to "plugins" in your WordPress admin, then click activate.
4. Add a MightyShare.io API key into settings > MightyShare.
5. Ensure each post type is enabled you want MightyShare enabled on.

== Frequently Asked Questions ==

=Is MightyShare free?=

Yes! You can use MightyShare for free using our free account allowing you 50 renders a month. [Paid plans](https://mightyshare.io/pricing/) start at $5 that allow you to generate hundreds of social images.

=How does image generation work?=

The image generation process is performed on our servers. This is done by the plugin creating a unique signed URL that allows your API Key to generate a social share image on our server. The URL is then put in your head tag to be used as an og:image. If you have Yoast SEO or RankMath installed the plugin will automatically write their og:images if enabled. Otherwise the plugin will create an og:image meta tag with your unique URL. [Read our Privacy Policy](https://mightyshare.io/privacy-policy/)

=When is an image rendered?=

The MightyShare plugin places a signed MightShare image URL onto your meta tags. Images aren't rendered until that URL is visited (either by a user or crawler). Typically you'll see a 5 second delay in a social preview image loading when it's first shared.

=Why aren't my images showing up?=

If you are using an SEO plugin be sure to have a default image set for the type of content you want MightyShare to show on. For example in Yoast SEO you need to go to the Facebook section and set a default Open Graph image, this allows MightyShare to run filtering those meta tags. Additionally make sure you've cleared any caching mechanisms you may have. Still having issues? [Contact Us](https://mightyshare.io/contact/).

== Screenshots ==

== Changelog ==

= 1.3.4 =
* New template added (standard-4).

= 1.3.3 =
* Added support for SEOPress.
* PHP warning bug fix.

= 1.3.2 =
* Tested up to WordPress 6.1.
* PHP compatibility fix for PHP 7.

= 1.3.1 =
* PHP warning bug fix.

= 1.3.0 =
* Minimum PHP version upgraded to 7.4 as older versions are at their end of life.
* New templates travel-1, bold-3 & basic-3.
* Upgraded the template picker when modifying a single post.
* Only load template examples if browse templates is clicked (speeds up admin).
* Added support for Slim SEO.

= 1.2.0 =
* Google Font selection for users on a paid plan.
* New template options modal.
* Bug fix template selector not showing up correctly.

= 1.1.3 =
* Changed default render format to JPEG from PNG.
* Option to use post excepts as subheadings in MightyShare templates.
* Bug fix for MightyShare not setting a correct height/width for OG images when using Yoast.
* Bug fix that didn't allow settings checkboxes to be unchecked.
* Bug fix for initial install showing PHP warnings.

= 1.1.2 =
* Bug fix when overwriting background image urls with the MightyShare filter.

= 1.1.1 =
* New template browser to easily select a template.
* New API Key details when entered in the MightyShare settings.
* New template (float-1).

= 1.1.0 =
* New feature that allows you to overwrite template options by post type.
* Added option to provide a fallback image.
* New option to overwrite post titles that is used for the MightyShare renders.
* Grey color added to background of logo field so you can see white logos.
* Plenty of bug fixes.

= 1.0.8 =
* Added ability to enable MightyShare on taxonomies (archive pages)!
* Code bug fixes.

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
