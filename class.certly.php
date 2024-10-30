<?php

class Certly {
	const API_HOST = 'guard.certly.io';
	const API_PORT = 443;
	const MAX_DELAY_BEFORE_MODERATION_EMAIL = 86400; // One day in seconds

	private static $last_comment = '';
	private static $initiated = false;
	private static $prevent_moderation_email_for_these_comments = array();
	private static $last_comment_result = null;
	private static $comment_as_submitted_allowed_keys = array( 'blog' => '', 'blog_charset' => '', 'blog_lang' => '', 'blog_ua' => '', 'comment_agent' => '', 'comment_author' => '', 'comment_author_IP' => '', 'comment_author_email' => '', 'comment_author_url' => '', 'comment_content' => '', 'comment_date_gmt' => '', 'comment_tags' => '', 'comment_type' => '', 'guid' => '', 'is_test' => '', 'permalink' => '', 'reporter' => '', 'site_domain' => '', 'submit_referer' => '', 'submit_uri' => '', 'user_ID' => '', 'user_agent' => '', 'user_id' => '', 'user_ip' => '' );

	public static function init() {
		if ( ! self::$initiated ) {
			self::init_hooks();
		}
	}

	/**
	 * Initializes WordPress hooks
	 */
	private static function init_hooks() {
		self::$initiated = true;

		add_action( 'wp_insert_comment', array( 'Certly', 'auto_check_update_meta' ), 10, 2 );
		add_filter( 'preprocess_comment', array( 'Certly', 'auto_check_comment' ), 1 );
		add_action( 'certly_scheduled_delete', array( 'Certly', 'delete_old_comments' ) );
		add_action( 'certly_scheduled_delete', array( 'Certly', 'delete_old_comments_meta' ) );
		add_action( 'certly_schedule_cron_recheck', array( 'Certly', 'cron_recheck' ) );

		/**
		 * To disable the Certly comment nonce, add a filter for the 'certly_comment_nonce' tag
		 * and return any string value that is not 'true' or '' (empty string).
		 *
		 * Don't return boolean false, because that implies that the 'certly_comment_nonce' option
		 * has not been set and that Certly should just choose the default behavior for that
		 * situation.
		 */
		$certly_comment_nonce_option = apply_filters( 'certly_comment_nonce', get_option( 'certly_comment_nonce' ) );

		if ( $certly_comment_nonce_option == 'true' || $certly_comment_nonce_option == '' )
			add_action( 'comment_form',  array( 'Certly',  'add_comment_nonce' ), 1 );

		add_filter( 'comment_moderation_recipients', array( 'Certly', 'disable_moderation_emails_if_unreachable' ), 1000, 2 );
		add_filter( 'pre_comment_approved', array( 'Certly', 'last_comment_status' ), 10, 2 );

		add_action( 'transition_comment_status', array( 'Certly', 'transition_comment_status' ), 10, 3 );

		// Run this early in the pingback call, before doing a remote fetch of the source uri
		add_action( 'xmlrpc_call', array( 'Certly', 'pre_check_pingback' ) );
	}

	public static function get_api_key() {
		return apply_filters( 'certly_get_api_key', defined('CERTLY_API_KEY') ? constant('CERTLY_API_KEY') : get_option('certly_api_key') );
	}

	public static function check_key_status( $key, $ip = null ) {
		return self::http_post( Certly::build_query( array( 'key' => $key, 'blog' => get_option('home') ) ), 'key/verify');
	}

	public static function verify_key( $key, $ip = null ) {
		$response = self::check_key_status( $key, $ip );

		if ( $response[1] != 'valid' && $response[1] != 'invalid' )
			return 'failed';

		return $response[1];
	}

	public static function deactivate_key( $key ) {
		$response = self::http_post( Certly::build_query( array( 'key' => $key, 'blog' => get_option('home') ) ), 'deactivate' );

		if ( $response[1] != 'deactivated' )
			return 'failed';

		return $response[1];
	}

	/**
	 * When the certly option is updated, run the registration call.
	 *
	 * This should only be run when the option is updated from the Jetpack/WP.com
	 * API call, and only if the new key is different than the old key.
	 *
	 * @param mixed  $old_value   The old option value.
	 * @param mixed  $value       The new option value.
	 */
	public static function updated_option( $old_value, $value ) {
		// Not an API call
		if ( ! class_exists( 'WPCOM_JSON_API_Update_Option_Endpoint' ) ) {
			return;
		}
		// Only run the registration if the old key is different.
		if ( $old_value !== $value ) {
			self::verify_key( $value );
		}
	}

