<div class="no-key config-wrap">
<p><?php esc_html_e('Certly Guard effortlessly discards comments that have links to malware, phishing, or spam. To set up Certly Guard, enter your API key below.', 'certly'); ?></p>
<div class="activate-highlight activate-option">
	<div class="option-description">
		<strong><?php esc_html_e('Enter your API key.', 'certly'); ?></strong>
		<p><?php esc_html_e('If you already know your API key.', 'certly'); ?></p>
	</div>
	<form action="<?php echo esc_url( Certly_Admin::get_page_url() ); ?>" method="post" id="certly-enter-api-key" class="right">
		<input id="key" name="key" type="text" size="15" value="<?php echo esc_attr( Certly::get_api_key() ); ?>" class="regular-text code">
		<input type="hidden" name="action" value="enter-key">
		<?php wp_nonce_field( Certly_Admin::NONCE ); ?>
		<input type="submit" name="submit" id="submit" class="button button-secondary" value="<?php esc_attr_e('Use this key', 'certly');?>">
	</form>
</div>
<p>Don't have an API key? Sign up to get one at <a href="https://guard.certly.io">guard.certly.io</a>.</p>
</div>
