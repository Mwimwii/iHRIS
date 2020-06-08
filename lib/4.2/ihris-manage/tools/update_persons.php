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
class UpdatePersons extends Processor
{
    protected function getExpectedHeaders()
    {
        return  array(
            'surname' => 'surname',
            'firstname' => 'firstname',
            'othername' => 'othername',
            'birth_date' => 'DOB',
            'employee_no' => 'employee_no',
            'NRC' => 'NRC',
            'file_no' => 'file_no',
            'facility' => 'facility',
            'nationality' => 'nationality',
            'district' => 'district',
            'gender' => 'gender',
            'marital_status' => 'marital_status',
            'position' => 'substantive_position',
            // 'department' => 'department',
            'position_type' => 'position_type',
            // 'classification' => 'classification',
            'cadre' => 'cadre',
            // 'salary_grade' => 'salary_grade',
            'DOFA' => 'DOFA',
            'ae_no' => 'ae_no'
        );
    }

    protected function processStats($stat) 
    {
        if (!array_key_exists($stat, $this->process_stats)) {
            $this->process_stats[$stat] = 0;
        }
        if (in_array($stat, $this->process_stats_checked)) {
            return;
        }
        $this->process_stats[$stat]++;
    }
    
    protected $process_stats = array();
    protected $process_stats_checked = array();
    
    public function __construct($file) 
    {
        parent::__construct($file);
    }
    
    protected static $default_country_code = 'country|ZM';

    protected function _processRow() 
    {

        $ff = I2CE_FormFactory::instance();

        $firstname = trim($this->mapped_data['firstname']);
        $surname = trim($this->mapped_data['surname']);
        $othername = trim($this->mapped_data['othername']);
        $gender = trim($this->mapped_data['gender']);
        $marital_status = trim($this->mapped_data['marital_status']);
        $birth_date = trim($this->mapped_data['birth_date']);
        $facility = trim($this->mapped_data['facility']);
        $district = trim($this->mapped_data['district']);
        $position = trim($this->mapped_data['position']);
        $employee_no = trim($this->mapped_data['employee_no']);
        $nrc = trim($this->mapped_data['NRC']);
        $file_no = trim($this->mapped_data['file_no']);
        $ae_no = trim($this->mapped_data['ae_no']);
        $start_date = trim($this->mapped_data['DOFA']);
        $nationality = trim($this->mapped_data['nationality']);

        if (!$this->mapped_data['NRC'] || !$this->mapped_data['surname']) {
            $this->addBadRecord("Incomplete information");
            return false;
        }

        $districtObj = $this->listLookup('district', $district);
        $facilityObj = $this->findFacility($facility, $districtObj->getFormID());
        
        if ((($facilityObj = $this->findFacility($facility, $districtObj->getFormID())) instanceof iHRIS_Facility)) {
            if (($positionObj = $this->findPosition($facilityObj->getFormID(), $position)) instanceof iHRIS_Position) {
                if (($personObj = $this->findPersonByNRC($nrc)) instanceof iHRIS_Person ) {
                    if ($this->isSetPosition($personObj)) {
                        $this->processStats("Position already set for person: ".$personObj->getFormID());
                    } else {
                        $personPosObj = $this->ff->createContainer('person_position');
                        $personPosObj->getField('position')->setFromDB($positionObj->getFormID());
                        $personPosObj->setParent($personObj->getFormID());
    
                        if ($start_date) {
                            $personPosObj->getField('start_date')->setFromDB($this->convertDate($start_date));
                        }
    
                        $this->save($personPosObj);
                    }
                } else {
                    $this->processStats("Can't find person with NRC: ".$nrc."\n");
                    $this->processStats("Creating person: ".$firstname." ".$surname. " - " .$nrc."\n");
    
                    $personObj = $this->createPerson(
                        $firstname, 
                        $surname, 
                        $othername, 
                        $nationality
                    );
    
                    $this->createPersonID(
                        $personObj, 
                        $employee_no, 
                        $nrc,
                        $file_no,
                        $ae_no
                    );
                    
                    $this->setDemographicInfo(
                        $personObj, 
                        $gender, 
                        $this->convertDate($birth_date),
                        $marital_status
                    );

                    $this->processStats('creating_new_person_position');
                    $personPosObj = $this->ff->createContainer('person_position');
                    $personPosObj->getField('position')->setFromDB($positionObj->getFormID());
                    $personPosObj->setParent($personObj->getFormID());
                    $personPosObj->getField('start_date')->setFromDB($this->convertDate($start_date));
                    $this->save($personPosObj);
                }

                $positionObj->getField('status')->setValue(array('position_status','closed'));
                $this->save($positionObj);

            } else {
                    $this->addBadRecord('Could not find position');
                    $this->processStats("No position: ".$position." at Facility: ".$facility."\n");
            }
            
        } else {
            $this->addBadRecord('Could not find facility');
            $this->processStats("Can't find facility: ".$facility."\n");
        }

        return true;
    
    }

