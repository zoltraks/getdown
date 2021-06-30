<?php

require 'vendor/autoload.php';

use League\HTMLToMarkdown\HtmlConverter;
use League\HTMLToMarkdown\Converter\TableConverter;
use Masterminds\HTML5;

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
  case 'html': 
  case 'json': 
    break;
  default: 
    $format = 'text';
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

function fetch_links($url) {
  $esc = addslashes($url);
  $cmd = '';
  $cmd .= 'links';
  //$cmd .= ' -ssl.certificates=0';
  $cmd .= ' -no-connect';
  $cmd .= ' -codepage UTF-8';
  $cmd .= ' -dump ' . $esc;
  $out = null;
  $ret = null;
  exec($cmd, $out, $ret);
  $res = implode("\n", $out);
  $res = preg_replace('/^\s{0,}/m', '', $res);
  return $res;
}

function fetch_curl($url) {
  $curl = curl_init($url);

  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

  //curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

  curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

  $result = curl_exec($curl);

  curl_close($curl);
  
  return $result;
}

function fetch_php($url) {
  $result = @file_get_contents($url);
  return $result;  
}

function strip_html_comment($html) {
  $html = preg_replace('/<![^>]*>/', '', $html);
  return $html;
}

function strip_html_script($html) {
  $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
  $html = preg_replace('/<script[^>]*\/>/i', '', $html);
  $html = preg_replace('/<\/script>/i', '', $html);
  return $html;
}

function strip_html_style($html) {
  $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
  $html = preg_replace('/<\/style>/i', '', $html);
  return $html;
}

function strip_html_element_dom($html, $array) {
  $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
  $dom = new DOMDocument();
  if (!@$dom->loadHTML($html)) {
    die("Error: DOMDocument exception\n");
  }
  $remove = [];
  foreach ($array as $tag) {
    $tags = $dom->getElementsByTagName($tag);
    foreach ($tags as $item) $remove[] = $item;
  }
  foreach ($remove as $item) {
    $item->parentNode->removeChild($item); 
  }
  $dom->formatOutput = true;
  $html = $dom->saveHTML();
  $dom->loadHTML($html);
  $body = $dom->getElementsByTagName('body');
  if (count($body)) {
    $html = $dom->saveHTML($body[0]);
  }
  return $html;
}

function strip_html_element_html5($html, $array) {
  //$html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
$options = array(
  'encode_entities' => true,
  'disable_html_ns' => true,
);

  $dom = new HTML5($options);
  if (!@$dom->loadHTML($html)) {
    die("Error: HTML5 exception\n");
  }
  echo 'y';
  var_dump($dom);
  
  $html = $dom->saveHTML();
  echo 'x';
  return $html;
}

function html_to_markdown($html) {
  $converter = new HtmlConverter();
  $converter->getConfig()->setOption('strip_tags', true);
  $converter->getEnvironment()->addConverter(new TableConverter());
  $markdown = $converter->convert($html);
  return $markdown;
}

function markdown_eject_garbage($markdown) {
  $eject = array();
  $eject[] = '/^\s*\[]\(\/\)\s{0,}/m';
  $eject[] = '/^\s*\\\-\-(>|\&gt;)\s*$/m';
  $eject[] = '/^\s*-\s*$/m';
  foreach ($eject as $pattern) {
    $markdown = preg_replace($pattern, '', $markdown);
  }
  return $markdown;
}

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
}
catch (Exception $e) {
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

?>