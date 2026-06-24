# Qualinav QI Projects — Development Spec

Reference document for the `qualinav-qi-projects` WordPress plugin. Captures every architectural decision, the data model, the canvas structure, and the phased build plan before further development.

---

## 1. Purpose

Replace the current QI projects flow on qualinav.com (Elementor pages + client-side DOM scraping into `post_meta` JSON blobs) with a structured, multi-tenant, admin-editable canvas builder owned by a dedicated plugin.

A "QI Project" is a 4-tab journey: **Improvement Canvas → Matrix Diagram → Gameplan Canvas → Commit**, with rich card-based collaboration, scoring, and a final "Mark Complete" step that finalizes the charter.

---

## 2. Constraints

| Constraint | Why |
|---|---|
| **No Formidable Forms** | Today's flow is broken specifically because Formidable + Elementor stitching is fragile. Clean break. |
| **No Elementor dependency** | Same reason. No widgets, no template references, no DOM scraping. |
| **Custom DB tables (`wp_qi_*`)** | The platform has an AI brain. `post_meta` JSON blobs are not queryable. Structured tables with FKs and indexes give the AI usable data. |
| **Hard multi-tenant org scoping** | Healthcare quality platform; orgs (hospitals) must NEVER see each other's data. |
| **Native WordPress only for UI** | Plugin renders its own templates and admin UI. No reliance on page builders. |

---

## 3. Architecture Decisions

### 3.1 Scope: Level C (full template builder)

Admins can create multiple charter types, custom tabs, custom field types, drag-drop builder, with versioning. The PDF "Improvement Charter" is one template among potentially many.

**Implication:** Charter UI must be a JSON-driven renderer reading from `wp_qi_template_versions`, not hardcoded HTML.

### 3.2 Hybrid: CPT + custom tables

- `qi_project` Custom Post Type as a thin WP-side shell — gives us auth, REST plumbing, author tracking, and a stable WP-side ID for the AI brain.
- All structured data lives in `wp_qi_*` tables.
- Linked via `wp_qi_projects.post_id`.

### 3.3 Multi-tenant isolation rules

- Every project-scoped table carries `org_id BIGINT UNSIGNED NOT NULL` indexed.
- Every read/write filtered by current user's `org_id` at the data-access (repository) layer — NOT scattered across callers.
- Templates are per-org. Org A's "Improvement Charter" is independent of Org B's.
- A "global starter library" (`org_id = NULL`) holds platform-provided templates that orgs clone on signup.
- Cross-org access only for a platform-super-admin role (Heartland staff, future cross-org benchmarking).
- AI brain queries inherit the org filter — never sees data outside the requester's org.

### 3.4 My Org table (stubbed)

The real `wp_my_org` table will live in a future "My Org" plugin. To unblock QI development:
- Stub `wp_qi_orgs` and `wp_qi_org_members` inside this plugin temporarily.
- Minimal columns. Easy to migrate out when the real My Org plugin ships (rename + drop the stubs).

### 3.5 Versioning: snapshot-on-publish

When an admin edits a template, edits are saved to a draft. On publish, a new immutable `wp_qi_template_versions` row is written. Existing projects keep their original `template_version_id` — they don't break or auto-migrate.

### 3.6 PDSA cycles

Deferred to v2. The 4-tab flow (Improvement → Matrix → Gameplan → Commit) is the v1 lifecycle. "Mark Complete" finalizes a project.

### 3.7 AI brain interop

The AI brain does NOT live in `qualinav-roundtable`. Its exact location is TBD. This plugin exposes:
- REST endpoints (`/wp-json/qualinav-qi/v1/...`) for read/write
- Webhooks on project create / update / complete
- All scoped by org_id automatically

The AI brain itself is built elsewhere; this plugin just exposes a clean interop surface.

---

## 4. The QI Project Journey (4 Tabs)

### Tab 1: Improvement Canvas (9 steps)

Helper text and field types for each step:

