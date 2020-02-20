# Order

## Create new order

Send request to `api/v2/orders.json`:

```json
{
  "customer": {
    "email": "user@openbizbox.com",
    "name": "Ludvig Forbes"
  },
  "billing_address": {
    "name": "Ludvig Forbes",
    "street": "Dalbygade 40 A",
    "zip": "DK-6000",
    "city": "Kolding",
    "country": "DNK"
  },
  "status": 4,
  "currency": {
    "rate": 1,
    "code": "DNK"
  }
}
```

To create an order with lines:

```json
{
  "customer": {
    "email": "user@openbizbox.com",
    "name": "Ludvig Forbes",
    "customer_type_id": 2,
    "language": "da"
  },
  "billing_address": {
    "name": "Ludvig Forbes",
    "street": "Dalbygade 40 A",
    "zip": "DK-6000",
    "city": "Kolding",
    "country": "DNK"
  },
  "shipping_address": {
    "name": "Ludvig Forbes",
    "street": "Dalbygade 40 A",
    "zip": "DK-6000",
    "city": "Kolding",
    "country": "DNK"
  },
  "status": 5,
  "currency": {
    "rate": 1,
    "code": "DNK"
  },
  "lines": {
    "payment": {
      "title": "cash",
      "price": 100,
      "tax_rate": 0.25,
      "tax_title": "Another VAT"
    },
    "shipping": {
      "title": "flat",
      "price": 100,
      "tax_class_id": 3
    },
    "products": [
        {"product_article_id": 1001}
    ],
    "discounts": [
        {
          "title": "Coupon",
          "price": 50,
          "tax_rate": 0.25,
          "tax_title": "Another VAT"
        }
    ],
    "fees": [
        {
          "title": "low order penalty",
          "price": 20
        }
    ]
  }
}
```

## Update order

To update lines data send  PUT request with such json to `api/v2/orders/<order_id>.json`::
```json
{
  "lines": {
    "products": [
      {"price": 1200},
      {"weight": 0.33, "quantity": 33},
      {}
    ]
  }
}
```
It is important to keep same number of elements in products array, otherwise skipped lines will be removed. In this example the request edits data for 1st and 2nd lines, 3rd line stays as is.

If you need to create order with exact id and/or creation date do like this(works for POST only!):
```json
{
  "id": 777,
  "created_at": "2012-12-12 14:14",
  ...
}
```

### Add new line
Let's imagine you want to add new product line to the order with 2 product lines:
```json
{
  "lines": {
    "products": [
      {},
      {},
      {"product_article_id": 1001, "price": 100}
    ]
  }
}
```

### Remove lines

Let's imagine you have an order with 3 product lines and you need to delete first one. To do this send PUT request with such json:
```json
{
  "lines": {
    "products": {
      "1": {},
      "2": {}
    },
    "shipping": null
  }
}
```
Zero(first) product line will be removed, 2nd and 3rd lines will stay as is. Shipping line will be removed as well.

To remove all lines:
```json
{
  "lines": {
    "products": {}
  }
}
```

## Invoice

Send POST request to `api/v2/orders/<order_id>/invoice.json`:
```json
{
  "notify": true
}
```

Or if you need to generate an invoice with exact id and/or date:
```json
{
  "id": 444,
  "notify": true,
  "created_at": "2012-10-01 12:15"
}
```


## Refund

To cancel full order just send an empty POST request to `api/v2/orders/<order_id>/refunds.json`.
By default order will be re-stock, if you don't want to restock send POST:
```json
{
  "restock": false
}
```

To make partial refund specify line id you want to refund, amount and quantity:

```json
{
  "lines":[
    {
      "order_line_id": 16,
      "amount_with_tax": 1000,
      "quantity": 1
    }
   ]
}
```
You can omit amount and quantity, in this case this line will be fully refunded.

## Response example

