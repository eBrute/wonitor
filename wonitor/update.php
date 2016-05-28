<?php
    require_once 'config.php';
    require_once 'dbUtil.php';

    if (!file_exists('./data')) {
        if (!mkdir('./data', 0755, true)) {
            die("Error: unable to create data directory.<br>\nMake sure user <b>" . `whoami` . "</b> has permissions to write into the wonitor root directory or manually create the <b>./data</b> directory with permissions to read and write for user <b>" . `whoami` . "</b>.");
        }
    }

    $logFile = './data/logfile.txt';
    if ($logging) {
        $isodate = date('c', time());
        file_put_contents($logFile, $isodate . ' ',                      FILE_APPEND | LOCK_EX) or die('Error: Unable to write file');
        file_put_contents($logFile, 'POST ' . json_encode($_POST) . ' ', FILE_APPEND | LOCK_EX) or die('Error: Unable to write file');
        file_put_contents($logFile, 'GET '  . json_encode($_GET) . "\n", FILE_APPEND | LOCK_EX) or die('Error: Unable to write file');
        // file_put_contents($logFile, 'SERVER '  . json_encode($_SERVER) . "\n", FILE_APPEND | LOCK_EX) or die('Error: Unable to write file');
    }
    else {
        if (file_exists($logFile)) {
           unlink($logFile);
        }
    }

    if ($debug_showServerIds) {
        showServerIds();
    }

    $data = array();


    function readData() {
       global $data;

        if ( !array_key_exists( 'data', $_POST ) ) {
            exit('invalid data');
        }

        $data = json_decode($_POST['data'], true);
        if ($data == NULL) {
          exit('Error reading data');
        }
    }


    function checkData() {
        global $data, $wonitorStructure;
        if ( !array_key_exists( 'messageType', $_POST ) ) {
            exit('Unknown message type');
        }
        if ( $_POST['messageType'] == 'MatchEnd' ) {
            foreach ( $wonitorStructure['rounds'] as $fieldName => $fieldType ) {
                if ( !array_key_exists( $fieldName, $data ) && $fieldType != 'INTEGER PRIMARY KEY' ) {
                    exit('Field ' . $fieldName . ' missing');
                }
            }
        }
        elseif ( $_POST['messageType'] == 'NS2PlusStats' ) {
            if (
                !array_key_exists( 'RoundInfo', $data )  ||
                !array_key_exists( 'ServerInfo', $data ) ||
                !array_key_exists( 'Research', $data )   ||
                !array_key_exists( 'Buildings', $data )  ||
                !array_key_exists( 'Locations', $data )  ||
                !array_key_exists( 'MarineCommStats', $data ) ||
                !array_key_exists( 'PlayerStats', $data )
            ) {
                exit('Fields missing');
            }
        }
        else {
            exit('Unsupported message type:' . $_POST['messageType']);
        }
    }


    function checkWhitelist() {
        global $serverIdWhiteList;
        if ( !array_key_exists( 'serverId', $_POST ) ) {
            exit('Missing serverId');
        }
        if ( ! in_array( $_POST['serverId'], $serverIdWhiteList ) ) {
            exit('serverId ' . $_POST['serverId'] . ' not Whitelisted');
        }
        // NOTE: if serverId is a secret, possible timing attack here
        // NOTE: possible optimization here if hashes would be saved as blobs
    }


    function insertRoundData(& $db, & $data) {
        global $wonitorStructure;

        // Prepare INSERT statement to SQLite3 file db
        $insertStatement = buildInsertQueryStatement($wonitorStructure, 'rounds');
        $stmt = $db->prepare($insertStatement);

        // Bind parameters to statement variables
        $stmt->bindValue(':serverName',           $data['serverName'],        PDO::PARAM_STR);
        $stmt->bindValue(':serverIp',             $data['serverIp'],          PDO::PARAM_STR);
        $stmt->bindValue(':serverPort',           $data['serverPort'],        PDO::PARAM_INT);
        $stmt->bindValue(':serverId',   shortHash($_POST['serverId']),        PDO::PARAM_STR);
        $stmt->bindValue(':version',              $data['version'],           PDO::PARAM_STR);
        $stmt->bindValue(':modIds',   json_encode($data['modIds']),           PDO::PARAM_STR);
        $stmt->bindValue(':time',                 $data['time'],              PDO::PARAM_STR);
        $stmt->bindValue(':map',                  $data['map'],               PDO::PARAM_STR);
        $stmt->bindValue(':winner',               $data['winner'],            PDO::PARAM_INT);
        $stmt->bindValue(':length',               $data['length'],            PDO::PARAM_STR);
        $stmt->bindValue(':isTournamentMode',     $data['isTournamentMode'],  PDO::PARAM_BOOL);
        $stmt->bindValue(':isRookieServer',       $data['isRookieServer'],    PDO::PARAM_BOOL);
        $stmt->bindValue(':startPathDistance',    $data['startPathDistance'], PDO::PARAM_STR);
        $stmt->bindValue(':startHiveTech',        $data['startHiveTech'],     PDO::PARAM_STR);
        $stmt->bindValue(':startLocation1',       $data['startLocation1'],    PDO::PARAM_STR);
        $stmt->bindValue(':startLocation2',       $data['startLocation2'],    PDO::PARAM_STR);
        $stmt->bindValue(':numPlayers1',          $data['numPlayers1'],       PDO::PARAM_INT);
        $stmt->bindValue(':numPlayers2',          $data['numPlayers2'],       PDO::PARAM_INT);
        $stmt->bindValue(':numPlayersRR',         $data['numPlayersRR'],      PDO::PARAM_INT);
        $stmt->bindValue(':numPlayersSpec',       $data['numPlayersSpec'],    PDO::PARAM_INT);
        $stmt->bindValue(':numPlayers',           $data['numPlayers'],        PDO::PARAM_INT);
        $stmt->bindValue(':maxPlayers',           $data['maxPlayers'],        PDO::PARAM_INT);
        $stmt->bindValue(':numRookies1',          $data['numRookies1'],       PDO::PARAM_INT);
        $stmt->bindValue(':numRookies2',          $data['numRookies2'],       PDO::PARAM_INT);
        $stmt->bindValue(':numRookiesRR',         $data['numRookiesRR'],      PDO::PARAM_INT);
        $stmt->bindValue(':numRookiesSpec',       $data['numRookiesSpec'],    PDO::PARAM_INT);
        $stmt->bindValue(':numRookies',           $data['numRookies'],        PDO::PARAM_INT);
        $stmt->bindValue(':skillTeam1',           $data['skillTeam1'],        PDO::PARAM_INT);
        $stmt->bindValue(':skillTeam2',           $data['skillTeam2'],        PDO::PARAM_INT);
        $stmt->bindValue(':averageSkill', floatval($data['averageSkill']) > 0 ? $data['averageSkill'] : 0, PDO::PARAM_STR); // NOTE for 0 players NS2 reports nan (lua) -> null (json) -> NULL (php) -> -2147483648.0 (sql)
        $stmt->bindValue(':killsTeam1',           $data['killsTeam1'],        PDO::PARAM_INT);
        $stmt->bindValue(':killsTeam2',           $data['killsTeam2'],        PDO::PARAM_INT);
        $stmt->bindValue(':kills',                $data['kills'],             PDO::PARAM_INT);
        $stmt->bindValue(':numRTs1',              $data['numRTs1'],           PDO::PARAM_INT);
        $stmt->bindValue(':numRTs2',              $data['numRTs2'],           PDO::PARAM_INT);
        $stmt->bindValue(':numRTs',               $data['numRTs'],            PDO::PARAM_INT);
        $stmt->bindValue(':numHives',             $data['numHives'],          PDO::PARAM_INT);
        $stmt->bindValue(':numCCs',               $data['numCCs']  ,          PDO::PARAM_INT);
        $stmt->bindValue(':numTechPointsCaptured',$data['numTechPointsCaptured'], PDO::PARAM_INT);
        $stmt->bindValue(':biomassLevel',         $data['biomassLevel'],      PDO::PARAM_INT);

        $stmt->execute();
    }


    function insertNS2PlusData(& $db, & $data) {
        global $ns2plusStructure;

        // convert timestamps to human readable times
        $data['RoundInfo']['roundDate'] = gmdate('Y-m-d H:i:s', $data['RoundInfo']['roundDate']);

        // RoundInfo
        $insertStatement = buildInsertQueryStatement($ns2plusStructure, 'RoundInfo');
        $stmt = $db->prepare($insertStatement);
        $dt = $data['RoundInfo'];
        $stmt->bindValue(':roundDate',            $dt['roundDate'],              PDO::PARAM_STR);
        $stmt->bindValue(':roundLength',          $dt['roundLength'],            PDO::PARAM_STR);
        $stmt->bindValue(':winningTeam',          $dt['winningTeam'],            PDO::PARAM_INT);
        $stmt->bindValue(':maxPlayers1',          $dt['maxPlayers1'],            PDO::PARAM_INT);
        $stmt->bindValue(':maxPlayers2',          $dt['maxPlayers2'],            PDO::PARAM_INT);
        $stmt->bindValue(':mapName',              $dt['mapName'],                PDO::PARAM_STR);
        $stmt->bindValue(':startingLocation1',    $dt['startingLocations']['1'], PDO::PARAM_STR);
        $stmt->bindValue(':startingLocation2',    $dt['startingLocations']['2'], PDO::PARAM_STR);
        $stmt->bindValue(':locationNames',  json_encode($data['Locations']),     PDO::PARAM_STR);
        $stmt->bindValue(':tournamentMode',       $dt['tournamentMode'],         PDO::PARAM_BOOL);
        $stmt->bindValue(':minimapExtents', json_encode($dt['minimapExtents']),  PDO::PARAM_STR);
        $stmt->execute();

        // Get Round Number
        $query = 'SELECT MAX(roundId) as roundId FROM RoundInfo';
        $roundId = intval($db->query( $query, PDO::FETCH_ASSOC )->fetch()['roundId']);

        // ServerInfo
        $insertStatement = buildInsertQueryStatement($ns2plusStructure, 'ServerInfo');
        $stmt = $db->prepare($insertStatement);
        $dt = $data['ServerInfo'];
        $modIds = [];
        $modNames = [];
        foreach ( $dt['mods'] as $mod ) {
            $modIds[]   = $mod['modId'];
            $modNames[] = $mod['name'];
        }
        $stmt->bindValue(':roundId',              $roundId,              PDO::PARAM_INT);
        $stmt->bindValue(':serverId',   shortHash($_POST['serverId']),   PDO::PARAM_STR);
        $stmt->bindValue(':name',                 $dt['name'],           PDO::PARAM_STR);
        $stmt->bindValue(':ip',                   $dt['ip'],             PDO::PARAM_STR);
        $stmt->bindValue(':port',                 $dt['port'],           PDO::PARAM_INT);
        $stmt->bindValue(':slots',                $dt['slots'],          PDO::PARAM_INT);
        $stmt->bindValue(':modIds',   json_encode($modIds),              PDO::PARAM_STR);
        $stmt->bindValue(':modNames', json_encode($modNames),            PDO::PARAM_STR);
        $stmt->bindValue(':rookieOnly',           $dt['rookieOnly'],     PDO::PARAM_BOOL);
        $stmt->bindValue(':buildNumber',          $dt['buildNumber'],    PDO::PARAM_INT);
        $stmt->execute();

        // Research
        $insertStatement = buildInsertQueryStatement($ns2plusStructure, 'Research');
        $stmt = $db->prepare($insertStatement);
        $stmt->bindValue(':roundId',              $roundId,          PDO::PARAM_INT);
        foreach ($data['Research'] as $researchEvent) {
            $stmt->bindValue(':gameTime',         $researchEvent[0], PDO::PARAM_STR);
            $stmt->bindValue(':teamNumber',       $researchEvent[1], PDO::PARAM_INT);
            $stmt->bindValue(':researchId',       $researchEvent[2], PDO::PARAM_STR);
            $stmt->execute();
        }

        // Buildings
        $insertStatement = buildInsertQueryStatement($ns2plusStructure, 'Buildings');
        $stmt = $db->prepare($insertStatement);
        $stmt->bindValue(':roundId',              $roundId,          PDO::PARAM_INT);
        foreach ($data['Buildings'] as $buildingEvent) {
            $stmt->bindValue(':gameTime',         $buildingEvent[0], PDO::PARAM_STR);
            $stmt->bindValue(':teamNumber',       $buildingEvent[1], PDO::PARAM_INT);
            $stmt->bindValue(':techId',           $buildingEvent[2], PDO::PARAM_STR);
            $stmt->bindValue(':destroyed',        $buildingEvent[3], PDO::PARAM_BOOL);
            $stmt->bindValue(':built',            $buildingEvent[4], PDO::PARAM_BOOL);
            $stmt->bindValue(':recycled',         $buildingEvent[5], PDO::PARAM_BOOL);
            $stmt->execute();
        }

        // MarineCommStats
        $insertStatement = buildInsertQueryStatement($ns2plusStructure, 'MarineCommStats');
        $stmt = $db->prepare($insertStatement);
        $stmt->bindValue(':roundId',              $roundId,                           PDO::PARAM_INT);
        foreach ($data['MarineCommStats'] as $commander => $commStats) {
            $stmt->bindValue(':steamId',          $commander,                         PDO::PARAM_INT);
            $stmt->bindValue(':medpackPicks',     $commStats['medpack']['picks'],     PDO::PARAM_INT);
            $stmt->bindValue(':medpackMisses',    $commStats['medpack']['misses'],    PDO::PARAM_INT);
            $stmt->bindValue(':medpackHitsAcc',   $commStats['medpack']['hitsAcc'],   PDO::PARAM_INT);
            $stmt->bindValue(':medpackRefilled',  $commStats['medpack']['refilled'],  PDO::PARAM_INT);
            $stmt->bindValue(':ammopackPicks',    $commStats['ammopack']['picks'],    PDO::PARAM_INT);
            $stmt->bindValue(':ammopackMisses',   $commStats['ammopack']['misses'],   PDO::PARAM_INT);
            $stmt->bindValue(':ammopackRefilled', $commStats['ammopack']['refilled'], PDO::PARAM_STR);
            $stmt->bindValue(':catpackPicks',     $commStats['catpack']['picks'],     PDO::PARAM_INT);
            $stmt->bindValue(':catpackMisses',    $commStats['catpack']['misses'],    PDO::PARAM_INT);
            $stmt->execute();
        }

        // PlayerRoundStats
        $insertStatement  = buildInsertQueryStatement($ns2plusStructure, 'PlayerRoundStats');
        $stmt  = $db->prepare($insertStatement);
        $stmt->bindValue(':roundId',                 $roundId,                        PDO::PARAM_INT);
        foreach ($data['PlayerStats'] as $player => $pStats) {
            $stmt->bindValue( ':steamId',            $player,                         PDO::PARAM_INT);
            $stmt->bindValue( ':playerName',         $pStats['playerName'],           PDO::PARAM_STR);
            $stmt->bindValue( ':lastTeam',           $pStats['lastTeam'],             PDO::PARAM_INT);
            $stmt->bindValue( ':hiveSkill',          $pStats['hiveSkill'],            PDO::PARAM_INT);
            $stmt->bindValue( ':isRookie',           $pStats['isRookie'],             PDO::PARAM_BOOL);
            foreach (['1', '2'] as $t) {
                $stmt->bindValue(':teamNumber',      $t,                              PDO::PARAM_INT);
                $stmt->bindValue(':timePlayed',      $pStats[$t][0],                  PDO::PARAM_STR);
                $stmt->bindValue(':timeBuilding',    $pStats[$t][1],                  PDO::PARAM_STR);
                $stmt->bindValue(':commanderTime',   $pStats[$t][2],                  PDO::PARAM_STR);
                $stmt->bindValue(':kills',           $pStats[$t][3],                  PDO::PARAM_INT);
                $stmt->bindValue(':assists',         $pStats[$t][4],                  PDO::PARAM_INT);
                $stmt->bindValue(':deaths',          $pStats[$t][5],                  PDO::PARAM_INT);
                $stmt->bindValue(':killstreak',      $pStats[$t][6],                  PDO::PARAM_INT);
                $stmt->bindValue(':hits',            $pStats[$t][7],                  PDO::PARAM_INT);
                $stmt->bindValue(':onosHits',        $pStats[$t][8],                  PDO::PARAM_INT);
                $stmt->bindValue(':misses',          $pStats[$t][9],                  PDO::PARAM_INT);
                $stmt->bindValue(':playerDamage',    $pStats[$t][10],                 PDO::PARAM_STR);
                $stmt->bindValue(':structureDamage', $pStats[$t][11],                 PDO::PARAM_STR);
                $stmt->bindValue(':score',           $pStats[$t][12],                 PDO::PARAM_INT);
                if ($pStats[$t][0] != 0) { // timePlayed != 0
                    $stmt->execute();
                }
            }
        }

        // PlayerStats
        $insertStatement = 'INSERT OR IGNORE INTO PlayerStats (steamId) VALUES ( :steamId );';
        $updateStatement = 'UPDATE PlayerStats SET
            playerName = :playerName,
            hiveSkill = :hiveSkill,
            isRookie = :isRookie,
            roundsPlayed = roundsPlayed + 1,
            timePlayed1 = timePlayed1 + :timePlayed1,
            timePlayed2 = timePlayed2 + :timePlayed2,
            wins = wins + :wins,
            losses = losses + :losses,
            commanderWins = commanderWins + :commanderWins,
            commanderLosses = commanderLosses + :commanderLosses,
            lastSeen = :lastSeen
        WHERE steamId = :steamId;';
        $updateTeamStatement = 'UPDATE PlayerStats SET
            timePlayed = timePlayed + :timePlayed,
            timeBuilding = timeBuilding + :timeBuilding,
            commanderTime = commanderTime + :commanderTime,
            kills = kills + :kills,
            deaths = deaths + :deaths,
            assists = assists + :assists,
            killstreak = MAX(killstreak, :killstreak),
            hits = hits + :hits,
            onosHits = onosHits + :onosHits,
            misses = misses + :misses,
            playerDamage = playerDamage + :playerDamage,
            structureDamage = structureDamage + :structureDamage,
            score = score + :score
        WHERE steamId = :steamId;';
        $stmt1 = $db->prepare($insertStatement);
        $stmt2 = $db->prepare($updateStatement);
        $stmt3 = $db->prepare($updateTeamStatement);
        $commanderTimes = array('1' => [-1 => -1], '2' => [-1 => -1]); //max wont work with empty arrays
        foreach ($data['PlayerStats'] as $player => $pStats) {
            foreach (['1', '2'] as $t) {
              $commanderTimes[$t][$player] = $pStats[$t][2];
            }
        }
        $marineComm = array_search(max($commanderTimes['1']), $commanderTimes['1']);
        $alienComm  = array_search(max($commanderTimes['2']), $commanderTimes['2']);
        $winningTeam = $data['RoundInfo']['winningTeam'];
        foreach ($data['PlayerStats'] as $player => $pStats) {
            $pLastTeam = $pStats['lastTeam'];
            $isWinner = $winningTeam > 0 && $winningTeam == $pLastTeam;
            $isLoser  = $winningTeam > 0 && ($pLastTeam == 1 || $pLastTeam == 2) && $winningTeam != $pLastTeam;
            $isWinnerComm = ($player == $marineComm && $winningTeam == 1) || ($player == $alienComm && $winningTeam == 2);
            $isLoserComm  = ($player == $marineComm && $winningTeam == 2) || ($player == $alienComm && $winningTeam == 1); //TODO loser is initial Comm not longest Comm
            $stmt1->bindValue(':steamId',             $player,                         PDO::PARAM_INT);
            $stmt2->bindValue(':steamId',             $player,                         PDO::PARAM_INT);
            $stmt3->bindValue(':steamId',             $player,                         PDO::PARAM_INT);
            $stmt2->bindValue(':playerName',          $pStats['playerName'],           PDO::PARAM_STR);
            $stmt2->bindValue(':hiveSkill',           $pStats['hiveSkill'],            PDO::PARAM_INT);
            $stmt2->bindValue(':isRookie',            $pStats['isRookie'],             PDO::PARAM_BOOL);
            $stmt2->bindValue(':wins',                $isWinner ? 1 : 0,               PDO::PARAM_INT);
            $stmt2->bindValue(':losses',              $isLoser  ? 1 : 0,               PDO::PARAM_INT);
            $stmt2->bindValue(':commanderWins',       $isWinnerComm ? 1 : 0,           PDO::PARAM_INT);
            $stmt2->bindValue(':commanderLosses',     $isLoserComm  ? 1 : 0,           PDO::PARAM_INT);
            $stmt2->bindValue(':lastSeen',            $data['RoundInfo']['roundDate'], PDO::PARAM_STR);
            $stmt1->execute();
            foreach (['1', '2'] as $t) {
                $stmt2->bindValue(":timePlayed$t",    $pStats[$t][0],                  PDO::PARAM_STR);
                $stmt3->bindValue(':timePlayed',      $pStats[$t][0],                  PDO::PARAM_STR);
                $stmt3->bindValue(':timeBuilding',    $pStats[$t][1],                  PDO::PARAM_STR);
                $stmt3->bindValue(':commanderTime',   $pStats[$t][2],                  PDO::PARAM_STR);
                $stmt3->bindValue(':kills',           $pStats[$t][3],                  PDO::PARAM_INT);
                $stmt3->bindValue(':assists',         $pStats[$t][4],                  PDO::PARAM_INT);
                $stmt3->bindValue(':deaths',          $pStats[$t][5],                  PDO::PARAM_INT);
                $stmt3->bindValue(':killstreak',      $pStats[$t][6],                  PDO::PARAM_INT);
                $stmt3->bindValue(':hits',            $pStats[$t][7],                  PDO::PARAM_INT);
                $stmt3->bindValue(':onosHits',        $pStats[$t][8],                  PDO::PARAM_INT);
                $stmt3->bindValue(':misses',          $pStats[$t][9],                  PDO::PARAM_INT);
                $stmt3->bindValue(':playerDamage',    $pStats[$t][10],                 PDO::PARAM_STR);
                $stmt3->bindValue(':structureDamage', $pStats[$t][11],                 PDO::PARAM_STR);
                $stmt3->bindValue(':score',           $pStats[$t][12],                 PDO::PARAM_INT);
                $stmt3->execute();
            }
            $stmt2->execute();
        }

        // PlayerWeaponStats
        $insertStatement = buildInsertQueryStatement($ns2plusStructure, 'PlayerWeaponStats');
        $stmt = $db->prepare($insertStatement);
        $stmt->bindValue(':roundId',                 $roundId,                   PDO::PARAM_INT);
        foreach ($data['PlayerStats'] as $player => $pStats) {
            $stmt->bindValue(':steamId',             $player,                    PDO::PARAM_INT);
            foreach ($pStats['weapons'] as $weapon => $wStats) {
                $stmt->bindValue(':weapon',          $weapon,                    PDO::PARAM_STR);
                $stmt->bindValue(':teamNumber',      $wStats[0],                 PDO::PARAM_INT);
                $stmt->bindValue(':hits',            $wStats[1],                 PDO::PARAM_INT);
                $stmt->bindValue(':onosHits',        $wStats[2],                 PDO::PARAM_INT);
                $stmt->bindValue(':misses',          $wStats[3],                 PDO::PARAM_INT);
                $stmt->bindValue(':playerDamage',    $wStats[4],                 PDO::PARAM_STR);
                $stmt->bindValue(':structureDamage', $wStats[5],                 PDO::PARAM_STR);
                $stmt->bindValue(':kills',           $wStats[6],                 PDO::PARAM_INT);
                $stmt->execute();
            }
        }

        // PlayerClassStats
        $insertStatement = buildInsertQueryStatement($ns2plusStructure, 'PlayerClassStats');
        $stmt = $db->prepare($insertStatement);
        $stmt->bindValue(':roundId',                 $roundId,                   PDO::PARAM_INT);
        foreach ($data['PlayerStats'] as $player => $pStats) {
            $stmt->bindValue(':steamId',             $player,                    PDO::PARAM_INT);
            foreach ($pStats['status'] as $cStats) {
                $stmt->bindValue(':class',           $cStats[0],                 PDO::PARAM_STR);
                $stmt->bindValue(':classTime',       $cStats[1],                 PDO::PARAM_STR);
                $stmt->execute();
            }
        }

        // KillFeed
        if ( !array_key_exists( 'KillFeed', $data ) ) return; // KillFeed is optional
        $insertStatement = buildInsertQueryStatement($ns2plusStructure, 'KillFeed');
        $stmt = $db->prepare($insertStatement);
        $stmt->bindValue(':roundId',                      $roundId,              PDO::PARAM_INT);
        foreach ($data['KillFeed'] as $killEvent) {
            $stmt->bindValue(':gameTime',                 $killEvent[0],         PDO::PARAM_STR);
            $stmt->bindValue(':victimClass',              $killEvent[1],         PDO::PARAM_STR);
            $stmt->bindValue(':victimSteamId',            $killEvent[2],         PDO::PARAM_INT);
            $victimLocation = isset($killEvent[3]) ? $data['Locations'][intval($killEvent[3])-1] : null;
            $stmt->bindValue(':victimLocation',           $victimLocation,       PDO::PARAM_STR);
            $stmt->bindValue(':victimPosition',           $killEvent[4],         PDO::PARAM_STR);
            $stmt->bindValue(':killerWeapon',             $killEvent[5],         PDO::PARAM_STR);
            $stmt->bindValue(':killerTeamNumber',         $killEvent[6],         PDO::PARAM_INT);
            $stmt->bindValue(':killerClass',        isset($killEvent[7]) ? $killEvent[7] : null,   PDO::PARAM_STR);
            $stmt->bindValue(':killerSteamId',      isset($killEvent[8]) ? $killEvent[8] : null,   PDO::PARAM_INT);
            $killerLocation = isset($killEvent[9]) ? $data['Locations'][intval($killEvent[9])-1] : null;
            $stmt->bindValue(':killerLocation',           $killerLocation,       PDO::PARAM_STR);
            $stmt->bindValue(':killerPosition',     isset($killEvent[10]) ? $killEvent[10] : null, PDO::PARAM_STR);

            $doerLocation = isset($killEvent[11]) ? $data['Locations'][intval($killEvent[11])-1] : null;
            $stmt->bindValue(':doerLocation',             $doerLocation,         PDO::PARAM_STR);
            $stmt->bindValue(':doerPosition',       isset($killEvent[12]) ? $killEvent[12] : null, PDO::PARAM_STR);

            $stmt->execute();
        }
    }


    function createTables(& $db, $dbStructure) {
        $execStatement = '';
        foreach ( $dbStructure as $tableName => $tableContent ) {
            $execStatement .= 'CREATE TABLE IF NOT EXISTS ' . $tableName . ' (';
            end($tableContent); $lastKey = key($tableContent);
            foreach ( $tableContent as $fieldName => $fieldType ) {
                $execStatement .= "\n\t" . $fieldName . ' ' . $fieldType;
                if ( $fieldName != $lastKey ) $execStatement .= ',';
            }
            $execStatement .= ");\n";
        }
        $db->exec($execStatement);
    }


    function buildInsertQueryStatement($dbStructure, $table) {
        // Prepare INSERT statement
        $insertStatement = "INSERT INTO $table (";
        end($dbStructure[$table]); $lastKey = key($dbStructure[$table]);
        foreach ( $dbStructure[$table] as $fieldName => $fieldType ) {
            if ($fieldType != 'INTEGER PRIMARY KEY') { // primary keys don't need values
                $insertStatement .= $fieldName;
                if ( $fieldName != $lastKey )
                    $insertStatement .= ',';
            }
        }
        $insertStatement .= ') VALUES (';
        foreach ( $dbStructure[$table] as $fieldName => $fieldType ) {
            if ($fieldType != 'INTEGER PRIMARY KEY') { // primary keys don't need values
                $insertStatement .= ':' . $fieldName;
                if ( $fieldName != $lastKey )
                    $insertStatement .= ',';
            }
        }
        $insertStatement .= ')';
        return $insertStatement;
    }


    function showServerIds() {
        global $serverIdWhiteList;
        foreach ( $serverIdWhiteList as $serverId ) {
            echo "$serverId => " . shortHash($serverId) . " <br>\n";
        }
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
        global $wonitorDb, $wonitorStructure;
        global $ns2plusDb, $ns2plusStructure;
        readData();
        checkWhitelist();
        checkData();
        try {
            if ( $_POST['messageType'] == 'MatchEnd' ) {
                $db = openDB( $wonitorDb );
                createTables( $db, $wonitorStructure );
                insertRoundData( $db, $data );
                closeDB( $db );
                echo "MatchEnd post successful\n";
            }
            if ( $_POST['messageType'] == 'NS2PlusStats' ) {
                $db = openDB( $ns2plusDb );
                createTables( $db, $ns2plusStructure );
                insertNS2PlusData( $db, $data );
                closeDB( $db );
                echo "NS2PlusStats post successful\n";
            }
        }
        catch(PDOException $e) {
            echo $e->getMessage();
        }
    }

    main();

    // kate: syntax PHP; word-wrap off; replace-tabs on; indent-width 4; tab-width 4; indent-mode cstyle; show-tabs on;
?>
