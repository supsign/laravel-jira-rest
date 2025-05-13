<?php

namespace Supsign\LaravelJiraRest;

use BaseApi;

class JiraRestApi extends BaseApi
{
	protected int $maxResults = 100;

	public function __construct() 
	{
		$this->clientId = env('JIRA_REST_LOGIN');
		$this->clientSecret = env('JIRA_REST_PASSWORD');
		$this->baseUrl = env('JIRA_REST_URL');

		return $this->useBasicAuth();
	}

	public function getIssues($depaginate = true)
	{
		$endpoint = 'search';
		$requestData = [
			'maxResults' => $this->maxResults,
			'jql' => 'ORDER BY updated DESC'
		];

		if ($depaginate) {
			return $this->depaginate($endpoint, $requestData);
		}

		return $this->makeCall($endpoint, $requestData)->issues;
	}

    protected function depaginate(string $endpoint, array|object $requestData = [], string $requestMethod = 'get'): array|object
    {
        $result = parent::makeCall($endpoint, $requestData, $requestMethod);
        $items = $result->issues;
        $requestData['startAt'] = 0;

        while (count($items) < $result->total) {
        	$requestData['startAt'] += $this->maxResults;

        	$items = array_merge(
        		$items,
        		$this->makeCall($endpoint, $requestData, $requestMethod)->issues,
        	);

        }

        return $items;
    }
}