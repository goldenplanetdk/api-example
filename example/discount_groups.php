<?php
require "get_token_guzzle.php";

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

