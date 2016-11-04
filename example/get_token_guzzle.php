<?php
/**
 * Not for production use
 */
require __DIR__ . '/../vendor/autoload.php';

$config = \Symfony\Component\Yaml\Yaml::parse(file_get_contents(__DIR__ . '/../config/parameters.yml'));

$shopUrl = 'http://' . $config['parameters']['shop_domain']; // obb shop url
$apiUrl = $shopUrl . "/api/v1/";
$tokenUrl = $shopUrl . "/oauth/v2/";
$clientId = $config['parameters']['api_client_id']; // API Client ID from /admin/api-token page
$clientSecret = $config['parameters']['api_client_secret']; // API Client Secret from /admin/api-token page

$credentials = [
    'grant_type' => 'client_credentials',
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
];

$client = new GuzzleHttp\Client(['base_uri' => $tokenUrl]);

// get access token
$response = $client->post('token', [
    'json' => $credentials,
]);
$tokenData = json_decode($response->getBody(), true);
$accessToken = $tokenData['access_token'];
echo 'Access token: ' . $accessToken;

// create client for API requests
$apiClient = new GuzzleHttp\Client([
    'base_uri' => $apiUrl,
    'headers' => [
        'Authorization' => "Bearer " . $accessToken,
    ],
]);
