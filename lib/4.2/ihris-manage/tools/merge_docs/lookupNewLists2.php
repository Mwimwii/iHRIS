<?php

require_once 'MDB2.php';

$list = fopen ( $argv[1], "r" );

$lookups = array();
while ( $data = fgetcsv( $list ) ) {
    if ( !array_key_exists( $data[0], $lookups ) ) {
        $lookups[ $data[0] ] = array();
    }
    if ( !array_key_exists( $data[1], $lookups[ $data[0] ] ) ) {
        $lookups[ $data[0] ][ $data[1] ] = array();
    }
    $lookups[ $data[0] ][ $data[1] ][] = $data[2];
}

$db = MDB2::singleton( "mysql://USER:PASS@localhost/ihs_mlp" );
$db->loadModule( 'Extended' );
$db->setFetchMode( MDB2_FETCHMODE_ASSOC );

$fields = array( 
        'department' => array( 'name' ),
        'facility' => array( 'name', 'location', 'facility_type' ),
        'facility_contact' => array( 'parent', 'address', 'telephone', 'email', 'notes' ),
        'grade' => array( 'name' ),
        'job' => array( 'title', 'salary_grade' ),
        'qualification_type' => array( 'name' ),
        'id_type' => array( 'name' ),
        'facility_type' => array( 'name' ),
        'ward' => array( 'name' ),
        'language' => array( 'name' ),
        'district' => array( 'name', 'region' ),
        'region' => array( 'name', 'country' ),
        );

$alldata = array();

$headercount = 0;
$headers = array( 'Database' => $headercount++, 'Form' => $headercount++ );

foreach( $lookups as $dbname => $forms ) {
    $alldata[$dbname] = array();
    foreach( $forms as $form => $ids ) {
        $alldata[$dbname][$form] = array();
        if ( count( $ids ) > 0 ) {
            $idlist = "'$form|" . implode( "','$form|", $ids ) . "'";
            $qry = "SELECT id," . implode(',', $fields[$form] ) . " FROM $dbname.hippo_$form WHERE id IN ( $idlist )";
            $origqry = $db->prepare( "SELECT id," . implode(',', $fields[$form] ) . " FROM Botswana_Manage_41.hippo_$form WHERE id = ?");
            $results = $db->queryAll( $qry, null, MDB2_FETCHMODE_ASSOC );
            foreach( $results as $info ) {
                $addinfo = $info;
                foreach( $info as $field => $data ) {
                    if ( !array_key_exists( "$field", $headers ) ) {
                        $headers["$field"] = $headercount++;
                    }
                }
                $res = $origqry->execute( array( $info['id'] ) );
                while ( $data = $res->fetchRow() ) {
                    foreach( $data as $field => $value ) {
                        $addinfo["orig_$field"] = $value;
                        if ( !array_key_exists( "orig_$field", $headers ) ) {
                            $headers["orig_$field"] = $headercount++;
                        }
                    }
                }
                $alldata[$dbname][$form][ $info['id'] ] = $addinfo;
            }
        } else {
            echo "No ids found for $form\n";
        }

    }
}


$out = fopen( "MergedAdditionsOrConflicts2.csv", "w" );
$hout = array_flip( $headers );
ksort( $hout );
fputcsv( $out, $hout );
foreach( $alldata as $dbname => $dbdata ) {
    foreach( $dbdata as $form => $formdata ) {
        foreach( $formdata as $id => $fields ) {
            $final_row = array_pad( array(), count($headers), "" );
            $final_row[ $headers['Database'] ] = $dbname;
            $final_row[ $headers['Form'] ] = $form;
            foreach( $fields as $field => $val ) {
                $final_row[ $headers[$field] ] = $val;
            }
            fputcsv( $out, $final_row );
        }
    }
}
fclose( $out );

?>
