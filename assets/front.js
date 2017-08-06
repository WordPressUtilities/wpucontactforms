/* globals jQuery,ajaxurl */

'use strict';

jQuery(document).ready(function() {
    jQuery('.wpucontactforms-form-wrapper').each(function() {
        set_wpucontactforms_form(jQuery(this));
    });
});

/* ----------------------------------------------------------
  Set Contact form
---------------------------------------------------------- */

function set_wpucontactforms_form($wrapper) {
    var $form = $wrapper.find('form');

    function submit_form(e) {
        e.preventDefault();
        $wrapper.addClass('contact-form-is-loading');
        $wrapper.find('button').attr('aria-disabled', 'true').attr('disabled', 'disabled');
        $wrapper.trigger('wpucontactforms_before_ajax');
        $form.ajaxSubmit({
            target: $wrapper,
            url: ajaxurl,
            success: ajax_success
        });
    }

    function ajax_success() {
        $wrapper.removeClass('contact-form-is-loading');
        $wrapper.trigger('wpucontactforms_after_ajax');
        jQuery('html, body').animate({
            scrollTop: $wrapper.offset().top - 150
        }, 300);
    }

    function autocompleteform(fields) {
        for (var i in fields) {
            if (!fields.hasOwnProperty(i)) {
                continue;
            }
            $wrapper.find('[name="' + i + '"]').val(fields[i]);
        }
    }

    /* Events -------------------------- */

    /* Form submit */
    $form.on('submit', submit_form);

    /* Special actions before AJAX send */
    $wrapper.on('wpucontactforms_before_ajax', function() {
        jQuery('html, body').animate({
            scrollTop: $wrapper.offset().top
        }, 300);
    });

    /* Autocomplete */
    if ($form.attr('data-autofill') == '1') {
        jQuery.post(
            ajaxurl, {
                'action': 'wpucontactforms_autofill',
                'form_id': $wrapper.find('[name="form_id"]').val(),
            },
            function(response) {
                var fields = JSON.parse(response);
                if (typeof fields !== 'object') {
                    return;
                }
                autocompleteform(fields);
            }
        );
    }

}
