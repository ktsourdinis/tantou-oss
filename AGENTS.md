<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>

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