	public static function auto_check_comment( $commentdata ) {
		self::$last_comment_result = null;

		$comment = $commentdata;

		$comment['raw']          = base64_encode($comment['comment_content']);

		unset($comment['comment_content']);

		$comment['simple']       = 'true';
		$comment['ip']           = self::get_ip_address();
		$comment['user_agent']   = self::get_user_agent();
		$comment['referrer']     = self::get_referer();
		$comment['permalink']    = get_permalink( $comment['comment_post_ID'] );

		if ( !empty( $comment['user_ID'] ) )
			$comment['user_role'] = Certly::get_user_roles( $comment['user_ID'] );

		/** See filter documentation in init_hooks(). */
		$certly_nonce_option = apply_filters( 'certly_comment_nonce', get_option( 'certly_comment_nonce' ) );
		$comment['certly_comment_nonce'] = 'inactive';
		if ( $certly_nonce_option == 'true' || $certly_nonce_option == '' ) {
			$comment['certly_comment_nonce'] = 'failed';
			if ( isset( $_POST['certly_comment_nonce'] ) && wp_verify_nonce( $_POST['certly_comment_nonce'], 'certly_comment_nonce_' . $comment['comment_post_ID'] ) )
				$comment['certly_comment_nonce'] = 'passed';

			// comment reply in wp-admin
			if ( isset( $_POST['_ajax_nonce-replyto-comment'] ) && check_ajax_referer( 'replyto-comment', '_ajax_nonce-replyto-comment' ) )
				$comment['certly_comment_nonce'] = 'passed';

		}

		if ( self::is_test_mode() )
			$comment['is_test'] = 'true';

		foreach( $_POST as $key => $value ) {
			if ( is_string( $value ) )
				$comment["POST_{$key}"] = $value;
		}

		foreach ( $_SERVER as $key => $value ) {
			if ( ! is_string( $value ) ) {
				continue;
			}

			if ( preg_match( "/^HTTP_COOKIE/", $key ) ) {
				continue;
			}

			// Send any potentially useful $_SERVER vars, but avoid sending junk we don't need.
			if ( preg_match( "/^(HTTP_|REMOTE_ADDR|REQUEST_URI|DOCUMENT_URI)/", $key ) ) {
				$comment[ "$key" ] = $value;
			}
		}

		$post = get_post( $comment['comment_post_ID'] );
		$comment[ 'comment_post_modified_gmt' ] = $post->post_modified_gmt;

		$response = self::http_post( Certly::build_query( $comment ), 'lookup' );

		do_action( 'certly_comment_check_response', $response );

		$commentdata['comment_as_submitted'] = array_intersect_key( $comment, self::$comment_as_submitted_allowed_keys );
		$commentdata['certly_result']       = $response[1];

		if ( isset( $response[0]['x-certly-error'] ) ) {
			// An error occurred that we anticipated (like a suspended key) and want the user to act on.
			// Send to moderation.
			self::$last_comment_result = '0';
		}
		else if ( 'true' == $response[1] ) {
			// certly_spam_count will be incremented later by comment_is_spam()
			self::$last_comment_result = 'spam';

			$discard = self::allow_discard();

			do_action( 'certly_spam_caught', $discard );

			if ($discard) {
				if ( $incr = apply_filters('certly_spam_count_incr', 1) )
					update_option( 'certly_spam_count', get_option('certly_spam_count') + $incr );
				$redirect_to = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : get_permalink( $post );
				wp_safe_redirect( esc_url_raw( $redirect_to ) );
				die();
			}
		}

		// if the response is neither true nor false, hold the comment for moderation and schedule a recheck
		if ( 'true' != $response[1] && 'false' != $response[1] ) {
			if ( !current_user_can('moderate_comments') ) {
				// Comment status should be moderated
				self::$last_comment_result = '0';
			}
			if ( function_exists('wp_next_scheduled') && function_exists('wp_schedule_single_event') ) {
				if ( !wp_next_scheduled( 'certly_schedule_cron_recheck' ) ) {
					wp_schedule_single_event( time() + 1200, 'certly_schedule_cron_recheck' );
					do_action( 'certly_scheduled_recheck', 'invalid-response-' . $response[1] );
				}
			}

			self::$prevent_moderation_email_for_these_comments[] = $commentdata;
		}

		if ( function_exists('wp_next_scheduled') && function_exists('wp_schedule_event') ) {
			// WP 2.1+: delete old comments daily
			if ( !wp_next_scheduled( 'certly_scheduled_delete' ) )
				wp_schedule_event( time(), 'daily', 'certly_scheduled_delete' );
		}
		elseif ( (mt_rand(1, 10) == 3) ) {
			// WP 2.0: run this one time in ten
			self::delete_old_comments();
		}

		self::set_last_comment( $commentdata );
		self::fix_scheduled_recheck();

		return $commentdata;
	}

	public static function get_last_comment() {
		return self::$last_comment;
	}

	public static function set_last_comment( $comment ) {
		if ( is_null( $comment ) ) {
			self::$last_comment = null;
		}
		else {
			// We filter it here so that it matches the filtered comment data that we'll have to compare against later.
			// wp_filter_comment expects comment_author_IP
			self::$last_comment = wp_filter_comment(
				array_merge(
					array( 'comment_author_IP' => self::get_ip_address() ),
					$comment
				)
			);
		}
	}

