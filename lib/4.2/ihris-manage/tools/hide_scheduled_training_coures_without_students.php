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


$user = new I2CE_User();

$ff = I2CE_FormFactory::instance();

$person_scheduled_courses = I2CE_FormStorage::listFields('person_scheduled_training_course', array('scheduled_training_course'));
$person_courses = array(); //courses that are assigned to people
foreach($person_scheduled_courses as $key => $value){
    $person_courses[] = $value['scheduled_training_course'];
  }
//print_r($person_courses);
$empty_courses = array();
$failed_hide = array();
$scheduled_training_courses = I2CE_FormStorage::search('scheduled_training_course');
foreach($scheduled_training_courses as $id ){
    if(!in_array("scheduled_training_course|$id", $person_courses)){
      $empty_courses[] = 'scheduled_training_course|'.$id;
      //set i2ce_hidden = 1
      if (!($emptyCourseObj = $ff->createContainer(array('scheduled_training_course',$id))) instanceof iHRIS_Scheduled_Training_Course) {
          echo "Could not populate form for scheduled_training_course|$id\n";
          $failed_hide[] = 'scheduled_training_course|'.$id;
          continue;
      }
      echo "hiding scheduled_training_course|$id\n";
      $emptyCourseObj->populate();
      $emptyCourseObj->getField('i2ce_hidden')->setValue(1);
      $emptyCourseObj->save($user);
    }
  }
echo "hid ".count($empty_courses)."\n";




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
