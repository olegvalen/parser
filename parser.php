<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('/home/oleg-pc/public_html/phpQuery/include/db.php');
require_once('/home/oleg-pc/public_html/phpQuery/include/functions.php');
require_once('/home/oleg-pc/public_html/phpQuery/proxy/proxy.php');

class Parse_olx extends Proxy
{
    function __construct()
    {
        $name = 'olx';
        $headers = array(
            'Host: www.olx.ua',
            'Cache-Control: max-age=0',
            'Upgrade-Insecure-Requests: 1',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.8,ru;q=0.6',
            'Connection: close'
        );

        $path_prepare_proxy_from = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_' . $name;
        $path_cookie = '/home/oleg-pc/public_html/phpQuery/proxy/cookie_parse/cookie_' . $name;
        $this->params = array(
            'name' => $name,
            'headers' => $headers,
            'cur_i' => 1,
            '$path_prepare_proxy_from' => $path_prepare_proxy_from,
            'path_cookie' => $path_cookie,
            'tries' => 15,
            'sleep_start' => 1,
            'sleep_end' => 2,
            'validate_func' => 'is_validate',
            'validate' => true
        );

        $this->curl_params = array(
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_SSL_VERIFYPEER' => 0,
            'CURLOPT_SSL_VERIFYHOST' => 0,
            'CURLOPT_CONNECTTIMEOUT' => 3,
            'CURLOPT_TIMEOUT' => 8,
            'CURLOPT_COOKIEJAR' => $path_cookie,
            'CURLOPT_COOKIEFILE' => $path_cookie,
            'CURLOPT_HTTPHEADER' => $headers
        );

        $this->proxies = explode("\n", file_get_contents($this->params['$path_prepare_proxy_from']));
        $this->set_random_proxy();
    }

