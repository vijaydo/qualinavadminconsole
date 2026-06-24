# UI Review Notes

## Phase UI-2 Changes

- Fixed `/qualinav` routing for QualiNav global admins so the default admin experience is `/qualinav/admin`.
- Converted the Admin Console into active sidebar sections: Overview, Hospitals, Health Systems, Users, Invitations, Brand Settings, Audit Logs, and System Check.
- Converted the Hospital Console into active sidebar sections: Dashboard, Day 0 Setup, Scout Setup Preview, Users, Reporting, Committees, Plans & Policies, Clinical Monitoring, and Settings.
- Reworked hospital, health system, user, and invitation tables for compact row height, cleaner cells, role/status presentation, and dropdown action menus.
- Standardized button contrast and added enterprise button classes for primary, secondary, ghost, danger, icon, and menu actions.
- Replaced the unfinished brand placeholder with a practical brand preview showing a sample sidebar, card, badge, button, logo area, and color swatches.
- Kept Scout backend logic, invitation security, and database schema unchanged.

## Pages Reviewed

- `/qualinav/admin`
- `/qualinav`
- Admin hospital management table
- Admin user and invitation tables
- Brand Settings preview
- Hospital Dashboard, Day 0 Setup, Scout Setup Preview, and Users sections

## Known Remaining UI Limitations

- Audit Logs is still a polished placeholder because the audit viewer is not part of this correction phase.
- Reporting, Committees, Plans & Policies, Clinical Monitoring, and Settings remain placeholders until later product phases.
- The current action menu is intentionally simple and client-side only; deeper keyboard menu behavior can be improved later if needed.

## UI-Day0-1

### Old Issues

- Day 0 Setup looked like a raw generated form rather than a guided enterprise setup flow.
- Step cards were too large and wasted vertical space.
- Progress was visually disconnected from the wizard header.
- Scout help was dense paragraph text and did not change cleanly by step.
- Step 1 fields were presented as one flat list.
- Submit Final Setup could appear before the final step context was clear.

### Changes Made

- Reworked Day 0 Setup into a three-column desktop layout: compact stepper, active form card, and Scout help panel.
- Added a branded wizard header with hospital name, system/type/service/state chips, integrated progress text, progress bar, and save status.
- Replaced large step cards with compact stepper rows showing number/check state and Not Started/In Progress/Complete labels.
- Grouped Step 1 into Hospital Information and Quality Director Information sections with icons and two-column field layout.
- Replaced dense Scout help text with step-specific icon bullets and a persistent data-boundary note.
- Moved actions into a sticky form footer with Previous, Save, Save & Continue, and final-step-only Submit Final Setup.
- Improved field styling, required markers, textarea height, repeater add-row affordance, warning chips, and responsive stacking.

### Known Limitations

- Only Step 1 receives custom sub-section grouping in this pass; later steps use the same polished group container around their existing seeded fields.
- The wizard still relies on the existing questionnaire metadata and answer storage; no backend validation or schema changes were made.
- Repeater rows remain generic text inputs until the questionnaire metadata supports richer column-level field types.

## UI-Day0-1B

### Verification Notes

- Verified Day 0 Setup in super-admin hospital setup preview mode using organization ID 1394.
- Step 1 footer shows disabled Previous, Save, and Save & Continue; Submit Final Setup is hidden.
- Step 8 footer shows Previous, Save, and Submit Final Setup; Save & Continue is hidden.
- Clicked all 8 stepper items with save/load waits; active state changed for each step and status labels remained readable as Not started, In progress, or Complete.
- Scout guidance updated per step, each guidance item rendered with an icon, and the data-boundary note stayed visible.
- Step 1 rendered Hospital Information and Quality Director Information groups with the intended field layout.
- Narrow viewport check confirmed the wizard, stepper, help panel, and Step 1 fields stack into a single-column layout.
- Save status changed from Unsaved changes to Saving... and then Saved during the save flow.
- No QualiNav Admin Console JavaScript errors were logged during the wizard checks; the browser did report an unrelated Elementor frontend config error outside this plugin.

### Fixes Made

- Added a final-step guard so Submit Final Setup cannot be triggered from earlier wizard steps.
- Added an onboarding submit-in-flight state so the final submit button remains disabled/busy while the submit request is pending, even after the pre-submit save re-renders the wizard.
- Bumped the plugin version from 0.1.7 to 0.1.8 because the wizard JavaScript changed.
- Follow-up: changed stepper tile navigation to switch steps immediately and save the previous step in the background, removing the perceived delay when clicking wizard tiles. Bumped the plugin version to 0.1.11.
- Follow-up: moved the stepper active-state paint ahead of the heavier form render so tile clicks provide immediate visual feedback. Bumped the plugin version to 0.1.12.

## UI-Dashboard-1

### Changes Made

- Replaced the Hospital Dashboard placeholder layout with a production-style hospital workspace dashboard.
- Removed placeholder language, raw organization/state ID cards, and the large visible `Loading hospital...` dashboard headline.
- Added a full-width hospital hero using selected hospital context rather than the current user display name.
- Added role, Day 0 setup progress, and Scout preview status to the hero.
- Added compact context chips for Hospital System, Hospital Type, Service Model, Payment Model, State, and Current Workspace.
- Added action-oriented cards for Day 0 Setup, Scout Setup Preview, Hospital Users, Reporting Schedule, Committees, Plans & Policies, Clinical Monitoring, Priority Queue, and System Health.
- Added polished loading skeletons and an empty state for no selected hospital workspace.
- Updated the topbar fallback label to `Loading workspace...` / `Hospital workspace`.
- Bumped the plugin version from 0.1.8 to 0.1.9 because dashboard PHP, CSS, and JavaScript changed.

### Known Data Limitations

- Reporting, Committees, Plans & Policies, Clinical Monitoring, and Priority Queue use graceful `Available after Scout preview` style statuses until richer workflow data exists.
- Hospital Users uses currently available hospital users and invitations data; no new user-count endpoint was added.
- System Health is passive on the Hospital Dashboard in this phase; deeper diagnostics remain outside the hospital dashboard scope.
- Payment Model is shown when present in existing hospital context and otherwise falls back to an incomplete-state chip.

## UI-ScoutPreview-1

### Old Issues

- Scout Setup Preview felt like a technical run viewer instead of a hospital operating-system preview.
- Completed, failed, and empty states did not clearly explain what Scout generated or what to do next.
- Persona context looked like debug metadata rather than a personalization summary.
- Workflow output only rendered returned groups, so expected product areas could disappear without a clear `Not returned` state.
- Details were shown inline and could feel like raw response inspection rather than a readable product view.

### Changes Made

- Rebuilt Scout Setup Preview as a full product page with an admin preview banner, page title/subtitle, hospital context chips, status hero, metrics, and clear CTAs.
- Added polished empty, unavailable, generating, failed, and completed states without exposing raw backend error dumps.
- Added a stronger `Personalized for this hospital` panel with persona summary and chips for hospital category, payment model, survey/accreditation pathway, Quality Director experience, guidance level, program maturity, and First 30 Days track.
- Added a `Needs your attention` panel grouping missing inputs, Scout warnings, and backend/contract warnings with blank/duplicate values filtered out.
- Added 16 ordered workflow cards with icons, counts, preview text, status badges, and `View Details` actions.
- Added a readable details modal that renders summaries, lists, key/value rows, warnings, missing inputs, and section sources, with admin-only raw response access collapsed behind a disclosure.
- Added a safe `Sources used by Scout` panel with source cards when present and a subtle no-source note otherwise.
- Bumped the plugin version to 0.1.10 for the Scout Preview CSS, JavaScript, and PHP template changes.

