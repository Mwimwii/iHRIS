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



ini_set('memory_limit','4G');

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
if (!is_readable($file)  || ($fp = fopen($file,"r")) === false) {
    usage("Please specify the name of a spreadsheet to import: " . $file . " is not readable");
}

I2CE::raiseMessage("Looking for DPSM->CS codes from $file");


$expected_headers = array(
    'org_name'=>'New Organisational Location',
    'org_code'=>'New Org Loc',
    'dpsm_code'=>'New Position',
    );

$headers = loadHeadersFromCSV($fp);
mapHeaders($headers);

$row = 0;
$org_codes =array();
$org_names =array();
while (($data = fgetcsv($fp)) !== FALSE) {
    $row++;
    if ( ! ($mapped_data = mapData($data,$row))) {
        continue;
    }
    if (! ($dpsm_code = $mapped_data['dpsm_code'])) {
        continue;
    }
    if ( ($org_name = strtoupper(trim($mapped_data['org_name']))) && ($org_name != 'NULL') && ($org_code = str_replace('/','',strtoupper(trim($mapped_data['org_code']))))) {
        $org_names[$org_code] = $org_name;
        $org_codes[$dpsm_code] = $org_code;
    }
}
fclose($fp);
$existing_codes = I2CE_FormStorage::search('dpsm_org');
foreach ($org_names as $code=>$name) {
    if (in_array($code,$existing_codes)) {
        continue;
    }
    echo "Want to create $code -> $name\n";
    if (! ( ($orgObj = $ff->createContainer('dpsm_org')) instanceof I2CE_SimpleList)) {
        continue;
    }
    $orgObj->setID($code);
    $orgObj->getField('name')->setValue($name);
    $orgObj->save($user);
    $orgObj->populate();
    $orgObj->cleanup();
    $existing_codes[] = $code;
}



$where = array(
    'operator'=>'FIELD_LIMIT',
    'field'=>'dpsm_org',
            'style'=>'null'
    );




foreach ( I2CE_FormStorage::listFields('post',array('dpsm_code'),false,$where) as $post_id=>$data) {
    if (  !is_array($data) || !array_key_exists('dpsm_code',$data) || !($dpsm_code = trim($data['dpsm_code'])) || ! ($postObj = $ff->createContainer(array('post',$post_id))) instanceof Botswana_Post) {
        continue;
    }
    $postObj->populate(true);
    $changed =false;
    $org_field = $postObj->getField('dpsm_org');
    if (!$org_field->isValid() &&  array_key_exists($dpsm_code,$org_codes)) {
        $org_field->setValue(array('dpsm_org',$org_codes[$dpsm_code]));
        $changed = true;
    }
    if ($changed) {
        echo "Wan to save " . $org_codes[$dpsm_code] ." for $dpsm_code\n";
        $postObj->save($user);
    }
    $postObj->cleanup();

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
    
    return $mapped_data;
}

# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