    public function parse()
    {
        if (count($this->proxies) == 0) {
            return;
        }

        do {
            try {
                $html = $this->get_xml_page();
                $this->params['cur_i']++;
                $html = phpQuery::newDocument($html);
                $elements = $html->find('.fixed.offers.breakword:not(".offers--top") .wrap');
                $countElements = $elements->count();
                foreach ($elements as $element) {

                    $importDateText = pq($element)->find('p.color-9.lheight16.marginbott5.x-normal')->text();
                    $importDate = date('Y-m-d');
                    if (strpos($importDateText, 'Сегодня') == false) {
                        break 2;
                    }
                    $urlSource = pq($element)->find('a')->attr('href');
//                    $urlSource = 'https://www.olx.ua/obyavlenie/arenda-kvartiry-2-kom-v-novostroe-po-ul-liteynaya-tsentr-most-siti-IDsrRM2.html#21391abbf4';

                    $pos = strpos($urlSource, '.html');
                    $urlBase = $urlSource;
                    if ($pos !== false) {
                        $urlBase = substr($urlSource, 0, $pos + 5);
                    }
                    loging($urlBase);

                    if (DB::db_url_exists($urlBase)) {
                        continue;
                    }

                    if (preg_match('/-ID?(.*?).html/', $urlSource, $matchesID)) {
                        $id = $matchesID[1];
                    } else {
                        continue;
                    }
                    $urlPhones = 'https://www.olx.ua/ajax/misc/contact/phone/' . $id;
                    $parse_olx_tel = new Parse_olx_tel($urlPhones);
                    $parse_olx_tel->params['headers'][] = 'Referer: ' . $urlBase;
                    $parse_olx_tel->curl_params['CURLOPT_HTTPHEADER'][] = 'Referer: ' . $urlBase;
                    $phonesHtml = $parse_olx_tel->get_xml_page();
                    if ($phonesHtml == 'Извините, страница не найдена') {
                        continue;
                    } elseif ($phonesHtml == false) {
                        DB::db_delete_url($urlBase);
                        continue;
                    }

                    $matchesPhones = $this->getPhones($phonesHtml);
                    if ($matchesPhones === false) {
                        continue;
                    }
                    if (DB::db_tel_exists($matchesPhones)) {
                        continue;
                    }

                    $textHeader = pq($element)->find('.marginright5.link.linkWithHash.detailsLink')->text();
                    $mainRegex = false;
                    if (preg_match('/(?<![а-яА-ЯёЁ])сво[йюя]|хозя[ий]|владел|собствен|не посредн|не ри[еэ]лто(?=[а-яА-ЯёЁ])/iu', $textHeader)) {
                        $mainRegex = true;
                    }
                    if ($mainRegex == false) {
                        if ($this->isRieltor($matchesPhones)) {
                            continue;
                        }
                    }

                    $parse_olx_adv = new Parse_olx_adv($urlSource);
                    $parse_olx_adv->params['headers'][] = 'Referer: ' . $this->params['url'] . (isset($this->params['cur_i']) ? $this->params['cur_i'] - 1 : '');
                    $parse_olx_adv->curl_params['CURLOPT_HTTPHEADER'][] = 'Referer: ' . $this->params['url'] . (isset($this->params['cur_i']) ? $this->params['cur_i'] - 1 : '');
                    $innerHtml = $parse_olx_adv->get_xml_page();
                    if ($innerHtml == false) {
                        DB::db_delete_url($urlBase);
                        continue;
                    }

                    $innerHtml = phpQuery::newDocument($innerHtml);
                    $from = pq($innerHtml)->find('#offerdescription > div.clr.descriptioncontent.marginbott20 > table > tr:nth-child(1) > td:nth-child(1) > table > tr > td > strong > a')->text();
                    if (strpos($from, 'Бизнес') > 0) {
                        continue;
                    }

                    $shortInfo = pq($innerHtml)->find('#offerdescription > div.offer-titlebox > h1')->text();
                    $shortInfo = prepareString($shortInfo);
                    $text = pq($innerHtml)->find('#textContent > p')->text();
                    $text = prepareString($text);

                    $textPrice = pq($innerHtml)->find('#offeractions > div.price-label > strong')->text();
                    $price = preg_replace('/[\D]/', '', $textPrice);
                    if (strpos($textPrice, '$') != false) {
                        $price *= $this->USD;
                    } else if (strpos($textPrice, '€') != false) {
                        $price *= $this->EVRO;
                    }

                    $numberOfRooms = pq($innerHtml)->find('#offerdescription > div.clr.descriptioncontent.marginbott20 > table > tr:nth-child(2) > td.col > table > tr > td > strong')->text();
                    $numberOfRooms = preg_replace('/[\D]/', '', $numberOfRooms);

                    $this->db_obj = array(
                        'importDate' => $importDate,
                        'rentSale' => 'аренда',
                        'city' => 'Днепр',
                        'district' => 'O',
                        'kind' => $this->kind,
                        'phoneNumber1' => null,
                        'phoneNumber2' => null,
                        'numberOfRooms' => $numberOfRooms == '' ? null : $numberOfRooms,
                        'cost' => $price == '' ? null : $price,
                        'urlSource' => $urlSource,
                        'info' => $text,
                        'shortInfo' => $shortInfo
                    );
                    DB::db_add_objects_tmp($this->db_obj);
                }
                phpQuery::unloadDocuments();
            } catch (Exception $e) {
                loging($e->getMessage());
            }
        } while ($countElements > 0);
    }
}

class Parse_olx_list extends Parse_olx
{
    function __construct($url, $kind)
    {
        parent::__construct();
        $params = array(
            'url' => $url,
            'validate_element' => '.wrap'
        );
        $this->params = array_merge($this->params, $params);
        $this->kind = $kind;
    }
}

class Parse_olx_adv extends Parse_olx
{
    function __construct($url)
    {
        parent::__construct();
        $params = array(
            'url' => $url,
            'validate_element' => '.clr.offerbody',
            'tries' => 5
        );
        $this->params = array_merge($this->params, $params);
        unset($this->params['cur_i']);
    }
}

class Parse_olx_tel extends Parse_olx
{
    function __construct($url)
    {
        parent::__construct();
        $params = array(
            'url' => $url,
            'tries' => 5,
            'sleep_start' => 1,
            'sleep_end' => 2,
            'validate_func' => 'is_validate_tel'
        );
        $this->params = array_merge($this->params, $params);
        unset($this->params['cur_i']);
    }

    function is_validate($page)
    {
        if (isJSON($page)) {
            return true;
        }
        return false;
    }
}

