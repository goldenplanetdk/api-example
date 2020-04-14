<?php

/**
 * read data from PDST
 */

$payload = file_get_contents('php://input');
/**
 * Headers structure
 * {
 *  "CONTENT-TYPE": "application/json",
 *  "X-OBB-EVENT": "order.created",
 *  "X-OBB-EVENT-ID": "16",
 *  "X-OBB-SIGNATURE": "5afa0cba2be988c81a90bcef72b84ed8ec97efb35bd5bb83972fa025f24284e5",
 *  "X-OBB-CREATED-AT": "2020-04-14 09:28:06.957600",
 *  "X-OBB-RETRY": "0"
 *  }
 */
$headers = getallheaders();

$secret = '#WEBHOOK_SECRET#'; // from backend /admin/webhooks/
/**
 * validate payload
 */
$hash = hash_hmac('sha256', $payload, $secret);
$signature = $headers['X-OBB-SIGNATURE'];
if ($hash !== $signature) {
    throw new DomainException('Signature is wrong ');
}

$order = json_decode($payload, true); // the same structure as for order in API v2
print_r($order);
