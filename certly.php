<?php
/**
 * @package Certly
 */
/*
Plugin Name: Certly Guard
Plugin URI: https://certly.io
Description: Certly Guard effortlessly discards comments that contain links to malware, spam, or phishing. Sign up for a free API key at <a href="https://guard.certly.io">guard.certly.io</a> to get started.
Version: 1.0.0
Author: Certly
Author URI: https://certly.io
License: GPLv2 or later
Text Domain: certly
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

Copyright 2005-2015 Automattic, Inc.
Copyright 2016 Certly, Inc.
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define( 'CERTLY_VERSION', '3.1.10' );
define( 'CERTLY__MINIMUM_WP_VERSION', '3.2' );
define( 'CERTLY__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CERTLY_DELETE_LIMIT', 100000 );

register_activation_hook( __FILE__, array( 'Certly', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'Certly', 'plugin_deactivation' ) );

require_once( CERTLY__PLUGIN_DIR . 'class.certly.php' );
require_once( CERTLY__PLUGIN_DIR . 'class.certly-widget.php' );

add_action( 'init', array( 'Certly', 'init' ) );

if ( is_admin() ) {
	require_once( CERTLY__PLUGIN_DIR . 'class.certly-admin.php' );
	add_action( 'init', array( 'Certly_Admin', 'init' ) );
}

//add wrapper class around deprecated certly functions that are referenced elsewhere
require_once( CERTLY__PLUGIN_DIR . 'wrapper.php' );
