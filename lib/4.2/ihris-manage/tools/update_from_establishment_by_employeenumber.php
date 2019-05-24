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
 * @author Sovello Hildebrand Mgani <sovellohpmgani@gmail.com>
 * @copyright Copyright &copy; 2007, 2008 IntraHealth International, Inc. 
 * @version 4.1.6
 */


require_once("./import_base.php");



/*********************************************
*
*      Process Class
*
*********************************************/

class UpdateProfessionsFromBHPC extends Processor {
	
    public function __construct($file) {
      I2CE::raiseMessage("Loading person_id lookup");
      $this->person_ids = $this->loadPersonIds();
        
      parent::__construct($file);
    }


    protected function getExpectedHeaders() {
        return  array(
        'position' => 'Title',
        'employee_number' =>'Employee No.',
        'surname' =>'Surname',
        'firstname' =>'Firstname'
      );
    }
    
    protected function _processRow() {
      $this->findPersonByEmployeeNumber($this->mapped_data['employee_number'],$this->mapped_data['surname'],$this->mapped_data['firstname']);
    }
    
    protected static $employeenum_id_type = 'id_type|4';
    
    function findPersonByEmployeeNumber($emp_number, $surname, $firstname) {
        
      $id_num = trim( $emp_number );
      $personObj= false;
      
      if (array_key_exists($id_num, $this->person_ids)) {
          $person_id = $this->person_ids[$id_num];
          //$personObj = $this->ff->createForm($person_id);
         // $personObj->populate();
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
            if (count($personIds) > 1) {
                $this->addBadRecord('duplicate records exists with that name');
                return false;
            }
            if (count($personIds) == 1) {
                $person_id = 'person|' . current($personIds);
                //$personObj = $this->ff->createForm($person_id);
                //$personObj->populate();
            }
            if (count($personIds) == 0) {
                $person_id = 'person|' . current($personIds);
                $this->addBadRecord('record not found');
            }
            
        }
    }
    
    function loadPersonIds(){
      $personId = array();
      $where = array(
            'operator' => 'FIELD_LIMIT',
            'field'=>'id_type',
            'style'=>'equals',
            'data'=>array(
                'value' => 'id_type|4'
            )
        );
      $person_ids = I2CE_FormStorage::listFields('person_id',array('id_type', 'id_num', 'parent'), false, $where);
      foreach($person_ids as $data){
          $personId[$data['id_num']] = $data['parent'];
        }
      return $personId;
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


$processor = new UpdateProfessionsFromBHPC($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
