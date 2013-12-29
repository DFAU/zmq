<?php

namespace React\ZMQ;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;

/**
 * Class SocketWrapper
 *
 * @method \React\ZMQ\SocketWrapper bind(string $dsn, bool $force = false) Bind the socket
 * @method \React\ZMQ\SocketWrapper connect(string $dsn, bool $force = false) Connect the socket
 * @method \React\ZMQ\SocketWrapper disconnect(string $dsn) Disconnect a socket
 * @method \array getEndpoints() Returns an array containing elements 'bind' and 'connect'.
 * @method \string getPersistentId() Returns the persistent id string assigned of the object and null if socket is not persistent.
 * @method \mixed getSockOpt(string $key) Returns either a string or an integer depending on
 * @method \int getSocketType() Returns an integer representing the socket type. The integer can be compared against constants.
 * @method \bool isPersistent() Returns a boolean based on whether the socket is persistent or not.
 * @method \string recv(int $mode = false) Receives a message
 * @method \string recvMulti(int $mode = false) Receives a multipart message
 * @method \React\ZMQ\SocketWrapper sendmulti(array $message, int $mode = false) Sends a multipart message
 * @method \React\ZMQ\SocketWrapper setSockOpt(int $key, mixed $value) Set a socket option
 * @method \React\ZMQ\SocketWrapper unbind(string $dsn) Unbind the socket
 */
class SocketWrapper extends EventEmitter
{

    /**
     * @var mixed
     */
    public $fd;

    /**
     * @var bool
     */
    public $closed = false;

    /**
     * @var \ZMQSocket
     */
    private $socket;

    /**
     * @var \React\EventLoop\LoopInterface
     */
    private $loop;

    /**
     * @var Buffer
     */
    private $buffer;

    /**
     * @param \ZMQSocket $socket
     * @param LoopInterface $loop
     */
    public function __construct(\ZMQSocket $socket, LoopInterface $loop)
    {
        $this->socket = $socket;
        $this->loop = $loop;

        $this->fd = $this->socket->getSockOpt(\ZMQ::SOCKOPT_FD);

        $writeListener = array($this, 'handleEvent');
        $this->buffer = new Buffer($socket, $this->fd, $this->loop, $writeListener);
    }

    /**
     *
     */
    public function attachReadListener()
    {
        $this->loop->addReadStream($this->fd, array($this, 'handleEvent'));
    }

    /**
     *
     */
    public function handleEvent()
    {
        while (true) {
            $events = $this->socket->getSockOpt(\ZMQ::SOCKOPT_EVENTS);

            $hasEvents = ($events & \ZMQ::POLL_IN) || ($events & \ZMQ::POLL_OUT && $this->buffer->listening);
            if (!$hasEvents) {
                break;
            }

            if ($events & \ZMQ::POLL_IN) {
                $this->handleReadEvent();
            }

            if ($events & \ZMQ::POLL_OUT && $this->buffer->listening) {
                $this->buffer->handleWriteEvent();
            }
        }
    }

    /**
     *
     */
    public function handleReadEvent()
    {
        $messages = $this->socket->recvmulti(\ZMQ::MODE_NOBLOCK);
        if (false !== $messages) {
            if (1 === count($messages)) {
                $this->emit('message', array($messages[0]));
            }
            $this->emit('messages', array($messages));
        }
    }

    /**
     * @return \ZMQSocket
     */
    public function getWrappedSocket()
    {
        return $this->socket;
    }

    /**
     * @param string $channel
     */
    public function subscribe($channel)
    {
        $this->socket->setSockOpt(\ZMQ::SOCKOPT_SUBSCRIBE, $channel);
    }

    /**
     * @param string $channel
     */
    public function unsubscribe($channel)
    {
        $this->socket->setSockOpt(\ZMQ::SOCKOPT_UNSUBSCRIBE, $channel);
    }

    /**
     * @param string|array $message
     */
    public function send($message)
    {
        $this->buffer->send($message);
    }

    /**
     *
     */
    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->emit('end', array($this));
        $this->loop->removeStream($this->fd);
        $this->buffer->removeAllListeners();
        $this->removeAllListeners();
        unset($this->socket);
        $this->closed = true;
    }

    /**
     *
     */
    public function end()
    {
        if ($this->closed) {
            return;
        }

        $that = $this;

        $this->buffer->on('end', function () use ($that) {
            $that->close();
        });

        $this->buffer->end();
    }

    /**
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array(array($this->socket, $method), $args);
    }

}