### Known Limitations

- Local test data included completed and failed Scout runs; no submitted-without-run fixture was available, so that no-run submitted state is implemented but not verified against a real local record.
- Current Scout responses may omit many structured workflow groups; those now show as `Not returned` until the bridge returns richer contract-aligned sections.
- Source cards depend on the existing Scout response `sources` shape and intentionally avoid surfacing secret/debug fields.

## UI-Sidebar-1

### Changes Made

- Converted the shared console sidebar into a compact desktop icon rail to give Day 0 Setup and other workspace screens more horizontal room.
- Added hover and keyboard-focus expansion so labels appear when users explore the rail, while the main content stays in place instead of shifting on hover.
- Added a sidebar pin button in the expanded state so users can keep the full navigation open when preferred; the preference is stored locally in the browser.
- Added accessible labels and native tooltips for collapsed navigation items.
- Kept the mobile/narrow layout expanded so touch users do not have to rely on hover behavior.
- Bumped the plugin version to 0.1.13 because shared PHP templates, CSS, and JavaScript changed.
- Follow-up: centered the collapsed logo by removing hidden toggle spacing, removed the white logo tile treatment, increased collapsed icon size, and added visible hover/focus background states for sidebar items. Bumped the plugin version to 0.1.14.
- Follow-up: reverted the clipped collapsed-logo treatment after visual review. Matched the Grapevine menu approach by centering the full logo/wordmark in a fixed logo slot with `object-fit: contain`, natural aspect ratio, and no cropping. Bumped the plugin version to 0.1.16.
- Follow-up: matched Grapevine's collapsed rail centering more closely by centering the logo against the full rail width instead of the padded sidebar content lane, reducing the wordmark width, and nudging the asset slightly right to align with the Home icon. Bumped the plugin version to 0.1.17.
- Follow-up: removed the duplicate sidebar brand text when a logo image is configured, centered the logo in the expanded sidebar header, and kept the sidebar toggle pinned to the right. Bumped the plugin version to 0.1.18.
- Follow-up: reduced the collapsed logo-to-first-menu gap and increased collapsed sidebar icon size so the orange icons read proportionally inside their targets. Bumped the plugin version to 0.1.19.

### Known Limitations

- The collapsed sidebar preference is browser-local only; it is not stored as a WordPress user setting.

## UI-Day0-Step1-Fields

### Changes Made

- Reworked Day 0 Step 1 field rendering for Hospital & Director Info only.
- Added `wp_states`-backed state options to the onboarding payload and rendered Hospital state as a dropdown with `Select state` placeholder.
- Prefilled Hospital state from the organization state code/id/name when no onboarding answer exists.
- Replaced Critical Access Hospital and Independent/System fields with segmented options using standardized values.
- Added non-negative whole-number handling for licensed, acute, and swing beds.
- Added a soft warning when acute beds plus swing beds exceed licensed beds.
- Cleaned form value rendering so empty fields no longer display `-`; text and number fields now use helpful placeholders.
- Kept Role start date as a native date input.
- Added Quality Director background placeholder/help text and refreshed Step 1 Scout guidance to mention hospital profile, pathway, director experience, setup path, and no PHI.
- Bumped the plugin version to 0.1.20 because PHP, CSS, and JavaScript changed.

### Known Limitations

- Hospital state saves into the existing onboarding answer map as a state abbreviation when available; it does not update the organization `state_id` column.
- If `wp_states` is unavailable, Step 1 shows a disabled `States unavailable` select and leaves existing onboarding state answers untouched.
- Follow-up: removed invalid nested label markup from Step 1 segmented controls after a loading/render concern. Bumped the plugin version to 0.1.21.
- Follow-up: added the existing centered workspace loading overlay to the initial Day 0 setup load so slow local WordPress responses do not leave a half-empty wizard shell. Save/submit refreshes remain quiet. Bumped the plugin version to 0.1.22.
- Follow-up: replaced the delayed initial Day 0 overlay with an immediate inline centered loader inside the wizard body and corrected workspace overlay centering for the collapsed sidebar rail. Bumped the plugin version to 0.1.23.
- Follow-up: moved the initial Day 0 loading state to the full setup panel so the wizard uses the same blurred, centered modal-style loader as the hospital workspace switch instead of placing the spinner low inside the empty form body. Bumped the plugin version to 0.1.24.
- Follow-up: shortened the spinner wait by loading Day 0 before hospital users/invitations and by making onboarding progress calculation read-only for page loads instead of updating every section row during the GET request. Bumped the plugin version to 0.1.25.
- UI-Day0-Step1-Field-Control-Fix: changed Independent/System back to a polished dropdown with standard value keys and tightened the Critical Access Hospital control into a compact three-option pill segment. Bumped the plugin version to 0.1.26.
- Follow-up: removed repeated Day 0 Setup text inside the wizard hero; the page header now owns the Day 0 title, while the hero card shows the selected hospital and setup context. Bumped the plugin version to 0.1.27.
- Follow-up: converted Critical Access Hospital to a dropdown as well, keeping yes/no/not_sure values and reducing Step 1 visual spacing. Bumped the plugin version to 0.1.28.

## UI-Day0-Step2-Fields

### Changes Made

- Added a custom Day 0 Step 2 renderer instead of relying on the generic field metadata renderer.
- Grouped Step 2 into Accreditation & Certification Pathway, Current Survey Risk, and Survey History cards.
- Converted accreditation status, accrediting body, CMS certification pathway, open POC status, and projected survey window into standard dropdowns with stable value keys.
- Rendered historical deficiency areas and current readiness activities as structured checklist pills, preserving legacy free-text answers as checked custom values.
- Reworked survey history into responsive row cards with date, dropdown, text, and textarea controls plus compact delete buttons; this avoids the old horizontal-scroll repeater.
- Added lightweight conditional guidance for CMS/state survey-only or not-accredited pathways, Joint Commission readiness, and open POC process-only warnings.
- Moved the onboarding save/message text into the action footer so it no longer appears detached below the wizard card.
- Bumped the plugin version to 0.1.29 because PHP, JS, CSS, and template files changed.

### Known Limitations

- Accreditation 360 is only rendered if an existing seeded question with key `accreditation_360` is present; no schema or questionnaire seed changes were made.
- Legacy free-text checklist values are preserved as custom selected pills but are not automatically mapped to canonical options.

### UX Follow-up

- Replaced always-visible checklist pills for historical deficiency areas and current readiness activities with collapsed custom multi-select dropdowns.
- Added compact selected chips with remove controls while preserving saved arrays and legacy custom values.
- Reworked survey history from a cramped row layout into bordered survey cards with a header, responsive field grid, and polished Add survey/delete controls.
- Removed Step 2 horizontal-scroll behavior for survey history cards.
- Bumped the plugin version to 0.1.30 because JS and CSS changed.

### Known Limitations

- The custom multi-select is intentionally lightweight; it supports mouse/touch selection and chip removal, but does not implement full combobox keyboard search.

## UI-Day0-Step3-Fields

### Changes Made

- Added Step 3-specific controls while keeping the existing Services & Clinical Model layout.
- Converted surgery procedure types, radiology model, and anesthesia/moderate sedation model to the reusable multi-select dropdown with removable chips.
- Converted laboratory model, pharmacy model, and blood bank model to structured dropdowns.
- Added placeholder/helper copy for contracted quality monitoring agreements while keeping the existing textarea and answer key.
- Added lightweight conditional behavior for surgery not offered, no blood products plus zero transfusions, and visiting specialists.
- Preserved legacy free-text values as custom selected chips for Step 3 multiselects where possible.
- Bumped the plugin version to 0.1.31 because PHP, JS, and CSS changed.

