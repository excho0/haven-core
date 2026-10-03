# HavenCore

HavenCore is a WordPress/WooCommerce plugin that provides supplier operations, fulfillment workflows, supplier/admin messaging, settings management, REST APIs, and a versioned MCP server for AI agents.

> **Status:** Early development  
> **Current version:** 0.0.1  
> **Default development branch:** `dev`

## What HavenCore Provides

- Supplier management:
  - List suppliers
  - Get supplier details
  - Create, update, and delete suppliers
  - Safety checks before deletion
- Supplier order fulfillment:
  - View supplier assignments
  - Reassign suppliers
  - Reset fulfillment state
  - Confirm fulfillment and tracking
- Supplier/admin messaging:
  - Conversations
  - Messages
  - Read/unread state
  - Conversation deletion for administrators
- HavenCore settings:
  - Read and update settings
  - Retrieve the settings schema and field metadata
- WordPress lifecycle management:
  - Creates the Supplier role on activation
  - Creates required plugin pages
  - Creates/updates HavenCore database tables
  - Flushes rewrite rules
- REST API and MCP integration for automation agents

## Requirements

- WordPress
- WooCommerce
- PHP with the extensions required by WordPress, WooCommerce, and the Composer dependencies
- A WordPress user with the appropriate capabilities for the requested operation

The plugin ships with its Composer dependencies in `vendor/`. If you are installing from source and `vendor/autoload.php` is missing, run:

```bash
composer install --no-dev --optimize-autoloader
```

For development, install all dependencies:

```bash
composer install
```

## Installation

### From a ZIP file

The ZIP must contain one plugin directory with `haven-core.php` at its root:

```text
haven-core.zip
└── haven-core/
    ├── haven-core.php
    ├── vendor/
    ├── src/
    ├── include/
    ├── db/
    └── composer.json
```

In WordPress:

1. Go to **Plugins → Add New → Upload Plugin**.
2. Upload `haven-core.zip`.
3. Install and activate the plugin.
4. Confirm that WooCommerce is active.
5. Open **HavenCore → Settings** and review the configuration.

### From Git

```bash
cd wp-content/plugins
git clone https://github.com/excho0/haven-core.git haven-core
cd haven-core
composer install --no-dev --optimize-autoloader
```

Then activate HavenCore from the WordPress admin.

## Activation and Deactivation

When HavenCore is activated, it:

- Creates the `supplier` role if it does not exist.
- Creates the Goodbye, Supplier Portal, Place Order, and Account Security pages when needed.
- Installs HavenCore database tables.
- Publishes the plugin-managed pages.
- Flushes WordPress rewrite rules.

When it is deactivated, the plugin-managed pages are moved to draft and rewrite rules are flushed.

Uninstalling the plugin removes HavenCore settings, the Supplier role, plugin-managed pages, supplier order metadata, and HavenCore database data. Treat uninstall as a destructive operation and back up the site first.

## Project Structure

```text
haven-core/
├── haven-core.php          # WordPress plugin entry point
├── composer.json           # PHP dependencies and autoloading
├── composer.lock           # Locked dependency versions
├── src/                    # Bootstrap, services, views, assets, and integrations
├── include/                # Namespaced PHP classes and MCP implementation
├── db/                     # Database migrations and seeds
├── vendor/                 # Composer dependencies included in distributable builds
├── phinx.php               # Development migration configuration
└── README.md
```

Business logic should live in services and domain classes. REST and MCP handlers should validate input, enforce capabilities, call the appropriate service, and return structured results.

## REST API

HavenCore registers REST API namespaces including:

- `hc/v1`
- `hc/v1/suppliers`
- `hc/v1/communications`
- `hc/v1/customers`

Refer to the implementation in `src/` for the current endpoint contracts and permission callbacks.

## MCP Server

HavenCore exposes a versioned MCP HTTP server.

Default endpoint:

```text
https://YOUR-SITE.example/wp-json/havencore/mcp/v1
```

The namespace can be customized with the `havencore_mcp_rest_namespace` filter.

Internal server metadata:

- Server ID: `havencore-mcp-v1`
- Namespace: `havencore/mcp`
- Route version: `v1`

### Authentication

For development and server-to-server use, authenticate with a WordPress Application Password:

```http
Authorization: Basic BASE64(username:application-password)
```

Use HTTPS in production. Never commit Application Passwords, API keys, or other credentials to the repository.

### MCP Ability Groups

#### Suppliers

- `havencore/suppliers-list`
- `havencore/supplier-get`
- `havencore/supplier-create`
- `havencore/supplier-update`
- `havencore/supplier-delete`

Supplier deletion is protected: a non-force delete is blocked when the supplier has non-fulfilled assigned orders. The failure payload includes the reason and pending fulfillment order IDs.

#### Messaging

- `havencore/conversations-list`
- `havencore/conversation-create`
- `havencore/conversation-messages-list`
- `havencore/conversation-message-create`
- `havencore/conversation-mark-read`
- `havencore/conversation-delete` (administrator)

#### Orders

- `havencore/order-suppliers-get` (administrator)
- `havencore/order-supplier-reassign` (administrator)
- `havencore/order-fulfillment-reset` (administrator)
- `havencore/order-fulfillment-confirm` (supplier)
- `havencore/supplier-assigned-orders-list` (supplier)

#### Settings

- `havencore/settings-get`
- `havencore/settings-update`
- `havencore/settings-schema-get`

Agents should call `settings-schema-get` before changing unfamiliar settings. It provides field-level metadata, defaults, and guidance for supported settings.

