<?php
date_default_timezone_set('UTC');
$isodate = date('c', time());
ini_set('error_reporting', E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', 1);

$warningsCount = 0;

function info($stringy) {
    echo 'Info: ' . $stringy. '<br \>';
}
function warning($string) {
    echo '<b>Warning:</b> ' . $string. '<br \>';
    global $warningsCount;
    $warningsCount++;
}
function error($string) {
    die('<b>Error:</b> ' . $string);
}


// PHP
info('PHP version ' . phpversion() . ' running as user ' . `whoami` . '.');


// config.php
if (!file_exists('./config.php')) {
    error('<b>config.php</b> does not exist. Create it by copying <b>config.php.new</b>, and configure it by consulting the readme file.');
}
else {
    info('config.php found.');
}

require_once 'config.php';
if (!isset($serverIdWhiteList)) {
    error('No whitelist found in config.php. Consult the readme file.');
}
if (gettype($serverIdWhiteList) != 'array') {
    error('Whitelist in config.php is not an array. Consult the readme file.');
}
if (count($serverIdWhiteList) == 0) {
    warning('Whitelist in config.php is empty. You will not be able to recieve data from the game server. Consult the readme file.');
} else {
    info('Whitelist found with ' . count($serverIdWhiteList) . ' entries.');
}

if ($logging) {
    info('Logging is enabled. This will reveal your serverId(s).');
}
if (file_exists('./data/logfile.txt')) {
    info('<a href="./data/logfile.txt">Logfile</a> exists. Check it out.');
}


// dbUtil.php
if (!file_exists('./dbUtil.php')) {
    error('<b>dbUtil.php</b> does not exist.');
}
else {
    info('dbUtil.php found.');
}
require_once 'dbUtil.php';


// index.php
if (file_exists('./index.php') && file_exists('./index.html')) {
    warning('More than one landing page found (<a href="./index.php">index.php</a> and <a href="./index.html">index.html</a>). You should probably delete the html version.');
}


// data
if (file_exists('./data')) {
    info('Data directory exists (./data).');
}
else {
    if (!mkdir('./data', 0755, true)) {
        error("unable to create data directory.<br>\nMake sure user <b>" . `whoami` . '</b> has permissions to write into the wonitor root directory or manually create the <b>./data</b> directory with permissions to read and write for user <b>' . `whoami` . '</b>.');
    }
}

if (is_writable('./data')) {
    info('Data directory is writable.');
}
else {
    error('Data directory is not writable. Check permissions. User <b>' . `whoami` . '</b> should have write access.');
}

//file_put_contents('./data/temp', $isodate, FILE_APPEND | LOCK_EX) or error('Unable to write into data directory. Check permissions. User <b>' . `whoami` . '</b> should have write access.');
//unlink('./data/temp');

// rounds.sqlite3
if (!databaseExists( $wonitorDb )) {
    warning('No wonitor database found (./data/rounds.sqlite3). It will be created as soon as the first round stats are recieved.');
}
else {
    info('Wonitor database found (./data/rounds.sqlite3).');

    if (filesize('./data/rounds.sqlite3') == 0) {
        warning('Wonitor database has size 0. This is not a problem. Once the first data is recieved, the tables will be created. If not, you might try to delete the database and wait for the creation of a new one.');
    } else {
        try {
            $db = openDB( $wonitorDb );
            $query = 'SELECT COUNT(1) as numentries FROM rounds';
            $numentries = $db->query( $query, PDO::FETCH_NUM )->fetchAll(PDO::FETCH_COLUMN, 0)[0];
            closeDB( $db );
            info("Wonitor database query successful. $numentries entries on record.");
        }
        catch (PDOException $e) {
            warning($e->getMessage());
        }
    }

    if (is_writable('./data/rounds.sqlite3')) {
        info('Wonitor database is writable.');
    }
    else {
        error('Wonitor database is not writable. Check permissions. User <b>' . `whoami` . '</b> should have write access.');
    }
    
    try {
        $db = openDB( $wonitorDb );
        $query = 'SELECT COUNT(1) as count FROM rounds WHERE averageSkill<0';
        $numentries = $db->query( $query, PDO::FETCH_NUM )->fetchAll(PDO::FETCH_COLUMN, 0)[0];
        closeDB( $db );
        if ($numentries != 0) {
            warning('Found rounds with negative averageSkil. Click <a href="./fixDb.php">here</a> to fix them.');
        }
    }
    catch (PDOException $e) {
        warning($e->getMessage());
    }
}


// ns2plus.sqlite3
if (!databaseExists( $ns2plusDb )) {
    info('No NS2+ database found (./data/ns2plus.sqlite3). It will be created as soon as the first round stats are recieved. But only if the game server is running ns2+ and is configured to send it.');
}
else {
    info('NS2+ database found (./data/ns2plus.sqlite3).');
    if (filesize('./data/ns2plus.sqlite3') == 0) {
        warning('NS2+ database has size 0. This is not a problem. Once the first data is recieved, the tables will be created. If not, you might try to delete the database and wait for the creation of a new one.');
    } else {
        try {
            $db = openDB( $ns2plusDb );
            $query = 'SELECT COUNT(1) as numentries FROM RoundInfo';
            $numentries = $db->query( $query, PDO::FETCH_NUM )->fetchAll(PDO::FETCH_COLUMN, 0)[0];
            closeDB( $db );
            info("NS2+ database query successful. $numentries entries on record.");
        }
        catch (PDOException $e) {
            warning($e->getMessage());
        }
    }

    if (is_writable('./data/ns2plus.sqlite3'))  {
        info('NS2+ database database is writable.');
    }
    else {
        error('NS2+ database is not writable. Check permissions. User <b>' . `whoami` . '</b> should have write access.');
    }
}

if ($warningsCount == 0) {
    echo "<br>\nAll systems operating within normal parameters.";
} else {
    echo "<br>\nThere have been <b>$warningsCount</b> warnings.";
}
?>
