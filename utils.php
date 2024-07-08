<?php

declare(strict_types=1);


require_once "./instance.php";
require_once "./codes.php";
require_once "./scripts/validation.php";
require_once "./auth_utils.php";
require_once "./exceptions.php";
require_once "./scripts/classes.php";
require_once "./otherutils.php";
require_once "./dotenv.php";

function getTheRequired(string $rawUrl): array
{
    $as_array = explode("/", $rawUrl);
    $return = array();
    for ($i = 3; $i < count($as_array); $i++) {
        array_push($return, $as_array[$i]);
    }
    return $return;
}


function getData(string $sql, array $params = [])
{
    $jwt = new JWT($_ENV["KEY"]);
    $auth = new Authentication($jwt);
    if (!$auth->authenticate()) {
        http_response_code(403);
        die(json_encode(["error" => INVALID_TOKEN]));
    }
    global $db;
    $data = [];
    try {
        $data = $db->executeSql($sql, $params, true);
    } catch (PDOException $e) {
        http_response_code(500);
        return json_encode([
            "error" => DB_SERVER_ERROR
        ]);
    }
    if (is_object($data)) {
        http_response_code(500);
        return json_encode([
            "error" => DB_SERVER_ERROR,
            "desc" => $data->errorInfo
        ]);
    }
    if ($data["rows"] === 0) {
        http_response_code(400);
        return json_encode([
            "error" => EMPTY_RESPONSE
        ]);
    }
    unset($data["rows"]);

    return json_encode([
        "success" => true,
        "data" => $data
    ]);
}

function getDataWithParams(string $sql, array $params = [])
{
    $jwt = new JWT($_ENV["KEY"]);
    $auth = new Authentication($jwt);
    if (!$auth->authenticate()) {
        http_response_code(403);
        die(json_encode(["error" => INVALID_TOKEN]));
    }
    global $db;
    $data = [];
    try {
        $data = $db->executeParams($sql, $params, true);
    } catch (PDOException $e) {
        http_response_code(500);
        return json_encode([
            "error" => DB_SERVER_ERROR
        ]);
    }
    if (is_object($data)) {
        http_response_code(500);
        return json_encode([
            "error" => DB_SERVER_ERROR,
            "desc" => $data->errorInfo
        ]);
    }
    if ($data["rows"] === 0) {
        http_response_code(400);
        return json_encode([
            "error" => EMPTY_RESPONSE
        ]);
    }
    unset($data["rows"]);

    return json_encode([
        "success" => true,
        "data" => $data
    ]);
}

function acceptEvent(int $event_id, int $user_id)
{
    global $db;
    $result = $db->executeSql("SELECT accepted_by FROM events WHERE event_id = ?", [$event_id], true);
    $acceptedBy = (array)json_decode($result[0]["accepted_by"], true);
    if (!in_array($user_id, $acceptedBy)) {
        return json_encode(accept_event($event_id, $user_id, $acceptedBy));
    } else {
        return json_encode(reject_event($event_id, $user_id, $acceptedBy));
    }
}
function like(string $user_id, string $project_id)
{
    $action = -1;
    $jwt = new JWT($_ENV["KEY"]);
    $auth = new Authentication($jwt);
    if (!$auth->authenticate()) {
        http_response_code(403);
        die(json_encode(["code" => INVALID_TOKEN]));
    }
    global $db;
    $prevArray = $db->executeParams("select liked_by from projects where project_id=?", [$project_id], true);
    $newArray = (array)json_decode($prevArray[0]["liked_by"]);
    if (in_array($user_id, $newArray)) {
        global $action;
        $key = (int)array_search($user_id, $newArray);
        unset($newArray[$key]);
        $db->executeSql("UPDATE projects SET likes = likes - 1 WHERE project_id = ?", [$project_id]);
        $action = 0;
    } else {
        global $action;
        array_push($newArray, (int)$user_id);
        $db->executeSql("UPDATE projects SET likes = likes + 1 WHERE project_id = ?", [$project_id]);
        $action = 1;
    }
    $newArrayJSON = json_encode($newArray);
    $db->executeSql("UPDATE projects SET liked_by = ? WHERE project_id = ?", [$newArrayJSON, $project_id]);
    return json_encode([
        "success" => true,
        "action" => $action
        // 0 for dislike and 1 for 
    ]);
}

class TakeApiTest
{
    private $subid;
    private $subjectMap = [1 => "JavaScript", 2 => "HTML"];
    private $userId = null;
    private Cache $cache;
    private DataBase $db;
    private $subjectCheck;
    public function __construct(string $subjectID, int $userID)
    {
        global $db;
        $jwt = new JWT($_ENV["KEY"]);
        $auth = new Authentication($jwt);
        if (!$auth->authenticate()) {
            http_response_code(403);
            die(json_encode(["code" => INVALID_TOKEN]));
        }
        switch ($subjectID) {
            case "JavaScript":
                define("SUBJECT_ID", 1);
                break;
            case "HTML":
                define("SUBJECT_ID", 2);
                break;
            default:
                throw new Error("Undefined subject id");
        }
        $this->subid = $subjectID;
        $this->db = new DataBase();
        $this->userId = $userID;
        $this->cache = new Cache((int)$this->userId);
        if ($this->db->executeSql("select username from users where id=?", [$userID], true)["rows"] === 0) {
            throw new UserNotFoundException("User not found");
        }
        if (!$this->userId) {
            throw new CacheException("Cache not found");
        }
    }

