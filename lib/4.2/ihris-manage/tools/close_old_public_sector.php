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
    'operator'=>'AND',
    'operand'=>array(
        0=>   array(
            'operator'=>'FIELD_LIMIT',
            'field'=>'infinium_valid_date',
            'style'=>'null',
            ),
        1=> array(
            'operator'=>'FIELD_LIMIT',
            'field'=>'end_date',
            'style'=>'null',
            )
        )
    );

$pps = I2CE_FormStorage::listFields('person_position',array('parent','position','end_date'),false,$where);
$has_post = array();
$no_post = array();
$no_position = array();
$has_dpsm = array();
$public = array();
$private = array();
foreach ($pps as $ps=>$ps_data) {
    if (!$ps_data['position']) {
        $no_position[$ps] = $ps_data;
        continue;
    }
    $p_data = I2CE_FormStorage::lookupField('position',substr($ps_data['position'],9), array('pos_type','post','job','facility'),false);
    if (!$p_data['post']) {
        $no_post[$ps] = $ps_data;
        $j_data = I2CE_FormStorage::lookupField('job',substr($p_data['job'],4), array('title'),false);
        $f_data = I2CE_FormStorage::lookupField('facility',substr($p_data['facility'],9), array('name','health_sector'),false);
        if ($f_data['health_sector'] == 'health_sector|PU') {
            //print_r($f_data);
            //print_r($ps_data);
            $public[$ps] = $ps_data;
        } else         {
            $private[$ps] = $ps_data;
        }
    }  else {      
        //no post set, so its not imported from infinium
        $has_post[$ps] = $ps_data;
        $post_data = I2CE_FormStorage::lookupField('post',substr($p_data['post'],5), array('name','dpsm_code','salary_grade'),false);
        if ($post_data['dpsm_code']) {
            $has_dpsm[$ps] = $ps_data;
        }
    }

    
}

//echo "Want to close " .count($has_post) . " because has post\n";
echo "Want to close positions with DPSM but never vaidated = " . count($has_dpsm) . "\n";
echo "Want to close " .count($no_position) . " because has no position\n";
echo "Want to close " .count($public) . " because public sector but not DPSM code\n";
echo "Want to keep open " .count($private) . " because private sector\n";
echo "Total = " .count($pps) . "\n";

if (! simple_prompt("Found " . count($has_dpsm) . " DPDM position to close.  Does this sound correct?")) {
    die("Please try again\n");
}

$now = I2CE_Date::now(I2CE_Date::DATE)->dbFormat();
$row =0;
foreach (array($has_dpsm,$no_position,$public) as $closers) {
    foreach ( $closers as $pp_id => $data) {
        $row++;
        echo "Closing $row for person_position|$pp_id\n";
        if ( ! ( $ppObj = $ff->createContainer('person_position|'.$pp_id)) instanceof iHRIS_PersonPosition) {
            echo "Invalid person_position|$pp_id\n";
            continue;
        }
        $ppObj->populate();
        $ppObj->getField('end_date')->setFromDB($now);
        $ppObj->save($user);
        $ppObj->cleanup();
        if (!is_array($data) || !array_key_exists('position',$data) || !$data['position']) {
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

}



# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