## Agent Instructions

This section is intended for AI agents and MCP clients integrating with HavenCore.

### Operating principles

1. **Authenticate first.** Confirm that the current WordPress user has the required role and capabilities.
2. **Use least privilege.** Use supplier-level operations for supplier workflows and administrator-level operations only when necessary.
3. **Read before writing.** Retrieve the current supplier, order, conversation, or setting before changing it.
4. **Validate identifiers.** Confirm supplier IDs, order IDs, conversation IDs, and tracking data before executing a mutation.
5. **Use the settings schema.** Do not guess setting keys or value types.
6. **Prefer structured outputs.** Preserve IDs, statuses, error codes, and pending-action lists in the agent's response.
7. **Explain side effects.** Tell the user when an action changes orders, sends messages, changes settings, or modifies site data.
8. **Confirm destructive actions.** Ask for explicit confirmation before deleting suppliers, deleting conversations, resetting fulfillment, or performing bulk changes.
9. **Do not expose secrets.** Never return Application Passwords, tokens, encrypted settings, or private customer data unless the user is authorized to see it.
10. **Fail safely.** If a precondition fails, stop and report the exact reason instead of forcing the operation.

### Recommended workflows

#### Create or update a supplier

1. Call `havencore/suppliers-list` or `havencore/supplier-get` to check for duplicates.
2. Validate required supplier fields.
3. Call `havencore/supplier-create` or `havencore/supplier-update`.
4. Report the resulting supplier ID and status.
5. If the operation fails, preserve the structured validation errors.

#### Delete a supplier

1. Retrieve the supplier and its assigned orders.
2. Check whether any assigned orders are not fulfilled.
3. If pending fulfillment exists, do not force deletion automatically.
4. Explain the blocking order IDs to the user.
5. Request explicit confirmation before any force-delete option is used.

#### Fulfill an order

1. Retrieve the supplier/order assignment.
2. Confirm that the acting user is authorized for the supplier or administrator workflow.
3. Validate tracking information and fulfillment state.
4. Call the appropriate fulfillment operation.
5. Report the new fulfillment status and any customer notification side effects.

#### Change settings

1. Call `havencore/settings-schema-get`.
2. Call `havencore/settings-get`.
3. Present the proposed change and its effect.
4. Call `havencore/settings-update` only after the value has been validated.
5. Re-read the settings after the update when confirmation is important.

#### Messaging

1. Locate or create the correct conversation.
2. Verify the recipient and relevant order/supplier context.
3. Send only the minimum necessary information.
4. Return the conversation and message IDs.
5. Never include credentials, payment secrets, or unnecessary customer data.

## Permissions

At a high level:

- Administrator tools require capabilities such as `manage_options` and/or `manage_woocommerce`.
- Supplier tools require an authenticated user in the relevant supplier workflow.
- Exact permission checks are defined in the REST and MCP implementation and are authoritative over this document.

## Development

Install dependencies:

```bash
composer install
```

Run database migrations:

```bash
composer migrate
```

Roll back migrations during development:

```bash
composer migrate:rollback
```

Keep database changes in `db/migrations`. Keep MCP abilities grouped by domain and version. Keep outputs machine-readable and backward-compatible where possible.

## Troubleshooting

### MCP tools are not visible

- Confirm the MCP endpoint is correct.
- Confirm the WordPress user is authenticated.
- Confirm the plugin is active.
- Reload the MCP client/session after code changes.
- Confirm the ability is registered in the current MCP registry.

### Composer autoloader error

Verify that `vendor/autoload.php` exists inside the plugin directory. If it does not, run Composer or install a distributable ZIP that includes `vendor/`.

### Authentication or permission error

- Verify the Application Password.
- Verify the WordPress user role.
- Verify the required capability for the requested operation.
- Check whether the operation is administrator-only.

### Route mismatch

The default MCP endpoint is:

```text
/wp-json/havencore/mcp/v1
```

If a custom namespace filter is enabled, use the customized namespace instead.

## Hostinger and AI Agent Operations

For SSH setup, safe deployment, key management, Hostinger paths, MCP configuration, backups, approvals, and maintenance procedures, see [`docs/HOSTINGER_AI_RUNBOOK.md`](docs/HOSTINGER_AI_RUNBOOK.md).\n\nFor Windows Codex setup with Hostinger MCP/API, see [`docs/SETUP_HOSTINGER_MCP_WINDOWS.md`](docs/SETUP_HOSTINGER_MCP_WINDOWS.md) and the credential-free [`docs/HOSTINGER_MCP_CODEX_WINDOWS.toml`](docs/HOSTINGER_MCP_CODEX_WINDOWS.toml) template.

## Release Checklist

Before distributing HavenCore:

- [ ] Test installation on a clean WordPress site.
- [ ] Confirm WooCommerce is active.
- [ ] Confirm `vendor/autoload.php` is included.
- [ ] Test activation, deactivation, and uninstall behavior.
- [ ] Test database installation and upgrade paths.
- [ ] Test administrator and supplier permissions.
- [ ] Test all destructive-operation safeguards.
- [ ] Test the MCP endpoint with an Application Password.
- [ ] Confirm no secrets or private credentials are included.
- [ ] Create a version tag and GitHub Release.
- [ ] Attach a clean plugin ZIP to the Release.
- [ ] Update the version in `haven-core.php`.

## License

HavenCore's original project code is released under the [MIT License](LICENSE). Third-party libraries and bundled assets retain their own licenses; check their notices before redistributing them.

## Maintainer

Maintained by [@excho0](https://github.com/excho0).
