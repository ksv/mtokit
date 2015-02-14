<?php

class mtoIpcQueue
{
    const PERMISSIONS = 0777;

    public $maxMsgSize = 65536; // 64 Kb
    public $blocking = false;
    public $blockingTimeout = 60;
    public $blockingWait = 10000;
    public $blockingError = false;
    protected $key;
    protected $permissions;
    protected $queue;
    protected static $msgsnd_errors = array(
        // EACCES
        13 => "The calling process does not have write permission on the message queue",
        MSG_EAGAIN => "The message can't be sent due to the msg_qbytes limit for the queue",
        // EFAULT
        14 => "The address pointed to by msgp isn't accessible",
        // EIDRM
        43 => "The message queue was removed",
        // EINTR
        4 => "Sleeping on a full message queue condition, the process caught a signal",
        // EINVAL
        22 => "Invalid msqid value, or non-positive mtype value, or invalid msgsz value (less than 0 or greater than the system value MSGMAX)",
        // ENOMEM
        12 => "The system does not have enough memory to make a copy of the message pointed to by msgp",
    );
    protected static $msgrcv_errors = array(
        // E2BIG*/
        7 => "The message text length is greater than msgsz and MSG_NOERROR isn't specified in msgflg",
        // EACCES
        13 => "The calling process does not have read permission on the message queue",
        MSG_EAGAIN => "No message was available in the queue and IPC_NOWAIT was specified in msgflg",
        // EFAULT
        14 => "The address pointed to by msgp isn't accessible",
        // EIDRM
        43 => "While the process was sleeping to receive a message, the message queue was removed",
        // EINTR
        4 => "While the process was sleeping to receive a message, the process caught a signal",
        // EINVAL
        22 => "msgqid was invalid, or msgsz was less than 0",
        MSG_ENOMSG => "IPC_NOWAIT was specified in msgflg and no message of the requested type existed on the message queue",
    );

    public static function instance($file, $proj = 'q', $permissions = self::PERMISSIONS)
    {
        $key = ftok($file, $proj);
        if ($key === -1)
        {
            throw new mtoException("Can't initialize message queue with file [$file] and project [$proj]", 1);
        }
        return new self($key, $permissions);
    }

    public function __construct($key, $permissions = self::PERMISSIONS)
    {
        if (!function_exists('msg_get_queue'))
        {
            throw new mtoException('You need php compiled with --enable-sysvmsg option to work with shared memory message queue');
        }
        if (!is_numeric($key))
        {
            $key = sprintf('%u', crc32($key));
        }
        $this->key = (int) $key;
        $this->permissions = $permissions;
    }

    protected function init()
    {
        if (null === $this->queue)
        {
            $this->queue = msg_get_queue($this->key, $this->permissions);
        }
    }

    public function put($data)
    {
        $this->init();

        $data = self::useIgbinary() ? igbinary_serialize($data) : serialize($data);
        if (!msg_send($this->queue, 1, $data, false, false, $errno))
        {
            $error = $errno && isset(self::$msgsnd_errors[$errno]) ? self::$msgsnd_errors[$errno] : 'Cannot send message';
            throw new mtoException($error, 1, $errno);
        }

        return true;
    }

    public function peek()
    {
        $this->init();
        $flags = $this->blocking && !$this->blockingTimeout ? 0 : MSG_IPC_NOWAIT;

        if (!$this->blockingTimeout || !$this->blocking)
        {
            msg_receive($this->queue, 1, $msgtype, $this->maxMsgSize, $message, false, $flags, $errno);
        }
        else
        {
            $start = microtime(true);
            $timeout = $this->blockingTimeout ? : false;
            do
            {
                if (!msg_receive($this->queue, 1, $msgtype, $this->maxMsgSize, $message, false, $flags, $errno))
                {
                    usleep($this->blockingWait);
                }
            }
            while (!$message && (!$timeout || microtime(true) - $timeout < $start));
        }

        if ($message)
        {
            return self::useIgbinary() ? igbinary_unserialize($message) : unserialize($message);
        }
        if ($errno == MSG_ENOMSG && (!$this->blocking || !$this->blockingError))
        {
            return null;
        }
        $error = $errno && isset(self::$msgrcv_errors[$errno]) ? self::$msgrcv_errors[$errno] : 'Can\'t receive message';
        throw new mtoException($error, 1, $errno);
    }

    public function capacity()
    {
        $this->init();
        $stat = msg_stat_queue($this->queue);
        return $stat["msg_qnum"];
    }

    public function stat()
    {
        $this->init();
        return msg_stat_queue($this->queue);
    }

    public function destroy()
    {
        $this->init();
        $res = msg_remove_queue($this->queue);
        $this->queue = null;
        return $res;
    }

    public static function useIgBinary()
    {
        return function_exists('igbinary_serialize');
    }

}