```json
{
    "id": 1015,
    "total_with_tax": 2351.54,
    "total": 2317.79,
    "customer": {
        "id": 1511,
        "email": "janeroe@openbizbox.com",
        "name": "Jane Roe",
        "ean": "",
        "vat": "",
        "user_id": "",
        "language": "en",
        "customer_type_id": 1,
        "customer_type_title": "Private",
        "created_at": "-0001-11-30T00:00:00+0100",
        "modified_at": "-0001-11-30T00:00:00+0100"
    },
    "currency": {
        "code": "DKK",
        "rate": 1
    },
    "extra_tracking": [],
    "shipping_address": {
        "name": "Jane Roe",
        "street": "Foobar street, 123",
        "zip": "6660",
        "city": "Wonderlandburg",
        "company_name": "",
        "country": {
            "id": "DNK",
            "title": "Denmark",
            "iso_code2": "DK",
            "iso_code3": "DNK"
        },
        "country_title": "Denmark",
        "state_title": "",
        "created_at": "-0001-11-30T00:00:00+0100",
        "modified_at": "-0001-11-30T00:00:00+0100"
    },
    "billing_address": {
        "name": "Jane Roe",
        "phone": "",
        "mobile": "",
        "street": "Foobar street, 123",
        "zip": "6660",
        "city": "Wonderlandburg",
        "company_name": "",
        "country": {
            "id": "DNK",
            "title": "Denmark",
            "iso_code2": "DK",
            "iso_code3": "DNK"
        },
        "country_title": "Denmark",
        "created_at": "-0001-11-30T00:00:00+0100",
        "modified_at": "-0001-11-30T00:00:00+0100"
    },
    "status": {
        "id": 4,
        "title": "Pending",
        "sort_order": 20,
        "is_cancelled": false,
        "is_default": true,
        "is_pre_payment": false
    },
    "created_at": "2017-06-03T13:07:36+0200",
    "comment": "",
    "ip_address": "192.168.97.102",
    "lines": {
        "products": [
            {
                "price_with_tax": 187.5,
                "order_line_id": 74,
                "title": "512MB miniSD",
                "quantity": 1,
                "price": 150,
                "tax_title": "",
                "tax_rate": 0.25,
                "weight": 0.005,
                "attributes": [],
                "product_id": 14,
                "variant_id": 1014,
                "model": "v512",
                "is_bundled": 1
            },
            {
                "price_with_tax": 0,
                "order_line_id": 75,
                "title": "Bob Marley - Is This Love",
                "quantity": 1,
                "price": 0,
                "tax_title": "",
                "tax_rate": 0.25,
                "weight": 0,
                "attributes": [],
                "product_id": 16,
                "variant_id": 1016,
                "model": "",
                "is_bundled": 1
            },
            {
                "price_with_tax": 2420,
                "order_line_id": 76,
                "title": "Nokia 7373",
                "quantity": 1,
                "price": 2420,
                "tax_title": "",
                "tax_rate": 0,
                "weight": 0.2,
                "attributes": [
                    {
                        "id": 2,
                        "title": "Color",
                        "value": "Black"
                    },
                    {
                        "id": 3,
                        "title": "Battery",
                        "value": "1200 mAh"
                    }
                ],
                "product_id": 9,
                "variant_id": 1023,
                "model": "",
                "is_bundled": 1
            }
        ],
        "discounts": [
            {
                "price_with_tax": -260.75,
                "order_line_id": 78,
                "title": "Customer Loyalty Discount",
                "quantity": 1,
                "price": -257,
                "tax_title": "",
                "tax_rate": 0.0146,
                "weight": 0
            }
        ],
        "payment": {
            "price_with_tax": 3.8,
            "order_line_id": 79,
            "title": "Payment Type Charge (1,45&nbsp;DKK + 0,10%)",
            "quantity": 1,
            "price": 3.8,
            "tax_title": "",
            "tax_rate": 0,
            "weight": 0
        },
        "shipping": {
            "price_with_tax": 0.99,
            "order_line_id": 80,
            "title": "Copy of Flat Rate",
            "quantity": 1,
            "price": 0.99,
            "tax_title": "",
            "tax_rate": 0,
            "weight": 0
        },
        "taxes": [
            {
                "title": "Taxes",
                "rate": 0,
                "amount": 33.75
            }
        ]
    },
    "discount_total": -257,
    "discount_total_with_tax": -260.75,
    "subtotal": 2570,
    "subtotal_with_tax": 2607.5,
    "shipping_data": {
        "method": "flat1",
        "choices": "N;"
    },
    "payment_data": {
        "transaction_id": "46513332"
    }
}
```
