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
chdir("../sites/blank/pages");
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

$ids = I2CE_FormStorage::search('country');


$forms = I2CE::getConfig()->getKeys("/modules/forms/forms");

sort($forms);

  
  $form = chooseMenuValue("Please select a form to capitalize",$forms);
  $form_ids = I2CE_FormStorage::search($form);

//Then we choose the fields from that form:
  $ff = I2CE_FormFactory::instance();
  $obj = $ff->createContainer($form);
  $all_fields = $obj->getFieldNames();
  sort($all_fields);
  $selected_field_indices = chooseMenuIndices("Select the fields you wish to capitalize", $all_fields);
  foreach ($form_ids as $form_id) {
     $formObj = $ff->createContainer($form . '|' . $form_id);
     $formObj->populate();
     $changed = false;
    foreach ($selected_field_indices as $selected_field_index) {
        $field  = $all_fields[$selected_field_index];
        if (! ($fieldObj = $formObj->getField($field)) instanceof I2CE_FormField_STRING_LINE) {
          continue;
        }
        $value = $fieldObj->getDBValue();
        I2CE::raiseMessage("Now capitalizing $value\n");
        $new_value = fixCase( $value);
        I2CE::raiseMessage("Changed $value to $new_value");
        //$changed == ( $value != $new_value );
       // $fieldObj->setValue( $new_value );
       $formObj->getField($field)->setValue($new_value);
    }
    //if ( !$changed ) {
      //continue;
    //}else{
    //}
     $formObj->save( $user );
     $formObj->cleanup();
}


//And the fixCase function we will need to write.   One thing is that we don't want abbreviations (such as DHMT to get converted to Dhmt)

global $excluded;   //we will need to add to the list by hand once we look at the data for form.
//$excluded = array('DHMT', 'HIV', 'HIV/AIDS', 'AIDS', 'DPSM');
function fixCase( $formValue ) {
    //$split_string = explode(' ', $formValue );
    $excluded = array( 'DHMT', 'HIV', 'HIV/AIDS', 'AIDS', 'DPSM', 'GOVT', 'KITSO', 'KTCU', 'MGNT', 'AIDS/STD', 'STD', 'IT', 
                        'I.T', 'I', 'T', 'ICU/ICN', 'ICN', 'ICU', 'OT', 'BMC', 'BDF', 'IHS', 'IDM', 'II', 'III', 'IV', 'BDF', 'DHT', 'SDA', 'PHCAR', 'PMTCT',
                        'SQ', 'TAG', 'UB', 'FET', 'ARV', 'AHMO', 'TB', 'NBTS', 'FNP', 'BOTUSA', 'CHBC', 'BHHRL', 'ICT', 'OPD', 'IMU', 'CSSD', 'DCS', 'MCH', 'ASRH', 'MCH/ASRH',
                        'PSHCY', 'TAEDS', 'IDCC', 'DRM', 'OBS', 'GYN');
    $temp = preg_split('/(\W)/', $formValue, -1, PREG_SPLIT_DELIM_CAPTURE );
    foreach ( $temp as $key => $word ) {
        if ( in_array( $word, $excluded ) ) {
          continue;
        }
        if ( preg_match( '/[aeiou]/', $word ) ) {
           //check to see if there is a vowel.  If not, assume that it is an abbreviation
           continue;
        }
        $temp[$key] = ucfirst(strtolower( $word ) );
    }
    //print_r( $temp );
    return join ( '', $temp );
}
/*
*/