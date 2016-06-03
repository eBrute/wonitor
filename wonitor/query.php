<?php
    date_default_timezone_set('UTC');
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
        '_lk' => 'LIKE',
        '_nl' => 'NOT LIKE',
        '_mt' => 'GLOB',
        '_nm' => 'NOT GLOB',
        '_re' => 'REGEXP',
        '_nr' => 'NOT REGEXP',
        );

    // options for the sql query (ODER BY)
    $orderOptions = array(
        'asc' => 'ASC',
        'desc' => 'DESC',
        );

    $specialFields = array(
        'all' => '*',
        'count' => 'COUNT(1) AS count',
    );

    // empty all fields
    foreach ($wonitorStructure as &$table) {
        foreach ($table as &$field) {
            $field = '';
        }
    }
    foreach ($ns2plusStructure as &$table) {
        foreach ($table as &$field) {
            $field = '';
        }
    }

    $specialWonitorFields = array(
        'rounds' => array(
            'numRounds' => 'COUNT(1)',
            'team1Wins' => 'SUM( CASE WHEN winner=1 THEN 1 ELSE 0 END )',
            'team2Wins' => 'SUM( CASE WHEN winner=2 THEN 1 ELSE 0 END )',
            'draws' => 'SUM( CASE WHEN winner=0 THEN 1 ELSE 0 END )',
            'relTeam1Wins' => 'SUM( CASE WHEN winner=1 THEN 1 ELSE 0 END ) * 1. / COUNT(1)',
            'relTeam2Wins' => 'SUM( CASE WHEN winner=2 THEN 1 ELSE 0 END ) * 1. / COUNT(1)',
            'relDraws' => 'SUM( CASE WHEN winner=0 THEN 1 ELSE 0 END ) * 1. / COUNT(1)',
            'winDiff' => 'SUM( CASE WHEN winner=1 THEN 1 WHEN winner=2 THEN -1 ELSE 0 END )',
            'relWinDiff' => 'SUM( CASE WHEN winner=1 THEN 1 WHEN winner=2 THEN -1 ELSE 0 END ) * 1. / COUNT(1)',
            'skillDiff' => '(skillTeam1 - skillTeam2)',
        ),
    );
    $wonitorStructure = array_merge_recursive($wonitorStructure, $specialWonitorFields);

    $specialTables = array(
       'NamedKillFeed' => 'SELECT temp.*, Playerstats.playerName as [killerName] FROM (
            SELECT KillFeed.*, Playerstats.playerName as [victimName] FROM KillFeed
            LEFT JOIN PlayerStats ON KillFeed.victimSteamId = PlayerStats.steamId) AS temp
            LEFT JOIN PlayerStats ON temp.killerSteamId = PlayerStats.steamId',
        'ExtendedRoundInfo' => 'SELECT * from RoundInfo LEFT OUTER JOIN ServerInfo ON RoundInfo.roundId = ServerInfo.roundId',
    );

    $ns2plusStructure = array_merge_recursive($ns2plusStructure, array(
          'NamedKillFeed' => array_merge($ns2plusStructure['KillFeed'], ['victimName' => '', 'killerName' => '']),
          'ExtendedRoundInfo' => array_merge($ns2plusStructure['RoundInfo'], $ns2plusStructure['ServerInfo']),
      )
    );

    $shortNames = array(
        'teamWins' => 'team1Wins,team2Wins,draws',
        'relTeamWins' => 'relTeam1Wins,relTeam2Wins,relDraws',
        'startLocations' => 'startLocation1,startLocation2',
        'serverInfo' => 'serverName,serverIp,serverPort,serverId',
    );

    function isValidField($structure, $table, $dataField)
    {
        global $specialFields;
        global $wonitorStructure, $ns2plusStructure;

        $isWonitor = $structure == $wonitorStructure;

        if (isset($specialFields[$dataField])) {
            return true;
        }
        if (isset($structure[$table][$dataField])) {
            return true;
        }

        return false;
    }

    function getFieldQuery($structure, $table, $dataField)
    {
        global $specialFields;
        global $wonitorStructure, $ns2plusStructure;

        $isWonitor = $structure == $wonitorStructure;

        if (isset($specialFields[$dataField])) {
            return $specialFields[$dataField];
        }
        if (isset($structure[$table][$dataField])) {
            return $structure[$table][$dataField] != '' ? $structure[$table][$dataField] : $dataField;
        }

        return '';
    }

    function queryDB(&$db, $structure, $table)
    {
        global $statsMethodDefs, $constraintTypes, $orderOptions, $shortNames;
        global $specialTables, $specialFields;
        global $wonitorStructure, $ns2plusStructure;

        $isWonitor = $structure == $wonitorStructure;

        // select data
        $dataFields = array();
        $dataRequests = ['all'];

        if (isset($_GET['data'])) {
            $dataRequests = explode(',', strReplaceAssoc($shortNames, $_GET['data']));
        }

        foreach ($dataRequests as $value) {
            $dataField = $value;
            $dataFieldRename = '';

            /* i.e. data=length_sum, data=winner_avg, data=numPlayers_cnt */
            $dataStatsMethod = substr($value, -4);
            if (isset($statsMethodDefs[$dataStatsMethod])) {
                $dataField = substr($value, 0, -4);
            } else {
                $dataStatsMethod = '';
            }

            if ($dataField == '') {
                continue;
            } elseif (isset($specialFields[$dataField])) {
                $dataFields[] = $specialFields[$dataField];
                continue;
            } elseif (isValidField($structure, $table, $dataField)) {
                $dataFieldQuery = getFieldQuery($structure, $table, $dataField);
            } else {
                exit(); // exit here to indicate sth is wrong
            }

            if ($dataFieldQuery != $dataField) {
                $dataFieldRename = ' AS '.$dataField;
            }

            if ($dataStatsMethod == '') {
                $dataFields[] = $dataFieldQuery.$dataFieldRename;
            } else {
                //              COUNT                               (  length      ) AS   length_cnt                                                      ,   length
                $dataFields[] = $statsMethodDefs[$dataStatsMethod].'('.$dataFieldQuery.') AS '.$dataField.$dataStatsMethod.($dataStatsMethod == '_cnt' ? ', '.$dataFieldQuery.$dataFieldRename : ''); // no injection here because we tested the fields earlier
            }
        }

        if (!$dataFields) {
            exit(); // data field is required
        }
        $data = implode(', ', $dataFields);

        // grouping
        $groupBy = array();
        if (isset($_GET['group_by'])) {
            $groups = explode(',', $_GET['group_by']);

            foreach ($groups as $index => $value) {
                if ($value == '') {
                    continue;
                }

                $group = explode('_every_', $value);
                $groupField = $group[0];

                if (!isValidField($structure, $table, $groupField)) {
                    continue;
                }
                $groupFieldQuery = getFieldQuery($structure, $table, $groupField);

                if (isset($group[1])) {
                    $binsize = (float) $group[1];
                    if ($binsize == 0) {
                        $binsize = 1;
                    }
                    $data .= ', CAST('.$groupFieldQuery.'/'.$binsize.' AS INTEGER)*'.$binsize.' AS [group'.($index + 1).']';  // no injection here because we tested the group earlier
                    //$data .= ', ROUND(' . $groupField . '/' . $binsize . ')*' . $binsize.' AS [group'.($index==0 ? '' : $index+1 ).']';
                } else {
                    $data .= ', '.$groupFieldQuery.' AS [group'.($index + 1).']';
                }
                $groupBy[] = '[group'.($index + 1).']';
            }
        }

        // constraints
        $constraints = array();
        $bindings = array();
        foreach ($_GET as $key => $value) {
            if (strlen($key) < 3) {
                continue;
            }
            $constraintField = substr($key, 0, -3);
            $constraintType = substr($key, -3);
            if (($constraintField == 'map' || $constraintField == 'mapName') && strpos($value, '@official') !== false) {
                $officialMaps = 'ns2_biodome,ns2_caged,ns2_derelict,ns2_descent,ns2_docking,ns2_eclipse,ns2_kodiak,ns2_mineshaft,ns2_refinery,ns2_summit,ns2_tram,ns2_veil';
                $value = str_replace('@official', $officialMaps, $value);
            }
            $constraintValues = explode(',', $value);

            /* i.e. map_is=..., length_gt=..., numPlayers_ge=... */
            if (!isset($constraintTypes[$constraintType])) {
                continue;
            }
            if (!isValidField($structure, $table, $constraintField)) {
                continue;
            }
            $constraintFieldQuery = getFieldQuery($structure, $table, $constraintField);

            if ($constraintType == '_is') {
                // IS constraints are chained with OR
              $subconstraint = array();
                foreach ($constraintValues as $index => $constraintValue) {
                    //                 numPlayers               >=                                     :numPlayers_ge1
                  $subconstraint[] = $constraintFieldQuery.' '.$constraintTypes[$constraintType].' :'.$key.($index + 1);
                }

                if (count($subconstraint) == 1) {
                    // no ugly brackets in query for a single constraint
                  $constraints[] = $subconstraint[0];
                } else {
                    $constraints[] = '( '.implode(' OR ', $subconstraint).' )';
                }
            } else {
                foreach ($constraintValues as $index => $constraintValue) {
                    $constraints[] = $constraintFieldQuery.' '.$constraintTypes[$constraintType].' :'.$key.($index + 1);
                }
            }

            foreach ($constraintValues as $index => $constraintValue) {
                $bindings[] = array('key' => ':'.$key.($index + 1), 'value' => $constraintValue);
            }
        }

        // ordering
        $orderBy = array();
        if (isset($_GET['order_by'])) {
            $orders = explode(',', $_GET['order_by']);

            foreach ($orders as $index => $value) {
                $order = explode('_', $value); // NOTE we can't have fieldnames with _ because auf this
                $orderField = $order[0];
                $orderDirection = $order[1];

                if (!isValidField($structure, $table, $orderField)) {
                    continue;
                }
                $orderFieldQuery = getFieldQuery($structure, $table, $orderField);

                $orderBy[] = $orderField.(isset($orderDirection, $orderOptions[$orderDirection]) ? ' '.$orderOptions[$orderDirection] : '');
            }
        }

        // build and prepare query
        $query = 'SELECT '.$data;
        if (isset($specialTables[$table])) {
            $query .= ' FROM ('.$specialTables[$table].') AS '.$table.' '; // NOTE this is safe because we checked the table exists
        } else {
            $query .= ' FROM '.$table; // NOTE this is safe because we checked the table exists
        }
        if ($constraints) {
            $query .= ' WHERE '.implode(' AND ', $constraints);
        }
        if ($groupBy) {
            $query .= ' GROUP BY '.implode(', ', $groupBy);
        }
        if ($orderBy) {
            $query .= ' ORDER BY '.implode(', ', $orderBy);
        }

        if (isset($_GET['showQuery'])) {
            echo $query."<br /><br />\n";
        }

        $statement = $db->prepare($query);

        // bind values
        foreach ($bindings as $binding) {
            $statement->bindValue($binding['key'], $binding['value']); // NOTE this is safe because we check the key above
        }

        // query db
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        $statement->execute();

        $result = [];
        $fetch = isset($_GET['fetch']) ? $_GET['fetch'] : 'all';
        switch ($fetch) {
            case 'first':
                $result = $statement->fetch() || null;
                break;
            case 'last':
                $result = $statement->fetchAll();
                $result = count($result) > 1 ? $result[count($result) - 1] : null;
                break;
            default:
            case 'all':
                $result = $statement->fetchAll();
        }

        // print results
        echo json_encode($result)."\n";
        //foreach( $result as $row ) {var_dump( $row );}
    }

    function strReplaceAssoc(array $replace, $subject)
    {
        return str_replace(array_keys($replace), array_values($replace), $subject);
    }

    function main()
    {
        global $wonitorDb, $wonitorStructure;
        global $ns2plusDb, $ns2plusStructure;

        $table = 'rounds';
        if (isset($_GET['table'])) {
            $table = $_GET['table'];
        }

        $db = null;
        $structure = null;
        if (isset($wonitorStructure[$table])) {
            $db = openDB($wonitorDb);
            $structure = $wonitorStructure;
        } elseif (isset($ns2plusStructure[$table])) {
            $db = openDB($ns2plusDb);
            $structure = $ns2plusStructure;
        } else {
            exit();
        }

        try {
            queryDB($db, $structure, $table);
        } catch (PDOException $e) {
            echo $e->getMessage();
        }

        closeDB($db);
    }

    main();

    // TODO SELECT time FROM rounds WHERE time > datetime('now', '-2 day');
    // timediff_gt=-2_day,-10_month , timediff_is
    // TODO make fieldnames and tables case insensitive
    // curl --request GET 'http://example.com/wonitor/query.php?data=length_avg&group_by=serverId&length_gt=500'
    // curl --request GET 'http://example.com/wonitor/query.php?data=teamWins&map_is=ns2_veil&group_by=serverId'
    // curl --request GET 'http://example.com/wonitor/query.php?data=winner&map_is=ns2_veil'
    // kate: syntax PHP; word-wrap off; replace-tabs on; indent-width 4; tab-width 4; indent-mode cstyle; show-tabs on;
;
