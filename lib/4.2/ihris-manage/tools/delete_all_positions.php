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

$facility_type = 'facility_type|1';
$district_id = 'district|79';

$where = array(
    //'operator'=>'OR',
    'operator'=>'AND',
    'operand'=>array(
        0=>array(
            'operator'=>'FIELD_LIMIT',
            'field'=>'facility_type',
            'style'=>'lowerequals',
            'data'=>array(
                'value'=>$facility_type
                )
            ),
        1=>array(
            'operator'=>'FIELD_LIMIT',
            'field'=>'location',
            'style'=>'lowerequals',
            'data'=>array(
                'value'=>$district_id
                )
        )
    )
);

$facilities = I2CE_FormStorage::search('facility',true,$where);

//print_r($facilities);
$x = array();

foreach($facilities as $facility_id){


    $facilityObj = $ff->createContainer('facility|'.$facility_id);
    $facilityObj->populate();
    

    $where = array(
        'operator'=>'OR',
        //'operator'=>'AND',
        'operand'=>array(
            0=>array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'facility',
                'style'=>'lowerequals',
                'data'=>array(
                    'value'=>'facility|'.$facility_id
                    )
                )
        )
    );

    $positions = I2CE_FormStorage::search('position', true, $where);
    
    
    $x[$facilityObj->name] = count($positions);
    
    // foreach ($positions as $position_id) {
    //     // if ( !($positionObj = $ff->createContainer('position|'.$position_id)) instanceof iHRIS_Position ) {
    //     //     continue;
    //     // }
    //     // echo "Deleting position with id=" . 'position|'.$position_id . "\n";
    //     // $positionObj->populate();
    //     // $positionObj->delete(false,true);
    // }
}

print_r($x);




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
