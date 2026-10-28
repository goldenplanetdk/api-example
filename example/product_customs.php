<?php
/**
 * Product customs data examples for the OBB REST API.
 *
 * Customs data is the HS code, the national tariff codes per market (EU export, Norway,
 * United Kingdom, United States) and the country of origin of a product. One record per
 * product, addressed by the product id or by model.
 * Demonstrates:
 *   1. creating the record, with codes written in any spacing
 *   2. reading it back, and the 404 a product without customs data gives
 *   3. a partial PUT: change one code, clear another
 *   4. listing them, and the paginated envelope the list comes back in
 *   5. the ways a request gets refused
 *   6. the same record addressed by model instead of id
 *   7. deleting it again
 *
 * See product_customs.md for the reference documentation.
 *
 * These endpoints only exist on v3, while get_token_guzzle.php points $apiClient at /api/v2/,
 * so every call below uses an absolute /api/v3/... path - Guzzle replaces the path and keeps
 * the host.
 *
 * Pick a product that has no customs data yet: the script creates the record and deletes it
 * again at the end, so it is safe to re-run. It does not touch any other product.
 *
 * Not for production use.
 */

require "get_token_guzzle.php";

$productId = 101; // replace with a product id from your shop
$model = '';       // optional: the model of that product or of one of its variants, for section 6
$path = '/api/v3/products/' . $productId . '/customs';

/**
 * Send a request and return [decoded, statusCode], tolerating the 4xx we provoke on purpose.
 */
function send($apiClient, $method, $path, array $body = null) {
	$options = ['http_errors' => false];
	if ($body !== null) {
		$options['json'] = $body;
	}
	$response = $apiClient->request($method, $path, $options);
	return [json_decode($response->getBody(), true), $response->getStatusCode()];
}

/**
 * Print the first validation message per field, so the refusals below are readable.
 *
 * Messages come back in the SHOP's language, not English - a Danish shop answers in Danish.
 * Branch on the field name and the status code, never on the message text.
 */
function printErrors($label, $status, $body) {
	echo str_pad($label, 34) . 'HTTP ' . $status;
	foreach (($body['errors'] ?? []) as $field => $messages) {
		echo '  ' . $field . ': ' . reset($messages);
	}
	echo "\n";
}


/* ---------------------------------------------------------------------------
 * 1. Create
 *
 * Codes are strings. Spaces and dots are ignored, so each market can be written the way
 * its own tariff writes it. Never send a code as a JSON number: the leading zero of a
 * code like 030211 would be lost.
 * ------------------------------------------------------------------------- */

echo "\n\n1. Create\n";

[$customs, $status] = send($apiClient, 'POST', $path, [
	'hs_code' => '610910',
	'eu_code' => '61091000',
	'no_code' => '6109.1000',     // Norway writes 4.4
	'gb_code' => '6109 10 00 10', // the UK writes 4 2 2 2
	'origin_country' => 'PT',     // ISO 3166-1 alpha-2
]);
if ($status !== 201) {
	printErrors('create', $status, $customs);
	exit("Pick a product without customs data, or delete its record first.\n");
}
print_r($customs); // codes come back as plain digits: no_code is "61091000"


/* ---------------------------------------------------------------------------
 * 2. Read
 *
 * 404 has two meanings: the product does not exist, or it has no customs data yet.
 * ------------------------------------------------------------------------- */

echo "\n\n2. Read\n";

[$customs, $status] = send($apiClient, 'GET', $path);
print_r($customs);

[$body, $status] = send($apiClient, 'GET', '/api/v3/products/999999999/customs');
echo 'unknown product: HTTP ' . $status . ' ' . $body['message'] . "\n";


/* ---------------------------------------------------------------------------
 * 3. Partial update
 *
 * PUT changes only the fields sent; the others keep their value. null clears a field.
 * Null fields are omitted from responses, so no_code disappears from the result.
 * ------------------------------------------------------------------------- */

echo "\n\n3. Update\n";

[$customs, $status] = send($apiClient, 'PUT', $path, [
	'us_code' => '6109100012',
	'no_code' => null,
]);
print_r($customs); // hs_code, eu_code, gb_code and origin_country are unchanged


/* ---------------------------------------------------------------------------
 * 4. List
 *
 * Not a bare array: the records are under _embedded.resources. Only products that
 * have customs data are listed.
 * ------------------------------------------------------------------------- */

echo "\n\n4. List\n";

[$list, $status] = send($apiClient, 'GET', '/api/v3/product-customs?page=1&limit=20');
echo 'total: ' . $list['total'] . ', pages: ' . $list['pages'] . "\n";
foreach ($list['_embedded']['resources'] as $row) {
	echo $row['product_id'] . "\t" . ($row['hs_code'] ?? '-') . "\t" . ($row['origin_country'] ?? '-') . "\n";
}


/* ---------------------------------------------------------------------------
 * 5. Refusals - each is a 400 and changes nothing
 * ------------------------------------------------------------------------- */

echo "\n\n5. Refusals\n";

[$body, $status] = send($apiClient, 'PUT', $path, ['hs_code' => '61091']);
printErrors('HS code one digit short', $status, $body);

[$body, $status] = send($apiClient, 'PUT', $path, ['eu_code' => '6109100']);
printErrors('EU code of the wrong length', $status, $body);

[$body, $status] = send($apiClient, 'PUT', $path, ['origin_country' => 'PRT']);
printErrors('3-letter country code', $status, $body);

[$body, $status] = send($apiClient, 'POST', $path, ['hs_code' => '610910']);
printErrors('second record for the product', $status, $body);

[$body, $status] = send($apiClient, 'PUT', $path, ['hs' => '610910']);
printErrors('unknown field', $status, $body);


/* ---------------------------------------------------------------------------
 * 6. By model
 *
 * Shops that use the model as SKU can skip the id: /products/bymodel/{model}/customs
 * takes the model of the product or of any of its variants and reaches the same record.
 * A model that is awkward in a path goes URL-encoded in the query of
 * /products/bymodel/customs instead.
 * ------------------------------------------------------------------------- */

echo "\n\n6. By model\n";

if ($model === '') {
	echo "skipped: set \$model to run it\n";
} else {
	[$customs, $status] = send($apiClient, 'GET', '/api/v3/products/bymodel/' . $model . '/customs');
	echo 'read by model: HTTP ' . $status . ', product ' . $customs['product_id'] . "\n"; // the same $productId

	[$customs, $status] = send($apiClient, 'PUT', '/api/v3/products/bymodel/customs?model=' . rawurlencode($model), [
		'origin_country' => 'BD',
	]);
	echo 'update by model (query form): HTTP ' . $status . ', origin ' . $customs['origin_country'] . "\n";
}

[$body, $status] = send($apiClient, 'GET', '/api/v3/products/bymodel/NO-SUCH-MODEL/customs');
echo 'unknown model: HTTP ' . $status . ' ' . $body['message'] . "\n"; // 404 Product not found


/* ---------------------------------------------------------------------------
 * 7. Delete
 * ------------------------------------------------------------------------- */

echo "\n\n7. Delete\n";

[$body, $status] = send($apiClient, 'DELETE', $path);
echo 'delete: HTTP ' . $status . "\n"; // 204
