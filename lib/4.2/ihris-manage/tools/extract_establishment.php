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




require_once("import_base.php");
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
$data_file = new CSVDataFile($file);
print_r($data_file->getHeaders());
$establishment = array();
while ($data_file->hasDataRow()) {
    $data = $data_file->getDataRow();
    $establishment[$data[2]]=  $data[5];
}
asort($establishment);
//print_r($establishment);
$posts = I2CE_FormStorage::listFields('post',array('dpsm_code'));
$codes = array();
foreach ($posts as $id=>$data) {
    if (!$data['dpsm_code']) {
        continue;
    }
    $codes[$data['dpsm_code']] ='post|' . $id;
}
$est = array();
foreach ($establishment as $dpsm=>$amnt) {
    if (!array_key_exists($dpsm,$codes)) {
        continue;
    }
    $est[$dpsm] = array('post'=>$codes[$dpsm],'amount'=>$amnt);
}
print_r($est);
if (!ask("Does this look corrent?")) {
    die("Death\n");
}
$obj = $ff->createContainer('establishment_type|est');
$obj->populate();
$obj->getField('name')->setFromDB('Establishment');
$obj->save($user);
$obj->cleanup();
$obj = $ff->createContainer('establishment_period|est_2012');
$obj->getField('year')->setFromDB('2012-00-00 00:00:00');
$obj->getField('establishment_type')->setFromDB('establishment_type|est');
$obj->populate();
$obj->save($user);
$obj->cleanup();
foreach ($est as $dpsm=>$data) {
    echo "Setting:\n" . print_r($data,true);
    $obj = $ff->createContainer('establishment|est_2012_' . $dpsm);
    $obj->populate();
    $obj->getField('establishment_period')->setFromDB('establishment_period|est_2012');
    $obj->getField('post')->setFromDB($data['post']);
    $obj->getField('amount')->setFromDB($data['amount']);
    $obj->save($user);
    $obj->cleanup();
}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