	// this fires on wp_insert_comment.  we can't update comment_meta when auto_check_comment() runs
	// because we don't know the comment ID at that point.
	public static function auto_check_update_meta( $id, $comment ) {

		// failsafe for old WP versions
		if ( !function_exists('add_comment_meta') )
			return false;

		if ( !isset( self::$last_comment['comment_author_email'] ) )
			self::$last_comment['comment_author_email'] = '';

		// wp_insert_comment() might be called in other contexts, so make sure this is the same comment
		// as was checked by auto_check_comment
		if ( is_object( $comment ) && !empty( self::$last_comment ) && is_array( self::$last_comment ) ) {
			if ( self::matches_last_comment( $comment ) ) {

					load_plugin_textdomain( 'certly' );

					// normal result: true or false
					if ( self::$last_comment['certly_result'] == 'true' ) {
						update_comment_meta( $comment->comment_ID, 'certly_result', 'true' );
						self::update_comment_history( $comment->comment_ID, '', 'check-spam' );
						if ( $comment->comment_approved != 'spam' )
							self::update_comment_history(
								$comment->comment_ID,
								'',
								'status-changed-'.$comment->comment_approved
							);
					}
					elseif ( self::$last_comment['certly_result'] == 'false' ) {
						update_comment_meta( $comment->comment_ID, 'certly_result', 'false' );
						self::update_comment_history( $comment->comment_ID, '', 'check-ham' );
						// Status could be spam or trash, depending on the WP version and whether this change applies:
						// https://core.trac.wordpress.org/changeset/34726
						if ( $comment->comment_approved == 'spam' || $comment->comment_approved == 'trash' ) {
							if ( wp_blacklist_check($comment->comment_author, $comment->comment_author_email, $comment->comment_author_url, $comment->comment_content, $comment->comment_author_IP, $comment->comment_agent) )
								self::update_comment_history( $comment->comment_ID, '', 'wp-blacklisted' );
							else
								self::update_comment_history( $comment->comment_ID, '', 'status-changed-'.$comment->comment_approved );
						}
					} // abnormal result: error
					else {
						update_comment_meta( $comment->comment_ID, 'certly_error', time() );
						self::update_comment_history(
							$comment->comment_ID,
							'',
							'check-error',
							array( 'response' => substr( self::$last_comment['certly_result'], 0, 50 ) )
						);
					}

					// record the complete original data as submitted for checking
					if ( isset( self::$last_comment['comment_as_submitted'] ) )
						update_comment_meta( $comment->comment_ID, 'certly_as_submitted', self::$last_comment['comment_as_submitted'] );

					if ( isset( self::$last_comment['certly_pro_tip'] ) )
						update_comment_meta( $comment->comment_ID, 'certly_pro_tip', self::$last_comment['certly_pro_tip'] );
			}
		}
	}

	public static function delete_old_comments() {
		global $wpdb;

		/**
		 * Determines how many comments will be deleted in each batch.
		 *
		 * @param int The default, as defined by CERTLY_DELETE_LIMIT.
		 */
		$delete_limit = apply_filters( 'certly_delete_comment_limit', defined( 'CERTLY_DELETE_LIMIT' ) ? CERTLY_DELETE_LIMIT : 10000 );
		$delete_limit = max( 1, intval( $delete_limit ) );

		/**
		 * Determines how many days a comment will be left in the Spam queue before being deleted.
		 *
		 * @param int The default number of days.
		 */
		$delete_interval = apply_filters( 'certly_delete_comment_interval', 15 );
		$delete_interval = max( 1, intval( $delete_interval ) );

		while ( $comment_ids = $wpdb->get_col( $wpdb->prepare( "SELECT comment_id FROM {$wpdb->comments} WHERE DATE_SUB(NOW(), INTERVAL %d DAY) > comment_date_gmt AND comment_approved = 'spam' LIMIT %d", $delete_interval, $delete_limit ) ) ) {
			if ( empty( $comment_ids ) )
				return;

			$wpdb->queries = array();

			foreach ( $comment_ids as $comment_id ) {
				do_action( 'delete_comment', $comment_id );
			}

			$comma_comment_ids = implode( ', ', array_map('intval', $comment_ids) );

			$wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_id IN ( $comma_comment_ids )");
			$wpdb->query("DELETE FROM {$wpdb->commentmeta} WHERE comment_id IN ( $comma_comment_ids )");

			clean_comment_cache( $comment_ids );
		}

		if ( apply_filters( 'certly_optimize_table', ( mt_rand(1, 5000) == 11), $wpdb->comments ) ) // lucky number
			$wpdb->query("OPTIMIZE TABLE {$wpdb->comments}");
	}

	public static function delete_old_comments_meta() {
		global $wpdb;

		$interval = apply_filters( 'certly_delete_commentmeta_interval', 15 );

		# enfore a minimum of 1 day
		$interval = absint( $interval );
		if ( $interval < 1 )
			$interval = 1;

		// certly_as_submitted meta values are large, so expire them
		// after $interval days regardless of the comment status
		while ( $comment_ids = $wpdb->get_col( $wpdb->prepare( "SELECT m.comment_id FROM {$wpdb->commentmeta} as m INNER JOIN {$wpdb->comments} as c USING(comment_id) WHERE m.meta_key = 'certly_as_submitted' AND DATE_SUB(NOW(), INTERVAL %d DAY) > c.comment_date_gmt LIMIT 10000", $interval ) ) ) {
			if ( empty( $comment_ids ) )
				return;

			$wpdb->queries = array();

			foreach ( $comment_ids as $comment_id ) {
				delete_comment_meta( $comment_id, 'certly_as_submitted' );
			}
		}

		if ( apply_filters( 'certly_optimize_table', ( mt_rand(1, 5000) == 11), $wpdb->commentmeta ) ) // lucky number
			$wpdb->query("OPTIMIZE TABLE {$wpdb->commentmeta}");
	}

	// how many approved comments does this author have?
	public static function get_user_comments_approved( $user_id, $comment_author_email, $comment_author, $comment_author_url ) {
		global $wpdb;

		if ( !empty( $user_id ) )
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d AND comment_approved = 1", $user_id ) );

		if ( !empty( $comment_author_email ) )
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_author_email = %s AND comment_author = %s AND comment_author_url = %s AND comment_approved = 1", $comment_author_email, $comment_author, $comment_author_url ) );

		return 0;
	}

	// get the full comment history for a given comment, as an array in reverse chronological order
	public static function get_comment_history( $comment_id ) {

		// failsafe for old WP versions
		if ( !function_exists('add_comment_meta') )
			return false;

		$history = get_comment_meta( $comment_id, 'certly_history', false );
		usort( $history, array( 'Certly', '_cmp_time' ) );
		return $history;
	}

