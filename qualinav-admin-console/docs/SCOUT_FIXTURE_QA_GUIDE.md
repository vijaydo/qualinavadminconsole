# Scout Fixture QA Guide

Project: QualiNav Admin Console  
Phase: Scout Fixture QA-1  
Scope: Local fixture-based renderer QA only

## Purpose

These fixtures provide safe, fictional Scout output shapes for verifying Scout Preview and downstream module renderers. They are not production seed data, are not exposed through a REST endpoint, and should not be loaded by the production UI.

All fixture data is fictional and intentionally avoids PHI, patient names, MRNs, provider case details, adverse-event narratives, and peer-review case details.

## Fixture Files

| Fixture | Persona Represented | Path |
| --- | --- | --- |
| Experienced CAH / Joint Commission | Experienced Critical Access Hospital Quality Director using a Joint Commission readiness pathway. | `docs/fixtures/experienced-cah-joint-commission-scout-output.json` |
| Rural PPS / Joint Commission | Experienced Rural PPS Quality Director with Joint Commission accreditation and value-based reporting exposure. | `docs/fixtures/rural-pps-joint-commission-scout-output.json` |
| New CAH / CMS State Survey | New Critical Access Hospital Quality Director using CMS/state survey pathway and First 30 Days guidance. | `docs/fixtures/new-cah-cms-state-survey-scout-output.json` |

## Expected UI Sections

### Scout Preview

Expected to render:

- Persona context card from `request_payload_json.persona_context`
- Scout Experience Summary from `persona_experience_summary`
- Master Reporting Schedule
- Meeting & Report Flow Map
- Survey Readiness Timeline
- Recurring Clinical Monitoring
- Aggregate Data Uploads
- Routine Task Rhythm
- Active Improvement Projects
- Plans & Policies
- Priority Queue
- Learning Journey
- First 30 Days & Learning Journey for the new CAH CMS fixture
- Reminder Rules
- Warnings
- Missing inputs
- Sources

### Reporting

Expected to render:

- `master_reporting_schedule`
- Report name
- Frequency
- Due date/rule
- Owner
- Backup
- Approval
- Status

### Committees

Expected to render:

- `meeting_report_flow_map`
- Committee name
- Frequency/timing
- User role
- Reports to
- Preparation lead time
- Information flow
- Sequencing rule

### Plans & Policies

Expected to render:

- `plan_policy_tasks`
- `template_needs`
- Plan or policy name
- Current status
- Owner
- Last approved/review date
- Board approval
- Action needed
- Priority
- Plan/policy-related `priority_queue` items

### Clinical Monitoring

Expected to render:

- `recurring_clinical_monitoring`
- `aggregate_data_uploads`
- `routine_task_rhythm`
- `active_improvement_projects`
- Monitoring-related `priority_queue` items

### Settings

Expected behavior:

- Settings primarily reflects Day 0 Step 8 onboarding preferences, not Scout response JSON.
- `reminder_rules` is visible in Scout Preview.
- Settings should still render safely when a fixture Scout run exists.
- Calendar sync should continue to state that it is not active in this phase.

## Local-Only Manual Fixture Load

Do not add production fixture endpoints or public loader routes.

Use this only on a local development database. Back up the current latest Scout run first if you need to restore it.

### 1. Pick Fixture And Organization

Example:

- Organization ID: `1394`
- Fixture file: `docs/fixtures/new-cah-cms-state-survey-scout-output.json`

### 2. Prepare Request Payload JSON

The UI reads persona context from `request_payload_json`, so the manual test row should include at least:

```json
{
  "request_type": "scout_day0_workflow_generation",
  "questionnaire_version": "day0_v1",
  "persona_context": {
    "hospital_category": "critical_access_hospital",
    "payment_model": "cost_based_cah",
    "survey_pathway": "cms_state_survey",
    "accreditation_pathway": "cms_state_survey",
    "quality_director_experience": "new",
    "guidance_level": "high",
    "program_maturity": "early",
    "first_30_days_track": true
  },
  "persona_summary": "New Quality Director at a Critical Access Hospital using CMS/state survey pathway."
}
```

For best QA fidelity, copy the `persona_context` from the selected fixture into this request payload.

### 3. Insert A Completed Run

In phpMyAdmin or a local SQL client, insert a row into `wp_qualinav_scout_runs`.

Use the selected fixture JSON as the `response_json` value.

