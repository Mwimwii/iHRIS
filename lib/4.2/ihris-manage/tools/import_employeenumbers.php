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

class EmployeeNumberProcessor extends Processor {

    protected function getExpectedHeaders() {
        return  array(
            'omang' => 'Omang',
            'employee_number' => 'EmployeeNumber',
            'firstname' => 'FirstName',
            'surname' => 'Surname',
            );
    }

    public function __construct($file) {
        parent::__construct($file);
        $this->ensureEmpNumber();
    }

    protected static $empnum_id = '4';
    protected static $empnum_name = 'Employee Number';

    protected function ensureEmpNumber() {
        $idtypes = I2CE_FormStorage::search('id_type');        
        if (in_array(self::$empnum_id,$idtypes)) {
            return;
        }
        if (!simple_prompt("The ID Type for " . self::$empnum_name . " was not found.  Should it be added?")) {
            usage("Need to have the ID Type for " . self::$empnum_name);
        }
        if (! ($idTypeObj = $this->ff->createContainer( 'id_type'))instanceof I2CE_SimpleList) {
            I2CE::raiseError("No id_type could be created",E_USER_ERROR);
            die();
        }
        $idTypeObj->getField('name')->setValue(self::$empnum_name);
        $idTypeObj->setID(self::$empnum_id);
        $this->save($idTypeObj);
    }

    protected function _processRow() {
        if (!$this->mapped_data['omang']) {
            I2CE::raiseMessage("Bad/Invalid Omang Number.");
            $this->addBadRecord("Surname: ".$this->mapped_data['surname'], "Firstname: ".$this->mapped_data['firstname'], 
                              "Emp. #: ".$this->mapped_data['employee_number'], "Omang #: ".$this->mapped_data['omang']);
            return false;
        }
        $where = array(
          'operator'=>'FIELD_LIMIT',
          'field'=>'id_num',
          'style'=>'equals',
          'data'=>array(
              'value'=>$this->mapped_data['omang']
            )
          );
        I2CE::raiseMessage('We are searching using omang/exemption #:'.$this->mapped_data['omang']);
        $person_idIds = I2CE_FormStorage::search('person_id', true, $where);//get the person_id id value
        $person_id = I2CE_FormStorage::listFields('person_id', array('id', 'id_num', 'parent'), true, $where);
        //we get all the values for that omang/exemption number
       
        I2CE::raiseMessage("Person with Omang=".$this->mapped_data['omang']." has parent.");
        
        if ( count($person_idIds) > 1 ){
            I2CE::raiseMessage("We have this Omang number entered more than once. Skipping.");
            foreach( $person_id as $key=>$value ){
              $this->addBadRecord($value['id'], $value['parent'], $this->mapped_data['employee_number'], $this->mapped_data['omang']);
            }
            return false;
        }
        else if (count($person_idIds) == 1) {
            reset($person_idIds);
            $person_idId = current($person_idIds);
            $parent = $person_id[$person_idId]['parent'];
            //we can now go ahead and save this value
            if (! ($idObj = $this->ff->createContainer('person_id')) instanceof iHRIS_PersonID) {
                I2CE::raiseError("Could not instantiate person id form", E_USER_ERROR);
                die();
            }
            
            $idObj->getField('id_num')->setValue(trim($this->mapped_data['employee_number']));
            $idObj->getField('id_type')->setValue(array('id_type',self::$empnum_id));
            $idObj->setParent($parent);
            $this->save($idObj);
            return true;
        }
        return true;
    }
    
    /**
     * initialize a file into which we record all the bad data/unsuccessful row imports
     * 
     */
    protected function initBadFile() {
        $info = pathinfo($this->file);
        $bad_fp =false;
        $this->bad_file_name = dirname($this->file) . DIRECTORY_SEPARATOR . basename($this->file,'.'.$info['extension']) . '.duplicate_omang_' .date('d-m-Y_G:i') .'.csv';
        I2CE::raiseMessage("Will put any bad records in $this->bad_file_name");
        $this->bad_headers[] = "person_id.id";
        $this->bad_headers[] = "parent";
        $this->bad_headers[] = "EmployeeNumber";
        $this->bad_headers[] = "Omang/Expatriate";
    }
    
    
    /**
     * add a bad record to the file holding all unsuccessful imports
     * @param string $reason, the reason for the failure of import of this record
     */
    function addBadRecord($personid, $parent, $omang_indb, $omang_infile) {
        if (!is_resource($this->bad_fp)) {
            $this->bad_fp = fopen($this->bad_file_name,"w");
            if (!is_resource($this->bad_fp)) {
                I2CE::raiseMessage("Could not open $this->bad_file_name for writing.", E_USER_ERROR);
                die();
            }        
            fputcsv($this->bad_fp, $this->bad_headers);
        }
        I2CE::raiseMessage("Skipping processing of row $this->row: with omang $omang_infile");
        $raw_data[] = $personid;
        $raw_data[] = $parent;
        $raw_data[] = $omang_indb;
        $raw_data[] = $omang_infile;
        fputcsv($this->bad_fp, $raw_data);
    }
    
    /**
     * process the import giving the user choices to run in test mode or in production mode
     * every unsuccessful processing for a row is recorded into a log file
     */
    public function run() {
        if (simple_prompt("Skip rows?")) {
            $this->skip = ask("Skip to which row?  Start row =2 (b/c row 1 is header)");
        }
        $this->success = 0;
        while ( $this->hasDataRow()) {
            if ($this->blank_lines > 10) {
                if (simple_prompt("Encounted 10 consective blank rows ending at row " . $this->row . ". Should we stop processing?")) {
                    break;
                } else {
                    $this->blank_lines = 0;
                }
            }
            if ($this->processRow()) {
                $this->success++;
                if ($this->testmode) {
                    I2CE::raiseMessage("SUCCESS ON TEST");
                }
            }
        }
    }


}




/*********************************************
*
*      Execute!
*
*********************************************/

ini_set('memory_limit','2G');


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


$processor = new EmployeeNumberProcessor($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());


# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
