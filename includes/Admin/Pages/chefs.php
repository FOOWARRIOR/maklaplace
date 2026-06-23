<?php
/**
 * Chefs page implementation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get services from container
$chefProfileService = $this->container->get( \MaklaPlace\Core\ChefProfileService::class );
$userService = $this->container->get( \MaklaPlace\Core\UserService::class );
?>
<div class='wrap'>
	<h1><?php esc_html_e( 'MaklaPlace Chefs', 'maklaplace' ); ?></h1>
	
	<?php
	// For now, just show a placeholder
	// In a full implementation, we would get chefs from the services
	?>
	<p><?php esc_html_e( 'Chefs management page - to be implemented', 'maklaplace' ); ?></p>
</div>