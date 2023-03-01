<?php
declare(strict_types=1);

namespace App\Tasks;

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

use App\Services\Chat;

use Phalcon\Cli\Task;

class ChatTask extends Task
{
    public function mainAction()
    {
        $websocketPort = $this->getDI()->getConfig()->application->websocketPort;

        $server = IoServer::factory(
            new HttpServer(new WsServer(new Chat())),
            $websocketPort
        );

        $server->run();
    }
}
