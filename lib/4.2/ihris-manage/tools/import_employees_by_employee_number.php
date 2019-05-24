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
 * @author Sovello Hildebrand <sovellohpmgani@gmail.com>
 * @copyright Copyright &copy; 2007, 2008 IntraHealth International, Inc. 
 * @version Botswana-v4.1
 */


require_once("./import_base.php");



/*********************************************
*
*      Process Class
*
*********************************************/

class EmployeeProcessor extends Processor {


    protected $create_new_people = null;
    public function __construct($file){
        I2CE::raiseMessage("Pre-Loading DPSM data");
        $this->loadDPSMData();
        I2CE::raiseMessage("Loading facility lookup table");
        $this->loadFacilityLookup();
        I2CE::raiseMessage("Loading country lookup table");
        $this->loadCountryLookup();
        parent::__construct($file);
    }


    protected function mapData() {
        $mapped_data = parent::mapData();        
        return $mapped_data;
    }

    protected function getExpectedHeaders() {
        return  array(
            'org_level'=>'ORG. LVL',
            'unit'=>'Unit',
            'position'=>'Position',
            'title'=>'Title',
            'salary_grade'=>'Grade',
            'authorized'=>'Authorized',
            'actual'=>'Actual',
            'emp_name'=>'Employee Name',
            'emp_number'=>'Employee No.'
          );
    }
    
    protected $effective_date;
    protected function _processRow() {
        if (!$this->verifyData()) {
            return false;
        }
        if ( ! ($this->effective_date = $this->getTransactionDate())) {
            return false;
        }
        $success = false;

            if (! ($personObj = $this->createNewPerson())) {
              //
            }
            else{
                if($success = $this->setNewPosition($personObj)){
                  $personObj->cleanup();
                  $this->addBadRecord("Created a new employee with position");
                }else{
                  $this->addBadRecord("Failed to create position for this imployee");
                }
              }
        return $success;
    }


