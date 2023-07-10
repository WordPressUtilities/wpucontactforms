/*jslint browser:true*/
/* globals jQuery,ajaxurl,ga,grecaptcha,wpucontactforms_obj */

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

/* Enable form when recaptcha has loaded */
function wpucontactforms_callback_recaptcha() {
    'use strict';
    jQuery('.wpucontactforms-form-wrapper').find('form').each(function() {
        jQuery(this).find(':input').removeAttr('readonly');
    });
}

function wpucontactforms_load_recaptcha_v2() {
    'use strict';
    if (typeof grecaptcha == 'object') {
        return;
    }
    wpucontactforms_load_js('https://www.google.com/recaptcha/api.js?onload=wpucontactforms_callback_recaptcha');
}

function wpucontactforms_load_recaptcha_v3(recaptcha_sitekey) {
    'use strict';
    if (typeof grecaptcha == 'object') {
        return;
    }
    wpucontactforms_load_js('https://www.google.com/recaptcha/api.js?onload=wpucontactforms_callback_recaptcha&render=' + recaptcha_sitekey);
}

/* Turnstile
-------------------------- */

function wpucontactforms_load_recaptcha_turnstile() {
    'use strict';
    if (typeof turnstile == 'object') {
        return;
    }
    wpucontactforms_load_js('https://challenges.cloudflare.com/turnstile/v0/api.js?onload=wpucontactforms_callback_recaptcha_turnstile');
}

function wpucontactforms_callback_recaptcha_turnstile() {
    'use strict';
    wpucontactforms_refresh_recaptcha_turnstile();
    wpucontactforms_callback_recaptcha();
}

function wpucontactforms_refresh_recaptcha_turnstile() {
    'use strict';
    Array.prototype.forEach.call(document.querySelectorAll('.box-recaptcha-turnstile[data-sitekey]'), function(el, i) {
        if (el.classList.contains('cf-turnstile') || el.classList.contains('has-turnstile-enabled')) {
            return;
        }
        el.classList.add('has-turnstile-enabled');
        turnstile.render(el, {
            sitekey: el.getAttribute('data-sitekey')
        });
    });
}

/* hCaptcha
-------------------------- */

function wpucontactforms_load_recaptcha_hcaptcha() {
    'use strict';
    if (typeof hcaptcha == 'object') {
        return;
    }
    wpucontactforms_load_js('https://js.hcaptcha.com/1/api.js?onload=wpucontactforms_callback_recaptcha_hcaptcha');
}

function wpucontactforms_callback_recaptcha_hcaptcha() {
    'use strict';
    wpucontactforms_refresh_recaptcha_hcaptcha();
    wpucontactforms_callback_recaptcha();
}

