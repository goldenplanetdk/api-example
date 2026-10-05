<?php
require_once "get_token_guzzle.php";

/**
 * Create webhook
 */
$response = $apiClient->get('webhooks');
$webhooks = json_decode($response->getBody(), true);
print_r($webhooks);

$webhookUrl = 'https://putsreq.com/tfU95WXsyOogSTSYjMdT';
$webhook = [
    'url' => $webhookUrl,
    'event_name' => 'order.created',
];

$response = $apiClient->post(
    'webhooks',
    [
        'json' => $webhook
    ]
);
$response = json_decode($response->getBody(), true);
print_r($response);
