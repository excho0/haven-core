# HavenCore on Hostinger: AI Agent Runbook

This document explains how an AI agent can safely connect to a Hostinger hosting account, inspect a WordPress installation, deploy HavenCore, and continue development and maintenance.

It is intentionally credential-free. The agent must receive the connection profile and secrets through the runtime environment or a local SSH configuration. Never commit private keys, SSH passwords, WordPress Application Passwords, database passwords, or API tokens.

## 1. Connection profile

Hostinger Web/Cloud hosting commonly uses SSH port `65002`. The authoritative values are the ones shown in **hPanel → Websites → Manage → SSH Access**.

Example local SSH alias:

```sshconfig
Host hostinger-havencore
    HostName HOSTINGER_SSH_HOST
    User HOSTINGER_SSH_USER
    Port 65002
    IdentityFile ~/.ssh/havencore_hostinger_ed25519
    IdentitiesOnly yes
    ServerAliveInterval 30
    ServerAliveCountMax 3
```

Test the connection without changing the server:

```bash
ssh -o BatchMode=yes hostinger-havencore \
  'printf "connected\\n"; pwd; php -v | head -1; composer --version | head -1; wp --version | head -1'
```

If password authentication is used temporarily, do not place the password in shell history, a repository, an agent prompt, or a script.

## 2. SSH key setup

Generate a dedicated key for this hosting account:

```bash
ssh-keygen -t ed25519 \
  -f ~/.ssh/havencore_hostinger_ed25519 \
  -C "havencore-hostinger-deploy"
```

Add only the `.pub` file contents to hPanel under **SSH Access → Add SSH key**. Keep the private key on the operator's machine or in an approved secret manager.

Recommended permissions:

```bash
chmod 700 ~/.ssh
chmod 600 ~/.ssh/havencore_hostinger_ed25519
chmod 644 ~/.ssh/havencore_hostinger_ed25519.pub
```

For an AI agent, the safer design is:

- The agent receives a short-lived or narrowly scoped SSH access mechanism.
- The private key is mounted at runtime, not stored in the repository or ZIP.
- The agent uses `IdentitiesOnly yes` and the dedicated key.
- The key can be revoked from hPanel without changing the application.
- Production changes require an explicit approval step.

An SSH key lets the agent authenticate. It does not automatically grant permission to deploy or change the site. The agent must still follow the operation policy below.

## 3. Discover the WordPress installation

Do not assume the website path. Discover it first:

```bash
pwd
find "$HOME/domains" -maxdepth 4 -type d -name public_html -print
```

A typical Hostinger WordPress path is:

```text
/home/HOSTINGER_USER/domains/example.com/public_html
```

Before changing anything, inspect:

```bash
cd /home/HOSTINGER_USER/domains/example.com/public_html
wp core version
wp plugin list --status=active
php -v
composer --version
df -h .
```

The agent must identify the intended domain and WordPress root explicitly. If multiple domains exist, it must stop and ask for the target rather than choosing one.

## 4. Deploy HavenCore

The production Hostinger copies are intentionally detached from GitHub. Do not configure a GitHub remote, deploy key, webhook, or `git pull` on the production server.

Use a release ZIP and upload each approved version as a new versioned folder, for example:

```text
wp-content/plugins/haven-core-v0.1.0/
```

The uploaded folder must contain `haven-core.php`, `vendor/`, `src/`, `include/`, and `db/`. Run a read-only preflight in the new folder:

```bash
test -f haven-core.php
test -f vendor/autoload.php
php -l haven-core.php
```

After the initial SSH setup, the agent can perform ordinary plugin code deployments automatically. It uploads the new versioned folder over SSH, runs preflight, keeps the current live folder as a recoverable backup, and switches the new folder into the live plugin name `haven-core`. The final folder used by WordPress must still be named `haven-core` unless the plugin activation path is deliberately changed.

Example switch pattern:

```bash
PLUGIN_ROOT=/home/HOSTINGER_USER/domains/example.com/public_html/wp-content/plugins
VERSIONED=haven-core-v0.1.0
mv "$PLUGIN_ROOT/haven-core" "$PLUGIN_ROOT/haven-core-backup-$(date +%Y%m%d-%H%M%S)"
mv "$PLUGIN_ROOT/$VERSIONED" "$PLUGIN_ROOT/haven-core"
```

