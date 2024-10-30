<div class="wrap">
	<h2><?php esc_html_e( 'Certly Stats' , 'certly');?><?php if ( !isset( $hide_settings_link ) ): ?> <a href="<?php echo esc_url( Certly_Admin::get_page_url() );?>" class="add-new-h2"><?php esc_html_e( 'Settings' , 'certly');?></a><?php endif;?></h2>
	<iframe src="<?php echo esc_url( sprintf( '//certly.io/web/1.0/user-stats.php?blog=%s&api_key=%s&locale=%s', urlencode( get_bloginfo('url') ), Certly::get_api_key(), get_locale() ) ); ?>" width="100%" height="2500px" frameborder="0" id="certly-stats-frame"></iframe>
</div>
