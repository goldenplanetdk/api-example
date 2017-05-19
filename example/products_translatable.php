<?php
require_once "get_token_guzzle.php";

// Can be used for requests which contain "locale" parameter in Requirements e.g: categories, products
$apiClientToTranslatableObjects = new GuzzleHttp\Client([
	'base_uri' => $shopUrl . "/da/api/v1/",
	'headers' => [
		'Authorization' => "Bearer " . $accessToken,
	],
]);

// Create new product with default language
$data = [
	'title' => 'New product'
];
$response = $apiClient->post('products', ['form_params' => $data])
	->getBody();
$product = json_decode($response, true);



// Update product with Danish title
$dataDK = [
	'title' => 'Nyt produkt'
];
$apiClientToTranslatableObjects->put('products/' . $product['id'], ['form_params' => $dataDK]);



// get Product with origin language
$response = $apiClient->get('products/' . $product['id'])
	->getBody();
$product = json_decode($response, true);
echo $product['title']; // "New product" - will be printed



// get Product with Danish language
$response = $apiClientToTranslatableObjects->get('products/' . $product['id'])
	->getBody();
$product = json_decode($response, true);
echo $product['title']; // "Nyt produkt" - will be printed