class Parse_m2 extends Proxy
{
    function __construct($url)
    {
        $name = 'm2';
        $validate_element = '#main-content-wrapper';
        $headers = array(
            'Host: ua.m2bomber.com',
            'Upgrade-Insecure-Requests: 1',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.8,ru;q=0.6',
            'Referer: https://ua.m2bomber.com/',
            'Connection: close'
        );
        $path_prepare_proxy_from = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_' . $name;
        $path_cookie = '/home/oleg-pc/public_html/phpQuery/proxy/cookie_parse/cookie_' . $name;
        $this->params = array(
            'name' => $name,
            'url' => $url,
            'validate_element' => $validate_element,
            'headers' => $headers,
            '$path_prepare_proxy_from' => $path_prepare_proxy_from,
            'path_cookie' => $path_cookie,
            'tries' => 5,
            'sleep_start' => 1,
            'sleep_end' => 2,
            'validate_func' => 'is_validate',
            'validate' => true
        );

        $this->curl_params = array(
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_SSL_VERIFYPEER' => 0,
            'CURLOPT_SSL_VERIFYHOST' => 0,
            'CURLOPT_CONNECTTIMEOUT' => 3,
            'CURLOPT_TIMEOUT' => 10,
            'CURLOPT_COOKIEJAR' => $path_cookie,
            'CURLOPT_COOKIEFILE' => $path_cookie,
            'CURLOPT_HTTPHEADER' => $headers
        );

        $this->proxies = explode("\n", file_get_contents($this->params['$path_prepare_proxy_from']));
        $this->set_random_proxy();
    }
}

class Parse_variants extends Proxy
{
    function __construct()
    {
        $name = 'variants';
        $headers = array(
            'Host: variants.dp.ua',
            'Cache-Control: max-age=0',
            'Upgrade-Insecure-Requests: 1',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.8,ru;q=0.6',
            'Connection: close'
        );
        $path_prepare_proxy_from = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_' . $name;
        $path_cookie = '/home/oleg-pc/public_html/phpQuery/proxy/cookie_parse/cookie_' . $name;
        $this->params = array(
            'name' => $name,
            'headers' => $headers,
            'cur_i' => 0,
            '$path_prepare_proxy_from' => $path_prepare_proxy_from,
            'path_cookie' => $path_cookie,
            'tries' => 15,
            'sleep_start' => 1,
            'sleep_end' => 2,
            'validate_func' => 'is_validate',
            'validate' => true
        );

        $this->curl_params = array(
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_CONNECTTIMEOUT' => 3,
            'CURLOPT_TIMEOUT' => 5,
            'CURLOPT_COOKIEJAR' => $path_cookie,
            'CURLOPT_COOKIEFILE' => $path_cookie,
            'CURLOPT_HTTPHEADER' => $headers
        );

        $this->proxies = explode("\n", file_get_contents($this->params['$path_prepare_proxy_from']));
        $this->set_random_proxy();
    }

