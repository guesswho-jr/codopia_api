<?php

declare(strict_types=1);
require_once "./instance.php";
function accept_event(int $event_id, int $user_id, array $array, int $point = 10)
{
    global $db;
    array_push($array, $user_id);
    $encode = json_encode($array);
    $db->executeSql("UPDATE events SET accepted_by = ? WHERE event_id = ?", [$encode, $event_id]);
    $db->executeSql("UPDATE events SET accepts = accepts + 1 WHERE event_id = ?", [$event_id]);
    $db->executeSql("UPDATE `users` SET `points` = `points` + ? WHERE id=?", [$point, $user_id], FALSE);
    return ["action" => "inc"];
}

function reject_event(int $event_id, int $user_id, array $array, int $point = 10)
{
    global $db;
    $index = array_search($user_id, $array);
    unset($array[$index]);
    $encode = json_encode($array);
    $db->executeSql("UPDATE events SET accepted_by = ? WHERE event_id = ?", [$encode, $event_id]);
    $db->executeSql("UPDATE events SET accepts = accepts - 1 WHERE event_id = ?", [$event_id]);
    $db->executeSql("UPDATE `users` SET `points` = `points` - ? WHERE id=?", [$point, $user_id], FALSE);
    return ["action" => "dec"];
}
