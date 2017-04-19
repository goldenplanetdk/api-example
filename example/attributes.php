<?php
require "get_token_guzzle.php";

// create new attribute with attribute values
$data = [
	"is_filterable" => false,
	"display_zoom" => true,
	"sorting" => 200,
	"title" => "Test attribute",
	"internal_title" => "My test attribute",
	"values" => [
		["sorting" => 20, "title" => "First"],
		["sorting" => 30, "title" => "Second"]
	]
];
/** @var Api $response */
$response = $apiClient->post('attributes', ['json' => $data])
	->getBody();
$attribute = json_decode($response, true);

// add attribute value to attribute
$data = [
	"sorting" => 40,
	"title" => "Third"
];

$response = $apiClient->post('attributes/' . $attribute['id'] . '/values', ['json' => $data])
	->getBody();
$attributeValue = json_decode($response, true);

// update existing attribute value
$data = [
	"sorting" => 40,
	"title" => "Third updated"
];

$response = $apiClient->put('attributes/' . $attribute['id'] . '/values/' . $attributeValue['id'], ['json' => $data])
	->getBody();


// delete attribute value
$response = $apiClient->delete('attributes/' . $attribute['id'] . '/values/' . $attributeValue['id'])
	->getBody();

// delete attribute
$response = $apiClient->delete('attributes/' . $attribute['id'])
	->getBody();

