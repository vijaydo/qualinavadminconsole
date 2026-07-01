<?php

if (!defined('ABSPATH')) {
    exit;
}

class QN_Invitations
{
    const ONBOARDING_HANDOFF_META = '_qualinav_invite_onboarding_handoff';

    public static function invite_user($data)
    {
        $inviter_id = get_current_user_id();
        $inviter = QN_Users::get_user_row($inviter_id);
        if (!$inviter || $inviter->qualinav_status !== 'active') {
            return new WP_Error('qn_inviter_inactive', __('Your QualiNav account cannot invite users.', 'qualinav-admin-console'), array('status' => 403));
        }

        $email = sanitize_email(isset($data['email']) ? $data['email'] : '');
        $full_name = sanitize_text_field(isset($data['full_name']) ? $data['full_name'] : '');
        $role = sanitize_key(isset($data['qualinav_role']) ? $data['qualinav_role'] : '');
        $organization_id = isset($data['organization_id']) ? absint($data['organization_id']) : 0;
        $state_id = isset($data['state_id']) ? absint($data['state_id']) : 0;

        if (!$email || !is_email($email)) {
            return new WP_Error('qn_invalid_email', __('Enter a valid email address.', 'qualinav-admin-console'), array('status' => 400));
        }

        $inviter_role = QN_Users::is_qualinav_admin($inviter_id) ? QN_Users::get_user_qualinav_role($inviter_id) : QN_Users::get_role_for_organization($inviter_id, QN_Users::get_current_organization_id($inviter_id));
        if (!self::can_invite_role($inviter_role, $role)) {
            return new WP_Error('qn_invalid_invite_role', __('You cannot invite that QualiNav role.', 'qualinav-admin-console'), array('status' => 403));
        }

        if (QN_Users::is_hospital_user($inviter_id)) {
            $organization_id = QN_Users::get_current_organization_id($inviter_id);
            $access = QN_Users::get_user_organization_access($inviter_id, $organization_id);
            $state_id = $access && $access['state_id'] ? absint($access['state_id']) : absint($inviter->state_id);
        } else {
            if (!$organization_id) {
                return new WP_Error('qn_missing_organization', __('Select a hospital for this invitation.', 'qualinav-admin-console'), array('status' => 400));
            }

            $hospital = QN_Organizations::get_hospital($organization_id);
            $state_id = $hospital && !empty($hospital['state_id']) ? absint($hospital['state_id']) : $state_id;
        }

        if (!$organization_id && !in_array($role, array('qualinav_super_admin', 'qualinav_admin'), true)) {
            return new WP_Error('qn_missing_organization', __('Hospital users must belong to a hospital.', 'qualinav-admin-console'), array('status' => 400));
        }

        $existing_user = get_user_by('email', $email);
        if ($existing_user) {
            $existing_row = QN_Users::get_user_row($existing_user->ID);
            $user_id = absint($existing_user->ID);
            $existing_access = QN_Users::get_user_organization_access($user_id, $organization_id);

            if ($existing_access && $existing_access['status'] === 'active') {
                return new WP_Error('qn_user_org_exists_active', __('That user already has active access to this hospital.', 'qualinav-admin-console'), array('status' => 409));
            }

            QN_Users::add_user_to_organization($user_id, $organization_id, $state_id, $role, 'invited', !QN_Users::get_current_organization_id($user_id));
            self::set_user_qualinav_fields($user_id, $organization_id, $state_id, $role, $existing_row && $existing_row->qualinav_status === 'active' ? 'active' : 'invited');

            $existing_invitation = self::get_resendable_invitation_for_user($user_id, $organization_id);
            if ($existing_invitation) {
                self::update_pending_invitation_details($existing_invitation['id'], array(
                    'email' => $email,
                    'full_name' => $full_name,
                    'organization_id' => $organization_id,
                    'state_id' => $state_id,
                    'qualinav_role' => $role,
                    'updated_at' => current_time('mysql'),
                ));
                return self::resend_invitation($existing_invitation['id']);
            }
        } else {
            $user_id = self::create_pending_wp_user($email, $full_name, $organization_id, $state_id, $role);
            if (is_wp_error($user_id)) {
                return $user_id;
            }
        }

        $record = self::create_invitation_record(array(
            'user_id' => $user_id,
            'email' => $email,
            'full_name' => $full_name,
            'organization_id' => $organization_id,
            'state_id' => $state_id,
            'qualinav_role' => $role,
            'invited_by' => $inviter_id,
        ));

        if (is_wp_error($record)) {
            return $record;
        }

        $audit_after = self::without_token_hash($record['invitation']);
        if (!empty($record['mail_failed'])) {
            $audit_after['mail_failed'] = true;
            $audit_after['mail_warning'] = self::mail_failure_message();
            QN_Audit_Log::log('user_invited_email_failed', 'user', $user_id, null, $audit_after, $organization_id);
        } else {
            QN_Audit_Log::log('user_invited', 'user', $user_id, null, $audit_after, $organization_id);
        }

        return $record['invitation'];
    }

