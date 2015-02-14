<?php

abstract class mtoShell
{
    const OK = 0;
    const ERROR = 1;
    const LOCKED = 2;
    const USAGE = 64;
    const DATAERR = 65;
    const NOINPUT = 66;
    const NOUSER = 67;
    const NOHOST = 68;
    const UNAVAILABLE = 69;
    const SOFTWARE = 70;
    const OSERR = 71;
    const OSFILE = 72;
    const CANTCREAT = 73;
    const IOERR = 74;
    const TEMPFAIL = 75;
    const PROTOCOL = 76;
    const NOPERM = 77;
    const CONFIG = 78;
    const TERM = 143;


    public static $eventBase;
    public static $isMaster = true;
    public static $signals = array(
        SIGHUP => 'SIGHUP',
        SIGINT => 'SIGINT',
        SIGQUIT => 'SIGQUIT',
        SIGILL => 'SIGILL',
        SIGTRAP => 'SIGTRAP',
        SIGABRT => 'SIGABRT',
        7 => 'SIGEMT',
        SIGFPE => 'SIGFPE',
        SIGKILL => 'SIGKILL',
        SIGBUS => 'SIGBUS',
        SIGSEGV => 'SIGSEGV',
        SIGSYS => 'SIGSYS',
        SIGPIPE => 'SIGPIPE',
        SIGALRM => 'SIGALRM',
        SIGTERM => 'SIGTERM',
        SIGURG => 'SIGURG',
        SIGSTOP => 'SIGSTOP',
        SIGTSTP => 'SIGTSTP',
        SIGCONT => 'SIGCONT',
        SIGCHLD => 'SIGCHLD',
        SIGTTIN => 'SIGTTIN',
        SIGTTOU => 'SIGTTOU',
        SIGIO => 'SIGIO',
        SIGXCPU => 'SIGXCPU',
        SIGXFSZ => 'SIGXFSZ',
        SIGVTALRM => 'SIGVTALRM',
        SIGPROF => 'SIGPROF',
        28 => 'SIGINFO',
        SIGWINCH => 'SIGWINCH',
        SIGUSR1 => 'SIGUSR1',
        SIGUSR2 => 'SIGUSR2',
    );

    public static function signalName($signo, &$found = null)
    {
        return ($found = isset(self::$signals[$signo])) ? self::$signals[$signo] : 'UNKNOWN';
    }

    public static function signalHandle($handler, $signo = null, $ignore = false, $default = false)
    {
        $handler = $ignore ? SIG_IGN : ($default ? SIG_DFL : $handler);

        if ($signo !== null)
        {
            if (isset(self::$signals[$signo]) && SIGKILL !== $signo && SIGSTOP !== $signo)
            {
                if (!pcntl_signal($signo, $handler))
                {
                    $name = self::$signals[$signo];
                    throw new mtoException("Can't initialize signal handler for $name ($signo)");
                }
            }
        }
        else
        {
            foreach (self::$signals as $signo => $name)
            {
                if ($signo === SIGKILL || $signo === SIGSTOP)
                {
                    continue;
                }
                if (!pcntl_signal($signo, $handler))
                {
                    throw new mtoException("Can't initialize signal handler for $name ($signo)");
                }
            }
        }
    }

    public static function signalWait($signo, $seconds = 1, $nanoseconds = null, &$siginfo = null)
    {
        if (isset(self::$signals[$signo]))
        {
            pcntl_sigprocmask(SIG_BLOCK, array($signo));
            $res = pcntl_sigtimedwait(array($signo), $siginfo, $seconds, $nanoseconds);
            pcntl_sigprocmask(SIG_UNBLOCK, array($signo));
            if ($res > 0)
            {
                return true;
            }
        }
        return false;
    }

    public static function fork()
    {
        $pid = pcntl_fork();
        if ($pid === -1)
        {
            throw new mtoException('Could not fork');
        }
        else if ($pid === 0)
        {
            self::$isMaster = false;
        }
        return $pid;
    }

    public static function detach()
    {
        if (self::fork())
        {
            exit;
        }

        self::$isMaster = true;
        if (posix_setsid() === -1)
        {
            throw new mtoException('Could not detach from terminal');
        }
    }

    public static function getTtyColumns($stream = null)
    {
        if (IS_WIN || !self::getIsTty($stream ? : STDOUT))
        {
            return 95;
        }
        $cols = (int) shell_exec('stty -a|grep -oPm 1 "(?<=columns )(\d+)(?=;)"');
        return max(40, $cols);
    }

    public static function getIsTty($stream)
    {
        return function_exists('posix_isatty') && @posix_isatty($stream);
    }

    public static function getCommandByPid($pid)
    {
        if ($pid < 1 || IS_WIN)
        {
            return '';
        }
        exec("ps -p {$pid} -o%c", $data);
        return $data && count($data) === 2 ? array_pop($data) : '';
    }

    public static function killProcessTree($pid, $signal = SIGKILL)
    {
        if ($pid < 1 || IS_WIN)
        {
            return;
        }

        // Kill childs
        exec("ps -ef| awk '\$3 == '$pid' { print  \$2 }'", $output, $ret);
        if ($ret)
        {
            throw new mtoException('You need ps, grep, and awk', 1);
        }
        foreach ($output as $t)
        {
            if ($t != $pid)
            {
                self::killProcessTree($t, $signal);
            }
        }

        // Kill self
        posix_kill($pid, $signal);
    }

    public static function getProcessIsAlive($pid)
    {
        // EPERM === 1
        return posix_kill($pid, 0) || posix_get_last_error() === 1;
    }

    public static function setProcessTitle($title)
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        function_exists('setproctitle') && setproctitle($title);
    }

    public static function getLogTime()
    {
        $mt = explode(' ', microtime());
        return '[' . date('Y.m.d H:i:s', $mt[1]) . '.' . sprintf('%06d', $mt[0] * 1000000) . ' ' . date('O') . ']';
    }

    public static function hasForkSupport()
    {
        return function_exists('pcntl_fork');
    }

    public static function hasLibEvent()
    {
        return function_exists("event_base_new");
    }

}

