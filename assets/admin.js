document.addEventListener("DOMContentLoaded", function() {
    'use strict';

    var $from = jQuery("#wpucontactforms_export__wpucontactforms_export_from"),
        $to = jQuery("#wpucontactforms_export__wpucontactforms_export_to"),
        _dateFormat = 'yy-mm-dd';

    $from.datepicker({
            dateFormat: _dateFormat,
            minDate: wpucontactforms_adminobj.oldest_message,
            maxDate: wpucontactforms_adminobj.today,
            changeMonth: true,
            changeYear: true
        })
        .on("change", function() {
            $to.datepicker("option", "minDate", getDate(this));
        });

    $to.datepicker({
            dateFormat: _dateFormat,
            minDate: wpucontactforms_adminobj.oldest_message,
            maxDate: wpucontactforms_adminobj.today,
            changeMonth: true,
            changeYear: true
        })
        .on("change", function() {
            $from.datepicker("option", "maxDate", getDate(this));
        });

    function getDate(element) {
        var date;
        try {
            date = jQuery.datepicker.parseDate(_dateFormat, element.value);
        }
        catch (error) {
            date = null;
        }
        return date;
    }

});
