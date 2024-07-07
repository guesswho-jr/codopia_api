<?php

declare(strict_types=1);

define("URI", $_SERVER['REQUEST_URI']);
require_once "./codes.php";
require_once "./auth_utils.php";

header("Content-Type: application/json");
$contentType = isset($_SERVER['CONTENT_TYPE']) ? trim($_SERVER['CONTENT_TYPE']) : '';
if ($contentType != ("application/json" || "multipart/form-data") && strtolower($_SERVER['REQUEST_METHOD']) == 'post') {

    http_response_code(403);
    echo json_encode(["code" => NOT_ALLOWED]);
    exit;
}


// die((string)count(explode('/', URI)));
if (count(explode('/', URI)) < 4) {
    header("HTTP/2 400 Bad Request"); // Look up this THE CODE
    die(json_encode([
        "error" => BAD_REQUEST_CODE
    ]));
}
require_once "./utils.php";
require_once "../admin/scripts/validation.php";

define("PARAMS", getTheRequired(URI));
// die(print_r(PARAMS));
$testCont = null;
switch (PARAMS[0]) {
    case "projects_all":

        die(getData("select * from projects order by project_name asc"));
    case "project":
        if (!isset(PARAMS[1])) {
            header("HTTP/2 403 Forbidden");
            die(json_encode([
                "error" => 403
            ]));
        }
        // syntax /api/index.php/project/1 1 is the user id
        die(getData("select project_id, project_name, project_detail from projects where user_id=?", [PARAMS[1]]));
    case "accept":
        if (!(isset(PARAMS[1]) && isset(PARAMS[1]))) {
            header("HTTP/2 403 Forbidden");
            die(json_encode([
                "error" => 403
            ]));
        };
        // event_id, user_id
        die(acceptEvent((int)PARAMS[1], (int)PARAMS[2]));
    case "decode":
        $jwt = new JWT(KEY);
        $data = (array)json_decode(file_get_contents("php://input"), true)["data"];
        $token = $data["token"];
        if (!$token) die(json_encode(["code" => BAD_REQUEST_CODE]));
        if (!preg_match("/^Bearer\s+(.*)$/", $token, $matches)) {
            http_response_code(400);
            die(json_encode(["code" => 403]));
        }
        if (!$jwt->decode($matches[1])) {
            die(json_encode(["code" => NOT_ALLOWED]));
        } else {
            die(json_encode($jwt->decode($matches[1])));
        }
    case "events":
        if (!isset(PARAMS[1])) {
            header("HTTP/2 403 Forbidden");
            die(json_encode([
                "error" => 403
            ]));
        }
        $data = getData("select event_id, event_name, event_desc, xp, deadline, accepts, accepted_by from events");
        $data = (array)json_decode($data, true);

        if (!$data["success"]) {
            die(json_encode([
                "error" => $data["error"]
            ]));
        }
        $userId = (int)PARAMS[1];
        // $theArray = (array)json_decode($data["data"], true);
        $all_events = [];
        foreach ($data["data"] as $entry) {
            if (in_array($userId, (array)json_decode($entry["accepted_by"], true))) {
                $entry["accepted"] = true;
            } else {
                $entry["accepted"] = false;
            }
            unset($entry["accepted_by"]);
            array_push($all_events, $entry);
        }
        unset($data);
        die(json_encode([
            "success" => true,
            "data" => $all_events
        ]));
    case "test_list":
        die(getData("select * from test_list limit 5"));
    case "profile":
        if (!isset(PARAMS[1])) {
            header("HTTP/2 403 Forbidden");
            die(json_encode([
                "error" => 403
            ]));
        }
        die(getData("select username, email, points, uploads, full_name from users where id=?", [PARAMS[1]]));
    case "login":
        $data = file_get_contents("php://input");
        die(login($data));
    case "like":
        if (!(isset(PARAMS[1]) && isset(PARAMS[2]))) {
            http_response_code(403);
            die(json_encode([
                "error" => 403,
                "code" => INVALID_NUMBER_OF_PARAMS
            ]));
        }
        // like/user_id/project_id
        die(like(PARAMS[1], PARAMS[2]));
    case "begin_test":
        // take_test/user_id/subject_id
        if (!(isset(PARAMS[1]) && isset(PARAMS[2]))) {
            header("HTTP/2 403 Forbidden");
            die(json_encode([
                "error" => 403
            ]));
        }
        try {
            $cache = new Cache((int)PARAMS[1]);
            $testCont = new TakeApiTest(PARAMS[2], (int)PARAMS[1]);
            $testCont->initCache();
            $resp = $testCont->firstQuestion();
            if (!$resp) {
                http_response_code(403);
                die(json_encode(["error" => 403]));
            }
            die($resp);
        } catch (UserNotFoundException) {
            die(json_encode([
                "error" => "User not found"
            ]));
        } catch (CacheException) {
            die(json_encode([
                "error" => "Server error"
            ]));
        } catch (Error) {
            die(json_encode([
                "error" => "Unknown error occured"
            ]));
        }
    case "next_question":
        // next_question/user_id/subject_id/answer
        if (!(isset(PARAMS[1]) && isset(PARAMS[2]) && isset(PARAMS[3]))) {
            header("HTTP/2 403 Forbidden");
            die(json_encode([
                "error" => 403
            ]));
        }
        $testCont = new TakeApiTest(PARAMS[2], (int)PARAMS[1]);
        $data = $testCont->nextQuestion(PARAMS[3]);
        die($data);
    case "delete":
        // user_id, project_id
        if (!(isset(PARAMS[1]) && isset(PARAMS[2]))) {
            header("HTTP/2 403 Forbidden");
            die(json_encode([
                "error" => 403
            ]));
        }
        die(deleteProject(PARAMS[2], PARAMS[1]));
    case "feedback":
        die(submitFeedback());
    case "upload":
        if (!(isset(PARAMS[1]) && isset(PARAMS[2]) && isset(PARAMS[3]))) {
            header("HTTP/2 403 Forbidden");
            die(json_encode([
                "error" => 403
            ]));
        }

        die(upload($_FILES["file"], PARAMS[1], PARAMS[2], (int)PARAMS[3]));
    case "signup":
        if (!(isset(PARAMS[1]))) {
            header("HTTP/2 403 Forbidden");
            die(json_encode([
                "error" => 403
            ]));
        }
        try {
            $signer = new SignUpForApp(
                $_POST["username"],
                $_POST["password"],
                $_POST["cpassword"],
                $_POST["bio"],
                $_POST["full_name"],
                $_POST["email"]
            );
            if (PARAMS[1] == "send_code") {
                $signer->sendCode();
                die(json_encode([
                    "success" => true
                ]));
            } else if (PARAMS[1] == "check_code") {
                if (!isset(PARAMS[2])) {
                    header("HTTP/2 403 Forbidden");
                    die(json_encode([
                        "error" => 403
                    ]));
                }
                $resp = $signer->checkCode((int)PARAMS[2]);
                if ($resp === true) {
                    $resp = $signer->createUser();
                    die(json_encode(["success" => true]));
                } else {
                    die(json_encode(["success" => false, "reason" => "You might have not received your code or it expired or wrong."]));
                }
            } else {
                die(json_encode(["error" => "Unknown code"]));
            }
        } catch (Exception $e) {
            die(json_encode(["error" => $e->getMessage()]));
        }
    case "check":
        if (!validateString($_POST["username"])) {
            die(json_encode(["error" => "Error while validation"]));
        }
        $r = $db->executeSql("select username from users where username=? limit 1", [$_POST["username"]], true);
        if ((int)$r["rows"] !== 0) {
            die(json_encode(["info" => "Username is taken"]));
        } else {
            die(json_encode(["success" => "Username is valid"]));
        }
    default:
        header("HTTP/2 404 Not Found");
        die(json_encode([
            "error" => 404
        ]));
}
