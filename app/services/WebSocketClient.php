<?php
namespace App\Services;

use Phalcon\Di\Injectable;

class WebSocketClient extends Injectable
{
    private $host;
    private $port;
    private $socket;

    public function __construct($host, $port)
    {
        $this->host = $host;
        $this->port = $port;
    }

    public function connect()
    {
        $shm_id = shm_attach(SHM_KEY, 10000, 0666);
        shm_detach($shm_id);
    }

    public function sendData($data)
    {
        socket_write($this->socket, $data, strlen($data));
    }

    public function receiveData()
    {
        return socket_read($this->socket, 2048);
    }

    public function close()
    {
        socket_close($this->socket);
    }
}