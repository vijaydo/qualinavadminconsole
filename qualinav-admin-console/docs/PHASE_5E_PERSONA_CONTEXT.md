# Phase 5E Persona Context Normalization and Scout Output Alignment

Project: QualiNav Admin Console  
Build Rank: 26  
Phase: 5E - Persona Context Normalization and Scout Output Alignment  
Plugin version: 0.1.4

## Summary

Phase 5E implements the Phase 5D audit recommendations without adding a local workflow generator. GrapevineAI Scout remains the source of generated recommendations. The WordPress plugin now sends Scout a normalized `persona_context`, a human-readable `persona_summary`, and a broader `requested_outputs` list that includes both existing workflow keys and sample-package-style keys.

The Scout Preview UI now understands the sample package output vocabulary, displays cards in a preferred order, uses dedicated labels/icons, and shows a safe persona context panel for each run.

## What Was Added

- Plugin version bumped to `0.1.4`.
- Optional defensive `wp_organizations.payment_model` migration support.
- `QN_Organizations::get_payment_model_options()`.
- `QN_Scout::build_persona_context($organization_id, $answer_map, $user_id)`.
- `persona_context` added to Day 0 Scout payload.
- `persona_summary` added to Day 0 Scout payload.
- Sample package output keys added to `requested_outputs`.
- PHP labels for sample package response keys.
- Safe run metadata returned to the frontend:
  - `persona_context`
  - `persona_summary`
  - `request_meta`
- Scout Preview persona context panel.
- JS labels, icons, and preferred ordering for package-style output sections.

## Persona Context Fields

`persona_context` includes:

- `hospital_category`
- `payment_model`
- `accreditation_pathway`
- `survey_pathway`
- `accreditation_body`
- `quality_director_experience`
- `new_director`
- `first_30_days_track`
- `learning_journey_enabled`
- `program_maturity`
- `preferred_guidance_level`

## Derivation Rules

| Field | Rule summary |
| --- | --- |
| `hospital_category` | CAH if organization hospital type is `critical_access_hospital` or Day 0 CAH answer is yes; rural PPS if rural hospital plus PPS payment model; acute if acute care hospital; otherwise hospital type or `unknown`. |
| `payment_model` | Uses organization `payment_model` if present; CAH if CAH; PPS if answers mention PPS/IQR/OQR/Promoting Interoperability/value-based/HCAHPS/eCQM; otherwise `unknown`. |
| `accreditation_body` | Normalizes Day 0 accrediting body to `joint_commission`, `dnv`, `hfap`, `cihq`, `none`, or `unknown`. |
| `survey_pathway` | `accreditation` when accredited with a known accrediting body; `cms_state_survey` when CMS/state pathway fields are present; otherwise `unknown`. |
| `accreditation_pathway` | Joint Commission + Accreditation 360 mention becomes `joint_commission_accreditation_360`; otherwise known accreditor value, CMS/state-only, or `unknown`. |
| `quality_director_experience` | `new` from new-to-role answer or role start date under one year; `developing` for 1-3 year signals or developing confidence; `experienced` for 3+ year or high confidence; otherwise `unknown`. |
| `new_director` | True when experience is `new`. |
| `first_30_days_track` | True when explicitly selected, or when new director and not explicitly declined. |
| `learning_journey_enabled` | True for new director, developing confidence, or provided learning preference. |
| `program_maturity` | `thin` when multiple required plans/processes are missing; `partial` when some processes exist or gaps are present; `mature` when plans, policy cycle, dashboard, monitoring, and QI projects are functioning; otherwise `unknown`. |
| `preferred_guidance_level` | `guided` for new director or First 30 Days; `standard` for developing/partial; `light` for experienced without First 30 Days; otherwise `standard`. |

## Payload Example

