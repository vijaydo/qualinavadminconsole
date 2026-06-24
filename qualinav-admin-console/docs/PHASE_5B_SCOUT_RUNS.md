# Phase 5B Scout API Run Storage

## Architecture

QualiNav does not generate workflows locally in WordPress. WordPress builds a Day 0 setup payload, calls the existing GrapevineAI bridge function `grapevine_ai_run_scout_task()`, stores the run and backend response, and renders a Scout Setup Preview.

The bridge call stays server-side so backend URLs, keys, and tokens are never exposed in frontend JavaScript.

## Database

Phase 5B adds `wp_qualinav_scout_runs`:

- `organization_id` ties every run to one hospital workspace.
- `request_type` is `scout_day0_workflow_generation`.
- `input_data_type` is `qualinav-day0-workflow-generation-v1`.
- `request_payload_json` stores the sanitized Day 0 payload.
- `response_json` stores the bridge/backend response.
- `status` is one of `pending`, `running`, `completed`, `failed`, or `cancelled`.
- `api_request_id`, `source_count`, and `error_message` support preview and troubleshooting.

## Endpoints

- `POST /qualinav/v1/scout/generate`
- `GET /qualinav/v1/scout/runs`
- `GET /qualinav/v1/scout/runs/{id}`
- `POST /qualinav/v1/scout/runs/{id}/retry`

Hospital users use their current selected hospital from `wp_users.organization_id`. QualiNav admins may pass `organization_id`.

## Bridge Behavior

The plugin calls:

```php
grapevine_ai_run_scout_task(array(
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

If the bridge is missing, the run fails with a configuration message. If the payload exceeds 40,000 bytes, the run fails locally before calling Scout.

## Payload

The payload includes hospital identity, system/type/service model context, state code, current user role, Day 0 answers grouped by the 8 questionnaire sections, no-PHI constraints, and requested workflow output groups.

The payload excludes passwords, invitation tokens, token hashes, backend keys, PHI, patient names, MRNs, incident narratives, and peer-review case details.

## Response Normalization

The preview handles several backend shapes:

- `workflows`: shown as structured workflow cards.
- `action_items`, `goals`, `recommendations`, or `commitments`: shown as generic Scout Recommendations with a warning.
- `markdown_summary` or `summary`: shown as a summary card.
- `error` or unsuccessful response: stored as failed and shown with a retry state.

## Failure States

- Bridge unavailable: hospital users see a support message; admins see a configuration message.
- Backend/API failure: run is stored as failed with a safe error message and retry option.
- Payload too large: run is stored as failed and asks the user to shorten long free-text fields or contact support.

Onboarding submission is never blocked by Scout generation failure.

## Manual Testing

1. Activate the plugin and confirm `wp_qualinav_scout_runs` exists.
2. Open `/qualinav/admin` and confirm System Check shows GrapevineAI bridge availability and Scout run count.
3. Submit Day 0 Setup as a Quality Director and confirm a Scout run is created.
4. If the bridge/backend fails, confirm the run status is `failed` and the UI shows Retry.
5. If the bridge/backend succeeds, confirm preview cards render without raw JSON as the primary UI.
6. Confirm a Quality Director cannot generate for an unmapped hospital.
7. Confirm a QualiNav Super Admin can view/generate for a selected hospital.
8. Confirm no secrets, raw tokens, token hashes, or PHI are displayed.

## Phase 5C Authenticated QA Results

Authenticated browser QA was completed locally after adding route-safe asset registration, explicit `window.QualiNavConsole` config, DOM-ready startup, and an XHR-based REST helper.

- `/qualinav/admin` loads for the QualiNav Super Admin.
- System Check shows plugin version `0.1.3`, GrapevineAI bridge `Available`, and Scout run count `3`.
- Hospital table loads and each hospital row includes the Scout Preview action.
- No passwords, bridge keys, tokens, or secret header names were visible in the console UI.
- The Super Admin Scout Preview action routes to the selected hospital preview URL.
- A Quality Director with two active mapped hospitals can load `/qualinav`.
- Hospital switcher shows only active mapped hospitals for that QD.
- Switching between Hospital A and Hospital B updates current hospital context and Scout preview state.
- Hospital A and Hospital B use separate `wp_qualinav_scout_runs` rows.
- Viewer, Reporting User, Committee User, and Hospital Admin can view hospital console context but cannot generate Scout previews.
- Quality Director can generate for mapped hospitals and cannot generate/view for unmapped hospitals.
- Super Admin can view/generate for any hospital.
- Cross-organization run access by run ID is blocked for hospital users.

## Local Login Automation Note

The local site uses a custom login experience that did not reliably complete through browser automation. For authenticated browser smoke only, temporary local handoff files were used to set WordPress auth cookies for existing QA users, then immediately deleted. No secrets were added to plugin code or documentation.

## Response-Shape Behavior

Normalization was tested against:

- `workflows`
- `action_items`
- `recommendations`
- `goals`
- `commitments`
- `markdown_summary`
- `error`
- empty response

The UI renders workflow cards when structured workflows exist, generic Scout Recommendations when the backend returns goals/actions/recommendations/commitments, a summary card for markdown summaries, and readable failed/empty states. Blank warnings or missing-input entries from backend noise are filtered out.

## Remaining Backend Contract Questions

- The backend currently may return a generic structured-result warning instead of the final Day 0 workflow contract.
- Source objects may be sparse; the UI displays safe key/value rows when source fields are present.
- Once a dedicated `scout_day0_workflow_generation` task type is allowlisted, the bridge payload can switch from interim `generate_action_plan` to the dedicated task type.
