<?php
mtoClass :: import("mtokit/unittest/mtoUnittest.class.php");
mtoClass :: import("mtokit/unittest/mtoUnittestCollector.class.php");

class mtoUnittestRunner
{
    protected $setup = array();
    protected $teardown = array();
    protected $preexec = array();
    protected $postexec = array();
    protected $tests = array();
    protected $test_count = 0;

    function run($args)
    {
        $time = microtime(true);
        if (!empty($args['only-teardown']))
        {
            $this->execute($this->teardown);
            exit;
        }
        if (empty($args['skip-setup']))
        {
            $this->execute($this->setup);
        }
        $this->execute($this->preexec);
        $this->execute($this->tests);
        if (empty($args['skip-teardown']))
        {
            $this->execute($this->teardown);
        }
        $collector = mtoUnittestCollector :: instance();
        $collector->addMessage("\n\n\n", true);
        $collector->addMessage("{$this->test_count} tests executed in ".round(microtime(true)-$time, 2)." seconds", true);
        $collector->addMessage($collector->getCases() . " cases executed", true);
        $collector->addMessage($collector->getPassed() . " cases passed", true);
        $collector->addMessage($collector->getFailed() . " cases failed", true);
        $collector->addMessage(number_format(memory_get_peak_usage(true)) . " bytes allocated", true);
        $collector->addMessage("\n\n\n", true);
    }

    function collect($args = array())
    {
        $files = glob(dirname(__FILE__) . "/../*/tests/*.class.php");
        foreach ($files as $file)
        {
            if (preg_match("#^.+Test\.class\.php$#", $file))
            {
                if (!$this->filter($file, $args))
                {
                    continue;
                }
                $this->test_count++;
                $className = str_replace(".class.php", "", basename($file));
                mtoClass :: import($file);
                $instance = new $className();
                $class = new ReflectionClass($className);
                $methods = $class->getMethods();
                $constants = $class->getConstants();
                if (isset($constants['PRIORITY']))
                {
                    $index = $constants['PRIORITY'];
                }
                else
                {
                    $index = 9999;
                }
                if (!isset($this->setup[$index]))
                {
                    $this->setup[$index] = array();
                }
                if (!isset($this->teardown[$index]))
                {
                    $this->teardown[$index] = array();
                }
                if (!isset($this->tests[$index]))
                {
                    $this->tests[$index] = array();
                }
                if (!isset($this->preexec[$index]))
                {
                    $this->preexec[$index] = array();
                }
                if (!isset($this->postexec[$index]))
                {
                    $this->postexec[$index] = array();
                }
                foreach ($methods as $method)
                {
                    if ($method->class == $className)
                    {
                        if ($method->name == "setUp")
                        {
                            $this->setup[$index][] = array($instance, $method->name);
                        }
                        if ($method->name == "tearDown")
                        {
                            $this->teardown[$index][] = array($instance, $method->name);
                        }
                        if ($method->name == "preExec")
                        {
                            $this->preexec[$index][] = array($instance, $method->name);
                        }
                        if ($method->name == "postExec")
                        {
                            $this->preexec[$index][] = array($instance, $method->name);
                        }
                        if (preg_match("#^test#", $method->name))
                        {
                            $this->tests[$index][] = array($instance, $method->name);
                        }
                    }
                }
            }
        }
        ksort($this->setup);
        ksort($this->teardown);
        ksort($this->tests);
    }

    function setMessageCallback($callback)
    {
        mtoUnittestCollector :: instance()->setMessageCallback($callback);
    }

    protected function filter($file, $args = array())
    {
        return true;
    }

    protected function execute($list)
    {
        foreach ($list as $index => $clist)
        {
            foreach ($clist as $callback)
            {
                call_user_func_array($callback, array());
            }
        }
    }

}