<?php
namespace awo\shortcodes;

defined( 'ABSPATH' ) or die( 'No direct access!' );

function week( $atts ) {

	// Attributes
	$atts = shortcode_atts(
		array(
			'exceptions' => '',
		),
		$atts
	);

	if($atts['exceptions']=="true")
	{
		
	}
	else
	{

	}

	//return $html;

}

add_shortcode( 'awo_week', '\awo\shortcodes\week' );