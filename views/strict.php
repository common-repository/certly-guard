<fieldset>
	<legend class="screen-reader-text">
		<span><?php esc_html_e( 'Certly anti-spam strictness', 'certly' ); ?></span>
	</legend>
	<p>
		<label for="certly_strictness_1">
			<input type="radio" name="certly_strictness" id="certly_strictness_1" value="1" <?php checked( '1', get_option( 'certly_strictness' ) ); ?> />
			<?php esc_html_e( 'Strict: silently discard the worst and most pervasive spam.', 'certly' ); ?>
		</label>
	</p>
	<p>
		<label for="certly_strictness_0">
			<input type="radio" name="certly_strictness" id="certly_strictness_0" value="0" <?php checked( '0', get_option( 'certly_strictness' ) ); ?> />
			<?php esc_html_e( 'Safe: always put spam in the Spam folder for review.', 'certly' ); ?>
		</label>
	</p>
</fieldset>
