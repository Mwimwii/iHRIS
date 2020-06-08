<?php

require_once 'MDB2.php';


if ( !array_key_exists( 1, $argv ) ) {
    die( "Syntax: $argv[0] DATABASE\n" );
}
$dsn = "mysql://USER:PASS@localhost/$argv[1]";

if ( ($fh = fopen( $argv[1] . ".csv", "r" ) ) === false ) {
    die ( "Unable to open file: " .$argv[1] . ".csv\n" );
}

$firstpass = array();
$secondpass = array();
$deletes = array();
$count = 1;

while( $data = fgetcsv( $fh ) ) {
    $firstpass[$data[0] . "|" . $data[1]] = $data[0] . "|tmp_" . $count;
    $secondpass[$data[0] . "|tmp_" . $count] = $data[0] . "|" . $data[2];
    if ( !array_key_exists( $data[0], $deletes ) ) {
        $deletes[$data[0]] = array();
    }
    $deletes[$data[0]][] = $data[1];
    $count++;
}

$db = MDB2::singleton( $dsn );
if ( PEAR::isError( $db ) ) {
    die( "Unable to connect to database.  Please check the DSN.\n" );
}

$db->loadModule('Extended');
$db->setFetchMode( MDB2_FETCHMODE_OBJECT, 'MDB2_row' );
$db->query("SET NAMES 'utf8'" );

$fix_entry = $db->prepare( "UPDATE entry SET string_value = ? WHERE string_value = ?", array( 'text', 'text' ), MDB2_PREPARE_MANIP );
if ( PEAR::isError( $fix_entry ) ) {
    die("Failed to prepare entry update\n");
}
$fix_last_entry = $db->prepare( "UPDATE last_entry SET string_value = ? WHERE string_value = ?", array( 'text', 'text' ), MDB2_PREPARE_MANIP );
if ( PEAR::isError( $fix_last_entry ) ) {
    die("Failed to prepare last_entry update\n");
}
$fix_config_alt = $db->prepare( "UPDATE config_alt SET value = ? WHERE value = ?", array( 'text', 'text' ), MDB2_PREPARE_MANIP );
if ( PEAR::isError( $fix_config_alt ) ) {
    die("Failed to prepare config_alt update\n");
}

$del_config_alt = $db->prepare( "DELETE FROM config_alt WHERE (parent = ? AND name = ?) OR parent = ? OR parent LIKE ?" );
if ( PEAR::isError( $del_config_alt ) ) {
    die("Failed to prepare config_alt delete\n");
}

$db->beginTransaction();
foreach( $firstpass as $old => $new ) {
    if ( PEAR::isError( $fix_entry->execute( array( $new, $old ) ) ) ) {
        $db->rollback();
        die( "Failed to update entry for $old to $new\n" );
    }
    if ( PEAR::isError( $fix_last_entry->execute( array( $new, $old ) ) ) ) {
        $db->rollback();
        die( "Failed to update last_entry for $old to $new\n" );
    }
    if ( PEAR::isError( $fix_config_alt->execute( array( $new, $old ) ) ) ) {
        $db->rollback();
        die( "Failed to update config_alt for $old to $new\n" );
    }
}
foreach( $secondpass as $old => $new ) {
    if ( PEAR::isError( $fix_entry->execute( array( $new, $old ) ) ) ) {
        $db->rollback();
        die( "Failed to update entry for $old to $new\n" );
    }
    if ( PEAR::isError( $fix_last_entry->execute( array( $new, $old ) ) ) ) {
        $db->rollback();
        die( "Failed to update last_entry for $old to $new\n" );
    }
    if ( PEAR::isError( $fix_config_alt->execute( array( $new, $old ) ) ) ) {
        $db->rollback();
        die( "Failed to update config_alt for $old to $new\n" );
    }
}
foreach ( $deletes as $form => $list ) {
    foreach( $list as $id ) {
        if ( PEAR::isError( $del_config_alt->execute( array( "/I2CE/formsData/forms/$form", $id, "/I2CE/formsData/forms/$form/$id", "/I2CE/formsData/forms/$form/$id/%" ) ) ) ) {
            $db->rollback();
            die( "Failed to delete config_alt for $form $id\n" );
        }
    }
}

$db->commit();



?>