| Step | Title | Field type | Helper |
|---|---|---|---|
| 1 | What have we done before? | `card_list` | "What work have we done in this space? What is work we've done that is comparable? What prior work should we look to or be aware of as we hone in on our focus area? (Add as many initiatives or examples as you have!)" |
| 2 | Scope | `card_list_2col` (In Scope \| Out of Scope) | "Think of this as an exercise in setting boundaries! Within our focus area-what is in scope of this project and what is out of scope? Out of scope activities or focuses don't mean they're things you'll never do, but could be things you can't do right now." |
| 3 | What are we Trying to Accomplish? | `card_slots_named` (Population, Goal, Time, Location, Guidance) | "Your aim statement should be explicit, clear, unambiguous, precise, and plain. An aim statement should include the following essential components: Population, Goal, Time Expectation, Location, Guidance" |
| 4 | Aim Statement | `single_textarea` | "Using the components you defined in Step 3, write your aim statement as a clear and concise sentence... use as a 'North Star' for your team moving forward." |
| 5 | How Will We Measure This? | `card_slots_named` (Outcome, Process, Tracking Tool) | "Strong measurement requires thinking through three key components: Outcome Measure, Process Measure, Tracking Tool" |
| 6 | What Will We Do Differently? | `card_slots_numbered` (Idea 1–5, fixed) | "A change idea is an actionable, specific idea for changing a process. Change ideas come from research, best practices..." |
| 7 | Supports | `card_list` | "What supports do we have going into this work that we can utilize or will help us in this effort?" |
| 8 | Barriers | `card_list` | "What barriers exist that might make this work challenging that we should be aware of or work to mitigate?" |
| 9 | Team Members | `card_list` | "Who will be essential members of this team? Think about both specific people and departments/roles." |

### Tab 2: Matrix Diagram

Pulls all 5 ideas from Tab 1 Step 6 (cross-canvas reference). For each idea, 4 sliders (1–5 scale, default 5):

1. The solution can be accomplished in 90 days
2. There is the will to support this solution
3. The solution is within our control
4. We have a sponsor and buy-in for this solution

`computed_sum` field: Cumulative Score = sum of 4 sliders → X/20.

Per-idea Submit button (row-level save). "Pillar" + "Focus Area" appear on the visual diagram — these are project-level metadata fields.

### Tab 3: Gameplan Canvas (7 steps)

| Step | Title | Field type | Notes |
|---|---|---|---|
| 1 | Our Targets | Mixed: AIM TO EXECUTE (mirrored from Tab 1 likely Population), OTHER OBJECTIVES + OTHER STAKEHOLDERS as `card_list_2col` | |
| 2 | Set Your Timeline | 4 columns of `single_textarea` (Phase 1–4 timelines) | |
| 3 | Phase 1 Action Steps and Supports | `section_repeat` block — 3 stacked card_lists: ACTION STEPS, SUPPORT NEEDED, TEAM MEMBERS | The block structure repeats once per phase |
| 4 | Phase 2 Action Steps and Supports | (same as Step 3) | |
| 5 | Phase 3 Action Steps and Supports | (same as Step 3) | **Bug:** currently labeled "Phase 4" — fix |
| 6 | Phase 4 Action Steps and Supports | (same as Step 3) | |
| 7 | Challenges | `card_list` | |

### Tab 4: Commit (aggregation + final submit)

| Section | Content | Origin |
|---|---|---|
| What are we trying to accomplish? | AIM STATEMENT | Mirrored from Tab 1 Step 4 (also editable) |
| How are we going to measure this? | OUTCOME MEASURES + PROCESS MEASURES (`measure_row` rows: description \| current \| target) + TRACKING TOOLS card list | NEW current/target fields, descriptions mirrored from Tab 1 Step 5 |
| What changes will we make? | 5 Ideas displayed with Matrix Score; SUPPORTS \| BARRIERS; IN SCOPE \| OUT OF SCOPE | Aggregated from Tabs 1 + 2 |
| Team | EXECUTIVE SPONSORS (NEW); TEAM MEMBERS | Sponsors are native to Commit; Team mirrored from Tab 1 Step 9 |
| Team Submission | "Mark Complete and Generate Report" button | Terminal action; finalizes project, fires webhooks |

