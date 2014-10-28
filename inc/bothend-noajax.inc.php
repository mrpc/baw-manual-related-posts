<?php
defined( 'ABSPATH' ) ||	die( 'Cheatin\' uh?' );

add_action( 'init', 'bawmrp_init', 1 );
function bawmrp_init() {
	load_plugin_textdomain( 'bawmrp', '', dirname( plugin_basename( BAWMRP__FILE__ ) ) . '/lang' );
}

function bawmrp_get_all_related_posts( $post ) {
	global $wpdb;
	$bawmrp_options = get_option( 'bawmrp' );
	if ( ! $related_posts = get_transient( 'bawmrp_' . $post->ID . '_' ) ) {
		$ids_manual = wp_parse_id_list( bawmrp_get_related_posts( $post->ID ) );
		$ids_auto = $bawmrp_options['auto_posts'] != 'none' ? bawmrp_get_related_posts_auto( $post ) : array();
		$ids = wp_parse_id_list( array_merge( $ids_manual, $ids_auto ) );
		$related_posts = get_posts( array( 'include' => $ids, 'post_type' => $bawmrp_options['post_types'] ) );
		set_transient( 'bawmrp_' . $post->ID . '_', $related_posts );
	}
	return $related_posts;
}

/**
 * Get the related posts list
 * @global wpdb $wpdb
 * @param int $post_id
 * @param bool $only_published
 * @param bool $twoway Should the posts be linked to each other?
 * @return string
 */
function bawmrp_get_related_posts( $post_id, $only_published=true, $twoway=true ) {
	global $wpdb;
	$bawmrp_options = get_option( 'bawmrp' );
	$ids = get_post_meta( $post_id, '_yyarpp', true );
	if ( $only_published ) {
		$ids = ! empty( $ids ) ? implode( ',', wp_parse_id_list( wp_list_pluck( get_posts( array( 'include' => $ids, 'post_type' => $bawmrp_options['post_types'] ) ), 'ID' ) ) ) : array();
	} else {
		$ids = ! empty( $ids ) ? implode( ',', wp_parse_id_list( $ids ) ) : array();
	}
        if ($twoway == true) {
            $ids_bonus = $wpdb->get_row( "SELECT group_concat(post_id) as ids FROM $wpdb->postmeta WHERE post_id != {$post_id} AND meta_key='_yyarpp' AND concat(',',meta_value,',') LIKE '%,{$post_id},%'" );
            if ( ! is_admin() && reset( $ids_bonus ) ) {
                    if ( ! is_array( $ids ) ) {
                            $ids .= ',' . reset( $ids_bonus );
                    }else{
                            $ids[] = reset( $ids_bonus );
                    }
            }
        }
	return $ids;
}

function bawmrp_get_related_posts_auto( $post ) {
	$bawmrp_options = get_option( 'bawmrp' );
	// Get all manuals ids
	$rel_id = bawmrp_get_related_posts( $post->ID, false );
	// How many auto posts we need
	$num_posts = (int) $bawmrp_options['max_posts'] == 0 ? -1 : (int) $bawmrp_options['max_posts'] - count( wp_parse_id_list( $rel_id ) );
	// If we still need auto posts or post number is illimited
	if ( $num_posts > 0 || $num_posts == -1 ) {
		$rel_id = array_filter( wp_parse_id_list( $rel_id ) );
		if ( isset( $bawmrp_options['auto_posts'] ) && 'none' != $bawmrp_options['auto_posts'] ) {
			$args = array(
				'post_type' => $post->post_type,
				'post_status' => 'publish',
				'post__not_in' => array_merge( array( $post->ID ), $rel_id ),
				'numberposts' => $num_posts,
				'post_count' => $num_posts,
				'order' => $bawmrp_options['random_order'] ? 'RAND' : 'DESC'
			);
			// auto posts by tags
			if ( 'none' != $bawmrp_options['auto_posts'] ) {
				// $tags = wp_get_post_tags( $post->ID, array( 'fields' => 'ids' ) );
				// if ( $tags ) {
				// 	$args['tag__in'] = $tags;
				// }
				$cat = get_the_category( $post->ID );
				$cat_ids = wp_list_pluck( $cat, 'term_id' );
				$args['category'] = implode( ',', $cat_ids );
			}
			// auto posts query
			$relative_query = get_posts( $args );
			$rel_id = array(); // set empty
			// we found posts ?
			if ( ! empty( $relative_query ) ) {
				$rel_id = wp_list_pluck( $relative_query, 'ID' );
			}
		}
		// do we still need posts ? ok go sticky if checked
		if ( $num_posts>count( $rel_id ) ) {
			$rel_id = array_merge( $rel_id, get_option( 'sticky_posts' ) );
		}
		$rel_id = array_flip( $rel_id );
		if ( isset( $rel_id[ $post->ID ] ) ) {
			unset( $rel_id[ $post->ID ] );
		}
		$rel_id = array_flip( $rel_id );
		// do we still need posts ? ok go recent if checked
		if ( $num_posts>count( $rel_id ) ) {
			$args = array(
				'post_type' => $post->post_type,
				'post_status' => 'publish',
				'post__not_in' => array_merge( array( $post->ID ), $rel_id ),
				'numberposts' => $num_posts - count( $rel_id ),
				'post_count' => $num_posts - count( $rel_id )
			);
			$recent_query = get_posts( $args );
			// we found posts ?
			if ( ! empty( $recent_query ) ) {
				$rel_id = array_merge( $rel_id, wp_list_pluck( $recent_query, 'ID' ) );
			}
		}
		// return not empty
		return wp_parse_id_list( $rel_id );
	}
	return array();
}

function bawmrp_wpml_lang_by_code( $code ) {
	static $langs = null;
	global $sitepress;
	if ( isset( $sitepress ) && null === $langs ) {
		$langs = $sitepress->get_languages( "en' AND active='1");
		$codes = wp_list_pluck( $langs, 'code' );
		$langs = wp_list_pluck( $langs, 'default_locale' );
		$langs = array_combine( $codes, $langs );
	}
	if ( isset( $langs[ $code ] ) ) {
		return $langs[ $code ];
	} elseif( isset( $sitepress, $langs[ $sitepress->get_default_language() ] )) {
		return $langs[ $sitepress->get_default_language() ];
	}
	return get_locale();
}