<?php
namespace awo;

defined( 'ABSPATH' ) or die( 'No direct access!' );
/*
Plugin Name: Are we open?
Description: Plan & display business opening hours.
Version:     1.0.5
Author:      Wojciech Międzybrodzki, Andrzej Regmunt
Author URI:  https://miedzybrodzki.net
Text Domain: are-we-open
Domain Path: /lang
 */

require("awo-widget.php");
require("awo-admin.php");
require("awo-shortcodes.php");

add_action('widgets_init', '\awo\widget\register');
add_action('plugins_loaded', '\awo\load_textdomain');
add_action( 'admin_enqueue_scripts', 'awo\admin\enqueue_scripts');
add_action( 'wp_ajax_delete_exception', 'awo\admin\wp_ajax_delete_exception');

register_activation_hook(__FILE__, 'awo\install');
register_uninstall_hook(__FILE__, 'awo\delete');

$wp_timezone = get_option('timezone_string');
!$wp_timezone? $wp_timezone = "GMT" : "";

$timezone = new \DateTimeZone($wp_timezone);

function load_textdomain(){
	$plugin_dir = basename(dirname(__FILE__));
	load_plugin_textdomain('are-we-open', false, $plugin_dir.'/lang/');
}

function install(){
	global $wpdb;

	/** Create exceptions table */
	$tabela = $wpdb->prefix."awo_hours";
	$wpdb->query("CREATE TABLE IF NOT EXISTS `".$tabela."`
			(
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`date` date NOT NULL,
			`from` datetime NOT NULL,
			`until` datetime NOT NULL,
			`is_closed` tinyint(1) NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `date` (`date`)
			)");

	/** Create defaults table */
	$tabela2 = $wpdb->prefix."awo_default";
	$wpdb->query("CREATE TABLE IF NOT EXISTS `".$tabela2."`
			(
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`weekday` int NOT NULL,
			`from` time NOT NULL,
			`until` time NOT NULL,
			`is_closed` tinyint(1) NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `weekday` (`weekday`)
			)");

	$wpdb->query("INSERT IGNORE INTO `".$tabela2."` VALUES"
		."(NULL, 1, 0, 0, TRUE),"
		."(NULL, 2, 0, 0, TRUE),"
		."(NULL, 3, 0, 0, TRUE),"
		."(NULL, 4, 0, 0, TRUE),"
		."(NULL, 5, 0, 0, TRUE),"
		."(NULL, 6, 0, 0, TRUE),"
		."(NULL, 7, 0, 0, TRUE)"
	);
}

function delete(){
	global $wpdb;

	$tabela = $wpdb->prefix."awo_hours";
	$tabela2 = $wpdb->prefix."awo_default";
	$wpdb->query("DROP TABLE IF EXISTS `".$tabela."`");
	$wpdb->query("DROP TABLE IF EXISTS `".$tabela2."`");
}

is_admin() ? add_action('admin_menu', 'awo\admin\menu') : "";

function weekday($date){
	$dow = $date->format("w");
	if($dow==0){$dow=7;}
	return $dow;
}

function weekday_name($i){
	/** get weekday name: 2016-08-01 - very handy Monday :) */
	return date_i18n("l", strtotime("2016-08-0".$i));
}

function get_defaults(){
	global $wpdb;
	return $wpdb->get_results("SELECT * FROM `$wpdb->prefix"."awo_default` ORDER BY `id`");
}

/** @todo: maybe like oncoming_exceptions? */
function get_exceptions(){
	global $wpdb;
	return $wpdb->get_results("SELECT * FROM `$wpdb->prefix"."awo_hours` WHERE `until` >= CURDATE() ORDER BY `date`");
}

function get_exception($date){
	global $wpdb;
	$result = $wpdb->get_row(
		$wpdb->prepare("SELECT * FROM `$wpdb->prefix"."awo_hours` WHERE `date` = %s", 
		$date->format("Y-m-d"))
	);
	return $result;
}

function display_hours($from, $until, $now, $is_closed)
{
	global $timezone;

	$from = date($from);
	$until = date($until);

	$terms = array("", "");

	if(weekday($from) != weekday($until))
	{
		if(weekday($now) == weekday($from)){
			$terms[1] = " jutro";
		}
		else if(weekday($now) == weekday($until)){
			$terms[0] = " wczoraj";
		}
	}

	if($is_closed){
		$html = "<div id=\"hours\" class=\"text-muted\">";
		$html.= __("closed", "are-we-open");
		$html.= "</div>";
	}
	else if($now < $from || $now > $until){
		/** outside of todays opening hours */
		$html = "<div id=\"hours\" class=\"text-muted\">";
		$html.= $from->format("G:i").$terms[0];
		$html.= " - ";
		$html.= $until->format("G:i").$terms[1];
		$html.= "</div>";
	}
	else
	{
		$html = "<div id=\"hours\">";
		$html.= $from->format("G:i").$terms[0];
		$html.= " - ";
		$html.= $until->format("G:i").$terms[1];
		$html.= "</div>";
	}
	return $html;
}
function date($str)
{
	global $timezone;
	return new \DateTime($str, $timezone);
}

/** get opening hours for selected day */
function get_day($date, $ignore_exceptions = false)
{
	$defaults = get_defaults();

	if(!$ignore_exceptions)
	{
		$before = clone $date;
		$before = $before->modify("-1 day");
		$html = "";
		$exception = false;

		$date_exception = get_exception($date);
		$before_exception = get_exception($before);

		if($before_exception){
			$end = date($before_exception->until);

			/** is the preceding day exception ongoing? */
			if($end >= $date){
				$exception = $yesterdays_exception;
			}
		}

		/** if there is an exception and no ongoing "yesterdays" exception */
		if($date_exception && $exception === false)
		{
			$exception = $date_exception;
		}
		if($exception)
		{
			return $exception;
		}
		/** if no exception is present, program continues to return default */
	}

	$i = weekday($date) - 1; //table is indexed from 0, our monday is 1 etc.
	return $defaults[$i];
}

function slice_range($range_string){
	$range = explode("-", $range_string);
	if(sizeof($range) === 2){
		return autocorrect_range($range);
	}
	else
	{
		return false;
	}
}

function autocorrect_range($range)
{
	foreach($range as $i => $hour)
	{
		$part = explode(":", $hour);
		$part[0] = autocorrect_hour($part[0]);
		isset($part[1]) ? $part[1] = autocorrect_minsec($part[1]) : $part[1] = "00";
		isset($part[2]) ? $part[2] = autocorrect_minsec($part[2]) : $part[2] = "00";
		$range[$i] = $part[0].":".$part[1].":".$part[2];
	}
	return [$range[0],$range[1]];
}

function autocorrect_hour($hour)
{
	(int)$hour>23 ? $hour = 23 : '';
	return (int)$hour;
}

function autocorrect_minsec($mins)
{
	(int)$mins>59 ? $mins = 59 : '';
	return (int)$mins;
}

function delete_exception($date){
	global $wpdb;
	return $wpdb->query(
		$wpdb->prepare("DELETE FROM `".$wpdb->prefix."awo_hours`
		WHERE `date` = %s ", $date)
	);
}

function set_exception($date, $from, $until, $is_closed){
	global $wpdb;

	$args = array(
		$date,
		$from,
		$until,
		$is_closed,
		/** pass second time for ON DUPLICATE clause */
		$from,
		$until,
		$is_closed
	);

	return $wpdb->query(
		$wpdb->prepare("INSERT INTO `$wpdb->prefix"."awo_hours`
		VALUES (NULL, %s, %s, %s, %s)
		ON DUPLICATE KEY UPDATE `from` = %s, `until` = %s, `is_closed` = %s", $args)
	);
}

function set_default($from, $until, $day)
{
	global $wpdb;
	$args = [$from, $until, $day];
	return $wpdb->query(
		$wpdb->prepare("UPDATE `$wpdb->prefix"."awo_default`
		SET `from` = %s,
		`until` = %s
		WHERE `id` = %d", $args)
	);
}

function set_default_closed($day, $is_closed)
{
	global $wpdb;
	return $wpdb->query(
		$wpdb->prepare("UPDATE `$wpdb->prefix"."awo_default`
		SET `is_closed` = %d
		WHERE `id` = %d", [$is_closed, $day])
	);
}