```sql
INSERT INTO wp_qualinav_scout_runs
(
  organization_id,
  request_type,
  input_data_type,
  request_payload_json,
  response_json,
  status,
  api_request_id,
  source_count,
  error_message,
  generated_by,
  created_at,
  updated_at
)
VALUES
(
  1394,
  'scout_day0_workflow_generation',
  'qualinav-day0-workflow-generation-v1',
  '{PASTE_REQUEST_PAYLOAD_JSON_HERE}',
  '{PASTE_FIXTURE_JSON_HERE}',
  'completed',
  'fixture-local-manual',
  2,
  NULL,
  NULL,
  NOW(),
  NOW()
);
```

Notes:

- Replace `wp_` if the local table prefix is different.
- Escape pasted JSON correctly for SQL, or use a SQL client field editor that can paste raw LONGTEXT safely.
- Keep the row local only.
- If several fixture rows are inserted, the latest `created_at` row is the one the UI should treat as latest.

### 4. Optional Restore

To remove fixture rows:

```sql
DELETE FROM wp_qualinav_scout_runs
WHERE api_request_id = 'fixture-local-manual'
  AND organization_id = 1394;
```

## Browser Routes To Check

Open each route after inserting the fixture row:

- `/qualinav?organization_id=1394#scout-preview`
- `/qualinav?organization_id=1394#reporting`
- `/qualinav?organization_id=1394#committees`
- `/qualinav?organization_id=1394#plans`
- `/qualinav?organization_id=1394#clinical-monitoring`
- `/qualinav?organization_id=1394#settings`

Reload the page after changing the hash directly, because this app initializes the active section from the URL hash during load.

## Pass/Fail Checklist

### Global

- [ ] Page loads without QualiNav JavaScript errors.
- [ ] No raw JSON is shown as the primary UI.
- [ ] No PHI, patient identifiers, case narratives, or peer-review case details appear.
- [ ] Cards, chips, tables, and responsive card views do not overflow horizontally.
- [ ] Admin preview banner appears for super-admin hospital preview mode.

### Scout Preview

- [ ] Status hero shows completed/ready state.
- [ ] Persona context renders from `request_payload_json`.
- [ ] All major workflow cards render.
- [ ] View Details opens readable content.
- [ ] Warnings and missing inputs render safely.
- [ ] Sources render safely.

### Reporting

- [ ] Master Reporting Schedule rows render.
- [ ] Due date/rule values are readable.
- [ ] Owner, backup, approval, and status columns/cards render.
- [ ] No empty generic placeholder appears when fixture data exists.

### Committees

- [ ] Meeting and Report Flow Map rows render.
- [ ] Information flow and sequencing details render.
- [ ] No raw objects are shown.

### Plans & Policies

- [ ] Plan/policy rows render.
- [ ] Template needs render if present.
- [ ] Plan/policy priority items render.
- [ ] No unrelated clinical-only priority items dominate the plan section.

### Clinical Monitoring

- [ ] Recurring monitoring rows/cards render.
- [ ] Aggregate uploads render.
- [ ] Routine Task Rhythm is grouped by cadence.
- [ ] Active improvement projects render.
- [ ] Monitoring-related priority gaps render.

### Settings

- [ ] Hospital Settings title renders.
- [ ] Workspace Context renders.
- [ ] Step 8 preference sections render if onboarding answers exist.
- [ ] Setup Status renders.
- [ ] Calendar sync is clearly described as not active.
- [ ] No raw IDs are exposed in the main settings UI.

## Renderer Coverage Matrix

| Fixture Section | Scout Preview | Reporting | Committees | Plans & Policies | Clinical Monitoring | Settings |
| --- | --- | --- | --- | --- | --- | --- |
| `persona_experience_summary` | Yes | No | No | No | No | No |
| `persona_context` | Yes, via request payload | Context chip only | Context chip only | Context chip only | Context chip only | Workspace context |
| `master_reporting_schedule` | Yes | Yes | No | No | No | No |
| `meeting_report_flow_map` | Yes | No | Yes | No | No | No |
| `survey_readiness_timeline` | Yes | No | No | No | No | No |
| `recurring_clinical_monitoring` | Yes | No | No | No | Yes | No |
| `aggregate_data_uploads` | Yes | No | No | No | Yes | No |
| `routine_task_rhythm` | Yes | No | No | No | Yes | No |
| `active_improvement_projects` | Yes | No | No | No | Yes | No |
| `plan_policy_tasks` | Yes | No | No | Yes | No | No |
| `template_needs` | Yes if normalized as workflow detail | No | No | Yes | No | No |
| `priority_queue` | Yes | No | No | Plan/policy filtered items | Monitoring filtered items | No |
| `learning_journey` | Yes | No | No | No | No | No |
| `first_30_days_learning_journey` | Yes for new QD fixture | No | No | No | No | No |
| `reminder_rules` | Yes | No | No | No | No | Indirect only; Settings uses Step 8 preferences |

