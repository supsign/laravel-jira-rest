<?php

namespace Supsign\LaravelJiraRest;

use Config;
use Exception;
use SimpleXMLElement;

class JiraRestApi
{
    protected
    	$ch = null,
        $client = null,
        $endpoint = '',
        $endpoints = array(),
        $login = null,
        $password = null,
        $request = array(),
        $requestMaxResults = 1000,
        $requestIssueStatus = array('"In Progress"', 'Open', 'Resolved'),
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

	protected function createRequest($method = 'GET') 
	{
		$this->ch = curl_init();

		if ($this->endpoint) {
			curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($this->ch, CURLOPT_USERPWD, $this->login.':'.$this->password);
		}

		curl_setopt($this->ch, CURLOPT_URL, $this->url.$this->endpoint.$this->getRequestString());
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);

		if (strtoupper($method) === 'POST') {
			curl_setopt($this->ch, CURLOPT_POST, true);
		} else
			curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $method);

		return $this;
	}

	public function getEndpoint() {
		return $this->endpoint;
	}

	public function getIssue($id)
	{
		$this->newCall()->endpoint = 'issue/'.$id;

		return $this->getResponse();
	}

	public function getIssues() 
	{
		$this->newCall()->endpoint = 'search';
		$this->responseKey = 'issues';
    	$this->setRequestData([
			'maxResults' => $this->requestMaxResults,
			'jql' => urlencode('status in ('.$this->getRequestIssueStatus().') ORDER BY duedate DESC')
    	]);

		return $this->getResponse();
	}

	public function getIssuesByAssignee($id)
	{
		$this->newCall()->endpoint = 'search';
		$this->responseKey = 'issues';
    	$this->setRequestData([
			'maxResults' => $this->requestMaxResults,
			'jql' => urlencode('assignee in ('.$id.') AND status in ('.$this->getRequestIssueStatus().') ORDER BY duedate DESC')
    	]);

    	return $this->getResponse();
	}

	public function getUser($accountId) {
		$this
			->newCall()
			->setRequestData(['accountId' => $accountId])
			->endpoint = 'user';

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
    	$result = isset($this->responseKey) ? $response->{$this->responseKey} : $response;

    	if (isset($response->total)) {
    		$this->requestFinished = isset($this->request['startAt']) ? $response->total < $this->request['startAt'] : false;
	    	$this->responseRaw = array_merge($this->responseRaw, $result);

	    	return $this;
    	} 

    	$this->responseRaw = $result;

		return $this;
    }
}