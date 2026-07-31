<?php
/**
 * Elementor customer profile widget.
 *
 * @package MaklaPlace\PublicArea\Widgets
 */

namespace MaklaPlace\PublicArea\Widgets;

use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the customer profile form inside Elementor templates.
 */
final class CustomerProfileWidget extends Widget_Base {

	public function get_name() : string {
		return 'maklaplace_customer_profile';
	}

	public function get_title() : string {
		return __( 'MaklaPlace Customer Profile', 'maklaplace' );
	}

	public function get_icon() : string {
		return 'eicon-user-circle-o';
	}

	public function get_categories() : array {
		return array( 'maklaplace' );
	}

	protected function render() : void {
		echo do_shortcode( '[maklaplace_customer_profile]' );
	}

	protected function content_template() : void {
		?>
		<# print( 'Use the live frontend output for this widget.' ); #>
		<?php
	}
}
