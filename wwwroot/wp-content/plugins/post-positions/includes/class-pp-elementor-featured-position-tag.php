<?php
if (!defined('ABSPATH')) exit;

class PP_Elementor_Featured_Position_Tag extends \Elementor\Core\DynamicTags\Tag {

    public function get_name() {
        return 'pp_featured_position';
    }

    public function get_title() {
        return __('Featured Position', 'post-positions');
    }

    public function get_group() {
        return 'post';
    }

    public function get_categories() {
        return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
    }

    protected function render() {
        echo esc_html(get_post_meta(get_the_ID(), 'featured_position', true));
    }
}