## Known Limits

- These fixtures do not exercise a production GrapevineAI bridge call.
- Settings is not a Scout response renderer; it reflects onboarding Step 8 preferences and setup status.
- Fixture loading is manual and local-only by design.
- The fixtures are representative, not exhaustive contract tests.

## Manual QA Results

Date tested: 2026-06-18  
Organization tested: `1394`  
Tester note: Fixture rows were inserted one at a time into `wp_qualinav_scout_runs` with completed status, then removed after browser checks. No production fixture endpoint, UI loader, route, PHP, JS, or CSS change was added.

### JSON Validation

All three fixture files parsed successfully before insertion:

- `experienced-cah-joint-commission-scout-output.json`: valid JSON, 13 workflow sections.
- `rural-pps-joint-commission-scout-output.json`: valid JSON, 13 workflow sections.
- `new-cah-cms-state-survey-scout-output.json`: valid JSON, 14 workflow sections including `first_30_days_learning_journey`.

### Browser Verification Summary

The fixtures inserted successfully, but organization `1394` is currently treated by the console as `Pending Day 0 Setup`. Step progress shows Step 3 (`services_clinical_model`) still in progress at 53%, so downstream pages intentionally remain in their Day 0 gated state and do not render the inserted Scout fixture workflows.

| Fixture | Insert Result | Scout Preview | Reporting | Committees | Plans & Policies | Clinical Monitoring | Settings | Cleanup |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Experienced CAH / Joint Commission | Inserted as `fixture-experienced-cah-jc` | Partial: page shell loaded, no raw JSON, but remained on loading/gated state rather than fixture workflow cards. | Blocked by Pending Day 0 gate. | Blocked by Pending Day 0 gate. | Blocked by Pending Day 0 gate. | Blocked by Pending Day 0 gate. | Safe: settings rendered without raw JSON, but setup status remained Pending Day 0. | Completed |
| Rural PPS / Joint Commission | Inserted as `fixture-rural-pps-jc` | Partial: page shell loaded, no raw JSON, but remained on loading/gated state rather than fixture workflow cards. | Blocked by Pending Day 0 gate. | Blocked by Pending Day 0 gate. | Blocked by Pending Day 0 gate. | Blocked by Pending Day 0 gate. | Safe: settings rendered without raw JSON, but setup status remained Pending Day 0. | Completed |
| New CAH / CMS state survey | Inserted as `fixture-new-cah-cms` | Partial: page shell loaded, no raw JSON, but remained on loading/gated state rather than fixture workflow cards; First 30 Days content could not be verified because fixture rendering was gated. | Blocked by Pending Day 0 gate. | Blocked by Pending Day 0 gate. | Blocked by Pending Day 0 gate. | Blocked by Pending Day 0 gate. | Safe: settings rendered without raw JSON, but setup status remained Pending Day 0. | Completed |

### Responsive and Console Notes

- Narrow viewport checks for Scout Preview and Clinical Monitoring showed no horizontal overflow in the gated state.
- No QualiNav JavaScript errors were observed during these checks.
- The known unrelated Elementor `elementorFrontendConfig` console error should continue to be ignored for this QA pass.

### Result

Manual browser QA is not complete for renderer coverage. The fixture rows are valid and insert cleanly, but organization `1394` does not currently satisfy the Day 0 completion condition required for the fixture workflows to appear in Scout Preview and downstream modules.

Recommended next QA step:

1. Use a local organization whose Day 0 setup is fully submitted, or complete Step 3 for organization `1394` through the normal UI.
2. Reinsert the fixtures one at a time.
3. Re-run the route checks for Scout Preview, Reporting, Committees, Plans & Policies, Clinical Monitoring, and Settings.

## Manual QA Results - QA-3 Attempt

Date tested: 2026-06-18  
Organization tested: `1394`

### Gate Resolution

- No local organization was found with all Day 0 sections already submitted/complete.
- The fallback path was used for organization `1394`.
- Step 3 (`services_clinical_model`) was completed using safe, fictional structural/service values.
- Day 0 progress now shows all 8 sections complete at 100%.
- Final setup submission was attempted through the final wizard step; a normal Scout run was created/completed afterward.

