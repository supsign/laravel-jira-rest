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
        $password = null,
        $request = array(),
        $requestMaxResults = 1000,
        $requestIssueStatus = array('Backlog', '"In Progress"', 'Open', 'Resolved'),
        $response = null,
        $responseKey = null,
        $responseRaw = array(),
        $step = 100,
        $url = null;

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
		$this->responseKey = null;

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

		curl_setopt($this->ch, CURLOPT_URL, $this->url.$this->endpoint.$this->getRequestString());
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'GET');

		return $this;
	}

	public function getEndpoint() {
		return $this->endpoint;
	}

	public function getIssue($id)
	{
		$this->newCall()->endpoint = 'issue/'.$id;
		// $this->responseKey = 'fields';

		return $this->getResponse();
	}

	public function getIssues() 
	{
		$this->newCall()->endpoint = 'search';
		$this->responseKey = 'issues';

    	$this->setRequestData([
			'maxResults' => $this->requestMaxResults,
			'jql' => urlencode('status in ('.$this->getRequestIssueStatus().') ORDER BY created DESC')
    	]);

		return $this->getResponse();
	}

	public function getIssuesByAssignee($id)
	{
		$this->newCall()->endpoint = 'search';
		$this->responseKey = 'issues';

    	$this->setRequestData([
			'maxResults' => $this->requestMaxResults,
			'jql' => urlencode('assignee in ('.$id.') AND status in ('.$this->getRequestIssueStatus().') ORDER BY created DESC')
    	]);

    	return $this->getResponse();
	}

	protected function getRequestIssueStatus()
	{
		return implode(',', $this->requestIssueStatus);
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
    		$this->sendRequests();
    	}

    	return $this->response;
    }

	protected function newCall() {
		return $this
			->clearRequestData()
			->clearResponse();
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

			if (!isset($this->request['startAt'])) {
				$this->request['startAt'] = $this->step;
			} else {
				$this->request['startAt'] += $this->step;
			}
    	} while (!$this->requestFinished);

    	$this->response = $this->responseRaw;
    	unset($this->responseRaw);

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

	public function setRequestIssueStatus(array $statis) {
		$this->requestIssueStatus = $statis;

		return $this;
	}

    protected function setResponse($response) 
    {
    	$this->requestFinished = true;

    	if (isset($response->total)) {
    		$this->requestFinished = !isset($this->request['startAt']) ? false : $response->total < $this->request['startAt'];
	    	$this->responseRaw = array_merge($this->responseRaw, $response->{$this->responseKey});

	    	return $this;
    	} 

    	$this->responseRaw = isset($this->responseKey) ? $response->{$this->responseKey} : $response;

		return $this;
    }
}