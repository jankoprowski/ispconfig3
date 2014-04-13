<?php

require_once 'lib/cmd.php';

class InstallTest extends PHPUnit_Framework_TestCase {
    
    public function testInstall_configurationFileWithAllNecessarySettingsPassed_configurationWereDoneAutomatically() {
        
        global $php;
        
        $result = $php('../install/install.php --config=../tests/resources/complete.ini');
        echo $result->stdout;
        
        $this->assertEquals(0, $result->exitcode);
        $this->assertEquals('', $result->stderr);
    }
}
