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

//ini_set('memory_limit','4G');

include_once('PHPExcel/PHPExcel.php'); 
  if (!class_exists('PHPExcel',false)) {
      usage("You must have PHPExcel installed to load excel spreadsheets");
  }
        
echo "allocated peak memory to PHP is ".memory_get_usage()."\n";

if (count($arg_files) != 1) {
    usage("Please specify the name of a directory");
}

reset($arg_files);
$dir = current($arg_files);


$files = glob("$dir/*xlsm");

echo "2. allocated peak memory to PHP is ".memory_get_peak_usage()."\n";

$filenames_to_ihris_districts = array(
    '04Bobirwa' => 'BOBIRWA',
    '13Boteti' => 'BOTETI',
    '28Charleshill' => 'CHARLESHILL SUB-DISTRICT',
    '10Chobe' => 'CHOBE',
    '16Francistown' => 'FRANCISTOWN',
    '07Gantsi' => 'GABORONE',
    '23Good Hope' => 'GANTSI',
    '15Greater Gaborone' => 'GOODHOPE',
    '22Jwaneng Town' => 'JWANENG',
    '24Kgalagadi North' => 'KGALAGADI NORTH',
    '11Kgalagadi South' => 'KGALAGADI SOUTH',
    '09Kgatleng' => 'KGATLENG',
    '05Kweneng East' => 'KWENENG EAST',
    '20Kweneng West' => 'KWENENG WEST',
    '18Lobatse' => 'LOBATSE',
    '21Mabutsane' => 'MABUTSANE SUB-DISTRICT',
    '08Mahalapye' => 'MAHALAPYE',
    '26Moshupa' => 'MOSHUPA SUB-DISTRICT',
    '01Ngamiland' => 'NGAMILAND',
    '02North East' => 'NORTH EAST',
    '14Okavango' => 'OKAVANGO',
    '03Palapye' => 'PALAPYE',
    '19Selibe-Phikwe' => 'SELIBE PHIKWE',
    '29Serowe' => 'SEROWE',
    '17South East' => 'SOUTH EAST',
    '06Southern Kanye' => 'SOUTHERN',
    '27Tlokweng' => 'TLOKWENG SUB-DISTRICT',
    '25Tonota' => 'TONOTA SUB-DISTRICT',
    '12Tutume' => 'TUTUME',
    '30MofH' => 'MoH'
  );

//if( !findDistrictByName('') instanceof iHRIS_District )

$personObj = findPersonByOmang(494510805, '06/21/2017', $user);
updatePerson($personObj,'','Hildebrand',$user);
updatePersonPosition($personObj,'Kiddalis','Warra', $user);

