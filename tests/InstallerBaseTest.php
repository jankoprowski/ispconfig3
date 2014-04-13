<?php

require_once '../install/lib/configuration.lib.php';
require_once '../install/lib/install.lib.php';
require_once '../install/lib/installer_base.lib.php';

class InstallerBaseTest extends PHPUnit_Framework_TestCase {
    
    private $inst;
    
    protected function setUp() {
        $options = array();
        $options['config'] = 'resources/language.ini';
        $default = configuration::read($options);
        $this->inst = new installer_base($default);
    }
    
    public function testSimpleQuery_valueExistInConfigurationFile_valueEnFromConfigurationFileIsReturned() {
        
        $language = $this->inst->simple_query('language', 'Select installation language', array('en', 'de'), 'de');
        
        $this->assertEquals('en', $language);
    }
    
    /**
     * @expectedException OutOfRangeException
     * @expectedExceptionMessage Value 'en' of 'language' is not one of: pl,de
     */
    public function testSimpleQuery_valueExistInConfigurationFileIsNotOnTheList_exceptionExpected() {
        
        $this->inst->simple_query('language', 'Select installation language', array('pl', 'de'), 'de');
    }
    
    public function testFreeQuery_valueOfHostnameExistInConfigurationFile_ispconfigOrgIsExpected() {
        
        $hostname = $this->inst->free_query('hostname', 'Enter your hostname', 'example.org');
        
        $this->assertEquals('ispconfig.org', $hostname);
    }
}
