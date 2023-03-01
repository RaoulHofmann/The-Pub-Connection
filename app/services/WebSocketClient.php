<?php
namespace App\Services;

use Phalcon\Di\Injectable;

class WebSocketClient extends Injectable
{
    public function connect()
    {
        $websocketPort = $this->config->application->websocketPort;
        $httpHost = $this->request->getServer('HTTP_HOST');

        // You may need to change the domain name and host
        // port depending upon the system.
        $this->view->setVars([
            'HTTP_HOST'      => $httpHost,
            'WEBSOCKET_PORT' => $websocketPort,
        ]);
    }
}