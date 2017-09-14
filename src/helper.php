<?php


function console($str) {
    echo $str . "\n";
}

function getSummColors($str_article) {
    $result = "";
    $tmp_arr = [];
    preg_match_all('~| ([0-9]+)$~si', $str_article, $tmp_arr);
    $result = $tmp_arr[1][117];
    return $result;
}

function getArticleColors($str_article) {
    $result = "";
    $tmp_arr = [];
    preg_match_all('~([0-9]+) |~Usi', $str_article, $tmp_arr);
    $result = $tmp_arr[1][0];
    return $result;
}
