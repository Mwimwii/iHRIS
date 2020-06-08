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

class InfiniumSnapshotResolver extends Processor {

    public function __construct($file) {
        I2CE::raiseMessage("Pre-Loading DPSM data");
        $this->loadDPSMData();
        parent::__construct($file);

    }


    protected function getExpectedHeaders() {
        //Script is based on the following header row:  
        //    Unit,               Position, Title,               Grade, Authorized, Actual, Employee Name,        Omang/Excemption, SEX
        //and a sample data row:
        //    Corporate Services, KA01AA,   Permanent Secretary, F1,    1,          1,      "SURNAME, FIRSTNAME", 359212208,        M
        return  array(
            'omang'=>'Omang/Excemption',
            'unit_name'=>'Unit',
            'position_code'=>'Position',
            'position_title'=>'Title',
            'salary_grade'=>'Grade',
            'sex'=>'SEX',
            'name'=>'Employee Name'
            );
    }

    protected function alreadyProcessed() {
        return false;
    }
    protected function markProcessed($hash_val) {
        return ;
    }
    protected function _processRow() {
        $this->process_stats_checked = array();
        if (! ($personObj = $this->findPersonByOmang(true)) instanceof iHRIS_Person) {
            $this->addBadRecord('Could not find/create person');
            $this->processStats('bad_person');
            return false;
        }
        $this->updatePosition($personObj);
        $personObj->cleanup();
        echo "Row: " . $this->row . "\n"  . print_r($this->process_stats,true) . "\n";
        echo "Duplicates:" . implode(" ", $this->duplicate_ids) . "\n";
        return true;
    }
    

    function closeAttachedPosition($existingPersonPosObj) {
        echo "Ending person position " . $existingPersonPosObj->getFormID() . "\n";
        if (! ($existingPosObj = $this->ff->createContainer( $existingPersonPosObj->position))instanceof iHRIS_Position) {
            I2CE::raiseMessage("No position could be created for CAP from " . $existingPersonPosObj->getField('position')->getDBValue() . ' from person position' . $existingPersonPosObj->getFormID() );
        } else {
            echo "\tClosing attached position\n";
            $existingPosObj->populate();
            $existingPosObj->status = array('position_status','discontinued');
            $this->save($existingPosObj);
        }
        $existingPersonPosObj->getField('end_date')->setValue(I2CE_Date::now());
        //$existingPersonPosObj->end_date = I2CE_Date:now()
        //$existingPersonPosObj->reason = explode("|",$pos_change_reason);
        $this->save($existingPersonPosObj);
        return true;
    }

    


    protected function updatePosition($personObj) {
        $personPosObjs = $this->findLastPositions($personObj,true);
        $matched = false;
        foreach ($personPosObjs as $personPosObj) {
            if ( !($posObj = $this->ff->createContainer($personPosObj->getField('position')->getValue())) instanceof iHRIS_Position) {
                $this->processStats("invalid_existing_position");
                $this->closeAttachedPosition($personPosObj);
                continue;
            }
            $posObj->populate();
            echo "POST=" . $posObj->getField('post')->getDBValue() . "\n";
            if ( ($postObj = $this->ff->createContainer($posObj->getField('post')->getValue())) instanceof Botswana_Post) {
                $this->processStats('existing_post');
                $postObj->populate();
                if ($postObj->getField('dpsm_code')->getDBValue() ==  $this->mapped_data['position_code']) {
                    //don't need to do anything the current position codes matches
                    $this->processStats('verified_against_existing');
                    $this->markAsVerified($personPosObj);
                    $this->save($personPosObj);
                    $this->processStats('existing_post_match');
                    $matched = true;
                    $postObj->cleanup();
                    $posObj->cleanup();
                    continue;
                }
                echo "post " . $this->mapped_data['position_code'] . ' != ' . $postObj->getField('dpsm_code')->getDBValue()  . "\n";
                $postObj->cleanup();
            }
            $this->processStats('existing_post_mismatch');
            $this->save($posObj);
            //we make it here if the existin pers_pos does not match the dpsm_post.  we should close it.
            $this->closeAttachedPosition($personPosObj);
        }
        if ($matched) {            
            return true;
        }
        //we now need to create a new position 
        $this->processStats('creating_new_position');
        $newPosObj = $this->ff->createContainer('position');
        $newPosObj->getField('title')->setValue($this->mapped_data['position_title']);
        $newPosObj->getField('status')->setValue(array('position_status','closed'));
        $postid =false;
        if (!array_key_exists($this->mapped_data['position_code'],$this->dpsm_code_map)) {
            $postid = $this->addPost($this->mapped_data['position_code'],$this->mapped_data['position_title']);
            //$this->addBadRecord("DPSM Code ({$this->mapped_data['position_code']}) is not in system");
            //return false;
        } else {
            $postid = $this->dpsm_code_map[$this->mapped_data['position_code']];
        }
        if ($postid) {
            $newPosObj->getField('post')->setValue(array('post',$postid));
        }
        $newPosObj = $this->save($newPosObj);

        

        //now we create a new person position object to link to the person and the position objects
        $newPersonPosObj = $this->ff->createContainer('person_position');
        $newPersonPosObj->getField('position')->setValue(array('position',$newPosObj));
        $newPersonPosObj->getField('start_date')->setValue(I2CE_Date::blank());
        $newPersonPosObj->setParent($personObj->getNameID());
        $this->markAsVerified($newPersonPosObj);
        $this->save($newPersonPosObj);
    }

    function markAsVerified($personPosObj) {
        if (! ($fieldObj = $personPosObj->getField('infinium_valid_date')) instanceof I2CE_FormField_DATE_TIME) {
            return;
        }
        $fieldObj->setValue(I2CE_Date::now());
    }


