<?php

/* WPUContactForms v 1.0.0 */

/* ----------------------------------------------------------
  Check file
---------------------------------------------------------- */

if (!isset($_GET['file'])) {
    return;
}

$file = dirname(__FILE__) . '/' . $_GET['file'];
if (!file_exists($file)) {
    return;
}

/* Avoid / */
if (strpos($_GET['file'], "/") !== false) {
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
    return;
}

/* ----------------------------------------------------------
  Load file
---------------------------------------------------------- */

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file);
finfo_close($finfo);
header('Content-Type: ' . $mime);
include $_GET['file'];
