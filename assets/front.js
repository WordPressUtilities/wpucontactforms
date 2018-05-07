/*jslint browser:true*/
/* globals jQuery,ajaxurl,grecaptcha */

jQuery(document).ready(function() {
    'use strict';
    jQuery('.wpucontactforms-form-wrapper').each(function() {
        set_wpucontactforms_form(jQuery(this));
    });
});

/* ----------------------------------------------------------
  Recaptcha
---------------------------------------------------------- */

function wpucontactforms_recaptcha_callback_expired() {
    'use strict';
    jQuery('[data-recaptchavalid]').attr('data-recaptchavalid', '0').trigger('wpucontactforms_expired_recaptcha');
}

function wpucontactforms_recaptcha_callback() {
    'use strict';
    jQuery('[data-recaptchavalid]').attr('data-recaptchavalid', '1').trigger('wpucontactforms_valid_recaptcha');
}

/* ----------------------------------------------------------
  Set Contact form
---------------------------------------------------------- */

function set_wpucontactforms_form($wrapper) {
    'use strict';
    var $form = $wrapper.find('form');
    if ($form.attr('data-wpucontactformset') == '1') {
        return;
    }
    var has_recaptcha = (typeof grecaptcha === 'object');
    $form.attr('data-wpucontactformset', '1');
    $form.attr('data-recaptchavalid', '0');

    function submit_form(e) {
        e.preventDefault();
        if (has_recaptcha && !grecaptcha.getResponse()) {
            $form.trigger('wpucontactforms_invalid_recaptcha');
            return;
        }
        $form.trigger('wpucontactforms_valid_recaptcha');
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
        $form = $wrapper.find('form');
        $wrapper.removeClass('contact-form-is-loading');
        $wrapper.trigger('wpucontactforms_after_ajax');
        jQuery('html, body').animate({
            scrollTop: $wrapper.offset().top - 150
        }, 300);
        if (has_recaptcha) {
            var recaptcha_item = document.querySelector('.g-recaptcha');
            if (recaptcha_item) {
                grecaptcha.render(recaptcha_item);
            }
        }
    }

    function autocompleteform(fields) {
        var $field;
        for (var i in fields) {
            if (!fields.hasOwnProperty(i)) {
                continue;
            }
            $field = $wrapper.find('[name="' + i + '"]');
            if (typeof fields[i] != 'string') {
                transformInputIntoSelect($field, fields[i]);
            }
            else {
                $field.val(fields[i]);
            }
        }
    }

    function transformInputIntoSelect($field, $values) {
        if ($values.length < 1) {
            return $field;
        }
        var $new_field = jQuery('<select></select>');

        $new_field.attr('aria-labelledby', $field.attr('aria-labelledby'));
        $new_field.attr('aria-required', $field.attr('aria-required'));
        $new_field.attr('class', $field.attr('class'));
        $new_field.attr('id', $field.attr('id'));
        $new_field.attr('name', $field.attr('name'));
        $new_field.attr('placeholder', $field.attr('placeholder'));
        if ($field.attr('required')) {
            $new_field.attr('required', $field.attr('required'));
        }

        jQuery.each($values, function(key, value) {
            $new_field.append(jQuery("<option></option>").attr("value", key).text(value));
        });

        $new_field.appendTo($field.parent());
        $field.remove();

        return $new_field;
    }

    /* Events -------------------------- */

    /* Form submit */
    $wrapper.on('submit', 'form', submit_form);

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
