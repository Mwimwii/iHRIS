#!/usr/bin/php
<?php
/** 
 * © Copyright 2007, 2008 IntraHealth International, Inc.
 * This File is part of iHRIS
 * iHRIS is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * This page loads the main HTML template for the home page of the site.
 * 
 * @package    IHRIS
 * @subpackage DemoManage
 * @author     Carl Leitner <litlfred@ibiblio.org>
 * @copyright  2007-2008 IntraHealth International, Inc.
 * @version    CVS: Demo-v2.a
 * @since      Demo-v2.a
 */


require_once "./import_base.php";
require_once $i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'I2CE_config.inc.php';
require_once $i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'CLI.php';


/*
*Process Class
*/
$user = new I2CE_User();

/**
 * 
 */
class UpdatePersonsByFacility extends Processor 
{
    protected function getExpectedHeaders() 
    {
        return  array(
            'surname' => 'surname',
            'firstname' => 'firstname',
            'othername' => 'othername',
            'birth_date' => 'birth_date',
            'employee_no' => 'employee_no',
            'NRC' => 'NRC',
            'nationality' => 'nationality',
            'facility_id' => 'facility_id',
            'district' => 'district',
            'gender' => 'gender',
            'position' => 'position',
            'department' => 'department',
            'position_type' => 'position_type',
            'classification' => 'classification',
            'cadre' => 'cadre',
            'salary_grade' => 'salary_grade',
            'salary' => 'basic_salary',
            'start_date' => 'start_date'
            );
    }
    
    public function __construct($file) {
        parent::__construct($file);
    }
    
    protected static $default_country_code = 'country|ZM';

    protected function _processRow() {

        $facility_id = trim($this->mapped_data['facility_id']);
        $district_id = trim($this->mapped_data['district']);
        $position = trim($this->mapped_data['position']);
        $employee_no = trim($this->mapped_data['employee_no']);
        $nrc = trim($this->mapped_data['NRC']);


        if (!$this->mapped_data['firstname'] || !$this->mapped_data['surname']) {
            $this->addBadRecord("Incomplete information");
            return false;
        }
        
        $ff = I2CE_FormFactory::instance();

        $facilityObj = $this->ff->createContainer($facility_id);
        $facilityObj->populate();

        $districtObj = $this->ff->createContainer($district_id);
        $districtObj->populate();

        if( ($person = $this->findPersonByNRC($nrc)) instanceof iHRIS_Person ){

            // $person = $ff->createContainer('person');
            // $person->firstname = $this->mapped_data['firstname'];
            // $person->othername = $this->mapped_data['othername'];
            // $person->surname = $this->mapped_data['surname'];
            // $person->getField("nationality")->setFromDB('country|ZM');
            // $person->getField("residence")->setFromDB( $districtObj->getFormID() );

            // $this->createPersonID( $person, $employee_no, $nrc );
            // $this->setDemographicInfo( $person, $this->mapped_data['gender'], $this->convertDate($this->mapped_data['birth_date']) );

            if (($positionObj = $this->findPosition($facility_id, $position)) instanceof iHRIS_Position) {
                $personPosObj = $this->ff->createContainer('person_position');
                $personPosObj->getField('position')->setFromDB($positionObj->getFormID());
                $personPosObj->setParent($person->getFormID());
                $personPosObj->getField('start_date')->setFromDB($this->convertDate($this->mapped_data['start_date']));
                //$personPosObj->getField( 'salary' )->setFromDB( $this->mapped_data['salary'] );
                $this->save($personPosObj);
            } else {
                print("No position: ".$position);
            }
        
            
            $this->save($person);   
            $person->cleanup();
            unset($person);
        } else {
            print ("Can't find person with NRC: ".$nrc."\n");
        }
        

        return true;
    }

