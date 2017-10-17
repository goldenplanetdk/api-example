# Customer

## Create new Customer

Send POST request to `api/v2/customers.json`:

```json
{
    "email": "john@nowhere.domain",
    "name": "John Smith",
    "customer_type": 2,
    "billing_address": {
        "phone": "+4570209594",
        "mobile": "+4570209594",
        "street": "Dalbygade 40a",
        "zip": "6000"
    }
}
```

You can skip `customer_type`, in this case system will assign default customer type

## Update Customer

Send PUT request to `api/v2/customers/{customer}.json`:

```json
{
        "email": "john.smith@nowhere.domain",
        "billing_address": {
            "phone": "",
            "country": "DNK"
        }
}
```

## Add shipping Address

Send POST request to `api/v2/customers/{customer}/addresses.json`:

```json
{
    "company_name": "GoldenPlanet",
    "street": "Dalbygade 40a",
    "name": "John Doe",
    "city": "Kolding",
    "country": "DNK",
    "zip": "6000"
}
``` 
