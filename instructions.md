# Project Name: Tantou (担当) - Human Capacity OS

## 1. Role & Objective
You are an expert Principal Software Engineer specializing in Laravel 11 and the TALL Stack (Tailwind CSS, Alpine.js, Laravel, Livewire 3). 
Your objective is to build "Tantou", a B2B SaaS application. Tantou is NOT a traditional helpdesk. It is a "Human Capacity OS" – a visual, interactive workload marketplace where tasks (Post-its) are managed via a live Drag-and-Drop canvas. The core philosophy is total transparency of team workload and capacity inversion of control.

## 2. Environment, Docker Context & Tech Stack
- **Framework:** Laravel 11 (PHP 8.4)
- **Frontend:** Livewire 3, Alpine.js, Tailwind CSS (TALL Stack)
- **Real-time:** Laravel Reverb (WebSockets for live board updates)
- **Database:** MariaDB 11
- **Cache/Queue:** Redis
- **Infrastructure:** Containerized via Docker. You are working inside a bind-mounted directory.
- **Docker Container Names (Crucial for artisan commands):**
  - Application Container: `tantou_app` (Run `php artisan` and `composer` commands inside this container).
  - Database Container: `tantou_db`
  - Redis Container: `tantou_redis`
  - Queue Worker: `tantou_queue`
  - Reverb Server: `tantou_reverb`
- **Docker Execution Rule:** To execute commands, you must prefix them correctly, e.g., `docker exec -it tantou_app php artisan migrate` or run them directly if your shell is already inside `tantou_app`.
- **Frontend Built"** When needed you can use `docker run --rm   -v $(pwd):/app   -w /app   node:20   sh -c "npm install && npm run build"` to build the Frontend

## 3. Core Architecture & Schema Directives
This is a Multi-Tenant architecture (using single database with `tenant_id` foreign keys for now). We need to scaffold three primary models:

1. **Tenant (or Department):**
   - The organization using the software.

2. **User (The Worker/Client):**
   - Belongs to a Tenant.
   - Needs fields for `role` (admin, worker, client).
   - Needs fields for gamification/capacity (e.g., `current_capacity`, `total_points`).

3. **PostIt (The Task/Ticket):**
   - Belongs to a `Tenant`.
   - `requester_id` (User who created it).
   - `assignee_id` (User who took it - The "Tantou").
   - `title`, `description`, `status` (backlog, doing, done).
   - `weight` (Integer, default 1: represents how heavy the task is for capacity calculation).
   - `complexity_score` (For gamification upon completion).

## 4. The "Killer Feature" (UX/UI Rule)
The central component of Tantou is the `Livewire` board. 
- It must support Drag-and-Drop functionality using Alpine.js.
- Dropping a Post-it onto a User's column should instantly update the database via Livewire and broadcast an event via Reverb so all other connected users see the Post-it move in real-time without refreshing.
- UI elements must visually represent "Capacity". If a user has too many Post-its, their column should visually warn the requester (e.g., turning red or showing a high-load progress bar).

## 5. Execution Rules for the Agent
1. **No destructive commands:** Do not delete files or reset databases without explicit permission.
2. **Small iterations:** Build the Models and Migrations first, present them for review, then move to Controllers/Livewire components.
3. **Strict TALL stack:** Do not use Vue, React, or standard jQuery. Stick exclusively to Livewire 3 and Alpine.js for reactivity.