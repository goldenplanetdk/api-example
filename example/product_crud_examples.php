<?php
/**
 * Product create / update examples for the OBB REST API (v2).
 *
 * Demonstrates the request payloads for:
 *   1. a simple product
 *   2. a product with variants
 *   3. a product with bundles (bundled / "buy together" products)
 *   4. a product set (free set with variants, and fixed set)
 *   5. product attribute settings
 *
 * The payloads mirror the API feature tests in
 * core/symfony/src/Openbizbox/ApiBundle/Features/:
 *   - product_api_crud.feature
 *   - product_bundle_api_crud.feature
 *   - product_set_api_crud.feature
 *   - product_attribute_setting_api_crud.feature
 *
 * The numeric ids below (products, articles, attribute values, attribute,
 * category) are example fixture ids - replace them with ids from your shop.
 *
 * Not for production use.
 */

require_once "get_token_guzzle.php"; // provides $apiClient: Guzzle client (Bearer token, base_uri .../api/v2/)

/**
 * Create a product and return the decoded response.
 */
function createProduct($apiClient, array $data) {
	$response = $apiClient->post('products', ['json' => $data])->getBody();
	return json_decode($response, true);
}

/**
 * Update a product by id (PUT only sends the fields you want to change) and
 * return the freshly fetched product.
 */
function updateProduct($apiClient, $id, array $data) {
	$apiClient->put('products/' . $id, ['json' => $data]);
	$response = $apiClient->get('products/' . $id)->getBody();
	return json_decode($response, true);
}


/* ---------------------------------------------------------------------------
 * 1. Simple product
 * ------------------------------------------------------------------------- */

// create
$product = createProduct($apiClient, [
	'title'         => 'Simple product',
	'is_enabled'    => true,
	'raw_price'     => '199.00',
	'model'         => 'SIMPLE-1',
	'main_category' => 3,
]);
print_r($product);

// update
$product = updateProduct($apiClient, $product['id'], [
	'title'     => 'Simple product (renamed)',
	'raw_price' => '149.00',
]);
print_r($product);


/* ---------------------------------------------------------------------------
 * 2. Product with variants
 *    Each variant lists its attribute_values; keep the set of attributes
 *    consistent across variants. On update, send the variant "id" to match an
 *    existing variant (omit "id" to add a new one).
 * ------------------------------------------------------------------------- */

// create
$product = createProduct($apiClient, [
	'title'             => 'Product with variants',
	'is_enabled'        => true,
	'variants_template' => 'drop_down',
	'variants' => [
		[
			'is_enabled'       => true,
			'model'            => 'VAR-S',
			'raw_price'        => '10',
			'weight'           => 10,
			'quantity'         => 4,
			'attribute_values' => [
				['attributeValue' => 1], // e.g. Size
				['attributeValue' => 6], // e.g. Color
			],
		],
		[
			'is_enabled'       => true,
			'model'            => 'VAR-M',
			'raw_price'        => '10',
			'weight'           => 10,
			'quantity'         => 4,
			'attribute_values' => [
				['attributeValue' => 2],
				['attributeValue' => 7],
			],
		],
	],
]);
print_r($product);

// update existing variants by id, and add a third
$product = updateProduct($apiClient, $product['id'], [
	'variants_template' => 'drop_down',
	'variants' => [
		['id' => $product['variants'][0]['id']],
		['id' => $product['variants'][1]['id']],
		[
			'is_enabled'       => true,
			'attribute_values' => [
				['attributeValue' => 1],
				['attributeValue' => 7],
			],
		],
	],
]);
print_r($product);


/* ---------------------------------------------------------------------------
 * 3. Product with bundles
 *    "bundles" is a collection of ProductBundle. Each bundle has an "items"
 *    list (the bundled products). Use this for bundled / "buy together"
 *    products ("is_set" stays false).
 * ------------------------------------------------------------------------- */

