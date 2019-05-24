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

class FileNumberProcessor extends Processor {

    protected function getExpectedHeaders() {
        return  array(
            'surname'=>'SURNAME',
            'name'=>'NAME',
            'mpf'=>'MPF',
            );
    }

    public function __construct($file) {
        parent::__construct($file);
        $this->ensureMPFID();
    }

    protected static $mpf_id = '9999';
    protected static $mpf_name = 'Manpower File Number';

    protected function ensureMPFID() {
        $idtypes = I2CE_FormStorage::search('id_type');        
        if (in_array(self::$mpf_id,$idtypes)) {
            return;
        }
        if (!simple_prompt("The ID Type for " . self::$mpf_name . " was not found.  Should it be added?")) {
            usage("Need to have the ID Type for " . self::$mpf_name);
        }
        if (! ($idTypeObj = $this->ff->createContainer( 'id_type'))instanceof I2CE_SimpleList) {
            I2CE::raiseError("No id_type could be created",E_USER_ERROR);
            die();
        }
        $idTypeObj->getField('name')->setValue(self::$mpf_name);
        $idTypeObj->setID(self::$mpf_id);
        $this->save($idTypeObj);
    }

    protected function _processRow() {
        if (!$this->mapped_data['name'] || !$this->mapped_data['surname'] || !$this->mapped_data['mpf']) {
            $this->addBadRecord("Incomplete information");
            return false;
        }
        $where = 
            array(
                'operator'=>'OR',
                'operand'=>array(
                    0=> array(
                        'operator'=>'AND',
                        'operand'=>array(
                            0=>array(
                                'operator'=>'FIELD_LIMIT',
                                'field'=>'firstname',
                                'style'=>'lowerequals',
                                'data'=>array(
                                    'value'=>strtolower(trim($this->mapped_data['name']))
                                    )
                                ), 
                            1=>array(
                                'operator'=>'FIELD_LIMIT',
                                'field'=>'surname',
                                'style'=>'lowerequals',
                                'data'=>array(
                                    'value'=>strtolower(trim($this->mapped_data['surname']))
                                    )
                                )
                            )
                        ),
                    1=> array(  //sometimes the names are backwards!
                        'operator'=>'AND',
                        'operand'=>array(
                            0=>array(
                                'operator'=>'FIELD_LIMIT',
                                'field'=>'surname',
                                'style'=>'lowerequals',
                                'data'=>array(
                                    'value'=>strtolower(trim($this->mapped_data['name']))
                                    )
                                ), 
                            1=>array(
                                'operator'=>'FIELD_LIMIT',
                                'field'=>'firstname',
                                'style'=>'lowerequals',
                                'data'=>array(
                                    'value'=>strtolower(trim($this->mapped_data['surname']))
                                    )
                                )
                            )
                        )
                    )
                );
        $personIds = I2CE_FormStorage::search('person', false,$where);
        if (count($personIds) == 0) {
            $this->addBadRecord("Person not found in the system");
            return false;
        } else if (count($personIds) > 1) {
            $this->addBadRecord("More than one person found in the system with this name");
            return false;
        }
        //now we check to see if this person already has an id
        reset($personIds);
        $personId = current($personIds);
        $where = array(
            'operator'=>'FIELD_LIMIT',
            'field'=>'id_type',
            'style'=>'equals',
            'data'=>array(
                'value'=>'id_type|' . self::$mpf_id
                )
            );
        $mpfIds = I2CE_FormStorage::listFields('person_id', array('id_num'), 'person|' . $personId,$where);
        if (count($mpfIds) > 1) {
            $this->addBadRecord("Person already has more than one ". self::$mpf_name);
            return false;
        } else if (count($mpfIds) == 1) {
            reset($mpfIds);
            $mpf_num = current($mpfIds);
            if ($mpf_num == $this->mapped_data['mpf']) {
                return true;
            } else {
                $this->addBadRecord("Person already has a " . self::$mpf_name . " of " . $mpf_num);
                return false;
            }
        }
        //if we are here, all is goof and we simply add it.
        if (! ($idObj = $this->ff->createContainer('person_id')) instanceof iHRIS_PersonID) {
            I2CE::raiseError("Could not instantiate person id form", E_USER_ERROR);
            die();
        }
        $idObj->getField('id_num')->setValue(trim($this->mapped_data['mpf']));
        $idObj->getField('id_type')->setValue(array('id_type',self::$mpf_id));
        $idObj->setParent('person|' . $personId);
        $this->save($idObj);
        return true;
    }


}




/*********************************************
*
*      Execute!
*
*********************************************/


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


$processor = new FileNumberProcessor($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