    function createPerson($firstname, $surname, $othername, $nationality)
    {
        $personObj = $this->ff->createContainer('person'); //create the person object
        $personObj->surname = $surname;
        $personObj->firstname = $firstname;
        if (!empty($othername)) {
            $personObj->othername = $othername;
        }
        if (!empty($nationality)) {
            $countryObj = $this->listLookup('country', $nationality);
            $personObj->getField('nationality')->setFromDB($countryObj->getFormID());
        }
        $this->save($personObj);
        return $personObj;
    }
    

    function createPersonID( $personObj, $emp_number, $NRC, $file_no, $ae_no) 
    {
        if (!empty($emp_number)) {
            $pidObj = $this->ff->createContainer('person_id'); //create the person object
            $pidObj->id_num = trim($emp_number);
            $pidObj->id_type = array( 'id_type',5 );
            $pidObj->setParent($personObj->getFormID());
            $this->save($pidObj);
        }

        if (!empty($NRC)) {
            $pidObj = $this->ff->createContainer('person_id'); //create the person object
            $pidObj->id_num = $NRC;
            $pidObj->id_type = array( 'id_type',2 );
            $pidObj->setParent($personObj->getFormID());
            $this->save($pidObj);
        }

        if (!empty($file_no)) {
            $pidObj = $this->ff->createContainer('person_id'); //create the person object
            $pidObj->id_num = $file_no;
            $pidObj->id_type = array( 'id_type',5 );
            $pidObj->setParent($personObj->getFormID());
            $this->save($pidObj);
        }

        if (!empty($ae_no)) {
            $pidObj = $this->ff->createContainer('person_id'); //create the person object
            $pidObj->id_num = $ae_no;
            $pidObj->id_type = array( 'id_type',3 );
            $pidObj->setParent($personObj->getFormID());
            $this->save($pidObj);
        }
        return true;
    }
      
    function setDemographicInfo( $personObj, $gender, $birth_date, $marital_status )
    {
        if (!empty($gender)) {
            $demographicObj = $this->ff->createContainer('demographic');
            $demographicObj->getField('gender')->setValue(array('gender', $gender));
            $demographicObj->getField('marital_status')->setValue(array('marital_status', $marital_status));
            $demographicObj->getField('birth_date')->setFromDB($birth_date);
            $demographicObj->setParent($personObj->getFormID());
            $this->save($demographicObj);
        }
        return true;
    }

