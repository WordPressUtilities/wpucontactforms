# WPU Contact Forms

Contact forms for your WordPress website.

## Insert into your page

```php
<?php do_action('wputh_contact_content', false, 'default'); ?>
```

## Create a new form

```php
add_action('init', 'launch_wpucontactforms_myawesomeform');
function launch_wpucontactforms_myawesomeform() {
    new wpucontactforms(array('id' => 'myawesomeform'));
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
* **wpucontactforms_submit_contactform** : ($formObject) Action after form validation.

## Filters

* **wpucontactforms_fields** : (string)
* **wpucontactforms_message** : (string)
* **wpucontactforms_message___maxlinks__nb** : (string)
* **wpucontactforms_success** : (string:html)
* **wpucontactforms_settings** : (array)

### Settings

* **ul_class** : 'cssc-form cssc-form--default float-form'
* **box_class** : 'box'
* **submit_class** : 'cssc-button cssc-button--default'
* **submit_label** : 'Submit'
* **li_submit_class** : ''

### Fields

* **value** : (string) Default value.
* **label** : (string) Field name.
* **type** : (string) Field type : text, url, email, textarea, select.
* **datas** : (array) 1dim array setting datas for select.
* **required** : (bool) Field is required.
* **html_before** : (string) HTML before LI box.
* **html_after** : (string) HTML after LI box.
* **box_class** : (string) LI Box CSS Class
