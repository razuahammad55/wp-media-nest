/**
 * WP Media Nest - Media Library Integration
 *
 * Extends wp.media views to integrate folder functionality.
 *
 * @package WP_Media_Nest
 */

/* global jQuery, wp, wpMediaNest, MediaNestFolderTree */

( function( $, wp ) {
	'use strict';

	// Ensure dependencies are loaded.
	if ( ! wp || ! wp.media || ! wpMediaNest ) {
		return;
	}

	const mediaNest = {

		/**
		 * Current folder ID.
		 *
		 * @type {number}
		 */
		currentFolder: -1,

		/**
		 * Folder tree instance.
		 *
		 * @type {MediaNestFolderTree|null}
		 */
		folderTree: null,

		/**
		 * Initialize the integration.
		 */
		init: function() {
			this.extendAttachmentsBrowser();
			this.extendAttachmentsCollection();
			this.extendToolbar();
			this.setupUploadHandler();
			this.setupDragDropOnAttachments();

			// Initialize on media library page.
			if ( wpMediaNest.isMediaPage ) {
				this.initMediaLibraryPage();
			}
		},

		/**
		 * Initialize on media library page (upload.php).
		 */
		initMediaLibraryPage: function() {
			const self = this;

			// Wait for media grid to be ready.
			$( document ).ready( function() {
				self.injectFolderSidebar();
			} );
		},

		/**
		 * Inject folder sidebar into media library.
		 */
		injectFolderSidebar: function() {
			const self = this;

			// Create sidebar container.
			const $sidebar = $( '<div class="media-nest-sidebar"></div>' );
			const $wrap = $( '.wrap' );

			if ( $wrap.length ) {
				$wrap.addClass( 'media-nest-active' );
				$wrap.prepend( $sidebar );

				// Initialize folder tree.
				this.folderTree = new MediaNestFolderTree( {
					container: $sidebar[ 0 ],
					folders: wpMediaNest.folders.tree,
					selectedFolder: this.currentFolder,
					onFolderSelect: function( folderId ) {
						self.filterByFolder( folderId );
					},
					onFolderCreate: function() {
						self.refreshFolderCounts();
					},
					onFolderDelete: function() {
						self.refreshFolderCounts();
						self.filterByFolder( -1 );
					},
					onMediaDrop: function( attachmentIds, folderId ) {
						self.assignMediaToFolder( attachmentIds, folderId );
					},
				} );
			}
		},

		/**
		 * Extend AttachmentsBrowser view.
		 */
		extendAttachmentsBrowser: function() {
			const self = this;
			const originalBrowser = wp.media.view.AttachmentsBrowser;

			wp.media.view.AttachmentsBrowser = originalBrowser.extend( {
				createSidebar: function() {
					originalBrowser.prototype.createSidebar.apply( this, arguments );
					this.createFolderSidebar();
				},

				createFolderSidebar: function() {
					const $folderSidebar = $( '<div class="media-nest-modal-sidebar"></div>' );
					this.$el.prepend( $folderSidebar );

					self.folderTree = new MediaNestFolderTree( {
						container: $folderSidebar[ 0 ],
						folders: wpMediaNest.folders.tree,
						selectedFolder: self.currentFolder,
						onFolderSelect: function( folderId ) {
							self.currentFolder = folderId;
							self.filterModalByFolder( folderId );
						},
						onFolderCreate: function() {
							self.refreshFolderCounts();
						},
						onFolderDelete: function() {
							self.refreshFolderCounts();
						},
						onMediaDrop: function( attachmentIds, folderId ) {
							self.assignMediaToFolder( attachmentIds, folderId );
						},
					} );
				},
			} );
		},

		/**
		 * Extend attachments collection for folder filtering.
		 */
		extendAttachmentsCollection: function() {
			const originalQuery = wp.media.model.Query;

			wp.media.model.Query = originalQuery.extend( {
				sync: function( method, model, options ) {
					options = options || {};
					options.data = options.data || {};

					// Add folder filter to query.
					if ( mediaNest.currentFolder > 0 ) {
						options.data.media_folder = mediaNest.currentFolder;
					} else if ( mediaNest.currentFolder === -1 ) {
						options.data.media_folder = -1; // All files.
					}

					return originalQuery.prototype.sync.call( this, method, model, options );
				},
			} );
		},

		/**
		 * Extend toolbar with folder dropdown.
		 */
		extendToolbar: function() {
			const self = this;

			// Add folder dropdown to toolbar filters.
			$( document ).on( 'click', '.media-toolbar .view-switch', function() {
				setTimeout( function() {
					self.addFolderDropdown();
				}, 100 );
			} );

			// Initialize on page load.
			$( document ).ready( function() {
				self.addFolderDropdown();
			} );
		},

		/**
		 * Add folder dropdown to media toolbar.
		 */
		addFolderDropdown: function() {
			if ( $( '.media-nest-folder-filter' ).length ) {
				return;
			}

			const $toolbar = $( '.media-toolbar-secondary' );
			if ( ! $toolbar.length ) {
				return;
			}

			const $dropdown = $( '<select class="attachment-filters media-nest-folder-filter"></select>' );
			$dropdown.append( '<option value="-1">' + wpMediaNest.strings.allFiles + '</option>' );

			this.buildFolderOptions( wpMediaNest.folders.flat, $dropdown );

			$toolbar.prepend( $dropdown );

			$dropdown.on( 'change', ( e ) => {
				const folderId = parseInt( $( e.target ).val(), 10 );
				this.filterByFolder( folderId );

				if ( this.folderTree ) {
					this.folderTree.selectFolder( folderId );
				}
			} );
		},

		/**
		 * Build folder options for dropdown.
		 *
		 * @param {Array}  folders  Flat folder list.
		 * @param {jQuery} $dropdown Dropdown element.
		 */
		buildFolderOptions: function( folders, $dropdown ) {
			folders.forEach( ( folder ) => {
				const indent = '&nbsp;&nbsp;'.repeat( folder.depth );
				$dropdown.append(
					'<option value="' + folder.id + '">' +
					indent + folder.name + ' (' + folder.count + ')' +
					'</option>'
				);
			} );
		},

		/**
		 * Filter media by folder on library page.
		 *
		 * @param {number} folderId Folder ID.
		 */
		filterByFolder: function( folderId ) {
			this.currentFolder = folderId;

			// Update URL.
			const url = new URL( window.location.href );
			if ( folderId > 0 ) {
				url.searchParams.set( 'media_folder', folderId );
			} else {
				url.searchParams.delete( 'media_folder' );
			}
			window.history.replaceState( {}, '', url );

			// Update dropdown.
			$( '.media-nest-folder-filter' ).val( folderId );

			// Refresh the grid.
			if ( wp.media.frame && wp.media.frame.content ) {
				const collection = wp.media.frame.content.get().collection;
				if ( collection ) {
					collection.props.set( { media_folder: folderId } );
					collection.reset();
				}
			}

			// For grid mode on upload.php.
			if ( wp.media.frames && wp.media.frames.browse ) {
				const library = wp.media.frames.browse.content.get().collection;
				library.props.set( { media_folder: folderId } );
			}
		},

		/**
		 * Filter media in modal by folder.
		 *
		 * @param {number} folderId Folder ID.
		 */
		filterModalByFolder: function( folderId ) {
			this.currentFolder = folderId;

			if ( wp.media.frame ) {
				const library = wp.media.frame.state().get( 'library' );
				if ( library ) {
					library.props.set( { media_folder: folderId } );
				}
			}
		},

		/**
		 * Setup upload handler to assign folder to new uploads.
		 */
		setupUploadHandler: function() {
			const self = this;

			// Extend uploader.
			if ( typeof wp.Uploader !== 'undefined' ) {
				$.extend( wp.Uploader.prototype, {
					init: function() {
						const originalInit = wp.Uploader.prototype.init;

						return function() {
							originalInit.apply( this, arguments );

							this.uploader.bind( 'FileUploaded', function( up, file, response ) {
								if ( response && response.response ) {
									try {
										const data = JSON.parse( response.response );
										if ( data.success && data.data && data.data.id && self.currentFolder > 0 ) {
											self.assignMediaToFolder( [ data.data.id ], self.currentFolder );
										}
									} catch ( e ) {
										// Ignore parsing errors.
									}
								}
							} );
						};
					}(),
				} );
			}
		},

		/**
		 * Setup drag and drop on attachments.
		 */
		setupDragDropOnAttachments: function() {
			const self = this;

			$( document ).on( 'mouseenter', '.attachments-browser .attachment', function() {
				const $attachment = $( this );

				if ( $attachment.data( 'draggable-initialized' ) ) {
					return;
				}

				$attachment.draggable( {
					helper: function() {
						const $selected = $( '.attachments-browser .attachment.selected' );
						const count = Math.max( $selected.length, 1 );
						const $helper = $( '<div class="media-nest-drag-helper"></div>' );
						$helper.text( count + ' ' + ( count === 1 ? 'item' : 'items' ) );
						return $helper;
					},
					revert: 'invalid',
					cursor: 'move',
					cursorAt: { left: 50, top: 20 },
					zIndex: 1000,
					appendTo: 'body',
					start: function() {
						$( 'body' ).addClass( 'media-nest-dragging' );
					},
					stop: function() {
						$( 'body' ).removeClass( 'media-nest-dragging' );
					},
				} );

				$attachment.data( 'draggable-initialized', true );
			} );
		},

		/**
		 * Assign media items to a folder.
		 *
		 * @param {Array}  attachmentIds Array of attachment IDs.
		 * @param {number} folderId      Folder ID.
		 */
		assignMediaToFolder: function( attachmentIds, folderId ) {
			const self = this;

			$.ajax( {
				url: wpMediaNest.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wp_media_nest_assign_media',
					nonce: wpMediaNest.nonce,
					attachment_ids: JSON.stringify( attachmentIds ),
					folder_id: folderId,
				},
				success: function( response ) {
					if ( response.success ) {
						// Update folder tree with new counts.
						if ( response.data.tree && self.folderTree ) {
							self.folderTree.updateFolders( response.data.tree );
						}

						// Refresh current view if not viewing target folder.
						if ( self.currentFolder !== folderId && self.currentFolder !== -1 ) {
							self.refreshCurrentView();
						}
					}
				},
			} );
		},

		/**
		 * Refresh current view.
		 */
		refreshCurrentView: function() {
			if ( wp.media.frame && wp.media.frame.content ) {
				const collection = wp.media.frame.content.get().collection;
				if ( collection ) {
					collection.reset();
				}
			}
		},

		/**
		 * Refresh folder counts.
		 */
		refreshFolderCounts: function() {
			const self = this;

			$.ajax( {
				url: wpMediaNest.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wp_media_nest_get_folders',
					nonce: wpMediaNest.nonce,
				},
				success: function( response ) {
					if ( response.success && self.folderTree ) {
						self.folderTree.updateFolders( response.data.tree );
						self.folderTree.updateTotalCount( response.data.total_count );
						wpMediaNest.folders.tree = response.data.tree;
						wpMediaNest.folders.flat = response.data.flat;
					}
				},
			} );
		},
	};

	// Initialize when ready.
	$( document ).ready( function() {
		mediaNest.init();
	} );

	// Export for external access.
	window.wpMediaNestController = mediaNest;

}( jQuery, wp ) );
