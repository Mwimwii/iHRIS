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

$personIds = I2CE_FormStorage::search('person', false);

$where = array(
        'operator'=>'AND',
        'operand'=>array(
            0=>array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'end_date',
                'style'=>'null'
                )
            ),
            1=>array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'infinium_valid_date',
                'style'=>'not_null'
                )
        );

$pPositions = I2CE_FormStorage::listFields('person_position', array('parent', 'position'), true, $where);

echo count($pPositions);

foreach($pPositions as $key=>$positionData){
    $personObj = $ff->createContainer($positionData['parent']);
    $personObj->populate();
    
    if( !empty($positionData['position']) ){
      $positionObj = $ff->createContainer($positionData['position']);
      $positionObj->populate();
      $facility = $positionObj->getField('deploy_facility')->getValue('deploy_facility');
      $job = $positionObj->getField('job')->getValue('job');
    }
    
    if( !empty($job[0]) && !empty($job[1])){
      echo "non empty job\n";
      print_r($job);
      $jobObj = $ff->createContainer($job);
      $jobObj->populate();
      $cadre = $jobObj->getField('cadre')->getValue('cadre');
    }
    
    if( !empty($cadre[0]) && !empty($cadre[1]) ){
      echo "non empty cadre\n";
      print_r($cadre);
      $personObj->getField('cadre')->setValue($cadre);
      $personObj->save($user);
    }
    
    if( !empty($facility[0]) && !empty($facility[1]) ){
      echo "non empty facility\n";
      print_r($facility);
      $depFacObj = $ff->createContainer('person_deploy_facility');
      $depFacObj->getField('facility')->setValue($facility);
      $depFacObj->setParent($positionData['parent']);
      $depFacObj->save($user);
    }
    
    $personObj->cleanup();
    $positionObj->cleanup();
    $jobObj->cleanup();
    $depFacObj->cleanup();
  }
echo "\n";
/*
 * 
$where = array(
    'operator' => 'FIELD_LIMIT',
    'field'=>'name',
    'style'=>'null',
  );

$invalidpos = I2CE_FormStorage::search('facility',false,$where);
//echo count($invalidpos);
$deletePos = $ff->createContainer('facility|14');
$deletePos->populate();
foreach($deletePos as $fieldName => $Objvalue){
  echo $fieldName.'=>'.$Objvalue->getDBValue()."\n";
  }
  $deletePos->delete();
*/
$displayName = "Seach Training Participants";
//I2CE::getConfig()->setIfIsSet($displayName, "/modules/CustomReports/reportViews/search_people/display_name");
$title = I2CE::getConfig()->traverse("/modules/CustomReports/reportViews/search_people/description", true,false);
$title->setValue($displayName);
I2CE::raiseMessage($title);
/*/$invalidpos = I2CE_FormStorage::listFields('facility',array('id', 'name'), false, $where);
/foreach($invalidpos as $key=>$pos){
$deletePos = $ff->createContainer('facility|14');
$deletePos->populate();
$deletePos->delete();
}
/*
foreach ($invalid_person as $key => $person) {
   $personObj = $ff->createContainer($person);
    
    echo "Deleting person with id =" . $person . "\n";
    $personObj->populate();
    $personObj->populateChildren($child_forms);
    foreach ($personObj->getChildren() as $child_form_name=>$child_form_data) {
        foreach ($child_form_data as $child_form_id=>$child_form) {
            if (!$child_form instanceof I2CE_Form) {
                continue;
            }
            echo "\tDeleting: " . $child_form->getFormID() . "\n";
            if ($child_form_name == 'person_position' && ($posObj= $ff->createContainer($child_form->getField('position')->getValue())) instanceof iHRIS_Position) {
                echo "\t\tDeleting linked position with ID=" . $posObj->getFormID() . "\n";
                $posObj->delete(false,true);
            }
            $child_form->delete(false,true);
        }
    }
    $personObj->delete(false,true);
}



*/

# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
