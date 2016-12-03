=== Customize Posts ===
Contributors:      xwp, westonruter, valendesigns
Tags:              customizer, customize, posts, postmeta, editor, preview, featured-image, page-template
Requires at least: 4.5
Tested up to:      4.7
Stable tag:        0.8.4
License:           GPLv2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.html

Edit posts and postmeta in the Customizer. Stop editing your posts/postmeta blind!

== Description ==

*This is a feature plugin intended to implement [#34923](https://core.trac.wordpress.org/ticket/34923): Introduce basic content authorship in the Customizer.*

The goal for this plugin is to be able to expose the editing of posts and pages in the Customizer, allowing you to edit post data and postmeta for any number of posts, and preview the changes before saving them for others to see. This plugin was birthed out of the Widget Customizer feature-as-plugin project which was merged into WordPress Core: as widgets (in 3.9) and nav menus (4.3) can now be managed in the Customizer, so too should posts and pages be editable in the Customizer as well.

Did you know that **changing the featured image actually makes the change live even before you save the post**? This is very surprising/unexpected behavior. The only way to truly preview a change to a featured image is to use something like Customize Posts.

Likewise, did you know that **changing a page template cannot be previewed from the post editor?** When you change the selected page template, the change will not show up when you preview the page (see [#11049](https://core.trac.wordpress.org/ticket/11049)). However, in Customize Posts you *can* preview changes to the page template just by changing the dropdown selection, and then you can see what your page would look like with the new template after the preview refreshes.

Most other changes to metaboxes containing data that gets saved to custom fields (postmeta) also get written when clicking the Preview button. The Customize Posts plugin provides a framework to edit postmeta in the Customizer with a live preview of the changes. (Fixing this underlying issue of incorrectly persisting postmeta when doing a preview is captured in [#20299](https://core.trac.wordpress.org/ticket/20299).)

As much as possible, the previewing of changes in Customize Posts utilizes the [selective refresh](https://make.wordpress.org/core/2016/02/16/selective-refresh-in-the-customizer/) capabilities introduced in WordPress 4.5. Not only does this mean it is faster to preview changes to posts and postmeta, but it also allows you to shift-click on an element to focus on the corresponding control in the Customizer pane. For example you can shift-click on the post title in the preview to focus on the post title control's input field, or shift-click on a featured image to focus on the control's button to open the media library.

**Development of this plugin is done [on GitHub](https://github.com/xwp/wp-customize-posts). Pull requests welcome. Please see [issues](https://github.com/xwp/wp-customize-posts/issues) reported there before going to the [plugin forum](https://wordpress.org/support/plugin/customize-posts).**

(This **Customize Posts** plugin is not to be confused with 10up's [**Post Customizer**](https://github.com/10up/Post-Customizer).)

= Demo Videos =

The following are listed in reverse chronological order. The first, more recent videos, show more polish.

[2016-04-28] New features in 0.5.0.

[youtube https://www.youtube.com/watch?v=2NXh-1_eUqI]

[2016-03-28] Previewing post from Post Edit screen.

[youtube https://www.youtube.com/watch?v=Q62nav1k4gY]

[2016-03-05] Opening a draft post in the Customizer to preview title wrapping.

[youtube https://www.youtube.com/watch?v=sXu2pA42J88]

[2016-03-04] Demo featuring the WP visual rich text editor (TinyMCE), including the insertion of images from the media library. Post content can be edited in the Customizer and previewed in multiple contexts. For example, this allows you to preview how a Read More tag will appear when the post appears on a post list page, and you can navigate to the single post to continue previewing subsequent paragraphs. You can expand the editor into a full-screen mode to focus on writing and then quickly preview the changes on the site by toggling the editor. You can make changes to as many posts as you want, but none of the changes will go live until you hit Save & Publish: everything is previewed so there is no “save and surprise”.

[youtube https://www.youtube.com/watch?v=QJsEl0gd7dk]

[2016-03-03] Demonstration of integration with [Customize Setting Validation](https://github.com/xwp/wp-customize-setting-validation) ([#34893](https://core.trac.wordpress.org/ticket/34893)) to gracefully handle failures to save due to post locking and concurrent user editing:

[youtube https://www.youtube.com/watch?v=OUwwTt6FtlQ]

[2016-03-01] Demonstration of hooking into edit post links so that they actually work in the Customizer and expand the section to edit the given post (as opposed to the link doing nothing at all when clicked), as well as shift-clicking on the title and content (needs better discovery UI, see [#27403](https://core.trac.wordpress.org/ticket/27403)):

[youtube https://www.youtube.com/watch?v=nYfph3NbNCc]

== Screenshots ==

1. [0.7.0] Select2 dropdown in a post type's panel allows all posts of that type to be searched, including trashes. Selecting a post here causes its section to be added and expanded, with the preview then navigating to the URL for that post.
2. [0.7.0] Post status control is now accompanied by a post date control. A Move to Trash link also appears with the status control.
3. [0.7.0] Selecting a future date switches status form published to scheduled, and a countdown for when the post will be scheduled is available along with the timezone information.
4. [0.7.0] Clicking the date reset link causes the setting's date to be emptied, with the control's inputs then receiving the current date/time as placeholders which update each minute to correspond to the current date/time.
5. [0.8.0] Integration with dropdown-pages controls. Buttons to add and edit posts appear next to the page on front and page for posts controls.
6. [0.8.0] Edit post button appears in nav menu items that link to a post or page.
7. [0.8.0] Post parent and basic menu order control.

== Changelog ==

= [0.8.4] - 2016-12-03 =

* Ensure auto-draft posts referenced in snapshot/changeset get transitioned to customize-draft, and that customize-draft nav_menus_created_posts get published (PR <a href="https://github.com/xwp/wp-customize-posts/pull/326" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/326" data-id="193245407">#326</a>).</li>
* Improve method for skipping attachments so no error in console appears (PR <a href="https://github.com/xwp/wp-customize-posts/pull/325" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/325" data-id="191920013">#325</a>, Issue <a href="https://github.com/xwp/wp-customize-posts/issues/32" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/32" data-id="138023072">#32</a>).</li>

See <a href="https://github.com/xwp/wp-customize-posts/milestone/12?closed=1">issues and PRs in milestone</a> and <a href="https://github.com/xwp/wp-customize-posts/compare/0.8.3...0.8.4">full release commit log</a>.

= [0.8.3] - 2016-11-24 =

* Prevent extra section params from being passed along to previewUrl (PR <a href="https://github.com/xwp/wp-customize-posts/pull/306" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/306" data-id="181028762">#306</a>)
* Fix errors and erroneous selective refresh caused by dirty post settings not being synced into nav menu item settings (PR <a href="https://github.com/xwp/wp-customize-posts/pull/307" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/307" data-id="181318396">#307</a>)
* Add compat for WP4.5 where settingConstructor is not defined (PR <a href="https://github.com/xwp/wp-customize-posts/pull/308" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/308" data-id="182835256">#308</a>)
* Fix handling of postmeta preview filter when no modifications are in customized state (PR <a href="https://github.com/xwp/wp-customize-posts/pull/309" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/309" data-id="183709726">#309</a>)
* Improve compatibility with Customize Changesets (PR <a href="https://github.com/xwp/wp-customize-posts/pull/300" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/300" data-id="180176248">#300</a>)
* Recognize and handle dropdown-pages control in WP 4.7-alpha (PR <a href="https://github.com/xwp/wp-customize-posts/pull/311" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/311" data-id="185033930">#311</a>)
* Prevent attempting to update original titles for removed nav menu items (placeholders) (PR <a href="https://github.com/xwp/wp-customize-posts/pull/312" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/312" data-id="185814279">#312</a>)
* Restore edit_post_link() in WordPress 4.7 (PR <a href="https://github.com/xwp/wp-customize-posts/pull/314" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/314" data-id="187211686">#314</a>)
* Disable polyfill for nav menu item loading/searching fix landing in 4.7 (PR <a href="https://github.com/xwp/wp-customize-posts/pull/315" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/315" data-id="187265752">#315</a>)
* Eliminate determination of user capability in setting constructor (PR <a href="https://github.com/xwp/wp-customize-posts/pull/316" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/316" data-id="187916567">#316</a>)
* Prevent sending back DB-persisted setting values in <code>customize_save_response</code> unless changeset is being published (PR <a href="https://github.com/xwp/wp-customize-posts/pull/317" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/317" data-id="190010791">#317</a>)
* Pass missing options param in wrapped <code>isLinkPrewable</code> function (PR <a href="https://github.com/xwp/wp-customize-posts/pull/318" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/318" data-id="190618522">#318</a>)
* Improve compat with posts &amp; pages created as stubs in 4.7 (PR <a href="https://github.com/xwp/wp-customize-posts/pull/320" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/320" data-id="191193786">#320</a>)

See <a href="https://github.com/xwp/wp-customize-posts/milestone/11?closed=1">issues and PRs in milestone</a> and <a href="https://github.com/xwp/wp-customize-posts/compare/0.8.2...0.8.3">full release commit log</a>.

Props Weston Ruter (<a href="https://github.com/westonruter" class="user-mention">@westonruter</a>), Sayed Taqui (<a href="https://github.com/sayedwp" class="user-mention">@sayedwp</a>), Utkarsh Patel (<a href="https://github.com/PatelUtkarsh" class="user-mention">@PatelUtkarsh</a>).

= [0.8.2] - 2016-10-03 =

* Fixed browser incompatible way of parsing local datetime strings. This is a follow-up on <a href="https://github.com/xwp/wp-customize-posts/pull/293" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/293" data-id="178983334">#293</a>, which was not fully fixed in 0.8.1. PR <a href="https://github.com/xwp/wp-customize-posts/pull/304" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/304" data-id="180808420">#304</a>.
* Improved fetching of post/postmeta settings so that the <code>customized</code> state is included in the request, and allow for placeholder <code>nav_menu_item</code> settings to be fetched. PR <a href="https://github.com/xwp/wp-customize-posts/pull/299" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/299" data-id="180154206">#299</a>.
* Added <code>$setting</code> context to <code>customize_previewed_postmeta_rows</code> filter and add new <code>customize_previewed_postmeta_rows_{$setting-&gt;post_meta}</code> filter. PR <a href="https://github.com/xwp/wp-customize-posts/pull/299" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/299" data-id="180154206">#299</a>.

Props Weston Ruter (<a href="https://github.com/westonruter" class="user-mention">@westonruter</a>), Utkarsh Patel (<a href="https://github.com/PatelUtkarsh" class="user-mention">@PatelUtkarsh</a>).

See <a href="https://github.com/xwp/wp-customize-posts/milestone/10?closed=1">issues and PRs in milestone</a> and <a href="https://github.com/xwp/wp-customize-posts/compare/0.8.1...0.8.2">full release commit log</a>.

= [0.8.1] - 2016-09-23 =

Fixed compatibility with Safari in the <code>wp.customize.Posts.getCurrentTime()</code> method. See <a href="https://github.com/xwp/wp-customize-posts/pull/293" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/293" data-id="178983334">#293</a>. Props Piotr Delawski (<a href="https://github.com/delawski" class="user-mention">@delawski</a>).

= [0.8.0] - 2016-09-22 =

Added:

* Add ability to edit and create pages from <code>dropdown-pages</code> controls, such as the controls for page on front and page for posts. Adds <code>startEditPageFlow</code> and <code>startAddPageFlow</code> methods from the <a href="https://wordpress.org/plugins/customize-object-selector/">Customize Object Selector</a> plugin. See <a href="https://github.com/xwp/wp-customize-posts/issues/271" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/271" data-id="177534175">#271</a> and PRs <a href="https://github.com/xwp/wp-customize-posts/pull/272" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/272" data-id="177604644">#272</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/284" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/284" data-id="177854768">#284</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/285" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/285" data-id="177931701">#285</a>.
* Allow page/post stubs created via nav menus in 4.7-alpha to also be edited like any other page. See <a href="https://github.com/xwp/wp-customize-posts/issues/253" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/253" data-id="176089785">#253</a> and PR <a href="https://github.com/xwp/wp-customize-posts/pull/287" class="issue-link js-issue-link" data-id="177968990" title="Insert full posts instead of stubs via nav menus">#287</a>.
* For nav menu items linking to a post/page, add a edit button that appears in the nav menu item control next to the original link. Clicking edit will expand the section for that post/page, and collapsing the section will return focus to the button in the nav menu item control. See <a href="https://github.com/xwp/wp-customize-posts/issues/147" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/147" data-id="156856956">#147</a> and PR <a href="https://github.com/xwp/wp-customize-posts/pull/288" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/288" data-id="178214146">#288</a>.
* Sync post/page changes to available nav menu items and nav menu item controls, ensuring titles are consistent in available item lists and in the nav menu item control's title, label placeholder, and original link, and in the nav menu item setting's <code>original_title</code> property. If a nav menu item lacks its own <code>title</code> and inherits from <code>original_title</code>, post/page changes will now trigger nav menu selective refresh updates. See <a href="https://github.com/xwp/wp-customize-posts/issues/156" class="issue-link js-issue-link" data-id="157966860" title="Ensure that newly-added posts/pages appear among list of available nav menu items">#156</a> and PR <a href="https://github.com/xwp/wp-customize-posts/pull/288" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/288" data-id="178214146">#288</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/289" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/289" data-id="178254750">#289</a>.
* Sync pages additions, changes, and removals to all <code>dropdown-pages</code> controls, in particular to the page on front and page for posts controls in the static front page section. See <a href="https://github.com/xwp/wp-customize-posts/issues/153" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/153" data-id="157836015">#153</a>. PRs <a href="https://github.com/xwp/wp-customize-posts/pull/190" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/190" data-id="162327507">#190</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/270" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/270" data-id="177513674">#270</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/275" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/275" data-id="177619431">#275</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/276" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/276" data-id="177621953">#276</a>.
* Add support post parent control via <a href="https://wordpress.org/plugins/customize-object-selector/">Customize Object Selector</a> plugin's control (in v0.3). See <a href="https://github.com/xwp/wp-customize-posts/issues/65" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/65" data-id="140029031">#65</a> and PRs <a href="https://github.com/xwp/wp-customize-posts/pull/189" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/189" data-id="162324880">#189</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/233" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/233" data-id="173935582">#233</a>.
* Add rudimentary number-based menu order control. See <a href="https://github.com/xwp/wp-customize-posts/issues/84" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/84" data-id="145841727">#84</a> and PR <a href="https://github.com/xwp/wp-customize-posts/pull/255" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/255" data-id="176195094">#255</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/257" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/257" data-id="176198972">#257</a>.
* Add previewing of title changes for pages listed by <code>wp_list_pages()</code>. PR <a href="https://github.com/xwp/wp-customize-posts/pull/256" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/256" data-id="176196258">#256</a>.
* Implement support for all of the <code>WP_Query</code> query vars, including post meta queries, ensuring results have posts' customized state applied as expected. This is a big improvement to ensure customized posts and postmeta will appear the same before or after saving the customized state. Previously, previewing <code>WP_Query</code> results was very limited and often not accurate. As part of this, <code>get_posts()</code> and any queries made with <code>suppress_filters</code> on will be overridden to force filters to apply. See <a href="https://github.com/xwp/wp-customize-posts/issues/246" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/246" data-id="174935332">#246</a> and PR <a href="https://github.com/xwp/wp-customize-posts/pull/248" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/248" data-id="175036035">#248</a>.
* Add support to <code>WP_Customize_Postmeta_Setting</code> for representing non-single postmeta. Such settings are instantiated with a true <code>single</code> arg and they require an array value. The <code>customize_sanitize_{$setting_id}</code> filter will apply to each array item separately, as will the <code>sanitize_meta()</code> call. PR <a href="https://github.com/xwp/wp-customize-posts/pull/248" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/248" data-id="175036035">#248</a>.
* Ensure that static front page constructs get registered even without any pages yet created. Add active callback to the static front page section so that it will appear as soon as a published page exists (either via adding a new page from the Pages panel or via a nav menu item page stub in WP 4.7-alpha). See <a href="https://github.com/xwp/wp-customize-posts/issues/252" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/252" data-id="176088241">#252</a> and PR <a href="https://github.com/xwp/wp-customize-posts/pull/254" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/254" data-id="176191968">#254</a>.
* Refactor editor control to be reusable for not just post content but also custom fields (postmeta). See PRs <a href="https://github.com/xwp/wp-customize-posts/pull/216" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/216" data-id="170141016">#216</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/269" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/269" data-id="177433639">#269</a>.
* Ensure that the results of <code>get_pages()</code> has the customized state applied. This enables support for <code>wp_dropdown_pages()</code> (and thus the post parent control). See <a href="https://github.com/xwp/wp-customize-posts/issues/241" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/241" data-id="174864969">#241</a> and PR <a href="https://github.com/xwp/wp-customize-posts/pull/250" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/250" data-id="175667734">#250</a>.
* Prevent the same page from being selected as both the page for posts and the page on front. PR <a href="https://github.com/xwp/wp-customize-posts/pull/270" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/270" data-id="177513674">#270</a>.
* Improve support for page for posts by hiding page template and content since not displayed. Show notice that the page for posts is being edited. See <a href="https://github.com/xwp/wp-customize-posts/issues/228" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/228" data-id="172810805">#228</a>, <a href="https://github.com/xwp/wp-customize-posts/issues/277" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/277" data-id="177631373">#277</a> and PR <a href="https://github.com/xwp/wp-customize-posts/pull/278" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/278" data-id="177632576">#278</a>.
* Allow <code>posts</code> component to be filtered out to be disabled like other components (<code>widgets</code> and <code>nav_menus</code>). See <a href="https://github.com/xwp/wp-customize-posts/issues/132" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/132" data-id="153294531">#132</a> with PRs <a href="https://github.com/xwp/wp-customize-posts/pull/219" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/219" data-id="171576042">#219</a> and <a href="https://github.com/xwp/wp-customize-posts/pull/258" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/258" data-id="176218976">#258</a>.
* Disable edit-post-links module in customize-direct-manipulation plugin so that edit links in the preview will continue to work in Customize Posts. PR <a href="https://github.com/xwp/wp-customize-posts/pull/234" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/234" data-id="174110828">#234</a>.
* Let Customize link in admin bar deep link on the post section when on a singular template. See <a href="https://github.com/xwp/wp-customize-posts/issues/105" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/105" data-id="150835037">#105</a> and PR <a href="https://github.com/xwp/wp-customize-posts/pull/236" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/236" data-id="174199695">#236</a>.
* Use non-minified scripts and styles if plugin installed via git (submodule), eliminating the need to add <code>SCRIPT_DEBUG</code> or do a build. PR <a href="https://github.com/xwp/wp-customize-posts/pull/249" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/249" data-id="175164833">#249</a>.
* Add all to <code>wp_transition_post_status()</code><code>when transitioning from</code>auto-draft<code>to</code>customize-draft`. PR <a href="https://github.com/xwp/wp-customize-posts/pull/266" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/266" data-id="177001743">#266</a>.
* Include nav menu items and their postmeta in requests to fetch settings. This is to facilitate <a href="https://github.com/xwp/wp-customize-nav-menu-item-custom-fields">custom fields in nav menus items</a>. PRs <a href="https://github.com/xwp/wp-customize-posts/pull/263" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/263" data-id="176788265">#263</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/268" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/268" data-id="177095805">#268</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/274" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/274" data-id="177611421">#274</a>.
* Support constructing <code>Setting</code> objects in JS using a specific <code>settingConstructor</code> if defined for a given setting <code>type</code> when fetching settings. PR <a href="https://github.com/xwp/wp-customize-posts/pull/286" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/286" data-id="177933251">#286</a>.

Changed:

* For registered post type attributes, use <code>public</code> instead of <code>show_ui</code> as default <code>show_in_customizer</code> flag. See <a href="https://github.com/xwp/wp-customize-posts/issues/264" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/264" data-id="176943625">#264</a> and PR <a href="https://github.com/xwp/wp-customize-posts/pull/265" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/265" data-id="176998017">#265</a>.

Fixed:

* Restrict page template control to just the page post type (as opposed to other post types that support <code>page-attributes</code>). PR <a href="https://github.com/xwp/wp-customize-posts/pull/227" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/227" data-id="172810482">#227</a>.
* Ensure that preview urls are used as permalinks for customized posts. PR <a href="https://github.com/xwp/wp-customize-posts/pull/245" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/245" data-id="174934233">#245</a>.
* Ensure the page for posts can be previewed as the page for posts (and not a normal page). See <a href="https://github.com/xwp/wp-customize-posts/issues/260" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/260" data-id="176228893">#260</a> and PR <a href="https://github.com/xwp/wp-customize-posts/pull/292" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/292" data-id="178398516">#292</a>.
* Fix ability to do translation via translate.wordpress.org. PR <a href="https://github.com/xwp/wp-customize-posts/pull/215" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/215" data-id="169808602">#215</a>.
* Ensure wp_insert_post_empty_content filter gets removed after trashing. PR <a href="https://github.com/xwp/wp-customize-posts/pull/214" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/214" data-id="169766481">#214</a>.
* Fix theme compat for twentysixteen content rendering by including additional template tags. PR <a href="https://github.com/xwp/wp-customize-posts/pull/226" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/226" data-id="172805472">#226</a>.
* Fix display of notifications in date control. See <a href="https://github.com/xwp/wp-customize-posts/issues/229" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/229" data-id="173005101">#229</a> and PR <a href="https://github.com/xwp/wp-customize-posts/pull/230" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/230" data-id="173150330">#230</a>.
* Use <code>notification.setting</code> instead of <code>notification.data.setting</code>. PR <a href="https://github.com/xwp/wp-customize-posts/pull/238" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/238" data-id="174457778">#238</a>.
* Add syncing of slug (<code>post_name</code>) between edit post screen and customize post preview. PR <a href="https://github.com/xwp/wp-customize-posts/pull/239" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/239" data-id="174649735">#239</a>.
* Eliminate post type names in generic strings to ensure ability to translate. See <a href="https://github.com/xwp/wp-customize-posts/issues/237" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/237" data-id="174353198">#237</a> and PR <a href="https://github.com/xwp/wp-customize-posts/pull/242" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/242" data-id="174871134">#242</a>.
* Use flexbox for post selection lookup select2 and add button. PR <a href="https://github.com/xwp/wp-customize-posts/pull/244" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/244" data-id="174921132">#244</a>.
* Remove underline from time-info-handle in 4.7-alpha. PR <a href="https://github.com/xwp/wp-customize-posts/pull/242" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/242" data-id="174871134">#242</a>.
* Make sure <code>post_author</code> is string in PHP but keep int in JS. PR <a href="https://github.com/xwp/wp-customize-posts/pull/251" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/251" data-id="175925801">#251</a>.
* Ensure that a post section is expanded when a post editor control is expanded. Issue <a href="https://github.com/xwp/wp-customize-posts/issues/259" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/259" data-id="176227695">#259</a> and PR <a href="https://github.com/xwp/wp-customize-posts/pull/280" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/280" data-id="177633269">#280</a>.
* Prevent ESC key from causing the editor to collapse unexpectedly. See <a href="https://github.com/xwp/wp-customize-posts/issues/281" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/281" data-id="177684798">#281</a> and PR <a href="https://github.com/xwp/wp-customize-posts/pull/282" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/282" data-id="177709885">#282</a>.
* Ensure that all TinyMCE UIs are hidden when the editor control is collapsed. See <a href="https://github.com/xwp/wp-customize-posts/issues/231" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/231" data-id="173576573">#231</a> and PR <a href="https://github.com/xwp/wp-customize-posts/pull/282" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/282" data-id="177709885">#282</a>.

Removed:

 * Removed the `customize_previewed_posts_for_query` filter since now unnecessary and irrelevant after refactor in #248.

See [issues and PRs in milestone](https://github.com/xwp/wp-customize-posts/milestone/6?closed=1) and [full release commit log](https://github.com/xwp/wp-customize-posts/compare/0.7.0...0.8.0)

Props: Weston Ruter (<a href="https://github.com/westonruter" class="user-mention">@westonruter</a>), Sayed Taqui (<a href="https://github.com/sayedwp" class="user-mention">@sayedwp</a>), Sunny Ratilal (<a href="https://github.com/sunnyratilal" class="user-mention">@sunnyratilal</a>), Ryan Kienstra (<a href="https://github.com/kienstra" class="user-mention">@kienstra</a>), Eduard Maghakyan (<a href="https://github.com/EduardMaghakyan" class="user-mention">@EduardMaghakyan</a>).

= [0.7.0] - 2016-08-06 =

Added:

 * Introduce Select2 dropdown in a post type's panel for searching for any post to load, even if it is not shown in the preview. Selecting a post adds its section to the Customizer and expands it while also navigating to the post's URL in the preview. Trashed posts are also listed, and selecting a trashed post will load its post data into the Customizer in its untrashed state (restoring its original status and slug) so that upon save it will be be untrashed. (<a href="https://github.com/xwp/wp-customize-posts/pull/196" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/196" data-id="164758731">#196</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/199" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/199" data-id="167144506">#199</a>)
 * Add control for post <em>date</em>, including synchronization with publish/future status for when date is in future. Control includes timezone information and countdown for when schedule publish will happen. Also includes reset link to leave the date empty so that it will default to the current date/time when it is published. (<a href="https://github.com/xwp/wp-customize-posts/issues/56" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/56" data-id="139950255">#56</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/202" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/202" data-id="167405100">#202</a>)
 * Improve UX for trashing posts, adding a Move to Trash link. (<a href="https://github.com/xwp/wp-customize-posts/issues/172" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/172" data-id="159524166">#172</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/211" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/211" data-id="169467306">#211</a>)
 * Add initial support for meta queries when the Customizer state includes changes to postmeta. (<a href="https://github.com/xwp/wp-customize-posts/pull/174" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/174" data-id="159564977">#174</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/191" class="issue-link js-issue-link" data-id="162754015" title="Improve meta query support">#191</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/197" class="issue-link js-issue-link" data-id="165623206" title="Ensure meta queries work for newly-inserted auto draft posts">#197</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/193" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/193" data-id="163417852">#193</a>)
 * Allow post field control labels to be defined in <code>register_post_type()</code> so that the names can be repurposed to be appropriate to the custom post type. (<a href="https://github.com/xwp/wp-customize-posts/pull/195" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/195" data-id="163751896">#195</a>)
 * Add low-fidelity live JS-applied previews to post title changes while waiting for high-fidelity PHP-applied selective refresh requests to response. (<a href="https://github.com/xwp/wp-customize-posts/issues/43" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/43" data-id="139483578">#43</a>)

Fixed:

 * Ensure title is focused and selected when creating a new post. (<a href="https://github.com/xwp/wp-customize-posts/issues/209" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/209" data-id="168734888">#209</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/208" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/208" data-id="168732561">#208</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/206" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/206" data-id="168702760">#206</a>)
 * Make sure that posts are loaded for any post sections/controls that are autofocused. (<a href="https://github.com/xwp/wp-customize-posts/issues/204" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/204" data-id="168454674">#204</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/205" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/205" data-id="168479600">#205</a>)
 * Speed up performance by registering post/postmeta settings and partials only as they are needed (just in time), introducing a new <code>ensurePosts</code> API call to fetch the settings over Ajax and create the relevant section. (<a href="https://github.com/xwp/wp-customize-posts/pull/201" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/201" data-id="167209668">#201</a>)
 * Improve test coverage. (<a href="https://github.com/xwp/wp-customize-posts/pull/200" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/200" data-id="167208162">#200</a>)
 * Fix editor text not persistent issue across all posts and post types. (<a href="https://github.com/xwp/wp-customize-posts/issues/129" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/129" data-id="152863642">#129</a>, <a href="https://github.com/xwp/wp-customize-posts/pull/198" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/198" data-id="166660326">#198</a>)
 * Sort posts in a section reverse-chronologically by date (if non-hierarchical) or else by <code>menu_order</code> if hierarchical. (<a href="https://github.com/xwp/wp-customize-posts/issues/124" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/124" data-id="151904091">#124</a>)
 * Ensure changes to post status are reflected in return value when calling <code>get_post_status()</code>. (<a href="https://github.com/xwp/wp-customize-posts/pull/194" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/194" data-id="163502015">#194</a>)

See full commit log: [`0.6.1...0.7.0`](https://github.com/xwp/wp-customize-posts/compare/0.6.1...0.7.0)

Issues in milestone: [`milestone:0.7.0`](https://github.com/xwp/wp-customize-posts/issues?q=milestone%3A0.7)

Props: Weston Ruter (<a href="https://github.com/westonruter" class="user-mention">@westonruter</a>), John Regan (<a href="https://github.com/johnregan3" class="user-mention">@johnregan3</a>), Sayed Taqui (<a href="https://github.com/sayedwp" class="user-mention">@sayedwp</a>), Utkarsh Patel (<a href="https://github.com/PatelUtkarsh" class="user-mention">@PatelUtkarsh</a>), Luke Gedeon (<a href="https://github.com/lgedeon" class="user-mention">@lgedeon</a>), Ahmad Awais (<a href="https://github.com/ahmadawais" class="user-mention">@ahmadawais</a>), Derek Herman (<a href="https://github.com/valendesigns" class="user-mention">@valendesigns</a>), Piotr Delawski (<a href="https://github.com/delawski" class="user-mention">@delawski</a>)

= [0.6.1] - 2016-06-16 =

* Send values to JS via `js_value()` and use the settings `json` method if available.
* Move `comments_open` and `pings_open` filters to the `WP_Customize_Posts_Preview::add_preview_filters` method.
* Fix `purgeTrash` to ensure trashed post sections do not appear in the Customizer root panel after publishing changes.
* Ensure the modified date is not changed when transitioning to `customize-draft`.
* Make sure the `customize-draft` status is always available to Customize Snapshots & `wp-admin`
* Fix PHP notice generated when a post type is registered without `map_meta_cap`
* Delete `auto-draft` and `customize-draft` status posts when saving the trash `post_status`
* Use a post type's `edit_posts` capability for sections
* Defer embedding a sections contents until expanded
* Implement `focusControl` support for `deferred-embedded` post section controls
* Add support for focusing on controls for setting properties when those properties are invalid
* Prevent `customized-posts` messages sent via `selective-refresh` from effecting `post-navigation` state
* Improve feature detection for including customize-controls patched for trac-36521
* Included plugin-support and theme-support PHP files that were inadvertantly omitted from the 0.6.0 build.

See full commit log: [`0.6.0...0.6.1`](https://github.com/xwp/wp-customize-posts/compare/0.6.0...0.6.1)

Issues in milestone: [`milestone:0.6.1`](https://github.com/xwp/wp-customize-posts/issues?q=milestone%3A0.6.1)

Props: Weston Ruter (<a href="https://github.com/westonruter" class="user-mention">@westonruter</a>), Derek Herman (<a href="https://github.com/valendesigns" class="user-mention">@valendesigns</a>)

= 0.6.0 - 2016-06-02 =

Added:

 * Add the ability to create new posts and pages in the Customizer. Created posts get <code>auto-draft</code> status in the DB so they will be garbage-collected if the Customizer is never saved. A new view link appears in the post section allowing a newly-created post to be navigated to easily without having to find the created post linked to in the preview. (Issues <a href="https://github.com/xwp/wp-customize-posts/issues/48" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/48" data-id="139489948">#48</a>, <a href="https://github.com/xwp/wp-customize-posts/issues/50" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/50" data-id="139490828">#50</a>, PR <a href="https://github.com/xwp/wp-customize-posts/pull/134" class="issue-link js-issue-link" data-id="154290445" title="Post Creation">#134</a>)
 * Add post status control and preview, with <code>trash</code> status support (Issues <a href="https://github.com/xwp/wp-customize-posts/issues/40" class="issue-link js-issue-link" data-id="138757986" title="Add post status dropdown selection control and preview">#40</a>, <a href="https://github.com/xwp/wp-customize-posts/issues/137" class="issue-link js-issue-link" data-id="154582644" title="Delete a post whilst in the Customizer">#137</a>, PR <a href="https://github.com/xwp/wp-customize-posts/pull/152" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/152" data-id="157317131">#152</a>)
 * Add support for setting validation in WordPress 4.6-alpha, showing notifications if attempting to save when a post is locked or a conflicting update was previously made. (Issue <a href="https://github.com/xwp/wp-customize-posts/issues/142" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/142" data-id="156058078">#142</a>, PR <a href="https://github.com/xwp/wp-customize-posts/pull/150" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/150" data-id="156916244">#150</a>)
 * Add the ability to vertically resize the post editor (Issue <a href="https://github.com/xwp/wp-customize-posts/issues/136" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/136" data-id="154541791">#136</a>, PR <a href="https://github.com/xwp/wp-customize-posts/pull/149" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/149" data-id="156898366">#149</a>)
 * Add post slug control, wherein changes do not cause the preview to refresh by default since there is nothing to see (Issue <a href="https://github.com/xwp/wp-customize-posts/issues/63" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/63" data-id="140028525">#63</a>, PR <a href="https://github.com/xwp/wp-customize-posts/pull/148" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/148" data-id="156877309">#148</a>)
 * Posts data as saved will now be synced back into the Customizer interface, ensuring that if a post slug gets the infamous <code>-2</code> added, you’ll see that in the Control. Likewise, if a <code>wp_insert_post_data</code> filter or <code>content_save_pre</code> changes your data in some way, these will be shown in the post’s Customizer controls upon saving.
 * Add extendable theme &amp; plugin compatibility classes that can configure partial rendering. All Core themes &amp; Jetpack are currently supported. (Issues <a href="https://github.com/xwp/wp-customize-posts/issues/82" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/82" data-id="145302561">#82</a>, <a href="https://github.com/xwp/wp-customize-posts/issues/103" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/103" data-id="150771094">#103</a>, PR <a href="https://github.com/xwp/wp-customize-posts/pull/123" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/123" data-id="151805533">#123</a>)
 * Use <code>plugins_url()</code> for each asset URL so that the plugin can be installed as a submodule without <code>SCRIPT_DEBUG</code> (Issue <a href="https://github.com/xwp/wp-customize-posts/pull/133" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/133" data-id="154007394">#133</a>)

Fixed:

 * Add all postmeta settings for registered types not just the ones actually referenced (Issues <a href="https://github.com/xwp/wp-customize-posts/pull/141" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/141" data-id="155671511">#141</a>, <a href="https://github.com/xwp/wp-customize-posts/issues/145" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/145" data-id="156801928">#145</a>)
 * Export all registered post types to client, but only register panels if <code>show_in_customizer</code> (PR <a href="https://github.com/xwp/wp-customize-posts/pull/130" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/130" data-id="152866156">#130</a>)
 * Ensure that control pane expand button is visible when editor is open and the Customizer pane is collapsed (Issue <a href="https://github.com/xwp/wp-customize-posts/issues/44" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/44" data-id="139484076">#44</a>, PR <a href="https://github.com/xwp/wp-customize-posts/pull/126" class="issue-link js-issue-link" data-url="https://github.com/xwp/wp-customize-posts/issues/126" data-id="152710123">#126</a>)
 * Improve compatibility with the Customize Snapshots plugin.
 * Improve compatibility with the WP REST API plugin.
 * Supply a default <code>(no title)</code> placeholder to the post title control for new posts.
 * Filter post and page links in the Customizer to return the preview URL.

See full commit log: [`0.5.0...0.6.0`](https://github.com/xwp/wp-customize-posts/compare/0.5.0...0.6.0)

Issues in milestone: [`milestone:0.6`](https://github.com/xwp/wp-customize-posts/issues?q=milestone%3A0.6)

Props: Weston Ruter (<a href="https://github.com/westonruter" class="user-mention">@westonruter</a>), Derek Herman (<a href="https://github.com/valendesigns" class="user-mention">@valendesigns</a>), Philip Ingram (<a href="https://github.com/pingram3541" class="user-mention">@pingram3541</a>), Daniel Bachhuber (<a href="https://github.com/danielbachhuber" class="user-mention">@danielbachhuber</a>), Stuart Shields (<a href="https://github.com/stuartshields" class="user-mention">@stuartshields</a>)

= 0.5.0 - 2016-04-27 =

Added:

* Support for postmeta, including a framework for registering postmeta types. (Issues #1, PR #89)
* Page template control and preview, with sync from Customizer post preview back to post edit screen. (Issue #85, PR #89)
* Featured image control, with sync from Customizer post preview back to post edit screen. Changes to the featured image can now be previewed, where normally this is not possible in WordPress. Improved featured image selection on edit post screen to not update featured image in place, instead waiting until the post is Saved until updating the featured image postmeta. The featured image can be set from the post edit screen and then previewed in the Customizer via the post Preview Changes button: the featured image can be further changed in the Customizer post preview, with changes synced back to the post edit screen when the Customizer post preview is exited. (Issue #57, PR #102)
* Author control and preview, with sync from Customizer post preview back to post edit screen (Issue #62, PRs #89 #92)
* Excerpt control and preview, with sync from Customizer post preview back to post edit screen (Issue #60, PR #91)
* Comment status control and preview, with sync from Customizer post preview back to post edit screen (Issues #61, PR #100)
* Ping status control and preview, with sync from Customizer post preview back to post edit screen (Issue #64, PR #100)
* Improve PHPUnit test coverage to 98%.
* Note: Selective refresh support was specifically tested with Twenty Fifteen and Twenty Sixteen. See #103 for a way for themes to configure how they represent the various post fields in template parts.

Fixed:

* Improve editor styles in mobile and in fullscreen mode. (Issue #45, PR #107)
* Modals, toolbars, and tooltips and are no longer hidden (Issue #80, PRs #81, #101).
* Improve compatibility with Customize Widgets Plus (PR #83). See also https://github.com/xwp/wp-customize-widgets-plus/pull/46 for a fix in the Customizer post preview.
* Export post/postmeta settings during selective refresh requests so that new posts added will appear in the panel, such as when adding the number of posts to show in the Recent Posts widget. (Issue #97, PR #99)
* Improve compatibility with Customize Snapshots (PR #95)

See [v0.5 release post](https://make.xwp.co/2016/04/29/customize-posts-v0-5-released/) on Make XWP.

See full commit log: [`0.4.2...0.5.0`](https://github.com/xwp/wp-customize-posts/compare/0.4.2...0.5.0)

Issues in milestone: [`milestone:0.5`](https://github.com/xwp/wp-customize-posts/issues?q=milestone%3A0.5)

Props: Weston Ruter (@westonruter), Derek Herman (@valendesigns), Luke Carbis (@lukecarbis), Mike Crantea (@mehigh), Stuart Shields (@stuartshields)

= 0.4.2 - 2016-03-30 =
Restore stylesheet erroneously deleted during `grunt deploy`.

= 0.4.1 [YANKED] =
* Restore editability of pages in the Customizer (remove default condition that a post type have `publicly_queryable` as `true`).
* Log errors in `customize-posts` message receiver instead of throwing them.

= 0.4.0 - 2016-03-29 =
* Open Customizer to preview and make additional changes when clicking Preview from post edit admin screen (see [video](https://www.youtube.com/watch?v=Q62nav1k4gY)).
* Introduce `show_in_customizer` arg for `register_post_type()`, and let override condition on `show_ui` ~~and `publicly_queryable`~~ being both true.
* Fix modals and inline toolbars in TinyMCE editor displayed in Customizer.
* Fix initialization when TinyMCE does not default to Visual.
* Complete support for Jetpack Infinite Scroll, ensuring posts are listed in Customizer in order of appearance.
* Remove dependency on widgets component being loaded.
* Allow auto-draft posts to be previewed.
* Add Grunt, contributing.

= 0.3.0 - 2016-03-08 =
* Complete rewrite of plugin.
* Added: Selective refresh is now used to preview changes to the title and content.
* Added: A TinyMCE editor is now used to edit content, including initial support for Shortcake.
* Added: Each post type has a separate panel. Each post is represented by a section within those panels.
* Added: Edit post links in Customizer preview now open post section.
* Added: Integration with [Customize Setting Validation](https://github.com/xwp/wp-customize-setting-validation) to show show error message when post locking or version conflict happens.
* Removed: Postmeta fields (custom fields, page template, featured image) were removed for rewrite but will be re-introduced.

= 0.2.4 - 2016-01-06 =
Remove shim that implemented the `customize_save_response` filter which was introduced in 4.2. The shim used a slightly different filter name and broke insertion of nav menu items in the Customizer.

= 0.2.3 - 2015-01-09 =
Change method for registering scripts/styles to fix conflict w/ Jetpack. [PR #26](https://github.com/xwp/wp-customize-posts/pull/26)

= 0.2.2 - 2014-12-12 =
Add compatibility with WordPress 4.1 now that the Customizer has a proper JS API.

= 0.2.1 - 2014-09-22 =
Supply missing `selected` attribute on `post_status` dropdown.

= 0.2.0 - 2014-09-17 =
Initial release on WordPress.org. Key new features:

* Postmeta can now be added, modified, and deleted—all of actions which are fully previewable.
* Grant `customize` capability to authors and editors who normally can't access the Customizer, so they can edit posts there.
* Move the “Customize” admin bar link to the top level, and add one for editors and authors.
* Allow the Page Template and Featured Image to be modified and previewed.
