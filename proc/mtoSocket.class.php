<?php

abstract class mtoSocket
{

    public static $useSocket = true;

    public static function pair()
    {
        if (self::$useSocket)
        {
            // On Windows we need to use AF_INET
            $domain = IS_WIN ? AF_INET : AF_UNIX;
            if (!socket_create_pair($domain, SOCK_STREAM, 0, $sockets))
            {
                $errno = socket_last_error();
                $error = socket_strerror($errno);
                throw new mtoException("Can't create socket pair. Reason ($errno): $error", 1);
            }
        }
        else
        {
            // On Windows we need to use PF_INET
            $domain = IS_WIN ? STREAM_PF_INET : STREAM_PF_UNIX;
            if (!$sockets = stream_socket_pair($domain, STREAM_SOCK_STREAM, 0))
            {
                throw new mtoException('Can\'t create stream socket pair.', 1);
            }
        }
        return $sockets;
    }

    public static function read($socket, $length)
    {
        if (self::$useSocket)
        {
            return socket_read($socket, $length, PHP_BINARY_READ);
        }
        else
        {
            return fread($socket, $length);
        }
    }

    public static function readLine($socket, $length)
    {
        if (self::$useSocket)
        {
            return socket_read($socket, $length, PHP_NORMAL_READ);
        }
        else
        {
            return fgets($socket, $length);
        }
    }

    public static function write($socket, $buffer, $length = null)
    {
        if (self::$useSocket)
        {
            if ($length === null)
            {
                return socket_write($socket, $buffer);
            }
            return socket_write($socket, $buffer, $length);
        }
        else
        {
            if ($length === null)
            {
                return fwrite($socket, $buffer);
            }
            return fwrite($socket, $buffer, $length);
        }
    }

    public static function close($socket)
    {
        if (self::$useSocket)
        {
            socket_close($socket);
        }
        else
        {
            fclose($socket);
        }
    }

}
