# magento2-orders-graphql

[![Build Status](https://dev.azure.com/graycore/open-source/_apis/build/status/graycoreio.magento2-orders-graphql?branchName=master)](https://dev.azure.com/graycore/open-source/_build/latest?definitionId=17&branchName=master)

A Magento 2 module that adds a fully featured GraphQL orders endpoint.

Note that this is scheduled to be obsolete and deprecated in Magento 2.4.1 given that an officially maintained GraphQL order module will be released.

## Installation

```sh
composer require graycore/magento2-orders-graphql
```

## Usage

### Guest Orders

For guest carts, use the `graycoreGuestOrders` query and pass in the cart ID as `cartId`:

```gql
query GetGuestOrders {
  graycoreGuestOrders(cartId: "dsfg67dsfg65sd6fgs8dhffdgs") {
    orders {
      id
    }
  }
}
```

### Customer Orders

For customer carts, use the `graycoreCustomerOrders` query. There is an optional `orderNumber` parameter which will return a specific order. Not passing `orderNumber` will return all of the authenticated customer's orders. Authenticate the customer according to normal Magento procedures.

```gql
query GetCustomerOrders {
  graycoreCustomerOrders(
    orderNumber: "0000000001"
  ) {
    orders {
      id
    }
  }
}
```

### Schema

Refer to the [GraphQL schema](etc/schema.graphqls) for documentation about the types available in the queries.
