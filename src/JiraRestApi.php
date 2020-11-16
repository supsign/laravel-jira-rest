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
        $step = 100,
        $url = null;

    public function test()
    {
    	$this->endpoint = 'search';
    	// $this->endpoint = 'users/search';
    	// $this->endpoint = 'user';

    	$this->setRequestData([
			'maxResults' => 1000,
			// 'startAt' => 100,
			'jql' => urlencode('assignee in (5cd3af9d5c99a60dcbae1e1e) order by created DESC')
    	]);

    	// $this->setRequestData([
    	// 	'accountId' => '5db5856352817b0c343d6d5c'
    	// ]);



    	$this->sendRequests();

    	// var_dump($this->response);

    	// var_dump(count($this->response->issues));

    	// foreach ($this->response->issues AS $issue) {
    	// 	var_dump($issue);
    	// }

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

		var_dump(
			$this->url.$this->endpoint.$this->getRequestString()
		);

		curl_setopt($this->ch, CURLOPT_URL, $this->url.$this->endpoint.$this->getRequestString());
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'GET');

		return $this;
	}

	public function getEndpoint() {
		return $this->endpoint;
	}

	protected function getRequestString()
	{
		if (!$this->request) {
			return '';
		}

		foreach ($this->request AS $key => $value) {
			$pairs[] = implode('=', [$key, $value]);
		}

		return '?'.implode('&', $pairs);
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
		$this->setResponse(json_decode(curl_exec($this->ch)));
		curl_close($this->ch);

		return $this;
	}

    protected function sendRequests()
    {
    	do {
    		$this->sendRequest();

			if (!isset($this->parameters['startAt'])) {
				$this->request['startAt'] = $this->step;
			} else {
				$this->request['startAt'] += $this->step;
			}
    	} while (!$this->requestFinished);

    	$this->response = $this->responseRaw;

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
    		->request = $data;

    	return $this;
    }

    protected function setResponse($response) 
    {
    	if (!isset($this->request['startAt']))
    		$this->requestFinished = false;
    	else
			$this->requestFinished = $response->total < $this->request['startAt'];

		var_dump(
			$this->request
		);

    	// $this->responseRaw = array_merge($this->responseRaw, $response->{$this->endpoint == 'search' ? 'issues' : $this->endpoint});

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