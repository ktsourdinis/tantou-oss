# Contributing to Tantou (担当)

Thank you for your interest in contributing to **Tantou (担当)** — the Open-Core Human Capacity Operating System! We welcome bug reports, feature discussions, documentation improvements, and pull requests from the community.

---

## 🛠️ Development Environment Setup

Tantou is containerized using Docker. All services (PHP 8.4-FPM, MariaDB 11, Redis 7, Caddy 2, Reverb, Queue Worker, and Telegram Poller) are orchestrated via Docker Compose.

### 1. Prerequisites
- Docker Engine 24+ & Docker Compose v2
- Node.js 20+ (optional locally, or via Node Docker container)
- Git

### 2. Quickstart
```bash
# Clone repository
git clone https://github.com/ktsourdinis/tantou-oss.git
cd tantou-oss

# Copy local environment file
cp .env.example .env

# Start containers
docker compose up -d

# Generate application key & run database migrations
docker exec -u 1000:1000 tantou_app php artisan key:generate
docker exec -u 1000:1000 tantou_app php artisan migrate --seed
```

The application will be accessible at:
- **Web Canvas:** `http://localhost:8085`
- **Reverb WebSockets:** `ws://localhost:8086`

---

## 🧪 Testing & Code Quality Standards

Before submitting any Pull Request, ensure your code satisfies Tantou's strict quality benchmarks:

### 1. Code Style Formatter (Laravel Pint)
All PHP files must adhere to the project's Pint style configuration:
```bash
docker exec -u 1000:1000 tantou_app ./vendor/bin/pint --format agent
```

To verify formatting without altering files (as run in CI):
```bash
docker exec -u 1000:1000 tantou_app ./vendor/bin/pint --test
```

### 2. Test Suite (PHPUnit 12.5)
All tests must pass cleanly. Run the complete test suite:
```bash
docker exec -u 1000:1000 tantou_app php artisan test --compact
```

Or run directly via PHPUnit:
```bash
docker exec -u 1000:1000 tantou_app ./vendor/bin/phpunit
```

### 3. Frontend Compilation
If you modify Blade views, Alpine.js scripts, or Tailwind styling:
```bash
docker run --rm -u 1000:1000 -e npm_config_cache=/tmp/.npm -v $(pwd):/app -w /app node:20 sh -c "npm install && npm run build"
```

---

## 🔀 Pull Request Guidelines

1. **Target Branch:** Submit all feature PRs and non-urgent bugfixes against the `develop` branch (or `main` for critical patches).
2. **Atomic Commits:** Keep commits logical and well-described.
3. **No Database Destruction:** Never write migrations or commands that drop production tables or delete customer data.
4. **Security & Privacy:** Strictly adhere to Tantou's 3-Tier Visibility Firewall rules. Never leak requester identities or private internal columns across tenant or client boundaries.
5. **Localization (i18n):** All visible UI strings must use `__('...')` translation helpers, with primary Greek translations added to `lang/el.json`.

---

## 📜 Contributor License Agreement (CLA) & Dual-Licensing

To protect the open-source community, ensure long-term sustainability, and enable dual-licensing under both AGPLv3 and commercial terms (Tantou Cloud & Enterprise editions), **all contributors must agree to this Contributor License Agreement (CLA)**.

### 1. Developer Certificate of Origin (DCO 1.1)
By submitting a Pull Request, patch, or contribution to Tantou, you certify that:
- **(a)** The contribution was created in whole or in part by you, and you have the right to submit it under the open-source license indicated in the file; or
- **(b)** The contribution is based upon previous work that, to the best of your knowledge, is covered under an appropriate open-source license and you have the right under that license to submit that work with modifications; or
- **(c)** The contribution was provided directly to you by some other person who certified (a), (b) or (c) and you have not modified it.

### 2. Inbound Grant of Rights (Dual-Licensing)
By contributing to `tantou/tantou`, you hereby grant to **Konstantinos Tsourdinis / HELLENIC WEB3 STUDIO** ("Project Maintainer") a perpetual, worldwide, non-exclusive, no-charge, royalty-free, irrevocable copyright and patent license to:
1. Reproduce, prepare derivative works of, publicly display, publicly perform, sublicense, and distribute your contributions and derivative works thereof.
2. Relicense and distribute your contribution under:
   - The open-source **GNU Affero General Public License v3.0 or later (`AGPL-3.0-or-later`)**; AND
   - Any proprietary or commercial licenses offered for **Tantou Cloud**, **Tantou Managed**, or **Tantou Enterprise** editions without additional obligation or royalty.

### 3. Moral Rights & Authorship
You retain copyright ownership of your contribution. You will receive credit in release notes, git commits, and public contributor lists according to standard open-source conventions.

### 4. Acceptance Checkbox
Every Pull Request submitted to Tantou must include the mandatory CLA acceptance checkbox found in the [Pull Request Template](.github/PULL_REQUEST_TEMPLATE.md):
```markdown
- [x] I have read CONTRIBUTING.md and hereby agree to the Contributor License Agreement (CLA), granting the project maintainer the right to distribute my contribution under both AGPL-3.0-or-later and Commercial licenses.
```
PRs cannot be merged without this affirmative agreement.
