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

class UpdateFacility2 extends Processor {


    protected $create_new_people = null;
    protected $person_ids = array();
    public function __construct($file) {
        parent::__construct($file);

    }


    protected function mapData() {
        $mapped_data = parent::mapData();        
        return $mapped_data;
    }
    
    
    protected function getExpectedHeaders() {
        return  array(
            'old_name' => 'old name',
            'new_name' => 'new name',
            //'old_type' => 'old type',
            'new_type' => 'new type',
            'sector' => 'sector',
            'comment'=> 'comment',
            'district'=>'district id'
            );
    }
    
    protected function _processRow() {
        $this->facilityExists($this->mapped_data['old_name'],$this->mapped_data['new_name'],$this->mapped_data['new_type'],$this->mapped_data['comment'],$this->mapped_data['district']);
          
        return true;
    }
          
    public function updateFacilityType( $name ){
        $lname = strtolower( trim($name) );
          $where = array(
            'operator'=>'FIELD_LIMIT',
            'field'=>'name',
            'style'=>'lowerequals',
            'data'=>array(
                  'value' => $lname
                )
            );
          $types = I2CE_FormStorage::search('facility_type', false, $where);
          if( count($types) >= 1 ){
              return current($types); //like 123432
            }
          elseif( count($types) == 0){
              $this->addBadRecord("Facility type not found");
              $formObj = $this->ff->createContainer('facility_type');
              $formObj->getField('name')->setValue(trim($name));
              $typeId = $this->save($formObj);
              return $typeId; //like 123432
            }
      }
    
    public function facilityExists($old_name, $new_name, $newType, $comment, $district){
        $name = strtolower( $old_name );
          $where = array(
			'operator'=>'FIELD_LIMIT',
			'field'=>'name',
			'style'=>'lowerequals',
			'data'=>array(
			  'value' => $name
			  )
			);
          $oldfacilitnameids = I2CE_FormStorage::search('facility', false, $where);
          if( count($oldfacilitnameids) >= 1 ){ 
              $oldFacilityObj = $this->ff->createContainer('facility|'.current($oldfacilitnameids)); 
              $oldFacilityObj->populate();//populate an old object so that we can update it accordingly
              
              if(!empty($newType)){//set new facility type
                  $oldFacilityObj->getField('facility_type')->setValue(array( 'facility_type', $this->updateFacilityType( $newType ) ) );
                }
			  if(!empty($comment) && $comment == 'district'){
					$oldFacilityObj->getField('location')->setValue(explode('|',$district));
				  }
              if(!empty($new_name)){ //if we have the new name set
                  $wherenew = array(
					'operator'=>'FIELD_LIMIT',
					'field'=>'name',
					'style'=>'lowerequals',
					'data'=>array(
					  'value' => $new_name
					  )
                    );
                  $newfacilitynameids = I2CE_FormStorage::search('facility', false, $wherenew); //check if new name exists in the system
                  if( count($newfacilitynameids) == 0){ //if the name is not found, just update/change the name to reflect the current one.
                      $this->addBadRecord("facility doesn't exist, updating name");
                      $oldFacilityObj->getField('name')->setValue($new_name);
                      if(!empty($district)){
						$oldFacilityObj->getField('location')->setValue(explode('|',$district));
					  }
                    }
                  elseif( count($newfacilitynameids) >= 1){//if found, we need to do a remapping
                      $this->addBadRecord("facility exists, remapping");
                      $oldFacilityObj->getField('i2ce_hidden')->setValue(1);//hide the old form instance from lists
                      //set a value for remap to the id of the new name that exists in the database
                      $oldFacilityObj->getField('remap')->setValue(array('facility',current($newfacilitynameids) ));
                      
                      $wherepos = array(
                        'operator'=>'FIELD_LIMIT',
                        'field'=>'deploy_facility',
                        'style'=>'lowerequals',
                        'data'=>array(
                                'value' => 'facility|'.current($oldfacilitnameids)
                            )
                        );
                      $positions = I2CE_FormStorage::search('position', false, $wherepos); //search for positions that use the old name
                      $this->addBadRecord("Found ".count($positions)." positions linked to that facility $old_name id=".current($oldfacilitnameids));
                      foreach($positions as $position => $id){
                          $posObj = $this->ff->createContainer('position|'.$id);
                          $posObj->populate();
                          $posObj->getField('deploy_facility')->setValue( array('facility',current($newfacilitynameids)) );
                          $this->save($posObj);
                        }
                    }
                }
                $this->save($oldFacilityObj);
            }
          elseif( count($oldfacilitnameids) == 0){
			  $formObj = $this->ff->createContainer('facility');
			  if(empty($new_name)){
				  $this->addBadRecord("facility doesn't exist, creating");
				  $formObj->getField('name')->setValue($old_name);
				  if(!empty($facType)){
					  $formObj->getField('facility_type')->setValue(array( 'facility_type', $this->updateFacilityType( $newType ) ) );
					}
				  if(!empty($district)){
						$formObj->getField('location')->setValue(explode('|',$district));
					  }
					  $this->save($formObj);
				}
				elseif(!empty($new_name)){
					$wherenew = array(
					'operator'=>'FIELD_LIMIT',
					'field'=>'name',
					'style'=>'lowerequals',
					'data'=>array(
					  'value' => $new_name
					  )
                    );
                  $newfacilitynameids = I2CE_FormStorage::search('facility', false, $wherenew); //check if new name exists in the system
                  if( count($newfacilitynameids) == 0){ //if the name is not found, just update/change the name to reflect the current one.
                      $this->addBadRecord("new facility doesn't exist, updating name");
                      $formObj->getField('name')->setValue($new_name);
                      if(!empty($district)){
						$formObj->getField('location')->setValue(explode('|',$district));
					  }
                      if(!empty($newType)){
						$formObj->getField('facility_type')->setValue(array( 'facility_type', $this->updateFacilityType( $newType ) ) );
					  }
					  $this->save($formObj);
                    }
					}
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


$processor = new UpdateFacility2($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
