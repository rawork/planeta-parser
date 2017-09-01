<?php
require_once ('vendor/simplehtmldom/simple_html_dom.php');

$time = -microtime(true);

$baseUrl = 'http://www.omoikiri.ru';
$baseStuffName = 'Omoikiri';

$html = file_get_html($baseUrl.'/catalog');

$container = $html->find('div[class=cat_grid]', 0);

if ($container) {
    $links = $container->find('a');

    $categoryLinks = array();
    foreach ($links as $link) {
        $categoryLinks[] = $link->attr['href'];
    }

//    var_dump($categoryLinks);
    $goodLinks = array();
    foreach ($categoryLinks as $categoryLink) {
        $html = file_get_html($baseUrl.$categoryLink);

        $goodContainers = $html->find('div[class=item_column]');
        foreach ($goodContainers as $cont) {
            $links = $cont->find('a[href]');
            foreach ($links as $link) {
                if ($link->attr['href'] && strpos($link->attr['href'], 'NODE') === false) {
                    $goodLinks[] = $link->attr['href'];
                }
            }
        }
    }

    //    var_dump(count($goodLinks));

    foreach ($goodLinks as $link) {
        //    $link = 'http://www.omoikiri.ru/catalog/dispenser/om-01';
        //    $link = 'http://www.omoikiri.ru/catalog/purifier/pure-drop-214';
        //    $link = 'http://www.omoikiri.ru/catalog/washer/akisame-78';

        $html = file_get_html($baseUrl.$link);

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


        $stuff = array(
            'name' => strip_tags($html->find('h1', 0)->innertext),
            'articul' => $html->find('div[class=prodArticle]', 0)->find('span', 0)->innertext,
            'descriptions' => array(),
            'images' => $images,
            'colors' => $colorJson,
            'schema' => null,
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

        $path = __DIR__ . '/content/' . $baseStuffName . '/' . $stuff['articul'];

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
                curl_setopt($ch, CURLOPT_VERBOSE, 1);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
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
                curl_setopt($ch, CURLOPT_VERBOSE, 1);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
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
                curl_setopt($ch, CURLOPT_VERBOSE, 1);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
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

//        var_dump($stuff['articul']);
    }
}

echo 'ready';

$time = +microtime(true);

//echo $time;





