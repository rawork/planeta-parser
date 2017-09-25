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

$baseUrl = 'http://elica.com';
$baseStuffName = 'Elica';
$categories = array(
    array('link' => 'http://elica.com/RU-ru/%D0%B2%D1%8B%D1%82%D1%8F%D0%B6%D0%BA%D0%B8', 'section_id' => 108),
    array('link' => 'http://elica.com/RU-ru/hobs', 'section_id' => 106),
);

$listPath = __DIR__ . '/content/' . strtolower($baseStuffName) . '/' . 'list.json';
$stuffList = array();
$goodLinks = array();

console($colors->getColoredString('Parse category pages', "light_green"));
foreach ($categories as $categoryPage) {
    $html = file_get_html($categoryPage['link']);

    $liHtml = $html->find('ul[class=gallery_prodotti]', 0)->find('li');

    foreach ($liHtml as $liContainer) {
        $link = $liContainer->find('a', 0);
        $goodLinks[] = array(
            'link' => $link->attr['href'],
            'name' => $link->attr['title'],
            'section_id' => $categoryPage['section_id'],
        );
    }
}

console($colors->getColoredString(count($goodLinks) . ' stuff found', "light_green"));

foreach ($goodLinks as $key => $link) {
    console($colors->getColoredString('['.($key+1).'] Start parse "'. $baseUrl.$link['link'] . '"', "light_green"));
    $html = file_get_html($baseUrl.$link['link']);

    if ($articulContainerHtml = $html->find('div[class=blocco_mod_acc]', 0)) {
        $articul = trim($articulContainerHtml->find('td[class=tab_mod_acc_3]', 1)->innertext);
    } else {
        console($colors->getColoredString('Articul not found "'. $baseUrl.$link['link'] . '"', "light_red"));
    }

    $stuffList[] = $articul;
    console($colors->getColoredString('Articul found "'. $articul . '"', "light_green"));

    console($colors->getColoredString('Parse images', "yellow"));
    $images = array();
    $htmlImagesContainer = $html->find('div[class=container_img_cope]', 0);

    if ($htmlImagesContainer) {
        $htmlImages = $htmlImagesContainer->find('a');
        if ($htmlImages) {
            foreach ($htmlImages as $htmlImage) {

                $original = $htmlImage->attr['href'] ?: $htmlImage->find('img', 0)->attr['src'];

                $images[] = array(
                    'thumb' => $htmlImage->find('img', 0)->attr['src'],
                    'original' => $original
                );
            }
        }

    }
    console($colors->getColoredString(count($images).' images found', "light_green"));

    console($colors->getColoredString('Parse docs', "yellow"));
    $docs = array();
    $htmlDocsContainer = $html->find('div[id=blocco_download]', 0);

    if ($htmlDocsContainer) {
        $htmlDocs = $htmlDocsContainer->find('a');
        if ($htmlDocs) {
            foreach ($htmlDocs as $htmlDoc) {
                if ($htmlDoc->attr['title'] != 'Загрузить') {
                    continue;
                }
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
        'section_id' => $link['section_id'],
    );

    $stuff['descriptions'][] = $html->find('div[class=blocco_new_prodotto_2_1]', 0)->innertext;

    $extraHtml = $html->find('div[class=ico_caratteristiche]', 0);
    if ($extraHtml) {
        $stuff['extra'][] = $extraHtml->innertext;
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





