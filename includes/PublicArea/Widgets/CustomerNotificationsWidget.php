<?php
/**
 * Elementor customer notifications widget.
 *
 * @package MaklaPlace\PublicArea\Widgets
 */

namespace MaklaPlace\PublicArea\Widgets;

use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Renders customer notifications inside Elementor templates.
 */
final class CustomerNotificationsWidget extends Widget_Base {

	public function get_name() : string {
		return 'maklaplace_customer_notifications';
	}

	public function get_title() : string {
		return __( 'MaklaPlace Customer Notifications', 'maklaplace' );
	}

	public function get_icon() : string {
		return 'eicon-bell';
	}

	public function get_categories() : array {
		return array( 'maklaplace' );
	}

	protected function render() : void {
		echo do_shortcode( '[maklaplace_customer_notifications]' );
	}

	protected function content_template() : void {
		?>
		<# print( 'Use the live frontend output for this widget.' ); #>
		<?php
	}
}
