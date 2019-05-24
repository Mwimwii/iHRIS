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

class FormBUpdate extends Processor {
    public function __construct($file) {
        parent::__construct($file);

    }


    protected function mapData() {
        $mapped_data = parent::mapData();        
        return $mapped_data;
    }

    protected function getExpectedHeaders() {
        return  array(
            'surname' => 'Surname',
            'firstname' => 'Firstname',
            'id_num' => 'Omang Exemption',
            'expiration_date' => 'Omang Expiry Date (YYYY-mm-dd)',
            'position' => 'LU-Corrected CP',
            'deploy_facility' => 'Duty Station',
            'district_code' => 'LU-DistNo',
            //'lu_current_pos' => 'LU-CP',
            'position_title' => 'LU-Corrected CP'
            );
    }
    
    public $districtMap = array(
        //the value is the id for the district
        '4' => '1',
        '13' => '10',
        '28' => '24',
        '10' => '3',
        '16' => '4',
        '31' => '46',
        '7' => '6',
        '23' => '7',
        '15' => '5',
        '22' => '23',
        '24' => '8',
        '11' => '9',
        '9' => '10',
        '5' => '11',
        '20' => '28',
        '18' => '12',
        '21' => '26',
        '8' => '13',
        '30' => '45',
        '26' => '14',
        '1' => '15',
        '2' => '16',
        '14' => '17',
        '3' => '31',
        '19' => '27',
        '29' => '18',
        '17' => '19',
        '6' => '20',
        '27' => '22',
        '25' => '25',
        '12' => '21'
      );
    
    protected function _processRow() {
        //$this->process_stats_checked = array();
            $id = $this->findPersonByOmang();
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
            $this->addBadRecord("Needs to create a new position");
            $personPosObj = $this->ff->createContainer('person_position');
            $personPosObj->getField('infinium_valid_date')->setValue($this->convertDate('25-Jun-13'));
            $personPosObj->getField('position')->setValue(array('position',$this->getPosIDByName(trim($this->mapped_data['position_title']))));
            $personPosObj->setParent($id);
            
            $personPosObjId = $this->save($personPosObj);
            //echo "created new position with id = personPosObjId and parent = $id\n";
            //$this->personPositions[$id] = 'person_position|'.$personPosObjId;
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
            $this->addBadRecord("need to create new position!");
                //$pos = $this->getPosIDByName(trim($this->mapped_data['position_title']));
                //$position = 'position|'.$pos;
              }
            echo "updating the position from DB ".$position."\n";
            $posObj = $this->ff->createContainer($position);
            $posObj->deploy_facility = array('facility',$this->getFacilityIDByName($this->mapped_data['deploy_facility']));
            $posObj->job = array('job',$this->getJobIDByName(trim($this->mapped_data['position_title'])));
            $posObj->title = trim($this->mapped_data['position_title']);
            $posId = $this->save($posObj);
            $posObj->cleanup();
            
