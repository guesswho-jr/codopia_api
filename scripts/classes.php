<?php

declare(strict_types=1); // For Gerum: similar to "use strict"
/* I wrote a lot of code so there must be a bug. */

/** Read it carefully tho */

require_once("validation.php");
define("LOG_DIR", realpath($_SERVER['DOCUMENT_ROOT']) . DIRECTORY_SEPARATOR . 'logs');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// Functions

require "PHPMailer/src/Exception.php";
require "PHPMailer/src/PHPMailer.php";
require "PHPMailer/src/SMTP.php";
// Created this file to follow the DRY principle.

// Please manually set the credentials

// OPTIMIZE: MARK: TRACKER
class Tracker
{

    public  $logFile;
    public function __construct(string $logFile)
    {
        // This arg is the file you want to save the log on. No default value.
        $this->logFile = $logFile;
        // create the files

    }
    public function log(string $info)
    {
        if (!is_dir(LOG_DIR)) {
            mkdir(LOG_DIR);
        }
        // format is generic
        $file = fopen(LOG_DIR . DIRECTORY_SEPARATOR . $this->logFile, 'a');
        fwrite($file,  $info . ' ' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['SERVER_PROTOCOL'] . " \n");
        fclose($file);
        unset($file);
    }
}

// OPTIMIZE: MARK: DATABASE
class DataBase
{
    private $con;
    // public function __construct(string $username = "if0_36424959", string $password = "infinitecode", string $host = "sql104.infinityfree.com", string $dbname = "if0_36424959_codopia")
    public function __construct(string $username = "freedb_admin_root", string $password = 'f9DuQP!BXCy$wT4', string $host = "sql.freedb.tech", string $dbname = "freedb_donict")
    {
        $this->con = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    }
    public function executeSql(string $sql, $params = array(), bool $return = false)
    {
        // For Gerum
        // If you use this function use the prepared statements are params as the execute array
        // and returns the number of rows
        $statement = $this->con->prepare($sql);

        try {
            $statement->execute($params);
            if ($return) {
                return array_merge($statement->fetchAll(PDO::FETCH_ASSOC), ["rows" => $statement->rowCount()]);
            }
        } catch (PDOException $e) {
            return $e;
        }
    }
    private function getPDOType(mixed $value)
    {
        $value = gettype($value);
        switch ($value) {
            case "integer":
                return PDO::PARAM_INT;
            case "string":
                return PDO::PARAM_STR;
            default:
                return -1;
        }
    }
    public function executeParams(string $sql, array $params, bool $return = false)
    {
        // Params is NOT associative array

        $testStmt = $this->con->prepare($sql);
        $i = 1;
        foreach ($params as $param) {
            $type = $this->getPDOType($param);
            if ($type == -1) {
                return -1;
            }
            $testStmt->bindValue($i, $param, $type);
            $i++;
        }
        try {
            $testStmt->execute();
            $resp = $testStmt->fetchAll(PDO::FETCH_ASSOC);

            if ($return)
                return array_merge($resp, ["rows" => $testStmt->rowCount()]);
            else
                return TRUE;
        } catch (PDOException $except) {
            return $except;
        }
    }
    // public function customSqlValidation(string $sql){
    //     // If you are Gerum pls don't use this everywhere I am not sure about it.
    //     // add layers if you got
    //     $layer0 = is_string($sql);
    //     $layer1 = cleanUp($sql);
    //     $layer2 = preg_match("/select|update\s(from)?\s?events|projects|blocked_users\s(set\saccepted_by|user_list=\?)?\swhere\sid|user_id|project_id=\?\s/i",$sql);
    //     return $layer1 && $layer2 && $layer0;
    // }
    // Find a way if there is a way to close PDO connections
}

// OPTIMIZE: MARK: XPSYSTEM
class XPSystem extends DataBase
{

    private $userId;
    private $logger;
    public function __construct(int $userId)
    {
        parent::__construct();
        $this->logger = new Tracker("xplog.log");
        if (validateInteger($userId)) {
            $this->userId = $userId;
        } else {
            die("UserID validation failed");
        }
    }
    public function addXP(int $points)
    {
        if (validateInteger($points)) {
            $this->logger->log("[INFO] at " . date("y-m-d h:m:s") . " xp added for user " . $_SESSION["username"] . "with IP address " . $_SERVER['REMOTE_ADDR'] . "Added XP: $points");
            $this->executeSql("UPDATE `users` SET `points` = `points` + ? WHERE id=?", [$points, $this->userId], FALSE);
        } else {
            die("Error occurred check your argument!!");
        }
    }
    public function decreaseXP(int $points)
    {
        if (validateInteger($points)) {
            $this->logger->log("[INFO] at " . date("y-m-d h:m:s") . " xp decreased for user " . $_SESSION["username"] . "with IP address " . $_SERVER['REMOTE_ADDR'] . "XP: $points");
            $this->executeSql("UPDATE `users` SET `points` = `points` - ? WHERE id=?", [$points, $this->userId], FALSE);
        } else {
            die("Error occurred check your argument!!");
        }
    }
}

