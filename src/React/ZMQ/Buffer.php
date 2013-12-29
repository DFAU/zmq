<?php

namespace React\ZMQ;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;

/**
 * Class Buffer
 */
class Buffer extends EventEmitter
{

    /**
     * @var \ZMQSocket
     */
    public $socket;

    /**
     * @var bool
     */
    public $closed = false;

    /**
     * @var bool
     */
    public $listening = false;

    /**
     * @var \React\EventLoop\LoopInterface
     */
    private $loop;

    /**
     * @var mixed
     */
    private $fd;

    /**
     * @var callable
     */
    private $writeListener;

    /**
     * @var array
     */
    private $messages = array();

    /**
     * @param \ZMQSocket $socket
     * @param mixed $fd
     * @param LoopInterface $loop
     * @param callable $writeListener
     */
    public function __construct(\ZMQSocket $socket, $fd, LoopInterface $loop, $writeListener)
    {
        $this->socket = $socket;
        $this->fd = $fd;
        $this->loop = $loop;
        $this->writeListener = $writeListener;
    }

    /**
     * @param string|array $message
     */
    public function send($message)
    {
        if ($this->closed) {
            return;
        }

        $this->messages[] = $message;

        if (!$this->listening) {
            $this->listening = true;
            $this->loop->addWriteStream($this->fd, $this->writeListener);
        }
    }

    /**
     *
     */
    public function end()
    {
        $this->closed = true;

        if (!$this->listening) {
            $this->emit('end');
        }
    }

    /**
     *
     */
    public function handleWriteEvent()
    {
        foreach ($this->messages as $i => $message) {
            try {
                $message = !is_array($message) ? array($message) : $message;
                $sent = (bool) $this->socket->sendmulti($message, \ZMQ::MODE_NOBLOCK);
                if ($sent) {
                    unset($this->messages[$i]);
                    if (0 === count($this->messages)) {
                        $this->loop->removeWriteStream($this->fd);
                        $this->listening = false;
                        $this->emit('end');
                    }
                }
            } catch (\ZMQSocketException $e) {
                $this->emit('error', array($e));
            }
        }
    }

}
