<?php

namespace VarnishBakery\Model;

use Cake\Core\Exception\Exception;

class Socket
{
    protected $_socket = null;
    protected $_host = '127.0.0.1';
    protected $_port = '6082';
    protected $_timeout = 10;

    /**
     * Socket constructor.
     * @param array $options config
     */
    public function __construct(array $options = [])
    {
        foreach ($options as $key => $value) {
            switch ($key) {
                case 'host':
                    $this->_host = $value;
                    break;
                case 'port':
                    $this->_port = $value;
                    break;
                case 'timeout':
                    $this->_timeout = $value;
                    break;
                default:
                    break;
            }
        }

        if (is_null($this->_socket)) {
            $this->_socket = fsockopen($this->_host, $this->_port, $errorCode, $errorDesc, $this->_timeout);
            if (!is_resource($this->_socket)) {
                throw new Exception(sprintf($this->_host, $this->_port, $errorCode, $errorDesc));
            }

            stream_set_blocking($this->_socket, 1);
            stream_set_timeout($this->_socket, $this->_timeout);
        }
    }

    /**
     * @param string $data data
     * @return array|bool|string
     */
    public function execute($data)
    {
        try {
            return $this->_writeSocket($data)->readSocket();
        } catch (Exception $e) {
            return ['code' => $e->getCode(), 'text' => $e->getMessage()];
        }
    }

    /**
     * @param string $data data
     * @return $this
     */
    protected function _writeSocket($data)
    {
        $data = rtrim($data) . PHP_EOL;
        $res = fwrite($this->_socket, $data);

        if ($res != strlen($data)) {
            throw new Exception('Socket write error');
        }

        return $this;
    }

    /**
     * @return array|bool|string
     */
    public function readSocket()
    {
        $code = null;
        $len = -1;
        while (!feof($this->_socket)) {
            $res = fgets($this->_socket, 1024);
            if (empty($res)) {
                $streamMeta = stream_get_meta_data($this->_socket);
                if (isset($streamMeta['timed_out']) && $streamMeta['timed_out']) {
                    throw new Exception('Socket timeout');
                }
            }
            if (preg_match('/(\d{3}) (\d+)/', $res, $match)) {
                $code = (int)$match[1];
                $len = (int)$match[2];
                break;
            }
        }

        if (is_null($code)) {
            throw new Exception('Failed to read response code from Varnish');
        } else {
            $res = ['code' => $code, 'text' => ''];
            $totalLength = strlen($res['text']);
            while (!feof($this->_socket) && $totalLength < $len) {
                $res['text'] .= fgets($this->_socket, 1024);
            }

            return $res;
        }
    }
}
