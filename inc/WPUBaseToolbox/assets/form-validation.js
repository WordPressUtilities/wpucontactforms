document.addEventListener("DOMContentLoaded", function() {
    'use strict';
    /* Boxes */
    Array.prototype.forEach.call(document.querySelectorAll('.wpubasetoolbox-form [data-box-name]'), wpubasetoolbox_box_validation);

    /* Wizard */
    Array.prototype.forEach.call(document.querySelectorAll('.wpubasetoolbox-form[data-wizard="1"]'), wpubasetoolbox_form_setup_wizard);
});

/* ----------------------------------------------------------
  Fieldset switch
---------------------------------------------------------- */

function wpubasetoolbox_form_setup_wizard($form) {
    'use strict';
    var $fieldsets = $form.querySelectorAll('fieldset');
    var _currentFieldset = 0;
    var _nbFieldsets = $fieldsets.length;
    var $wizardSteps = $form.querySelectorAll(' .form-wizard-steps [data-go]');

    /* Display first fieldset */
    wpubasetoolbox_fieldset_display($fieldsets, _currentFieldset);

    /* On button click : change visible fieldset */
    Array.prototype.forEach.call($form.querySelectorAll(' .form-navigation [data-dir]'), function($btn) {
        $btn.querySelector('span').style['pointer-events'] = 'none';
        $btn.addEventListener('click', btn_click_event, 1);
    });
    Array.prototype.forEach.call($wizardSteps, function($btn) {
        $btn.querySelector('span').style['pointer-events'] = 'none';
        $btn.addEventListener('click', btn_click_event_go, 1);
    });

    function btn_click_event(e) {
        var _dir = e.target.getAttribute('data-dir');
        e.preventDefault();
        if (_dir == 'next') {
            /* Check if a field is invalid in this fieldset*/
            if (wpubasetoolbox_fieldset_fieldset_has_invalid_fields($fieldsets[_currentFieldset])) {
                return;
            }
            /* Allow next fieldset */
            _currentFieldset++;
        }
        else {
            /* Always allow previous fieldset */
            _currentFieldset--;
        }
        go_to_fieldset(_currentFieldset);
    }

    function btn_click_event_go(e) {
        var _target_fieldset = parseInt(e.target.getAttribute('data-go'), 10);
        e.preventDefault();
        for (var i = 0; i <= _target_fieldset; i++) {
            go_to_fieldset(i);
            /* Do not check target fieldset */
            if (i == _target_fieldset) {
                break;
            }
            /* Check if a field is invalid in this fieldset */
            if (wpubasetoolbox_fieldset_fieldset_has_invalid_fields($fieldsets[i])) {
                break;
            }
        }
    }

    function go_to_fieldset(_fieldset) {
        /* Ensure everything is ok */
        _currentFieldset = Math.max(0, _fieldset);
        _currentFieldset = Math.min(_nbFieldsets - 1, _currentFieldset);

        $form.setAttribute('data-current-fieldset', _currentFieldset);

        /* Display fieldset */
        wpubasetoolbox_fieldset_display($fieldsets, _currentFieldset);

        /* Current wizard step */
        if ($wizardSteps.length) {
            Array.prototype.forEach.call($wizardSteps, function(el) {
                el.setAttribute('data-active', 0);
            });
            $wizardSteps[_currentFieldset].setAttribute('data-active', 1);
        }

        /* Event */
        $form.dispatchEvent(new CustomEvent("wpubasetoolbox_form_set_fieldset", {
            detail: {
                id: _currentFieldset,
                item: $fieldsets[_currentFieldset]
            }
        }));
    }
}

function wpubasetoolbox_fieldset_fieldset_has_invalid_fields($fieldset) {
    'use strict';
    var $invalidFields = $fieldset.querySelectorAll(':invalid');
    Array.prototype.forEach.call($invalidFields, function(el) {
        el.dispatchEvent(new Event('change'));
    });
    return $invalidFields.length > 0;
}

function wpubasetoolbox_fieldset_display($fieldsets, _nb) {
    'use strict';
    Array.prototype.forEach.call($fieldsets, function(el) {
        el.style.display = 'none';
    });
    $fieldsets[_nb].style.display = '';
}

/* ----------------------------------------------------------
  Box validation
---------------------------------------------------------- */

function wpubasetoolbox_box_validation($box) {
    'use strict';
    var _id = $box.getAttribute('data-box-name'),
        $fields = $box.querySelectorAll('[name="' + _id + '"]'),
        $message = $box.querySelector('.wpubasetoolbox-form-validation-message'),
        _ischecking = false;

    if (!$fields.length || !$message) {
        return;
    }

    function check_field_error($tmp_field) {
        if (_ischecking) {
            return false;
        }
        _ischecking = true;
        var _valid = $tmp_field.checkValidity();
        _ischecking = false;
        if (_valid) {
            $box.setAttribute('data-box-error', '0');
            $message.innerHTML = '';
            return;
        }
        setTimeout(function() {
            window.scrollTo({
                top: $box.getBoundingClientRect().top + window.pageYOffset - 100,
                behavior: 'smooth'
            });
        }, 10);

        $box.setAttribute('data-box-error', '1');
        $message.innerHTML = $tmp_field.validationMessage;
    }

    Array.prototype.forEach.call($fields, function($tmp_field) {
        $tmp_field.addEventListener("invalid", function() {
            check_field_error($tmp_field);
        });
        $tmp_field.addEventListener("change", function() {
            check_field_error($tmp_field);
        });
    });

}
