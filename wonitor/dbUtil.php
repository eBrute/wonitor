<?php
    /* Database model/location
     * i.e. $wonitorDb = ['sqlite:./data/rounds.sqlite3'];
     * or   $wonitorDb = ['mysql:host=localhost;dbname=rounds', 'username', 'password'];
     * ./data is created if it does not exist
     */
    $wonitorDb  = ['sqlite:./data/rounds.sqlite3'];
    $ns2plusDb = ['sqlite:./data/ns2plus.sqlite3'];

    function openDB($dbDef) {
        try{
          // Connect to database
          //$db = new PDO( $dbName );
          $db = (new ReflectionClass('PDO'))->newInstanceArgs($dbDef);
          // Set errormode to exceptions
          $db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        catch (PDOException $e) {
            die( 'Error: ' . $e->getMessage() . ".<br />\nProbably you need to install php5-sqlite." );
        }
        return $db;
    }

    function closeDB(& $db) {
        // Close file db connection
        try {
            $db = null;
        }
        catch (PDOException $e) {
            echo $e->getMessage();
        }
    }


    // all fields listed here are query-able
    // changes in the sql structure should also enter here, fieldnames should not include a _
    $wonitorStructure = array(
      'rounds' => array(
        'id' => 'INTEGER PRIMARY KEY',
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
        'biomassLevel' => 'INTEGER',
      ),
    );


    $ns2plusStructure = array(
        'RoundInfo' => array(
            'roundId' => 'INTEGER PRIMARY KEY',
            'roundDate' => 'TEXT',
            'roundLength' => 'REAL',
            'winningTeam' => 'INTEGER',
            'maxPlayers1' => 'INTEGER',
            'maxPlayers2' => 'INTEGER',
            'mapName' => 'TEXT',
            'startingLocation1' => 'TEXT',
            'startingLocation2' => 'TEXT',
            'locationNames' => 'TEXT',
            'tournamentMode' => 'INTEGER',
            'minimapExtents' => 'TEXT',
        ),
        'ServerInfo' => array(
            'roundId' => 'INTEGER',
            'serverId' => 'TEXT',
            'name' => 'TEXT',
            'ip' => 'TEXT',
            'port' => 'INTEGER',
            'slots' => 'INTEGER',
            'modIds' => 'TEXT',
            'modNames' => 'TEXT',
            'rookieOnly' => 'INTEGER',
            'buildNumber' => 'INTEGER',
        ),
        'Research' => array(
            'roundId' => 'INTEGER',
            'gameTime' => 'REAL',
            'teamNumber' => 'INTEGER',
            'researchId' => 'TEXT',
        ),
        'Buildings' => array(
            'roundId' => 'INTEGER',
            'gameTime' => 'REAL',
            'teamNumber' => 'INTEGER',
            'techId' => 'TEXT',
            'destroyed' => 'INTEGER',
            'built' => 'INTEGER',
            'recycled' => 'INTEGER',
        ),
        'MarineCommStats' => array(
            'roundId' => 'INTEGER',
            'steamId' => 'INTEGER',
            'medpackPicks' => 'INTEGER',
            'medpackMisses' => 'INTEGER',
            'medpackHitsAcc' => 'INTEGER',
            'medpackRefilled' => 'REAL',
            'ammopackPicks' => 'INTEGER',
            'ammopackMisses' => 'INTEGER',
            'ammopackRefilled' => 'INTEGER',
            'catpackPicks' => 'INTEGER',
            'catpackMisses' => 'INTEGER',
        ),
        'PlayerRoundStats' => array(
            'roundId' => 'INTEGER',
            'steamId' => 'INTEGER',
            'playerName' => 'TEXT',
            'lastTeam' => 'INTEGER',
            'hiveSkill' => 'INTEGER',
            'isRookie' => 'INTEGER',
            'teamNumber' => 'INTEGER',
            'timePlayed' => 'REAL',
            'timeBuilding' => 'REAL',
            'commanderTime' => 'REAL',
            'kills' => 'INTEGER',
            'assists' => 'INTEGER',
            'deaths' => 'INTEGER',
            'killstreak' => 'INTEGER',
            'hits' => 'INTEGER',
            'onosHits' => 'INTEGER',
            'misses' => 'INTEGER',
            'playerDamage' => 'REAL',
            'structureDamage' => 'REAL',
            'score' => 'INTEGER',
        ),
        'PlayerStats' => array(
            'steamId' => 'INTEGER PRIMARY KEY',
            'playerName' => 'TEXT',
            'hiveSkill' => 'INTEGER',
            'isRookie' => 'INTEGER',
            'timePlayed' => 'REAL DEFAULT 0',
            'roundsPlayed' => 'INTEGER DEFAULT 0',
            'timePlayed1' => 'REAL DEFAULT 0',
            'timePlayed2' => 'REAL DEFAULT 0',
            'timeBuilding' => 'REAL DEFAULT 0',
            'commanderTime' => 'REAL DEFAULT 0',
            'wins' =>  'INTEGER DEFAULT 0',
            'losses' =>  'INTEGER DEFAULT 0',
            'commanderWins' =>  'INTEGER DEFAULT 0',
            'commanderLosses' =>  'INTEGER DEFAULT 0',
            'kills' => 'INTEGER DEFAULT 0',
            'deaths' => 'INTEGER DEFAULT 0',
            'assists' => 'INTEGER DEFAULT 0',
            'killstreak' => 'INTEGER DEFAULT 0',
            'hits' => 'INTEGER DEFAULT 0',
            'onosHits' => 'INTEGER DEFAULT 0',
            'misses' => 'INTEGER DEFAULT 0',
            'playerDamage' => 'REAL DEFAULT 0',
            'structureDamage' => 'REAL DEFAULT 0',
            'score' => 'INTEGER DEFAULT 0',
            'lastSeen' => 'TEXT',
        ),
        'PlayerWeaponStats' => array(
            'roundId' => 'INTEGER',
            'steamId' => 'INTEGER',
            'weapon' => 'TEXT',
            'teamNumber' => 'INTEGER',
            'hits' => 'INTEGER',
            'onosHits' => 'INTEGER', //NOTE will be zero if teamNumber==2
            'misses' => 'INTEGER',
            'playerDamage' => 'REAL',
            'structureDamage' => 'REAL',
            'kills' => 'INTEGER',
        ),
        'PlayerClassStats' => array(
            'roundId' => 'INTEGER',
            'steamId' => 'INTEGER',
            'class' => 'TEXT',
            'classTime' => 'REAL',
        ),
        'KillFeed' => array(
            'roundId' => 'INTEGER',
            'gameTime' => 'REAL',
            'victimClass' => 'TEXT',
            'victimSteamId' => 'INTEGER',
            'victimLocation' => 'TEXT',
            'victimPosition' => 'TEXT',
            'killerWeapon' => 'TEXT',
            'killerTeamNumber' => 'INTEGER',
            'killerClass' => 'TEXT',
            'killerSteamId' => 'INTEGER',
            'killerLocation' => 'TEXT',
            'killerPosition' => 'TEXT',
        ),
    );
?>
