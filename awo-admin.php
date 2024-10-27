<?php
namespace awo\admin;

function menu(){
	add_options_page( __('Are we open?', "are-we-open"), 
		__('Are we open?', "are-we-open"), 
		'manage_options', 
		'awo-settings',
		'awo\admin\options_page');
}

function enqueue_scripts(){
	$defaults = \awo\get_defaults();
	$js_translation = [
		'days_of_week' => monday_to_friday($defaults),
		'empty_fields' => __("There are empty fields", "are-we-open"),
		'until_from' => __("'Until' date cannot be before 'from' date.", "are-we-open"),
		'confirm_removing_exc' => __("Confirm removing exception", "are-we-open"),
		'removal_failed' => __("Removal failed", "are-we-open")
	];

    wp_enqueue_style(
    	'datepicker_css', 
		plugin_dir_url(__FILE__) . 'assets/jquery-ui-datepicker.min.css', false, '1.0.0'
	);
	wp_enqueue_style(
    	'admin_css', 
		plugin_dir_url(__FILE__) . 'assets/admin.css', false, '1.0.0'
	);
	wp_enqueue_script(
		'datepicker_js',
		plugin_dir_url(__FILE__) . 'assets/jquery-ui-datepicker.min.js', false, '1.0.0'
	);
	wp_register_script(
		'admin_js',
		plugin_dir_url(__FILE__).'assets/awo_admin.js'
	);
	wp_localize_script('admin_js', 'translation', $js_translation);
	wp_enqueue_script('admin_js');

}

function wp_ajax_delete_exception(){
	if(isset($_POST['exception_date'])){
		if(\awo\delete_exception($_POST['exception_date']))
		{
			echo "ok";
		}
		else{
			echo "fail";
		}
	}
	else
	{
		echo "date not set";
	}
	exit();
}

/** save POST data to database */
function handle_post(){
	global $wpdb;
	global $timezone;

	/** update defaults */
	if( isset($_POST['range']) ){
		foreach($_POST['range'] as $n=>$range_str){
			if($range = \awo\slice_range($range_str)){
				\awo\set_default($range[0], $range[1], $n+1);
			}
		}

		/** check data for each "defaults" weekday.
		 * data for weekday present - set day as closed
		 * else - set day open */
		for($i=1; $i<=7; $i++){
			if(isset($_POST[$i."-is_closed"])){
				\awo\set_default_closed($i, 1);
			}
			else{
				\awo\set_default_closed($i, 0);
			}
		}
	}

	/** Exceptions */
	if(isset($_POST['exception-from'], $_POST['exception-until'])){
		$from = \awo\date($_POST['exception-from']);
		$current = clone $from;
		$until = \awo\date($_POST['exception-until']);

		/** Loop on selected dates range */
		while($current <= $until){
			$i = \awo\weekday($current);

			if(
				isset($_POST[$i."-range"])
				&& $range = \awo\slice_range($_POST[$i.'-range'])
			){
				$date_from = \awo\date( $current->format("Y-m-d").$range[0] );
				$date_until = \awo\date( $current->format("Y-m-d").$range[1] );
				
				/** if "until" hour is earlier, assume it's next day */
				if($date_from > $date_until){
					$date_until->modify('+1 day');
				}

				$is_closed = 0;

				/** "Closed" checkbox overwrites opening hours */
				if(isset($_POST[$i."-is_closed"])){
					$is_closed = 1;
				}

				\awo\set_exception(
					$current->format("Y-m-d"),
					$date_from->format("Y-m-d H:i:s"),
					$date_until->format("Y-m-d H:i:s"),
					$is_closed
				);
			}
			else if (isset($_POST[$i."-is_closed"])){
				\awo\set_exception(
					$current->format("Y-m-d"),
					$current->format("Y-m-d")." 0",
					$current->format("Y-m-d")." 0",
					$closed = true
				);
			}//end day

			$current->modify('+1 day');
		} //end exceptions loop
	} //end exceptions statement	
}

function range_format($day)
{
	$day->from = \awo\date($day->from);
	$day->until = \awo\date($day->until);
	return $day->from->format("H:i")."-".$day->until->format("H:i");
}

function range_field($name, $value){
	return "<input type=\"text\" name=\"".$name."\" class=\"awo_range_field\" value=\"".$value."\" autocomplete=\"off\">";
}

function checkbox($defaults, $day){
	if((int)$defaults[($day-1)]->is_closed===1){
		$variant = " checked=\"true\"";
	}
	else {
		$variant = "";
	}
	return "<input".$variant." type=\"checkbox\" name=\"".$day."-is_closed\" autocomplete=\"off\" class=\"awo-checkbox\">";
}

