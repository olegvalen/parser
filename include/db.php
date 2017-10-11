<?php
require_once('/home/oleg-pc/public_html/phpQuery/include/db_connect.php');

class DB
{
    static function db_url_exists($url)
    {
        global $link;
        $curDate = date('Y-m-d');
        $stmt = $link->prepare("SELECT * FROM pro36_objects_done WHERE importDate=:importDate AND urlSource=:urlSource");
        $stmt->bindValue(':importDate', $curDate, PDO::PARAM_STR);
        $stmt->bindValue(':urlSource', $url, PDO::PARAM_STR);
        $stmt->execute();
        $count = $stmt->rowCount();
        if ($count > 0) {
            return true;
        } else {
            $stmt = $link->prepare("INSERT INTO pro36_objects_done (importDate, urlSource) VALUES (:importDate, :urlSource)");
            $stmt->bindParam(':importDate', $curDate);
            $stmt->bindParam(':urlSource', $url);
            try {
                $stmt->execute();
            } catch (PDOException $e) {
                loging($e->getMessage());
            }
            return false;
        }
    }

    static function db_tel_exists($matchesPhones)
    {
        global $link;
        $curDate = date('Y-m-d');
        $telephones = implode(',', $matchesPhones);
        $stmt = $link->prepare("SELECT * FROM pro36_objects_tmp WHERE importDate=:importDate AND (phoneNumber1 IN (" . $telephones . ") OR phoneNumber2 IN (" . $telephones . "))");
        $stmt->bindValue(':importDate', $curDate, PDO::PARAM_STR);
        $stmt->execute();
        $count = $stmt->rowCount();
        if ($count > 0) {
            return true;
        }
        return false;
    }

    static function db_delete_url($url)
    {
        global $link;
        $curDate = date('Y-m-d');
        $stmt = $link->prepare("DELETE FROM pro36_objects_done WHERE importDate=:importDate AND urlSource=:urlSource");
        $stmt->bindParam(':importDate', $curDate);
        $stmt->bindParam(':urlSource', $url);
        try {
            $stmt->execute();
        } catch (PDOException $e) {
            loging($e->getMessage());
        }
    }

    static function db_is_phones_agent($tel)
    {
        global $link;
        $stmt = $link->prepare("SELECT * FROM pro36_phones_agents WHERE phoneNumber=:phoneNumber");
        $stmt->bindValue(':phoneNumber', $tel, PDO::PARAM_STR);
        $stmt->execute();
        $count = $stmt->rowCount();
        if ($count > 0) {
            return true;
        }
        return false;
    }

    static function db_add_phones_agent($tels)
    {
        global $link;
        foreach ($tels as $phone) {
            $stmt = $link->prepare("SELECT * FROM pro36_phones_agents WHERE phoneNumber=:phoneNumber");
            $stmt->bindValue(':phoneNumber', $phone, PDO::PARAM_STR);
            $stmt->execute();
            $count = $stmt->rowCount();
            if ($count === 0) {
                $stmt = $link->prepare("INSERT INTO pro36_phones_agents (phoneNumber) VALUES (:phoneNumber)");
                $stmt->bindParam(':phoneNumber', $phone);
                try {
                    $stmt->execute();
                } catch (PDOException $e) {
                    loging($e->getMessage());
                }
            }
        }
    }

    static function db_add_objects_tmp($db_obj)
    {
        global $link;
        $stmt = $link->prepare("INSERT INTO pro36_objects_tmp (importDate,  rentSale, city,    kind, phoneNumber1,  phoneNumber2,  cost,  urlSource,  info, shortInfo, datetime, numberOfRooms, district) VALUES
                                                                      (:importDate, :rentSale,   :city, :kind, :phoneNumber1, :phoneNumber2, :cost, :urlSource, :info, :shortInfo, NOW(), :numberOfRooms, :district)");
        $stmt->bindParam(':importDate', $db_obj['importDate']);
        $stmt->bindParam(':rentSale', $db_obj['rentSale']);
        $stmt->bindParam(':city', $db_obj['city']);
        $stmt->bindParam(':kind', $db_obj['kind']);
        $stmt->bindParam(':phoneNumber1', $db_obj['phoneNumber1']);
        $stmt->bindParam(':phoneNumber2', $db_obj['phoneNumber2']);
        $stmt->bindParam(':cost', $db_obj['cost']);
        $stmt->bindParam(':urlSource', $db_obj['urlSource']);
        $stmt->bindParam(':info', $db_obj['info']);
        $stmt->bindParam(':shortInfo', $db_obj['shortInfo']);
        $stmt->bindParam(':numberOfRooms', $db_obj['numberOfRooms']);
        $stmt->bindParam(':district', $db_obj['district']);
        try {
            $stmt->execute();
        } catch (PDOException $e) {
            loging($e->getMessage());
        }
    }

    static function db_prepare_json()
    {
        global $link;
        $stmt = $link->prepare("SELECT id, importDate, rentSale, city, district, kind, phoneNumber1, phoneNumber2, numberOfRooms, cost, info, shortInfo FROM pro36_objects_tmp WHERE importDate=:importDate ORDER BY datetime");
        $stmt->bindValue(':importDate', date('Y-m-d'), PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll();
        $fp = fopen('results.json', 'w');
        fwrite($fp, json_encode($result, JSON_UNESCAPED_UNICODE));
        fclose($fp);
    }

}