// OPTIMIZE: MARK: USER
class User extends DataBase
{
    /**
     * This class will fetch the user data with the username and password. (login method)
     * There are functions to help for some tasks.
     * I think the names are enough. 
     */
    private $password;
    private $username;
    private $tracker;
    public function __construct(string $username, string $password)
    {
        parent::__construct();
        $this->tracker = new Tracker("user.log");
        if (validateString($username) && validatePassword($password)) {

            $this->username = $username;
            $this->password = $password;
        } else {
            die(json_encode(["type" => "error", "message" => "Error occurred when validating your inputs."]));
        }
    }
    public static function getUser(string $username)
    {
        if (!validateString($username)) {
            throw new Exception("Validation failed");
        }
    }

    public function login()
    {
        $this->tracker->log("[INFO] at " . date("y-m-d h:m:s")  . " IP address " . $_SERVER['REMOTE_ADDR'] . " attempted a login with a username of $this->username");
        $result = $this->executeSql("select id,password from users where username=? limit 1", [$this->username], true);
        if ($result["rows"] == 0) {
            die(json_encode(["type" => "error", "message" => "User not found"]));
        }
        $password = $result[0]["password"];

        $userId = $result[0]["id"];
        $rows = $result["rows"];
        if ($rows == 1) {
            if (password_verify($this->password, $password)) {
                session_regenerate_id();
                $resultFromDB = $this->executeSql("select full_name,bio, is_admin from users where username=? and password=? limit 1", [$this->username, $password], true);
                $_SESSION["username"] = $this->username;
                $_SESSION["loggedin"] = TRUE;
                $_SESSION["full_name"] = $resultFromDB[0]["full_name"];
                $_SESSION["bio"] = $resultFromDB[0]["bio"];
                $_SESSION["userid"] = $userId;
                $_SESSION["admin_status"] = $resultFromDB[0]['is_admin'];
                return TRUE;
            } else {
                $this->tracker->log("[WARNING] at " . date("y-m-d h:m:s")  . " IP address " . $_SERVER['REMOTE_ADDR'] . " attempted a login but failed username is $this->username");
                return FALSE;
            }
        }
    }
    private function validateForSignUp(string $username, string $password, string $cpassword, string $bio, string $full_name, string $email): int
    {
        if (!(isset($email) && isset($password) && isset($bio) && isset($cpassword) && isset($full_name) && isset($username))) {
            return 0; // "error"=> "Form error")
        }
        if (!preg_match("/[a-zA-Z]\s[a-zA-Z]/", $full_name)) {
            // ("type"=> "error","message"=>"Check your form error occurred"));
            return 1;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // echo json_encode(["type"=>"error", "message"=>"Email verification failed"]);
            return 2;
        }
        if (strlen($password) < 6) {
            // echo json_encode(["type"=> "error","message"=> "Password is too short"]);
            return 3;
        }
        if ((preg_match("/^[a-zA-Z0-9_]+$/", $username) == 0)) {
            //echo json_encode(["type"=> "error","message"=> "Only letters, underscore and numbers are allowed for username"]);
            return 4;
        }

        if (strcmp($password, $cpassword) != 0) {
            // echo json_encode(["type"=> "error","message"=> "Passwords don't match"]);
            return 5;
        }
        // Password verification. too tuff if there is a bug check here.
        /* If you forgot it the (?=.*[...]) means atlease one occurance of this */
        if (preg_match("/(?=.*[a-z])(?=.*[0-9])(?=.*[!@#$%^&*])[a-z0-9!@#$%^&*]/i", $password) == 0) {
            // echo json_encode(["type"=>"error","message"=> "Password doesn't meet our criterias. "]);
            return 6;
        }
        return 7;
    }
    public function createUser(string $username, string $password, string $cpassword, string $bio, string $full_name, $email)
    {
        $this->tracker->log("[INFO] at " . date("y-m-d h:m:s")  . " IP address " . $_SERVER['REMOTE_ADDR'] . " attempted a signup with a username of $username");
        $resp = $this->validateForSignUp($username,  $password,  $cpassword,  $bio,  $full_name, $email);
        if ($resp != 7) {
            // if (!signupValidation($username,  $password,  $cpassword,  $bio,  $full_name, $email)) {
            $this->tracker->log("[WARNING] at " . date("y-m-d h:m:s")  . " IP address " . $_SERVER['REMOTE_ADDR'] . " attempted a signup with  $this->username but validation failed");
            return ["data" => json_encode(["type" => "error", "message" => "Error occurred while validating your input possibly it is your password length."]), "code" => $resp];
            // return ["data" => json_encode(["type" => "error", "message" => "Error occurred while validating your input possibly it is your password length."]), "code" => 1];
        }
        $hashed_password = password_hash($password, PASSWORD_ARGON2ID);
        $a = $this->executeSql("insert into users (full_name, email, username, password, bio, points, uploads) values (?,?,?,?,?,?,?)", [$full_name, $email, $username, $hashed_password, $bio, 0, 0], TRUE);
        $this->tracker->log("[INFO] at " . date("y-m-d h:m:s")  . " IP address " . $_SERVER['REMOTE_ADDR'] . " $this->username is added to the database");
        if (is_object($a)) {
            if (get_class($a) == "PDOException") {
                $message = $a->getMessage();
                $this->tracker->log("[WARNING] at " . date("y-m-d h:m:s")  . " IP address " . $_SERVER['REMOTE_ADDR'] . " error occurred on the database with the error of $message");
                unset($message);
                return ["code" => 2, "obj" => $a];
            }
        }
        return ["code" => 0];
    }
}

