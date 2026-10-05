# API Filtering Documentation

## Introduction

This document outlines the filtering capabilities of our API. Our system uses FIQL (Feed Item Query Language) notation for constructing filter expressions, allowing for precise and flexible querying of resources.

## Basic Filter Syntax

All filter expressions follow a consistent pattern where comparisons are made using field-operator-value combinations. Multiple filters can be combined using logical operators and can be applied to various endpoints such as orders and products.

### Comparison Operators

The API supports a comprehensive set of comparison operators, each designed for specific types of queries:

#### Basic Comparisons
- Equal to: `==`
- Not equal to: `=!=`
- Less than: `=lt=`
- Less than or equal to: `=lte=`
- Greater than: `=gt=`
- Greater than or equal to: `=gte=`

#### Set Operations
- In: `=in=`
- Not in: `=not-in=`

#### Text Operations
- Contains: `=like=` (supported by text fields only)
- Not contains: `=not-like=` (supported by text fields only)

#### Null Checks
- Empty: `=null=`
- Not Empty: `=not-null=`

### Logical Operators

You can create complex queries by combining multiple conditions:

- AND: Use semicolon (`;`) to separate conditions
- OR: Use comma (`,`) to separate conditions
- Grouping: Use parentheses `()` to group OR conditions

## Order Endpoint

Base URL: `/api/v3/orders.json`

### Available Fields

The orders endpoint supports filtering on the following fields:

| Field | Type | Description |
|-------|------|-------------|
| `id` | numeric | Unique order identifier |
| `name` | text | Customer's name |
| `email` | text | Customer's email address |
| `customer` | numeric | Customer's ID |
| `status` | numeric | Order status ID |
| `created_at` | date | Order creation timestamp |
| `modified_at` | date | Last modification timestamp |
| `model` | text | Product model |
| `shipping_method` | numeric | Shipping module ID |

### Example Order Queries

```
# Get orders with ID 1017 or higher
/api/v3/orders.json?filter=id=gte=1017

# Find orders for customers named "Yaroslav"
/api/v3/orders.json?filter=name=like=Yaroslav*

# Complex query combining multiple conditions
/api/v3/orders.json?filter=customer==1507;email==user@openbizbox.com;(name==Angelina Doe,name==John Doe)

# Find orders for specific customers
/api/v3/orders.json?filter=customer=in=(1507,1509)

# Search orders by multiple email addresses
/api/v3/orders.json?filter=email=in=(olekirk@openbibox.com,ud@example.org)
```

## Product Endpoint

Base URL: `/api/v3/products.json`

### Available Fields

The products endpoint supports filtering on the following fields:

| Field | Type | Description |
|-------|------|-------------|
| `id` | numeric | Unique product identifier |
| `model` | text | Product or article model number |
| `mpn` | text | Manufacturer part number |
| `gtin` | text | Global Trade Item Number (barcode) |
| `brand` | mixed | Brand ID or title |
| `supplier` | mixed | Supplier ID or title |
| `created_at` | date | Product creation timestamp |
| `modified_at` | date | Last modification timestamp |
| `category` | numeric | Category ID |

### Example Product Queries

```
# Get products with ID 10 or higher
/api/v3/products.json?filter=id=gte=10

# Search products by model number
/api/v3/products.json?filter=model=like=*512,model=like=acos1*

# Find products not from supplier #1
/api/v3/products.json?filter=supplier=!=1

# Get products from specific categories
/api/v3/products.json?filter=category=in=(1,3)

# Find products from specific brands
/api/v3/products.json?filter=brand=in=(Apple,Nokia)

# List products without categories
/api/v3/products.json?filter=category=null=

# Find products with non-empty model numbers
/api/v3/products.json?filter=model=not-null=
```

## Best Practices

1. **Use Specific Filters**: Always try to use the most specific filter possible to reduce server load and improve response times.

2. **Combine Filters Wisely**: When using multiple filters, consider which logical operator (AND/OR) makes the most sense for your use case.

3. **Handle Special Characters**: When filtering on text fields that might contain special characters, ensure proper URL encoding.

4. **Pagination**: Consider combining filters with pagination parameters for large result sets.

## Error Handling

The API will return appropriate error messages when:
- Invalid operators are used
- Unsupported fields are referenced
- Malformed filter syntax is detected
- Invalid data types are provided for specific fields

Always check the response status code and error messages to ensure your filters are properly formatted and valid.
