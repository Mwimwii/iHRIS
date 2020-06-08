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

$person_ids = I2CE_FormStorage::search('person');
$mysql_date_format = "Y-m-d H:i:s";



foreach ($person_ids as $person_id) {
    $person_position_ids = I2CE_FormStorage::search('person_position','person|' . $person_id,array(),array('start_date'));
    if (count($person_position_ids) < 2) {
        continue;
    }
    print_r( I2CE_FormStorage::listFields('person_position',array('start_date','end_date'),'person|' . $person_id , array(), array('start_date')));
    $prev_obj = false;
    foreach($person_position_ids as $person_position_id) {
        if ( ! ($curr_obj = $ff->createContainer(array('person_position',$person_position_id))) instanceof iHRIS_PersonPosition) {
            continue;
        }
        $curr_obj->populate();
        if ($prev_obj) {
            $prev_end_date = $prev_obj->getField('end_date');
            $curr_start_date = $curr_obj->getField('start_date');
            $curr_end_date = $curr_obj->getField('end_date');
            if ((!$prev_end_date->getValue()->isValid()) || date_after($prev_end_date,$curr_start_date)) {
                I2CE::raiseMessage("comparing " .  $person_position_id . " against previous\n" . 
                                  "\tSetting prev end date " . $prev_end_date->getDBValue() . " to " . $curr_start_date->getDBValue());
                $prev_end_date->setFromDB($curr_start_date->getDBValue());
                $prev_obj->save($user);
            }
            if ($curr_end_date->getValue()->isValid() && date_after($curr_start_date,$curr_end_date)) {
                I2CE::raiseMessage("comparing " .  $person_position_id . " against previous\n" . 
                                  "\tSetting current end date " . $curr_end_date->getDBValue() . " to " . $curr_start_date->getDBValue());
                $curr_end_date->setFromDB($curr_start_date->getDBValue());
            }
            $prev_obj->cleanup();
        }
        $prev_obj = $curr_obj;
        $curr_obj->save($user);
    }
    $curr_obj->cleanup();

}

    
function date_after($left_date,$right_date) {
    return strtotime( $left_date->getDBValue() ) >   strtotime( $right_date->getDBValue());
}

# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
