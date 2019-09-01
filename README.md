# WPU Contact Forms

Contact forms for your WordPress website.

## Default Form

### Build form infos
```php
add_filter('wpucontactforms_default_options', 'wputest__wpucontactforms_default_options', 10, 1);
function wputest__wpucontactforms_default_options($options) {
    return array(
        'id' => 'default',
        'name' => 'My Awesome Form',
        'contact__settings' => array(
            'group_class' => 'cssc-form cssc-form--default',
            'contact_fields' => array(
                'contact_name' => array(
                    'label' => __('Name', 'wpucontactforms'),
                    'required' => 1
                ),
                'contact_email' => array(
                    'label' => __('Email', 'wpucontactforms'),
                    'type' => 'email',
                    'required' => 1
                ),
                'contact_photo' => array(
                    'label' => __('Photos', 'wpucontactforms'),
                    'type' => 'file'
                ),
                'contact_message' => array(
                    'label' => __('Message', 'wpucontactforms'),
                    'type' => 'textarea',
                    'required' => 1
                )
            ),
        )
    );
}
```

## New form

### Insert into your page

```php
<?php do_action('wpucontactforms_content', false, 'myawesomeform');?>
```

### Create form infos

```php
add_action('init', 'launch_wpucontactforms_myawesomeform');
function launch_wpucontactforms_myawesomeform() {
    if(!class_exists('wpucontactforms')){
        return;
    }
    new wpucontactforms(array(
        'id' => 'myawesomeform',
        'name' => 'My Awesome Form',
        'contact__settings' => array(
            'group_class' => 'cssc-form cssc-form--default',
        )
    ));
}
```

## Add a special action after sending fields

```php
add_action('wpucontactforms_submit_contactform', 'wpucontactforms_submit_contactform__myawesomeform', 10, 1);
function wpucontactforms_submit_contactform__myawesomeform($formObject) {
    // Debug after submit
    error_log("The form {$formObject->options['id']} has been submitted");
}
```

---

## Actions

* **wpucontactforms_content** : ($hide_wrapper = false, $form_id = false) Load contact form.
* **wpucontactforms_beforesubmit_contactform** : ($formObject) Action before form validation.
* **wpucontactforms_submit_contactform** : ($formObject) Action after form validation.

## Filters

* **wpucontactforms_fields** : (string)
* **wpucontactforms_message** : (string)
* **wpucontactforms_message___maxlinks__nb** : (string)
* **wpucontactforms_success** : (string:html)
* **wpucontactforms_settings** : (array)
* **wpucontactforms_hidden_fields** : (array)

### Settings

* **ajax_enabled** : true
* **attach_to_post** : get_the_ID()
* **box_class** : 'box'
* **box_tagname** : 'div'
* **display_form_after_submit** : true
* **file_types** :  'file_types' => array('image/png','image/jpg','image/jpeg','image/gif'),
* **group_class** : 'cssc-form cssc-form--default float-form'
* **group_submit_class** : ''
* **group_tagname** : 'div'
* **input_class** : (string) Field classname.
* **label_text_required** : '<em>*</em>'
* **li_submit_class** : ''
* **max_file_size** :  2 * 1024 * 1024
* **submit_class** : 'cssc-button cssc-button--default'
* **submit_label** : 'Submit'
* **submit_type** : 'button' or 'input'
* **ul_class** : 'cssc-form cssc-form--default float-form'

### Fields

* **value** : (string) Default value.
* **label** : (string) Field name.
* **placeholder** : (string) Placeholder text in input content.
* **classname** : (string) Field classname.
* **type** : (string) Field type : text, url, email, textarea, select, checkbox.
* **autofill** : (string) User meta ID to autocomplete if user is logged in. user_email works too.
* **datas** : (array) 1dim array setting datas for select.
* **required** : (bool) Field is required.
* **html_before** : (string) HTML before LI box.
* **html_after** : (string) HTML after LI box.
* **html_before_checkbox** : (string) HTML before a checkbox input.
* **html_after_checkbox** : (string) HTML after a checkbox input.
* **html_before_input** : (string) HTML before a common input.
* **html_after_input** : (string) HTML after a common input.
* **box_class** : (string) LI Box CSS Class
* **validation_pattern** : (string) HTML Pattern for validation.
* **validation_regexp** : (string) Regexp Pattern for validation.

### Send mail
* **wpucontactforms__sendmail_intro** : (string) HTML before fields values in mail.
* **wpucontactforms__sendmail_subject** : (string) Subject of the sent email.
