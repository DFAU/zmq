<?php

namespace DFAU\ZMQ;

use React\EventLoop\LoopInterface;

/**
 * Class Context
 *
 * @method \mixed getOpt(string $key) Get context option
 * @method \DFAU\ZMQ\SocketWrapper getSocket(int $type, string $persistent_id = NULL, callable $on_new_socket = NULL) Create a new socket
 * @method \bool isPersistent() Whether the context is persistent
 * @method \ZMQContext setOpt(int $key, mixed $value) Set a socket option
 */
class Context
{

    /**
     * @var \React\EventLoop\LoopInterface
     */
    private $loop;

    /**
     * @var \ZMQContext
     */
    private $context;

    /**
     * @param LoopInterface $loop
     * @param \ZMQContext $context
     */
    public function __construct(LoopInterface $loop, \ZMQContext $context = null)
    {
        $this->loop = $loop;
        $this->context = $context ?: new \ZMQContext();
    }

    /**
     * @param $method
     * @param $args
     * @return mixed|SocketWrapper
     */
    public function __call($method, $args)
    {
        $res = call_user_func_array(array($this->context, $method), $args);
        if ($res instanceof \ZMQSocket) {
            $res = $this->wrapSocket($res);
        }
        return $res;
    }

    /**
     * @param \ZMQSocket $socket
     * @return SocketWrapper
     */
    private function wrapSocket(\ZMQSocket $socket)
    {
        $wrapped = new SocketWrapper($socket, $this->loop);

        if ($this->isReadableSocketType($socket->getSocketType())) {
            $wrapped->attachReadListener();
        }

        return $wrapped;
    }

    /**
     * @param mixed $type
     * @return bool
     */
    private function isReadableSocketType($type)
    {
        $readableTypes = array(
            \ZMQ::SOCKET_PULL,
            \ZMQ::SOCKET_SUB,
            \ZMQ::SOCKET_REQ,
            \ZMQ::SOCKET_REP,
            \ZMQ::SOCKET_ROUTER,
            \ZMQ::SOCKET_DEALER,
        );

        return in_array($type, $readableTypes);
    }

}
