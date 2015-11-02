<?php
/**
 * Plugin Name: Reactions
 * Plugin URI: https://wordpress.org/plugins/reactions/
 * Description: Enable Slack style reactions to comments.
 * Author: Aki Björklund
 * Author URI: https://akibjorklund.com/
 */

// TODO: Version 1:
// TODO: more emoji
// TODO: only show all button if there really are more

define( 'REACTIONS_VERSION', '0.1.0' );

add_action( 'wp_enqueue_scripts',              'reactions_load_script_and_style' );
add_action( 'wp_ajax_nopriv_reaction-submit',  'reactions_submit_reaction' );
add_action( 'wp_ajax_reaction-submit',         'reactions_submit_reaction' );
add_action( 'comment_text',                    'reactions_show_after_comment_text', 10, 2 );
add_action( 'init',                            'reactions_load_textdomain' );
add_action( 'wp_footer',                       'reactions_selector' );

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
		'thumbsups' => array( 'symbol' => '👍', 'description' => __( 'Thumbs up', 'reactions' ) ),
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

add_filter( 'reactions_available', function ($reactions) {
	$all = reactions_get_all_reactions();
	$aliases_to_show = array( 'grinning', 'grin', 'joy', 'smiley', 'smile', 'sweat_smile', 'satisfied' );
	$to_show = array();
	foreach ($aliases_to_show as $key) {
		$to_show[$key] = $all[$key];
	}

	return array_merge($reactions, $to_show );
});

