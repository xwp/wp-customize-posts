=== Customize Posts ===
Contributors:      xwp, westonruter
Tags:              customizer, customize, posts, preview, featured-image, page-template
Requires at least: 4.5-beta2
Tested up to:      4.5-beta2
Stable tag:        trunk
License:           GPLv2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.html

Edit posts and postmeta in the Customizer. Stop editing your posts/postmeta blind!

== Description ==

*This is a feature plugin intended to implement [#34923](https://core.trac.wordpress.org/ticket/34923): Introduce basic content authorship in the Customize.*

The goal for this plugin is to be able to expose the editing of posts and pages in the Customizer, allowing you to edit post data and postmeta for any number of posts, and preview the changes before saving them for others to see. This plugin was birthed out of the Widget Customizer feature-as-plugin project which was merged into WordPress Core: as widgets (in 3.9) and nav menus (4.3) can now be managed in the Customizer, so too should posts and pages be editable in the Customizer as well.

Did you know that **changing the featured image actually makes the change live even before you save the post**? This is this very surprising/unexpected behavior. The only way to truly preview a change to a featured image is to use something like Customize Posts.

Likewise, did you know that **changing a page template cannot be previewed from the post editor?** When you change the selected page template, the change will not show up when you preview the post (see [#11049](https://core.trac.wordpress.org/ticket/11049)). However, in Customize Posts you *can* preview changes to the page template just by changing the dropdown selection, and then you can see what your page would look like with the new template after the preview refreshes. (Note: This ability was removed in the 0.3 rewrite but it will be re-added.)

Most other changes to metaboxes containing data that gets saved to custom fields (postmeta) also get written when clicking the Preview button. The Customize Posts plugin provides a way to get around this, and also provides a live preview of the changes. Fixing this underlying issue of incorrectly persisting postmeta when doing a preview is captured in [#20299](https://core.trac.wordpress.org/ticket/20299). (Custom fields were removed in the complete rewrite in 0.3; postmeta controls will be re-introduced.)

**Development of this plugin is done [on GitHub](https://github.com/xwp/wp-customize-posts). Pull requests welcome. Please see [issues](https://github.com/xwp/wp-customize-posts/issues) reported there before going to the [plugin forum](https://wordpress.org/support/plugin/customize-posts).**

This **Customize Posts** plugin is not to be confused with 10up's [**Post Customizer**](https://github.com/10up/Post-Customizer) plugin which is a complimentary effort but seeks to address different use cases. The two plugin projects have [opened a discussion](https://github.com/10up/Post-Customizer/issues/9#issuecomment-43821746) to collaborate where possible.

= Demo Videos =

1) [2016-03-01] Demonstration of hooking into edit post links so that they actually work in the Customizer and expand the section to edit the given post (as opposed to the link doing nothing at all when clicked), as well as shift-clicking on the title and content (needs better discovery UI, see [#27403](https://core.trac.wordpress.org/ticket/27403)):

[youtube https://www.youtube.com/watch?v=nYfph3NbNCc]

2) [2016-03-03] Demonstration of integration with [Customize Setting Validation](https://github.com/xwp/wp-customize-setting-validation) ([#34893](https://core.trac.wordpress.org/ticket/34893)) to gracefully handle failures to save due to post locking and concurrent user editing:

[youtube https://www.youtube.com/watch?v=OUwwTt6FtlQ]

3) [2016-03-04] Demo featuring the WP visual rich text editor (TinyMCE), including the insertion of images from the media library. Post content can be edited in the Customizer and previewed in multiple contexts. For example, this allows you to preview how a Read More tag will appear when the post appears on a post list page, and you can navigate to the single post to continue previewing subsequent paragraphs. You can expand the editor into a full-screen mode to focus on writing and then quickly preview the changes on the site by toggling the editor. You can make changes to as many posts as you want, but none of the changes will go live until you hit Save & Publish: everything is previewed so there is no “save and surprise”.

[youtube https://www.youtube.com/watch?v=QJsEl0gd7dk]

4) [2016-03-05] Opening a draft post in the Customizer to preview title wrapping.

[youtube https://www.youtube.com/watch?v=sXu2pA42J88]

== Changelog ==

= 0.3.0 =
* Complete rewrite of plugin.
* Added: Selective refresh is now used to preview changes to the title and content.
* Added: A TinyMCE editor is now used to edit content, including initial support for Shortcake.
* Added: Each post type has a separate panel. Each post is represented by a section within those panels.
* Added: Edit post links in Customizer preview now open post section.
* Added: Integration with [Customize Setting Validation](https://github.com/xwp/wp-customize-setting-validation) to show show error message when post locking or version conflict happens.
* Removed: Postmeta fields (custom fields, page template, featured image) were removed for rewrite but will be re-introduced.

= 0.2.4 =
Remove shim that implemented the `customize_save_response` filter which was introduced in 4.2. The shim used a slightly different filter name and broke insertion of nav menu items in the Customizer.

= 0.2.3 =
Change method for registering scripts/styles to fix conflict w/ Jetpack. [PR #26](https://github.com/xwp/wp-customize-posts/pull/26)

= 0.2.2 =
Add compatibility with WordPress 4.1 now that the Customizer has a proper JS API.

= 0.2.1 =
Supply missing `selected` attribute on `post_status` dropdown.

= 0.2.0 =
Initial release on WordPress.org. Key new features:

* Postmeta can now be added, modified, and deleted—all of actions which are fully previewable.
* Grant `customize` capability to authors and editors who normally can't access the Customizer, so they can edit posts there.
* Move the “Customize” admin bar link to the top level, and add one for editors and authors.
* Allow the Page Template and Featured Image to be modified and previewed.
