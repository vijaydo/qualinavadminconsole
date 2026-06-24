# Document-Driven Completion Audit

Project: QualiNav Admin Console  
Build Rank: 26  
Phase: Document-Driven Completion Audit  
Plugin version reviewed: 0.1.48  
Audit date: 2026-06-18

## Reference Availability

This audit inspected the current plugin code and documentation, especially:

- `includes/class-qn-questionnaire.php`
- `includes/class-qn-scout.php`
- `assets/js/qualinav-console.js`
- `docs/UI_REVIEW_NOTES.md`
- Phase 5 Scout/persona audit documents in `docs/`

The standalone files for the three named sample output packages were not found in the plugin workspace or the synced D: project copy during this pass. The comparison below therefore uses the implemented questionnaire seed, the current UI renderers, the Scout/persona audit docs, and the sample output structures named in the brief, including Master Reporting Schedule, Meeting and Report Flow Map, Required Plans, Policy Library, Recurring Clinical Monitoring, Aggregate Data Uploads, Routine Task Rhythm, Priority Queue, First 30 Days, and Learning Journey.

## Executive Summary

The Day 0 wizard is substantially complete as a structured enterprise intake. All eight questionnaire steps exist in the seed, and the frontend now uses custom renderers for Steps 1 through 8 instead of relying only on raw metadata. Most originally raw textareas, generic selects, native multi-selects, and horizontal repeaters have been converted into dropdowns, custom multi-selects, cards, date controls, repeaters, and confirmation panels.

Scout payload generation is also substantially aligned with the sample package structure. The payload includes grouped onboarding answers, persona context, no-PHI constraints, and requested outputs that match the sample package concepts. The Scout Preview page can render known workflow sections, persona context, warnings, missing inputs, sources, and readable detail panels without showing raw JSON as the primary UI.

The main product gap is not Day 0 intake. The gap is downstream operationalization. Reporting, Committees, and Plans & Policies now have dedicated enterprise preview pages, but they are still preview/readiness modules, not editable workflow engines. Clinical Monitoring and Settings remain polished future-state modules rather than full product modules. Dashboard and Users are visually much stronger, but should still receive a final browser QA pass before enterprise review.

## Phase Status Table

| Area | Status | Notes |
| --- | --- | --- |
| Day 0 Step 1 - Hospital & Director Info | Done | Structured state, CAH, system model, beds, date, placeholders, validation warning. |
| Day 0 Step 2 - Accreditation & Survey Readiness | Done | Custom groups, dropdowns, multi-selects, survey history cards, conditional notes. |
| Day 0 Step 3 - Services & Clinical Model | Done | Structured service controls and multi-selects; contracted monitoring remains textarea with helper. |
| Day 0 Step 4 - Committees & Reporting | Done | Committee cards, reporting cards, structured deadline logic, defaults cleanup. |
| Day 0 Step 5 - Plans, Policies & Monitoring | Done / Needs visual review | Plan cards, policy controls, monitoring cards. Final density/date-field visual QA still recommended. |
| Day 0 Step 6 - Measures & QI Projects | Done | Measure upload cards, dashboard controls, QI project cards, structured defaults. |
| Day 0 Step 7 - Goals, Learning & Contacts | Done | Persona, learning journey, First 30 Days, and contacts are structured. |
| Day 0 Step 8 - Regulatory Monitoring & Preferences | Done | Monitoring sources, tools, reminders, backup users, and final confirmation are structured. |
| Dashboard | Done / Needs visual review | Current docs show dashboard cards and status surfaces are implemented. |
| Scout Preview | Done | Enterprise preview page with persona, workflow cards, details, warnings, sources, and safe states. |
| Users | Done / Needs visual review | Summary cards, polished table, pending invitations, action menus implemented. |
| Reporting module | Partial | Dedicated preview page exists; editable schedule/reminder workflow is not implemented. |
| Committees module | Partial | Dedicated preview page exists; editable committee calendar/minutes workflow is not implemented. |
| Plans & Policies module | Partial | Dedicated preview page exists; editable plan/policy queue is not implemented. |
| Clinical Monitoring module | Partial / Preview module implemented | Dedicated preview page now renders Scout-style monitoring output; editable task lifecycle is not implemented. |
| Settings module | Partial / Preferences readiness page implemented | Dedicated settings readiness page reflects Step 8 preferences; full editable settings engine and calendar sync are not implemented. |
| Scout backend/API integration | Partial | Payload/persona/preview normalization exist; still depends on GrapevineAI bridge and stable response contract. |
| Fixture-based Scout QA | Mobile shell overflow fixed / fixture QA rerun pending | Day 0 is now 100% complete for organization `1394`, fixture insertion/cleanup still works, direct hash route hydration was fixed, Plans & Policies fixture defects were fixed, and the narrow viewport shell overflow found during QA-4R was fixed. Full three-fixture renderer QA still needs to be rerun. |

