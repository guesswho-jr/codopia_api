<?php

declare(strict_types=1);
require_once "./instance.php";
require_once "../admin/scripts/validation.php";
require_once "./codes.php";
require_once "../admin/scripts/classes.php";
require_once '../admin/scripts/PHPMailer/src/Exception.php';
require_once '../admin/scripts/PHPMailer/src/PHPMailer.php';
require_once '../admin/scripts/PHPMailer/src/SMTP.php';


const KEY = '67e0321f1d0ba5ce3074c4fcb3916241bdae709983cd48d923e21a3f2d5ba0d21f05723a133ad38db0d85a6c00b3b4555e7afdadc3ac8386de8f5de6e84128d8aa8a51cbf387afba14434a3e88eaf7a667c985266ed3888ebad59e31c1a519da44d0801e5fd12c7be82a89b29c872bf0143474a89ccddf909dcbf52300decd4a';
class JWT
{
    public function __construct(private string $key)
    {
    }
    private function url_encode(string $text): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
    }
    public function encode(array $payload): string
    {
        $header = json_encode([
            "alg" => "HS256",
            "typ" => "JWT"
        ]);
        $header = $this->url_encode($header);
        $payload = json_encode($payload);
        $payload = $this->url_encode($payload);

        $signiture = hash_hmac("sha256", $header . "." . $payload, $this->key, true);
        $signiture = $this->url_encode($signiture);
        return $header . "." . $payload . "." . $signiture;
    }
    public function decode(string $token): array | bool
    {
        if (preg_match("/^(?<header>.+)\.(?<payload>.+)\.(?<signiture>.+)$/", $token, $matches) !== 1) {
            return false;
        }
        $signiture = hash_hmac("sha256", $matches["header"] . "." . $matches["payload"], $this->key, true);
        $signiture_got = $this->url_decode($matches["signiture"]);
        if (!hash_equals($signiture_got, $signiture)) {
            return false;
        }
        $payload = (array)json_decode($this->url_decode($matches["payload"]), true);
        return $payload;
    }
    public function decode_for_app(string $token): string | bool
    {
        if (preg_match("/^(?<header>.+)\.(?<payload>.+)\.(?<signiture>.+)$/", $token, $matches) !== 1) {
            return false;
        }
        $signiture = hash_hmac("sha256", $matches["header"] . "." . $matches["payload"], $this->key, true);
        $signiture_got = $this->url_decode($matches["signiture"]);
        if (!hash_equals($signiture_got, $signiture)) {
            return false;
        }
        $payload = $this->url_decode($matches["payload"]);
        return $payload;
    }
    private function url_decode(string $val): string
    {
        return base64_decode(str_replace(["-", "_"], ["+", "/"], $val));
    }
}

function login(string $data)
{
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        header("ALLOW: POST");
        exit;
    }

    function both_($val)
    {
        return isset($val) && !empty($val);
    }
    $data = (array)json_decode($data, true)["data"];
    if (!isset($data["username"]) || !isset($data['password'])) {
        http_response_code(403);
        return json_encode([
            "code" => 403,
            "payload" => $data
        ]);
    }
    if (!(both_($data["username"]) && both_($data["password"]))) {
        http_response_code(403);
        return json_encode([
            "code" => 403,
            "payload" => $data
        ]);
    }
    global $db;
    $user = $db->executeSql("select id, username, points, uploads, password from users where username=? limit 1", [$data["username"]], true);
    // print_r($user);
    if ($user["rows"] == 0) {
        return json_encode([
            'code' => EMPTY_RESPONSE
        ]);
    }
    if (!password_verify($data["password"], $user[0]["password"])) {
        http_response_code(403);
        return json_encode([
            "code" => PASSWORD_DONT_MATCH
        ]);
    }
    unset($user["rows"]);
    $payload = [
        "username" => $user[0]["username"],
        "points" => $user[0]["points"],
        "uploads" => $user[0]["uploads"],
        "user_id" => $user[0]["id"]
    ];
    $jwtCont = new JWT(KEY);
    return json_encode([
        "token" => $jwtCont->encode($payload)
    ]);
}

