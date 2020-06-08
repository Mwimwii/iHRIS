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

$where = array(
    'field'=>'training_institution',
    'operator'=>'FIELD_LIMIT',
    'style'=>'equals',
    'data'=>array(
        'value'=>'training_institution|232524'
        )
    );


$ids = I2CE_FormStorage::search('training_course', false,$where);
$courses = array();
foreach ($ids as $id) {
    $trainingCourseObj = $ff->createContainer('training_course|' . $id);
    $trainingCourseObj->populate();
    $data = $trainingCourseObj->getPost();
    if (!is_array($data) || !array_key_exists('fields',$data) || !is_array($data['fields'])) {
        continue;
    }
    if (array_key_exists('id',$data['fields'])) {
        unset($data['fields']['id']);
    }
    $data = json_encode($data,true);
    if (($found_id = array_search($data,$courses)) === false) {
        $courses[$id] = $data;
        continue;
    }
    //echo "Course Data Match on $found_id and $id\n$data\n{$courses[$found_id]}\n";
    $where = array(
        'field'=>'training_course',
        'operator'=>'FIELD_LIMIT',
        'style'=>'equals',
        'data'=>array(
            'value'=>'training_course|' . $id
            )
        );

    $sched_courses = I2CE_FormStorage::search('scheduled_training_course',false,$where);
    print_r($sched_courses);
    foreach ($sched_courses as $c_id) {
        $sCourseObj = $ff->createContainer('scheduled_training_course|' . $c_id);
        $sCourseObj->populate();
        $sCourseObj->getField('training_course')->setValue(array('training_course',$found_id));
        $sCourseObj->save($user);
        $sCourseObj->cleanup();
    }
    $sched_courses = I2CE_FormStorage::search('scheduled_training_course',false,$where);
    print_r($sched_courses);
    if (count($sched_courses) == 0) {
        echo "No Scheduled Courses\n";
        $trainingCourseObj->delete();
    }
    

}