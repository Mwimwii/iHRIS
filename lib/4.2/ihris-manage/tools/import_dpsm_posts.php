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
I2CE::raiseError("Connecting to DB");
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

I2CE::raiseError("Connected to DB");




require_once($i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'CLI.php');


$user = new I2CE_User();

require_once($i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'import_base.php');
if (count($arg_files) != 1) {
    usage("Please specify the name of an  .CSV file to import");
}
reset($arg_files);
$file = current($arg_files);
if($file[0] == '/') {
    $file = realpath(current($arg_files));
} else {
    $file = realpath($dir. '/' . $file);
}

if (!is_readable($file) || ($fp = fopen($file,"r")) === false) {
    usage("Please specify the name of a .CSV spreadsheet to import: " . $file . " is not readable");
}






$in_file_sep = false;
foreach (array("\t",",") as $sep) {
    $headers = fgetcsv($fp, 4000, $sep);
    if ( $headers === FALSE|| count($headers) < 3) {
        fseek($fp,0);
        continue;
    }
    $in_file_sep = $sep;
}
if (!$in_file_sep) {
    die("Could not get headers\n");
}


echo "Using the following headers:\n\t" . implode(",",$headers) . "\n";

$cols = array(
    'department'=>false,
    'division'=>false,
    'salary_scale'=>false,
    'dpsm_code'=>false,
    'post_name'=>false,
    'post_group'=>false
    );

$required_cols = array(
    'department'=>true,
    'division'=>true,
    'salary_scale'=>false,
    'dpsm_code'=>true,
    'post_name'=>true,
    'post_group'=>true
    );


$default_cols = array(
    'department'=>0,
    'division'=>1,
    'salary_scale'=>2,
    'dpsm_code'=>3,
    'post_name'=>4,
    'post_group'=>5
    );


$default_cols_text = array();
foreach ($default_cols as $col=>$i) {
    $default_cols_text[ $headers[$i]] = $col;
}
$mapped_data_blank = array();
if (simple_prompt("Use this mapping for the columns:" . print_r($default_cols_text,true))) {
    $cols = $default_cols;
} else {
    foreach ($cols as $col=>$index) {
        $msg = "Which column is $col?";
        $cols[$col] = chooseMenuIndex($msg,$headers);
        $mapped_data_blank[$col] = false;
    }
}


$bad_codes = array();
$added_dpsm_codes = array();
foreach (I2CE_FormStorage::listFields('post',array('dpsm_code')) as $id=>$data) {
    if (!array_key_exists('dpsm_code',$data) || !$data['dpsm_code']) {
        continue;
    }
    $added_dpsm_codes[$id] = strtoupper($data['dpsm_code']);
}
I2CE::raiseError("Existing DPSM codes:\n\t" . implode(",",$added_dpsm_codes));

$added_salary_grades = array();
foreach (I2CE_FormStorage::listFields('salary_grade',array('name')) as $id=>$data) {
    if (!array_key_exists('name',$data) || !$data['name']) {
        continue;
    }
    $added_salary_grades[$id] = strtoupper($data['name']);
}
I2CE::raiseError("Existing salary grades:\n\t" . implode(",",$added_salary_grades));


$added_post_groups = array();
foreach (I2CE_FormStorage::listFields('post_group',array('name')) as $id=>$data) {
    if (!array_key_exists('name',$data) || !$data['name']) {
        continue;
    }
    $added_post_groups[$id] = strtoupper($data['name']);
}
I2CE::raiseError("Existing post_groups :\n\t" . implode(",",$added_post_groups));

$added_cs = array();
foreach (I2CE_FormStorage::listFields('cs_dept',array('name')) as $id=>$data) {
    if (!array_key_exists('name',$data) || !$data['name']) {
        continue;
    }
    $added_cs[$id] = strtoupper($data['name']);
}
I2CE::raiseError("Existing corp. service establishment departments :\n\t" . implode(",",$added_cs));


$added_dpsms = array();
foreach (I2CE_FormStorage::listFields('dpsm_dept',array('name')) as $id=>$data) {
    if (!array_key_exists('name',$data) || !$data['name']) {
        continue;
    }
    $added_dpsms[$id] = strtoupper($data['name']);
}
I2CE::raiseError("Existing DPSM departments :\n\t" . implode(",",$added_dpsms));