Do not overwrite the live folder in place before the new version passes preflight.

For local development or staging, Git remains the source-control system. That does not authorize a production server to access the private GitHub repository.

## 5. HavenCore activation side effects

Activation can:

- Create the `supplier` role.
- Create plugin-managed pages.
- Install HavenCore database tables.
- Publish plugin-managed pages.
- Flush rewrite rules.

Deactivation can move plugin-managed pages to draft. Uninstall removes plugin settings, the Supplier role, plugin-managed pages, supplier order metadata, and HavenCore database data.

An AI agent must never run uninstall or a destructive migration as part of a normal deployment.

## 6. MCP configuration

Default MCP endpoint:

```text
https://example.com/wp-json/havencore/mcp/v1
```

The agent should verify the route with an authenticated request appropriate to the MCP client. Do not print the Application Password in logs.

Available domains:

- Suppliers
- Messaging
- Orders
- Settings

Before changing a setting, call:

1. `havencore/settings-schema-get`
2. `havencore/settings-get`
3. `havencore/settings-update`
4. `havencore/settings-get` again when confirmation is important

## 7. Agent operating policy

### Read-only first

The first session should be read-only. It may inspect:

- SSH connectivity
- PHP, Composer, WP-CLI, Git versions
- WordPress root
- Active plugins
- HavenCore version and Git commit
- Git status and remote
- Disk usage
- Backup availability

### Approval required

Require explicit approval before:

- Pulling code into production
- Activating, deactivating, or uninstalling a plugin
- Database migrations
- Changing WordPress settings
- Sending messages or emails
- Updating supplier/order data
- Deleting suppliers, conversations, or order data
- Rotating SSH keys or credentials
- Running recursive deletes or overwrites

### Never do automatically

- Read or print `wp-config.php` secrets.
- Copy private keys off the operator machine.
- Put credentials in Git, ZIP files, shell history, or logs.
- Force-delete a supplier with pending fulfillment.
- Deploy when the working tree contains uncommitted production changes.
- Run `composer update` in production; use the locked dependency set.
- Run `git reset --hard` or destructive cleanup without explicit approval.

### Change protocol

For every write operation, the agent should report:

- Target domain and absolute path
- Current Git branch and commit
- Intended change
- Backup status
- Commands to be executed
- Expected side effects
- Verification steps
- Rollback plan

## 8. Backup and rollback

Before production changes, confirm a recent backup of:

- WordPress files
- WordPress database
- Uploaded media
- HavenCore settings and relevant order data

A safe Git rollback is only valid for code. It does not roll back database migrations or WordPress content. Database rollback requires a tested migration or database backup restore.

## 9. Maintenance checks

Useful read-only checks:

```bash
wp core version
wp plugin status haven-core
wp plugin list --status=active
git -C /path/to/haven-core log -1 --oneline
git -C /path/to/haven-core status --short
php -l /path/to/haven-core/haven-core.php
df -h /path/to/public_html
```

For MCP troubleshooting, check:

- HTTPS and the endpoint URL
- WordPress authentication
- user role and capabilities
- plugin activation
- MCP registry and route namespace
- PHP error logs, without exposing credentials or customer data

## 10. Hostinger-specific notes

- Hostinger Web/Cloud hosting commonly exposes SSH on port `65002`.
- SSH access must be enabled in hPanel before login or key registration.
- The current connection command in hPanel is authoritative for host, username, and port.
- Website files are normally under the domain's `public_html` directory.
- Hostinger provides Git deployment and optional auto-deployment webhooks; use these only after branch protection, backups, and approval policy are in place.
- VPS hosting has a different control model. Do not apply Web/Cloud hosting assumptions to a VPS.

## 11. Release checklist

- [ ] Update the plugin version in `haven-core.php`.
- [ ] Run PHP syntax checks.
- [ ] Run Composer with the lock file.
- [ ] Test on staging.
- [ ] Confirm WooCommerce compatibility.
- [ ] Test activation and upgrade paths.
- [ ] Test MCP authentication and representative read-only abilities.
- [ ] Test permission boundaries.
- [ ] Create a Git tag and GitHub Release.
- [ ] Build a ZIP that includes `vendor/`.
- [ ] Keep credentials outside the repository and release artifact.
