<?php

require_once('/home/oleg-pc/public_html/phpQuery/phpQuery/phpQuery.php');

abstract class Proxy
{
    public $params;
    public $proxies;
    public $cur_proxy;
    public $curl_params;
    public static $user_agents;
    public static $my_ip = '91.193.128.153';
    public $db_obj;
    public $USD = 27;
    public $EVRO = 29;
    public $kind;

//    public static $my_ip = '62.221.54.116';

    public static function set_array_user_agent()
    {
        $file = 'useragent.txt';
        self::$user_agents = explode("\n", file_get_contents($file));
    }

    public static function get_random_user_agent()
    {
        $key = array_rand(self::$user_agents);
        return self::$user_agents[$key];
    }

    public function set_random_proxy()
    {
        $key = array_rand($this->proxies);
        $tmp = explode("\t", $this->proxies[$key]);
        $this->cur_proxy = array(
            'proxy' => $tmp[0],
            'port' => $tmp[1],
            'type' => $this->get_type_proxy($tmp[2]),
            'user_agent' => $tmp[3]
        );
    }

    public function get_type_proxy($type_txt)
    {
        if ($type_txt == 'HTTP' || $type_txt == 'HTTPS') {
            $type = CURLPROXY_HTTP;
        } else if ($type_txt == 'SOCKS4') {
            $type = CURLPROXY_SOCKS4;
        } else if ($type_txt == 'SOCKS5') {
            $type = CURLPROXY_SOCKS5;
        } else {
            $type = CURLPROXY_HTTP;
        }
        return $type;
    }

    public function get_random_sleep()
    {
        sleep(rand($this->params['sleep_start'], $this->params['sleep_end']));
    }

    public function is_validate_init($page)
    {
        if ($page == false || strpos($page, 'origin') == false || strpos($page, self::$my_ip) != false) {
            return false;
        } else {
            return true;
        }
    }

    public function is_validate($page)
    {
        if ($page == false) {
            return false;
        }
        $page = phpQuery::newDocument($page);
        $elements = pq($page)->find($this->params['validate_element']);
        $countElements = $elements->count();
        phpQuery::unloadDocuments();
        if ($countElements > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function is_validate_200($page)
    {
        return $page;
    }

    public function get_valid_init_proxy()
    {
        loging($this->params['name']);
        $tmp_array = array();
        $proxies = explode("\n", file_get_contents($this->params['$path_prepare_proxy_from']));
        foreach ($proxies as $str) {
            $tmp = explode("\t", $str);
            $proxy = $tmp[0];
            $port = $tmp[1];
            $type_txt = $tmp[2];
            $type = $this->get_type_proxy($type_txt);
            if ($this->params['validate_func'] == 'is_validate_init') {
                $user_agent = self::get_random_user_agent();
            } else {
                $user_agent = $tmp[3];
            }
            $this->cur_proxy = array('proxy' => $proxy, 'port' => $port, 'type' => $type, 'user_agent' => $user_agent);
            $page = $this->get_xml_page();
            if ($this->params['validate_func'] == 'is_validate_init') {
                $page = $this->is_validate_init($page);
            } elseif ($this->params['validate_func'] == 'is_validate') {
                $page = $this->is_validate($page);
            } elseif ($this->params['validate_func'] == 'is_validate_200') {
                $page = $this->is_validate_200($page);
            }
            if ($page == true) {
                $tmp_array[] = "$proxy\t$port\t$type_txt\t$user_agent\n";
                echo $this->params['name'] . " $proxy:$port is good\n";
            } else {
                echo $this->params['name'] . " $proxy:$port is bad\n";
            }
            if (isset($this->params['sleep_start']) && isset($this->params['sleep_end'])) {
                sleep(rand($this->params['sleep_start'], $this->params['sleep_end']));
            }
        }
        if (count($tmp_array) > 0) {
            $tmp_array[count($tmp_array) - 1] = rtrim($tmp_array[count($tmp_array) - 1], "\n");
            array_unique($tmp_array);
            file_put_contents($this->params['$path_prepare_proxy_to'], $tmp_array);
        }
    }

    public function get_xml_page()
    {
        if ($this->params['tries'] == 0) {
            return false;
        }

        $ch = curl_init($this->params['url'] . (isset($this->params['cur_i']) ? $this->params['cur_i'] : ''));

        if (isset($this->curl_params['CURLOPT_RETURNTRANSFER'])) {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, $this->curl_params['CURLOPT_RETURNTRANSFER']);
        }
        if (isset($this->curl_params['CURLOPT_FOLLOWLOCATION'])) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->curl_params['CURLOPT_FOLLOWLOCATION']);
        }
        if (isset($this->curl_params['CURLOPT_HTTPHEADER'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->curl_params['CURLOPT_HTTPHEADER']);
        }
        if (isset($this->curl_params['CURLOPT_SSL_VERIFYPEER'])) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->curl_params['CURLOPT_SSL_VERIFYPEER']);
        }
        if (isset($this->curl_params['CURLOPT_SSL_VERIFYHOST'])) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->curl_params['CURLOPT_SSL_VERIFYHOST']);
        }
        curl_setopt($ch, CURLOPT_PROXY, $this->cur_proxy['proxy'] . ':' . $this->cur_proxy['port']);
        curl_setopt($ch, CURLOPT_PROXYTYPE, $this->cur_proxy['type']);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->cur_proxy['user_agent']);

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->curl_params['CURLOPT_CONNECTTIMEOUT']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->curl_params['CURLOPT_TIMEOUT']);

        if (isset($this->curl_params['CURLOPT_COOKIEJAR'])) {
            file_put_contents($this->params['path_cookie'], '');
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->params['path_cookie']);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->params['path_cookie']);
        }

        if (isset($this->curl_params['CURLOPT_HEADER'])) {
            curl_setopt($ch, CURLOPT_HEADER, $this->curl_params['CURLOPT_HEADER']);
            curl_setopt($ch, CURLOPT_NOBODY, $this->curl_params['CURLOPT_NOBODY']);
        }

        $page = curl_exec($ch);
        $info = curl_getinfo($ch);

        curl_close($ch);

        echo $this->cur_proxy['proxy'] . ':' . $this->cur_proxy['port'] . "\t" . 'http_code' . "\t" . $info['http_code'] . "\n";
        if ($this->params['validate_func'] == 'is_validate_200') {
            return $info['http_code'] == 200;
        }


        if ($this->params['validate'] && $this->is_validate($page) == false) {
            $this->set_random_proxy();
            $this->params['tries']--;
            $page = $this->get_xml_page();
        }

        return $page;
    }

    public function get_simple_xml_page()
    {
        $ch = curl_init($this->params['check_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        $page = curl_exec($ch);
        curl_close($ch);
        return $page;
    }

    public function parse()
    {
    }

    public function getPhones($txt)
    {
        preg_match_all('/(3?[()\- ]?8?[()\- ]?0?[()\- ]?\d\d[)\- ]?[)\- ]?\d\d\d[)\- ]?\d\d[)\- ]?\d\d)|(3?[()\- ]?8?[()\- ]?0?[()\- ]?\d\d[)\- ]?[)\- ]?\d\d[)\- ]?\d\d[)\- ]?\d\d\d)/', $txt, $matchesPhones);
        if (sizeof($matchesPhones[0])) {
            $matchesPhones = preg_replace('/[\D]/', '', $matchesPhones[0]);
            foreach ($matchesPhones as $key => $phone) {
                if (strlen($phone) > 10) {
                    $matchesPhones[$key] = substr($phone, -10);
                }
            }
            return $matchesPhones;
        }
        return false;
    }

    function isRieltor($matchesPhones)
    {
        foreach ($matchesPhones as $tel) {
            try {
                if (DB::db_is_phones_agent($tel)) {
                    return true;
                }
                $parse_m2 = new Parse_m2('https://ua.m2bomber.com/phone/' . $tel);
                $telHtml = $parse_m2->get_xml_page();
                if ($telHtml == false) {
                    return true;
                }
                $telHtml = phpQuery::newDocument($telHtml);
                $phones = pq($telHtml)->find('#main-content-wrapper > div:nth-child(1) > div.row.clearfix > div.col-lg-9.col-md-8.col-sm-12 > div > div > h3')->text();
                $matchesPhones = preg_replace('/[^\d,]/', '', $phones);
                $matchesPhones = explode(',', $matchesPhones);

                $text = pq($telHtml)->find('#main-content-wrapper > div:nth-child(1) > div.row.clearfix > div.col-lg-3.col-md-4.col-sm-12.right-bar-wrapper > div > h2')->text();
                $text = prepareString($text);

                if ($text == 'агентство') {
                    DB::db_add_phones_agent($matchesPhones);
                    return true;
                }
            } catch
            (Exception $e) {
                loging($e->getMessage());
                return true;
            }
            return false;
        }
    }

    public static function prepareJson()
    {
        DB::db_prepare_json();
    }

    public static function sendJson()
    {
        $remote_file = '/dvushka.com.ua/www/src/results.json';

        $ftp_host = 'bx254361.ftp.ukraine.com.ua';
        $ftp_user_name = 'bx254361_ftp';
        $ftp_user_pass = 'L3fsBn6x';

        $local_file = 'results.json';

        $connect_it = ftp_connect($ftp_host);

        $login_result = ftp_login($connect_it, $ftp_user_name, $ftp_user_pass);
        ftp_pasv($connect_it, true);
        if (ftp_put($connect_it, $remote_file, $local_file, FTP_BINARY)) {
            loging("sendJson: WOOT! Successfully written to $remote_file");
        } else {
            loging("sendJson: Doh! There was a problem");
        }
        ftp_close($connect_it);
    }

}

class Proxy_httpbin extends Proxy
{
    function __construct()
    {
        $name = 'httpbin';
        $url = 'http://httpbin.org/ip';
        $path_prepare_proxy_from = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_' . $name;
        $path_prepare_proxy_to = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_' . $name;
        $this->params = array(
            'name' => $name,
            'url' => $url,
            '$path_prepare_proxy_from' => $path_prepare_proxy_from,
            '$path_prepare_proxy_to' => $path_prepare_proxy_to,
            'tries' => 1,
            'validate_func' => 'is_validate_init',
            'validate' => false
        );

        $this->curl_params = array(
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_CONNECTTIMEOUT' => 3,
            'CURLOPT_TIMEOUT' => 5
        );
    }
}

class Proxy_olx extends Proxy
{
    function __construct()
    {
        $name = 'olx';
        $url = 'https://www.olx.ua';
        $validate_element = '.maincategories-list';
        $headers = array(
            'Host: www.olx.ua',
            'Cache-Control: max-age=0',
            'Upgrade-Insecure-Requests: 1',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.8,ru;q=0.6',
            'Connection: close'
        );
        $path_prepare_proxy_from = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_httpbin';
        $path_prepare_proxy_to = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_' . $name;
        $path_cookie = '/home/oleg-pc/public_html/phpQuery/proxy/cookie/cookie_' . $name;
        $this->params = array(
            'name' => $name,
            'url' => $url,
            'validate_element' => $validate_element,
            'headers' => $headers,
            '$path_prepare_proxy_from' => $path_prepare_proxy_from,
            '$path_prepare_proxy_to' => $path_prepare_proxy_to,
            'path_cookie' => $path_cookie,
            'tries' => 1,
            'sleep_start' => 0,
            'sleep_end' => 0,
            'validate_func' => 'is_validate_200',
            'validate' => false
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
            'CURLOPT_HTTPHEADER' => $headers,
            'CURLOPT_HEADER' => true,
            'CURLOPT_NOBODY' => true

        );
    }
}

class Proxy_m2 extends Proxy
{
    function __construct()
    {
        $name = 'm2';
        $url = 'https://ua.m2bomber.com';
        $validate_element = '.col-lg-3.col-md-3.col-sm-6';
        $headers = array(
            'Host: ua.m2bomber.com',
//        'Cache-Control: max-age=0',
            'Upgrade-Insecure-Requests: 1',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.8,ru;q=0.6',
            'Referer: https://ua.m2bomber.com/',
            'Connection: close'
        );
        $path_prepare_proxy_from = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_httpbin';
        $path_prepare_proxy_to = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_' . $name;
        $path_cookie = '/home/oleg-pc/public_html/phpQuery/proxy/cookie/cookie_' . $name;
        $this->params = array(
            'name' => $name,
            'url' => $url,
            'validate_element' => $validate_element,
            'headers' => $headers,
            '$path_prepare_proxy_from' => $path_prepare_proxy_from,
            '$path_prepare_proxy_to' => $path_prepare_proxy_to,
            'path_cookie' => $path_cookie,
            'tries' => 1,
            'sleep_start' => 0,
            'sleep_end' => 0,
//            'validate_func' => 'is_validate_200',
            'validate_func' => 'is_validate',
            'validate' => false
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
//            'CURLOPT_HEADER' => true,
//            'CURLOPT_NOBODY' => true
        );
    }
}

class Proxy_variants extends Proxy
{
    function __construct()
    {
        $name = 'variants';
        $url = 'http://variants.dp.ua';
        $validate_element = '#firstcol';
        $headers = array(
            'Host: variants.dp.ua',
            'Cache-Control: max-age=0',
            'Upgrade-Insecure-Requests: 1',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.8,ru;q=0.6',
            'Connection: close'
        );
        $path_prepare_proxy_from = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_httpbin';
        $path_prepare_proxy_to = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_' . $name;
        $path_cookie = '/home/oleg-pc/public_html/phpQuery/proxy/cookie/cookie_' . $name;
        $this->params = array(
            'name' => $name,
            'url' => $url,
            'validate_element' => $validate_element,
            'headers' => $headers,
            '$path_prepare_proxy_from' => $path_prepare_proxy_from,
            '$path_prepare_proxy_to' => $path_prepare_proxy_to,
            'path_cookie' => $path_cookie,
            'tries' => 1,
            'sleep_start' => 0,
            'sleep_end' => 0,
            'validate_func' => 'is_validate_200',
            'validate' => false
        );

        $this->curl_params = array(
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_CONNECTTIMEOUT' => 3,
            'CURLOPT_TIMEOUT' => 5,
            'CURLOPT_COOKIEJAR' => $path_cookie,
            'CURLOPT_COOKIEFILE' => $path_cookie,
            'CURLOPT_HTTPHEADER' => $headers,
            'CURLOPT_HEADER' => true,
            'CURLOPT_NOBODY' => true
        );
    }
}

class Proxy_gorod extends Proxy
{
    function __construct()
    {
        $name = 'gorod';
        $url = 'http://gorod.dp.ua';
        $validate_element = '.block_news';
        $headers = array(
            'Host: gorod.dp.ua',
            'Upgrade-Insecure-Requests: 1',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.8,ru;q=0.6',
            'Connection: close'
        );

        $path_prepare_proxy_from = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_httpbin';
        $path_prepare_proxy_to = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_' . $name;
        $path_cookie = '/home/oleg-pc/public_html/phpQuery/proxy/cookie/cookie_' . $name;
        $this->params = array(
            'name' => $name,
            'url' => $url,
            'validate_element' => $validate_element,
            'headers' => $headers,
            '$path_prepare_proxy_from' => $path_prepare_proxy_from,
            '$path_prepare_proxy_to' => $path_prepare_proxy_to,
            'path_cookie' => $path_cookie,
            'tries' => 1,
            'sleep_start' => 0,
            'sleep_end' => 0,
            'validate_func' => 'is_validate_200',
            'validate' => false
        );

        $this->curl_params = array(
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_CONNECTTIMEOUT' => 3,
            'CURLOPT_TIMEOUT' => 8,
            'CURLOPT_COOKIEJAR' => $path_cookie,
            'CURLOPT_COOKIEFILE' => $path_cookie,
            'CURLOPT_HTTPHEADER' => $headers,
            'CURLOPT_HEADER' => true,
            'CURLOPT_NOBODY' => true
        );
    }
}

class Proxy_nedelka extends Proxy
{
    function __construct()
    {
        $name = 'nedelka';
        $url = 'http://www.nedelka.dp.ua';
        $validate_element = '.tabTitTDl';
        $headers = array(
            'Host: nedelka.dp.ua',
            'Accept-Encoding: gzip, deflate, sdch',
            'Accept-Language: en-US,en;q=0.8,ru;q=0.6',
            'Cache-Control: max-age=0',
            'Connection: close',
            'Upgrade-Insecure-Requests: 1'
        );
        $path_prepare_proxy_from = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_httpbin';
        $path_prepare_proxy_to = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_' . $name;
        $path_cookie = '/home/oleg-pc/public_html/phpQuery/proxy/cookie/cookie_' . $name;
        $this->params = array(
            'name' => $name,
            'url' => $url,
            'validate_element' => $validate_element,
            'headers' => $headers,
            '$path_prepare_proxy_from' => $path_prepare_proxy_from,
            '$path_prepare_proxy_to' => $path_prepare_proxy_to,
            'path_cookie' => $path_cookie,
            'tries' => 1,
            'sleep_start' => 0,
            'sleep_end' => 0,
            'validate_func' => 'is_validate_200',
            'validate' => false
        );

        $this->curl_params = array(
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_CONNECTTIMEOUT' => 3,
            'CURLOPT_TIMEOUT' => 8,
            'CURLOPT_COOKIEJAR' => $path_cookie,
            'CURLOPT_COOKIEFILE' => $path_cookie,
            'CURLOPT_HTTPHEADER' => $headers,
            'CURLOPT_HEADER' => true,
            'CURLOPT_NOBODY' => true
        );
    }
}

class Proxy_domria extends Proxy
{
    function __construct()
    {
        $name = 'domria';
        $url = 'https://dom.ria.com';
        $validate_element = '#mainPageSearchForm';
        $headers = array(
            'Host: dom.ria.com',
            'Connection: close',
            'Cache-Control: max-age=0',
            'Upgrade-Insecure-Requests: 1',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.8,ru;q=0.6'
        );
        $path_prepare_proxy_from = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_httpbin';
        $path_prepare_proxy_to = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_' . $name;
        $path_cookie = '/home/oleg-pc/public_html/phpQuery/proxy/cookie/cookie_' . $name;
        $this->params = array(
            'name' => $name,
            'url' => $url,
            'validate_element' => $validate_element,
            'headers' => $headers,
            '$path_prepare_proxy_from' => $path_prepare_proxy_from,
            '$path_prepare_proxy_to' => $path_prepare_proxy_to,
            'path_cookie' => $path_cookie,
            'tries' => 1,
            'sleep_start' => 0,
            'sleep_end' => 0,
            'validate_func' => 'is_validate_200',
            'validate' => false
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
            'CURLOPT_HTTPHEADER' => $headers,
            'CURLOPT_HEADER' => true,
            'CURLOPT_NOBODY' => true
        );
    }
}

class Proxy_vk extends Proxy
{
    function __construct()
    {
        $name = 'vk';
        $url = 'http://vk.com';
        $validate_element = '#mainPageSearchForm';
        $headers = array(
            'Host: vk.com',
            'Connection: close',
            'Cache-Control: max-age=0',
            'Upgrade-Insecure-Requests: 1',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.8,ru;q=0.6'
        );
        $path_prepare_proxy_from = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_httpbin';
        $path_prepare_proxy_to = '/home/oleg-pc/public_html/phpQuery/proxy/prepare_proxy/prepare_proxy_' . $name;
        $path_cookie = '/home/oleg-pc/public_html/phpQuery/proxy/cookie/cookie_' . $name;
        $this->params = array(
            'name' => $name,
            'url' => $url,
            'validate_element' => $validate_element,
            'headers' => $headers,
            '$path_prepare_proxy_from' => $path_prepare_proxy_from,
            '$path_prepare_proxy_to' => $path_prepare_proxy_to,
            'path_cookie' => $path_cookie,
            'tries' => 1,
            'sleep_start' => 0,
            'sleep_end' => 0,
            'validate_func' => 'is_validate_200',
            'validate' => false
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
            'CURLOPT_HTTPHEADER' => $headers,
            'CURLOPT_HEADER' => true,
            'CURLOPT_NOBODY' => true
        );
    }
}

function loging($text)
{
    $log = '/home/oleg-pc/public_html/phpQuery/logs/' . date('Y-m-d');
    $text = date('Y-m-d H:i:s') . "\t$text\n";
    file_put_contents($log, $text, FILE_APPEND);
    echo $text;
}
