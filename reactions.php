<?php
/**
 * Plugin Name: Reactions
 * Plugin URI: https://wordpress.org/plugins/reactions/
 * Description: Enable Slack style reactions to comments.
 * Author: Aki Björklund
 * Author URI: https://akibjorklund.com/
 * Version: 1.0
 * Text Domain: reactions
 * Domain Path: /languages
 */

define( 'REACTIONS_VERSION', '1.0.0' );

// Currently the plugin does noting in wp-admin, so let's not load anything more either.
if ( is_admin() ) {
	return;
}

add_action( 'wp_enqueue_scripts',              'reactions_load_script_and_style' );
add_action( 'wp_ajax_nopriv_reaction-submit',  'reactions_submit_reaction' );
add_action( 'wp_ajax_reaction-submit',         'reactions_submit_reaction' );
add_action( 'comment_text',                    'reactions_show_after_comment_text', 10, 2 );
add_action( 'init',                            'reactions_load_textdomain' );
add_action( 'init',                            'reactions_load_emoji' );
add_action( 'wp_footer',                       'reactions_selector' );

/**
 * Load plugin textdomain
 *
 * @since 1.0.0
 */
function reactions_load_textdomain() {
	load_plugin_textdomain( 'reactions', false, basename( dirname( __FILE__ ) ) . '/languages' );
}

/**
 * Only load the huge Emoji definitions PHP file if really needed.
 *
 * @since 1.0.0
 */
function reactions_load_emoji() {
	require_once( 'emoji-definitions.php' );
}

/**
 * Get the reactions available with one click.
 *
 * @since 1.0.0
 *
 * @return array $reactions The reactions as an array. An alias as a key. Array of 'symbol'
 *                          (the actual emoji) and 'description' (human readable text
 *                          description of the emoji) as a value.
 */
function reactions_get_visible_reactions() {
	$visible_reactions = array( 'thumbsup' );

	/**
	 * Reactions visible, available with one click.
	 *
	 * @since 1.0.0
	 *
	 * @param array $reactions The reaction aliases.
	 */
	return apply_filters( 'reactions_visible', $visible_reactions );
}

/**
 * Get reactions submitted to a comment plus all the always visible ones.
 *
 * @since 1.0.0
 *
 * @param int   $comment_id               The comment ID.
 * @param array $always_visible_reactions Aliases of reactions always visible.
 */
function get_comment_reactions( $comment_id, $always_visible_reactions = array() ) {
	$comment_meta = get_comment_meta( $comment_id );
	$all = reactions_get_all_reactions();

	$comment_reactions = array();

	foreach ( $always_visible_reactions as $always_visible_reaction ) {
		if ( isset( $all[ $always_visible_reaction ] ) ) {
			$reaction_to_add = $all[ $always_visible_reaction ];
			$reaction_to_add['visible'] = 'always';
			$comment_reactions[ $always_visible_reaction ] = $reaction_to_add;
		}
	}

	foreach ( $comment_meta as $single_meta => $meta_value ) {
		if ( substr( $single_meta, 0, 10 ) == 'reactions_' ) {
			$reaction = substr( $single_meta, 10 );
			if ( isset( $all[ $reaction ] ) && ! isset( $comment_reactions[ $reaction ] ) ) {
				$comment_reactions[ $reaction ] = $all[ $reaction ];
			}
		}
	}

	return $comment_reactions;
}

/**
 * Comment content filter to show reactions for the comment.
 *
 * @since 1.0.0
 *
 * @param string     $comment_content The comment text.
 * @param WP_Comment $comment         The comment.
 */
function reactions_show_after_comment_text( $comment_content, $comment = null ) {

	// When comment is posted, the 'itext' filter is called without the second argument.
	if ( $comment && ! is_admin() ) {
		return $comment_content . reactions_show( $comment->comment_ID );
	}
	return $comment_content;
}

/**
 * Print out reactions for a comment.
 *
 * @since 1.0.0
 *
 * @param int $comment_id The ID of the comment.
 */
