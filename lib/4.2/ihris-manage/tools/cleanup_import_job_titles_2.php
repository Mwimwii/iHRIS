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
 * @author Dimpho Pholele (The Consultant) <pholele.dimpho@gmail.com>
 * @copyright Copyright &copy; 2007, 2008-2014 IntraHealth International, Inc. 
 * @since Demo-v2.a
 * @version Demo-v2.a
 */


require_once("./import_base.php");



/*********************************************
*
*      Process Class
*
*********************************************/

class Cleanup_Import_Job_3 extends Processor {
    public function __construct($file){
        parent::__construct($file);
      }
    
    protected function getExpectedHeaders() {
        return  array(
            'original_title' => 'original title (from ihris)',
            'new_title' =>	'Reviewed Titles Dec 4, 2014',
            'notes' =>	'comment'
            );
    }

    protected function _processRow() {
        if(empty($this->mapped_data['original_title']) && empty($this->mapped_data['new_title'])){
            I2CE::raiseError("Skipping empty row");
            return true;
          }
        if(!empty($this->mapped_data['original_title'])){
              $this->checkPosition($this->mapped_data['original_title'],$this->mapped_data['new_title']);
            }
        I2CE::raiseError("Non empty row");
        $this->jobTitleLookup($this->mapped_data['original_title'],
                              $this->mapped_data['new_title'],
                              $this->mapped_data['notes']
                              //,$this->mapped_data['grouping']
                              );
        return true;
    }


    protected function processStats($stat) {
        //echo "Stat:$stat\n";
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

    protected $duplicate_ids = array();

    function jobTitleLookup( $old_title, $new_title, $notes){
        $where_old = array(
          'operator'=>'FIELD_LIMIT',
          'field'=>'title',
          'style'=>'lowerequals',
          'data'=>array(
              'value'=>strtolower(trim($old_title))
              )
          );
          
        $where_new = array(
          'operator'=>'FIELD_LIMIT',
          'field'=>'title',
          'style'=>'lowerequals',
          'data'=>array(
              'value'=>strtolower(trim($new_title))
              )
          );
          if(!empty($old_title)){
            $oldjob_titles = I2CE_FormStorage::listFields('job', array('id', 'title'), false, $where_old); //search for the old title
            if(count($oldjob_titles) >= 1){
                foreach($oldjob_titles as $id => $data){
                  I2CE::raiseError("Updating old title ".$data['title']);
                  if( strtolower(trim($data['title'])) == strtolower(trim($new_title)) ){
                  I2CE::raiseError("old title and new title match, do nothing");
                  }
                else{
                    $formObj = $this->ff->createContainer('job|'.$data['id']);
                    $formObj->populate();
                    if(!empty($new_title)){  
                      $formObj->title = trim($new_title);
                    }
                    if(!empty($notes)){
                        $formObj->notes = trim($notes);
                      }
                    $this->save($formObj);
                  }
                }
              }
            elseif( count($oldjob_titles) == 0){
                I2CE::raiseError("old title Not found");
                $new_job_titles = I2CE_FormStorage::listFields('job', array('id'), false, $where_new);
                if(count($new_job_titles) == 0){
                  if(!empty($new_title)){
                    $jobObj = $this->ff->createContainer('job');
                    if(!empty($notes)){
                          $jobObj->notes = trim($notes);
                        }
                    $jobObj->title = trim($new_title);
                    $this->save($jobObj);
                  }
                }
              }
          }
        else{
            I2CE::raiseError("New title");
            $new_job_titles = I2CE_FormStorage::listFields('job', array('id'), false, $where_new);
            if(count($new_job_titles) == 0){
              $jobObj = $this->ff->createContainer('job');
              if(!empty($notes)){
                $jobObj->notes = trim($notes);
              }
              $jobObj->title = trim($new_title);
              if(!empty($new_title)){
                $this->save($jobObj);
              }
            }
          }
      }
    
    function checkPosition( $positionTitle, $newTitle ){
      if(!empty($newTitle)){    
        $where = array(
          'operator'=>'FIELD_LIMIT',
          'field'=>'title',
          'style'=>'lowerequals',
          'data'=>array(
              'value'=>strtolower(trim($positionTitle))
              )
          );
        $titles = I2CE_FormStorage::listFields('position', array('id', 'title'), false, $where);
        if( count($titles) >=1 ){
            $this->addBadRecord("Found positions with old title");
            foreach( $titles as $id=>$data){
                if( strtolower(trim($data['title'])) == strtolower(trim($newTitle)) ){
                  I2CE::raiseError("old position title and new title match, do nothing");
                  }
                else{
                  $posObj = $this->ff->createContainer('position|'.$data['id']);
                  $posObj->populate();
                  $posObj->title = trim($newTitle);
                  $this->save($posObj);
                }
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


$processor = new Cleanup_Import_Job_3($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