function monday_to_friday($defaults){
	$html = "";
	for($i=1; $i<=7; $i++){
		$wday = \awo\weekday_name($i);
		$html.= "\t<p>";
		$html.= "\t\t<label for=\"\" style=\"display:inline-block; width:100px;\">".$wday."</label>";
		
		$html.= __("from", "are-we-open")." - ".__("until", "are-we-open");

		/** time range for the day */
		$html.= range_field($i."-range", "");		
		$html.= __("closed", "are-we-open");
		$html.= checkbox($defaults,$i);
		if($i===1){
			$html .= "<input type=\"button\" class=\"button-primary duplicate\" data-target=\"#exceptions-container\" value=\"&darr;\">";
		}
		$html.= "\t</p>";
	}
	return $html;
}

function options_page(){
	global $timezone;
	if(!current_user_can('manage_options')){
		wp_die(__('You do not have sufficient permissions to access this page.'), "are-we-open");
	}

	handle_post();

	$defaults = \awo\get_defaults();

	echo "<div class=\"wrap\" id=\"are-we-open-wrap\">";
		echo "<h2>".__("Are we open? Settings", "are-we-open")."</h2>";

		if(!get_option('timezone_string'))
		{
			echo "<p>".__("Timezone not set - please set Your timezone in WordPress general settings. Otherwise, GMT is assumed.","awo")."</p>";
		}
		
		echo "<div class=\"a1-2\">";
			echo "<h3>".__("Standard opening hours", "are-we-open")."</h3>";
			echo "<form action=\"\" method=\"POST\">";
			echo "<div id=\"defaults-container\" class=\"container\">";
			/** @todo: use monday_to_friday(), change handle_post() behaviour */
			for($i=1; $i<=7; $i++){

				$wday = \awo\weekday_name($i);
				echo "\t<p>";
				echo "\t\t<label for=\"\" style=\"display:inline-block; width:100px;\">".$wday."</label>";
				
				echo __("from", "are-we-open")." - ".__("until", "are-we-open");

				/** hour select range */
				echo range_field("range[]", range_format($defaults[$i-1]) );
				echo __("closed", "are-we-open");
				
				echo checkbox($defaults,$i);
				if($i===1){
					echo "<input type=\"button\" data-target=\"#defaults-container\" class=\"button-primary duplicate\" value=\"&darr;\">";
				}
				echo "\t</p>";
			}
			echo "<input type=\"button\" value=\"".__("Clear")."\" class=\"button-primary clear\" data-target=\"#defaults-container\"> ";
			echo "<input type=\"submit\" value=\"".__("Save")."\" class=\"button-primary\">";
			echo "</div>";
			echo "</form>";

			echo "<h3>".__("Exceptions", "are-we-open")."</h3>";
			?>
			<form action="" method="POST" id="exceptions">
				<label for="exception-from"><?php echo __("from", "are-we-open"); ?></label>
				<input id="exception-from" type="text" name="exception-from" class="date-pick" autocomplete="off">

				<label for="exception-until"><?php echo __("until", "are-we-open"); ?></label>
				<input id="exception-until" type="text" name="exception-until" class="date-pick" autocomplete="off">
				<div id="exceptions-container" class="container"></div>
				<?php 
				echo "<input type=\"button\" value=\"".__("Clear")."\" class=\"button-primary clear\" data-target=\"#exceptions-container\"> ";
				echo "<input type=\"submit\" value=\"".__("Save")."\" class=\"button-primary\">";
				?>
			</form>
		</div>

		<div id="exceptions-list" class="a1-2">
		<?php
		echo "<h3>".__("Planned exceptions", "are-we-open")."</h3>";
		$exceptions = \awo\get_exceptions();
		echo "<pre>";
		foreach($exceptions as $exception)
		{
			echo "<p>".$exception->date." - ";
			if($exception->is_closed){
				_e("closed", "are-we-open");
			}
			else{
				$from = \awo\date($exception->from);
				$until = \awo\date($exception->until);
				$from_weekday = $from->format("w");
				$until_weekday = $until->format("w");
				echo $from->format("H:i:s")." - ";
				echo $until->format("H:i:s")." ";
				if($from_weekday != $until_weekday)
				{
					_e("the next day", "are-we-open");
				}
			}
			echo " <a href=\"#\" class=\"exceptions_remover\" data-exception-date=\"".$exception->date."\">[".__("Delete")."]</a>";
			echo "</p>";
		}
		echo "</pre>";
		?>
		</div>
	 </div>
	 <?php
}

function dashboard_widget(){
	
}