    public static function create_invitation_record($data)
    {
        global $wpdb;

        $raw_token = wp_generate_password(48, false);
        $now = current_time('mysql');
        $expires_at = gmdate('Y-m-d H:i:s', current_time('timestamp', true) + DAY_IN_SECONDS * 7);

        $insert_data = array(
            'user_id' => absint($data['user_id']),
            'email' => sanitize_email($data['email']),
            'full_name' => sanitize_text_field($data['full_name']),
            'organization_id' => !empty($data['organization_id']) ? absint($data['organization_id']) : null,
            'state_id' => !empty($data['state_id']) ? absint($data['state_id']) : null,
            'qualinav_role' => sanitize_key($data['qualinav_role']),
            'token_hash' => self::hash_token($raw_token),
            'status' => 'pending',
            'email_status' => 'not_sent',
            'email_error' => null,
            'invited_by' => absint($data['invited_by']),
            'expires_at' => $expires_at,
            'created_at' => $now,
            'updated_at' => $now,
        );

        $inserted = $wpdb->insert(
            QN_DB::invitations_table(),
            QN_DB::filter_existing_columns(QN_DB::invitations_table(), $insert_data)
        );

        if (!$inserted) {
            return new WP_Error('qn_invitation_create_failed', __('Unable to create invitation.', 'qualinav-admin-console'), array('status' => 500));
        }

        $invitation_id = absint($wpdb->insert_id);
        $mail_sent = self::send_invite_email($invitation_id, $raw_token);
        $invitation = self::get_invitation($invitation_id, true);

        return array(
            'invitation' => self::without_token_hash($invitation),
            'raw_token' => $raw_token,
            'mail_failed' => !$mail_sent,
        );
    }

