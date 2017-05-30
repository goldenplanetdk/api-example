<?php
require_once "get_token_guzzle.php";

// Create new image for product with id = 1
$productId = 1;
$data = [
	'src' => 'https://d1v1.openbizbox.com/images/banners/slider1-electronics.jpg'
];
$response = $apiClient->post("products/$productId/images", ['json' => $data])
	->getBody();
$image = json_decode($response, true);
$imageId = $image['id'];

//Updating product image by id

//!!! Warning you can't change src for PUT request, if you need another image for product, you should create new one
$newData = [
	'description' => 'Some description'
];
$response = $apiClient->put("products/$productId/images/$imageId.json", ['json' => $newData])
	->getBody();
$updatedImage = json_decode($response, true);


// Receiving image object by id
$response = $apiClient->get("products/$productId/images/$imageId.json")
	->getBody();
$image = json_decode($response, true);
var_dump($image);


//Deleting Image by id
$response = $apiClient->delete("products/$productId/images/$imageId.json")
	->getBody();
