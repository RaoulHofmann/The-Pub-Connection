<?php

namespace App\Helpers;

class PortScanner
{
    private $port;

    public function __construct($port) {
        
    }

    private function checkPort() {
        if (is_resource(@fsockopen("0.0.0.0", $this->port))) {
            return true;
        } else {
            return false;
        }
    } 
}