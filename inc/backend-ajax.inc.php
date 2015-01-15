<?php
if( !defined( 'ABSPATH' ) )
	die( 'Cheatin\' uh?' );

add_action( 'wp_ajax_bawmrp_ajax_find_posts', 'bawmrp_ajax_find_posts' );
function bawmrp_ajax_find_posts()
{
	global $wpdb;

	check_ajax_referer( 'find-posts' );

	$pt = explode( ',', trim( $_POST['post_type'], ',' ) );
	if ( empty( $_POST['ps'] ) ) {
		$posttype = get_post_type_object( $pt[0] );
		wp_die( $posttype->labels->not_found );
	}
	$post_types = get_post_types( array( 'public' => true, 'show_ui'=>true ) );
	$in_array = array_intersect( $pt, $post_types );
	if ( !empty($_POST['post_type'] ) && !empty( $in_array ) )
		$what = "'" . implode( "','", $in_array ) . "'";
	else
		$what = 'post';
	$s = stripslashes($_POST['ps']);
	preg_match_all('/".*?("|$)|((?<=[\\s",+])|^)[^\\s",+]+/', $s, $matches);
	$search_terms = array_map( 'trim', $matches[0] );

	$searchand = $search = '';
	foreach ( (array)$search_terms as $term ) {
		$term = esc_sql( like_escape( $term ) );
		$search .= "{$searchand}(($wpdb->posts.post_title LIKE '%{$term}%') OR ($wpdb->posts.post_content LIKE '%{$term}%'))";
		$searchand = ' AND ';
	}
	$term = esc_sql( like_escape( $s ) );
	if ( count($search_terms) > 1 && $search_terms[0] != $s )
		$search .= " OR ($wpdb->posts.post_title LIKE '%{$term}%') OR ($wpdb->posts.post_content LIKE '%{$term}%')";

	$posts = $wpdb->get_results( "SELECT ID, post_title, post_status, post_date, post_type FROM $wpdb->posts WHERE post_type IN ($what) AND post_status NOT IN ( 'revision', 'trash' ) AND ($search) ORDER BY post_date_gmt DESC LIMIT 50" );

	if ( !$posts ) {
		$posttype = get_post_type_object( $pt[0] );
		wp_die( $posttype->labels->not_found );
	}

	$html = '<table class="widefat" cellspacing="0"><thead><tr><th class="found-radio"><br /></th><th>'.__('Title').'</th><th>'.__('Type').'</th><th>'.__('Category').'</th><th>'.__('Date').'</th><th>'.__('Status').'</th></tr></thead><tbody>';
	foreach ( $posts as $post ) {

		switch ( $post->post_status ) {
			case 'publish' :
			case 'private' :
				$stat = __('Published');
				break;
			case 'future' :
				$stat = __('Scheduled');
				break;
			case 'pending' :
				$stat = __('Pending Review');
				break;
			case 'draft' :
				$stat = __('Draft');
				break;
		}

		if ( '0000-00-00 00:00:00' == $post->post_date ) {
			$time = '';
		} else {
			$time = mysql2date( __('Y/m/d'), $post->post_date );
		}
		$posttype = get_post_type_object( $post->post_type );
		$posttype = $posttype->labels->singular_name;

                $category = strip_tags(get_the_category_list(', ', '', $post->ID));

		$html .= '<tr class="found-posts"><td class="found-radio"><input type="checkbox" id="found-'.$post->ID.'" name="found_post_id[]" value="' . esc_attr($post->ID) . '"></td>';
		$html .= '<td><label for="found-'.$post->ID.'">'.esc_html( $post->post_title ).'</label></td><td>'.esc_html( $posttype ).'</td><td>'.esc_html( $category ).'</td><td>'.esc_html( $time ).'</td><td>'.esc_html( $stat ).'</td></tr>'."\n\n";
	}
	$html .= '</tbody></table>';
	wp_send_json_success( $html );
	$x = new WP_Ajax_Response();
	$x->add( array(
		'what' => 'post',
		'data' => $html
	));
	$x->send();
}