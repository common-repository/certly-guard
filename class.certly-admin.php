<?php

class Certly_Admin {
	const NONCE = 'certly-update-key';

	private static $initiated = false;
	private static $notices   = array();
	private static $allowed   = array(
	    'a' => array(
	        'href' => true,
	        'title' => true,
	    ),
	    'b' => array(),
	    'code' => array(),
	    'del' => array(
	        'datetime' => true,
	    ),
	    'em' => array(),
	    'i' => array(),
	    'q' => array(
	        'cite' => true,
	    ),
	    'strike' => array(),
	    'strong' => array(),
	);

	public static function init() {
		if ( ! self::$initiated ) {
			self::init_hooks();
		}

		if ( isset( $_POST['action'] ) && $_POST['action'] == 'enter-key' ) {
			self::enter_api_key();
		}
	}

	public static function init_hooks() {
		// The standalone stats page was removed in 3.0 for an all-in-one config and stats page.
		// Redirect any links that might have been bookmarked or in browser history.
		if ( isset( $_GET['page'] ) && 'certly-stats-display' == $_GET['page'] ) {
			wp_safe_redirect( esc_url_raw( self::get_page_url( 'stats' ) ), 301 );
			die;
		}

		self::$initiated = true;

		add_action( 'admin_init', array( 'Certly_Admin', 'admin_init' ) );
		add_action( 'admin_menu', array( 'Certly_Admin', 'admin_menu' ), 5 ); # Priority 5, so it's called before Jetpack's admin_menu.
		add_action( 'admin_notices', array( 'Certly_Admin', 'display_notice' ) );
		add_action( 'admin_enqueue_scripts', array( 'Certly_Admin', 'load_resources' ) );
		add_action( 'activity_box_end', array( 'Certly_Admin', 'dashboard_stats' ) );
		add_action( 'rightnow_end', array( 'Certly_Admin', 'rightnow_stats' ) );
		add_action( 'manage_comments_nav', array( 'Certly_Admin', 'check_for_spam_button' ) );
		add_action( 'admin_action_certly_recheck_queue', array( 'Certly_Admin', 'recheck_queue' ) );
		add_action( 'wp_ajax_certly_recheck_queue', array( 'Certly_Admin', 'recheck_queue' ) );
		add_action( 'wp_ajax_comment_author_deurl', array( 'Certly_Admin', 'remove_comment_author_url' ) );
		add_action( 'wp_ajax_comment_author_reurl', array( 'Certly_Admin', 'add_comment_author_url' ) );
		add_action( 'jetpack_auto_activate_certly', array( 'Certly_Admin', 'connect_jetpack_user' ) );

		add_filter( 'plugin_action_links', array( 'Certly_Admin', 'plugin_action_links' ), 10, 2 );
		add_filter( 'comment_row_actions', array( 'Certly_Admin', 'comment_row_action' ), 10, 2 );

		add_filter( 'plugin_action_links_'.plugin_basename( plugin_dir_path( __FILE__ ) . 'certly.php'), array( 'Certly_Admin', 'admin_plugin_settings_link' ) );

		add_filter( 'wxr_export_skip_commentmeta', array( 'Certly_Admin', 'exclude_commentmeta_from_export' ), 10, 3 );
	}

	public static function admin_init() {
		load_plugin_textdomain( 'certly' );
		add_meta_box( 'certly-status', __('Certly Comment History', 'certly'), array( 'Certly_Admin', 'comment_status_meta_box' ), 'comment', 'normal' );
	}

	public static function admin_menu() {
		if ( class_exists( 'Jetpack' ) )
			add_action( 'jetpack_admin_menu', array( 'Certly_Admin', 'load_menu' ) );
		else
			self::load_menu();
	}

	public static function admin_head() {
		if ( !current_user_can( 'manage_options' ) )
			return;
	}

	public static function admin_plugin_settings_link( $links ) {
  		$settings_link = '<a href="'.esc_url( self::get_page_url() ).'">'.__('Settings', 'certly').'</a>';
  		array_unshift( $links, $settings_link );
  		return $links;
	}

	public static function load_menu() {
		$hook = add_options_page( __('Certly Guard', 'certly'), __('Certly Guard', 'certly'), 'manage_options', 'certly-key-config', array( 'Certly_Admin', 'display_page' ) );

		if ( version_compare( $GLOBALS['wp_version'], '3.3', '>=' ) ) {
			add_action( "load-$hook", array( 'Certly_Admin', 'admin_help' ) );
		}
	}

	public static function load_resources() {
		global $hook_suffix;

		if ( in_array( $hook_suffix, array(
			'index.php', # dashboard
			'edit-comments.php',
			'comment.php',
			'post.php',
			'settings_page_certly-key-config',
			'plugins.php',
		) ) ) {
			wp_register_style( 'certly.css', plugin_dir_url( __FILE__ ) . '_inc/certly.css', array(), CERTLY_VERSION );
			wp_enqueue_style( 'certly.css');

			wp_register_script( 'certly.js', plugin_dir_url( __FILE__ ) . '_inc/certly.js', array('jquery','postbox'), CERTLY_VERSION );
			wp_enqueue_script( 'certly.js' );
			wp_localize_script( 'certly.js', 'WPCertly', array(
				'comment_author_url_nonce' => wp_create_nonce( 'comment_author_url_nonce' ),
				'strings' => array(
					'Remove this URL' => __( 'Remove this URL' , 'certly'),
					'Removing...'     => __( 'Removing...' , 'certly'),
					'URL removed'     => __( 'URL removed' , 'certly'),
					'(undo)'          => __( '(undo)' , 'certly'),
					'Re-adding...'    => __( 'Re-adding...' , 'certly'),
				)
			) );
		}
	}

