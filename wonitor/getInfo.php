<?php
    date_default_timezone_set( 'UTC' );

    function openDB( & $file_db ) {
        // Create ( connect to ) SQLite database in file
        $file_db = new PDO( 'sqlite:./data/rounds.sqlite3' );
        // Set errormode to exceptions
        $file_db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
    }


    function queryDB( & $file_db ) {
        global $fieldTypes, $statsTypes, $constraintTypes;

        // build and prepare query
        $query = 'SELECT serverId, serverName, serverIp, serverPort FROM rounds GROUP BY serverId ORDER BY serverName';
        $servers = $file_db->query( $query, PDO::FETCH_ASSOC )->fetchAll();

        $query = 'SELECT DISTINCT map FROM rounds';
        $result = $file_db->query( $query, PDO::FETCH_ASSOC )->fetchAll();
        $maps = array();
        foreach ( $result as $row ) {
            $maps[] = $row["map"];
        }

        $query = 'SELECT map, startLocation1 AS startLocation FROM rounds UNION SELECT map, startLocation2 AS startLocation FROM rounds ORDER BY map';
        $result = $file_db->query( $query, PDO::FETCH_ASSOC )->fetchAll();
        $startLocations = array();
        foreach ( $result as $row ) {
            $map = $row["map"];
            $start = $row["startLocation"];
            $startLocations[$map][] = $start;
            unset($map, $start);
        }

        $query = 'SELECT DISTINCT version FROM rounds ORDER BY version DESC';
        $result = $file_db->query( $query, PDO::FETCH_ASSOC )->fetchAll();
        $versions = array();
        foreach ( $result as $row ) {
            $versions[] = $row["version"];
        }

        $response = array(
            'servers' => $servers,
            'maps' => $maps,
            'startLocations' => $startLocations,
            'versions' => $versions
        );
        echo json_encode( $response ) . "\n";
    }


    function closeDB( & $file_db ) {
        // Close file db connection
        $file_db = null;
    }


    function main() {
        $file_db = null;
        try {
            openDB( $file_db );
            queryDB( $file_db );
            closeDB( $file_db );
        }
        catch( PDOException $e ) {
            echo $e->getMessage();
        }
    }

    main();

    // kate: syntax PHP; word-wrap off; replace-tabs on; indent-width 4; tab-width 4; indent-mode cstyle; show-tabs on;
?>
