/**
 * WP Media Nest - Folder Tree Component
 *
 * Handles folder tree rendering, interactions, and drag/drop functionality.
 *
 * @package WP_Media_Nest
 */

/* global jQuery, wpMediaNest */

( function( $, window, document ) {
	'use strict';

	/**
	 * Folder Tree Class
	 *
	 * @param {Object} options Configuration options.
	 */
	const MediaNestFolderTree = function( options ) {
		this.options = $.extend( {
			container: null,
			folders: [],
			selectedFolder: -1,
			onFolderSelect: null,
			onFolderCreate: null,
			onFolderRename: null,
			onFolderDelete: null,
			onFolderMove: null,
			onMediaDrop: null,
		}, options );

		this.$container = $( this.options.container );
		this.folders = this.options.folders;
		this.selectedFolder = this.options.selectedFolder;
		this.expandedFolders = new Set();

		this.init();
	};

	MediaNestFolderTree.prototype = {

		/**
		 * Initialize the folder tree.
		 */
		init: function() {
			this.render();
			this.bindEvents();
			this.initDragDrop();
		},

		/**
		 * Render the folder tree.
		 */
		render: function() {
			const html = this.buildTreeHTML();
			this.$container.html( html );
			this.updateSelection();
		},

		/**
		 * Build HTML for the folder tree.
		 *
		 * @return {string} HTML string.
		 */
		buildTreeHTML: function() {
			let html = '<div class="media-nest-folder-tree">';

			// Add "All Files" virtual folder.
			html += this.buildFolderItemHTML( {
				id: -1,
				name: wpMediaNest.strings.allFiles,
				count: wpMediaNest.totalCount,
				is_system: true,
				children: [],
			}, 0, true );

			// Add actual folders.
			html += this.buildFolderListHTML( this.folders, 0 );

			html += '</div>';

			// Add action bar.
			html += '<div class="media-nest-actions">';
			html += '<button type="button" class="button media-nest-new-folder">';
			html += '<span class="dashicons dashicons-plus-alt2"></span> ';
			html += wpMediaNest.strings.newFolder;
			html += '</button>';
			html += '</div>';

			return html;
		},

		/**
		 * Build HTML for a list of folders.
		 *
		 * @param {Array} folders Folder array.
		 * @param {number} depth  Current depth level.
		 * @return {string} HTML string.
		 */
		buildFolderListHTML: function( folders, depth ) {
			let html = '';

			folders.forEach( ( folder ) => {
				html += this.buildFolderItemHTML( folder, depth, false );
			} );

			return html;
		},

		/**
		 * Build HTML for a single folder item.
		 *
		 * @param {Object}  folder    Folder data object.
		 * @param {number}  depth     Current depth level.
		 * @param {boolean} isAllFiles Whether this is the "All Files" folder.
		 * @return {string} HTML string.
		 */
		buildFolderItemHTML: function( folder, depth, isAllFiles ) {
			const hasChildren = folder.children && folder.children.length > 0;
			const isExpanded = this.expandedFolders.has( folder.id );
			const isSelected = this.selectedFolder === folder.id;

			let classes = 'media-nest-folder-item';
			if ( isSelected ) {
				classes += ' selected';
			}
			if ( folder.is_system ) {
				classes += ' is-system';
			}
			if ( isAllFiles ) {
				classes += ' is-all-files';
			}
			if ( hasChildren ) {
				classes += ' has-children';
			}
			if ( isExpanded ) {
				classes += ' is-expanded';
			}

			let html = '<div class="' + classes + '" data-folder-id="' + folder.id + '" data-depth="' + depth + '">';

			// Folder row.
			html += '<div class="media-nest-folder-row" style="padding-left: ' + ( depth * 20 + 10 ) + 'px;">';

			// Expand/collapse toggle.
			if ( hasChildren ) {
				html += '<span class="media-nest-folder-toggle dashicons dashicons-arrow-' + ( isExpanded ? 'down' : 'right' ) + '-alt2"></span>';
			} else {
				html += '<span class="media-nest-folder-toggle-placeholder"></span>';
			}

			// Folder icon.
			const iconClass = isAllFiles ? 'dashicons-portfolio' : ( folder.is_system ? 'dashicons-category' : 'dashicons-open-folder' );
			html += '<span class="media-nest-folder-icon dashicons ' + iconClass + '"></span>';

			// Folder name.
			html += '<span class="media-nest-folder-name">' + this.escapeHTML( folder.name ) + '</span>';

			// Count badge.
			html += '<span class="media-nest-folder-count">' + ( folder.count || 0 ) + '</span>';

			// Actions (only for non-system folders).
			if ( ! folder.is_system && ! isAllFiles ) {
				html += '<span class="media-nest-folder-actions">';
				html += '<button type="button" class="media-nest-action-rename" title="' + wpMediaNest.strings.rename + '">';
				html += '<span class="dashicons dashicons-edit"></span>';
				html += '</button>';
				html += '<button type="button" class="media-nest-action-delete" title="' + wpMediaNest.strings.delete + '">';
				html += '<span class="dashicons dashicons-trash"></span>';
				html += '</button>';
				html += '</span>';
			}

			html += '</div>'; // .media-nest-folder-row

			// Children container.
			if ( hasChildren ) {
				html += '<div class="media-nest-folder-children" style="display: ' + ( isExpanded ? 'block' : 'none' ) + ';">';
				html += this.buildFolderListHTML( folder.children, depth + 1 );
				html += '</div>';
			}

			html += '</div>'; // .media-nest-folder-item

			return html;
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			const self = this;

			// Folder selection.
			this.$container.on( 'click', '.media-nest-folder-row', function( e ) {
				if ( $( e.target ).closest( '.media-nest-folder-actions' ).length ) {
					return;
				}
				if ( $( e.target ).hasClass( 'media-nest-folder-toggle' ) ) {
					return;
				}

				const folderId = parseInt( $( this ).closest( '.media-nest-folder-item' ).data( 'folder-id' ), 10 );
				self.selectFolder( folderId );
			} );

			// Toggle expand/collapse.
			this.$container.on( 'click', '.media-nest-folder-toggle', function() {
				const $item = $( this ).closest( '.media-nest-folder-item' );
				self.toggleFolder( $item );
			} );

			// New folder button.
			this.$container.on( 'click', '.media-nest-new-folder', function() {
				self.showNewFolderDialog();
			} );

			// Rename action.
			this.$container.on( 'click', '.media-nest-action-rename', function( e ) {
				e.stopPropagation();
				const folderId = parseInt( $( this ).closest( '.media-nest-folder-item' ).data( 'folder-id' ), 10 );
				self.showRenameDialog( folderId );
			} );

			// Delete action.
			this.$container.on( 'click', '.media-nest-action-delete', function( e ) {
				e.stopPropagation();
				const folderId = parseInt( $( this ).closest( '.media-nest-folder-item' ).data( 'folder-id' ), 10 );
				self.showDeleteConfirm( folderId );
			} );

			// Double-click to rename.
			this.$container.on( 'dblclick', '.media-nest-folder-name', function( e ) {
				const $item = $( this ).closest( '.media-nest-folder-item' );
				if ( $item.hasClass( 'is-system' ) || $item.hasClass( 'is-all-files' ) ) {
					return;
				}
				const folderId = parseInt( $item.data( 'folder-id' ), 10 );
				self.showRenameDialog( folderId );
			} );

			// Context menu (right-click).
			this.$container.on( 'contextmenu', '.media-nest-folder-item', function( e ) {
				e.preventDefault();
				const $item = $( this );
				if ( $item.hasClass( 'is-system' ) || $item.hasClass( 'is-all-files' ) ) {
					return;
				}
				self.showContextMenu( e, $item );
			} );
		},

		/**
		 * Initialize drag and drop functionality.
		 */
		initDragDrop: function() {
			const self = this;

			// Make folders droppable for media items.
			this.$container.find( '.media-nest-folder-item:not(.is-all-files)' ).droppable( {
				accept: '.attachment, .media-nest-folder-item:not(.is-system):not(.is-all-files)',
				hoverClass: 'media-nest-drop-hover',
				tolerance: 'pointer',
				drop: function( event, ui ) {
					const targetFolderId = parseInt( $( this ).data( 'folder-id' ), 10 );

					// Check if dropping media or folder.
					if ( ui.draggable.hasClass( 'attachment' ) ) {
						// Media drop.
						const attachmentIds = self.getSelectedAttachmentIds( ui.draggable );
						if ( self.options.onMediaDrop ) {
							self.options.onMediaDrop( attachmentIds, targetFolderId );
						}
					} else if ( ui.draggable.hasClass( 'media-nest-folder-item' ) ) {
						// Folder drop.
						const sourceFolderId = parseInt( ui.draggable.data( 'folder-id' ), 10 );
						if ( sourceFolderId !== targetFolderId ) {
							self.moveFolder( sourceFolderId, targetFolderId );
						}
					}
				},
			} );

			// Make non-system folders draggable.
			this.$container.find( '.media-nest-folder-item:not(.is-system):not(.is-all-files)' ).draggable( {
				revert: 'invalid',
				helper: 'clone',
				opacity: 0.7,
				cursor: 'move',
				zIndex: 1000,
				start: function() {
					$( this ).addClass( 'is-dragging' );
				},
				stop: function() {
					$( this ).removeClass( 'is-dragging' );
				},
			} );

			// Root drop zone for moving folders to root level.
			this.$container.find( '.media-nest-folder-tree' ).droppable( {
				accept: '.media-nest-folder-item:not(.is-system):not(.is-all-files)',
				tolerance: 'pointer',
				drop: function( event, ui ) {
					const sourceFolderId = parseInt( ui.draggable.data( 'folder-id' ), 10 );
					self.moveFolder( sourceFolderId, 0 );
				},
			} );
		},

		/**
		 * Get selected attachment IDs.
		 *
		 * @param {jQuery} $draggedItem The dragged item.
		 * @return {Array} Array of attachment IDs.
		 */
		getSelectedAttachmentIds: function( $draggedItem ) {
			const ids = [];
			const $selected = $( '.attachments-browser .attachment.selected' );

			if ( $selected.length > 0 ) {
				$selected.each( function() {
					ids.push( parseInt( $( this ).data( 'id' ), 10 ) );
				} );
			} else {
				ids.push( parseInt( $draggedItem.data( 'id' ), 10 ) );
			}

			return ids;
		},

		/**
		 * Select a folder.
		 *
		 * @param {number} folderId Folder ID.
		 */
		selectFolder: function( folderId ) {
			this.selectedFolder = folderId;
			this.updateSelection();

			if ( this.options.onFolderSelect ) {
				this.options.onFolderSelect( folderId );
			}
		},

		/**
		 * Update visual selection state.
		 */
		updateSelection: function() {
			this.$container.find( '.media-nest-folder-item' ).removeClass( 'selected' );
			this.$container.find( '.media-nest-folder-item[data-folder-id="' + this.selectedFolder + '"]' ).addClass( 'selected' );
		},

		/**
		 * Toggle folder expand/collapse state.
		 *
		 * @param {jQuery} $item The folder item element.
		 */
		toggleFolder: function( $item ) {
			const folderId = parseInt( $item.data( 'folder-id' ), 10 );
			const $children = $item.children( '.media-nest-folder-children' );
			const $toggle = $item.find( '> .media-nest-folder-row .media-nest-folder-toggle' );
			const isExpanded = $item.hasClass( 'is-expanded' );

			if ( isExpanded ) {
				$children.slideUp( 200 );
				$item.removeClass( 'is-expanded' );
				$toggle.removeClass( 'dashicons-arrow-down-alt2' ).addClass( 'dashicons-arrow-right-alt2' );
				this.expandedFolders.delete( folderId );
			} else {
				$children.slideDown( 200 );
				$item.addClass( 'is-expanded' );
				$toggle.removeClass( 'dashicons-arrow-right-alt2' ).addClass( 'dashicons-arrow-down-alt2' );
				this.expandedFolders.add( folderId );
			}
		},

		/**
		 * Show new folder dialog.
		 *
		 * @param {number} parentId Optional parent folder ID.
		 */
		showNewFolderDialog: function( parentId ) {
			const self = this;
			parentId = parentId || ( this.selectedFolder > 0 ? this.selectedFolder : 0 );

			const $dialog = this.createDialog( {
				title: wpMediaNest.strings.createFolder,
				content: '<input type="text" class="media-nest-folder-name-input" placeholder="' + wpMediaNest.strings.enterFolderName + '" />',
				buttons: [
					{
						text: wpMediaNest.strings.createFolder,
						class: 'button-primary',
						callback: function( $dlg ) {
							const name = $dlg.find( '.media-nest-folder-name-input' ).val().trim();
							if ( name ) {
								self.createFolder( name, parentId );
								$dlg.remove();
							}
						},
					},
				],
			} );

			$dialog.find( '.media-nest-folder-name-input' ).focus().on( 'keypress', function( e ) {
				if ( e.which === 13 ) {
					$dialog.find( '.button-primary' ).click();
				}
			} );
		},

		/**
		 * Show rename dialog.
		 *
		 * @param {number} folderId Folder ID.
		 */
		showRenameDialog: function( folderId ) {
			const self = this;
			const folder = this.findFolderById( folderId );

			if ( ! folder ) {
				return;
			}

			const $dialog = this.createDialog( {
				title: wpMediaNest.strings.rename,
				content: '<input type="text" class="media-nest-folder-name-input" value="' + this.escapeHTML( folder.name ) + '" />',
				buttons: [
					{
						text: wpMediaNest.strings.rename,
						class: 'button-primary',
						callback: function( $dlg ) {
							const name = $dlg.find( '.media-nest-folder-name-input' ).val().trim();
							if ( name && name !== folder.name ) {
								self.renameFolder( folderId, name );
							}
							$dlg.remove();
						},
					},
				],
			} );

			$dialog.find( '.media-nest-folder-name-input' ).select().on( 'keypress', function( e ) {
				if ( e.which === 13 ) {
					$dialog.find( '.button-primary' ).click();
				}
			} );
		},

		/**
		 * Show delete confirmation.
		 *
		 * @param {number} folderId Folder ID.
		 */
		showDeleteConfirm: function( folderId ) {
			const self = this;

			if ( window.confirm( wpMediaNest.strings.confirmDelete ) ) {
				self.deleteFolder( folderId );
			}
		},

		/**
		 * Show context menu.
		 *
		 * @param {Event}  e     The event object.
		 * @param {jQuery} $item The folder item.
		 */
		showContextMenu: function( e, $item ) {
			const self = this;
			const folderId = parseInt( $item.data( 'folder-id' ), 10 );

			// Remove existing context menus.
			$( '.media-nest-context-menu' ).remove();

			const $menu = $( '<div class="media-nest-context-menu"></div>' );
			$menu.append( '<div class="menu-item rename">' + wpMediaNest.strings.rename + '</div>' );
			$menu.append( '<div class="menu-item delete">' + wpMediaNest.strings.delete + '</div>' );
			$menu.append( '<div class="menu-item new-subfolder">' + wpMediaNest.strings.newFolder + '</div>' );

			$menu.css( {
				left: e.pageX,
				top: e.pageY,
			} );

			$( 'body' ).append( $menu );

			$menu.on( 'click', '.rename', function() {
				self.showRenameDialog( folderId );
				$menu.remove();
			} );

			$menu.on( 'click', '.delete', function() {
				self.showDeleteConfirm( folderId );
				$menu.remove();
			} );

			$menu.on( 'click', '.new-subfolder', function() {
				self.showNewFolderDialog( folderId );
				$menu.remove();
			} );

			// Close on click outside.
			$( document ).one( 'click', function() {
				$menu.remove();
			} );
		},

		/**
		 * Create a dialog.
		 *
		 * @param {Object} options Dialog options.
		 * @return {jQuery} Dialog element.
		 */
		createDialog: function( options ) {
			let html = '<div class="media-nest-dialog-overlay">';
			html += '<div class="media-nest-dialog">';
			html += '<div class="media-nest-dialog-header">';
			html += '<span class="title">' + options.title + '</span>';
			html += '<button type="button" class="media-nest-dialog-close">&times;</button>';
			html += '</div>';
			html += '<div class="media-nest-dialog-content">' + options.content + '</div>';
			html += '<div class="media-nest-dialog-footer">';

			options.buttons.forEach( function( btn ) {
				html += '<button type="button" class="button ' + ( btn.class || '' ) + '">' + btn.text + '</button>';
			} );

			html += '</div>';
			html += '</div>';
			html += '</div>';

			const $dialog = $( html );
			$( 'body' ).append( $dialog );

			// Bind button callbacks.
			options.buttons.forEach( function( btn, index ) {
				$dialog.find( '.media-nest-dialog-footer .button' ).eq( index ).on( 'click', function() {
					btn.callback( $dialog );
				} );
			} );

			// Close button.
			$dialog.find( '.media-nest-dialog-close' ).on( 'click', function() {
				$dialog.remove();
			} );

			// Close on overlay click.
			$dialog.on( 'click', function( e ) {
				if ( $( e.target ).hasClass( 'media-nest-dialog-overlay' ) ) {
					$dialog.remove();
				}
			} );

			// Close on Escape.
			$( document ).on( 'keydown.mediaDialog', function( e ) {
				if ( e.which === 27 ) {
					$dialog.remove();
					$( document ).off( 'keydown.mediaDialog' );
				}
			} );

			return $dialog;
		},

		/**
		 * Create a new folder via AJAX.
		 *
		 * @param {string} name     Folder name.
		 * @param {number} parentId Parent folder ID.
		 */
		createFolder: function( name, parentId ) {
			const self = this;

			$.ajax( {
				url: wpMediaNest.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wp_media_nest_create_folder',
					nonce: wpMediaNest.nonce,
					name: name,
					parent: parentId,
				},
				success: function( response ) {
					if ( response.success ) {
						self.addFolderToTree( response.data, parentId );
						self.render();
						self.initDragDrop();

						if ( self.options.onFolderCreate ) {
							self.options.onFolderCreate( response.data );
						}
					} else {
						window.alert( response.data.message || wpMediaNest.strings.error );
					}
				},
				error: function() {
					window.alert( wpMediaNest.strings.error );
				},
			} );
		},

		/**
		 * Rename a folder via AJAX.
		 *
		 * @param {number} folderId Folder ID.
		 * @param {string} newName  New folder name.
		 */
		renameFolder: function( folderId, newName ) {
			const self = this;

			$.ajax( {
				url: wpMediaNest.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wp_media_nest_rename_folder',
					nonce: wpMediaNest.nonce,
					folder_id: folderId,
					name: newName,
				},
				success: function( response ) {
					if ( response.success ) {
						self.updateFolderInTree( response.data );
						self.render();
						self.initDragDrop();

						if ( self.options.onFolderRename ) {
							self.options.onFolderRename( response.data );
						}
					} else {
						window.alert( response.data.message || wpMediaNest.strings.error );
					}
				},
				error: function() {
					window.alert( wpMediaNest.strings.error );
				},
			} );
		},

		/**
		 * Delete a folder via AJAX.
		 *
		 * @param {number} folderId Folder ID.
		 */
		deleteFolder: function( folderId ) {
			const self = this;

			$.ajax( {
				url: wpMediaNest.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wp_media_nest_delete_folder',
					nonce: wpMediaNest.nonce,
					folder_id: folderId,
				},
				success: function( response ) {
					if ( response.success ) {
						self.removeFolderFromTree( folderId );

						if ( self.selectedFolder === folderId ) {
							self.selectedFolder = -1;
						}

						self.render();
						self.initDragDrop();

						if ( self.options.onFolderDelete ) {
							self.options.onFolderDelete( folderId );
						}
					} else {
						window.alert( response.data.message || wpMediaNest.strings.error );
					}
				},
				error: function() {
					window.alert( wpMediaNest.strings.error );
				},
			} );
		},

		/**
		 * Move a folder via AJAX.
		 *
		 * @param {number} folderId  Folder ID.
		 * @param {number} newParent New parent ID.
		 */
		moveFolder: function( folderId, newParent ) {
			const self = this;

			$.ajax( {
				url: wpMediaNest.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wp_media_nest_move_folder',
					nonce: wpMediaNest.nonce,
					folder_id: folderId,
					new_parent: newParent,
				},
				success: function( response ) {
					if ( response.success ) {
						self.moveFolderInTree( folderId, newParent );
						self.render();
						self.initDragDrop();

						if ( self.options.onFolderMove ) {
							self.options.onFolderMove( response.data );
						}
					} else {
						window.alert( response.data.message || wpMediaNest.strings.error );
					}
				},
				error: function() {
					window.alert( wpMediaNest.strings.error );
				},
			} );
		},

		/**
		 * Add a folder to the tree structure.
		 *
		 * @param {Object} folder   Folder data.
		 * @param {number} parentId Parent folder ID.
		 */
		addFolderToTree: function( folder, parentId ) {
			if ( parentId === 0 ) {
				this.folders.push( folder );
			} else {
				const parent = this.findFolderById( parentId, this.folders );
				if ( parent ) {
					if ( ! parent.children ) {
						parent.children = [];
					}
					parent.children.push( folder );
				}
			}
		},

		/**
		 * Update a folder in the tree structure.
		 *
		 * @param {Object} updatedFolder Updated folder data.
		 */
		updateFolderInTree: function( updatedFolder ) {
			const folder = this.findFolderById( updatedFolder.id, this.folders );
			if ( folder ) {
				folder.name = updatedFolder.name;
				folder.slug = updatedFolder.slug;
			}
		},

		/**
		 * Remove a folder from the tree structure.
		 *
		 * @param {number} folderId Folder ID.
		 */
		removeFolderFromTree: function( folderId ) {
			this.folders = this.removeFolderRecursive( folderId, this.folders );
		},

		/**
		 * Recursively remove a folder.
		 *
		 * @param {number} folderId Folder ID.
		 * @param {Array}  folders  Folder array.
		 * @return {Array} Updated folder array.
		 */
		removeFolderRecursive: function( folderId, folders ) {
			return folders.filter( ( folder ) => {
				if ( folder.id === folderId ) {
					return false;
				}
				if ( folder.children ) {
					folder.children = this.removeFolderRecursive( folderId, folder.children );
				}
				return true;
			} );
		},

		/**
		 * Move a folder in the tree structure.
		 *
		 * @param {number} folderId  Folder ID.
		 * @param {number} newParent New parent ID.
		 */
		moveFolderInTree: function( folderId, newParent ) {
			const folder = this.findFolderById( folderId, this.folders );
			if ( ! folder ) {
				return;
			}

			// Remove from current position.
			this.folders = this.removeFolderRecursive( folderId, this.folders );

			// Update parent.
			folder.parent = newParent;

			// Add to new position.
			if ( newParent === 0 ) {
				this.folders.push( folder );
			} else {
				const parent = this.findFolderById( newParent, this.folders );
				if ( parent ) {
					if ( ! parent.children ) {
						parent.children = [];
					}
					parent.children.push( folder );
				}
			}
		},

		/**
		 * Find a folder by ID.
		 *
		 * @param {number} folderId Folder ID.
		 * @param {Array}  folders  Folder array (optional).
		 * @return {Object|null} Folder object or null.
		 */
		findFolderById: function( folderId, folders ) {
			folders = folders || this.folders;

			for ( let i = 0; i < folders.length; i++ ) {
				if ( folders[ i ].id === folderId ) {
					return folders[ i ];
				}
				if ( folders[ i ].children ) {
					const found = this.findFolderById( folderId, folders[ i ].children );
					if ( found ) {
						return found;
					}
				}
			}

			return null;
		},

		/**
		 * Update folder data.
		 *
		 * @param {Array} folders Updated folders array.
		 */
		updateFolders: function( folders ) {
			this.folders = folders;
			this.render();
			this.initDragDrop();
		},

		/**
		 * Update folder counts.
		 *
		 * @param {number} folderId Folder ID.
		 * @param {number} count    New count.
		 */
		updateFolderCount: function( folderId, count ) {
			const folder = this.findFolderById( folderId );
			if ( folder ) {
				folder.count = count;
				this.$container.find( '.media-nest-folder-item[data-folder-id="' + folderId + '"] .media-nest-folder-count' ).text( count );
			}
		},

		/**
		 * Update total count.
		 *
		 * @param {number} count New total count.
		 */
		updateTotalCount: function( count ) {
			wpMediaNest.totalCount = count;
			this.$container.find( '.media-nest-folder-item[data-folder-id="-1"] .media-nest-folder-count' ).text( count );
		},

		/**
		 * Escape HTML entities.
		 *
		 * @param {string} str String to escape.
		 * @return {string} Escaped string.
		 */
		escapeHTML: function( str ) {
			const div = document.createElement( 'div' );
			div.textContent = str;
			return div.innerHTML;
		},
	};

	// Export to global scope.
	window.MediaNestFolderTree = MediaNestFolderTree;

}( jQuery, window, document ) );
