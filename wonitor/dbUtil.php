<?php
    /* Database model/location
     * i.e. $roundsDb = ['sqlite:./data/rounds.sqlite3'];
     * or   $roundsDb = ['mysql:host=localhost;dbname=rounds', 'username', 'password'];
     * ./data is created if it does not exist
     */
    $roundsDb  = ['sqlite:./data/rounds.sqlite3'];
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
?>
