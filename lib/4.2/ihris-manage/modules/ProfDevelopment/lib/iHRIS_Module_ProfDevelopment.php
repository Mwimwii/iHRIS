<?php
    class iHRIS_Module_ProfDevelopment extends I2CE_Module {
        public static function getMethods() {
            return array(
                'iHRIS_PageView->action_person_profdev' => 'action_person_profdev',
                'iHRIS_PageView->action_person_continuous_profdev' => 'action_person_continuous_profdev'
                );
        }
    
    
        public function action_person_profdev($obj) {
            if (!$obj instanceof iHRIS_PageView) {
                return;
            }
            return $obj->addChildForms('person_profdev', 'siteContent');
        }
        public function action_person_continuous_profdev($obj) {
            if (!$obj instanceof iHRIS_PageView) {
                return;
            }
            return $obj->addChildForms('person_continuous_profdev', 'siteContent');
        }
    }
?>