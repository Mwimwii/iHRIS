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


require_once("./import_base.php");



/*********************************************
*
*      Process Class
*
*********************************************/

class UpdateQualifications extends Processor {


    protected $create_new_people = null;
    protected $person_ids = array();
    public function __construct($file) {
        I2CE::raiseMessage("Loading person_id lookup");
        $this->person_ids = $this->loadPersonIdsByEmployeeNumber();
        parent::__construct($file);

    }


    protected function mapData() {
        $mapped_data = parent::mapData();        
        if (!$mapped_data['employee_number']) {
            $this->addBadRecord('No employee number');
            return false;
        }
        return $mapped_data;
    }

    protected function getExpectedHeaders() {
        return  array(
        'employee_number' => 'Employee Number',
        'id_num' => 'ID Number',
        'id_num' => 'ID Number',
        'surname' => 'Surname',
        'firstname' => 'Forename',	
        'othername' => 'Middle name',
        'q1_year' => 'Qual1Year',
        'q1_code' => 'Qual1QualCode',
        'q1_description' => 'Qual1Description',
        'q1_subject' => 'Qual1Subject',
        'q1_subsid1' => 'Qual1Subsid1',
        'q1_subsid2' => 'Qual1Subsid2',
        'q1_establishment' => 'Qual1Establishment',
        'q1_result' => 'Qual1Result',
        'q1_start_date' => 'Qual1DateStarted',
        'q1_end_date' => 'Qual1DateCompleted',
        'q2_year' => 'Qual2Year',
        'q2_code' => 'Qual2QualCode',
        'q2_description' => 'Qual2Description',
        'q2_subject' => 'Qual2Subject',
        'q2_subsid1' => 'Qual2Subsid1',
        'q2_subsid2' => 'Qual2Subsid2',
        'q2_establishment' => 'Qual2Establishment',
        'q2_result' => 'Qual2Result',
        'q2_start_date' => 'Qual2DateStarted',
        'q2_end_date' => 'Qual2DateCompleted',
        'q3_year' => 'Qual3Year',
        'q3_code' => 'Qual3QualCode',
        'q3_description' => 'Qual3Description',
        'q3_subject' => 'Qual3Subject',
        'q3_subsid1' => 'Qual3Subsid1',
        'q3_subsid2' => 'Qual3Subsid2',
        'q3_establishment' => 'Qual3Establishment',
        'q3_result' => 'Qual3Result',
        'q3_start_date' => 'Qual3DateStarted',
        'q3_end_date' => 'Qual3DateCompleted',
        'q4_year' => 'Qual4Year',
        'q4_code' => 'Qual4QualCode',
        'q4_description' => 'Qual4Description',
        'q4_subject' => 'Qual4Subject',
        'q4_subsid1' => 'Qual4Subsid1',
        'q4_subsid2' => 'Qual4Subsid2',
        'q4_establishment' => 'Qual4Establishment',
        'q4_result' => 'Qual4Result',
        'q4_start_date' => 'Qual4DateStarted',
        'q4_end_date' => 'Qual4DateCompleted',
        'q5_year' => 'Qual5Year',
        'q5_code' => 'Qual5QualCode',
        'q5_description' => 'Qual5Description',
        'q5_subject' => 'Qual5Subject',
        'q5_subsid1' => 'Qual5Subsid1',
        'q5_subsid2' => 'Qual5Subsid2',
        'q5_establishment' => 'Qual5Establishment',
        'q5_result' => 'Qual5Result',
        'q5_start_date' => 'Qual5DateStarted',
        'q5_end_date' => 'Qual5DateCompleted',
        'q6_year' => 'Qual6Year',
        'q6_code' => 'Qual6QualCode',
        'q6_description' => 'Qual6Description',
        'q6_subject' => 'Qual6Subject',
        'q6_subsid1' => 'Qual6Subsid1',
        'q6_subsid2' => 'Qual6Subsid2',
        'q6_establishment' => 'Qual6Establishment',
        'q6_result' => 'Qual6Result',
        'q6_start_date' => 'Qual6DateStarted',
        'q6_end_date' => 'Qual6DateCompleted',
        'q7_year' => 'Qual7Year',
        'q7_code' => 'Qual7QualCode',
        'q7_description' => 'Qual7Description',
        'q7_subject' => 'Qual7Subject',
        'q7_subsid1' => 'Qual7Subsid1',
        'q7_subsid2' => 'Qual7Subsid2',
        'q7_establishment' => 'Qual7Establishment',
        'q7_result' => 'Qual7Result',
        'q7_start_date' => 'Qual7DateStarted',
        'q7_end_date' => 'Qual7DateCompleted',
        'q8_year' => 'Qual8Year',
        'q8_code' => 'Qual8QualCode',
        'q8_description' => 'Qual8Description',
        'q8_subject' => 'Qual8Subject',
        'q8_subsid1' => 'Qual8Subsid1',
        'q8_subsid2' => 'Qual8Subsid2',
        'q8_establishment' => 'Qual8Establishment',
        'q8_result' => 'Qual8Result',
        'q8_start_date' => 'Qual8DateStarted',
        'q8_end_date' => 'Qual8DateCompleted',
        'q9_year' => 'Qual9Year',
        'q9_code' => 'Qual9QualCode',
        'q9_description' => 'Qual9Description',
        'q9_subject' => 'Qual9Subject',
        'q9_subsid1' => 'Qual9Subsid1',
        'q9_subsid2' => 'Qual9Subsid2',
        'q9_establishment' => 'Qual9Establishment',
        'q9_result' => 'Qual9Result',
        'q9_start_date' => 'Qual9DateStarted',
        'q9_end_date' => 'Qual9DateCompleted',
        'q10_year' => 'Qual10Year',
        'q10_code' => 'Qual10QualCode',
        'q10_description' => 'Qual10Description',
        'q10_subject' => 'Qual10Subject',
        'q10_subsid1' => 'Qual10Subsid1',
        'q10_subsid2' => 'Qual10Subsid2',
        'q10_establishment' => 'Qual10Establishment',
        'q10_result' => 'Qual10Result',
        'q10_start_date' => 'Qual10DateStarted	',
        'q10_end_date' => 'Qual10DateCompleted',
            );
    }
    