	/**
	 * Add help to the Certly page
	 *
	 * @return false if not the Certly page
	 */
	public static function admin_help() {
		$current_screen = get_current_screen();

		// Screen Content
		if ( current_user_can( 'manage_options' ) ) {
			if ( !Certly::get_api_key() || ( isset( $_GET['view'] ) && $_GET['view'] == 'start' ) ) {
				//setup page
				$current_screen->add_help_tab(
					array(
						'id'		=> 'overview',
						'title'		=> __( 'Overview' , 'certly'),
						'content'	=>
							'<p><strong>' . esc_html__( 'Certly Setup' , 'certly') . '</strong></p>' .
							'<p>' . esc_html__( 'Certly filters out spam, so you can focus on more important things.' , 'certly') . '</p>' .
							'<p>' . esc_html__( 'On this page, you are able to set up the Certly plugin.' , 'certly') . '</p>',
					)
				);

				$current_screen->add_help_tab(
					array(
						'id'		=> 'setup-signup',
						'title'		=> __( 'New to Certly' , 'certly'),
						'content'	=>
							'<p><strong>' . esc_html__( 'Certly Setup' , 'certly') . '</strong></p>' .
							'<p>' . esc_html__( 'You need to enter an API key to activate the Certly service on your site.' , 'certly') . '</p>' .
							'<p>' . sprintf( __( 'Sign up for an account on %s to get an API Key.' , 'certly'), '<a href="https://guard.certly.io" target="_blank">Certly</a>' ) . '</p>',
					)
				);

				$current_screen->add_help_tab(
					array(
						'id'		=> 'setup-manual',
						'title'		=> __( 'Enter an API Key' , 'certly'),
						'content'	=>
							'<p><strong>' . esc_html__( 'Certly Setup' , 'certly') . '</strong></p>' .
							'<p>' . esc_html__( 'If you already have an API key' , 'certly') . '</p>' .
							'<ol>' .
								'<li>' . esc_html__( 'Copy and paste the API key into the text field.' , 'certly') . '</li>' .
								'<li>' . esc_html__( 'Click the Use this Key button.' , 'certly') . '</li>' .
							'</ol>',
					)
				);
			}
			elseif ( isset( $_GET['view'] ) && $_GET['view'] == 'stats' ) {
				//stats page
				$current_screen->add_help_tab(
					array(
						'id'		=> 'overview',
						'title'		=> __( 'Overview' , 'certly'),
						'content'	=>
							'<p><strong>' . esc_html__( 'Certly Stats' , 'certly') . '</strong></p>' .
							'<p>' . esc_html__( 'Certly filters out spam, so you can focus on more important things.' , 'certly') . '</p>' .
							'<p>' . esc_html__( 'On this page, you are able to view stats on spam filtered on your site.' , 'certly') . '</p>',
					)
				);
			}
			else {
				//configuration page
				$current_screen->add_help_tab(
					array(
						'id'		=> 'overview',
						'title'		=> __( 'Overview' , 'certly'),
						'content'	=>
							'<p><strong>' . esc_html__( 'Certly Configuration' , 'certly') . '</strong></p>' .
							'<p>' . esc_html__( 'Certly automatically fiters comments with malicious links.' , 'certly') . '</p>' .
							'<p>' . esc_html__( 'On this page, you are able to enter/remove an API key and view your account information.' , 'certly') . '</p>',
					)
				);

				$current_screen->add_help_tab(
					array(
						'id'		=> 'settings',
						'title'		=> __( 'Settings' , 'certly'),
						'content'	=>
							'<p><strong>' . esc_html__( 'Certly Configuration' , 'certly') . '</strong></p>' .
							'<p><strong>' . esc_html__( 'API Key' , 'certly') . '</strong> - ' . esc_html__( 'Enter/remove an API key.' , 'certly') . '</p>' .
							'<p><strong>' . esc_html__( 'Comments' , 'certly') . '</strong> - ' . esc_html__( 'Show the number of approved comments beside each comment author in the comments list page.' , 'certly') . '</p>' .
							'<p><strong>' . esc_html__( 'Strictness' , 'certly') . '</strong> - ' . esc_html__( 'Choose to either discard flagged comments automatically or to always put flagged comments in the spam folder.' , 'certly') . '</p>',
					)
				);

				$current_screen->add_help_tab(
					array(
						'id'		=> 'account',
						'title'		=> __( 'Account' , 'certly'),
						'content'	=>
							'<p><strong>' . esc_html__( 'Certly Configuration' , 'certly') . '</strong></p>' .
							'<p><strong>' . esc_html__( 'Subscription Type' , 'certly') . '</strong> - ' . esc_html__( 'The Certly subscription plan' , 'certly') . '</p>' .
							'<p><strong>' . esc_html__( 'Status' , 'certly') . '</strong> - ' . esc_html__( 'The subscription status - active, cancelled or suspended' , 'certly') . '</p>',
					)
				);
			}
		}

		// Help Sidebar
		$current_screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'For more information:' , 'certly') . '</strong></p>' .
			'<p><a href="https://guard.certly.io/faq/" target="_blank">'     . esc_html__( 'Certly FAQ' , 'certly') . '</a></p>'
		);
	}

	public static function enter_api_key() {
		if ( function_exists('current_user_can') && !current_user_can('manage_options') )
			die(__('Cheatin&#8217; uh?', 'certly'));

		if ( !wp_verify_nonce( $_POST['_wpnonce'], self::NONCE ) )
			return false;

		foreach( array( 'certly_strictness', 'certly_show_user_comments_approved' ) as $option ) {
			update_option( $option, isset( $_POST[$option] ) && (int) $_POST[$option] == 1 ? '1' : '0' );
		}

		if ( defined( '' ) )
			return false; //shouldn't have option to save key if already defined

		$new_key = preg_replace( '/[^a-f0-9-]/i', '', $_POST['key'] );
		$old_key = Certly::get_api_key();

		if ( empty( $new_key ) ) {
			if ( !empty( $old_key ) ) {
				delete_option( 'certly_api_key' );
				self::$notices[] = 'new-key-empty';
			}
		}
		elseif ( $new_key != $old_key ) {
			self::save_key( $new_key );
		}

		return true;
	}

	public static function save_key( $api_key ) {
		$key_status = Certly::verify_key( $api_key );

		if ( $key_status == 'valid' ) {
			$certly_user = self::get_certly_user( $api_key );

			if ( $certly_user ) {
				if ( in_array( $certly_user->status, array( 'active', 'active-dunning', 'no-sub' ) ) )
					update_option( 'certly_api_key', $api_key );

				if ( $certly_user->status == 'active' )
					self::$notices['status'] = 'new-key-valid';
				elseif ( $certly_user->status == 'notice' )
					self::$notices['status'] = $certly_user;
				else
					self::$notices['status'] = $certly_user->status;
			}
			else
				self::$notices['status'] = 'new-key-invalid';
		}
		elseif ( in_array( $key_status, array( 'invalid', 'failed' ) ) )
			self::$notices['status'] = 'new-key-'.$key_status;
	}

	public static function dashboard_stats() {
		if ( !function_exists('did_action') || did_action( 'rightnow_end' ) )
			return; // We already displayed this info in the "Right Now" section

		if ( !$count = get_option('certly_spam_count') )
			return;

		global $submenu;

		echo '<h3>' . esc_html( _x( 'Spam', 'comments' , 'certly') ) . '</h3>';

		echo '<p>'.sprintf( _n(
				'<a href="%1$s">Certly</a> has protected your site from <a href="%2$s">%3$s spam comment</a>.',
				'<a href="%1$s">Certly</a> has protected your site from <a href="%2$s">%3$s spam comments</a>.',
				$count
			, 'certly'), 'https://certly.io/wordpress/', esc_url( add_query_arg( array( 'page' => 'certly-admin' ), admin_url( isset( $submenu['edit-comments.php'] ) ? 'edit-comments.php' : 'edit.php' ) ) ), number_format_i18n($count) ).'</p>';
	}

	// WP 2.5+
	public static function rightnow_stats() {
		if ( $count = get_option('certly_spam_count') ) {
			$intro = sprintf( _n(
				'<a href="%1$s">Certly</a> has protected your site from %2$s spam comment already. ',
				'<a href="%1$s">Certly</a> has protected your site from %2$s spam comments already. ',
				$count
			, 'certly'), 'https://certly.io/wordpress/', number_format_i18n( $count ) );
		} else {
			$intro = sprintf( __('<a href="%s">Certly</a> blocks spam from getting to your blog. ', 'certly'), 'https://certly.io/' );
		}

		$link = add_query_arg( array( 'comment_status' => 'spam' ), admin_url( 'edit-comments.php' ) );

		if ( $queue_count = self::get_spam_count() ) {
			$queue_text = sprintf( _n(
				'There&#8217;s <a href="%2$s">%1$s comment</a> in your spam queue right now.',
				'There are <a href="%2$s">%1$s comments</a> in your spam queue right now.',
				$queue_count
			, 'certly'), number_format_i18n( $queue_count ), esc_url( $link ) );
		} else {
			$queue_text = sprintf( __( "There&#8217;s nothing in your <a href='%s'>spam queue</a> at the moment." , 'certly'), esc_url( $link ) );
		}

		$text = $intro . '<br />' . $queue_text;
		echo "<p class='certly-right-now'>$text</p>\n";
	}

	public static function check_for_spam_button( $comment_status ) {
		// The "Check for Spam" button should only appear when the page might be showing
		// a comment with comment_approved=0, which means an un-trashed, un-spammed,
		// not-yet-moderated comment.
		if ( 'all' != $comment_status && 'moderated' != $comment_status ) {
			return;
		}

		if ( function_exists('plugins_url') )
			$link = add_query_arg( array( 'action' => 'certly_recheck_queue' ), admin_url( 'admin.php' ) );
		else
			$link = add_query_arg( array( 'page' => 'certly-admin', 'recheckqueue' => 'true', 'noheader' => 'true' ), admin_url( 'edit-comments.php' ) );

		echo '</div><div class="alignleft"><a class="button-secondary checkforspam" href="' . esc_url( $link ) . '">' . esc_html__('Check via Certly', 'certly') . '</a><span class="checkforspam-spinner"></span>';
	}

	public static function recheck_queue() {
		global $wpdb;

		Certly::fix_scheduled_recheck();

		if ( ! ( isset( $_GET['recheckqueue'] ) || ( isset( $_REQUEST['action'] ) && 'certly_recheck_queue' == $_REQUEST['action'] ) ) )
			return;

		$paginate = '';
		if ( isset( $_POST['limit'] ) && isset( $_POST['offset'] ) ) {
			$paginate = $wpdb->prepare( " LIMIT %d OFFSET %d", array( $_POST['limit'], $_POST['offset'] ) );
		}
		$moderation = $wpdb->get_results( "SELECT * FROM {$wpdb->comments} WHERE comment_approved = '0'{$paginate}", ARRAY_A );

		foreach ( (array) $moderation as $c ) {
			$c['raw'] = base64_encode($c['comment_content']);
			$c['simple']       = 'true';
			$c['user_ip']      = $c['comment_author_IP'];
			$c['user_agent']   = $c['comment_agent'];
			$c['referrer']     = '';
			$c['blog']         = get_bloginfo('url');
			$c['blog_lang']    = get_locale();
			$c['blog_charset'] = get_option('blog_charset');
			$c['permalink']    = get_permalink($c['comment_post_ID']);

			$c['user_role'] = '';
			if ( isset( $c['user_ID'] ) )
				$c['user_role'] = Certly::get_user_roles($c['user_ID']);

			if ( Certly::is_test_mode() )
				$c['is_test'] = 'true';

			add_comment_meta( $c['comment_ID'], 'certly_rechecking', true );

			$response = Certly::http_post( Certly::build_query( $c ), 'lookup' );

			if ( 'true' == $response[1] ) {
				wp_set_comment_status( $c['comment_ID'], 'spam' );
				update_comment_meta( $c['comment_ID'], 'certly_result', 'true' );
				delete_comment_meta( $c['comment_ID'], 'certly_error' );
				delete_comment_meta( $c['comment_ID'], 'certly_delayed_moderation_email' );
				Certly::update_comment_history( $c['comment_ID'], '', 'recheck-spam' );

			} elseif ( 'false' == $response[1] ) {
				update_comment_meta( $c['comment_ID'], 'certly_result', 'false' );
				delete_comment_meta( $c['comment_ID'], 'certly_error' );
				delete_comment_meta( $c['comment_ID'], 'certly_delayed_moderation_email' );
				Certly::update_comment_history( $c['comment_ID'], '', 'recheck-ham' );
			// abnormal result: error
			} else {
				update_comment_meta( $c['comment_ID'], 'certly_result', 'error' );
				Certly::update_comment_history(
					$c['comment_ID'],
					'',
					'recheck-error',
					array( 'response' => substr( $response[1], 0, 50 ) )
				);
			}

			delete_comment_meta( $c['comment_ID'], 'certly_rechecking' );
		}
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			wp_send_json( array(
				'processed' => count((array) $moderation),
			));
		}
		else {
			$redirect_to = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : admin_url( 'edit-comments.php' );
			wp_safe_redirect( $redirect_to );
			exit;
		}
	}

	// Adds an 'x' link next to author URLs, clicking will remove the author URL and show an undo link
	public static function remove_comment_author_url() {
		if ( !empty( $_POST['id'] ) && check_admin_referer( 'comment_author_url_nonce' ) ) {
			$comment = get_comment( intval( $_POST['id'] ), ARRAY_A );
			if ( $comment && current_user_can( 'edit_comment', $comment['comment_ID'] ) ) {
				$comment['comment_author_url'] = '';
				do_action( 'comment_remove_author_url' );
				print( wp_update_comment( $comment ) );
				die();
			}
		}
	}

	public static function add_comment_author_url() {
		if ( !empty( $_POST['id'] ) && !empty( $_POST['url'] ) && check_admin_referer( 'comment_author_url_nonce' ) ) {
			$comment = get_comment( intval( $_POST['id'] ), ARRAY_A );
			if ( $comment && current_user_can( 'edit_comment', $comment['comment_ID'] ) ) {
				$comment['comment_author_url'] = esc_url( $_POST['url'] );
				do_action( 'comment_add_author_url' );
				print( wp_update_comment( $comment ) );
				die();
			}
		}
	}

	public static function comment_row_action( $a, $comment ) {

		// failsafe for old WP versions
		if ( !function_exists('add_comment_meta') )
			return $a;

		$certly_result = get_comment_meta( $comment->comment_ID, 'certly_result', true );
		$certly_error  = get_comment_meta( $comment->comment_ID, 'certly_error', true );
		$user_result    = get_comment_meta( $comment->comment_ID, 'certly_user_result', true);
		$comment_status = wp_get_comment_status( $comment->comment_ID );
		$desc = null;
		if ( $certly_error ) {
			$desc = __( 'Awaiting spam check' , 'certly');
		} elseif ( !$user_result || $user_result == $certly_result ) {
			// Show the original Certly result if the user hasn't overridden it, or if their decision was the same
			if ( $certly_result == 'true' && $comment_status != 'spam' && $comment_status != 'trash' )
				$desc = __( 'Flagged by Certly' , 'certly');
			elseif ( $certly_result == 'false' && $comment_status == 'spam' )
				$desc = __( 'Cleared by Certly' , 'certly');
		} else {
			$who = get_comment_meta( $comment->comment_ID, 'certly_user', true );
			if ( $user_result == 'true' )
				$desc = sprintf( __('Flagged as spam by %s', 'certly'), $who );
			else
				$desc = sprintf( __('Un-spammed by %s', 'certly'), $who );
		}

		// add a History item to the hover links, just after Edit
		if ( $certly_result ) {
			$b = array();
			foreach ( $a as $k => $item ) {
				$b[ $k ] = $item;
				if (
					$k == 'edit'
					|| ( $k == 'unspam' && $GLOBALS['wp_version'] >= 3.4 )
				) {
					$b['history'] = '<a href="comment.php?action=editcomment&amp;c='.$comment->comment_ID.'#certly-status" title="'. esc_attr__( 'View Certly comment history' , 'certly') . '"> '. esc_html__('History', 'certly') . '</a>';
				}
			}

			$a = $b;
		}

		if ( $desc && !is_plugin_active('akismet') )
			echo '<span class="certly-status" commentid="'.$comment->comment_ID.'"><a href="comment.php?action=editcomment&amp;c='.$comment->comment_ID.'#certly-status" title="' . esc_attr__( 'View Certly comment history' , 'certly') . '">'.esc_html( $desc ).'</a></span>';

		$show_user_comments = apply_filters( 'certly_show_user_comments_approved', get_option('certly_show_user_comments_approved') );
		$show_user_comments = $show_user_comments === 'false' ? false : $show_user_comments; //option used to be saved as 'false' / 'true'

		if ( $show_user_comments ) {
			$comment_count = Certly::get_user_comments_approved( $comment->user_id, $comment->comment_author_email, $comment->comment_author, $comment->comment_author_url );
			$comment_count = intval( $comment_count );
			echo '<span class="certly-user-comment-count" commentid="'.$comment->comment_ID.'" style="display:none;"><br><span class="certly-user-comment-counts">'. sprintf( esc_html( _n( '%s approved', '%s approved', $comment_count , 'certly') ), number_format_i18n( $comment_count ) ) . '</span></span>';
		}

		return $a;
	}

	public static function comment_status_meta_box( $comment ) {
		$history = Certly::get_comment_history( $comment->comment_ID );

		if ( $history ) {
			echo '<div class="certly-history" style="margin: 13px;">';

			foreach ( $history as $row ) {
				$time = date( 'D d M Y @ h:i:m a', $row['time'] ) . ' GMT';

				$message = '';

				if ( ! empty( $row['message'] ) ) {
					// Old versions of Certly stored the message as a literal string in the commentmeta.
					// New versions don't do that for two reasons:
					// 1) Save space.
					// 2) The message can be translated into the current language of the blog, not stuck
					//    in the language of the blog when the comment was made.
					$message = $row['message'];
				}

				// If possible, use a current translation.
				switch ( $row['event'] ) {
					case 'recheck-spam';
						$message = __( 'Certly re-checked and caught this comment as spam.', 'certly' );
					break;
					case 'check-spam':
						$message = __( 'Certly caught this comment as spam.', 'certly' );
					break;
					case 'recheck-ham':
						$message = __( 'Certly re-checked and cleared this comment.', 'certly' );
					break;
					case 'check-ham':
						$message = __( 'Certly cleared this comment.', 'certly' );
					break;
					case 'wp-blacklisted':
						$message = __( 'Comment was caught by wp_blacklist_check.', 'certly' );
					break;
					case 'report-spam':
						if ( isset( $row['user'] ) ) {
							$message = sprintf( __( '%s reported this comment as spam.', 'certly' ), $row['user'] );
						}
						else if ( ! $message ) {
							$message = __( 'This comment was reported as spam.', 'certly' );
						}
					break;
					case 'report-ham':
						if ( isset( $row['user'] ) ) {
							$message = sprintf( __( '%s reported this comment as not spam.', 'certly' ), $row['user'] );
						}
						else if ( ! $message ) {
							$message = __( 'This comment was reported as not spam.', 'certly' );
						}
					break;
					case 'cron-retry-spam':
						$message = __( 'Certly caught this comment as spam during an automatic retry.' , 'certly');
					break;
					case 'cron-retry-ham':
						$message = __( 'Certly cleared this comment during an automatic retry.', 'certly');
					break;
					case 'check-error':
						if ( isset( $row['meta'], $row['meta']['response'] ) ) {
							$message = sprintf( __( 'Certly was unable to check this comment (response: %s) but will automatically retry later.', 'certly'), $row['meta']['response'] );
						}
					break;
					case 'recheck-error':
						if ( isset( $row['meta'], $row['meta']['response'] ) ) {
							$message = sprintf( __( 'Certly was unable to recheck this comment (response: %s).', 'certly'), $row['meta']['response'] );
						}
					break;
					default:
						if ( preg_match( '/^status-changed/', $row['event'] ) ) {
							// Half of these used to be saved without the dash after 'status-changed'.
							// See https://plugins.trac.wordpress.org/changeset/1150658/certly/trunk
							$new_status = preg_replace( '/^status-changed-?/', '', $row['event'] );
							$message = sprintf( __( 'Comment status was changed to %s', 'certly' ), $new_status );
						}
						else if ( preg_match( '/^status-/', $row['event'] ) ) {
							$new_status = preg_replace( '/^status-/', '', $row['event'] );

							if ( isset( $row['user'] ) ) {
								$message = sprintf( __( '%1$s changed the comment status to %2$s.', 'certly' ), $row['user'], $new_status );
							}
						}
					break;

				}

				echo '<div style="margin-bottom: 13px;">';
					echo '<span style="color: #999;" alt="' . $time . '" title="' . $time . '">' . sprintf( esc_html__('%s ago', 'certly'), human_time_diff( $row['time'] ) ) . '</span>';
					echo ' - ';
					echo esc_html( $message );
				echo '</div>';
			}

			echo '</div>';
		}
	}

	public static function plugin_action_links( $links, $file ) {
		if ( $file == plugin_basename( plugin_dir_url( __FILE__ ) . '/certly.php' ) ) {
			$links[] = '<a href="' . esc_url( self::get_page_url() ) . '">'.esc_html__( 'Settings' , 'certly').'</a>';
		}

		return $links;
	}

	// Total spam in queue
	// get_option( 'certly_spam_count' ) is the total caught ever
	public static function get_spam_count( $type = false ) {
		global $wpdb;

		if ( !$type ) { // total
			$count = wp_cache_get( 'certly_spam_count', 'widget' );
			if ( false === $count ) {
				if ( function_exists('wp_count_comments') ) {
					$count = wp_count_comments();
					$count = $count->spam;
				} else {
					$count = (int) $wpdb->get_var("SELECT COUNT(comment_ID) FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
				}
				wp_cache_set( 'certly_spam_count', $count, 'widget', 3600 );
			}
			return $count;
		} elseif ( 'comments' == $type || 'comment' == $type ) { // comments
			$type = '';
		}

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(comment_ID) FROM {$wpdb->comments} WHERE comment_approved = 'spam' AND comment_type = %s", $type ) );
	}

	// Check connectivity between the WordPress blog and Certly's servers.
	// Returns an associative array of server IP addresses, where the key is the IP address, and value is true (available) or false (unable to connect).
	public static function check_server_ip_connectivity() {

		$servers = $ips = array();

		// Some web hosts may disable this function
		if ( function_exists('gethostbynamel') ) {

			$ips = gethostbynamel( 'guard.certly.io' );
			if ( $ips && is_array($ips) && count($ips) ) {
				$api_key = Certly::get_api_key();

				foreach ( $ips as $ip ) {
					$response = Certly::verify_key( $api_key, $ip );
					// even if the key is invalid, at least we know we have connectivity
					if ( $response == 'valid' || $response == 'invalid' )
						$servers[$ip] = 'connected';
					else
						$servers[$ip] = $response ? $response : 'unable to connect';
				}
			}
		}

		return $servers;
	}

	// Simpler connectivity check
	public static function check_server_connectivity($cache_timeout = 86400) {

		$debug = array();
		$debug[ 'PHP_VERSION' ]         = PHP_VERSION;
		$debug[ 'WORDPRESS_VERSION' ]   = $GLOBALS['wp_version'];
		$debug[ 'CERTLY_VERSION' ]     = CERTLY_VERSION;
		$debug[ 'CERTLY__PLUGIN_DIR' ] = CERTLY__PLUGIN_DIR;
		$debug[ 'SITE_URL' ]            = site_url();
		$debug[ 'HOME_URL' ]            = home_url();

		$servers = get_option('certly_available_servers');
		if ( (time() - get_option('certly_connectivity_time') < $cache_timeout) && $servers !== false ) {
			$servers = self::check_server_ip_connectivity();
			update_option('certly_available_servers', $servers);
			update_option('certly_connectivity_time', time());
		}

		$response = wp_remote_get( 'http://guard.certly.io/api/v1/test' );

		$debug[ 'gethostbynamel' ]  = function_exists('gethostbynamel') ? 'exists' : 'not here';
		$debug[ 'Servers' ]         = $servers;
		$debug[ 'Test Connection' ] = $response;

		Certly::log( $debug );

		if ( $response && 'OK' == wp_remote_retrieve_body( $response ) )
			return true;

		return false;
	}

	// Check the server connectivity and store the available servers in an option.
	public static function get_server_connectivity($cache_timeout = 86400) {
		return self::check_server_connectivity( $cache_timeout );
	}

	public static function get_number_spam_waiting() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE meta_key = 'certly_error'" );
	}

	public static function get_page_url( $page = 'config' ) {

		$args = array( 'page' => 'certly-key-config' );

		if ( $page == 'stats' )
			$args = array( 'page' => 'certly-key-config', 'view' => 'stats' );
		elseif ( $page == 'delete_key' )
			$args = array( 'page' => 'certly-key-config', 'view' => 'start', 'action' => 'delete-key', '_wpnonce' => wp_create_nonce( self::NONCE ) );

		$url = add_query_arg( $args, class_exists( 'Jetpack' ) ? admin_url( 'admin.php' ) : admin_url( 'options-general.php' ) );

		return $url;
	}

	public static function get_certly_user( $api_key ) {
		$certly_user = false;

		$subscription_verification = Certly::http_post( Certly::build_query( array( 'key' => $api_key, 'blog' => get_bloginfo( 'url' ) ) ), 'key/user' );

		if ( ! empty( $subscription_verification[1] ) ) {
			if ( 'invalid' !== $subscription_verification[1] ) {
				$certly_user = json_decode( $subscription_verification[1] );
			}
		}

		return $certly_user;
	}

	public static function get_stats( $api_key ) {
		$stat_totals = array();

		foreach( array( '6-months', 'all' ) as $interval ) {
			$response = Certly::http_post( Certly::build_query( array( 'blog' => get_bloginfo( 'url' ), 'key' => $api_key, 'from' => $interval ) ), 'get-stats' );

			if ( ! empty( $response[1] ) ) {
				$stat_totals[$interval] = json_decode( $response[1] );
			}
		}

		return $stat_totals;
	}

	public static function verify_wpcom_key( $api_key, $user_id, $extra = array() ) {
		$certly_account = Certly::http_post( Certly::build_query( array_merge( array(
			'user_id'          => $user_id,
			'api_key'          => $api_key,
			'get_account_type' => 'true'
		), $extra ) ), 'verify-wpcom-key' );

		if ( ! empty( $certly_account[1] ) )
			$certly_account = json_decode( $certly_account[1] );

		Certly::log( compact( 'certly_account' ) );

		return $certly_account;
	}

	public static function connect_jetpack_user() {
		return false;
	}

	public static function display_alert() {
		Certly::view( 'notice', array(
			'type' => 'alert',
			'code' => (int) get_option( 'certly_alert_code' ),
			'msg'  => get_option( 'certly_alert_msg' )
		) );
	}

	public static function display_spam_check_warning() {
		Certly::fix_scheduled_recheck();

		if ( wp_next_scheduled('certly_schedule_cron_recheck') > time() && self::get_number_spam_waiting() > 0 ) {
			$link_text = apply_filters( 'certly_spam_check_warning_link_text', sprintf( __( 'Please check your <a href="%s">Certly configuration</a> and contact your web host if problems persist.', 'certly'), esc_url( self::get_page_url() ) ) );
			Certly::view( 'notice', array( 'type' => 'spam-check', 'link_text' => $link_text ) );
		}
	}

	public static function display_invalid_version() {
		Certly::view( 'notice', array( 'type' => 'version' ) );
	}

	public static function display_api_key_warning() {
		Certly::view( 'notice', array( 'type' => 'plugin' ) );
	}

	public static function display_page() {
		if ( !Certly::get_api_key() || ( isset( $_GET['view'] ) && $_GET['view'] == 'start' ) )
			self::display_start_page();
		elseif ( isset( $_GET['view'] ) && $_GET['view'] == 'stats' )
			self::display_stats_page();
		else
			self::display_configuration_page();
	}

	public static function display_start_page() {
		if ( isset( $_GET['action'] ) ) {
			if ( $_GET['action'] == 'delete-key' ) {
				if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], self::NONCE ) )
					delete_option( 'certly_api_key' );
			}
		}

		if ( $api_key = Certly::get_api_key() && ( empty( self::$notices['status'] ) || 'existing-key-invalid' != self::$notices['status'] ) ) {
			self::display_configuration_page();
			return;
		}

		//the user can choose to auto connect their API key by clicking a button on the certly done page
		//if jetpack, get verified api key by using connected wpcom user id
		//if no jetpack, get verified api key by using an certly token

		$certly_user = false;

		if ( isset( $_GET['token'] ) && preg_match('/^(\d+)-[0-9a-f]{20}$/', $_GET['token'] ) )
			$certly_user = self::verify_wpcom_key( '', '', array( 'token' => $_GET['token'] ) );

		if ( isset( $_GET['action'] ) ) {
			if ( $_GET['action'] == 'save-key' ) {
				if ( is_object( $certly_user ) ) {
					self::save_key( $certly_user->api_key );
					self::display_notice();
					self::display_configuration_page();
					return;
				}
			}
		}

		self::display_status();

		Certly::view( 'start', compact( 'certly_user' ) );
	}

	public static function display_stats_page() {
		Certly::view( 'stats' );
	}

	public static function display_configuration_page() {
		$api_key      = Certly::get_api_key();
		$certly_user = self::get_certly_user( $api_key );

		if ( ! $certly_user ) {
			// This could happen if the user's key became invalid after it was previously valid and successfully set up.
			self::$notices['status'] = 'existing-key-invalid';
			self::display_start_page();
			return;
		}

		$stat_totals  = self::get_stats( $api_key );

		// If unset, create the new strictness option using the old discard option to determine its default
       	if ( get_option( 'certly_strictness' ) === false )
        	add_option( 'certly_strictness', (get_option('certly_discard_month') === 'true' ? '1' : '0') );

		if ( empty( self::$notices ) ) {
			//show status
			if ( ! empty( $stat_totals['all'] ) && isset( $stat_totals['all']->time_saved ) && $certly_user->status == 'active' && $certly_user->account_type == 'free-api-key' ) {

				$time_saved = false;

				if ( $stat_totals['all']->time_saved > 1800 ) {
					$total_in_minutes = round( $stat_totals['all']->time_saved / 60 );
					$total_in_hours   = round( $total_in_minutes / 60 );
					$total_in_days    = round( $total_in_hours / 8 );
					$cleaning_up      = __( 'Cleaning up spam takes time.' , 'certly');

					if ( $total_in_days > 1 )
						$time_saved = $cleaning_up . ' ' . sprintf( _n( 'Certly has saved you %s day!', 'Certly has saved you %s days!', $total_in_days, 'certly' ), number_format_i18n( $total_in_days ) );
					elseif ( $total_in_hours > 1 )
						$time_saved = $cleaning_up . ' ' . sprintf( _n( 'Certly has saved you %d hour!', 'Certly has saved you %d hours!', $total_in_hours, 'certly' ), $total_in_hours );
					elseif ( $total_in_minutes >= 30 )
						$time_saved = $cleaning_up . ' ' . sprintf( _n( 'Certly has saved you %d minute!', 'Certly has saved you %d minutes!', $total_in_minutes, 'certly' ), $total_in_minutes );
				}

				Certly::view( 'notice', array( 'type' => 'active-notice', 'time_saved' => $time_saved ) );
			}

			if ( !empty( $certly_user->limit_reached ) && in_array( $certly_user->limit_reached, array( 'yellow', 'red' ) ) ) {
				Certly::view( 'notice', array( 'type' => 'limit-reached', 'level' => $certly_user->limit_reached ) );
			}
		}

		if ( !isset( self::$notices['status'] ) && in_array( $certly_user->status, array( 'cancelled', 'suspended', 'missing', 'no-sub' ) ) )
			Certly::view( 'notice', array( 'type' => $certly_user->status ) );

		Certly::log( compact( 'stat_totals', 'certly_user' ) );
		Certly::view( 'config', compact( 'api_key', 'certly_user', 'stat_totals' ) );
	}

	public static function display_notice() {
		global $hook_suffix;

		if ( in_array( $hook_suffix, array( 'jetpack_page_certly-key-config', 'settings_page_certly-key-config', 'edit-comments.php' ) ) && (int) get_option( 'certly_alert_code' ) > 0 ) {
			Certly::verify_key( Certly::get_api_key() ); //verify that the key is still in alert state

			if ( get_option( 'certly_alert_code' ) > 0 )
				self::display_alert();
		}
		elseif ( $hook_suffix == 'plugins.php' && !Certly::get_api_key() ) {
			self::display_api_key_warning();
		}
		elseif ( $hook_suffix == 'edit-comments.php' && wp_next_scheduled( 'certly_schedule_cron_recheck' ) ) {
			self::display_spam_check_warning();
		}
		elseif ( in_array( $hook_suffix, array( 'jetpack_page_certly-key-config', 'settings_page_certly-key-config' ) ) && Certly::get_api_key() ) {
			self::display_status();
		}
	}

	public static function display_status() {
		$type = '';

		if ( !self::get_server_connectivity() )
			$type = 'servers-be-down';

		if ( !empty( $type ) )
			Certly::view( 'notice', compact( 'type' ) );
		elseif ( !empty( self::$notices ) ) {
			foreach ( self::$notices as $type ) {
				if ( is_object( $type ) ) {
					$notice_header = $notice_text = '';

					if ( property_exists( $type, 'notice_header' ) )
						$notice_header = wp_kses( $type->notice_header, self::$allowed );

					if ( property_exists( $type, 'notice_text' ) )
						$notice_text = wp_kses( $type->notice_text, self::$allowed );

					if ( property_exists( $type, 'status' ) ) {
						$type = wp_kses( $type->status, self::$allowed );
						Certly::view( 'notice', compact( 'type', 'notice_header', 'notice_text' ) );
					}
				}
				else
					Certly::view( 'notice', compact( 'type' ) );
			}
		}
	}

	private static function get_jetpack_user() {
		if ( !class_exists('Jetpack') )
			return false;

		Jetpack::load_xml_rpc_client();
		$xml = new Jetpack_IXR_ClientMulticall( array( 'user_id' => get_current_user_id() ) );

		$xml->addCall( 'wpcom.getUserID' );
		$xml->addCall( 'certly.getAPIKey' );
		$xml->query();

		Certly::log( compact( 'xml' ) );

		if ( !$xml->isError() ) {
			$responses = $xml->getResponse();
			if ( count( $responses ) > 1 ) {
				$api_key = array_shift( $responses[0] );
				$user_id = (int) array_shift( $responses[1] );
				return compact( 'api_key', 'user_id' );
			}
		}
		return false;
	}

	/**
	 * Some commentmeta isn't useful in an export file. Suppress it (when supported).
	 *
	 * @param bool $exclude
	 * @param string $key The meta key
	 * @param object $meta The meta object
	 * @return bool Whether to exclude this meta entry from the export.
	 */
	public static function exclude_commentmeta_from_export( $exclude, $key, $meta ) {
		if ( in_array( $key, array( 'certly_as_submitted', 'certly_rechecking', 'certly_delayed_moderation_email' ) ) ) {
			return true;
		}

		return $exclude;
	}
}