    function createPersonID( $personObj, $emp_number, $NRC ){
        if(!empty($emp_number)){
            $pidObj = $this->ff->createContainer('person_id'); //create the person object
            $pidObj->id_num = trim($emp_number);
            $pidObj->id_type = array( 'id_type',5 );
            $pidObj->setParent( $personObj->getFormID());
            $this->save( $pidObj );
          }
        if(!empty($NRC)){
            $pidObj = $this->ff->createContainer( 'person_id' ); //create the person object
            $pidObj->id_num = trim( $NRC );
            $pidObj->id_type = array( 'id_type',2 );
            $pidObj->setParent( $personObj->getFormID() );
            $this->save( $pidObj );
          }
        return true;
    }
      
    function setDemographicInfo( $personObj, $gender, $birth_date ){
        if(!empty( $gender )){
            $demographicObj = $this->ff->createContainer( 'demographic' );
            $demographicObj->getField( 'gender' )->setValue( array( 'gender',$gender[0] ));   
            $demographicObj->getField( 'birth_date' )->setFromDB( $birth_date );
            $demographicObj->setParent( $personObj->getFormID() );
            $this->save( $demographicObj );
          }
        return true;
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
        else
            return false;
          
        
    }

    function findPersonByNRC($nrc){
        if( empty( $nrc ) ){
            return false;
          } 
        $nrc = trim($nrc);
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
                        'value'=>$nrc
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
        else
            return false;
    }
      
    function findFacility($facility_name, $district_id) {
        $facility_name = strtolower(trim($facility_name));
        $district_id = strtolower(trim($district_id));
        $where = array(
            'operator'=>'AND',
            'operand'=>array(
                0=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'name',
                    'style'=>'lowerequals',
                    'data'=>array(
                        'value'=>$facility_name
                        )
                    ),
                1=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'location',
                    'style'=>'lowerequals',
                    'data'=>array(
                        'value'=>$district_id
                        )
                    )
                )
            );
        $facility = I2CE_FormStorage::search('facility', true, $where);
        if (count($facility) >= 1) {
            $facilityObj = $this->ff->createContainer('facility|'.current($facility));
            $facilityObj->populate();
            return $facilityObj;
        } else
            return false;
        
    }
    
    
    function findPosition($location, $title)
    {
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
                    'field'=>'facility',
                    'style'=>'lowerequals',
                    'data'=>array(
                        'value'=>$location
                        )
                    )
                )
            );
        $position = I2CE_FormStorage::search('position', true, $where);
        if (count($position) >= 1) {
            $positionObj = $this->ff->createContainer('position|'.current($position));
            $positionObj->populate();
            return $positionObj;
        } else
            return false;
    }

    function listLookup($listform, $listValue, $otherFields=array())
    {
        if ($listform == 'job' || $listform == 'position') {
            $namefield = 'title';
        } else {
            $namefield = 'name';
        }
        
        $where = array(
          'operator'=>'FIELD_LIMIT',
          'field'=>$namefield,
          'style'=>'lowerequals',
          'data'=>array('value'=>strtolower(trim($listValue)))
        );
        $form_list = I2CE_FormStorage::listFields($listform, array('id'), false, $where);
        if (count($form_list) >= 1) {
            $data = current($form_list);
            $formObj = $this->ff->createContainer($listform.'|'.$data['id']);
            $formObj->populate();
        } else {
            //list doesn't exist, so we need to create
            $formObj = $this->ff->createContainer($listform);
            $formObj->$namefield = trim($listValue);
            $this->save($formObj);
            $form_list = I2CE_FormStorage::listFields($listform, array('id'), false, $where);
            $data = current($form_list);
            $formObj = $this->ff->createContainer($listform.'|'.$data['id']);
            $formObj->populate();
          }
        
        return $formObj;
      }

    
    public function convertDate( $date ) {
        list($m, $d, $y) = preg_split("/[\/]/",$date);
        return $y . '-' . $m . '-'. $d.' 00:00:00';
    }
}




/*********************************************
*
*      Execute!
*
*********************************************/


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


$processor = new UpdatePersonsByFacility($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
