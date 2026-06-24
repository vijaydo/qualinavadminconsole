# Phase 5A Scout API / GrapevineAI Integration Audit

Project: QualiNav Admin Console  
Build Rank: 26  
Plugin version audited: `0.1.1`  
Local plugin path: `C:\wamp64\www\qualinavio\wp-content\plugins\qualinav-admin-console`  
Audit date: 2026-06-17

## Summary

Phase 5A was completed as an audit-only pass. No Phase 5 workflow generator was implemented.

The local WordPress environment includes an active-looking `grapevine-ai` plugin that already provides a server-side Scout bridge function:

```php
grapevine_ai_run_scout_task(array $args)
```

That function sends WordPress-mediated requests to the Grapevine AI backend endpoint:

```text
POST /scout/task
```

The safest next architecture is **Option C: Hybrid**. WordPress should normalize Day 0 onboarding data, call GrapevineAI Scout for generated recommendations, store the request/response run, and display a preview. If the backend is unavailable or not configured, WordPress should show a clear retry/configuration state and must not invent workflow output locally.

Main gating item for Phase 5B: the existing Grapevine AI Scout task allowlist does not currently include `scout_day0_workflow_generation`. Phase 5B should either coordinate a backend/bridge addition for that task type or temporarily map the call to an existing supported task type such as `generate_action_plan` with a clearly versioned `input_data_type`.

## Files Inspected

QualiNav Admin Console:

- `qualinav-admin-console.php`
- `includes/class-qn-activator.php`
- `includes/class-qn-audit-log.php`
- `includes/class-qn-auth.php`
- `includes/class-qn-branding.php`
- `includes/class-qn-db.php`
- `includes/class-qn-health-systems.php`
- `includes/class-qn-invitations.php`
- `includes/class-qn-onboarding.php`
- `includes/class-qn-organizations.php`
- `includes/class-qn-permissions.php`
- `includes/class-qn-questionnaire.php`
- `includes/class-qn-rest-api.php`
- `includes/class-qn-router.php`
- `includes/class-qn-users.php`
- `templates/admin-console-shell.php`
- `templates/console-shell.php`
- `templates/accept-invite.php`
- `assets/js/qualinav-console.js`
- `assets/css/qualinav-console.css`
- `docs/PHASE_1_TO_4_QA_CHECKLIST.md`

Grapevine/Scout-related local plugins and theme areas:

- `wp-content/plugins/grapevine-ai/grapevine-ai.php`
- `wp-content/plugins/grapevine-ai/includes/class-grapevine-ai-plugin.php`
- `wp-content/plugins/grapevine-ai/includes/class-grapevine-ai-backend-client.php`
- `wp-content/plugins/grapevine-ai/assets/grapevine-ai.js`
- `wp-content/plugins/grapevine-ai/README.md`
- `wp-content/plugins/qualinav-quality-lab/includes/class-qlab-scout-recommendations.php`
- `wp-content/plugins/qualinav-quality-lab/assets/js/quality-lab-scout-recommendations.js`
- `wp-content/plugins/qualinav-scout-roadmap/qualinav-scout-roadmap.php`
- `wp-content/plugins/qualinav-content-hub/`
- `wp-content/themes/grapevine-theme-0.0.15/`
- `wp-config.php` was inspected only for constant names/environment presence. Secret values are not documented here.

## Current QualiNav Admin Console State

Loaded classes:

- `QN_DB`
- `QN_Activator`
- `QN_Users`
- `QN_Permissions`
- `QN_Organizations`
- `QN_Health_Systems`
- `QN_Branding`
- `QN_Invitations`
- `QN_Questionnaire`
- `QN_Onboarding`
- `QN_Auth`
- `QN_Router`
- `QN_Audit_Log`
- `QN_REST_API`

Registered REST namespace:

```text
qualinav/v1
```

Registered endpoints:

