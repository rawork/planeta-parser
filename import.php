#!/usr/bin/env php
<?php

if (php_sapi_name() != 'cli') {
    die('Commandline mode only accepted'."\n");
}

set_time_limit(0);

$siteFolder = __DIR__ . '/..';

$_SERVER["DOCUMENT_ROOT"] = $siteFolder;
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define("LANG", "s1");

define('CATALOG_IBLOCK_ID', 22);

$sites = array(
    'omoikiri',
    'faber',
    'elica',
    'mikadzo',
    'smeg',
    'franke',
);

$brands = array(
    'Omoikiri' => array('id' => 386, 'name' => 'Omoikiri'),
    'FABER' => array('id' => 380, 'name' => 'FABER'),
    'Elica' => array('id' => 382, 'name' => 'Elica'),
    'Mikadzo' => array('id' => 388, 'name' => 'Mikadzo'),
    'Smeg' => array('id' => 384, 'name' => 'Smeg'),
    'Franke' => array('id' => 379, 'name' => 'Franke'),
);

require($_SERVER["DOCUMENT_ROOT"]. "/bitrix/modules/main/include/prolog_before.php");
require_once ('src/helper.php');

CModule::IncludeModule('iblock');
CModule::IncludeModule('file');

foreach($sites as $site) {
    console('Start import '.$site);
    $stuffList = json_decode(file_get_contents(__DIR__ . '/content/' . $site . '/' . 'list.json'), true);

    foreach($stuffList as $stuffArticul) {
        $basePath = __DIR__ . '/content/' . $site . '/' . $stuffArticul . '/';

        $stuffData = json_decode(file_get_contents($basePath . 'stuff.json'), true);

        // Find $elementId
        $arSelect = Array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM","PROPERTY_ARTNUMBER", "PROPERTY_addon_photo", "PROPERTY_color_image", "PROPERTY_article_price");

        $articulFilter = array(
            "LOGIC" => "OR"
        );
        $articulFilter[] = array("PROPERTY_ARTNUMBER" => $stuffArticul);
        $articulFilter[] = array("NAME" => $stuffData['brand'] . ' ' .$stuffData['name']);
        $articulFilter[] = array("NAME" => $stuffData['name']);

        $arFilter = Array(
            "IBLOCK_ID" => CATALOG_IBLOCK_ID,
            $articulFilter,
        );
        $res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>5), $arSelect);

        $arTranslitParams = array("replace_space"=>"-","replace_other"=>"-");
        if ($ob = $res->GetNextElement()) {
            $arFields = $ob->GetFields();

            $el = new CIBlockElement;

            $PRODUCT_ID = $arFields['ID'];
            console('Product ' . $stuffData['articul'] . ' found: "' . $stuffData['brand'] . ' ' .$stuffData['name'].'"');

            $PROP = array();

            $PROP[107] = $stuffArticul;
            $PROP[108] = $stuffData['brand'];
            $PROP[120] = $brands[$stuffData['brand']]['id'];

            /*
             * todo Для кода товара, который используется в урл страницы товара нельзя использовать Cutil::translit,
             * т.к. он работает некорректно с русскими буквами в UTF-8
             * Cutil::translit($stuffData['brand'] . ' ' .$stuffData['name'], "ru", $arTranslitParams)
             */
            $arLoadProductArray = Array(
                "MODIFIED_BY"    => 1,
                "CODE"           => str2url($stuffData['brand'] . ' ' .$stuffData['name']),
                "PROPERTY_VALUES" => $PROP,
                "DETAIL_TEXT"    => implode('<br><br>', $stuffData['descriptions']),
                "DETAIL_TEXT_TYPE" => 'html',
            );

            if (count($stuffData['colors']) > 0) {
                $arLoadProductArray['DETAIL_PICTURE'] = CFile::MakeFileArray($basePath . $stuffData['colors'][0]['img']);
            } elseif (count($stuffData['images']) > 0) {
                $arLoadProductArray['DETAIL_PICTURE'] = CFile::MakeFileArray($basePath . $stuffData['images'][0]['original']);
            }

            if (in_array($stuffData['brand'], array('smeg'))) {
                $arLoadProductArray['NAME'] = $stuffData['brand'] . ' ' .$stuffData['name'];
            }

            $res = $el->Update($PRODUCT_ID, $arLoadProductArray);

            // Обновляем additional_text
            if (isset($stuffData['extra']) && is_array($stuffData['extra'])){
                CIBlockElement::SetPropertyValueCode(
                    $PRODUCT_ID,
                    'additional_text',
                    array(array("TYPE"=>"HTML", "TEXT"=> implode('<br>', $stuffData['extra'])))
                );
            }

            // Обновляем PROPERTY_addon_photo
            if ($stuffData['images']) {
                $PROP['addon_photo'] = array('VALUE' => false);
                $elUpdate = CIBlockElement::SetPropertyValuesEx($PRODUCT_ID, CATALOG_IBLOCK_ID, $PROP);

                $arFile = array();
                foreach ($stuffData['images'] as $image) {
                    $arFile[] = array("VALUE" => CFile::MakeFileArray($basePath . $image['original']), "DESCRIPTION"=>"");
                }
                CIBlockElement::SetPropertyValueCode($PRODUCT_ID, 'addon_photo', $arFile);
            }

            // Обновляем PROPERTY_color_image и PROPERTY_article_price
            if ($stuffData['colors']) {
                $PROP['color_image'] = array('VALUE' => false);
                $elUpdate = CIBlockElement::SetPropertyValuesEx($PRODUCT_ID, CATALOG_IBLOCK_ID, $PROP);

                $arFile = array();
                $arArticles = array();

                foreach ($stuffData['colors'] as $color) {
                    $fileData = CFile::MakeFileArray($basePath . $color['img']);
                    $arFile[] = array("VALUE" => $fileData, "DESCRIPTION"=> $color['name']);
                    $articlePrice = array($color['art'], $fileData['name'], '0');
                    $arArticles[] = array("VALUE"=> implode(' | ', $articlePrice),"DESCRIPTION"=>"");
                }
                CIBlockElement::SetPropertyValueCode($PRODUCT_ID, 'color_image', $arFile);
                CIBlockElement::SetPropertyValueCode($PRODUCT_ID, 'article_price', $arArticles );
            }

            console('Update - OK');
        } else {
            console('New Product "'.$stuffData['articul'].' - ' . $stuffData['brand'] . ' ' .$stuffData['name'] . '"');

            // Добавляем товар в каталог
            // description => DETAIL_TEXT
            // name => NAME & CODE
            // articul => PROPERTY_ARTNUMBER [107]
            // producer => PROPERTY_MANUFACTURER (text) [108]
            // PROPERTY_MANUFACTURER_CATALOG [120]
            // PROPERTY_addon_photo [137]
            // PROPERTY_color_image [138]
            // PROPERTY_additional_text [140]
            // PROPERTY_article_price [142]
            // PROPERTY_doc_file [139]

            $el = new CIBlockElement;

            $PROP = array();

            $PROP[107] = $stuffData['articul'];
            $PROP[108] = $stuffData['brand'];
            $PROP[120] = $brands[$stuffData['brand']]['id'];
            if (isset($stuffData['extra']) && is_array($stuffData['extra'])){
                $PROP[140] = array("VALUE" => array("TYPE" =>"HTML","TEXT" => implode('<br>', $stuffData['extra'])));
            }

            $arLoadProductArray = Array(
                "MODIFIED_BY"    => 1, // элемент изменен текущим пользователем
                "IBLOCK_SECTION_ID" => false,          // элемент лежит в корне раздела
                "IBLOCK_ID"      => CATALOG_IBLOCK_ID,
                "PROPERTY_VALUES"=> $PROP,
                "NAME"           => $stuffData['brand'] . ' ' .$stuffData['name'],
                "CODE"           => str2url($stuffData['brand'] . ' ' .$stuffData['name']), //Cutil::translit($stuffData['brand'] . ' ' .$stuffData['name'], "ru", $arTranslitParams),
                "ACTIVE"         => "Y",            // активен
                "PREVIEW_TEXT"   => "",
                "DETAIL_TEXT"    => implode('<br><br>', $stuffData['descriptions']),
                "DETAIL_TEXT_TYPE" => 'html',
            );

            if (count($stuffData['colors']) > 0) {
                $arLoadProductArray['DETAIL_PICTURE'] = CFile::MakeFileArray($basePath . $stuffData['colors'][0]['img']);
            } elseif (count($stuffData['images']) > 0) {
                $arLoadProductArray['DETAIL_PICTURE'] = CFile::MakeFileArray($basePath . $stuffData['images'][0]['original']);
            }

            if (isset($stuffData['section_id'])) {
                $arLoadProductArray['IBLOCK_SECTION_ID'] = $stuffData['section_id'];
            }

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

                // добавляем Файлы документации
                if (isset($stuffData['docs']) && $stuffData['docs']) {
                    $arFile = array();
                    foreach ($stuffData['docs'] as $doc) {
                        if (is_array($doc)) {
                            $arFile[] = array(
                                "VALUE" => CFile::MakeFileArray($basePath . $doc['link']), "DESCRIPTION" => $doc['description']);
                        } else {
                            $arFile[] = array(
                                "VALUE" => CFile::MakeFileArray($basePath . $doc), "DESCRIPTION" => "");
                        }
                    }

                    CIBlockElement::SetPropertyValueCode($PRODUCT_ID, 'doc_file', $arFile);
                }

                // добавляем Цвет - Изображение
                // добавляем Артикул | Цена | Цвет (ссылка)
                if ($stuffData['colors']) {
                    $arFile = array();
                    $arArticles = array();
                    foreach ($stuffData['colors'] as $color) {

                        $fileData = CFile::MakeFileArray($basePath . $color['img']);

                        $arFile[] = array("VALUE" => $fileData, "DESCRIPTION"=> $color['name']);

                        $articlePrice = array($color['art'], $fileData['name'], '0');

                        $arArticles[] = array("VALUE"=> implode(' | ', $articlePrice),"DESCRIPTION"=>"");

                    }
                    CIBlockElement::SetPropertyValueCode($PRODUCT_ID, 'color_image', $arFile);
                    CIBlockElement::SetPropertyValueCode($PRODUCT_ID, 'article_price', $arArticles );
                }

                console('Import - OK');
            } else {
                console("Import - Error: ".$el->LAST_ERROR);
            }
        }
    }
    console('Finish import '.$site);
}

CIBlock::clearIblockTagCache(CATALOG_IBLOCK_ID);

console('Ready');