    public static function get_invitation_by_token($raw_token)
    {
        global $wpdb;

        $hash = self::hash_token($raw_token);
        if (!$hash) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . QN_DB::invitations_table() . " WHERE token_hash = %s LIMIT 1", $hash)
        );

        return $row ? self::normalize_invitation_row($row, true) : null;
    }

    public static function get_invitation($id, $include_token_hash = false)
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . QN_DB::invitations_table() . " WHERE id = %d", absint($id))
        );

        return $row ? self::normalize_invitation_row($row, $include_token_hash) : null;
    }

    public static function get_invitations($args = array())
    {
        global $wpdb;

        $where = array('1=1');
        $values = array();

        if (!empty($args['organization_id'])) {
            $where[] = 'organization_id = %d';
            $values[] = absint($args['organization_id']);
        }

        $sql = 'SELECT * FROM ' . QN_DB::invitations_table() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 200';
        if ($values) {
            $sql = $wpdb->prepare($sql, $values);
        }

        $rows = $wpdb->get_results($sql);
        return array_map(array(__CLASS__, 'normalize_public_invitation_row'), $rows);
    }

    public static function resend_invitation($id)
    {
        global $wpdb;

        $invitation = self::get_invitation($id, true);
        if (!$invitation) {
            return new WP_Error('qn_invitation_not_found', __('Invitation not found.', 'qualinav-admin-console'), array('status' => 404));
        }

        if ($invitation['status'] === 'accepted' || $invitation['status'] === 'revoked') {
            return new WP_Error('qn_invitation_not_resendable', __('This invitation cannot be resent.', 'qualinav-admin-console'), array('status' => 400));
        }

        $raw_token = wp_generate_password(48, false);
        $update_data = array(
                'token_hash' => self::hash_token($raw_token),
                'status' => 'pending',
                'email_status' => 'not_sent',
                'email_error' => null,
                'expires_at' => gmdate('Y-m-d H:i:s', current_time('timestamp', true) + DAY_IN_SECONDS * 7),
                'updated_at' => current_time('mysql'),
        );
        $wpdb->update(
            QN_DB::invitations_table(),
            QN_DB::filter_existing_columns(QN_DB::invitations_table(), $update_data),
            array('id' => absint($id))
        );

        $mail_sent = self::send_invite_email($id, $raw_token);
        $after = self::get_invitation($id);
        if (!$mail_sent) {
            $after['mail_failed'] = true;
            $after['mail_warning'] = self::mail_failure_message();
            QN_Audit_Log::log('user_invited_email_failed', 'invitation', $id, null, $after, $after['organization_id']);
        } else {
            QN_Audit_Log::log('invite_resent', 'invitation', $id, null, $after, $after['organization_id']);
        }

        return $after;
    }

    public static function revoke_invitation($id)
    {
        global $wpdb;

        $before = self::get_invitation($id);
        if (!$before) {
            return new WP_Error('qn_invitation_not_found', __('Invitation not found.', 'qualinav-admin-console'), array('status' => 404));
        }

        if ($before['status'] === 'accepted') {
            return new WP_Error('qn_invitation_accepted', __('Accepted invitations cannot be revoked.', 'qualinav-admin-console'), array('status' => 400));
        }

        $wpdb->update(
            QN_DB::invitations_table(),
            array(
                'status' => 'revoked',
                'revoked_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('id' => absint($id))
        );

        $after = self::get_invitation($id);
        QN_Audit_Log::log('invite_revoked', 'invitation', $id, $before, $after, $after['organization_id']);

        return $after;
    }

    public static function accept_invitation($raw_token, $password, $display_name = null)
    {
        global $wpdb;

        $invitation = self::get_invitation_by_token($raw_token);
        $valid = self::validate_invitation_for_acceptance($invitation);
        if (is_wp_error($valid)) {
            return $valid;
        }

        if (strlen($password) < 10) {
            return new WP_Error('qn_weak_password', __('Password must be at least 10 characters.', 'qualinav-admin-console'), array('status' => 400));
        }

        $user_id = absint($invitation['user_id']);
        wp_set_password($password, $user_id);

        $user_update = array('ID' => $user_id);
        if ($display_name !== null && trim($display_name) !== '') {
            $user_update['display_name'] = sanitize_text_field($display_name);
        }
        wp_update_user($user_update);

        self::mark_user_active($user_id);
        QN_Users::update_user_organization_status($user_id, $invitation['organization_id'], 'active');
        QN_Users::set_current_organization($user_id, $invitation['organization_id']);
        $access = QN_Users::get_user_organization_access($user_id, $invitation['organization_id']);
        if ($access) {
            global $wpdb;
            $wpdb->update(
                QN_DB::user_organizations_table(),
                array('accepted_at' => current_time('mysql'), 'updated_at' => current_time('mysql')),
                array('user_id' => $user_id, 'organization_id' => absint($invitation['organization_id']))
            );
        }

        $wpdb->update(
            QN_DB::invitations_table(),
            array(
                'status' => 'accepted',
                'accepted_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('id' => absint($invitation['id']))
        );

        $after = self::get_invitation($invitation['id']);
        QN_Audit_Log::log('invite_accepted', 'invitation', $invitation['id'], $invitation, $after, $after['organization_id']);

        return array(
            'user_id' => $user_id,
            'redirect' => QN_Users::is_qualinav_admin($user_id) ? home_url('/qualinav/admin') : add_query_arg('organization_id', absint($invitation['organization_id']), home_url('/qualinav')),
        );
    }

    public static function is_hospital_invitation_role($role)
    {
        return !in_array(sanitize_key($role), array('qualinav_super_admin', 'qualinav_admin'), true);
    }

    public static function accept_invitation_for_magic_handoff($raw_token)
    {
        global $wpdb;

        $invitation = self::get_invitation_by_token($raw_token);
        $valid = self::validate_invitation_for_acceptance($invitation);
        if (is_wp_error($valid)) {
            return $valid;
        }

        if (!self::is_hospital_invitation_role($invitation['qualinav_role'])) {
            return new WP_Error('qn_invitation_requires_password_flow', __('This invitation must be accepted through the account setup flow.', 'qualinav-admin-console'), array('status' => 400));
        }

        $user_id = absint($invitation['user_id']);
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new WP_Error('qn_invited_user_missing', __('This invitation is not linked to an active account record.', 'qualinav-admin-console'), array('status' => 404));
        }

        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            if (!$current_user || absint($current_user->ID) !== $user_id || strcasecmp((string) $current_user->user_email, (string) $invitation['email']) !== 0) {
                return new WP_Error(
                    'qn_invitation_wrong_user',
                    __('This invitation belongs to a different user. Please sign out or open the invitation in a private browser window, then try again.', 'qualinav-admin-console'),
                    array('status' => 403)
                );
            }
        }

        self::mark_user_active($user_id);
        QN_Users::update_user_organization_status($user_id, $invitation['organization_id'], 'active');
        QN_Users::set_current_organization($user_id, $invitation['organization_id']);

        $access = QN_Users::get_user_organization_access($user_id, $invitation['organization_id']);
        if ($access) {
            $wpdb->update(
                QN_DB::user_organizations_table(),
                array('accepted_at' => current_time('mysql'), 'updated_at' => current_time('mysql')),
                array('user_id' => $user_id, 'organization_id' => absint($invitation['organization_id']))
            );
        }

        $wpdb->update(
            QN_DB::invitations_table(),
            array(
                'status' => 'accepted',
                'accepted_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('id' => absint($invitation['id']))
        );

        self::store_onboarding_handoff($user_id, $invitation);

        $after = self::get_invitation($invitation['id']);
        QN_Audit_Log::log('invite_accepted_magic_handoff', 'invitation', $invitation['id'], $invitation, $after, $after['organization_id']);

        return array(
            'user_id' => $user_id,
            'email' => $user->user_email,
            'organization_id' => absint($invitation['organization_id']),
            'redirect' => self::entry_url_after_invite($user_id, absint($invitation['organization_id'])),
        );
    }

    public static function entry_url_after_invite($user_id, $organization_id)
    {
        $target = self::hospital_setup_url($organization_id);

        if (self::grapevine_onboarding_completed($user_id)) {
            return $target;
        }

        return home_url('/onboarding/');
    }

    public static function onboarding_completion_redirect($redirect_url, $user_id)
    {
        $handoff = self::get_valid_onboarding_handoff($user_id);
        if (!$handoff) {
            $organization_id = self::completion_redirect_organization_id($user_id);
            if ($organization_id) {
                $target = self::hospital_setup_url($organization_id);
                self::debug_onboarding_redirect('fallback', $user_id, $target);
                return $target;
            }

            self::debug_onboarding_redirect('no', $user_id, $redirect_url);
            return $redirect_url;
        }

        $target = !empty($handoff['target']) ? esc_url_raw($handoff['target']) : self::hospital_setup_url(absint($handoff['organization_id']));
        self::debug_onboarding_redirect('yes', $user_id, $target);

        if (self::grapevine_onboarding_completed($user_id)) {
            delete_user_meta(absint($user_id), self::ONBOARDING_HANDOFF_META);
        }

        return $target;
    }

    public static function clear_completed_handoff_for_organization($user_id, $organization_id)
    {
        $handoff = self::get_valid_onboarding_handoff($user_id);
        if ($handoff && absint($handoff['organization_id']) === absint($organization_id) && self::grapevine_onboarding_completed($user_id)) {
            delete_user_meta(absint($user_id), self::ONBOARDING_HANDOFF_META);
        }
    }

    public static function handle_grapevine_onboarding_reset($user_id)
    {
        $user_id = absint($user_id);
        if (!$user_id) {
            return;
        }

        delete_user_meta($user_id, self::ONBOARDING_HANDOFF_META);

        $organization_id = self::completion_redirect_organization_id($user_id);
        if (!$organization_id) {
            return;
        }

        update_user_meta($user_id, '_dtm_welcome_pending', '1');
        self::store_onboarding_handoff_for_organization($user_id, $organization_id);
    }

    public static function generate_invite_url($raw_token)
    {
        return add_query_arg('token', rawurlencode($raw_token), home_url('/qualinav/accept-invite'));
    }

    public static function send_invite_email($invitation_id, $raw_token)
    {
        $invitation = self::get_invitation($invitation_id);
        if (!$invitation) {
            return false;
        }

        $hospital = !empty($invitation['organization_id']) ? QN_Organizations::get_hospital($invitation['organization_id']) : null;
        $hospital_name = $hospital ? $hospital['name'] : __('QualiNav', 'qualinav-admin-console');
        $link = self::generate_invite_url($raw_token);
        $body = self::render_invite_email(array(
            'recipient_name' => !empty($invitation['full_name']) ? $invitation['full_name'] : $invitation['email'],
            'hospital_name' => $hospital_name,
            'role_label' => self::role_label($invitation['qualinav_role']),
            'accept_url' => $link,
        ));

        $sent = QN_Email::send(
            $invitation['email'],
            __('You have been invited to QualiNav', 'qualinav-admin-console'),
            $body,
            array('preheader' => __('You have been invited to join QualiNav.', 'qualinav-admin-console'))
        );
        self::mark_email_delivery($invitation_id, $sent);

        return $sent;
    }

    private static function render_invite_email($context)
    {
        $brand = class_exists('QN_Branding') ? QN_Branding::get_default_brand() : array();
        $text = sanitize_hex_color(isset($brand['text_color']) ? $brand['text_color'] : '') ?: '#102A43';
        $muted = '#64748B';
        $recipient_name = trim((string) $context['recipient_name']);
        $recipient_name = $recipient_name !== '' && is_email($recipient_name) ? strtok($recipient_name, '@') : $recipient_name;
        $recipient_name = $recipient_name !== '' ? $recipient_name : __('there', 'qualinav-admin-console');
        $hospital_name = (string) $context['hospital_name'];
        $role_label = (string) $context['role_label'];
        $accept_url = esc_url((string) $context['accept_url']);

        return '
              <p style="margin:0 0 12px; font-size:16px; line-height:1.6; color:' . esc_attr($text) . ';">Hi ' . esc_html($recipient_name) . ',</p>
              <h1 style="margin:0 0 14px; font-size:28px; line-height:1.2; color:' . esc_attr($text) . '; font-weight:850;">You have been invited to QualiNav</h1>
              <p style="margin:0 0 26px; font-size:15px; line-height:1.75; color:' . esc_attr($text) . ';">Your QualiNav workspace is ready. This secure link will sign you in and start your QualiNav onboarding.</p>

              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 26px; border:1px solid #DDE7F0; border-radius:14px; overflow:hidden;">
                <tr>
                  <td style="padding:17px 20px; background:#F8FBFD; border-bottom:1px solid #DDE7F0;">
                    <div style="font-size:12px; letter-spacing:0.08em; text-transform:uppercase; color:' . esc_attr($muted) . '; font-weight:800; margin-bottom:5px;">Hospital</div>
                    <div style="font-size:18px; line-height:1.35; color:' . esc_attr($text) . '; font-weight:800;">' . esc_html($hospital_name) . '</div>
                  </td>
                </tr>
                <tr>
                  <td style="padding:17px 20px; background:#FFFFFF;">
                    <div style="font-size:12px; letter-spacing:0.08em; text-transform:uppercase; color:' . esc_attr($muted) . '; font-weight:800; margin-bottom:5px;">Role</div>
                    <div style="display:inline-block; padding:7px 12px; border-radius:999px; background:#E6FAF7; color:#075E58; font-size:14px; line-height:1.2; font-weight:800;">' . esc_html($role_label) . '</div>
                  </td>
                </tr>
              </table>

              <p style="margin:0 0 24px; text-align:center;">
                ' . QN_Email::button($accept_url, __('Start QualiNav onboarding', 'qualinav-admin-console')) . '
              </p>

              <p style="margin:0 0 8px; font-size:13px; line-height:1.7; color:' . esc_attr($muted) . '; text-align:center;">This invitation expires in 7 days. No password is included in this email.</p>
              <p style="margin:0; font-size:12px; line-height:1.7; color:' . esc_attr($muted) . '; text-align:center;">If the button does not work, copy and paste this link into your browser:<br><a href="' . $accept_url . '" style="color:' . esc_attr(QN_Email::brand_color($brand, 'primary_color', '#003B5C')) . '; word-break:break-all;">' . esc_html($accept_url) . '</a></p>';
    }

    private static function role_label($role)
    {
        $labels = array(
            'qualinav_super_admin' => __('QualiNav Super Admin', 'qualinav-admin-console'),
            'qualinav_admin' => __('QualiNav Admin', 'qualinav-admin-console'),
            'quality_director' => __('Hospital Quality Director', 'qualinav-admin-console'),
            'executive_leader' => __('Executive Leader (CEO or CFO)', 'qualinav-admin-console'),
            'clinical_ancillary_services_leader' => __('Clinical or Ancillary Services Leader or Director', 'qualinav-admin-console'),
            'hospital_admin' => __('Hospital Admin', 'qualinav-admin-console'),
            'backup_quality_user' => __('Backup Quality User', 'qualinav-admin-console'),
            'reporting_user' => __('Reporting User', 'qualinav-admin-console'),
            'policy_owner' => __('Policy Owner', 'qualinav-admin-console'),
            'committee_user' => __('Committee User', 'qualinav-admin-console'),
            'viewer' => __('Viewer', 'qualinav-admin-console'),
        );

        return isset($labels[$role]) ? $labels[$role] : ucwords(str_replace('_', ' ', (string) $role));
    }

    public static function mail_failure_message()
    {
        return __('Invite was created, but email delivery failed. Configure SMTP/mail transport or resend after mail is fixed.', 'qualinav-admin-console');
    }

    public static function allowed_invite_roles($inviter_role)
    {
        $map = array(
            'qualinav_super_admin' => array('qualinav_admin', 'quality_director', 'executive_leader', 'clinical_ancillary_services_leader', 'hospital_admin', 'backup_quality_user', 'reporting_user', 'policy_owner', 'committee_user', 'viewer'),
            'qualinav_admin' => array('quality_director', 'executive_leader', 'clinical_ancillary_services_leader', 'hospital_admin', 'backup_quality_user', 'reporting_user', 'policy_owner', 'committee_user', 'viewer'),
            'quality_director' => array('executive_leader', 'clinical_ancillary_services_leader', 'hospital_admin', 'backup_quality_user', 'reporting_user', 'policy_owner', 'committee_user', 'viewer'),
            'executive_leader' => array('clinical_ancillary_services_leader', 'reporting_user', 'policy_owner', 'committee_user', 'viewer'),
            'hospital_admin' => array('clinical_ancillary_services_leader', 'reporting_user', 'policy_owner', 'committee_user', 'viewer'),
        );

        return isset($map[$inviter_role]) ? $map[$inviter_role] : array();
    }

    public static function can_invite_role($inviter_role, $target_role)
    {
        return in_array($target_role, self::allowed_invite_roles($inviter_role), true);
    }

    public static function create_pending_wp_user($email, $full_name, $organization_id, $state_id, $role)
    {
        $user_id = wp_insert_user(array(
            'user_login' => $email,
            'user_email' => $email,
            'display_name' => $full_name ? $full_name : $email,
            'user_pass' => wp_generate_password(32, true),
            'role' => 'subscriber',
        ));

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        self::set_user_qualinav_fields($user_id, $organization_id, $state_id, $role, 'invited');
        QN_Users::add_user_to_organization($user_id, $organization_id, $state_id, $role, 'invited', true);

        return $user_id;
    }

    public static function mark_user_active($user_id)
    {
        global $wpdb;

        return $wpdb->update($wpdb->users, array('qualinav_status' => 'active'), array('ID' => absint($user_id)));
    }

    public static function update_user_role($user_id, $new_role)
    {
        global $wpdb;

        $before = QN_Users::get_user_row($user_id);
        if (!$before || !array_key_exists($new_role, QN_Permissions::permission_map())) {
            return new WP_Error('qn_invalid_role', __('Invalid QualiNav role.', 'qualinav-admin-console'), array('status' => 400));
        }

        $organization_id = !empty($before->organization_id) ? absint($before->organization_id) : QN_Users::get_current_organization_id($user_id);
        if ($organization_id && !QN_Users::is_qualinav_admin($user_id)) {
            QN_Users::update_user_organization_role($user_id, $organization_id, $new_role);
        }
        $wpdb->update($wpdb->users, array('qualinav_role' => sanitize_key($new_role)), array('ID' => absint($user_id)));
        $after = QN_Users::get_user_row($user_id);
        QN_Audit_Log::log('user_role_changed', 'user', $user_id, $before, $after, $after->organization_id);

        return self::normalize_user_row($after);
    }

    public static function update_user_status($user_id, $new_status)
    {
        global $wpdb;

        $allowed = array('invited', 'active', 'disabled', 'archived');
        if (!in_array($new_status, $allowed, true)) {
            return new WP_Error('qn_invalid_status', __('Invalid QualiNav status.', 'qualinav-admin-console'), array('status' => 400));
        }

        $before = QN_Users::get_user_row($user_id);
        if (!$before) {
            return new WP_Error('qn_user_not_found', __('User not found.', 'qualinav-admin-console'), array('status' => 404));
        }

        $organization_id = !empty($before->organization_id) ? absint($before->organization_id) : QN_Users::get_current_organization_id($user_id);
        if ($organization_id && !QN_Users::is_qualinav_admin($user_id)) {
            QN_Users::update_user_organization_status($user_id, $organization_id, $new_status);
        }
        $wpdb->update($wpdb->users, array('qualinav_status' => sanitize_key($new_status)), array('ID' => absint($user_id)));
        $after = QN_Users::get_user_row($user_id);
        QN_Audit_Log::log('user_status_changed', 'user', $user_id, $before, $after, $after->organization_id);

        return self::normalize_user_row($after);
    }

    public static function get_users($args = array())
    {
        global $wpdb;

        $mapping = QN_DB::user_organizations_table();
        if (!empty($args['organization_id'])) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT u.ID, u.user_email, u.display_name, m.organization_id, m.state_id, m.qualinav_role, m.status AS qualinav_status FROM {$wpdb->users} u INNER JOIN {$mapping} m ON u.ID = m.user_id WHERE m.organization_id = %d ORDER BY u.display_name ASC LIMIT 500",
                    absint($args['organization_id'])
                )
            );
        } else {
            $rows = $wpdb->get_results("SELECT u.ID, u.user_email, u.display_name, u.organization_id, u.state_id, u.qualinav_role, u.qualinav_status FROM {$wpdb->users} u WHERE u.qualinav_role IS NOT NULL AND u.qualinav_role <> '' ORDER BY u.display_name ASC LIMIT 500");
        }
        return array_map(array(__CLASS__, 'normalize_user_row'), $rows);
    }

    public static function validate_invitation_for_acceptance($invitation)
    {
        if (!$invitation) {
            return new WP_Error('qn_invalid_invitation', __('This invitation is invalid.', 'qualinav-admin-console'), array('status' => 404));
        }

        if ($invitation['status'] === 'accepted') {
            return new WP_Error('qn_invitation_used', __('This invitation has already been accepted.', 'qualinav-admin-console'), array('status' => 400));
        }

        if ($invitation['status'] === 'revoked') {
            return new WP_Error('qn_invitation_revoked', __('This invitation has been revoked.', 'qualinav-admin-console'), array('status' => 400));
        }

        $expires_ts = 0;
        if (!empty($invitation['expires_at'])) {
            try {
                $expires_at = new DateTimeImmutable((string) $invitation['expires_at'], new DateTimeZone('UTC'));
                $expires_ts = $expires_at->getTimestamp();
            } catch (Exception $e) {
                $expires_ts = 0;
            }
        }

        if (!$expires_ts || $expires_ts <= current_time('timestamp', true)) {
            return new WP_Error('qn_invitation_expired', __('This invitation has expired.', 'qualinav-admin-console'), array('status' => 400));
        }

        return true;
    }

    public static function without_token_hash($invitation)
    {
        if (is_array($invitation) && isset($invitation['token_hash'])) {
            unset($invitation['token_hash']);
        }

        return $invitation;
    }

    private static function set_user_qualinav_fields($user_id, $organization_id, $state_id, $role, $status)
    {
        global $wpdb;

        $wpdb->update(
            $wpdb->users,
            array(
                'organization_id' => $organization_id ? absint($organization_id) : null,
                'state_id' => $state_id ? absint($state_id) : null,
                'qualinav_role' => sanitize_key($role),
                'qualinav_status' => sanitize_key($status),
            ),
            array('ID' => absint($user_id))
        );

        $user = new WP_User($user_id);
        $user->set_role('subscriber');
    }

    private static function get_resendable_invitation_for_user($user_id, $organization_id = null)
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . QN_DB::invitations_table() . " WHERE user_id = %d AND organization_id = %d AND status = %s ORDER BY id DESC LIMIT 1",
                absint($user_id),
                absint($organization_id),
                'pending'
            )
        );

        return $row ? self::normalize_invitation_row($row) : null;
    }

    private static function update_pending_invitation_details($invitation_id, $data)
    {
        global $wpdb;

        $update_data = array(
                'email' => sanitize_email($data['email']),
                'full_name' => sanitize_text_field($data['full_name']),
                'organization_id' => !empty($data['organization_id']) ? absint($data['organization_id']) : null,
                'state_id' => !empty($data['state_id']) ? absint($data['state_id']) : null,
                'qualinav_role' => sanitize_key($data['qualinav_role']),
                'email_status' => 'not_sent',
                'email_error' => null,
                'updated_at' => current_time('mysql'),
        );
        $wpdb->update(
            QN_DB::invitations_table(),
            QN_DB::filter_existing_columns(QN_DB::invitations_table(), $update_data),
            array('id' => absint($invitation_id))
        );
    }

    private static function mark_email_delivery($invitation_id, $sent)
    {
        global $wpdb;

        $data = array(
            'email_status' => $sent ? 'sent' : 'failed',
            'email_error' => $sent ? null : self::mail_failure_message(),
            'updated_at' => current_time('mysql'),
        );

        $wpdb->update(QN_DB::invitations_table(), QN_DB::filter_existing_columns(QN_DB::invitations_table(), $data), array('id' => absint($invitation_id)));
    }

    private static function hash_token($raw_token)
    {
        $raw_token = trim((string) $raw_token);
        return $raw_token !== '' ? hash('sha256', $raw_token) : '';
    }

    private static function normalize_public_invitation_row($row)
    {
        return self::normalize_invitation_row($row, false);
    }

    private static function normalize_invitation_row($row, $include_token_hash = false)
    {
        $inviter = !empty($row->invited_by) ? get_userdata($row->invited_by) : null;

        $data = array(
            'id' => absint($row->id),
            'user_id' => $row->user_id !== null ? absint($row->user_id) : null,
            'email' => $row->email,
            'full_name' => $row->full_name,
            'organization_id' => $row->organization_id !== null ? absint($row->organization_id) : null,
            'state_id' => $row->state_id !== null ? absint($row->state_id) : null,
            'qualinav_role' => $row->qualinav_role,
            'status' => $row->status,
            'email_status' => property_exists($row, 'email_status') ? $row->email_status : null,
            'email_failed' => property_exists($row, 'email_status') && $row->email_status === 'failed',
            'email_error' => property_exists($row, 'email_error') ? $row->email_error : null,
            'invited_by' => absint($row->invited_by),
            'invited_by_name' => $inviter ? $inviter->display_name : '',
            'expires_at' => $row->expires_at,
            'accepted_at' => $row->accepted_at,
            'revoked_at' => $row->revoked_at,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        );

        if ($include_token_hash) {
            $data['token_hash'] = $row->token_hash;
        }

        return $data;
    }

    private static function normalize_user_row($row)
    {
        $hospital = !empty($row->organization_id) ? QN_Organizations::get_hospital($row->organization_id) : null;
        $state = !empty($row->state_id) ? QN_Organizations::get_state($row->state_id) : null;
        $organizations = QN_Users::get_user_organizations($row->ID);

        return array(
            'ID' => absint($row->ID),
            'display_name' => $row->display_name,
            'user_email' => $row->user_email,
            'avatar_url' => get_avatar_url(absint($row->ID), array('size' => 96)),
            'organization_id' => $row->organization_id !== null ? absint($row->organization_id) : null,
            'organization_name' => $hospital ? $hospital['name'] : '',
            'state_id' => $row->state_id !== null ? absint($row->state_id) : null,
            'state_code' => $state ? $state['abbreviation'] : '',
            'state_name' => $state ? $state['name'] : '',
            'qualinav_role' => $row->qualinav_role,
            'qualinav_status' => $row->qualinav_status,
            'organizations' => $organizations,
            'organization_count' => count($organizations),
            'last_login' => get_user_meta($row->ID, 'last_login', true),
        );
    }

    private static function store_onboarding_handoff($user_id, $invitation)
    {
        $organization_id = absint($invitation['organization_id']);
        self::store_onboarding_handoff_for_organization(absint($user_id), $organization_id, absint($invitation['id']));
    }

    private static function store_onboarding_handoff_for_organization($user_id, $organization_id, $invite_id = 0)
    {
        update_user_meta(
            absint($user_id),
            self::ONBOARDING_HANDOFF_META,
            array(
                'invite_id' => absint($invite_id),
                'organization_id' => absint($organization_id),
                'target' => self::hospital_setup_url($organization_id),
                'created_at' => current_time('timestamp', true),
                'expires_at' => current_time('timestamp', true) + DAY_IN_SECONDS,
            )
        );
    }

    private static function completion_redirect_organization_id($user_id)
    {
        $user_id = absint($user_id);
        if (!$user_id || !class_exists('QN_Users') || QN_Users::is_qualinav_admin($user_id) || !QN_Users::is_hospital_user($user_id)) {
            return 0;
        }

        $organization_id = QN_Users::get_current_organization_id($user_id);
        if ($organization_id && QN_Users::user_has_organization($user_id, $organization_id)) {
            return absint($organization_id);
        }

        $organizations = QN_Users::get_user_organizations($user_id);
        foreach ($organizations as $organization) {
            if (!empty($organization['organization_id']) && (!isset($organization['status']) || $organization['status'] === 'active')) {
                return absint($organization['organization_id']);
            }
        }

        return 0;
    }

    private static function get_valid_onboarding_handoff($user_id)
    {
        $handoff = get_user_meta(absint($user_id), self::ONBOARDING_HANDOFF_META, true);
        if (!is_array($handoff) || empty($handoff['organization_id'])) {
            return null;
        }

        $expires_at = isset($handoff['expires_at']) ? absint($handoff['expires_at']) : 0;
        if ($expires_at && $expires_at < current_time('timestamp', true)) {
            delete_user_meta(absint($user_id), self::ONBOARDING_HANDOFF_META);
            return null;
        }

        return $handoff;
    }

    private static function pending_welcome_organization_id($user_id)
    {
        $user_id = absint($user_id);
        if (!$user_id || !get_user_meta($user_id, '_dtm_welcome_pending', true)) {
            return 0;
        }

        $organization_id = QN_Users::get_current_organization_id($user_id);
        if ($organization_id && QN_Users::user_has_organization($user_id, $organization_id)) {
            return absint($organization_id);
        }

        $organizations = QN_Users::get_user_organizations($user_id);
        foreach ($organizations as $organization) {
            if (!empty($organization['organization_id']) && (!isset($organization['status']) || $organization['status'] === 'active')) {
                return absint($organization['organization_id']);
            }
        }

        return 0;
    }

    private static function hospital_setup_url($organization_id)
    {
        return add_query_arg(
            array(
                'qualinav_welcome' => '1',
                'organization_id' => absint($organization_id),
            ),
            home_url('/')
        );
    }

    private static function debug_onboarding_redirect($handoff_found, $user_id, $url)
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $path = wp_parse_url($url, PHP_URL_PATH);
        $query = wp_parse_url($url, PHP_URL_QUERY);
        error_log(sprintf(
            '[QN-DIAG] gv_onboarding_completion_redirect_url handoff=%s user_id=%d redirect=%s',
            $handoff_found,
            absint($user_id),
            ($path ? $path : '/') . ($query ? '?' . $query : '')
        ));
    }

    private static function grapevine_onboarding_completed($user_id)
    {
        if (class_exists('GV_Submission_Handler') && method_exists('GV_Submission_Handler', 'gv_onboarding_already_completed')) {
            return (bool) GV_Submission_Handler::gv_onboarding_already_completed(absint($user_id));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'gv_user_profile';
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        if (!$table_exists) {
            return false;
        }

        return (int) $wpdb->get_var($wpdb->prepare("SELECT onboarding_completed FROM {$table} WHERE user_id = %d", absint($user_id))) === 1;
    }
}
