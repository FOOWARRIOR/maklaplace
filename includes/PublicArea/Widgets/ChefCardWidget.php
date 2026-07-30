<?php
/**
 * Elementor chef card widget.
 *
 * @package MaklaPlace\PublicArea\Widgets
 */

namespace MaklaPlace\PublicArea\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a single chef card for Elementor templates.
 */
final class ChefCardWidget extends Widget_Base {

	public function get_name() : string {
		return 'maklaplace_chef_card';
	}

	public function get_title() : string {
		return __( 'MaklaPlace Chef Card', 'maklaplace' );
	}

	public function get_icon() : string {
		return 'eicon-person';
	}

	public function get_categories() : array {
		return array( 'maklaplace', 'general' );
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
				'label'       => __( 'Chef ID', 'maklaplace' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => 0,
				'min'         => 0,
				'description' => __( 'Use the chef user ID from MaklaPlace.', 'maklaplace' ),
			)
		);

		$this->end_controls_section();
	}

	protected function render() : void {
		$settings = $this->get_settings_for_display();
		$chef_id = absint( $settings['chef_id'] ?? 0 );
		if ( $chef_id <= 0 ) {
			return;
		}

		echo do_shortcode( sprintf( '[maklaplace_chef_card chef_id="%d"]', $chef_id ) );
	}
}
