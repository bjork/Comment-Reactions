jQuery(function ($) {
	var reactions = null;

	function createCookie(name,value,days) {
		if (days) {
			var date = new Date();
			date.setTime(date.getTime()+(days*24*60*60*1000));
			var expires = "; expires="+date.toGMTString();
		}
		else var expires = "";
		document.cookie = name+"="+value+expires+"; path=/";
	}

	function readCookie(name) {
		var nameEQ = name + "=";
		var ca = document.cookie.split(';');
		for(var i=0;i < ca.length;i++) {
			var c = ca[i];
			while (c.charAt(0)==' ') c = c.substring(1,c.length);
			if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
		}
		return null;
	}

	// Read user reactions from cookie and decode it.
	function get_user_reactions() {
		if ( reactions ) {
			return reactions;
		}

		var user_reactions = {};
		var cookie_reactions = readCookie('reactions');
		if ( null == cookie_reactions ) {
			return {};
		}

		var all = cookie_reactions.split( ',' );

		for ( var i = 0; i < all.length; i++ ) {
			var reactions_for_comment = all[ i ];
			if ( reactions_for_comment.length ) {
				var reactions_data = reactions_for_comment.split( ':' );
				var reaction_comment_id = reactions_data[0];
				var the_reactions = reactions_data[1].split( '.' );

				if ( '' == the_reactions[0] ) {
					the_reactions = [];
				}
			}

			user_reactions[ reaction_comment_id ] = the_reactions;
		}

		reactions = user_reactions;

		return user_reactions;
	}

	/**
	 * Encodes reactions as a string to be stored in the cookie.
	 */
	function encode_reactions( reactions ) {
		var response = [];
		for ( var comment_id in reactions ) {
			if ( reactions.hasOwnProperty( comment_id ) ) {
				var reactions_for_comment = reactions[ comment_id ];

				if ( 'undefined' != typeof reactions_for_comment ) {
					response.push( comment_id + ':' + reactions_for_comment.join( '.' ) );
				}
			}
		}
		return response.join( ',' );
	}

	/**
	 * Add a user reaction to the cookie.
	 */
	function add_user_reaction( comment_id, reaction ) {

		var user_reactions = get_user_reactions();

		// If reactions for this comment already added to the cookie.
		if ( 'undefined' != typeof user_reactions[ comment_id ] ) {

			// If not already have the same reaction in the cookie, add it.
			if ( $.inArray( reaction, user_reactions[ comment_id ] ) < 0 ) {
				user_reactions[ comment_id ].push( reaction );
			}
		} else {

			// Add reaction as new.
			user_reactions[ comment_id ] = [ reaction ];
		}

		// Store it as a cookie.
		createCookie( 'reactions', encode_reactions( user_reactions ), Reactions.cookie_days );

		console.log(encode_reactions( user_reactions ));

		reactions = user_reactions;
	}

	/**
	 * Remove a user reaction from the cookie.
	 */
	function remove_user_reaction( comment_id, reaction ) {

		var user_reactions = get_user_reactions();

		if ( 'undefined' != typeof user_reactions[ comment_id ]
			&& $.inArray( reaction, user_reactions[ comment_id ] ) >= 0 ) {
			delete user_reactions[ comment_id ][ $.inArray( reaction, user_reactions[ comment_id ] ) ];
		}

		createCookie( 'reactions', encode_reactions( user_reactions ), Reactions.cookie_days );
	}

	/**
	 * Update UI with count.
	 */
	function update_with_count( that, amount ) {
		var current_count = parseInt( that.find('.reactions-count .reactions-num').html(), 10 );
		var new_count = current_count + amount;
		that.find('.reactions-count .reactions-num').html( new_count );
		if ( new_count < 1 ) {
			that.find('.reactions-count').hide();
		} else {
			that.find('.reactions-count').show();
		}
	}

	$('.reactions .show_all_reactions').click(function () {

		var that = $( this );

		var all = $( '#reactions_all' );
		var attach_handlers = false;

		// Get all reactions from a script element
		if ( all.length <= 0 ) {
			all = $( $( '#reactions_all_wrapper' ).html() );
			attach_handlers = true;
		}

		that.after( all );

		if ( attach_handlers ) {
			attach_click_handler();
		}

		that.toggleClass( 'reaction reacted' );

		$('#reactions_all').toggle();

	});

	function attach_click_handler() {
		$('#reactions_all .reaction').click(function () {
			var that = $( this );

			// todo
			// set data-comment_id

			var reactions = that.parents( '#reactions_all' ).parent();
			var existing = reactions.children( '.reaction-' + that.data( 'reaction' ) );

			// Add the reaction if not already exists
			if ( existing.length <= 0 ) {
				var clone = that.clone();

				var comment_id = reactions.data( 'comment_id' );
				// For some reason setting data attribute value with data() does not work.
				clone.attr( 'data-comment_id', comment_id );

				clone.click( click_handler );

				that.parents( '#reactions_all' ).prev().prev().after( clone );
			}

			that.parents( '#reactions_all' ).hide();

			reactions.children( '.reaction-' + that.data( 'reaction' ) ).click();

			that.parents( '#reactions_all' ).hide().prev().toggleClass( 'reaction reacted' );
		});
	}

	function click_handler () {

		var that = $( this );

		// In the process of communicating with WordPress, do nothing.
		if ( that.hasClass( 'reacting') ) {
			return;
		}

		that.addClass( 'reacting' );

		var comment_id = that.data('comment_id');
		var reaction   = that.data('reaction');

		// remove a reaction: -1, add one: 1
		var direction = that.hasClass( 'reacted' ) ? -1 : 1;

		update_with_count( that, direction );

		jQuery.post(
			Reactions.ajax_url, {
				action:     'reaction-submit',
				comment_id: comment_id,
				reaction:   reaction,
				method:     1 == direction ? 'react' : 'revert',
			}, function( response ) {
				that.removeClass( 'reacting' );

				if ( response.success ) {
					if ( 1 == direction ) {
						add_user_reaction( comment_id, reaction );
					} else {
						remove_user_reaction( comment_id, reaction );
					}
				} else {
					// revert too hasty UI update
					update_with_count( that, direction == 1 ? -1 : 1 );
				}
			}
		);

		that.toggleClass( 'reacted' );
	}

	$('.reactions .reaction').click( click_handler );

	// Prepare reactions according to the cookie.
	// For each reaction test if cookie is set and set class to reflect that.
	$( '.reactions .reaction' ).each(function () {

		var reactions = get_user_reactions();

		var comment_id = $( this ).data( 'comment_id' );
		var reaction   = $( this ).data( 'reaction'   );

		if ( 'undefined' != reactions[ comment_id ]
			&& $.inArray( reaction, reactions[ comment_id ] ) >= 0 ) {
			$( this ).addClass( 'reacted' );
		}
	});
});