    protected function _processRow() {
        //$this->process_stats_checked = array();
        if ( !(($personid = $this->findPersonByOmangAndEmployeeNumber()) instanceof iHRIS_Person ) ){
            $this->addBadRecord("no identification information");
        }else{
            $this->importQualifications($personid);
          }
        return true;
    }
    
    protected static  $omang_id_type = 'id_type|1';
    protected static $excemption_id_type = 'id_type|2';
    protected static $employeenum_id_type = 'id_type|4';
    protected $duplicate_ids = array();
    
    function findPersonByOmangAndEmployeeNumber($create = false) {
        $personObj= false;
        
        $omang = strtoupper(trim($this->mapped_data['id_num']));
        $employeenum = trim($this->mapped_data['employee_number']);
        
        $surname = trim($this->mapped_data['surname']);
        $firstname = trim($this->mapped_data['firstname']);

        $id_type = false;
        //we only want the Omang, because the exemption id can exist in duplicates
        if (strlen($omang) > 0 && ctype_digit($omang)) {
            $id_type = self::$omang_id_type;
            $id_num = $omang;
        }else{
            $id_type = self::$employeenum_id_type;
            $id_num = $employeenum;
          }

        //$person_id = false;
  
      if (array_key_exists($id_num, $this->person_ids)) {
          $person_id = $this->person_ids[$id_num];
          $personObj = $this->ff->createForm($person_id);
        }
      elseif(array_key_exists($id_num, $this->person_ids) === false){ //search by names
            //try a search by name
            $where = array(
                'operator'=>'AND',
                'operand'=>array(
                    0=>array(
                        'operator'=>'FIELD_LIMIT',
                        'field'=>'surname',
                        'style'=>'lowerequals',
                        'data'=>array(
                            'value'=>trim($surname) 
                            )
                        ),
                    1=>array(
                        'operator'=>'FIELD_LIMIT',
                        'field'=>'firstname',
                        'style'=>'lowerequals',
                        'data'=>array(
                            'value'=> trim($firstname) 
                            )
                        )
                    )
                );
            $personIds = I2CE_FormStorage::search('person',false,$where);
            if (count($personIds) > 1) {
                $this->addBadRecord('duplicate records exists with that name');
                return false;
            }
            if (count($personIds) == 1) {
                $person_id =  'person|' . current($personIds);
                $personObj = $this->ff->createForm($person_id);
            }
        }
      return $personObj;   
    }
    
    
    public function importQualifications($personObj){
      for($i = 1; $i <=10; $i++){
        
        if(empty($this->mapped_data['q'.$i.'_code'])){
          break;
          }
        
        $year = $this->convertYear($this->mapped_data['q'.$i.'_year']); //graduation year
        //$year = $this->mapped_data['q'.$i.'_year']; //graduation year
        $code = trim($this->mapped_data['q'.$i.'_code']); //education level/qualification code
        $edu_type = trim($this->mapped_data['q'.$i.'_description']); //education level title
        $institution = trim($this->mapped_data['q'.$i.'_establishment']); //institution
        $degree = trim($this->mapped_data['q'.$i.'_subject']); //major
        $major = trim($this->mapped_data['q'.$i.'_subsid1']); //submajor1
        $submajor1 = trim($this->mapped_data['q'.$i.'_subsid2']); //submajor2
        $result = trim($this->mapped_data['q'.$i.'_result']); //final score
        //$start_date = trim($this->mapped_data['q'.$i.'_start_date']); //final score
        $start_date = $this->convertDate(trim($this->mapped_data['q'.$i.'_start_date'])); //final score
        //$end_date = trim($this->mapped_data['q'.$i.'_end_date']); //final score
        $end_date = $this->convertDate(trim($this->mapped_data['q'.$i.'_end_date'])); //final score
            
        //check education type exists
        $whereEduType = array(
                    'operator' => 'FIELD_LIMIT',
                    'field'=>'name',
                    'style'=>'lowerequals',
                    'data'=>array(
                        'value' => strtolower($edu_type)
                    )
                );
        
        //check degree exists
        $whereDegree = array(
                    'operator' => 'FIELD_LIMIT',
                    'field'=>'name',
                    'style'=>'lowerequals',
                    'data'=>array(
                        'value' => strtolower($degree)
                    )
                );
        if(empty($code)){}
        else{
        I2CE::raiseMessage("Saving for column $i");
        $eduTypeIds = I2CE_FormStorage::search('edu_type',false,$whereEduType);
        
        if(count($eduTypeIds) >= 1){ //update it with code
            $id = current($eduTypeIds);
            $eduTypeObj = $this->ff->createContainer('edu_type|'.$id);
            $eduTypeObj->populate();
          }
        if(count($eduTypeIds) == 0){ //create a new if it doesn't exists
            $eduTypeObj = $this->ff->createContainer('edu_type');
          }
        
          $eduTypeObj->getField('name')->setValue($edu_type);
          $eduTypeObj->getField('code')->setValue($code);
          $eduTypeId = $this->save($eduTypeObj);
        
        
        $degreeIds = I2CE_FormStorage::search('degree',false,$whereDegree);
        if(count($degreeIds) > 1){//hide the rest and take one and update all degree to refer to this one
          $degreeObj = $this->ff->createContainer('degree|'.$degreeIds[0]);
          $degreeObj->populate();
        }
        if(count($degreeIds) == 1){ //update it with code
            $id = current($degreeIds);
            $degreeObj = $this->ff->createContainer('degree|'.$id);
            $degreeObj->populate();
          }
        if(count($degreeIds) == 0){ //create a new if it doesn't exists
            $degreeObj = $this->ff->createContainer('degree');
          }
        
          $degreeObj->getField('name')->setValue($degree);
          $degreeObj->getField('edu_type')->setValue(array('edu_type', $eduTypeId));
          $degreeId = $this->save($degreeObj);
          
        //update person's education history
          $educationObj = $this->ff->createContainer('education');
          $educationObj->getField('institution')->setValue($institution);
          $educationObj->year = $year;
          $educationObj->getField('major')->setValue($major);
          $educationObj->getField('submajor1')->setValue($submajor1);
          $educationObj->getField('result')->setValue($result);
          $educationObj->getField('degree')->setValue(array('degree',$degreeId));
          $educationObj->getField('start_date')->setValue($start_date);
          $educationObj->getField('end_date')->setValue($end_date);
          $educationObj->setParent($personObj->getNameID());
          $this->save($educationObj);
          $eduTypeObj->cleanup();
          $degreeObj->cleanup();
      }}
    }
    
 
    
