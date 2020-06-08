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

class ImportPersons extends Processor {

    protected function getExpectedHeaders() {
        return  array(
            'surname'=>'surname',
            'firstname'=>'firstname',
            'othername'=>'othername',
            'residence'=>'residence',
            'nationality'=>'nationality'
            );
    }
    
    public function __construct($file) {
        parent::__construct($file);
    }
    
    protected static $default_country_code = 'country|ZM';

    protected function _processRow() {
        if (!$this->mapped_data['firstname'] || !$this->mapped_data['surname']) {
            $this->addBadRecord("Incomplete information");
            return false;
        }
        
        $ff = I2CE_FormFactory::instance();
        //$user = new I2CE_User();

        $person = $ff->createContainer('person');
        
        //$person = $form_factory->createForm( "person" );
        $person->firstname = $this->mapped_data['firstname'];
        $person->othername = $this->mapped_data['othername'];
        $person->surname = $this->mapped_data['surname'];
        $person->getField("nationality")->setFromDB('country|ZM');
        $person->getField("residence")->setFromDB('country|ZM');
        //$person->getField("nationality") = "country|ZM";
        
        //$person->residence = $this->mapped_data['residence'];
       
        $person->surname_ignore = true;
        
        $this->save($person);   
        $person->cleanup();
        unset( $person );

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


$processor = new ImportPersons($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
