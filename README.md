# HavenCore

Core supplier and admin utilities for WooCommerce, with REST API and MCP tooling for automation agents.

## What This Plugin Provides

- Supplier management (create/update/list/delete with safety checks)
- Supplier order fulfillment workflows
- Supplier/admin messaging
- HavenCore settings management
- MCP server integration for AI/agent tooling

## MCP Overview

HavenCore exposes a versioned MCP server so agents can safely execute operational tools.

Default server route:

`/wp-json/havencore/mcp/v1`

You can customize the namespace via the `havencore_mcp_rest_namespace` filter.

### MCP Architecture

Abilities are grouped by domain:

- `Suppliers`
- `Messaging`
- `Orders`
- `Settings`

Current MCP tree (source of truth):

```text
include/mcp/
├── Abilities/
│   └── V1/
│       ├── Messaging/class-hc-mcp-messaging-abilities-v1-registrar.php
│       ├── Orders/class-hc-mcp-orders-abilities-v1-registrar.php
│       ├── Settings/class-hc-mcp-settings-abilities-v1-registrar.php
│       ├── Suppliers/class-hc-mcp-supplier-abilities-v1-registrar.php
│       └── class-hc-mcp-abilities-v1-registry.php
├── Bootstrap/class-hc-mcp-bootstrap.php
├── Contracts/class-hc-mcp-abilities-v1-registrar-contract.php
├── Servers/V1/class-hc-mcp-havencore-server-v1.php
├── Utils/class-hc-mcp-supplier-payload.php
└── class-hc-mcp-adapter.php
```

Important MCP files:

- `include/mcp/Bootstrap/class-hc-mcp-bootstrap.php`
- `include/mcp/Abilities/V1/class-hc-mcp-abilities-v1-registry.php`
- `include/mcp/Servers/V1/class-hc-mcp-havencore-server-v1.php`

Internal server metadata:

- Server ID: `havencore-mcp-v1`
- Namespace: `havencore/mcp` (default)
- Route: `v1`

## Authentication

For MCP HTTP usage, authenticate with a WordPress user that has the required capabilities.

Recommended for development:

- WordPress Application Password (Basic auth header)

Example header:

`Authorization: Basic base64(username:application-password)`

## Available MCP Tool Groups

All ability IDs use the `havencore/<tool-name>` format.

### Suppliers

- `havencore/suppliers-list`
- `havencore/supplier-get`
- `havencore/supplier-create`
- `havencore/supplier-update`
- `havencore/supplier-delete`

Delete safeguards:

- Non-force delete is blocked when supplier has non-fulfilled assigned orders.
- Failure payload includes:
  - `reason: supplier_orders_not_fulfilled`
  - `pending_fulfillment_order_ids: [...]`

### Messaging

- `havencore/conversations-list`
- `havencore/conversation-create`
- `havencore/conversation-messages-list`
- `havencore/conversation-message-create`
- `havencore/conversation-mark-read`
- `havencore/unread-summary-get`
- `havencore/conversation-delete` (admin)

### Orders

- `havencore/order-suppliers-get` (admin)
- `havencore/order-supplier-reassign` (admin)
- `havencore/order-fulfillment-reset` (admin)
- `havencore/order-fulfillment-confirm` (supplier)
- `havencore/supplier-assigned-orders-list` (supplier)

### Settings

- `havencore/settings-get`
- `havencore/settings-update`
- `havencore/settings-schema-get`

`settings-schema-get` returns field-level metadata to help agents understand what each setting does before updating.

## REST API Namespaces

HavenCore registers REST endpoints under:

- `hc/v1`
- `hc/v1/suppliers`
- `hc/v1/communications`
- `hc/v1/customers`

## Quick Start

1. Install and activate HavenCore plugin.
2. Ensure WooCommerce is active.
3. Configure MCP client to point to:
   - `https://<your-site>/wp-json/havencore/mcp/v1`
4. Add auth headers (Application Password recommended).
5. Reload your MCP client session.
6. Call a simple tool first (example: `havencore/suppliers-list`).

## Capability Model (High Level)

- Admin tools require WordPress admin capabilities (`manage_options` and/or `manage_woocommerce`).
- Supplier tools require authenticated supplier role in relevant flows.

## Development Notes

- Keep business logic in services where possible, not only in REST/MCP handlers.
- Prefer domain registrars for MCP abilities (`Suppliers`, `Messaging`, `Orders`, `Settings`).
- Keep MCP tool outputs structured and machine-readable.

## Troubleshooting

- Tools not visible after code changes:
  - Regenerate autoload if needed: `composer dump-autoload -o`
  - Reload MCP client/session
- Auth failures:
  - Verify user role/capabilities
  - Verify headers and endpoint URL
- Route mismatch:
  - Confirm namespace and route (`/wp-json/havencore/mcp/v1` by default)

## Maintainer

Made by `@excho0`.
