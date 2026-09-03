<?php
/**
 * Bulk pricing examples for the OBB REST API.
 *
 * Everything that moves prices in bulk answers 202 with a job rather than a result, so this
 * script is mostly a tour of that one contract. Demonstrates:
 *   1. polling a job to completion
 *   2. mass updating the selling price, and the cost price
 *   3. that retrying an identical request folds into the same job
 *   4. that an empty selection is refused
 *   5. creating a pricing campaign, applying it, removing it, deleting it
 *   6. removing special prices with no campaign involved
 *   7. tier price presets, and attaching / detaching them in bulk
 *
 * See bulk_pricing.md for the reference documentation.
 *
 * These endpoints only exist on v3, while get_token_guzzle.php points $apiClient at /api/v2/,
 * so every call below uses an absolute /api/v3/... path - Guzzle replaces the path and keeps
 * the host.
 *
 * WARNING: this script really does change prices. Point it at a test shop, and set the ids
 * below to something you do not mind repricing.
 *
 * Not for production use.
 */

require "get_token_guzzle.php";

$categoryId = 12; // replace with a category id from your shop
$brandId    = 3;  // replace with a brand id from your shop

/**
 * Poll a job until it leaves the queued/running states, then print and return it.
 *
 * The fast paths finish during the request itself, so the first read is usually already
 * "done" - the loop is here for the catalogue-wide case that runs in the background.
 */
function awaitJob($apiClient, $jobId, $label) {
	for ($attempt = 0; $attempt < 30; $attempt++) {
		$job = json_decode($apiClient->get('/api/v3/jobs/' . $jobId . '.json')->getBody(), true);
		if (!in_array($job['status'], ['queued', 'running'], true)) {
			break;
		}
		sleep(2);
	}
	echo str_pad($label, 46)
		. $job['status']
		. ' ' . json_encode($job['status'] === 'failed' ? $job['error'] : $job['result'])
		. "\n";
	return $job;
}

/**
 * Send a bulk request and wait for the job it returns. Returns [job, statusCode].
 *
 * 202 means this call started the work; 200 means an identical request was already in
 * flight and we were handed that job instead.
 */
function runBulk($apiClient, $method, $path, array $body, $label) {
	$response = $apiClient->request($method, $path, ['json' => $body]);
	$job = json_decode($response->getBody(), true);
	return [awaitJob($apiClient, $job['id'], $label), $response->getStatusCode()];
}


/* ---------------------------------------------------------------------------
 * 1. Mass update the selling price
 *    "+7.5%" raises; rounding applies only to percentage adjustments.
 * ------------------------------------------------------------------------- */

list($job) = runBulk($apiClient, 'POST', '/api/v3/products/bulk/price.json', [
	'target' => 'price',
	'price' => '+7.5%',
	'rounding' => 'nearest',
	'categories' => [$categoryId],
	'is_include_sub_categories' => true,
], 'raise selling price 7.5%');

// The job carries both counts: products, and the variants that hold an absolute price
// of their own. Variants with a relative price (+20, -10%) follow their product instead.
echo '  products: ' . $job['result']['products'] . ', variants: ' . $job['result']['variants'] . "\n";


/* ---------------------------------------------------------------------------
 * 2. Retrying an identical request is safe
 *    Same client, same endpoint, same payload while the job is still fresh gives
 *    200 and the SAME job back - the increase is not applied a second time.
 * ------------------------------------------------------------------------- */

list($retried, $status) = runBulk($apiClient, 'POST', '/api/v3/products/bulk/price.json', [
	'target' => 'price',
	'price' => '+7.5%',
	'rounding' => 'nearest',
	'categories' => [$categoryId],
	'is_include_sub_categories' => true,
], 'same request again');

echo '  HTTP ' . $status . ', job id ' . $retried['id']
	. ($retried['id'] === $job['id'] ? " (same job - nothing applied twice)\n" : " (new job)\n");


/* ---------------------------------------------------------------------------
 * 3. Mass update the cost price
 *    target=costPrice writes what you buy at, not what you sell at.
 * ------------------------------------------------------------------------- */

runBulk($apiClient, 'POST', '/api/v3/products/bulk/price.json', [
	'target' => 'costPrice',
	'price' => '42',
	'brands' => [$brandId],
], 'fixed cost price on one brand');


/* ---------------------------------------------------------------------------
 * 4. An empty selection is refused
 *    In the admin a blank form means "the whole catalogue". Over the API you have
 *    to say so explicitly with apply_to_all, or you get a 400.
 * ------------------------------------------------------------------------- */

try {
	$apiClient->post('/api/v3/products/bulk/price.json', [
		'json' => ['target' => 'price', 'price' => '+1%'],
	]);
	echo "empty selection was accepted - unexpected\n";
} catch (GuzzleHttp\Exception\ClientException $e) {
	echo str_pad('empty selection', 46) . 'HTTP ' . $e->getResponse()->getStatusCode() . " as expected\n";
}

