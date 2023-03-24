document.addEventListener("DOMContentLoaded", function() {
    'use strict';
    jQuery("#wpucontactforms_export_from").datepicker({
        dateFormat: 'yy-mm-dd',
        minDate: wpucontactforms_adminobj.oldest_message,
        maxDate: wpucontactforms_adminobj.today,
    });

    jQuery("#wpucontactforms_export_to").datepicker({
        dateFormat: 'yy-mm-dd',
        minDate: wpucontactforms_adminobj.oldest_message,
        maxDate: wpucontactforms_adminobj.today,
    });

});