---

## 5. Field Types Catalog

The template builder must support these 12 types:

| Type | Where used |
|---|---|
| `single_text` | (anticipated, e.g. project title, pillar, focus_area) |
| `single_textarea` | Aim Statement, Phase Timelines |
| `card_list` | Most steps — free-form list of cards |
| `card_list_2col` | In/Out Scope, Supports/Barriers, Other Objectives/Stakeholders |
| `card_slots_named` | Population/Goal/Time/Location/Guidance; Outcome/Process/Tracking Tool |
| `card_slots_numbered` | Ideas 1–5 (fixed N) |
| `measure_row` | description \| current \| target (repeatable rows) |
| `slider_1_5` | Matrix Diagram criteria scoring |
| `computed_sum` | Cumulative Score from sliders |
| `field_reference` | Cross-canvas data pull (Commit tab, Matrix's idea list, Gameplan's mirrors) |
| `section_repeat` | Phases 1–4 in Gameplan |
| `submit_button` | Mark Complete (terminal action) |

Every card-bearing type carries `created_by_user_id` + `created_at` + `updated_at` + edit/delete actions per card.

---

## 6. Database Schema

All tables prefixed `wp_qi_` (via `$wpdb->prefix . QUALINAV_QI_TABLE_PREFIX`).

### 6.1 `wp_qi_orgs` (stub)
```
id BIGINT UNSIGNED PK
name VARCHAR(255)
slug VARCHAR(190) UNIQUE
owner_user_id BIGINT UNSIGNED NULL
status VARCHAR(20) DEFAULT 'active'
created_at, updated_at DATETIME
```

### 6.2 `wp_qi_org_members` (stub)
```
id BIGINT UNSIGNED PK
org_id BIGINT UNSIGNED
user_id BIGINT UNSIGNED
role VARCHAR(40) DEFAULT 'member'
created_at DATETIME
UNIQUE (org_id, user_id)
```

### 6.3 `wp_qi_templates`
```
id BIGINT UNSIGNED PK
org_id BIGINT UNSIGNED NULL    -- NULL = global starter library
name VARCHAR(255)
slug VARCHAR(190)
description TEXT
status VARCHAR(20) DEFAULT 'draft'  -- draft, published, archived
current_version_id BIGINT UNSIGNED NULL
created_by_user_id BIGINT UNSIGNED NULL
created_at, updated_at DATETIME
KEY (org_id, slug), KEY status
```

### 6.4 `wp_qi_template_versions` (snapshot-on-publish)
```
id BIGINT UNSIGNED PK
template_id BIGINT UNSIGNED
version_number INT UNSIGNED
structure_json LONGTEXT      -- the canonical template definition
published_at DATETIME NULL
created_by_user_id BIGINT UNSIGNED NULL
created_at DATETIME
UNIQUE (template_id, version_number)
```

### 6.5 `wp_qi_projects`
```
id BIGINT UNSIGNED PK
org_id BIGINT UNSIGNED               -- multi-tenant scope
template_version_id BIGINT UNSIGNED  -- snapshotted at create
post_id BIGINT UNSIGNED NULL         -- hybrid CPT shell link
title VARCHAR(255)
status VARCHAR(40) DEFAULT 'draft'   -- draft, in_progress, completed, archived
pillar VARCHAR(190) NULL
focus_area VARCHAR(190) NULL
owner_user_id BIGINT UNSIGNED
completed_at DATETIME NULL
created_at, updated_at DATETIME
KEY (org_id), (org_id, status), (template_version_id), (owner_user_id), (post_id)
```