function reactions_show( $comment_id ) {

	$html = '';
	// The empty <i> below mysteriously solves the issue of not being able
	// to add reactions to a comment with no reactions yet. Somehow without
	// it there seems not to be any parents for the reaction button in the
	// selector element, so determining the comment id fails.
	$html .= '<p class="reactions" data-comment_id="' . esc_attr( $comment_id ) . '"><i></i>';

	$reactions_to_show = get_comment_reactions( $comment_id, reactions_get_visible_reactions() );

	foreach ( $reactions_to_show as $reaction_alias => $reaction_info ) {

		$count_reactions = get_comment_meta( $comment_id, 'reactions_' . $reaction_alias, true );
		if ( empty( $count_reactions ) ) {
			$count_reactions = 0;
		}

		$class = isset( $reaction_info['visible'] ) ? 'reaction-always-visible' : '';

		$html .= reactions_single( $reaction_alias, $reaction_info['symbol'], $reaction_info['description'], $count_reactions, $class );
	}

	/**
	 * Whether to show the add new button.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $show_add_new_button To show the add new button or not.
	 */
	$show_add_new_button = apply_filters( 'reactions_show_add_new_button', true );

	if ( $show_add_new_button ) {
		$html .= '<span class="all_reactions_wrapper"><button class="show_all_reactions" aria-controls="reactions_all" aria-label="' . esc_attr( __( 'Add new reaction', 'reactions' ) ) . '">😀+</button></span>';
	}

	$html .= '</p>';

	return $html;
}

/**
 * Return the HTML of a single Emoji reaction.
 *
 * @since 1.0.0
 *
 * @param string $alias The Emoji "slug"
 * @param string $alias The Emoji character.
 * @param string $alias Text that describes the reaction.
 * @param int $count Count of submitted reactions to be shown.
 * @param string $class Extra CSS class.
 */
function reactions_single( $alias, $symbol, $description, $count = 0, $class = '' ) {
	if ( $class ) {
		$class = ' ' . trim( $class );
	}

	$html = sprintf( '<button class="reaction reaction-%s%s" data-reaction="%s" aria-label="%s">', esc_attr( $alias ), $class, $alias, esc_attr( $description ) );
	
	$html .= sprintf( '<span class="reactions-symbol">%s</span>', esc_html( $symbol ) );

	$html .= sprintf( '<span class="reactions-description">%s</span>', esc_html( $description ) );

	$html .= sprintf( '<span class="reactions-count"%s">', $count <= 0 ? ' style="display:none"' : '' );

	$html .= sprintf( '<span class="reactions-num">%d</span></span></button>', $count );

	return $html;
}

/**
 * Prints out the selector element.
 *
 * @since 1.0.0
 */
function reactions_selector() {

	// Selector is only available on pages that have comments.
	if ( ! is_single() || ! comments_open() ) {
		return;
	}

	/** This filter is documented in reactions.php */
	$show_add_new_button = apply_filters( 'reactions_show_add_new_button', true );

	if ( ! $show_add_new_button ) {
		return;
	}

	// Printed out as a script type="text/html" to avoid loading a huge number of
	// images for Emoji replacements on non-supporting browsers.
	?><script type="text/html" id="reactions_all_wrapper"><div id="reactions_all" style="display:none;z-index:99"><?php

	foreach ( reactions_get_all_reactions() as $reaction_alias => $reaction_info ) {
		if ( 'section' == substr( $reaction_alias, 0, 7 ) ) {
			?><h2><?php echo esc_html( $reaction_info ) ?></h2><?php
			continue;
		}

		echo reactions_single( $reaction_alias, $reaction_info['symbol'], $reaction_info['description'] );
	}

	?></div></script><script type="text/template" id="reaction_template">
		<button class="reaction reaction-<%= reaction.a %>" data-reaction="<%= reaction.a %>" aria-label="<%= reaction.d %>">
			<span class="reactions-symbol"><%= reaction.s %></span>
			<span class="reactions-description"><%= reaction.d %></span>
			<span class="reactions-count" style="display:none"><span class="reactions-num">0</span></span>
		</button>
	</script><?php
}

/**
 * Enqueue scripts and styles for the plugin.
 */
function reactions_load_script_and_style() {
	if ( ! is_singular() ) {
		return;
	}

	wp_enqueue_script( 'reactions', plugin_dir_url( __FILE__ ) . 'reactions.min.js', array( 'jquery', 'underscore' ), REACTIONS_VERSION, true );

	/** This filter is documented in reactions.php */
	$show_add_new_button = apply_filters( 'reactions_show_add_new_button', true );
	$all_reactions = null;

	if ( $show_add_new_button ) {
		$all_reactions = reactions_filter_for_brevity( reactions_get_all_reactions() );
	}

	/**
	 * Cookie expires in number of days.
	 *
	 * @since 0.1.0
	 *
	 * @param int $cookie_days The number of days the cookie is set to last.
	 */
	$cookie_days = apply_filters( 'reactions_cookie_days', 30 );
	wp_localize_script( 'reactions', 'Reactions', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'cookie_days' => $cookie_days, 'all_reactions' => $all_reactions ) );

	/**
	 * The CSS URL the plugin enqueues.
	 *
	 * @since 0.1.0
	 *
	 * @param string|null $src URL of the CSS file. Null if not to be loaded.
	 */
	$css = apply_filters( 'reactions_css', plugin_dir_url( __FILE__ ) . 'reactions.css' );
	if ( $css ) {
		wp_enqueue_style( 'reactions', $css, null, REACTIONS_VERSION );
	}
}

