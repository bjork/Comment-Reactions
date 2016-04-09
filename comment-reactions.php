<?php
/*
Plugin Name: Comment Reactions
Plugin URI: https://wordpress.org/plugins/comment-reactions/
Description: Enable Slack style reactions to comments.
Author: Aki Björklund
Author URI: https://akibjorklund.com/
Version: 1.1.0
Text Domain: comment-reactions
Domain Path: /languages
*/
define( 'COMMENT_REACTIONS_VERSION', '1.0.0' );
define( 'COMMENT_REACTIONS_REST_NAMESPACE', 'comment-reactions/v1' );

add_action( 'wp_enqueue_scripts',              'creactions_load_script_and_style' );
add_action( 'comment_text',                    'creactions_show_after_comment_text', 10, 2 );
add_action( 'init',                            'creactions_load_textdomain' );
add_action( 'init',                            'creactions_load_emoji' );
add_action( 'wp_footer',                       'creactions_selector' );
add_action( 'rest_api_init',                   'creactions_register_rest_routes' );

/**
 * Load plugin textdomain
 *
 * @since 1.0.0
 */
function creactions_load_textdomain() {
	load_plugin_textdomain( 'creactions', false, basename( dirname( __FILE__ ) ) . '/languages' );
}

/**
 * Only load the huge Emoji definitions PHP file if really needed.
 *
 * @since 1.0.0
 */
function creactions_load_emoji() {
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
function creactions_get_visible_reactions() {
	$visible_reactions = array( 'thumbsup' );

	/**
	 * Reactions visible, available with one click.
	 *
	 * @since 1.0.0
	 *
	 * @param array $reactions The reaction aliases.
	 */
	return apply_filters( 'creactions_visible', $visible_reactions );
}

/**
 * Get reactions submitted to a comment plus all the always visible ones.
 *
 * @since 1.0.0
 *
 * @param int   $comment_id               The comment ID.
 * @param array $always_visible_reactions Aliases of reactions always visible.
 */
function creactions_get_comment_reactions( $comment_id, $always_visible_reactions = array() ) {
	$comment_meta = get_comment_meta( $comment_id );
	$all = creactions_get_all_reactions();

	$comment_reactions = array();

	foreach ( $always_visible_reactions as $always_visible_reaction ) {
		if ( isset( $all[ $always_visible_reaction ] ) ) {
			$reaction_to_add = $all[ $always_visible_reaction ];
			$reaction_to_add['visible'] = 'always';
			$comment_reactions[ $always_visible_reaction ] = $reaction_to_add;
		}
	}

	foreach ( $comment_meta as $single_meta => $meta_value ) {
		if ( substr( $single_meta, 0, 11 ) == 'creactions_' ) {
			$reaction = substr( $single_meta, 11 );
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
function creactions_show_after_comment_text( $comment_content, $comment = null ) {

	// When comment is posted, the 'itext' filter is called without the second argument.
	if ( $comment && ! is_admin() ) {
		return $comment_content . creactions_show( $comment->comment_ID );
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
function creactions_show( $comment_id ) {

	$html = '';
	// The empty <i> below mysteriously solves the issue of not being able
	// to add reactions to a comment with no reactions yet. Somehow without
	// it there seems not to be any parents for the reaction button in the
	// selector element, so determining the comment id fails.
	$html .= '<p class="reactions" data-comment_id="' . esc_attr( $comment_id ) . '"><i></i>';

	$reactions_to_show = creactions_get_comment_reactions( $comment_id, creactions_get_visible_reactions() );

	foreach ( $reactions_to_show as $reaction_alias => $reaction_info ) {

		$count_reactions = get_comment_meta( $comment_id, 'creactions_' . $reaction_alias, true );
		if ( empty( $count_reactions ) ) {
			$count_reactions = 0;
		}

		$class = isset( $reaction_info['visible'] ) ? 'reaction-always-visible' : '';

		$html .= creactions_single( $reaction_alias, $reaction_info['symbol'], $reaction_info['description'], $count_reactions, $class );
	}

	/**
	 * Whether to show the add new button.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $show_add_new_button To show the add new button or not.
	 */
	$show_add_new_button = apply_filters( 'creactions_show_add_new_button', true );

	if ( $show_add_new_button ) {
		$html .= '<span class="all_reactions_wrapper"><button class="show_all_reactions" aria-controls="reactions_all" aria-label="' . esc_attr( __( 'Add new reaction', 'creactions' ) ) . '">😀+</button></span>';
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
function creactions_single( $alias, $symbol, $description, $count = 0, $class = '' ) {
	if ( $class ) {
		$class = ' ' . trim( $class );
	}

	$html = sprintf( '<button class="reaction reaction-%s%s" data-reaction="%s" aria-label="%s">', esc_attr( $alias ), esc_attr( $class ), esc_attr( $alias ), esc_attr( $description ) );
	
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
function creactions_selector() {

	// Selector is only available on pages that have comments.
	if ( ! is_single() || ! comments_open() ) {
		return;
	}

	/** This filter is documented in reactions.php */
	$show_add_new_button = apply_filters( 'creactions_show_add_new_button', true );

	if ( ! $show_add_new_button ) {
		return;
	}

	// Printed out as a script type="text/html" to avoid loading a huge number of
	// images for Emoji replacements on non-supporting browsers.
	?><script type="text/html" id="reactions_all_wrapper"><div id="reactions_all" style="display:none;z-index:99"><?php

	foreach ( creactions_get_all_reactions() as $reaction_alias => $reaction_info ) {
		if ( 'section' == substr( $reaction_alias, 0, 7 ) ) {
			?><h2><?php echo esc_html( $reaction_info ) ?></h2><?php
			continue;
		}

		echo creactions_single( $reaction_alias, $reaction_info['symbol'], $reaction_info['description'] );
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
function creactions_load_script_and_style() {
	if ( ! is_singular() ) {
		return;
	}

	$js_suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '.min.js' : '.js';
	wp_enqueue_script( 'creactions', plugin_dir_url( __FILE__ ) . 'comment-reactions' . $js_suffix, array( 'jquery', 'underscore' ), COMMENT_REACTIONS_VERSION, true );

	/** This filter is documented in reactions.php */
	$show_add_new_button = apply_filters( 'creactions_show_add_new_button', true );
	$all_reactions = null;

	if ( $show_add_new_button ) {
		$all_reactions = creactions_filter_for_brevity( creactions_get_all_reactions() );
	}

	/**
	 * Cookie expires in number of days.
	 *
	 * @since 0.1.0
	 *
	 * @param int $cookie_days The number of days the cookie is set to last.
	 */
	$cookie_days = apply_filters( 'creactions_cookie_days', 30 );
	wp_localize_script(
		'creactions',
		'Comment_Reactions',
		array(
			'rest_url' => get_rest_url() . COMMENT_REACTIONS_REST_NAMESPACE,
			'cookie_days' => $cookie_days,
			'all_reactions' => $all_reactions
		)
	);

	/**
	 * The CSS URL the plugin enqueues.
	 *
	 * @since 0.1.0
	 *
	 * @param string|null $src URL of the CSS file. Null if not to be loaded.
	 */
	$css = apply_filters( 'creactions_css', plugin_dir_url( __FILE__ ) . 'comment-reactions.css' );
	if ( $css ) {
		wp_enqueue_style( 'creactions', $css, null, COMMENT_REACTIONS_VERSION );
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
function creactions_filter_for_brevity( $all ) {
	$filtered = array();

	foreach ( $all as $alias => $value ) {
		$new_value = array( 'a' => $alias, 's' => $value['symbol'], 'd' => $value['description'] );
		$filtered[] = $new_value;
	}
	return $filtered;
}

/**
 * Register REST routes.
 *
 * @since 1.1.0
 */
function creactions_register_rest_routes() {
	register_rest_route( COMMENT_REACTIONS_REST_NAMESPACE, '/comment/(?P<id>\d+)', array(
		'methods'  => 'POST',
		'callback' => 'creactions_rest_request_handler',
		'args' => array(
			'id' => array(
				'validate_callback' => function( $param, $request, $key ) {
					return null != get_comment( absint( $param ) );
				}
			),
			'reaction' => array(
				'validate_callback' => function( $param, $request, $key ) {
					return array_key_exists( $param, creactions_get_all_reactions() );
				}
			),
			'action' => array(
				'validate_callback' => function( $param, $request, $key ) {
					return in_array( $param, array( 'react', 'revert' ) );
				}
			),
		),
	) );
}

/**
 * Handler for a REST Request.
 *
 * @since 1.1.0
 * 
 * @todo There is a race condition in counting of the reactions.
 *
 * @param WP_REST_Request $request The REST Request.
 */
function creactions_rest_request_handler( WP_REST_Request $request ) {
	$reaction   = sanitize_key( $request->get_param( 'reaction' ) );
	$action     = sanitize_key( $request->get_param( 'action' ) );
	$comment_id = absint( $request->get_param( 'id' ) );

	// Get reaction count before the action.
	$meta_key = 'creactions_' . $reaction;
	$count = get_comment_meta( $comment_id, $meta_key, true );
	if ( empty( $count ) ) {
		$count = 0;
	}

	// Figure out the new reaction count.
	if ( 'react' == $action ) {
		$count = ( int )$count + 1;
	} else {
		$count = ( int )$count - 1;
	}

	// Update comment meta accordingly.
	if ( $count > 0 ) {
		update_comment_meta( $comment_id, $meta_key, $count );
	} else {
		delete_comment_meta( $comment_id, $meta_key );
	}

	// Deal with caching.
	creactions_clear_caching( $comment_id );
	creactions_set_comment_cookie( wp_get_current_user() );

	/**
	 * After submitting a reaction or a revert.
	 *
	 * @since 0.1.0
	 *
	 * @param string $reaction   Reaction (Emoji) alias.
	 * @param string $action     The submitted action, 'react' or 'revert'.
	 * @param int    $comment_id Comment ID.
	 * @param int    $count      Count of these reactions on this comment after the execution.
	 */
	do_action( 'creactions_after_submit', $action, $comment_id, $count );

	return new WP_REST_Response( array( 'count' => $count ) );
}

/**
 * Clear cache for the post on known big caching plugins.
 * 
 * @since 1.1.0
 *
 * @param int $comment_id ID of the comment there was a reaction to.
 */
function creactions_clear_caching( $comment_id ) {
	$comment = get_comment( $comment_id );

	if ( function_exists( 'wp_cache_post_id_gc' ) ) {
		wp_cache_post_id_gc( '', $comment->comment_post_ID );
	} else if ( function_exists( 'w3tc_pgcache_flush_post' ) ) {
		w3tc_pgcache_flush_post( $comment->comment_post_ID );
	}
}

/**
 * Set comment cookie to deal with potential caching issues.
 *
 * @since 1.0.0
 *
 * @param object $user Comment author's object.
 */
function creactions_set_comment_cookie( $user ) {
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