    public function parse()
    {
        if (count($this->proxies) == 0) {
            return;
        }
        do {
            $html = $this->get_xml_page();
            $this->params['cur_i']++;
            $html = phpQuery::newDocument($html);
            $elements = $html->find('.adv_text');
            $countElements = $elements->count();
            foreach ($elements as $element) {
                try {
                    $href = pq($element)->find('a')->attr('href');
                    $urlSource = 'http://variants.dp.ua' . $href;
                    loging($urlSource);

                    if (DB::db_url_exists($urlSource)) {
                        continue;
                    }

                    $parse_variants_adv = new Parse_variants_adv($urlSource);
                    $parse_variants_adv->params['headers'][] = 'Referer: ' . $this->params['url'] . (isset($this->params['cur_i']) ? $this->params['cur_i'] - 1 : '');
                    $parse_variants_adv->curl_params['CURLOPT_HTTPHEADER'][] = 'Referer: ' . $this->params['url'] . (isset($this->params['cur_i']) ? $this->params['cur_i'] - 1 : '');
                    $innerHtml = $parse_variants_adv->get_xml_page();
                    if ($innerHtml == false) {
                        DB::db_delete_url($urlSource);
                        continue;
                    }

                    $innerHtml = phpQuery::newDocument($innerHtml);
                    $shortInfo = pq($innerHtml)->find('#adv_text_inside > h1')->text();
                    $shortInfo = prepareString($shortInfo);
                    $text = pq($innerHtml)->find('.text')->text();
                    $text = prepareString($text);

                    $matchesPhones = $this->getPhones($text);
                    if ($matchesPhones === false) {
                        continue;
                    }
                    if (DB::db_tel_exists($matchesPhones)) {
                        continue;
                    }

                    $curDate = date('Y-m-d');
                    $importDate = pq($innerHtml)->find('#adv_info > ul > li:nth-child(1) > span')->text();
                    $importDate = date_create_from_format('Y-m-d H:i:s', $importDate)->format('Y-m-d');
                    if ($importDate < $curDate) {
                        break 2;
                    }

                    if (preg_match('/Цена:? (.*?) грн/i', $text, $matchesCost)) {
                        $price = $matchesCost[1];
                    } else {
                        $price = 0;
                    }

                    $mainRegex = false;
                    if (preg_match('/(?<![а-яА-ЯёЁ])сво[йюя]|хозя[ий]|владел|собствен|не посредн|не ри[еэ]лто(?=[а-яА-ЯёЁ])/iu', $text)) {
                        $mainRegex = true;
                    }
                    if ($mainRegex == false) {
                        if ($this->isRieltor($matchesPhones)) {
                            continue;
                        }
                    }

                    $this->db_obj = array(
                        'importDate' => $importDate,
                        'rentSale' => 'аренда',
                        'city' => 'Днепр',
                        'district' => 'V',
                        'kind' => $this->kind,
                        'phoneNumber1' => count($matchesPhones) > 0 ? $matchesPhones[0] : null,
                        'phoneNumber2' => count($matchesPhones) > 1 ? $matchesPhones[1] : null,
                        'numberOfRooms' => 0,
                        'cost' => $price,
                        'urlSource' => $urlSource,
                        'info' => $text,
                        'shortInfo' => $shortInfo
                    );
                    DB::db_add_objects_tmp($this->db_obj);

                } catch (Exception $e) {
                    loging($e->getMessage());
                }
            }
            phpQuery::unloadDocuments();
        } while ($countElements > 0);
    }
}

class Parse_variants_list extends Parse_variants
{
    function __construct($url, $kind)
    {
        parent::__construct();
        $params = array(
            'url' => $url,
            'validate_element' => '.adv_text'
        );
        $this->params = array_merge($this->params, $params);
        $this->kind = $kind;
    }
}

class Parse_variants_adv extends Parse_variants
{
    function __construct($url)
    {
        parent::__construct();
        $params = array(
            'url' => $url,
            'validate_element' => '#adv_text_inside',
            'tries' => 5
        );
        $this->params = array_merge($this->params, $params);
        unset($this->params['cur_i']);
    }
}

class Parse_gorod extends Proxy
{
    function __construct()
    {
        $name = 'gorod';
        $url = 'http://gorod.dp.ua/gazeta/list.php?id=12&kind=7&page=';
        $headers = array(
            'Host: gorod.dp.ua',
            'Upgrade-Insecure-Requests: 1',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.8,ru;q=0.6',
            'Connection: close'
        );
        $path_prepare_proxy_from = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_' . $name;
        $path_cookie = '/home/oleg-pc/public_html/phpQuery/proxy/cookie_parse/cookie_' . $name;
        $this->params = array(
            'name' => $name,
            'url' => $url,
            'validate_element' => '.obyava',
            'headers' => $headers,
            'cur_i' => 0,
            '$path_prepare_proxy_from' => $path_prepare_proxy_from,
            'path_cookie' => $path_cookie,
            'tries' => 15,
            'sleep_start' => 1,
            'sleep_end' => 2,
            'validate_func' => 'is_validate',
            'validate' => true
        );

        $this->curl_params = array(
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_CONNECTTIMEOUT' => 3,
            'CURLOPT_TIMEOUT' => 8,
            'CURLOPT_COOKIEJAR' => $path_cookie,
            'CURLOPT_COOKIEFILE' => $path_cookie,
            'CURLOPT_HTTPHEADER' => $headers
        );

        $this->proxies = explode("\n", file_get_contents($this->params['$path_prepare_proxy_from']));
        $this->set_random_proxy();
    }