### Known Limitations

- Contracted quality monitoring agreements remains a textarea in this pass to avoid changing the stored answer shape.

## UI-Day0-Step4-Fields

### Changes Made

- Added a custom Day 0 Step 4 renderer for Committees & Reporting instead of using the generic metadata renderer.
- Grouped Step 4 into Committee Structure, Reporting Obligations, and Reporting Rules & Approvals.
- Replaced the committee list horizontal repeater with responsive committee cards.
- Replaced the reporting obligations horizontal repeater with responsive report cards.
- Added structured dropdowns for committee names, roles, reporting destinations, required/optional status, report categories, frequencies, lead times, approvals, payment-linked status, and event-triggered status.
- Converted MBQIP measure set and approval requirements into reusable multi-select dropdowns with removable chips.
- Added CAH/PPS contextual helper text for MBQIP reporting examples.
- Added Step 4 Scout guidance and data-boundary copy focused on committee/report process information.
- Bumped the plugin version to 0.1.32 because PHP, JS, and CSS changed.

### Known Limitations

- Committee and report cards preserve the existing repeater answer keys while adding richer per-row fields; older rows with only the original columns still render safely.
- Approval required inside each report card is a dropdown, while the top-level approval requirements field is multi-select.

## UI-Day0-Step4-DateRules-Fix

### Changes Made

- Fixed committee card alignment by using a controlled two-column row layout and making minutes location plus standing agenda items full width.
- Replaced free-text report due dates with structured due-date rule controls stored inside each reporting obligation row.
- Added conditional due-date detail controls for quarterly specific dates, annual specific dates, monthly day-of-month rules, before-meeting rules, external notice placeholders, and event-triggered timelines.
- Preserved legacy `due_dates` text inside the reporting obligation row and displayed it as a muted previous-value note.
- Replaced top-level Minutes owner and location with structured owner, location dropdown, and location details fields under the existing `minutes_owner_location` answer key.
- Replaced top-level Board agenda timing with a structured dropdown plus details under the existing `board_agenda_timing` answer key.
- Bumped the plugin version to 0.1.33 because JS and CSS changed.

### Known Limitations

- Existing plain text values for top-level minutes and board agenda timing are preserved as legacy notes and are not auto-parsed into the new structured fields.

## UI-Day0-Step4-Cleanup

### Changes Made

- Removed duplicated committee-specific fields from the visible Reporting Defaults area; committee required status, standing agenda items, and minutes owner/location now live in committee cards only.
- Preserved hidden saved values for the removed duplicate global fields so save/reload does not discard existing data.
- Renamed Reporting Rules & Approvals to Reporting Defaults & Program Notes.
- Renamed the report deadline selector to "How is the deadline determined?" and replaced technical option labels with Quality Director-friendly labels.
- Added helper text explaining that structured deadline methods help Scout create accurate reminders.
- Updated deadline detail labels for known dates, same-day monthly deadlines, meeting lead times, external notices, triggering events, and not-sure follow-up.
- Renamed Board agenda timing to "When are board materials due?" with "Timing details" shown only when Other is selected.
- Renamed MBQIP measure set to "MBQIP / CMS reporting programs" and expanded options to include MBQIP, IQR, OQR, HCAHPS, eCQMs, Promoting Interoperability, and value-based program monitoring.
- Bumped the plugin version to 0.1.34 because JS and PHP changed.

### Known Limitations

- Hidden duplicate global fields are preserved for compatibility but are not shown as editable controls in this cleaned-up Step 4 UI.

## UI-Day0-Step5-Fields

### Changes Made

- Added a custom Day 0 Step 5 renderer for Plans, Policies & Monitoring instead of using the generic textarea-heavy form.
- Grouped Step 5 into Required Plans, Policy Library & Templates, Clinical Monitoring Areas, and Monitoring Gaps & Priorities.
- Replaced repeated PHI warning behavior with one strong "Do not enter patient information" warning near the top of Step 5 plus a smaller clinical monitoring process-only reminder.
- Rendered required plans as compact plan cards with exists, last approved, board approved, owner, location, and action-needed controls.
- Converted policy management system and annual policy review cycle into structured dropdowns.
- Converted templates needed and weakest monitoring areas into reusable multi-select dropdowns with chips, preserving legacy free-text values as custom selections.
- Replaced giant clinical monitoring textareas with structured monitoring cards for applicability, current state, cadence, review body, method, priority/gap, and process-only notes.
- Added lightweight defaulting for non-applicable operative/anesthesia/blood monitoring based on existing Step 3 answers where available.
- Updated Step 5 Scout guidance and data-boundary copy.
- Bumped the plugin version to 0.1.35 because JS, CSS, and PHP changed.

### Known Limitations

- Clinical monitoring cards use lightweight accordions and preserve legacy free-text as notes; they do not attempt to parse old prose into individual structured fields.

## UI-Day0-Step6-Fields

### Changes Made

- Added a custom Day 0 Step 6 renderer for Measures & QI Projects instead of using the generic textarea/repeater renderer.
- Grouped Step 6 into Measure Upload Plan, Current Quality Dashboard, Active QI Projects, and QI Program Defaults.
- Replaced measure upload textareas with compact measure cards that capture upload status, cadence, source system, and aggregate/de-identified notes.
- Preserved legacy free-text measure values by carrying them into structured notes where possible.
- Kept Current quality dashboard as a shorter textarea with aggregate/dashboard-only placeholder guidance.
- Converted Data source currency, QI framework, project charters status, and baseline data status into structured dropdowns.
- Replaced the active QI projects horizontal repeater with responsive project cards for aim, method, measure, status, next milestone, charter status, and baseline status.
- Updated Step 6 Scout guidance and data-boundary copy for aggregate/de-identified measure and project information.
- Bumped the plugin version to 0.1.37 because JS, CSS, and PHP changed.

### Known Limitations

- Legacy active QI project `status_next_milestone` text is preserved and displayed as the next milestone when possible, but it is not automatically split into separate status and milestone values.

## UI-Day0-Step7-Fields

### Changes Made

- Added a custom Day 0 Step 7 renderer for Goals, Learning & Contacts instead of using the generic raw textarea/text-input form.
- Grouped Step 7 into Strategic Goals, Quality Director Experience, Learning Journey, and External Contacts.
- Kept strategic goal fields as concise textareas with targeted placeholders and shorter visual height.
- Converted New to Quality Director role, Time in current role, First 30 Days activation, and confidence fields into structured dropdowns.
- Converted Quality certifications and Learning format preference into reusable multi-select dropdowns with chips.
- Added dynamic First 30 Days guidance that highlights the track for new Quality Directors and shows lighter guidance for experienced directors.
- Reworked external contact fields into compact contact cards with name/organization, email, phone, and notes while preserving legacy text as notes.
- Updated Step 7 Scout guidance and data-boundary copy for professional contact/program information only.
- Bumped the plugin version to 0.1.38 because JS, CSS, and PHP changed.

### Known Limitations

- Legacy contact prose is preserved in notes/name fields but is not automatically parsed into separate name, organization, email, and phone values.
- Existing numeric confidence values are mapped to New/Developing/Confident for display and future saves use the structured labels.