## Day 0 Step-by-Step Audit

### Step 1: Hospital & Director Info

Status: Done

Fields present:

- `hospital_name`
- `hospital_city`
- `hospital_state`
- `licensed_beds`
- `acute_beds`
- `swing_beds`
- `is_critical_access_hospital`
- `independent_or_system`
- `quality_director_name`
- `quality_director_role_start_date`
- `quality_director_background`

Controls used:

- State dropdown backed by available `wp_states` data.
- CAH dropdown with yes/no/not sure values.
- Independent/system dropdown with standard values.
- Numeric bed fields with non-negative handling.
- Native date input for role start date.
- Textarea for Quality Director background with targeted placeholder.

Scout usefulness:

- Strong. This step supports hospital profile, payment/pathway inference, director experience, setup path, and persona context.

Remaining gaps:

- The state answer is stored through existing onboarding answer logic and does not update the organization `state_id`.
- Final visual review should confirm date input rendering across browsers.

### Step 2: Accreditation & Survey Readiness

Status: Done

Fields present:

- `accreditation_status`
- `accrediting_body`
- `cms_certification_pathway`
- `state_survey_agency`
- `life_safety_survey_agency`
- `open_plans_of_correction`
- `survey_history`
- `projected_next_survey_window`
- `historical_deficiency_areas`
- `current_readiness_activities`

Controls used:

- Dropdowns for accreditation status, accreditor, CMS pathway, POC status, and projected survey window.
- Custom multi-select dropdowns with chips for deficiency areas and readiness activities.
- Survey history row cards with date, survey type, agency, deficiencies/findings, and POC/follow-up.
- Conditional helper notes for Joint Commission, CMS/state survey-only, and open POC warnings.

Scout usefulness:

- Strong. This step feeds survey readiness timeline, accreditation pathway, risk weighting, open POC follow-up, and source monitoring.

Remaining gaps:

- Accreditation 360 is only supported if an existing seeded question exists. No new seed/schema was added.
- Legacy free-text values are preserved but not automatically normalized into canonical options.

### Step 3: Services & Clinical Model

Status: Done

Fields present:

- `emergency_department`
- `surgery_invasive_procedures`
- `surgery_procedure_types`
- `obstetrics_labor_delivery`
- `laboratory_model`
- `radiology_model`
- `respiratory_therapy`
- `rehabilitation_services`
- `dietary_nutrition_services`
- `pharmacy_model`
- `anesthesia_moderate_sedation_model`
- `blood_bank_model`
- `transfusions_per_year`
- `visiting_specialists`
- `contracted_quality_monitoring_agreements`

Controls used:

- Multi-selects for surgery procedure types, radiology, and anesthesia/sedation.
- Dropdowns for lab, pharmacy, and blood bank model.
- Conditional de-emphasis/help for surgery not offered, no blood products, and visiting specialists.
- Contracted monitoring agreements remains a textarea with helper text.

Scout usefulness:

- Strong for clinical monitoring applicability, recurring monitoring tasks, blood usage review, ancillary review, and contracted service flags.

Remaining gaps:

- Contracted quality monitoring agreements is still less structured than the rest of the step.

### Step 4: Committees & Reporting

Status: Done

Fields present:

- `committee_list`
- `committee_required_status`
- `standing_agenda_items`
- `minutes_owner_location`
- `board_agenda_timing`
- `reporting_obligations`
- `mbqip_measure_set`
- `backup_preparer`
- `report_lead_time`
- `approval_requirements`

Controls used:

- Responsive committee cards.
- Responsive reporting obligation cards.
- Structured deadline controls with conditional month/day, monthly day, before-meeting, external notice, and event-triggered details.
- Structured reporting defaults and program notes.
- Multi-selects for MBQIP/CMS programs and approval requirements.

Scout usefulness:

- Strong. This is directly useful for Master Reporting Schedule, Meeting and Report Flow Map, reminder rules, approval routing, board timing, and report preparation cadence.

