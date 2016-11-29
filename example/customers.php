<?php
require_once "get_token_guzzle.php";

// get Customer
$response = $apiClient->get('customers/' . $order['customer']['id'])
	->getBody();
$customer = json_decode($response, true);
print_r($customer);
