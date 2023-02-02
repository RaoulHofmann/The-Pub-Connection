<?php
namespace App\Services;

use Phalcon\Di\Injectable;

class WebSocket extends Injectable
{
    private $lockFile = '/tmp/websocket.lock';

    private $socket;
    private $clients = [];

    private $address;
    private $port;

    public function __construct($address, $port) {
        $this->address = $address;
        $this->port = $port;
    }

    public function start() {
        if ($this->isProcessRunning()) {
            echo "WebSocket Running";
            return;
        }

        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new Exception("Could not fork process");
        } elseif (!$pid) {
            $this->createLockFile();
            $this->runWebSocket();
            exit();
        }       
    }
    

    private function runWebSocket() {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_bind($this->socket, $this->address, $this->port);
        socket_listen($this->socket);

        while (true) {
            $client = socket_accept($this->socket);
            
            var_dump($client);

            if ($client) {
                $this->handleConnection($client);
            }
        }
    }

    private function handleConnection($client)
    {
        $id = uniqid();
        $this->clients[$id] = $client;

        $headers = [];
        $request = [];
        $line = socket_read($client, 2048);

        while ($line !== "\r\n") {
            $request[] = $line;
            $line = socket_read($client, 2048);
        }

        foreach ($request as $line) {
            if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                $headers[$matches[1]] = $matches[2];
            }
        }

        if (!isset($headers['Sec-WebSocket-Key'])) {
            return;
        }

        $key = $headers['Sec-WebSocket-Key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
        $accept = base64_encode(sha1($key, true));

        $response = "HTTP/1.1 101 Switching Protocols\r\n";
        $response .= "Upgrade: websocket\r\n";
        $response .= "Connection: Upgrade\r\n";
        $response .= "Sec-WebSocket-Accept: $accept\r\n\r\n";
        socket_write($client, $response);

        while (true) {
            $line = socket_read($client, 2048, PHP_NORMAL_READ);
            $frame = $this->decodeFrame($line);

            if (!$frame) {
                break;
            }

            $data = json_decode($frame['payload'], true);
            $recipient = $data['recipient'];
            $message = $data['message'];

            if ($recipient === 'all') {
                foreach ($this->clients as $id => $client) {
                    socket_write($client, $this->encodeFrame($message));
                }
                continue;
            }

            if (isset($this->clients[$recipient])) {
                socket_write($this->clients[$recipient], $this->encodeFrame($message));
            }
        }

        unset($this->clients[$id]);
        socket_close($client);
    }

    function decodeFrame($data) {
        $decodedData = [];
        $len = ord($data[1]) & 127;
        $mask = substr($data, 2, 4);
        $payload = substr($data, 6);
    
        for ($i = 0; $i < $len; $i++) {
            $decodedData[] = ord($payload[$i]) ^ ord($mask[$i % 4]);
        }
    
        return $decodedData;
    }

    function encodeFrame($data, $type = 'text', $masked = true) {
        $b1 = 0x80 | (ord($type) & 0x0f);
        $length = strlen($data);
    
        if($length <= 125)
            $header = pack('CC', $b1, $length | ($masked ? 0x80 : 0));
        elseif($length > 125 && $length < 65536)
            $header = pack('CCn', $b1, 126 | ($masked ? 0x80 : 0), $length);
        elseif($length >= 65536)
            $header = pack('CCNN', $b1, 127 | ($masked ? 0x80 : 0), $length >> 32, $length & 0xFFFFFFFF);
    
        if($masked) {
            $key = random_bytes(4);
            $mask = str_repeat("\x00", 4);
            for ($i = 0; $i < $length; $i++) {
                $mask[$i % 4] = $data[$i] ^ $key[$i % 4];
            }
            return $header . $key . $mask;
        }
        else {
            return $header . $data;
        }
    }

    private function isProcessRunning() {
        // Check if lock file exists
        if (file_exists($this->lockFile)) {
            // Get PID from lock file and check process
            $pid = file_get_contents($this->lockFile);
            $this->checkSocketState();
            // If process is running then fine, if not assume something went wrong and delete the lock file so we can setup the process again
            if (posix_getpgid($pid)) {
                return true;
            } else {
                posix_kill($pid, 9);
                echo "Removing lock file";
                $this->removeLockFile();
            }
        }
        return false;
    }

    private function createLockFile() {
        file_put_contents($this->lockFile, getmypid());
    }

    private function removeLockFile() {
        unlink($this->lockFile);
    }

    private function checkSocketState() {
        try {
            $con = fsockopen($this->address, $this->port);
            if (!is_resource($con)) {
                fclose($con);
                return false;
            }
            print_r($con);
            fclose($con);

            return true;
        } catch (\Exception $e)  {
            return false;
        }
    }
}