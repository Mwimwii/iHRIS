#!/usr/bin/php
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
I2CE::raiseError("Connecting to DB");
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

I2CE::raiseError("Connected to DB");
$db= MDB2::singleton();
$ff = I2CE_FormFactory::instance();
$user = new I2CE_User();

require_once($i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'CLI.php');

if (count($arg_files) != 1) {
    usage("Please specify the name of a .CSV file for changing departments into facilities");
}

reset($arg_files);
$file = current($arg_files);
if($file[0] == '/') {
    $file = realpath(current($arg_files));
} else {
    $file = realpath($dir. '/' . $file);
}

if (!is_readable($file) || ($fp = fopen($file,"r")) === false) {
    usage("Please specify the name of a .CSV spreadsheet to import: " . $file . " is not readable");
}

$expected_headers = array(
    'dept_id'=>'id',
    'dept_name'=>'name'
    );

$headers = loadHeadersFromCSV($fp);
mapHeaders($headers);
$row = 0;
while (($data = fgetcsv($fp)) !== FALSE) {
    $row++;
    addFacilityFromDepartment($data,$row);    
}

/*********************************************
*
*      Helper functions
*
*********************************************/

function loadHeadersFromCSV($fp) {
    $in_file_sep = false;
    foreach (array("\t",",") as $sep) {
        $headers = fgetcsv($fp, 4000, $sep);
        if ( $headers === FALSE|| count($headers) < 3) {
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
            I2CE::raiseError("Could not find $expected_header in the following headers:\n\t" . implode(" ", $expected_headers). "\nFull list of found headers is:\n\t" . implode(" ", $headers));
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

function addFacilityFromDepartment($data,$row) {
    global $user;
    $ff = I2CE_FormFactory::instance();
    if ( ! ($mapped_data = mapData($data,$row))) {
        return false;
    }
    if (!$mapped_data['dept_id'] || !$mapped_data['dept_name']) {
        continue;
    }
    //first check to see if a facility already exists
    $where = array(
        'operator'=>'FIELD_LIMIT',
        'field'=>'name',
        'style'=>'equals',
        'data'=>array(
            'value'=>$mapped_data['dept_name']
            )
        );
    $facs = I2CE_FormStorage::search('facility',false,$where);
    if (count($facs) > 1) {
        I2CE::raiseError("Department " .  $mapped_data['dept_name'] . " has multiiples on facility");
    } else  if (count($facs) == 1) {
        I2CE::raiseError("Department " . $mapped_data['dept_name'] ." has already been made a facility");
        reset($facs);
        $fac_id = current($facs);
    } else {
        if (! ($facObj = $ff->createContainer( 'facility')) instanceof iHRIS_Facility) {
            I2CE::raiseError("Could not create facility");
            break;
        }
        $facObj->getField('name')->setValue($mapped_data['dept_name']);
        $facObj->save($user);
        $fac_id = $facObj->getID();
        $facObj->cleanup();
        I2CE::raiseError("Department " . $mapped_data['dept_name'] . " has just been made a facility");
    }
    I2CE::raiseError("Fac_id =$fac_id");
    //now we need to look for any positions with the department id 
    $where = array(
        'operator'=>'FIELD_LIMIT',
        'field'=>'department',
        'style'=>'equals',
        'data'=>array(
            'value'=>$mapped_data['dept_id']
            )
        );
    if (is_string($fac_id) && substr($fac_id,0,9) == 'facility|') {
        $fac_id = substr($fac_id,9);
    }
    $positions = I2CE_FormStorage::search('position',false,$where);
    if (!simple_prompt("Update " . count($positions) . " positions for facility " . $mapped_data['dept_name'] . ":\n" . implode(" " ,$positions))) {
        continue;
    }
    foreach ($positions as $posid) {
        
        if (! ($posObj = $ff->createContainer("position|$posid"))instanceof iHRIS_Position) {
            I2CE::raiseError("Could not create position|$posid");
            continue;
        }
        $posObj->populate();
        $posObj->getField('facility')->setValue(array('facility',$fac_id));
        $posObj->save($user);
        $posObj->cleanup();
    }
    if (!simple_prompt("Continue?")) {
        die("Dead\n");
    }
    return true;
}






# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