## UI-Day0-Step8-Fields

### Changes Made

- Added a custom Day 0 Step 8 renderer for Regulatory Monitoring & Preferences instead of using the raw metadata form.
- Grouped Step 8 into Regulatory Monitoring, Tools & Systems, Reminders & Backup Coverage, and Review & Confirm.
- Replaced the native monitored sources list with a reusable multi-select dropdown with chips and expanded regulatory source options.
- Converted current tools into a multi-select dropdown with chips and converted calendar system, NHSN / QualityNet access, reminder lead time, reminder buffer, update preference, and task adjustment preference into structured dropdowns.
- Added a structured backup visibility user card repeater with name, role, email, access level, and notes under the existing `backup_visibility_users` answer key.
- Replaced the small final checkbox with a polished final confirmation card and added front-end validation copy before final submission.
- Updated Step 8 Scout guidance and data-boundary copy for regulatory monitoring, reminders, backup coverage, and final PHI review.
- Bumped the plugin version to 0.1.39 because JS, CSS, and PHP changed.

### Known Limitations

- Legacy backup visibility prose is preserved in the first backup-user notes field but is not automatically parsed into separate name, role, email, and access-level fields.

## UI-Users-1B

### Changes Made

- Removed the duplicate generic Users title by making the page header own "Hospital Users" and changing the in-panel heading to "Workspace access roster."
- Added hospital context chips for current hospital, current user role, active user count, and pending invite count.
- Added four compact summary cards for Active Users, Pending Invites, Quality Directors, and Disabled Users.
- Reworked the hospital users table into User, Hospital Access, Role, Status, and Actions columns with avatar initials, hospital chips, role badges, and status pills.
- Replaced the raw dash/empty action column with permission-aware action menus for role changes, disable/reactivate/archive, and resend invite where applicable.
- Moved pending hospital invitations into a clear card on the Users page with invitee, role, email status, invite status, expires, and actions.
- Polished the hospital invite modal with helper copy, a read-only workspace chip, role descriptions, and existing mail-failure warning behavior.
- Bumped the plugin version to 0.1.40 because PHP, JS, and CSS changed.
- Follow-up: corrected the Pending Invitations table class and scoped table column widths so the Actions column stays inside the invitations card instead of being pushed off the edge. Bumped the plugin version to 0.1.41.

### Known Limitations

- The action menu uses direct role/status actions rather than a nested role-picker modal; it still calls the existing permission-checked REST endpoints.

## UI-PlaceholderModules-1

### Changes Made

- Replaced the raw one-line placeholders for Reporting, Committees, Plans & Policies, Clinical Monitoring, and Settings with a shared polished future-module renderer.
- Added module titles, subtitles, hospital context chips, status badges, main future-state cards, preview capability cards, and setup/Scout CTAs.
- Added dynamic CTA/status behavior based on Day 0 setup progress, Scout preview submission state, and latest Scout run status.
- Added a `#clinical-monitoring` URL alias for the existing Clinical Monitoring section.
- Updated navigation page titles for Reporting Schedule and Hospital Settings.
- Added responsive styles so capability cards stack cleanly on narrower screens.
- Bumped the plugin version to 0.1.42 because PHP, JS, and CSS changed.

### Known Limitations

- These modules remain future-state UI only; backend workflow data, editable settings, reporting schedules, committee tools, policy queues, and clinical monitoring task lists are not implemented in this phase.

## UI-Reporting-1

### Changes Made

- Replaced the generic Reporting future-state renderer with a dedicated Reporting Schedule page.
- Added a polished page header with hospital context chips and Scout status.
- Added Day 0 incomplete, Scout not generated, and Scout-ready empty states with Quality Director-facing copy and clear CTAs.
- Added summary cards for Reporting status, Upcoming reports, Pending approvals, and Missing due dates.
- Added Reporting capability cards for Master Reporting Schedule, Due Date Reminders, Owner & Backup Tracking, and Board / Committee Prep.
- Added safe Scout reporting extraction for `master_reporting_schedule`, `reporting_schedule`, `reporting_obligations`, and `report_schedule`, rendering clean rows/cards instead of raw JSON.
- Added an Admin Preview Mode banner for super-admin hospital preview views.
- Added responsive styles so reporting rows switch from table to cards on narrower screens.
- Bumped the plugin version to 0.1.43 because PHP, JS, and CSS changed.

### Known Limitations

- Reporting remains a preview/readiness module in this phase; full editable reporting workflows and backend schedule operations are not implemented.
- Scout reporting row normalization supports common field names, but unusual Scout response shapes may still appear as simplified draft rows.

## UI-Committees-1

### Changes Made

- Replaced the generic Committees future-state renderer with a dedicated Committees page.
- Added a polished page header with hospital context chips and Scout status.
- Added Day 0 incomplete, Scout not generated, and Scout-ready empty states with Quality Director-facing copy and clear CTAs.
- Added summary cards for Committees identified, Reporting dependencies, Board-facing meetings, and Missing meeting details.
- Added capability cards for Meeting Cadence, Report Flow Map, Agenda & Minutes Tracking, and Board Quality Reporting.
- Added safe Scout committee extraction for `meeting_report_flow_map`, `committee_flow_map`, `committees`, `meeting_flow_map`, and `report_flow_map`.
- Rendered committee data as clean rows/cards with committee name, frequency/timing, user role, reports to, preparation lead time, status, and readable flow details.
- Added an Admin Preview Mode banner for super-admin hospital preview views.
- Bumped the plugin version to 0.1.44 because PHP, JS, and CSS changed.

### Known Limitations

- Committees remains a preview/readiness module in this phase; full editable committee management, minutes tracking, and meeting calendar workflows are not implemented.
- Scout committee row normalization supports common field names, but unusual Scout response shapes may still appear as simplified draft rows.

## UI-PlansPolicies-1

### Changes Made

- Replaced the generic Plans & Policies future-state renderer with a dedicated Plans & Policies page.
- Added a polished page header with hospital context chips and Scout status.
- Added Day 0 incomplete, Scout not generated, and Scout-ready empty states with Quality Director-facing copy and clear CTAs.
- Added summary cards for Required Plans, Plans Needing Review, Templates Needed, and Approval Items.
- Added capability cards for Required Plan Review, Policy Review Cycle, Template Support, and Approval Routing.
- Added safe Scout extraction for `plan_policy_tasks`, `plans_policies`, `required_plan_review`, `policy_review_cycle`, `template_needs`, and plan/policy entries in `priority_queue`.
- Rendered plan/policy data as clean rows/cards with name, current status, owner, last approved/review date, board approval, action needed, and priority.
- Added secondary panels for policy review cycle and template needs where Scout returns those details.
- Added an Admin Preview Mode banner for super-admin hospital preview views.
- Bumped the plugin version to 0.1.45 because PHP, JS, and CSS changed.

### Known Limitations

- Plans & Policies remains a preview/readiness module in this phase; full editable plan queues, policy review workflows, template generation, and approval routing are not implemented.
- Scout plan/policy row normalization supports common field names, but unusual Scout response shapes may still appear as simplified draft rows.

## UI-ClinicalMonitoring-1

### Changes Made

