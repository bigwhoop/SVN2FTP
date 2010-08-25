<?php
namespace BigWhoop\SVN2FTP;

class Config
{
    static public function merge(array $defaults, array $config)
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $value = static::merge(isset($defaults[$key]) ? $defaults[$key] : array(), $value);
            }
            
            $defaults[$key] = $value;
        }
        
        return $defaults;
    }
}