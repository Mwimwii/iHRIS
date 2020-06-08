<?php


$dir = getcwd();
chdir("../pages");
$i2ce_site_user_access_init = null;
$wd = getcwd();

$i2ce_site_user_database = null;
require_once( $wd . DIRECTORY_SEPARATOR . 'config.values.php');

$local_config = $wd . DIRECTORY_SEPARATOR .'local' . DIRECTORY_SEPARATOR . 'config.values.php';
if (file_exists($local_config)) {
    require_once($local_config);
}

if(!isset($i2ce_site_i2ce_path) || !is_dir($i2ce_site_i2ce_path)) {
    echo "Please set the \$i2ce_site_i2ce_path in $local_config";
    exit(55);
}

require_once ($i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'I2CE_config.inc.php');
if (isset($i2ce_site_dsn)) {
    @I2CE::initializeDSN($i2ce_site_dsn,   $i2ce_site_user_access_init,    $i2ce_site_module_config);         
} else if (isset($i2ce_site_database_user)) {    
    I2CE::initialize($i2ce_site_database_user,
                     $i2ce_site_database_password,
                     $i2ce_site_database,
                     $i2ce_site_user_database,
                     $i2ce_site_module_config         
        );
} else {
    die("Do not know how to configure system\n");
}



require_once($i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'CLI.php');


if (count($arg_files) != 1) {
    usage("Please specify the name of a directory");
}

reset($arg_files);
$dir = current($arg_files);

$files = glob("$dir/*xlsm");

include_once('PHPExcel/PHPExcel.php'); 
if (!class_exists('PHPExcel',false)) {
    usage("You must have PHPExcel installed to load excel spreadsheets");
}
//ini_set('memory_limit','4G');

$pos = array();
$f_found = false;
$pos = explode("\n",trim(file_get_contents("$dir/missing_omang.csv")));

foreach ($files as $file) {
    if (!$f_found) {
	$f_found = $file == '/home/sovello/botswana/all_completed_form_b/Form B-BOOK15-PORTIAH-KGOTLAMOTHO- 15Greater Gaborone-1.xlsm';
	continue;
    }
    echo "Doing $file\n";
    $readerType = PHPExcel_IOFactory::identify($file);
    $reader = PHPExcel_IOFactory::createReader($readerType);
    $reader->setReadDataOnly(false);
    $excel = $reader->load($file);        
    $worksheet = $excel->getActiveSheet();
    $rowIterator = $worksheet->getRowIterator();
    $found = false;
    for ($i=1; true ; $i++) {
	$val = strtoupper(trim($worksheet->getCell('C' . $i)->getValue()));
	if (!$found) {
	    $found= ($val == "OMANG\nEXEMPTION");
	    if ($i > 100) {
		echo "\tSkipping this file because could not find 'OMANG EXCEMPTION' column\n";
		continue 2;
	    }
	} else {
	    if (!$val) {
		break;
	    }
	    if (!in_array($val,$pos)) {
		$pos[] =  $val;
	    }
	}
    }
    //natsort($pos);
    file_put_contents("$dir/missing_omang.csv", implode("\n",$pos) . "\n");
}
