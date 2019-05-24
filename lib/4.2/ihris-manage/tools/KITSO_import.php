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
putenv('nocheck=1');
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
if (count($arg_files) != 1) {
    usage("Please specify the name of a spreadsheet to process");
}

reset($arg_files);
$src = current($arg_files);
if($src[0] == '/') {
    $src = realpath($src);
} else {
    $src = realpath($dir. '/' . $src);
}
if (!is_readable($src)) {
    usage("Please specify the name of a spreadsheet to import: " . $src . " is not readable");
}

I2CE::raiseMessage("Loading from $src");


$ddir = getcwd() . "/local";

if (!is_readable($src)) {
    die( "Can't read $src\n");
}





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
    'tblzExamRemarks'=>array('ExamRemarkID','ExamRemark'),
    'tblCourseExamCategory'=>array('CourseID','PassMark')


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
    shell_exec("$mdb_export -d , $src $table > /tmp/$table.csv"); 
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

$sub_errors =array();
//subrow($particpant,'TrainingParticipantDetailID,tblTrainingParticipantDetail');

function substituteRow(&$row,$replace_col,$table, $lookup_table_col = null) {
    global $sub_errors;
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
            I2CE::raiseError("Cant find $lookup_table_col in $table with val  " . $row[$replace_col] . "\n");
            if (!array_key_exists($table,$sub_errors)) {
                $sub_errors[$table] = 1;
            } else {
                $sub_errors[$table]++;
            }
            return false;
        } else {
            return true;
        }
    }
    $row = array_merge($row,$merge);
    return true;
}

//get the maximum TPID.
$existing = I2CE_FormStorage::listFields('person_scheduled_training_course',array('kitso'));
foreach ($existing as $i=>&$data) {
    if (!is_array($data) || !array_key_exists('kitso',$data) || !is_numeric($data['kitso'])) {
        unset($existing[$i]);
        continue;
    }
    $data = $data['kitso'];
}



foreach ($table_data as $table=>$data) {
    echo $table . ' has '. count($data) . ' rows' . "\n";
}



$ff = I2CE_FormFactory::instance();
$user = new I2CE_User();

//first make sure training_KTCU
$ensure_forms = array( 
    array('training_course_institution','KITSO','KITSO'),
    array('training_institution','KTCU','KTCU')
    );

$kitso_ids = array();

foreach ($ensure_forms as $ensure_form) {
    list($form,$id,$name) = $ensure_form;    
    $where = array(
        'operator'=>'FIELD_LIMIT',
        'field'=>'name',
        'style'=>'equals',
        'data'=>array(
            'value'=>$name
            )
        );        
    $ids = I2CE_FormStorage::search($form,false,$where);
    if (count($ids) > 0) {
        $kitso_ids[$form] =current($ids);
        I2CE::raiseError("Found $form|$id");
        continue;
    }
    I2CE::raiseError("Creating $form|$id");
    $formObj = $ff->createContainer($form);
    if (! ($fieldObj = $formObj->getField('name')) instanceof I2CE_FormField) {
        continue;
    }
    $fieldObj->setFromDB($name);
    //    $formObj->setID($id);
    $formObj->save($user);
    $kitso_ids[$form] = $formObj->getID();
}

print_r($kitso_ids);  



$save_count = 0;
$double_match = 0;
$no_match = 0;
$match = 0;
$have_id = 0;
$have_id_match = 0;
$have_id_no_match = 0;
$have_id_double_match = 0;
$people_match  =array();
$year_no_match = array();
$test_mode = simple_prompt("Run in test mode (do not save any records)?");
$save_continue = null;
$dotted = false;

$continue = null;