function sendEmail(string $to, $code)
{

    // Create a new PHPMailer instance
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        //Server settings
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'codopiaet@gmail.com';
        $mail->Password = 'jskobrezvxrjsfua';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('codopiaet@gmail.com', 'Codopia');
        $mail->addAddress($to, "User");

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Email Verification Code';
        $mail->Body = "This is your verification code: <b>{$code}</b>";
        $mail->AltBody = "This is your verification code: <b>{$code}</b>";

        $mail->send();
    } catch (Exception $e) {
        throw new MailException("Error while sending");
    }
}

class Authentication
{
    public function __construct(private JWT $jwt)
    {
    }

    public function authenticate(): bool
    {
        $headers = apache_request_headers();

        if (isset($_SERVER['HTTP_AUTHORIZATION']) || isset($headers["authorization"]) || isset($headers["Authorization"])) {
            if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $data = $_SERVER['HTTP_AUTHORIZATION'];
            } else if (isset($headers["authorization"])) {
                $data = $headers["authorization"];
            } else if (isset($headers["Authorization"])) {
                $data = $headers["Authorization"];
            } else {
                return false;
            }
            if (!preg_match("/^Bearer\s+(.*)$/", $data, $matches)) {
                http_response_code(400);
                return false;
            }
            if (!$this->jwt->decode($matches[1])) {
                return false;
            }
            return true;
        } else {
            return false;
        }
    }
}

class SignUpForApp extends DataBase
{
    private $file;
    public function __construct(
        private string $username,
        private string $password,
        private string $cpassword,
        private string $bio,
        private string $full_name,
        private string $email,
    ) {
        parent::__construct();
        if ($this->cpassword !== $this->password) {
            throw new Exception("Passwords don't match");
        }
        $this->file = "cache" . DIRECTORY_SEPARATOR . "$this->username-file.cache";
    }

    private function setCodeForUser(int $code, int $trials = 1, int $code_sent = 1)
    {
        if (file_exists($this->file)) {
            unlink($this->file);
        }
        $data = [
            "username" => $this->username,
            "code" => $code,
            "issue_time" => time(),
            "trials" => $trials,
            "code_sent" => $code_sent
        ];
        file_put_contents($this->file, base64_encode(json_encode($data)));
    }
    private function getCodeForUser()
    {
        if (!file_exists($this->file)) {
            return false;
        }
        $data = file_get_contents($this->file);
        if (!$data) {
            http_response_code(500);
            throw new Exception("Error occured on our side. Please report it");
        }
        return (array)json_decode(base64_decode($data), true);
    }

    public function sendCode()
    {
        $code = random_int(100000, 999999);
        if (!file_exists($this->file)) {
            $this->setCodeForUser($code);
        }
        // sendEmail($this->email, $code);

        $returned = $this->getCodeForUser();
        if ($returned["code_sent"]) {
            if ((int)$returned["trials"] >= 5) {
                throw new Exception("No more trials");
            }
            $this->setCodeForUser($code, (int)$returned["trials"] + 1, 1);
        }
    }
    public function checkCode($code)
    {
        if (!file_exists($this->file)) {
            die(json_encode(["error" => "Code is not sent yet!"]));
        }
        $file = $this->getCodeForUser();
        $realCode = (int)$file["code"];
        if ($realCode === $code) {
            $time = (int)$file["issue_time"];
            if (time() - $time < 300) {
                $this->cleanUp();
                return true;
            }
            return false;
        }
        return false;
    }
    public function cleanUp()
    {
        unlink($this->file);
    }
    public function createUser()
    {
        $userCont = new User($this->username, $this->password);
        return $userCont->createUser($this->username, $this->password, $this->cpassword, $this->bio, $this->full_name, $this->email);
    }
    // public function __destruct()
    // {
    //     $this->cleanUp();
    // }
}
