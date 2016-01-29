# WPU Contact Forms

Contact forms for your WordPress website.

## Actions

* **wpucontactforms_content** : Load contact form.

## Hooks

* **disable_wpucontactforms** : (bool:false) Disable contact form.
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