    function findPersonByEmployeeNumber($employee_no)
    {
        if (empty($employee_no)) {
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
        if (count($person_ids) >= 1) {
            $data = current($person_ids);
            $personObj = $this->ff->createContainer($data['parent']);
            $personObj->populate();
            return $personObj;
          }
        else
            return false;
          
        
    }

    function findPersonByNRC($nrc)
    {
        if (empty($nrc)) {
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
        if (count($person_ids) >= 1) {
            $data = current($person_ids);
            $personObj = $this->ff->createContainer($data['parent']);
            $personObj->populate();
            return $personObj;
        }

        else
            return false;
    }
      
    function findFacility($facility, $district_id)
    {
        $facility = strtolower($facility);

        $where = array(
            'operator'=>'AND',
            'operand'=>array(
                0=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'name',
                    'style'=>'lowerequals',
                    'data'=>array(
                        'value'=>$facility
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
    
    
    function findPosition($facility, $title)
    {
        $title = strtolower($title);
        $facility = strtolower($facility);

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
                        'value'=>$facility
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

    function createPersonPosition( $personObj, $positionObj ) 
    {    
        $personPosObj = $this->ff->createContainer('person_position');
        $personPosObj->getField('position')->setFromDB($positionObj->getFormID());
        $personPosObj->setParent($personObj->getFormID());
        $this->save($personPosObj);
        return $personPosObj;
    }
      

    /**
     * Person position lookup function
     *
     * @param personObj $personObj Person object
     *
     * @return false if a positon for a person is not found 
     */ 
    function findLastPositions($personObj, $only_current) 
    {
        if ($only_current) {
            $where = array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'end_date',
                'style'=>'null'
                );
        } else {
            $where = array();
        }
        $persPosObjs  = array();
        $per_pos_ids = I2CE_FormStorage::search('person_position', $personObj->getNameId(), $where, '-start_date');
        if (count($per_pos_ids) == 0) {
            $this->processStats('no_current_position');
        }
        foreach ($per_pos_ids as $per_pos_id) {
            $persPosObj = I2CE_FormFactory::instance()->createContainer('person_position'.'|'.$per_pos_id);
            if (!$persPosObj instanceof iHRIS_PersonPosition) {
                $this->processStats('no_current_position');
                continue;
            }
            $persPosObj->populate();
            echo "PersonPosition " . $persPosObj->getFormID() . " references position " . $persPosObj->getField('position')->getDBValue() . "\n";
            $this->processStats('has_current_position');
            $persPosObjs[$per_pos_id] = $persPosObj;
        }
        return $persPosObjs;
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
          'data'=>array('value'=>strtolower($listValue))
        );
        $form_list = I2CE_FormStorage::listFields($listform, array('id'), false, $where);
        if (count($form_list) >= 1) {
            $data = current($form_list);
            $formObj = $this->ff->createContainer($listform.'|'.$data['id']);
            $formObj->populate();
        } else {
            //list doesn't exist, so we need to create
            $formObj = $this->ff->createContainer($listform);
            $formObj->$namefield = $listValue;
            $this->save($formObj);
            $form_list = I2CE_FormStorage::listFields($listform, array('id'), false, $where);
            $data = current($form_list);
            $formObj = $this->ff->createContainer($listform.'|'.$data['id']);
            $formObj->populate();
          }
        
        return $formObj;
    }

    function createFacility( $districtObj, $facility, $facility_type )
    {
        $formObj = $this->ff->createContainer('facility');
        $formObj->name = $facility;
        $formObj->getField('location')->setFromDB($districtObj->getFormID());
        $this->save($formObj);
        return $formObj;
    }

    public function deletePersonPosition($personObj)
    {
        $child_forms = I2CE::getConfig()->getAsArray("/modules/forms/forms/person/meta/child_forms");
        
        $personObj->populate();
        $personObj->populateChildren($child_forms);
        foreach ($personObj->getChildren() as $child_form_name=>$child_form_data) {
            foreach ($child_form_data as $child_form_id=>$child_form) {
                if (!$child_form instanceof I2CE_Form) {
                    continue;
                }
                
                if ($child_form_name == 'person_position') {
                    echo "\t\tDeleting person_position with ID = " . $child_form->getFormID() . "\n";
                    $child_form->delete(false, true);
                }
               
            }
        }

    }

    public function isSetPosition($personObj)
    {
        $child_forms = I2CE::getConfig()->getAsArray("/modules/forms/forms/person/meta/child_forms");
        
        $personObj->populate();
        $personObj->populateChildren($child_forms);
        foreach ($personObj->getChildren() as $child_form_name=>$child_form_data) {
            foreach ($child_form_data as $child_form_id=>$child_form) {
                if (!$child_form instanceof I2CE_Form) {
                    continue;
                }
                
                if ($child_form_name == 'person_position') {
                    return true;
                }
               
            }
        }
        return false;
    }

    
    public function convertDate( $date ) 
    {
        list($d, $m, $y) = preg_split("/[\/]/", $date);
        return $y . '-' . $m . '-'. $d.' 00:00:00';
    }
}




/*********************************************
*Execute!
*********************************************/

if (count($arg_files) != 1) {
    usage("Please specify the name of a spreadsheet to process");
}

reset($arg_files);
$file = current($arg_files);
if ($file[0] == '/') {
    $file = realpath($file);
} else {
    $file = realpath($dir. '/' . $file);
}
if (!is_readable($file)) {
    usage("Please specify the name of a spreadsheet to import: " . $file . " is not readable");
}

I2CE::raiseMessage("Loading from $file");


$processor = new UpdatePersons($file);
$processor->run();

echo "Processing Statistics:\n";
print_r($processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
