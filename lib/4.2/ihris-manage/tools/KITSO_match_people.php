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




if ( ! ($mdb_export = trim(`which mdb-export`))) {
    echo "sudo apt-get install mdb-tools\n";
    die();
}

$src = getcwd() . "/local/KITSO.mdb";
$ddir = getcwd() . "/local";

if (!is_readable($src)) {
    die( "Can't read $src\n");
}

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


$needed_headers = array(
    'tblzCourseCode'=>array('CourseCodeID','CourseCode','CourseDescription','ExamOffered','PrintLetter','PrintCertificate','TrainingFocusID'),
    'tblzTrainingFocus'=>array('TrainingFocus','TrainingFocusID'),
    'tblTraining'=>array('Venue','TrainingID','DateFrom','DateTo','CourseCodeID'),
    'tblzCountry'=>array('CountryID', 'Country','CountryCode'),
    'tblTrainingParticipantDetail'=>array('TrainingParticipantDetailID','Organisation','JobTitle','HealthSectorID','PostalAddress','TownCity','Telephone','CellPhone','Fax','Email'),
    'tblTrainingParticipant'=>array('TrainingParticipantID','TrainingID','ParticipantID'),
    'tblTrainingTrainer'=>array('TrainingTrainerID', 'TrainingID','TrainerID'),
    'tblParticipant'=>array('ParticipantID','Firstname','Middlename','Surname','IDTypeID','IDNo'),
    'tblzSponsor'=>array('SponsorID','Sponsor','SponsorCode','ContactPerson','PostalAddress','Town','Telephone','Fax','Email'),
    'tblzHealthSector'=>array('HealthSector','HealthSectorID'),
    'tblTrainerCourse'=>array('TrainerCourseID','TrainerID','CourseCodeID'),
    'tblTrainer'=>array('Firstname','Middlename','Surname','CountryID','IDNo','IDTypeID'),
    'tblzIDType'=>array('IDTypeID','IDType'),
    'tblExamResult'=>array('TrainingParticipantID','PretestScore','Score1','Remark1'),
    'tblzExamRemarks'=>array('ExamRemarkID','ExamRemark')


    );


$training_merges = array(
    'CourseCodeID'=>'tblzCourseCode',
    'TrainingFocusID'=>'tblzTrainingFocus',
    '?SponsorID'=>'tblzSponsor',
    '?IDTypeID'=>'tblzIDType'
    );

$participant_merges = array(
    'TrainingParticipantDetailID'=>'tblTrainingParticipantDetail',
    '?HealthSectorID'=>'tblzHealthSector',
    );


function lookupRow($table,$lookup_col,$lookup_val) {
    global $table_data;
    global $needed_headers;
    global $headers;
    if (!array_key_exists($table,$table_data)) {
        die("Lookup bad table $table\n");
        return false;
    }
    if ($lookup_col[0] == '?') {
        $lookup_col = substr($lookup_col,1);
    }
    foreach ($table_data[$table] as $row) {
        if (!array_key_exists($lookup_col,$row)) {
            echo "Bad column $lookup_col in $table\n";
            return false;
        }
        if ( $row[$lookup_col] == $lookup_val) {
            return $row;
        }
    }            
    return false;
}

function lookupAllRows($table,$lookup_col,$lookup_val) {
    global $table_data;
    global $needed_headers;
    global $headers;
    if (!array_key_exists($table,$table_data)) {
        die("Lookup bad table $table\n");
        return false;
    }
    $rows = array();
    foreach ($table_data[$table] as $row) {
        if ( $row[$lookup_col] == $lookup_val) {
            $rows[] =  $row;
        }
    }            
    return $rows;
}


function lookupVal($table,$lookup_col,$lookup_val,$return_col) {
    global $table_data;
    global $needed_headers;
    global $headers;
    if ( ($row  = lookupRow($table,$lookup_col,$lookup_val)) === false) {
        return false;
    }
    return $row[$return_col];    
}

$tables = array_keys($needed_headers);
$table_data = array();
foreach($tables as $table) {
    if (! ($mp =fopen("php://temp","w+"))) {
        die("Could not open temporary mem area");
    }
    fwrite($mp, shell_exec("$mdb_export -d , $src $table")); 
    rewind($mp);
    $data = array();
    while (($row = fgetcsv($mp, 4000, ",")) !== FALSE) {
        $data[] = $row;
    }
    fclose($mp);
    if (count($data) < 2) {
        //index 0 is the header row.
        die( "No data in $table\n");
    }
    $headers = array_shift($data);
    if ((count($diff = array_diff($needed_headers[$table],$headers)))> 0) {
        die("Table $table is missing headers:\n\t" . implode(",", $diff) . "\n");
    }
    $keyed_data = array();
    foreach($data as $row) {
        $keyed_data_row = array();
        foreach ($headers as $index=>$header) {
            $keyed_data_row[$header] = $row[$index];
        }
        $keyed_data[] = $keyed_data_row;
    }
    $table_data[$table] = $keyed_data;
    // print_r(array_slice($keyed_data,0,4));
    // die();
}


function substituteRow(&$row,$replace_col,$table, $lookup_table_col = null) {
    if ($lookup_table_col === null) {
        $lookup_table_col = $replace_col;
    }
    if ($replace_col[0] == '?') {
        $optional = true;
        $replace_col = substr($replace_col,1);
    } else {
        $optional = false;
    }
    if (!array_key_exists($replace_col,$row)) {
        if ($optional ) {
            return true;
        } else {
            echo( "Bad Replace Column $replace_col");
            return false;
        }
    }
    if (! ($merge = lookupRow($table,$lookup_table_col,$row[$replace_col]))) {
        if (!$optional) {
            echo("Cant find $lookup_table_col in $table with val  " . $row[$replace_col] . "\n");
            return false;
        } else {
            return true;
        }
    }
    $row = array_merge($row,$merge);
    return true;
}


foreach ($table_data as $table=>$data) {
    echo $table . ' has '. count($data) . ' rows' . "\n";
}


$double_match = 0;
$no_match = 0;
$match = 0;
$have_id = 0;
$have_id_match = 0;
$have_id_no_match = 0;
$have_id_double_match = 0;
$people_match  =array();
$year_no_match = array();

