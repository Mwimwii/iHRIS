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

class DeleteFacilitiesNotInMFL extends Processor {


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
            );
    }
    
    protected function _processRow() {
      $this->cadreExists($this->mapped_data['old_name'],$this->mapped_data['new_name']);
      return true;
    }
    
    public function cadreExists($old_name, $new_name){
      $name = strtolower( $old_name );
      $where = array(
        'operator'=>'FIELD_LIMIT',
        'field'=>'name',
        'style'=>'lowerequals',
        'data'=>array(
			  'value' => $name
			  )
			);
      $cadreids = I2CE_FormStorage::search('cadre', false, $where);
      if( count($cadreids) == 0 ){
        $this->addBadRecord("creating this new cadre");
          $cadreObj = $this->ff->createContainer('cadre');
          $cadreObj->name = trim($old_name);
          $this->save($cadreObj);
        }
      elseif( count($cadreids) != 0 && !empty( $new_name ) ){
        $wherenew = array(
          'operator'=>'FIELD_LIMIT',
          'field'=>'name',
          'style'=>'lowerequals',
          'data'=>array(
            'value' => strtolower(trim($new_name))
            )
          );
          $cadreids = I2CE_FormStorage::search('cadre', false, $wherenew);
          $this->addBadRecord("updating this new cadre");
          $cadreObj = $this->ff->createContainer('cadre'.current($cadreids));
          $cadreObj->name = trim($new_name);
          $this->save($cadreObj);
        }
        else{
          
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


$processor = new DeleteFacilitiesNotInMFL($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
