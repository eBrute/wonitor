<?php
    date_default_timezone_set('UTC');
    $isodate = date('c', time());

    if (!file_exists('./data')) { mkdir('./data', 0777, true); }

    // uncomment the next block to enable logging
    // logs all incoming requests to $logFile
    // WARNING logs are public and might reveal your pre-hashed serverId, use for debuggin only and delete afterwards
    /*
    $logFile = './data/logfile.txt';
    file_put_contents($logFile, $isodate . ' ', FILE_APPEND | LOCK_EX) or die('Error: Unable to write file');
    file_put_contents($logFile, 'POST ' . json_encode($_POST) . ' ', FILE_APPEND | LOCK_EX) or die('Error: Unable to write file');
    file_put_contents($logFile, 'GET '  . json_encode($_GET) . "\n", FILE_APPEND | LOCK_EX) or die('Error: Unable to write file');
    */

    // white list: all requests that do not contain on of these serverIds will be rejected
    // case sensitive, comma separated
    // I.e. $serverIdWhiteList = array('MyServer','MyOtherServer','someRandomString');
    $serverIdWhiteList = array();

    $fieldTypes = array(
        'serverName' => 'TEXT',
        'serverIp' => 'TEXT',
        'serverPort' => 'INTEGER',
        'serverId' => 'TEXT',
        'version' => 'INTEGER',
        'modIds' => 'TEXT',
        'time' => 'TEXT',
        'map' => 'TEXT',
        'winner' => 'INTEGER',
        'length' => 'REAL',
        'isTournamentMode' => 'INTEGER',
        'isRookieServer' => 'INTEGER',
        'startPathDistance' => 'REAL',
        'startHiveTech' => 'TEXT',
        'startLocation1' => 'TEXT',
        'startLocation2' => 'TEXT',
        'numPlayers1' => 'INTEGER',
        'numPlayers2' => 'INTEGER',
        'numPlayersRR' => 'INTEGER',
        'numPlayersSpec' => 'INTEGER',
        'numPlayers' => 'INTEGER',
        'maxPlayers' => 'INTEGER',
        'numRookies1' => 'INTEGER',
        'numRookies2' => 'INTEGER',
        'numRookiesRR' => 'INTEGER',
        'numRookiesSpec' => 'INTEGER',
        'numRookies' => 'INTEGER',
        'skillTeam1' => 'INTEGER',
        'skillTeam2' => 'INTEGER',
        'averageSkill' => 'REAL',
        'killsTeam1' => 'INTEGER',
        'killsTeam2' => 'INTEGER',
        'kills' => 'INTEGER',
        'numRTs1' => 'INTEGER',
        'numRTs2' => 'INTEGER',
        'numRTs' => 'INTEGER',
        'numHives' => 'INTEGER',
        'numCCs' => 'INTEGER',
        'numTechPointsCaptured' => 'INTEGER',
        'biomassLevel' => 'INTEGER'
        );

    $data = array();


    function readData() {
       global $data, $fieldTypes;

        if ( !array_key_exists( 'data', $_POST ) ) {
            exit('invalid data');
        }

        $data = json_decode($_POST['data'], true);

        foreach ( $fieldTypes as $fieldName => $fieldType ) {
            if ( !array_key_exists( $fieldName, $data ) ) {
                exit('Field ' . $fieldName . ' missing');
            }
        }
    }


    function checkWhitelist(& $data) {
        global $serverIdWhiteList;
        if ( ! in_array( $data['serverId'], $serverIdWhiteList ) ) {
            exit('serverId ' . $data['serverId'] . ' not Whitelisted');
        }
        // NOTE: if serverId is a secret, possible timing attack here
        // NOTE: possible optimization here if hashes would be saved as blobs
    }



    function openDB(& $file_db) {
        // Create (connect to) SQLite database in file
        $file_db = new PDO('sqlite:./data/rounds.sqlite3');
        // Set errormode to exceptions
        $file_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }


    function createTable(& $file_db) {
        global $fieldTypes;
        $execStatement = "CREATE TABLE IF NOT EXISTS rounds (\n\tid INTEGER PRIMARY KEY";
        foreach ($fieldTypes as $fieldName => $fieldType ) {
            $execStatement .= ",\n\t" . $fieldName . ' ' . $fieldType;
        }
        $execStatement .= ')';

        $file_db->exec($execStatement);
    }


    function insertData(& $file_db, & $data) {
        global $fieldTypes;

        // Prepare INSERT statement to SQLite3 file db
        $insertStatement = 'INSERT INTO rounds (';
        end($fieldTypes); $lastKey = key($fieldTypes);
        foreach ( $fieldTypes as $fieldName => $fieldType ) {
            $insertStatement .= $fieldName;
            if ($fieldName != $lastKey) $insertStatement .= ',';
        }
        $insertStatement .= ') VALUES (';
        foreach ( $fieldTypes as $fieldName => $fieldType ) {
            $insertStatement .= ':' . $fieldName;
            if ($fieldName != $lastKey) $insertStatement .= ',';
        }
        $insertStatement .= ')';
        $stmt = $file_db->prepare($insertStatement);

        // Bind parameters to statement variables
        $stmt->bindValue(':serverName',           $data['serverName']);
        $stmt->bindValue(':serverIp',             $data['serverIp']);
        $stmt->bindValue(':serverPort',           $data['serverPort']);
        $stmt->bindValue(':serverId',   shortHash($data['serverId']));
        $stmt->bindValue(':version',              $data['version']);
        $stmt->bindValue(':modIds',   json_encode($data['modIds']));
        $stmt->bindValue(':time',                 $data['time']);
        $stmt->bindValue(':map',                  $data['map']);
        $stmt->bindValue(':winner',               $data['winner']);
        $stmt->bindValue(':length',               $data['length']);
        $stmt->bindValue(':isTournamentMode',     $data['isTournamentMode'], PDO::PARAM_BOOL);
        $stmt->bindValue(':isRookieServer',       $data['isRookieServer']  , PDO::PARAM_BOOL);
        $stmt->bindValue(':startPathDistance',    $data['startPathDistance']);
        $stmt->bindValue(':startHiveTech',        $data['startHiveTech']);
        $stmt->bindValue(':startLocation1',       $data['startLocation1']);
        $stmt->bindValue(':startLocation2',       $data['startLocation2']);
        $stmt->bindValue(':numPlayers1',          $data['numPlayers1']);
        $stmt->bindValue(':numPlayers2',          $data['numPlayers2']);
        $stmt->bindValue(':numPlayersRR',         $data['numPlayersRR']);
        $stmt->bindValue(':numPlayersSpec',       $data['numPlayersSpec']);
        $stmt->bindValue(':numPlayers',           $data['numPlayers']);
        $stmt->bindValue(':maxPlayers',           $data['maxPlayers']);
        $stmt->bindValue(':numRookies1',          $data['numRookies1']);
        $stmt->bindValue(':numRookies2',          $data['numRookies2']);
        $stmt->bindValue(':numRookiesRR',         $data['numRookiesRR']);
        $stmt->bindValue(':numRookiesSpec',       $data['numRookiesSpec']);
        $stmt->bindValue(':numRookies',           $data['numRookies']);
        $stmt->bindValue(':skillTeam1',           $data['skillTeam1']);
        $stmt->bindValue(':skillTeam2',           $data['skillTeam2']);
        $stmt->bindValue(':averageSkill',         $data['averageSkill']);
        $stmt->bindValue(':killsTeam1',           $data['killsTeam1']);
        $stmt->bindValue(':killsTeam2',           $data['killsTeam2']);
        $stmt->bindValue(':kills',                $data['kills']);
        $stmt->bindValue(':numRTs1',              $data['numRTs1']);
        $stmt->bindValue(':numRTs2',              $data['numRTs2']);
        $stmt->bindValue(':numRTs',               $data['numRTs']);
        $stmt->bindValue(':numHives',             $data['numHives']);
        $stmt->bindValue(':numCCs',               $data['numCCs']);
        $stmt->bindValue(':numTechPointsCaptured',$data['numTechPointsCaptured']);
        $stmt->bindValue(':biomassLevel',         $data['biomassLevel']);

        $stmt->execute();
    }


    function deleteTable(& $file_db) {
        // Drop table messages from file db
        $file_db->exec('DROP TABLE rounds');
    }


    function closeDB(& $file_db) {
        // Close file db connection
        $file_db = null;
    }


    /* generates a short hash by computing a sha1 hash, base64 encoding it,
     * making it pseudo base62 and truncating the result to $n characters
     * hash is not secure by any means
     * default for $n is 10, which has roughly 60 bits of entropy
     * estimated collision is 2^29 (once every 500 million hashes) for length 10
     */
    function shortHash($input, $n = 10) {
        return substr( strtr( base64_encode( sha1( $input, true ) ), '+/=', 'aeo'), 0, $n );
    }


    function main() {
        global $data;
        $file_db = null;
        readData();
        checkWhitelist($data);
        try {
            openDB($file_db);
            createTable($file_db);
            insertData($file_db, $data);
            //deleteTable($file_db);
            closeDB($file_db);
        }
        catch(PDOException $e) {
            // Print PDOException message
            echo $e->getMessage();
        }
    }

    main();

    // kate: syntax PHP; word-wrap off; replace-tabs on; indent-width 4; tab-width 4; indent-mode cstyle; show-tabs on;
?>