Remaining gaps:

- The UI collects schedule logic, but no editable reporting schedule engine exists yet.
- Legacy hidden fields are preserved for compatibility and not shown as primary controls.

### Step 5: Plans, Policies & Monitoring

Status: Done / Needs visual review

Fields present:

- `qapi_plan_status`
- `patient_safety_plan_status`
- `infection_prevention_plan_status`
- `emergency_preparedness_plan_status`
- `risk_management_plan_status`
- `plan_location_authority`
- `policy_management_system`
- `annual_policy_review_cycle`
- `templates_needed`
- `morbidity_mortality_monitoring`
- `blood_usage_review`
- `medication_safety_monitoring`
- `operative_invasive_review`
- `anesthesia_sedation_monitoring`
- `sentinel_never_event_protocol`
- `ancillary_services_review`
- `contracted_service_quality_data_flow`
- `weakest_monitoring_areas`

Controls used:

- Plan cards for required plans.
- Dropdowns for policy system and annual review cycle.
- Multi-selects for templates needed and weakest monitoring areas.
- Clinical monitoring cards with structured applicability, state, cadence, review body, method, priority, and notes.
- One strong PHI warning instead of repeated warnings after every field.

Scout usefulness:

- Strong for required plan review, policy queue, clinical monitoring tasks, recurring monitoring, and priority gaps.

Remaining gaps:

- Needs final visual review for plan card density and date input alignment.
- Monitoring cards are lightweight accordions; they are not a full monitoring task engine.

### Step 6: Measures & QI Projects

Status: Done

Fields present:

- `mbqip_upload`
- `nhsn_hai_rates_upload`
- `patient_experience_scores_upload`
- `fall_rates_upload`
- `pressure_injury_rates_upload`
- `hand_hygiene_upload`
- `other_dashboard_metrics`
- `current_quality_dashboard`
- `data_source_currency`
- `active_qi_projects`
- `qi_framework`
- `project_charters_status`
- `baseline_data_status`

Controls used:

- Measure upload cards with upload status, cadence, source system, and notes.
- Shorter dashboard textarea.
- Data currency dropdown.
- QI project cards.
- Structured QI framework, charter status, and baseline status.

Scout usefulness:

- Strong for aggregate data uploads, dashboard trend setup, active improvement projects, QI milestones, and priority queue.

Remaining gaps:

- Legacy active QI project text is preserved but not deeply parsed.

### Step 7: Goals, Learning & Contacts

Status: Done

Fields present:

- `department_goals_this_year`
- `department_goals_two_three_years`
- `protected_workflow_goals`
- `program_gaps`
- `strategic_plan_alignment`
- `new_to_quality_director_role`
- `time_in_current_role`
- `quality_certifications`
- `confidence_foundational`
- `confidence_qi_patient_safety`
- `confidence_specialized_areas`
- `confidence_professional_development`
- `activate_first_30_days_track`
- `learning_format_preference`
- `state_flex_contact`
- `state_office_rural_health_contact`
- `state_hospital_association_contact`
- `state_survey_agency_contacts`
- `peer_cah_contacts`
- `accreditation_liaison`
- `referral_hospital_contacts`

Controls used:

- Concise goal textareas.
- Dropdowns for new-to-role, time in role, First 30 Days, and confidence fields.
- Multi-selects for certifications and learning preferences.
- Contact cards with name/organization, email, phone, and notes.

Scout usefulness:

- Strong for persona classification, guidance level, First 30 Days track, learning journey, protected workflow goals, and external contact directory.

Remaining gaps:

- Contact legacy prose is preserved but not automatically parsed into all structured fields.
- There is no standalone Learning module page yet.

### Step 8: Regulatory Monitoring & Preferences

Status: Done

Fields present:

- `monitored_sources`
- `update_preference`
- `auto_propose_task_adjustments`
- `current_tools`
- `calendar_system`
- `ehr_system`
- `incident_reporting_system`
- `nhsn_qualitynet_access`
- `reminder_lead_time`
- `reminder_buffer_time`
- `backup_visibility_users`
- `final_review_confirmation`

Controls used:

- Multi-selects for monitored sources and current tools.
- Dropdowns for update preference, auto-propose behavior, calendar system, NHSN/QualityNet access, reminder lead time, and reminder buffer.
- Backup visibility user cards.
- Polished final confirmation card with PHI/no-case-detail confirmation.

Scout usefulness:

