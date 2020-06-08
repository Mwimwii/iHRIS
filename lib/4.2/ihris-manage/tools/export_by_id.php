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





if (count($arg_files) != 1) {
    usage("Please specify the name of a spreadsheet to process");
}

reset($arg_files);
$file = current($arg_files);
if($file[0] == '/') {
    $file = realpath($file);
} else {
    $file = realpath($dir. '/' . $file);
}
if (!is_readable($file)) {
    usage("Please specify the name of a spreadsheet to import: " . $file . " is not readable");
}

I2CE::raiseMessage("Loading from $file");
			  
			  
if (!is_readable($file) || ($fp = fopen($file,"r")) === false) {
    usage("Please specify the name of a .CSV spreadsheet to import: " . $file . " is not readable");
}
$dir = dirname($file) . '/exports';
exec('mkdir -p ' . $dir);
$expected_headers = array(
    'type' =>'Type',
    'value' => 'Value'
    );

$child_forms = array('person_id','demographic','person_contact_work','person_contact_personal','person_contact_other','person_contact_emergency');


$omang_id_type = 'id_type|1';
$emp_id_type = 'id_type|4';
$excemption_id_type = 'id_type|2';
$manpower_id_type = 'id_type|9999';
$reg_id_type = 'id_type|3';

$id_types = array(
    'EmpID' => $emp_id_type,
    'EstOmang'=>$omang_id_type,
    'Exemption' => $excemption_id_type,
    'Manpower'=> $manpower_id_type,
    'Omang'=>$omang_id_type,
    'Registration'=>$reg_id_type,
    );



$headers = loadHeadersFromCSV($fp);
mapHeaders($headers);
$row = 0;
$success =0;
while (($data = fgetcsv($fp)) !== FALSE) {
    echo "Got $success out of $row successes\n";
    $row++;
    if ( ! ($mapped_data = mapData($data,$row))) {
        continue;
    }
    if (!$mapped_data['type'] || !$mapped_data['value'] || ! array_key_exists($mapped_data['type'],$id_types)) {
        continue;
    }
    if ( ($personId = findPersonByID($id_types[$mapped_data['type']],$mapped_data['value'])) === false) {
        continue;
    }
    if ( ! ($personObj = $ff->createContainer($personId)) instanceof iHRIS_Person) {
        continue;
    }
    $personObj->populate();
    $json = array();
    $json['person'] = $personObj->getPost();
    $personObj->populateChildren($child_forms);
    $children = $personObj->getChildren();
    foreach($children as $child_form=>$childObjs) {
        $json[$child_form] = array();
        foreach ($childObjs as $childObj) {
            $json[$child_form][] =$childObj->getPost();
            $childObj->cleanup();
        }
    }
    $personObj->cleanup();
    file_put_contents($dir . '/' . str_replace('|','_' ,$personObj->getNameID()) . '.json',json_encode($json));
    $success++;
}
echo "Got $success out of $row successes\n";





function findPersonByID($id_type,$id_num) {
    $id_num = strtoupper(trim($id_num));
    $where = array(
        'operator'=>'AND',
        'operand'=>array(
            0=>array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'id_type',
                'style'=>'equals',
                'data'=>array(
                    'value'=>$id_type
                    )
                ),
            1=>array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'id_num',
                'style'=>'equals',
                'data'=>array(
                    'value'=>$id_num
                    )
                )
            )
        );
    $person_ids = I2CE_FormStorage::listFields('person_id',array('parent'),true,$where);
    if (count($person_ids) > 1) {
        return false;
    } else if (count($person_ids) == 0) {
        return false;
    }
    $data = current($person_ids);
    if (!is_array($data) || !array_key_exists('parent',$data) ||  !is_string($data['parent']) || !substr($data['parent'],0,7) == 'person|' || !substr($data['parent'],7)) {
        return false;
    }
    return $data['parent'];
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





# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
