<?php

require 'vendor/autoload.php';
require 'include/function.php';

$p = array_change_key_case($_GET, CASE_LOWER);

$test = isset($p['test']);
$help = isset($p['help']);
$mode = isset($p['mode']) ? $p['mode'] : (isset($p['m']) ? $p['m'] : null);
$format = isset($p['format']) ? $p['format'] : (isset($p['f']) ? $p['f'] : null);
$charset = isset($p['charset']) ? $p['charset'] : (isset($p['c']) ? $p['c'] : null);
$url = isset($p['url']) ? $p['url'] : (isset($p['u']) ? $p['u'] : null);

unset($p);

if (!$url) {
  die("Error: Use query parameters with at least url=<url> as remote address\n");
}

$configuration = array();
$configuration['curl'] = true;
$configuration['debug'] = false;
$configuration['mode'] = 'curl';

$dir = dirname(__FILE__);

if (@file_exists($file = $dir . '/config.json')) {
  $json = @file_get_contents($file);
  $data = $json ? json_decode($json, true) : false;
  if ($data) {
    foreach (array('debug', 'curl', 'mode') as $key) {
      if (array_key_exists($key, $data)) {
        $configuration[$key] = $data[$key];
      }
    }
  }
}

if ($configuration['debug']) {
  error_reporting(E_ALL);
}

if (empty($mode)) $mode = $configuration['mode'];
$mode = strtolower($mode);

$url = preg_replace('/[\x00-\x1F]+/', '', $url);

if (!preg_match('/^https?:\/\//i', $url)) $url = 'http://' . $url;

switch ($format = strtolower($format)) {
  case 'text':
  case 'json':
    break;
  default:
    $format = 'html';
}

switch ($charset = strtoupper($charset)) {
  case 'ASCII':
    break;
  case 'UTF8':
  default:
    $charset = 'UTF-8';
}

$error_handler = function ($severity, $message, $file, $line) {
  throw new ErrorException($message, $severity, $severity, $file, $line);
};

set_error_handler($error_handler);

try {
  switch ($mode) {
    case 'links':
      $html = fetch_links($url);
      break;
    case 'curl':
      $html = fetch_curl($url);
      break;
    case 'php':
      $html = fetch_php($url);
      break;
  }
  if (in_array($mode, array('curl', 'php'))) {
    $html = strip_html_comment($html);
    $html = strip_html_script($html);
    $html = strip_html_style($html);
    //$html = strip_html_element_html5($html, array('style', 'head', 'svg', 'img'));
    $html = html_to_markdown($html);
    $html = markdown_eject_garbage($html);
  }
} catch (Exception $e) {
  die('Error: ' . $e->getMessage());
}

restore_error_handler();

$html = trim($html);

$html = preg_replace('/(\s*\r?\n){2,}/', "\n\n", $html);

if ($format == 'html') {
  $html = preg_replace('/\n/', "<br>\n", $html);
}

switch ($charset) {
  case 'ASCII':
    $html = iconv('UTF-8', 'US-ASCII//TRANSLIT', $html);
    break;
}

echo $html;
echo "\n";

exit(0);
