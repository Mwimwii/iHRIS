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
        parent::__construct($file);

    }


    protected function getExpectedHeaders() {
        return  array(
        'full_name' => 'Full Name',
        'profession' => 'Profession Sub Type'
      );
    }
    
    protected function _processRow() {
		$this->findPersonByNames($this->mapped_data['full_name'], $this->mapped_data['profession']);
    }
    
    function findPersonByNames($fullname, $profession) {
        
        $personObj= false;
        
        $firstname = trim(substr($fullname, 0, strpos($fullname,' ')));
        $surname = trim(substr($fullname, strrpos($fullname,' ')));
                  
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
		  if( count($personIds) == 0) {
			  $this->addBadRecord('record not found');
			  return false;
		  }
		  
		  if( count($personIds) == 1) {
			  $id = current($personIds);
			  $pProfObj = $this->ff->createContainer('person|'.$id);
			  $pProfObj->getField('profession')->setFromDB('profession|'.$this->getProfessionIDByName($profession) );
			  $this->save($pProfObj);
			  $pProfObj->cleanup();
		  }
    }
    
    function getProfessionIDByName($name) {
        $profession = strtolower(trim($name));
        $where = array(
                  'operator' => 'FIELD_LIMIT',
                  'field'=>'name',
                  'style'=>'lowerequals',
                  'data'=>array(
                      'value' => trim($profession)
                  )
                );
    
        $professions = I2CE_FormStorage::listFields('profession',array('id', 'name'), true, $where);
          if(count($professions) >= 1){
            $data = current($professions);
            return $data['id'];
          }
          elseif(count($professions) == 0){
            $profObj = $this->ff->createContainer('profession');
            $profObj->name = trim($name);
            $profId = $this->save($profObj);
            $profObj->cleanup();
            echo "returning prof id as prof|$profId\n";
            return $profId;
          }else{
              $this->addBadRecord("Badness: this profession can't be handled by a script");
              return false;
            }
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