- Replaced the generic Clinical Monitoring future-state renderer with a dedicated Clinical Monitoring page.
- Added a polished page header with hospital context chips and Scout status.
- Added Day 0 incomplete, Scout not generated, and Scout-ready empty states with Quality Director-facing copy and clear CTAs.
- Added summary cards for Monitoring areas, Priority gaps, Aggregate uploads, and Active projects.
- Added safe Scout extraction for `recurring_clinical_monitoring`, `clinical_monitoring_tasks`, `active_monitoring_improvement_tasks`, `monitoring_tasks`, `required_monitoring_areas`, `aggregate_data_uploads`, `aggregate_measure_uploads`, `measure_uploads`, `data_uploads`, `routine_task_rhythm`, `task_rhythm`, `recurring_tasks`, `active_improvement_projects`, `qi_project_milestones`, `improvement_projects`, and monitoring-related `priority_queue` items.
- Rendered clinical monitoring data as readable rows/cards for recurring monitoring, aggregate uploads, routine task rhythm, active improvement projects, and priority monitoring gaps.
- Added capability cards for Recurring Monitoring, Aggregate Data Uploads, Routine Task Rhythm, and Priority Gap Tracking.
- Added an Admin Preview Mode banner for super-admin hospital preview views.
- Bumped the plugin version to 0.1.46 because PHP, JS, and CSS changed.

### Known Limitations

- Clinical Monitoring remains a preview/readiness module in this phase; full editable monitoring task lifecycle, ownership workflow, and completion tracking are not implemented.
- Scout clinical monitoring normalization supports common field names, but unusual Scout response shapes may still appear as simplified draft rows/cards.

## UI-Settings-1

### Changes Made

- Replaced the generic Settings future-state renderer with a dedicated Hospital Settings page.
- Added a polished page header with hospital context chips and Scout status.
- Added a Workspace Context section for hospital name, state, hospital type, service model, payment model, current user role, and admin preview status without exposing raw IDs.
- Rendered Day 0 Step 8 regulatory monitoring preferences as source chips plus digest/alert and proposed-adjustment behavior.
- Rendered Step 8 tools and systems preferences, reminder preferences, and backup visibility users safely without PHI, credentials, or raw JSON.
- Added a Setup Status section for Day 0 status, Scout Preview status, last Scout run, and module preview availability.
- Added permission-aware CTA behavior: editors are routed back to Day 0 Setup for preference updates, while non-editors see a View only state.
- Added an Admin Preview Mode banner for super-admin hospital preview views.
- Bumped the plugin version to 0.1.47 because PHP, JS, and CSS changed.

### Known Limitations

- Settings remains a preferences/readiness page in this phase; there is no full editable settings engine or new save endpoint.
- External calendar integration and calendar sync are not active from this page.

## Scout Fixture QA-1

### Changes Made

- Added `docs/fixtures/` with three safe local Scout output fixtures:
  - `experienced-cah-joint-commission-scout-output.json`
  - `rural-pps-joint-commission-scout-output.json`
  - `new-cah-cms-state-survey-scout-output.json`
- Each fixture includes status, request ID, workflow version, generated timestamp, persona experience summary, persona context, workflow sections, warnings, missing inputs, sources, and source count.
- Covered sample output concepts for master reporting schedule, meeting/report flow, survey readiness, recurring clinical monitoring, aggregate data uploads, routine task rhythm, active improvement projects, plan/policy tasks, priority queue, learning journey, and reminder rules.
- Added `docs/SCOUT_FIXTURE_QA_GUIDE.md` with local-only manual SQL instructions, browser routes, pass/fail checklist, and renderer coverage matrix.
- Kept fixtures documentation-only. No production fixture endpoint, public route, UI loader, PHP, JS, or CSS change was added in this phase.

### Known Limitations

- Fixture loading is manual and local-only.
- Settings is not a Scout response renderer; fixture `reminder_rules` are verified in Scout Preview while Settings continues to reflect Step 8 onboarding preferences.
- Fixture-based browser execution is pending after local manual insertion into `wp_qualinav_scout_runs`.

## Scout Fixture QA-2

### Manual QA Results

- Re-validated all three fixture JSON files successfully before browser testing.
- Inserted the three local Scout fixture rows one at a time into `wp_qualinav_scout_runs` for organization `1394`:
  - `fixture-experienced-cah-jc`
  - `fixture-rural-pps-jc`
  - `fixture-new-cah-cms`
- Confirmed no production fixture endpoints, UI loaders, public routes, PHP, JS, or CSS changes were added.
- Confirmed no raw JSON primary UI appeared during the gated route checks.
- Confirmed no QualiNav JavaScript errors were observed; the known unrelated Elementor `elementorFrontendConfig` error remains outside this plugin.
- Confirmed narrow viewport checks for Scout Preview and Clinical Monitoring did not show horizontal overflow in the gated state.
- Deleted all three fixture rows after testing.

### Blocker Found

- Organization `1394` is currently treated as `Pending Day 0 Setup`; Step 3 (`services_clinical_model`) is still in progress at 53%.
- Because of that Day 0 completion gate, Scout Preview and downstream module pages did not render the inserted fixture workflows.
- Scout Preview loaded its page shell but remained in a loading/gated state instead of showing persona context, workflow cards, sources, warnings, and fixture detail panels.
- Reporting, Committees, Plans & Policies, and Clinical Monitoring rendered their polished Pending Day 0 states rather than the inserted Scout workflow data.
- Settings rendered safely, but reflected Pending Day 0 status and existing onboarding preferences rather than fixture workflow content.

### Follow-Up Needed

- Complete Day 0 normally for organization `1394`, or repeat this fixture QA against a local organization that already satisfies the submitted Day 0 condition.
- Reinsert the fixtures one at a time and verify the full renderer coverage matrix after the Day 0 gate is satisfied.
- No version bump was made because this pass changed documentation only.

## Scout Fixture QA-3

### Manual QA Results

- Rechecked local organizations and found no workspace already fully submitted/complete for Day 0.
- Used the fallback path on organization `1394`.
- Completed Step 3 (`services_clinical_model`) with safe, fictional structural/service values and confirmed all eight Day 0 sections now show 100% complete.
- Attempted final setup submission through the Step 8 final confirmation flow; a normal Scout run was created/completed afterward.
- Revalidated all three fixture JSON files successfully.
- Inserted the Experienced CAH / Joint Commission fixture as `fixture-experienced-cah-jc`.
- Stopped before testing the Rural PPS and New CAH CMS fixtures because the first fixture exposed a route/state hydration blocker.
- Deleted the inserted fixture row and confirmed `remaining_fixture_rows = 0`.

### Blocker Found

- Direct route loads for downstream modules still render stale/generic state after Day 0 is 100% complete:
  - `#reporting`
  - `#committees`
  - `#plans`
  - `#clinical-monitoring`
  - `#settings`
- The Day 0 route correctly renders organization `1394`, the selected hospital name, and 100% setup progress.
- The downstream routes still render `Hospital workspace`, `Hospital type not set`, `Service model not set`, and `Scout: Pending Day 0`.
- Scout Preview direct route remains on `Loading Scout preview...` instead of rendering the latest completed fixture run.
- No raw JSON, PHI, or QualiNav JavaScript error was observed in this blocked state. The known unrelated Elementor `elementorFrontendConfig` error remains present.

### Proposed Fix Before Re-running QA

- Investigate `assets/js/qualinav-console.js`, especially hospital console initialization, direct-hash route rendering, `loadOnboarding()`, `loadScoutRuns()`, and module re-render ordering.
- The likely issue is that direct module routes render before selected organization/onboarding/scout state hydrates and are not being updated with the preview organization context afterward, while Day 0 itself hydrates correctly.
- Do not mark fixture renderer QA complete until direct module routes render the selected organization and latest completed fixture run.

### Version

- No version bump was made because QA-3 changed documentation only and no production code was modified.

