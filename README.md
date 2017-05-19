Openbizbox API 
==============

Shop API documentation: `/api/doc` (e.g. webshopdemo.dk/api/doc)

## Prerequisites

1. Get OAuth2 access token
2. Send request to API with access token

## Request access token
### Access token will be expired after XXX seconds. Check **expires_in** token data field.

API_CLIENT_ID and API_CLIENT_SECRET access data can be found at /admin/api-token in the backend (for instance webshopdemo.dk/admin/api-token). 

Using **bash**
```bash
curl -XPOST  http://SHOP_DOMAIN/oauth/v2/token -d "client_id=CLIENT_ID&client_secret=CLIENT_SECRET&grant_type=client_credentials"
```

**Response**
```json
{
  "access_token": "MmQwZTY1NjRlZDFjY2QzYjUyNWEwNzUyNWQ1ZTQ2ZTAwNWVkYWNmM2IxMDMxMGZjNDJmMzJjYzQwZWZjNzNlZg",
  "expires_in": 3600,
  "token_type": "bearer",
  "scope": "read write"
}
```


Using **php** Guzzle library 
```php
$credentials = [
    'grant_type' => 'client_credentials',
    'client_id' => API_CLIENT_ID,
    'client_secret' => API_CLIENT_SECRET,
];

$client = new GuzzleHttp\Client(['base_uri' => $apiUrl]);
$response = $client->post('token', [ 
    'form_params' => $credentials,
]);
$tokenData = json_decode($response->getBody(), true);

$accessToken = $tokenData['access_token'];
```

## Warning: Request new access token after first one will be expired!!! 

## Request orders list

Using **bash**
```bash
curl -XGET  'http://SHOP_DOMAIN/api/v1/orders?access_token=ACCESS_TOKEN&limit=2&from=2'
```

Using **php** Guzzle library
```php
// create client for API requests
$apiClient = new GuzzleHttp\Client([
    'base_uri' => $apiUrl,
    'headers' => [
        'Authorization' => "Bearer " . $accessToken,
    ],
]);

// get two orders starting from second
$response = $apiClient->get('orders', [
    'query' => ['limit' => 2, 'from' => 2]
]);
$orders = json_decode($response->getBody(), true);
```

## Try **php** example

### Install Composer

`curl -sS https://getcomposer.org/installer | php`

### Install dependencies

`./composer.phar install`

### Run
 
`php ./example/get_token_guzzle.php`
