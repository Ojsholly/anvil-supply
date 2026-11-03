# Anvil Supply — Laravel MCP Build-Along

Companion repo for the Inside The Build video **"I Built an MCP Server in
Laravel" (Build Log #1)** — https://youtube.com/@InsideTheBuildHQ

Anvil Supply Co. is a fictional industrial-supply store backend: customers,
products, orders, order items, and refunds — seeded with deliberately messy
data (two James Carters, a Sarah O'Brien with and without the apostrophe,
cancelled/refunded/unshipped orders) so a real AI agent has to disambiguate.

The app is wired to a **read-only MCP server** built with Laravel's official
[`laravel/mcp`](https://github.com/laravel/mcp) package, so an agent
(Claude Code, Cursor, Antigravity, MCP Inspector) can query the store over
JSON-RPC.

## Branches

| branch | what it is |
|---|---|
| `starter` | the app **before** any MCP work — clone this to build along |
| `main` | the finished state after all merged build-log PRs |

Each build-log part lands as a pull request whose commits are the steps:

- **PR #1 `add-mcp-server`** — package install → server class + registration →
  four read tools → the `#[Name]` fix → `get-top-customers`. Follow the commit
  list to reproduce the video step by step (including the real naming failure
  and its fix).

## Setup (from `starter`)

Requires PHP 8.4+ and Composer. SQLite — no other services.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
```

## Running the MCP server (from `main` / after the PR)

```bash
php artisan mcp:start anvil-supply
```

Point an agent's MCP config at it:

```json
"anvil-supply": {
  "type": "stdio",
  "command": "php",
  "args": ["/absolute/path/to/anvil-supply/artisan", "mcp:start", "anvil-supply"]
}
```

(On macOS + Laravel Herd, `command` is the Herd PHP binary, e.g.
`~/Library/Application Support/Herd/bin/php84`.)

**Inspector:** `php artisan mcp:inspector` is broken on `laravel/mcp` v1.1.2 —
it omits the required `--server` flag. Run the Inspector directly instead:

```bash
npx @modelcontextprotocol/inspector \
  php /absolute/path/to/artisan mcp:start anvil-supply
```

## The tools (all read-only in Part 1)

| tool | what it does |
|---|---|
| `search-orders` | find orders by number, customer name, or status |
| `get-order` | full detail of one order — items, totals, refunds |
| `get-customer` | customer record + order history (disambiguates names) |
| `check-inventory` | product stock lookup by SKU or name |
| `get-top-customers` | spend leaderboard — gross vs net (cancelled/refunded excluded) |

The server's `#[Instructions]` warn every connecting agent that names are NOT
unique — the seed data is built to test that.
