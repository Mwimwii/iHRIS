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

class PositionsAllRecord extends Processor {

    protected $user;
    protected $create_new_people = null;
    protected $person_ids = array();
    public function __construct($file) {
        I2CE::raiseMessage("Loading person_id lookup");
        $this->person_ids= $this->loadPersonIds();
        I2CE::raiseMessage("Loading positions and person_positions lookup");
        $this->loadPersonPositions();
        I2CE::raiseMessage("Loading positions lookup");
        $this->loadPositions();
        $this->user = new I2CE_User();
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
            'department' => 'Department',
            'department_description' => 'Department description',
            //'division' => 'Division',
            'division_description' => 'Division description',
            'location' => 'Location',
            'district' => 'Location description',
            'unit' => 'Unit',
            'facility' => 'Unit description',
            'position' => 'Position',
            'position_title' => 'Position title',
            'id_num' => 'ID Number',
            'surname' => 'Surname',
            'firstname' => 'Forename',
            'othername' => 'Middle name',
            'date_employed' => 'Date Employed',
            'employment_status' => 'Employment status',
            'position_start_date' => 'Date entered position'
          );
    }
    
    protected function _processRow() {
        //$this->getJobIDByName($this->mapped_data['position_title']);
        $id = $this->getPersonId();//get personID as person|123123
            //echo $this->getJobIDByName(trim($this->mapped_data['position_title']))."\n";
        $where = array(
              'operator'=>'AND',
              'operand'=>array(
                  0=>array(
                      'operator'=>'FIELD_LIMIT',
                      'field'=>'parent',
                      'style'=>'equals',
                      'data'=>array(
                          'value'=>$id
                          )
                      ),
                  1=>array(
                      'operator'=>'FIELD_LIMIT',
                      'field'=>'end_date',
                      'style'=>'null'
                      )
                  )
              );     
        $per_pos_id = I2CE_FormStorage::listFields('person_position', array('id','position'),false,$where,'-start_date',1);
        print_r($per_pos_id);
        $data = current($per_pos_id);
        
        if(empty($data)){ //if person not in personpositions, create
            echo "Needs to create a new position \n";
            $personPosObj = $this->ff->createContainer('person_position');
            $personPosObj->getField('start_date')->setValue($this->convertDate($this->mapped_data['position_start_date']));
            $personPosObj->getField('infinium_valid_date')->setValue($this->convertDate('25-Jun-13'));
            $personPosObj->getField('position')->setValue(array('position',$this->getPosIDByName(trim($this->mapped_data['position_title']))));
            $personPosObj->setParent($id);
            
            $personPosObjId = $this->save($personPosObj);
            echo "created new position with id = personPosObjId and parent = $id\n";
            $this->personPositions[$id] = 'person_position|'.$personPosObjId;
          }
        else{
            $personPosObj = $this->ff->createContainer('person_position|'.$data['id']);
            if(!$personPosObj){
                $this->addBadRecord("Could not update person position");
                return false;
              }
            $personPosObj->populate();
            $position = $data['position'];
            if(empty($position)){
              echo "need to create new position!\n";
                $pos = $this->getPosIDByName(trim($this->mapped_data['position_title']));
                $position = 'position|'.$pos;
              }
            $pos_type = strtolower(trim($this->mapped_data['employment_status']));
            echo "updating the position from DB ".$position;
            $posObj = $this->ff->createContainer($position);
            $posObj->title = trim($this->mapped_data['position_title']);
            $posObj->job = array('job',$this->getJobIDByName(trim($this->mapped_data['position_title'])));
            $posObj->status = array('position_status','closed');
            $posObj->pos_type = array('position_type',$this->position_types[$pos_type]);
            $posId = $this->save($posObj);
            $posObj->cleanup();
            
            $start_date = $this->convertDate(trim($this->mapped_data['position_start_date']));
            $personPosObj->getField('start_date')->setValue($start_date);
            $personPosObj->getField('infinium_valid_date')->setValue($this->convertDate('25-Jun-13'));
            $personPosObj->getField('position')->setValue(explode('|',$position));
            $this->save($personPosObj);
            
          }
        return true;
    }
    
    protected $position_types = array(
      'fixed term contract' => '2',
      'permanent & pensionable' => '1',
      'permanent part time' => 'PERMP',
      'probationary' => '6',
      'temporary full time' => '8',
      'temporary part time' => '7'
    );
    
    
    protected static  $omang_id_type = 'id_type|1';
    protected static $excemption_id_type = 'id_type|2';
    protected static $employeenum_id_type = 'id_type|4';
    protected $duplicate_ids = array();
    
    function getPersonId() {
        $personObj= false;
        
        $omang = strtoupper(trim($this->mapped_data['id_num']));
        $employeenum = trim($this->mapped_data['employee_number']);
        
        $surname = trim($this->mapped_data['surname']);
        $firstname = trim($this->mapped_data['firstname']);
        $othername = trim($this->mapped_data['othername']);

        $id_type = false;
        //we only want the Omang, because the exemption id can exist in duplicates
        if (strlen($omang) > 0 && ctype_digit($omang)) {
            $id_type = self::$omang_id_type;
            $id_num = $omang;
        }else{
            $id_type = self::$employeenum_id_type;
            $id_num = $employeenum;
          }
        
        $ids = array();
            
            if(!empty($this->mapped_data['passport_num'])){
              $ids['P'] = ($this->mapped_data['passport_num']);
              }
            if(!empty($employeenum)){
              $ids['4'] = $employeenum;
              }
            if(!empty($omang) && ctype_digit($omang)){
              $ids['1'] = $omang;
              }
            if(!empty($omang) && !ctype_digit($omang)){
              $ids['2'] = $omang;
              }
              
        
            if (array_key_exists($id_num, $this->person_ids)) {
                //$this->addBadRecord('found by id number');
                $person_id = $this->person_ids[$id_num];
                return $person_id;
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
                                  'value'=>strtolower($surname) 
                                  )
                              ),
                          1=>array(
                              'operator'=>'FIELD_LIMIT',
                              'field'=>'firstname',
                              'style'=>'lowerequals',
                              'data'=>array(
                                  'value'=> strtolower($firstname) 
                                  )
                              )
                          )
                      );
                  $personIds = I2CE_FormStorage::search('person',false,$where);
                  if (count($personIds) == 1) { //record found but couldn't match by id_num, therefore need to update id
                    //$this->addBadRecord('needs update the person_id');
                     $person_id = 'person|' . current($personIds);
                    /* foreach($ids as $type=>$number){
                      $idObj = $this->ff->createContainer('person_id');
                       $idObj->getField('id_type')->setValue(array('id_type',$type));
                       if(!empty($this->mapped_data['passport_expiry_date'])){
                          $idObj->getField('expiration_date')->setValue($this->convertDate((trim($this->mapped_data['passport_expiry_date']))));
                         }
                        $idObj->getField('id_num')->setValue($number);
                        $idObj->setParent($person_id);
                        $this->save($idObj);
                        $idObj->cleanup(); 
                      }*/
                     return $person_id;
                  }
                  else{
                      $this->addBadRecord('either duplicates or never existed');
                  }
              }  
    }
    
    protected $positions = array();
    protected $personPositions = array();
    function loadPersonPositions() {
        $where = array(
            'operator'=>'FIELD_LIMIT',
            'field'=>'end_date',
            'style'=>'null'
            );
        foreach (I2CE_FormStorage::listFields('person_position',array('parent','position', 'id'), true, $where) as $id=>$data) {
           $this->personPositions[$data['parent']] = 'person_position|'.$data['id']; //positions['person|123] = 'person_position|123'
        }
      return $this->personPositions;
    }
    function loadPositions() {
        $where = array(
            'operator'=>'FIELD_LIMIT',
            'field'=>'end_date',
            'style'=>'null'
            );
        foreach (I2CE_FormStorage::listFields('person_position',array('parent','position'), true, $where) as $id=>$data) {
           $this->positions[$data['parent']] = $data['position']; //positions['person|123] = 'position|123'
        }
      return $this->positions;
    }
    
    function hasLastPosition($person_id){
        $where = array(
              'operator'=>'AND',
              'operand'=>array(
                  0=>array(
                      'operator'=>'FIELD_LIMIT',
                      'field'=>'parent',
                      'style'=>'equals',
                      'data'=>array(
                          'value'=>$person_id
                          )
                      ),
                  1=>array(
                      'operator'=>'FIELD_LIMIT',
                      'field'=>'end_date',
                      'style'=>'null'
                      )
                  )
              );
        $per_pos_id = I2CE_FormStorage::listFields('person_position', array('id','position'),$where,'-start_date');
        $data = current($per_pos_id);
        echo "returning position ".$data['position']."\n";
        return $data['position']; 
      }
    
    function getPosIDByName($name) {
        $pos_type = strtolower(trim($this->mapped_data['employment_status']));
        if(ctype_digit($jobId = $this->getJobIDByName($name))){
            $posObj = $this->ff->createContainer('position');
            $posObj->title = trim($name);
            $posObj->job = array('job',$jobId);
            //$posObj->facility = array('facility',trim($this->getFacilityIDByName($this->mapped_data['facility'])));
            $posObj->status = array('position_status','closed');
            $posObj->pos_type = array('position_type',$this->position_types[$pos_type]);
            $posId = $this->save($posObj);
            $posObj->cleanup();
            echo "returning position id as position|$posId\n";
            return $posId;
          }else{
              $this->addBadRecord("Badness: this position can't be handled by the script");
              return false;
            }
    }
    
    function getJobIDByName($name) {
        $title = strtolower(trim($name));
        $where = array(
                  'operator' => 'FIELD_LIMIT',
                  'field'=>'title',
                  'style'=>'lowerequals',
                  'data'=>array(
                      'value' => trim($title)
                  )
                );
    
        $jobTitles = I2CE_FormStorage::listFields('job',array('id', 'title'), true, $where);
          if(count($jobTitles) >= 1){
            $data = current($jobTitles);
            return $data['id'];
          }
          elseif(count($jobTitles) == 0){
            $jobObj = $this->ff->createContainer('job');
            $jobObj->title = trim($name);
            $jobId = $this->save($jobObj);
            $jobObj->cleanup();
            echo "returning job id as job|$jobId\n";
            return $jobId;
          }else{
              $this->addBadRecord("Badness: this position can't be handled by the script");
              return false;
            }
    }
    
    function getFacilityIDByName($name) {
        $facilityName = strtolower(trim($name));
        $where = array(
                  'operator' => 'FIELD_LIMIT',
                  'field'=>'facilityName',
                  'style'=>'lowerequals',
                  'data'=>array(
                      'value' => trim($facilityName)
                  )
                );
    
        $facilityNames = I2CE_FormStorage::listFields('facility',array('id', 'facilityName'), true, $where);
          if(count($facilityNames) >= 1){
            $data = current($facilityNames);
            return $data['id'];
          }
          elseif(count($facilityNames) == 0){
            $facilityObj = $this->ff->createContainer('job');
            $facilityObj->name = trim($name);
            $facilityObj->location = array('district',$this->districts[$this->mapped_data['district']]);
            $facId = $this->save($facilityObj);
            $facilityObj->cleanup();
            echo "returning facility id as facility|$facId\n";
            return $facId;
          }else{
              $this->addBadRecord("Badness: this position can't be handled by the script");
              return false;
            }
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
    
    function loadPersonIds(){
        $personId = array();
        $person_ids = I2CE_FormStorage::listFields('person_id',array('id_type', 'id_num', 'parent'));
        foreach($person_ids as $data){
            $personId[$data['id_num']] = $data['parent'];
          }
        return $personId;
      }
    
    
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
        }
        $month = $Months[strtolower($month)];
        return $this->getDate($yr.'/'.$month.'/'.$day, 'Y/m/d');
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


$processor = new PositionsAllRecord($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End: