<?php
mtoClass :: import("mtokit/thread/mtoLibEventBasic.class.php");

class mtoLibEventBuffer extends mtoLibEventBasic
{
    const E_READ = 0x01; // EVBUFFER_READ
    const E_WRITE = 0x02; // EVBUFFER_WRITE
    const E_EOF = 0x10; // EVBUFFER_EOF
    const E_ERROR = 0x20; // EVBUFFER_ERROR
    const E_TIMEOUT = 0x40; // EVBUFFER_TIMEOUT
    const DEF_LOWMARK = 1;
    const DEF_HIGHMARK = 0xffffff;
    const DEF_PRIORITY = 10;
    const DEF_TIMEOUT_READ = 30;
    const DEF_TIMEOUT_WRITE = 30;
    public $stream;

    public function __construct($stream, $readcb, $writecb, $errorcb, $arg = null)
    {
        parent::__construct();
        $this->stream = $stream;
        if (!$this->resource = event_buffer_new($stream, $readcb, $writecb, $errorcb, array($this, $arg)))
        {
            throw new mtoException('Can\'t create new buffered event resourse (event_buffer_new)', 1);
        }
    }

    public function disable($events)
    {
        $this->checkResourse();
        if (!event_buffer_disable($this->resource, $events))
        {
            throw new mtoException("Can't disable buffered event (event_buffer_disable)", 1);
        }
        return $this;
    }

    public function enable($events)
    {
        $this->checkResourse();
        if (!event_buffer_enable($this->resource, $events))
        {
            throw new mtoException("Can't enable buffered event (event_buffer_enable)", 1);
        }
        return $this;
    }

    public function setBase($event_base)
    {
        $this->checkResourse();
        $event_base->checkResourse();
        if (!event_buffer_base_set($this->resource, $event_base->resource))
        {
            throw new mtoException('Can\'t set buffered event base (event_buffer_base_set)', 1);
        }
        return parent::setBase($event_base);
    }

    public function free()
    {
        if ($this->resource)
        {
            event_buffer_free($this->resource);
            $this->resource = null;
            parent::free();
        }
        return $this;
    }

    public function read($data_size)
    {
        $this->checkResourse();
        return event_buffer_read($this->resource, $data_size);
    }

    public function write($data, $data_size = -1)
    {
        $this->checkResourse();
        if (!event_buffer_write($this->resource, $data, $data_size))
        {
            throw new mtoException('Can\'t write data to the buffered event (event_buffer_write)', 1);
        }
        return $this;
    }

    public function setStream($stream)
    {
        $this->checkResourse();
        if (!event_buffer_fd_set($this->resource, $stream))
        {
            throw new mtoException("Can't set buffered event stream (event_buffer_fd_set)", 1);
        }
        $this->stream = $stream;
        return $this;
    }

    public function setCallback($readcb, $writecb, $errorcb, $arg = null)
    {
        $this->checkResourse();
        if (!event_buffer_set_callback($this->resource, $readcb, $writecb, $errorcb, array($this, $arg)))
        {
            throw new mtoException("Can't set buffered event callbacks (event_buffer_set_callback)", 1);
        }
        return $this;
    }

    public function setTimout($read_timeout = self::DEF_TIMEOUT_READ, $write_timeout = self::DEF_TIMEOUT_WRITE)
    {
        $this->checkResourse();
        event_buffer_timeout_set($this->resource, $read_timeout, $write_timeout);
        return $this;
    }

    public function setWatermark($events, $lowmark = self::DEF_LOWMARK, $highmark = self::DEF_HIGHMARK)
    {
        $this->checkResourse();
        event_buffer_watermark_set($this->resource, $events, $lowmark, $highmark);
        return $this;
    }

    public function setPriority($value = self::DEF_PRIORITY)
    {
        $this->checkResourse();
        if (!event_buffer_priority_set($this->resource, $value))
        {
            throw new mtoException("Can't set buffered event priority to $value (event_buffer_priority_set)", 1);
        }
        return $this;
    }

}