    public function parse()
    {
        if (count($this->proxies) == 0) {
            return;
        }

        do {
            $html = $this->get_xml_page();
            $this->params['cur_i']++;
            $html = phpQuery::newDocument($html);
            $elements = $html->find('.obyava');
            $countElements = $elements->count();
            foreach ($elements as $element) {
                try {
                    $text = pq($element)->find('.dark3')->text();
                    $text = prepareString($text);
                    $href = pq($element)->find('a.dark3')->attr('href');
                    $urlSource = 'http://gorod.dp.ua/gazeta/' . $href;
                    loging($urlSource);

                    if (DB::db_url_exists($urlSource)) {
                        continue;
                    }

                    if (preg_match('/СДАМ, (Магазин, торговые площади)|Офис|Здание|Гараж|Склад|(Земельный участок)|Производство|(Сфера услуг \(салон, ресторан...\))/iu', $text)) {
                        continue;
                    }

                    $textPhones = pq($element)->find('p')->text();
                    $matchesPhones = $this->getPhones($textPhones);
                    if ($matchesPhones === false) {
                        continue;
                    }

                    $importDate = pq($element)->find('.norm8')->text();
                    if (preg_match('/[0-9]{1,2}.[0-9]{1,2}.[0-9]{4}/', $importDate, $matchesDate)) {
                        $importDate = $matchesDate[0];
                    } else {
                        $importDate = date('Y-m-d');
                    }
                    $importDate = date_create_from_format('d.m.Y', $importDate)->format('Y-m-d');
                    $curDate = date('Y-m-d');
                    if ($importDate < $curDate) {
                        break 2;
                    }

                    if (DB::db_tel_exists($matchesPhones)) {
                        continue;
                    }

                    if (preg_match('/Цена:? (.*?) грн/i', $text, $matchesCost)) {
                        $price = $matchesCost[1];
                    } else {
                        $price = 0;
                    }

                    if (preg_match('/СДАМ, Комната/iu', $text)) {
                        $kind = 'к';
                    } else if (preg_match('/СДАМ, Дом/iu', $text)) {
                        $kind = 'д';
                    } else if (preg_match('/СДАМ, 1-к квартира/iu', $text)) {
                        $kind = 'кв';
                        $numberOfRooms = '1';
                    } else if (preg_match('/СДАМ, 2-к квартира/iu', $text)) {
                        $kind = 'кв';
                        $numberOfRooms = '2';
                    } else if (preg_match('/СДАМ, 3-к квартира/iu', $text)) {
                        $kind = 'кв';
                        $numberOfRooms = '3';
                    } else if (preg_match('/СДАМ, 4-к и более квартира/iu', $text)) {
                        $kind = 'кв';
                        $numberOfRooms = '4';
                    }

                    $mainRegex = false;
                    if (preg_match('/(?<![а-яА-ЯёЁ])сво[йюя]|хозя[ий]|владел|собствен|не посредн|не ри[еэ]лто(?=[а-яА-ЯёЁ])/iu', $text)) {
                        $mainRegex = true;
                    }
                    if ($mainRegex == false) {
                        if ($this->isRieltor($matchesPhones)) {
                            continue;
                        }
                    }

                    $this->db_obj = array(
                        'importDate' => $importDate,
                        'rentSale' => 'аренда',
                        'city' => 'Днепр',
                        'district' => 'G',
                        'kind' => $kind,
                        'phoneNumber1' => count($matchesPhones) > 0 ? $matchesPhones[0] : null,
                        'phoneNumber2' => count($matchesPhones) > 1 ? $matchesPhones[1] : null,
                        'numberOfRooms' => $numberOfRooms == '' ? null : $numberOfRooms,
                        'cost' => $price,
                        'urlSource' => $urlSource,
                        'info' => $text,
                        'shortInfo' => ''
                    );
                    DB::db_add_objects_tmp($this->db_obj);
                } catch (Exception $e) {
                    loging($e->getMessage());
                }
            }
            phpQuery::unloadDocuments();
        } while ($countElements > 0);
    }
}

