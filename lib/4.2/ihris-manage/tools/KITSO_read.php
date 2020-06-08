<?php



   /* This example shows how to open and do operations on a Microsoft Access Database using PHP odbc functions */
if ( ! ($mdb_export = trim(`which mdb-export`))) {
    echo "sudo apt-get install mdb-tools\n";
    die();
}

$src = getcwd() . "/local/KITSO.mdb";
$ddir = getcwd() . "/local";

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


require_once ($i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'CLI.php');


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

$show_training = simple_prompt("Show Trainings?");
$count_private = simple_prompt("Only count locations");
$locations = array('Not Set'=>0);
foreach ($table_data['tblTrainingParticipant'] as $training_participant) {
    if ( ($participant = lookupRow('tblParticipant','ParticipantID',$training_participant['ParticipantID'])) === false) {
        continue;
    }
    $participant['TrainingParticipantDetailID'] = $training_participant['TrainingParticipantID'];
    foreach ($participant_merges as $col=>$table) {
        if (!substituteRow($participant,$col,$table)) {
            continue 2;
        }
    }
    if (!array_key_exists('HealthSector',$participant)) {
        $locations['Not Set']++;
    } else if (! array_key_exists($participant['HealthSector'] , $locations)) {
        $locations[$participant['HealthSector']] = 1;
    } else {
        $locations[$participant['HealthSector']]++;
    }
    if ($count_private) {
        continue;
    }
    if (!$show_training) {
        print_r($participant);
        ask("Press enter for next record");
        continue;
    }


    if ( ($training =  lookupRow('tblTraining','TrainingID',$training_participant['TrainingID'])) == false) {
        continue;
    }

    

    foreach ($training_merges as $col=>$table) {
        if (!substituteRow($training,$col,$table)) {
            continue 2;
        }
    }
    substituteRow($training,'CourseCodeID','tblCourseExamCategory','CourseID');

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
    print_r($data);
}
echo "have working in:\n" . print_r($locations,true) . "\n";
// $needed_headers = array(
//     'tblzCourseCode'=>array('CourseCodeID','CourseCode','CourseDescription','ExamOffered','PrintLetter','PrintCertificate','TrainingFocusID'),
//     'tblzTrainingFocus'=>array('TrainingFocus','TrainingFocusID'),
//     'tblTraining'=>array('Venue','TrainingID','DateFrom','DateTo','CourseCodeID'),
//     'tblTrainingParticipantDetail'=>array('TrainingParticipantDetailID','TrainingParticipantID'),
//     'tblTrainingParticipant'=>array('TrainingParticipantID','TrainingID','ParticipantID'),
//     'tblTrainingTrainer'=>array('TrainingTrainerID', 'TrainingID','TrainerID')
//    'tblParticipant'=>array('ParticipantID','Firstname','Middlename','Surname','IDTypeId','IDNo')
//     );
