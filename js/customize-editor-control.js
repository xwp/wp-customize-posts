/* global jQuery, wp, _, tinyMCE */
/* eslint consistent-this: [ "error", "control" ] */

(function( api, $ ) {
	'use strict';

	/**
	 * An editor control.
	 */
	api.EditorControl = api.controlConstructor.dynamic.extend({

		initialize: function( id, options ) {
			var control = this, args;

			args = {};
			args.params = _.extend(
				{
					type: 'editor',
					section: '',
					priority: 25,
					label: api.Posts.data.l10n.fieldContentLabel,
					active: true,
					field_type: 'textarea',
					setting_property: 'post_content'
				},
				options.params || {}
			);

			api.controlConstructor.dynamic.prototype.initialize.call( control, id, args );

			control.deferred.embedded.done( function() {
				control.editorControl();
			});
		},

		editorControl: function() {
			var control  = this,
			    section  = api.section( control.section() ),
			    setting  = api( section.id );

			control.customizePreview = $( '#customize-preview' );
			control.editorDragbar    = $( '#customize-posts-content-editor-dragbar' );
			control.editorPane       = $( '#customize-posts-content-editor-pane' );
			control.editorFrame      = $( '#customize-posts-content_ifr' );
			control.collapseSidebar  = $( '.collapse-sidebar' );

			control.editorExpanded = new api.Value( false );
			control.editorToggleExpandButton = $( '<button type="button" class="button"></button>' );
			control.updateEditorToggleExpandButtonLabel( control.editorExpanded.get() );

			/**
			 * Update the setting value when the editor changes its state.
			 *
			 * @returns {void}
			 */
			control.onVisualEditorChange = function() {
				var value, editor;
				if ( control.editorSyncSuspended ) {
					return;
				}
				editor = tinyMCE.get( 'customize-posts-content' );
				value = wp.editor.removep( editor.getContent() );
				control.editorSyncSuspended = true;
				control.propertyElements[0].set( value );
				control.editorSyncSuspended = false;
			};

			/**
			 * Update the setting value when the editor changes its state.
			 *
			 * @returns {void}
			 */
			control.onTextEditorChange = function() {
				if ( control.editorSyncSuspended ) {
					return;
				}
				control.editorSyncSuspended = true;
				control.propertyElements[0].set( $( this ).val() );
				control.editorSyncSuspended = false;
			};

			/**
			 * Update the editor when the setting changes its state.
			 */
			setting.bind( function( newPostData, oldPostData ) {
				var editor, textarea = $( '#customize-posts-content' ),
				    differentSettingValues = newPostData[ control.params.setting_property ] !== oldPostData[ control.params.setting_property ];

				if ( control.editorExpanded.get() && ! control.editorSyncSuspended && differentSettingValues ) {
					control.editorSyncSuspended = true;
					editor = tinyMCE.get( 'customize-posts-content' );
					if ( editor && ! editor.isHidden() ) {
						editor.setContent( wp.editor.autop( newPostData[ control.params.setting_property ] ) );
					} else {
						textarea.val( newPostData[ control.params.setting_property ] );
					}
					control.editorSyncSuspended = false;
				}
			} );

			/**
			 * Update the button text when the expanded state changes;
			 * toggle editor visibility, and the binding of the editor
			 * to the post setting.
			 */
			control.editorExpanded.bind( function( expanded ) {
				var editor,
				    textarea = $( '#customize-posts-content' ),
					settingValue = setting()[ control.params.setting_property ];

				editor = tinyMCE.get( 'customize-posts-content' );
				control.updateEditorToggleExpandButtonLabel( expanded );
				$( document.body ).toggleClass( 'customize-posts-content-editor-pane-open', expanded );

				if ( expanded ) {
					if ( editor && ! editor.isHidden() ) {
						editor.setContent( wp.editor.autop( settingValue ) );
					} else {
						textarea.val( settingValue );
					}
					editor.on( 'input change keyup', control.onVisualEditorChange );
					textarea.on( 'input', control.onTextEditorChange );
					control.resizeEditor.call( control, window.innerHeight - control.editorPane.height() );
				} else {
					editor.off( 'input change keyup', control.onVisualEditorChange );
					textarea.off( 'input', control.onTextEditorChange );

					// Cancel link and force a click event to exit fullscreen & kitchen sink mode.
					editor.execCommand( 'wp_link_cancel' );
					$( '.mce-active' ).click();
					control.customizePreview.css( 'bottom', '' );
					control.collapseSidebar.css( 'bottom', '' );
				}
			} );

			/**
			 * Unlink the editor from this post and collapse the editor when the section is collapsed.
			 */
			section.expanded.bind( function( expanded ) {
				if ( expanded ) {
					api.Posts.postIdInput.val( section.params.post_id );
				} else {
					api.Posts.postIdInput.val( '' );
					control.editorExpanded.set( false );
				}
			} );

			/**
			 * Toggle the editor when clicking the button, focusing on it if it is expanded.
			 */
			control.editorToggleExpandButton.on( 'click', function() {
				var editor = tinyMCE.get( 'customize-posts-content' );
				control.editorExpanded.set( ! control.editorExpanded() );
				if ( control.editorExpanded() ) {
					editor.focus();
				}
			} );

			/**
			 * Expand the editor and focus on it when the post content control is focused.
			 *
			 * @param {object} args Focus args.
			 * @returns {void}
			 */
			control.focus = function( args ) {
				var editor = tinyMCE.get( 'customize-posts-content' );
				api.controlConstructor.dynamic.prototype.focus.call( control, args );
				control.editorExpanded.set( true );
				editor.focus();
			};

			// Resize the editor.
			control.editorDragbar.on( 'mousedown', function() {
				if ( ! section.expanded() ) {
					return;
				}
				$( document ).on( 'mousemove.customize-posts-editor', function( event ) {
					event.preventDefault();
					$( document.body ).addClass( 'customize-posts-content-editor-pane-resize' );
					control.editorFrame.css( 'pointer-events', 'none' );
					control.resizeEditor.call( control, event.pageY );
				} );
			} );

			// Remove editor resize.
			control.editorDragbar.on( 'mouseup', function() {
				if ( ! section.expanded() ) {
					return;
				}
				$( document ).off( 'mousemove.customize-posts-editor' );
				$( document.body ).removeClass( 'customize-posts-content-editor-pane-resize' );
				control.editorFrame.css( 'pointer-events', '' );
			} );

			// Resize the editor when the viewport changes.
			$( window ).on( 'resize', function() {
				var resizeDelay = 50;
				if ( ! section.expanded() ) {
					return;
				}
				_.delay( function() {
					control.resizeEditor.call( control, window.innerHeight - control.editorPane.height() );
				}, resizeDelay );
			} );

			control.injectButton();
		},

		/**
		 * Inject button in place of textarea.
		 *
		 * @return void
	     */
		injectButton: function() {
			var control = this,
			    textarea = control.container.find( 'textarea:first' );

			control.editorToggleExpandButton.attr( 'id', textarea.attr( 'id' ) );
			textarea.attr( 'id', '' );
			control.container.append( control.editorToggleExpandButton );
		},

		/**
		 * Update editor toggle expand button text.
		 *
	     * @param expanded
		 * @return void
		 */
		updateEditorToggleExpandButtonLabel: function( expanded ) {
			var control = this;
			control.editorToggleExpandButton.text( expanded ? api.Posts.data.l10n.closeEditor : api.Posts.data.l10n.openEditor );
		},

		/**
		 * Vertically Resize Expanded Post Editor.
		 *
		 * @param {int} position - The position of the post editor from the top of the browser window.
		 * @returns {void}
		 */
		resizeEditor: function( position ) {
			var control = this,
				windowHeight = window.innerHeight,
			    windowWidth = window.innerWidth,
			    sectionContent = $( '[id^=accordion-panel-posts] ul.accordion-section-content' ),
			    mceTools = $ ('#wp-customize-posts-content-editor-tools'),
			    mceToolbar = $ ('.mce-toolbar-grp'),
			    mceStatusbar = $ ('.mce-statusbar'),
			    minScroll = 40,
			    maxScroll = 1,
			    mobileWidth = 782,
			    collapseMinSpacing = 56,
			    collapseBottomOutsideEditor = 8,
			    collapseBottomInsideEditor = 4,
			    args = {},
			    resizeHeight;

			if ( ! $( document.body ).hasClass( 'customize-posts-content-editor-pane-open' ) ) {
				return;
			}

			if ( ! _.isNaN( position ) ) {
				resizeHeight = windowHeight - position;
			}

			args.height = resizeHeight;
			args.components = mceTools.outerHeight() + mceToolbar.outerHeight() + mceStatusbar.outerHeight();

			if ( resizeHeight < minScroll ) {
				args.height = minScroll;
			}

			if ( resizeHeight > windowHeight - maxScroll ) {
				args.height = windowHeight - maxScroll;
			}

			if ( windowHeight < control.editorPane.outerHeight() ) {
				args.height = windowHeight;
			}

			control.customizePreview.css( 'bottom', args.height );
			control.editorPane.css( 'height', args.height );
			control.editorFrame.css( 'height', args.height - args.components );
			control.collapseSidebar.css( 'bottom', args.height + collapseBottomOutsideEditor );

			if ( collapseMinSpacing > windowHeight - args.height ) {
				control.collapseSidebar.css( 'bottom', mceStatusbar.outerHeight() + collapseBottomInsideEditor );
			}

			if ( windowWidth <= mobileWidth ) {
				sectionContent.css( 'padding-bottom', args.height );
			} else {
				sectionContent.css( 'padding-bottom', '' );
			}
		}

});

	api.controlConstructor.editor = api.EditorControl;

})( wp.customize, jQuery );