class Parse_nedelka extends Proxy
{
    function __construct()
    {
        $name = 'nedelka';
        $headers = array(
            'Host: nedelka.dp.ua',
            'Accept-Encoding: gzip, deflate, sdch',
            'Accept-Language: en-US,en;q=0.8,ru;q=0.6',
            'Cache-Control: max-age=0',
            'Connection: close',
            'Upgrade-Insecure-Requests: 1'
        );
        $path_prepare_proxy_from = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_' . $name;
        $path_cookie = '/home/oleg-pc/public_html/phpQuery/proxy/cookie_parse/cookie_' . $name;
        $this->params = array(
            'name' => $name,
            'headers' => $headers,
            '$path_prepare_proxy_from' => $path_prepare_proxy_from,
            'path_cookie' => $path_cookie,
            'tries' => 15,
            'sleep_start' => 1,
            'sleep_end' => 2,
            'validate_func' => 'is_validate',
            'validate' => true
        );

        $this->curl_params = array(
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_CONNECTTIMEOUT' => 3,
            'CURLOPT_TIMEOUT' => 8,
            'CURLOPT_COOKIEJAR' => $path_cookie,
            'CURLOPT_COOKIEFILE' => $path_cookie,
            'CURLOPT_HTTPHEADER' => $headers
        );

        $this->proxies = explode("\n", file_get_contents($this->params['$path_prepare_proxy_from']));
        $this->set_random_proxy();
    }

    public function parse()
    {
        if (count($this->proxies) == 0) {
            return;
        }

        try {
            $html = $this->get_xml_page();
            $html = phpQuery::newDocument($html);
            $elements = $html->find('.sp-body2 > ul> li > a');
            foreach ($elements as $element) {
                $href = pq($element)->attr('href');
                $text = pq($element)->text();
                if (strpos('Квартиры|1-комнатные|2-комнатные|3-комнатные|4-комнатные|5-комнатные|6-комнатные|7-комнатные|Дома', $text) !== false) {
                    $urlSource = 'http://nedelka.dp.ua' . $href;
                    loging($urlSource);

                    if (DB::db_url_exists($urlSource)) {
                        continue;
                    }

                    $parse_nedelka_adv = new Parse_nedelka_adv($urlSource);
                    $parse_nedelka_adv->params['headers'][] = 'Referer: http://nedelka.dp.ua/stroki/nedv_sdam';
                    $parse_nedelka_adv->curl_params['CURLOPT_HTTPHEADER'][] = 'Referer: http://nedelka.dp.ua/stroki/nedv_sdam';
                    $innerHtml = $parse_nedelka_adv->get_xml_page();
                    if ($innerHtml == false) {
                        DB::db_delete_url($urlSource);
                        continue;
                    }

                    $innerHtml = phpQuery::newDocument($innerHtml);
                    $elementsPage = pq($innerHtml)->find('.strcontent > p');
                    foreach ($elementsPage as $elPage) {
                        $textAdv = pq($elPage)->text();

                        $matchesPhones = $this->getPhones($textAdv);
                        if ($matchesPhones === false) {
                            continue;
                        }
                        if (DB::db_tel_exists($matchesPhones)) {
                            continue;
                        }

                        $mainRegex = false;
                        if (preg_match('/(?<![а-яА-ЯёЁ])сво[йюя]|хозя[ий]|владел|собствен|не посредн|не ри[еэ]лто(?=[а-яА-ЯёЁ])/iu', $textAdv)) {
                            $mainRegex = true;
                        }
                        if ($mainRegex == false) {
                            if (isRieltor($matchesPhones)) {
                                continue;
                            }
                        }

                        $numberOfRooms = 0;
                        $kind = '';
                        if (strpos($text, '1-комнатные') !== false) {
                            $numberOfRooms = 1;
                            $kind = 'кв';
                        } elseif (strpos($text, '2-комнатные') !== false) {
                            $numberOfRooms = 2;
                            $kind = 'кв';
                        } elseif (strpos($text, '3-комнатные') !== false) {
                            $numberOfRooms = 3;
                            $kind = 'кв';
                        } elseif (strpos($text, '4-комнатные') !== false) {
                            $numberOfRooms = 4;
                            $kind = 'кв';
                        } elseif (strpos($text, 'комнатные') !== false) {
                            $numberOfRooms = 5;
                            $kind = 'кв';
                        } elseif (strpos($text, 'Дома') !== false) {
                            $kind = 'д';
                        } elseif (strpos($text, 'Квартиры') !== false) {
                            $numberOfRooms = 1;
                            $kind = 'к';
                        }

                        $curDate = date('Y-m-d');
                        $this->db_obj = array(
                            'importDate' => $curDate,
                            'rentSale' => 'аренда',
                            'city' => 'Днепр',
                            'district' => 'N',
                            'kind' => $kind,
                            'phoneNumber1' => count($matchesPhones) > 0 ? $matchesPhones[0] : null,
                            'phoneNumber2' => count($matchesPhones) > 1 ? $matchesPhones[1] : null,
                            'numberOfRooms' => $numberOfRooms,
                            'cost' => 0,
                            'urlSource' => $urlSource,
                            'info' => $textAdv,
                            'shortInfo' => ''
                        );
                        DB::db_add_objects_tmp($this->db_obj);
                    }
                }
            }
            phpQuery::unloadDocuments();
        } catch
        (Exception $e) {
            loging($e->getMessage());
        }
    }
}

