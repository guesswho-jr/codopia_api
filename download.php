<?php

declare(strict_types=1);
define("UPLOAD_PATH", realpath("./uploads"));
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH);
}
function getPrettyFile($bulkyfile)
{
    $bulkyfileArr = explode('--', $bulkyfile);
    return $bulkyfileArr[0] . $bulkyfileArr[2];
}
if (isset($_GET["file"])) {
    $file = basename($_GET["file"]);

    $size = filesize(realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . $file));
    // echo ($size);
    // exit;
    if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . $file)) {
        $name =  basename(getPrettyFile($file));
        header("Content-Description: File Transfer");
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=" . $name);
        header("Expires: 0");
        header("Cache-Control: must revalidate");
        header("Pragma: public");
        header("Content-Length: " . $size);
        header("X-NAME:$name");
        readfile(realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . $file));
        exit;
    } else die("File not found ");
} else die("Invalid request");
