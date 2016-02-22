<?php
    date_default_timezone_set( 'UTC' );
    require_once 'dbUtil.php';

    // uncomment the next block to enable logging
    // logs all incoming requests to $logFile
    /*
    if (!file_exists('./data')) { mkdir('./data', 0755, true); }
    if (!file_exists('./data')) { exit('Error: unable to create data directory'); }
    $logFile = './data/logfile.log';
    $isodate = date( 'c', time() );
    file_put_contents( $logFile, $isodate . ' ', FILE_APPEND | LOCK_EX ) or die( 'Error: Unable to write file' );
    file_put_contents( $logFile, 'POST ' . json_encode( $_POST ) . ' ', FILE_APPEND | LOCK_EX ) or die( 'Error: Unable to write file' );
    file_put_contents( $logFile, 'GET ' . json_encode( $_GET ) . "\n", FILE_APPEND | LOCK_EX ) or die( 'Error: Unable to write file' );
    */

    // simple statistics functions that are applied when key is added to the requested data column
    $statsMethodDefs = array(
        '_avg' => 'AVG',
        '_sum' => 'SUM',
        '_cnt' => 'COUNT',
        );

    // constraints for the sql query (WHERE )
    $constraintTypes = array(
        '_is' => '=',
        '_ne' => '!=',
        '_lt' => '<',
        '_le' => '<=',
        '_gt' => '>',
        '_ge' => '>=',
        );

    // options for the sql query (ODER BY)
    $orderOptions = array(
        'asc' => 'ASC',
        'desc' => 'DESC',
        );

    $specialTables = array(
       'NamedKillFeed' => 'SELECT temp.*, Playerstats.playerName as [killerName] FROM (
          SELECT KillFeed.*, Playerstats.playerName as [victimName] FROM KillFeed
          LEFT JOIN PlayerStats ON KillFeed.victimSteamId = PlayerStats.steamId) AS temp
          LEFT JOIN PlayerStats ON temp.killerSteamId = PlayerStats.steamId'
        );

    $ns2plusStructure['NamedKillFeed'] = $ns2plusStructure['KillFeed'];
    $ns2plusStructure['NamedKillFeed']['victimName'] = 'TEXT';
    $ns2plusStructure['NamedKillFeed']['killerName'] = 'TEXT';


    function queryDB( & $db, $structure, $table ) {
        global $statsMethodDefs, $constraintTypes, $orderOptions, $specialTables;
        global $wonitorStructure, $ns2plusStructure;

        $isWonitor = $structure == $wonitorStructure;

        // select data
        $dataFields = array();
        $dataRequests = ['all'];
        if ( isset( $_GET['data'] ) ) {
            $dataRequests = explode( ',', $_GET['data']);
        }

        foreach( $dataRequests as $value ) {
            if ( $value == '' ) {
                continue;
            }
            elseif ( $value == 'all' ) {
                $dataFields[] = '*';
            }
            elseif ( $value == 'count' ) {
                $dataFields[] = 'COUNT(1) AS count';
            }
            elseif ( $isWonitor && $value == 'numRounds' ) {
                $dataFields[] = 'COUNT(1) AS numRounds';
            }
            elseif ( $isWonitor && $value == 'team1Wins' ) {
                $dataFields[] = 'SUM( CASE WHEN winner=1 THEN 1 ELSE 0 END ) AS team1Wins';
            }
            elseif ( $isWonitor && $value == 'team2Wins' ) {
                $dataFields[] = 'SUM( CASE WHEN winner=2 THEN 1 ELSE 0 END ) AS team2Wins';
            }
            elseif ( $isWonitor && $value == 'draws' ) {
                $dataFields[] = 'SUM( CASE WHEN winner=0 THEN 1 ELSE 0 END ) AS draws';
            }
            elseif ( $isWonitor && $value == 'teamWins' ) {
                $dataFields[] = 'SUM( CASE WHEN winner=1 THEN 1 ELSE 0 END ) AS team1Wins, SUM( CASE WHEN winner=2 THEN 1 ELSE 0 END ) AS team2Wins, SUM( CASE WHEN winner=0 THEN 1 ELSE 0 END ) AS draws';
            }
            elseif ( $isWonitor && $value == 'startLocations' ) {
                $dataFields[] = 'startLocation1, startLocation2';
            }
            elseif ( $isWonitor && $value == 'serverInfo' ) {
                $dataFields[] = 'serverName, serverIp, serverPort, serverId';
            }
            elseif ( isset( $structure[$table][$value] ) ) {
                /* i.e. data=map */
                $dataFields[] = $value;
            }
            else {
                /* i.e. data=length_sum, data=winner_avg, data=numPlayers_cnt */
                $dataField = substr( $value, 0, -4 );
                $dataStatsMethod = substr( $value, -4 );

                if ( !isset( $statsMethodDefs[$dataStatsMethod] ) ) exit(); // exit here to indicate sth is wrong
                if ( !isset( $structure[$table][$dataField] ) ) exit(); // exit here to indicate sth is wrong

                //              COUNT                               (  length      ) AS   length_cnt                                                 ,   length
                $dataFields[] = $statsMethodDefs[$dataStatsMethod].'('.$dataField.') AS '.$dataField.$dataStatsMethod . ($dataStatsMethod=='_cnt' ? ', '.$dataField : ''); // no injection here because we tested the fields earlier
            }
        }

        if ( !$dataFields ) {
            exit(); // data field is required
        }
        $data = implode( ', ', $dataFields );

        // grouping
        $groupBy = array();
        if ( isset( $_GET['group_by'] ) ) {
            $groups = explode( ',', $_GET['group_by']);

            foreach ( $groups as $index => $value ) {
                if ( $value == '' ) {
                    continue;
                }

                $group = explode( '_every_', $value );

                if ( !isset( $structure[$table][$group[0]] ) ) continue;

                if ( isset( $group[1] ) ) {
                    $binsize = (float) $group[1];
                    if ( $binsize == 0 ) $binsize = 1;
                    $data .= ', CAST(' . $group[0] . '/' . $binsize . ' AS INTEGER)*' . $binsize.' AS [group'.($index+1).']';  // no injection here because we tested the group earlier
                    //$data .= ', ROUND(' . $group[0] . '/' . $binsize . ')*' . $binsize.' AS [group'.($index==0 ? '' : $index+1 ).']';
                }
                else {
                    $data .= ', ' . $group[0] . ' AS [group'.($index + 1).']';
                }
                $groupBy[] = '[group'.($index + 1).']';
            }
        }

        // constraints
        $constraints = array();
        foreach( $_GET as $key => $value ) {
            if ( strlen($key ) < 3 ) continue;
            $constraintField  = substr($key, 0, -3 );
            $constraintType   = substr($key, -3 );
            if (($constraintField == 'map' || $constraintField == 'mapName') && strpos($value, '@official') !== false) {
                $officialMaps ='ns2_derelict,ns2_docking,ns2_kodiak,ns2_refinery,ns2_tram,ns2_biodome,ns2_descent,ns2_eclipse,ns2_mineshaft,ns2_summit,ns2_veil';
                $value = str_replace('@official', $officialMaps, $value);
            }
            $constraintValues = explode( ',', $value );

            /* i.e. map_is=..., length_gt=..., numPlayers_ge=... */
            if ( !isset( $constraintTypes[$constraintType] ) ) continue;
            if ( !isset( $structure[$table][$constraintField] ) ) continue;

            if ( $constraintType == '_is') {
              // IS constraints are chained with OR
              $subconstraint = array();
              foreach ($constraintValues as $index => $constraintValue) {
                  $subconstraint[] = $constraintField . ' ' . $constraintTypes[$constraintType] . ' :'.$key.($index+1);
              }
              if (count($subconstraint) == 1) {
                  $constraints[] = $subconstraint[0];
              }
              else {
                  $constraints[] = '( ' . implode( ' OR ', $subconstraint ) . ' )';
              }
            }
            else {
                foreach ($constraintValues as $index => $constraintValue) {
                    $constraints[] = $constraintField . ' ' . $constraintTypes[$constraintType] . ' :'.$key.($index+1);
                }
            }
        }

        // ordering
        $orderBy = array();
        if ( isset( $_GET['order_by'] ) ) {
            $orders = explode( ',', $_GET['order_by']);

            foreach ( $orders as $index => $value ) {
                $order = explode( '_', $value); // NOTE we can't have fieldnames with _ because auf this

                if ( !isset( $structure[$table][$order[0]] ) ) continue;

                $orderBy[] = $order[0] . (isset($order[1], $orderOptions[$order[1]]) ? ' ' . $orderOptions[$order[1]] : '');
            }
        }

        // build and prepare query
        $query = 'SELECT ' . $data;
        if ( isset( $specialTables[$table]) ) {
            $query .= ' FROM (' . $specialTables[$table] . ') AS ' . $table . ' '; // NOTE this is safe because we checked the table exists
        }
        else {
            $query .= ' FROM ' . $table; // NOTE this is safe because we checked the table exists
        }
        if ( $constraints ) {
            $query .= ' WHERE ' . implode( ' AND ', $constraints );
        }
        if ( $groupBy ) {
            $query .= ' GROUP BY ' . implode( ', ' , $groupBy );
        }
        if ( $orderBy ) {
            $query .= ' ORDER BY ' . implode( ', ', $orderBy );
        }

        if ( isset( $_GET['showQuery']) ) {
            echo $query . "<br /><br />\n";
        }

        $statement = $db->prepare( $query );

        // bind values
        foreach( $_GET as $key => $value ) { // NOTE same loop as above, possible optimization here
            if ( strlen($key ) < 3 ) continue;
            $constraintField  = substr($key, 0, -3 );
            $constraintType   = substr($key, -3 );
            if (($constraintField == 'map' || $constraintField == 'mapName') && strpos($value, '@official') !== false) {
                $officialMaps ='ns2_derelict,ns2_docking,ns2_kodiak,ns2_refinery,ns2_tram,ns2_biodome,ns2_descent,ns2_eclipse,ns2_mineshaft,ns2_summit,ns2_veil';
                $value = str_replace('@official', $officialMaps, $value);
            }
            $constraintValues = explode( ',', $value);

            if ( !isset( $constraintTypes[$constraintType] ) ) continue;
            if ( !isset( $structure[$table][$constraintField] ) ) continue;

            foreach ($constraintValues as $index => $constraintValue) {
                $statement->bindValue( ':'.$key.($index+1), $constraintValue ); // NOTE this is safe because we check the key above
            }
        }

        // query db
        $statement->setFetchMode( PDO::FETCH_ASSOC );
        $statement->execute();

        $result = [];
        $fetch = isset($_GET['fetch']) ? $_GET['fetch'] : 'all';
        switch ( $fetch ) {
            case "first":
                $result = $statement->fetch() || null;
                break;
            case "last":
                $result = $statement->fetchAll();
                $result = count($result)>1 ? $result[count($result)-1] : null;
                break;
            default:
            case "all":
                $result = $statement->fetchAll();
        }

        // print results
        echo json_encode( $result ) . "\n";
        //foreach( $result as $row ) {var_dump( $row );}
    }


    function main() {
        global $wonitorDb, $wonitorStructure;
        global $ns2plusDb, $ns2plusStructure;

        $table = 'rounds';
        if ( isset($_GET['table']) ) {
          $table = $_GET['table'];
        }

        $db = null;
        $structure = null;
        if ( isset($wonitorStructure[$table]) ) {
            $db = openDB( $wonitorDb );
            $structure = $wonitorStructure;
        }
        elseif( isset($ns2plusStructure[$table]) ) {
            $db = openDB( $ns2plusDb );
            $structure = $ns2plusStructure;
        }
        else {
            exit();
        }

        try {
            queryDB( $db, $structure, $table );
        }
        catch (PDOException $e) {
            echo $e->getMessage();
        }

        closeDB( $db );
    }

    main();

    // TODO SELECT time FROM rounds WHERE time > datetime('now', '-2 day');
    // timediff_gt=-2_day,-10_month , timediff_is
    // TODO map_is=@official
    // TODO make fieldnames and tables case insensitive
    // curl --request GET 'http://example.com/wonitor/query.php?data=length_avg&group_by=serverId&length_gt=500'
    // curl --request GET 'http://example.com/wonitor/query.php?data=teamWins&map_is=ns2_veil&group_by=serverId'
    // curl --request GET 'http://example.com/wonitor/query.php?data=winner&map_is=ns2_veil'
    // kate: syntax PHP; word-wrap off; replace-tabs on; indent-width 4; tab-width 4; indent-mode cstyle; show-tabs on;
?>
