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

$baseUrl = 'https://www.franke.com';
$catalogUrl = 'https://www.franke.com/kitchensystems/ru/ru/home/products.html';
$baseStuffName = 'Franke';

$commonPath = __DIR__ . '/content/' . strtolower($baseStuffName) ;

@mkdir($commonPath, 0777, true);

$html = file_get_html($catalogUrl);

console($colors->getColoredString('Parse catalog page ', "yellow"));

$container = $html->find('div[class=content_cmp_ts_prod_1]', 1);
if ($container) {

    $cachePath = __DIR__ . '/content/' . strtolower($baseStuffName) . '/' . 'cache.json';

    if (file_exists($cachePath) && time() - filemtime($cachePath) < 86400) {
        console($colors->getColoredString('Stuff list cache found', "green"));
        $goodLinks = json_decode(file_get_contents($cachePath), true);
    } else {
        console($colors->getColoredString('Stuff list cache generation...', "red"));
        $catLinks = array();
        $linksContainerHtml = $container->find('div[class=cmp_ts_prod_1]');
        if ($linksContainerHtml) {
            foreach($linksContainerHtml as $linkContainer) {
                $link = $linkContainer->find('a', 0);
                if ($link) {
                    $catLinks[] = array(
                        'link' => $link->attr['href'],
                        'name' => $link->attr['title'],
                    );
                }
            }
        }

        console($colors->getColoredString(count($catLinks) . ' categories found', "light_green"));

        $lineLinks = array();


        $agent= 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';

        foreach ($catLinks as $key => $catLink) {
            console($colors->getColoredString('------------------------', "green"));
            console($colors->getColoredString($baseUrl.$catLink['link'], "green"));

            $categoryPageHtml = file_get_html($baseUrl.$catLink['link']);

            $stuffContainerHtml = $categoryPageHtml->find('div[class=cmp_ts_prod_2_grid]', 0);

            $stuffHtml = $stuffContainerHtml->find('div[class=cmp_ts_prod_2]');
            console($colors->getColoredString(count($stuffHtml) . ' lines found on page', "yellow"));

            foreach ($stuffHtml as $stuff) {
                $link = $stuff->find('a', 0);
                $img = $stuff->find('img', 0);
                $lineLinks[] = array(
                    'name' => $link->attr['title'],
                    'link' => $link->attr['href'],
                    'image' => $img->attr['src'],
                    'category' => $catLink,
                );
            }
        }

        $lineCount = count($lineLinks);
        console($colors->getColoredString($lineCount . ' lines found', "light_green"));

        $goodLinks = array();

        foreach ($lineLinks as $lineLink) {
            console($colors->getColoredString('------------------------', "green"));
            console($colors->getColoredString($baseUrl.$lineLink['link'], "green"));

            $categoryPageHtml = file_get_html($baseUrl.$lineLink['link']);

            $stuffHtml = $categoryPageHtml->find('div[class=mod cmp_ts_prod_3]');

            console($colors->getColoredString(count($stuffHtml) . ' stuff found on page', "yellow"));

            foreach ($stuffHtml as $stuff) {
                $name = $stuff->find('h2', 0);
                $desc = $stuff->find('table', 0);
                $linksHtml = $stuff->find('a');
                $img = $stuff->find('img', 0);
                $links = array();
                foreach ($linksHtml as $link) {
                    $links[] = array(
                        'name' => $link->innertext,
                        'link' => $link->attr['href']
                    );
                }

                $goodLinks[] = array(
                    'name' => $name->innertext,
                    'desc' => $desc ? $desc->outertext : '',
                    'link' => $links,
                    'image' => $img->attr['src'],
                    'category' => $lineLink['category'],
                    'line' => $lineLink
                );
            }
        }

        file_put_contents($cachePath, json_encode($goodLinks));
    }

    $stuffCount = count($goodLinks);
    console($colors->getColoredString($stuffCount . ' stuff found', "light_green"));

    $listPath = __DIR__ . '/content/' . strtolower($baseStuffName) . '/' . 'list.json';
    $stuffList = array();

    foreach ($goodLinks as $key => $linkInfo) {

        $stuff = array(
            'name' => $linkInfo['name'],
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

        foreach ($linkInfo['link'] as $linkKey => $link) {
            $html = file_get_html($baseUrl.$link['link']);
            if (0 == $linkKey) {
                $articulHtml = $html->find('div[class=cmp_tbna_pane]', 0);

                if ($articulHtml) {

                    $articul = trim($articulHtml->find('h2', 0)->innertext);
                    $stuff['articul'] = $articul;
                    $stuffList[] = $articul;

                    console($colors->getColoredString($baseUrl.$link['link'], "green"));
                    console($colors->getColoredString('['.($key+1).'/'.$stuffCount.'] Start parse '.$linkInfo['name'].' '.$link['name'].' ['. $articul . ']', "yellow"));

                    console($colors->getColoredString('Parse images', "yellow"));

                    $htmlImageContainer = $html->find('div[class=img_frame]', 0);
                    if ($htmlImageContainer) {
                        $stuff['images'][] = array(
                            'thumb' => $htmlImageContainer->find('img', 0)->attr['src'],
                            'original' => $htmlImageContainer->find('img', 0)->attr['src']
                        );
                    }

                    $htmlImageContainer = $html->find('div[class=dataSheetImage]', 0);
                    if ($htmlImageContainer) {
                        $stuff['images'][] = array(
                            'thumb' => $htmlImageContainer->find('img', 0)->attr['src'],
                            'original' => $htmlImageContainer->find('div[id=dataSheetImage_large]', 0)->find('img',0)->attr['src']
                        );
                    }

                    if (count($stuff['images']) > 0) {
                        console($colors->getColoredString(count($stuff['images']).' images found', "light_green"));
                    } else {
                        console($colors->getColoredString('Images not found', "light_red"));
                    }

                    console($colors->getColoredString('Parse docs', "yellow"));

                    $htmlDocsContainer = $html->find('div[class=cmp_ll_1]', 0);
                    if ($htmlDocsContainer) {
                        $htmlDoc = $htmlDocsContainer->find('a', 0);
                        if (strpos($htmlDoc->attr['href'], 'ajax') === false) {
                            $stuff['docs'][] = array(
                                'link' => $htmlDoc->attr['href'],
                                'description' => $htmlDoc->innertext,
                            );
                        }
                    }

                    console($colors->getColoredString(count($stuff['docs']).' docs found', "light_green"));

                    $descriptionHtml = $html->find('div[class=dataSheetTable]', 0);
                    if ($descriptionHtml) {

                        $descHtml = str_get_html($descriptionHtml->innertext);
                        $descHtml->find('tr', 0)->outertext = "";
                        $stuff['descriptions'][] = $descHtml->outertext;
                    }
                } else {
                    console($colors->getColoredString('Articul not found', "light_red"));
                }
            }

            // для все ссылок собираем значения варианта цвета
            $articulHtml = $html->find('div[class=cmp_tbna_pane]', 0);
            $articul = trim($articulHtml->find('h2', 0)->innertext);
            $htmlImageContainer = $html->find('div[class=img_frame]', 0);

            $stuff['colors'][] = array(
                'art' => $articul,
                'img' => $htmlImageContainer->find('img', 0)->attr['src'],
                'name' => $link['name'],
            );
        }

        console($colors->getColoredString(count($stuff['colors']).' colors found', "light_green"));

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

        foreach ($stuff['docs'] as $docKey => &$docUrl) {
            console($colors->getColoredString('Download doc "'. $docUrl['link'] . '"', "yellow"));

            $rawDocUrl = $docUrl['link'];
            $docUrlParts = explode('?', $docUrl['link']);
            $docUrl['link'] = $stuff['articul'].'__'.($docKey+1).'.pdf';

            if (file_exists($path.'/'.$docUrl['link'])) {
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

            file_put_contents($path.'/'.$docUrl['link'], $result);
        }

        unset($docUrl);

        file_put_contents($path.'/stuff.json', json_encode($stuff));

        console($colors->getColoredString('Parsed "'. $stuff['name'] .'" '. $articul, "green"));

        file_put_contents($listPath, json_encode($stuffList));
    }

    file_put_contents($listPath, json_encode($stuffList));
}

console('ready');

$time = +microtime(true);

//echo $time;





