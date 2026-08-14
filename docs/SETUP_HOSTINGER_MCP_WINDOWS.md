# Hostinger MCP + Codex on Windows

## Recommended approach: OAuth

The current `hostinger-api-mcp` package supports OAuth 2.0 with PKCE when no API token is supplied in stdio mode. This is the preferred option for an interactive development computer because the token is not placed in `config.toml`.

1. Install Node.js 24 or later on the Windows computer.
2. Copy the relevant sections from the Windows Codex configuration template into:
   ```text
   %USERPROFILE%\\.codex\\config.toml
   ```
3. Restart Codex.
4. Invoke a Hostinger tool. A browser login may open automatically.
5. Approve the Hostinger access request.
6. The local OAuth credential is stored by the MCP package under:
   ```text
   %APPDATA%\\hostinger-mcp\\credentials.json
   ```

Do not copy that file into Git, the HavenCore ZIP, or an agent handoff package.

## API token approach

Use this when the computer is unattended, used in CI, or OAuth is not available.

### Create the token in hPanel

1. Sign in to Hostinger hPanel.
2. Open **Dev tools → API**.
3. Choose **Generate API token**.
4. Give it a narrow name, for example `havencore-dev-windows`.
5. Set the shortest practical expiration date.
6. Copy the token immediately. Hostinger indicates it may not be shown again.

Do not paste the token into chat, GitHub, `config.toml`, a ZIP, or a screenshot.

### Store the token in the Windows user environment

Open PowerShell as the same Windows user that runs Codex and execute:

```powershell
$secure = Read-Host "Hostinger API token" -AsSecureString
$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
try {
    $token = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)
    [Environment]::SetEnvironmentVariable("HOSTINGER_API_TOKEN", $token, "User")
}
finally {
    if ($ptr -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)
    }
    Remove-Variable token -ErrorAction SilentlyContinue
}
```

Close and reopen Codex after setting the variable. The MCP server inherits the variable; no token needs to appear in `config.toml`.

Verify only that the variable exists; do not print it:

```powershell
if ([string]::IsNullOrWhiteSpace($env:HOSTINGER_API_TOKEN)) {
    Write-Host "Current PowerShell session does not have the token. Restart Codex/PowerShell."
} else {
    Write-Host "Hostinger token is present in this process environment."
}
```

## Node.js prerequisite

The current package requires Node.js 24 or later:

```powershell
node --version
npx --version
```

## Scope recommendation for HavenCore

Start with:

- `hostinger-hosting-mcp` — hosting and WordPress deployment operations.
- `hostinger-wordpress-mcp` — WordPress-specific operations.

Enable `hostinger-dns-mcp` only when the agent must change DNS. Avoid billing, domains, mail, VPS, and ecommerce servers until explicitly required.

## Safety policy

The Hostinger MCP can perform destructive operations. Require confirmation before deleting websites, overwriting deployments, changing DNS, restoring backups, changing billing/domain settings, or modifying production WordPress.

For HavenCore deployments, first identify the exact domain, inspect the current plugin commit and status, confirm a recent backup, and only then deploy the approved branch or release.

## Token rotation

If a token may have leaked:

1. Revoke it in Hostinger hPanel.
2. Remove `HOSTINGER_API_TOKEN` from the Windows user environment.
3. Generate a replacement with a new name and expiration.
4. Restart Codex.
