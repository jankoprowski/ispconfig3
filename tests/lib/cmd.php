<?php

class result {
    
    public $stdout;
    public $stderr;
    public $exitcode;
    
    public function __construct($stdout, $stderr, $exitcode) {
        $this->stdout = $stdout;
        $this->stderr = $stderr;
        $this->exticode = $exitcode;
    }
}

class cmd  {
    
    private $cmd;
    
    public function __construct($cmd = '') {
        $this->cmd = $cmd;
    }
    
    public function __invoke($cmd, $stdin='') {
        
        if ($this->cmd != "") {
            $cmd = $this->cmd . ' ' . $cmd;
        }
        
        $descriptorspec = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );
        
        $process = proc_open($cmd, $descriptorspec, $pipes);
        
        if (is_resource($process)) {
            
            fwrite($pipes[0], $stdin);
            fclose($pipes[0]);
            
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            
            $exitcode = proc_close($process);
            
            return new result($stdout, $stderr, $exitcode);
        }
    }
}

$cmd = new cmd();
$php = new cmd($_ENV['PHP_PEAR_PHP_BIN'] ?: 'php');