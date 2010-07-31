<?php
/*
Plugin Name: Notifly post/comment emailer
Plugin URI: http://wordpress.org/extend/plugins/notifly/
Description: Sends a notification to a fixed list of people whenever a new post or comment is made.
Author: Otto42, Matt
Version: 1.1
Author URI: http://ottodestruct.com

Let's point the URLs to the .org listing page once it's in the directory -- matt

TODO: HTML emails
TODO: admin option and UI for emails
*/

global $pce_mailing_list;
$pce_mailing_list = array (
	'test@example.com',
	'test2@example.com',
);

add_action( 'comment_post', 'pce_comment_email', 10, 2 );
function pce_comment_email( $comment_id, $comment_status ) {
	global $pce_mailing_list;

	// only send emails for actual published comments
	if ( '1' != $comment_status )
		return;
	
	$comment = get_comment( $comment_id );
	$post    = get_post( $comment->comment_post_ID );
	$comment_author_domain = gethostbyaddr( $comment->comment_author_IP );
	$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

	$notify_message  = sprintf( __( 'New comment on post "%s"' ), $post->post_title ) . "\r\n";
	$notify_message .= sprintf( __('Author : %1$s (IP: %2$s , %3$s)'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
	$notify_message .= sprintf( __('E-mail : %s'), $comment->comment_author_email ) . "\r\n";
	$notify_message .= sprintf( __('URL    : %s'), $comment->comment_author_url ) . "\r\n";
	$notify_message .= sprintf( __('Whois  : http://ws.arin.net/cgi-bin/whois.pl?queryinput=%s'), $comment->comment_author_IP ) . "\r\n";
	$notify_message .= __('Comment: ') . "\r\n" . $comment->comment_content . "\r\n\r\n";
	$notify_message .= __('You can see all comments on this post here: ') . "\r\n";
	$notify_message .= get_permalink($comment->comment_post_ID) . '#comment-' . $comment_id . "\r\n\r\n";
	$subject = sprintf( __('[%1$s] Comment: "%2$s"'), $blogname, $post->post_title );

	$wp_email = 'wordpress@' . preg_replace( '#^www\.#', '', strtolower( $_SERVER['SERVER_NAME'] ) );

	if ( '' == $comment->comment_author ) {
		$from = "From: \"$blogname\" <$wp_email>";
		if ( '' != $comment->comment_author_email )
			$reply_to = "Reply-To: $comment->comment_author_email";
	} else {
		$from = "From: \"$comment->comment_author\" <$wp_email>";
		if ( '' != $comment->comment_author_email )
			$reply_to = "Reply-To: \"$comment->comment_author_email\" <$comment->comment_author_email>";
	}

	$message_headers = "$from\n"
		. "Content-Type: text/plain; charset=\"" . get_option( 'blog_charset' ) . "\"\n";

	if ( isset( $reply_to ) )
		$message_headers .= $reply_to . "\n";

	foreach ( $pce_mailing_list as $address )
		wp_mail( $address, $subject, $notify_message, $message_headers );
}


add_action( 'transition_post_status', 'pce_post_email', 10, 3 );
function pce_post_email( $new, $old, $post ) {
	global $pce_mailing_list;

	// don't send emails on updates
	if ( 'publish' != $new || 'publish' == $old )
		return;
	
	$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	$user = get_userdata( $post->post_author );
	
	$notify_message  = get_permalink( $post->ID ). "\r\n\r\n";
	$notify_message .= sprintf( __( 'Title: "%s"' ), $post->post_title ) . "\r\n";
	$notify_message .= sprintf( __('Author : %1$s'), $user->user_nicename ) . "\r\n\r\n";
	$notify_message .= __('Post Preview: ') . "\r\n" . strip_tags( $post->post_content ) . "\r\n\r\n";
//	$notify_message .= print_r( $post, true );
	
	$subject = sprintf( __('[%1$s] Post: "%2$s" by %3$s'), $blogname, $post->post_title, $user->user_nicename );

	$wp_email = 'wordpress@' . preg_replace( '#^www\.#', '', strtolower( $_SERVER['SERVER_NAME'] ) );

	$from = "From: \"$blogname\" <$wp_email>";
	if ( '' != $user->user_email )
		$reply_to = "Reply-To: $user->user_email";

	$message_headers = "$from\n"
		. "Content-Type: text/plain; charset=\"" . get_option( 'blog_charset' ) . "\"\n";

	if ( isset( $reply_to ) )
		$message_headers .= $reply_to . "\n";

	foreach ( $pce_mailing_list as $address )
		wp_mail( $address, $subject, $notify_message, $message_headers );
}
