# CollabSpace — Design

Team collaboration platform built from the Figma file **"Collabpspace, Team C"**.
One Laravel 13 codebase, three layers that share the same domain logic:

```
                ┌──────────────┐   ┌──────────────┐   ┌──────────────┐
                │  REST API    │   │  Blade UI    │   │  SPA (Vue)   │
                │  /api/v1/*   │   │  /*  (web)   │   │  consumes API│
                └──────┬───────┘   └──────┬───────┘   └──────┬───────┘
                       │  thin controllers │                  │ HTTP
                       ▼                   ▼                  │
                ┌─────────────────────────────────────┐       │
                │ Form Requests · Policies · Services │◄──────┘
                └─────────────────┬───────────────────┘
                                  ▼
                       Eloquent models · MySQL
```

* **Controllers stay thin**: validate (Form Request) → authorize (Policy) → call a Service → return a Resource (API) or a View (Blade).
* **Services own the business rules** (who can move a task, what happens when a meeting is created, Ask AI context…), so API and Blade never diverge.
* **API Resources** define every JSON shape in one place.

---

## 1. Screens → features (from Figma "UI Design" page)

| Figma screen / overlay | Feature |
|---|---|
| Sign in / Sign up | Auth (Sanctum tokens for API, session for Blade) |
| Dashboard | Stats, active projects, priority tasks, team status, upcoming meetings, recent files, Ask AI |
| All Projects · New Project overlay | Projects list + create |
| All Projects / Alpha · /Tasks · /Files · /Teams | Project details, its tasks, files, members |
| Tasks Page · Create Task overlay · My task overlay | Kanban board (todo → in progress → in review → completed), create, "my tasks" |
| Files · Select Files | Upload / list / download / delete |
| Chats · Slack-style channels | Direct chats + channels, messages with attachments, pin / archive |
| Meetings · Add Meeting overlay · Meetings / join · Meeting room | Schedule (location, link, reminder "1 hour before"), attendees, transcript, **AI summary** |
| Report | Project progress, team performance, export |
| Notification overlay | Notifications (meeting reminders, assignments, overdue tasks) |
| Profile / overview · tasks · files · Settings | Member profile + edit own profile |
| Ask AI / chat | AI assistant over workspace data, with chat history |
| Search Bar overlay | Global search (projects, tasks, files, people) |
| Language overlay | Arabic / English (`locale` per user) |

---

## 2. Database

Conventions: `id` bigint PK, `timestamps()` everywhere, InnoDB, `utf8mb4_unicode_ci`.
Enums are stored as strings and backed by PHP enums in `app/Enums`.

### users
| column | type | notes |
|---|---|---|
| name, email, password | | email unique |
| phone | string null | |
| avatar_path | string null | public disk |
| job_title | string null | |
| track | string | `ui_ux` · `front_end` · `back_end` · `graphic_design` |
| role | string | `admin` · `manager` · `member` (workspace-level) |
| availability | string | `available` · `busy` · `away` |
| years_of_experience | tinyint | |
| about | text null | |
| locale | string(2) | `en` · `ar` |
| joined_at | date null | |

### projects  (soft deletes)
| column | type | notes |
|---|---|---|
| owner_id | FK users | |
| name | string | |
| description | text null | |
| type | string | e.g. `saas`, `mobile`, `website` |
| priority | string | `high` · `medium` · `low` |
| status | string | `active` · `completed` · `archived` |
| wiki | longText null | markdown |
| start_date, end_date | date null | |

### project_members
`project_id`, `user_id`, `role` (`owner` · `manager` · `member`) — unique(project_id, user_id).

### tasks  (soft deletes)
| column | type | notes |
|---|---|---|
| project_id | FK | cascade |
| created_by | FK users | |
| title, description | | |
| status | string | `todo` · `in_progress` · `in_review` · `completed` |
| priority | string | `high` · `medium` · `low` |
| track | string | same values as users.track |
| progress | tinyint 0–100 | |
| position | int | order inside a Kanban column |
| start_date, due_date | date null | |
| completed_at | timestamp null | set by the service when status → completed |

Index: (project_id, status, position).

### task_user  (assignees)
`task_id`, `user_id`, `is_reviewer` bool — unique(task_id, user_id).

### files
| column | type | notes |
|---|---|---|
| project_id | FK | cascade |
| task_id | FK null | null on delete |
| uploaded_by | FK users | |
| original_name, path, mime_type | | |
| size_bytes | bigint | |

### conversations
`type` (`direct` · `channel`), `name` null (channels), `project_id` null, `created_by`.

### conversation_user  (participants — per-user state)
`conversation_id`, `user_id`, `is_pinned`, `is_archived`, `last_read_at` — unique pair.
> Pin / archive are **per user** (pinning a chat must not pin it for everyone).

### messages
`conversation_id`, `sender_id`, `body` text null, `attachment_path`, `attachment_name`, `attachment_type` (`image` · `audio` · `document`) null.

### meetings
| column | type | notes |
|---|---|---|
| project_id | FK null | |
| created_by | FK users | |
| title, agenda | | |
| location | string null | "Meeting room" in Figma |
| meeting_link | string null | |
| starts_at, ends_at | datetime | |
| reminder_minutes | smallint null | e.g. 60 = "1 hour before event" |
| transcript | longText null | |
| ai_summary | text null | |
| key_points | json null | array of strings |

### meeting_user  (attendees)
`meeting_id`, `user_id`, `response` (`pending` · `accepted` · `declined`).

