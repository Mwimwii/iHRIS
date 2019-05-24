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

require_once($i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'import_base.php');

$change_grades = array(
    'A1DR','A2DR','A3DR','B1','B2','B2DR','B3DR','B4','B4DR','B5','B51D','B5DR','C1','C2','C3','C4','D1','D2','D3','D4','E1','F2'
    );

$salary_grades = I2CE_FormStorage::listDisplayFields('salary_grade','name');
if (count($salary_grades) == 0) {
    usage("No Salary_Grades");
}
$sal_grade_formids = array();
foreach ($salary_grades as $id=>$salary_grade) {
    $sal_grade_formids[ 'salary_grade|' . $id ] = trim(strtoupper($salary_grade['name']));
}


$move = null;
foreach ($change_grades as $salgrade) {
    $ph = 'PLACEHOLDER-' . $salgrade;
    if ( ($sal_grade_formid = array_search($salgrade,$sal_grade_formids)) === false) {
        echo "Salaray grade $salgrade was not found\n";
        continue;
    }
    if ( ($ph_formid = array_search($ph,$sal_grade_formids)) === false) {
        echo "Salaray grade $ph was not found\n";
        continue;
    }
    $where = array(
        'operator'=>'FIELD_LIMIT',
        'field'=>'salary_grade',
        'style'=>'equals',
        'data'=>array(
            'value'=>$ph_formid
            )
        );

    $jobs = I2CE_FormStorage::search('job',false,$where);
    if (!prompt("Have " . count($jobs)  . " with Salary Grade $ph.  Should I attempt to move them to $salgrade?",$move)) {
        continue;
    }
}






# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