    public function initCache()
    {
        $this->cache->set("1", SUBJECT_ID);
        if (is_array($this->cache->get()) &&  $this->cache->get()["code"] == 88 || ($this->cache->get() == -1)) {
            throw new CacheException("Cache error");
        }
    }
    // the longest fn name everrrrr
    public function checkIfTheUserHasTakenTheTest(int $user_id)
    {
        $subjectCheck = $this->db->executeSql("select taken_by from test_list where name=?", [$this->subjectMap[SUBJECT_ID]], true);
        $this->subjectCheck = $subjectCheck;
        $people = (array)json_decode($subjectCheck[0]["taken_by"]);
        if (in_array($user_id, $people)) {
            return false;
        }
        return true;
    }
    // cherdleys
    public function firstQuestion()
    {

        if (!$this->checkIfTheUserHasTakenTheTest($this->userId)) {
            http_response_code(403);
            return json_encode([
                "code" => 403,
                "message" => "You've already taken the test"
            ]);
        }

        // $questionOne = $this->db->executeParams("select question,a,b,c,d from tests where subject=? limit 1 offset ?", [$this->subjectMap[SUBJECT_ID], (int)$this->cache->get() - 1], TRUE);
        $questionOne = getDataWithParams("select question,a,b,c,d from tests where subject=? limit 1 offset ?", [$this->subjectMap[SUBJECT_ID], (int)$this->cache->get() - 1]);
        return $questionOne;
    }
    public function nextQuestion(string $userAnswer)
    {
        $userAnswer = strip_tags($userAnswer);
        $userAnswer = stripslashes($userAnswer);
        $userAnswer = htmlspecialchars($userAnswer);


        if (!preg_match("/a|b|c|d/", $userAnswer)) {
            return json_encode([
                "code" => VALIDATION_ERROR
            ]);
        }


        $temp = $this->db->executeSql("select count(*) as noOfQuestions from tests where subject=?", [$this->subjectMap[(int)SUBJECT_ID]], true);

        if (is_object($temp)) {
            return json_encode([
                "code" => DB_SERVER_ERROR
            ]);
        }

        define("NUMBER_OF_QUESTIONS", (int)$temp[0]["noOfQuestions"]);

        $realAns = $this->db->executeParams("select answer from tests where subject=? limit 1 offset ?", [$this->subjectMap[SUBJECT_ID], (int)($this->cache->get()) - 1], TRUE);
        if (!$realAns) {
            return (json_encode(["code" => 400]));
        }

        $this->cache->set((string)($this->cache->get() + 1), $this->cache->get(TRUE)["subId"]);
        $newQuestion = $this->db->executeParams("select question,a,b,c,d from tests where subject=? limit 1 offset ?", [$this->subjectMap[SUBJECT_ID], (int)$this->cache->get() - 1], TRUE);
        if ($newQuestion["rows"] == 0) {
            $subjectCheck = $this->db->executeSql("select taken_by from test_list where name=?", [$this->subjectMap[SUBJECT_ID]], true);

            $res = (array)json_decode($subjectCheck[0]["taken_by"], true);
            array_push($res, $this->userId);
            $this->db->executeSql("update test_list set taken_by=? where name=?", [json_encode($res), $this->subjectMap[SUBJECT_ID]]);
            // print_r($realAns[0]);
            $correct = ($realAns[0]["answer"] == $userAnswer) ? 1 : 0;

            if ($correct == 1) {
                $this->db->executeSql("UPDATE `users` SET `points` = `points` - ? WHERE id=?", [10, $this->userId], FALSE);
            }

            $this->cache->cleanUp();
            return json_encode([
                "code" => 12,
                "correct" => $correct
            ]);
        }
        $newQuestionJSON = [
            "question" => $newQuestion[0]["question"],

            "a" => $newQuestion[0]["a"],
            "b" => $newQuestion[0]["b"],
            "c" => $newQuestion[0]["c"],
            "d" => $newQuestion[0]["d"],
            "topic" => $this->subjectMap[SUBJECT_ID]
        ];


        if ($realAns[0]["answer"] == $userAnswer) {
            $this->db->executeSql("UPDATE `users` SET `points` = `points` + ? WHERE id=?", [10, $this->userId], FALSE);
            return (json_encode(["correct" => true, "nextQuestion" => $newQuestionJSON, "ans" => $realAns[0]]));
        } else {
            $this->db->executeSql("UPDATE `users` SET `points` = `points` - ? WHERE id=?", [10, $this->userId], FALSE);
            return (json_encode(["correct" => false, "ans" => $realAns[0], "nextQuestion" => $newQuestionJSON]));
        }
    }
};

