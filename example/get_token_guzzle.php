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
    'images' => [
        ['src' => 'http://webshopdemo.dk/media/cache/product_info_main_thumb/product-images/6/audio1-1.jpg'],
        ['src' => 'http://webshopdemo.dk/media/cache/product_info_main_thumb/product-images/9/coffee1-1.jpg'],
    ],
];
$response = $apiClient->post('products', ['json' => $productData])
    ->getBody();
$product = json_decode($response, true);
print_r($product);


// update product
$productData = [
    'title' => 'Updated Product',
];
$response = $apiClient->put('products/' . $product['id'], ['json' => $productData]);

$response = $apiClient->get('products/' . $product['id'])
    ->getBody();
$product = json_decode($response, true);
print_r($product);

// get products updated during last 30 minutes
$response = $apiClient->get('products', ['query' => ['updated_from' => '30 minutes']])
    ->getBody();
$products = json_decode($response, true);
print_r($products);

// get products created during last 2 hours
$response = $apiClient->get('products', ['query' => ['created_from' => '2 hours']])
    ->getBody();
$products = json_decode($response, true);
print_r($products);

// get discount groups
$response = $apiClient->get('discountgroups')
    ->getBody();
$discounts = json_decode($response, true);
print_r($discounts);

// create discount group
$discountData = [
    'title' => 'VIP group',
    'comment' => 'vip members'
];
$response = $apiClient->post('discountgroups', ['json' => $discountData])
    ->getBody();
$discount = json_decode($response, true);
print_r($discount);

// delete the first
$response = $apiClient->delete('discountgroups/'. $discounts[0]['id'])
    ->getBody();
$result = json_decode($response, true);
print_r($result);

// update created group
$discountData = [
    'title' => 'super VIP group',
    'comment' => 'only cool dude here'
];
$response = $apiClient->put('discountgroups/' . $discount['id'], ['json' => $discountData])
    ->getBody();
var_dump((string)$response);
$discount = json_decode($response, true);
print_r($discount);
