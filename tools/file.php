<?php

/* WPUContactForms v 1.2.1 */

/* ----------------------------------------------------------
  Check file
---------------------------------------------------------- */

if (!isset($_GET['file'])) {
    return;
}

$file_name = $_GET['file'];

$file = dirname(__FILE__) . '/' . $file_name;
if (!file_exists($file)) {
    return;
}

/* Basic upload model OR avoid / */
if (!preg_match('/^[0-9]{4}\/[0-9]{2}\/[^\.]*\.[^\.]*$/', $file_name) && strpos($file_name, "/") !== false) {
    return;
}

/* ----------------------------------------------------------
  Load WordPress
---------------------------------------------------------- */

define('WP_USE_THEMES', false);
define('FROM_EXTERNAL_FILE', false);
define('WP_ADMIN', true);

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

wp();

/* ----------------------------------------------------------
  Check user rights
---------------------------------------------------------- */

if (!is_user_logged_in()) {
    status_header(404);
    return;
}

if (!apply_filters('wpucontactforms__user_can_access_file', true, $file_name)) {
    status_header(404);
    return;
}

/* ----------------------------------------------------------
  Load file
---------------------------------------------------------- */

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file);
finfo_close($finfo);
header('Content-Type: ' . $mime);
readfile($file);
exit;
