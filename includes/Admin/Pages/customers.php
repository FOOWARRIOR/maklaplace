<?php
/**
 * Customers page implementation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get services from container
$userService = $this->container->get( \MaklaPlace\Core\UserService::class );
?>
<div class='wrap'>
	<h1><?php esc_html_e( 'MaklaPlace Customers', 'maklaplace' ); ?></h1>
	
	<?php
	// For now, just show a placeholder
	// In a full implementation, we would get customers from the user service
	?>
	<p><?php esc_html_e( 'Customers management page - to be implemented', 'maklaplace' ); ?></p>
</div>