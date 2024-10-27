<?php
namespace awo\widget;

defined( 'ABSPATH' ) or die( 'No direct access!' );

function register(){
	register_widget('awo\widget\Widget');
}

class Widget extends \WP_Widget {
	function __construct(){
		parent::__construct(
			'widget',
			__('Are we open?', "are-we-open"),
			array('description' => __("Plan & display business opening hours.", "are-we-open"))
			);
	}

	public function widget($args, $instance){
		$title = apply_filters( 'widget_title', $instance['title'] );
		$url = apply_filters('widget_text', $instance['url']);
		$text = apply_filters('widget_text', $instance['text']);
		
		echo $args['before_widget'];
		
		echo "<div id=\"open-hours\">";
			if (!empty($title)){
				echo $args['before_title'] . $title . $args['after_title'];
			}
			
			echo date_i18n('j F Y', time());
			$now = \awo\date("now");
			$today = \awo\get_day($now, $ignore_exceptions = false);
			echo \awo\display_hours($today->from, $today->until, $now, $today->is_closed);

			if(!empty($url) && !empty($text))
			{
				echo "<div><a href=\"".$url."\">".$text."</a></div>";
			}
		echo "</div>";
		
		echo $args['after_widget'];
	}

	public function form($instance){
		$title = (isset($instance['title']))?$instance['title']:__("Title", "are-we-open");
		$url = (isset($instance['url']))?$instance['url']:"#hours";
		$text = (isset($instance['text']))?$instance['text']:__("Link text", "are-we-open");

		$html = "<p>";
		$html.= "<label for=\"".$this->get_field_id( 'title' )."\">".__("Title", "are-we-open")."</label>";
		$html.= "<input class=\"widefat\"
		id=\"".$this->get_field_id( 'title' )."\"
		name=\"".$this->get_field_name( 'title' )."\" type=\"text\" value=\"".esc_attr($title)."\">";
		
		$html.= "<label for=\"".$this->get_field_id( 'url' )."\">".__("URL", "are-we-open")."</label>";
		$html.= "<input class=\"widefat\"
		id=\"".$this->get_field_id( 'url' )."\"
		name=\"".$this->get_field_name( 'url' )."\" type=\"text\" value=\"".esc_attr($url)."\">";

		$html.= "<label for=\"".$this->get_field_id('text')."\">".__("Link text", "are-we-open")."</label>";
		$html.= "<input class=\"widefat\"
		id=\"".$this->get_field_id('text')."\"
		name=\"".$this->get_field_name('text')."\" type=\"text\" value=\"".esc_attr($text)."\">";
		$html.= "</p>";
		echo $html;
	}

	public function update($new_instance, $old_instance){
		$instance = array();
		$instance['title'] = (!empty( $new_instance['title'] )) ? strip_tags( $new_instance['title'] ):'';
		$instance['url'] = (!empty( $new_instance['url'] )) ? strip_tags( $new_instance['url'] ):'';
		$instance['text'] = (!empty( $new_instance['text'] )) ? strip_tags( $new_instance['text'] ):'';
		return $instance;
	}

}