### 6.6 `wp_qi_project_fields` (single-value fields)
```
id BIGINT UNSIGNED PK
project_id BIGINT UNSIGNED
org_id BIGINT UNSIGNED
field_path VARCHAR(255)              -- e.g. "improvement_canvas.aim_statement"
field_type VARCHAR(40)               -- text, textarea, computed, etc.
value_text LONGTEXT
value_number DECIMAL(20,4)
value_json LONGTEXT
updated_by_user_id BIGINT UNSIGNED
created_at, updated_at DATETIME
UNIQUE (project_id, field_path)
```

### 6.7 `wp_qi_project_cards` (cards across all card-bearing field types)
```
id BIGINT UNSIGNED PK
project_id BIGINT UNSIGNED
org_id BIGINT UNSIGNED
field_path VARCHAR(255)              -- e.g. "improvement_canvas.scope"
slot_key VARCHAR(100) NULL           -- 'in_scope' | 'out_of_scope' | 'idea_1' | 'population' | NULL
position INT UNSIGNED DEFAULT 0
content LONGTEXT
created_by_user_id BIGINT UNSIGNED
updated_by_user_id BIGINT UNSIGNED
created_at, updated_at DATETIME
KEY (project_id, field_path), (project_id, field_path, slot_key), (org_id), (created_by)
```

### 6.8 `wp_qi_project_measures`
```
id BIGINT UNSIGNED PK
project_id BIGINT UNSIGNED
org_id BIGINT UNSIGNED
measure_type VARCHAR(20)             -- 'outcome' | 'process'
position INT UNSIGNED DEFAULT 0
description LONGTEXT
current_value VARCHAR(190)
target_value VARCHAR(190)
created_by_user_id BIGINT UNSIGNED
updated_by_user_id BIGINT UNSIGNED
created_at, updated_at DATETIME
KEY (project_id, measure_type), (org_id)
```

### 6.9 `wp_qi_project_idea_scores` (matrix sliders)
```
id BIGINT UNSIGNED PK
project_id BIGINT UNSIGNED
org_id BIGINT UNSIGNED
idea_card_id BIGINT UNSIGNED         -- FK -> wp_qi_project_cards.id
criterion_key VARCHAR(60)            -- achievable_in_90_days, will_to_support, within_control, sponsor_buyin
score TINYINT UNSIGNED               -- 1-5
scored_by_user_id BIGINT UNSIGNED
created_at, updated_at DATETIME
UNIQUE (idea_card_id, criterion_key)  -- last-write-wins for v1
```

### 6.10 `wp_qi_activity_log` (audit trail; AI brain context)
```
id BIGINT UNSIGNED PK
org_id BIGINT UNSIGNED
project_id BIGINT UNSIGNED NULL
user_id BIGINT UNSIGNED
action VARCHAR(60)                   -- 'project.created', 'card.added', 'project.completed', etc.
target_type VARCHAR(40)              -- 'card', 'measure', 'project', 'score'
target_id BIGINT UNSIGNED NULL
payload_json LONGTEXT
created_at DATETIME
KEY (org_id), (project_id), (user_id), (action), (created_at)
```

---

## 7. Cross-Canvas Data Flow

- **Tab 1 (Improvement Canvas)** is the foundation; most data originates here.
- **Tab 2 (Matrix)** reads: Ideas (Tab 1 Step 6).
- **Tab 3 (Gameplan)** reads: probably Aim Statement + Population (TO CONFIRM).
- **Tab 4 (Commit)** reads: Aim, Outcome/Process/Tracking descriptions, Ideas + Matrix Scores, Supports, Barriers, In/Out Scope, Team Members.
- **Tab 4 adds new native fields** not present elsewhere: measure Current + Target columns, Executive Sponsors.

The `field_reference` field type in the template definition expresses these cross-tab pulls.

---

## 8. Bugs in Current (Live) Implementation — Do NOT Carry Forward

