<table cellspacing="0" cellpadding="5" border="0">
	<tr valign="top">
		<td align="right"><?php print_string("eduid_user_info_endpoint", "auth_eduid"); ?>:</td>
		<td>
			<input type="text" name="eduid_user_info_endpoint" value="<?php echo $config->eduid_user_info_endpoint ?>" size="80" />
		</td>
	</tr>
	<tr>
		<td align="right"><?php print_string("service_token_duration", "auth_eduid"); ?>:</td>
		<td>
			<select name="service_token_duration">
				<?php
					$service_token_duration = empty($config->service_token_duration)? 1 : intval($config->service_token_duration);
					for($h=1; $h<48; $h++){
						$time = $h*3600;
						$selection_status = $service_token_duration == $time ? 'selected="selected"' : '';
						echo "<option value='$time' $selection_status>$h</option>";
					}
				?>
			</select>
		</td>
	</tr>
	<tr>
		<td align="right"><?php print_string("app_token_duration", "auth_eduid"); ?>:</td>
		<td>
			<select name="app_token_duration">
				<?php
					$app_token_duration = empty($config->app_token_duration)? 1 : intval($config->app_token_duration);
					for($h=1; $h<48; $h++){
						$time = $h*3600;
						$selection_status = $app_token_duration == $time ? 'selected="selected"' : '';
						echo "<option value='$time' $selection_status>$h</option>";
					}
				?>
			</select>
		</td>
	</tr>

</table>
