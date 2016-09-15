<?php
/**
 * Not for production use
 */
require __DIR__ . '/../vendor/autoload.php';

$shopUrl = 'http://obb.docker'; // obb shop url
$apiUrl = $shopUrl . "/api/v1/";
$clientSecret = 'API_CLIENT_ID'; // API Client Secret from /admin/api-token page
$clientId = 'API_CLIENT_SECRET'; // API Client ID from /admin/api-token page

$credentials = [
    'grant_type' => 'client_credentials',
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
];

$client = new GuzzleHttp\Client(['base_uri' => $apiUrl]);

// get access token
$response = $client->post('token', [
    'form_params' => $credentials,
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

// get two orders for second
$response = $apiClient->get('orders', [
    'query' => ['limit' => 2, 'from' => 2]
]);
$orders = json_decode($response->getBody(), true);
print_r($orders);

// get order by ID
$firstOrderId = $orders[0]['id'];
$response = $apiClient->get('orders/' . $firstOrderId);
$order = json_decode($response->getBody(), true);
print_r($order);
