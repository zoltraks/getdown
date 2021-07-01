<?php

use League\HTMLToMarkdown\HtmlConverter;
use League\HTMLToMarkdown\Converter\TableConverter;
use Masterminds\HTML5;

function fetch_links($url)
{
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

function fetch_curl($url)
{
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

function fetch_php($url)
{
  $result = @file_get_contents($url);
  return $result;
}

function strip_html_comment($html)
{
  $html = preg_replace('/<![^>]*>/', '', $html);
  return $html;
}

function strip_html_script($html)
{
  $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
  $html = preg_replace('/<script[^>]*\/>/i', '', $html);
  $html = preg_replace('/<\/script>/i', '', $html);
  return $html;
}

function strip_html_style($html)
{
  $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
  $html = preg_replace('/<\/style>/i', '', $html);
  return $html;
}

function strip_html_element_dom($html, $array)
{
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

function strip_html_element_html5($html, $array)
{
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

function html_to_markdown($html)
{
  $converter = new HtmlConverter();
  $converter->getConfig()->setOption('strip_tags', true);
  $converter->getEnvironment()->addConverter(new TableConverter());
  $markdown = $converter->convert($html);
  return $markdown;
}

function markdown_eject_garbage($markdown)
{
  $eject = array();
  $eject[] = '/^\s*\[]\(\/\)\s{0,}/m';
  $eject[] = '/^\s*\\\-\-(>|\&gt;)\s*$/m';
  $eject[] = '/^\s*-\s*$/m';
  foreach ($eject as $pattern) {
    $markdown = preg_replace($pattern, '', $markdown);
  }
  return $markdown;
}
