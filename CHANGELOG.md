# Changelog

All notable changes to **Tantou (担当)** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] - 2026-11-17

### Added
- **Human Capacity Engine & Dual-Lens Radar:**
  - Real-time cognitive capacity management based on mental load weights (`W1` Trivial to `W5` Critical).
  - Dynamic visual column overload states, progress indicators, and dynamic capacity recalculation.
  - Dual-lens department radar: client lens aggregates available public collaborator columns; internal staff lens aggregates public + internal columns.
- **Smart Deterministic Dispatch (FIFO + Priority Queue):**
  - Queue prioritization formula combining urgency levels (`low`, `normal`, `high`, `urgent`) with deterministic FIFO arrival sorting.
  - Contextual modal quick-actions and ghost drop slots for direct column and backlog assignment.
- **3-Tier Column Visibility Firewalls:**
  - Multi-lens column visibility modes (`public`, `internal`, `private`).
  - Strict server-side firewall blocking external client requests from entering internal or private staff columns.
  - Granular privacy filters anonymizing requester identities for non-admin viewers.
- **Public QR Drop-Box & Live Request Tracker:**
  - Public unauthenticated ticket submission page with honeypot anti-spam protection, rate-limiting, and department routing.
  - Real-time public tracking view with PIN-protected status monitoring, timeline audits, and file download support.
- **2-Way Telegram Bot Integration:**
  - Long-polling background worker and managed webhook gateway support.
  - 1-on-1 personal collaborator pairing via `/start <token>` for task alerts and status updates.
  - Shared department group linking via `/link <dept_token>` with unassigned task dispatch and zero-group personal routing fallback.
  - Inline keyboard actions: `[ 🙋‍♂️ Claim ]`, `[ ▶ Start ]`, `[ 💬 Reply ]`, and `[ ✅ Done ]`.
  - Bi-directional comment cascade with automated email fallback notifications.
- **Dual Timezone Architecture:**
  - Per-tenant organization timezone configuration.
  - Per-user client browser timezone auto-detection and display formatting.
- **SLA Analytics & CSV Export:**
  - Historical task duration metrics, turnaround trends, and cognitive load distribution.
  - One-click CSV export for external reporting and auditing.
- **Multi-Tenant Open-Core Foundation:**
  - Single-tenant Community Edition (Docker-ready, self-hosted, AGPLv3).
  - Clean abstraction hooks for multi-tenant SaaS, billing, and enterprise features.
- **Localization (i18n):**
  - Full English (`en`) and Greek (`el`) localization across all Blade components, modals, and notifications.
  - Persistent locale preference per user with session fallback.
- **Open-Core Governance:**
  - Contributor License Agreement (CLA) with Developer Certificate of Origin (DCO).
  - Formal Trademark Policy and NIS2-ready Vulnerability Disclosure Policy.
