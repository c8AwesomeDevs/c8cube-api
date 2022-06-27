<?php

use App\Log;

function saveLog($user_id, $description) {
    $log = new Log();
    $log->user_id = $user_id;
    $log->description = $description;
    $log->save();

    return $log;
}