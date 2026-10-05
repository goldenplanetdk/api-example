<?php
require_once "get_token_guzzle.php";

// get all current carts
$response = $apiClient->get('carts');

$carts = json_decode($response->getBody(), true);
print_r($carts);

$latestCart = array_pop($carts);
// get Cart by ID
$response = $apiClient->get('carts/' . $latestCart['id']);
$cart = json_decode($response->getBody(), true);
print_r($cart);


// get Abandoned Carts
$response = $apiClient->get('carts/abandoned');
$carts = json_decode($response->getBody(), true);
print_r($carts);
