<?php
require_once "get_token_guzzle.php";
require "orders.php";

// get Product
$response = $apiClient->get('products/' . $order['line_items'][0]['product_id'])
	->getBody();
$product = json_decode($response, true);
print_r($product);

// create new product
$productData = [
	'title' => 'New Product',
	'images' => [
		['src' => 'http =>//webshopdemo.dk/media/cache/product_info_main_thumb/product-images/6/audio1-1.jpg'],
		['src' => 'http =>//webshopdemo.dk/media/cache/product_info_main_thumb/product-images/9/coffee1-1.jpg'],
	],
	"main_category" => 3,
	"variants" => [
		[
			"is_enabled" => true,
			"allow_no_stock" => false,
			"is_shippable" => true,
			"model" => "variant1S",
			"raw_price" => "+0",
			"weight" => 10,
			"quantity" => 4,
			"special_discount" => 0,
			"attribute_values" => [
				["attributeValue" => 1],
				["attributeValue" => 6]
			]
		],
		[
			"is_enabled" => true,
			"allow_no_stock" => false,
			"is_shippable" => false,
			"model" => "variant2nS",
			"raw_price" => "10",
			"weight" => 10,
			"quantity" => 4,
			"special_discount" => 0,
			"attribute_values" => [
				["attributeValue" => 2],
				["attributeValue" => 7]
			]
		]
	]
];
$response = $apiClient->post('products', ['json' => $productData])
	->getBody();
$product = json_decode($response, true);
print_r($product);


// update product
$productData = [
	'title' => 'Updated Product',
	"variants_template" => "drop_down",
	"variants" => [
		[
			"id" => $product['variants'][0]['id'],
		],
		[
			"id" => $product['variants'][1]['id'],
			"attribute_values" => [
				["attributeValue" => 2],
				["attributeValue" => 8]
			]
		],
		[
			"is_enabled" => true,
			"attribute_values" => [
				["attributeValue" => 1],
				["attributeValue" => 7]
			]
		]
	]
];
$response = $apiClient->put('products/' . $product['id'], ['json' => $productData]);

$response = $apiClient->get('products/' . $product['id'])
	->getBody();
$product = json_decode($response, true);
print_r($product);

// add single product variant
$data = [
	"is_enabled" => true,
	"allow_no_stock" => false,
	"raw_price" => "+0",
	"quantity" => 2,
	"attribute_values" => [
		["attributeValue" => 3],
		["attributeValue" => 6]
	]
];

$response = $apiClient->post('products/' . $product['id'] . '/variants', ['json' => $data])
	->getBody();
$variant = json_encode($response);

// update single product variant
$data = [
	"allow_no_stock" => false,
	"quantity" => 1,
];

$response = $apiClient->put('products/' . $product['id'] . '/variants/' . $variant['id'], ['json' => $data])
	->getBody();

// delete product variant
$response = $apiClient->delete('products/' . $product['id'] . '/variants/' . $variant['id'])
	->getBody();

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