- `GET /me`
- `GET /brand`
- `GET /my-organizations`
- `POST /switch-organization`
- `GET /admin/dashboard`
- `GET /admin/system-check`
- `GET /admin/hospitals`
- `POST /admin/hospitals`
- `GET /admin/hospitals/{id}`
- `PUT /admin/hospitals/{id}`
- `GET /admin/states`
- `GET /admin/brand/{organization_id}`
- `PUT /admin/brand/{organization_id}`
- `GET /admin/health-systems`
- `POST /admin/health-systems`
- `GET /admin/health-systems/{id}`
- `PUT /admin/health-systems/{id}`
- `GET /admin/health-systems/{id}/hospitals`
- `GET /hospital-types`
- `GET /service-models`
- `GET /admin/users`
- `POST /admin/users/invite`
- `POST /hospital/users/invite`
- `GET /hospital/users`
- `GET /hospital/invitations`
- `PUT /admin/users/{id}/role`
- `PUT /hospital/users/{id}/role`
- `PUT /admin/users/{id}/status`
- `PUT /hospital/users/{id}/status`
- `GET /admin/invitations`
- `POST /admin/invitations/{id}/resend`
- `POST /admin/invitations/{id}/revoke`
- `POST /hospital/invitations/{id}/resend`
- `POST /hospital/invitations/{id}/revoke`
- `GET /onboarding`
- `POST /onboarding/save`
- `GET /onboarding/progress`
- `POST /onboarding/submit`

Frontend routes:

- `/qualinav`
- `/qualinav/admin`
- `/qualinav/accept-invite`

Existing API/backend helpers in QualiNav Admin Console:

- No remote API client class exists.
- No `wp_remote_post`, `wp_remote_get`, `wp_remote_request`, cURL, or Scout API call exists inside this plugin.
- No GrapevineAI-specific configuration constants are defined by this plugin.

Existing Scout/workflow code in QualiNav Admin Console:

- Scout is referenced only in UI/help copy.
- Day 0 onboarding copy says what answers inform in Scout.
- No generated workflow table exists.
- No `wp_qualinav_generated_workflows` table exists.
- No `wp_qualinav_scout_runs` table exists.
- No Phase 5 workflow generator exists.

Current onboarding submit behavior:

- `POST /onboarding/submit` resolves the selected organization.
- It calls `QN_Onboarding::submit_onboarding($organization_id, $user_id)`.
- Required questions are checked.
- Organization onboarding columns are updated defensively if present.
- Audit action `onboarding_submitted` is logged.
- It does not call Scout or generate workflows.

Error/logging patterns:

- REST errors use `WP_Error` with HTTP status arrays.
- Audit logging uses `QN_Audit_Log::log($action, $entity_type, $entity_id, $before, $after, $organization_id)`.
- Invitation mail failure is tracked with `email_status`, `email_error`, and audit action `user_invited_email_failed`.
- Grapevine AI backend client decodes non-2xx backend responses into `WP_Error`.

## GrapevineAI / Scout References Found

Installed relevant plugins:

- `grapevine-ai`
- `qualinav-scout-roadmap`
- `qualinav-quality-lab`
- `qualinav-content-hub`

`grapevine-ai` plugin:

- Plugin version: `0.4.239`
- Public PHP bridge: `grapevine_ai_run_scout_task(array $args)`
- Backend HTTP class: `Grapevine_AI_Backend_Client`
- Settings option: `grapevine_ai_settings`
- Default backend URL is a Cloud Run Grapevine AI backend URL in plugin defaults.
- Local settings are configured for backend URL, hub ID, tenant ID, WordPress AI key, documents token, and admin setup token. Sensitive values are intentionally not documented.

`qualinav-quality-lab` plugin:

- Uses `grapevine_ai_run_scout_task()` for a Scout-backed recommendations tile.
- Existing task call uses:
  - `task_type`: `summarize_findings`
  - `caller_plugin`: `quality-lab-recommendation-tile`
  - `input_data_type`: `quality-lab-ranked-recommendation-candidates`
  - `output_format`: `structured_json`
  - `include_sources`: `true`
  - `persist_result`: `false`
- Normalizes result keys such as `action_items`, `goals`, `recommendations`, and `commitments`.

`qualinav-scout-roadmap` plugin:

- Prototype/gamified onboarding flow.
- Mostly local WordPress UI/state logic.
- No clear Grapevine AI backend API call found in the active file during this audit.

## Existing GrapevineAI Backend API Contract

Preferred WordPress bridge:

```php
$result = grapevine_ai_run_scout_task(array(
    'task_type'       => 'summarize_findings',
    'caller_plugin'   => 'quality-lab-recommendation-tile',
    'input_data_type' => 'quality-lab-ranked-recommendation-candidates',
    'input_data'      => $input_data,
    'output_format'   => 'structured_json',
    'include_sources' => true,
    'persist_result'  => false,
));
```

