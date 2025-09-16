<?php

namespace Supsign\LaravelJiraRest;

use BaseApi;

class JiraRestApi extends BaseApi
{
	protected int $maxResults = 5000;

	public function __construct() 
	{
		$this->clientId = env('JIRA_REST_LOGIN');
		$this->clientSecret = env('JIRA_REST_PASSWORD');
		$this->baseUrl = env('JIRA_REST_URL');

		return $this->useBasicAuth();
	}

	public function getIssues($depaginate = true): array
	{
		$endpoint = 'search/jql';
		$requestData = [
			'fields' => '*all',
			'nextPageToken' => null,
			'maxResults' => $this->maxResults,
			'jql' => 'priority >= Lowest ORDER BY updated DESC'
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

        while (!empty($result->nextPageToken)) {
        	$requestData['nextPageToken'] = $result->nextPageToken;
        	$result = $this->makeCall($endpoint, $requestData, $requestMethod);

        	$items = array_merge(
        		$items,
        		$result->issues,
        	);
        }

        return $items;
    }
}