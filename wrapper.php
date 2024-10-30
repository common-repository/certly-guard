<?php

global $wpcom_api_key, $certly_api_host, $certly_api_port;

$wpcom_api_key    = defined( 'CERTLY_API_KEY' ) ? constant( 'CERTLY_API_KEY' ) : '';
$certly_api_host = 'guard.certly.io';
$certly_api_port = 443;

function certly_test_mode() {
	return Certly::is_test_mode();
}

function certly_http_post( $request, $host, $path, $port = 80, $ip = null ) {
	$path = str_replace( '/1.1/', '', $path );

	return Certly::http_post( $request, $path, $ip );
}

function certly_microtime() {
	return Certly::_get_microtime();
}

function certly_delete_old() {
	return Certly::delete_old_comments();
}

function certly_delete_old_metadata() {
	return Certly::delete_old_comments_meta();
}

function certly_check_db_comment( $id, $recheck_reason = 'recheck_queue' ) {
   	return Certly::check_db_comment( $id, $recheck_reason );
}

function certly_rightnow() {
	if ( !class_exists( 'Certly_Admin' ) )
		return false;

   	return Certly_Admin::rightnow_stats();
}

function certly_admin_init() {
	_deprecated_function( __FUNCTION__, '3.0' );
}
function certly_version_warning() {
	_deprecated_function( __FUNCTION__, '3.0' );
}
function certly_load_js_and_css() {
	_deprecated_function( __FUNCTION__, '3.0' );
}
function certly_nonce_field( $action = -1 ) {
	return wp_nonce_field( $action );
}
function certly_plugin_action_links( $links, $file ) {
	return Certly_Admin::plugin_action_links( $links, $file );
}
function certly_conf() {
	_deprecated_function( __FUNCTION__, '3.0' );
}
function certly_stats_display() {
	_deprecated_function( __FUNCTION__, '3.0' );
}
function certly_stats() {
	return Certly_Admin::dashboard_stats();
}
function certly_admin_warnings() {
	_deprecated_function( __FUNCTION__, '3.0' );
}
function certly_comment_row_action( $a, $comment ) {
	return Certly_Admin::comment_row_actions( $a, $comment );
}
function certly_comment_status_meta_box( $comment ) {
	return Certly_Admin::comment_status_meta_box( $comment );
}
function certly_comments_columns( $columns ) {
	_deprecated_function( __FUNCTION__, '3.0' );

	return $columns;
}
function certly_comment_column_row( $column, $comment_id ) {
	_deprecated_function( __FUNCTION__, '3.0' );
}
function certly_text_add_link_callback( $m ) {
	return Certly_Admin::text_add_link_callback( $m );
}
function certly_text_add_link_class( $comment_text ) {
	return Certly_Admin::text_add_link_class( $comment_text );
}
function certly_check_for_spam_button( $comment_status ) {
	return Certly_Admin::check_for_spam_button( $comment_status );
}
function certly_submit_nonspam_comment( $comment_id ) {
	return Certly::submit_nonspam_comment( $comment_id );
}
function certly_submit_spam_comment( $comment_id ) {
	return Certly::submit_spam_comment( $comment_id );
}
function certly_transition_comment_status( $new_status, $old_status, $comment ) {
	return Certly::transition_comment_status( $new_status, $old_status, $comment );
}
function certly_spam_count( $type = false ) {
	return Certly_Admin::get_spam_count( $type );
}
function certly_recheck_queue() {
	return Certly_Admin::recheck_queue();
}
function certly_remove_comment_author_url() {
	return Certly_Admin::remove_comment_author_url();
}
function certly_add_comment_author_url() {
	return Certly_Admin::add_comment_author_url();
}
function certly_check_server_connectivity() {
	return Certly_Admin::check_server_connectivity();
}
function certly_get_server_connectivity( $cache_timeout = 86400 ) {
	return Certly_Admin::get_server_connectivity( $cache_timeout );
}
function certly_server_connectivity_ok() {
	_deprecated_function( __FUNCTION__, '3.0' );

	return true;
}
function certly_admin_menu() {
	return Certly_Admin::admin_menu();
}
function certly_load_menu() {
	return Certly_Admin::load_menu();
}
function certly_init() {
	_deprecated_function( __FUNCTION__, '3.0' );
}
function certly_get_key() {
	return Certly::get_api_key();
}
function certly_check_key_status( $key, $ip = null ) {
	return Certly::check_key_status( $key, $ip );
}
function certly_update_alert( $response ) {
	return Certly::update_alert( $response );
}
function certly_verify_key( $key, $ip = null ) {
	return Certly::verify_key( $key, $ip );
}
function certly_get_user_roles( $user_id ) {
	return Certly::get_user_roles( $user_id );
}
function certly_result_spam( $approved ) {
	return Certly::comment_is_spam( $approved );
}
function certly_result_hold( $approved ) {
	return Certly::comment_needs_moderation( $approved );
}
function certly_get_user_comments_approved( $user_id, $comment_author_email, $comment_author, $comment_author_url ) {
	return Certly::get_user_comments_approved( $user_id, $comment_author_email, $comment_author, $comment_author_url );
}
function certly_update_comment_history( $comment_id, $message, $event = null ) {
	return Certly::update_comment_history( $comment_id, $message, $event );
}
function certly_get_comment_history( $comment_id ) {
	return Certly::get_comment_history( $comment_id );
}
function certly_cmp_time( $a, $b ) {
	return Certly::_cmp_time( $a, $b );
}
function certly_auto_check_update_meta( $id, $comment ) {
	return Certly::auto_check_update_meta( $id, $comment );
}
function certly_auto_check_comment( $commentdata ) {
	return Certly::auto_check_comment( $commentdata );
}
function certly_get_ip_address() {
	return Certly::get_ip_address();
}
function certly_cron_recheck() {
	return Certly::cron_recheck();
}
function certly_add_comment_nonce() {
	return Certly::add_comment_nonce( $post_id );
}
function certly_fix_scheduled_recheck() {
	return Certly::fix_scheduled_recheck();
}
function certly_spam_comments() {
	_deprecated_function( __FUNCTION__, '3.0' );

	return array();
}
function certly_spam_totals() {
	_deprecated_function( __FUNCTION__, '3.0' );

	return array();
}
function certly_manage_page() {
	_deprecated_function( __FUNCTION__, '3.0' );
}
function certly_caught() {
	_deprecated_function( __FUNCTION__, '3.0' );
}
function redirect_old_certly_urls() {
	_deprecated_function( __FUNCTION__, '3.0' );
}
function certly_kill_proxy_check( $option ) {
	_deprecated_function( __FUNCTION__, '3.0' );

	return 0;
}
function certly_pingback_forwarded_for( $r, $url ) {
	return Certly::pingback_forwarded_for( $r, $url );
}
function certly_pre_check_pingback( $method ) {
	return Certly::pre_check_pingback( $method );
}