foreach ($table_data['tblTrainingParticipant'] as $training_participant) {
    if ( ($participant = lookupRow('tblParticipant','ParticipantID',$training_participant['ParticipantID'])) === false) {
        continue;
    }
    
    if ( ($training =  lookupRow('tblTraining','TrainingID',$training_participant['TrainingID'])) == false) {
        continue;
    }    
    $participant['TrainingParticipantDetailID'] = $training_participant['TrainingParticipantID'];
    foreach ($training_merges as $col=>$table) {
        if (!substituteRow($training,$col,$table)) {
            continue 2;
        }
    }
    foreach ($participant_merges as $col=>$table) {
        if (!substituteRow($participant,$col,$table)) {
            continue 2;
        }
    }
    
    $training['trainers'] = lookupAllRows('tblTrainerCourse','CourseCodeID',$training['CourseCodeID']);
    foreach ($training['trainers'] as &$trainer) {
        if (substituteRow($trainer,'TrainerID','tblTrainer')) {
            substituteRow($trainer,'CountryID','tblzCountry');
            substituteRow($trainer,'?IDTypeID','tblzIDType');
        }
    }
    $data=array(
        'participant'=>$participant,
        'training'=>$training
        );
    if (is_array($exam = lookupRow('tblExamResult','TrainingParticipantID',$training_participant['TrainingParticipantID']))) {
        substituteRow($exam, '?Remark1','tblzExamRemarks','ExamRemarkID');
        $data['exam'] = $exam;
    }



    //for now, we are going to simply check to see if the person is in the database alread.
    $personId = false;
    if ($participant['IDNo'] && $participant['IDTypeID'] == 1) {
        //omang number
        $have_id++;
        $where = array(
            'operator'=>'AND',
            'operand'=>array(
                0=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'id_type',
                    'style'=>'equals',
                    'data'=>array(
                        'value'=>'id_type|1'
                        )
                    ),
                1=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'id_num',
                    'style'=>'equals',
                    'data'=>array(
                        'value'=>strtoupper(trim($participant['IDNo'])),
                        )
                    )
                )
            );
        $Ids =  I2CE_FormStorage::listFields('person_id',array('parent'),false,$where);
        if (count($Ids) == 0) {
            echo "No match on Omang " . $participant['IDNo'] . "\n";
            $have_id_no_match++;
        } else  if (count($Ids) > 1) {
            echo "Multiple match on Omang " . $participant['IDNo'] . "\n";
            $have_id_double_match++;
        } else {
            $Ids = reset($Ids);
            $Ids = current($Ids);
            $personId = substr($Ids['parent'],6);
            $have_id_match++;
        }
    } 
    if (!$personId) {
        $where = array(
            'operator'=>'AND',
            'operand'=>array(
                0=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'surname',
                    'style'=>'equals',
                    'data'=>array(
                        'value'=>strtoupper(trim($participant['Surname'])),
                        )
                    ),
                1=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'firstname',
                    'style'=>'equals',
                    'data'=>array(
                        'value'=>strtoupper(trim($participant['Firstname'])),
                        )
                    )
                )
            );
        $peopleIds =  I2CE_FormStorage::search('person',false,$where);
        if (count($peopleIds) == 0) {
            $no_match++;
            echo "Person " . $participant['Surname'] . ', ' . $participant['Firstname'] . " does not match anyone. Skipping\n";
        }else if (count($peopleIds) > 1) {
            if ($participant['Middlename']) {
                $where['operand'][] = array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'othername',
                    'style'=>'equals',
                    'data'=>array(
                        'value'=>strtoupper(trim($participant['Middlename']))
                        )
                    );
                $peopleIds =  I2CE_FormStorage::search('person',false,$where);
                if (count($peopleIds) == 0) {
                    echo "Person " . $participant['Surname'] . ', ' . $participant['Firstname'] . ' ' . $participant['Middlename'] . " does not match anyone. Skipping\n";
                    $no_match++;
                } else if (count($peopleIds) > 1) {
                    echo "Person " . $participant['Surname'] . ', ' . $participant['Firstname'] . ' ' . $participant['Middlename'] . "  matches more than one person. Skipping\n";
                    $double_match++;
                }
            } else {
                echo "Person " . $participant['Surname'] . ', ' . $participant['Firstname'] . " matches more than once. Skipping\n";
                $double_match++;
            }
        }
        if (count($peopleIds) != 1) {
            reset($peopleIds);
            $personId = current($peopleIds);
        }
    }
    if (!$personId) {
        if ($training['DateFrom']) {
            $year = substr($training['DateFrom'],6,2);
            echo "\tNo match with training begining on " . $training['DateFrom'] , " (year=$year)\n";
            if (!array_key_exists($year,$year_no_match)) {
                $year_no_match[$year] = 1;
            } else {
                $year_no_match[$year]++;
            }
        }
        continue;
    }
    $match++;
    $people_match[$personId] = 1;
    print_r($data);    
}



/*       Summary Data     */

echo "The following is a table of years which number of mismatched records\n";
print_r($year_no_match);
echo "There were $have_id records with a person with a Omang\n";
echo "There were $have_id_double_match records whose associated person  by Omang is not unique\n";
echo "There were $have_id_no_match records with no matching person on Omang\n";
echo "There were $have_id_match records with a unique person matching on Omang\n";
echo "There were $double_match records whose associated person by name is not unique\n";
echo "There were $no_match records with no matching person by name or id\n";
echo "There were $match records with a unique person matching by name\n";
$tot_match = $match + $match;
echo "There were $tot_match records with a unique person matching by name or Omang\n";
echo "There were " . count($people_match) . " unique people who had a training\n";


// $needed_headers = array(
//     'tblzCourseCode'=>array('CourseCodeID','CourseCode','CourseDescription','ExamOffered','PrintLetter','PrintCertificate','TrainingFocusID'),
//     'tblzTrainingFocus'=>array('TrainingFocus','TrainingFocusID'),
//     'tblTraining'=>array('Venue','TrainingID','DateFrom','DateTo','CourseCodeID'),
//     'tblTrainingParticipantDetail'=>array('TrainingParticipantDetailID','TrainingParticipantID'),
//     'tblTrainingParticipant'=>array('TrainingParticipantID','TrainingID','ParticipantID'),
//     'tblTrainingTrainer'=>array('TrainingTrainerID', 'TrainingID','TrainerID')
//    'tblParticipant'=>array('ParticipantID','Firstname','Middlename','Surname','IDTypeId','IDNo')
//     );