	/**
	 * Log an event for a given comment, storing it in comment_meta.
	 *
	 * @param int $comment_id The ID of the relevant comment.
	 * @param string $message The string description of the event. No longer used.
	 * @param string $event The event code.
	 * @param array $meta Metadata about the history entry. e.g., the user that reported or changed the status of a given comment.
	 */
	public static function update_comment_history( $comment_id, $message, $event=null, $meta=null ) {
		global $current_user;

		// failsafe for old WP versions
		if ( !function_exists('add_comment_meta') )
			return false;

		$user = '';

		$event = array(
			'time'    => self::_get_microtime(),
			'event'   => $event,
		);

		if ( is_object( $current_user ) && isset( $current_user->user_login ) ) {
			$event['user'] = $current_user->user_login;
		}

		if ( ! empty( $meta ) ) {
			$event['meta'] = $meta;
		}

		// $unique = false so as to allow multiple values per comment
		$r = add_comment_meta( $comment_id, 'certly_history', $event, false );
	}

	public static function check_db_comment( $id, $recheck_reason = 'recheck_queue' ) {
		global $wpdb;

		$c = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->comments} WHERE comment_ID = %d", $id ), ARRAY_A );
		if ( !$c )
			return;

		$c['raw'] = base64_encode($c['comment_content']);
		$c['ip']        = $c['comment_author_IP'];
		$c['user_agent']     = $c['comment_agent'];
		$c['referrer']       = '';
		$c['blog']           = get_option('home');
		$c['blog_lang']      = get_locale();
		$c['blog_charset']   = get_option('blog_charset');
		$c['permalink']      = get_permalink($c['comment_post_ID']);
		$c['recheck_reason'] = $recheck_reason;

		if ( self::is_test_mode() )
			$c['is_test'] = 'true';

		$response = self::http_post( Certly::build_query( $c ), 'lookup' );

