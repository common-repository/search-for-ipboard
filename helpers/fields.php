<?php 
/*------------------------------------------------------------------------
# IP.Board Search
# ------------------------------------------------------------------------
# The Krotek
# Copyright (C) 2011-2019 thekrotek.com. All Rights Reserved.
# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
# Website: https://thekrotek.com
# Support: support@thekrotek.com
-------------------------------------------------------------------------*/

foreach ($fields as $field => $data) { 
	$id = $this->name."-".$field;
	$name = $this->name."_params[".$field."]";
	?>
	<tr>
		<th><label for="<?php echo $id; ?>"><?php echo __($field.'_title', $this->name); ?></label><a class="dashicons dashicons-editor-help option-hint" title="<?php echo esc_attr(__($field.'_hint', $this->name)); ?>"></a></th>
		<td>
			<?php if ($data['type'] == 'text') { ?>
				<input name="<?php echo $name; ?>" id="<?php echo $id; ?>" type="text" value="<?php echo esc_attr($this->getOption($field)); ?>" class="regular-text <?php echo $field; ?>" />
			<?php } elseif ($data['type'] == 'image') { ?>
				<input type="hidden" name="<?php echo $name; ?>" id="<?php echo $id; ?>" value="<?php echo esc_attr($this->getOption($field)); ?>" />
					
				<?php if ($this->getOption($field)) { ?>
					<img src="<?php echo esc_attr($this->getOption($field)); ?>" class="uploaded-image" />
				<?php } ?>
							
				<button id="upload-<?php echo $field; ?>" class="button secondary upload-file"><span class="dashicons dashicons-format-image"></span> <?php echo __('button_image', $this->name); ?></button>
			<?php } elseif ($data['type'] == 'number') { ?>
				<input type="number" id="<?php echo $id; ?>" name="<?php echo $name; ?>" value="<?php echo esc_attr($this->getOption($field)); ?>" class="small-text <?php echo $field; ?>" min="0" step="1">
			<?php } elseif ($data['type'] == 'radio') { ?>
				<?php foreach ($data['data'] as $value => $title) { ?>
					<label class="radio-option"><input type='radio' name='<?php echo $name; ?>' value='<?php echo $value; ?>'<?php echo $this->getOption($field) == $value ? " checked='checked'" : ""; ?> /><?php echo $title; ?></label>
				<?php } ?>
			<?php } elseif ($data['type'] == 'select') { ?>
				<select id="<?php echo $id; ?>" name='<?php echo $name; ?>'>
					<?php foreach ($data['data'] as $value => $title) { ?>
						<option value='<?php echo $value; ?>'<?php echo $this->getOption($field) == $value ? " selected='selected'" : ""; ?> /><?php echo $title; ?></option>
					<?php } ?>
				</select>
			<?php } ?>				
		</td>
	</tr>
<?php } ?>