function reactions_get_all_reactions() {
	$all_reactions = array(

		'section' => __( 'People', 'reactions' ),

		'grinning'              => array( 'symbol' => '😀', 'description' => __( 'Grinning',                'reactions' ) ),
		'grin'                  => array( 'symbol' => '😁', 'description' => __( 'Grin',                    'reactions' ) ),
		'joy'                   => array( 'symbol' => '😂', 'description' => __( 'Tears of joy',            'reactions' ) ),
		'smiley'                => array( 'symbol' => '😃', 'description' => __( 'Smiley',                  'reactions' ) ),
		'smile'                 => array( 'symbol' => '😄', 'description' => __( 'Smile',                   'reactions' ) ),
		'sweat_smile'           => array( 'symbol' => '😅', 'description' => __( 'Smiling with cold sweat', 'reactions' ) ),
		'satisfied'             => array( 'symbol' => '😆', 'description' => __( 'Satisfied',               'reactions' ) ),
		'wink'                  => array( 'symbol' => '😉', 'description' => __( 'Wink',                    'reactions' ) ),
		'blush'                 => array( 'symbol' => '😊', 'description' => __( 'Blush',                   'reactions' ) ),
		'yum'                   => array( 'symbol' => '😋', 'description' => __( 'Yum',                     'reactions' ) ),
		'sunglasses'            => array( 'symbol' => '😎', 'description' => __( 'Sunglasses',              'reactions' ) ),
		'heart_eyes'            => array( 'symbol' => '😍', 'description' => __( 'Heart-shaped eyes',       'reactions' ) ),
		'kissing'               => array( 'symbol' => '😘', 'description' => __( 'Kissing',                 'reactions' ) ),
		'kissing_heart'         => array( 'symbol' => '😗', 'description' => __( 'Kissing, heart',          'reactions' ) ),
		'kissing_smiling_eyes'  => array( 'symbol' => '😙', 'description' => __( 'Kissing, smiling eyes',   'reactions' ) ),
		'kissing_closed_eyes'   => array( 'symbol' => '😚', 'description' => __( 'Kissing, closed eyes',    'reactions' ) ),
		'relaxed'               => array( 'symbol' => '☺️', 'description' => __( 'Relaxed',                 'reactions' ) ),
		//missing
		//missing
		'innocent'              => array( 'symbol' => '😇', 'description' => __( 'Innocent',                'reactions' ) ),
		//missing
		'neutral_face'          => array( 'symbol' => '😐', 'description' => __( 'Neutral face',            'reactions' ) ),
		'expressionless'        => array( 'symbol' => '😑', 'description' => __( 'Expressionless',          'reactions' ) ),
		'no_mouth'              => array( 'symbol' => '😶', 'description' => __( 'No mouth',                'reactions' ) ),
		//missing face with rolling eyes
		'smirk'                 => array( 'symbol' => '😏', 'description' => __( 'Smirk',                   'reactions' ) ),
		'persevere'             => array( 'symbol' => '😣', 'description' => __( 'Persevere',               'reactions' ) ),
		'disappointed_relieved' => array( 'symbol' => '😥', 'description' => __( 'Disappointed, relieved',  'reactions' ) ),
		'open_mouth'            => array( 'symbol' => '😮', 'description' => __( 'Open mouth',              'reactions' ) ),
		//missing zipper-mouth face
		'hushed'                => array( 'symbol' => '😯', 'description' => __( 'Hushed',                  'reactions' ) ),
		'sleepy'                => array( 'symbol' => '😪', 'description' => __( 'Sleepy',                  'reactions' ) ),
		'tired_face'            => array( 'symbol' => '😫', 'description' => __( 'Tired',                   'reactions' ) ),
		'sleeping'              => array( 'symbol' => '😴', 'description' => __( 'Sleeping',                'reactions' ) ),
		'relieved'              => array( 'symbol' => '😌', 'description' => __( 'Relieved',                'reactions' ) ),
		//missing nerd face
		'stuck_out_tongue'               => array( 'symbol' => '😛', 'description' => __( 'Stuck out tongue',              'reactions' ) ),
		'stuck_out_tongue_winking_eye'   => array( 'symbol' => '😜', 'description' => __( 'Stuck out tongue, winking eye', 'reactions' ) ),
		'stuck_out_tongue_closed_eyes'   => array( 'symbol' => '😝', 'description' => __( 'Stuck out tongue, closed eyes', 'reactions' ) ),
		//missing white frowning face
		//missing slightly frowning face
		'unamused'              => array( 'symbol' => '😒', 'description' => __( 'Unamused',               'reactions' ) ),
		'sweat'                 => array( 'symbol' => '😓', 'description' => __( 'Sweat',                  'reactions' ) ),
		'pensive'               => array( 'symbol' => '😔', 'description' => __( 'Pensive',                'reactions' ) ),
		'confused'              => array( 'symbol' => '😕', 'description' => __( 'Confused',               'reactions' ) ),
		'confounded'            => array( 'symbol' => '😖', 'description' => __( 'Confounded',             'reactions' ) ),
		//missing upside-down face
		'mask'                  => array( 'symbol' => '😷', 'description' => __( 'Mask',                   'reactions' ) ),
		//missing face with thermometer
		//missing face with head-bandage
		//missing money-mouth face
		'astonished'            => array( 'symbol' => '😲', 'description' => __( 'Astonished',             'reactions' ) ),
		'disappointed'          => array( 'symbol' => '😞', 'description' => __( 'Disappointed',           'reactions' ) ),
		'worried'               => array( 'symbol' => '😟', 'description' => __( 'Worried',                'reactions' ) ),
		'triumph'               => array( 'symbol' => '😤', 'description' => __( 'Triumph',                'reactions' ) ),
		'cry'                   => array( 'symbol' => '😢', 'description' => __( 'Cry',                    'reactions' ) ),
		'sob'                   => array( 'symbol' => '😭', 'description' => __( 'Sob',                    'reactions' ) ),
		'frowning'              => array( 'symbol' => '😦', 'description' => __( 'Frowning',               'reactions' ) ),
		'anguished'             => array( 'symbol' => '😧', 'description' => __( 'Anguished',              'reactions' ) ),
		'fearful'               => array( 'symbol' => '😨', 'description' => __( 'Fearful',                'reactions' ) ),
		'weary'                 => array( 'symbol' => '😩', 'description' => __( 'Weary',                  'reactions' ) ),
		'grimacing'             => array( 'symbol' => '😬', 'description' => __( 'Grimacing',              'reactions' ) ),
		'cold_sweat'            => array( 'symbol' => '😰', 'description' => __( 'Cold sweat',             'reactions' ) ),
		'scream'                => array( 'symbol' => '😱', 'description' => __( 'Scream',                 'reactions' ) ),
		'flushed'               => array( 'symbol' => '😳', 'description' => __( 'Flushed',                'reactions' ) ),
		'dizzy_face'            => array( 'symbol' => '😵', 'description' => __( 'Dizzy',                  'reactions' ) ),
		'rage'                  => array( 'symbol' => '😡', 'description' => __( 'Rage',                   'reactions' ) ),
		'angry'                 => array( 'symbol' => '😠', 'description' => __( 'Angry',                  'reactions' ) ),
		'imp'                   => array( 'symbol' => '👿', 'description' => __( 'Imp',                    'reactions' ) ),
		'smiling_imp'           => array( 'symbol' => '😈', 'description' => __( 'Smiling imp',            'reactions' ) ),
		// ..
		'thumbsup'              => array( 'symbol' => '👍', 'description' => __( 'Thumbs up',              'reactions' ) ),
		'thumbsdown'            => array( 'symbol' => '👎', 'description' => __( 'Thumbs down',            'reactions' ) ),

		// 'section 2' => __( 'Nature', 'reactions' ),

		// 'section 3' => __( 'Food & Drink', 'reactions' ),

		// 'section 4' => __( 'Celebration', 'reactions' ),

		// 'section 5' => __( 'Activity', 'reactions' ),

		// 'section 6' => __( 'Travel & Places', 'reactions' ),

		// 'section 7' => __( 'Objects & Symbols', 'reactions' ),
	);

	/**
	 * All reactions in the system.
	 *
	 * @since 0.1.0
	 *
	 * @param array $reactions The reactions as an array. An alias as a key. Array of 'symbol'
	 *                         (the actual emoji) and 'description' (human readable text
	 *                         description of the emoji) as a value.
	 */
	return apply_filters( 'reactions_all', $all_reactions );
}