/*foreach( $filenames_to_ihris_districts as $key => $value ){
  mkdir('/home/sovello/botswana/districts/'.$value);
  
  for($i=0; $i<count($files); $i++ ){
    echo "searching for $key in ".$files[$i]."\n";
    if( strpos($files[$i], $key) !== false ){
        echo "found district $value in ".basename($files[$i])."\n";
        
        if( !rename( $files[$i], '/home/sovello/botswana/districts/'.$value.'/'.basename( $files[$i] )) ){
            rename( $files[$i], '/home/sovello/botswana/no_district/'.basename( $files[$i] ) );
            echo "Error => Failed to copy file ".basename( $files[$i] )." copied to notfound\n";
          }else{ echo "Copied file ".basename( $files[$i] )."\n"; }
        }
    else{
        echo "district $value not found\n";
        }
      
    
  }}
  
  
/*
foreach( $files as $file ){
    echo "District $ihris_district has files \n";
    for( $i=0 ; $i<count($subfiles); $i++ ){
      if( strpos($subfiles[$i], $key ) === true ){
          echo $subfiles[$i]."\n";
        }
    }           
}

//$position = loadPositions();

/*
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
    */
        

    /*/echo "there are ".count($position)." positions to be imported\n";

    foreach( $position as $form_b_pos => $salary_corrected_position ){
      list($grade,$new_position) = $salary_corrected_position;
      if( empty($grade) ){
        echo "this position $form_b_pos is not recognizedz\n";
      }
      if( !empty(  $new_position ) ){
          echo "We are correcting position $form_b_pos to $new_position\n";
          echo "salary scale for the position is ".$grade."\n";
        }
      else{
          echo "We use the existing position $form_b_pos\n";
          echo "salary scale for the position is ".$grade."\n";
        }
    }
    
    /*
     * search people by their id number Omang/Expatriate Num
     * 
     */
    
    function findDistrictByName( $districtName ){
      $name = strtolower( trim($districtName) );
      $where =  array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'name',
                    'style'=>'lowerequals',
                    'data'=>array(
                      'value'=>$name
                  )
                );
      $districtIds = I2CE_FormStorage::listFields('district', array('id', 'name'), true,$where);
      if( count($districtIds) == 1 ){
        $dist = current( $districtIds);
        $ff = I2CE_FormFactory::instance();
        $districtObj = $ff->createContainer( 'district|'.$dist['id'] );
      }else{
          return false;
        }
      return $districtObj;
    }
    
    
    
    function findPersonByOmang($omang, $expiry, $user) { //if found set their expiration date for that Identification and return $personObj
        $omang = strtoupper(trim($omang));
        
        $omang_id_type = 'id_type|1';
        $excemption_id_type = 'id_type|2';
        
        if (ctype_digit($omang)) {
            $id_type = $omang_id_type;
        }else if (in_array($omang, array('EXP', 'EXPATRIATE'))) {
            return false; //expatriate
        } else {
            $id_type = $excemption_id_type;
            //exception id
        }
        $where = array(
            'operator'=>'AND',
            'operand'=>array(
                0=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'id_type',
                    'style'=>'equals',
                    'data'=>array(
                        'value'=>$id_type
                        )
                    ),
                1=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'id_num',
                    'style'=>'equals',
                    'data'=>array(
                        'value'=>$omang
                        )
                    )
                )
            );
        $person_Ids = I2CE_FormStorage::listFields('person_id',array('parent', 'id'),false,$where);
        $person_id = false;
        if (count($person_Ids) != 1) {
            return false;
        }
        $data = current($person_Ids);
        if (array_key_exists('parent',$data) && is_string($data['parent']) && substr($data['parent'],0,7) == 'person|') {
            $person_id = substr($data['parent'],7);
        }            
        if (!$person_id) {
            return false;
        }
        echo "found person with person_id ".$data['id']."\n";
        $ff = I2CE_FormFactory::instance();
        $personIdObj = $ff->createContainer('person_id|' . $data['id']);
        $personIdObj->populate();
        $fieldObj = $personIdObj->getField('expiration_date');
        //print_r($personIdObj->getFieldNames());
        $expiry_date = $fieldObj->getDBValue();
        
        $expires_on = getTransactionDate($expiry);
        echo $expires_on . "\n";
        //$personIdObj->expiration_date= $expires_on;
        $personIdObj->getField('expiration_date')->setFromDB($expires_on);
        $personIdObj->save($user);
        $personIdObj->cleanup();
        
        $personObj = $ff->createForm('person|' . $person_id);
        if (!$personObj instanceof iHRIS_Person) {
            return false;
        }
        $personObj->populate();
        
        return $personObj;
    }
    
    function updatePerson($personObj, $firstname, $surname, $user){
      $personObj->populate();
      $DBsurname = $personObj->getField('surname')->getDBValue();
      $DBfirstname = $personObj->getField('firstname')->getDBValue();
      if(!empty($firstname) && !empty($surname) ){ //if one of them is not set, we don't change anything
      $personObj->getField('surname')->setFromDB( $surname );
      $personObj->getField('firstname')->setFromDB( $firstname);
          $personObj->save($user);
        }
      //$personObj->cleanup();
    }
    
    function updatePersonPosition($personObj,$newPosition,$newFacility, $user){
        //$personObj->getField('id')->getDBValue();
        $where = array(
                  'operator'=>'FIELD_LIMIT',
                  'field'=>'parent',
                  'style'=>'equals',
                  'data'=>array(
                      'value'=>$personObj
                      )
                );
        
        $person_position_ids = I2CE_FormStorage::search('person_position',$personObj->getField('id')->getDBValue(), array(), array('-start_date'));
          if (count($person_position_ids) > 1) {
              $person_position_ids = current($person_position_ids);
          }
          //Only editing the most recent position of the person, no creation of any new person_position
          $currentPersonPos = current(I2CE_FormStorage::listFields('person_position',array('start_date','end_date', 'position'),$personObj->getField('id')->getDBValue(), array(), array('-start_date')));
           print_r($currentPersonPos); 
            //update job with $newPosition
            //if job exists, return job_id, else create new and return the same
           
            if(($jobid = jobExists($newPosition, $user)) === false){
              echo "job id not found\n";
            }else{
                echo "Job $newPosition found in the system\n";
              }
            //update facility with $newFacility
            //if facility exists return facility_id, else create new and return the same
            $whereFacility = array(
                  'operator'=>'FIELD_LIMIT',
                  'field'=>'name',
                  'style'=>'equals',
                  'data'=>array(
                      'value'=>$newFacility
                      )
                );
            $fac_ids = I2CE_FormStorage::search('facility', array(),$whereFacility);
            
            if( count($fac_ids) < 1 ){
              echo "Adding new Facility\n";
              $facObj = $ff->createContainer('facility');
              $facObj->name = $newFacility;
              $facObj->location = explode('|', $district);
              }
              else{
                  echo "Updating Facility\n";
                  $facObj = $ff->createContainer('facility|'.$fac_ids[0]);
                  $facObj->populate();
                  $facObj->name = $newFacility;
                  $facObj->location = array('district', 40);
                }
              $facId = $facObj->save($user);
            //update position
            //if position exists update facility and job and title and return position ID
            $wherePos = array(
                  'operator'=>'FIELD_LIMIT',
                  'field'=>'title',
                  'style'=>'lowerequals',
                  'data'=>array(
                      'value'=>strtolower($newPosition)
                      )
                );
            $pos_ids = I2CE_FormStorage::search('position', array(),$wherePos);
            if( count($pos_ids)  <1 ){
              echo "Adding new Position\n";
              $posObj = $ff->createContainer('position');
              }
              else{
                echo "Updating Position\n";
                $posObj = $ff->createContainer('position|'.$pos_ids[0]);
                $posObj->populate();                
              }
            $posObj->getField('title')->setValue( $newPosition);
            $posObj->getField('job')->setValue(array('job',$jobId));
            $posObj->getField('facility')->setValue(array('facility',$facId));
            $posObj->getField('status')->setValue(array('position_status','closed')); //if for some reason the date was set to closed
            $posId = $posObj->save($user);
            echo "created position with id=position|$posId\n";
            //update person position
            $currentPersonPosId = $person_position_ids[0];
            $personPosObj = $ff->createContainer('person_position|'. $currentPersonPosId );
            $personPosObj->populate();
            $personPosObj->getField('position')->setValue(array('position',$posId) );
            //$personPosObj->getField('start_date')->setValue('2013-04-23 00:00:00');
            $person_po_id = $personPosObj->save($user);
          
          $currentPersonPos = current(I2CE_FormStorage::listFields('person_position',array('start_date','end_date', 'position'),$personObj->getField('id')->getDBValue(), array(), array('-start_date')));
           print_r($currentPersonPos); 
    }
    
    function jobExists($title, $user){
       $whereJob = array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'title',
                'style'=>'equals',
                'data'=>array(
                    'value'=>$title
                    )
              );
      $job_ids = I2CE_FormStorage::search('job', array(),$whereJob);
      $ff = I2CE_FormFactory::instance();
      if( count($job_ids) < 1 ){
        return false;
        }
        $ff =I2CE_FormFactory::instance();
        $jobObj = $ff->createContainer('job|'.$job_ids[0]);
        print_r($job_ids);
        $jobObj->populate();
        $jobObj->title = $title;
        $jobId = $jobObj->save($user);
        return $jobId;
    }
    
    function getTransactionDate($dates){
      return date('Y-m-d 00:00:00', strtotime($dates));
    }
    /*
     * function to load salary grades and any new positions.
     * if new position column C is empty then we use title from column A which is the key of the array.
     * if there is no salary grade, that means the position is not recognized/doesn't exist
     * as per Corporate Services
     */
    
    function loadPositions() {
      
        $file = dirname(__FILE__) . "/data/extracted-current-positions.xlsx";
        
        $readerType = PHPExcel_IOFactory::identify($file);
        $reader = PHPExcel_IOFactory::createReader($readerType);
        $reader->setReadDataOnly(false);
        $excel = $reader->load($file);        
        $worksheet = $excel->getActiveSheet();
        $rowIterator = $worksheet->getRowIterator();
        $found = false;
        $pos = array();
        for ($i=2; true ; $i++) {
        $form_b_position = strtoupper(trim($worksheet->getCell('A' . $i)->getValue()));
        $pos_salary_scale = strtoupper(trim($worksheet->getCell('B' . $i)->getValue()));
        $corrected_pos = strtoupper(trim($worksheet->getCell('C' . $i)->getValue()));
        
        $pos[$form_b_position] = array($pos_salary_scale, $corrected_pos);
        
        //echo "Processing row $i\n";
        //echo "Form B Position is $form_b_position, salary_scale=$pos_salary_scale and corrected position $corrected_pos\n";
        }
        return $pos;
    }

