<?php

class Mandrill
{

    private $version = '1.0';
    private $api_url = 'https://mandrillapp.com/api/1.0/%s/%s.json';
    private $api_key = null;
    private $last_error = null;
    private $last_error_code = 0;
    private $api_map = array(
        'users' => array(
            'info' => array(),
            'ping' => array(),
            'senders' => array(),
            'disable-sender' => array('domain'),
            'verify-sender' => array('email')
        ),
        'messages' => array(
            'send' => array('message'),
            'send-template' => array('template_name', 'template_content', 'message'),
            'search' => array('query', 'date_from', 'date_to', 'tags', 'senders', 'limit')
        ),
        'tags' => array(
            'list' => array(),
            'info' => array('tag'),
            'time-series' => array('tag'),
            'all-time-series' => array()
        ),
        'senders' => array(
            'list' => array(),
            'info' => array('address'),
            'time-series' => array('address')
        ),
        'urls' => array(
            'list' => array(),
            'search' => array('q'),
            'time-series' => array('url')
        ),
        'templates' => array(
            'add' => array('name', 'code'),
            'info' => array('name'),
            'update' => array('name', 'code'),
            'delete' => array('name'),
            'list' => array()
        ),
        'webhooks' => array(
            'list' => array(),
            'add' => array('url', 'events'),
            'info' => array('id'),
            'update' => array('id', 'url', 'events'),
            'delete' => array('id')
        )
    );


    function __construct($api_key)
    {
        $this->api_key = $api_key;
    }

    private function validate($scope, $method, $args)
    {
        if (!array_key_exists($scope, $this->api_map))
        {
            throw new Exception('Invalid call type.');
        }
        if (!array_key_exists($method, $this->api_map[$scope]))
        {
            throw new Exception("Invalid call for call type $scope");
        }

        $diff_keys = array_diff(array_keys($args), $this->api_map[$scope][$method]);

        if (count($diff_keys) > 0)
        {
            throw new Exception('Invalid keys in call: ' . implode(',', $diff_keys));
        }

        return true;
    }

    function getLastErrorCode()
    {
        return $this->last_error_code;
    }

    function getLastErrorMessage()
    {
        return $this->last_error;
    }


    function call($scope, $method, $args = array())
    {
        $this->last_error_code = 0;
        $this->last_error = null;
        if (!$this->validate($scope, $method, $args))
        {
            throw new Exception($this->last_error);
        }

        $args['key'] = $this->api_key;

        $data_string = json_encode($args);

        $parsed_url = sprintf($this->api_url, $scope, $method);


        $ch = curl_init($parsed_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string))
        );

        $result = curl_exec($ch);


        if ($method != 'ping')
        {
            $result = json_decode($result, true);
        }
        if (isset($result['status']) && $result['status'] == "error")
        {
            $this->last_error_code = $result['code'];
            $this->last_error = $result['message'];
        }


        return $result;
    }


}
