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

$brands = array(
    'Faberspa' => array('id' => 380, 'name' => 'FABER'),
);

require($_SERVER["DOCUMENT_ROOT"]. "/bitrix/modules/main/include/prolog_before.php");
require_once ('src/helper.php');

CModule::IncludeModule('iblock');
CModule::IncludeModule('util');

$arTranslitParams = array("replace_space"=>"-","replace_other"=>"-");

foreach($brands as $brandName => $brandData) {
    console('Start correct names for brand '.$brandName);

    // Find $elementId
    $arSelect = Array("ID", "IBLOCK_ID", "NAME");

    $arFilter = Array(
        "IBLOCK_ID" => CATALOG_IBLOCK_ID,
        "NAME" => $brandName . '%',
    );
    $res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>1000), $arSelect);


    while ($ob = $res->GetNextElement()) {
        $arFields = $ob->GetFields();
        $el = new CIBlockElement;
        $PRODUCT_ID = $arFields['ID'];
        console("Product found: [" . $arFields['ID'] . '] ' . $arFields['NAME']);
        $newName = str_replace($brandName, $brandData['name'], $arFields['NAME']);
        console('New name = '. $newName);

        $arLoadProductArray = Array(
            "MODIFIED_BY"    => 1,
            "NAME"           => $newName,
            "CODE"           => Cutil::translit($newName, "ru", $arTranslitParams),
        );

        $res = $el->Update($PRODUCT_ID, $arLoadProductArray);

        console('Update - OK');
    }
    console('Finish correction '.$brandName);
}

console('Ready');




