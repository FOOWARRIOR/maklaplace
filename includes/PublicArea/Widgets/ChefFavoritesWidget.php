<?php
/**
 * Elementor chef favorites widget.
 *
 * @package MaklaPlace\PublicArea\Widgets
 */

namespace MaklaPlace\PublicArea\Widgets;

use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the logged-in customer's favorite chefs list.
 */
final class ChefFavoritesWidget extends Widget_Base {

	public function get_name() : string {
		return 'maklaplace_chef_favorites';
	}

	public function get_title() : string {
		return __( 'MaklaPlace Chef Favorites', 'maklaplace' );
	}

	public function get_icon() : string {
		return 'eicon-heart-o';
	}

	public function get_categories() : array {
		return array( 'maklaplace' );
	}

	protected function render() : void {
		echo do_shortcode( '[maklaplace_chef_favorites]' );
	}

	protected function content_template() : void {
		?>
		<# print( 'Use the live frontend output for this widget.' ); #>
		<?php
	}
}
