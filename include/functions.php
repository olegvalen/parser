<?php
function isJSON($string)
{
    return is_string($string) && is_array(json_decode($string, true)) && (json_last_error() == JSON_ERROR_NONE) ? true : false;
}

function prepareString($str)
{
    return trim(str_replace(array("\r\n", "\r", "\n"), '', strip_tags($str)));
}
