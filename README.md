# Usage

Blueprint extension for Pterodactyl that adds a **Usage** tab to the admin sidebar.

The page shows combined RAM, CPU, and disk across every node:

- **Allocated vs total** — how much of each resource has been assigned to servers
- **Live used vs total** — how much Wings reports servers are actually consuming right now
- Extra panel stats: node online/offline counts, servers, users, allocations, locations, Wings versions
- Per-node breakdown table

## Install

1. Download `usage.blueprint` from the latest version in the [releases tab](https://github.com/MineFlare/usage/releases), and copy it into your Pterodactyl directory (usually `/var/www/pterodactyl`).
2. Run:

```bash
cd /var/www/pterodactyl
blueprint -install usage
```

3. Open **Admin → Usage** (new sidebar item under Overview), or go to `/admin/extensions/usage`.

## How the numbers work

| Resource | Total | Allocated | Live used |
| --- | --- | --- | --- |
| Memory | Sum of each node's configured memory | Sum of every server's memory limit | Sum of Wings `memory_bytes` for servers on that node |
| Disk | Sum of each node's configured disk | Sum of every server's disk limit | Sum of Wings `disk_bytes` |
| CPU | Online Wings thread count × 100 | Sum of every server's CPU limit (`100` = 1 thread) | Sum of Wings `cpu_absolute` |

CPU total only includes nodes that Wings can reach. Offline nodes still count toward memory/disk totals from the panel database.

Live used values stay at zero for a node if Wings is offline or `/api/servers` cannot be queried.

## Files

- `conf.yml` — extension metadata
- `controller.php` — gathers panel + Wings stats
- `view.blade.php` — admin Usage dashboard
- `wrapper.blade.php` — injects the sidebar tab
- `admin.css` — layout extras
- `icon.png` — extension icon

## Uninstall

```bash
blueprint -remove usage
```
