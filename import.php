#!/usr/bin/env php
<?php

if (php_sapi_name() != 'cli') {
    die('Commandline mode only accepted'."\n");
}

set_time_limit(0);

$siteFolder = __DIR__ . '/../..';

$_SERVER["DOCUMENT_ROOT"] = $siteFolder;
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define("LANG", "s1");

define('CATALOG_IBLOCK_ID', 22);

$sites = array(
    'omoikiri'
);

$brands = array(
    'Omoikiri' => 386
);

require($_SERVER["DOCUMENT_ROOT"]. "/bitrix/modules/main/include/prolog_before.php");
require_once ('src/helper.php');

CModule::IncludeModule('iblock');
CModule::IncludeModule('file');


$test = "4993335 | /var/www/planethab/data/www/planethab.webaltsite.ru/parser/tmp_files/4993335mi.png | 4993335";

//$res = getSummColors($test);
//$res2 = getArticleColors($test);
//
//var_dump($res, $res2);
//die;


foreach($sites as $site) {
    $stuffList = json_decode(file_get_contents(__DIR__ . '/content/' . $site . '/' . 'list.json'), true);

    foreach($stuffList as $stuffArticul) {
        $basePath = __DIR__ . '/content/' . $site . '/' . $stuffArticul . '/';

        $stuffData = json_decode(file_get_contents($basePath . 'stuff.json'), true);

        var_dump($stuffData);

        die;

        // Find $elementId
        $arSelect = Array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM","PROPERTY_ARTNUMBER", "PROPERTY_addon_photo", "PROPERTY_color_image", "PROPERTY_article_price");

        $articulFilter = array(
            "LOGIC" => "OR"
        );
        $articulFilter[] = array("PROPERTY_ARTNUMBER" => $stuffArticul);
        $articulFilter[] = array("NAME" => $stuffData['brand'] . ' ' .$stuffData['name']);

        $arFilter = Array(
            "IBLOCK_ID" => CATALOG_IBLOCK_ID,
            //"PROPERTY_ARTNUMBER" => $stuffArticul,
            $articulFilter,
        );
        $res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>5), $arSelect);

        if ($ob = $res->GetNextElement()) {
            $arFields = $ob->GetFields();
            $PRODUCT_ID = $arFields['ID'];
            console("Product $articul found: " . $stuffData['brand'] . ' ' .$stuffData['name']);

            // todo обновить ДопИзображение, Цвет-Изображение, Артикул|Цена|Цвет(Ссылка на картинку)
            // PROPERTY_color_image
            // PROPERTY_article_price
            $arLoadProductArray = Array(
                "MODIFIED_BY"    => 1,
                "DETAIL_TEXT"    => implode('<br><br>', $stuffData['descriptions']),
                "DETAIL_TEXT_TYPE" => 'html',
            );

            $res = $el->Update($PRODUCT_ID, $arLoadProductArray);

            $PROP['color_image'] = array('VALUE' => false);
            $elUpdate = CIBlockElement::SetPropertyValuesEx($PRODUCT_ID, CATALOG_IBLOCK_ID, $PROP);

            $arFile = array();
            $arArticles = array();


            foreach ($stuffData['colors'] as $color) {
                $fileData = CFile::MakeFileArray($basePath . $color['img']);
                $arFile[] = array("VALUE" => $fileData, "DESCRIPTION"=> $color['name']);
                $articlePrice = array($color['art'], $fileData['name'], $color['art']);
                $arArticles[] = array("VALUE"=> implode(' | ', $articlePrice),"DESCRIPTION"=>"");
            }
            CIBlockElement::SetPropertyValueCode($PRODUCT_ID, 'color_image', $arFile);
            CIBlockElement::SetPropertyValueCode($PRODUCT_ID, 'article_price', $arArticles );

            console('Update - OK');
        } else {
            console('New Product '.$articul.' - "' . $stuffData['brand'] . ' ' .$stuffData['name'] . '""');


            // todo добавить товар в каталог
            // description => DETAIL_TEXT
            // name => NAME & CODE
            // articul => PROPERTY_ARTNUMBER [107]
            // producer => PROPERTY_MANUFACTURER (text) [108]
            // PROPERTY_MANUFACTURER_CATALOG [120]

            // PROPERTY_addon_photo [137]
            // PROPERTY_color_image [138]
            // PROPERTY_additional_text [140]

            // PROPERTY_article_price [142]

            $el = new CIBlockElement;

            $PROP = array();

            $PROP[107] = $stuffData['articul'];
            $PROP[108] = $stuffData['brand'];
            $PROP[120] = $brands[$stuffData['brand']];

            $arLoadProductArray = Array(
                "MODIFIED_BY"    => 1, // элемент изменен текущим пользователем
                "IBLOCK_SECTION_ID" => false,          // элемент лежит в корне раздела
                "IBLOCK_ID"      => CATALOG_IBLOCK_ID,
                "PROPERTY_VALUES"=> $PROP,
                "NAME"           => $stuffData['brand'] . ' ' .$stuffData['name'],
                "CODE"           => $stuffData['brand'] . ' ' .$stuffData['name'],
                "ACTIVE"         => "Y",            // активен
                "PREVIEW_TEXT"   => "",
                "DETAIL_TEXT"    => implode('<br><br>', $stuffData['descriptions']),
                "DETAIL_TEXT_TYPE" => 'html',
                "DETAIL_PICTURE" => CFile::MakeFileArray($basePath . $stuffData['colors'][0]['img']),
            );

            if($PRODUCT_ID = $el->Add($arLoadProductArray)) {
                console("New Product ID: ".$PRODUCT_ID);

                // добавляем ДопИзображение
                $arFile = array();
                foreach ($stuffData['images'] as $image) {
                    $arFile[] = array("VALUE" => CFile::MakeFileArray($basePath . $image['original']), "DESCRIPTION"=>"");
                }
                if ($stuffData['schema']) {
                    $arFile[] = array("VALUE" => CFile::MakeFileArray($basePath . $stuffData['schema']), "DESCRIPTION"=>"");
                }
                CIBlockElement::SetPropertyValueCode($PRODUCT_ID, 'addon_photo', $arFile);

                // добавляем Цвет - Изображение
                // добавляем Артикул | Цена | Цвет (ссылка)
                $arFile = array();
                $arArticles = array();
                foreach ($stuffData['colors'] as $color) {

                    $fileData = CFile::MakeFileArray($basePath . $color['img']);

                    $arFile[] = array("VALUE" => $fileData, "DESCRIPTION"=> $color['name']);

                    $articlePrice = array($color['art'], $fileData['name'], $color['art']);

                    $arArticles[] = array("VALUE"=> implode(' | ', $articlePrice),"DESCRIPTION"=>"");

                }
                CIBlockElement::SetPropertyValueCode($PRODUCT_ID, 'color_image', $arFile);
                CIBlockElement::SetPropertyValueCode($PRODUCT_ID, 'article_price', $arArticles );

                console('Import - OK');
            } else {
                console("Import - Error: ".$el->LAST_ERROR);
            }
        }
    }
}