## QA-RouteHydration-1

### Changes Made

- Fixed direct hash route hydration for hospital console modules by re-rendering Scout Preview and downstream module pages after onboarding and Scout run state finish loading.
- Updated selected hospital context resolution so direct `organization_id` preview routes can use the hydrated onboarding organization instead of falling back to generic `Hospital workspace` context.
- Updated Scout Preview state ordering so an existing latest Scout run can render even if the bridge is currently unavailable; bridge-unavailable messaging now applies when there is no latest run to show.
- Fixed a Reporting/Committees preview renderer name collision so downstream fixture rows render as report/committee rows instead of Day 0 repeater controls.
- Bumped the plugin version to `0.1.48` because JavaScript and PHP version metadata changed.

### Verification Scope

- This phase verified the route hydration blocker only.
- Full three-fixture Scout renderer QA remains pending and should be rerun as the next fixture QA phase.

### Known Limitations

- No endpoint, route, database schema, Day 0 gating, or fixture loading behavior was changed.
- No fixture endpoint or production fixture loader was added.

## QA-PlansPoliciesRenderer-1

### Changes Made

- Fixed Plans & Policies template extraction so `template_needs` can render from Scout workflow response data, normalized preview data, and object-shaped plan/policy groups.
- Tightened Plans & Policies priority filtering so clinical monitoring priorities such as `Confirm blood usage review ownership` do not appear in the Plans & Policies module.
- Preserved valid plan/policy priorities such as QAPI Plan annual review evidence and plan/policy/template/approval-related items.
- Bumped the plugin version to `0.1.49` because JavaScript and PHP version metadata changed.

### Verification

- Inserted the Experienced CAH / Joint Commission fixture for organization `1394`.
- Confirmed `Templates Needed` count is `2`.
- Confirmed `Board quality report template` and `Survey readiness checklist` render.
- Confirmed `Confirm blood usage review ownership` is filtered out of Plans & Policies.
- Confirmed valid plan/policy priority `Refresh QAPI Plan annual review evidence` remains.
- Confirmed no raw JSON, no unsafe PHI text, no QualiNav JavaScript errors, and no horizontal overflow.
- Deleted fixture rows and confirmed `remaining_fixture_rows = 0`.

### Known Limitations

- Full three-fixture Scout QA remains pending for the QA-4 rerun.

## QA-MobileShellOverflow-1

### Changes Made

- Fixed the narrow viewport shell overflow that appeared during QA-4R on Clinical Monitoring.
- Added mobile shell containment at the `900px` breakpoint so `html`, `body`, `.qn-app-shell`, `.qn-sidebar`, `.qn-main`, `.qn-topbar`, and active page panels can shrink within the viewport.
- Constrained mobile sidebar/home/nav items to `max-width: 100%` with `box-sizing: border-box` so expanded or hover state styles do not force a wider document.
- Added wrapping protection for the current hospital name in the topbar.
- Bumped the plugin version to `0.1.50` because CSS and PHP version metadata changed.

### Verification

- Inserted the Experienced CAH / Joint Commission fixture for organization `1394`.
- Checked at approximately `430px` and `390px` viewport widths:
  - `#clinical-monitoring`
  - `#scout-preview`
  - `#reporting`
  - `#committees`
  - `#plans`
  - `#settings`
  - `#day-0-setup`
- Confirmed document width no longer exceeds viewport width on those routes.
- Confirmed Clinical Monitoring fixture content still renders at narrow width.
- Confirmed no raw JSON, no unsafe PHI text, and no QualiNav JavaScript errors.
- Deleted fixture rows and confirmed `remaining_fixture_rows = 0`.

### Known Limitations

- Full three-fixture Scout QA remains pending for the QA-4R rerun.

## QA-AdminInviteRole-1

### Changes Made

- Fixed admin invitation role validation for WordPress administrator accounts that rely on the QualiNav super-admin fallback.
- Invite creation now uses the effective QualiNav role for admin inviters instead of the raw nullable `qualinav_role` column.
- Bumped the plugin version to `0.1.51` because PHP changed.

### Verification

- Confirmed the production account `vijay.koushik@dotankdo.com` is a WordPress administrator, active in QualiNav, and resolves to `qualinav_super_admin`.

### Known Limitations

- This does not change the invite role hierarchy; it only fixes the fallback role used for admin invitation validation.

## QA-AdminRoleResolution-1

### Changes Made

- Hardened QualiNav role resolution so WordPress administrator accounts always resolve as `qualinav_super_admin`.
- This prevents a hospital workspace role stored in the custom QualiNav user columns from downgrading access to the internal admin console.
- Bumped the plugin version to `0.1.52` because PHP changed.

### Verification

- Restored `vijay.koushik@dotankdo.com` to WordPress administrator and QualiNav super admin on production.
- Confirmed `/qualinav/admin` loads again and shows `QualiNav Super Admin`.

## QA-Day0Step1Hydration-1

### Changes Made

- Added organization-context hydration for Step 1 bed fields.
- `licensed_beds` now falls back to the existing organization `beds` column when no Day 0 answer is saved.
- `acute_beds` and `swing_beds` can hydrate if matching organization columns exist, while preserving empty fields when no source exists.
- Bumped the plugin version to `0.1.53` because PHP and JavaScript changed.

### Verification

- Confirmed production Mammoth Hospital has `beds = 17`, which now maps to Step 1 `Licensed beds`.

## QA-HospitalModalFields-1

### Changes Made

- Added existing `wp_organizations` fields to the admin Edit Hospital modal: ZIP, Licensed Beds, and Payment Model.
- Wired those fields through the admin hospital save payload without adding schema.
- Confirmed the production table has `zip`, `beds`, and `payment_model`; `timezone` and `ccn` are displayed by the current modal but are not present in the production organization table.
- Bumped the plugin version to `0.1.54` because PHP, JavaScript, and template files changed.

### Known Limitations

- Region is present as `region_id` in the table but has no region lookup/editor in this plugin yet.

## QA-StagingSubmit-1

### Changes Made

- Fixed the Day 0 final submit flow so it cannot remain permanently disabled in a `Submitting...` state.
- Added a 60-second XHR timeout and safe visible error handling for unreadable responses, failed requests, permission errors, validation errors, and timeouts.
- Changed final submit to fail closed if the Step 8 save fails before submit.
- Decoupled Day 0 submission from synchronous Scout generation. Day 0 now submits first; Scout preview generation is available from Scout Setup Preview.
- Added server-side invalid-organization handling and a safe REST exception response for final submit.
- Updated Step 8 confirmation copy to say Scout can be generated after submission.
- Bumped the plugin version to `0.1.56` because PHP and JavaScript changed.

### Verification

- Deployed the changed plugin files to staging `https://qualinav.io` and confirmed the browser loaded `qualinav-console.js?ver=0.1.56-*`.
- Retested organization `1382`, `Ozark Ridge Critical Access Hospital QA 2026-06-19`.
- Clicking `Submit Final Setup` returned with a visible message: `Day 0 setup was submitted. Generate Scout setup preview from the Scout Setup Preview page.`
- The submit button was restored and did not remain stuck in `Submitting...`.
- Reload confirmed Day 0 answers/context still loaded for the selected hospital.
- Scout Setup Preview opened for the selected hospital and showed a ready latest preview with New QD / CMS state survey persona context.
- No raw JSON appeared as the primary Scout Preview UI.
- No QualiNav JavaScript errors were captured; the known Elementor `elementorFrontendConfig is not defined` error and a browser-tool clipboard bridge artifact were ignored.

