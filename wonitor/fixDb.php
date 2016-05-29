<?php
date_default_timezone_set('UTC');
$isodate = date('c', time());
ini_set('error_reporting', E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', 1);
require_once 'dbUtil.php';

function info($stringy) {
    echo 'Info: ' . $stringy. '<br \>';
}
function warning($string) {
    echo 'Warning: ' . $string. '<br \>';
}
function error($string) {
    die('Error: ' . $string);
}

// rounds.sqlite3
if (file_exists('./data/rounds.sqlite3')) {
    try {
        $db = openDB( $wonitorDb );
        $query = 'SELECT COUNT(1) as count FROM rounds WHERE averageSkill<0';
        $numentries = $db->query( $query, PDO::FETCH_NUM )->fetchAll(PDO::FETCH_COLUMN, 0)[0];
        if ($numentries != 0) {
            info('Found rounds with negative averageSkil. Will set them to 0 now.');
            $query = 'UPDATE rounds SET averageSkill=0 WHERE averageSkill<0';
            $db->query( $query, PDO::FETCH_NUM );
        }

        closeDB( $db );
    }
    catch (PDOException $e) {
        warning($e->getMessage());
    }
}
echo 'All done.'
?>