// create
$product = createProduct($apiClient, [
	'title'      => 'Product with bundle',
	'is_enabled' => true,
	'bundles' => [
		[
			'is_enabled' => true,
			'title'      => 'Starter bundle',
			'model'      => 'BNDL-1',
			'items' => [
				[
					'product'                   => 1,      // bundled product id
					'article'                   => 1001,   // optional specific variant (article) id
					'quantity'                  => 2,
					'raw_price'                 => '-10%', // absolute (1.99) or relative (-10%)
					'is_limited_to_one_variant' => true,
				],
			],
		],
	],
]);
print_r($product);

// update: replace the bundle contents
$product = updateProduct($apiClient, $product['id'], [
	'bundles' => [
		[
			'title' => 'Updated bundle',
			'items' => [
				['product' => 3, 'quantity' => 1, 'raw_price' => '0'],
			],
		],
	],
]);
print_r($product);


/* ---------------------------------------------------------------------------
 * 4. Product set
 *    A set is a Product with "is_set": true holding exactly one bundle.
 *    The bundle's "is_free" flag selects the flavour:
 *      - free set with variants (is_free=true): per-variant quantities, keyed
 *        by the host product's variant attribute values
 *      - fixed set (is_free=false): fixed contents; "hide_content" hides the
 *        bundled items in cart/checkout
 * ------------------------------------------------------------------------- */

// 4a. free set with variants
$freeSet = createProduct($apiClient, [
	'title'             => 'Free set with variants',
	'is_enabled'        => true,
	'is_set'            => true,
	'variants_template' => 'drop_down',
	'variants' => [
		['is_enabled' => true, 'raw_price' => '10', 'weight' => 1, 'quantity' => 5, 'attribute_values' => [['attributeValue' => 1]]],
		['is_enabled' => true, 'raw_price' => '10', 'weight' => 1, 'quantity' => 5, 'attribute_values' => [['attributeValue' => 2]]],
	],
	'bundles' => [
		[
			'is_free' => true,
			'title'   => 'Variant set',
			'items' => [
				[
					'product'                   => 1,
					'article'                   => 1001,
					'raw_price'                 => '-0%',
					'is_limited_to_one_variant' => false,
					// quantity per host variant: attribute_value => qty
					'quantity_per_variant' => [
						['attribute_value' => 1, 'qty' => 2],
						['attribute_value' => 2, 'qty' => 3],
					],
				],
				[
					'product'                   => 3,
					'raw_price'                 => '-0%',
					'is_limited_to_one_variant' => true,
					'quantity_per_variant' => [
						['attribute_value' => 1, 'qty' => 1],
						['attribute_value' => 2, 'qty' => 4],
					],
				],
			],
		],
	],
]);
print_r($freeSet);

// 4b. fixed set (hide the bundled items in cart/checkout)
$fixedSet = createProduct($apiClient, [
	'title'      => 'Fixed set',
	'is_enabled' => true,
	'is_set'     => true,
	'bundles' => [
		[
			'is_free'      => false,
			'hide_content' => true,
			'title'        => 'Fixed set',
			'items' => [
				['product' => 1, 'quantity' => 3, 'raw_price' => '-10%'],
			],
		],
	],
]);
print_r($fixedSet);

// update a set: rename and swap the single bundle's item
$fixedSet = updateProduct($apiClient, $fixedSet['id'], [
	'is_set' => true,
	'bundles' => [
		[
			'is_free' => false,
			'title'   => 'Fixed set (updated)',
			'items' => [
				['product' => 3, 'quantity' => 2, 'raw_price' => '0'],
			],
		],
	],
]);
print_r($fixedSet);


/* ---------------------------------------------------------------------------
 * 5. Product attribute settings
 *    "attribute_setting" controls per-attribute-value image handling. It is
 *    not exposed in the read response, so set it on create/update only.
 * ------------------------------------------------------------------------- */

// create with an attribute setting
$product = createProduct($apiClient, [
	'title'      => 'Attribute setting product',
	'is_enabled' => true,
	'attribute_setting' => [
		'is_image_per_attribute_value'    => true,
		'is_use_image_attribute_selector' => false
	],
]);
print_r($product);

// update: toggle the flags
$apiClient->put('products/' . $product['id'], ['json' => [
	'attribute_setting' => [
		'is_image_per_attribute_value'    => false,
		'is_use_image_attribute_selector' => true,
	],
]]);