    function addPost($dpsm_code,$dpsm_title) {
        $dpsm_code =  strtoupper($dpsm_code);
        if (!prompt("Could not find dpsm post $dpsm_code.Should I add it?",$this->add_post)) {
            return false;
        }
        $postObj = $this->ff->createContainer('post');
        if (!$postObj instanceof I2CE_List) {
            die("Cannot add post");
            return false;
        }
        $postObj->getField('name')->setValue($dpsm_title);    
        $postObj->getField('dpsm_code')->setValue($dpsm_code);    
        if ( ($new_id = $this->save($postObj)) === false) {
            die("Could not save post");
        }
        $this->dpsm_code_map[$dpsm_code] = $new_id;
        echo "Added $dpsm_code\n";
        return $new_id;
    }




    protected function processStats($stat) {
        if (!array_key_exists($stat,$this->process_stats)) {
            $this->process_stats[$stat] = 0;
        }
        if (in_array($stat,$this->process_stats_checked)) {
            return;
        }
        $this->process_stats[$stat]++;

    }
    
    protected $process_stats = array();
    protected $process_stats_checked = array();

    protected static  $omang_id_type = 'id_type|1';
    protected static $excemption_id_type = 'id_type|2';
    protected $duplicate_ids = array();
    function findPersonByOmang($create = false) {
        $personObj= false;
        
        if (strlen(trim($this->mapped_data['name'])) == 0) {
            $this->processStats('no_name');
            return $personObj;
        }
        $omang = strtoupper(trim($this->mapped_data['omang']));
        list($surname,$firstname) = explode(",",$this->mapped_data['name'],2);
        $surname = trim($surname);
        $firstname = trim($firstname);

        $id_type = false;
        if (strlen($omang) > 0 && ctype_digit($omang)) {
            $id_type = self::$omang_id_type;
        }else if (!in_array($omang, array('EXP', 'EXPATRIATE'))) {
            $id_type = self::$excemption_id_type;
            //exception id
        }

        $person_id = false;
        if ($id_type) {
            $where = array(
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
                        'style'=>'equals',
                        'data'=>array(
                            'value'=>$omang
                            )
                        )
                    )
                );
            $person_ids = I2CE_FormStorage::listFields('person_id',array('parent'),true,$where);
            if (count($person_ids) > 1) {
                $this->processStats('duplicate_id');
                $this->duplicate_ids[] = $omang;
                return false;
            }
            $data = current($person_ids);
            if (is_array($data) && array_key_exists('parent',$data) && is_string($data['parent']) && substr($data['parent'],0,7) == 'person|') {
                $person_id = $data['parent'];
                $this->processStats('found_by_id');
            }
        } 
        if(!$person_id) {
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
            $person_ids = I2CE_FormStorage::search('person',false,$where);
            if (count($person_ids) > 1) {
                $this->processStats('duplicate_person_name');
                return false;
            }
            if (count($person_ids) == 1) {
                $this->processStats('found_by_name');
                $person_id = 'person|' . current($person_ids);
            }
        }

        if ($person_id) {
            $personObj = $this->ff->createForm($person_id);
            if (!$personObj instanceof iHRIS_Person) {
                $this->processStats('person_not_found');
                return false;
            }
            $personObj->populate();
        } else {
            if (!$create) {
                $this->processStats('person_not_found');
                return false;
            }
            $this->processStats('creating_person');
            $personObj = $this->ff->createContainer('person');
            $personObj->surname = $surname;
            $personObj->firstname = $firstname;
            $person_id = $this->save($personObj);


            $demoObj = $this->ff->createContainer('demographic');                
            $demoObj->getField('gender')->setValue(array('gender',$this->mapped_data['sex']));
            $demoObj->setParent('person|'  . $person_id);

            if ($id_type) {
                //now we add in the OMANG                
                $idObj = $this->ff->createContainer('person_id');                
                $idObj->getField('id_type')->setValue(explode('|',$id_type));
                $idObj->getField('id_num')->setValue($omang);
                $idObj->setParent('person|' .$person_id);
            }
            $this->save($idObj);
        }
        return $personObj;
    }


    

    
    function findLastPositions($personObj,$only_current) {
        if ($only_current) {
            $where = array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'end_date',
                'style'=>'null'
                );
        } else {
            $where = array();
        }
        $persPosObjs  = array();
        $per_pos_ids = I2CE_FormStorage::search('person_position', $personObj->getNameId(),$where,'-start_date');
        if (count($per_pos_ids) == 0) {
            $this->processStats('no_current_position');
        }
        foreach ($per_pos_ids as $per_pos_id) {
            $persPosObj = I2CE_FormFactory::instance()->createContainer('person_position'.'|'.$per_pos_id);
            if (!$persPosObj instanceof iHRIS_PersonPosition) {
                $this->processStats('no_current_position');
                continue;
            }
            $persPosObj->populate();
            echo "PersonPosition " . $persPosObj->getFormID() . " references position " . $persPosObj->getField('position')->getDBValue() . "\n";
            $this->processStats('has_current_position');
            $persPosObjs[$per_pos_id] = $persPosObj;
        }
        return $persPosObjs;
    }

    protected $dpsm_code_map;
   
    function loadDPSMData() {
        $this->dpsm_code_map = array();
   
        foreach (I2CE_FormStorage::listFields('post',array('dpsm_code')) as $id=>$data) {
            if (!is_array($data) || !array_key_exists('dpsm_code',$data) || !$data['dpsm_code']) {
                continue;
            }
            $this->dpsm_code_map[strtoupper($data['dpsm_code'])] =  $id;
        }

    }
    




}




/*********************************************
*
*      Execute!
*
*********************************************/

ini_set('memory_limit','4G');

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


$processor = new InfiniumSnapshotResolver($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
