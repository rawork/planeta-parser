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

$baseUrl = 'http://www.smeg.ru';
$ajaxUrl = 'http://www.smeg.ru/ajax/products_listing/?';
$baseStuffName = 'Smeg';

$commonPath = __DIR__ . '/content/' . strtolower($baseStuffName) ;

@mkdir($commonPath, 0777, true);

$html = file_get_html($baseUrl);

console($colors->getColoredString('Parse catalog page ', "yellow"));

$container = $html->find('div[id=nav_mobile]', 0);
if ($container) {

    $cachePath = __DIR__ . '/content/' . strtolower($baseStuffName) . '/' . 'cache.json';

    if (file_exists($cachePath) && time() - filemtime($cachePath) < 86400) {
        console($colors->getColoredString('Stuff list cache found', "green"));
        $goodLinks = json_decode(file_get_contents($cachePath), true);
    } else {
        console($colors->getColoredString('Stuff list cache generation...', "red"));
        $catLinks = array();

        $mainUlHtml = $container->find('ul[class=primary]', 0);
        $li1Html = $mainUlHtml->children(1);
        $ul1Html = $li1Html->find('ul', 0);
        $catsHtml = $ul1Html->find('a');
        foreach ($catsHtml as $link) {
            if ($link && substr_count($link->attr['href'], '/') == 2 && $link->attr['href'] != '/domashnyaya-oranzhereya/') {
                $catLinks[] = array(
                    'link' => $link->attr['href'],
                    'name' => $link->innertext,
                    'fulltname' => false,
                );
            }
        }

        $li1Html = $mainUlHtml->children(2);;
        var_dump($li1Html->innertext);
        $ul1Html = $li1Html->find('ul', 0);
        $catsHtml = $ul1Html->find('a');
        foreach ($catsHtml as $link) {
            if ($link && substr_count($link->attr['href'], '/') == 2) {
                $catLinks[] = array(
                    'link' => $link->attr['href'],
                    'name' => $link->innertext,
                    'fulltname' => false,
                );
            }
        }

//        $catalogMenu = $mainUlHtml->innertext;
//        $catalogMenuHtml = str_get_html($catalogMenu);
//        $catalogMenuHtml->find('li', 0)->outertext = '';
//        $catalogMenuHtml = str_get_html($catalogMenuHtml->innertext);
//        $li1Html = $catalogMenuHtml->find('li', 0);
//        $ul1Html = $li1Html->find('ul', 0);
//        $catsHtml = $ul1Html->find('a');
//        foreach ($catsHtml as $link) {
//            if ($link && substr_count($link->attr['href'], '/') == 2) {
//                $catLinks[] = array(
//                    'link' => $link->attr['href'],
//                    'name' => $link->innertext,
//                    'fulltname' => true,
//                );
//            }
//        }

        console($colors->getColoredString(count($catLinks) . ' categories found', "light_green"));

        $goodLinks = array();
        $countCats = count($catLinks);
        foreach ($catLinks as $key => $catLink) {

            console($colors->getColoredString('------------------------', "green"));
            console($colors->getColoredString('['.($key+1).'/'.$countCats.'] '.$baseUrl.$catLink['link'], "green"));

            $filter = array();
            $categoryPageHtml = file_get_html($baseUrl.$catLink['link']);
            $formHtml = $categoryPageHtml->find('form#filter', 0);
            $inputHtml = $formHtml->find('input');
            foreach ($inputHtml as $input) {
                $filter[$input->attr['name']] = $input->attr['value'];
                if ($input->attr['name'] == 'np') {
                    $filter[$input->attr['name']] = -1;
                }
            }

            console($colors->getColoredString($baseUrl.$catLink['link'].'#'.http_build_query($filter), "green"));
            $stuffJson = json_decode(file_get_contents($ajaxUrl.http_build_query($filter)), true);

            $listingHtml = str_get_html($stuffJson['listing']);
            $stuffHtml = $listingHtml->find('li');
            console($colors->getColoredString(count($stuffHtml) . ' stuff found on page', "yellow"));
            foreach ($stuffHtml as $stuff) {
                $name = $stuff->find('span[class=code]', 0);
                $link = $stuff->find('a', 0);
                $img = $stuff->find('img', 0);
                $goodLinks[] = array(
                    'name' => $name->innertext,
                    'link' => $link->attr['href'],
                    'image' => $img->attr['src'],
                    'category' => $catLink,
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
        $html = file_get_html($baseUrl.$link['link']);

        $articulHtml = $html->find('p[class=ean_code]', 0);
        if ($articulHtml) {

            $articul = str_replace('EAN13: ', '', trim($articulHtml->innertext));

            $stuffList[] = $articul;

            console($colors->getColoredString('['.($key+1).'/'.$stuffCount.'] Start parse '.$link['name'].' ['. $articul . ']', "yellow"));

            console($colors->getColoredString('Parse images', "yellow"));
            $images = array();

            $htmlImagesContainer = $html->find('div[class=photo]', 0);
            if ($htmlImagesContainer) {
                $owlHtml = $htmlImagesContainer->find('div[class=owl-item]');
                if ($owlHtml) {
                    foreach ($owlHtml as $imageHtml) {
                        $images[] = array(
                            'thumb' => $imageHtml->find('img', 0)->attr['src'],
                            'original' => $imageHtml->find('a', 0)->attr['href']
                        );
                    }
                } else {
                    $images[] = array(
                        'thumb' => $htmlImagesContainer->find('img', 0)->attr['src'],
                        'original' => $htmlImagesContainer->find('a', 0)->attr['href']
                    );
                }
            }

            $htmlImagesContainer = $html->find('li[class=disegno_tecnico]', 0);
            if ($htmlImagesContainer) {
                $images[] = array(
                    'thumb' => $htmlImagesContainer->find('a', 0)->attr['href'],
                    'original' => $htmlImagesContainer->find('a', 0)->attr['href']
                );
            }

            if (count($images) > 0) {
                console($colors->getColoredString('Found '.count($images).' images', "light_green"));
            } else {
                console($colors->getColoredString('Images not found', "light_red"));
            }

            console($colors->getColoredString('Parse docs', "yellow"));
            $docs = array();

            $htmlDocsContainer = $html->find('li[class=libretto_istruzioni]', 0);
            if ($htmlDocsContainer) {
                $htmlDoc = $htmlDocsContainer->find('a', 0);
                if (strpos($htmlDoc->attr['href'], 'ajax') === false) {
//                    var_dump($htmlDoc->attr['href'], strpos($htmlDoc->attr['href'], 'ajax'));
                    $docs[] = array(
                        'link' => $htmlDoc->attr['href'],
                        'description' => $htmlDoc->innertext,
                    );
                }
            }

            $htmlDocsContainer = $html->find('li[class=pdf_scheda]', 0);
            if ($htmlDocsContainer) {
                $htmlDoc = $htmlDocsContainer->find('a', 0);
                if (strpos($htmlDoc->attr['href'], 'ajax') === false) {
//                    var_dump($htmlDoc->attr['href'], strpos($htmlDoc->attr['href'], 'ajax'));
                    $docs[] = array(
                        'link' => $htmlDoc->attr['href'],
                        'description' => $htmlDoc->innertext,
                    );
                }
            }

            console($colors->getColoredString(count($docs).' docs found', "light_green"));

            $stuff = array(
                'name' => $link['name'],
                'articul' => $articul,
                'descriptions' => array(),
                'images' => $images,
                'brand' => $baseStuffName,
                'colors' => array(),
                'extra' => array(),
                'schema' => null,
                'docs' => $docs,
                'category' => null,
            );

            $descriptionHtml = $html->find('div[class=description]', 0);
            $fullNameHtml = $descriptionHtml->find('p', 1);
            if ($fullNameHtml) {
                $stuff['descriptions'][] = $fullNameHtml->innertext;
                unset($fullNameHtml);
            }

            $featuresHtml = $descriptionHtml->find('div[class=features]', 0);
            if ($featuresHtml) {
                $iconsHtml = $featuresHtml->find('img');
                $tmp = '<div>';
                foreach ($iconsHtml as $iconHtml) {
                    $content = $iconHtml->outertext;
                    $content = str_replace('src="/', 'src="'.$baseUrl.'/', $content);
                    $tmp .= $content;
                }
                $tmp = '</div>';
                $stuff['descriptions'][] = $tmp;
                unset($featuresHtml);
            }

            $descriptionHtml = $html->find('div[class=full_description]', 0);
            if ($descriptionHtml) {
                $stuff['descriptions'][] = $descriptionHtml->innertext;
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
                console($colors->getColoredString('Download doc "'. $docUrl['link'] . '"', "yellow"));

                $rawDocUrl = $docUrl['link'];
                $docUrlParts = explode('?', $docUrl['link']);
                $docUrl['link'] = basename($docUrlParts[0]);

                if (file_exists($path.'/'.$docUrl['link'])) {
                    continue;
                }

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $baseUrl.$rawDocUrl);
                curl_setopt($ch, CURLOPT_VERBOSE, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_AUTOREFERER, false);
                curl_setopt($ch, CURLOPT_REFERER, $baseUrl);
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                $result = curl_exec($ch);
                curl_close($ch);

                file_put_contents($path.'/'.$docUrl['link'], $result);
            }

            unset($docUrl);

            file_put_contents($path.'/stuff.json', json_encode($stuff));

            console($colors->getColoredString('Parsed "'. $stuff['name'] .'" '. $articul, "green"));
        } else {
            console($colors->getColoredString('Articul not found', "light_red"));
        }

        file_put_contents($listPath, json_encode($stuffList));
    }

    file_put_contents($listPath, json_encode($stuffList));
}

console('ready');

$time = +microtime(true);

//echo $time;





