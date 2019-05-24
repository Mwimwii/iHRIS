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

class Venue_Update extends Processor {
    protected $course_ids;
    public function __construct($file) {        
        $this->course_ids = $this->loadTrainingCourses();
        print_r($this->course_ids);
        parent::__construct($file);
    }
    
    protected function loadTrainingCourses(){
        $course_ids = array();
        foreach( I2CE_FormStorage::listFields('training_course', array('id','name')) as $id => $data){
            $course_ids[$data['id']] = $data['name'];
          }
        return $course_ids;
      }
    protected function getExpectedHeaders() {
        return  array(
            'course'=>'CourseCodeID',
            'start'=>'DateFrom',
            'end'=>'DateTo',
            'venue'=>'Venue'
            );
    }

    protected function _processRow() {
        $this->process_stats_checked = array();
        
        if ( ($sCourseID = $this->findScheduledCourse($this->mapped_data['course'],$this->mapped_data['start'],$this->mapped_data['end'],$this->mapped_data['venue'])) === false) {
            //$this->addBadRecord('Could not find/create course');
            $this->processStats('bad_course');
            return false;
        }
        
        return true;
    }

    public function convertDate($date) {
        list($d, $m, $y) = preg_split("/[\/]/",$date);
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
        //$month = $Months[strtolower($month)];
        //return $this->getDate($yr.'/'.$month.'/'.$day, 'Y/m/d');
        
        return $y . '-' . $m . '-'. $d . ' 00:00:00';
    }
    
    function findScheduledCourse($course,$start,$end, $venue) {
        $venue = strtolower(trim($venue));
        $course = 'KITSO'.trim($course);
        echo "course code id is $course\n";
        $start = $this->convertDate($start);
        if (!$start) {
            $this->addBadRecord("Invalid start date, can't create this");
            return false;
        }
        $end = $this->convertDate($end);
        if (!$end) {
            $this->addBadRecord("Invalid end date");
            $this->processStats('bad_end_date');
            return false;
        }
        
        $where = array(
            'operator'=>'AND',
            'operand'=>array(
                0=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'start_date',
                    'style'=>'equals',
                    'data'=>array(
                        'value'=>$start
                        )
                    ),
                1=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'end_date',
                    'style'=>'equals',
                    'data'=>array(
                        'value'=>$end
                        )
                    ),
                2=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'training_course',
                    'style'=>'equals',
                    'data'=>array(
                        'value'=>'training_course|'.$course
                        )
                    )
                )
            );
        
        if (!array_key_exists($course,$this->course_ids)) { 
            $this->addBadRecord("not in the list of courses, need to add");
            $trCObj = $this->ff->createContainer( 'training_course');
            $trCObj->name = 'PLACEHOLDER For - '.$course;
            $trCObj->setID($course);
            $trCObjId = $this->save($trCObj);
            $trCObj->cleanup();
            $this->course_ids[] = $trCObjId;
            //return $course;
        }
        
        $sCourse_ids = I2CE_FormStorage::search('scheduled_training_course',false,$where);
        
        if (count($sCourse_ids) > 1) {
            $this->addBadRecord("duplicate_scheduled_course");
            return false;
        }
        elseif (count($sCourse_ids) == 1) {
            $this->addBadRecord("course found, update venue");
            $strCObj = $this->ff->createContainer( 'scheduled_training_course|'.current($sCourse_ids));
            $strCObj->populate();
            $strCObj->venue = ucwords(strtolower(trim($this->mapped_data['venue'])));
            $this->save($strCObj);
            $strCObj->cleanup();
            return current($sCourse_ids);
        } 
        elseif (count($sCourse_ids) == 0) {
            if ( !($sCourseObj = $this->ff->createContainer( 'scheduled_training_course')) instanceof iHRIS_Scheduled_Training_Course) {
            $this->addBadRecord("failed initialization");
              $this->processStats('cannot_create_stc');
              return false;
          }else{
            $this->addBadRecord("course not scheduled, creating new one.");
            $sCourseObj->getField('start_date')->setFromDB($start);
            $sCourseObj->getField('end_date')->setFromDB($end);
            $sCourseObj->getField('venue')->setValue(ucwords(strtolower(trim($this->mapped_data['venue']))));
            $sCourseObj->getField('training_course')->setValue(array('training_course',$course));
            $sCourseID = $this->save($sCourseObj);
            $sCourseObj->cleanup();
            return $sCourseID;
        } 
      }
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


$processor = new Venue_Update($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End: