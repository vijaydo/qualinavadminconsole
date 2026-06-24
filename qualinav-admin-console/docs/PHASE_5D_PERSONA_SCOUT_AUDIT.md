# Phase 5D Persona-Aware Scout Output Audit

Project: QualiNav Admin Console  
Build Rank: 26  
Phase: 5D - Persona-Aware Scout Output Audit  
Plugin version audited: 0.1.3  
Audit date: 2026-06-17

## Executive Summary

The current Scout integration is architecturally sound for Phase 5B/5C: WordPress normalizes Day 0 onboarding data, calls the GrapevineAI Scout bridge server-side through `grapevine_ai_run_scout_task()`, stores request and response data in `wp_qualinav_scout_runs`, and displays a safe Scout Setup Preview without exposing backend secrets.

Persona support is partially present, but not yet explicit enough for dependable persona-aware Scout generation. The payload includes strong hospital context, hospital type, service model, state, current user role, grouped onboarding answers, requested outputs, and no-PHI constraints. However, persona fields such as `payment_model`, `accreditation_pathway`, `quality_director_experience`, `new_director`, `first_30_days_track`, `program_maturity`, and `preferred_guidance_level` are not elevated into normalized top-level payload fields. Some of those signals exist inside raw onboarding answers, so Scout can infer them, but the backend currently has to do more interpretation than it should.

The UI can display the existing Phase 5B response shapes and can gracefully render unknown `workflows` keys. It does not yet provide dedicated labels/icons for the sample package section names such as `master_reporting_schedule`, `priority_queue`, `aggregate_data_uploads`, or `first_30_days_learning_journey`.

Recommendation for Phase 5E: add a persona/context normalization layer in WordPress before calling Scout, extend requested output keys to match sample package terminology, and add response alias handling in PHP/JS. Do not generate persona outputs locally; keep Scout as the source of generated workflow recommendations.

## Files Inspected

- `qualinav-admin-console.php`
- `includes/class-qn-scout.php`
- `includes/class-qn-rest-api.php`
- `includes/class-qn-onboarding.php`
- `includes/class-qn-questionnaire.php`
- `includes/class-qn-organizations.php`
- `includes/class-qn-db.php`
- `includes/class-qn-activator.php`
- `assets/js/qualinav-console.js`
- `docs/PHASE_5A_SCOUT_API_AUDIT.md`
- `docs/PHASE_5B_SCOUT_RUNS.md`

No implementation files were changed during this audit.

## Sample Package Implications

The three sample packages imply that Scout needs more than generic onboarding answers. It needs a stable persona/context envelope that distinguishes:

- Experienced Critical Access Hospital Quality Director, Joint Commission accredited CAH.
- Experienced Rural PPS Quality Director, Joint Commission accredited rural PPS hospital.
- New Critical Access Hospital Quality Director, CMS/state survey pathway.

The differences are not cosmetic. They affect output depth, regulatory emphasis, reporting programs, learning journey intensity, and the presence or absence of a First 30 Days track.

## Current Scout Payload

`QN_Scout::build_day0_payload()` currently includes these top-level fields:

| Field | Status | Notes |
| --- | --- | --- |
| `source` | Supported | Always `qualinav_admin_console`. |
| `request_type` | Supported | `scout_day0_workflow_generation`. |
| `questionnaire_version` | Supported | `day0_v1`. |
| `organization_id` | Supported | Selected/current organization. |
| `organization_name` | Supported | From normalized hospital record. |
| `parent_system_id` | Supported | From `wp_organizations.parent_system_id`. |
| `parent_system_name` | Supported | From health system lookup. |
| `hospital_type` | Supported | From `wp_organizations.hospital_type`. |
| `hospital_type_label` | Supported | Human label from `QN_Organizations`. |
| `service_model` | Supported | From `wp_organizations.service_model`. |
| `service_model_label` | Supported | Human label from `QN_Organizations`. |
| `state_id` | Supported | From hospital record. |
| `state_code` | Supported | From state abbreviation/code. |
| `current_user_id` | Supported | Current WordPress user ID. |
| `current_user_role` | Supported | Mapping role for organization, falling back to global QualiNav role. |
| `onboarding_answers` | Supported | Grouped by the 8 Day 0 sections. |
| `constraints` | Supported | Includes no-PHI and no patient/MRN/provider/incident/peer-review constraints. |
| `requested_outputs` | Supported | Ten output keys listed below. |

### Persona/Context Fields

| Field | Status | Notes |
| --- | --- | --- |
| `hospital_category` | Missing | Could be derived from `hospital_type`, `is_critical_access_hospital`, payment model, and rural flags. |
| `payment_model` | Missing | Needed for PPS, CAH, value-based reporting, IQR/OQR, and rural PPS differentiation. |
| `accreditation_pathway` | Partially captured | Raw fields exist in onboarding; no normalized top-level payload field. |
| `quality_director_experience` | Partially captured | Raw fields include role start date, background, time in role, confidence, certifications. |
| `first_30_days_track` | Partially captured | Raw `activate_first_30_days_track` exists; not elevated. |
| `program_maturity` | Partially inferable | Can infer from plan status, dashboard status, projects, program gaps, confidence. |
| `preferred_guidance_level` | Missing | Could be inferred, but no explicit field. |
| `survey_pathway` | Partially captured | Raw `cms_certification_pathway`, `state_survey_agency`, accrediting body exist. |
| `accreditation_body` | Partially captured | Raw `accrediting_body` exists; not elevated. |
| `new_director` flag | Partially captured | Raw `new_to_quality_director_role` exists; not elevated. |
| `learning_journey` flag | Partially captured | Learning preference and first 30 days fields exist; no explicit boolean. |

Conclusion: the current payload is sufficient for a general Day 0 Scout run. It is not yet explicit enough for reliable persona selection without backend inference.

## Current Requested Outputs

Current `requested_outputs`:

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

Comparison to sample package sections:

| Sample package section | Current support | Notes |
| --- | --- | --- |
| Master reporting schedule | Partial | Current key is `reporting_schedule`; sample likely uses `master_reporting_schedule`. |
| Meeting and report flow map | Partial | Current key is `committee_flow_map`; sample likely uses `meeting_report_flow_map`. |
| Survey readiness timeline | Supported | Key matches concept. |
| Active monitoring and improvement tasks | Partial | Split across `clinical_monitoring_tasks` and `qi_project_milestones`; sample key may differ. |
| Recurring clinical monitoring | Partial | Current key is `clinical_monitoring_tasks`; no explicit recurring emphasis. |
| Aggregate data uploads | Missing explicit output | Could be included under reporting or regulatory monitoring, but should be requested directly. |
| Routine task rhythm | Partial | Current `reminder_rules` covers cadence, but not the whole rhythm concept. |
| Active improvement projects | Partial | Current key is `qi_project_milestones`; sample key may be `active_improvement_projects`. |
| Priority queue | Missing explicit output | Important for persona packages; should be requested. |
| First 30 Days and learning journey | Partial | Current `learning_journey` exists; no explicit `first_30_days_learning_journey`. |

## Response Normalization and UI Support

`QN_Scout::extract_preview_summary()` supports these response shapes:

- `workflows` object: supported. Each workflow key becomes a preview card group.
- `action_items`: supported as generic Scout Recommendations.
- `recommendations`: supported as generic Scout Recommendations.
- `goals`: supported as generic Scout Recommendations.
- `commitments`: supported as generic Scout Recommendations.
- `markdown_summary` or `summary`: supported as Scout Summary.
- `warnings`: supported and displayed.
- `missing_inputs`: supported and displayed.
- `sources`: supported and displayed safely.
- `source_count`: supported and displayed.
- Error or unsuccessful responses: normalized to failed state.

Package-like response key support:

| Key | Backend normalization | UI rendering |
| --- | --- | --- |
| `master_reporting_schedule` | Supported if inside `workflows`, fallback label | Supported with fallback title/icon. |
| `reporting_schedule` | Dedicated label | Dedicated icon/title. |
| `meeting_report_flow_map` | Supported if inside `workflows`, fallback label | Supported with fallback title/icon. |
| `committee_flow_map` | Dedicated label | Dedicated icon/title. |
| `survey_readiness_timeline` | Dedicated label | Dedicated icon/title. |
| `active_monitoring_improvement_tasks` | Supported if inside `workflows`, fallback label | Supported with fallback title/icon. |
| `clinical_monitoring_tasks` | Dedicated label | Dedicated icon/title. |
| `recurring_clinical_monitoring` | Supported if inside `workflows`, fallback label | Supported with fallback title/icon. |
| `aggregate_data_uploads` | Supported if inside `workflows`, fallback label | Supported with fallback title/icon. |
| `routine_task_rhythm` | Supported if inside `workflows`, fallback label | Supported with fallback title/icon. |
| `active_improvement_projects` | Supported if inside `workflows`, fallback label | Supported with fallback title/icon. |
| `priority_queue` | Supported if inside `workflows`, fallback label | Supported with fallback title/icon. |
| `first_30_days_learning_journey` | Supported if inside `workflows`, fallback label | Supported with fallback title/icon. |
| `learning_journey` | Dedicated label | Dedicated icon/title. |

Conclusion: unknown package keys will not break the UI, but they will look generic. Phase 5E should add aliases, labels, icons, and grouping order for the sample package keys.

## Persona Comparison

| Persona | Expected Scout behavior | Current onboarding captures enough data? | Current payload sends enough data? | Current UI can display likely output? | Backend inference burden |
| --- | --- | --- | --- | --- | --- |
| A. Experienced CAH QD, Joint Commission / Accreditation 360 | Lighter guidance; no First 30 Days track; CAH/MBQIP emphasis; Joint Commission/ORYX/Accreditation 360 readiness; seven required clinical monitoring areas; priority queue; refresher learning only. | Mostly. Captures CAH via hospital type and CAH question; captures accrediting body; captures MBQIP, survey readiness, monitoring areas, plans, confidence, and role background. | Partial. Sends hospital type and raw answers, but does not elevate `accreditation_body`, `new_director=false`, `guidance_level=light`, or `first_30_days_track=false`. | Mostly. Standard workflow cards render; `priority_queue` and package-specific keys render generically unless returned under current keys. | Moderate. Scout must infer experienced persona and Joint Commission path from raw answers. |
| B. Experienced Rural PPS QD, Joint Commission / Accreditation 360 | PPS/IQR/OQR reporting; HCAHPS/eCQM/Promoting Interoperability; CMS value-based program monitoring; OPPE/FPPE and medical staff peer review; utilization review; antibiotic stewardship; payment-linked reporting risk; lighter guidance. | Partial. Captures rural/acute hospital type approximately, accrediting body, reporting obligations, patient experience status, dashboards, QI projects, and monitoring text fields. Does not explicitly capture PPS, IQR/OQR, Promoting Interoperability, value-based programs, OPPE/FPPE, utilization review, or antibiotic stewardship. | Weak-to-partial. Sends `hospital_type` but no `payment_model`, PPS designation, value-based reporting flags, or normalized reporting program context. | Partial. Output can render, but package keys such as `aggregate_data_uploads`, `routine_task_rhythm`, and `priority_queue` are generic. | High. Backend must infer too much from sparse/free-text answers. |
| C. New CAH QD, CMS/state survey pathway | First 30 Days onboarding track; deeper learning journey; templates for missing documents; CMS Conditions of Participation / Appendix W readiness; state survey pathway; guided educational tone; contact-building tasks; foundational priority queue. | Mostly. Captures CAH, CMS/state pathway, state survey agency, new-to-role flag, time in role, confidence scores, first 30 days preference, templates needed, contact fields, plans, and gaps. | Partial. Raw signals are present, but no normalized `new_director=true`, `first_30_days_track=true`, `survey_pathway=CMS/state`, or `preferred_guidance_level=guided`. | Mostly. `learning_journey` renders; `first_30_days_learning_journey` and `priority_queue` render generically. | Moderate. Backend can infer, but normalized fields would improve reliability and tone. |

## Current Questionnaire Fields

The seeded questionnaire contains the intended 8 Day 0 sections and broad coverage across hospital profile, accreditation, clinical services, reporting, monitoring, QI projects, goals, learning, contacts, and regulatory preferences.

Confirmed useful fields:

- Hospital identity and size: `hospital_name`, `hospital_city`, `hospital_state`, `licensed_beds`, `acute_beds`, `swing_beds`.
- CAH context: `is_critical_access_hospital`, plus organization-level `hospital_type`.
- System/service context: organization-level `parent_system_id`, `parent_system_name`, `service_model`; onboarding field `independent_or_system`.
- Accreditation/survey: `accreditation_status`, `accrediting_body`, `cms_certification_pathway`, `state_survey_agency`, `life_safety_survey_agency`, `open_plans_of_correction`, `survey_history`, `projected_next_survey_window`, `historical_deficiency_areas`, `current_readiness_activities`.
- Services/clinical model: emergency, surgery/invasive procedures, OB, lab, radiology, respiratory therapy, rehabilitation, dietary/nutrition, pharmacy, sedation/anesthesia, blood bank, transfusion volume, visiting specialists, contracted monitoring agreements.
- Committees/reporting: `committee_list`, required committee status, agenda/minutes/board timing, `reporting_obligations`, `mbqip_measure_set`, backup preparer, lead time, approvals.
- Plans/policies/monitoring: QAPI, patient safety, infection prevention, emergency preparedness, risk management plan status, templates needed, monitoring areas, contracted service data flow, weakest monitoring areas.
- Measures/QI: MBQIP, NHSN HAI, patient experience, falls, pressure injury, hand hygiene, other metrics, dashboard, data currency, active QI projects, QI framework, charters, baseline status.
- QD experience/learning: new-to-role, time in role, certifications, confidence levels, First 30 Days track preference, learning format preference.
- Contacts: Flex, Office of Rural Health, hospital association, survey agency, peer CAH, accreditation liaison, referral hospital contacts.
- Tools/preferences: monitored sources, update preference, task adjustment preference, calendar, EHR, incident reporting, NHSN/QualityNet access, reminders, backup visibility, final confirmation.

Gaps:

- No explicit `payment_model`.
- No explicit Rural PPS hospital flag.
- No explicit PPS acute hospital classification beyond broad `acute_care_hospital`.
- No Sole Community Hospital field.
- No SHIP participant field.
- No provider-based RHC field.
- No hospital outpatient department/HOPD field.
- No explicit IQR/OQR field.
- No explicit Promoting Interoperability field.
- No explicit CMS value-based program monitoring field.
- No explicit OPPE/FPPE or medical staff peer review field.
- No explicit utilization review field.
- No explicit antibiotic stewardship field.
- No normalized `program_maturity`.
- No explicit `preferred_guidance_level`.

## Hospital Type and Payment Model Support

Current `hospital_type` values:

- `critical_access_hospital`
- `rural_hospital`
- `acute_care_hospital`
- `swing_bed_hospital`
- `specialty_hospital`
- `outpatient_facility`
- `other`

Current `service_model` values:

- `independent`
- `system_owned`
- `network_affiliated`
- `managed_services`
- `other`

These are useful for broad routing but not sufficient for Persona B. A rural PPS hospital is not just `rural_hospital`; it affects payment-linked reporting, IQR/OQR participation, HCAHPS/eCQM expectations, value-based monitoring, and potentially outpatient reporting. Phase 5E should add either structured organization fields or questionnaire fields for:

- `payment_model`
- `rural_designation`
- `sole_community_hospital`
- `ship_participant`
- `provider_based_rhc`
- `hospital_outpatient_department`
- `iqr_participant`
- `oqr_participant`
- `promoting_interoperability_applicable`
- `value_based_programs_applicable`

## Scout Run Storage

`wp_qualinav_scout_runs` stores:

- `organization_id`
- `request_type`
- `input_data_type`
- `request_payload_json`
- `response_json`
- `status`
- `api_request_id`
- `source_count`
- `error_message`
- `generated_by`
- timestamps

This is enough to debug persona behavior once normalized persona fields are added to the payload. Today, debugging requires inspecting nested `onboarding_answers` to understand which persona Scout might have inferred.

Security posture:

- Backend API secrets are not stored by this plugin.
- Raw invite tokens are not part of Scout payloads.
- `sanitize_payload_for_scout()` recursively removes values under keys containing password, pass, token, token_hash, api_key, secret, authorization, or cookie.
- No-PHI constraints are explicitly sent.
- `response_json` stores backend output as returned; the UI escapes rendered output and does not display raw JSON as the default view.

Risk: if the backend ever echoes sensitive data inside response fields, it would be stored in `response_json`. The current request payload should not contain those secrets, so this is mainly a backend contract risk.

## Backend Dependency Notes

The QualiNav plugin should continue to rely on the GrapevineAI plugin bridge rather than calling the backend directly:

- Function: `grapevine_ai_run_scout_task(array $args)`
- Current task type: `generate_action_plan`
- Current input data type: `qualinav-day0-workflow-generation-v1`
- Current request type inside payload: `scout_day0_workflow_generation`

The backend should not be expected to infer all persona fields from free text. It should receive a normalized persona/context block and still be allowed to use onboarding answers for nuance.

Recommended future payload addition:

```json
{
  "persona_context": {
    "hospital_category": "critical_access_hospital",
    "payment_model": "cah",
    "accreditation_pathway": "joint_commission_accreditation_360",
    "survey_pathway": "joint_commission",
    "accreditation_body": "The Joint Commission",
    "quality_director_experience": "experienced",
    "new_director": false,
    "first_30_days_track": false,
    "program_maturity": "developing",
    "preferred_guidance_level": "light",
    "learning_journey_enabled": true
  }
}
```

Values above are illustrative only. Phase 5E should define controlled option lists and derivation rules.

## Recommended Phase 5E Implementation Plan

1. Add a Scout persona/context normalization method, likely `QN_Scout::build_persona_context($organization_id, $answer_map, $user_id)`.
2. Promote key questionnaire answers into top-level normalized fields:
   - `accreditation_body`
   - `survey_pathway`
   - `accreditation_pathway`
   - `quality_director_experience`
   - `new_director`
   - `first_30_days_track`
   - `learning_journey_enabled`
   - `preferred_guidance_level`
   - `program_maturity`
3. Add payment/designation support for Rural PPS and related programs:
   - Prefer organization fields for stable classification.
   - Use questionnaire fields for operational participation and reporting applicability.
4. Extend `requested_outputs` to include sample package keys:
   - `master_reporting_schedule`
   - `meeting_report_flow_map`
   - `active_monitoring_improvement_tasks`
   - `recurring_clinical_monitoring`
   - `aggregate_data_uploads`
   - `routine_task_rhythm`
   - `active_improvement_projects`
   - `priority_queue`
   - `first_30_days_learning_journey`
5. Add PHP response alias labels in `QN_Scout::labelize()`.
6. Add JS icons/titles and preferred display order for package keys.
7. Add a lightweight admin/debug view showing normalized persona context for a Scout run, without secrets or PHI.
8. Add test fixtures for the three sample personas and verify payload shape plus UI rendering.

## Risks

- Persona B is the least supported today because Rural PPS/payment model details are not explicit.
- Backend output may use package-specific keys that currently render with generic labels/icons.
- Raw free-text onboarding answers may not be enough for deterministic persona selection.
- Adding too many healthcare program fields to the first Day 0 experience could make onboarding feel heavier; consider adding concise classification fields with optional advanced details.
- Scout run storage is adequate, but response_json should remain treated as backend-supplied content and rendered safely.

## Testing Recommendations

For Phase 5E, create three local test hospitals/users:

1. Experienced CAH QD, Joint Commission / Accreditation 360.
2. Experienced Rural PPS QD, Joint Commission / Accreditation 360.
3. New CAH QD, CMS/state survey pathway.

For each fixture:

- Confirm organization classification fields are set.
- Fill the minimal Day 0 answers that establish the persona.
- Generate Scout preview.
- Inspect `request_payload_json` and verify normalized `persona_context`.
- Confirm `requested_outputs` includes sample package keys.
- Confirm response preview renders package keys with dedicated labels/icons.
- Confirm no PHI, secrets, invite tokens, raw token hashes, passwords, or backend keys are present in request/response UI.
- Confirm multi-hospital isolation still works.

## Final Audit Status

Phase 5D is complete as an audit-only phase. No new persona logic was implemented. The current code is ready for Phase 5E planning, with the main recommended work being persona context normalization, payment model support, requested output alignment, and UI aliases for sample package output sections.