/**
 * Minimizes key names of reactions array to save bandwidth.
 *
 * @since 1.0.0
 *
 * @param array $all All the reactions.
 *
 * @return array $reactions A minimized copy of reactions array.
 */
function reactions_filter_for_brevity( $all ) {
	$filtered = array();

	foreach ( $all as $alias => $value ) {
		$new_value = array( 'a' => $alias, 's' => $value['symbol'], 'd' => $value['description'] );
		$filtered[] = $new_value;
	}
	return $filtered;
}

/**
 * Ajax callback function to submit a reaction.
 *
 * @since 1.0.0
 */
function reactions_submit_reaction() {

	header( "Content-Type: application/json" );

	$comment_id = ( int )reactions_from_post( 'comment_id' );
	$reaction   = reactions_from_post( 'reaction' );
	$method     = reactions_from_post( 'method'   );

	// Bail early if comment does not exist or reaction not available.
	$comment = get_comment( $comment_id );
	if ( null == $comment || ! array_key_exists( $reaction, reactions_get_all_reactions() ) ) {
		echo $reaction;die();
		echo json_encode( array( 'success' => false ) );
		exit;
	}

	// Get reaction count before the action.
	$meta_key = 'reactions_' . $reaction;
	$count = get_comment_meta( $comment_id, $meta_key, true );
	if ( empty( $count ) ) {
		$count = 0;
	}

	// Figure out the new reaction count.
	if ( 'react' == $method ) {
		$count = ( int )$count + 1;
	} else if ( 'revert' == $method ) {
		$count = ( int )$count - 1;
	}

	// Update comment meta accordingly.
	if ( $count > 0 ) {
		update_comment_meta( $comment_id, $meta_key, $count );
	} else {
		delete_comment_meta( $comment_id, $meta_key );
	}

	// Clear cache for the post on known big caching plugins
	if ( function_exists( 'wp_cache_post_id_gc' ) ) {
		wp_cache_post_id_gc( '', $comment->comment_post_ID );
	} else if ( function_exists( 'w3tc_pgcache_flush_post' ) ) {
		w3tc_pgcache_flush_post( $comment->comment_post_ID );
	}

	// Set comment cookie mainly to deal with potential caching issues.
	reactions_set_comment_cookie( wp_get_current_user() );

	/**
	 * After submitting a reaction or a revert.
	 *
	 * @since 0.1.0
	 *
	 * @param string $reaction Reaction (Emoji) alias.
	 * @param string $method   The submitted method, 'react' or 'revert'.
	 * @param int    $method   Comment ID.
	 * @param int    $count    Count of these reactions on this comment after the execution.
	 */
	do_action( 'reactions_after_submit', $method, $comment_id, $count );

	echo json_encode( array( 'success' => true, 'count' => $count ) );
	exit;
}

/**
 * Set comment cookie to deal with potential caching issues.
 *
 * @since 1.0.0
 *
 * @param object $user Comment author's object.
 */
function reactions_set_comment_cookie( $user ) {
	if ( $user->exists() ) {
		return;
	}

	/** This filter is documented in wp-includes/comment-functions.php */
	$comment_cookie_lifetime = apply_filters( 'comment_cookie_lifetime', 30000000 );
	$secure = ( 'https' === parse_url( home_url(), PHP_URL_SCHEME ) );

	if ( ! isset( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) ) {
		setcookie( 'comment_author_' . COOKIEHASH, '', time() + $comment_cookie_lifetime, COOKIEPATH, COOKIE_DOMAIN, $secure );
	}
}

/**
 * A helper function to get a value from $_POST
 *
 * @since 1.0.0
 *
 * @param string $key The key.
 */
function reactions_from_post( $key ) {
	if ( isset( $_POST[ $key ] ) ) {
		return $_POST[ $key ];
	}
	return null;
}
