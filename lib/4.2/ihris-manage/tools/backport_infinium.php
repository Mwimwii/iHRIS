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

class InfiniumProcessor extends Processor {


    protected $create_new_people = null;
    public function __construct($file) {
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
        if (!$mapped_data['transaction']) {
            $this->addBadRecord('No transcation type');
            return false;
        }
        return $mapped_data;
    }

    protected function getExpectedHeaders() {
        return  array(
        'ministry'=>'Ministry',
        'emp_num'=>'Employee Number',
        'omang'=>'Omang/ Exemption',
        'date'=>'Effective Date',
        'transaction'=>'Transaction Type',
        'dpsm_code'=>'New Position',
        'new_pos_title'=>'New Position Title',
        'new_loc'=>'New Location',
        'new_loc_inf_code'=>'New Loc Code',
        'prev_pos'=>'Previous Position',
        'prev_pos_title'=>'Previous Position Title',
        'prev_code'=>'Previous Location',
        'term_code'=>'Termination Code',
        'term_desc'=>'Termination Description',
        'new_stat_code'=>'New Status Code',
        'new_stat_desc'=>'New Status Description',
        'new_unit_code'=>'New Unit Code',
        'new_loc_code'=>'New Org Loc',
        'prev_stat_code'=>'Previous Status Code',
        'prev_stat_desc'=>'Previous Status Description',
        'prev_surnname'=>'Prev Surname',
        'prev_forename'=>'Prev. Forename',
        'surname'=>'Surname',
        'forename'=>'Forename',
        'nationality'=>'Country of Citizenship'
            );
    }
    protected static $required_cols_by_transaction = array(
        'EE'=>array('omang','date','term_code','new_stat_code'),
        'NE'=>array('omang','date','surname','forename','new_stat_code','dpsm_code','new_pos_title'),
        'PS'=>array('omang','date','dpsm_code','new_pos_title'),
        'ST'=>array('omang','date','new_stat_code')
        );

    protected static $infinium_termination_map = array(
        'DISMI'=>'pos_change_reason|3', //dismisal
        'DEATH'=>'pos_change_reason|1' //death
        // 'CONTE'=>'', //terminaion of contract
        // 'COMRE'=>'', //Compulory retirement
        // 'VOLRE'=>'', //volunary retirement
        // 'EXTTR'=>'', //transfer to another ministry
        // 'PURGE'=>'', //code to purge employees  --- SKIP!
        // 'ENDCO'=>'' //end of contract
        );
    protected static $infinium_stat_code_map = array(
        'TEMPF'=>'position_type|8',//Temp full time -- double check
        'PERM'=>'position_type|1',//Permanent and penshionable
        'FTCON'=>'position_type|2',//fixed term contract --douvle check
        'PROB'=>'position_type|6' //probabionary
        //nothing for acting.
        );


