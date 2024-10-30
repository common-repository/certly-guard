<div class="wrap">

	<h2><?php esc_html_e( 'Certly Guard' , 'certly');?></h2>

	<div class="have-key">

		<?php if ( $certly_user ):?>

			<div id="wpcom-stats-meta-box-container" class="metabox-holder"><?php
				wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
				wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
				?>
				<script type="text/javascript">
				jQuery(document).ready( function($) {
					jQuery('.if-js-closed').removeClass('if-js-closed').addClass('closed');
					if(typeof postboxes !== 'undefined')
						postboxes.add_postbox_toggles( 'plugins_page_certly-key-config' );
				});
				</script>
				<div class="postbox-container" style="width: 55%;margin-right: 10px;">
					<div id="normal-sortables" class="meta-box-sortables ui-sortable">
						<div id="referrers" class="postbox ">
							<div class="handlediv" title="Click to toggle"><br></div>
							<h3 class="hndle"><span><?php esc_html_e( 'Settings' , 'certly');?></span></h3>
							<form name="certly_conf" id="certly-conf" action="<?php echo esc_url( Certly_Admin::get_page_url() ); ?>" method="POST">
								<div class="inside">
									<table cellspacing="0" class="certly-settings">
										<tbody>
											<?php if ( !defined( 'CERTLY_API_KEY' ) ):?>
											<tr>
												<th class="certly-api-key" width="10%" align="left" scope="row"><?php esc_html_e('API Key', 'certly');?></th>
												<td width="5%"/>
												<td align="left">
													<span class="api-key"><input id="key" name="key" type="text" size="15" value="<?php echo esc_attr( get_option('certly_api_key') ); ?>" class="regular-text code <?php echo $certly_user->status;?>"></span>
												</td>
											</tr>
											<?php endif; ?>
											<?php if ( isset( $_GET['ssl_status'] ) ) { ?>
												<tr>
													<th align="left" scope="row"><?php esc_html_e( 'SSL Status', 'certly' ); ?></th>
													<td></td>
													<td align="left">
														<p>
															<?php

															if ( ! function_exists( 'wp_http_supports' ) ) {
																?><b><?php esc_html_e( 'Disabled.', 'certly' ); ?></b> <?php printf( esc_html( 'Your WordPress installation does not include the function %s; upgrade to the latest version of WordPress.', 'certly' ), '<code>wp_http_supports</code>' ); ?><?php
															}
															else if ( ! wp_http_supports( array( 'ssl' ) ) ) {
																?><b><?php esc_html_e( 'Disabled.', 'certly' ); ?></b> <?php esc_html_e( 'Your Web server cannot make SSL requests; contact your Web host and ask them to add support for SSL requests.', 'certly' ); ?><?php
															}
															else {
																$ssl_disabled = get_option( 'certly_ssl_disabled' );

																if ( $ssl_disabled ) {
																	?><b><?php esc_html_e( 'Temporarily disabled.', 'certly' ); ?></b> <?php esc_html_e( 'Certly encountered a problem with a previous SSL request and disabled it temporarily. It will begin using SSL for requests again shortly.', 'certly' ); ?><?php
																}
																else {
																	?><b><?php esc_html_e( 'Enabled.', 'certly' ); ?></b> <?php esc_html_e( 'All systems functional.', 'certly' ); ?><?php
																}
															}

															?>
														</p>
													</td>
												</tr>
											<?php } ?>
											<tr>
												<th align="left" scope="row"><?php esc_html_e('Comments', 'certly');?></th>
												<td></td>
												<td align="left">
													<p>
														<label for="certly_show_user_comments_approved" title="<?php esc_attr_e( 'Show approved comments' , 'certly'); ?>"><input name="certly_show_user_comments_approved" id="certly_show_user_comments_approved" value="1" type="checkbox" <?php checked('1', get_option('certly_show_user_comments_approved')); ?>> <?php esc_html_e('Show the number of approved comments beside each comment author', 'certly'); ?></label>
													</p>
												</td>
											</tr>
											<tr>
												<th class="strictness" align="left" scope="row"><?php esc_html_e('Strictness', 'certly'); ?></th>
												<td></td>
												<td align="left">
													<fieldset><legend class="screen-reader-text"><span><?php esc_html_e('Certly anti-spam strictness', 'certly'); ?></span></legend>
													<p><label for="certly_strictness_1"><input type="radio" name="certly_strictness" id="certly_strictness_1" value="1" <?php checked('1', get_option('certly_strictness')); ?> /> <?php esc_html_e('Silently discard flagged comments so I never see them.', 'certly'); ?></label></p>
													<p><label for="certly_strictness_0"><input type="radio" name="certly_strictness" id="certly_strictness_0" value="0" <?php checked('0', get_option('certly_strictness')); ?> /> <?php esc_html_e('Always put flagged comments in the Spam folder for review.', 'certly'); ?></label></p>
													</fieldset>
													<span class="note"><strong><?php esc_html_e('Note:', 'certly');?></strong>
													<?php

													$delete_interval = max( 1, intval( apply_filters( 'certly_delete_comment_interval', 15 ) ) );

													printf(
														_n(
															'Spam in the <a href="%1$s">spam folder</a> older than 1 day is deleted automatically.',
															'Spam in the <a href="%1$s">spam folder</a> older than %2$d days is deleted automatically.',
															$delete_interval,
															'certly'
														),
														admin_url( 'edit-comments.php?comment_status=spam' ),
														$delete_interval
													);

													?>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
								<div id="major-publishing-actions">
									<?php if ( !defined( 'CERTLY_API_KEY' ) ):?>
									<div id="delete-action">
										<a class="submitdelete deletion" href="<?php echo esc_url( Certly_Admin::get_page_url( 'delete_key' ) ); ?>"><?php esc_html_e('Disconnect this account', 'certly'); ?></a>
									</div>
									<?php endif; ?>
									<?php wp_nonce_field(Certly_Admin::NONCE) ?>
									<div id="publishing-action">
											<input type="hidden" name="action" value="enter-key">
											<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Save Changes', 'certly');?>">

									</div>
									<div class="clear"></div>
								</div>
							</form>
						</div>
					</div>
				</div>
				<div class="postbox-container" style="width:44%;">
					<div id="normal-sortables" class="meta-box-sortables ui-sortable">
						<div id="referrers" class="postbox ">
							<div class="handlediv" title="Click to toggle"><br></div>
							<h3 class="hndle"><span><?php esc_html_e( 'Account' , 'certly');?></span></h3>
							<div class="inside">
								<table cellspacing="0">
									<tbody>
										<tr>
											<th scope="row" align="left"><?php esc_html_e( 'Subscription Type' , 'certly');?></th>
											<td width="5%"/>
											<td align="left">
												<span><?php echo $certly_user->account_name; ?></span>
											</td>
										</tr>
										<tr>
											<th scope="row" align="left"><?php esc_html_e( 'Status' , 'certly');?></th>
											<td width="5%"/>
											<td align="left">
												<span><?php
													if ( 'cancelled' == $certly_user->status ) :
														esc_html_e( 'Cancelled', 'certly' );
													elseif ( 'suspended' == $certly_user->status ) :
														esc_html_e( 'Suspended', 'certly' );
													elseif ( 'missing' == $certly_user->status ) :
														esc_html_e( 'Missing', 'certly' );
													elseif ( 'no-sub' == $certly_user->status ) :
														esc_html_e( 'No Subscription Found', 'certly' );
													else :
														esc_html_e( 'Active', 'certly' );
													endif; ?></span>
											</td>
										</tr>
										<?php if ( $certly_user->next_billing_date ) : ?>
										<tr>
											<th scope="row" align="left"><?php esc_html_e( 'Next Billing Date' , 'certly');?></th>
											<td width="5%"/>
											<td align="left">
												<span><?php echo date( 'F j, Y', $certly_user->next_billing_date ); ?></span>
											</td>
										</tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
							<div id="major-publishing-actions">
								<div id="publishing-action">
								</div>
								<div class="clear"></div>
							</div>
						</div>
					</div>
				</div>
			</div>

		<?php endif;?>

	</div>
</div>
