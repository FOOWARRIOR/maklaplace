<?php
/**
 * Elementor chef reviews list widget.
 *
 * @package MaklaPlace\PublicArea\Widgets
 */

namespace MaklaPlace\PublicArea\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the public chef review list inside Elementor templates.
 */
final class ChefReviewsListWidget extends Widget_Base {

	public function get_name() : string {
		return 'maklaplace_chef_reviews_list';
	}

	public function get_title() : string {
		return __( 'MaklaPlace Chef Reviews List', 'maklaplace' );
	}

	public function get_icon() : string {
		return 'eicon-comments';
	}

	public function get_categories() : array {
		return array( 'maklaplace' );
	}

	protected function register_controls() : void {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Content', 'maklaplace' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'chef_id',
			array(
				'label'   => __( 'Chef ID', 'maklaplace' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 0,
				'min'     => 0,
			)
		);

		$this->end_controls_section();
	}

	protected function render() : void {
		$settings = $this->get_settings_for_display();
		echo do_shortcode( sprintf( '[maklaplace_chef_reviews chef_id="%d"]', absint( $settings['chef_id'] ?? 0 ) ) );
	}

	protected function content_template() : void {
		?>
		<# print( 'Use the live frontend output for this widget.' ); #>
		<?php
	}
}
