<style type="text/css">
<!--
	textarea {
		width: 98%;
	}

	tr.authority {
		background-color: #B0E0E6;
	}

	tr.new_authority {
		background-color: #FFE4E1;
	}

	div.authority_management {
		color: black;
		font-weight: normal;
		border: none;
		padding: 10px 15px;
		text-align: center;
		text-decoration: none;
		display: inline-block;
		font-size: 16px;
		-webkit-transition-duration: 0.5s; /* Safari */
		transition-duration: 0.5s;
		cursor: pointer;
		cursor: hand;
	}

	div.add {
		border: 2px solid #4CAF50;
	}

	div.drop {
		border: 2px solid #f44336;
	}

	div.add:hover {
		background-color: #4CAF50;
		color: white;
	}

	div.drop:hover {
		background-color: #f44336;
		color: white;
	}

	.not_valid {
		border: 3px solid red !important;
	}


-->
</style>
<table id="settings" cellspacing="0" cellpadding="5" border="0">
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
	<tr>
		<td><hr /></td>
		<td><hr /></td>
	</tr>
	<tr>
		<th align="right"><?php print_string("eduid_authorities", "auth_eduid"); ?>:</th>
		<th><div class="authority_management add"><?php print_string("eduid_add_new_authority", "auth_eduid"); ?></div></th>
	</tr>
	<?php // authority table entries start ?>
	<?php foreach($authority_entries as $entry) {?>
		<?php // drop authority button ?>
		<tr class="authority" authority="<?php echo $entry->id ?>">
			<td></td>
			<td align="right"><div class="authority_management drop" authority="<?php echo $entry->id ?>"><?php print_string("eduid_drop_authority_entry", "auth_eduid"); ?></div></td>
		</tr>
		<?php // authority name ?>
		<tr class="authority" authority="<?php echo $entry->id ?>">
			<td align="right"><?php print_string("eduid_authority_name", "auth_eduid"); ?>:</td>
			<td>
				<input type="text" name="authority[<?php echo $entry->id ?>][authority_name]" value="<?php echo $entry->authority_name ?>" size="80" />
			</td>
		</tr>
		<?php // authority url ?>
		<tr class="authority" authority="<?php echo $entry->id ?>">
			<td align="right"><?php print_string("eduid_authority_url", "auth_eduid"); ?>:</td>
			<td>
				<input type="text" name="authority[<?php echo $entry->id ?>][authority_url]" value="<?php echo $entry->authority_url ?>" size="80" />
			</td>
		</tr>
		<?php // authority shared token ?>
		<tr class="authority" authority="<?php echo $entry->id ?>">
			<td align="right"><?php print_string("eduid_authority_shared_token", "auth_eduid"); ?>:</td>
			<td>
				<textarea type="text" name="authority[<?php echo $entry->id ?>][authority_shared_token]"><?php echo $entry->authority_shared_token ?></textarea>
			</td>
		</tr>
		<?php // privkey for authority ?>
		<tr class="authority" authority="<?php echo $entry->id ?>">
			<td align="right"><?php print_string("eduid_privkey_for_authority", "auth_eduid"); ?>:</td>
			<td>
				<textarea type="text" name="authority[<?php echo $entry->id ?>][privkey_for_authority]"><?php echo $entry->privkey_for_authority ?></textarea>
			</td>
		</tr>
		<?php // authority public key ?>
		<tr class="authority" authority="<?php echo $entry->id ?>">
			<td align="right"><?php print_string("eduid_authority_public_key", "auth_eduid"); ?>:</td>
			<td>
				<textarea type="text" name="authority[<?php echo $entry->id ?>][authority_public_key]"><?php echo $entry->authority_public_key ?></textarea>
			</td>
		</tr>
		<tr authority="<?php echo $entry->id ?>">
			<td><hr /></td>
			<td><hr /></td>
		</tr>
	<?php } // foreach end ?>
</table>

