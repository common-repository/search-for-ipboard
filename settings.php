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

$fields = array(
	'dbname' => array('type' => 'text'),
	'dbhost' => array('type' => 'text'),
	'dbusername' => array('type' => 'text'),
	'dbpassword' => array('type' => 'text'),
	'dbprefix' => array('type' => 'text'),
	'url' => array('type' => 'text'));

?>
		
<div class="<?php echo $this->name; ?> wrap">
	<h1><?php echo __('heading_settings', $this->name); ?></h1>
	<form action="options.php" method="post">
		<?php wp_nonce_field('update-options') ?>
		<input type="hidden" name="action" value="update" />
		<input type="hidden" name="page_options" value="<?php echo $this->name; ?>_params" />
		<h2 class="title"><?php echo __('heading_database', $this->name); ?></h2>
		<p><?php echo __('note_database', $this->name); ?></p>
		<table class="form-table">
	
			<?php require($this->basedir.'helpers/fields.php'); ?>
					
		</table>
		<h2 class="title"><?php echo __('heading_search', $this->name); ?></h2>
		<?php if (file_exists($this->basedir.'premium/settings.php')) { ?>
			<?php require($this->basedir.'premium/settings.php'); ?>
		<?php } else { ?>
			<p>Layout Settings available in <a href="https://thekrotek.com/wordpress-extensions/ipboard-search" title="Go Premium!">Premium version</a> only.</p>
		<?php } ?>
		<?php submit_button(); ?>
	</form>
	<div class="footnote"><?php echo sprintf(__('footnote', $this->name), date("Y", time())); ?></div>
</div>