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
 * @author Dimpho Pholele (The Consultant) <pholele.dimpho@gmail.com>
 * @copyright Copyright &copy; 2007, 2008-2014 IntraHealth International, Inc. 
 * @since Demo-v2.a
 * @version Demo-v2.a
 */


require_once("./import_base.php");



/*********************************************
*
*      Process Class
*
*********************************************/

class Import_From_iHRIS_Manage extends Processor {
    public function __construct($file){
        parent::__construct($file);
      }
    protected function getExpectedHeaders() {
        return  array(
            'surname' => 'Surname',
            'firstname' =>	'First Name',
            'othername' => 'Other Names',
            'employee_number' => 'Employee Number',
            'omang' => 'Omang',
            'nationality' => 'Nationality',
            'facility' => 'Facility',
            'profession' => 'Profession',
            'gender' => 'Gender',
            'position' => 'Position Title'
            );
    }

    protected static  $omang_id_type = 'id_type|1';

    protected function _processRow() {
        if( !(($personObj = $this->findPersonByEmployeeNumber($this->mapped_data['employee_number'])) instanceof iHRIS_Person)){
            if( !(($personObj = $this->findPersonByNames($this->mapped_data['firstname'], $this->mapped_data['surname'])) instanceof iHRIS_Person)){
                $personObj = $this->createPerson( $this->mapped_data['firstname'], $this->mapped_data['surname'],
                                                  $this->mapped_data['othername'],$this->mapped_data['nationality']);
              }
          }
        $this->createPersonID($personObj, $this->mapped_data['employee_number'], $this->mapped_data['omang']);
        $this->setDemographicInfo($personObj, $this->mapped_data['gender'], $this->mapped_data['nationality']);
        $jobObject = $this->listLookup('job', $this->mapped_data['position']);
        $positionObj = $this->createPosition($jobObject, $this->mapped_data['position'],$this->mapped_data['facility']);
        $this->createPersonPosition($personObj, $positionObj);
        return true;
    }


    protected function processStats($stat) {
        //echo "Stat:$stat\n";
        if (!array_key_exists($stat,$this->process_stats)) {
            $this->process_stats[$stat] = 0;
        }
        if (in_array($stat,$this->process_stats_checked)) {
            return;
        }
        $this->process_stats[$stat]++;

    }
    
    protected $process_stats = array();
    protected $process_stats_checked = array();

    protected $duplicate_ids = array();

    function findPersonByNames($firstname,$surname) {
        $firstname = strtolower(trim($firstname));
        $surname = strtolower(trim($surname));
        $where = array(
            'operator'=>'AND',
            'operand'=>array(
                0=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'firstname',
                    'style'=>'lowerequals',
                    'data'=>array(
                        'value'=>$firstname
                        )
                    ),
                1=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'surname',
                    'style'=>'lowerequals',
                    'data'=>array(
                        'value'=>$surname
                        )
                    )
                )
            );
        $persons = I2CE_FormStorage::search('person',true,$where);
        if (count($persons) >= 1) {
            $personObj = $this->ff->createContainer('person|'.current($persons));
            $personObj->populate();
            return $personObj;
        } elseif (count($persons) == 0) {
            return false;
        }
    }
  
    function findPersonByEmployeeNumber($employee_number){
        if( empty( $employee_number ) ){
            return false;
          } 
        $emp_number = trim($employee_number);
        $where = array(
            'operator'=>'AND',
            'operand'=>array(
                0=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'id_type',
                    'style'=>'equals',
                    'data'=>array(
                        'value'=>'id_type|4'
                        )
                    ),
                1=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'id_num',
                    'style'=>'equals',
                    'data'=>array(
                        'value'=>$emp_number
                        )
                    )
                )
            );
        $person_ids = I2CE_FormStorage::listFields('person_id', array('parent'), false, $where);
        if( count($person_ids) >= 1){
            $data = current($person_ids);
            $personObj = $this->ff->createContainer($data['parent']);
            $personObj->populate();
            return $personObj;
          }
        if( count($person_ids) == 0){
            return false;
          }
        
      }
    
    function createPerson($firstname, $surname, $othername, $nationality){
        $personObj = $this->ff->createContainer('person'); //create the person object
        $personObj->surname = trim($surname);
        $personObj->firstname = trim($firstname);
        if(!empty($othername)){
          $personObj->othername = trim($othername);
        }
        if(!empty($nationality)){
          $countryObj = $this->listLookup('country', $nationality);
          $personObj->getField('nationality')->setFromDB($countryObj->getFormID());
        }
        $this->save($personObj);
        
        return $personObj;
      }
    
    function createPersonID($personObj, $emp_number, $omang){
        if(!empty($emp_number)){
            $pidObj = $this->ff->createContainer('person_id'); //create the person object
            $pidObj->id_num = trim($emp_number);
            $pidObj->id_type = array('id_type',4);
            $pidObj->setParent( $personObj->getFormID());
            $this->save($pidObj);
          }
        if(!empty($omang)){
            $pidObj = $this->ff->createContainer('person_id'); //create the person object
            $pidObj->id_num = trim($omang);
            $pidObj->id_type = array('id_type',1);
            $pidObj->setParent( $personObj->getFormID());
            $this->save($pidObj);
          }
        
        return true;
      }
      
    function setDemographicInfo($personObj, $gender, $country){
        if(!empty($gender)){
            $demographicObj = $this->ff->createContainer('demographic');
            $demographicObj->getField('gender')->setValue(array('gender',$gender[0]));
            $country = $this->listLookup('country', trim($country));
            $demographicObj->getField('country')->setFromDB($country->getFormID());
            $demographicObj->setParent( $personObj->getFormID() );
            $this->save($demographicObj);
          }
        return true;
      }
    
    function createPersonPosition($personObj, $positionObj){
      if( $positionObj instanceof iHRIS_Position ){
          $personPosObj = $this->ff->createContainer('person_position');
          $personPosObj->getField('position')->setFromDB($positionObj->getFormID());
          $personPosObj->setParent( $personObj->getFormID() );
          $this->save($personPosObj);
        }
      }
      
    function listLookup( $listform, $listValue, $otherFields=array()){
        if($listform == 'job' || $listform == 'position'){
            $namefield = 'title';
          }
        else{
            $namefield = 'name';
          }
        
        $where = array(
          'operator'=>'FIELD_LIMIT',
          'field'=>$namefield,
          'style'=>'lowerequals',
          'data'=>array(
              'value'=>strtolower(trim($listValue))
              )
          );
        $form_list = I2CE_FormStorage::listFields($listform, array('id'), false, $where);
        if(count($form_list) >= 1){
            $data = current($form_list);
            $formObj = $this->ff->createContainer($listform.'|'.$data['id']);
            $formObj->populate();
          }
        else{
            //list doesn't exist, so we need to create
            $formObj = $this->ff->createContainer($listform);
            $formObj->$namefield = trim($listValue);
            $this->save($formObj);
          }
        
        return $formObj;
      }

    function createPosition($jobObj, $title, $facility){
        $formObj = $this->ff->createContainer('position');
        $formObj->title = trim($title);
        $formObj->getField('job')->setFromDB($jobObj->getFormID());
        $formObj->getField('status')->setFromDB('position_status|closed');
        $facility = $this->listLookup('facility', trim($facility));
        $formObj->getField('facility')->setFromDB($facility->getFormID());
        $this->save($formObj);
        return $formObj;
      }
}

/*********************************************
*
*      Execute!
*
*********************************************/

//ini_set('memory_limit','4G');

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


$processor = new Import_From_iHRIS_Manage($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
