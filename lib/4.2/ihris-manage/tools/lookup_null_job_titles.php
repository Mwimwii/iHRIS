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

$dir = getcwd();
chdir("../pages");
$i2ce_site_user_access_init = null;
$wd = getcwd();
require_once( $wd . DIRECTORY_SEPARATOR . 'config.values.php');

$local_config = $wd . DIRECTORY_SEPARATOR .'local' . DIRECTORY_SEPARATOR . 'config.values.php';
if (file_exists($local_config)) {
    require_once($local_config);
}

if(!isset($i2ce_site_i2ce_path) || !is_dir($i2ce_site_i2ce_path)) {
    echo "Please set the \$i2ce_site_i2ce_path in $local_config";
    exit(55);
}

require_once ($i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'I2CE_config.inc.php');
@I2CE::initializeDSN($i2ce_site_dsn,   $i2ce_site_user_access_init,    $i2ce_site_module_config);

require_once $i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'CLI.php';

$user = new I2CE_User();
$ff = I2CE_FormFactory::instance();
//load all titles from database
function allJobTitles(){
    $titles = array();
    $where = array(
            'operator'=>'FIELD_LIMIT',
            'field'=>'title',
            'style'=>'null'
          );
    $jobTitles = I2CE_FormStorage::search('job',false, $where);
    return $jobTitles;
  }
  
$allTitles = allJobTitles();
echo "Found ".count($allTitles)." without titles\n";
foreach($allTitles as $id=>$value){
  $whereposition = array(
        'operator'=>'FIELD_LIMIT',
        'field'=>'job',
        'style'=>'equals',
        'data'=>array(
          'value'=>"job|$value"
        )
      );
  $positions = I2CE_FormStorage::search('position',false,$whereposition);
  if(count($positions) == 0){
      echo "Deleting job with id $value\n";
      $jobObj = $ff->createContainer("job|$value");
      $jobObj->populate();
      $jobObj->delete();
      $jobObj->cleanup();
    }
  else{
      echo "job with id $value has ".count($positions)." linked to it\n";
    }
}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