Backend endpoint used by bridge:

```text
POST /scout/task
```

Authentication:

- Server-side only.
- Header: `X-WordPress-AI-Key: <secret>`
- Browser does not receive backend keys.
- Grapevine AI REST endpoints also accept `x-wordpress-ai-key` or `x-gv-ai-key` for backend structured reads.

Backend base URL:

- Setting key: `backend_url`
- Option group: `grapevine_ai_settings`
- Constant-based override exists for secret keys, not for all visible settings.
- Do not expose backend keys or tokens to frontend JavaScript.

Required local GrapevineAI settings for Scout bridge:

- `backend_url`
- `hub_id`
- `wordpress_ai_key`
- `chat_enabled = 1`
- Current user must satisfy configured `chat_access_capability`.

Request payload generated by `run_scout_task()`:

```json
{
  "task_type": "summarize_findings",
  "hub_id": "configured hub id",
  "tenant_id": "configured tenant id or null",
  "requester_user_id": "stable Grapevine AI UUID mapped from current WP user",
  "requester_context": {
    "wordpress_user_id": "current user context and safe metadata"
  },
  "caller_plugin": "caller plugin key",
  "input_data_type": "input type key",
  "input_data": {},
  "document_ids": [],
  "use_approved_knowledge": true,
  "use_user_uploads": false,
  "use_project_sources": false,
  "persona": "general_assistant",
  "output_format": "structured_json",
  "max_goals": 5,
  "include_sources": true,
  "persist_result": false,
  "correlation_id": "uuid"
}
```

Scout task type allowlist currently permits:

- `generate_goals`
- `generate_commitments`
- `generate_action_plan`
- `summarize_findings`
- `board_summary`
- `qapi_recommendations`

Output format allowlist currently permits:

- `structured_json`
- `goals_commitments_json`
- `markdown_summary`

Input size limit:

- `input_data` JSON encoding must be no more than 40,000 bytes.

Timeout/retry behavior:

- JSON backend calls use WordPress HTTP with a 45 second timeout.
- Streaming/multipart calls use cURL with a 90 second timeout.
- No retry loop was found in the backend client.

Error behavior:

- Non-2xx backend responses become `WP_Error`.
- Error message extraction checks response fields `message`, `detail`, then `error`.
- If none are present, a generic backend status message is returned.

Response shape:

- `run_scout_task()` returns decoded backend JSON directly.
- Quality Lab currently expects `status = ok` and one or more of:
  - `action_items`
  - `goals`
  - `recommendations`
  - `commitments`
- Existing code references sources and source counts in chat/UI contexts, but no Day 0 workflow-specific response contract exists yet.

## Security Notes

- Do not send PHI.
- Do not send patient names, MRNs, provider case details, peer-review details, incident narratives, risk-management case files, or specific adverse-event details.
- Do not send raw WordPress passwords.
- Do not send invite raw tokens or token hashes.
- Do not expose GrapevineAI backend keys in frontend JS.
- Do not store raw backend secrets in QualiNav Admin Console settings unless WordPress admin storage and masking are deliberately added.
- Use the existing Grapevine AI bridge where possible because it already keeps secrets server-side.
- `wp-config.php` contains sensitive constants; values were not copied into this document.

## Recommended Architecture

Recommendation: **Option C, Hybrid**.

WordPress should:

- Normalize the selected hospital context.
- Normalize Day 0 onboarding answers into a stable payload.
- Validate permissions using QualiNav hospital mappings.
- Call GrapevineAI Scout via the existing WordPress bridge if available.
- Store request/response metadata in a run table.
- Display a Scout Setup Preview from the returned response.
- Show a retry/configuration state if the API is unavailable.

GrapevineAI Scout should:

- Generate recommended workflow plan content.
- Return structured JSON.
- Return warnings and missing input fields.
- Return source metadata where applicable.

Why not Option A:

- A local-only WordPress generator would duplicate Scout intelligence and may diverge from the existing GrapevineAI backend.
- It risks inventing workflows when the backend is unavailable.

Why not pure Option B:

- WordPress still needs deterministic normalization, storage, permission checks, retry state, and preview rendering.
- A pure pass-through would make local state/audit harder to control.

## Proposed Scout API Payload

Phase 5B should build an input payload like this and pass it as `input_data` to `grapevine_ai_run_scout_task()`.

Recommended bridge call:

```php
$result = grapevine_ai_run_scout_task(array(
    'task_type'       => 'generate_action_plan',
    'caller_plugin'   => 'qualinav-admin-console',
    'input_data_type' => 'qualinav-day0-workflow-generation-v1',
    'input_data'      => $payload,
    'output_format'   => 'structured_json',
    'include_sources' => true,
    'persist_result'  => true,
    'max_goals'       => 12,
    'correlation_id'  => wp_generate_uuid4(),
));
```

Preferred future task type, if GrapevineAI/backend allowlist is extended:

```text
scout_day0_workflow_generation
```

Payload:

```json
{
  "source": "qualinav_admin_console",
  "request_type": "scout_day0_workflow_generation",
  "questionnaire_version": "day0_v1",
  "organization_id": 123,
  "organization_name": "Example Hospital",
  "parent_system_id": 10,
  "parent_system_name": "Example Health System",
  "hospital_type": "critical_access_hospital",
  "hospital_type_label": "Critical Access Hospital",
  "service_model": "system_owned",
  "service_model_label": "System-Owned",
  "state_id": 5,
  "state_code": "TN",
  "current_user_id": 24,
  "current_user_role": "quality_director",
  "onboarding_answers": {
    "hospital_director_info": {},
    "accreditation_survey_readiness": {},
    "services_clinical_model": {},
    "committees_reporting": {},
    "plans_policies_monitoring": {},
    "measures_qi_projects": {},
    "goals_learning_contacts": {},
    "regulatory_tools_preferences": {}
  },
  "constraints": {
    "no_phi": true,
    "do_not_include_patient_names": true,
    "do_not_include_mrns": true,
    "do_not_include_provider_case_details": true,
    "do_not_include_incident_narratives": true,
    "do_not_include_peer_review_details": true
  },
  "requested_outputs": [
    "reporting_schedule",
    "committee_flow_map",
    "survey_readiness_timeline",
    "plan_policy_tasks",
    "clinical_monitoring_tasks",
    "qi_project_milestones",
    "external_contact_directory",
    "regulatory_monitoring_preferences",
    "learning_journey",
    "reminder_rules"
  ]
}
```

## Proposed Scout API Response

```json
{
  "success": true,
  "status": "ok",
  "request_id": "backend-request-id",
  "workflow_version": "day0_workflow_v1",
  "generated_at": "2026-06-17T00:00:00Z",
  "workflows": {
    "reporting_schedule": [],
    "committee_flow_map": [],
    "survey_readiness_timeline": [],
    "plan_policy_tasks": [],
    "clinical_monitoring_tasks": [],
    "qi_project_milestones": [],
    "external_contact_directory": [],
    "regulatory_monitoring_preferences": [],
    "learning_journey": [],
    "reminder_rules": []
  },
  "warnings": [],
  "missing_inputs": [],
  "source_count": 0,
  "sources": [],
  "error": null
}
```

Failed response:

```json
{
  "success": false,
  "status": "error",
  "request_id": "backend-request-id-if-available",
  "error": {
    "code": "scout_generation_failed",
    "message": "Safe user-facing message"
  },
  "warnings": [],
  "missing_inputs": []
}
```

## Proposed Storage Strategy

Create API-oriented table first:

```text
wp_qualinav_scout_runs
```

Recommended fields:

