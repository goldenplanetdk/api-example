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

// get two orders
$response = $apiClient->get('orders', [
    'query' => ['limit' => 2, 'from' => 0]
]);
$orders = json_decode($response->getBody(), true);
print_r($orders);

$latestOrder = array_pop($orders);
// get Order by ID
$response = $apiClient->get('orders/' . $latestOrder['id']);
$order = json_decode($response->getBody(), true);
print_r($order);

// get Customer
$response = $apiClient->get('customers/' . $order['customer']['id'])
    ->getBody();
$customer = json_decode($response, true);
print_r($customer);

// get Product
$response = $apiClient->get('products/' . $order['line_items'][0]['product_id'])
    ->getBody();
$product = json_decode($response, true);
print_r($product);

// create new product
$productData = [
    'title' => 'New Product',
];
$response = $apiClient->post('products', ['form_params' => $productData])
    ->getBody();
$product = json_decode($response, true);
print_r($product);


// update product
$productData = [
    'title' => 'Updated Product',
];
$response = $apiClient->put('products/' . $product['id'], ['form_params' => $productData]);

$response = $apiClient->get('products/' . $product['id'])
    ->getBody();
$product = json_decode($response, true);
print_r($product);