/**
 * Comment content filter to show reactions for the comment.
 *
 * @param string     $comment_content The comment text.
 * @param WP_Comment $comment         The comment.
 */
function reactions_show_after_comment_text( $comment_content, $comment = null ) {

	// When comment is posted, the 'comment_text' filter is called without the second argument.
	if ( $comment && ! is_admin() ) {
		reactions_show( $comment->comment_ID );
	}
}

/**
 * Print out reactions for a comment.
 *
 * @param int $comment_id The ID of the comment.
 */
function reactions_show( $comment_id ) {

	?><div class="reactions" data-comment_id="<?php echo esc_attr( $comment_id ) ?>"><?php

	foreach ( reactions_get_available_reactions() as $reaction_alias => $reaction_info ) {

		$count_reactions = get_comment_meta( $comment_id, 'reactions_' . $reaction_alias, true );
		if ( empty( $count_reactions ) ) {
			$count_reactions = 0;
		}

		reactions_single( $reaction_alias, $reaction_info['symbol'], $reaction_info['description'], $comment_id, $count_reactions );
	}

	?><button class="show_all_reactions" title="<?php echo esc_attr( __( 'Add new', 'reactions' ) ) ?>">+</button><?php

	?></div><?php
}

function reactions_single( $alias, $symbol, $description, $comment_id = 0, $count = 0 ) {
	/**
	 * Reaction symbol.
	 *
	 * @since 0.1.0
	 *
	 * @param string $symbol      The emoji symbol to be shown.
	 * @param string $description The description of the symbol.
	 * @param string $alias       The alias of the symbol the description is for.
	 */
	$symbol = apply_filters( 'reactions_symbol', $symbol, $description, $alias );

	/**
	 * Reaction description.
	 *
	 * @since 0.1.0
	 *
	 * @param string $description The description to be shown.
	 * @param string $symbol      The emoji symbol the description is for.
	 * @param string $alias       The alias of the symbol the description is for.
	 */
	$description = apply_filters( 'reactions_description', $description, $symbol, $alias );

	?><button title="<?php

	echo esc_attr( $description );

	?>" data-comment_id="<?php echo esc_attr( $comment_id ) ?>" data-reaction="<?php

	echo esc_attr( $alias );

	?>" class="reaction reaction-<?php

	echo esc_attr( $alias );

	?>"><span class="reactions-symbol"><?php

	echo esc_html( $symbol );

	?></span> <span class="reactions-description"><?php

	echo esc_html( $description );

	?></span> <span class="reactions-count"<?php

	if ( $count <= 0 ) {
		?> style="display:none"<?php
	} ?>> <span class="reactions-num"><?php

	echo $count;

	?></span></span></button><?php
}

function reactions_selector() {
	?><script id="reactions_all_wrapper" type="text/html"><div id="reactions_all" style="display:none"><?php

	foreach ( reactions_get_all_reactions() as $reaction_alias => $reaction_info ) {
		if ( 'section' == substr( $reaction_alias, 0, 7 ) ) {
			?><h2><?php echo esc_html( $reaction_info ) ?></h2><?php
			continue;
		}

		reactions_single( $reaction_alias, $reaction_info['symbol'], $reaction_info['description'] );
	}

	?></div></script><?php
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