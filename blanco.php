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

$baseUrl = 'http://www.blanco-germany.com/';
$baseStuffName = 'Blanco';
$commonPath = __DIR__ . '/content/' . strtolower($baseStuffName) ;
$catalogUrls = array(
    array(
        'category' => 'http://www.blanco-germany.com/ru/ru/kitchen_sinks_ru/range_of_sinks_ru/overview_sinks.html',
        'baseurl'  => 'http://www.blanco-germany.com/ru/ru/kitchen_sinks_ru/range_of_sinks_ru/',
        'path' => '/blanco_sinks.html',
    ),
    array(
        'category' => 'http://www.blanco-germany.com/ru/ru/mixer_taps_ru/range_taps_ru/overview_taps.html',
        'baseurl'  => 'http://www.blanco-germany.com/ru/ru/mixer_taps_ru/range_taps_ru/',
        'path' => '/blanco_taps.html',
    ),
    array(
        'category' => 'http://www.blanco-germany.com/ru/ru/accessories_ru/soap_dispensers_ru/dispenseroverview.html',
        'baseurl'  => 'http://www.blanco-germany.com/ru/ru/accessories_ru/soap_dispensers_ru/',
        'path' => '/blanco_soap_dispensers.html',
    ),

    array(
        'category' => 'http://www.blanco-germany.com/ru/ru/accessories_ru/waste_separation_ru/overview.html',
        'baseurl'  => 'http://www.blanco-germany.com/ru/ru/accessories_ru/waste_separation_ru/',
        'path' => '/blanco_waste_separation.html',
    ),
);

@mkdir($commonPath, 0777, true);

console($colors->getColoredString('Parse catalog page ', "yellow"));

$cachePath = __DIR__ . '/content/' . strtolower($baseStuffName) . '/' . 'cache.json';

if (file_exists($cachePath) && time() - filemtime($cachePath) < 86400) {
    console($colors->getColoredString('Stuff list cache found', "green"));
    $goodLinks = json_decode(file_get_contents($cachePath), true);
} else {
    console($colors->getColoredString('Stuff list cache generation...', "red"));

    $goodLinks = array();
    foreach ($catalogUrls as $catalogUrl) {
        console($colors->getColoredString('Parse category page ' . $catalogUrl['path'], "yellow"));
        $html = file_get_html(__DIR__ . $catalogUrl['path']);

        $catalogHtml = $html->find('div[id=product-overview]', 0);

        $stuffHtml = $catalogHtml->find('li');

        console($colors->getColoredString(count($stuffHtml) . ' stuff found on page', "yellow"));

        foreach ($stuffHtml as $stuff) {
            $name = $stuff->find('div[class=text]', 0);
            $link = $stuff->find('a', 0);
            $img = $stuff->find('img', 0);

            if (!$name) {
                continue;
            }

            $goodLinks[] = array(
                'name' => $name->find('p', 0)->innertext.($name->find('p', 1) ? ' '.$name->find('p', 1)->innertext : ''),
                'link' => $catalogUrl['baseurl'].$link->attr['href'],
                'image' => $img->attr['src'],
            );
        }

    }

    file_put_contents($cachePath, json_encode($goodLinks));
}

$stuffCount = count($goodLinks);
console($colors->getColoredString($stuffCount . ' stuff found', "light_green"));

$listPath = __DIR__ . '/content/' . strtolower($baseStuffName) . '/' . 'list.json';

