<?php
/*
Plugin Name: P2 Slack Notifications
Description: Basic slack notification for mentions on P2
Version: 0.2
Author: Silvan Hagen, required+
Author URI: http://wearerequired.com
License: GPL2
*/

/*  Copyright 2014 Silvan Hagen (email: silvan@required.ch)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

add_action( 'set_object_terms', 'p2_slack_send_mentions', 10, 4 );
/**
 * P2 handles it's mention magic by matching users with terms in a custom taxonomy. Whenever an update
 * is modified, whether it be a post or comment, P2 will search for any possible matching terms in the
 * content and then update the terms on the post accordingly.
 *
 * This allows us to hook into `set_object_terms` and perform our own actions when users are added to
 * the thread. Slack notifactions are sent once per user per thread. It would be nice one day to send an slack mention for
 * each mention in a thread, as a conversation could occur for a while.
 *
 * @param $post_id int current post ID
 * @param $users array of terms, in this case users
 * @param $tt_ids array of taxonomy/term ids, not used
 * @param $taxonomy_label string taxonomy label
 */
function p2_slack_send_mentions( $post_id, $users, $tt_ids, $taxonomy_label ) {

	if ( 'mentions' !== $taxonomy_label )
		return;

	if ( ! $notifications_sent = get_post_meta( $post_id, '_p2_notifications_sent', true ) )
		$notifications_sent = array();

	/*
	 * We only send mentions to users that have not already been mentioned on the post. Because
	 * things are handled at the post level, it seems difficult so far to determine if this was
	 * just fired due to a comment or initial update.
	 */
	$new_user_mentions = array_diff( $users, $notifications_sent );

	if ( empty( $new_user_mentions ) )
		return;

	$current_post = get_post( $post_id );

	if ( ! $current_post || 'publish' !== $current_post->post_status )
		return;
	
	$user_names = array();

	foreach ( $new_user_mentions as $user ) {
		$user_full = get_user_by( 'login', $user );
		$user_names[] = '<@' . $user_full->user_login . '>';
	}
	
	$mentions = implode( ', ', $user_names );
	
	$bot_url = get_option( 'p2_slack_webhook_url' );
	$bot_args = array(
		'icon_emoji' => ':ok_woman:',
		'channel' => '#maintenance-support',
		'username' => get_bloginfo( 'name' ),
		'text' => sprintf( '%s mentioned in <%s|%s>', $mentions, get_permalink( $post_id ), get_the_title( $post_id ) )
	);

	$payload = array( 'payload' => json_encode( $bot_args ) );
	
	$posting = wp_remote_post( $bot_url, array( 'body' => $payload ) );

	update_post_meta( $post_id, '_p2_notifications_payload', $payload );
	update_post_meta( $post_id, '_p2_notifications_response', $posting );
	update_post_meta( $post_id, '_p2_notifications_sent', $users );
}
