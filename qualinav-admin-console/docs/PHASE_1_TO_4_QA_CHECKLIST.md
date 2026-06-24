# QualiNav Phase 1-4 Local QA Checklist

Project: QualiNav Admin Console  
Build Rank: 26  
Scope: Phase 1 through Phase 4.6 only. Do not start Phase 5 workflow generation.

## Activation Checks

- Plugin activates from `wp-content/plugins/qualinav-admin-console` without fatal errors.
- Rewrite routes are flushed after activation.
- `/qualinav` redirects anonymous users to WordPress login.
- `/qualinav/admin` redirects anonymous users to WordPress login.
- `/qualinav/accept-invite` resolves and shows a safe invalid-invite state when no token is present.
- Local QualiNav Super Admin has `qualinav_role = qualinav_super_admin` and `qualinav_status = active`.

## DB Migration Checks

- `wp_users` includes `organization_id`, `state_id`, `qualinav_role`, and `qualinav_status`.
- `wp_organizations` includes `parent_system_id`, `hospital_type`, and `service_model`.
- Required plugin tables exist:
  - `wp_qualinav_audit_logs`
  - `wp_qualinav_invitations`
  - `wp_qualinav_user_organizations`
  - `wp_qualinav_health_systems`
  - `wp_qualinav_questionnaire_sections`
  - `wp_qualinav_questionnaire_questions`
  - `wp_qualinav_questionnaire_answers`
  - `wp_qualinav_onboarding_progress`
- Questionnaire seed contains 8 sections.
- Questionnaire seed contains the expected Day 0 questions.
- Invitation `token_hash` stores only a SHA-256 hash, never a raw token.

## Super Admin Checks

- QualiNav Super Admin can access `/qualinav/admin`.
- QualiNav Super Admin can call admin REST endpoints.
- Dashboard metric cards load without raw JSON errors.
- Admin-only System Check card loads plugin version, DB prefix, schema status, questionnaire counts, and current QualiNav role/status.

## Health System Checks

- Super Admin can create a health system.
- Super Admin can edit a health system.
- Health system slug is unique.
- Health system table shows system name, headquarters state, hospital count, active status, and actions.
- Health system actions do not expose hospital editing to hospital users.

## Hospital Creation Checks

- Super Admin can create Hospital A.
- Super Admin can create Hospital B.
- Hospitals can be assigned to the same health system.
- Hospitals can have different `hospital_type` and `service_model` values.
- Hospital table shows hospital, system, state, type, service model, active status, onboarding percent, primary QD, and actions.
- Hospital updates write audit logs for classification changes.

## Invite Checks

- Super Admin can invite a Quality Director.
- Quality Director invite creates a WordPress subscriber user.
- Invite creates a `wp_qualinav_user_organizations` mapping row.
- Invite creates a `wp_qualinav_invitations` row.
- Invite status remains pending until accepted.
- If local mail delivery fails, the invite remains pending and the UI shows "Email failed."
- QD cannot invite QualiNav admins.
- Hospital Admin cannot invite Quality Directors.

## Accept Invite Checks

- Valid invite token loads the accept invite page.
- Invalid, expired, revoked, or accepted tokens show a safe error.
- Password must meet minimum length.
- Accepting an invite activates the user and mapping row.
- Accepted token cannot be reused.
- Accepted QD redirects to `/qualinav`.

## Multi-Hospital Switcher Checks

- Same user can be mapped to more than one hospital.
- Same email is not rejected only because the WordPress user exists.
- Same user cannot receive duplicate active access to the same hospital.
- Switcher lists only active hospital mappings.
- Switching hospital updates `wp_users.organization_id` and `wp_users.state_id`.
- Dashboard, user list, onboarding wizard, and hospital context card reload selected hospital context.
- Hospital switch toast appears.

## Hospital User Checks

- Quality Director can invite hospital users into the currently selected hospital.
- Hospital user receives only the selected hospital mapping.
- Hospital users cannot access `/qualinav/admin`.
- Hospital users cannot access `/wp-admin`.
- Admin bar is hidden for non-WordPress-admin users.
- Hospital users cannot call admin REST endpoints.

## Onboarding Checks

- `/qualinav` shows Day 0 Setup.
- Wizard loads answers for the selected hospital.
- Switching hospitals loads separate answers and progress.
- Step 1 saves for Hospital A and Hospital B independently.
- Repeaters save for survey history, committees, reporting obligations, and QI projects.
- PHI warning appears in Plans, Policies & Monitoring.
- Quality Director can save all onboarding steps for mapped hospitals.
- Hospital Admin can save only the hospital profile setup step.
- Viewer, Reporting User, and Committee User cannot save onboarding.
- Super Admin can save selected hospital onboarding using `organization_id`.
- Hospital users cannot save onboarding for unmapped hospitals.
- Onboarding save and submit actions write audit logs.

## Permission And Security Checks

- QualiNav permissions use `wp_users` fields and `wp_qualinav_user_organizations`, not WordPress custom capabilities.
- Global QualiNav admins use `wp_users.qualinav_role`.
- Hospital roles are resolved from `wp_qualinav_user_organizations`.
- Disabled `wp_users.qualinav_status` blocks all QualiNav access.
- Disabled mapping status blocks access to that hospital.
- Invite tokens are hashed only.
- Raw invite tokens are never returned by list/detail APIs.
- Revoked, expired, and accepted invitations cannot be accepted.
- No PHI collection fields have been added.

## UI Checks

- Admin console uses dark navy sidebar, teal/blue active states, white cards, and soft page background.
- Hospital console shows current hospital context, system, type, service model, and state.
- Tables use status pills, role controls, aligned actions, and readable empty states.
- Modals have clear labels, cancel/save actions, validation messages, and no small-screen overflow.
- Toasts appear for save, invite, role/status change, resend/revoke, hospital switch, and onboarding submission.
- Icon style is consistent.
- Focus states are visible.

## Known Local Mail Limitation

Local WAMP does not have a working PHP mail transport by default. The plugin now sends valid QualiNav `From` headers, but `wp_mail` may still fail locally with `Could not instantiate mail function.` This is expected until SMTP or a local mail catcher is configured. Failed invite email delivery should leave the invitation pending and show an "Email failed" warning.