function wpucontactforms_refresh_recaptcha_hcaptcha() {
    'use strict';
    Array.prototype.forEach.call(document.querySelectorAll('.box-recaptcha-hcaptcha[data-sitekey]'), function(el, i) {
        if (el.classList.contains('has-hcaptcha-enabled')) {
            return;
        }
        el.classList.add('has-hcaptcha-enabled');
        hcaptcha.render(el, {
            sitekey: el.getAttribute('data-sitekey')
        });
    });
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

    /* Recaptcha */
    var recaptcha_item_v2 = document.querySelector('.g-recaptcha');
    var has_recaptcha_v2 = !!recaptcha_item_v2;

    /* Turnstile */
    var recaptcha_item_turnstile = document.querySelector('.box-recaptcha-turnstile');
    var has_recaptcha_turnstile = !!recaptcha_item_turnstile;

    /* hcaptcha */
    var recaptcha_item_hcaptcha = document.querySelector('.box-recaptcha-hcaptcha');
    var has_recaptcha_hcaptcha = !!recaptcha_item_hcaptcha;

    var _disposable_domains = JSON.parse(atob(wpucontactforms_obj.disposable_domains));
    if($form.attr('data-disposable-domains')){
        var extra_disposable_domains = JSON.parse(atob($form.attr('data-disposable-domains')));
        if(typeof extra_disposable_domains == 'object'){
            _disposable_domains = _disposable_domains.concat(extra_disposable_domains);
        }
    }

    $form.find('.fake-upload-wrapper').each(function(i, el) {

        /* Handle dragndrop */
        var _timer,
            $wrap = jQuery(el);
        $wrap.on('dragover', function(e) {
            var dt = e.originalEvent.dataTransfer;
            if (dt.types && (dt.types.indexOf ? dt.types.indexOf('Files') != -1 : dt.types.contains('Files'))) {
                $wrap.attr('data-is-dragging', '1');
                window.clearTimeout(_timer);
            }
        });
        $wrap.on('dragleave', function() {
            _timer = window.setTimeout(function() {
                $wrap.attr('data-is-dragging', '0');
            }, 25);
        });

        $wrap.on('change', 'input[type="file"]', function() {
            var $cover = $wrap.find('[data-placeholder]');
            $wrap.attr('data-is-dragging', '0');

            if (this.value) {
                $wrap.attr('data-has-value', '1');
                $cover.text(this.value.replace(/\\/g, '/').split('/').reverse()[0]);
            }
            else {
                $wrap.attr('data-has-value', '0');
                $cover.html($cover.attr('data-placeholder'));
            }
        });

    });

    $form.on('click', '[data-error-suggest][data-new-value]', function(e) {
        e.preventDefault();
        var _val = this.getAttribute('data-new-value');
        if (!_val) {
            return;
        }
        var $input = jQuery(this).closest('[data-boxtype]').find('[name][aria-labelledby]');
        $input.val(_val);
        $input.trigger('change');
    });

    /* Async loading for recaptcha */
    if (has_recaptcha_v2) {
        /* Init recaptcha */
        if (typeof grecaptcha !== 'object') {
            /* Disable form until recaptcha has loaded */
            $form.find(':input').attr('readonly', 'true');
            /* Load recaptcha */
            wpucontactforms_load_recaptcha_v2();
        }
        /* Init recaptcha */
        else {
            grecaptcha.render(recaptcha_item_v2);
        }
    }

    if (has_recaptcha_turnstile) {
        /* Init recaptcha */
        if (typeof turnstile !== 'object') {
            /* Disable form until recaptcha has loaded */
            $form.find(':input').attr('readonly', 'true');
            /* Load recaptcha */
            wpucontactforms_load_recaptcha_turnstile();
        }
        /* Init recaptcha */
        else {
            wpucontactforms_refresh_recaptcha_turnstile();
        }
    }

    if (has_recaptcha_hcaptcha) {
        /* Init recaptcha */
        if (typeof hcaptcha !== 'object') {
            /* Disable form until recaptcha has loaded */
            $form.find(':input').attr('readonly', 'true');
            /* Load recaptcha */
            wpucontactforms_load_recaptcha_hcaptcha();
        }
        /* Init recaptcha */
        else {
            wpucontactforms_refresh_recaptcha_hcaptcha();
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
            wpucontactforms_load_recaptcha_v3(recaptcha_sitekey);
        }
    }

    /* Datepicker */
    $form.find('input[data-wpucontactforms-datepicker]').each(function(i, el) {
        var $item = jQuery(el),
            _args = JSON.parse($item.attr('data-wpucontactforms-datepicker'));

        _args.beforeShow = function(input, inst) {
            inst.dpDiv.addClass('wpucontactforms-datepicker');
        };
        $item.datepicker(_args);
    });

    $form.attr('data-wpucontactformset', '1');
    $form.attr('data-recaptchavalid', '0');

    /* Form validation */
    if (wpucontactforms_obj.enable_custom_validation == '1') {
        $form.attr('novalidate', 'novalidate');
    }

    var _domains = ['aol.com', 'comcast.net', 'free.fr', 'gmail.com', 'gmx.de', 'googlemail.com', 'hotmail.co.uk', 'hotmail.com', 'hotmail.fr', 'hotmail.it', 'libero.it', 'live.co.uk', 'live.com', 'live.fr', 'mail.ru', 'msn.com', 'orange.fr', 'outlook.com', 'rediffmail.com', 'sbcglobal.net', 'sfr.fr', 'uol.com.br', 'verizon.net', 'wanadoo.fr', 'web.de', 'yahoo.co.in', 'yahoo.co.uk', 'yahoo.com', 'yahoo.com.br', 'yahoo.es', 'yahoo.fr', 'yandex.ru', 'ymail.com'];
    var field_suggestions = [];
    _domains.forEach(function(_domain) {
        var _domain_no_dots = _domain.replace(/\./g, '');
        field_suggestions.push({
            'type': ['email'],
            'regexp': new RegExp('@' + _domain_no_dots + '$', 'g'),
            'fix': function(val) {
                return val.replace(_domain_no_dots, _domain);
            }
        });
    });

    function check_field_error($box) {
        var _hasError = false,
            _type = $box.attr('data-boxtype'),
            _id = $box.attr('data-boxid'),
            _required = $box.attr('data-required') == 'true',
            $error = $box.find('[data-error-invalid]'),
            $field = $box.find('[name="' + _id + '"]').eq(0),
            $form = $box.closest('form'),
            $fieldMult = $box.find('[name="' + _id + '[]"]').eq(0),
            _field = $field.get(0),
            _fieldMult = $fieldMult.get(0),
            simple_fields = ['text', 'textarea', 'email', 'url', 'select', 'number', 'tel', 'file', 'checkbox'],
            multiple_fields = ['radio', 'checkbox-list'];

        /* Start without error */
        $box.attr('data-has-error', 0);
        $box.attr('data-field-ok', 0);
        $error.get(0).removeAttribute('data-new-value');
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

            /* Custom error messages for some specific fields */
            if (_field.validationMessage && !_field.validity.valueMissing) {
                if (_type == 'number') {
                    _hasError = true;
                    $box.attr('data-has-error', 1);
                    $error.get(0).textContent = _field.validationMessage;
                }
            }

            if (_isEmpty && !_isInvalid) {
                $box.attr('data-field-ok', 1);
            }
        }

        /* Emails */
        (function() {
            if ($form.attr('data-disallow-temp-email') != '1' || _isInvalid || _type != 'email' || !_field) {
                return;
            }
            for (var i = 0, len = _disposable_domains.length; i < len; i++) {
                if (_field.value.indexOf('@' + _disposable_domains[i]) !== -1) {
                    _hasError = true;
                    $box.attr('data-has-error', 1);
                    $error.get(0).textContent += $error.attr('data-error-invalid');
                    return false;
                }
            }
        }());

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

        if (_fieldMult && _required && !_hasError && !_fieldMult.value) {
            _hasError = true;
            $box.attr('data-has-error', 1);
            $error.get(0).textContent += $error.attr('data-error-empty');
        }

        (function() {
            var _fieldFile = _fieldMult ? _fieldMult : _field;
            if (_type != 'file' || !_fieldFile) {
                return;
            }
            var _errorParts = [];
            for (var i = 0; i < _fieldFile.files.length; i++) {
                if (_fieldFile.files[i] && _fieldFile.files[i].size >= parseInt($form.attr('data-max-file-size'))) {
                    _hasError = true;
                    $box.attr('data-has-error', 1);
                    _errorParts.push($error.attr('data-error-file-heavy').replace('%s', Math.round(_fieldFile.files[i].size / 1000 / 100) / 10));
                }
            }
            if (_errorParts.length) {
                $error.get(0).textContent += _errorParts.join(', ');
            }
        }());

        /* Try suggestions */
        if (_field) {
            var _newValue = _field.value;
            for (var i = 0, len = field_suggestions.length; i < len; i++) {
                /* Test type */
                if (field_suggestions[i].type.indexOf(_type) < 0) {
                    continue;
                }
                /* Test detection */
                if (!_field.value.match(field_suggestions[i].regexp)) {
                    continue;
                }
                /* Suggest new value */
                _newValue = field_suggestions[i].fix(_newValue);
                _hasError = true;
                $box.attr('data-has-error', 1);
                $error.get(0).setAttribute('data-new-value', _newValue);
                $error.get(0).textContent += $error.attr('data-error-suggest').replace('%s', _newValue);
            }
        }

        return _hasError;
    }

    function check_form_error($form, only_visible) {
        var _hasError = false;
        only_visible = only_visible || false;
        var _boxes_selector = '[data-wpucontactforms-group="1"][data-visible="1"] [data-boxtype]';
        if (!only_visible) {
            _boxes_selector = '[data-wpucontactforms-group="1"] [data-boxtype]';
        }
        $form.find(_boxes_selector).each(function() {
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
        var $form = jQuery(e.target);
        e.preventDefault();

        /* Go to next */
        var $nextButton = $form.find('[data-wpucontactforms-group="1"][data-visible="1"] button[data-type="next"]');
        if ($nextButton.length) {
            $nextButton.trigger('click');
            return;
        }

        /* Check errors */
        if (wpucontactforms_obj.enable_custom_validation == '1' && check_form_error($form)) {
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
        if (has_recaptcha_v2 && recaptcha_item_v2) {
            grecaptcha.render(recaptcha_item_v2);
        }
        if (has_recaptcha_turnstile) {
            wpucontactforms_refresh_recaptcha_turnstile();
        }
        if (has_recaptcha_hcaptcha) {
            wpucontactforms_refresh_recaptcha_hcaptcha();
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
        $wrapper.on('wpucontactforms_after_ajax', function() {
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
                    _isInListCond,
                    _isNotInListCond,
                    _isNegativeCond;

                for (var _id in conditions_array) {
                    _isNegativeCond = conditions_array[_id].substr(0, 4) == 'not:';
                    _isInListCond = conditions_array[_id].substr(0, 3) == 'in:';
                    _isNotInListCond = conditions_array[_id].substr(0, 6) == 'notin:';
                    _isSupCond = conditions_array[_id].substr(0, 1) == '>';
                    _isInfCond = conditions_array[_id].substr(0, 1) == '<';
                    if (_isNegativeCond) {
                        conditions_array[_id] = conditions_array[_id].substr(4);
                    }
                    if (_isInListCond) {
                        conditions_array[_id] = conditions_array[_id].substr(3).split(',');
                    }
                    if (_isNotInListCond) {
                        conditions_array[_id] = conditions_array[_id].substr(6).split(',');
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
                    if (_isCheckbox && !_isInListCond && !_isNotInListCond) {
                        if (_tmp_item.prop('checked') && conditions_array[_id] != 'checked') {
                            _return_condition = false;
                        }
                        if (!_tmp_item.prop('checked') && conditions_array[_id] != 'notchecked') {
                            _return_condition = false;
                        }
                    }

                    /* Radio */
                    if (_isRadio && !_isInListCond && !_isNotInListCond) {
                        if (_isNegativeCond) {
                            _return_condition = !_tmp_item.get(0).checked;
                        }
                        else {
                            _return_condition = _tmp_item.get(0).checked;
                        }
                    }

                    if (_isInListCond) {
                        _return_condition = false;
                        _tmp_item.each(function(a, el) {
                            if (conditions_array[_id].indexOf(el.value) > -1 && el.checked) {
                                _return_condition = true;
                            }
                        });
                    }

                    if (_isNotInListCond) {
                        _return_condition = true;
                        _tmp_item.each(function(a, el) {
                            if (conditions_array[_id].indexOf(el.value) > -1 && el.checked) {
                                _return_condition = false;
                            }
                        });
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
        $wrapper.on('wpucontactforms_after_ajax', function() {
            set_fields_by_condition();
        });
        $wrapper.on('keyup', '[name]', debounce(set_fields_by_condition, 300));

    }());

    /* Events -------------------------- */

    /* Quick event watch */
    $wrapper.on('change', 'input[data-checkbox-list="1"]', function(e) {
        e.target.parentNode.setAttribute('data-checked', e.target.checked ? '1' : '0');
    });

    /* Field validation */
    if (wpucontactforms_obj.enable_custom_validation == '1') {
        $wrapper.on('change blur', '[name]', function() {
            check_field_error(jQuery(this).closest('[data-boxtype]'));
        });
    }

    /* Form submit */
    $wrapper.on('submit', 'form', submit_form);

    /* Intermediate submit */

    function switch_fieldset($currentGroup, $newGroup) {
        if (!$newGroup.length) {
            return;
        }
        $wrapper.trigger('wpucontactforms_going_next_fieldset');
        $currentGroup.attr('data-visible', '0');
        $currentGroup.trigger('wpucontactforms_fieldset_hiding');
        $newGroup.attr('data-visible', '1');
        $newGroup.trigger('wpucontactforms_fieldset_showing');
    }

    /* Handle steps */
    $wrapper.on('click', '[data-form-step-goto]', function(e) {
        e.preventDefault();
        var $currentGroup = $wrapper.find('[data-wpucontactforms-group="1"][data-visible="1"]');
        var $newGroup = $wrapper.find('[data-wpucontactforms-group="1"][data-wpucontactforms-group-id="' + jQuery(this).attr('data-form-step-goto') + '"]');
        switch_fieldset($currentGroup, $newGroup);
    });

    /* Previous button */
    $wrapper.on('click', '[data-wpucontactforms-group="1"][data-visible="1"] button[data-type="previous"]', function(e) {
        e.preventDefault();
        var $currentGroup = $wrapper.find('[data-wpucontactforms-group="1"][data-visible="1"]');
        var $newGroup = $currentGroup.prev('[data-wpucontactforms-group="1"]');
        switch_fieldset($currentGroup, $newGroup);
    });

    /* Next button */
    $wrapper.on('click', '[data-wpucontactforms-group="1"][data-visible="1"] button[data-type="next"]', function() {
        var _hasError = check_form_error($wrapper.find('form'), true);
        if (_hasError) {
            return;
        }
        var $currentGroup = $wrapper.find('[data-wpucontactforms-group="1"][data-visible="1"]');
        var $newGroup = $currentGroup.next('[data-wpucontactforms-group="1"]');
        switch_fieldset($currentGroup, $newGroup);
    });

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

/* ----------------------------------------------------------
  Helpers
---------------------------------------------------------- */

function wpucontactforms_load_js(js_url) {
    var script_id = js_url.replace(/[\W_]+/g, "");
    if (document.querySelector('[data-script="' + script_id + '"]')) {
        return false;
    }
    var s = document.createElement('script');
    s.type = 'text/javascript';
    s.async = true;
    s.src = js_url;
    var x = document.getElementsByTagName('script')[0];
    x.setAttribute('data-script', script_id);
    x.parentNode.insertBefore(s, x);
}
