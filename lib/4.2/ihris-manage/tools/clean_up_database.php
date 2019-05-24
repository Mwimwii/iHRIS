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
 * @author Dimpho Pholele
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

class Cleanup_Database extends Processor {
    protected $child_forms = array();
    public function __construct($file){
        parent::__construct($file);
        $this->child_forms = I2CE::getConfig()->getAsArray("/modules/forms/forms/person/meta/child_forms");
        
      }
    
    protected function getExpectedHeaders() {
        return  array(
            'personid' => 'Person Id',
            'action' =>	'General Comments'
            );
    }
    
    public $counter = 0;
    protected function _processRow() {
        if(strtolower(trim($this->mapped_data['action'])) == 'delete' ||
            strtolower(trim($this->mapped_data['action'])) == 'resigned' ||
            strtolower(trim($this->mapped_data['action'])) == 'retired' ||
            strtolower(trim($this->mapped_data['action'])) == 'transfered' ||
            strtolower(trim($this->mapped_data['action'])) == 'duplicate' ||  empty($this->mapped_data['action'])
            ){
            $this->deletePersonChildForms($this->mapped_data['personid']);
            $this->counter += 1;
          }
        else{
            $this->addBadRecord("Kept");
          }
        return true;
    }
  
    public function deletePersonChildForms( $personid ){
      echo "Now processing for person with id $personid\n";
      $where = array(
        'operator'=>'FIELD_LIMIT',
        'field'=>'parent',
        'style'=>'equals',
        'data'=>array(
            'value'=>$personid
            )
        );
      $personObj = $this->ff->createContainer($personid);
      $personObj->populate();
      $personObj->populateChildren($this->child_forms);
      foreach ($personObj->getChildren() as $child_form_name=>$child_form_data) {
        foreach ($child_form_data as $child_form_id=>$child_form) {
            if (!$child_form instanceof I2CE_Form) {
                continue;
            }
            echo "\tDeleting: " . $child_form->getFormID() . "\n";
            if ($child_form_name == 'person_position' && ($posObj= $this->ff->createContainer($child_form->getField('position')->getValue())) instanceof iHRIS_Position) {
                echo "\t\tDeleting linked position with ID=" . $posObj->getFormID() . "\n";
                $posObj->delete(false,true);
            }
            $child_form->delete(false,true);
          }
      }
      $personObj->delete(false,true);
      $this->addBadRecord("Deleted");
    }
}
  

/*********************************************
*
*      Execute!
*
*********************************************/

//ini_set('memory_limit','4G');

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


$processor = new Cleanup_Database($file);
$processor->run();

//echo "To delete records ".$processor->counter."\n";

echo "Processing Statistics:\n";
print_r( $processor->getStats());

# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