### Fixture Validation and Cleanup

- All three fixture JSON files validated successfully before insertion.
- The Experienced CAH / Joint Commission fixture was inserted as `fixture-experienced-cah-jc` and became the latest completed Scout run.
- QA was stopped before testing the remaining two fixtures because a direct route hydration/rendering defect blocked meaningful renderer coverage.
- The inserted fixture row was deleted.
- `remaining_fixture_rows = 0`.

### Blocker Found After Gate Was Satisfied

Even after Day 0 reached 100% for organization `1394` and the fixture was the latest completed Scout run, direct browser loads of downstream routes still rendered a stale/generic state:

- `/qualinav?organization_id=1394#reporting`
- `/qualinav?organization_id=1394#committees`
- `/qualinav?organization_id=1394#plans`
- `/qualinav?organization_id=1394#clinical-monitoring`
- `/qualinav?organization_id=1394#settings`

Observed behavior:

- Day 0 route correctly showed `QN Phase 5E QA C - New CAH CMS`, `100% complete`, and Step 3 complete.
- Downstream routes rendered `Hospital workspace`, `Hospital type not set`, `Service model not set`, and `Scout: Pending Day 0`.
- Scout Preview direct route remained on `Loading Scout preview...`.
- No raw JSON or PHI appeared.
- No QualiNav JavaScript error was observed; the known unrelated Elementor `elementorFrontendConfig` error remained present.

### QA-3 Result

Blocked. The Day 0 gate was resolved, but direct module route hydration/state still prevented fixture renderer coverage. Do not mark fixture renderer QA complete until this route/state defect is fixed and all three fixtures render their workflow sections in the intended modules.

## QA-PlansPoliciesRenderer-1 Follow-Up

Date tested: 2026-06-18  
Organization tested: `1394`

### QA-4 Stop Reason

QA-4 was rerun after the route hydration fix and stopped on the Experienced CAH / Joint Commission fixture because the Plans & Policies page exposed a real renderer defect:

- `template_needs` did not render, so the summary showed `Templates Needed 0` even though the fixture contained `Board quality report template` and `Survey readiness checklist`.
- A clinical-only priority item, `Confirm blood usage review ownership`, appeared in Plans & Policies even though it belongs in Clinical Monitoring.

### Fix Proof

- Fixed in `QA-PlansPoliciesRenderer-1`.
- Proof check inserted `fixture-experienced-cah-jc` for organization `1394`.
- Confirmed `Templates Needed` count is `2`.
- Confirmed `Board quality report template` and `Survey readiness checklist` render.
- Confirmed `Confirm blood usage review ownership` is filtered out of Plans & Policies.
- Confirmed valid plan/policy priority `Refresh QAPI Plan annual review evidence` remains.
- Confirmed no raw JSON, no unsafe PHI text, no QualiNav JavaScript errors, and no horizontal overflow.
- Deleted fixture rows and confirmed `remaining_fixture_rows = 0`.

### Result

Plans & Policies renderer defect fixed. Full three-fixture QA-4 rerun remains pending.

## QA-MobileShellOverflow-1 Follow-Up

Date tested: 2026-06-18  
Organization tested: `1394`

### QA-4R Stop Reason

QA-4R was rerun after the Plans & Policies renderer fix and stopped on the Experienced CAH / Joint Commission fixture because a narrow viewport shell overflow was found:

- At approximately `415px` viewport width, the document shell widened to roughly `470px`.
- The overflowing elements were the global QualiNav shell/sidebar/main/topbar layers, not Scout fixture data.
- Clinical Monitoring rendered fixture content on desktop, but the required narrow viewport check failed.

### Fix Proof

- Fixed in `QA-MobileShellOverflow-1`.
- Proof check inserted `fixture-experienced-cah-jc` for organization `1394`.
- Checked `#clinical-monitoring`, `#scout-preview`, `#reporting`, `#committees`, `#plans`, `#settings`, and `#day-0-setup` at approximately `430px` and `390px`.
- Confirmed document width no longer exceeds viewport width on those routes.
- Confirmed Clinical Monitoring fixture content still renders at narrow width.
- Confirmed no raw JSON, no unsafe PHI text, and no QualiNav JavaScript errors.
- Deleted fixture rows and confirmed `remaining_fixture_rows = 0`.

### Result

Mobile shell overflow fixed. Full three-fixture QA-4R rerun remains pending.