		return ( is_array( $response ) && ! empty( $response[1] ) ) ? $response[1] : false;
	}



	public static function transition_comment_status( $new_status, $old_status, $comment ) {

		if ( $new_status == $old_status )
			return;

		# we don't need to record a history item for deleted comments
		if ( $new_status == 'delete' )
			return;

		if ( !current_user_can( 'edit_post', $comment->comment_post_ID ) && !current_user_can( 'moderate_comments' ) )
			return;

		if ( defined('WP_IMPORTING') && WP_IMPORTING == true )
			return;

		// if this is present, it means the status has been changed by a re-check, not an explicit user action
		if ( get_comment_meta( $comment->comment_ID, 'certly_rechecking' ) )
			return;

		global $current_user;
		$reporter = '';
		if ( is_object( $current_user ) )
			$reporter = $current_user->user_login;

		// Assumption alert:
		// We want to submit comments to Certly only when a moderator explicitly spams or approves it - not if the status
		// is changed automatically by another plugin.  Unfortunately WordPress doesn't provide an unambiguous way to
		// determine why the transition_comment_status action was triggered.  And there are several different ways by which
		// to spam and unspam comments: bulk actions, ajax, links in moderation emails, the dashboard, and perhaps others.
		// We'll assume that this is an explicit user action if certain POST/GET variables exist.
		if ( ( isset( $_POST['status'] ) && in_array( $_POST['status'], array( 'spam', 'unspam' ) ) ) ||
			 ( isset( $_POST['spam'] )   && (int) $_POST['spam'] == 1 ) ||
			 ( isset( $_POST['unspam'] ) && (int) $_POST['unspam'] == 1 ) ||
			 ( isset( $_POST['comment_status'] )  && in_array( $_POST['comment_status'], array( 'spam', 'unspam' ) ) ) ||
			 ( isset( $_GET['action'] )  && in_array( $_GET['action'], array( 'spam', 'unspam' ) ) ) ||
			 ( isset( $_POST['action'] ) && in_array( $_POST['action'], array( 'editedcomment' ) ) )
		 ) {
			if ( $new_status == 'spam' && ( $old_status == 'approved' || $old_status == 'unapproved' || !$old_status ) ) {
				return self::submit_spam_comment( $comment->comment_ID );
			} elseif ( $old_status == 'spam' && ( $new_status == 'approved' || $new_status == 'unapproved' ) ) {
				return self::submit_nonspam_comment( $comment->comment_ID );
			}
		}

		self::update_comment_history( $comment->comment_ID, '', 'status-' . $new_status );
	}

	public static function submit_spam_comment( $comment_id ) {
		global $wpdb, $current_user, $current_site;

		$comment_id = (int) $comment_id;

		$comment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->comments} WHERE comment_ID = %d", $comment_id ) );

		if ( !$comment ) // it was deleted
			return;

		if ( 'spam' != $comment->comment_approved )
			return;

		// use the original version stored in comment_meta if available
		$as_submitted = self::sanitize_comment_as_submitted( get_comment_meta( $comment_id, 'certly_as_submitted', true ) );

		if ( $as_submitted && is_array( $as_submitted ) && isset( $as_submitted['comment_content'] ) )
			$comment = (object) array_merge( (array)$comment, $as_submitted );

		$comment->blog         = get_bloginfo('url');
		$comment->blog_lang    = get_locale();
		$comment->blog_charset = get_option('blog_charset');
		$comment->permalink    = get_permalink($comment->comment_post_ID);

		if ( is_object($current_user) )
			$comment->reporter = $current_user->user_login;

		if ( is_object($current_site) )
			$comment->site_domain = $current_site->domain;

		$comment->user_role = '';
		if ( isset( $comment->user_ID ) )
			$comment->user_role = Certly::get_user_roles( $comment->user_ID );

		if ( self::is_test_mode() )
			$comment->is_test = 'true';

		$post = get_post( $comment->comment_post_ID );
		$comment->comment_post_modified_gmt = $post->post_modified_gmt;

		$response = Certly::http_post( Certly::build_query( $comment ), 'submit-spam' );
		if ( $comment->reporter ) {
			self::update_comment_history( $comment_id, '', 'report-spam' );
			update_comment_meta( $comment_id, 'certly_user_result', 'true' );
			update_comment_meta( $comment_id, 'certly_user', $comment->reporter );
		}

		do_action('certly_submit_spam_comment', $comment_id, $response[1]);
	}

	public static function submit_nonspam_comment( $comment_id ) {
		global $wpdb, $current_user, $current_site;

		$comment_id = (int) $comment_id;

		$comment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->comments} WHERE comment_ID = %d", $comment_id ) );
		if ( !$comment ) // it was deleted
			return;

		// use the original version stored in comment_meta if available
		$as_submitted = self::sanitize_comment_as_submitted( get_comment_meta( $comment_id, 'certly_as_submitted', true ) );

		if ( $as_submitted && is_array($as_submitted) && isset($as_submitted['comment_content']) )
			$comment = (object) array_merge( (array)$comment, $as_submitted );

		$comment->blog         = get_bloginfo('url');
		$comment->blog_lang    = get_locale();
		$comment->blog_charset = get_option('blog_charset');
		$comment->permalink    = get_permalink( $comment->comment_post_ID );
		$comment->user_role    = '';

		if ( is_object($current_user) )
			$comment->reporter = $current_user->user_login;

		if ( is_object($current_site) )
			$comment->site_domain = $current_site->domain;

		if ( isset( $comment->user_ID ) )
			$comment->user_role = Certly::get_user_roles($comment->user_ID);

		if ( Certly::is_test_mode() )
			$comment->is_test = 'true';

		$post = get_post( $comment->comment_post_ID );
		$comment->comment_post_modified_gmt = $post->post_modified_gmt;

		$response = self::http_post( Certly::build_query( $comment ), 'submit-ham' );
		if ( $comment->reporter ) {
			self::update_comment_history( $comment_id, '', 'report-ham' );
			update_comment_meta( $comment_id, 'certly_user_result', 'false' );
			update_comment_meta( $comment_id, 'certly_user', $comment->reporter );
		}

		do_action('certly_submit_nonspam_comment', $comment_id, $response[1]);
	}

	public static function cron_recheck() {
		global $wpdb;

		$api_key = self::get_api_key();

		$status = self::verify_key( $api_key );
		if ( get_option( 'certly_alert_code' ) || $status == 'invalid' ) {
			// since there is currently a problem with the key, reschedule a check for 6 hours hence
			wp_schedule_single_event( time() + 21600, 'certly_schedule_cron_recheck' );
			do_action( 'certly_scheduled_recheck', 'key-problem-' . get_option( 'certly_alert_code' ) . '-' . $status );
			return false;
		}

		delete_option('certly_available_servers');

		$comment_errors = $wpdb->get_col( "SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key = 'certly_error'	LIMIT 100" );

		load_plugin_textdomain( 'certly' );

		foreach ( (array) $comment_errors as $comment_id ) {
			// if the comment no longer exists, or is too old, remove the meta entry from the queue to avoid getting stuck
			$comment = get_comment( $comment_id );
			if ( !$comment || strtotime( $comment->comment_date_gmt ) < strtotime( "-15 days" ) ) {
				delete_comment_meta( $comment_id, 'certly_error' );
				delete_comment_meta( $comment_id, 'certly_delayed_moderation_email' );
				continue;
			}

			add_comment_meta( $comment_id, 'certly_rechecking', true );
			$status = self::check_db_comment( $comment_id, 'retry' );

			$event = '';
			if ( $status == 'true' ) {
				$event = 'cron-retry-spam';
			} elseif ( $status == 'false' ) {
				$event = 'cron-retry-ham';
			}

			// If we got back a legit response then update the comment history
			// other wise just bail now and try again later.  No point in
			// re-trying all the comments once we hit one failure.
			if ( !empty( $event ) ) {
				delete_comment_meta( $comment_id, 'certly_error' );
				self::update_comment_history( $comment_id, '', $event );
				update_comment_meta( $comment_id, 'certly_result', $status );
				// make sure the comment status is still pending.  if it isn't, that means the user has already moved it elsewhere.
				$comment = get_comment( $comment_id );
				if ( $comment && 'unapproved' == wp_get_comment_status( $comment_id ) ) {
					if ( $status == 'true' ) {
						wp_spam_comment( $comment_id );
					} elseif ( $status == 'false' ) {
						// comment is good, but it's still in the pending queue.  depending on the moderation settings
						// we may need to change it to approved.
						if ( check_comment($comment->comment_author, $comment->comment_author_email, $comment->comment_author_url, $comment->comment_content, $comment->comment_author_IP, $comment->comment_agent, $comment->comment_type) )
							wp_set_comment_status( $comment_id, 1 );
						else if ( get_comment_meta( $comment_id, 'certly_delayed_moderation_email', true ) )
							wp_notify_moderator( $comment_id );
					}
				}

				delete_comment_meta( $comment_id, 'certly_delayed_moderation_email' );
			} else {
				// If this comment has been pending moderation for longer than MAX_DELAY_BEFORE_MODERATION_EMAIL,
				// send a moderation email now.
				if ( ( intval( gmdate( 'U' ) ) - strtotime( $comment->comment_date_gmt ) ) < self::MAX_DELAY_BEFORE_MODERATION_EMAIL ) {
					delete_comment_meta( $comment_id, 'certly_delayed_moderation_email' );
					wp_notify_moderator( $comment_id );
				}

				delete_comment_meta( $comment_id, 'certly_rechecking' );
				wp_schedule_single_event( time() + 1200, 'certly_schedule_cron_recheck' );
				do_action( 'certly_scheduled_recheck', 'check-db-comment-' . $status );
				return;
			}
			delete_comment_meta( $comment_id, 'certly_rechecking' );
		}

		$remaining = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE meta_key = 'certly_error'" );
		if ( $remaining && !wp_next_scheduled('certly_schedule_cron_recheck') ) {
			wp_schedule_single_event( time() + 1200, 'certly_schedule_cron_recheck' );
			do_action( 'certly_scheduled_recheck', 'remaining' );
		}
	}

	public static function fix_scheduled_recheck() {
		$future_check = wp_next_scheduled( 'certly_schedule_cron_recheck' );
		if ( !$future_check ) {
			return;
		}

		if ( get_option( 'certly_alert_code' ) > 0 ) {
			return;
		}

		$check_range = time() + 1200;
		if ( $future_check > $check_range ) {
			wp_clear_scheduled_hook( 'certly_schedule_cron_recheck' );
			wp_schedule_single_event( time() + 300, 'certly_schedule_cron_recheck' );
			do_action( 'certly_scheduled_recheck', 'fix-scheduled-recheck' );
		}
	}

	public static function add_comment_nonce( $post_id ) {
		echo '<p style="display: none;">';
		wp_nonce_field( 'certly_comment_nonce_' . $post_id, 'certly_comment_nonce', FALSE );
		echo '</p>';
	}

	public static function is_test_mode() {
		return defined('CERTLY_TEST_MODE') && CERTLY_TEST_MODE;
	}

	public static function allow_discard() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
			return false;
		if ( is_user_logged_in() )
			return false;

		return ( get_option( 'certly_strictness' ) === '1'  );
	}

	public static function get_ip_address() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : null;
	}

	/**
	 * Do these two comments, without checking the comment_ID, "match"?
	 *
	 * @param mixed $comment1 A comment object or array.
	 * @param mixed $comment2 A comment object or array.
	 * @return bool Whether the two comments should be treated as the same comment.
	 */
	private static function comments_match( $comment1, $comment2 ) {
		$comment1 = (array) $comment1;
		$comment2 = (array) $comment2;

		$comments_match = (
			   isset( $comment1['comment_post_ID'], $comment2['comment_post_ID'] )
			&& intval( $comment1['comment_post_ID'] ) == intval( $comment2['comment_post_ID'] )
			&& (
				// The comment author length max is 255 characters, limited by the TINYTEXT column type.
				// If the comment author includes multibyte characters right around the 255-byte mark, they
				// may be stripped when the author is saved in the DB, so a 300+ char author may turn into
				// a 253-char author when it's saved, not 255 exactly.  The longest possible character is
				// theoretically 6 bytes, so we'll only look at the first 248 bytes to be safe.
				substr( $comment1['comment_author'], 0, 248 ) == substr( $comment2['comment_author'], 0, 248 )
				|| substr( stripslashes( $comment1['comment_author'] ), 0, 248 ) == substr( $comment2['comment_author'], 0, 248 )
				|| substr( $comment1['comment_author'], 0, 248 ) == substr( stripslashes( $comment2['comment_author'] ), 0, 248 )
				// Certain long comment author names will be truncated to nothing, depending on their encoding.
				|| ( ! $comment1['comment_author'] && strlen( $comment2['comment_author'] ) > 248 )
				|| ( ! $comment2['comment_author'] && strlen( $comment1['comment_author'] ) > 248 )
				)
			&& (
				// The email max length is 100 characters, limited by the VARCHAR(100) column type.
				// Same argument as above for only looking at the first 93 characters.
				substr( $comment1['comment_author_email'], 0, 93 ) == substr( $comment2['comment_author_email'], 0, 93 )
				|| substr( stripslashes( $comment1['comment_author_email'] ), 0, 93 ) == substr( $comment2['comment_author_email'], 0, 93 )
				|| substr( $comment1['comment_author_email'], 0, 93 ) == substr( stripslashes( $comment2['comment_author_email'] ), 0, 93 )
				// Very long emails can be truncated and then stripped if the [0:100] substring isn't a valid address.
				|| ( ! $comment1['comment_author_email'] && strlen( $comment2['comment_author_email'] ) > 100 )
				|| ( ! $comment2['comment_author_email'] && strlen( $comment1['comment_author_email'] ) > 100 )
			)
		);

		return $comments_match;
	}

	// Does the supplied comment match the details of the one most recently stored in self::$last_comment?
	public static function matches_last_comment( $comment ) {
		if ( is_object( $comment ) )
			$comment = (array) $comment;

		return self::comments_match( self::$last_comment, $comment );
	}

	private static function get_user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null;
	}

	private static function get_referer() {
		return isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : null;
	}

	// return a comma-separated list of role names for the given user
	public static function get_user_roles( $user_id ) {
		$roles = false;

		if ( !class_exists('WP_User') )
			return false;

		if ( $user_id > 0 ) {
			$comment_user = new WP_User( $user_id );
			if ( isset( $comment_user->roles ) )
				$roles = join( ',', $comment_user->roles );
		}

		if ( is_multisite() && is_super_admin( $user_id ) ) {
			if ( empty( $roles ) ) {
				$roles = 'super_admin';
			} else {
				$comment_user->roles[] = 'super_admin';
				$roles = join( ',', $comment_user->roles );
			}
		}

		return $roles;
	}

	// filter handler used to return a spam result to pre_comment_approved
	public static function last_comment_status( $approved, $comment ) {
		// Only do this if it's the correct comment
		if ( is_null(self::$last_comment_result) || ! self::matches_last_comment( $comment ) ) {
			self::log( "comment_is_spam mismatched comment, returning unaltered $approved" );
			return $approved;
		}

		// bump the counter here instead of when the filter is added to reduce the possibility of overcounting
		if ( $incr = apply_filters('certly_spam_count_incr', 1) )
			update_option( 'certly_spam_count', get_option('certly_spam_count') + $incr );

		return self::$last_comment_result;
	}

	/**
	 * If Certly is temporarily unreachable, we don't want to "spam" the blogger with
	 * moderation emails for comments that will be automatically cleared or spammed on
	 * the next retry.
	 *
	 * For comments that will be rechecked later, empty the list of email addresses that
	 * the moderation email would be sent to.
	 *
	 * @param array $emails An array of email addresses that the moderation email will be sent to.
	 * @param int $comment_id The ID of the relevant comment.
	 * @return array An array of email addresses that the moderation email will be sent to.
	 */
	public static function disable_moderation_emails_if_unreachable( $emails, $comment_id ) {
		if ( ! empty( self::$prevent_moderation_email_for_these_comments ) && ! empty( $emails ) ) {
			$comment = get_comment( $comment_id );

			foreach ( self::$prevent_moderation_email_for_these_comments as $possible_match ) {
				if ( self::comments_match( $possible_match, $comment ) ) {
					update_comment_meta( $comment_id, 'certly_delayed_moderation_email', true );
					return array();
				}
			}
		}

		return $emails;
	}

	public static function _cmp_time( $a, $b ) {
		return $a['time'] > $b['time'] ? -1 : 1;
	}

	public static function _get_microtime() {
		$mtime = explode( ' ', microtime() );
		return $mtime[1] + $mtime[0];
	}

	/**
	 * Make a POST request to the Certly API.
	 *
	 * @param string $request The body of the request.
	 * @param string $path The path for the request.
	 * @param string $ip The specific IP address to hit.
	 * @return array A two-member array consisting of the headers and the response body, both empty in the case of a failure.
	 */
	public static function http_post( $request, $path, $ip=null ) {

		$certly_ua = sprintf( 'WordPress/%s | Certly/%s', $GLOBALS['wp_version'], constant( 'CERTLY_VERSION' ) );
		$certly_ua = apply_filters( 'certly_ua', $certly_ua );

		$content_length = strlen( $request );

		$api_key   = self::get_api_key();
		$host      = self::API_HOST;

		$http_host = $host;

		$http_args = array(
			'body' => $request,
			'headers' => array(
				'Content-Type' => 'application/x-www-form-urlencoded; charset=' . get_option( 'blog_charset' ),
				'Host' => $host,
				'User-Agent' => $certly_ua,
			),
			'httpversion' => '1.0',
			'timeout' => 15
		);

		$certly_url = $http_certly_url = "https://{$http_host}/api/v1/{$path}";

		$response = wp_remote_post( $certly_url, $http_args );

		Certly::log( compact( 'certly_url', 'http_args', 'response' ) );

		if ( is_wp_error( $response ) ) {
			do_action( 'certly_request_failure', $response );

			return array( '', '' );
		}

		$simplified_response = array( $response['headers'], $response['body'] );

		self::update_alert( $simplified_response );

		return $simplified_response;
	}

	// given a response from an API call like check_key_status(), update the alert code options if an alert is present.
	private static function update_alert( $response ) {
		$code = $msg = null;
		if ( isset( $response[0]['x-certly-alert-code'] ) ) {
			$code = $response[0]['x-certly-alert-code'];
			$msg  = $response[0]['x-certly-alert-msg'];
		}

		// only call update_option() if the value has changed
		if ( $code != get_option( 'certly_alert_code' ) ) {
			if ( ! $code ) {
				delete_option( 'certly_alert_code' );
				delete_option( 'certly_alert_msg' );
			}
			else {
				update_option( 'certly_alert_code', $code );
				update_option( 'certly_alert_msg', $msg );
			}
		}
	}


	private static function bail_on_activation( $message, $deactivate = true ) {
?>
<!doctype html>
<html>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<style>
* {
	text-align: center;
	margin: 0;
	padding: 0;
	font-family: "Lucida Grande",Verdana,Arial,"Bitstream Vera Sans",sans-serif;
}
p {
	margin-top: 1em;
	font-size: 18px;
}
</style>
<body>
<p><?php echo esc_html( $message ); ?></p>
</body>
</html>
<?php
		if ( $deactivate ) {
			$plugins = get_option( 'active_plugins' );
			$certly = plugin_basename( CERTLY__PLUGIN_DIR . 'certly.php' );
			$update  = false;
			foreach ( $plugins as $i => $plugin ) {
				if ( $plugin === $certly ) {
					$plugins[$i] = false;
					$update = true;
				}
			}

			if ( $update ) {
				update_option( 'active_plugins', array_filter( $plugins ) );
			}
		}
		exit;
	}

	public static function view( $name, array $args = array() ) {
		$args = apply_filters( 'certly_view_arguments', $args, $name );

		foreach ( $args AS $key => $val ) {
			$$key = $val;
		}

		load_plugin_textdomain( 'certly' );

		$file = CERTLY__PLUGIN_DIR . 'views/'. $name . '.php';

		include( $file );
	}

	/**
	 * Attached to activate_{ plugin_basename( __FILES__ ) } by register_activation_hook()
	 * @static
	 */
	public static function plugin_activation() {
		if ( version_compare( $GLOBALS['wp_version'], CERTLY__MINIMUM_WP_VERSION, '<' ) ) {
			load_plugin_textdomain( 'certly' );

			$message = '<strong>'.sprintf(esc_html__( 'Certly %s requires WordPress %s or higher.' , 'certly'), CERTLY_VERSION, CERTLY__MINIMUM_WP_VERSION ).'</strong> '.sprintf(__('Please <a href="%1$s">upgrade WordPress</a> to a current version, or <a href="%2$s">downgrade to version 2.4 of the Certly plugin</a>.', 'certly'), 'https://codex.wordpress.org/Upgrading_WordPress', 'http://wordpress.org/extend/plugins/certly/download/');

			Certly::bail_on_activation( $message );
		}
	}

	/**
	 * Removes all connection options
	 * @static
	 */
	public static function plugin_deactivation( ) {
		return self::deactivate_key( self::get_api_key() );
	}

	/**
	 * Essentially a copy of WP's build_query but one that doesn't expect pre-urlencoded values.
	 *
	 * @param array $args An array of key => value pairs
	 * @return string A string ready for use as a URL query string.
	 */
	public static function build_query( $args ) {
			if (is_object($args)) {
					$args = (array) $args;
			}

			$args['token'] = Certly::get_api_key();

			return _http_build_query( $args, '', '&' );
	}

	/**
	 * Log debugging info to the error log.
	 *
	 * Enabled when WP_DEBUG_LOG is enabled, but can be disabled via the certly_debug_log filter.
	 *
	 * @param mixed $certly_debug The data to log.
	 */
	public static function log( $certly_debug ) {
		if ( apply_filters( 'certly_debug_log', defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ) {
			error_log( print_r( compact( 'certly_debug' ), true ) );
		}
	}

	public static function pre_check_pingback( $method ) {
		if ( $method !== 'pingback.ping' )
			return;

		global $wp_xmlrpc_server;

		if ( !is_object( $wp_xmlrpc_server ) )
			return false;

		// Lame: tightly coupled with the IXR class.
		$args = $wp_xmlrpc_server->message->params;

		if ( !empty( $args[1] ) ) {
			$post_id = url_to_postid( $args[1] );

			// If this gets through the pre-check, make sure we properly identify the outbound request as a pingback verification
			Certly::pingback_forwarded_for( null, $args[0] );
			add_filter( 'http_request_args', array( 'Certly', 'pingback_forwarded_for' ), 10, 2 );

			$comment = array(
				'comment_author_url' => $args[0],
				'comment_post_ID' => $post_id,
				'comment_author' => '',
				'comment_author_email' => '',
				'comment_content' => '',
				'comment_type' => 'pingback',
				'certly_pre_check' => '1',
				'comment_pingback_target' => $args[1],
			);

			$comment = Certly::auto_check_comment( $comment );

			if ( isset( $comment['certly_result'] ) && 'true' == $comment['certly_result'] ) {
				// Lame: tightly coupled with the IXR classes. Unfortunately the action provides no context and no way to return anything.
				$wp_xmlrpc_server->error( new IXR_Error( 0, 'Invalid discovery target' ) );
			}
		}
	}

	public static function pingback_forwarded_for( $r, $url ) {
		static $urls = array();

		// Call this with $r == null to prime the callback to add headers on a specific URL
		if ( is_null( $r ) && !in_array( $url, $urls ) ) {
			$urls[] = $url;
		}

		// Add X-Pingback-Forwarded-For header, but only for requests to a specific URL (the apparent pingback source)
		if ( is_array( $r ) && is_array( $r['headers'] ) && !isset( $r['headers']['X-Pingback-Forwarded-For'] ) && in_array( $url, $urls ) ) {
			$remote_ip = preg_replace( '/[^a-fx0-9:.,]/i', '', $_SERVER['REMOTE_ADDR'] );

			// Note: this assumes REMOTE_ADDR is correct, and it may not be if a reverse proxy or CDN is in use
			$r['headers']['X-Pingback-Forwarded-For'] = $remote_ip;

			// Also identify the request as a pingback verification in the UA string so it appears in logs
			$r['user-agent'] .= '; verifying pingback from ' . $remote_ip;
		}

		return $r;
	}

	/**
	 * Ensure that we are loading expected scalar values from certly_as_submitted commentmeta.
	 *
	 * @param mixed $meta_value
	 * @return mixed
	 */
	private static function sanitize_comment_as_submitted( $meta_value ) {
		if ( empty( $meta_value ) ) {
			return $meta_value;
		}

		$meta_value = (array) $meta_value;

		foreach ( $meta_value as $key => $value ) {
			if ( ! isset( self::$comment_as_submitted_allowed_keys[$key] ) || ! is_scalar( $value ) ) {
				unset( $meta_value[$key] );
			}
		}

		return $meta_value;
	}
}