    protected $existing_codes;
    protected function loadCountryLookup() {
        $this->existing_codes= I2CE_FormStorage::listFields('country',array('alpha_two'));
        foreach ($this->existing_codes as $id=>&$data) {
            $data = $data['alpha_two'];
        }
        unset($data);
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

    protected $dpsm_code_map;
    protected $dpsm_grade;
    protected $position_job_map;

    function loadDPSMData() {
        $this->dpsm_code_map = array();
        $this->dpsm_grade = array();
        $this->position_job_map = array();
        $pos_job_file = dirname(__FILE__) . "/data/position_missing_job.csv";
			  
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
    

    protected static  $emp_number_id_type = 'id_type|4';
    function findPersonByEmployeeNumber() {
        $emp_number = $this->mapped_data['emp_number'];
        $emp_number = strtoupper(trim($emp_number));
        if (ctype_digit($emp_number)) {
            $id_type = self::$emp_number_id_type;
        }else {
            return false;
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
                        'value'=>$emp_number
                        )
                    )
                )
            );
        $persons = I2CE_FormStorage::listFields('person_id',array('parent'),true,$where);
        $person_id = false;
        if (count($persons) != 1) {
            return false;
        }
        $data = current($persons);
        if (array_key_exists('parent',$data) && is_string($data['parent']) && substr($data['parent'],0,7) == 'person|') {
            $person_id = substr($data['parent'],7);
        }            
        if (!$person_id) {
            return false;
        }
        $personObj = $this->ff->createForm('person|' . $person_id);
        if (!$personObj instanceof iHRIS_Person) {
            return false;
        }
        $personObj->populate();
        return $personObj;
    }


    function verifyData() {
        $employeenumber = $this->mapped_data['emp_number'];
        if ( empty($employeenumber) ) {
            $this->addBadRecord("Record has no employee number");
            return false;
        }
        return true;
    }

    function getTransactionDate() {
        return $this->getDate('2012/11/29','Y/m/d');
    }

    function createNewPerson() {
        $personObj = $this->findPersonByEmployeeNumber();
        if ($personObj instanceof iHRIS_Person) {
            //$this->addBadRecord("Person already exists in system with that Employee number");
            return false;
        }
        $emp_number = $this->mapped_data['emp_number'];
        $emp_number = strtoupper(trim($emp_number));
        if (ctype_digit($emp_number)) {
            $id_type = self::$emp_number_id_type;
        }else {
            I2CE::raiseMessage("Invalid employee number");
            return false;
        }
        
        //we create the person
        $personObj = $this->ff->createContainer('person');
        
        $fullname = $this->mapped_data['emp_name'];
        
        $names = explode(',', $fullname);
        $surname = $names[0];

        $lastnames = trim($names[1]);
        if( strpos($lastnames, ' ') === false ){
        $firstname = $lastnames;
        }else{
        $firstname = substr($lastnames, 0, strpos($lastnames, ' '));
        $othernames = substr($lastnames, strpos($lastnames, ' '));
        }
        
        I2CE::raiseMessage("Setting name values for $fullname");
        
        I2CE::raiseMessage("Firstname is set to $firstname");
        
        I2CE::raiseMessage("Surname is set to $surname");
        if(!empty($othernames)){
          I2CE::raiseMessage("Othername is set to $othernames");
        }
        
        $personObj->firstname = $firstname;
        $personObj->surname = $surname;
        $personObj->othername = $othernames;
        
        /*/if nationality exists, add it 
        if ($this->mapped_data['nationality'] 
            && ($country_id = array_search($this->mapped_data['nationality'],$this->existing_codes)) !== false) {
            $personObj->nationality = array('country',$country_id);
        }*/

        //now we add in the EMP_NUMBER
        $idObj = $this->ff->createContainer('person_id');
        $idObj->getField('id_type')->setValue(explode('|',$id_type));
        $idObj->getField('id_num')->setValue($emp_number);

        $personID = $this->save($personObj,false);
        $idObj->setParent('person|' . $personID);
        $this->save($idObj);
        return $personObj;
    }

    protected $add_post = null;

    function addPost($dpsm_code,$dpsm_title) {
        $dpsm_code =  strtoupper($dpsm_code);
        if (!prompt("Could not find dpsm post $dpsm_code.Should I add it?",$this->add_post)) {
            return false;
        }
        $postObj = $this->ff->createContainer('post');
        if (!$postObj instanceof I2CE_List) {
            die("Cannot add post");
            return false;
        }
        $postObj->getField('name')->setValue($dpsm_title);    
        $postObj->getField('dpsm_code')->setValue($dpsm_code);    
        if ( ($new_id = $this->save($postObj)) === false) {
            die("Could not save post");
        }
        $this->dpsm_code_map[$dpsm_code] = $new_id;
        echo "Added $dpsm_code\n";
        return $new_id;
    }



    protected $add_facility = null;
    function addFacility() {
        $code = $this->mapped_data['new_loc_inf_code'];
        $facname = $this->mapped_data['unit'];
        $unit_code = $this->mapped_data['new_unit_code'];
        if (!$code || !$facname) {
            return false;
        }
        if (!prompt("Could not find facility $facname. Should I add it?",$this->add_facility)) {
            return false;
        }
        $facObj = $this->ff->createContainer('facility');
        if (!$facObj instanceof I2CE_List) {
            die("Cannot add fac $facname");
            return false;
        }
        $facObj->setID($code);
        $facObj->getField('name')->setValue($facname);    
        if (! $this->save($facObj)) {
            die("Could not save facility");
        }
        $this->facLocations[$unit_code][$code] = $code;
        return $code;
    }


    function setNewPosition($personObj) {
        
        if (!array_key_exists($this->mapped_data['position'],$this->dpsm_code_map)) {
            $postid = $this->addPost($this->mapped_data['position'],$this->mapped_data['title']);
        } else {
            $postid = $this->dpsm_code_map[$this->mapped_data['position']];
        }
        //search for the facility
        $facilityname = strtolower(trim($this->mapped_data['unit']));
        $wherefacility = array(
                        'operator'=>'FIELD_LIMIT',
                        'field'=>'name',
                        'style'=>'lowerequals',
                        'data'=>array(
                            'value'=>$facilityname
                            )
                        );
        $facilityids = I2CE_FormStorage::search('facility', true, $wherefacility);
        
        if(count($facilityids) == 0){
          I2CE::raiseMessage("Could not find facility with that name");
        }elseif(count($facilityids) == 1){
          //$facilityid = $facilityids[0];
          print_r($facilityids);
        }else{
          I2CE::raiseMessage("There is more than one facility with that name");
          }

        //create new forms
        $newPosObj = $this->ff->createContainer('position');
        $newPersonPosObj = $this->ff->createContainer('person_position');
        $newSalaryObj = $this->ff->createContainer('salary');

        
        //post+dpms_code shoud be the new position code
        //set new form data
        $newPosObj->status = array('position_status','closed');
        if ($postid) {
            $newPosObj->post = array('post',$postid);
        }
        $newPosObj->title = $this->mapped_data['title'];
        if ($facilityid) {
            I2CE::raiseMessage("Setting facility Id to $facilityid");
            $newPosObj->facility = array('facility',$facilityid);
        }
        if (array_key_exists($up_title = strtoupper(trim($this->mapped_data['title'])), $this->position_job_map)) {
            $newPosObj->job = $this->position_job_map[$up_title];
        } else {
            $this->addBadRecord("Could not find the job associated to this position");
            //I2CE::raiseMessage("Could not find any job associated to the position " . $this->mapped_data['title']);
        }
        
        if($newPosId = $this->save($newPosObj)){
          I2CE::raiseMessage("could not save this position at this moment because its associated job could not be found");
        }

        $newPersonPosObj->start_date = $this->effective_date;
        $newPersonPosObj->position =  array('position',$newPosId);
        $newPersonPosObj->setParent($personObj->getNameID());
        $newPersonPosId = $this->save($newPersonPosObj);
        
        $newSalaryObj->start_date = $this->effective_date;
        // post+salary_grade is a map_mult .  if it has exactly one selection this shold be the salarycode
        $salarygrade = trim($this->mapped_data['salary_grade']);
        
        //I2CE::raiseMessage("Setting salary grade to currency|1=".$salarygrade);
        $newSalaryObj->salary = array('currency','1',$salarygrade);  //currency|1 is BWP

        $newSalaryObj->setParent('person_position|' . $newPersonPosId);
        $this->save($newSalaryObj);
        return true;
    }


    protected $facLocations;
    protected $existingFacNames =array();
    protected function loadFacilityLookup() {
        $user = new I2CE_User();
        $existingFacs = I2CE_FormStorage::search('facility');        
        $t_existingFacNames = I2CE_FormStorage::listFields('facility',array('name'));
        foreach ($t_existingFacNames as $id=>$data) {
            $data = $data['name'];
            $this->existingFacNames[strtoupper(trim($data))] = $id;
        }
        $create_facs = null;
    }
    
    /**
     * initialize a file into which we record all the bad data/unsuccessful row imports
     * 
     */
    protected function initBadFile() {
        $info = pathinfo($this->file);
        $bad_fp =false;
        $this->bad_file_name = dirname($this->file) . DIRECTORY_SEPARATOR . basename($this->file,'.'.$info['extension']) . '.created_records' .date('d-m-Y_G:i') .'.csv';
        I2CE::raiseMessage("Will put any bad records in $this->bad_file_name");
        $this->bad_headers[] = "ORG. LVL";
        $this->bad_headers[] = "Unit";
        $this->bad_headers[] = "Position";
        $this->bad_headers[] = "Title";
        $this->bad_headers[] = "Grade";
        $this->bad_headers[] = "Authorized";
        $this->bad_headers[] = "Actual";
        $this->bad_headers[] = "Employee Name";
        $this->bad_headers[] = "Employee No.";
        $this->bad_headers[] = "Row";
        $this->bad_headers[] = "Message";
    }
    
}




/*********************************************
*
*      Execute!
*
*********************************************/

ini_set('memory_limit','2G');

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


$processor = new EmployeeProcessor($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
