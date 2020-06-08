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
 * @author Sovello Hildebrand <sovellohpmgani@gmail.com>
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

$acronyms = array(
'AAT','ACHAP','BGCSE','BO','BOCODOL','BWP','COSC','DHMT','DRC','en','GCE','ICM','IEB','JC','PMTCT','PSLE','USA','YWCA','IHS','OHS','I','II','III',
'FNP','RN','ANO','ENT','BMPC','BHPC','IEC','EMS','MOH','HQ','BDF','SDA','DHT','UB','STD','EE','BMC','HP','OBS','GYN','CSSD','SRN','BSC','MSC','NCC',
'TB','SDU','HIV','AIDS','CD','ROM','ASLM', 'SLIPTA','ARV','ALSO','AU680','PATS','DNA','MEQ','ISO','BOS','PBRS','BLS','NHS','BEST','ATTC','USAID',
'VRQ','CAPS','CGPA','COBAS','CS','LEEP','HPV','HSK','STDS','STIS','STI','STD','DIP',
);

$person_ids = I2CE_FormStorage::search('person');

$upcasenames = array();

/*/get all child forms for this person
$child_forms = I2CE::getConfig()->getAsArray("/modules/forms/forms/person/meta/child_forms");
 
foreach( $person_ids as $personid ) {
    if ( !($personObj = $ff->createContainer("person|".$personid)) instanceof iHRIS_Person ) {
        continue;
    }
    echo "Person with id=" . $personid . "\n";
    $personObj->populate();
    foreach( $personObj->getFieldNames() as $fieldname ){
            $dbValue = $personObj->getField($fieldname)->getDBValue();
        if( $personObj->getField($fieldname) instanceof I2CE_FormField_STRING_LINE && (ctype_lower($dbValue ) || ctype_upper($dbValue) ) ){
            if(!in_array(strtoupper($dbValue), $acronyms )){
              $newvalue = ucfirst(strtolower($dbValue));
              $upcasenames[] = $dbValue.','.upcaseEveryWord($dbValue, $acronyms);
              $personObjgetField($fieldname)->setFromDB(upcaseEveryWord($dbValue, $acronyms));
//              $personObj->save($user);
            }
          }
      }
    $personObj->populateChildren($child_forms);
    foreach ($personObj->getChildren() as $child_form_name=>$child_form_data) {
        foreach ($child_form_data as $child_form_id=>$child_form) {
            if (!$child_form instanceof I2CE_Form) {
                continue;
            }
            echo "\tUpcasing" . $child_form->getFormID() . "\n";
            $formObj = $ff->createContainer($child_form->getFormID());
            $formObj->populate();
            foreach( $formObj->getFieldNames() as $fieldname ){
                $fieldValue = $formObj->getField($fieldname)->getDBValue();
                if( $formObj->getField($fieldname) instanceof I2CE_FormField_STRING_LINE && 
                    (count(preg_split('/[[:punct:]]|[[:space:]]/',$fieldValue))>1 ||ctype_lower($fieldValue ) || ctype_upper($fieldValue) ) ){
                    if(!in_array(strtoupper($fieldValue), $acronyms )){
                      $newvalue = ucfirst(strtolower($fieldValue));
                      $upcasenames[] = $fieldValue.','.upcaseEveryWord($fieldValue, $acronyms);
                      $formObj->getField($fieldname)->setFromDB(upcaseEveryWord($fieldValue, $acronyms));
//                      $formObj->save($user);
                    }
                  }
              }
            $formObj->cleanup();
        }
    }
    
}
*/
//list forms
$config = I2CE::getConfig();
$listforms = $config->modules->forms->forms;
$forms  = $listforms->getKeys('/modules/forms/forms');
print_r($forms);
$exempt_forms = array('user','locale','role');
echo "Following forms are lists\n";
foreach( $forms as $form){
  if( !in_array($form, $exempt_forms)){ 
    $lFormObj = $ff->createContainer($form);
    if( $lFormObj instanceof I2CE_List ){
      $form_ids = I2CE_FormStorage::search($form);
    if(!empty($form_ids)){
      foreach( $form_ids as $formid ) {
          if ( !($formObj = $ff->createContainer("$form|$formid")) instanceof I2CE_List ) {
              continue;
          }
          //echo "form with id=" . $formid . "\n";
          $formObj->populate();
          foreach( $formObj->getFieldNames() as $fieldname ){
              $listValue = $formObj->getField($fieldname)->getDBValue();
              if( $formObj->getField($fieldname) instanceof I2CE_FormField_STRING_LINE && (count(preg_split('/[[:punct:]]|[[:space:]]/',$listValue))>1 ||ctype_lower($listValue ) || ctype_upper($listValue) ) ){
                  $newvalue = ucfirst(strtolower($listValue));
                  if(!in_array($fieldname, array('alpha_two','code')) ){
                    if(!in_array(strtoupper($listValue), $acronyms )){
                        $upcasenames[] = $listValue.','.upcaseEveryWord($listValue, $acronyms);
                        $formObj->getField($fieldname)->setFromDB(upcaseEveryWord($listValue, $acronyms));
                        $formObj->save($user);
                      }
                    }
                  }
                }
            }
          $formObj->cleanup();
        }
      }
  }
  }

function upcaseEveryWord($string, $exceptions){
    if(strpos($string, '@') !== false ){
       return $string;
      }
    $parts = preg_split('/[[:space:]]|[[:punct:]]/', $string);
    $sentence = $string;
    foreach( $parts as $part){
        if(in_array(strtoupper($part), $exceptions)){
            $sentence = preg_replace('/\b'.$part.'\b/', strtoupper($part), &$sentence);
          }
        else{
            $sentence = preg_replace('/\b'.$part.'\b/', ucfirst(strtolower($part)), &$sentence);
          }
      }
    
    return $sentence;
  }
//$fp = fopen('mixedchars.csv', 'w');
natsort($upcasenames);
file_put_contents("../tools/mixedcharsnames.csv", implode("\n",$upcasenames) . "\n");
//qfile_put_contents("../tools/mixedcharsnameslists.csv", implode("\n",$upcasenames) . "\n");

# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
