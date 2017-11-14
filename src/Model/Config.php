<?php

namespace VarnishBakery\Model;

use Cake\Core\Configure;

class Config
{
    protected $_config = null;

    public function getVclConfig()
    {
        $data = $this->getConfig();
        if(!isset($data['vcl'])) {
            throw new Exception('Vcl configuration data are missing');
        }
        return $data['vcl'];
    }

    public function getVarnishConfig()
    {
        $data = $this->getConfig('varnish');

        return $data;
    }

    public function getConfig($name = false)
    {
        if(is_null($this->_config)) {
            if(!Configure::check('varnish_bakery')) {
                throw new Exception('Configuration is missing');
            }
            $this->_config = Configure::read('varnish_bakery');
        }

        if($name) {
            if(!isset($this->_config[$name])) {
                throw new Exception("Varnish $name configuration is missing");
            }
            return $this->_config[$name];
        }

        return $this->_config;
    }
}