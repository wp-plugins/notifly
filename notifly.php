<?php
/*
Plugin Name: Notifly - Post/Comment Emailer
Plugin URI: http://wordpress.org/extend/plugins/notifly/
Description: Sends a notification to all users of a site when a new post or comment is made. Add email addesses in your Discussion Settings area.
Author: Otto42, Matt, John James Jacoby
Version: 1.2
Author URI: http://ottodestruct.com
*/

/**
 * pce_add_settings_link( $links, $file )
 *
 * Add Settings link to plugins area
 *
 * @return string Links
 */
function pce_add_settings_link( $links, $file ) {
	if ( plugin_basename( __FILE__ ) == $file ) {
		$settings_link = '<a href="options-discussion.php">' . __( 'Settings', 'notifly' ) . '</a>';
		array_unshift( $links, $settings_link );
	}
	return $links;
}
add_filter( 'plugin_action_links', 'pce_add_settings_link', 10, 2 );


/**
 * pce_admin_loader ()
 *
 * Sets up the settings section in the Discussion admin screen
 *
 * @uses add_settings_section
 */
function pce_discussion_settings_loader() {
	// Add the section to Duscission options
	add_settings_section( 'pce_options', __( 'Notifications', 'notifly' ), 'pce_section_heading', 'discussion' );

	add_settings_field( 'pce_email_addresses', __( 'Email Addresses', 'notifly' ), 'pce_email_addresses_textarea', 'discussion', 'pce_options' );

	// Register our setting with the discussions page
	register_setting( 'discussion', 'pce_email_addresses', 'pce_validate_email_addresses' );
}
add_action( 'admin_init', 'pce_discussion_settings_loader' );

/**
 * pce_section_heading()
 *
 * Output the email address notification section in the Discussion admin screen
 *
 */
function pce_section_heading() {
	_e( 'Enter the email addresses of recipients to notify when new posts and comments are published. One per line.', 'notifly' );
}

/**
 * pce_email_addresses_textarea()
 *
 * Output the textarea of email addresses
 */
function pce_email_addresses_textarea() {
	$pce_email_addresses = get_option( 'pce_email_addresses' );
	$pce_email_addresses = str_replace( ' ', "\n", $pce_email_addresses );

	echo '<textarea class="large-text" id="pce_email_addresses" cols="50" rows="10" name="pce_email_addresses">' . $pce_email_addresses . '</textarea>';
}

/**
 * pce_validate_email_addresses( $email_addresses )
 *
 * Returns validated results
 *
 * @param string $email_addresses
 * @return string
 *
 */
function pce_validate_email_addresses( $email_addresses ) {

	// Make array out of textarea lines
	$recipients = str_replace( ' ', "\n", $email_addresses );
	$recipients = explode( "\n", $recipients );

	// Check validity of each address
	foreach ( $recipients as $recipient ) {
		if ( is_email( trim( $recipient ) ) )
			$valid_addresses .= $recipient . "\n";
	}

	// Trim off extra whitespace
	$valid_addresses = trim( $valid_addresses );

	// Return valid addresses
	return apply_filters( 'pce_validate_email_addresses', $valid_addresses );
}

/**
 * pce_get_recipients()
 *
 * Gets the recipients
 *
 * @param array $duplicates optional Users to remove from notifications
 * @return array
 */
function pce_get_recipients( $duplicates = '' ) {
	// Get recipients and turn into an array
	$recipients = get_option( 'pce_email_addresses' );
	$recipients = str_replace( ' ', "\n", $recipients );
	$recipients = explode( "\n", $recipients );

	// Loop through recipients and remove duplicates if any were passed
	if ( !empty( $duplicates ) ) {
		foreach ( $duplicates as $duplicate ) {
			foreach ( $recipients as $key => $recipient ) {
				if ( $duplicate === $recipient ) {
					unset( $recipients[$key] );
				}
			}
			$recipients = array_values( $recipients );
		}
	}

	// Return result
	return apply_filters( 'pce_get_recipients', $recipients );
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

	// Get comment info
	$comment                 = get_comment( $comment_id );
	$post                    = get_post( $comment->comment_post_ID );
	$author                  = get_userdata( $post->post_author );
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
	$message['comment_content']   = sprintf( __( 'Comment: %s', 'notifly' ), "<br />" . $comment->comment_content . "<br />" );
	$message['comment_permalink'] = sprintf( __( 'You can see all comments on this post here: %s', 'notifly' ), $permalink );

	foreach ( $message as $message_part )
		$email['body'] .= $message_part . "<br /><br />";

	// Email Headers
	$headers['from'] = sprintf( 'From: %1$s <%2$s>', !empty( $comment->comment_author ) ? $comment->comment_author : $blogname, $wp_email );
	$headers['mime'] = 'MIME-Version: 1.0';
	$headers['type'] = 'Content-Type: text/html; charset="' . get_option( 'blog_charset' ) . '"';
	if ( !empty( $comment->comment_author_email ) )
		$headers['reply-to'] = sprintf( 'Reply-To: %1$s <%2$s>', $comment->comment_author_email, $comment->comment_author_email );

	foreach ( $headers as $header_part )
		$email['headers'] .= $header_part . "\n";

	// Get recipients
	$recipients = pce_get_recipients( array( $author->user_email, $comment->comment_author_email ) );

	// Send email to each user
	foreach ( $recipients as $recipient )
		@wp_mail( $recipient, $email['subject'], $email['body'], $email['headers'] );
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

	// Get author
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
	$message['post_preview'] = sprintf( __( 'Post Preview: %s', 'notifly' ), "<br />" . strip_tags( $post->post_content ) );

	foreach ( $message as $message_part )
		$email['body'] .= $message_part . "<br /><br />";

	// Headers
	$headers['from'] = sprintf( 'From: %1$s <%2$s>', $blogname, $wp_email );
	$headers['mime'] = 'MIME-Version: 1.0';
	$headers['type'] = 'Content-Type: text/html; charset="' . get_option( 'blog_charset' ) . '"';
	if ( !empty( $user->user_email ) )
		$headers['reply-to'] = sprintf( 'Reply-To: %1$s <%2$s>', $user->user_email, $user->user_email );

	foreach ( $headers as $header_part )
		$email['headers'] .= $header_part . "\n";

	// Get recipients
	$recipients = pce_get_recipients( array( $author->user_email ) );
	
	// Send email to each user
	foreach ( $recipients as $recipient )
		@wp_mail( $recipient, $email['subject'], $email['message'], $email['headers'] );
}
add_action( 'transition_post_status', 'pce_post_email', 10, 3 );

?>