- Strong for regulatory monitoring, reminder rules, tool/system context, backup visibility, and final safety gate.

Remaining gaps:

- Settings editing is not yet available after setup; Step 8 captures preferences but does not power a full settings module yet.

## Module-by-Module Audit Against Sample Output Concepts

### Dashboard

Status: Done / Needs visual review

The dashboard has been updated with action-oriented cards and status surfaces for Day 0, Scout Preview, users, reporting, committees, plans/policies, clinical monitoring, priority queue, and system health. It supports enterprise navigation but still needs final browser review against real hospital data states.

### Scout Preview

Status: Done

Sample output alignment:

- Scout Experience Summary: supported through `persona_experience_summary`.
- Master Reporting Schedule: supported through workflow keys and preview cards.
- Meeting and Report Flow Map: supported.
- Survey Readiness Timeline: supported.
- Active Monitoring and Improvement Tasks: supported.
- Recurring Clinical Monitoring: supported.
- Aggregate Data Uploads: supported.
- Routine Task Rhythm: supported.
- Active Improvement Projects: supported.
- Priority Queue: supported.
- Plans and Policies: supported through `plan_policy_tasks`.
- Regulatory Monitoring: supported.
- External Contacts: supported.
- First 30 Days and Learning Journey: supported.
- Reminder Rules: supported.

The page includes status states, persona context, workflow cards, warnings, missing inputs, source rendering, and readable detail panels. This is the strongest downstream sample-output renderer in the current plugin.

Remaining gaps:

- It still depends on the GrapevineAI bridge returning stable structured response keys.
- Unusual response shapes may be simplified rather than rendered as rich domain-specific cards.

### Reporting Schedule

Status: Partial

Sample output alignment:

- Compares to Master Reporting Schedule.
- Dedicated page extracts `master_reporting_schedule`, `reporting_schedule`, `reporting_obligations`, and `report_schedule`.
- Renders report name, frequency, due date/rule, owner, backup, approval, and status when data exists.
- Provides summary cards and capability cards.

Remaining gaps:

- No editable reporting schedule.
- No actual reminder engine.
- No owner assignment workflow beyond display.
- No approval routing workflow.

### Committees

Status: Partial

Sample output alignment:

- Compares to Meeting and Report Flow Map.
- Dedicated page extracts `meeting_report_flow_map`, `committee_flow_map`, `committees`, `meeting_flow_map`, and `report_flow_map`.
- Renders committee name, frequency/timing, role, reports-to, preparation lead time, status, and flow details.

Remaining gaps:

- No editable committee calendar.
- No minutes tracking.
- No agenda generation.
- No dependency sequencing engine beyond display.

### Plans & Policies

Status: Partial

Sample output alignment:

- Compares to Required Plans, Policy Library, Template Needs, and Priority Queue.
- Dedicated page extracts `plan_policy_tasks`, `plans_policies`, `required_plan_review`, `policy_review_cycle`, `template_needs`, and plan/policy entries in `priority_queue`.
- Renders plan/policy name, current status, owner, date, board approval, action needed, and priority.

Remaining gaps:

- No editable plan review queue.
- No policy approval workflow.
- No template library management.
- No task completion lifecycle.

### Clinical Monitoring

Status: Partial / Preview module implemented

Sample output alignment:

- Day 0 Steps 5 and 6 collect strong data for Recurring Clinical Monitoring, Active Monitoring and Improvement Tasks, Aggregate Data Uploads, and Routine Task Rhythm.
- Scout Preview can render these output groups.
- The standalone Clinical Monitoring page now renders Scout-style recurring monitoring, aggregate uploads, routine task rhythm, active improvement projects, and priority monitoring gaps when those outputs are available.

Remaining gaps:

- No monitoring task lifecycle exists yet.
- No editable owner routing, task completion, or recurring reminder workflow exists yet.

### Settings

Status: Partial / Preferences readiness page implemented

Current alignment:

- Step 8 captures settings-like preferences for sources, tools, reminders, backup visibility, and final confirmation.
- The Settings page now renders workspace context, regulatory monitoring preferences, tools/systems, reminder preferences, backup visibility, and setup status from existing Day 0 and Scout state.

Remaining gaps:

- No editable settings page.
- No persistent user preference editor beyond Day 0 answers.
- No reminder integration settings UI beyond intake.
- No external calendar integration or calendar sync from this page.

### Users

Status: Done / Needs visual review

The Users page has summary cards, polished table cells, role/status presentation, pending invitations, and permission-aware actions. It is not directly part of the sample Scout output packages, but it is important for enterprise readiness.

Remaining gaps:

- Action menu behavior should be browser-tested with each role type.
- Invitation edge states should be checked with real pending/expired invites.

## Scout Backend/API Status

Status: Partial

Implemented:

- Payload request type: `scout_day0_workflow_generation`.
- Input data type: `qualinav-day0-workflow-generation-v1`.
- GrapevineAI bridge call is in place.
- Payload includes hospital context, state, user role, persona context, persona summary, grouped onboarding answers, and no-PHI constraints.
- Requested outputs include the major sample package concepts:
  - reporting schedule
  - committee flow map
  - survey readiness timeline
  - plan/policy tasks
  - clinical monitoring tasks
  - QI milestones
  - external contact directory
  - regulatory monitoring preferences
  - learning journey
  - reminder rules
  - master reporting schedule
  - meeting report flow map
  - recurring clinical monitoring
  - aggregate data uploads
  - routine task rhythm
  - active improvement projects
  - priority queue
  - first 30 days learning journey
  - persona experience summary
- Persona context derives hospital category, payment model, accreditation pathway, survey pathway, accreditor, QD experience, first 30 days track, program maturity, and guidance level.
- Preview normalization supports known workflow keys and safe fallbacks.
- Sensitive request payload keys are sanitized.

Not complete:

- End-to-end output quality depends on the GrapevineAI bridge and response contract.
- Three local fixture JSON files now exist for the named sample output package shapes, with manual QA instructions for inserting them into `wp_qualinav_scout_runs`.
- Fixture-based browser execution is still pending after local manual insertion.
- Downstream pages only render subsets of Scout output; they do not yet operate as editable workflow systems.

## Remaining Must-Fix List

1. Execute the fixture-based browser QA guide using the three sample output package shapes after local manual insertion.
2. Perform a final visual QA pass for Day 0 Step 5 and all completed downstream pages using real local hospital data.
3. Confirm end-to-end Scout run states: no run, generating, failed, completed, bridge unavailable, and source-less completed response.
4. Begin planning editable workflow lifecycles for reporting, committees, plans/policies, clinical monitoring, and settings.
5. Define the future calendar integration contract before exposing any calendar sync controls.

## Nice-to-Have List

1. Parse legacy free-text answers into structured fields where safe instead of only preserving them as notes/custom chips.
2. Add keyboard-search behavior to custom multi-selects.
3. Add export/print views for Scout Preview and reporting/committee/plan summaries.
4. Add inline explanations for how Scout used each major Day 0 answer group.
5. Add role-specific onboarding guidance for Quality Director, Hospital Admin, and viewers.

## Recommended Build Order From Here

1. Run manual fixture QA from `docs/SCOUT_FIXTURE_QA_GUIDE.md`.
2. Enterprise visual QA pass for all Day 0 steps and primary modules.
3. Begin editable workflow engines in this order: Reporting Schedule, Committees, Plans & Policies, Clinical Monitoring.
4. Add settings edit/save operations after the preferences contract is finalized.
5. Add reporting/monitoring reminder operations after editable workflow engines exist.

## Next Action List

Must finish before enterprise review:

1. Manual fixture verification against the three sample output package shapes.
2. Final browser QA for Day 0 Steps 1-8, Scout Preview, Users, Reporting, Committees, Plans & Policies, Clinical Monitoring, and Settings.
3. End-to-end Scout state verification for no-run, generating, failed, completed, bridge unavailable, and no-source responses.
4. Permission QA for read-only versus editor behavior on Users, Day 0, and Settings.

Can wait for next product phase:

1. Editable reporting schedule and reminder engine.
2. Editable committee calendar, agenda, and minutes tracking.
3. Editable plan/policy review queue and approval workflow.
4. Full clinical monitoring task lifecycle.
5. Advanced migration/parser for legacy free-text answers.

## QA-StagingSubmit-1

Status: Submit fix verified on staging; full staging E2E remains partial.

Findings:

- The production/staging New CAH / CMS State Survey E2E was blocked when Day 0 Step 8 final submit stayed in `Submitting...`.
- Root cause was a combined frontend/backend submit-flow issue:
  - frontend requests had no timeout/recovery guard;
  - the pre-submit save catch path did not fail closed for final submit;
  - backend Day 0 submit synchronously called Scout generation, so a slow bridge call could hold the final submit request open.
- Day 0 submission is now independent from Scout generation.
- Submit failures now restore the button and surface visible safe messages.
- Staging organization `1382` submitted without the button hanging after deploying version `0.1.56`.
- Scout Setup Preview was reached afterward and showed selected hospital/persona context with a ready latest preview.

Remaining:

- Do not mark full staging E2E passed yet.
- Downstream Reporting, Committees, Plans & Policies, Clinical Monitoring, and Settings still need the full post-Scout route validation pass.
- Day 0 should get a persistent submitted/completed banner or status treatment because the progress meter can remain below 100% even after successful submission.

## QA-StagingSubmittedState-1

Status: Submitted-state propagation fixed and verified on staging.

Findings:

- Staging E2E-2 found that downstream modules displayed stale `Pending Day 0 Setup` after a successful Day 0 submit.
- Root cause was submitted-state derivation being too dependent on answer-completion percentage and a single `state.scoutOnboardingSubmitted` flag.
- The submitted source of truth is now explicit:
  - `/onboarding` exposes `onboarding_submitted` and `onboarding_status`;
  - `/scout/runs` exposes `onboarding_submitted` and `onboarding_status`;
  - frontend renderers use explicit submitted state and latest Scout run fallback instead of treating `73%` answer completeness as unsubmitted.
- Day 0 now displays submitted state while preserving answer completeness.
- Reporting, Committees, Plans & Policies, Clinical Monitoring, and Settings no longer show stale pending-Day-0 CTAs for organization `1382`.

Remaining:

- Do not mark full staging E2E passed yet.
- Scout output for organization `1382` is ready but structurally insufficient: Scout Preview reports `model_structured_result_insufficient`, and downstream module sections show polished `Pending Scout` / missing-detail states rather than populated workflow rows.
- Full staging E2E can resume with Scout backend/output quality validation and downstream module rendering once Scout returns the required structured sections.

## Scout Backend Contract Validation - Day 0 Workflow Output

Status: Backend response contract fixed and verified on staging.

Findings:

- Latest pre-fix org `1382` Scout run had `request_type=scout_day0_workflow_generation`, `input_data_type=qualinav-day0-workflow-generation-v1`, and `status=completed`, but the response used the generic action-plan shape (`summary`, `goals`, `commitments`, `action_items`) and warning `model_structured_result_insufficient`.
- Request payload diagnosis: WordPress was sending the correct contract, including organization context, grouped Day 0 answers, persona context, persona summary, no-PHI constraints, and requested workflow outputs.
- Root cause category: C - GrapevineAI Scout backend generated/stored generic prose/action-plan structure instead of the required Day 0 workflow JSON structure.
- Fixed layer: GrapevineAI Scout backend. WordPress plugin payload builder, REST routes, database schema, frontend renderers, roles, and permissions were not changed.
- Backend candidate `day0workflow0619b` is deployed and promoted to 100% Cloud Run traffic.
- Real post-fix Scout run `3` for organization `1382` completed with structured workflow keys:
  - `master_reporting_schedule`
  - `meeting_report_flow_map`
  - `survey_readiness_timeline`
  - `plan_policy_tasks`
  - `template_needs`
  - `recurring_clinical_monitoring`
  - `aggregate_data_uploads`
  - `routine_task_rhythm`
  - `active_improvement_projects`
  - `priority_queue`
  - `first_30_days_learning_journey`
  - `learning_journey`
  - `reminder_rules`
- Scout Preview and downstream modules now hydrate from structured Scout output for org `1382`.

Verification:

- `py -3 -m compileall` passed for changed backend model/service files.
- Focused backend contract test passed: `tests/backend/test_scout_day0_workflow_contract.py`.
- Cloud Run health passed for `day0workflow0619b`.
- Scout Preview: Ready, selected hospital/persona context visible, warnings 0, missing inputs 0, structured workflow cards/details visible.
- Reporting: schedule rows render, including MBQIP reporting.
- Committees: Quality and Safety Committee, Medical Staff Meeting, and Governing Board flow render.
- Plans & Policies: infection prevention, sentinel/serious event protocol, and related plan/policy rows render with template signals.
- Clinical Monitoring: recurring monitoring, aggregate uploads, routine rhythm, active projects, and priority gaps render.
- Settings: settings/workspace context renders; not treated as a Scout response renderer.
- Desktop and narrow 395px route checks passed with no horizontal overflow, no raw JSON, no unsafe PHI text, and no QualiNav JavaScript errors.