class Parse_nedelka_list extends Parse_nedelka
{
    function __construct($url)
    {
        parent::__construct();
        $params = array(
            'url' => $url,
            'validate_element' => '.sp-body2'
        );
        $this->params = array_merge($this->params, $params);
    }
}

class Parse_nedelka_adv extends Parse_nedelka
{
    function __construct($url)
    {
        parent::__construct();
        $params = array(
            'url' => $url,
            'validate_element' => '.strcontent',
            'tries' => 5
        );
        $this->params = array_merge($this->params, $params);
    }
}

class Parse_domria extends Proxy
{
    function __construct()
    {
        $name = 'domria';
        $headers = array(
            'Host: dom.ria.com',
            'Connection: close',
            'Cache-Control: max-age=0',
            'Upgrade-Insecure-Requests: 1',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.8,ru;q=0.6'
        );

        $path_prepare_proxy_from = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_' . $name;
        $path_cookie = '/home/oleg-pc/public_html/phpQuery/proxy/cookie_parse/cookie_' . $name;
        $this->params = array(
            'name' => $name,
            'headers' => $headers,
            '$path_prepare_proxy_from' => $path_prepare_proxy_from,
            'path_cookie' => $path_cookie,
            'tries' => 15,
            'sleep_start' => 1,
            'sleep_end' => 2,
            'validate_func' => 'is_validate',
            'validate' => true
        );

        $this->curl_params = array(
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_SSL_VERIFYPEER' => 0,
            'CURLOPT_SSL_VERIFYHOST' => 0,
            'CURLOPT_CONNECTTIMEOUT' => 3,
            'CURLOPT_TIMEOUT' => 8,
            'CURLOPT_COOKIEJAR' => $path_cookie,
            'CURLOPT_COOKIEFILE' => $path_cookie,
            'CURLOPT_HTTPHEADER' => $headers
        );

        $this->proxies = explode("\n", file_get_contents($this->params['$path_prepare_proxy_from']));
        $this->set_random_proxy();
    }

