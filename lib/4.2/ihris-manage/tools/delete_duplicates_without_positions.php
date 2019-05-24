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

class duplicateRecordsEraser extends Processor {


    protected $child_forms = array();
    public function __construct($file) {
        I2CE::raiseMessage("Loading person child forms");
        $this->child_forms = I2CE::getConfig()->getAsArray("/modules/forms/forms/person/meta/child_forms");
        parent::__construct($file);

    }


    protected function mapData() {
        $mapped_data = parent::mapData();        
        return $mapped_data;
    }

    protected function getExpectedHeaders() {
        return  array(
        'firstname' => 'firstname',
        'surname' => 'surname',
      );
    }
    
    protected function _processRow() {
        $duplicatePersonIds = $this->findPersonByNames();
        foreach ($duplicatePersonIds as $key => $id) {
              if (!($personObj = $this->ff->createContainer('person|'.$id)) instanceof iHRIS_Person) {
                    continue;
                }
                $personObj->populate();
                $personObj->populateChildren($this->child_forms);
                $children = $personObj->getChildren();
                
                if(count($children) == 0){
                    echo "deleting ".$this->mapped_data['surname']." ".$this->mapped_data['firstname']."\n";
                    $personObj->delete(false,true);
                  }
                else{
                    $this->addBadRecord("record has positions, review by hand");
                  }
                /*
                foreach ($personObj->getChildren() as $child_form_name=>$child_form_data) {
                  foreach ($child_form_data as $child_form_id=>$child_form) {
                        if (!$child_form instanceof I2CE_Form) {
                            continue;
                        }
                        if ($child_form_name == 'person_position' && ($posObj= $this->ff->createContainer($child_form->getField('position')->getValue())) instanceof iHRIS_Position) {
                            echo "\t\tDeleting linked position with ID=" . $posObj->getFormID() . "\n";
                            $posObj->delete(false,true);
                        }
                        
                        echo "\tDeleting: " . $child_form->getFormID() . "\n";
                        
                        $child_form->delete(false,true);
                    }
                }
                $personObj->delete(false,true);
                */
          /*
            $wherePersonPosition = array(
              'operator'=>'AND',
              'operand'=>array(
                  0=>array(
                      'operator'=>'FIELD_LIMIT',
                      'field'=>'parent',
                      'style'=>'equals',
                      'data'=>array(
                          'value'=>'person|'.$id 
                          )
                      ),
                  1=>array(
                      'operator'=>'FIELD_LIMIT',
                      'field'=>'end_date',
                      'style'=>'not_null',
                      )
                  )
              );
            
            $closedPositions = I2CE_FormStorage::search('person_position',false,$wherePersonPosition);
            
            if(count($closedPositions) >= 1){
                echo "person|$id is a duplicate and has some closed positions\n";
                if (!($personObj = $this->ff->createContainer('person|'.$id)) instanceof iHRIS_Person) {
                    continue;
                }
                $personObj->populate();
                $personObj->populateChildren($this->child_forms);
                
                foreach ($personObj->getChildren() as $child_form_name=>$child_form_data) {        
                    foreach ($child_form_data as $child_form_id=>$child_form) {
                        if (!$child_form instanceof I2CE_Form) {
                            continue;
                        }
                        if ($child_form_name == 'person_position' && ($posObj= $this->ff->createContainer($child_form->getField('position')->getValue())) instanceof iHRIS_Position) {
                            echo "\t\tDeleting linked position with ID=" . $posObj->getFormID() . "\n";
                            $posObj->delete(false,true);
                        }
                        
                        echo "\tDeleting: " . $child_form->getFormID() . "\n";
                        
                        $child_form->delete(false,true);
                    }
                }
                $personObj->delete(false,true);
            }else{
                echo "No open positions, skipping for person|$id\n";
              }*/
      }
    }


    public function findPersonByNames() {
      $surname = strtolower(trim($this->mapped_data['surname']));
      $firstname = strtolower(trim($this->mapped_data['firstname']));
          $where = array(
              'operator'=>'AND',
              'operand'=>array(
                  0=>array(
                      'operator'=>'FIELD_LIMIT',
                      'field'=>'surname',
                      'style'=>'lowerequals',
                      'data'=>array(
                          'value'=>$surname 
                          )
                      ),
                  1=>array(
                      'operator'=>'FIELD_LIMIT',
                      'field'=>'firstname',
                      'style'=>'lowerequals',
                      'data'=>array(
                          'value'=> $firstname 
                          )
                      )
                  )
              );
          return $personIds = I2CE_FormStorage::search('person',false,$where);
      }

    public function loadHeadersFromCSV($fp) {
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


$processor = new duplicateRecordsEraser($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());

# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
