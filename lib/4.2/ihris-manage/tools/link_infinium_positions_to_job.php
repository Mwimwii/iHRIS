<?php
/*
 * © Copyright 2007, 2008 IntraHealth International, Inc.
 * 
 * This File is part of iHRIS
 * 
 * iHRIS is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * The page wrangler
 * 
 * This page loads the main HTML template for the home page of the site.
 * @package iHRIS
 * @subpackage DemoManage
 * @access public
 * @author Carl Leitner <litlfred@ibiblio.org>
 * @copyright Copyright &copy; 2007, 2008 IntraHealth International, Inc. 
 * @since Demo-v2.a
 * @version Demo-v2.a
 */




$dir = getcwd();
chdir("../pages");
$i2ce_site_user_access_init = null;
$i2ce_site_user_database = null;
require_once( getcwd() . DIRECTORY_SEPARATOR . 'config.values.php');

$local_config = getcwd() . DIRECTORY_SEPARATOR .'local' . DIRECTORY_SEPARATOR . 'config.values.php';
if (file_exists($local_config)) {
    require_once($local_config);
}

if(!isset($i2ce_site_i2ce_path) || !is_dir($i2ce_site_i2ce_path)) {
    echo "Please set the \$i2ce_site_i2ce_path in $local_config";
    exit(55);
}

require_once ($i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'I2CE_config.inc.php');

I2CE::raiseMessage("Connecting to DB");
putenv('nocheck=1');
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

I2CE::raiseMessage("Connected to DB");

require_once($i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'CLI.php');

$ff = I2CE_FormFactory::instance();
$user = new I2CE_User();


$file = dirname(__FILE__) . "/data/position_missing_job.csv";
			  
			  
if (!is_readable($file) || ($fp = fopen($file,"r")) === false) {
    usage("Please specify the name of a .CSV spreadsheet to import: " . $file . " is not readable");
}
$expected_headers = array(
    'title' =>'infinium_position_title',
    'jobid' => 'job_id'
    );

$headers = loadHeadersFromCSV($fp);
mapHeaders($headers);
$row = 0;
while (($data = fgetcsv($fp)) !== FALSE) {
    $row++;
    if ( ! ($mapped_data = mapData($data,$row))) {
        return false;
    }
    if (!$mapped_data['jobid'] || !$mapped_data['title']) {
        continue;
    }
    list($form,$newjobid) =explode("|",$mapped_data['jobid']);
    if (!$newjobid) {
	continue;
    }
    $where = 
	array(
	    'operator'=>'AND',
	    'operand'=>array(
		array(
		    'operator'=>'FIELD_LIMIT',
		    'field'=>'title',
		    'style'=>'equals',
		    'data'=>array(
			'value'=>$mapped_data['title']
			)
		    ),
		array(
		    'operator'=>'FIELD_LIMIT',
		    'field'=>'job',
		    'style'=>'null',
		    )
		)
	    );
    $position_ids = I2CE_FormStorage::search('position',false,$where);
    foreach ($position_ids as $position_id) {
	if (!$position_id ){
	    echo "No $position_id\n";
	    continue;
	}
	if ( ! ($positionObj = $ff->createContainer('position|' . $position_id)) instanceof iHRIS_Position) {
	    echo "Bad object $position_id\n";
	    continue;
	}
	$positionObj->populate();
	list ($form,$id) = $positionObj->job;
	$positionObj->job = array('job',$newjobid);
	list ($form,$id) = $positionObj->job;
	echo "Setting $position_id to have job|$id\n";
	$positionObj->save($user);
	$positionObj->cleanup();
	echo convert(memory_get_usage(true)); 
    }

    
}



function convert($size)
{
    $unit=array('b','kb','mb','gb','tb','pb');
    return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
}





function loadHeadersFromCSV($fp) {
    $in_file_sep = false;
    foreach (array("\t",",") as $sep) {
        $headers = fgetcsv($fp, 4000, $sep);
        if ( $headers === FALSE|| count($headers) < 2) {
            fseek($fp,0);
            continue;
        }
        $in_file_sep = $sep;
    }
    if (!$in_file_sep) {
        die("Could not get headers\n");
    }
    foreach ($headers as &$header) {
        $header = trim($header);
    }
    unset($header);
    return $headers;
}

function mapHeaders($headers) {
    global $expected_headers;
    global $header_map;
    $header_map = array();
    foreach ($expected_headers as $expected_header_ref => $expected_header) {
        if (($header_col = array_search($expected_header,$headers)) === false) {
            I2CE::raiseError("Could not find $expected_header in the following headers:\n\t" . implode(" ", $headers). "\nFull list of found headers is:\n\t" . implode(" ", $headers));
            die();
        }
        $header_map[$expected_header_ref] = $header_col;
    }
}

function mapData($data,$row) {
    global $header_map;
    $mapped_data = array();
    foreach ($header_map as $header_ref=>$header_col) {
        if (!array_key_exists($header_col,$data)) {
            $mapped_data[$header_ref] = false;
        } else {
            $mapped_data[$header_ref] = $data[$header_col];
        }
    }
    
    // if (!$mapped_data['transaction']) {
    //     addBadRecord($data,$row,'No transcation type');
    //     return false;
    // }
    return $mapped_data;
}