function deleteProject(string $project_id, string $user_id)
{
    $jwt = new JWT($_ENV["KEY"]);
    $auth = new Authentication($jwt);
    if (!$auth->authenticate()) {
        http_response_code(403);
        die(json_encode(["code" => INVALID_TOKEN]));
    }
    global $db;
    $result = $db->executeSql("SELECT * FROM projects WHERE project_id = ?", [$project_id], true)[0];
    if (!$result["file_path"]) {
        return 'error';
    }
    $path = "../dashboard/upload/uploads/" . $result["file_path"];
    $path = realpath($path);
    if (unlink($path)) {
        $db->executeSql("DELETE FROM projects WHERE project_id = ? and user_id=?", [$project_id, $user_id]);
        $db->executeSql("UPDATE users SET uploads = uploads - 1 WHERE id = ?", [$user_id]);
        $db->executeSql("UPDATE `users` SET `points` = `points` - 10 WHERE id=?", [$user_id]);
        return 'deleted';
    } else {
        return 'error';
    }
}
function submitFeedback()
{
    global $db;
    $jwt = new JWT($_ENV["KEY"]);
    $auth = new Authentication($jwt);
    if (!$auth->authenticate()) {
        http_response_code(403);
        die(json_encode(["code" => INVALID_TOKEN]));
    }
    $feed = htmlspecialchars($_POST["feed"]);

    $userId = $_POST["userid"];

    if (!empty($feed)) {
        $db->executeSql("INSERT INTO feedbacks (feedback_body, user_id) VALUES (?, ?)", [$feed, $userId]);
    } else {
        return json_encode(["error" => true]);
    }
    return json_encode(["success" => true]);
}

function upload(array $theFile, string $name, string $desc, int $user_id)
{
    $jwt = new JWT($_ENV["KEY"]);
    $auth = new Authentication($jwt);
    if (!$auth->authenticate()) {
        http_response_code(403);
        die(json_encode(["code" => INVALID_TOKEN]));
    }
    global $db;
    $file_name = $theFile["name"];
    $project_name = htmlspecialchars($name);
    $project_desc = htmlspecialchars($desc);
    $random = bin2hex(random_bytes(21));

    if (!empty($file_name) && !empty($project_name) && !empty($project_desc)) {

        // Handling the file
        $tempFilePath = $theFile["tmp_name"];
        $tempFile = fopen($tempFilePath, "rb");
        if ($tempFile) {
            // MARK: INSERT FILE TO DATABASE
            $currentDate = date("mdhms");

            $pathInfo = pathinfo($file_name); // If the file's name from $_FILES is text_doc.txt,
            $actualFilename = $pathInfo["filename"]; // = text_doc
            $extension = $pathInfo["extension"]; // = txt

            // Insert the path name without project id
            $toStorePathOld = (string) $actualFilename . "--" . $currentDate . "$random-UzX" . $user_id . "$random-UzX" . "--." . $extension; // Without project id
            $db->executeSql("INSERT INTO projects (project_name, file_path, project_detail, user_id) VALUES(?, ?, ?, ?)", [$project_name, $toStorePathOld, $project_desc, $user_id]);

            // Select from projects table and get the project id to set it to the file path
            $result = $db->executeSql("SELECT project_id FROM projects WHERE file_path = ?", [$toStorePathOld], true);
            $resultId = (int) $result[0]["project_id"];
            $toStorePathNew = (string) $actualFilename . "--" . $currentDate . "$random-UzX" . $user_id . "$random-UzX" . "$random-PzX" . $resultId . "$random-PzX" . "--." . $extension;
            $db->executeSql("UPDATE projects SET file_path = ? WHERE project_id = ?", [$toStorePathNew, $resultId]);

            $fileContent = fread($tempFile, 5 * 1024 * 1024);
            fclose($tempFile);

            // MARK: WRITE ON A NEW FILE
            $toOpenPath = (string) "../dashboard/upload/uploads/" . $toStorePathNew; // Prepare the environment
            $newFile = fopen($toOpenPath, "wb"); // Create a new file and write contents of project
            if ($newFile) {
                fwrite($newFile, $fileContent);
                fclose($newFile);
            } else {
                return json_encode([
                    "type" => "error"
                ]);
            }
        } else {
            return json_encode([
                "type" => "error"
            ]);
        }

        $db->executeSql("UPDATE users SET uploads = uploads + 1 WHERE id = ?", [$user_id]);
        $db->executeSql("UPDATE users SET points = points + 10 WHERE id = ?", [$user_id]);
        return json_encode([
            "type" => "success"
        ]);
    }
}
