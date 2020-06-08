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


$where = array(
    'operator'=>'FIELD_LIMIT',
    'field'=>'id',
    'style'=>'like',
    'data'=>array(
        'value'=>'KITSO%'
        )
    );
$Course_ids = I2CE_FormStorage::search('training_course',false,$where);

foreach ($Course_ids as $Course_id) {
    $where = array(
        'operator'=>'FIELD_LIMIT',
        'field'=>'training_course',
        'style'=>'equals',
        'data'=>array(
            'value'=>'training_course|' . $Course_id
            )
        );
    $slots = array();
    $sCourse_ids = I2CE_FormStorage::listFields('scheduled_training_course',array('training_course','start_date','end_date'),false,$where, array('start_date','end_date'));
    foreach ($sCourse_ids as $sCourse_id=>$data) {
        if (!array_key_exists($data['start_date'],$slots)) {
            $slots[$data['start_date']] = array();
        }
        $slots[$data['start_date']][] = $sCourse_id;
    }
    foreach ($slots as $start_date => $sCourse_ids) {
        echo "\t$start_date has "  . count($sCourse_ids) . "\n";
        if (count($sCourse_ids)  <2 ) {
            continue;
        }
        reset($sCourse_ids);
        $master = 'scheduled_training_course|' . array_shift($sCourse_ids);
        foreach ($sCourse_ids as $sCourse_id) {
            $where = array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'scheduled_training_course',
                'style'=>'equals',
                'data'=>array(
                    'value'=>'scheduled_training_course|' . $sCourse_id
                    )
                );
            $psCourse_ids = I2CE_FormStorage::search('person_scheduled_training_course',false,$where);
            echo "\tGot " . count($psCourse_ids) . " students scheuled for schduled_training_course|" . $sCourse_id . "\n";
            foreach ($psCourse_ids as $psCourse_id) {
                echo "\t\tMovinig person_scheduled_training_course|$psCourse_id to refer to $master instaed of scheduled_training_course|$sCourse_id\n";
                $pscObj = $ff->createContainer('person_scheduled_training_course|' . $psCourse_id);
                $pscObj->populate();
                $pscObj->getField('scheduled_training_course')->setFromDB($master);
                $pscObj->save($user);
                $pscObj->cleanup();
            }
        }

    }
}

# local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
