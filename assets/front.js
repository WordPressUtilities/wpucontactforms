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

    function submit_form(e) {
        e.preventDefault();
        $wrapper.addClass('contact-form-is-loading');
        $wrapper.find('button').attr('aria-disabled', 'true').attr('disabled', 'disabled');
        $wrapper.trigger('wpucontactforms_before_ajax');
        jQuery(this).ajaxSubmit({
            target: $wrapper,
            url: ajaxurl,
            success: ajax_success
        });
    }

    function ajax_success() {
        $wrapper.removeClass('contact-form-is-loading');
        $wrapper.trigger('wpucontactforms_after_ajax');
    }

    /* Events -------------------------- */

    /* Form submit */
    $wrapper.on('submit', '.wpucontactforms__form', submit_form);

    /* Special actions before AJAX send */
    $wrapper.on('wpucontactforms_before_ajax', function() {
        jQuery('html, body').animate({
            scrollTop: $wrapper.offset().top - 50
        }, 300);
    });

}
