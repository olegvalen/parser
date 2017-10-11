<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('/home/oleg-pc/public_html/phpQuery/proxy/parser.php');

$parse_variants_list = new Parse_variants_list('http://variants.dp.ua/index.php?cat=22&pg=', 'кв');
$parse_variants_list->parse();
$parse_gorod = new Parse_gorod();
$parse_gorod->parse();
$parse_olx_list = new Parse_olx_list('https://www.olx.ua/nedvizhimost/arenda-kvartir/dolgosrochnaya-arenda-kvartir/dnepr/?page=', 'кв');
$parse_olx_list->parse();
$parse_olx_list = new Parse_olx_list('https://www.olx.ua/nedvizhimost/arenda-komnat/dolgosrochnaya-arenda-komnat/dnepr/?page=', 'к');
$parse_olx_list->parse();
$parse_olx_list = new Parse_olx_list('https://www.olx.ua/nedvizhimost/arenda-domov/dolgosrochnaya-arenda-domov/dnepr/?page=', 'д');
$parse_olx_list->parse();
