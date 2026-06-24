<?php
/**
 * Elementor chef directory widget.
 *
 * @package MaklaPlace\PublicArea\Widgets
 */

namespace MaklaPlace\PublicArea\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the public chef directory inside Elementor templates.
 */
final class ChefDirectoryWidget extends Widget_Base {

	public function get_name() : string {
		return 'maklaplace_chef_directory';
	}

	public function get_title() : string {
		return __( 'MaklaPlace Chef Directory', 'maklaplace' );
	}

	public function get_icon() : string {
		return 'eicon-posts-grid';
	}

	public function get_categories() : array {
		return array( 'general' );
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
			'per_page',
			array(
				'label'   => __( 'Chefs Per Page', 'maklaplace' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 12,
				'min'     => 1,
				'max'     => 24,
			)
		);

		$this->end_controls_section();
	}

	protected function render() : void {
		$settings = $this->get_settings_for_display();
		echo do_shortcode( sprintf( '[maklaplace_chef_directory per_page="%d"]', absint( $settings['per_page'] ?? 12 ) ) );
	}

	protected function content_template() : void {
		?>
		<# print( 'Use the live frontend output for this widget.' ); #>
		<?php
	}
}
