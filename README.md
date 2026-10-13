# QUIQQER Customer

![QUIQQER Customer](bin/images/Readme.png)

Customer management for QUIQQER. The module provides a dedicated customer group and streamlined tools for creating, finding, and maintaining customer accounts outside the general user administration.

## Features

- Create and manage customer accounts and addresses
- Assign users to the configured customer group
- Search customers by customer number, name, company, address, or contact data
- Maintain comments and customer history
- Manage customer files and optional frontend downloads
- Display and export open-item lists for ERP customers

## Requirements

- PHP 8.2 or newer
- QUIQQER Core 2.25 or newer
- QUIQQER ERP 3.2 or 4.0.1 or newer
- QUIQQER Payment Transactions 2.x
- QUIQQER Backend Search 2.1 or newer

## Installation

```bash
composer require quiqqer/customer
```

Optional integrations are available for `quiqqer/user-downloads` and `quiqqer/erp-demo-data`.

## MCP

Install `quiqqer/ai-mcp` to expose the customer tools and run QUIQQER setup after
installing or updating this package. See the [MCP developer documentation](https://www.quiqqer.com/docs/ai/developers).

Grant the dedicated `quiqqer.customer.mcp` permission to the AI account. Tools also
check the corresponding `quiqqer.customer.view`, `.create` or `.edit` permission.
Creation and editing retain the normal Core user permissions
(`quiqqer.admin.users.create` and `quiqqer.admin.users.edit`).

| Tool | Operation |
| --- | --- |
| `quiqqer_customer_options` | List language/country codes and optional payment/shipping IDs |
| `quiqqer_customer_search` | Search the configured customer group, with `query`, `limit` and `offset` |
| `quiqqer_customer_get` | Read by `customerId` (user UUID/ID) or `customerNo` (exact number, optionally prefixed) |
| `quiqqer_customer_create` | Create a customer with `data`; omit `customerNo` to use the number range |
| `quiqqer_customer_update` | Patch a customer by `customerId` and `data` |
| `quiqqer_customer_comments_list` | Paginated ERP comments, newest first, as plain text |
| `quiqqer_customer_history_list` | Paginated ERP customer history, newest first |
| `quiqqer_customer_comment_add` | Add a plain-text comment |
| `quiqqer_customer_comment_update` | Edit a customer-module comment by `commentId` |

The `data` object supports `customerNo`, `address`, `deliveryAddress`, `contactPerson`,
`paymentTerm` (days), `language`, `website`, `referenceCode`, `vatId`, `taxId`, `chUid`,
`companyNumber`, `pricingMode` (0=automatic, 1=net, 2=gross), `discount` (percent),
`paymentMethod` and `shippingType` (existing IDs; null clears the selection).
Payment and shipping selections require their respective optional packages.
Language and country codes must exist in the installation; websites accept HTTP(S)
URLs or hostnames without embedded credentials. Use `quiqqer_customer_options` to
discover available values.
Addresses support name/company/address fields, `mails` and `phones` (objects with
`type` = `tel`, `mobile` or `fax`, and `no`). Supplied communication lists replace
the existing list; omitted fields remain unchanged. `paymentTerm: null` restores
the ERP default. `contactPerson: null` clears the assignment. A contact's `addressId`
must belong to the customer; without it, the current contact is updated or created.

Unknown fields, incorrect types, invalid email addresses and out-of-range values
are rejected before writing. Tools expose only customers in the configured customer
group and never accept arbitrary user attributes or group/permission changes.
Comment writes require `quiqqer.customer.editComments` and Core user edit rights.
History is read-only; comments from other ERP modules retain their owning module.

## Development

Install the PHIVE-managed tools and hooks:

```bash
composer dev:init
```

Run static analysis, coding-style checks, and the PHPUnit suite:

```bash
composer test
```

## Contributing

- [Issue tracker](https://dev.quiqqer.com/quiqqer/customer/-/issues)
- [Source code](https://dev.quiqqer.com/quiqqer/customer/-/tree/main)

## Support

For questions, defects, and feature requests, contact [support@quiqqer.com](mailto:support@quiqqer.com).

## License

GPL-3.0-or-later. See [LICENSE](LICENSE).