/*
    if (!is_readable($pos_job_file) || ($fp = fopen($pos_job_file,"r")) === false) {
        usage($pos_job_file . " is not readable");
        return false;
    }
    $expected_headers = array(
        'title' =>'infinium_position_title',
        'jobid' => 'job_id'
        );

    $headers = $this->loadHeadersFromCSV($fp);
    $header_map = $this->getHeaderMap($headers,$expected_headers);
    $row = 0;
    while (($data = fgetcsv($fp)) !== FALSE) {
        if ( ! ($mapped_data = $this->mapByHeaderData($data,$header_map))) {
            continue;
        }
        if (!$mapped_data['jobid'] || !$mapped_data['title']) {
            continue;
        }
        list($form,$newjobid) =explode("|",$mapped_data['jobid']);
        if (!$newjobid) {
            continue;
        }
        $this->position_job_map[strtoupper(trim($mapped_data['title']))] = array('job',$newjobid);
    }
    $salary_grades = I2CE_FormStorage::listFields('salary_grade','name');
    foreach ($salary_grades as $id=>&$data) {
        if (!is_array($data) || !array_key_exists('name',$data) || !$data['name']) {
            unset($salary_grades[$id]);
            continue;
        }
        $data = $data['name'];
    }
    unset($data);
    foreach (I2CE_FormStorage::listFields('post',array('dpsm_code','salary_grade')) as $id=>$data) {
        if (!is_array($data) || !array_key_exists('dpsm_code',$data) || !$data['dpsm_code']) {
            continue;
        }
        $this->dpsm_code_map[strtoupper($data['dpsm_code'])] =  $id;
        $grade = false;
        if (array_key_exists('salary_grade',$data) && $data['salary_grade'] && count($grades = explode(",",$data['salary_grade'])) == 1) {
            $salary_grade_id = substr($grades[0],14);
            if (is_string($salary_grade_id) && strlen($salary_grade_id) > 0 && array_key_exists($salary_grade_id,$salary_grades)) {
                $grade = $salary_grades[$salary_grade_id];
            }
        }
        $this->dpsm_grade[strtoupper($data['dpsm_code'])] = $grade;
    }
}
*/


function date_after($left_date,$right_date) {
    return strtotime( $left_date->getDBValue() ) >   strtotime( $right_date->getDBValue());
}

# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
