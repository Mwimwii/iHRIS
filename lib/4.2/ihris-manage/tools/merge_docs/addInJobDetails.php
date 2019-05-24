<?php


require_once 'MDB2.php';

$list = fopen( $argv[1], "r" );

$db = MDB2::singleton( "mysql://USER:PASS@localhost/ihs_mlp" );
$db->loadModule( 'Extended' );
$db->setFetchMode( MDB2_FETCHMODE_ASSOC );

$databases = array( 'pmh_prod', 'scotbase_mar09', 'scot', 'tham', 'pc1', 'pc2', 'ihs_mlp', 'gaborone_main', 'gaborone_1', 'gaborone_2', 'francistown_1', 'francistown_2', 'nyangagwe' );
$stmt = array();
foreach( $databases as $dbname ) {
    $stmt[$dbname] = $db->prepare( "SELECT title,salary_grade FROM $dbname.hippo_job WHERE id = ?", array( 'text', 'text' ), array( 'text' ) );
    if ( PEAR::isError( $stmt[$dbname] ) ) {
        echo "For $dbname:\n";
        echo $stmt[$dbname]->getMessage() . "\n";
    }
}

$out = fopen( "FinalWithSG.csv", "w" );
while( $data = fgetcsv( $list ) ) {
    echo "Looking up $data[2]:\n";
    foreach( $stmt as $dbname => $st ) {
        $res = $st->execute( array( $data[2] ) );
        while( $results = $res->fetchRow() ) {
            if ( $results['salary_grade'] ) {
                if ( strtolower(trim($results['title'])) == strtolower(trim($data[0])) ) {
                    $data[] = $results['salary_grade'];
                    fputcsv( $out, $data );
                    continue 3;
                }
                $data[] = "$dbname:" . $results['title'] . ":" . $results['salary_grade'];
            }
        }
    }
    fputcsv( $out, $data );
}
?>
