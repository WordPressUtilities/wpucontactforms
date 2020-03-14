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

/* Enable form when recaptcha has loaded */
function wpucontactforms_callback_recaptcha() {
    jQuery('.wpucontactforms-form-wrapper').find('form').each(function() {
        jQuery(this).find(':input').removeAttr('readonly');
    });
}

function set_wpucontactforms_form($wrapper) {
    'use strict';
    var $form = $wrapper.find('form');
    if ($form.attr('data-wpucontactformset') == '1') {
        return;
    }
    var recaptcha_item = document.querySelector('.g-recaptcha');
    var has_recaptcha = !!recaptcha_item;
    /* Async loading for recaptcha */
    if (has_recaptcha) {
        /* Init recaptcha */
        if (typeof grecaptcha !== 'object') {
            /* Disable form until recaptcha has loaded */
            $form.find(':input').attr('readonly', 'true');
            /* Load recaptcha */
            (function() {
                var s = document.createElement('script');
                s.type = 'text/javascript';
                s.async = true;
                s.src = 'https://www.google.com/recaptcha/api.js?onload=wpucontactforms_callback_recaptcha';
                var x = document.getElementsByTagName('script')[0];
                x.parentNode.insertBefore(s, x);
            })();
        }
        /* Init recaptcha */
        else {
            grecaptcha.render(recaptcha_item);
        }
    }
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
        if ($form.find('.contact-error').length < 1) {
            if (typeof ga != 'undefined') {
                ga('send', 'event', 'wpucontactforms_success');
            }
            $form.addClass('form--has-success');
        }
        if (has_recaptcha) {
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

    /* Conditions */
    (function() {

        var $condition_fields = $wrapper.find('[data-wpucf-conditions]'),
            _conditions = [];

        $condition_fields.each(function() {
            _conditions.push(JSON.parse(jQuery(this).attr('data-wpucf-conditions')));
        });

        function set_field_by_condition(i, el) {
            var $blockWrapper = jQuery(el),
                $blockField = $blockWrapper.find('[aria-required]'),
                _condition = _conditions[i],
                _tmp_item,
                _tmp_val;

            function getItemById(_id, _value) {
                var _tmp_item = $wrapper.find('[name="' + _id + '"]');
                if (!_tmp_item.length) {
                    _tmp_item = $wrapper.find('[name="' + _id + '[]"]');
                    if(!_tmp_item.length){
                        return false;
                    }
                }
                if (_tmp_item.attr('type') == 'radio') {
                    _tmp_item = $wrapper.find('[name="' + _id + '"][value="' + _value + '"]');
                }
                if (!_tmp_item.length) {
                    return false;
                }
                return _tmp_item;
            }

            /* Change target block display */
            (function() {
                if (!_condition.display) {
                    return;
                }
                /* Block will be shown if no condition is invalid */
                var _showblock = true;
                for (var _id in _condition.display) {
                    _tmp_item = getItemById(_id, _condition.display[_id]);
                    if (!_tmp_item) {
                        continue;
                    }

                    _tmp_val = _tmp_item.val();
                    if (_tmp_val != _condition.display[_id]) {
                        _showblock = false;
                    }
                    if (_condition.display[_id] == 'checked' && _tmp_item.attr('type') == 'checkbox' && _tmp_item.prop('checked')) {
                        _showblock = true;
                    }
                    if (_tmp_item.attr('type') == 'radio' || _tmp_item.attr('data-checkbox-list') == '1') {
                        if (!_tmp_item.get(0).checked) {
                            _showblock = false;
                        }
                    }
                }

                if (_showblock) {
                    $blockWrapper.show();
                }
                else {
                    $blockWrapper.hide();
                }
            }());

            /* Change target block required */
            (function() {
                if (!_condition.required) {
                    return;
                }
                /* Block will not be required if a condition is invalid */
                var _required = true;
                for (var _id in _condition.display) {
                    _tmp_item = getItemById(_id, _condition.display[_id]);
                    if (!_tmp_item) {
                        continue;
                    }
                    _tmp_val = _tmp_item.val();
                    if (_tmp_val != _condition.display[_id]) {
                        _required = false;
                    }
                    if (_condition.display[_id] == 'checked' && _tmp_item.attr('type') == 'checkbox' && _tmp_item.prop('checked')) {
                        _required = true;
                    }
                    if (_tmp_item.attr('type') == 'radio' || _tmp_item.attr('data-checkbox-list') == '1') {
                        if (!_tmp_item.get(0).checked) {
                            _required = false;
                        }
                    }
                }

                var _requiredStr = _required.toString();
                $blockWrapper.attr('data-required', _requiredStr);
                $blockField.attr('aria-required', _requiredStr);
                if (_required) {
                    $blockField.attr('required', 'required');
                }
                else {
                    $blockField.removeAttr('required');
                }
            }());

        }

        function set_fields_by_condition() {
            $condition_fields.each(set_field_by_condition);
        }

        set_fields_by_condition();
        $wrapper.on('change blur', '[name]', set_fields_by_condition);
        (function() {
            var _timeout = false;
            $wrapper.on('keyup', '[name]', function() {
                clearTimeout(_timeout);
                _timeout = setTimeout(set_fields_by_condition, 300);
            });
        }());
    }());

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