    public function parse()
    {
        if (count($this->proxies) == 0) {
            return;
        }

        try {
            $html = $this->get_xml_page();
            $html = phpQuery::newDocument($html);
            $elements = $html->find('ticket-item:not(".hide")');
            foreach ($elements as $element) {

                $importDate = date('Y-m-d');
                $importDateText = pq($element)->find('.footer-ticket > div > span > span')->text();
                if (strpos($importDateText, 'назад') == false) {
                    break 1;
                }
                $urlSource = 'https://dom.ria.com/ru' . pq($element)->find('a.address')->attr('href');
                $urlSource = 'https://dom.ria.com/ru/realty-dolgosrochnaya-arenda-kvartira-dnepropetrovsk-krasnogvardeyskiy-makarova-ulitsa-10814163.html';
                loging($urlSource);

                if (DB::db_url_exists($urlSource)) {
                    continue;
                }

                $parse_domria_adv = new Parse_olx_adv($urlSource);
                $parse_domria_adv->params['headers'][] = 'Referer: ' . $this->params['url'];
                $parse_domria_adv->curl_params['CURLOPT_HTTPHEADER'][] = 'Referer: ' . $this->params['url'];
                $innerHtml = $parse_domria_adv->get_xml_page();
                if ($innerHtml == false) {
                    DB::db_delete_url($urlSource);
                    continue;
                }

                //https://dom.ria.com/node/api/getOwnerAndAgencyDataByIds?userId=826837&agencyId=0&langId=2&_csrf=3IYY7OoM-STzSBYd6FzN_6nvWWne4SnfPEG0
                $innerHtml = phpQuery::newDocument($innerHtml);
                $user_id = pq($innerHtml)->find('.heading-dom.view')->text();
                if (preg_match('/(?<="user_id": )(.*)(?=,)/', $user_id, $matches_user_id)) {
                    $user_id = $matches_user_id[1];
                } else {
                    continue;
                }
                $innerHtmlText = pq($innerHtml)->text();
                if (preg_match('/(?<=data-csrf=")(.*)(?=")/', $innerHtmlText, $matches_csrf)) {
                    $csrf = $matches_csrf[1];
                } else {
                    continue;
                }
                $urlPhones = 'https://dom.ria.com/node/api/getOwnerAndAgencyDataByIds?userId=' . $user_id . '&agencyId=0&langId=2&_csrf=' . $csrf;
                $parse_domria_tel = new Parse_olx_tel($urlPhones);
                $parse_domria_tel->params['headers'][] = 'Referer: ' . $urlSource;
                $parse_domria_tel->curl_params['CURLOPT_HTTPHEADER'][] = 'Referer: ' . $urlSource;
                $phonesHtml = $parse_domria_tel->get_xml_page();
                if ($phonesHtml == false) {
                    DB::db_delete_url($urlSource);
                    continue;
                }

                $matchesPhones = $this->getPhones($phonesHtml);
                if ($matchesPhones === false) {
                    continue;
                }
                if (DB::db_tel_exists($matchesPhones)) {
                    continue;
                }

                $text = prepareString(pq($innerHtml)->find('#realtyDescriptionText')->text());
                $mainRegex = false;
                if (preg_match('/(?<![а-яА-ЯёЁ])сво[йюя]|хозя[ий]|владел|собствен|не посредн|не ри[еэ]лто(?=[а-яА-ЯёЁ])/iu', $text)) {
                    $mainRegex = true;
                }
                if ($mainRegex == false) {
                    if ($this->isRieltor($matchesPhones)) {
                        continue;
                    }
                }

                $shortInfo = pq($innerHtml)->find('div.heading.view > h1')->text();
                $shortInfo = prepareString($shortInfo);

                $textPrice = pq($innerHtml)->find('#showLeftBarView > div:nth-child(1) > div > span.price')->text();
                $price = preg_replace('/[\D]/', '', $textPrice);

                $numberOfRooms = pq($innerHtml)->find('#showLeftBarView > div.base-information.delimeter > div > div:nth-child(1) > b')->text();
                $numberOfRooms = preg_replace('/[\D]/', '', $numberOfRooms);

                $this->db_obj = array(
                    'importDate' => $importDate,
                    'rentSale' => 'аренда',
                    'city' => 'Днепр',
                    'district' => 'D',
                    'kind' => $this->kind,
                    'phoneNumber1' => count($matchesPhones) > 0 ? $matchesPhones[0] : null,
                    'phoneNumber2' => count($matchesPhones) > 1 ? $matchesPhones[1] : null,
                    'numberOfRooms' => $numberOfRooms == '' ? null : $numberOfRooms,
                    'cost' => $price == '' ? null : $price,
                    'urlSource' => $urlSource,
                    'info' => $text,
                    'shortInfo' => $shortInfo
                );
                DB::db_add_objects_tmp($this->db_obj);
            }
            phpQuery::unloadDocuments();
        } catch (Exception $e) {
            loging($e->getMessage());
        }
    }
}

class Parse_domria_list extends Parse_domria
{
    function __construct($url, $kind)
    {
        parent::__construct();
        $params = array(
            'url' => $url,
            'validate_element' => '.header-page'
        );
        $this->params = array_merge($this->params, $params);
        $this->kind = $kind;
    }
}

class Parse_domria_adv extends Parse_domria
{
    function __construct($url)
    {
        parent::__construct();
        $params = array(
            'url' => $url,
            'validate_element' => '.heading.view',
            'tries' => 5
        );
        $this->params = array_merge($this->params, $params);
    }
}

class Parse_domria_tel extends Parse_domria
{
    function __construct($url)
    {
        parent::__construct();
        $params = array(
            'url' => $url,
            'tries' => 5,
            'sleep_start' => 1,
            'sleep_end' => 2,
            'validate_func' => 'is_validate_tel'
        );
        $this->params = array_merge($this->params, $params);
    }

    function is_validate($page)
    {
        if (isJSON($page)) {
            return true;
        }
        return false;
    }
}
