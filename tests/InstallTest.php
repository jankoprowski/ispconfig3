<?php

require_once 'lib/cmd.php';

class InstallTest extends PHPUnit_Framework_TestCase {
    
    public function testInstall_configurationFileWithMinimalNecessarySettingsPassed_ispconfigInstalledAutomatically() {
        
        global $php;
        
        $result = $php('../install/install.php --config=../tests/resources/minimal.ini');
        echo $result->stdout;
        
        $this->assertEquals(0, $result->exitcode);
        $this->assertEquals('', $result->stderr);
    }
}
