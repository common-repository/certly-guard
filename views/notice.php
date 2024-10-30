<?php if ( $type == 'plugin' ) :?>
<div id="message" class="updated notice">
	<p>You're almost done - <a href="<?php echo esc_url( Certly_Admin::get_page_url() ); ?>">activate Certly Guard</a> to start blocking malware and phishing links.</p>
</div>
<?php elseif ( $type == 'spam-check' ) :?>
<div id="certly-warning" class="updated fade">
	<p><strong><?php esc_html_e( 'Certly has detected a problem.', 'certly' );?></strong></p>
	<p><?php printf( __( 'Some comments have not yet been checked by Certly Guard. They have been temporarily held for moderation and will automatically be rechecked later.', 'certly' ) ); ?></p>
	<?php if ( $link_text ) { ?>
		<p><?php echo $link_text; ?></p>
	<?php } ?>
</div>
<?php elseif ( $type == 'version' ) :?>
<div id="certly-warning" class="updated fade"><p><strong><?php printf( esc_html__('Certly %s requires WordPress 3.0 or higher.', 'certly'), AKISMET_VERSION);?></strong> <?php printf(__('Please <a href="%1$s">upgrade WordPress</a> to a current version in order to use Certly Guard.', 'certly'), 'https://codex.wordpress.org/Upgrading_WordPress', 'https://wordpress.org/extend/plugins/certly/download/');?></p></div>
<?php elseif ( $type == 'alert' ) :?>
<div class='error'>
	<p><strong><?php printf( esc_html__( 'Certly Error Code: %s', 'certly' ), $code ); ?></strong></p>
	<p><?php echo esc_html( $msg ); ?></p>
	<p><?php

	/* translators: the placeholder is a clickable URL that leads to more information regarding an error code. */
	printf( esc_html__( 'For more information: %s' , 'certly'), '<a href="https://guard.certly.io/error/' . $code . '">https://guard.certly.io./error/' . $code . '</a>' );

	?>
	</p>
</div>
<?php elseif ( $type == 'notice' ) :?>
<div class="wrap alert critical">
	<h3 class="key-status failed"><?php echo $notice_header; ?></h3>
	<p class="description">
		<?php echo $notice_text; ?>
	</p>
</div>
<?php elseif ( $type == 'missing-functions' ) :?>
<div class="wrap alert critical">
	<h3 class="key-status failed"><?php esc_html_e('Network functions are disabled.', 'certly'); ?></h3>
	<p class="description"><?php printf( __('Your web host or server administrator has disabled PHP&#8217;s <code>gethostbynamel</code> function.  <strong>Certly cannot work correctly until this is fixed.</strong>  Please contact your web host or firewall administrator.', 'certly')); ?></p>
</div>
<?php elseif ( $type == 'servers-be-down' ) :?>
<div class="wrap alert critical">
	<h3 class="key-status failed"><?php esc_html_e("Certly can&#8217;t connect to your site.", 'certly'); ?></h3>
	<p class="description"><?php printf( __('Your firewall may be blocking Certly. Please contact your host and ensure port 443 is open.', 'certly')); ?></p>
</div>
<?php elseif ( $type == 'active-dunning' ) :?>
<div class="wrap alert critical">
	<h3 class="key-status"><?php esc_html_e("Please update your payment information.", 'certly'); ?></h3>
	<p class="description"><?php printf( __('We cannot process your payment. Please <a href="%s" target="_blank">update your payment details</a>.', 'certly'), 'https://guard.certly.io/settings'); ?></p>
</div>
<?php elseif ( $type == 'cancelled' ) :?>
<div class="wrap alert critical">
	<h3 class="key-status"><?php esc_html_e("Your Certly plan has been cancelled.", 'certly'); ?></h3>
	<p class="description"><?php printf( __('Please visit your <a href="%s" target="_blank">Certly account page</a> to reactivate your subscription.', 'certly'), 'https://guard.certly.io/settings/'); ?></p>
</div>
<?php elseif ( $type == 'suspended' ) :?>
<div class="wrap alert critical">
	<h3 class="key-status failed"><?php esc_html_e("Your Certly subscription is suspended.", 'certly'); ?></h3>
	<p class="description"><?php printf( __('Please contact <a href="%s" target="_blank">Certly support</a> for assistance.', 'certly'), 'https://certly.io/contact/'); ?></p>
</div>
<?php elseif ( $type == 'active-notice' && $time_saved ) :?>
<div class="wrap alert active">
	<h3 class="key-status"><?php echo esc_html( $time_saved ); ?></h3>
	<p class="description"><?php printf( __('You can help us fight spam and upgrade your account by <a href="%s" target="_blank">contributing a token amount</a>.', 'certly'), 'https://guard.certly.io/settings'); ?></p>
</div>
<?php elseif ( $type == 'missing' ) :?>
<div class="wrap alert critical">
	<h3 class="key-status failed"><?php esc_html_e( 'There is a problem with your API key.', 'certly'); ?></h3>
	<p class="description"><?php printf( __('Please contact <a href="%s" target="_blank">Certly support</a> for assistance.', 'certly'), 'https://guard.certly.io/'); ?></p>
</div>
<?php elseif ( $type == 'new-key-valid' ) :?>
<div id="message" class="updated notice">
	<p><?php esc_html_e('Certly Guard is now activated. Happy blogging!', 'certly'); ?></p>
</div>
<?php elseif ( $type == 'new-key-invalid' ) :?>
<div class="wrap alert critical">
	<h3 class="key-status"><?php esc_html_e( 'The key you entered is invalid. Please double-check it.' , 'certly'); ?></h3>
</div>
<?php elseif ( $type == 'existing-key-invalid' ) :?>
<div class="wrap alert critical">
	<h3 class="key-status"><?php esc_html_e( 'Your API key is no longer valid. Please enter a new key or contact support@certly.io.' , 'certly'); ?></h3>
</div>
<?php elseif ( $type == 'new-key-failed' ) :?>
<div class="wrap alert critical">
	<h3 class="key-status"><?php esc_html_e( 'The API key you entered could not be verified.' , 'certly'); ?></h3>
	<p class="description"><?php printf( __('The connection to certly.io could not be established. Please check your server configuration.', 'certly')); ?></p>
</div>
<?php endif;?>
