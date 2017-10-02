#!/usr/bin/env php
<?php

if (php_sapi_name() != 'cli') {
    die('Commandline mode only accepted');
}

require_once ('vendor/simplehtmldom/simple_html_dom.php');
require_once ('src/helper.php');
require_once ('src/Colors.php');

$colors = new Colors();

set_time_limit(0);

$time = -microtime(true);

$baseUrl = 'http://www.mikadzo.ru';
$baseStuffName = 'Mikadzo';

$html = file_get_html('http://mikadzo.ru/knifes/damascus-collection/damascus/');

console($colors->getColoredString('Parse catalog page ', "yellow"));

$container = $html->find('div[id=sublist]', 0);
if ($container) {
    $links = $container->find('a');

    $goodLinks = array();
    foreach ($links as $link) {
        $name = $link->find('span', 0);
        if ($name) {
            $goodLinks[] = array(
                'link' => $link->attr['href'],
                'name' => str_replace('<br>', ' ', $name->innertext),
            );
        }
    }


    $listPath = __DIR__ . '/content/' . strtolower($baseStuffName) . '/' . 'list.json';
    $stuffList = array();

    $extraGoodLinks = array();
    foreach ($goodLinks as $goodLink) {
        $html = file_get_html($goodLink['link']);
        $container = $html->find('aside[class=sidebar]', 0);
        if ($container) {
            $listContainer = $html->find('div[class=list]', 0);
            if ($listContainer) {
                $links = $listContainer->find('a');
                foreach ($links as $link) {
                    $name = $link->find('span', 0);
                    if ($name) {
                        $extraGoodLinks[] = array(
                            'link' => $link->attr['href'],
                            'name' => str_replace('<br />', '', $name->innertext),
                        );
                    }
                }
            }
        }
    }

    $goodLinks = array_merge($goodLinks, $extraGoodLinks);

    $stuffCount = count($goodLinks);
    console($colors->getColoredString($stuffCount . ' stuff found', "light_green"));

    foreach ($goodLinks as $key => $link) {
        $html = file_get_html($link['link']);

        $descriptionHtml = $html->find('div[class=description]', 0);
        if ($descriptionHtml) {

            $articul = str_replace('арт. ', '', trim($descriptionHtml->find('div[class=title]', 0)->find('p', 0)->innertext));

            $stuffList[] = $articul;

            console($colors->getColoredString('['.($key+1).'/'.$stuffCount.'] Start parse '.$link['name'].' ['. $articul . ']', "yellow"));

            $titleHtml = $descriptionHtml->find('div[class=title]', 0);

            $title = $titleHtml->find('span', 0)->innertext;
            $secondTitleHtml = $titleHtml->find('span', 1);
            if ($secondTitleHtml && $secondTitleHtml->innertext) {
                $title .= ' ' . $secondTitleHtml->innertext;
            }
            $title .= ' ' . $titleHtml->find('h1', 0)->innertext;

            $title = strip_tags($title);

            $images = array();

            $htmlImagesContainer = $html->find('div[class=description__image]', 0);
            if ($htmlImagesContainer) {
                $images[] = array(
                    'thumb' => $htmlImagesContainer->find('img', 0)->attr['src'],
                    'original' => $htmlImagesContainer->find('img', 0)->attr['src']
                );
            }

            if (count($images) > 0) {
                console($colors->getColoredString('Found '.count($images).' images', "light_green"));
            } else {
                console($colors->getColoredString('Images not found', "light_red"));
            }

            $stuff = array(
                'name' => $title,
                'articul' => $articul,
                'descriptions' => array(),
                'images' => $images,
                'brand' => $baseStuffName,
                'colors' => array(),
                'extra' => array(),
                'schema' => null,
                'category' => null,
            );

            $sideHtml = $html->find('div[class=sidebar__options]', 0);
            if ($sideHtml) {
                $stuff['descriptions'][] = $sideHtml->innertext;
            }

            $extraHtml = $descriptionHtml->find('div[class=description__icons]', 0);
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

            file_put_contents($path.'/stuff.json', json_encode($stuff));

            console($colors->getColoredString('Parsed "'. $stuff['name'] .'" '. $articul, "green"));
        } else {
            console($colors->getColoredString('Articul not found', "light_red"));
        }
    }

    file_put_contents($listPath, json_encode($stuffList));
}

console('ready');

$time = +microtime(true);

//echo $time;