### Known Limitations

- The Day 0 page still displays the progress meter as `73% complete` after successful submission because the progress display reflects answer completeness rather than submitted status.
- The wizard does not yet show a persistent submitted/completed banner after reload.

## QA-StagingSubmittedState-1

### Changes Made

- Fixed submitted-state propagation so downstream modules no longer rely only on answer-completion percentage.
- Added explicit `onboarding_submitted` and `onboarding_status` values to the Day 0 payload.
- Added `onboarding_status` to the Scout runs payload.
- Hardened `QN_Scout::is_onboarding_submitted()` so an existing Scout run counts as a submitted-state fallback when explicit status is unavailable.
- Updated frontend state derivation so `/onboarding`, `/scout/runs`, explicit submitted status, and latest Scout run can all prevent stale `Pending Day 0 Setup` messaging.
- Added a shared `isDay0Pending()` helper for downstream module renderers.
- Updated the Day 0 progress text after submit to show `Submitted - 73% complete` and clarify that 73% is answer completeness.
- Bumped the plugin version to `0.1.57` because PHP and JavaScript changed.

### Verification

- Deployed the changed plugin files to staging `https://qualinav.io` and confirmed the browser loaded `qualinav-console.js?ver=0.1.57-*`.
- Retested organization `1382`, `Ozark Ridge Critical Access Hospital QA 2026-06-19`.
- Day 0 now shows submitted status after reload while preserving `73%` answer completeness.
- Scout Setup Preview still opens and shows selected hospital/persona context with Scout status `Ready`.
- Reporting, Committees, Plans & Policies, and Clinical Monitoring no longer show `Pending Day 0 Setup` or `Continue Day 0 Setup`; they show module-specific `Pending Scout` / missing-detail states.
- Settings shows `Preferences Ready` and setup-derived Step 8 preferences.
- Confirmed no raw JSON, no unsafe PHI text, and no desktop horizontal overflow on checked routes.
- No QualiNav JavaScript errors were captured; the known Elementor `elementorFrontendConfig is not defined` error was ignored.

### Known Limitations

- Scout Preview still reports `model_structured_result_insufficient`, with downstream module sections not returned. Full staging E2E remains blocked on Scout output quality, not submitted-state propagation.

## Scout Backend Contract Validation - Day 0 Workflow Output

### Changes Made

- Fixed the GrapevineAI Scout backend response contract for `qualinav-day0-workflow-generation-v1`.
- Added Day 0 workflow fields to the backend response model: `workflow_version`, `persona_experience_summary`, `workflows`, `source_count`, and `missing_inputs`.
- Added a backend Day 0 workflow response path for `scout_day0_workflow_generation` requests that returns structured workflow sections from submitted Day 0 answers instead of the generic action-plan schema.
- Added a focused backend contract test for the New CAH / CMS state survey workflow output.
- Deployed backend candidate `day0workflow0619b` to Cloud Run and promoted it to 100% traffic.
- WordPress plugin files were not changed; plugin version remains `0.1.57`.

### Verification

- Before fix, latest org `1382` Scout run was `completed` but returned generic keys only and warning `model_structured_result_insufficient`.
- Confirmed WordPress request payload was correct: `request_type=scout_day0_workflow_generation`, `input_data_type=qualinav-day0-workflow-generation-v1`, organization context, persona context, grouped Day 0 answers, no-PHI constraints, and requested workflow outputs were present.
- Triggered a real Scout run through `QN_Scout::generate_for_organization()` after backend deployment. Latest run `3` completed with workflow keys for reporting, committees, survey readiness, plans/policies, clinical monitoring, aggregate uploads, routine rhythm, QI projects, priority queue, learning journeys, and reminders.
- Scout Preview now shows `Ready`, `Warnings 0`, `Missing Inputs 0`, selected hospital/persona context, and structured workflow content.
- Reporting renders 11 schedule rows including MBQIP items.
- Committees renders 3 committee/report-flow rows.
- Plans & Policies renders 7 plan/policy rows and template signals.
- Clinical Monitoring renders recurring monitoring, aggregate uploads, routine rhythm, active projects, and priority gaps.
- Settings remains the settings renderer with workspace context and preferences, not a Scout response renderer.
- Desktop and 395px narrow checks showed no horizontal overflow on Scout Preview, Reporting, Committees, Plans & Policies, Clinical Monitoring, or Settings.
- No raw JSON or unsafe PHI text appeared in the checked UI.
- No QualiNav/Grapevine JavaScript errors were captured; the known Elementor `elementorFrontendConfig is not defined` error was ignored.

### Known Limitations

- Backend response is deterministic from Day 0 structured inputs for this workflow contract and returns `source_count=0` unless future evidence/source enrichment is added.
- The visible completed Scout Preview state does not expose a refresh button; final retest used the safe admin flow through `QN_Scout::generate_for_organization()` rather than manual row insertion.

## Stakeholder Feedback Pass 1

### Changes Made

- Added a Day 0 setup guide modal with Quality Director-friendly onboarding copy, save-at-your-own-pace guidance, materials-to-gather checklist, and explicit no-PHI/no-case-detail language.
- Added a Day 0 header action to reopen the setup guide at any time.
- Added a print-friendly Day 0 question list workflow labeled `Print / save as PDF`. It is generated from questionnaire metadata and does not include saved answers by default.
- Moved Scout guidance out of the permanent right-side column and into an expandable top-of-section `How Scout uses this section` panel.
- Changed the Day 0 layout from three columns to two columns so the form has more horizontal room.
- Added friendly post-submit completion copy explaining that Scout can build the hospital operating-system preview, setup can be updated later, and annual review will be prompted.
- Added lightweight annual review copy to Settings setup status.
- Added compact admin hospital table visibility for Day 0 section progress counts with section-level detail in the tooltip.

### Verification Targets

- Day 0 should load saved answers and preserve submitted state.
- Setup guide should open/close and avoid repeated automatic display through localStorage.
- Print/save question list should open without saved answers or raw metadata/debug keys.
- Scout Preview and downstream modules should remain unchanged in behavior.

### Known Limitations

- No actual annual scheduler/reminder automation was built in this pass.
- Jack measure/QI tool integration was not built.
- Committee flowchart/flow-map engine was not built; current committee module remains row/detail oriented.

## Stakeholder Feedback Pass 2 - Hospital Setup Top Stepper Layout

### Changes Made

- Removed the internal left-side Hospital Setup stepper column on desktop/tablet by changing the setup layout to a single full-width form column.
- Reworked the existing setup stepper into a compact top progress rail above the active form.
- Preserved the existing step buttons, status derivation, active-step state, completed check icon, and click handlers.
- Let the active Hospital Setup form occupy the full available card width while keeping the Scout guidance in the expandable `How Scout uses this section` panel.
- Kept mobile simple and low-risk: the top stepper wraps to two columns on tablet/small screens and one column at narrow phone widths to avoid document overflow.
- Bumped the plugin version to `0.1.74` because CSS changed.

### Verification Targets

- Hospital Setup should load saved answers and continue to show submitted answer-completeness status for org `1382`.
- Top stepper should display all 8 sections with correct active/complete/in-progress/not-started visual states.
- Step clicks, Previous, Save, Save & Continue, setup guide, print/save question list, and expandable Scout guidance should continue to work.
- Scout Preview and downstream modules should remain unchanged.

### Known Limitations

- This pass does not change Hospital Setup permissions, internal storage, Scout generation, REST endpoints, or database schema.

