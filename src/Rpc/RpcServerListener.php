<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-08-30
 * Time: 15:06
 */

namespace Inhere\Server\Rpc;

use Inhere\Server\Listener\Port\PortListener;
use Inhere\Server\Listener\Port\TcpListenerInterface;
use Swoole\Server;

/**
 * Class RpcServer
 * @package Inhere\Server\Rpc
 */
abstract class RpcServerListener extends PortListener implements TcpListenerInterface
{
    // protected $type = 'tcp';

    /**
     * @var ParserInterface
     */
    protected $parser;

    /**
     * @var ParserInterface[]
     * [
     *  'text' => TextParser,
     *  'json' => JsonParser,
     *  'xml' => XmlParser,
     * ]
     */
    private $parsers = [];

    protected function init()
    {
        $this->options['setting'] = [
            'open_eof_check' => true,
            'package_eof' => "\r\n\r\n",
            'package_max_length' => 1024 * 1024 * 2,
            'socket_buffer_size' => 1024 * 1024 * 2, //2M缓存区
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function onConnect(Server $server, $fd)
    {
        $this->mgr->log("Has a new client [FD:$fd] connection.");
    }

    /**
     * 接收到数据
     *     使用 `fd` 保存客户端IP，`from_id` 保存 `from_fd` 和 `port`
     * @param  Server $server
     * @param  int $fd
     * @param  int $fromId
     * @param  mixed $data
     */
    public function onReceive(Server $server, $fd, $fromId, $data)
    {
        $data = trim($data);
        $this->log("Receive data [$data] from client [FD:$fd].");

        $server->send($fd, "I have been received your message.\n");

        // $this->onTaskReceive($server, $fd, $fromId, $data);

        $this->handleRpcRequest($server, $data, $fd);
    }

    /**
     * {@inheritDoc}
     */
    public function onClose(Server $server, $fd)
    {
        $this->mgr->log("The client [FD:$fd] connection closed.");
    }

    abstract protected function handleRpcRequest(Server $server, $data, $fd);

    /**
     * @param $name
     * @return ParserInterface|null
     */
    public function getParser($name): ?ParserInterface
    {
        return $this->parsers[$name] ?? null;
    }

    /**
     * @param ParserInterface $parser
     */
    public function setParser(ParserInterface $parser)
    {
        $this->parsers[$parser->getName()] = $parser;
    }

    /**
     * @param ParserInterface $parser
     */
    public function addParser(ParserInterface $parser)
    {
        if (isset($this->parsers[$parser->getName()])) {
            $this->parsers[$parser->getName()] = $parser;
        }
    }

    /**
     * @return ParserInterface[]
     */
    public function getParsers(): array
    {
        return $this->parsers;
    }

    /**
     * @param ParserInterface[] $parsers
     */
    public function setParsers(array $parsers)
    {
        foreach ($parsers as $parser) {
            $this->setParser($parser);
        }
    }
}
