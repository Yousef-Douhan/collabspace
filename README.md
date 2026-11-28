<div align="center">

# CollabSpace

**A team-collaboration workspace: projects, Kanban, chat, meetings with AI summaries, reports and an AI assistant.**

Built with Laravel 13 for the **Huma-volve CollabSpace Challenge** · Figma: *Collabpspace, Team C*

![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss&logoColor=white)
![Alpine.js](https://img.shields.io/badge/Alpine.js-3-8BC0D0?logo=alpinedotjs&logoColor=white)
[![CI](https://github.com/Yousef-Douhan/collabspace/actions/workflows/ci.yml/badge.svg)](https://github.com/Yousef-Douhan/collabspace/actions/workflows/ci.yml)
![Tests](https://img.shields.io/badge/tests-150%2B_passing-2EA44F)
![Larastan](https://img.shields.io/badge/Larastan-level_6-2A5EA7)
![Realtime](https://img.shields.io/badge/realtime-Laravel_Reverb-FF2D20?logo=laravel&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-one_command-2496ED?logo=docker&logoColor=white)
![i18n](https://img.shields.io/badge/i18n-English_%7C_Arabic_(RTL)_%7C_French-0B5FFF)

### 🔗 [Live demo → collabspace-s3qk.onrender.com](https://collabspace-s3qk.onrender.com)

### ▶️ [Watch the 1-minute demo video](https://youtu.be/gzkJ8Fmxl0U)

### 📘 [API documentation (Postman)](https://documenter.getpostman.com/view/45494637/2sBYPv5ZQC)

Sign in with **`admin@collabspace.test`** / **`password`** · free hosting: the first visit after a quiet period can take about a minute to wake up.

[Overview](#-overview) · [Features](#-features) · [Screenshots](#-screenshots) · [Quick start](#-quick-start) · [Docker](#-docker-one-command) · [API](#-rest-api) · [Architecture](#-architecture) · [Security](#-security--performance-highlights) · [Testing](#-testing)

<img src="docs/screenshots/dashboard.png" alt="CollabSpace dashboard" width="100%">

</div>

---

## 📌 Overview

CollabSpace gives a team one place to plan work, talk, meet and see progress. One Laravel codebase serves two clients:

| Client | Auth | Purpose |
|---|---|---|
| **Blade web app** | Session | The full UI from the Figma design, in English, Arabic (RTL) and French |
| **REST API** `/api/v1` | Sanctum tokens | For mobile and SPA clients. Ships with a 92-request Postman collection |

Both clients go through the **same Form Requests, Policies and Services**, so validation, permissions and business rules are defined once.

> **نبذة بالعربية:** منصة تعاون للفرق، فيها إدارة المشاريع، ولوحة مهام Kanban بالسحب والإفلات، ومحادثات مع علامات القراءة، واجتماعات بتقويم أسبوعي وملخص بالذكاء الاصطناعي، وتقارير ورسوم بيانية، ومساعد ذكي يجيب من بيانات الفريق. الواجهة كاملة بالعربية والإنجليزية والفرنسية مع دعم الكتابة من اليمين لليسار، ومعها REST API موثّق وأكثر من 150 اختباراً آلياً. الرسائل ولوحة المهام تتحدث فورياً عبر Laravel Reverb، ويعمل المشروع كاملاً بأمر Docker واحد.

---

## ✨ Features

### Work management

- **Projects:** status, priority, start and deadline dates, live completion %, members with roles (owner, manager, member), and a Markdown wiki.
- **Teams:** named groups with **lead / member** roles (a team always keeps a lead). Link a team to a project and every member joins it; someone who joins the team later joins its projects too. Unlinking never removes people from a project.
- **Kanban tasks:** filters by project, my tasks, overdue or due this week, and priority. Drag & drop across *To-do → In Progress → In Review → Completed*. Tasks have priorities, tracks (UI/UX, Front End, Back End, Graphic Design), assignees and a reviewer, progress, estimated hours, due dates with overdue highlighting, comments and attachments.
- **Files:** private per-project and per-task storage. Downloads are allowed only for members.

### Communication

- **Chats:** direct messages and group or project channels, attachments, editing and deleting your own messages, read receipts ✓ / ✓✓, unread counters, and pin or archive per user.
- **⚡ Real-time (Laravel Reverb):** new, edited and deleted messages appear instantly with a *“Sara is typing…”* indicator; a card dragged on the Kanban board slides into place on every teammate's screen with a toast (*“Omar moved a task”*). Private channels reuse the same policies as the pages, and message text never travels over the socket. If the WebSocket server is down, pages fall back to polling and no request ever fails.
- **Meetings:** a weekly calendar; a *New Meeting* form with date, time and duration, guests, a note, a location, a reminder, notifications by **in-app, email or Slack**, and attachments; Accept or Decline, shared notes, comments, attachments and transcript search.
- **Notifications:** task assigned, task overdue, meeting invitation and meeting reminder, in the app and through the API.

### Intelligence & insight

- **🤖 Ask AI:** a chat assistant that answers questions about the user's own projects, tasks and meetings, in English or Arabic.
- **✨ Meeting AI summary:** one click turns the transcript into a summary and key points.
- **🪄 Meeting → tasks:** the AI pulls the agreed action items, with their owners and dates, out of a meeting. You review and edit them, then they land on the Kanban board, and the assignees are notified.
- **🗺 Plan with AI:** from a project's description and wiki, the AI proposes a starter plan across the UI/UX, front-end, back-end and graphic-design tracks, matched to the team members. Nothing is created until you approve it.
- **Dashboard:** stats with month-over-month change and a completion gauge; an activity chart with *All Projects* and *Duration* menus (today, this week, this month, last three months, this year); a *Teams* card filtered by track with *Leader* badges; upcoming meetings with attendees.
- **Reports:** completion and activity KPIs, three charts (worker activity monthly or weekly), per-project and per-member tables, **AI-drafted report notes**, *Print / Save PDF*, saved reports and CSV export.

### Platform

- **Three languages:** an English / العربية / Français menu, with a full right-to-left layout for Arabic and translated dates, validation messages and chart axes.
- **Search:** global search, with a *Recent Searches* list under the search bar.
- **Profiles:** completed tasks, hours worked, recent activity, avatar, and a password change.
- **Accounts:** sign in with email, GitHub or Google; **forgot / reset password** by e-mail (web and API). The reset form never reveals which addresses exist, and a reset signs out every API token.

### Administration

- **🛡 Admin panel** (admins only): workspace-wide stats (users, projects, tasks, overdue work, files, meetings, messages, AI usage), users by role, and a site-wide activity feed.
- **User management:** search and filter users, change roles, and deactivate or reactivate accounts. A deactivated user is signed out everywhere (web session and API tokens) and cannot sign in again until an admin reactivates them. The workspace always keeps at least one active admin, and admins cannot demote or deactivate themselves.

---

## 🖼 Screenshots

<table>
  <tr>
    <td width="50%"><b>Kanban board</b>: Arabic, RTL<br><img src="docs/screenshots/kanban-ar.png" alt="Kanban board"></td>
    <td width="50%"><b>Meeting</b>: AI summary and key points<br><img src="docs/screenshots/meeting-ai-summary.png" alt="Meeting AI summary"></td>
  </tr>
  <tr>
    <td><b>Weekly calendar</b><br><img src="docs/screenshots/meetings-calendar-ar.png" alt="Weekly calendar"></td>
    <td><b>Reports</b><br><img src="docs/screenshots/reports-ar.png" alt="Reports"></td>
  </tr>
  <tr>
    <td><b>Ask AI</b>: answers from workspace data<br><img src="docs/screenshots/ask-ai.png" alt="Ask AI"></td>
    <td><b>Projects</b><br><img src="docs/screenshots/projects-ar.png" alt="Projects"></td>
  </tr>
</table>

---

## 🚀 Quick start

**Requirements:** PHP 8.3+, Composer 2, Node 20+, and MySQL 8 or MariaDB 10.6+.

```bash
git clone https://github.com/Yousef-Douhan/collabspace.git
cd collabspace

composer install && npm install
cp .env.example .env && php artisan key:generate

# set DB_DATABASE / DB_USERNAME / DB_PASSWORD in .env, then:
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve
```

Open **http://127.0.0.1:8000** and sign in with `admin@collabspace.test` / `password`.

During development, run `npm run dev` next to `php artisan serve` so CSS and JS changes reload instantly.

**Real-time (optional):** set `BROADCAST_CONNECTION=reverb` and the `REVERB_*` values in `.env` (see `.env.example`), then run `php artisan reverb:start` in a second terminal. A green **Live** badge appears in the top bar when the WebSocket is connected; without it, chats and the board fall back to polling.

> **File uploads:** the app accepts files up to 20 MB, but PHP's own limit is often 2 MB. If uploads fail, set `upload_max_filesize = 20M` and `post_max_size = 25M` in the `php.ini` shown by `php --ini`, then restart the server.

### Demo accounts

Every account uses the password **`password`**.

| Email | Name | Role | Track |
|---|---|---|---|
| `admin@collabspace.test` | Yousef Douhan | **Admin**: sees all projects, opens the Admin panel | Back End |
| `lina@collabspace.test` | Lina Haddad | Manager | UI/UX |
| `omar@collabspace.test` | Omar Khaled | Member | Front End |
| `sara@collabspace.test` | Sara Nasser | Member | Back End |
| `ahmad@collabspace.test` | Ahmad Saleh | Member | Graphic Design |

The seeder fills every screen with data: 2 projects, 9 tasks (one of them overdue), real files, 3 conversations and a meeting with a transcript and notes.

### AI configuration (optional)

Ask AI and meeting summaries need a provider key. The rest of the app works without one.

```env
AI_PROVIDER=gemini        # gemini | openai | anthropic
AI_API_KEY=your-key-here
AI_MODEL=                 # optional, default for Gemini: gemini-3.5-flash

# optional: post meeting invitations and reminders to a Slack channel
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/…
```

Email notifications use Laravel's mailer (`MAIL_MAILER=log` writes them to `storage/logs` during development).

A free Gemini key is available from [Google AI Studio](https://aistudio.google.com/).

### Scheduler

Two scheduled jobs run in the background:

| Command | Schedule | Purpose |
|---|---|---|
| `meetings:send-reminders` | every minute | Notify attendees before a meeting starts |
| `tasks:notify-overdue` | daily 09:00 | Notify assignees of overdue tasks |

Run `php artisan schedule:work` locally. In production, use a cron entry that calls `schedule:run` every minute.

---

## 🐳 Docker (one command)

```bash
git clone https://github.com/Yousef-Douhan/collabspace.git
cd collabspace
docker compose up --build
```

Open **http://localhost:8000** and sign in with `admin@collabspace.test` / `password`. One container runs Nginx, PHP-FPM 8.4, the Reverb WebSocket server and the scheduler (supervisord), on SQLite with the demo data seeded on first start. To enable AI, create a `.env` next to `docker-compose.yml` with `AI_API_KEY=...` before starting.

The same image runs the **[live demo](https://collabspace-s3qk.onrender.com)** on Render's free plan: `render.yaml` is a ready Blueprint (*New → Blueprint →* this repo), Reverb WebSockets included. The demo resets to clean demo data on every restart.

---

## 🔌 REST API

| | |
|---|---|
| **Base URL** | `/api/v1` |
| **Auth** | `POST /auth/login` returns a token; send it as `Authorization: Bearer <token>` |
| **Language** | `Accept-Language: ar` returns Arabic labels and messages |
| **Errors** | Always JSON: `401`, `403`, `404`, `422` (with field errors), `429`, `503` (AI) |
| **API docs** | [Published Postman documentation](https://documenter.getpostman.com/view/45494637/2sBYPv5ZQC), browsable online with every request |
| **Postman** | [`docs/postman/CollabSpace.postman_collection.json`](docs/postman/CollabSpace.postman_collection.json) |
| **Full reference** | [`docs/DESIGN.md`](docs/DESIGN.md), covering every endpoint and the database schema |

Example requests:

```bash
# Login
curl -X POST http://127.0.0.1:8000/api/v1/auth/login \
     -H "Accept: application/json" \
     -d "email=admin@collabspace.test" -d "password=password"

# Move a task on the Kanban board
curl -X PATCH http://127.0.0.1:8000/api/v1/tasks/5/move \
     -H "Authorization: Bearer <token>" -H "Accept: application/json" \
     -d "status=in_review" -d "position=0"
```

<details>
<summary><b>Endpoint groups</b></summary>

| Group | Highlights |
|---|---|
| `auth`, `profile` | register, login, logout, **forgot / reset password**, me, profile update, avatar, password |
| `users` | team directory with filters, member profile and stats |
| `projects` | CRUD, members and roles, wiki, linked teams |
| `teams` | CRUD, members with `lead` / `member` roles, link to and unlink from projects |
| `tasks` | CRUD, board view, `move` (Kanban), reviewer, comments, filters (`mine`, `overdue`) |
| `files` | upload, private download, delete |
| `conversations` | direct and channel chats, messages, edit and delete, read receipts, pin and archive |
| `meetings` | CRUD, RSVP, notes, attachments, comments, **AI summary** |
| `ai` | `ask`, history |
| `dashboard`, `reports`, `search` | aggregates, KPIs and charts, CSV export, global search |
| `notifications`, `meta` | list, read, read-all; enums for client dropdowns |
| `planner` | `POST /meetings/{id}/plan`, `POST /projects/{id}/plan` (AI suggestions), `POST /projects/{id}/tasks/bulk` |
| `admin` | workspace stats, user list with filters, change role, deactivate and reactivate (admins only) |

</details>

**Postman tip:** run **Login** first. It stores the token, and every request that creates something stores its ID in a collection variable, so the collection runs top to bottom.

---

## 🏗 Architecture

```
        ┌─────────────────────────┐     ┌─────────────────────────┐
        │  API controllers (v1)   │     │  Web controllers (Blade) │
        └────────────┬────────────┘     └────────────┬────────────┘
                     └──────────────┬────────────────┘
                   Form Requests  ·  Policies     ← shared validation & authorization
                                    │
                                 Services         ← shared business logic
         Project · Task · File · Chat · Meeting · Dashboard · Report
         AiService (provider-agnostic) · AiAssistant · ActivityLogger
                                    │
                Eloquent models + visibleTo() scopes  →  MySQL
```

### Design decisions

| Decision | Why |
|---|---|
| **Thin controllers, shared Services** | The API and the web app cannot drift apart. A rule changes in one place. |
| **`visibleTo($user)` scopes** | One definition of what a user can see. The AI assistant builds its context through the same scopes, so it never sees another team's data. |
| **Contiguous Kanban positions** | A move re-numbers both columns inside one database transaction, so the order stays consistent. |
| **Private `local` disk for files** | Nothing is publicly reachable. Every download passes a policy check. |
| **AI proposes, people decide** | Planner suggestions are validated against the project (members, tracks, dates) and only created after the user reviews them. The model's output is never trusted as-is. |
| **Provider-agnostic `AiService`** | Switching between Gemini, OpenAI and Anthropic is an `.env` change. Failures surface as a clean `503`. |
| **Backed enums with translated labels** | Type-safe statuses and priorities, the same labels in the UI and the API, and Arabic for free. |
| **Deactivate, don't delete** | Disabling an account keeps the user's tasks, files and history intact. Tokens are revoked at once, and web sessions end on their next request. |
| **`SetLocale` middleware** | Picks the language from API `Accept-Language`, then the session, then the user's saved preference. |

### Project structure

```
app/
├── Enums/                    TaskStatus, Priority, Track, roles … (+ EnumHelpers)
├── Http/
│   ├── Controllers/Api/V1/   REST controllers
│   ├── Controllers/Web/      Blade controllers
│   ├── Middleware/           SetLocale, EnsureAdmin, EnsureActiveUser
│   ├── Requests/             Form Requests (shared)
│   └── Resources/            API Resources
├── Models/  Policies/  Services/  Notifications/  Console/Commands/
lang/                         ar.json + ar/*.php (validation, auth, pagination)
resources/
├── views/                    pages + components (layouts, modal, stat-card, avatar…)
└── js/app.js                 Alpine: kanban (SortableJS), chart (Chart.js)
docs/                         DESIGN.md · UI_SPEC.md · Postman · screenshots
tests/Feature/                Api/ · Web/
```

---

## 🔒 Security & performance highlights

**Security**

| Area | What the code does |
|---|---|
| **Authorization** | Every model goes through a Policy, and lists go through `visibleTo($user)` scopes. A user never sees another team's project, file, chat or meeting, and neither does the AI assistant. |
| **API tokens** | Sanctum tokens **expire after 30 days** (`SANCTUM_EXPIRATION`). Changing a password revokes every other token, a password reset revokes all of them, and deactivating a user revokes them at once. |
| **Brute force & abuse** | Rate limits on every sensitive route: login `10/min`, forgot password `5/min`, reset `10/min`, social login `20/min`, AI ask `20/min`, AI planner and report drafts `10/min`. |
| **No account enumeration** | "Forgot password" returns the same answer whether or not the e-mail exists. |
| **Private files** | Uploads live on the private `local` disk. There is no public URL; every download is a policy-checked `Storage::download` sent as an attachment. |
| **Upload filtering** | A `SafeUpload` rule rejects files a browser or server could execute (`.php`, `.phar`, `.html`, `.svg`, `.js`, `.exe`, `.sh` …), including double extensions such as `invoice.php.pdf`. Max 20 MB. |
| **Private realtime channels** | Reverb channels are private and authorized per project or conversation. The socket payload carries IDs only, never the message text. |
| **XSS & CSRF** | Markdown is rendered with raw HTML stripped, Blade escapes output by default, and every web form carries a CSRF token. |
| **Security headers** | Every response (pages, API, downloads, errors) sends `nosniff`, `X-Frame-Options: DENY`, a `frame-ancestors 'none'` CSP, a strict `Referrer-Policy`, a `Permissions-Policy` that turns off camera / mic / location, and HSTS over https in production. |
| **Traceable requests** | Each request gets an `X-Request-Id` that is returned to the client and added to every log line, so an error report can be matched to the logs. |
| **Production config** | `APP_DEBUG=false` in production; secrets live in environment variables, never in the repository. |

**Performance**

| Area | What the code does |
|---|---|
| **Indexes** | Composite indexes on the hot queries: tasks `(project_id, status, position)`, activity `(project_id, created_at)`, messages `(conversation_id, id)`, AI history `(user_id, id)`, plus `meetings.starts_at` and `projects.status`. |
| **Pagination** | Chat history uses **cursor pagination**, so loading older messages stays fast however long the conversation is. API lists (projects, tasks, files, meetings, reports, users, notifications) are paginated with `per_page` capped at 100. |
| **No N+1** | Relations are eager-loaded in services and API resources. |
| **Kanban moves** | One transaction re-numbers both columns, instead of many separate updates. |

---

## 🧪 Testing

```bash
php artisan test
vendor/bin/phpstan analyse   # or: composer analyse
```

**170+ tests, all passing.** GitHub Actions runs them, plus a Laravel Pint style check, **Larastan static analysis (level 6, no baseline: every model property, relation and array is typed)** and a production asset build, on every push. The suite runs on in-memory SQLite and fakes every AI call, so it needs no database or API key.

| Suite | Coverage |
|---|---|
| `Feature/Api/*` (14 files) | Auth, projects and roles, tasks and Kanban ordering, files, chats and receipts, meetings and reminders, AI, notifications, dashboard, reports, search, and permission boundaries |
| `Feature/Web/WebPagesTest` | Every Blade page renders; login and logout; Kanban move; forms; live chat polling; access control |
| `Feature/Web/LocaleTest` | Language switching, RTL on every page, Arabic validation, API `Accept-Language` |
| `Feature/PlannerTest` | Meeting → tasks and project plans: the AI's output is sanitized (unknown tracks, people outside the project, bad dates), bulk creation, notifications, permissions, friendly errors |
| `Feature/FigmaExtrasTest` | Dashboard duration and team filters, the overdue filter, the New Meeting form (duration, attachments, email and Slack), report print and AI draft, the French UI |
| `Feature/AdminTest` | Admin-only access, role changes, the last-admin and self-protection rules, deactivation ending web and API sessions, admin API |
| `Feature/RealtimeTest` | Chat and Kanban broadcast the right events; only participants / project members can subscribe; the payload carries no message text; a dead WebSocket server never breaks a request |
| `Feature/TeamsTest` | Team CRUD, lead-only management, the last-lead rule, linking a team adds its people to the project (roles kept), the Teams API |
| `Feature/Web/PasswordResetTest` | Reset e-mail without revealing accounts, the reset form, bad tokens, API endpoints, tokens revoked |
| `Feature/DatabaseSeederTest` | The demo workspace is consistent |

---

## 🛠 Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.4 · Laravel 13 · Sanctum 4 · Reverb · Eloquent · Notifications · Scheduler |
| Database | MySQL / MariaDB (SQLite for tests) |
| Frontend | Blade components · Tailwind CSS 4 · Alpine.js · Laravel Echo · SortableJS · Chart.js · Vite 8 |
| AI | Google Gemini (default) · OpenAI · Anthropic, called over plain HTTP |
| DevOps | Docker (Nginx + PHP-FPM + Reverb, supervisord) · Render Blueprint · GitHub Actions CI |
| Tooling | PHPUnit · Pint · Larastan (PHPStan) · Postman |

---

<div align="center">

**Yousef Douhan**, Backend Developer (Laravel)

[GitHub](https://github.com/Yousef-Douhan) · Built for the Huma-volve CollabSpace Challenge, November 2026

</div>
