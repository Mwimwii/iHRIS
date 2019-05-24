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

class FormCacheProcessor extends Processor {


    protected static $id_map = array();
    protected $exp_headers = null;
    protected $map_config;
    protected function getExpectedHeaders() {
        //tricky! in parent class, this is called by mapHeaders() but after $this->expected_headers is set.
        if (!is_array($this->exp_headers)) {
            $this->exp_headers = array();
            foreach ($this->headers as $header) {
                $this->exp_headers[$header] = $header;
            }
        }
        return $this->exp_headers;
    }

    public function __construct($file) {
        parent::__construct($file);
        $this->map_config = I2CE::getConfig()->traverse("/I2CE/formsData/formCacheImport",true);
    }

    protected function _processRow() {
        if (!is_array($this->mapped_data) || !array_key_exists('id',$this->mapped_data) || !is_string($this->mapped_data['id']) || strlen($this->mapped_data['id'])==0) {
            $this->addBadRecord("Nothing in id column");
            return false;
        }
        list($form,$id) = array_pad(explode("|",$this->mapped_data['id'],2),2,'');
        if ($this->alreadyMapped($form,$id)) {
            $this->addBadRecord("Cannot remap the same id.  sorry");
            return false;
        }
        if (!$form) {
            $this->addBadRecord("No form specified");
            return false;
        }
        if (!$id) {
            $this->addBadRecord("No id specified");
            return false;
        }
        if (! ($formObj = $this->ff->createContainer($form)) instanceof I2CE_Form) {
            I2CE::raiseError("Cannot instantiate form $form", E_USER_ERROR);
            die();
        }
        I2CE::raiseError("Saving " . get_class($formObj) . " with id " . $formObj->getFormID() . " from:\n" . print_r($this->mapped_data,true));        

        foreach ($this->mapped_data as $header=>$val) {
            if (!is_string($val) || strlen($val) == 0 || $val === 'NULL') {
                continue;
            }
            switch ($header) {
            case 'last_modified':
            case 'id':
                break;
            case 'parent':
                $val = array_pad(explode("|",$val,2),2,'');
                $formObj->setParent(implode("|", $this->remapIds($val)));
                break;
            default:
                if ( ! ($fieldObj = $formObj->getField($header)) instanceof I2CE_FormField) {
                    I2CE::raiseError("Cannot instantiate field $header of form $form",E_USER_ERROR);
                    die();
                }
                $fieldObj->setFromDB($val);
                if ($fieldObj instanceof I2CE_FormField_MAP) {
                    $fieldObj->setValue($this->remapIds($old = $fieldObj->getValue()));
                }  else if ($fieldObj instanceof I2CE_FormField_MAP) {
                    $vals = array();
                    foreach ($fieldObj->getValue() as $val) {
                        $vals[] = $this->remapIds($val);
                    }
                    $fieldObj->setValue($vals);
                }
                break;
            }
        }
        I2CE::raiseError("Saving " . get_class($formObj) . " with id " . $formObj->getFormID() );        
        $new_id = $this->save($formObj);
        I2CE::raiseError("Saved " . get_class($formObj) . " with id " . $formObj->getFormID() );        

        $this->updateIDMap($form,$id,$new_id);
        return true;
    }


    protected function alreadyMapped($form,$id) {
        return  (I2CE_MagicDataNode::checkKey($form) && I2CE_MagicDataNode::checkKey($id)  && $this->map_config->__isset("$form/$id"));
    }

    protected function updateIDMap($form,$old_id,$new_id) {
        if (!I2CE_MagicDataNode::checkKey($form) || !I2CE_MagicDataNode::checkKey($old_id)) {
            return;
        }
        if (!$this->map_config instanceof I2CE_MagicDataNode) {
            die("Cannot setup map config");
            return;
        }
        $config = $this->map_config->traverse($form,true);
        if (!$this->map_config instanceof I2CE_MagicDataNode) {
            die("Cannot setup map config for form $form");
        }
        $config->$old_id = $new_id;
    }

    protected function remapIds($data) {
        if (!is_array($data) || count($data)!= 2) {
            return $data;
        }
        $new_id = false;
        list($form,$id) = $data;
        if (!I2CE_MagicDataNode::checkKey($form) || !I2CE_MagicDataNode::checkKey($id)) {
            return $data;
        }
        if (!  $this->map_config->setIfIsSet($new_id,"$form/$id")) {
            return $data;
        }
        return array($form,$new_id);
    }


}




/*********************************************
*
*      Execute!
*
*********************************************/


if (count($arg_files) == 0) {
    usage("Please specify the name of at least one  hippo table spreadsheet export to process");
}



foreach($arg_files as $file) {
    if($file[0] == '/') {
        $file = realpath($file);
    } else {
        $file = realpath($dir. '/' . $file);
    }
    if (!is_readable($file)) {
        usage("Please specify the name of a spreadsheet to import: " . $file . " is not readable");
    }

    I2CE::raiseMessage("Loading from $file");


    $processor = new FormCacheProcessor($file);
    $processor->run();

    echo "Processing Statistics:\n";
    print_r( $processor->getStats());
}



# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
