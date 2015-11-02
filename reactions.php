<?php
/**
 * Plugin Name: Reactions
 * Plugin URI: https://wordpress.org/plugins/reactions/
 * Description: Enable Slack style reactions to comments.
 * Author: Aki Björklund
 * Author URI: https://akibjorklund.com/
 */

define( 'REACTIONS_VERSION', '0.1.0' );

add_action( 'wp_enqueue_scripts',             'reactions_load_script_and_style' );
add_action( 'wp_ajax_nopriv_reaction-submit', 'reactions_submit_reaction' );
add_action( 'wp_ajax_reaction-submit',        'reactions_submit_reaction' );
add_action( 'comment_text',                   'reactions_show_after_comment_text', 10, 2 );
add_action( 'init',                           'reactions_load_textdomain' );

/**
 * Load plugin textdomain
 */
function reactions_load_textdomain() {
	load_plugin_textdomain( 'reactions', false, basename( dirname( __FILE__ ) ) . '/languages' );
}

/**
 * Get the available reactions. Currently a hard-coded list.
 *
 * @return array $reactions The reactions as an array. An alias as a key. Array of 'symbol'
 *                          (the actual emoji) and 'description' (human readable text
 *                          description of the emoji) as a value.
 */
function reactions_get_available_reactions() {
	$available_reactions = array(
		'thumbsup' => array( 'symbol' => '👍', 'description' => __( 'Thumbs up', 'reactions' ) ),
	);

	/**
	 * Available reactions.
	 *
	 * @since 0.1.0
	 *
	 * @param array $reactions The reactions as an array. An alias as a key. Array of 'symbol'
	 *                         (the actual emoji) and 'description' (human readable text
	 *                         description of the emoji) as a value.
	 */
	return apply_filters( 'reactions_available', $available_reactions );
}

/**
 * Comment content filter to show reactions for the comment.
 *
 * @param string     $comment_content The comment text.
 * @param WP_Comment $comment         The comment.
 */
function reactions_show_after_comment_text( $comment_content, $comment = null ) {
	if ( $comment && ! is_admin() ) {
		return $comment_content . reactions_show( $comment->comment_ID );
	}
	return $comment_content;
}

/**
 * Print out reactions for a comment.
 *
 * @param int $comment_id The ID of the comment.
 */
function reactions_show( $comment_id ) {

	$html = '';
	$html .= '<div class="reactions">';

	foreach ( reactions_get_available_reactions() as $reaction_alias => $reaction_info ) {

		$count_reactions = get_comment_meta( $comment_id, 'reactions_' . $reaction_alias, true );
		if ( empty( $count_reactions ) ) {
			$count_reactions = 0;
		}

		/**
		 * Reaction symbol.
		 *
		 * @since 0.1.0
		 *
		 * @param string $symbol      The emoji symbol to be shown.
		 * @param string $description The description of the symbol.
		 * @param string $alias       The alias of the symbol the description is for.
		 */
		$symbol = apply_filters( 'reactions_symbol', $reaction_info[ 'symbol' ], $reaction_info[ 'description' ], $reaction_alias );

		/**
		 * Reaction description.
		 *
		 * @since 0.1.0
		 *
		 * @param string $description The description to be shown.
		 * @param string $symbol      The emoji symbol the description is for.
		 * @param string $alias       The alias of the symbol the description is for.
		 */
		$description = apply_filters( 'reactions_description', $reaction_info[ 'description' ], $reaction_info[ 'symbol' ], $reaction_alias );

		$html .= '<button data-comment_id="' . esc_attr( $comment_id ) . '" data-reaction="';

		$html .= esc_attr( $reaction_alias );

		$html .= '" class="reaction"><span class="reactions-symbol">';

		$html .= esc_html( $symbol );

		$html .= '</span> <span class="reactions-description">';

		$html .= esc_html( $description );

		$html .= '</span> <span class="reactions-count"';

		if ( $count_reactions <= 0 ) {
			$html .= ' style="display:none"';
		}

		$html .= '> <span class="reactions-num">';

		$html .= $count_reactions;

		$html .= '</span></span></button>';
	}

	$html .= '</div>';

	return $html;
}

/**
 * Enqueue scripts and styles for the plugin.
 */
function reactions_load_script_and_style() {
	if ( ! is_singular() ) {
		return;
	}

	wp_enqueue_script( 'reactions', plugin_dir_url( __FILE__ ) . 'reactions.js', array( 'jquery' ), REACTIONS_VERSION, true );

	/**
	 * Cookie expires in number of days.
	 *
	 * @since 0.1.0
	 *
	 * @param int $cookie_days The number of days the cookie is set to last.
	 */
	$cookie_days = apply_filters( 'reactions_cookie_days', 30 );
	wp_localize_script( 'reactions', 'Reactions', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'cookie_days' => $cookie_days ) );

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
 * Ajax callback function to submit a reaction.
 */
function reactions_submit_reaction() {

	header( "Content-Type: application/json" );

	$comment_id = ( int )reactions_from_post( 'comment_id' );
	$reaction   = reactions_from_post( 'reaction' );
	$method     = reactions_from_post( 'method'   );

	// Bail early if comment does not exist or reaction not available.
	$comment = get_comment( $comment_id );
	if ( null == $comment || ! array_key_exists( $reaction, reactions_get_available_reactions() ) ) {
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

	echo json_encode( array( 'success' => true ) );
	exit;
}

/**
 * A helper function to get a value from $_POST
 *
 * @param string $key The key.
 */
function reactions_from_post( $key ) {
	if ( isset( $_POST[ $key ] ) ) {
		return $_POST[ $key ];
	}
	return null;
}