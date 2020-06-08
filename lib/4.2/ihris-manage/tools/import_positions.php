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

class ImportAll extends Processor {
    public function __construct($file){
        parent::__construct($file);
      }
    protected function getExpectedHeaders() {
        return  array(
            // 'facility_type'=>'facility_type',
            'position'=>'position',
            'salary_grade'=>'salary_grade',
            'establishment'=>'establishment',
            'classification'=>'classification',
            'department'=>'department',
            'position_type'=>'position_type',
            'cadre'=>'cadre'
        );
    }


    protected function _processRow() {
        // $facility_type = trim($this->mapped_data['facility_type']);
        $facility_type = 'facility_type|10';
        $where = array(
            'operator'=>'OR',
            //'operator'=>'FIELD_LIMIT',
            'operand'=>array(
                0=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'facility_type',
                    'style'=>'lowerequals',
                    'data'=>array(
                        'value'=>$facility_type
                        )
                    )
                )
            );
        $facilities = I2CE_FormStorage::search('facility',true,$where);
        foreach ($facilities as $facility_id){
            $facilityObj = $this->ff->createContainer('facility|'.$facility_id);
            $facilityObj->populate();

            $cities = array("district|21", "district|15", "district|23", "district|47", "district|79");

            if(!in_array($facilityObj->getField( 'location' )->getDBValue(), $cities)){
                //print $facilityObj->getField( 'location' )->getDBValue()."\n";
                //continue;

                $jobObject = $this->createJob( $this->mapped_data['position'], $this->mapped_data['classification'], $this->mapped_data['salary_grade'] );
                $count = $this->mapped_data['establishment'];
    
                while($count > 0){    
                    $positionObj = $this->createPosition($facilityObj, $jobObject, $this->mapped_data['position'], $this->mapped_data['position_type'],  $this->mapped_data['department'] );
                    $count = $count - 1;
                }
            }


        } 
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
  
    function findPersonByEmployeeNumber($employee_no){
        if( empty( $employee_no ) ){
            return false;
          } 
        $emp_number = trim($employee_no);
        $where = array(
            'operator'=>'AND',
            'operand'=>array(
                0=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'id_type',
                    'style'=>'equals',
                    'data'=>array(
                        'value'=>'id_type|5'
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
      
    function findFacility($facility_name, $district_id) {

    }
    
    function findPosition($location, $title) {
        $title = strtolower(trim($title));
        $location = strtolower(trim($location));
        $where = array(
            'operator'=>'AND',
            'operand'=>array(
                0=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'title',
                    'style'=>'lowerequals',
                    'data'=>array(
                        'value'=>$title
                        )
                    ),
                1=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'location',
                    'style'=>'lowerequals',
                    'data'=>array(
                        'value'=>$location
                        )
                    )
                )
            );
        $facility = I2CE_FormStorage::search('facility',true,$where);
        if (count($facility) >= 1) {
            $facilityObj = $this->ff->createContainer('facility|'.current($facility));
            $facilityObj->populate();
            return $facilityObj;
        } elseif (count($facility) == 0) {
            return false;
        }
    }
    

      
    function createJob( $job_title, $classification, $salary_grade ){   
        $jobObj = $this->listLookup( 'job', $job_title );
        $classificationObj = $this->listLookup( 'classification' , $classification );
        $jobObj->getField( 'classification' )->setFromDB( $classificationObj->getFormID() );
        //$cadreObj = $this->listLookup( 'cadre' , $cadre );
        //$jobObj->getField( 'cadre' )->setFromDB( $cadreObj->getFormID() );
        $salaryGradeObj = $this->listLookup( 'salary_grade' , $salary_grade );
        $jobObj->getField( 'salary_grade' )->setFromDB( $salaryGradeObj->getFormID() );
        
        $this->save($jobObj);
        return $jobObj;
    }
    
      
    function listLookup( $listform, $listValue, $otherFields=array() ){
        if( $listform == 'job' || $listform == 'position' ){
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
              'value'=>strtolower( trim( $listValue ) )
              )
        );
        $form_list = I2CE_FormStorage::listFields( $listform, array( 'id' ), false, $where );
        if( count( $form_list) >= 1 ){
            $data = current( $form_list );
            $formObj = $this->ff->createContainer( $listform.'|'.$data['id'] );
            $formObj->populate();
          }
        else{
            $formObj = $this->ff->createContainer( $listform );
            $formObj->$namefield = trim( $listValue );
            $this->save( $formObj );
            $form_list = I2CE_FormStorage::listFields( $listform, array( 'id' ), false, $where );
            $data = current( $form_list );
            $formObj = $this->ff->createContainer( $listform.'|'.$data['id'] );
            $formObj->populate();
          }
        
        return $formObj;
      }

    function createPosition( $facilityObj, $jobObj, $title, $position_type, $department ){
        $formObj = $this->ff->createContainer('position');
        
        $departmentObj = $this->listLookup( 'department' , $department );
        $formObj->getField( 'department' )->setFromDB( $departmentObj->getFormID() );
        
        //$postionTypeObj = $this->listLookup( 'position_type' , $position_type );
        //$formObj->getField( 'pos_type' )->setFromDB( $postionTypeObj->getFormID() );
        $formObj->getField( 'pos_type' )->setFromDB( $position_type );
        
        $formObj->getField( 'title' )->setValue(trim( $title ));
        $formObj->getField( 'job' )->setFromDB( $jobObj->getFormID() );
        $formObj->getField( 'status' )->setFromDB( 'position_status|open' );
        $formObj->getField( 'facility' )->setFromDB( $facilityObj->getFormID() );
        $formObj->getField( 'source' )->setFromDB( 'salary_source|1' );
        
        $this->save( $formObj );
        return $formObj;
      }
      
}

/*********************************************
*
*      Execute!
*
*********************************************/

ini_set('memory_limit','1G');

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


$processor = new ImportAll($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