- `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `organization_id BIGINT UNSIGNED NOT NULL`
- `request_type VARCHAR(120) NOT NULL`
- `request_payload_json LONGTEXT NULL`
- `response_json LONGTEXT NULL`
- `status VARCHAR(30) NOT NULL DEFAULT 'pending'`
- `api_request_id VARCHAR(160) NULL`
- `source_count INT NULL`
- `error_message TEXT NULL`
- `generated_by BIGINT UNSIGNED NULL`
- `created_at DATETIME NOT NULL`
- `updated_at DATETIME NULL`

Recommended statuses:

- `pending`
- `running`
- `completed`
- `failed`
- `cancelled`

Optional later table:

```text
wp_qualinav_generated_workflows
```

Use this only if normalized extracted workflow views need first-class querying, assignment, completion tracking, or joins. For Phase 5B, store the full Scout run first, then render preview cards from `response_json`.

## Proposed UI Behavior

Before generation:

- Show: "Complete Day 0 Setup to generate Scout setup preview."
- If onboarding is not submitted, keep generation disabled.

After onboarding submit:

- If Scout API is configured, show a generating state and call the backend.
- If the API returns success, show workflow preview cards grouped by:
  - Reporting Schedule
  - Committee Flow Map
  - Survey Readiness Timeline
  - Plan & Policy Tasks
  - Clinical Monitoring Tasks
  - QI Project Milestones
  - External Contact Directory
  - Regulatory Monitoring Preferences
  - Learning Journey
  - Reminder Rules
- Show `warnings`, `missing_inputs`, `source_count`, and sources where relevant.

If API unavailable:

- Show a clear configuration warning to QualiNav admins.
- For hospital users, show a neutral unavailable/retry state.
- Do not fake generated Scout output.

If API fails:

- Preserve onboarding submission.
- Store a failed `wp_qualinav_scout_runs` record.
- Log an audit event such as `scout_generation_failed`.
- Show retry action.

## Settings Needed

Prefer using existing Grapevine AI settings and bridge:

- Grapevine AI option: `grapevine_ai_settings`
- Existing backend URL setting: `backend_url`
- Existing hub setting: `hub_id`
- Existing tenant setting: `tenant_id`
- Existing key setting/constant: `wordpress_ai_key` / `GRAPEVINE_AI_WORDPRESS_AI_KEY`
- Existing fallback constant: `GV_AI_SHARED_KEY`

QualiNav Admin Console can add feature flags without storing duplicate secrets:

- `QUALINAV_SCOUT_API_ENABLED`
- `QUALINAV_SCOUT_API_MODE` with values such as `live`, `disabled`, `mock_for_dev`
- `QUALINAV_SCOUT_API_TIMEOUT` only if bypassing the existing Grapevine AI client

Only add these if Phase 5B needs QualiNav-specific overrides:

- `QUALINAV_SCOUT_API_BASE_URL`
- `QUALINAV_SCOUT_API_KEY`

If added, never expose them in frontend JavaScript and mask them in admin diagnostics.

## Implementation Plan For Phase 5B

1. Add `QN_Scout` or `QN_Scout_Runs` class.
2. Add activation migration for `wp_qualinav_scout_runs`.
3. Add a capability/permission gate:
   - QD can generate for mapped active hospital.
   - Super Admin can generate for selected hospital.
   - Viewers cannot generate.
4. Build `QN_Onboarding::get_onboarding_payload()` into a Scout-safe normalized payload.
5. Add PHI stripping/validation guard before API call.
6. Add wrapper method:
   - Prefer `grapevine_ai_run_scout_task()` when available.
   - Return configuration error if unavailable or not configured.
7. Decide task type:
   - Preferred: extend GrapevineAI/backend to allow `scout_day0_workflow_generation`.
   - Interim: use `generate_action_plan` with `input_data_type = qualinav-day0-workflow-generation-v1`.
8. Add REST endpoints:
   - `POST /scout/generate`
   - `GET /scout/runs`
   - `GET /scout/runs/{id}`
   - Optional `POST /scout/runs/{id}/retry`
9. Update hospital console UI with Scout Setup Preview panel.
10. Update admin hospital table action with "Scout Preview."
11. Log:
    - `scout_generation_requested`
    - `scout_generation_completed`
    - `scout_generation_failed`
12. Do not convert preview into operational tasks until the workflow response contract is stable.

## Open Questions

- Should GrapevineAI backend add a first-class `scout_day0_workflow_generation` task type?
- Should `persist_result` be true in GrapevineAI backend as well as WordPress storing `wp_qualinav_scout_runs`?
- Should source-backed workflow recommendations require approved knowledge only, or also project/user-upload sources?
- Should generated workflow previews be editable before becoming operational tasks in a later phase?
- What should be the exact maximum payload size after all 8 Day 0 sections are complete, given the current 40 KB Scout input limit?

## Verification

- No workflow generator was implemented.
- Existing QualiNav plugin code was audited.
- Existing GrapevineAI Scout bridge was identified.
- API contract, payload, response, storage, UI, and Phase 5B plan are documented.
- No secrets, tokens, passwords, raw invite tokens, or full sensitive environment values are included.
