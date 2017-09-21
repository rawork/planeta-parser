#!/usr/bin/env php
<?php

if (php_sapi_name() != 'cli') {
    die('Commandline mode only accepted'. "\033[0m" ."\n");
}

require_once ('vendor/simplehtmldom/simple_html_dom.php');
require_once ('src/helper.php');
require_once ('src/Colors.php');

$colors = new Colors();

set_time_limit(0);

$time = -microtime(true);

$baseUrl = 'http://www.faberspa.com/ru/vytyazhki/';
$baseStuffName = 'FABER';

$html = file_get_html('faber.html');

console($colors->getColoredString('Parse catalog page', "light_green"));

$links = $html->find('a[class=eg-skin-cappe-element-0]');

$goodLinks = array();
foreach ($links as $link) {
    $goodLinks[] =  array(
        'link' => $link->attr['href'],
        'name' => $link->innertext
    );

}

console($colors->getColoredString(count($goodLinks) . ' stuff found', "light_green"));

//var_dump(count($goodLinks));

$listPath = __DIR__ . '/content/' . strtolower($baseStuffName) . '/' . 'list.json';
$stuffList = array();

foreach ($goodLinks as $key => $link) {

    $html = file_get_html($link['link']);

    $articul = trim($html->find('div[class=tecnics]', 0)->find('td', 1)->innertext);
    $stuffList[] = $articul;
    console($colors->getColoredString('['.($key+1).'] Articul found "'. $articul . '"', "light_green"));

    console($colors->getColoredString('Parse images', "yellow"));
    $images = array();
    $htmlImagesContainer = $html->find('ul[class=slides]', 0);

    if ($htmlImagesContainer) {
        $htmlImages = $htmlImagesContainer->find('img');
        if ($htmlImages) {
            foreach ($htmlImages as $htmlImage) {
                $images[] = array(
                    'thumb' => $htmlImage->attr['src'],
                    'original' => $htmlImage->attr['src']
                );
            }
        }

    }
    console($colors->getColoredString(count($images).' images found', "light_green"));

    console($colors->getColoredString('Parse docs', "yellow"));
    $docs = array();
    $htmlDocsContainer = $html->find('div[class=portfolio-detail-attributes]', 0);

    if ($htmlDocsContainer) {
        $htmlDocs = $htmlDocsContainer->find('a[class=button]');
        if ($htmlDocs) {
            foreach ($htmlDocs as $htmlDoc) {
                $docs[] = $htmlDoc->attr['href'];
            }
        }

    }

    console($colors->getColoredString(count($docs).' docs found', "light_green"));

    $stuff = array(
        'name' => trim(strip_tags($html->find('h1', 0)->innertext)),
        'articul' => $articul,
        'descriptions' => array(),
        'images' => $images,
        'brand' => $baseStuffName,
        'colors' => array(),
        'schema' => null,
        'category' => null,
        'extra' => array(),
        'docs' => $docs,
    );

    $stuff['descriptions'][] = $html->find('div[class=portfolio-detail-title]', 0)->find('h4', 0)->outertext;
    $stuff['descriptions'][] = $html->find('div[class=portfolio-detail-description-text]', 0)->find('p', 0)->outertext;

    $capitoloHtml = $html->find('div[class=capitolo-energy]', 0);
    if ($capitoloHtml) {
        $stuff['descriptions'][] = $html->find('div[class=capitolo-energy]', 0)->find('h5', 0)->outertext;
        $stuff['descriptions'][] = $html->find('div[class=capitolo-energy]', 0)->find('p', 0)->outertext;
    }
    $stuff['descriptions'][] = $html->find('div[class=tecnics]', 0)->outertext;

    $prestazioneHtml = $html->find('div[class=prestazione-block]', 0);
    if ($prestazioneHtml) {
        if ($prestazioneHtml->find('h5', 0)) {
            $stuff['descriptions'][] = $prestazioneHtml->find('h5', 0)->outertext;
        }
        if ($prestazioneHtml->find('table[class=custom-table-2]', 0)) {
            $stuff['descriptions'][] = $prestazioneHtml->find('table[class=custom-table-2]', 0)->outertext;
        }
    }

    $extraHtml = $html->find('div[class=contenitor-plus]', 0);
    if ($extraHtml) {
        $stuff['extra'][] = $extraHtml->outertext;
    }

    $path = __DIR__ . '/content/' . strtolower($baseStuffName) . '/' . $stuff['articul'];
    @mkdir($path, 0777, true);

    foreach ($stuff['images'] as &$imageUrls) {
        foreach ($imageUrls as $key => &$imageUrl) {
            console($colors->getColoredString('Download image "'. $imageUrl . '"', "yellow"));

            $rawImageUrl = $imageUrl;
            $imageUrlParts = explode('?', $imageUrl);
            $imageUrl = $key.'_'.basename($imageUrlParts[0]);

            if (file_exists($path.'/'.$imageUrl)) {
                continue;
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $rawImageUrl);
            curl_setopt($ch, CURLOPT_VERBOSE, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_AUTOREFERER, false);
            curl_setopt($ch, CURLOPT_REFERER, $baseUrl);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            $result = curl_exec($ch);
            curl_close($ch);

            file_put_contents($path.'/'.$imageUrl, $result);
        }
    }

    unset($imageUrls, $imageUrl);

    foreach ($stuff['docs'] as &$docUrl) {
        console($colors->getColoredString('Download doc "'. $docUrl . '"', "yellow"));

        $rawDocUrl = $docUrl;
        $docUrlParts = explode('?', $docUrl);
        $docUrl = basename($docUrlParts[0]);

        if (file_exists($path.'/'.$docUrl)) {
            continue;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $rawDocUrl);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, false);
        curl_setopt($ch, CURLOPT_REFERER, $baseUrl);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $result = curl_exec($ch);
        curl_close($ch);

        file_put_contents($path.'/'.$docUrl, $result);
    }

    unset($docUrl);

    file_put_contents($path.'/stuff.json', json_encode($stuff));

    console($colors->getColoredString('Stuff "'.$stuff['name'].'" ('.$articul.') parsed ', "green"));
}

file_put_contents($listPath, json_encode($stuffList));

console('ready');

$time = +microtime(true);

//echo $time;





