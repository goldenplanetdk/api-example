# Coupons

## Listing

Send GET request to `api/v3/coupons.json`
You should get response like this:
```json
{
    "total": 1,
    "page": 1,
    "limit": 100,
    "pages": 1,
    "_embedded": {
        "rel": "coupons",
        "resources": [
            {
                "is_enabled": true,
                "title": "Summer Sale",
                "created_at": "2000-11-30T00:00:00+01:00",
                "id": 1,
                "priority": 0,
                "starts_at": "2013-05-07T00:00:00+02:00",
                "limit": 5,
                "limit_per_customer": 1,
                "usage_count": 1,
                "code": "summer 195",
                "discount": "195",
                "_links": {
                    "customers": {
                        "href": "/api/v3/coupons/1/customers"
                    }
                }
            }
        ]
    }
}
```

To see the list of customers used that coupon use endpoint `/api/v3/coupons/1/customers`


## Create new coupon

Send POST request to `api/v3/coupons.json`

```json
{
      "title": "test coupon",
      "code": "SALE20FROM_FB",
      "limit": "3",
      "is_enabled": true,
      "starts_at": "2019-01-01"
    }
```
