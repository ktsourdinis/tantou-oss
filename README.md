# ⚡ Tantou (担当) — The Open-Core Human Capacity OS

> **Stop burning out your team with infinite Kanban lists. Manage real human workload (`W1–W5`), enforce visibility firewalls, and execute requests in real time via Web & Telegram.**

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Laravel 11](https://img.shields.io/badge/Laravel-11-FF2D20.svg?logo=laravel)](https://laravel.com)
[![Livewire 3](https://img.shields.io/badge/Livewire-3-FB70A9.svg?logo=livewire)](https://livewire.laravel.com)
[![Reverb WebSockets](https://img.shields.io/badge/Reverb-WebSockets-6366F1.svg)](https://laravel.com/docs/reverb)
[![Docker Ready](https://img.shields.io/badge/Docker-Ready-2496ED.svg?logo=docker)](docker-compose.yml)
[![PRs Welcome](https://img.shields.io/badge/PRs-Welcome-brightgreen.svg)](https://github.com/ktsourdinis/tantou-oss/pulls)

---

### Quick Navigation
[⚡ Live Demo](#-live-interactive-demo) • [📖 User & Admin Guide](docs/USER_AND_ADMIN_GUIDE.md) • [🧠 Why Tantou?](#-why-tantou-the-philosophy) • [🚀 Key Features](#-key-features) • [🐳 60-Second Docker Quickstart](#-60-second-docker-quickstart) • [🌐 Intranet IP vs Domain Routing](#-intranet-ip-vs-domain-routing) • [✈️ 2-Way Telegram Bot](#️-2-way-telegram-bot-webhook--intranet-polling) • [⚖️ Open-Core vs Cloud](#-open-core-community-vs-managed-cloud) • [🛠️ Tech Stack & Tests](#️-tech-stack-testing--contributing)

> [!TIP]
> **Complete Operational & Deployment Manual Available:**
> Check out the **[Complete User & Administrator Guide](docs/USER_AND_ADMIN_GUIDE.md)** covering Caddy + Docker deployment, the 4 operational models (Corporate IT, Hospital/Biomedical, MSP B2B, Campus Drop-Box), W1–W5 cognitive load calibration, Telegram bot inline workflows, and SLA analytics.

---

## ⚡ Live Interactive Demo

Test the live canvas immediately without registration:
- **URL:** [https://tantou.app/login](https://tantou.app/login) (or run locally at `http://localhost:8085/login`)
- **Quick Login:** Click any pre-filled profile (`Alex M.` Admin, `Nick Pappas` Coordinator, `Elena D.` Public Worker, `Kostas K.` Internal Worker, `Sofia C.` Radiology Client).
- **Reset Interval:** Interactive demo tenant resets automatically every 2 hours via `php artisan tantou:reset-demo`.

---

## 🧠 Why Tantou? (The Philosophy)

### Inversion of Control for Human Workload
In traditional ticketing tools and Kanban boards (Jira, Trello, Asana), tickets are **project-centric**: tasks pile up in endless "To Do / Doing" backlog columns while managers guess who has bandwidth. Technicians drown under invisible, unstructured requests and context-switching burnout.

**Tantou (担当)** is the Japanese principle of designating the specific individual directly in charge and personally accountable for an outcome. Tantou inverts control from *project queues* to **real human capacity**:

1. **Columns Represent People, Not Abstract Stages:** Every collaborator column shows a live cognitive load meter (e.g. `8/10 load`) calculated from active tasks.
2. **Explicit Cognitive Weights (`W1–W5`):** Tasks aren't just tickets; they consume capacity based on cognitive weight (`W1` Trivial to `W5` Critical/Heavy).
3. **Smart Queue (`FIFO + Priority`):** Collaborator columns auto-sort tickets by Urgency (`Urgent → High → Normal → Low`) and First-In-First-Out submission time.
4. **Fair Gamification & Zero-Point Protection:**
   - Active work transitioned from `Doing → Done` awards **Bounty Score** based on weight, urgency multiplier, and speed.
   - Two engineers can pair up on complex tickets with automatic **Co-op % Splits** (up to 50%).
   - Dragging an unstarted ticket directly into `COMPLETED` marks it as **`Canceled (0 pts)`**, freeing capacity without gaming the leaderboard.

<!-- Screenshot / GIF Placeholder: docs/screenshots/capacity-board-dark.gif -->
```
+----------------------------------------------------------------------------------------------------+
| ⚡ Tantou Live Canvas • Hellenic Demo Ops                                        🟢 Reverb Sync Live |
+-----------------------+-----------------------+-----------------------+----------------------------+
| 📋 BACKLOG (3)        | 👤 Nick Pappas [Coord] | 👤 Elena D. [London]  | ✅ COMPLETED (24)          |
|                       | Cap: 4/10 [======    ] | Cap: 9/10 [=========!] |                            |
| [W5] DB Deadlock Fix  | [W2] API Endpoint     | [W4] Stripe Webhook   | [W3] Scaffold TALL Stack   |
| [W3] SSL Cert Renewal | ↳ Co-op: Kostas (30%)  | ↳ Priority: URGENT    | ↳ Tantou: Alex M. (+45pts) |
| [W1] Update DNS       |                       |                       |                            |
+-----------------------+-----------------------+-----------------------+----------------------------+
```

---

## 🚀 Key Features

### 1. Real-Time Capacity Radar (Laravel Reverb WebSockets)
- Sub-second drag-and-drop live synchronization powered by Laravel Reverb.
- Visual load progress bars dynamically switch from Emerald (Healthy) to Amber (High Load) and Rose (Burnout Hazard).
- Department Radar header displays aggregate capacity and bottleneck status across all operational units.

### 2. 3-Tier Visibility Firewalls (`Public` • `Internal` • `Private`)
- **Public Collaborators:** Visible to external clients, hospital wards, and customer accounts for direct assignment.
- **Internal Staff:** Visible only to fellow colleagues and department coordinators for internal handoffs (`A → B → C`).
- **Private Deep-Work:** Visible exclusively to the worker and workspace administrators (prevents unscheduled interruptions).
- **Strict B2B Client Isolation:** External clients only see their own company/facility tickets with real names; other active workloads appear anonymized (`#TNT-XXXX`) to protect privacy while preserving transparent load meters.

### 3. 4 Supported Deployment Models
1. **Internal Enterprise & Hospital IT Helpdesks:** Organize by clinical wards or departments (Radiology, Surgery, Pharmacy, Emergency) with room/bed tracking.
2. **B2B Managed Service Providers (MSPs):** Deliver outsourced IT across multiple corporate clients without cross-client ticket leakage.
3. **Zero-Login QR Facility Drop-Box (`/submit` & `/track/{token}`):** Floor staff scan a physical QR code on equipment or doors, submit photos and extensions without creating accounts, track live `#TNT-XXXXXXXX` progress, and self-close when solved.
4. **Multi-Site & Shared Services Hubs:** Central technical ops dispatching tasks to technicians across regional branches and physical campuses.

### 4. 2-Way Telegram Bot Integration
- Pair mobile Telegram accounts in 1 click (`/start usr_{token}`).
- Instant push notifications for new backlog requests and assigned tickets.
- **Interactive Inline Buttons:** Claim backlog tickets (`[ 🙋‍♂️ Claim ]`), start execution (`[ ▶️ Start Working ]`), or complete tasks (`[ ✅ Complete ]`) directly inside Telegram.
- **GDPR / Privacy Mode:** Toggle in Workspace Settings to redact task titles from push payloads, transmitting only `#TNT-XXXX` and metadata.
- **Dual Transport:** Works via standard domain Webhooks or **firewall-friendly Intranet Long-Polling** (`php artisan tantou:telegram-poll`) with zero open inbound ports!

### 5. Multi-Timezone Aware SLA Analytics & CSV Exports
- Mean Time to Acknowledge / Start (`MTTA`) and Mean Time to Resolution (`MTTR`).
- Top Requesting Facilities & client demand breakdown.
- Per-worker timezone overrides with visual local time badges.
- One-click CSV export of historical completion velocity and Co-op assist metrics.

### 6. Enterprise Security Hardening
- 2-Step expiring Magic-Link registration with automated background pruning.
- Global strong password policies (min 10 chars, mixed case, numbers, symbols).
- Strict route rate-limiting and invisible anti-bot honeypots.
- UUID-partitioned private attachment storage with strict MIME-type verification.
- Pre-configured HTTP security headers (CSP, HSTS, X-Frame-Options).

---

## 🐳 60-Second Docker Quickstart

Tantou ships with pre-configured Docker Compose files running **PHP 8.4, MariaDB 11, Redis, and Laravel Reverb**.

### 1. Clone & Boot Containers
```bash
# Clone the repository
git clone https://github.com/ktsourdinis/tantou-oss.git && cd tantou-oss

# Copy environment configuration
cp .env.example .env

# Start Docker containers in background
docker compose up -d
```

### 2. Initialize Database & Seed Demo Data
```bash
# Generate application key & run database migrations with demo dataset
docker exec -u 1000:1000 tantou_app php artisan key:generate
docker exec -u 1000:1000 tantou_app php artisan migrate --seed
```

### 3. Access Tantou
Open your browser at **[http://localhost:8085](http://localhost:8085)** (or your server's LAN IP, e.g. `http://192.168.1.100:8085`).

---

### Pre-Seeded Demo Accounts (Password: `password`)

| Name | Role | Email | Column Visibility | Department / Facility |
| :--- | :--- | :--- | :--- | :--- |
| **Alex M.** | `admin` | `admin@tantou.local` | Private (🛡️) | Operations (120 pts) |
| **Nick Pappas** | `coordinator` | `coordinator@tantou.local` | Public (🌐) | Software (245 pts) |
| **Elena D.** | `worker` | `elena@tantou.local` | Public (🌐) | Software • London Timezone (380 pts) |
| **Kostas K.** | `worker` | `kostas@tantou.local` | Internal (🔒) | Infrastructure & Net (310 pts) |
| **Maria O.** | `worker` | `maria@tantou.local` | Private (🛡️) | Operations (195 pts) |
| **Sofia C.** | `client` | `client@tantou.local` | N/A | Client • Radiology Unit |

> 💡 **Tip:** To reset the demo data at any time, run:
> ```bash
> docker exec -u 1000:1000 tantou_app php artisan tantou:reset-demo
> ```

---

## 🌐 Intranet IP vs Domain Routing

Tantou was built from the ground up to support **air-gapped intranet deployments** (local hospital IPs, factory subnets, homelabs) as well as **multi-tenant cloud SaaS domains**:

### Routing Modes (`.env` Reference)

| Mode (`TANTOU_ROUTING_MODE`) | Example URL | Use Case |
| :--- | :--- | :--- |
| `single` *(Default in Community)* | `http://192.168.1.100:8085/board` | Single-tenant private installations on local IP or intranet. |
| `path` | `http://192.168.1.100:8085/board?workspace=ops` | Multi-tenant testing on raw IP addresses without DNS. |
| `subdomain` | `https://ops.tantou.app/board` | Multi-tenant Cloud SaaS environments with wildcard DNS. |
| `auto` | Dynamic | Automatically detects IP address vs Domain name. |

```ini
# .env Configuration Example for Single-Tenant Intranet Server
APP_URL=http://192.168.1.100:8085
```

---

## ✈️ 2-Way Telegram Bot (Webhook & Intranet Polling)

Tantou supports two-way task alerts and interactive callbacks directly inside Telegram.

### 1. Create Your Bot
1. Open Telegram and message [@BotFather](https://t.me/BotFather).
2. Create a bot using `/newbot` and copy your API Token.
3. Add the token to `.env`:
   ```ini
   TANTOU_TELEGRAM_BOT_TOKEN="123456789:ABCdefGHIjklMNOpqrSTUvwxYZ"
   TANTOU_TELEGRAM_BOT_USERNAME="MyTantouBot"
   ```

### 2. Choose Your Transport Mode

#### A. Domain Mode (Webhook)
For installations with a public domain and valid SSL certificate:
```ini
TANTOU_TELEGRAM_MODE=webhook
TANTOU_TELEGRAM_WEBHOOK_SECRET="your-random-64-character-secret-key"
```
Webhook URL: `https://your-domain.com/api/telegram/webhook`

#### B. Intranet IP Mode (Firewall-Friendly Long-Polling)
For servers inside a hospital LAN, enterprise intranet, or homelab with **no public domain and zero inbound ports**:
```ini
TANTOU_TELEGRAM_MODE=polling
```
Run the polling worker inside Docker:
```bash
# Run one iteration (useful for testing or cron)
docker exec -it tantou_app php artisan tantou:telegram-poll --once

# Or run continuously in the background
docker exec -d tantou_app php artisan tantou:telegram-poll
```

---

## ⚖️ Open-Core Community vs Managed Cloud

| Feature | Community (Self-Hosted) | Cloud Team (€29/mo) | Cloud Business / MSP (€79/mo) |
| :--- | :---: | :---: | :---: |
| **License & Price** | **Free Forever (MIT)** | €29 / month | €79 / month |
| **Hosting Model** | Self-Hosted (Your Server/IP) | Managed Cloud SaaS | Managed Cloud SaaS |
| **Active Workers** | Unlimited (Single-Tenant) | Up to 15 Active Workers | **Unlimited Workers** |
| **Organizations & Departments** | Single Tenant • Unlimited Depts | 1 Workspace • Multi-Dept | **Multi-Tenant MSP Ready** |
| **Custom Domain** | Your own DNS / IP | `{slug}.tantou.app` | **Custom Domain (`ops.client.com`)** |
| **Capacity Radar & Smart Queue** | ✅ | ✅ | ✅ |
| **3-Tier Visibility Firewalls** | ✅ | ✅ | ✅ |
| **Public QR Drop-Box (`/submit`)** | ✅ | ✅ | ✅ (White-labeled) |
| **2-Way Telegram Bot** | Custom Bot Token (Poll/Hook) | Platform Bot (1-Click) | **Custom Bot + GDPR Privacy** |
| **SLA Analytics & CSV Export** | ✅ | ✅ | ✅ |
| **Backups & Maintenance** | Self-Managed | Automatic Daily Backups | Automated Backups + 99.9% SLA |
| **Support** | GitHub Community | Standard Email Support | **Priority SLA & Onboarding** |

---

## 🛠️ Tech Stack, Testing & Contributing

Tantou is crafted on the **TALL Stack** with modern PHP 8.4:
- **Framework:** [Laravel 11 / 13](https://laravel.com)
- **Reactivity:** [Livewire 3](https://livewire.laravel.com) & [Alpine.js](https://alpinejs.dev)
- **Styling:** [Tailwind CSS](https://tailwindcss.com)
- **WebSockets:** [Laravel Reverb](https://laravel.com/docs/reverb)
- **Database:** MariaDB 11 / MySQL 8
- **Cache & Queue:** Redis
- **Testing:** [Pest PHP / PHPUnit](https://pestphp.com)

### Running the Test Suite
All backend isolation, concurrency, security, and Livewire component tests can be executed inside the Docker container:

```bash
docker exec -u 1000:1000 tantou_app php artisan test --compact
```
*(All 181 tests, 1142 assertions passing)*

### Code Style (Laravel Pint)
```bash
docker exec -u 1000:1000 tantou_app vendor/bin/pint --format agent
```

---

## 📄 License & Credits

- Tantou Community Edition is open-source software licensed under the **[MIT License](LICENSE)**.
- Designed & Developed by **[Kostas Tsourdinis](https://github.com/ktsourdinis)** ([ktsourdinis/tantou-oss](https://github.com/ktsourdinis/tantou-oss)).
