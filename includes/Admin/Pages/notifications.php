<?php
/**
 * Notifications page implementation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get services from container
$notificationService = $this->container->get( \MaklaPlace\Core\NotificationService::class );
?>
<div class='wrap'>
	<h1><?php esc_html_e( 'MaklaPlace Notifications', 'maklaplace' ); ?></h1>
	
	<?php
	// For now, just show a placeholder
	// In a full implementation, we would get notifications from the notification service
	?>
	<p><?php esc_html_e( 'Notifications management page - to be implemented', 'maklaplace' ); ?></p>
</div>