$stuffList = array();
foreach ($goodLinks as $key => $link) {

    console($colors->getColoredString($baseUrl.$link['link'], "green"));

    $stuff = array(
        'name' => trim(str_ireplace($baseStuffName, '', $link['name'])),
        'articul' => '',
        'descriptions' => array(),
        'images' => array(),
        'brand' => $baseStuffName,
        'colors' => array(),
        'extra' => array(),
        'schema' => null,
        'docs' => array(),
        'category' => null,
    );


    $html = file_get_html($link['link']);
    $isSingleArticul = false;
    $articulHtml = $html->find('div[class=color-overview]', 0);

    if (!$articulHtml) {
        $articulHtml = $html->find('span[class=anum]', 0);
        $isSingleArticul = true;
    }

    if ($articulHtml) {

        if ($isSingleArticul) {
            $articul = trim($articulHtml->innertext);
            console($colors->getColoredString('['.($key+1).'/'.$stuffCount.'] Start parse '.$link['name'].' ['. $articul . ']', "yellow"));
        } else {
            $colorImageHtml = $articulHtml->find('div[class=image]');
            $colorArticulHtml = $articulHtml->find('td[class=artNr_uk]');

            if (count($colorArticulHtml) == count($colorImageHtml)) {
                foreach ($colorArticulHtml as $articulKey => $colorArticul) {
                    if (0 == $articulKey) {
                        $articul = trim($colorArticul->innertext);
                        console($colors->getColoredString('['.($key+1).'/'.$stuffCount.'] Start parse '.$link['name'].' ['. $articul . ']', "yellow"));
                    }

                    $stuff['colors'][] = array(
                        'art' => $colorArticul->innertext,
                        'name' => $colorImageHtml[$articulKey]->find('img', 0)->attr['alt'],
                        'img' => $colorImageHtml[$articulKey]->find('img', 0)->attr['src'],
                    );
                }
            } else {
                foreach ($colorArticulHtml as $articulKey => $colorArticul) {
                    if (0 == $articulKey) {
                        $articul = trim($colorArticul->innertext);
                        console($colors->getColoredString('['.($key+1).'/'.$stuffCount.'] Start parse '.$link['name'].' ['. $articul . ']', "yellow"));
                    }

                    $stuff['colors'][] = array(
                        'art' => $colorArticul->innertext,
                        'name' => $colorImageHtml[0]->find('img', 0)->attr['alt'],
                        'img' => $colorImageHtml[0]->find('img', 0)->attr['src'],
                    );
                }
            }

            console($colors->getColoredString(count($stuff['colors']).' colors found', "light_green"));
        }

        $stuff['articul'] = $articul;
        $stuffList[] = $articul;

        console($colors->getColoredString('Parse images', "yellow"));

        $htmlImageContainer = $html->find('div[class=image-gallery]', 0);
        if ($htmlImageContainer) {
            $imagesUlHtml = $htmlImageContainer->find('ul', 0);
            if ($imagesUlHtml) {
                foreach ($imagesUlHtml->find('li') as $imgHtml) {
                    $stuff['images'][] = array(
                        'thumb' => $imgHtml->find('img', 0)->attr['src'],
                        'original' => $imgHtml->find('a', 0)->attr['href']
                    );
                }
            } else {
                $stuff['images'][] = array(
                    'thumb' => $htmlImageContainer->find('img', 0)->attr['src'],
                    'original' => $htmlImageContainer->find('a', 0)->attr['href']
                );
            }
        }

        $htmlImageContainer = $html->find('ul[class=drawing-thumbs]', 0)->find('li');
        if ($htmlImageContainer) {
            foreach ($htmlImageContainer as $imgHtml){
                $stuff['images'][] = array(
                    'thumb' => $imgHtml->find('img', 0)->attr['src'],
                    'original' => $imgHtml->find('a',0)->attr['href'],
                );
            }
        }

        if (count($stuff['images']) > 0) {
            console($colors->getColoredString(count($stuff['images']).' images found', "light_green"));
        } else {
            console($colors->getColoredString('Images not found', "light_red"));
        }

        $descriptionHtml = $html->find('h3', 0);
        if ($descriptionHtml) {
            $stuff['descriptions'][] = $descriptionHtml->outertext;
        }

        $descriptionHtml = $html->find('li[id=tab-pros]', 0)->find('div[class=text]', 0);
        if ($descriptionHtml) {
            $stuff['descriptions'][] = $descriptionHtml->innertext;
        }

        $descriptionHtml = $html->find('li[id=tab-details]', 0)->find('div[class=text]', 0);
        if ($descriptionHtml) {
            $stuff['descriptions'][] = $descriptionHtml->innertext;
        }
    } else {
        console($colors->getColoredString('Articul not found', "light_red"));
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
            curl_setopt($ch, CURLOPT_URL, $baseUrl.$rawImageUrl);
            curl_setopt($ch, CURLOPT_VERBOSE, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_AUTOREFERER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
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
            console($colors->getColoredString('Download color image "'. $rawImageUrl . '"', "yellow"));

            if (file_exists($path . '/' . $colorData['img'])) {
                continue;
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $baseUrl.$rawImageUrl);
            curl_setopt($ch, CURLOPT_VERBOSE, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_AUTOREFERER, false);
            curl_setopt($ch, CURLOPT_REFERER, $baseUrl);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            $result = curl_exec($ch);
            curl_close($ch);

            file_put_contents($path . '/' . $colorData['img'], $result);
        }

        unset($colorData);
    }

    unset($docUrl);

    file_put_contents($path.'/stuff.json', json_encode($stuff));

    console($colors->getColoredString('Parsed "'. $stuff['name'] .'" '. $articul, "green"));

    file_put_contents($listPath, json_encode($stuffList));
}

file_put_contents($listPath, json_encode($stuffList));

console('ready');

$time = +microtime(true);

//echo $time;





