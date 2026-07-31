<?php
/**
 * Elementor customer dashboard widget.
 *
 * @package MaklaPlace\PublicArea\Widgets
 */

namespace MaklaPlace\PublicArea\Widgets;

use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the customer dashboard inside Elementor templates.
 */
final class CustomerDashboardWidget extends Widget_Base {

	public function get_name() : string {
		return 'maklaplace_customer_dashboard';
	}

	public function get_title() : string {
		return __( 'MaklaPlace Customer Dashboard', 'maklaplace' );
	}

	public function get_icon() : string {
		return 'eicon-dashboard';
	}

	public function get_categories() : array {
		return array( 'maklaplace' );
	}

	protected function render() : void {
		echo do_shortcode( '[maklaplace_customer_dashboard]' );
	}

	protected function content_template() : void {
		?>
		<# print( 'Use the live frontend output for this widget.' ); #>
		<?php
	}
}