    //protected $day;
    //protected $month;
    public function convertDate($date) {
        list($d, $m, $y) = preg_split("/[\-]/",$date);
        $Months = array(
          'jan' => '01',
          'feb' => '02',
          'mar' => '03',
          'apr' => '04',
          'may' => '05',
          'jun' => '06',
          'jul' => '07',
          'aug' => '08',
          'sep' => '09',
          'oct' => '10',
          'nov' => '11',
          'dec' => '12'
        );
          
        $day = $d;
        $month = $m;
        if(strlen(trim($day)) == 1){
          $day = '0'.$day;
        }else{
          $day = $day;
          }
        if(strlen($y) == 2){
          if($y < 20){
            $yr = '20'.$y;
          }
          else{
            $yr = '19'.$y;
          }
        }else{
            $yr = $y;
          }
        $month = $Months[strtolower($month)];
        return $this->getDate($yr.'/'.$month.'/'.$day, 'Y/m/d');
    }
    
    public function convertYear($year){
        return $this->getYear(trim($year), 'Y');
      }
    
    protected $existing_codes;
    protected function loadCountryLookup() {
        $this->existing_codes= I2CE_FormStorage::listFields('country',array('alpha_two'));
        foreach ($this->existing_codes as $id=>&$data) {
            $data = $data['alpha_two'];
        }
        unset($data);
    }
    
    
    function loadPersonIdsByEmployeeNumber(){
        $personId = array();
        $where = array(
            'operator' => 'FIELD_LIMIT',
            'field' => 'id_type',
            'style' => 'equals',
            'data' => array(
              'value' => 'id_type|4'
            )
          );
        $person_ids = I2CE_FormStorage::listFields('person_id',array('id_type', 'id_num', 'parent'));
        foreach($person_ids as $data){
            $personId[$data['id_num']] = $data['parent'];
          }
        return $personId;
      }
      
    function loadHeadersFromCSV($fp) {
        $in_file_sep = false;
        foreach (array("\t",",") as $sep) {
            $headers = fgetcsv($fp, 4000, $sep);
            if ( $headers === FALSE|| count($headers) < 2) {
                fseek($fp,0);
                continue;
            }
            $in_file_sep = $sep;
        }
        if (!$in_file_sep) {
            die("Could not get headers\n");
        }
        foreach ($headers as &$header) {
            $header = trim($header);
        }
        unset($header);
        return $headers;
    }
    
    function getTransactionDate() {
        return $this->getDate($this->mapped_data['date'],'Y/m/d');
    }
}




/*********************************************
*
*      Execute!
*
*********************************************/

//ini_set('memory_limit','3000MB');


if (count($arg_files) != 1) {
    usage("Please specify the name of a spreadsheet to process");
}

reset($arg_files);
$file = current($arg_files);
if($file[0] == '/') {
    $file = realpath($file);
} else {
    $file = realpath($dir. '/' . $file);
}
if (!is_readable($file)) {
    usage("Please specify the name of a spreadsheet to import: " . $file . " is not readable");
}

I2CE::raiseMessage("Loading from $file");


$processor = new UpdateQualifications($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End: