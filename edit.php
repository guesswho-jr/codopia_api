<?php

require_once "./scripts/classes.php";
require_once "./scripts/validation.php";
if (!(
    isset($_POST["projId"]) &&
    isset($_POST["name"]) &&
    isset($_POST["projDesc"])
)) {

    die(json_encode(["error" => "Fatal error occured"]));
}
if (empty($_POST["projId"])) {
    die(json_encode(["error" => "Fatal error occured"]));
}

$projName = true;
$projDesc = true;
if (empty($_POST["projDesc"])) {
    $projDesc = false;
}
if (empty($_POST["name"])) {
    $projName = false;
}

if ($projDesc)
    $db = new DataBase();
if ($projName) {
    if (!validateString($_POST["name"])) {
        die(json_encode(["error" => "Error while validation"]));
    }
    $resp = $db->executeSql("update projects set project_name=? where project_id=?", [$_POST['name'], $_POST["projId"]], true);
    if (is_object($resp) && get_class($resp) == "PDOException") {
        die(['error' => "Please report this on feedback: " . $resp->getCode()]);
    }
}
if ($projDesc) {
    if (!validateStringWithSpaces($_POST["projDesc"])) {
        die(json_encode(["error" => "Error while validation"]));
    }
    $resp = $db->executeSql("update projects set project_detail=? where project_id=?", [$_POST['projDesc'], $_POST["projId"]], true);
    if (is_object($resp) && get_class($resp) == "PDOException") {
        die(['error' => "Please report this on feedback: " . $resp->getCode()]);
    }
}

$db->executeSql("update projects set project_time=?", [date('y-m-d h:m:s')]);
die(json_encode(["success" => 1]));