foreach ($table_data['tblTrainingParticipant'] as $training_participant) {
    if (!prompt("Process row?",$continue)) {
      break;
    }   
    //echo "\n\nNEW ROW\n";
    //print_r($training_participant);
    $where = array(
        'operator'=>'FIELD_LIMIT',
        'field'=>'kitso',
        'style'=>'equals',
        'data'=>array(
            'value'=>$training_participant['TrainingParticipantID']
            )
        );        
    if (in_array($training_participant['TrainingParticipantID'] ,$existing)) {
        $dotted = true;
        echo ".";
        continue;
    } 
    if ($dotted) {
        echo "\n";
    }
    $dotted = false;
    if (count($person_scheduled_training_ids = I2CE_FormStorage::search('person_scheduled_training_course',false,$where)) > 0) {
        echo "\tSkipping TPID " .  $training_participant['TrainingParticipantID'] . "\n";
        //see if we have entered this trainingparitcipant id yet
        continue;
    }

    if ( ($participant = lookupRow('tblParticipant','ParticipantID',$training_participant['ParticipantID'])) === false) {
        continue;
    }
    //echo "particpant=" . print_r($participant,true) . "\n";    
    if ( ($training =  lookupRow('tblTraining','TrainingID',$training_participant['TrainingID'])) == false) {
        continue;
    }    
    substituteRow($training,'?CourseCodeID','tblCourseExamCategory','CourseID');

    //echo "training=" . print_r($training,true) . "\n";    
    $participant['TrainingParticipantDetailID'] = $training_participant['TrainingParticipantID'];
    foreach ($training_merges as $col=>$table) {
        if (!substituteRow($training,$col,$table)) {
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
    //we have all the course info


    //print_r($participant);
    // foreach ($participant_merges as $col=>$table) {
    //     if (!substituteRow($participant,$col,$table)) {
    //         continue 2;
    //     }
    // }
    
    if (!$training['TrainingFocus'] || !$training['CourseDescription'] || !$training['DateFrom'] || !$training['DateTo']) {
        echo "\tSkipping b/c no TF or CourseDec or no DateFrom or no DateTo\n";
        continue;
    }
    if (preg_match('/([0-9]+)\/([0-9]+)\/([0-9]+)/',$training['DateFrom'],$start_comps)) {
        array_shift($start_comps);
        $start_comps = array('day'=>$start_comps[1],'month'=>$start_comps[0],'year'=>'20' . $start_comps[2]);
    } else {
        echo "\tSkipping on bad date from\n";
        continue;
    }
    if (preg_match('/([0-9]+)\/([0-9]+)\/([0-9]+)/',$training['DateTo'],$end_comps)) {
        array_shift($end_comps);
        $end_comps = array('day'=>$end_comps[1],'month'=>$end_comps[0],'year'=>'20' .  $end_comps[2]);
    } else {
        echo "\tSkipping on bad date to\n";
        continue;
    }
    $eval_comps = false;
    if (array_key_exists('EffectiveDate',$training) && $training['EffectiveDate'] && preg_match('/([0-9]+)\/([0-9]+)\/([0-9]+)/',$training['EffectiveDate'],$eval_comps)) {
        array_shift($eval_comps);
        $eval_comps = array('day'=>$eval_comps[1],'month'=>$eval_comps[0],'year'=>'20' . $eval_comps[2]);
    }


    $data=array(
        'participant'=>$participant,
        'training'=>$training
        );
    if (is_array($exam = lookupRow('tblExamResult','TrainingParticipantID',$training_participant['TrainingParticipantID']))) {
        substituteRow($exam, '?Remark1','tblzExamRemarks','ExamRemarkID');
        $data['exam'] = $exam;
    } else {
        $data['exam'] = false;
    }


    $data=array(
        'participant'=>$participant,
        'training'=>$training
        );
    if (is_array($exam = lookupRow('tblExamResult','TrainingParticipantID',$training_participant['TrainingParticipantID']))) {
        substituteRow($exam, '?Remark1','tblzExamRemarks','ExamRemarkID');
        $data['exam'] = $exam;
    } else {
        $data['exam'] = false;
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
            //echo "No match on Omang " . $participant['IDNo'] . "\n";
            $have_id_no_match++;
        } else  if (count($Ids) > 1) {
            //echo "Multiple match on Omang " . $participant['IDNo'] . "\n";
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
            //echo "Person " . $participant['Surname'] . ', ' . $participant['Firstname'] . " does not match anyone. Skipping\n";
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
                    //echo "Person " . $participant['Surname'] . ', ' . $participant['Firstname'] . ' ' . $participant['Middlename'] . " does not match anyone. Skipping\n";
                    $no_match++;
                } else if (count($peopleIds) > 1) {
                    //echo "Person " . $participant['Surname'] . ', ' . $participant['Firstname'] . ' ' . $participant['Middlename'] . "  matches more than one person. Skipping\n";
                    $double_match++;
                }
            } else {
                //echo "Person " . $participant['Surname'] . ', ' . $participant['Firstname'] . " matches more than once. Skipping\n";
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
            //echo "\tNo match with training begining on " . $training['DateFrom'] , " (year=$year)\n";
            if (!array_key_exists($year,$year_no_match)) {
                $year_no_match[$year] = 1;
            } else {
                $year_no_match[$year]++;
            }
        }
        //continue;
    } else {
        echo "Have a record for person|$personId on " . $training_participant['TrainingParticipantID'] . "\n";
        $match++;
        $people_match[$personId] = 1;
    }

    //Create all objects
    $training = false;
    $sched_training = false;
    $cat = false;
    $exam = false;
    $where = array(
        'operator'=>'FIELD_LIMIT',
        'field'=>'kitso',
        'style'=>'equals',
        'data'=>array(
            'value'=>$training_participant['TrainingID']            
            )
        );        

    if (count($scheduled_training_ids = I2CE_FormStorage::search('scheduled_training_course',false,$where))> 0) {
        reset($scheduled_training_ids);
        $sched_training_id = current($scheduled_training_ids);
    } else {
        //we need to create an new scheduled training course and possible a new training couse
        $where = array(
            'operator'=>'AND',
            'operand'=>array(
                0=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'training_institution',
                    'style'=>'equals',
                    'data'=>array(
                        'value'=> "training_instituion|" . $kitso_ids['training_institution']
                        ),
                    ),
                1=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'name',
                    'style'=>'equals',
                    'data'=>array(
                        'value'=> $data['training']['CourseDescription']            
                        )
                    )
                )
            );
        //first we need to see if we have a training course
        if (count($training_ids = I2CE_FormStorage::search('training_course',false,$where))> 0) {        
            reset($training_ids); 
            $training_id =  current($training_ids);
        } else {
            //we need to create this training course
            $training = $ff->createContainer('training_course');
            $training->training_course_status = array('training_course_status','open');
            $training->training_institution = array('training_institution',$kitso_ids['training_institution']);
            $training->getField('name')->setValue($data['training']['CourseDescription']);            
            $training->topic = $data['training']['CourseDescription'];
            $where = array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'name',
                'style'=>'equals',
                'data'=>array(
                    'value'=>$data['training']['TrainingFocus']
                    )
                );        
            
            if (count($cat_ids = I2CE_FormStorage::search('training_course_category',false,$where))> 0) {
                reset($cat_ids);
                $cat_id =   current($cat_ids);
            } else {
                $cat = $ff->createContainer('training_course_category');
                $cat->name = $data['training']['TrainingFocus'];
                echo "\tCreateing Training Focus " . $cat->name . "\n";
            }
            echo "\tCreating Training " . $training->name . "\n";
        }
        //now we have our training course.  need to create scheduled training course
        $sched_training = $ff->createContainer('scheduled_training_course');
        $sched_training->training_course_institution = array('training_course_institution',        $kitso_ids['training_course_institution']);
        $sched_training->kitso = $training_participant['TrainingID'];
        echo "\tSheduling Training " . implode('/',$start_comps) . ' to ' . implode('/',$end_comps) . "\n";
        $sched_training->getField('start_date')->setFromPost($start_comps);
        $sched_training->getField('end_date')->setFromPost($end_comps);
        if (count($data['training']['trainers']) > 0) {
            $trainers = array();
            echo "\tHave Trainers(s)\n";
            foreach ($data['training']['trainers']  as $trainer_data) {
                $trainers[] = $trainer_data['Firstname'] . ' ' . $trainer_data['Surname'];
            }
            $sched_training->instructors=implode("\n",$trainers);
        }
    }


    if ($personId) {
        if (!($person = $ff->createContainer('person|' . $personId)) instanceof iHRIS_Person) {
            echo "\tbad person $person";
            continue;
        }

        $personScheduledTrainingCourse = $ff->createContainer('person_scheduled_training_course');    
        $personScheduledTrainingCourse->setParent($person);
        $personScheduledTrainingCourse->getField('request_date')->setFromPost($start_comps);
        $personScheduledTrainingCourse->completed = 1;
        $personScheduledTrainingCourse->retraining = 0;
        $personScheduledTrainingCourse->kitso = $training_participant['TrainingParticipantID'];

        if  ($data['exam'] && array_key_exists('Score1',$data['exam']) && $data['exam']['Score1']    && array_key_exists('PassMark',$data['training']) && $data['training']['PassMark']) {
            echo "\thave exam\n";
            if ( $data['exam']['Score1'] >= $data['training']['PassMark']) {
                $personScheduledTrainingCourse->training_course_evaluation = array('training_course_evaluation','pass');
            } else {
                $personScheduledTrainingCourse->training_course_evaluation = array('training_course_evaluation','fail');
            }
            $exam = $ff->createContainer('training_course_exam');
            if ($eval_comps) {
                $exam->getField('evaluation_date')->setFromPost($eval_comps);
            }
            $exam->training_course_exam_type = array('training_course_exam_type','final');
            $exam->score = $data['exam']['Score1'];
        } else {
            $personScheduledTrainingCourse->training_course_evaluation = array('training_course_evaluation','not_evaluated');
        }
    }


    if ($test_mode) {
        echo "\tCan save record\n";
        $save_count++;
        if ($cat) {
            $cat->cleanup();
        }
        if ($training) {
            $training->cleanup();
        }
        if ($sched_training) {
            $sched_training->cleanup();
        }
        if ($exam) {
            $exam->cleanup();
        }
        $personScheduledTrainingCourse->cleanup();
        $person->cleanup();
        continue;
    }
    //save all objects
    if ($cat) {
        $cat->save($user);
        $cat_id = $training->getId();
        $cat->cleanup();
    }
    if ($training) {
        echo "\tCat " . implode('|',array('training_course_category',$cat_id)) . "\n";
        $training->training_course_category = array('training_course_category',$cat_id);
        $training->save($user);
        $training_id = $training->getId();
        $training->cleanup();
    }
    if ($sched_training) {
        echo "\tTraining " . implode('|',array('training_course',$training_id)) . "\n";
        $sched_training->training_course = array('training_course',$training_id);
        $sched_training->save($user);
        $sched_training_id = $sched_training->getId();
        $sched_training->cleanup();
    }
    echo "\tSchuledcourse " . implode('|',array('scheduled_training_course',$sched_training_id)) . "\n";
    if ($personId) {
        $personScheduledTrainingCourse->scheduled_training_course = array('scheduled_training_course',$sched_training_id);
        $personScheduledTrainingCourse->save($user);
        if ($exam) {
            $exam->setParent($personScheduledTrainingCourse);
            $exam->save($user);
            $exam->cleanup();
        }
        echo "\tPersonScheduled " . $personScheduledTrainingCourse->getNameID() . "\n";
        $personScheduledTrainingCourse->cleanup();
        $person->cleanup();
        if (!prompt("Save Next Record?", $save_continue)) {
            die();
        }
    }
}



/*       Summary Data     */

echo "The following is a table of years which number of mismatched records\n";
ksort($year_no_match);
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
echo "These are the table lookup/substitution errors:\n";
print_r($sub_errors);