$ff = I2CE_FormFactory::instance();
$row = 0;
$continue = null;
while (($data = fgetcsv($fp)) !== FALSE) {
    $row++;
    $mapped_data = $mapped_data_blank;
    $bad = false;

    foreach ($cols as $col=>$index) {
        if (!array_key_exists($index,$data)) {
            I2CE::raiseError("Required $col [$index]  not present in row $row:\n" . print_r($data,true) );
            $bad = true;
            break;
        }
        $mapped_data[$col] = trim($data[$index]);
    }
    if (!$bad) {
        foreach ($required_cols as $col=>$required) {
            if (!$required) {
                continue;
            }
            if (!$mapped_data[$col]) {
                I2CE::raiseError("Required $col's value not present in row $row:\n" . print_r($mapped_data,true));
                $bad = true;
                break;
            }
        }
    }
    if ($bad) {
        I2CE::raiseError("Row $row is bad");
        if ($mapped_data['dpsm_code']) {
            $bad_codes[] = $mapped_data['dpsm_code'];
        }
        continue;
    }
    if (in_array($mapped_data['dpsm_code'],$added_dpsm_codes)) {
        I2CE::raiseError("Skipping " . $mapped_data['dpsm_code'] . " as it is already in the system");
        continue;
    }

    $has_dr =false;
    $grade = false;
    $mapped_data['salary_scale']= strtoupper($mapped_data['salary_scale']);
    $sals = array();
    foreach (preg_split('//',$mapped_data['salary_scale'], -1, PREG_SPLIT_NO_EMPTY) as $i=>$char) {
        switch ($char) {
        case 'A':
        case 'B':
        case 'C':
        case 'E':
        case 'F':
        case 'G':
        case 'T':
            $grade = $char;
            break;
        case 'D':
            if (($i < strlen($mapped_data['salary_scale']) -1) && ( $mapped_data['salary_scale'][$i+1] == 'R')) {
                $has_dr = true;
                break 2;
            } else {
                $grade = 'D';
                break;
            }
        case 'R':
            if ($grade == 'D') {
                
            }
            break;
        case '0':
        case '1':
        case '2':
        case '3':
        case '4':
        case '5':
        case '6':
        case '7':
        case '8':
        case '9':
            if (!$grade) {
                I2CE::raiseError("Bad grade in " . $mapped_data['dpsm_code']);
                break 2;
            }
            $sals[] = $grade . $char;
            break;
        default:
            break;
        }
    }
    $sal_ids = array();
    foreach ($sals as $sal) {
        if ( ($sal_id = array_search($sal,$added_salary_grades)) ===  false) {
            if (!simple_prompt("The salary grade $sal is not in the system.  Add it?"))  {
                continue ;
            }
            $salObj = $ff->createContainer('salary_grade');
            $salObj->getField('name')->setValue($sal);
            $salObj->save($user);
            $sal_id = $salObj->getID();
            $added_salary_grades[$sal_id] = $sal;
        }
        $sal_ids[] = array('salary_grade',$sal_id);
    }
    
    $pg_id =false;
    $mapped_data['post_group'] = strtoupper($mapped_data['post_group']);
    if ( ($pg_id = array_search($mapped_data['post_group'],$added_post_groups)) ===  false) {
        if (simple_prompt("The post group "  . $mapped_data['post_group']. " is not in the system.  Add it?")) {
            $pgObj = $ff->createContainer('post_group');
            $pgObj->getField('name')->setValue($mapped_data['post_group']);
            $pgObj->save($user);
            $pg_id = $pgObj->getID();
            $added_post_groups[$pg_id] = $mapped_data['post_group'];
        }
    }


    $cs_id =false;
    $mapped_data['division'] = strtoupper($mapped_data['division']); //cs dept
    $mapped_data['department'] = strtoupper($mapped_data['department']); //dpsm dept    
    if ( ($cs_id = array_search($mapped_data['division'],$added_cs)) ===  false) {
        if (simple_prompt("The corp. serv. establishemnt department  " . $mapped_data['division'] . " is not in the system.  Add it?"))  {
            $dpsm_id =false;
            if ( ($dpsm_id = array_search($mapped_data['department'],$added_dpsms)) ===  false) {
                if (simple_prompt("The dpsm department " .  $mapped_data['department'] . " is not in the system.  Add it?"))  {
                    $dpsmObj = $ff->createContainer('dpsm_dept');
                    $dpsmObj->getField('name')->setValue($mapped_data['department']);
                    $dpsmObj->save($user);
                    $dpsm_id = $dpsmObj->getID();
                    $added_dpsms[$dpsm_id] = $mapped_data['department'];
                }
            }
            
            if (!$dpsm_id) {
                I2CE::raiseError("Skipping row $row because could not map to a dpsm department");
                $bad_codes[] = $mapped_data['dpsm_code'];
                continue;
            }
            $csObj = $ff->createContainer('cs_dept');
            $csObj->getField('name')->setValue($mapped_data['division']);
            $csObj->getField('dpsm_dept')->setValue(array('dpsm_dept',$dpsm_id));
            $csObj->save($user);
            $cs_id = $csObj->getID();
            $added_cs[$cs_id] = $mapped_data['division'];
        }
    }
    
    if (!$cs_id) {
        I2CE::raiseError("Skipping row $row because could not map to a corportate services/establishment department");
        $bad_codes[] = $mapped_data['dpsm_code'];
        continue;
    }

    
    $postObj = $ff->createContainer('post');
    $postObj->getField('name')->setValue(strtoupper($mapped_data['post_name']));
    $postObj->getField('dpsm_code')->setValue( $mapped_data['dpsm_code']);
    if ($has_dr) {
        $postObj->getField('dr')->setValue(1);
    }
    $postObj->getField('salary_grade')->setValue($sal_ids);
    if ($pg_id) {
        $postObj->getField('post_group')->setValue(array('post_group',$pg_id));
    }    
    
    $postObj->getField('cs_dept')->setValue(array('cs_dept',$cs_id));
    $postObj->save($user);

    if (!prompt("Processed " . implode(",",$mapped_data) . ". Do the next record?",$continue)) {
        break;
    }
}

if (count($bad_codes)>0) {
    I2CE::raiseError("Could not import the following DPSM codes:\n" . implode(",",$bad_codes));
}


exit(0);






# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
