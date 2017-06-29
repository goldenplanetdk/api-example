## Order

To create an order:
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
Zero(first) line will be removed, 2nd and 3rd lines will stay as is.

To update lines data use PUT request with such json:
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

To remove lines:
```json
{
  "lines": {
    "products": {}
  }
}
```

## Invoice

```json
{
  "notify": true
}
```

Or if need exact id and/or date:
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