1. **Gameplan Steps 5 & 6 both labeled "Phase 4 Action Steps and Supports"** — should be Phase 3 and Phase 4
2. **Matrix Diagram wording mismatch** — visual diagram says "There is the will to **implement** this solution"; slider says "There is the will to **support** this solution"
3. **Commit Section 2 helper text is wrong placeholder** — currently reads Gameplan Step 1's helper
4. **Commit Section 4 helper text is wrong placeholder** — currently reads a Gameplan phase-step helper
5. **Step 6 idea slot count** is hard-coded to 5 — confirm if this is intentional or just no one's added a 6th yet (TO CONFIRM with user)

---

## 9. Phased Build Plan

| Phase | Deliverables | Status |
|---|---|---|
| **1** | Plugin scaffold + DB schema (all 10 `wp_qi_*` tables + activation/migration + CPT shell) | **DONE** (awaiting wp-admin activation) |
| **2** | Data access layer (org-scoped repositories) + REST endpoints scaffolded | Next |
| **3** | Front-end charter renderer (JSON-driven, all 12 field types) + seed default Improvement Charter template | |
| **4** | Admin template builder UI (drag/drop, snapshot-on-publish versioning) | |
| **5** | Migration of existing `qi_project` posts + AI interop hooks (webhooks on create/update/complete) | |

---

## 10. Open Questions (To Lock Before Phases 3+)

1. **Step 6 ideas:** hard fixed at 5, or just no one's added a 6th yet?
2. **Gameplan's "Aim to Execute":** editable here or read-only mirror from Tab 1?
3. **Gameplan's "Change Idea" focus:** auto-picked from highest Matrix score, user-chosen, or always Idea 1?
4. **Matrix scoring:** per-user-attributable history (who scored what when) or last-write-wins?
5. **AI brain location:** which plugin / external service does it live in? Determines the exact webhook/REST contract.
6. **Multi-org users:** can one user belong to multiple orgs (with an active-org switcher), or strictly one user → one org?
7. **Migration of existing `qi_project` posts:** best-effort remap of JSON section blobs to new structured fields, or archive as `legacy` and start fresh?

None of these block Phases 1–2. Items 1–4 must be resolved before Phase 3 (renderer). Items 5–7 must be resolved before Phase 5.

---

## 11. Plugin File Structure (Current)

```
qualinav-qi-projects/
├── qualinav-qi-projects.php          # Main plugin file (header, constants, hooks)
├── includes/
│   ├── class-activator.php            # dbDelta migrations for all 10 tables
│   ├── class-deactivator.php          # Cleanup transients (does not drop tables)
│   ├── class-plugin.php               # Bootstrap; runs migrations on version bump
│   ├── class-cpt.php                  # qi_project CPT (hybrid shell)
│   ├── repositories/                  # (to be added Phase 2)
│   ├── rest/                          # (to be added Phase 2)
│   └── helpers/                       # (to be added Phase 2)
├── assets/
│   ├── css/                           # (Phase 3+)
│   └── js/                            # (Phase 3+)
├── templates/                         # (Phase 3 — front-end charter renderer)
└── seeds/                             # (Phase 3 — default Improvement Charter template JSON)
```

---

## 12. Constants Reference

Defined in `qualinav-qi-projects.php`:

| Constant | Value |
|---|---|
| `QUALINAV_QI_VERSION` | `0.1.0` |
| `QUALINAV_QI_DB_VERSION` | `1` (bump to trigger re-run of `Activator::activate()`) |
| `QUALINAV_QI_PLUGIN_FILE` | `__FILE__` |
| `QUALINAV_QI_PLUGIN_DIR` | `plugin_dir_path( __FILE__ )` |
| `QUALINAV_QI_PLUGIN_URL` | `plugin_dir_url( __FILE__ )` |
| `QUALINAV_QI_TABLE_PREFIX` | `'qi_'` (combines with `$wpdb->prefix`) |

Bumping `QUALINAV_QI_DB_VERSION` and reloading any admin page triggers `Activator::activate()` again — `dbDelta()` is idempotent, so it adds new tables/columns without destroying data.
