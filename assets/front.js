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
    var recaptcha_item_v2 = document.querySelector('.g-recaptcha');
    var has_recaptcha_v2 = !!recaptcha_item_v2;

    /* Async loading for recaptcha */
    if (has_recaptcha_v2) {
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
            grecaptcha.render(recaptcha_item_v2);
        }
    }

    var recaptcha_item_v3 = document.querySelector('.box-recaptcha-v3');
    var has_recaptcha_v3 = !!recaptcha_item_v3;
    var recaptcha_sitekey = false;
    if (has_recaptcha_v3) {
        recaptcha_sitekey = recaptcha_item_v3.getAttribute('data-sitekey');
        if (typeof grecaptcha !== 'object') {
            /* Disable form until recaptcha has loaded */
            $form.find(':input').attr('readonly', 'true');
            /* Load recaptcha */
            (function() {
                var s = document.createElement('script');
                s.type = 'text/javascript';
                s.async = true;
                s.src = 'https://www.google.com/recaptcha/api.js?onload=wpucontactforms_callback_recaptcha&render=' + recaptcha_sitekey;
                var x = document.getElementsByTagName('script')[0];
                x.parentNode.insertBefore(s, x);
            })();
        }
    }

    $form.attr('data-wpucontactformset', '1');
    $form.attr('data-recaptchavalid', '0');

    /* Form validation */
    if (wpucontactforms_obj.enable_custom_validation == '1') {
        $form.attr('novalidate', 'novalidate');
    }

    function check_field_error($box) {
        var _hasError = false,
            _type = $box.attr('data-boxtype'),
            _id = $box.attr('data-boxid'),
            _required = $box.attr('data-required') == 'true',
            $error = $box.find('[data-error-invalid]'),
            $field = $box.find('[name="' + _id + '"]').eq(0),
            _field = $field.get(0),
            simple_fields = ['text', 'textarea', 'email', 'url', 'select', 'number', 'tel', 'file', 'checkbox'],
            multiple_fields = ['radio', 'checkbox-list'];

        /* Start without error */
        $box.attr('data-has-error', 0);
        $box.attr('data-field-ok', 0);
        $error.get(0).textContent = '';

        /* Validate simple fields */
        if (_field && simple_fields.indexOf(_type) > -1 && !_field.validity.valid) {
            var _isEmpty = _field.validity.valueMissing,
                _isInvalid = _field.validity.typeMismatch || _field.validity.patternMismatch || _field.validity.badInput;

            /* Empty field */
            if (_isEmpty && !_isInvalid) {
                _hasError = true;
                $box.attr('data-has-error', 1);
                $error.get(0).textContent += $error.attr('data-error-empty');
            }

            /* Invalid field */
            else if (_isInvalid) {
                _hasError = true;
                $box.attr('data-has-error', 1);
                $error.get(0).textContent += $error.attr('data-error-invalid');
            }

            if (_isEmpty && !_isInvalid) {
                $box.attr('data-field-ok', 1);
            }
        }

        /* Multiple fields */
        if (multiple_fields.indexOf(_type) > -1 && _required) {
            if ($box.find('[name]:checked').length < 1) {
                $box.attr('data-has-error', 1);
                _hasError = true;
                $error.get(0).textContent += $error.attr('data-error-choose');
            }
            else {
                $box.attr('data-field-ok', 1);
            }
        }

        return _hasError;
    }

    function check_form_error($form) {
        var _hasError = false;
        $form.find('[data-boxtype]').each(function() {
            var $box = jQuery(this);
            if (!check_field_error($box)) {
                return;
            }
            /* First visible error : Scroll to box */
            if (!_hasError) {
                jQuery('html,body').animate({
                    scrollTop: $box.offset().top - 100
                }, 300);
            }
            _hasError = true;
        });

        return _hasError;
    }

    function submit_form(e) {
        e.preventDefault();
        if (wpucontactforms_obj.enable_custom_validation == '1' && check_form_error(jQuery(e.target))) {
            return false;
        }
        if (has_recaptcha_v2 && !grecaptcha.getResponse()) {
            $form.trigger('wpucontactforms_invalid_recaptcha');
            return;
        }
        if (has_recaptcha_v3) {
            grecaptcha.execute(recaptcha_sitekey, {
                action: 'create_comment'
            }).then(function(token) {
                $form.prepend('<input type="hidden" name="g-recaptcha-response" value="' + token + '">');
                submit_form_trigger();
            });
            return;
        }
        submit_form_trigger();
    }

    function submit_form_trigger() {
        $form.trigger('wpucontactforms_valid_recaptcha');
        $wrapper.addClass('contact-form-is-loading');
        $wrapper.find('button').attr('aria-disabled', 'true').attr('disabled', 'disabled');
        $wrapper.trigger('wpucontactforms_before_ajax');
        $form.ajaxSubmit({
            target: $wrapper,
            url: wpucontactforms_obj.ajaxurl,
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
            $wrapper.trigger('wpucontactforms_after_success');
        }
        if (has_recaptcha_v2) {
            if (recaptcha_item_v2) {
                grecaptcha.render(recaptcha_item_v2);
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

        var $condition_fields,
            _conditions = [];

        function load_conditions() {
            $condition_fields = $wrapper.find('[data-wpucf-conditions]');
            $condition_fields.each(function() {
                _conditions.push(JSON.parse(jQuery(this).attr('data-wpucf-conditions')));
            });
        }

        load_conditions();
        $wrapper.on('wpucontactforms_after_ajax', function(e) {
            load_conditions();
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
                    if (!_tmp_item.length) {
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

            function get_condition_status(conditions_array_src) {
                var conditions_array = Object.assign({}, conditions_array_src),
                    _return_condition = true,
                    _tmpValNum,
                    _isSupCond,
                    _isInfCond,
                    _isNegativeCond;

                for (var _id in conditions_array) {
                    _isNegativeCond = conditions_array[_id].substr(0, 4) == 'not:';
                    _isSupCond = conditions_array[_id].substr(0, 1) == '>';
                    _isInfCond = conditions_array[_id].substr(0, 1) == '<';
                    if (_isNegativeCond) {
                        conditions_array[_id] = conditions_array[_id].substr(4);
                    }
                    if (_isSupCond || _isInfCond) {
                        conditions_array[_id] = parseInt(conditions_array[_id].substr(1), 10);
                    }

                    _tmp_item = getItemById(_id, conditions_array[_id]);
                    if (!_tmp_item) {
                        continue;
                    }

                    _tmp_val = _tmp_item.val();

                    var _isCheckbox = _tmp_item.attr('type') == 'checkbox';
                    var _isRadio = (_tmp_item.attr('type') == 'radio' || _tmp_item.attr('data-checkbox-list') == '1');

                    /* Textual value */
                    if (_isSupCond || _isInfCond) {
                        _tmpValNum = parseInt(_tmp_val, 10);
                        if (isNaN(_tmpValNum)) {
                            _tmpValNum = 0;
                        }
                        if (_isSupCond && _tmpValNum <= conditions_array[_id]) {
                            _return_condition = false;
                        }
                        if (_isInfCond && _tmpValNum >= conditions_array[_id]) {
                            _return_condition = false;
                        }
                    }
                    if (!_isRadio && !_isCheckbox && !_isSupCond && !_isInfCond) {
                        if (_isNegativeCond && _tmp_val == conditions_array[_id]) {
                            _return_condition = false;
                        }
                        if (!_isNegativeCond && _tmp_val != conditions_array[_id]) {
                            _return_condition = false;
                        }
                    }

                    /* Checkbox */
                    if (_isCheckbox) {
                        if (_tmp_item.prop('checked') && conditions_array[_id] != 'checked') {
                            _return_condition = false;
                        }
                        if (!_tmp_item.prop('checked') && conditions_array[_id] != 'notchecked') {
                            _return_condition = false;
                        }
                    }

                    /* Radio */
                    if (_isRadio) {
                        if (_isNegativeCond) {
                            _return_condition = !_tmp_item.get(0).checked;
                        }
                        else {
                            _return_condition = _tmp_item.get(0).checked;
                        }
                    }
                }
                return _return_condition;
            }

            /* Change target block display */
            (function() {
                if (!_condition.display) {
                    return;
                }
                /* Block will be shown if no condition is invalid */
                var _showblock = get_condition_status(_condition.display);
                if (_showblock) {
                    $blockWrapper.attr('data-displayed', 1).show();
                }
                else {
                    $blockWrapper.attr('data-displayed', 0).hide();
                }
                $blockWrapper.trigger('wpucontactforms_block_change_display');
            }());

            /* Change target block required */
            (function() {
                if (!_condition.required) {
                    return;
                }
                /* Block will not be required if a condition is invalid */
                var _required = get_condition_status(_condition.required);
                var _requiredStr = _required.toString();
                var _blockType = $blockWrapper.attr('data-boxtype');
                $blockWrapper.attr('data-required', _requiredStr);
                $blockField.attr('aria-required', _requiredStr);
                if (_blockType == 'checkbox-list') {
                    if (!_required || (_required && $blockField.is(':checked'))) {
                        $blockField.removeAttr('required');
                    }
                    if (_required && !$blockField.is(':checked')) {
                        $blockField.attr('required', 'required');
                    }
                }
                if (_blockType != 'checkbox-list') {
                    if (_required) {
                        $blockField.attr('required', 'required');
                    }
                    else {
                        $blockField.removeAttr('required');
                    }
                }
                $blockField.trigger('wpucontactforms_block_change_required');
            }());

        }

        function set_fields_by_condition() {
            $condition_fields.each(set_field_by_condition);
        }

        set_fields_by_condition();
        $wrapper.on('change blur', '[name]', debounce(set_fields_by_condition, 300));
        $wrapper.on('wpucontactforms_after_ajax', function(e) {
            set_fields_by_condition();
        });
        $wrapper.on('keyup', '[name]', debounce(set_fields_by_condition, 300));

    }());

    /* Events -------------------------- */

    /* Field validation */
    if (wpucontactforms_obj.enable_custom_validation == '1') {
        $wrapper.on('change blur', '[name]', function() {
            check_field_error(jQuery(this).closest('[data-boxtype]'));
        });
    }

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
            wpucontactforms_obj.ajaxurl, {
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

    function debounce(callback, delay) {
        var timer;
        if (!delay) {
            delay = 300;
        }
        return function() {
            var args = arguments;
            var context = this;
            clearTimeout(timer);
            timer = setTimeout(function() {
                callback.apply(context, args);
            }, delay);
        };
    }
}
