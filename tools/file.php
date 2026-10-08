<?php
/* WPUContactForms v 3.18.1 */

/* ----------------------------------------------------------
  Check file
---------------------------------------------------------- */

if (!isset($_GET['file'])) {
    return;
}

define('WPUCONTACTFORMS_UPLOADS_INDEX_FNAME', $_GET['file']);

define('WPUCONTACTFORMS_UPLOADS_INDEX_FILE', dirname(__FILE__) . '/' . WPUCONTACTFORMS_UPLOADS_INDEX_FNAME);
if (!file_exists(WPUCONTACTFORMS_UPLOADS_INDEX_FILE)) {
    return;
}

/* Basic upload model OR avoid / */
if (!preg_match('/^[0-9]{4}\/[0-9]{2}\/[^\.\/]{1}([a-zA-Z0-9_\-\s\.]*)$/', WPUCONTACTFORMS_UPLOADS_INDEX_FNAME) && strpos(WPUCONTACTFORMS_UPLOADS_INDEX_FNAME, "/") !== false) {
    return;
}

/* ----------------------------------------------------------
  Load WordPress
---------------------------------------------------------- */

define('WP_USE_THEMES', false);
define('FROM_EXTERNAL_FILE', false);
define('WP_ADMIN', false);

/* Load WordPress */
chdir(dirname(__FILE__));
$bootstrap = 'wp-load.php';
while (!is_file($bootstrap)) {
    if (is_dir('..') && getcwd() != '/') {
        chdir('..');
    } else {
        die('EN: Could not find WordPress! FR : Impossible de trouver WordPress !');
    }
}
require_once $bootstrap;

/* ----------------------------------------------------------
  Check user rights
---------------------------------------------------------- */

if (!is_user_logged_in()) {
    status_header(404);
    return;
}

if(!current_user_can('upload_files')){
    status_header(404);
    return;
}

if (!apply_filters('wpucontactforms__user_can_access_file', true, WPUCONTACTFORMS_UPLOADS_INDEX_FNAME)) {
    status_header(404);
    return;
}

/* ----------------------------------------------------------
  Load file
---------------------------------------------------------- */

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, WPUCONTACTFORMS_UPLOADS_INDEX_FILE);
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize(WPUCONTACTFORMS_UPLOADS_INDEX_FILE));
readfile(WPUCONTACTFORMS_UPLOADS_INDEX_FILE);
exit;
