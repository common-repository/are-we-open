var days_of_week = translation.days_of_week;

jQuery( function() {
	/* Polish initialisation for the jQuery UI date picker plugin. */
	/* Written by Jacek Wysocki (jacek.wysocki@gmail.com). */
	jQuery.datepicker.setDefaults({
		closeText: 'Zamknij',
		prevText: '&#x3C;Poprzedni',
		nextText: 'Następny&#x3E;',
		currentText: 'Dziś',
		monthNames: ['Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec',
		'Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'],
		monthNamesShort: ['Sty','Lu','Mar','Kw','Maj','Cze',
		'Lip','Sie','Wrz','Pa','Lis','Gru'],
		dayNames: ['Niedziela','Poniedziałek','Wtorek','Środa','Czwartek','Piątek','Sobota'],
		dayNamesShort: ['Nie','Pn','Wt','Śr','Czw','Pt','So'],
		dayNamesMin: ['N','Pn','Wt','Śr','Cz','Pt','So'],
		weekHeader: 'Tydz',
		dateFormat: 'dd.mm.yy',
		firstDay: 1,
		isRTL: false,
		showMonthAfterYear: false,
		yearSuffix: ''});
	/** for dynamically generated elements bind event to body  */
	jQuery("body").on("change", ".awo-checkbox", function(){
		var checked = jQuery(this).prop("checked");
		var range = jQuery(this).parent().find(".awo_range_field");
		range.prop("disabled", checked);
	});
	jQuery("body").on("click", ".duplicate", function(event){
		event.preventDefault();
		var target = jQuery(this).data("target");
		jQuery(target+" .awo_range_field").each(function(){
			var range = jQuery(target+" .awo_range_field").first().val();
			jQuery(this).val(range);
		});
	});
	jQuery(".clear").click(function(event){
		event.preventDefault();
		var target = jQuery(this).data("target");
		jQuery(target+" .awo_range_field").each(function(){
			jQuery(this).val("");
		});
	});
	jQuery(".date-pick").datepicker({
		dateFormat: "yy-mm-dd",
		showOtherMonths: true,
		selectOtherMonths: true,
		firstDay: 1
	});
	jQuery("#exceptions").submit(function(){
		var valid = true;
		jQuery("#exceptions .awo_range_field").each(function(){
			/** do not send empty fields, unless "closed" is checked */
			if(jQuery(this).val()==""){
				if(jQuery(this).parent().find("input[type='checkbox']").prop("checked") == false)
				{
					valid=false;
				}
			}
		});
	if(valid==false){
		alert(translation.empty_fields);
	}
	return valid;
	});
	function awo_datepicker_change()
	{
		var from = new Date(jQuery("#exception-from").val());
		var until = new Date(jQuery("#exception-until").val());

		if( isNaN(from.getTime()) || isNaN(until.getTime()) )
		{
			return false;
		}
		var delta = until - from;
		if(delta>=0){
			var days_delta = Math.floor(delta / (24 * 3600 * 1000));
			
			/** if selected period is shorter than a week */
			if(days_delta < 7){

				var from_dow = from.getDay();
				if(from_dow == 0){ from_dow = 7; }
				var until_dow = until.getDay();
				if(until_dow == 0){ until_dow = 7; }

				jQuery("#exceptions-container").html(days_of_week);

				jQuery("#exceptions .awo_range_field").each(function(){

					var select_dow = jQuery(this).attr("name").substring(0,1);

					if(until_dow >= from_dow && (select_dow < from_dow || select_dow > until_dow) ){
						/** @example: 1[2]34[5]67 - deletes 1,6,7; given:
						 * [2] - weekday from
						 * [5] - weekday until
						 */
						jQuery(this).parent().remove();
					}
					else if (select_dow > until_dow && select_dow < from_dow)
					{
						/** @example: 1[2]34[5]67 - deletes 3,4; given:
						 * [5] - weekday from
						 * [2] - weekday until
						 */
						jQuery(this).parent().remove();
					}
				});
			}
			else {
				jQuery("#exceptions-container").html(days_of_week);
			}
		}
		else{
			alert(translation.until_from);
			return false;
		}
	}
	jQuery("#exception-from").change(function(){
		awo_datepicker_change();
	});
	jQuery("#exception-until").change(function(){
		awo_datepicker_change();
	});
	jQuery(".exceptions_remover").click(function(event){
		event.preventDefault();
		if(window.confirm(translation.confirm_removing_exc)){
			var date = jQuery(this).data("exception-date");
			jQuery.post(ajaxurl, {
				'action': 'delete_exception',
				'exception_date': date
			},
			function(response){
				if(response=="ok"){
					jQuery(event.target).parent().remove();
				}
				else
				{
					console.log(translation.removal_failed);
				}
			});
		}
	});
});