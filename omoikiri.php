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

$baseUrl = 'http://www.omoikiri.ru';
$baseStuffName = 'Omoikiri';

$html = file_get_html($baseUrl.'/catalog');


console($colors->getColoredString('parse catalog page ', "light_red"));


$container = $html->find('div[class=cat_grid]', 0);

if ($container) {
    $links = $container->find('a');

    $categoryLinks = array();
    foreach ($links as $link) {
        $categoryLinks[] =  array(
            'link' => $link->attr['href'],
            'name' => $link->find('h2', 0)->innertext
        );

    }

//    var_dump($categoryLinks);
    $goodLinks = array();

    console($colors->getColoredString('parse category pages ', "light_red"));

    foreach ($categoryLinks as $categoryLink) {
        $html = file_get_html($baseUrl.$categoryLink['link']);

        $goodContainers = $html->find('div[class=item_column]');
        foreach ($goodContainers as $cont) {
            $links = $cont->find('a[href]');
            foreach ($links as $link) {
                if ($link->attr['href'] && strpos($link->attr['href'], 'NODE') === false) {
                    $goodLinks[] = array(
                        'link' => $link->attr['href'],
                        'category' => $categoryLink,
                    );
                }
            }
        }
    }

    //    var_dump(count($goodLinks));

    $listPath = __DIR__ . '/content/' . strtolower($baseStuffName) . '/' . 'list.json';
    $stuffList = array();

    foreach ($goodLinks as $link) {
        //    $link = 'http://www.omoikiri.ru/catalog/dispenser/om-01';
        //    $link = 'http://www.omoikiri.ru/catalog/purifier/pure-drop-214';
        //    $link = 'http://www.omoikiri.ru/catalog/washer/akisame-78';

        $html = file_get_html($baseUrl.$link['link']);

        $div = $html->find('div[class=content]', 0);
        $js = $div->find('script', 0);
        $textJS = $js->innertext;
        $textJS = substr($textJS, strpos($textJS, 'productColors'));
        $textJS = substr($textJS, strpos($textJS, '[{'));
        $textJS = substr($textJS, 0, strpos($textJS, '}];')+2);
        $textJS = str_replace('null', '""', $textJS);
        $colorJson = json_decode($textJS, true);
        //var_dump($colorJson);


        $images = array();
        $htmlImagesContainer = $html->find('div[class=field-name-field-extra-imgs]', 0);
        if ($htmlImagesContainer) {
            $htmlImages = $htmlImagesContainer->find('a');
            foreach ($htmlImages as $htmlImage) {
                $images[] = array(
                    'thumb' => $htmlImage->find('img', 0)->attr['src'],
                    'original' => $htmlImage->attr['href']
                );
            }
        }

        $articul = trim($html->find('div[class=prodArticle]', 0)->find('span', 0)->innertext);

        $stuffList[] = $articul;

        console($colors->getColoredString('start parsing '. $articul, "yellow"));

        $stuff = array(
            'name' => trim(strip_tags($html->find('h1', 0)->innertext)),
            'articul' => $articul,
            'descriptions' => array(),
            'images' => $images,
            'brand' => $baseStuffName,
            'colors' => $colorJson,
            'schema' => null,
            'category' => $link['category'],
        );


        $htmlSchemaContainer = $html->find('div[class=field-name-field-schema-img]', 0);
        if ($htmlSchemaContainer) {
            $stuff['schema'] = $htmlSchemaContainer->find('img', 0)->attr['src'];
        }

        // TODO clear colors from description
        $stuff['descriptions'][] = $html->find('div[class=prodTable]', 0)->innertext;
        $htmlControl = $html->find('div[class=productControl]', 0);
        if ($htmlControl) {
            $stuff['descriptions'][] =  $htmlControl->innertext;
        }

        $path = __DIR__ . '/content/' . strtolower($baseStuffName) . '/' . $stuff['articul'];

        @mkdir($path, 0777, true);

        foreach ($stuff['images'] as &$imageUrls) {
            foreach ($imageUrls as $key => &$imageUrl) {
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

        if ($stuff['colors']) {
            foreach ($stuff['colors'] as &$colorData) {
                $rawImageUrl = $colorData['img'];
                $imageUrlParts = explode('?', $colorData['img']);
                $colorData['img'] = basename($imageUrlParts[0]);

                if (file_exists($path . '/' . $colorData['img'])) {
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

                file_put_contents($path . '/' . $colorData['img'], $result);
            }

            unset($colorData);
        }

        if ($stuff['schema']) {

            $rawImageUrl = $stuff['schema'];
            $imageUrlParts = explode('?', $stuff['schema']);
            $stuff['schema'] = basename($imageUrlParts[0]);

            if (!file_exists($path . '/' . $stuff['schema'])) {
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

                file_put_contents($path . '/' . $stuff['schema'], $result);
            }
        }

        file_put_contents($path.'/stuff.json', json_encode($stuff));

        console($colors->getColoredString('parsed '. $articul, "green"));
    }

    file_put_contents($listPath, json_encode($stuffList));
}

console('ready');

$time = +microtime(true);

//echo $time;





