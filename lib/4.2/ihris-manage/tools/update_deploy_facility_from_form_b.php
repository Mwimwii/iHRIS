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

class DeployFacilityProcessor extends Processor {


    protected $create_new_people = null;
    protected $person_ids = array();
    public function __construct($file) {
        I2CE::raiseMessage("loading person positions");
        $this->loadPersonPositions();
        I2CE::raiseMessage("loading facilities");
        $this->loadFacilities();
        parent::__construct($file);

    }


    protected function mapData() {
        $mapped_data = parent::mapData();        
        if (!$mapped_data['id_num']) {
            $this->addBadRecord('No transcation type');
            return false;
        }
        return $mapped_data;
    }
    
    public $districtMap = array(
        '4' => 'district|1',
        '13' => 'district|10',
        '28' => 'district|24',
        '10' => 'district|3',
        '16' => 'district|4',
        '31' => 'district|46',
        '7' => 'district|6',
        '23' => 'district|7',
        '15' => 'district|5',
        '22' => 'district|23',
        '24' => 'district|8',
        '11' => 'district|9',
        '9' => 'district|10',
        '5' => 'district|11',
        '20' => 'district|28',
        '18' => 'district|12',
        '21' => 'district|26',
        '8' => 'district|13',
        '30' => 'district|45',
        '26' => 'district|14',
        '1' => 'district|15',
        '2' => 'district|16',
        '14' => 'district|17',
        '3' => 'district|31',
        '19' => 'district|27',
        '29' => 'district|18',
        '17' => 'district|19',
        '6' => 'district|20',
        '27' => 'district|22',
        '25' => 'district|25',
        '12' => 'district|21'
      );
    
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
            'current_position' => 'LU-Corrected CP'
            );
    }
    
    protected function _processRow() {
        //$this->process_stats_checked = array();
            $this->findPersonByOmang();
            //$this->addBadRecord('Could not find/create person');
            //$this->processStats('bad_person');
        return true;
    }
    
    protected static  $omang_id_type = 'id_type|1';
    protected static $excemption_id_type = 'id_type|2';
    protected $duplicate_ids = array();
    
    function findPersonByOmang() {
        $personObj= false;
        $omang = strtoupper(trim($this->mapped_data['id_num']));
        $id_type = false;
        $surname = strtolower($this->mapped_data['surname']);
        $firstname = strtolower($this->mapped_data['firstname']);
        //we only want the Omang, because the exemption id can exist in duplicates
        if (strlen($omang) > 0 && ctype_digit($omang)) {
            $id_type = self::$omang_id_type;
            $id_num = $omang;
          }
        else{
            $id_type = self::$excemption_id_type;
            $id_num = $omang;
          }
                
        $whereId = array(
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
                        'style'=>'contains',
                        'data'=>array(
                            'value'=>$id_num
                            )
                        )
                    )
                );
        $personIds = I2CE_FormStorage::listFields('person_id',array('id','parent','expiration_date'), true, $whereId);
        
        /*
         * then move onto updating the job title in the job form and
         * finally update the position title in the position form
         */
         if(empty($personIds)){
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
              if ((count($personIds) > 1)) {
                  $this->addBadRecord('duplicate records exists with that name');
                  return false;
              }
              if (count($personIds) >= 1) {
                  $person_id = 'person|' . current($personIds);
                  //update position informaiton with deploy_facility
                    $facilityId = $this->getFacilityIDByName2(trim($this->mapped_data['deploy_facility']));
                    if(($positionObj = $this->ff->createContainer($this->positions[$person_id])) instanceof iHRIS_BWPosition){
                      echo "Saving Position ".$this->positions[$person_id]."\n";
                        $positionObj->populate();
                        $positionObj->getField('deploy_facility')->setValue(array('facility',$facilityId));
                        $this->save($positionObj);
                        $positionObj->cleanup();
                    }
                }  
           }
        else{
        foreach( $personIds as $id => $data ){
          //update position informaiton with deploy_facility
            $facilityId = $this->getFacilityIDByName2(trim($this->mapped_data['deploy_facility']));
            if(($positionObj = $this->ff->createContainer($this->positions[$data['parent']])) instanceof iHRIS_BWPosition){
              echo "Saving Position ".$this->positions[$data['parent']]."\n";
                $positionObj->populate();
                $positionObj->getField('deploy_facility')->setValue(array('facility',$facilityId));
                $this->save($positionObj);
                $positionObj->cleanup();
            }
        }
        }
    }
    
    protected $positions = array();
    function loadPersonPositions() {
        foreach (I2CE_FormStorage::listFields('person_position',array('parent','position')) as $id=>$data) {
           $this->positions[$data['parent']] = $data['position']; //positions['person|123] = 'position|123'
        }
      return $this->positions;
    }
    
protected $facs = false;
 
function getFacilityIDByName($name) {
  $lname = strtolower($name);
  $where = array(
              'operator' => 'FIELD_LIMIT',
              'field'=>'name',
              'style'=>'lowerequals',
              'data'=>array(
                  'value' => $lname
              )
          );
          
    $facilityIds = I2CE_FormStorage::listFields('facility',array('id', 'name'), false, $where);
  
  $districtCode = trim($this->mapped_data['district_code']);
  if($districtCode == 31 || $districtCode == 32 || $districtCode == 33){
      $district = 31;
    }else{
      $district = $districtCode;
      }
  if (count($facilityIds) == 0) {
      $this->addBadRecord("Facility not found, creating");
     $facObj = I2CE_FormFactory::instance()->createContainer('facility');
     $facObj->location = explode('|', $this->districtMap[$district]);
     $facObj->name = $name;
     $id = $this->save($facObj);
     $this->addBAdRecord("returning id $id");
     return $id;
  }
  else{
    $facilitydata = current($facilityIds);
    $this->addBAdRecord("Facility Found, returning id ".$facilitydata['id'] );
    return $facilitydata['id'];
  }
}
    
    protected $facilities = array();
public function loadFacilities(){
    $where = array(
            'operator'=>'FIELD_LIMIT',
            'field'=>'name',
            'style'=>'not_null'
          );    
    $facilityIds = I2CE_FormStorage::listFields('facility',array('id', 'name'), false, $where);
    foreach($facilityIds as $data => $value){
      $this->facilities[ $value['id'] ] = strtolower($value['name']);
      }
    echo "facilities loaded ".count($this->facilities);
    return $this->facilities;
  }
  
function getFacilityIDByName2($name) {
  $lname = strtolower($name);
      
  $districtCode = trim($this->mapped_data['district_code']);
  if($districtCode == 31 || $districtCode == 32 || $districtCode == 33){
      $district = 31;
    }else{
      $district = $districtCode;
      }
  if ( !in_array($lname, $this->facilities) ) {
      $this->addBadRecord("Facility not found, creating");
     $facObj = I2CE_FormFactory::instance()->createContainer('facility');
     $facObj->location = explode('|', $this->districtMap[$district]);
     $facObj->name = $name;
     $id = $this->save($facObj);
     $this->addBAdRecord("returning id $id");
     $this->facilities[$id] = $lname;
     return $id;
  }
  else{
    $this->addBAdRecord("returning id as " .array_search($lname, $this->facilities));
    return array_search($lname, $this->facilities);
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
    
    
    
    function getTransactionDate() {
        return $this->getDate($this->mapped_data['expiration_date'],'n/j/Y');
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


$processor = new DeployFacilityProcessor($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End: