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

$date = false;
while (!$date) {
    $date = trim(ask("I need a date in the format YYYY-MM-DD.  I will close all ininfium positions that have not been validated after this date."));
    $matches = array();
    if (! preg_match('/^((19|20)\\d\\d)-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[01])$/',$date,$matches)) {
        echo "Invalid $date\n";
        $date = false;
    }
    $date .= ' 00:00:01';
}

$where = 
    array(
        'operator'=>'AND',
        'operand'=>array(
            0=>   array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'infinium_valid_date',
                'style'=>'not_null',
                ),
            1=> array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'infinium_valid_date',
                'style'=>'lessthan_equals',
                'data'=>array(
                    'value'=>$date
                    )
                ),
            2=> array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'end_date',
                'style'=>'null',
                'data'=>array()
                )
            )
        );
$closers =  I2CE_FormStorage::listFields('person_position',array('position','infinium_valid_date','parent','end_date'),false,$where);
echo "Closing records that look like:\n";
print_r(array_slice($closers,0,5));

$where = 
    array(
        'operator'=>'AND',
        'operand'=>array(
            0=>   array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'infinium_valid_date',
                'style'=>'not_null',
                ),
            1=> array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'infinium_valid_date',
                'style'=>'greaterthan',
                'data'=>array(
                    'value'=>$date
                    )
                ),
            2=> array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'end_date',
                'style'=>'null',
                'data'=>array()
                )
            )
        );
$openers =  I2CE_FormStorage::search('person_position',false,$where);

if (! simple_prompt("Found " . count($closers) . " positions to close leaving " . count($openers) . " open.  Does this sound correct?")) {
    die("Please try again\n");
}

$now = I2CE_Date::now();
$row =0;
foreach ($closers as $pp_id => $data) {
    $row++;
    echo "Closing $row of " . count($closers) ."\n";
    if ( ! ( $ppObj = $ff->createContainer('person_position|'.$pp_id)) instanceof iHRIS_PersonPosition) {
        echo "Invalid person_position|$pp_id\n";
        continue;
    }
    $ppObj->populate();
    $ppObj->getField('end_date')->setValue($now);
    $ppObj->save($user);
    $ppObj->cleanup();
    if (!is_array($data) || !array_key_exists('position',$data)) {
        echo "No position associtated to person_position|$pp_id\n";
    }
    if ( ! ( $pObj = $ff->createContainer(array('position',$data['position']))) instanceof iHRIS_Position) {
        echo "Invalid position|{$data['position']}\n";
        continue;
    }
    $pObj->populate();
    $pObj->getField('status')->setFromDB('position_status|discontinued');
    $pObj->save($user);
    $pObj->cleanup();
}






# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