## UX Copy Fix - Hospital Setup Naming

### Changes Made

- Replaced user-facing `Day 0` labels with `Hospital Setup` language across the hospital console navigation, dashboard, setup guide, print/save setup questions workflow, Scout/module pending states, Settings setup status, admin progress labels, and submit/permission messages.
- Day 0 remains the internal implementation term for code identifiers, route hash `#day-0-setup`, questionnaire/storage keys, audit history, and Scout contract context.
- Hospital Setup is now the user-facing name for Quality Directors and admin-facing product surfaces.
- No schema, backend, Scout contract, route, questionnaire key, or storage behavior changed.

## Permission QA Fix - Viewer Hospital Setup Actions

### Changes Made

- Fixed a viewer-role Hospital Setup defect where `Save & Continue` stayed enabled as a write-style action even though fields and `Save` were disabled.
- Added a role-aware frontend edit helper so Hospital Setup write actions require both the onboarding edit flag and an editor/admin role.
- Read-only users now see a calm view-only notice and get `Next section` navigation instead of `Save & Continue`.
- Viewer Previous, Next section, and stepper navigation do not fire save requests; QD/editor save behavior is preserved.
- Submit Final Setup remains hidden/disabled for roles that cannot submit.
- Bumped the plugin version to `0.1.76` because JS/CSS/PHP changed.

### Verification Targets

- Viewer should be able to review Hospital Setup sections without enabled write/save actions or permission-error toasts from navigation.
- QD/editor Save, Save & Continue, Previous, stepper navigation, and submit behavior should remain unchanged.
- Super Admin admin-console and preview behavior should remain unchanged.

## Hospital Setup Guidance Cleanup - Compact Scout Help

### Changes Made

- Replaced the large always-expanded section-level Scout guidance card with a compact optional disclosure beside the active section heading.
- Preserved the existing section-specific guidance bullets and no-PHI reminders.
- Guidance is collapsed by default and collapses again when the user changes Hospital Setup sections.
- Opening or closing guidance is UI-only and does not trigger save requests or permission changes.
- Bumped the plugin version to `0.1.77` because JS/CSS/PHP changed.
- Follow-up: added explicit `Show details` / `Hide details` action text, chevron state, hover/focus styling, and ARIA state updates so the compact Scout guidance reads as an interactive secondary CTA.
- Bumped the plugin version to `0.1.78` because JS/CSS/PHP changed.
- Follow-up: moved the `Show details` / `Hide details` pill beside the Scout guidance label instead of anchoring it to the far right edge, keeping the full row clickable while reducing eye travel on wide screens.
- Bumped the plugin version to `0.1.79` because CSS changed.

## Quality Director Workspace Welcome Modal + Hospital Setup Guidance Upgrade

### Changes Made

- Added a concise Quality Director workspace welcome modal with premium orientation copy, three value cards, setup reassurance, and no-PHI safety guidance.
- Added a dashboard entry card with `Open workspace guide` and Hospital Setup CTA actions.
- Added localStorage dismiss behavior per organization/role so the welcome modal does not repeat on every route load.
- Added an elegant footer checkbox, checked by default: `Don’t show this welcome automatically again on this browser.` Helper copy explains that users can reopen the guide from the dashboard.
- Suppressed automatic workspace welcome display for QualiNav Super Admin/admin console usage; Super Admin can continue QA/admin preview without interruption.
- Follow-up: kept auto-show suppressed for Super Admin but exposed manual workspace guide entry points in Super Admin hospital preview on both the dashboard card and Hospital Setup header.
- Adjusted viewer wording so the modal says `View Hospital Setup` / review-oriented copy rather than implying edit permission.
- Upgraded the Hospital Setup Guide copy to explain why questions matter, partial completion, nearby materials, post-submit Scout output, annual review, and data safety.
- Kept print/save setup questions, Hospital Setup save/submit behavior, viewer read-only navigation, Scout Preview, and downstream modules unchanged.
- Bumped the plugin version to `0.1.82` because PHP/JS changed.

### Verification Targets

- QD/editor should see the welcome orientation once per browser unless reopened manually from the dashboard.
- Viewer should see review-oriented copy and no write implication.
- Super Admin should not be interrupted in `/qualinav/admin`.
- Desktop and 390-430px mobile layouts should avoid horizontal overflow.

### Verification Targets

- Hospital Setup fields should appear higher on the page because the help panel no longer consumes vertical space by default.
- QD/editor save behavior and viewer read-only `Next section` behavior should remain unchanged.
- Desktop and narrow mobile layouts should avoid horizontal overflow.

## Invite Magic-Link Onboarding Flow

### Changes Made

- Removed the password setup requirement for Quality Director and hospital workspace invite acceptance.
- Hospital-role invite links now validate the QualiNav invite token, activate the invited hospital access, create a Grapevine magic-login handoff, and route users into `/onboarding/` when Grapevine onboarding is incomplete.
- Grapevine onboarding completion now redirects QualiNav invite handoff users to `/qualinav?organization_id={id}#day-0-setup`.
- Existing-user password overwrite risk is fixed for the hospital invite path because the magic-link acceptance flow never calls `wp_set_password()`.
- Invite email copy now says the secure link starts QualiNav onboarding and no longer tells invited hospital users to set a password.
- No database schema, Scout backend, REST route, Hospital Setup storage, or question-key changes were made.

### Verification Targets

- New QD and viewer invites should not show the password setup form.
- Existing users invited to another hospital should keep their current password and existing hospital access.
- Wrong-user invite clicks should show a safe branded error and not activate the invite.
- Expired, revoked, already accepted, and resent invites should keep their existing safe states.

## Invite Expiry Security Fix - UTC Expiration Validation

### Changes Made

- Fixed expired invite validation for passwordless hospital invite acceptance.
- `expires_at` is stored as a UTC timestamp and is now parsed explicitly as UTC during acceptance checks.
- Missing, invalid, or unparseable `expires_at` values now fail closed as expired instead of allowing invite acceptance.
- Expiry equality is safe: an invite expiring at the current UTC timestamp is rejected.
- No database schema, Scout backend, Grapevine Login, magic-link token behavior, onboarding flow, resend behavior, or invite role behavior changed.
- Bumped the QualiNav Admin Console plugin version to `0.1.85` because PHP changed.

### Verification Targets

- Expired invites must not create Grapevine magic-login tokens, activate user access, mark invitations accepted, or log users in.
- Valid future invites should still route through Grapevine magic-login and `/onboarding/`.
- Revoked, already accepted, wrong-user, and old resend-token safety states should remain unchanged.

## Workspace Welcome Modal Stacking Fix

### Changes Made

- Fixed a modal-stacking issue where the Quality Director workspace welcome modal and Hospital Setup guide modal could auto-open on the same route after invite/onboarding redirect.
- Added a shared modal-close helper so opening workspace welcome, Hospital Setup guide, or print/save setup questions closes any other open QualiNav modal first.
- Hospital Setup guide auto-open now defers if the workspace welcome modal is already visible.
- Manual guide buttons still work, but only one modal owns the screen at a time.
- No storage, database schema, Scout backend, Grapevine Login, magic-link, onboarding, invite, or permission behavior changed.
- Bumped the QualiNav Admin Console plugin version to `0.1.86` because JS/PHP changed.

### Verification Targets

- After invite/onboarding redirect to Hospital Setup, only the workspace welcome modal should be visible.
- Opening Setup guide or Print/save setup questions should close the welcome modal first.
- Escape should still close open QualiNav modals.
