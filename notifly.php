<?php
/*
Plugin Name: Notifly Post/Comment Emailer
Plugin URI: http://wordpress.org/extend/plugins/notifly/
Description: Sends a notification to all users of a site when a new post or comment is made.
Author: Otto42, Matt
Version: 1.1
Author URI: http://ottodestruct.com

TODO: HTML emails (similar to wp.com)
TODO: admin option and UI for email addresses
*/

/**
 * pce_get_recipients()
 *
 * Gets the recipients
 *
 * @todo Everything (ui, proper save/load)
 *
 * @return array
 */
function pce_get_recipients() {
	$users = maybe_unserialize( get_option( 'pce_recipients' ) );

	if ( !is_array( $users ) ) {
		$users = explode( ', ', $users );
	}
	return apply_filters( 'pce_get_recipients', $users );
}

/**
 * pce_comment_email( $comment_id, $comment_status )
 *
 * Send an email to all users when a comment is made
 *
 * @param int $comment_id
 * @param string $comment_status
 * @return Return empty if comment is not published
 */
function pce_comment_email( $comment_id, $comment_status ) {

	// Only send emails for actual published comments
	if ( '1' != $comment_status )
		return;

	// Get recipients
	$recipients = pce_get_recipients();

	// Get comment info
	$comment                 = get_comment( $comment_id );
	$post                    = get_post( $comment->comment_post_ID );
	$comment_author_domain   = gethostbyaddr( $comment->comment_author_IP );
	$blogname                = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	$permalink               = get_permalink( $comment->comment_post_ID ) . '#comment-' . $comment_id;

	// Email from address
	$wp_email = 'wordpress@' . preg_replace( '#^www\.#', '', strtolower( $_SERVER['SERVER_NAME'] ) );

	// Email Subject
	$email['subject'] = sprintf( __( '[%1$s] Comment: "%2$s"', 'notifly' ), $blogname, $post->post_title );

	// Email Body
	$message['post_title']        = sprintf( __( 'New comment on post "%s"', 'notifly' ), $post->post_title );
	$message['comment_author']    = sprintf( __( 'Author : %1$s (IP: %2$s , %3$s)', 'notifly' ), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain );
	$message['comment_email']     = sprintf( __( 'E-mail : %s', 'notifly' ), $comment->comment_author_email );
	$message['comment_url']       = sprintf( __( 'URL    : %s', 'notifly' ), $comment->comment_author_url );
	$message['comment_whois']     = sprintf( __( 'Whois  : http://ws.arin.net/cgi-bin/whois.pl?queryinput=%s', 'notifly' ), $comment->comment_author_IP );
	$message['comment_content']   = sprintf( __( 'Comment: %s', 'notifly' ), "\r\n" . $comment->comment_content . "\r\n" );
	$message['comment_permalink'] = sprintf( __( 'You can see all comments on this post here: %s', 'notifly' ), $permalink );

	foreach ( $message as $message_part )
		$email['body'] .= $message_part . "\r\n";

	// Email Headers
	$headers['from'] = sprintf( 'From: %1$s <%2$s>', !empty( $comment->comment_author ) ? $comment->comment_author : $blogname, $wp_email );
	$headers['mime'] = 'MIME-Version: 1.0';
	$headers['type'] = 'Content-Type: text/html; charset="' . get_option( 'blog_charset' ) . '"';
	if ( !empty( $comment->comment_author_email ) )
		$headers['reply-to'] = sprintf( 'Reply-To: %1$s <%2$s>', $comment->comment_author_email, $comment->comment_author_email );

	foreach ( $headers as $header_part )
		$email['headers'] .= $header_part . "\n";

	// Send email to each user
	foreach ( $recipients as $recipient )
		wp_mail( $recipient->user_email, $email['subject'], $email['body'], $email['headers'] );
}
add_action( 'comment_post', 'pce_comment_email', 10, 2 );

/**
 * pce_post_email( $new, $old, $post )
 *
 * Send an email to all users when a new post is created
 *
 * @param string $new
 * @param string $old
 * @param object $post
 * @return Return empty if post is being updated or not published
 */
function pce_post_email( $new, $old, $post ) {

	// Don't send emails on updates
	if ( 'publish' != $new || 'publish' == $old )
		return;

	// Get user related info
	$recipients = pce_get_recipients();
	$author     = get_userdata( $post->post_author );

	// Get comment info
	$blogname   = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	$permalink  = get_permalink( $post->ID );

	// Email from address
	$wp_email = 'wordpress@' . preg_replace( '#^www\.#', '', strtolower( $_SERVER['SERVER_NAME'] ) );

	// Email Subject
	$email['subject'] = sprintf( __( '[%1$s] Post: "%2$s" by %3$s' ), $blogname, $author->post_title, $author->user_nicename );

	// Email body
	$message['permalink']    = get_permalink( $post->ID );
	$message['post_title']   = sprintf( __( 'Title: "%s"', 'notifly'  ), $post->post_title );
	$message['post_author']  = sprintf( __( 'Author : %s', 'notifly'  ), $author->user_nicename );
	$message['post_preview'] = sprintf( __( 'Post Preview: %s', 'notifly' ), "\r\n" . strip_tags( $post->post_content ) );

	foreach ( $message as $message_part )
		$email['body'] .= $message_part . "\r\n\r\n";

	// Headers
	$headers['from'] = sprintf( 'From: %1$s <%2$s>', $blogname, $wp_email );
	$headers['mime'] = 'MIME-Version: 1.0';
	$headers['type'] = 'Content-Type: text/html; charset="' . get_option( 'blog_charset' ) . '"';
	if ( !empty( $user->user_email ) )
		$headers['reply-to'] = sprintf( 'Reply-To: %1$s <%2$s>', $user->user_email, $user->user_email );

	foreach ( $headers as $header_part )
		$email['headers'] .= $header_part . "\n";

	// Send email to each user
	foreach ( $recipients as $recipient )
		wp_mail( $recipient->user_email, $email['subject'], $email['message'], $email['headers'] );
}
add_action( 'transition_post_status', 'pce_post_email', 10, 3 );

?>