Remaining:

- Full staging E2E can continue from the now-populated downstream modules.
- Future backend enhancement can add evidence/source enrichment for this Day 0 workflow contract; current verified workflow output is generated from submitted Day 0 operational inputs and reports `source_count=0`.

## Stakeholder Feedback Pass 1

Status: Low-risk Day 0 guidance and admin visibility improvements implemented.

Findings:

- Stakeholder feedback emphasized more Quality Director hand-holding before and during Day 0, more breathing room in the setup layout, and clearer preparation guidance.
- Day 0 now includes a welcome/setup guide modal with offline preparation guidance, no-PHI boundaries, save-at-your-own-pace language, and a materials checklist.
- Day 0 now includes a printable question list workflow so QDs can print or save the questionnaire as a PDF from the browser before entering answers.
- The permanent right-side Scout guidance panel was replaced with a top-of-section expandable guidance panel so the main form has more horizontal room.
- Final submit copy now explains that Day 0 is submitted, Scout can build the hospital operating-system preview, setup can be updated later, and annual review will be prompted.
- Settings now includes lightweight annual review copy in setup status.
- Admin hospital rows now expose compact Day 0 section progress visibility using existing progress data.

Remaining:

- No annual scheduler or reminder automation has been built yet.
- No Jack measure/QI tool integration has been built yet.
- No committee flowchart/workflow engine has been built yet.
- Scout backend and GrapevineAI bridge were intentionally not changed in this phase.

## Stakeholder Feedback Pass 2 - Hospital Setup Top Stepper Layout

Status: Implemented and verified on staging.

Findings:

- Stakeholder feedback identified the remaining left-side Hospital Setup step list as a second sidebar that constrained the question area.
- The Hospital Setup layout now uses a top stepper/progress rail above the active form instead of a left column.
- The active form now uses the full Hospital Setup content width, preserving the setup guide, print/save question list, submitted-state copy, no-PHI warning, and expandable Scout guidance.
- Mobile behavior remains simple and stable: the stepper wraps down to a single-column list at narrow widths instead of forcing horizontal document overflow.

Verified:

- Org `1382` loads saved answers, all 8 steps remain visible/clickable, submitted answer-completeness status remains visible, and Scout Preview/downstream routes still render.
- This phase intentionally did not change Hospital Setup internal storage, question keys, Scout backend, REST endpoints, database schema, or submit behavior.

## UX Copy Fix - Hospital Setup Naming

Status: Implemented.

Findings:

- User-facing product copy now uses `Hospital Setup` instead of `Day 0` across primary Quality Director and admin UI surfaces.
- Updated visible labels include the hospital console nav item, dashboard setup card, setup guide modal, print/save setup questions flow, pending/setup-submitted states, Settings setup status, admin setup progress labels, and server-side submit/Scout messages.
- Day 0 remains the internal implementation term for route hash `#day-0-setup`, code identifiers, questionnaire/storage keys, existing audit history, and backend/Scout contract context.
- Hospital Setup is now the user-facing name.
- No schema, backend, Scout contract, route, question key, or storage behavior changed.

## Permission QA Fix - Viewer Hospital Setup Actions

Status: Implemented and verified on staging.

Findings:

- Permission QA found that viewer users could see disabled Hospital Setup fields and disabled `Save`, but `Save & Continue` remained enabled and attempted the save flow before surfacing a backend permission error.
- Root cause: the frontend action state and click handlers used the onboarding edit payload too broadly and routed Next through the save path, so read-only navigation was framed as a write action.
- Fix: Hospital Setup now uses a role-aware edit helper for Save, Save & Continue, final submit, stepper background save, and read-only navigation.
- Viewer/read-only users now see a view-only notice and `Next section` navigation that does not issue save requests.
- QD/editor and Super Admin write behavior was verified unchanged on staging for organization `1382`.
- No backend permission, schema, route, Scout backend, question key, or storage behavior changed.

## Hospital Setup Guidance Cleanup - Compact Scout Help

Status: Implemented and verified on staging.

Findings:

