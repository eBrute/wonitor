<?php
    date_default_timezone_set( 'UTC' );
    $isodate = date( 'c', time() );

    require_once 'dbUtil.php';

    // uncomment the next block to enable logging
    // logs all incoming requests to $logFile
    /*
    if (!file_exists('./data')) { mkdir('./data', 0755, true); }
    if (!file_exists('./data')) { exit('Error: unable to create data directory'); }
    $logFile = './data/logfile.log';
    file_put_contents( $logFile, $isodate . ' ', FILE_APPEND | LOCK_EX ) or die( 'Error: Unable to write file' );
    file_put_contents( $logFile, 'POST ' . json_encode( $_POST ) . ' ', FILE_APPEND | LOCK_EX ) or die( 'Error: Unable to write file' );
    file_put_contents( $logFile, 'GET ' . json_encode( $_GET ) . "\n", FILE_APPEND | LOCK_EX ) or die( 'Error: Unable to write file' );
    */

    // all fields listed here are query-able
    // changes in the sql structure should also enter here, value should not be null
    $fieldTypes = array(
        'id' => PDO::PARAM_INT,
        'serverName' => PDO::PARAM_STR,
        'serverIp' => PDO::PARAM_STR,
        'serverPort' => PDO::PARAM_INT,
        'serverId' => PDO::PARAM_STR,
        'version' => PDO::PARAM_INT,
        'modIds' => PDO::PARAM_STR,
        'time' => PDO::PARAM_STR,
        'map' => PDO::PARAM_STR,
        'winner' => PDO::PARAM_INT,
        'length' => PDO::PARAM_STR,
        'isTournamentMode' => PDO::PARAM_BOOL,
        'isRookieServer' => PDO::PARAM_BOOL,
        'startPathDistance' => PDO::PARAM_STR,
        'startHiveTech' => PDO::PARAM_STR,
        'startLocation1' => PDO::PARAM_STR,
        'startLocation2' => PDO::PARAM_STR,
        'numPlayers1' => PDO::PARAM_INT,
        'numPlayers2' => PDO::PARAM_INT,
        'numPlayersRR' => PDO::PARAM_INT,
        'numPlayersSpec' => PDO::PARAM_INT,
        'numPlayers' => PDO::PARAM_INT,
        'maxPlayers' => PDO::PARAM_INT,
        'numRookies1' => PDO::PARAM_INT,
        'numRookies2' => PDO::PARAM_INT,
        'numRookiesRR' => PDO::PARAM_INT,
        'numRookiesSpec' => PDO::PARAM_INT,
        'numRookies' => PDO::PARAM_INT,
        'skillTeam1' => PDO::PARAM_INT,
        'skillTeam2' => PDO::PARAM_INT,
        'averageSkill' => PDO::PARAM_STR,
        'killsTeam1' => PDO::PARAM_INT,
        'killsTeam2' => PDO::PARAM_INT,
        'kills' => PDO::PARAM_INT,
        'numRTs1' => PDO::PARAM_INT,
        'numRTs2' => PDO::PARAM_INT,
        'numRTs' => PDO::PARAM_INT,
        'numHives' => PDO::PARAM_INT,
        'numCCs' => PDO::PARAM_INT,
        'numTechPointsCaptured' => PDO::PARAM_INT,
        'biomassLevel' => PDO::PARAM_INT
        );

    // simple statistics functions that are applied when key is added to the requested data column
    $statsTypes = array(
        '_avg' => 'AVG',
        '_sum' => 'SUM',
        '_cnt' => 'COUNT',
        );

    // constraints for the sql query (WHERE )
    $constraintTypes = array(
        '_is' => '=',
        '_lt' => '<',
        '_le' => '<=',
        '_gt' => '>',
        '_ge' => '>='
        );


    function queryDB( & $db ) {
        global $fieldTypes, $statsTypes, $constraintTypes;

        // select data
        if ( !isset( $_GET['data'] ) ) exit(); // data field is required
        $dataFields = array();
        $dataRequests = explode( ',', $_GET['data']);

        foreach( $dataRequests as $value ) {
            if ( $value == '' ) {
                continue;
            }
            elseif ( $value == 'all' ) {
                $dataFields[] = '*';
            }
            elseif ( $value == 'team1Wins' ) {
                $dataFields[] = 'SUM( CASE WHEN winner=1 THEN 1 ELSE 0 END ) AS team1Wins';
            }
            elseif ( $value == 'team2Wins' ) {
                $dataFields[] = 'SUM( CASE WHEN winner=2 THEN 1 ELSE 0 END ) AS team2Wins';
            }
            elseif ( $value == 'draws' ) {
                $dataFields[] = 'SUM( CASE WHEN winner=0 THEN 1 ELSE 0 END ) AS draws';
            }
            elseif ( $value == 'teamWins' ) {
                $dataFields[] = 'SUM( CASE WHEN winner=1 THEN 1 ELSE 0 END ) AS team1Wins, SUM( CASE WHEN winner=2 THEN 1 ELSE 0 END ) AS team2Wins, SUM( CASE WHEN winner=0 THEN 1 ELSE 0 END ) AS draws';
            }
            elseif ( $value == 'numRounds' ) {
                $dataFields[] = 'COUNT(1) AS numRounds';
            }
            elseif ( $value == 'count' ) {
                $dataFields[] = 'COUNT(1) AS count';
            }
            elseif ( $value == 'startLocations' ) {
                $dataFields[] = 'startLocation1, startLocation2';
            }
            elseif ( $value == 'serverInfo' ) {
                $dataFields[] = 'serverName, serverIp, serverPort, serverId';
            }
            elseif ( isset( $fieldTypes[$value] ) ) {
                /* i.e. data=map */
                $dataFields[] = $value;
            }
            else {
                /* i.e. data=length_sum, data=winner_avg, data=numPlayers_cnt */
                $dataField = substr( $value, 0, -4 );
                $dataStats = substr( $value, -4 );

                if ( !isset( $statsTypes[$dataStats] ) ) exit(); // exit here to indicate sth is wrong
                if ( !isset( $fieldTypes[$dataField] ) ) exit(); // exit here to indicate sth is wrong

                //              COUNT                    (  length      ) AS   length_cnt                                     ,   length
                $dataFields[] = $statsTypes[$dataStats].'('.$dataField.') AS '.$dataField.$dataStats . ($dataStats=='_cnt' ? ', '.$dataField : ''); // no injection here because we tested the fields earlier
            }
        }

        if ( !$dataFields ) {
            exit(); // data field is required
        }
        $data = join( $dataFields, ', ' );

        // grouping
        $groupFields = array();
        if ( isset( $_GET['group_by'] ) ) {
            $groups = explode( ',', $_GET['group_by']);

            foreach ( $groups as $index => $value ) {
                if ( $value == '' ) {
                    continue;
                }

                $group = explode( '_every_', $value);

                if ( !isset( $fieldTypes[$group[0]] ) ) continue;

                if ( isset( $group[1] ) ) {
                    $binsize = (float) $group[1];
                    if ( $binsize == 0 ) $binsize = 1;
                    $data .= ', CAST(' . $group[0] . '/' . $binsize . ' AS INTEGER)*' . $binsize.' AS [group'.($index+1).']';  // no injection here because we tested the group earlier
                    //$data .= ', ROUND(' . $group[0] . '/' . $binsize . ')*' . $binsize.' AS [group'.($index==0 ? '' : $index+1 ).']';
                }
                else {
                    $data .= ', ' . $group[0] . ' AS [group'.($index+1).']';
                }
                $groupFields[] = '[group'.($index+1).']';
            }
        }
        $groupBy = join( $groupFields , ', ' );

        // constraints
        $constraints = [];
        foreach( $_GET as $key => $value ) {
            if ( strlen($key ) < 3 ) continue;
            $constraintField  = substr($key, 0, -3 );
            $constraintType = substr($key, -3 );

            /* i.e. map_is=..., length_gt=..., numPlayers_ge=... */
            if ( !isset( $constraintTypes[$constraintType] ) ) continue;
            if ( !isset( $fieldTypes[$constraintField] ) ) continue;

            $constraints[] = $constraintField . ' ' . $constraintTypes[$constraintType] . ' :'.$key;
        }
        // TODO SELECT time FROM rounds WHERE time > datetime('now', '-2 day');

        // build and prepare query
        $query = 'SELECT ' . $data;
        $query .= ' FROM rounds';
        if ( $constraints ) {
            $query .= ' WHERE ' . join( $constraints, ' AND ' );
        }
        if ( $groupBy ) {
            $query .= ' GROUP BY ' . $groupBy;
        }
        $statement = $db->prepare( $query );
        if ( isset( $_GET['showQuery']) ) {
            echo $statement->queryString . "\n";
        }

        // bind values
        foreach( $_GET as $key => $value ) { // NOTE same loop as above, possible optimization here
            if ( strlen($key ) < 3 ) continue;
            $constraintField  = substr($key, 0, -3 );
            $constraintType = substr($key, -3 );

            if ( !isset( $constraintTypes[$constraintType] ) ) continue;
            if ( !isset( $fieldTypes[$constraintField] ) ) continue;

            $statement->bindValue( ':'.$key, $value ); // NOTE this is safe because we check the key above
        }

        // query db
        $statement->setFetchMode( PDO::FETCH_ASSOC );
        $statement->execute();

        $result = $statement->fetchAll();

        // print results
        echo json_encode( $result ) . "\n";
        //foreach( $result as $row ) {var_dump( $row );}
    }


    function main() {
        global $roundsDb;
        $db = openDB( $roundsDb );
        try {
            queryDB( $db );
        }
        catch (PDOException $e) {
            echo $e->getMessage();
        }
        closeDB( $db );
    }

    main();

    // curl --request GET 'http://example.com/wonitor/query.php?data=length_avg&group_by=serverId&length_gt=500'
    // curl --request GET 'http://example.com/wonitor/query.php?data=teamWins&map_is=ns2_veil&group_by=serverId'
    // curl --request GET 'http://example.com/wonitor/query.php?data=winner&map_is=ns2_veil'
    // kate: syntax PHP; word-wrap off; replace-tabs on; indent-width 4; tab-width 4; indent-mode cstyle; show-tabs on;
?>
