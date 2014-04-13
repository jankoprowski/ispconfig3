<?php

include_once '../install/lib/configuration.lib.php';

class ConfigurationTest extends PHPUnit_Framework_TestCase {
        
    public function testRead_configParameterWasNotPassedInCommandLine_configurationIsEmpty() {
        
        $options = array();
        $config = configuration::read($options);
        
        $this->assertEquals(configuration::emptyConfiguration(), $config);
    }
    
    /**
     * @expectedException Exception
     * @expectedExceptionMessage File 'resources/nonExistingFile.ini' does not exist.
     */
    public function testRead_configurationFileDoesNotExist_fileDoesNotExistExceptionExpected() {
        
        $options = array();
        $options['config'] = 'resources/nonExistingFile.ini';
        configuration::read($options);
    }
    
    public function testOffsetGet_readExistingLanguagePropertyFromTheFile_langugeEnExpected() {
        
        $options = array();
        $options['config'] = 'resources/language.ini';
        $config = configuration::read($options);
        
        $this->assertEquals('en', $config['language']);
    }
    
    /**
     * @expectedException Exception
     * @expectedExceptionMessage Property 'unexistingProperty' does not exist.
     */
    public function testOffsetGet_readUnexistingPropertyFromTheFile_missingSettingException() {
        
        $options = array();
        $options['config'] = 'resources/language.ini';
        $config = configuration::read($options);
        
        $config['unexistingProperty'];
    }
    
    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage Configuration object is read only.
     */
    public function testOffsetSet_tryToSetNewKey_badMethodCallException() {
        
        $options = array();
        $options['config'] = 'resources/language.ini';
        $config = configuration::read($options);
        
        $config['key'] = 'value';
    }
    
    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage Configuration object is read only.
     */
    public function testOffsetUnset_tryToUnsetSomeKey_badMethodCallException() {
        
        $options = array();
        $options['config'] = 'resources/language.ini';
        $config = configuration::read($options);
        
        unset($config['key']);
    }
}
