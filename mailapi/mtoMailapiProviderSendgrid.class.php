<?php

mtoClass :: import("mtokit/mailapi/lib/SendGridHeader.class.php");
require_once(__DIR__ . "/lib/swift/swift_required.php");

class mtoMailapiProviderSendgrid extends mtoMailapiProviderAbstract
{

    protected $provider = "sendgrid";
    protected $template_callback = null;

    function __construct($devmode = false)
    {
        parent :: __construct($devmode);

        //$this->worker = new Mandrill($this->devmode ? $this->settings['apikey_dev'] : $this->settings['apikey']);
    }

    function loadLists()
    {
        return array();
    }

    function loadHooks($list_id)
    {
        throw new mtoException("not implemented");
    }

    function createHook($list_id, $url, $actions = array(), $sources = array())
    {
        throw new mtoException("not implemented");
    }

    function removeHook($list_id, $url)
    {
        throw new mtoException("not implemented");
    }

    function subscribe($list_id, $email, $args = array())
    {
        throw new mtoException("not implemented");
    }

    function update($list_id, $email, $args = array())
    {
        throw new mtoException("not implemented");
    }
    
    function updateBulk($list_id, $data)
    {
        throw new mtoException("not implemented");
    }

    function unsubscribe($list_id, $email, $args = array())
    {
        throw new mtoException("not implemented");
    }
    
    function remove($list_id, $email, $args = array())
    {
        throw new mtoException("not implemented");
    }

    function batchSubscribe($list_id, $data)
    {
        throw new mtoException("not implemented");
    }

    function createField($list_id, $tag, $args = array())
    {
        throw new mtoException("not implemented");
    }

    function memberInfo($list_id, $email)
    {
        throw new mtoException("not implemented");
    }

    function hasError($result = array())
    {
//        if ($this->worker->getLastErrorCode())
//        {
//            return array('code' => $this->worker->getLastErrorCode(), 'message' => $this->worker->getLastErrorMessage());
//        }
//        else
//        {
//            return false;
//        }
    }

    function usersInfo()
    {
        return array();
    }
    
    function setTemplateCallback($callback)
    {
        $this->template_callback = $callback;
    }

    function getMessageTemplate($tpl)
    {
        $filename = "templates/mail/send_grid/" . $tpl . ".tpl";
        if (is_callable($this->template_callback))
        {
            return call_user_func_array($this->template_callback, array($tpl));
        }
        else
        {
            return file_get_contents($filename);
        }
    }
    
    function sendMessage($tpl, $tpl_data, $message)
    {
        $hdr = new SendGridHeader();

        foreach ($message['to'] as $r)
        {
            $hdr->addTo($r['email']);
        }
//        $toStruct = array_shift($message['to']);
//        $to = $toStruct['email'];
//
//        $hdr->addTo($to);
        foreach ($message['global_merge_vars'] as $value)
        {
            $hdr->addSubVal("*|".$value['name']."|*", $value['content']);
        }
        //$hdr->addFilterSetting('footer', 'enable', 1);
        $hdr->addFilterSetting('subscriptiontrack', 'enable', 0);

        if (isset($message['category_id']))
        {
            $hdr->setCategory($message['category_id']);
        }
        else
        {
            $hdr->setCategory($tpl);
        }
        $subject = $message['subject'];
        $from = array($message['from_email'] => $message["from_name"]);
        $html = $this->getMessageTemplate($tpl);
        //var_dump($html);

//        $to = array('defaultdestination@example.com' => 'Personal Name Of Recipient');




        $username = $this->settings['username'];
        $password = $this->settings['password'];

        $transport = Swift_SmtpTransport::newInstance('smtp.sendgrid.net', 25);
        $transport->setUsername($username);
        $transport->setPassword($password);
        $swift = Swift_Mailer::newInstance($transport);

        $msg = new Swift_Message($subject);
        $headers = $msg->getHeaders();
        $headers->addTextHeader('X-SMTPAPI', $hdr->asJSON());
        $msg->setSubject($subject);
        //var_dump($hdr->asJSON());

        $msg->setFrom($from);
        $msg->setBody($html, 'text/html');
        foreach ($message['to'] as $r)
        {
            $msg->addTo($r['email'], $r['name']);
        }
        //$message->setTo($to);
//        $message->addPart($text, 'text/plain');

        if ($recipients = $swift->send($msg, $failures))
        {
            //echo 'Message sent out to ' . $recipients . ' users';
        }
        else
        {
            //var_dump($recipients);
            //echo "Something went wrong - ";
            //print_r($failures);
        }
        //return $result;
    }
    
    function loadFields()
    {
        throw new mtoException("not supported");
    }
    
    function dumpList($list_id, $status = null)
    {
        throw new mtoException("not supported");
    }
    

}