// The same request with the confirmation is accepted. Commented out, because it would
// reprice every product in the shop:
// runBulk($apiClient, 'POST', '/api/v3/products/bulk/price.json',
//     ['target' => 'price', 'price' => '+1%', 'apply_to_all' => true], 'whole catalogue');


/* ---------------------------------------------------------------------------
 * 5. A pricing campaign
 *    Creating one does NOT touch prices - unlike the admin, which applies a
 *    campaign immediately when it starts today. Apply is a separate call.
 * ------------------------------------------------------------------------- */

$campaign = json_decode($apiClient->post('/api/v3/specialcampaigns.json', [
	'json' => [
		'title' => 'API autumn sale',
		'date_start' => date('Y-m-d'),
		'date_end' => date('Y-m-d', strtotime('+30 days')),
		'discount' => '-20%',
		'categories' => [$categoryId],
		'is_include_sub_categories' => true,
		'is_keep_better_special' => true,
	],
])->getBody(), true);

echo str_pad('campaign created', 46) . 'id ' . $campaign['id'] . ', status ' . $campaign['status'] . "\n";

// Now apply it. This is the call that changes prices.
runBulk($apiClient, 'POST', '/api/v3/specialcampaigns/' . $campaign['id'] . '/apply.json', [], 'campaign applied');

// Remove puts the prices back but KEEPS the campaign, so it can be applied again.
runBulk($apiClient, 'POST', '/api/v3/specialcampaigns/' . $campaign['id'] . '/remove.json', [], 'campaign removed (kept)');

// Filtering the list by derived status: upcoming | running | expired.
$running = json_decode($apiClient->get('/api/v3/specialcampaigns.json?status=running')->getBody(), true);
echo str_pad('running campaigns', 46) . $running['total'] . "\n";

// DELETE unwinds the prices and deletes the campaign - so it is a job too, not a 204.
runBulk($apiClient, 'DELETE', '/api/v3/specialcampaigns/' . $campaign['id'] . '.json', [], 'campaign deleted');


/* ---------------------------------------------------------------------------
 * 6. Remove special prices with no campaign involved
 * ------------------------------------------------------------------------- */

runBulk($apiClient, 'POST', '/api/v3/specialcampaigns/bulk/remove.json', [
	'brands' => [$brandId],
	'is_keep_former_special' => true,
], 'ad-hoc special removal');


/* ---------------------------------------------------------------------------
 * 7. Tier price presets
 *    A named set of quantity breaks, attached to many products at once.
 * ------------------------------------------------------------------------- */

$preset = json_decode($apiClient->post('/api/v3/tierpricepresets.json', [
	'json' => [
		'title' => 'API wholesale 10/50/100',
		'ranges' => [
			['quantity' => 10, 'price' => '-5%'],
			['quantity' => 50, 'price' => '-12%'],
			['quantity' => 100, 'price' => '[costprice]+15%'],
		],
	],
])->getBody(), true);

echo str_pad('preset created', 46) . 'id ' . $preset['id'] . ', ' . count($preset['ranges']) . " ranges\n";

// Quantities must be unique within a preset - a duplicate is a 400, not a dropped row.
try {
	$apiClient->post('/api/v3/tierpricepresets.json', [
		'json' => [
			'title' => 'API duplicate quantities',
			'ranges' => [['quantity' => 10, 'price' => '-5%'], ['quantity' => 10, 'price' => '-6%']],
		],
	]);
	echo "duplicate quantity was accepted - unexpected\n";
} catch (GuzzleHttp\Exception\ClientException $e) {
	echo str_pad('duplicate quantity', 46) . 'HTTP ' . $e->getResponse()->getStatusCode() . " as expected\n";
}

// Attach it to a category, then detach whatever preset those products carry.
// Note that bulk remove takes no "preset" - sending one is a 400.
runBulk($apiClient, 'POST', '/api/v3/tierpricepresets/bulk/apply.json', [
	'preset' => $preset['id'],
	'categories' => [$categoryId],
	'is_include_sub_categories' => true,
], 'preset attached');

runBulk($apiClient, 'POST', '/api/v3/tierpricepresets/bulk/remove.json', [
	'categories' => [$categoryId],
	'is_include_sub_categories' => true,
], 'preset detached');

$apiClient->delete('/api/v3/tierpricepresets/' . $preset['id'] . '.json');


/* ---------------------------------------------------------------------------
 * 8. Your own job history, newest first
 *    You only ever see jobs your own API client created - someone else's id is a
 *    404, the same as an id that does not exist.
 * ------------------------------------------------------------------------- */

$jobs = json_decode($apiClient->get('/api/v3/jobs.json?limit=5')->getBody(), true);
foreach ($jobs['_embedded']['resources'] as $recent) {
	echo str_pad('  job ' . $recent['id'], 46) . $recent['type'] . ' ' . $recent['status'] . "\n";
}