- Replaced the large always-expanded `How Scout uses this section` panel with a compact optional disclosure near the Hospital Setup section subtitle.
- Preserved section-specific guidance bullets and no-PHI reminders.
- Guidance is collapsed by default and collapses on section change so it does not push the form down on each step load.
- The disclosure is UI-only and does not change storage, question keys, permissions, REST routes, database schema, Scout backend, or submit behavior.
- Follow-up: added explicit `Show details` / `Hide details` action text, chevron state, hover/focus styling, and ARIA state updates for the compact Scout guidance disclosure.
- Follow-up: moved the `Show details` / `Hide details` pill beside the Scout guidance label instead of anchoring it at the far right edge, preserving the full-row click target while making the CTA feel connected to the section help label.

## Quality Director Workspace Welcome Modal + Hospital Setup Guidance Upgrade

Status: Implemented and verified on staging.

Findings:

- Added a concise workspace welcome/orientation modal for hospital users with Quality Director-oriented copy, three value cards, setup reassurance, and no-PHI safety language.
- Added dashboard entry CTAs for reopening the workspace guide and continuing/viewing Hospital Setup.
- Added browser localStorage dismiss behavior keyed by organization and role; no backend preference, schema, storage, REST, Scout backend, question-key, or permission changes were made.
- Added a checked-by-default `Don’t show this welcome automatically again on this browser.` checkbox with helper copy explaining the guide can be reopened from the dashboard.
- Super Admin/admin console auto-show is suppressed; viewer copy is review-oriented and does not imply edit access.
- Follow-up: Super Admin hospital preview now keeps auto-show suppressed while exposing manual `Open workspace guide` and `Workspace guide` entry points.
- Upgraded the Hospital Setup Guide copy to explain why setup matters, partial completion, useful materials, post-submit Scout preview outputs, annual review, and data safety.

## Invite Magic-Link Onboarding Flow

Status: Implemented pending full invite-link QA.

Findings:

- The prior hospital invite acceptance path displayed a QualiNav password setup page and called `wp_set_password()`, which could overwrite an existing user's password when accepting an additional hospital invitation.
- The hospital invite path now validates the existing hashed QualiNav invite token, activates the invited hospital access, stores a short-lived QualiNav onboarding handoff context, and redirects logged-out users through a one-time Grapevine magic-login URL.
- The Grapevine magic-login helper now supports creating a one-time login URL without sending a second email, while preserving selector/validator tokens, SHA-256 validator hashes, expiry, and single-use limits.
- Grapevine onboarding completion now uses the QualiNav handoff context to redirect invited users to `/qualinav?organization_id={id}#day-0-setup`.
- Invite email copy no longer says to set a password and instead explains that the secure link signs users in and starts QualiNav onboarding.

Unchanged:

- No database schema, Scout backend, Grapevine onboarding question flow, REST route, QualiNav Hospital Setup storage, or question-key changes were made.
- Admin/internal invite password flow remains available for non-hospital QualiNav admin roles.

## Invite Expiry Security Fix - UTC Expiration Validation

Status: Implemented pending post-deploy browser verification.

Findings:

- Browser QA found that an invite marked expired in UTC could still be accepted and routed through the passwordless Grapevine magic-login handoff.
- Root cause was an inconsistent expiration comparison: invite records store `expires_at` as UTC-style timestamps, while acceptance validation compared against the site-local timestamp.
- `QN_Invitations::validate_invitation_for_acceptance()` now parses `expires_at` explicitly as UTC and compares it to the current UTC timestamp.
- Missing, invalid, or unparseable `expires_at` values now fail closed as expired.
- Invites expiring at the current UTC timestamp or earlier are rejected.

Unchanged:

- No database schema, Scout backend, Grapevine Login, magic-link token behavior, onboarding flow, REST route, resend semantics, or invite role behavior changed.

## Workspace Welcome Modal Stacking Fix

Status: Implemented pending post-deploy browser verification.

Findings:

- Browser review found that after invite/onboarding redirect into Hospital Setup, two modal layers could appear at once.
- Root cause was independent auto-open behavior: the workspace welcome modal could open from workspace/dashboard rendering, while the Hospital Setup guide modal could also auto-open from Hospital Setup rendering.
- Added a shared QualiNav modal coordination helper so opening one modal closes other open QualiNav modals first.
- Hospital Setup guide auto-open now defers while the workspace welcome modal is visible.
- Manual guide and print/save setup question actions remain available, but they now replace the current modal instead of stacking another backdrop.

Unchanged:

- No database schema, storage, Scout backend, Grapevine Login, magic-link behavior, invite acceptance, onboarding completion redirect, or permission behavior changed.
