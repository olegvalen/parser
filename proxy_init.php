<?php

require_once('/home/oleg-pc/public_html/phpQuery/phpQuery/phpQuery.php');
require_once('/home/oleg-pc/public_html/phpQuery/proxy/proxy.php');

$log = '/home/oleg-pc/public_html/phpQuery/logs/' . date('Y-m-d');
loging('prepare_proxy->start');

Proxy::set_array_user_agent();

$httpbin = new Proxy_httpbin();
$httpbin->get_valid_init_proxy();

$olx = new Proxy_olx();
$olx->get_valid_init_proxy();

$m2 = new Proxy_m2();
$m2->get_valid_init_proxy();

$gorod = new Proxy_gorod();
$gorod->get_valid_init_proxy();

$variants = new Proxy_variants();
$variants->get_valid_init_proxy();

$nedelka = new Proxy_nedelka();
$nedelka->get_valid_init_proxy();

$domria = new Proxy_domria();
$domria->get_valid_init_proxy();

loging('prepare_proxy->end');
phpQuery::unloadDocuments();
gc_collect_cycles();