```json
{
  "source": "qualinav_admin_console",
  "request_type": "scout_day0_workflow_generation",
  "questionnaire_version": "day0_v1",
  "organization_id": 123,
  "organization_name": "Example Hospital",
  "hospital_type": "critical_access_hospital",
  "service_model": "system_owned",
  "persona_context": {
    "hospital_category": "critical_access_hospital",
    "payment_model": "cah",
    "accreditation_pathway": "joint_commission",
    "survey_pathway": "accreditation",
    "accreditation_body": "joint_commission",
    "quality_director_experience": "experienced",
    "new_director": false,
    "first_30_days_track": false,
    "learning_journey_enabled": false,
    "program_maturity": "mature",
    "preferred_guidance_level": "light"
  },
  "persona_summary": "Experienced Quality Director at a Critical Access Hospital on a Joint Commission pathway.",
  "constraints": {
    "no_phi": true,
    "do_not_include_patient_names": true,
    "do_not_include_mrns": true,
    "do_not_include_provider_case_details": true,
    "do_not_include_incident_narratives": true,
    "do_not_include_peer_review_details": true
  }
}
```

## Requested Outputs

The request keeps the existing keys:

- `reporting_schedule`
- `committee_flow_map`
- `survey_readiness_timeline`
- `plan_policy_tasks`
- `clinical_monitoring_tasks`
- `qi_project_milestones`
- `external_contact_directory`
- `regulatory_monitoring_preferences`
- `learning_journey`
- `reminder_rules`

It also asks for sample package keys:

- `master_reporting_schedule`
- `meeting_report_flow_map`
- `active_monitoring_improvement_tasks`
- `recurring_clinical_monitoring`
- `aggregate_data_uploads`
- `routine_task_rhythm`
- `active_improvement_projects`
- `priority_queue`
- `first_30_days_learning_journey`
- `persona_experience_summary`

## Response Alias Map

| Key | UI label |
| --- | --- |
| `persona_experience_summary` | Scout Experience Summary |
| `master_reporting_schedule` | Master Reporting Schedule |
| `reporting_schedule` | Reporting Schedule |
| `meeting_report_flow_map` | Meeting & Report Flow Map |
| `committee_flow_map` | Committee Flow Map |
| `survey_readiness_timeline` | Survey Readiness Timeline |
| `active_monitoring_improvement_tasks` | Active Monitoring & Improvement Tasks |
| `recurring_clinical_monitoring` | Recurring Clinical Monitoring |
| `clinical_monitoring_tasks` | Clinical Monitoring |
| `aggregate_data_uploads` | Aggregate Data Uploads |
| `routine_task_rhythm` | Routine Task Rhythm |
| `active_improvement_projects` | Active Improvement Projects |
| `qi_project_milestones` | QI Project Milestones |
| `priority_queue` | Priority Queue |
| `plan_policy_tasks` | Plans & Policies |
| `regulatory_monitoring_preferences` | Regulatory Monitoring |
| `external_contact_directory` | External Contacts |
| `first_30_days_learning_journey` | First 30 Days & Learning Journey |
| `learning_journey` | Learning Journey |
| `reminder_rules` | Reminder Rules |

## UI Behavior

When a Scout run exists, the Scout Preview displays:

- Run status, generation timestamp, and source count.
- Warnings and missing inputs.
- Safe persona context panel:
  - Persona
  - Hospital category
  - Payment model
  - Survey pathway
  - Accreditation pathway
  - Guidance level
  - Program maturity
  - First 30 Days track
- Admin-only safe metadata:
  - request type
  - input data type
  - status
  - source count
- Ordered Scout preview cards with dedicated labels/icons.
- Source list when returned by Scout.

The UI does not show the full raw request payload, API keys, secrets, passwords, invite tokens, or token hashes.

## Manual Test Cases

### A. Experienced CAH / Joint Commission

Expected `persona_context`:

- `hospital_category = critical_access_hospital`
- `payment_model = cah`
- `survey_pathway = accreditation`
- `accreditation_pathway = joint_commission_accreditation_360` or `joint_commission`
- `quality_director_experience = experienced`
- `first_30_days_track = false`
- `preferred_guidance_level = light`

### B. Experienced Rural PPS / Joint Commission

Expected `persona_context`:

- `hospital_category = rural_pps_hospital` or `rural_hospital`
- `payment_model = pps`
- `survey_pathway = accreditation`
- `accreditation_pathway = joint_commission_accreditation_360` or `joint_commission`
- `quality_director_experience = experienced`
- `first_30_days_track = false`
- `preferred_guidance_level = light`

### C. New CAH / CMS State Survey

Expected `persona_context`:

- `hospital_category = critical_access_hospital`
- `payment_model = cah`
- `survey_pathway = cms_state_survey`
- `accreditation_pathway = cms_state_survey_only`
- `quality_director_experience = new`
- `new_director = true`
- `first_30_days_track = true`
- `preferred_guidance_level = guided`

## Known Limitations

- Payment model is derived if the optional organization column is absent or empty.
- PPS, IQR, OQR, Promoting Interoperability, HCAHPS, and eCQM are detected from existing answer text. A future phase should add concise structured fields for these.
- OPPE/FPPE, utilization review, antibiotic stewardship, Sole Community Hospital, SHIP, provider-based RHC, and HOPD remain future structured data gaps.
- Program maturity is a heuristic from existing Day 0 answers.
- Scout remains responsible for generating recommendations; WordPress does not create local workflow recommendations.

## Phase 5E-QA Runtime Validation Notes

Validation date: 2026-06-17

Runtime validation created/reused three local QA hospitals:

| Persona | Organization slug | Latest QA run | Run status | Notes |
| --- | --- | ---: | --- | --- |
| A. Experienced CAH / Joint Commission | `qn-phase5eqa-a` | 7 | failed | Safe stored attempt from CLI harness. Payload validation succeeded; backend call was not executed from the harness. |
| B. Experienced Rural PPS / Joint Commission | `qn-phase5eqa-b` | 8 | failed | Safe stored attempt from CLI harness. Payload validation succeeded; backend call was not executed from the harness. |
| C. New CAH / CMS state survey | `qn-phase5eqa-c` | 9 | failed | Safe stored attempt from CLI harness. Payload validation succeeded; backend call was not executed from the harness. |

The local WordPress CLI bootstrap timed out during this QA pass, so the runtime validation used direct local database setup plus an isolated PHP harness that loaded the actual `QN_Scout` class and wrote safe failed Scout run rows. This validates the Phase 5E payload-building code path and stores inspectable `request_payload_json`, while avoiding direct backend calls outside the existing GrapevineAI bridge architecture.

### Observed Persona Values

| Field | Persona A observed | Persona B observed | Persona C observed |
| --- | --- | --- | --- |
| `hospital_category` | `critical_access_hospital` | `rural_pps_hospital` | `critical_access_hospital` |
| `payment_model` | `cah` | `pps` | `cah` |
| `survey_pathway` | `accreditation` | `accreditation` | `cms_state_survey` |
| `accreditation_pathway` | `joint_commission_accreditation_360` | `joint_commission_accreditation_360` | `cms_state_survey_only` |
| `accreditation_body` | `joint_commission` | `joint_commission` | `none` |
| `quality_director_experience` | `experienced` | `experienced` | `new` |
| `new_director` | `false` | `false` | `true` |
| `first_30_days_track` | `false` | `false` | `true` |
| `preferred_guidance_level` | `light` | `light` | `guided` |

All three QA payloads included these sample-package keys in `requested_outputs`:

- `priority_queue`
- `master_reporting_schedule`
- `meeting_report_flow_map`
- `first_30_days_learning_journey`
- `routine_task_rhythm`
- `aggregate_data_uploads`

### Fixes Made During Runtime QA

- Added support for the local/live organization `hospital_type` enum values:
  - `cah`
  - `rural_pps`
  - `ipps_general_acute`
- Updated persona derivation so `cah` maps to `critical_access_hospital` and `rural_pps` maps to `rural_pps_hospital` / `pps`.
- Updated guidance derivation so experienced Quality Directors without the First 30 Days track receive `light` guidance even when program maturity is only `partial`.
- Updated Scout Preview failed-run rendering so safe `persona_context` is still visible when a Scout attempt fails or the bridge/backend is unavailable.

### Runtime Security Check

The QA rows were inspected for sensitive strings. No passwords, API keys, authorization headers, cookies, invite tokens, or token hashes were found. The string `mrn` appears only in the intended no-PHI constraint key `do_not_include_mrns`, not as collected data.

## Security Notes

- Scout calls continue through `grapevine_ai_run_scout_task()`.
- Backend secrets are not sent to frontend JavaScript.
- The Scout payload sanitizer removes sensitive key families such as password, token, token hash, API key, secret, authorization, and cookie.
- The payload keeps the no-PHI constraints introduced in Phase 5B.
- The preview UI escapes rendered output and avoids raw JSON as the primary display.
