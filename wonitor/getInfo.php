<?php
    date_default_timezone_set( 'UTC' );
    require_once 'dbUtil.php';

    function queryDB( & $db ) {
        // build and prepare query
        $query = 'SELECT serverId, serverName, serverIp, serverPort FROM rounds GROUP BY serverId ORDER BY serverName';
        $servers = $db->query( $query, PDO::FETCH_ASSOC )->fetchAll();

        $query = 'SELECT DISTINCT map FROM rounds';
        $result = $db->query( $query, PDO::FETCH_ASSOC )->fetchAll();
        $maps = array();
        foreach ( $result as $row ) {
            $maps[] = $row["map"];
        }

        $query = 'SELECT map, startLocation1 AS startLocation FROM rounds UNION SELECT map, startLocation2 AS startLocation FROM rounds ORDER BY map';
        $result = $db->query( $query, PDO::FETCH_ASSOC )->fetchAll();
        $startLocations = array();
        foreach ( $result as $row ) {
            $map = $row["map"];
            $start = $row["startLocation"];
            $startLocations[$map][] = $start;
            unset($map, $start);
        }

        $query = 'SELECT DISTINCT version FROM rounds ORDER BY version DESC';
        $result = $db->query( $query, PDO::FETCH_ASSOC )->fetchAll();
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


    function main() {
        global $wonitorDb;
        $db = openDB( $wonitorDb );
        try {
            queryDB( $db );
        }
        catch( PDOException $e ) {
            echo $e->getMessage();
        }
        closeDB( $db );
    }

    main();

    // kate: syntax PHP; word-wrap off; replace-tabs on; indent-width 4; tab-width 4; indent-mode cstyle; show-tabs on;
?>
