<?php

require_once "MDB2.php";


$args = getopt( "", array(
            "db:",
            "inc:",
            "min:",
            "map:",
            ) );

if ( !array_key_exists( 'db', $args ) || !array_key_exists( 'inc', $args ) ) {
    die( "Syntax: $argv[0] --db=DBNAME --inc=##### [--min=#####] --map=FORM,FORM]\n  The min is the minimum record ID to start with for incrementing.  The default is 48805.\nThe map is a list of forms that you want to also remap from the old to the new id in the entry/last_entry tables.  Default is 'position'.\n" );
}
if ( !array_key_exists( 'min', $args ) ) {
    $args['min'] = 48805;
}
if ( !array_key_exists( 'map', $args ) ) {
    $args['map'] = 'position';
}
$args['map'] = explode( ',', $args['map'] );

$dsn = "mysql://USER:PASS@localhost/" . $args['db'];


$db = MDB2::singleton( $dsn );
if ( PEAR::isError( $db ) ) {
    die( "Failed to connect to database." );
}
$db->loadModule( 'Extended' );


$db->beginTransaction();

$res = $db->query( "UPDATE record SET id = id + " . $args['inc'] . " WHERE id >= " . $args['min'] );
if ( PEAR::isError( $res ) ) {
    $db->rollback();
    var_dump($res);
    die( "Failed to update record ids." );
}
$res = $db->query( "UPDATE record SET parent_id = parent_id + " . $args['inc'] . " WHERE parent_id >= " . $args['min'] );
if ( PEAR::isError( $res ) ) {
    $db->rollback();
    die( "Failed to update record parent ids." );
}
$res = $db->query( "UPDATE entry SET record = record + " . $args['inc'] . " WHERE record >= " . $args['min'] );
if ( PEAR::isError( $res ) ) {
    $db->rollback();
    die( "Failed to update entry record ids." );
}
$res = $db->query( "UPDATE last_entry SET record = record + " . $args['inc'] . " WHERE record >= " . $args['min'] );
if ( PEAR::isError( $res ) ) {
    $db->rollback();
    die( "Failed to update last entry record ids." );
}
foreach( $args['map'] as $form ) {
    $res = $db->query( "UPDATE entry SET string_value = CONCAT('$form|',SUBSTR(string_value," 
            . (strlen($form)+2) .") + ". $args['inc'] . ") WHERE SUBSTR(string_value," .(strlen($form)+2) .") >= " . $args['min']);
    if ( PEAR::isError( $res ) ) {
        $db->rollback();
        die( "Failed to update entry mapped ids for $form." );
    }
    $res = $db->query( "UPDATE last_entry SET string_value = CONCAT('$form|',SUBSTR(string_value," 
            . (strlen($form)+2) .") + ". $args['inc'] . ") WHERE SUBSTR(string_value," .(strlen($form)+2) .") >= " . $args['min']);
    if ( PEAR::isError( $res ) ) {
        $db->rollback();
        die( "Failed to update last_entry mapped ids for $form." );
    }

}

$db->commit();


?>
