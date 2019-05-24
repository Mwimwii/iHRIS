<?php

require_once 'MDB2.php';

$dsn = "mysql://USER:PASS@localhost/DATABASE";

$db = MDB2::singleton( $dsn );
$db->loadModule('Extended');

$upd = $db->prepare( "UPDATE last_entry SET blob_value = ? WHERE record = ? AND form_field = 264", array( 'blob', 'integer' ), MDB2_PREPARE_MANIP );
if ( PEAR::isError( $upd ) ) {
    die( "Failed to prepared update statement! " . $upd->getMessage() . "\n" );
}
$res = $db->query("SELECT blob_value,record FROM last_entry WHERE form_field = 264" );
if ( PEAR::isError( $res ) ) {
    die( "Failed to get blob values for passport photos! " . $res->getMessage() . "\n" );
}
while ( $data = $res->fetchRow() ) {
    echo "Looking at record $data[1]\n";
    if( !$data[0] ) {
        continue;
    }
    $newdbval = resizePassportPhoto( $data[0] );
    if ( !$newdbval ) {
        continue;
    }
    echo "Updating $data[1]\n";
    $updres = $upd->execute( array($newdbval, $data[1]) );
    if ( PEAR::isError( $updres ) ) {
        echo $updres->getMessage();
        die( "Failed to update $data[1]\n" );
    }
}


$upd_e = $db->prepare( "UPDATE entry SET blob_value = ? WHERE record = ? AND form_field = 264 AND date = ?", array( 'blob', 'integer', 'timestamp' ), MDB2_PREPARE_MANIP );
if ( PEAR::isError( $upd_e ) ) {
    die( "Failed to prepared update statement! " . $upd_e->getMessage() . "\n" );
}
$res_e = $db->query("SELECT blob_value,record,date FROM entry WHERE form_field = 264" );
if ( PEAR::isError( $res_e ) ) {
    die( "Failed to get blob values for passport photos! " . $res_e->getMessage() . "\n" );
}
while ( $data = $res_e->fetchRow() ) {
    echo "Looking at record $data[1] $data[2]\n";
    if( !$data[0] ) {
        continue;
    }
    $newdbval = resizePassportPhoto( $data[0] );
    if ( !$newdbval ) {
        continue;
    }
    echo "Updating $data[1]\n";
    $updres = $upd_e->execute( array($newdbval, $data[1], $data[2]) );
    if ( PEAR::isError( $updres ) ) {
        echo $updres->getMessage();
        die( "Failed to update $data[1]\n" );
    }
}


function resizePassportPhoto( $value ) {
    $meta = array();
    while( strlen($value) >= 10 && $value[9] == '<' ) {
        $key = substr( $value, 0, 9 );
        if ( ( $pos = strpos( $value, '>' ) ) === false ) {
            die( "Failed to get meta data from image!\n" );
        }
        $meta[$key] = substr( $value, 10, $pos - 10 );
        $value = substr( $value, $pos + 1 );
    }
    $tmpfile = tempnam("/tmp", "ihris_passport_");
    file_put_contents( $tmpfile, $value );
    $imgdata = getimagesize( $tmpfile );
    if ( $imgdata[0] <= 320 && $imgdata[1] <= 240 ) {
        return null;
    }
    $img = imagecreatefromstring( $value );
    //$resized = imagescale( $img, 320, 240 );
    $resized = imagecreatetruecolor( 320, 240 );
    imagecopyresampled( $resized, $img, 0, 0, 0, 0, 320, 240, $imgdata[0], $imgdata[1] );
    ob_start();
    switch( $imgdata[2] ) {
        case 1 :
            imagegif( $resized );
            break;
        case 2 :
            imagejpeg( $resized );
            break;
        case 3 :
            imagepng( $resized );
            break;
        default :
            ob_end_clean();
            die( "Unknown image type!" );
            break;
    }
    $resizedstr = ob_get_clean();
    $newdbval = "";
    foreach( $meta as $key => $val ) {
        $newdbval .= "$key<$val>";
    }
    $newdbval .= $resizedstr;
    imagedestroy( $resized );
    imagedestroy( $img );
    unlink( $tmpfile );
    return $newdbval;
}


?>