### reports
`created_by`, `project_id` null, `type` (`project_progress` · `team_performance` · `tasks` · `meetings`), `period_start`, `period_end`, `notes`, `data` json (snapshot of the numbers at generation time).

### notifications
Laravel's built-in **database notifications** table (`php artisan make:notifications-table`), so every notification is a real `Notification` class (`TaskAssigned`, `MeetingScheduled`, `MeetingReminder`, `TaskOverdue`).

### activity_logs
`user_id`, `project_id` null, `subject_type` + `subject_id` (morph), `action` (e.g. `task.moved`), `description`.

### ai_messages  (Ask AI chat history)
`user_id`, `role` (`user` · `assistant`), `content` text.

---

## 3. REST API — `/api/v1`

All responses are JSON via API Resources. Lists are paginated (`?page=`, `?per_page=`).
Auth: `Authorization: Bearer <token>` (Sanctum). Errors: standard Laravel JSON (`422` validation, `403` policy, `404`).

### Auth & profile
| Method | Endpoint | |
|---|---|---|
| POST | `/auth/register` | returns token |
| POST | `/auth/login` | returns token |
| POST | `/auth/logout` | revokes current token |
| GET | `/me` | current user |
| PATCH | `/me` | update profile (incl. `locale`, `availability`) |
| POST | `/me/avatar` | upload avatar |
| PUT | `/me/password` | change password |

### Dashboard & search
| GET | `/dashboard` | stats + widgets (filters: `project_id`, `period`) |
|---|---|---|
| GET | `/search?q=` | projects, tasks, files, people |

### Members
| GET | `/users` | directory (filter `track`, `availability`) |
|---|---|---|
| GET | `/users/{user}` | profile overview, tasks, files |

### Projects
| GET | `/projects` | filter `status`, `priority`, `q` |
|---|---|---|
| POST | `/projects` | |
| GET | `/projects/{project}` | with stats |
| PATCH | `/projects/{project}` | |
| DELETE | `/projects/{project}` | soft delete |
| PUT | `/projects/{project}/wiki` | |
| GET | `/projects/{project}/members` | |
| POST | `/projects/{project}/members` | add member |
| DELETE | `/projects/{project}/members/{user}` | remove member |

### Tasks
| GET | `/tasks` | filter `project_id`, `status`, `track`, `assignee=me`, `q` — or `?view=board` grouped by status |
|---|---|---|
| POST | `/tasks` | with `assignees[]` |
| GET | `/tasks/{task}` | |
| PATCH | `/tasks/{task}` | |
| DELETE | `/tasks/{task}` | |
| PATCH | `/tasks/{task}/move` | `status` + `position` (Kanban drag & drop) |

### Files
| GET | `/files` | filter `project_id`, `q` |
|---|---|---|
| POST | `/files` | multipart, `project_id`, optional `task_id` |
| GET | `/files/{file}/download` | |
| DELETE | `/files/{file}` | |

### Chats
| GET | `/conversations` | filter `type`, `archived` |
|---|---|---|
| POST | `/conversations` | direct (`user_id`) or channel (`name`, `members[]`) |
| GET | `/conversations/{conversation}/messages` | cursor-paginated |
| POST | `/conversations/{conversation}/messages` | text and/or attachment |
| PATCH | `/conversations/{conversation}` | `is_pinned`, `is_archived` (for me) |
| POST | `/conversations/{conversation}/read` | mark as read |

### Meetings
| GET | `/meetings` | filter `range=today|week|upcoming|past`, `project_id` |
|---|---|---|
| POST | `/meetings` | with `attendees[]`, `reminder_minutes` |
| GET | `/meetings/{meeting}` | |
| PATCH | `/meetings/{meeting}` | incl. `transcript` |
| DELETE | `/meetings/{meeting}` | |
| POST | `/meetings/{meeting}/summary` | **AI** summary + key points |

### Reports
| GET | `/reports/overview` | project progress, team performance |
|---|---|---|
| GET | `/reports` | saved reports |
| POST | `/reports` | generate + save snapshot |
| GET | `/reports/{report}/export` | CSV |

### Notifications
| GET | `/notifications` | filter `unread` |
|---|---|---|
| PATCH | `/notifications/{id}/read` | |
| POST | `/notifications/read-all` | |

### Ask AI
| POST | `/ai/ask` | `{ "message": "..." }` → answer grounded in workspace data |
|---|---|---|
| GET | `/ai/messages` | chat history |
| DELETE | `/ai/messages` | clear history |

---

## 4. Authorization rules (Policies)

* **Project**: members can view; owner/manager or workspace admin can update, delete, manage members.
* **Task**: project members can view/create; assignees, project managers and admins can update/move; creator/manager/admin can delete.
* **File**: project members can view/upload; uploader, project manager or admin can delete.
* **Conversation**: participants only.
* **Meeting**: attendees and project members can view; creator/admin can update/delete.
* Users only ever see projects they belong to (admins see all).

---

## 5. Delivery plan (deadline 26 Nov 2026)

| Stage | When | Output |
|---|---|---|
| 1. Foundation | 21 Nov | migrations, models, enums, factories, seeders, auth API |
| 2. Core API | 22 Nov | projects, members, tasks/kanban, files + tests |
| 3. Collaboration API | 23 Nov | chats, meetings + AI summary, notifications, reports, Ask AI, search + tests, Postman collection |
| 4. Blade UI | 24–25 Nov | Figma screens on the same services |
| 5. SPA | 25–26 Nov | Vue app on the API (if time allows) |
| Buffer | 26 Nov | README, demo data, final review |
