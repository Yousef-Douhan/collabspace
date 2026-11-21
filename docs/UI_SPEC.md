# CollabSpace — UI spec (from the Figma file "Collabpspace, Team C")

Read from the **UI Design** and **Design system** pages and from the designer's review comments.
All screens are 1440 × 1024 desktop frames.

## Global look

| Token | Value (approx. from the file) |
|---|---|
| Page background ("BG color") | `#F6F8FA` |
| Cards | white, radius ~12px, very light border, no heavy shadows |
| Primary | bright blue ≈ `#0B5FFF` (active nav pill, primary buttons, progress bars, links) |
| Text | near-black navy for headings, slate grey for secondary text |
| Support colours | purple, magenta, pink, green, red, amber — used for chips and status |
| Priority chips | High = light red bg / red text · Medium = light amber / amber · Low = light green / green |
| Font | Inter-style sans-serif |

## Layout shell (every page)

- **Top bar, not a sidebar**: `Collabspace` wordmark · pill navigation
  `Dashboard · Projects · Tasks · Chats · Meetings · Reports` (active pill = solid primary, white text) ·
  search field · notification bell (red dot) · user name + track (e.g. "Mohamed Salah / UI/UX") + avatar.
- Content area on `#F6F8FA`, page title (large, bold) top-left, primary action button top-right
  (`Create Task +`, `Add Project +`, `Add Meeting +`, `Upload File +`).
- Overlays (modals): Create Task, New Project, Add Meeting, My task, Project, Teams, Duration,
  Language, Notification, Search Bar.

## Screens

### Sign in / Sign up
Centered white card on grey; title "Sign In", subtitle "Welcome back, you've been missed!";
"Sign With Google" button, "OR" divider, Email + Password fields with icons, "Remember Me",
"Forgot Password?", full-width primary "Log in", "Don't have an account yet? Sign Up".
Language select (English ▾) top-right, wordmark top-left.

### Dashboard
1. Four stat cards: **Pending Tasks**, **In Progress**, **Completed** (count, "+9% from last month",
   thin coloured progress bar with "32/94") and **Completion Rate** (percentage + gauge).
2. **Project overview** line chart (filters: All Projects ▾, This Year ▾) · **Active Projects**
   (name, coloured progress bar, "Task: 100/140") · **Recent Files** (icon, name, "By …• time", download).
3. **Tasks** (title, priority chip, due, % with progress bar) · **Teams** (filter by track; member,
   "Leader" badge, progress bar, "12/14" tasks) · **Upcoming meetings** (title, date, avatars, time)
   · **Ask AI** widget (input + send).

### All Projects
Table: Project Name · Start Date · Deadline · Status (coloured text) · Files ("22 Files") ·
Teams (stacked avatars) · Priority (coloured). Search, "All Projects ▾" and "Newest ▾" filters.

### Project (All Projects / Alpha)
Breadcrumb, name + "42% Completed" chip, due-date card (start / deadline + bar).
Tabs **Overview · Tasks · Teams · Files**.
Overview = Description · Project overview chart · **Project Progress** gauge (78%, pending /
in-progress / completed counts) · Team Members (filter by track, "Leader" badge) ·
Product Details (type, start date, deadline, priority, total tasks).

### Tasks (Kanban)
Search, "Project ▾" and "My Task ▾" filters, `Create Task +`.
Four columns with tinted headers: **To-do** (grey) · **In Progress** (light blue) ·
**In Review** (light pink) · **Completed Tasks** (light green); each has a dashed "Add Task +".
Card: title, priority chip, description, progress bar (in progress), **reviewer** line
(in review), assignee avatars, **comment count**, due date.

### Chats
Left panel: "Chats" + search, tabs **Inbox** / **Project Chats**, "Recent" list
(project channels show project name + channel name + last message; direct chats show avatar,
name, last message, time). Right: "Select Chat" empty state / conversation.

### Meetings
Month header (‹ December 2025 ›), "This Week ▾", **weekly calendar grid** (time rows ×
day columns, today highlighted) with meeting cards (title, time, avatars, "Join +").

### Meeting details (Meetings / join Meeting)
Title, project, attendee avatars, date, time. Video/thumbnail area.
Tabs **AI Summary · Note · Comments**; "AI Generate Summary" with **Key Points** bullets.
Right: **Meeting Transcripts** (language select, search, timestamped lines with speaker avatar).

### Files
Breadcrumb, "Search a File", "All Projects ▾". **Recent Files** (3 cards) and **All Files** grid
(file icon, name, "By …", "6.4 MB • 1h ago", download).

### Reports
Three KPI cards: **Task Completion Rate**, **Projects Completion Rate**, **Teams Activity Rate**
(with "% from last month"). Charts: **Task Completion Time vs Deadline** (bars per month),
**Projects Progress** (area), **Worker Activity Rate** (line, Monthly ▾).
**Submit a detailed report** form: report type, note, start date, end date, Submit.

### Ask AI
"Collabspace AI" — empty state "Hi There 👋 / Start chatting with Collabspace",
large input "ask me anything about…", attach (+), mic, **Submit**. A chat variant exists.

### Profile
Avatar, name, job title, joined date; cards **Completed Tasks** and **Hours Worked**.
Tabs **Overview · Tasks · Files · Settings**. Overview = About + Recent Activities (icon, text, time ago).

## Designer review comments (to satisfy)

| # | Comment | Covered by |
|---|---|---|
| 73 | Add message read receipts to every chat | `last_read_at` per participant → expose "seen" |
| 80 | Ability to edit messages | add `PATCH /messages/{id}` (sender only) |
| 82 | Add the team name for each user | `track` shown next to every name |
| 84 / 86 | Where is the meeting link / meeting files | `meeting_link` + meeting attachments |
| 85 | Status of member | `availability` badge |
| 77 / 88 | Filter tasks (incl. by failed/overdue) | `?overdue=1`, status, project, assignee filters |
| 87 | Add project name | project name on task cards / meetings |
| 79 | Meeting in wrong time place | calendar uses real `starts_at` |