    protected $effective_date;
    protected function _processRow() {
        if (!$this->verifyData()) {
            return false;
        }
        if ( ! ($this->effective_date = $this->getTransactionDate())) {
            return false;
        }
        $success = false;
        switch ($this->mapped_data['transaction']) {
        case 'EE':
            $success = $this->processEmployeeExit();
            break;
        case 'ST':
            $success = $this->processStatusChange();
            break;
        case 'PS': 
            if (! ($personObj = $this->findPersonByOmang()) instanceof iHRIS_Person) {
                $msg = "Person " .  $this->mapped_data['forename'] . " "  . $this->mapped_data['surname']. " with OMANG " .$this->mapped_data['omang']  . " does not exist in the system.  Should we create her/her?";
                if (prompt($msg,$this->create_new_people)) {
                    if (! ($personObj = $this->createNewPerson(true))) {
                        break;                
                    }
                } else     {
                    $this->addBadRecord("Person not found");
                    break;
                }
            }
            $new_stat_code = false;
            if ( ($existingPersonPosObj = $this->findLastPositionByOmang(true)) instanceof iHRIS_PersonPosition) {
                $existingPosObj = $existingPersonPosObj->getField('position')->getMappedFormObject();
                if ($existingPosObj instanceof iHRIS_Position) {
                    $new_stat_code = $existingPosObj->getField('pos_type')->getValue();            
                }
                $closed = $this->closeAttachedPosition($existingPersonPosObj);
                $existingPersonPosObj->cleanup();

                if (!$closed) {
                    $personObj->cleanup();
                    break;
                }
            }
            $success = $this->setNewPosition($personObj,$new_stat_code);
            $personObj->cleanup();
            break;
        case 'NE':
            if (! ($personObj = $this->createNewPerson())) {
                break;
            }
            if (array_key_exists($this->mapped_data['new_stat_code'],self::$infinium_stat_code_map)) {
                $new_stat_code = self::$infinium_stat_code_map[$this->mapped_data['new_stat_code']];
            } else {
                $new_stat_code = $this->addStatCode();
            }
            if (!$new_stat_code ) {
                $this->addBadRecord("New Position Status Code has not been mapped to iHRIS");
                $personObj->cleanup();
                break;
            }
            $success = $this->setNewPosition($personObj,$new_stat_code);
            $personObj->cleanup();
            break;
        default:
            $this->addBadRecord("Do not know how to handle the transaction");
            break;
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
    

    protected static  $omang_id_type = 'id_type|1';
    protected static $excemption_id_type = 'id_type|2';
    function findPersonByOmang() {
        $omang = $this->mapped_data['omang'];
        $omang = strtoupper(trim($omang));
        if (ctype_digit($omang)) {
            $id_type = self::$omang_id_type;
        }else if (in_array($omang, array('EXP', 'EXPATRIATE'))) {
            return false; //expatriate
        } else {
            $id_type = self::$excemption_id_type;
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


    


    function findLastPosition($personObj,$only_current) {
        if ($only_current) {
            $where = array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'end_date',
                'style'=>'null'
                );
        } else {
            $where = array();
        }
        $per_pos_id = I2CE_FormStorage::search('person_position', $personObj->getNameId(),$where,'-start_date',1);
        if (!$per_pos_id) {
            return false;
        }
        $persPosObj = I2CE_FormFactory::instance()->createContainer('person_position'.'|'.$per_pos_id);
        if (!$persPosObj instanceof iHRIS_PersonPosition) {
            return false;
        }
        $persPosObj->populate();
        return $persPosObj;
    }

    function findLastPositionByOmang($only_current) {
        if (! ($personObj = $this->findPersonByOmang()) instanceof iHRIS_Person) {
            return false;
        }
        return  $this->findLastPosition($personObj,$only_current);
    }



    function verifyData() {
        $transaction = $this->mapped_data['transaction'];
        if (!array_key_exists($transaction,self::$required_cols_by_transaction)) {
            $this->addBadRecord("Invalid transaction type");
            return false;
        }
        $missing_cols = array();
        foreach (self::$required_cols_by_transaction[$transaction] as $required_col) {
            if ($this->mapped_data[$required_col] === false || (is_string($this->mapped_data[$required_col]) && strlen($this->mapped_data[$required_col]) == 0)) {
                $missing_cols[] = $required_col;
            }
        }
        if (count($missing_cols) > 0) {
            $this->addBadRecord("Missing required columns " . implode(" ",$missing_cols));
            return false;
        }
        return true;
    }

    function getTransactionDate() {
        return $this->getDate($this->mapped_data['date'],'Y/m/d');
    }





    function processEmployeeExit() {
        //first If person does not exist, error out
        if (! ($personObj = $this->findPersonByOmang()) instanceof iHRIS_Person) {
            $this->addBadRecord("Person not found");
            return false;
        }
        if (! ($personPosObj = $this->findLastPositionByOmang(true)) instanceof iHRIS_PersonPosition) {
            $this->addBadRecord("No current position found for this person");
            $personObj->cleanup();
            return false;
        }

        $pos_change_reason = false;
        if (array_key_exists($this->mapped_data['term_code'],self::$infinium_termination_map)) {
            $pos_change_reason = self::$infinium_termination_map[$this->mapped_data['term_code']];
        } else {
            $pos_change_reason = $this->addTermination();
        }
        if (!$pos_change_reason) {
            $this->addBadRecord("Termination code has not been mapped to iHRIS");
            $personPosObj->cleanup();
            $personObj->cleanup();
            return false;
        }
        if (! ($posObj = $this->ff->createContainer( $personPosObj->position))instanceof iHRIS_Position) {
            $this->addBadRecord("No position could be created for EE from " . $personPosObj->getField('position')->getDBValue() . ' from person position' . $personPosObj->getFormID());
            $personPosObj->cleanup();
            $personObj->cleanup();
            return false;
        }
        $posObj->populate();

        $personPosObj->end_date = $this->effective_date;
        $personPosObj->reason = explode("|",$pos_change_reason);
        $pos_status_id = 'discontinued'; //also 'closed' or 'discontinued' as possibilities
        $posObj->status = array('position_status',$pos_status_id);
        $this->save($personPosObj);
        $this->save($posObj);
        $personObj->cleanup();
        return true;        
    }

    protected $add_termination = null;

    function addTermination() {
        $code = $this->mapped_data['term_code'];
        $title = $this->mapped_data['term_desc'];
        if (!$title) {
            return false;
        }
        if (strtoupper($code) == 'PURGE') {
            return false;
        }
        $existing_terminations = I2CE_FormStorage::listFields('pos_change_reason','name');
        foreach ($existing_terminations as $id=>&$data) {
            if (!is_array($data) || !array_key_exists('name',$data) || !$data['name']) {
                unset($existing_terminations[$id]);
                continue;
            }
            $data = $data['name'];
        }
        unset($data);

        if ( ($id = array_search($title,$existing_terminations))!==false) {
            return "pos_change_reason|" . $id;
        }
        if (!prompt("Could not find position change reason $code: $title in " . implode(",",$existing_terminations) . "Should I add it?",$this->add_termination)) {
            return false;
        }
        $posChangeObj = $this->ff->createContainer('position_type');
        if (!$posChangeObj instanceof I2CE_SimpleList) {
            die("Bad position type");
            return false;
        }
        $posChangeObj->setID($code);
        $posChangeObj->getField('name')->setValue($title);    
        $this->save($posChangeObj);
        $mapped = 'pos_change_reason|' . $code;
        self::$infinium_termination_map[$code] = $mapped;
        return $mapped;
    }



    function  addStatCode() {
        $code = $this->mapped_data['new_stat_code'];
        $title = $this->mapped_data['new_stat_desc'];        
        if (!$title) {
            return false;
        }
        $existing_stat_codes = I2CE_FormStorage::listFields('position_type','name');
        foreach ($existing_stat_codes as $id=>&$data) {
            if (!is_array($data) || !array_key_exists('name',$data) || !$data['name']) {
                unset($existing_stat_codes[$id]);
                continue;
            }
            $data = $data['name'];
        }
        unset($data);

        if ( ($id = array_search($title,$existing_stat_codes))!==false) {
            return "position_type|" . $id;
        }
        if (!simple_prompt("Could not find position type $code: $title in " . implode(",",$existing_stat_codes) . "Should I add it?")) {
            return false;
        }
        $posTypeObj = $this->ff->createContainer('position_type');
        if (!$posTypeObj instanceof I2CE_SimpleList) {
            return false;
        }
        $posTypeObj->setID($code);
        $posTypeObj->getField('name')->setValue($title);    
        $this->save($posTypeObj);
        $mapped = 'position_type|' . $code;
        self::$infinium_stat_code_map[$code] = $mapped;
        return $mapped;        
    }



    function processStatusChange() {
        if (! ($existingPersonPosObj = $this->findLastPositionByOmang(true)) instanceof iHRIS_PersonPosition) {
            $this->addBadRecord("No current position found for this person");
            return false;
        }
        if (array_key_exists($this->mapped_data['new_stat_code'],self::$infinium_stat_code_map)) {
            $new_stat_code = self::$infinium_stat_code_map[$this->mapped_data['new_stat_code']];
        } else {
            $new_stat_code = $this->addStatCode();
        }
        if (!$new_stat_code ) {
            $this->addBadRecord("New Position Status Code has not been mapped to iHRIS");
            $existingPersonPosObj->cleanup();
            return false;
        }
        if (! ($existingPosObj = $this->ff->createContainer( $existingPersonPosObj->position))instanceof iHRIS_Position) {
            $this->addBadRecord("No position could be created for SC from " . $existingPersonPosObj->getField('position')->getDBValue() . ' from person position' . $existingPersonPosObj->getFormID() );
            $existingPersonPosObj->cleanup();
            return false;
        }
        $existingPosObj->populate();
        $existingPosObj->pos_type = array('position_type',$new_stat_code);
        $this->save($existingPosObj);
        return true;
    }


    function createNewPerson() {
        $personObj = $this->findPersonByOmang();
        if ($personObj instanceof iHRIS_Person) {
            $this->addBadRecord("Person already exists in system with that OMANG number");
            return false;
        }
        $omang = $this->mapped_data['omang'];
        $omang = strtoupper(trim($omang));
        if (ctype_digit($omang)) {
            $id_type = self::$omang_id_type;
        }else if (in_array($omang, array('EXP', 'EXPATRIATE'))) {
            I2CE::raiseMessage("Don't know how to create an expatriate");
            return false; //expatriate
        } else {
            $id_type = self::$excemption_id_type;
            //exception id
        }
        //for a NE we create the person
        $personObj = $this->ff->createContainer('person');
        $personObj->firstname = $this->mapped_data['forename'];
        $personObj->surname = $this->mapped_data['surname'];

        //if nationality exists, add it 
        if ($this->mapped_data['nationality'] 
            && ($country_id = array_search($this->mapped_data['nationality'],$this->existing_codes)) !== false) {
            $personObj->nationality = array('country',$country_id);
        }

        //now we add in the OMANG
        $idObj = $this->ff->createContainer('person_id');
        $idObj->getField('id_type')->setValue(explode('|',$id_type));
        $idObj->getField('id_num')->setValue($omang);

        $personID = $this->save($personObj,false);
        $idObj->setParent('person|' . $personID);
        $this->save($idObj);
        return $personObj;
    }

    function closeAttachedPosition($existingPersonPosObj) {
        if ($existingPersonPosObj instanceof iHRIS_PersonPosition) {
            if (! ($existingPosObj = $this->ff->createContainer( $existingPersonPosObj->position))instanceof iHRIS_Position) {
                $this->addBadRecord("No position could be created for CAP from " . $existingPersonPosObj->getField('position')->getDBValue() . ' from person position' . $existingPersonPosObj->getFormID() );
                return false;
            }
            $existingPosObj->populate();

            $existingPersonPosObj->end_date = $this->effective_date;
            //$existingPersonPosObj->reason = explode("|",$pos_change_reason);
            $existingPosObj->status = array('position_status','discontinued');
            $this->save($existingPersonPosObj);
            $this->save($existingPosObj);
        }
        return true;
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
        $name = $this->mapped_data['new_loc'];
        $unit_code = $this->mapped_data['new_unit_code'];
        if (!$code || !$name) {
            return false;
        }
        if (!prompt("Could not find facility $name with code $code. Should I add it?",$this->add_facility)) {
            return false;
        }
        $facObj = $this->ff->createContainer('facility');
        if (!$facObj instanceof I2CE_List) {
            die("Cannot add fac $name");
            return false;
        }
        $facObj->setID($code);
        $facObj->getField('name')->setValue($name);    
        if (! $this->save($facObj)) {
            die("Could not save facility");
        }
        $this->facLocations[$unit_code][$code] = $code;
        return $code;
    }




    function setNewPosition($personObj,$new_stat_code) {
        
        if (!array_key_exists($this->mapped_data['dpsm_code'],$this->dpsm_code_map)) {
            //$postid = $this->addPost($this->mapped_data['dpsm_code']);
            $postid = $this->addPost($this->mapped_data['dpsm_code'],$this->mapped_data['new_pos_title']);
            //$this->addBadRecord("DPSM Code ({$this->mapped_data['dpsm_code']}) is not in system");
            //return false;
        } else {
            $postid = $this->dpsm_code_map[$this->mapped_data['dpsm_code']];
        }
        $facilityid = $this->lookupFacility();
        if ($new_stat_code  !== false) {
            if (is_array($new_stat_code) && count($new_stat_code) == 2 && $new_stat_code[0] == 'position_type') {
                //do nothing.
            } else if (is_scalar($new_stat_code)) {
                $new_stat_code = array('position_type',$new_stat_code);
            } else {
                //$this->addBadRecord("Invalid position status code");
                //return false;
            }
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
        $newPosObj->title = $this->mapped_data['new_pos_title'];
        if ($facilityid) {
            $newPosObj->facility = array('facility',$facilityid);
        }
        if (array_key_exists($up_title = strtoupper(trim($this->mapped_data['new_pos_title'])), $this->position_job_map)) {
            $newPosObj->job = $this->position_job_map[$up_title];
            //I2CE::raiseMessage("Found job associated to the position " . $this->mapped_data['new_pos_title']);
        } else {
            I2CE::raiseMessage("Could not find any job associated to the position " . $this->mapped_data['new_pos_title']);
        }
        if (is_array($new_stat_code)) {
            $newPosObj->pos_type = $new_stat_code;
        }
        $newPosId = $this->save($newPosObj);

        $newPersonPosObj->start_date = $this->effective_date;
        $newPersonPosObj->position =  array('position',$newPosId);
        $newPersonPosObj->setParent($personObj->getNameID());
        $newPersonPosId = $this->save($newPersonPosObj);

        $newSalaryObj->start_date = $this->effective_date;
        // post+salary_grade is a map_mult .  if it has exactly one selection this shold be the salarycode
        if ( array_key_exists($this->mapped_data['dpsm_code'],$this->dpsm_grade) && ( $salarycode = $this->dpsm_grade[$this->mapped_data['dpsm_code']])) {
            $newSalaryObj->salary = array('currency','1',$salarycode);  //currency|1 is BWP
        }
        $newSalaryObj->setParent('person_position|' . $newPersonPosId);
        $this->save($newSalaryObj);
        return true;
    }


    protected $facLocations;
    protected $existingFacNames =array();
    protected function loadFacilityLookup() {
        $user = new I2CE_User();
        $this->facLocations = array();
        $file = dirname(__FILE__) . "/data/facility_location.csv";
        $dataFile = new CSVDataFile($file);
        $headers = $dataFile->getHeaders();
        $needed = array('facility_id','district_id','location_code','unit_code');
        $map = array();
        foreach ($needed as $head) {
            if (($header_col = array_search($head,$headers)) === false) {
                die("Imprroper $file.  could not find $head\n");
            }
            $map[$head] = $header_col;
        }
        while (($data = $dataFile->getDataRow()) !== FALSE) {
            foreach ($needed as $head) {
                if (!array_key_exists($map[$head],$data) || !trim($data[$map[$head]])) {
                    I2CE::raiseMessage("Skipping " . implode(",", $data));
                    continue 2;
                }
            }
            $loc_code = trim($data[$map['location_code']]);
            $unit_code = trim($data[$map['unit_code']]);
            $fac_id = trim($data[$map['facility_id']]);
            $dis_id = trim($data[$map['district_id']]);
            if (!array_key_exists($unit_code,$this->facLocations)) {
                $this->facLocations[$unit_code] = array();
            }
            if (!array_key_exists($loc_code,$this->facLocations[$unit_code])) {
                $this->facLocations[$unit_code][$loc_code] = array();
            }
            if ($unit_code == 'PHCAR') { 
                //have multiple facilities in iHRIS for a (PHCAR,location code).   we will create below a new facility for each pair of (PHCAR,location code) using the district_id
                $this->facLocations[$unit_code][$loc_code][] = $dis_id;
                $this->facLocations[$unit_code][$loc_code] =  array_unique($this->facLocations[$unit_code][$loc_code]);
            } else {
                $this->facLocations[$unit_code][$loc_code][] = $fac_id;
            }
        }
        $dataFile->close();
        $existingFacs = I2CE_FormStorage::search('facility');        
        $t_existingFacNames = I2CE_FormStorage::listFields('facility',array('name'));
        foreach ($t_existingFacNames as $id=>$data) {
            $data = $data['name'];
            $this->existingFacNames[strtoupper(trim($data))] = $id;
        }
        $create_facs = null;
        foreach ($this->facLocations as $unit=>$units) {
            foreach ($units as $loc=>$facs) {
                if ($unit == 'PHCAR') {
                    //create any PHCAR facilities for each of the districts if needed                
                    $dnames = array();
                    $facid = "PHCAR_{$loc}";
                    if (!in_array($facid,$existingFacs)) {
                        //need to create this facility
                        foreach ($facs as $districtid) {
                            if (! ($disObj = $this->ff->createContainer($districtid)) instanceof iHRIS_District) {
                                continue;
                            }
                            $disObj->populate();
                            $dnames[] = $disObj->getField('name')->getValue();
                        }
                        $facname = "PHCAR: " . implode(", " ,$dnames);
                        if (!prompt("Facility $facname does not exist.  Should we create it?",$create_facs)) {
                            die("Usage all facilities need to be created\n");
                        }
                        I2CE::raiseMessage("Creating $facname");
                        if  (! ($facObj = $this->ff->createContainer('facility')) instanceof iHRIS_Facility) {
                            I2CE::raiseMessage("Could not instantiate facility form");
                            continue;
                        }
                        $facObj->getField('name')->setValue($facname);
                        $facObj->setID($facid);
                        $t_facid = $this->save($facObj);
                        if ($t_facid != $facid) {
                            die("Facility id creation mismatch ($t_facid/$facid)");
                        }
                    }
                    //replace the  above disitrctid with the facilityid                
                    $facs = array($facid);
                    $this->facLocations[$unit][$loc] = $facs;
                } else   if (count($facs) > 1) {
                    I2CE::raiseMessage("Warning: UnitCode ($unit) and LocCode($loc) maps to more than one facility:\n" . implode(" ",$facs));
                }
                if (count($facs) != 1)  {
                    I2CE::raiseMessage("Improper number of facilities for Unit $unit Location $loc:\n\t" . print_r($facs,true), E_USER_ERROR);
                }
                reset ($facs);
                $facid = current($facs);
                if (substr($facid,0,9) == 'facility|') {
                    $facid = substr($facid,9);
                }
                $this->facLocations[$unit][$loc] = $facid;
            }
        }
    }

    function lookupFacility() {
        $unit_code = $this->mapped_data['new_unit_code'];
        $loc_code = $this->mapped_data['new_loc_code'];
        $loc_name = strtoupper(trim($this->mapped_data['new_loc']));
        $id = false;
        if (array_key_exists($unit_code,$this->facLocations)
            && is_array($this->facLocations[$unit_code]) 
            && array_key_exists($loc_code,$this->facLocations[$unit_code]) 
            && is_string($this->facLocations[$unit_code][$loc_code])
            && strlen($this->facLocations[$unit_code][$loc_code])) {
            $id =$this->facLocations[$unit_code][$loc_code];
        } else if (array_key_exists($loc_name,$this->existingFacNames)) {
            $id = $this->existingFacNames[$loc_name];
        }
        return $id;
    }

}




/*********************************************
*
*      Execute!
*
*********************************************/

ini_set('memory_limit','4G');


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


$processor = new InfiniumProcessor($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End: