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

class FixGender extends Processor {


    protected $create_new_people = null;
    protected $person_ids = array();
    public function __construct($file) {
        I2CE::raiseMessage("Loading person_id lookup");
        $this->person_ids = $this->loadPersonIdsByEmployeeNumber();
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
        'id_num' => 'ID Number',
        'id_num' => 'ID Number',
        'surname' => 'Surname',
        'firstname' => 'Forename',	
        'othername' => 'Middle name',
        'gender' => 'Gender'
            );
    }
    
    protected function _processRow() {
        //$this->process_stats_checked = array();
        $this->findPersonByOmangAndEmployeeNumber();
        return true;
    }
    
    protected static  $omang_id_type = 'id_type|1';
    protected static $employeenum_id_type = 'id_type|4';
    
    function findPersonByOmangAndEmployeeNumber($create = false) {
        $personObj= false;
        
        $omang = strtoupper(trim($this->mapped_data['id_num']));
        $employeenum = trim($this->mapped_data['employee_number']);
        
        $surname = trim($this->mapped_data['surname']);
        $firstname = trim($this->mapped_data['firstname']);

        $id_type = false;
        //we only want the Omang, because the exemption id can exist in duplicates
        if (strlen($omang) > 0 && ctype_digit($omang)) {
            $id_type = self::$omang_id_type;
            $id_num = $omang;
        }else{
            $id_type = self::$employeenum_id_type;
            $id_num = $employeenum;
          }

        //$person_id = false;
  
      if (array_key_exists($id_num, $this->person_ids)) {
          $person_id = $this->person_ids[$id_num];
          $personObj = $this->ff->createForm($person_id);
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
            }
            if (count($personIds) == 1) {
                $person_id =  'person|' . current($personIds);
                $personObj = $this->ff->createForm($person_id);
            }
        }
        if ($personObj instanceof iHRIS_Person){
        $whereDemographic = array(
            'operator' => 'FIELD_LIMIT',
            'field'=>'parent',
            'style'=>'equals',
            'data'=>array(
                'value' => $person_id
            )
        );
        $demoIds = I2CE_FormStorage::search('demographic',false,$whereDemographic,'-last_modified');
        if(count($demoIds) == 1 || count($demoIds) > 1){
          $demoId = current($demoIds);
          $demoObj = $this->ff->createContainer('demographic|'.$demoId);
          $demoObj->populate();
        }else{
          $this->addBadRecord('creating new demographic');
          $demoObj = $this->ff->createContainer('demographic');
          }
        if(trim($this->mapped_data['gender']) == 'Male'){
              $gender = 'M';
              }
          elseif(trim($this->mapped_data['gender']) == 'Female'){
            $gender = 'F';
          }
        $demoObj->getField('gender')->setValue(array('gender',$gender));
        $demoObj->setParent($personObj->getNameID());
        $this->save($demoObj);
        $demoObj->cleanup();
      }
      else{
          $this->addBadRecord('personobj not created');
        }
    }
  
    
    function loadPersonIdsByEmployeeNumber(){
        $personId = array();
        $where = array(
            'operator' => 'FIELD_LIMIT',
            'field' => 'id_type',
            'style' => 'equals',
            'data' => array(
              'value' => 'id_type|4'
            )
          );
        $person_ids = I2CE_FormStorage::listFields('person_id',array('id_type', 'id_num', 'parent'));
        foreach($person_ids as $data){
            $personId[$data['id_num']] = $data['parent'];
          }
        return $personId;
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


$processor = new FixGender($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End: