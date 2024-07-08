<?php

function cleanUp($value)
{
    $value = htmlspecialchars($value);
    $value = htmlentities($value);
    $value = stripslashes($value);
    $value = strip_tags($value);
    $value = str_replace("'", "", $value);
    $value = str_replace('"', "", $value);
    return $value;
}

function validateInteger(&$value)
{
    $value = cleanUp($value);
    return preg_match('/\d+/', $value);
}
function validateString(&$value)
{
    $value = cleanUp($value);
    return preg_match("/[a-zA-Z]+/", $value);
}
function validateStringWithSpaces(&$value)
{
    $value = cleanUp($value);
    return preg_match("/[a-z A-Z0-9\-]+/", $value);
}
function validateDate(&$value)
{
    $value = cleanUp($value);
    return preg_match("/[0-9]{4}-[1-12]{2}-[1-30]{2}\s\d:\d:\d/", $value);
} // 2022-01-02
function validateEmail(&$value)
{
    $value = cleanUp($value);
    if (filter_var($value, FILTER_VALIDATE_EMAIL) == FALSE) {
        return FALSE;
    } else {
        return TRUE;
    }
}

function validatePassword($value)
{
    $value = cleanUp($value);
    return preg_match("/\w{8,}/", $value);
}
