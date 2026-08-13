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