// OPTIMIZE: MARK: BADGE
function getBadge(int $pts)
{
    switch ($pts) {
        case $pts < 100:
            return ["Junior", 'bg-primary'];
        case $pts >= 100 && $pts < 500:
            return ["Medium", "bg-secondary"];
        case $pts >= 500 && $pts < 1000:
            return ["Gold", "bg-warning"];
        case $pts >= 1000 && $pts < 2000:
            return ["Diamond", "bg-info"];
        case $pts >= 2000:
            return ["Elite", 'bg-opacity-25'];
        default:
            return ["Unknown", "bg-danger"];
    }
}

// OPTIMIZE: MARK: CACHE
class Cache
{
    public $userId;
    static private $fileName;
    public $subId;
    public $getFunctinError;
    private $tracker;
    public function __construct(int $userId)
    {
        $this->tracker = new Tracker("caches.log");
        $this->userId = (string)$userId;

        $sessionHash = sha1((string)$userId); // sha1 is weak we need to change it.
        if (!is_dir("cache")) {
            mkdir("cache");
        }
        Cache::$fileName = "cache/" . $this->userId . "-cache_$sessionHash.cache";
    }
    public function set(string $value, int $subjectID)
    {
        $this->tracker->log("[INFO] at " . date("y-m-d h:m:s")  . " IP address " . $_SERVER['REMOTE_ADDR'] . " used a cache of value $value and subjectID $subjectID");
        /** set the cache data and returns -1 if the user is not logged in */

        $file = fopen(Cache::$fileName, "w");
        $encValue = serialize($value);
        $dataToWrite = json_encode(["id" => $this->userId, "data" => $encValue, "subId" => $subjectID]);
        // fwrite($file, "\n");
        fwrite($file, $dataToWrite);
        fclose($file);
        return ["code" => 99];
    }
    public function get(bool $returnSubject = FALSE)
    {
        //$this->tracker->log("[INFO] at " . date("y-m-d h:m:s")  . " IP address " . $_SERVER['REMOTE_ADDR'] . " the cache get function is called");

        if (!file_exists(Cache::$fileName)) {
            return ["code" => -2];
        }
        $file = fopen(Cache::$fileName, 'r');
        $theData = fread($file, 1024);

        $arr = (array)json_decode($theData);
        $this->subId = $arr["subId"];
        if ($arr["id"] == $this->userId) {
            if (!$returnSubject)
                return unserialize($arr["data"]);
            else
                return ["subId" => $arr["subId"], "data" => unserialize($arr["data"])];
        } else {
            return json_encode(["code" => 88]);
        }
    }
    public function cleanUp(): void
    {
        $this->tracker->log("[INFO] at " . date("y-m-d h:m:s")  . " IP address " . $_SERVER['REMOTE_ADDR'] . " the clean up");
        unlink(Cache::$fileName);
    }
    // public function __destruct() {
    //     unlink(Cache::$fileName);
    // }
}

// class JSON extends DataBase {
//     private $col, $tableName, $idCol, $baseStmt;
//     public function __construct(string $tableName, string $col, string $idCol){
//         parent::__construct();
//         $this->col = $col;
//         $this->tableName = $tableName;
//         $this->idCol = $idCol;
//     }
//     public function updateRecord($appendValue, int $id){
//         // Append the value into the table name identified by $id
//         // Warning!! this function has no validation checks!!
//         $stmt = "select $this->col from $this->tableName where $this->idCol=?";
//         if (!parent::customSqlValidation($stmt)){
//             throw new Exception("Error while validating the sql query!!!");
//         }
//         $this->baseStmt = $stmt;
//         $prev = parent::executeParams($this->baseStmt,[$id], true);
//         $prev = (array)json_decode($prev[$this->col]);
//         array_push($prev, $appendValue);
//         $stmt = "update $this->tableName set $this->col="
//         parent::executeSql("update ")
//     }
// }


/** It took me a lot of time but if it doesn't work don't do anything. */
