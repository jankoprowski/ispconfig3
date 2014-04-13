<?php

class configuration implements arrayaccess {

    private $filename;
    private $values;

    private function __construct($filename, $values) {
        $this->filename = $filename;
        $this->values = $values;
    }

    public static function read($options) {

        if(array_key_exists('config', $options)) {

           if (!file_exists($options['config'])) {
               throw new Exception("File '".$options['config']."' does not exist.");
           }

           $filename = $options['config'];
           $values = parse_ini_file($options['config']);

           return new configuration($filename, $values);

        }
        
        return self::emptyConfiguration();
    }
    
    public static function emptyConfiguration() {
        return new configuration("None", array());
    }

    public function offsetSet($offset, $value) {
        throw new BadMethodCallException("Configuration object is read only.");
    }
    public function offsetExists($index) {
        return isset($this->values[$index]);
    }
    public function offsetUnset($offset) {
        throw new BadMethodCallException("Configuration object is read only.");
    }
    
    public function offsetGet($index) {
        if (isset($this->values[$index])) {
           return $this->values[$index];
        }
        throw new Exception("Property '".$index."' does not exist.");
    }
}
