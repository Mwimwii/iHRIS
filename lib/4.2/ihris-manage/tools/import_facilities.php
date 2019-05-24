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
require_once ($i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'I2CE_config.inc.php');
require_once($i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'CLI.php');


/*********************************************
*
*      Process Class
*
*********************************************/
$user = new I2CE_User();

class ImportDistricts extends Processor {

    protected function getExpectedHeaders() {
        return  array(
            'facility'=>'facility_name',
            'district'=>'district',
            'region'=>'province',
            'facility_type'=>'facility_type'
            );
    }
    
    public function __construct($file) {
        parent::__construct($file);
    }

    protected function _processRow() {
        // if (trim($this->mapped_data['facility_type']) == "") {
        //     $this->addBadRecord("Incomplete information");
        //     return false;
        // }
        
     

        $regionObj = $this->listLookup('region', trim($this->mapped_data['region']));

        if ( !(($districtObj = $this->listLookup('district', trim($this->mapped_data['district']))) instanceof iHRIS_District)){
            $districtObj = $this->createDistrict($regionObj, $this->mapped_data['district']);
        }
        
        
        if ( !(($facilityObj = $this->findFacility(trim($this->mapped_data['facility']), $districtObj->getFormID())) instanceof iHRIS_Facility)){
            $facilityObj = $this->createFacility($districtObj, $this->mapped_data['facility'], trim($this->mapped_data['facility_type']));
        }

        return true;
    }

    function createFacility( $districtObj, $facility_name, $facility_type ){
        $formObj = $this->ff->createContainer( 'facility' );
        $formObj->name = trim( $facility_name );

        if($facility_type){
            print "Facility Type:  $facility_type";
            
            $facilityTypeObj = $this->listLookup( 'facility_type' , $facility_type );
            $formObj->getField( 'facility_type' )->setFromDB( $facilityTypeObj->getFormID() );
        }
    
        $formObj->getField( 'location' )->setFromDB( $districtObj->getFormID() );
        $this->save( $formObj );
        return $formObj;
    
      }

      function createDistrict($regionObj, $district_name){
        $formObj = $this->$ff->createContainer( 'district' );
        $formObj->name = trim( $district_name );
        $formObj->getField( 'region' )->setFromDB( $regionObj->getFromID() );
        $this->save($formObj);
        return $formObj; 
      }

    function listLookup( $listform, $listValue, $otherFields=array() ){
        if( $listform == 'job' || $listform == 'position' ){
            $namefield = 'title';
          }
        else{
            $namefield = 'name';
          }
        
        $where = array(
          'operator'=>'FIELD_LIMIT',
          'field'=>$namefield,
          'style'=>'lowerequals',
          'data'=>array(
              'value'=>strtolower( trim( $listValue ) )
              )
        );
        $form_list = I2CE_FormStorage::listFields( $listform, array( 'id' ), false, $where );
        if( count( $form_list) >= 1 ){
            $data = current( $form_list );
            $formObj = $this->ff->createContainer( $listform.'|'.$data['id'] );
            $formObj->populate();
          }
        else{
            //list doesn't exist, so we need to create
            $formObj = $this->ff->createContainer( $listform );
            $formObj->$namefield = trim( $listValue );
            //$this->save( $formObj );
          }
        
        return $formObj;
      }

      function findFacility($facility_name, $district_id) {
        $facility_name = strtolower(trim($facility_name));
        $district_id = strtolower(trim($district_id));
        $where = array(
            'operator'=>'AND',
            'operand'=>array(
                0=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'name',
                    'style'=>'lowerequals',
                    'data'=>array(
                        'value'=>$facility_name
                        )
                    ),
                1=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'location',
                    'style'=>'lowerequals',
                    'data'=>array(
                        'value'=>$district_id
                        )
                    )
                )
            );
        $facility = I2CE_FormStorage::search('facility',true,$where);
        if (count($facility) >= 1) {
            $facilityObj = $this->ff->createContainer('facility|'.current($facility));
            $facilityObj->populate();
            return $facilityObj;
        } elseif (count($facility) == 0) {
            return false;
        }
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


$processor = new ImportDistricts($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
