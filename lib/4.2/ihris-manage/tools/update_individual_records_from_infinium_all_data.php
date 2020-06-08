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
 * @author Carl Leitner <litlfred@ibiblio.org>, Sovello Hildebrand Mgani <sovellohpmgani@gmail.com>
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

class NewIndividualInfoUpdater extends Processor {


    protected $create_new_people = null;
    protected $person_ids = array();
    public function __construct($file) {
        I2CE::raiseMessage("Loading person_id lookup");
        $this->person_ids = $this->loadPersonIds();
        //I2CE::raiseMessage("Loaded ".count($this->person_ids)." person_ids");
        //print_r($this->person_ids[496114509]);
        //print_r($this->person_ids);
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
        'surname' => 'Surname',
        'firstname' => 'Forename',	
        'othername' => 'Middle name',
        'title' => 'Title',
        'dob' => 'Date Of Birth',
        'gender' => 'Gender',
        'marital_status' => 'Marital Status',
        'work_telephone' => 'Office tel',	
        'email' => 'Email',
        'ad1' => 'Address line 1',
        'ad2' => 'Address line 2',
        'ad3' => 'Address line 3',
        'town' => 'Town/City',
        'telephone' => 'Home tel',
        'mobile' => 'Cell phone',
        'nxtofkin_name' => 'Emergency contact name',
        'nxtofkin_address' => 'Emergency contact address',
        'nxtofkin_town' => 'Emergency contact Town/city',
        'nxtofkin_home_telephone' => 'Emergency contact home phone',
        'nxtofkin_phone2' => 'Emergency contact second phone',
        'nxtofkin_relationship' => 'Emergency contact relationship',
        'nxtofkin_relationship_description' => 'Emergency contact relationship description',
        'citizenship' => 'Citizenship',
        'citizenship_desc' => 'Citizenship description',
        'birth_place' => 'Place of birth',
        'birth_country' => 'Country of birth',
        'passport_num' => 'Passport number',
        'passport_expiry_date' => 'Passport expiry date',
      );
    }
    
    protected function _processRow() {
        //$this->process_stats_checked = array();
        if (! ($personObj = $this->findPersonByOmangAndEmployeeNumber(true)) instanceof iHRIS_Person) {
            $this->addBadRecord("person couldn't be found/updated/created in the system");
        }
        return true;
    }
    
    protected static  $omang_id_type = 'id_type|1';
    protected static $excemption_id_type = 'id_type|2';
    protected static $employeenum_id_type = 'id_type|4';
    protected $duplicate_ids = array();
    
    function findPersonByOmangAndEmployeeNumber($create = false) {
        $personObj= false;
        
        $omang = strtoupper(trim($this->mapped_data['id_num']));
        $employeenum = trim($this->mapped_data['employee_number']);
        
        $surname = trim($this->mapped_data['surname']);
        $firstname = trim($this->mapped_data['firstname']);
        $othername = trim($this->mapped_data['othername']);

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
                $personObj->populate();
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
                      return false;
                  }
                  if (count($personIds) == 1) {
                      $person_id = 'person|' . current($personIds);
                      $personObj = $this->ff->createForm($person_id);
                      $personObj->populate();
                  }
              //}
            
            if ($personObj instanceof iHRIS_Person) {
                $whereDemographic = array(
                    'operator' => 'FIELD_LIMIT',
                    'field'=>'parent',
                    'style'=>'equals',
                    'data'=>array(
                        'value' => $person_id
                    )
                );
                
                $wherePCWork = array(
                    'operator' => 'FIELD_LIMIT',
                    'field'=>'parent',
                    'style'=>'equals',
                    'data'=>array(
                        'value' => $person_id
                    )
                );
                
                $whereNextofkin = array(
                    'operator' => 'FIELD_LIMIT',
                    'field'=>'parent',
                    'style'=>'equals',
                    'data'=>array(
                        'value' => $person_id
                    )
                );
                
                $wherePCHome = array(
                    'operator' => 'FIELD_LIMIT',
                    'field'=>'parent',
                    'style'=>'equals',
                    'data'=>array(
                        'value' => $person_id
                    )
                );
                $whereidentifications = array(
                    'operator' => 'FIELD_LIMIT',
                    'field'=>'parent',
                    'style'=>'equals',
                    'data'=>array(
                        'value' => $person_id
                    )
                );
                
                
                $demoIds = I2CE_FormStorage::search('demographic',false,$whereDemographic,'-last_modified');
                $PCHome = I2CE_FormStorage::search('person_contact_personal',false,$wherePCHome,'-last_modified');
                $nextofkin = I2CE_FormStorage::search('person_nextofkin',false,$whereNextofkin,'-last_modified');
                $PCWork = I2CE_FormStorage::search('person_contact_work',false,$wherePCWork,'-last_modified');
                $identifications = I2CE_FormStorage::search('person_id',false,$whereidentifications,'-last_modified');
                
                if(count($identifications) >= 1){
                  foreach($identifications as $key=>$id_num){
                  $idObj = $this->ff->createContainer('person_id|'.$id_num);
                  $idObj->populate();
                  //$idObj->delete();
                }
                }else{
                  }
                  
                if(count($demoIds) == 1 || count($demoIds) > 1){
                  $demoId = current($demoIds);
                  $demoObj = $this->ff->createContainer('demographic|'.$demoId);
                  $demoObj->populate();
                }else{
                  $demoObj = $this->ff->createContainer('demographic');
                  }
                  //create home contact object
                if(count($PCHome) == 1 || count($PCHome) > 1){
                  $PCHomeid = current($PCHome);
                  $PCHomeObj = $this->ff->createContainer('person_contact_personal|'.$PCHomeid);
                  $PCHomeObj->populate();
                }else{
                  $PCHomeObj = $this->ff->createContainer('person_contact_personal');
                  }
                  
                  //create work contact object
                if(count($PCWork) == 1 || count($PCWork) > 1){
                  $PCWorkid = current($PCWork);
                  $PCWorkObj = $this->ff->createContainer('person_contact_work|'.$PCWorkid);
                  $PCWorkObj->populate();
                }else{
                  $PCWorkObj = $this->ff->createContainer('person_contact_work');
                  }
                  
                  //create nextofkin object
                if(count($nextofkin) == 1 || count($nextofkin) > 1){
                  $nextofkinid = current($nextofkin);
                  $nextofkinObj = $this->ff->createContainer('person_nextofkin|'.$nextofkinid);
                  $nextofkinObj->populate();
                }else{
                  $nextofkinObj = $this->ff->createContainer('person_nextofkin');
                  }
            }
            
            else{
              echo "creating new record\n";
              $personObj = $this->ff->createContainer('person');
              $demoObj = $this->ff->createContainer('demographic');
              $nextofkinObj = $this->ff->createContainer('person_nextofkin');
              $PCWorkObj = $this->ff->createContainer('person_contact_work');
              $PCHomeObj = $this->ff->createContainer('person_contact_personal');
              
              }
              
            echo "the person id is $person_id\n";
            $personObj->surname = $surname;
            $personObj->firstname = $firstname;
            if(!empty($othername)){
              $personObj->othername = $othername;
            }
            $personObj->nationality = array('country',trim($this->mapped_data['citizenship']));
            $person_id = $this->save($personObj);
            //$personObj->cleanup(); 
            
            //update gender information
            if(trim($this->mapped_data['gender']) == 'Male'){
              $gender = 'M';
            }
            elseif(trim($this->mapped_data['gender']) == 'Female'){
              $gender = 'F';
            }
            $dob = trim($this->mapped_data['dob']);
            $demoObj->getField('gender')->setValue(array('gender',$gender));
            $demoObj->getField('birth_date')->setValue($this->convertDate($dob));
            $demoObj->getField('marital_status')->setValue(explode('|',$this->mStatus($this->mapped_data['marital_status'])));
            $demoObj->setParent('person|'  . $person_id);
            $this->save($demoObj);
            $demoObj->cleanup();
            
            $PCWorkObj->getField('telephone')->setValue(trim($this->mapped_data['work_telephone']));
            $PCWorkObj->setParent('person|'  . $person_id);
            $this->save($PCWorkObj);
            $PCWorkObj->cleanup();
            
            $nextofkinObj->getField('nxtofkin_name')->setValue(trim($this->mapped_data['nxtofkin_name']));
            $nextofkinObj->getField('nxtofkin_telephone')->setValue(trim($this->mapped_data['nxtofkin_home_telephone']).'/'.trim($this->mapped_data['nxtofkin_phone2']));
            $nextofkinObj->getField('nxtofkin_relship')->setValue(trim($this->mapped_data['nxtofkin_relationship_description']));
            $nextofkinObj->getField('nxtofkin_address')->setValue(trim($this->mapped_data['nxtofkin_address']).' '.trim($this->mapped_data['nxtofkin_town']));
            $nextofkinObj->setParent('person|'  . $person_id);
            $this->save($nextofkinObj);
            $nextofkinObj->cleanup();
            
            $PCHomeObj->getField('telephone')->setValue(trim($this->mapped_data['telephone']));
            $PCHomeObj->getField('mobile_phone')->setValue(trim($this->mapped_data['mobile']));
            $PCHomeObj->getField('address')->setValue(trim($this->mapped_data['ad1']).' '.trim($this->mapped_data['town'].' ['.trim($this->mapped_data['ad2']).' '.trim($this->mapped_data['ad3']).']'));
            $PCHomeObj->getField('email')->setValue(trim($this->mapped_data['email']));
            $PCHomeObj->setParent('person|'  . $person_id);
            $this->save($PCHomeObj);
            $PCHomeObj->cleanup();
            
            
            $pass_num = trim($this->mapped_data['passport_num']);
            
            $ids = array();
            
            if(!empty($this->mapped_data['passport_num'])){
              $ids['P'] = ($this->mapped_data['passport_num']);
              }
            if(!empty($employeenum)){
              $ids['4'] = $employeenum;
              }
            if(!empty($omang) && ctype_digit($omang)){
              $ids['1'] = $omang;
              }
            if(!empty($omang) && !ctype_digit($omang)){
              $ids['2'] = $omang;
              }
              
            foreach($ids as $type=>$number){
              $idObj = $this->ff->createContainer('person_id');
               $idObj->getField('id_type')->setValue(array('id_type',$type));
               if(!empty($this->mapped_data['passport_expiry_date'])){
                  $idObj->getField('expiration_date')->setValue($this->convertDate((trim($this->mapped_data['passport_expiry_date']))));
                 }
                $idObj->getField('id_num')->setValue($number);
                $idObj->setParent('person|'. $person_id);
                $this->save($idObj);
                $idObj->cleanup(); 
              }
              return $personObj;   
    }
    

 
    
    //protected $day;
    //protected $month;
    public function convertDate($date) {
        list($d, $m, $y) = preg_split("/[\-]/",$date);
        $Months = array(
          'jan' => '01',
          'feb' => '02',
          'mar' => '03',
          'apr' => '04',
          'may' => '05',
          'jun' => '06',
          'jul' => '07',
          'aug' => '08',
          'sep' => '09',
          'oct' => '10',
          'nov' => '11',
          'dec' => '12'
        );
          
        $day = $d;
        $month = $m;
        if(strlen(trim($day)) == 1){
          $day = '0'.$day;
        }else{
          $day = $day;
          }
        if(strlen($y) == 2){
          if($y < 20){
            $yr = '20'.$y;
          }
          else{
            $yr = '19'.$y;
          }
        }
        $month = $Months[strtolower($month)];
        return $this->getDate($yr.'/'.$month.'/'.$day, 'Y/m/d');
    }
    
    public function convertYear($year){
        return $this->getYear(trim($year), 'Y');
      }
    
    public function mStatus($mStatus){
        $status = strtolower(trim($mStatus));
        if($status == 'm')
          return 'marital_status|1'; //married
        if($status == 's')
          return 'marital_status|2'; //single
        if($status == 'w')
          return 'marital_status|4'; //widowed
        if($status == 'd')
          return 'marital_status|3'; //divorced
        if($status == 'a')
          return 'marital_status|A'; //
        if($status == 'p')
          return 'marital_status|P'; //
      }
    protected $existing_codes;
    protected function loadCountryLookup() {
        $this->existing_codes= I2CE_FormStorage::listFields('country',array('alpha_two'));
        foreach ($this->existing_codes as $id=>&$data) {
            $data = $data['alpha_two'];
        }
        unset($data);
    }
    
    
    function loadPersonIds(){
        $personId = array();
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
    
    function getTransactionDate() {
        return $this->getDate($this->mapped_data['date'],'Y/m/d');
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


$processor = new NewIndividualInfoUpdater($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End: