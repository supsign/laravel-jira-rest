<?php

namespace Supsign\LaravelJiraRest;

use Config;
use Exception;
use SimpleXMLElement;

class JiraRestApi
{
    protected
    	$cache = array(),
    	$ch = null,
        $client = null,
        $endpoint = '',
        $endpoints = array(),
        $login = null,
        $parameters = array(),
        $password = null,
        $request = array(),
        $response = null,
        $responseRaw = array(),
        $url = null;

    public function test()
    {
    	$this->endpoint = 'issue/P113SUPSIG-643';

    	$this->sendRequest();

    	return $this;
    }

	public function __construct() 
	{
		$this->login = env('JIRA_REST_LOGIN');
		$this->password = env('JIRA_REST_PASSWORD');
		$this->url = env('JIRA_REST_URL');

		return $this;
	}

	protected function clearCache()
	{
		$this->cache = array();

		return $this;
	}

	public function clearResponse()
	{
		$this->response = null;
		$this->responseRaw = array();
		$this->parameters = array();

		return $this;
	}

	protected function clearRequestData() 
	{
		foreach ($this->request AS $key => $value) {
			unset($this->request[$key]);
		}

		return $this;
	}

	protected function createRequest() 
	{
		$this->ch = curl_init();

		if ($this->endpoint) {
			curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($this->ch, CURLOPT_USERPWD, $this->login.':'.$this->password);
		}

		curl_setopt($this->ch, CURLOPT_URL, $this->url.$this->endpoint.$this->getParamterString());
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'GET');

		return $this;
	}

	public function getEndpoint() {
		return $this->endpoint;
	}

	protected function getParamterString()
	{
		if (!$this->parameters) {
			return '';
		}

		foreach ($this->parameters AS $key => $value) {
			$pairs[] = implode('=', [$key, $value]);
		}

		return '?'.implode('&', $pairs);
	}

	protected static function getProperties($element) 
	{
		return $element->content->children('m', true)->properties->children('d', true);
	}

    public function getResponse() 
    {
    	if (!$this->endpoint) {
    		throw new Exception('no endpoint specified', 1);
    	}

    	if (!$this->response) {
    		$this->sendRequest();
    	}

    	return $this->response;
    }

	protected function sendRequest()
	{
		$this->createRequest();
		$this->responseRaw = curl_exec($this->ch);
		$this->response = json_decode($this->responseRaw);
		curl_close($this->ch);

		return $this;
	}

	public function setEndpoint($endpoint) {
		$this->endpoint = $endpoint;

		return $this;
	}

    protected function setRequestData(array $data)
    {
    	$this
    		->clearRequestData()
    		->request = array_merge($this->request, $data);

    	return $this;
    }

    protected static function toStdClass($collection) 
    {
    	$collection = json_decode(json_encode($collection));

    	foreach ($collection AS $entry) {
    		foreach ($entry AS $key => $value) {
    			if (is_object($value)) {
    				$entry->$key = null;
    			}

    			switch ($key) {
    				case 'EANNummer':
    					if (!is_numeric($value) OR $value == 0 OR floor(log10($value) + 1) != 13) {
    						$entry->$key = null;
    					}
    					break;	
    			}
    		}
    	}

    	return $collection;
    }
}