<?php
require_once "get_token_guzzle.php";

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
