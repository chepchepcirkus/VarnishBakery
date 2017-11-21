<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     3.0.0
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Varnishbakery\Shell;

use Cake\Console\Shell;
use Cake\Core\Exception\Exception;
use VarnishBakery\Model\Socket as Socket;
use VarnishBakery\Model\Config;

class VclCookShell extends Shell
{
    protected $_secret = null;
    protected $_socket = null;
    protected $_config = null;

    /**
     * Start the shell and interactive console.
     * @param array $args args
     * @return bool
     */
    public function main(...$args)
    {
        $this->out();
        $this->info('Welcome to varnish bakery cooking Shell');
        $this->out();
        if (count($args) > 0) {
            $command = $args[0];
            array_shift($args);
            $this->varnishCommand($command, $args);
        } else {
            $this->varnishCommand();
        }

        return true;
    }

    /**
     * @return bool
     */
    public function applyVcl()
    {
        try {
            // Retrieve configuration data
            $vclConfig = $this->_getConfig()->getVclConfig();

            // Retrieve configuration for vcl template
            if (!isset($vclConfig['vcl_template'])) {
                $this->err('"vcl_template" configuration doesn\'t exist.');

                return false;
            }

            // Check if vcl template exists
            if (!is_file($vclConfig['vcl_template'])) {
                $this->err('"vcl_template" configuration is not a regular file path');

                return false;
            }

            $content = file_get_contents($vclConfig['vcl_template']);

            // Match and replace all variables in template by values from configuration
            if (preg_match_all("/{{(.*?)}}/", $content, $dynamicVars)) {
                foreach ($dynamicVars[1] as $i => $varname) {
                    if (isset($vclConfig[$varname])) {
                        $content = str_replace($dynamicVars[0][$i], sprintf('%s', $vclConfig[$varname]), $content);
                    } else {
                        throw new Exception("$varname is not present in the configuration");
                    }
                }
            }

            // Create unique name for this vcl
            $vclId = hash('sha256', sprintf('%s%s', time(), $this->_secret));
            // Clean comment from vcl
            $content = $this->_cleanVclStr($content);
            // Load the inline vcl
            $this->varnishCommand('vcl.inline', [$vclId, $content]);
            // Apply the new vcl
            $this->varnishCommand('vcl.use', [$vclId]);

            return true;
        } catch (Exception $e) {
            $this->err($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @param string $command command
     * @param array $options options
     * @return bool
     */
    public function varnishCommand($command = 'help', $options = [])
    {
        try {
            if (is_null($this->_secret)) {
                $config = $this->_getConfig()->getVarnishConfig();
                if (!isset($config['secret'])) {
                    throw new Exception('Varnish secret not found in configuration');
                } else {
                    $this->_secret = $config['secret'];
                }
            }

            // Prepare string command
            $data = $this->_prepareCommand($command, $options);

            // Create the socket
            if (is_null($this->_socket)) {
                $this->_socket = new Socket($this->_getConfig()->getVarnishConfig());
                $res = $this->_socket->readSocket();

                // Need to authenticate
                if ($res['code'] === 107) {
                    $varnishToken = substr($res['text'], 0, 32);
                    $authArgHash = hash('sha256', sprintf("%s\n%s%s\n", $varnishToken, $this->_secret, $varnishToken));
                    $authData = $this->_prepareCommand('auth', [$authArgHash]);
                    $authRes = $this->_socket->execute($authData);
                    if ($authRes['code'] !== 200) {
                        throw new Exception('Authentication failed : ' . $authRes['text']);
                    }
                }
            }

            //Execute command throughout the socket
            $res = $this->_socket->execute($data);

            if ($res['code'] !== 200) {
                throw new Exception(
                    sprintf("Varnish error code : %d\r\n%s", $res['code'], $res['text'])
                );
            } else {
                $this->info($res['text']);
            }
        } catch (Exception $e) {
            $this->err($e->getMessage());

            return false;
        }
    }

    /**
     *
     * @return null
     */
    public function help()
    {
        $this->varnishCommand();
        $this->_displayHelp('help');
    }

    /**
     * @return null|Config
     */
    protected function _getConfig() {
        if (is_null($this->_config)) {
            $this->_config = new Config();
        }
        return $this->_config;
    }

    /**
     * @param string $code code
     * @return string
     */
    protected function _cleanAsCStyle($code)
    {
        // Escape quote as C style
        $cp = addcslashes($code, "\"\\");
        // Replace carriage return by corresponding caracter in C
        $cp = str_replace(PHP_EOL, '\n', $cp);
        // Wrap string with double quote
        $r = sprintf('"%s"', $cp);

        return $r;
    }

    /**
     * @param string $command command
     * @param array $options options
     * @return string
     */
    protected function _prepareCommand($command, $options = [])
    {
        $cleanedParams = [];
        foreach ($options as $opt) {
            $cleanedParams[] = $this->_cleanAsCStyle($opt);
        }

        return implode(' ', array_merge([sprintf('"%s"', $command)], $cleanedParams));
    }

    /**
     * Display help for this console.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser()
    {
        $parser = parent::getOptionParser();

        $parser->setDescription(
            '<warning>You can use these command to execute following routines</warning>'
        );
        $parser->addSubcommand('help', [
            'help' => 'Display command help',
        ])->addSubcommand('apply_vcl', [
            'help' => 'Apply a vcl built with configuration data',
        ])->addSubcommand('ban', [
            'help' => 'ban url by regex',
        ]);

        return $parser;
    }

    /**
     * @param string $data string to clean
     * @return string
     */
    protected function _cleanVclStr($data)
    {
        $data = array_filter(array_map('trim', explode(PHP_EOL, trim($data))));
        $data = implode(PHP_EOL, array_filter($data, [$this, '_vclCommentCleaner']));
        return $data;
    }

    /**
     * @param string $line line
     * @return bool
     */
    protected function _vclCommentCleaner($line)
    {

        return (substr($line, 0, 1) != '#'
            && substr($line, 0, 2) != '//'
            && substr($line, 0, 2) != '/*');
    }
}
