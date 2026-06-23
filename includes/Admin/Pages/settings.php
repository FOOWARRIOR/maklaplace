<?php
/**
 * Settings page implementation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class='wrap'>
	<h1><?php esc_html_e( 'MaklaPlace Settings', 'maklaplace' ); ?></h1>
	
	<form method='post' action='options.php'>
		<?php
		// This would output the settings fields and sections
		// settings_fields( 'maklaplace_settings_group' );
		// do_settings_sections( 'maklaplace' );
		?>
		<p><?php esc_html_e( 'Settings form - to be implemented', 'maklaplace' ); ?></p>
		<?php // submit_button(); ?>
	</form>
</div>