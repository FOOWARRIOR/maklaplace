<?php
/**
 * Dashboard page implementation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get services from container
$analyticsService = $this->container->get( \MaklaPlace\Core\AnalyticsService::class );
$stats = $analyticsService->get_platform_stats();
$userService = $this->container->get( \MaklaPlace\Core\UserService::class );
$chefProfileService = $this->container->get( \MaklaPlace\Core\ChefProfileService::class );
$walletService = $this->container->get( \MaklaPlace\Core\WalletService::class );

// For now, we'll use what we have from analytics service and add placeholders for missing data
$totalOrders = isset( $stats['total_orders'] ) ? (int) $stats['total_orders'] : 0;
$activeChefs = isset( $stats['total_active_chefs'] ) ? (int) $stats['total_active_chefs'] : 0;
$platformRevenue = isset( $stats['total_revenue_volume'] ) ? (float) $stats['total_revenue_volume'] : 0.0;
$totalCommission = isset( $stats['total_commissions_generated'] ) ? (float) $stats['total_commissions_generated'] : 0.0;

// Placeholders for data we need to implement
$activeCustomers = 0; // TODO: Get from UserService or AnalyticsService
$pendingChefApprovals = 0; // TODO: Get from ChefProfileService
$walletsReadyForCollection = 0; // TODO: Get from WalletService
?>
<div class='wrap'>
	<h1><?php esc_html_e( 'MaklaPlace Dashboard', 'maklaplace' ); ?></h1>
	<div class='metabox-holder'>
		<div class='meta-box-sortables ui-sortable'>
			<div class='postbox'>
				<h3 class='hndle'><span><?php esc_html_e( 'Overview', 'maklaplace' ); ?></span></h3>
				<div class='inside'>
					<table class='widefat'>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Metric', 'maklaplace' ); ?></th>
								<th><?php esc_html_e( 'Value', 'maklaplace' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<th scope='row'><?php esc_html_e( 'Total Orders', 'maklaplace' ); ?></th>
								<td><?php echo esc_html( number_format( $totalOrders ) ); ?></td>
							</tr>
							<tr>
								<th scope='row'><?php esc_html_e( 'Active Chefs', 'maklaplace' ); ?></th>
								<td><?php echo esc_html( number_format( $activeChefs ) ); ?></td>
							</tr>
							<tr>
								<th scope='row'><?php esc_html_e( 'Active Customers', 'maklaplace' ); ?></th>
								<td><?php echo esc_html( number_format( $activeCustomers ) ); ?></td>
							</tr>
							<tr>
								<th scope='row'><?php esc_html_e( 'Pending Chef Approvals', 'maklaplace' ); ?></th>
								<td><?php echo esc_html( number_format( $pendingChefApprovals ) ); ?></td>
							</tr>
							<tr>
								<th scope='row'><?php esc_html_e( 'Platform Revenue', 'maklaplace' ); ?></th>
								<td><?php echo esc_html( sprintf( '%d DA', number_format( $platformRevenue ) ) ); ?></td>
							</tr>
							<tr>
								<th scope='row'><?php esc_html_e( 'Total Commission', 'maklaplace' ); ?></th>
								<td><?php echo esc_html( sprintf( '%d DA', number_format( $totalCommission ) ) ); ?></td>
							</tr>
							<tr>
								<th scope='row'><?php esc_html_e( 'Wallets Ready for Collection', 'maklaplace' ); ?></th>
								<td><?php echo esc_html( number_format( $walletsReadyForCollection ) ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>