<script type="text/javascript">
<!--
	YUI().use(
	  'node', 'event',
	  function (Y) {
		// drop the authority slot: delegated to the table because of the dynamically created nodes
		Y.one('table#settings').delegate('click', function(e){
			var authority = Y.one(e.target).getAttribute('authority');
			Y.all('tr[authority="'+authority+'"]').remove();
		}, 'div.drop');

		// add new authority slot
		Y.one('div.add').on('click', function(e){
			var authority_code = (Math.random().toString(36)+'00000000000000000').slice(0, 18);
			var tr = e.target.get('parentNode').get('parentNode');
			//*************************************************
			// drop authority button
			//*************************************************
			// prepare the container: drop authority button
			var drop_button_container = Y.Node.create('<tr class="new_authority" authority="'+authority_code+'"/>');
			Y.Node.create('<td align="right"/>').appendTo(drop_button_container);
			Y.Node.create('<td align="right"/>').insert(
				Y.Node.create('<div class="authority_management drop" authority="'+authority_code+'" />').insert('<?php print_string("eduid_drop_authority_entry", "auth_eduid"); ?>')
			).appendTo(drop_button_container);

			// prepare the container: authority name
			var authority_name_container = Y.Node.create('<tr class="new_authority" authority="'+authority_code+'"/>');
			Y.Node.create('<td align="right"/>').insert('<?php print_string("eduid_authority_name", "auth_eduid"); ?>:').appendTo(authority_name_container);
			Y.Node.create('<td/>').insert(
				Y.Node.create('<input type="text" name="new_authority[\''+authority_code+'\'][authority_name]" size="80" />')
			).appendTo(authority_name_container);

			// prepare the container: authority url
			var authority_url_container = Y.Node.create('<tr class="new_authority" authority="'+authority_code+'"/>');
			Y.Node.create('<td align="right"/>').insert('<?php print_string("eduid_authority_url", "auth_eduid"); ?>:').appendTo(authority_url_container);
			Y.Node.create('<td/>').insert(
				Y.Node.create('<input type="text" name="new_authority[\''+authority_code+'\'][authority_url]" size="80" />')
			).appendTo(authority_url_container);

			// prepare the container: authority shared token
			var authority_shared_token_container = Y.Node.create('<tr class="new_authority" authority="'+authority_code+'"/>');
			Y.Node.create('<td align="right"/>').insert('<?php print_string("eduid_authority_shared_token", "auth_eduid"); ?>:').appendTo(authority_shared_token_container);
			Y.Node.create('<td/>').insert(
				Y.Node.create('<textarea type="text" name="new_authority[\''+authority_code+'\'][authority_shared_token]" />')
			).appendTo(authority_shared_token_container);

			// prepare the container: privkey for authority
			var privkey_for_authority_container = Y.Node.create('<tr class="new_authority" authority="'+authority_code+'"/>');
			Y.Node.create('<td align="right"/>').insert('<?php print_string("eduid_privkey_for_authority", "auth_eduid"); ?>:').appendTo(privkey_for_authority_container);
			Y.Node.create('<td/>').insert(
				Y.Node.create('<textarea type="text" name="new_authority[\''+authority_code+'\'][privkey_for_authority]" />')
			).appendTo(privkey_for_authority_container);

			// prepare the container: authority public key
			var authority_public_key_container = Y.Node.create('<tr class="new_authority" authority="'+authority_code+'"/>');
			Y.Node.create('<td align="right"/>').insert('<?php print_string("eduid_authority_public_key", "auth_eduid"); ?>:').appendTo(authority_public_key_container);
			Y.Node.create('<td/>').insert(
				Y.Node.create('<textarea type="text" name="new_authority[\''+authority_code+'\'][authority_public_key]" />')
			).appendTo(authority_public_key_container);

			// ***** insert in the page *****
			tr.insert(drop_button_container, 'after');
			drop_button_container.insert(authority_name_container, 'after');
			authority_name_container.insert(authority_url_container, 'after');
			authority_url_container.insert(authority_shared_token_container, 'after');
			authority_shared_token_container.insert(privkey_for_authority_container, 'after');
			privkey_for_authority_container.insert(authority_public_key_container, 'after');
		});

		// check the submission
		Y.one('form#authmenu').on('submit', function(e){
			var valid_submission = true;
			Y.all('tr.new_authority input, tr.new_authority textarea, tr.authority input, tr.authority textarea').each(function(node){
				if(node.get('value').length == 0) {
					node.addClass('not_valid');
					valid_submission = false;
				} else {
					node.removeClass('not_valid');
				}
			});

			if(!valid_submission) {
				e.preventDefault(); // prevent the submission
				alert('<?php print_string("eduid_invalid_form", "auth_eduid"); ?>');
			}
		});
	  }
	);
// -->
</script>