            $personPosObj->getField('infinium_valid_date')->setValue($this->convertDate('25-Jun-13'));
            $personPosObj->getField('position')->setValue(explode('|',$position));
            $this->save($personPosObj);
            $personPosObj->cleanup();
          }
        return true;
    }
    
    protected static  $omang_id_type = 'id_type|1';
    protected static $excemption_id_type = 'id_type|2';
    protected $duplicate_ids = array();
    
    function findPersonByOmang() {
        $personObj= false;
        $omang = strtoupper(trim($this->mapped_data['id_num']));
        $firstname = strtoupper(trim($this->mapped_data['firstname']));
        $surname = strtoupper(trim($this->mapped_data['surname']));
        $id_type = false;
        //we only want the Omang, because the exemption id can exist in duplicates
        if (strlen($omang) > 0 && ctype_digit($omang)) {
            $id_type = self::$omang_id_type;
            $id_num = $omang;
            
            $whereOmang = array(
                'operator'=>'AND',
                'operand'=>array(
                  0=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'id_num',
                    'style'=>'equals',
                    'data'=>array(
                      'value'=>$omang
                    )
                  ),
                  1=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'id_type',
                    'style'=>'equals',
                    'data'=>array(
                      'value'=>$id_type
                    )
                  )
                )
              );
            $persons = I2CE_FormStorage::listFields('person_id',array('id','parent'), false, $whereOmang);
          }
        if(isset($persons) && count($persons) == 1){
            $data = current($persons);
            return $data['parent']; //person|123123123
          }          
        else{ //search by names
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
                 $person_id = 'person|' . current($personIds);
                 return $person_id;
              }
              elseif(count($personIds) > 1){ 
                $this->addBadRecord('duplicates ,'.implode(',', $personIds));
              }
              else{
                  $this->addBadRecord('never existed');
                }
          }
    }
    
      public function getPosIDByName($name) {
        if(ctype_digit($jobId = $this->getJobIDByName($name))){
            $posObj = $this->ff->createContainer('position');
            $posObj->title = trim($name);
            $posObj->job = array('job',$jobId);
            //$posObj->facility = array('facility',trim($this->getFacilityIDByName($this->mapped_data['facility'])));
            $posId = $this->save($posObj);
            $posObj->cleanup();
            echo "returning position id as position|$posId\n";
            return $posId;
          }else{
              $this->addBadRecord("Badness: this position can't be handled by the script");
              return false;
            }
    }

    public function getFacilityIDByName($name) {
        $fname = strtolower($name);
        $where = array(
                  'operator' => 'FIELD_LIMIT',
                  'field'=>'name',
                  'style'=>'lowerequals',
                  'data'=>array(
                      'value' => trim($fname)
                  )
                );
        $districtCode = trim($this->mapped_data['district_code']);
        if($districtCode == 31 || $districtCode == 32 || $districtCode == 33){
            $district = 31;
          }else{
            $district = $districtCode;
            }

        $facilities = I2CE_FormStorage::listFields('facility',array('id', 'name'), false, $where);
          if(count($facilities) >= 1){
            $data = current($facilities);
            echo "Found facility in DB, Returning ID as ".$data['id']."\n";
            $facObj = $this->ff->createContainer('facility|'.$data['id']);
            $facObj->location = array('district', $this->districtMap[$district]);
            $this->save($facObj);
            $facObj->cleanup();
            return $data['id'];
          }
          elseif(count($facilities) == 0){
            echo "Facility not found, creating\n";
            $facObj = $this->ff->createContainer('facility');
            $facObj->name = trim($name);
            $facObj->location = array('district', $this->districtMap[$district]);
            $facId = $this->save($facObj);
            $facObj->cleanup();
            echo "returning facility is as $facId\n";
            return $facId;
          }else{
              $this->addBadRecord("Badness: this facility can't be handled by the script");
            }
    }
 
    
    function getJobIDByName($name) {
        $where = array(
                  'operator' => 'FIELD_LIMIT',
                  'field'=>'title',
                  'style'=>'lowerequals',
                  'data'=>array(
                      'value' => trim($name)
                  )
                );
        $jobTitles = I2CE_FormStorage::listFields('job',array('id', 'title'), true, $where);
          if(count($jobTitles) >= 1){
            $data = current($jobTitles);
            echo "Found title in DB, Returning ID as ".$data['id']."\n";
            return $data['id'];
          }
          elseif(count($jobTitles) == 0){
            echo "Job not found, creating\n";
            $jobObj = $this->ff->createContainer('job');
            $jobObj->title = trim($name);
            $jobId = $this->save($jobObj);
            //$jobId = $jobObj->getNameID();
            $jobObj->cleanup();
            echo "returning job is as $jobId\n";
            return $jobId;
          }else{
              $this->addBadRecord("Badness: this position can't be handled by the script");
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


$processor = new FormBUpdate($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End: