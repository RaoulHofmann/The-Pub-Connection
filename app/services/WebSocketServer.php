<?php

namespace App\Services;

use Phalcon\Di\Injectable;

class WebSocketServer extends Injectable
{
    private $socket;
    private $clients = [];

    private $address;
    private $port;

    private $output;

    public function __construct()
    {
        $this->address = $this->request->getServer('HTTP_HOST');
        $this->port = $this->getDI()->getConfig()->application->websocketPort;

        $this->output = fopen('php://stderr', 'w');
    }

    public function start()
    {
        $shm_id = shm_attach(SHM_KEY, 10000, 0666);

        $sem_key = ftok(__FILE__, 's');
        $sem_id = sem_get($sem_key, 1, 0666, 0);

        // Create the socket pair only once
        if (!shm_has_var($shm_id, 1)) {
            echo 'ASD';
            if (!socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets)) {
                die('Failed to create socket pair');
            }

            var_dump(stream_get_meta_data(socket_export_stream($sockets[1])));

            $pid = pcntl_fork();

            if ($pid === -1) {
                echo 'Could not fork process';
                exit();
            } elseif (!$pid) {
                sem_acquire($sem_id);
                shm_put_var( $shm_id, 1, stream_get_meta_data(socket_export_stream($sockets[1])));
                sem_release($sem_id);

                sleep(1);

                $counter = 0;

                while (true) {
                    if ($counter < 10) {
                        $output = fopen('php://stderr', 'w');
                        fwrite($output, 'CHILD: ' . $pid);
                        fclose($output);
                        sleep(1);
                        $counter++;
                    }
                }

                exit();
            }
        } else {
            sem_acquire($sem_id);
            $json_encoded_socket_stream = shm_get_var($shm_id, 1);
            var_dump($json_encoded_socket_stream);
            sem_release($sem_id);
        }
    }

    private function isProcessRunning($shm_id)
    {
        if (shm_has_var($shm_id, 1) && shm_get_var($shm_id, 1) != false) {
            $socket = json_decode(
                socket_import_stream(shm_get_var($shm_id, 1))
            );
            if (
                socket_getsockname($socket, $this->address, $this->port) ===
                false
            ) {
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }
}
