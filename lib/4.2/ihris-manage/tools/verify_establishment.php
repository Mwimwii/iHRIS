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

class InfiniumVerifier extends Processor {



    protected function getExpectedHeaders() {
        return  array(
            'omang'=>'Omang/Excemption'
            );
    }
    protected function _processRow() {
        $this->findLastPositionByOmang(true) ;
        echo "Row: " . $this->row . "\n"  . print_r($this->verify_stats,true) . "\n";
        return true;
    }

    protected function alreadyProcessed() {
        return false;
    }

    
    protected $verify_stats = array(
        'bad_omang' => 0,
        'person_not_found'=>0,
        'no_current_position'=>0,
        'current_position'=>0
        );
    protected static  $omang_id_type = 'id_type|1';
    protected static $excemption_id_type = 'id_type|2';
    function findPersonByOmang() {
        $omang = $this->mapped_data['omang'];
        $omang = strtoupper(trim($omang));
        if (strlen($omang) == 0) {
            return false;
        }
        if (!ctype_digit($omang)) {
            $this->verify_stats['bad_omang']++;
            return false;
        }
        $id_type = self::$omang_id_type;
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
        $persons = I2CE_FormStorage::listFields('person_id',array('parent'),true,$where);
        $person_id = false;
        if (count($persons) != 1) {
            $this->verify_stats['person_not_found']++;
            return false;
        }
        $data = current($persons);
        if (array_key_exists('parent',$data) && is_string($data['parent']) && substr($data['parent'],0,7) == 'person|') {
            $person_id = substr($data['parent'],7);
        }            
        if (!$person_id) {
            $this->verify_stats['person_not_found']++;
            return false;
        }
        $personObj = $this->ff->createForm('person|' . $person_id);
        if (!$personObj instanceof iHRIS_Person) {
            $this->verify_stats['person_not_found']++;
            return false;
        }
        $personObj->populate();
        return $personObj;
    }


    


    function findLastPosition($personObj,$only_current) {
        if ($only_current) {
            $where = array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'end_date',
                'style'=>'null'
                );
        } else {
            $where = array();
        }
        $per_pos_id = I2CE_FormStorage::search('person_position', $personObj->getNameId(),$where,'-start_date',1);
        if (!$per_pos_id) {
            $this->verify_stats['no_current_position']++;
            return false;
        }
        $persPosObj = I2CE_FormFactory::instance()->createContainer('person_position'.'|'.$per_pos_id);
        if (!$persPosObj instanceof iHRIS_PersonPosition) {
            $this->verify_stats['no_current_position']++;
            return false;
        }
        $persPosObj->populate();
        $this->verify_stats['current_position']++;
        return $persPosObj;
    }

    function findLastPositionByOmang($only_current) {
        if (! ($personObj = $this->findPersonByOmang()) instanceof iHRIS_Person) {
            return false;
        }
        return  $this->findLastPosition($personObj,$only_current);
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


$processor = new InfiniumVerifier($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
