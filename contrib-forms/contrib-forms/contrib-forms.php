<?php
/*
Plugin Name: Contrib Forms
Plugin URI: https://github.com/DevelopersContrib/Wordpress-Plugins/tree/master/contrib-forms
Description: Contrib Forms allows you to add or embed Contrib forms into your wordpress posts or pages by calling the shortcodes  [contribform form="inquiry"]
 [contribform form="partnership"]
 [contribform form="offer"]
 [contribform form="staffing"]  while in Text Mode.
Author: Contrib.com
Version: 1
Author URI:
*/
 
function contribform_func( $atts ) {
	extract( shortcode_atts( array(
		'form' => '',
	), $atts ) );

	$page = '';
	$str = '';
	switch($form){
		case "inquiry":
			$page = 'inquiry';
		break;		
		case "partnership":
			$page = 'partnership';
		break;		
		case "offer":
			$page = 'offer';
		break;		
		case "staffing":
			$page = 'staffing';
		break;
	}
	if(!empty($page)){
		$url = "http://www.contrib.com/signup/$page/".$_SERVER['HTTP_HOST'];
		$str = "<iframe src='$url' scrolling='no' frameborder='no' style='width:332px;height:435px;border: none;'></iframe>";
	}
	return $str;
}
add_shortcode( 'contribform', 'contribform_func' );