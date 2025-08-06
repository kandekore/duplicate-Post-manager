=== Duplicate Post Manager ===
Contributors: Darren Kandekore
Tags: duplicate posts, redirect, 301, seo, htaccess
Requires at least: 5.0
Tested up to: 6.8.2
Requires PHP: 7.4
Stable tag: 1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage and clean up duplicate WordPress posts with ease. Delete duplicates, assign 301 redirects, and generate `.htaccess` rules.

== Description ==

Manage and clean up duplicate WordPress posts with ease. Delete duplicates in bulk, assign 301 redirects, and generate `.htaccess` rules — all from one simple interface.

=== Features ===
* Detect duplicate posts by **title** and **slug**
* Display all duplicates in a table (with checkboxes)
* Per-post redirect options:
* Choose from other duplicates in a dropdown
* Enter a custom redirect URL manually
* Validate redirect targets before deleting
* Redirect deleted posts using 301 rules
* Save posts to **trash**, not permanent delete
* Generate `.htaccess` rules for all redirects
* Copy/paste or export redirects as needed

=== How to Use ===
1. Go to **Tools > Duplicate Post Manager**
2. Click **Scan for Duplicates**
3. Review the table of duplicate posts
4. For each post:
    - Check the box to delete it
    - Choose a redirect target (dropdown or custom URL)
5. Click **Delete Selected & Redirect**
6. Scroll down to copy or export your `.htaccess` redirect rules

=== Redirect Format ===
The plugin generates Apache `.htaccess` rules like:

Redirect 301 /old-slug /new-slug
Redirect 301 /another-old /new-target

* All redirects use **relative paths** for portability.
* Only valid (non-404) redirects are saved.

=== Why Use It? ===
* Prevent SEO penalties from duplicate content
* Control user redirection after cleanup
* Maintain your site's authority by preserving link equity
* Clean and update old auto-imported posts or legacy content

=== Installation ===
1. Download and extract the plugin
2. Upload the folder to `/wp-content/plugins/`
3. Activate via **Plugins > Installed Plugins**
4. Navigate to **Tools > Duplicate Post Manager**

=== Technical Notes ===
* Uses WordPress core functions (`get_posts`, `get_permalink`, `wp_trash_post`)
* Compatible with Classic Editor and Block Editor (Gutenberg)
* Does not delete posts permanently
* Nonce-verified form for security

=== License ===
This plugin is licensed under the [GPLv2](https://www.gnu.org/licenses/gpl-2.0.html) or later.

=== Coming Soon (Ideas) ===
* Export `.htaccess` as a downloadable file
* Custom post type support
* Integration with Redirection plugins
* Inline AJAX validation of manual URLs

=== Author ===
[Darren Kandekore](https://darrenk.uk/)