<?php
/**
 * Template Name: Data Management
 * Description: Data Management folders and links with 3-level drill-down
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( empty( $GLOBALS['dh_embed_mode'] ) && ! is_user_logged_in() ) {
    wp_redirect( wp_login_url() );
    exit;
}

$dm_page_org_name = '';
if ( is_user_logged_in() ) {
    $dm_page_user_id = (int) get_current_user_id();
    if ( function_exists( 'qualinav_data_hub_get_org_context' ) ) {
        $dm_page_org_context = qualinav_data_hub_get_org_context( $dm_page_user_id );
        $dm_page_org_name    = trim( (string) ( $dm_page_org_context['org_name'] ?? '' ) );
    }
    if ( '' === $dm_page_org_name ) {
        global $wpdb;
        $dm_page_org_name = trim( (string) $wpdb->get_var( $wpdb->prepare(
            "SELECT o.name
               FROM {$wpdb->users} u
               LEFT JOIN {$wpdb->prefix}organizations o ON o.id = u.organization_id
              WHERE u.ID = %d
              LIMIT 1",
            $dm_page_user_id
        ) ) );
    }
    if ( '' === $dm_page_org_name ) {
        $dm_page_org_name = trim( (string) get_user_meta( $dm_page_user_id, 'organization', true ) );
    }
}
$dm_org_data_label = '' !== $dm_page_org_name ? $dm_page_org_name . ' Data' : 'Organization Data';

// 1. DATA HIERARCHY
$folders = [
    [
        'id' => 'general',
        'name' => 'Universal Workbook',
        'icon' => 'fas fa-cloud-upload-alt',
        'desc' => 'Download and upload the universal Data Hub workbook.',
        'measures' => [
            'Bulk Upload',
        ],
    ],
    [
        'id' => 'mbqip',
        'name' => 'MBQIP',
        'icon' => 'fas fa-folder-open',
        'desc' => 'MBQIP measures grouped by event type.',
        'subfolders' => [
            [
                'id' => 'mbqip-global-measures',
                'name' => 'Global Measures',
                'measures' => [
                    'CAH Quality Infrastructure Assessment',
                ],
            ],
            [
                'id' => 'mbqip-patient-safety',
                'name' => 'Patient Safety',
                'measures' => [
                    'HCP/IMM-3 — Healthcare Personnel Influenza Vaccination',
                    'Antibiotic Stewardship',
                    'Safe Use of Opioids eCQM — MBQIP Submission',
                ],
            ],
            [
                'id' => 'mbqip-patient-experience',
                'name' => 'Patient Experience (HCAHPS)',
                'measures' => [
                    'HCAHPS — Composite 1: Communication with Nurses',
                    'HCAHPS — Composite 2: Communication with Doctors',
                    'HCAHPS — Composite 3: Restfulness of Hospital Environment',
                    'HCAHPS — Composite 4: Responsiveness of Hospital Staff',
                    'HCAHPS — Composite 5: Communication About Medicines',
                    'HCAHPS — Composite 6: Discharge Information / Care Coordination',
                    'HCAHPS — Composite 7: Transitions of Care',
                    'HCAHPS — Q7: Cleanliness of Hospital Environment',
                    'HCAHPS — Q20: Info About Symptoms to Watch For After Discharge',
                    'HCAHPS — Q24: Overall Rating of Hospital (0-10)',
                    'HCAHPS — Q5: Willingness to Recommend Hospital',
                ],
            ],
            [
                'id' => 'mbqip-emergency-department',
                'name' => 'Emergency Department',
                'measures' => [
                    'EDTC — Emergency Department Transfer Communication',
                    'OP-18 — Median ED Arrival to Departure Time (Discharged Patients)',
                    'OP-22 — Patient Left Without Being Seen (LWBS) Rate',
                ],
            ],
        ],
    ],
    [
        'id' => 'improvement-calculator',
        'name' => 'HACs & HAIs',
        'icon' => 'fas fa-calculator',
        'desc' => 'Hospital-acquired condition and healthcare-associated infection tracking.',
        'measures' => [
            'C. Diff',
            'MRSA',
            'CAUTI',
            'CLABSI',
            'Pressure Ulcers 3+',
            'Sepsis Mortality',
            'Readmissions',
            'Falls with Injury',
        ],
    ],
    [
        'id' => 'patient-safety',
        'name' => '1. Patient Safety & Inpatient (NHSN/HAI)',
        'icon' => 'fas fa-shield-alt',
        'desc' => 'HCP Flu, ASP, Safe Use of Opioids, IMM-3, CAUTI Rate, Falls.',
        'measures' => ['HCP Flu (Staff Vaccination)', 'ASP (Antibiotic Stewardship)', 'Safe Use of Opioids', 'IMM-3 (Vaccination Coverage)', 'CAUTI Rate', 'Falls with Major Injury']
    ],
    [
        'id' => 'edtc',
        'name' => '2. Care Transitions (EDTC)',
        'icon' => 'fas fa-exchange-alt',
        'desc' => 'Emergency Department Transfer Communication metrics.',
        'measures' => ['EDTC-All (Composite)', 'EDTC-Med (Medications Sent)', 'EDTC-Prov (Note/H&P Sent)']
    ],
    [
        'id' => 'outpatient-ed',
        'name' => '3. Outpatient & ED Efficiency',
        'icon' => 'fas fa-clock',
        'desc' => 'OP-18, OP-3, OP-22, OP-2 efficiency metrics.',
        'measures' => ['OP-18 (ED Arrival to Departure)', 'OP-3 (Time to Transfer)', 'OP-22 (Left Without Being Seen)', 'OP-2 (Fibrinolytic Therapy)']
    ],
    [
        'id' => 'hcahps',
        'name' => '4. Patient Engagement (HCAHPS)',
        'icon' => 'fas fa-user-friends',
        'desc' => 'H-Comp, H-Global, H-Clean, and SDOH metrics.',
        'measures' => ['H-Comp-1 (Nurse Communication)', 'H-Comp-3 (Staff Style)', 'H-Global (Willingness to Recommend)', 'H-Clean (Cleanliness)', 'SDOH 1+2 (Social Determinants)', 'HWR (Hospital-Wide Readmission)']
    ],
    [
        'id' => 'swing-bed',
        'name' => '5. Swing Bed Quality',
        'icon' => 'fas fa-bed',
        'desc' => 'Functional Gains, Discharge Disposition, ALOS.',
        'measures' => ['Functional Gains (Mobility/Self-care)', 'Discharge Disposition (Home/LTC/Acute)', 'Average Length of Stay (ALOS)']
    ],
    [
        'id' => 'pips',
        'name' => '6. Performance Improvement Projects (PIPs)',
        'icon' => 'fas fa-tasks',
        'desc' => 'ASP, Fall Reduction, ER Throughput, PDSA cycles.',
        'measures' => ['Antibiotic Stewardship Program (PIP)', 'Reduction of Patient Falls (PIP)', 'ER: Throughput Efficiency (PIP)', 'PDSA Cycle Status (Plan-Do-Study-Act)', 'Monthly Interventions Summary']
    ],
    [
        'id' => 'risk-management',
        'name' => '7. Risk Management & Grievances',
        'icon' => 'fas fa-exclamation-triangle',
        'desc' => 'Grievances, Incident Reports, Sentinel Events.',
        'measures' => ['Patient Grievances (Resolution Status)', 'Incident Reports (Variance Summary)', 'Sentinel Events (Root Cause Analysis)']
    ],
    [
        'id' => 'infection-control',
        'name' => '8. Infection Control (Monthly)',
        'icon' => 'fas fa-virus-slash',
        'desc' => 'CLABSI, CAUTI (Monthly), Hand Hygiene Compliance.',
        'measures' => ['CLABSI Rate', 'CAUTI Rate (Monthly)', 'Hand Hygiene Compliance']
    ],
    [
        'id' => 'rural-health',
        'name' => '9. Rural Health Clinics (Quarterly)',
        'icon' => 'fas fa-clinic-medical',
        'desc' => 'Diabetes, Hypertension, and Depression screenings.',
        'measures' => ['Diabetes Control (A1c > 9)', 'Hypertension Control', 'Depression Screening']
    ],
    [
        'id' => 'utilization-review',
        'name' => '10. Utilization Review (Quarterly)',
        'icon' => 'fas fa-file-invoice-dollar',
        'desc' => 'Medical Necessity Denials, Peer Review outcomes.',
        'measures' => ['Medical Necessity Denials', 'Peer-to-Peer Review Outcomes']
    ],
    [
        'id' => 'regulatory',
        'name' => '11. Regulatory & Survey Readiness',
        'icon' => 'fas fa-clipboard-check',
        'desc' => 'Mock Survey Findings, Life Safety audits.',
        'measures' => ['Mock Survey Findings (Internal Audits)', 'Life Safety (Fire Doors/Generator)']
    ],
];
$dm_inline_css_and_js = function() {
    ?>
    <script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js"></script>
    <style>
        :root {
            --dm-primary: #03283E;
            --dm-accent: #01A75C;
            --dm-bg: #f8fafc;
            --dm-border: #e0e6ed;
            --dm-hover-border: #a8dbe6;
            --dm-text-muted: #64748b;
        }

        .dm-data-hub-dashboard {
            padding-top: 0 !important;
            height: auto !important;
            min-height: 100vh;
            overflow: visible !important;
        }

        /* Hub Integration */
        .dm-data-hub-view-container {
            display: flex;
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--dm-border);
            margin: 12px 0 20px !important;
            min-height: 720px;
            min-height: calc(100vh - 90px);
        }

        .myorg-dashboard-topbar {
            padding: 10px 20px !important;
            margin-bottom: 0 !important;
            border-bottom: 1px solid rgba(0,0,0,0.05); /* Optional: just a nice touch */
        }

        .dm-shell {
            display: flex;
            width: 100%;
            min-height: inherit;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        /* Internal Sidebar */
        .dm-sidebar {
            width: 300px;
            background: #fcfdfe;
            border-right: 1px solid var(--dm-border);
            padding: 30px 20px;
            flex-shrink: 0;
            overflow-y: auto;
            min-height: inherit;
        }
        .dm-sidebar h3 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--dm-text-muted);
            margin: 0 0 20px 10px;
            font-weight: 700;
        }
        #dmAppPages .dm-nav-item {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            padding: 12px 14px !important;
            color: #4b5563 !important;
            text-decoration: none !important;
            border-radius: 10px !important;
            margin-bottom: 6px !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            justify-content: flex-start !important;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }
        #dmAppPages .dm-nav-item:hover { background: #f1f5f9 !important; color: var(--dm-primary) !important; }
        #dmAppPages .dm-nav-item.active {
            background: #eef2ff !important;
            color: var(--dm-primary) !important;
            font-weight: 600 !important;
            box-shadow: inset 0 0 0 1px rgba(3,40,62,0.05) !important;
        }
        #dmAppPages .dm-nav-item i {
            width: 20px !important;
            min-width: 20px !important;
            flex-shrink: 0 !important;
            text-align: center !important;
            font-size: 14px !important;
            color: #94a3b8 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            margin-right: 0 !important;
        }
        #dmAppPages .dm-nav-item.active i { color: var(--dm-primary) !important; }
        
        .dm-sub-nav { 
            margin-left: 24px; 
            border-left: 2px solid #f1f5f9; 
            padding-left: 12px; 
            margin-top: 6px; 
            margin-bottom: 12px; 
        }
        .dm-sub-item {
            padding: 8px 12px;
            font-size: 12px;
            color: #64748b;
            border-radius: 8px;
            cursor: pointer;
            margin-bottom: 2px;
            line-height: 1.4;
        }
        .dm-sub-item:hover { color: var(--dm-primary); background: #f8fafc; }
        .dm-sub-item.active { 
            color: var(--dm-primary); 
            font-weight: 600; 
            background: #f1f5f9; 
        }

        /* Main Content area */
        .dm-content { 
            flex: 1;
            padding: 40px 60px; 
            overflow-y: auto;
            background: #fff;
            min-height: inherit;
        }
        .dm-measure-rail {
            width: 100%;
            max-width: 1080px;
        }
        .dm-measure-action-row {
            width: 100%;
            max-width: 1080px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 14px;
            margin: 0 0 18px;
        }
        
        .dm-header { margin-bottom: 36px; }
        .dm-header h1 { font-size: 32px; font-weight: 800; color: var(--dm-primary); margin: 0; letter-spacing: -0.02em; }
        .dm-header p { color: var(--dm-text-muted); margin: 12px 0 0; font-size: 15px; max-width: 700px; line-height: 1.6; }
        .dm-measure-spec {
            width: 100%;
            max-width: 1080px;
            margin-top: 24px;
            border-collapse: collapse;
            border-top: 3px solid #2a8f95;
            border-bottom: 3px solid #2a8f95;
            color: var(--dm-primary);
            font-size: 14px;
            line-height: 1.45;
        }
        .dm-measure-spec th,
        .dm-measure-spec td {
            border: 1px solid #1f2933;
            padding: 8px 12px;
            vertical-align: top;
            text-align: left;
        }
        .dm-measure-spec thead th {
            background: #c9eef2;
            color: #071827;
            font-size: 16px;
            font-weight: 800;
        }
        .dm-measure-spec tbody th {
            width: 260px;
            background: #e8e8e8;
            color: #071827;
            font-weight: 700;
        }
        .dm-measure-spec tbody td {
            background: #fff;
            color: #172a3a;
        }
        .dm-measure-spec ol {
            margin: 6px 0 0 18px;
            padding: 0;
        }
        .dm-measure-spec li { margin: 2px 0; }
        .dm-measure-spec-stack {
            display: flex;
            flex-direction: column;
            gap: 28px;
            margin-top: 28px;
        }
        .dm-measure-spec-stack .dm-measure-spec {
            max-width: none;
            margin-top: 0;
        }
        .dm-measure-spec-section-title {
            margin: 0 0 14px;
            color: var(--dm-primary);
            font-size: 22px;
            font-weight: 800;
            line-height: 1.25;
            letter-spacing: 0;
        }
        .dm-measure-spec-section-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 14px;
        }
        .dm-measure-spec-section-head .dm-measure-spec-section-title {
            margin-bottom: 0;
        }
        .dm-measure-spec-section-head .dm-report-owner-control {
            padding-top: 0;
        }
        .dm-measure-spec-section-head .dm-report-owner-control,
        .dm-tabs-row .dm-report-owner-control,
        .dm-measure-action-row .dm-report-owner-control {
            margin-left: auto;
        }
        .dm-measure-goals {
            max-width: 1080px;
            margin-top: 20px;
            padding: 18px;
            border: 1px solid var(--dm-border);
            border-radius: 8px;
            background: #f8fbfc;
            overflow-x: auto;
        }
        .dm-measure-goals-head {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: flex-start;
            margin-bottom: 14px;
        }
        .dm-measure-goals-title {
            margin: 0;
            color: var(--dm-primary);
            font-size: 18px;
            font-weight: 800;
        }
        .dm-measure-goals-status {
            min-height: 20px;
            color: var(--dm-text-muted);
            font-size: 12px;
            font-weight: 700;
        }
        .dm-measure-goals-body {
            min-height: 250px;
            display: flex;
            flex-direction: column;
        }
        .dm-measure-goals-body.is-past {
            max-height: 250px;
            overflow: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
        }
        .dm-measure-goals-body.is-past .dm-guide {
            margin: 0;
            border: 0;
            border-radius: 0;
        }
        .dm-measure-goals-body.is-current .dm-goal-actions {
            margin-top: auto;
            padding-top: 18px;
        }
        .dm-goal-tabs {
            display: flex;
            gap: 18px;
            border-bottom: 1px solid #e5e7eb;
            margin: 2px 0 16px;
        }
        .dm-goal-tab {
            padding: 8px 0;
            color: var(--dm-text-muted);
            font-weight: 800;
            cursor: pointer;
            border-bottom: 3px solid transparent;
        }
        .dm-goal-tab.active {
            color: var(--dm-primary);
            border-bottom-color: var(--dm-primary);
        }
        .dm-measure-goals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            align-items: end;
        }
        .dm-measure-goals > .dm-measure-goals-grid {
            align-items: start;
            row-gap: 32px;
        }
        .dm-goal-field label {
            display: block;
            margin-bottom: 6px;
            color: var(--dm-primary);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .dm-goal-field .dm-field-label-inline {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .dm-field-tooltip {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            border-radius: 999px;
            background: #e8eef2;
            color: #64748b;
            font-size: 10px;
            line-height: 1;
            cursor: help;
        }
        .dm-field-tooltip:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(3, 40, 62, 0.16);
        }
        .dm-field-tooltip::after {
            content: attr(data-tooltip);
            position: absolute;
            left: 50%;
            bottom: calc(100% + 8px);
            z-index: 20;
            min-width: 190px;
            max-width: 240px;
            transform: translateX(-50%) translateY(4px);
            padding: 8px 10px;
            border-radius: 8px;
            background: #03283e;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.35;
            letter-spacing: 0;
            text-transform: none;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity .15s ease, transform .15s ease, visibility .15s ease;
        }
        .dm-field-tooltip:hover::after,
        .dm-field-tooltip:focus::after {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
        }
        .dm-goal-field {
            position: relative;
        }
        .dm-goal-field input,
        .dm-goal-field select {
            width: 100%;
            min-height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 8px 10px;
            font: inherit;
            color: var(--dm-primary);
            background: #fff;
            box-sizing: border-box;
        }
        .dm-goal-field select {
            cursor: pointer;
        }
        .dm-goal-field input:focus,
        .dm-goal-field input:focus-visible,
        .dm-goal-field select:focus,
        .dm-goal-field select:focus-visible,
        .dm-goal-date-trigger:focus,
        .dm-goal-date-trigger:focus-visible {
            outline: none !important;
            box-shadow: none !important;
            border-color: #d1d5db !important;
        }
        .dm-goal-field input[readonly] {
            background: #eef3f6;
            color: #64748b;
        }
        .dm-goal-field.has-error input,
        .dm-goal-field.has-error input[readonly],
        .dm-goal-date-control.has-error .dm-goal-date-display {
            border-color: #dc2626;
            background: #fff5f5;
            color: #991b1b;
        }
        .dm-goal-validation-error {
            display: flex;
            align-items: center;
            gap: 6px;
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            margin: 0;
            color: #b91c1c;
            font-size: 12px;
            font-weight: 800;
            line-height: 1.35;
        }
        .dm-improvement-single-grid {
            grid-template-columns:
                minmax(90px, .55fr)
                minmax(220px, 1.2fr)
                minmax(95px, .55fr)
                minmax(210px, 1.15fr)
                minmax(95px, .55fr)
                minmax(280px, 1.5fr);
        }
        .dm-improvement-single-grid.is-measure-scoped {
            grid-template-columns:
                minmax(96px, .55fr)
                minmax(92px, .5fr)
                minmax(160px, .9fr)
                minmax(170px, .95fr)
                minmax(118px, .55fr);
        }
        .dm-improvement-single-grid .dm-goal-field input,
        .dm-improvement-single-grid .dm-goal-field select {
            padding-left: 9px;
            padding-right: 9px;
        }
        .dm-improvement-rate-field {
            position: relative;
        }
        .dm-improvement-rate-field input {
            padding-right: 56px !important;
            text-overflow: ellipsis;
        }
        .dm-improvement-single-grid.is-measure-scoped .dm-improvement-rate-field input {
            padding-right: 12px !important;
            text-align: left;
        }
        .dm-improvement-rate-field .dm-den-warning-icon {
            right: 14px;
            top: calc(50% + 11px);
            pointer-events: none;
        }
        @media (max-width: 1100px) {
            .dm-improvement-single-grid {
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            }
        }
        .dm-goal-field-wide {
            grid-column: span 2;
        }
        @media (max-width: 720px) {
            .dm-goal-field-wide {
                grid-column: span 1;
            }
        }
        .dm-goal-date-control {
            position: relative;
            display: flex;
            align-items: center;
        }
        .dm-goal-date-control .dm-goal-date-display[readonly] {
            background: #fff;
            color: var(--dm-primary);
            cursor: pointer;
            padding-right: 44px;
        }
        .dm-goal-date-trigger {
            position: absolute;
            right: 10px;
            width: 28px;
            height: 28px;
            border: 0;
            background: transparent;
            color: var(--dm-primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .dm-goal-date-popover {
            position: fixed;
            z-index: 10000;
            width: min(328px, calc(100vw - 32px));
            background: #fff;
            border: 1px solid var(--dm-border);
            border-radius: 14px;
            box-shadow: 0 24px 48px rgba(15,23,42,0.22);
            padding: 14px;
            color: var(--dm-primary);
        }
        .dm-goal-date-popover-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }
        .dm-goal-date-title {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }
        .dm-goal-date-month,
        .dm-goal-date-year {
            border: 1px solid var(--dm-border);
            border-radius: 8px;
            background: #fff;
            color: var(--dm-primary);
            height: 34px;
            padding: 0 28px 0 10px;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
        }
        .dm-goal-date-month {
            min-width: 118px;
        }
        .dm-goal-date-year {
            min-width: 84px;
        }
        .dm-goal-date-month:focus,
        .dm-goal-date-month:focus-visible,
        .dm-goal-date-year:focus,
        .dm-goal-date-year:focus-visible {
            outline: none !important;
            outline-offset: 0 !important;
            box-shadow: none !important;
            border-color: var(--dm-border) !important;
        }
        .dm-goal-date-quick button {
            border: 1px solid var(--dm-border);
            background: #fff;
            color: var(--dm-primary);
            border-radius: 8px;
            min-width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .dm-goal-date-quick button:hover {
            background: #f8fafc;
            border-color: var(--dm-primary);
        }
        .dm-goal-date-weekdays,
        .dm-goal-date-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }
        .dm-goal-date-weekdays {
            margin-bottom: 6px;
            color: var(--dm-text-muted);
            font-size: 11px;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .dm-goal-date-day {
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: var(--dm-primary);
            height: 36px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }
        .dm-goal-date-day:hover {
            background: #eef6f8;
        }
        .dm-goal-date-day.is-muted {
            color: #94a3b8;
            font-weight: 600;
        }
        .dm-goal-date-day.is-selected {
            background: var(--dm-primary);
            color: #fff;
        }
        .dm-goal-date-day.is-today:not(.is-selected) {
            box-shadow: inset 0 0 0 2px #8fcbd5;
        }
        .dm-goal-date-quick {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--dm-border);
        }
        .dm-goal-date-quick button {
            flex: 1;
            font-weight: 800;
            font-size: 12px;
        }
        .dm-goal-date-popover button:focus,
        .dm-goal-date-popover button:focus-visible {
            outline: none;
            box-shadow: none;
        }
        .dm-goal-save {
            min-height: 42px;
            justify-content: center;
            padding: 10px 18px;
        }
        .dm-goal-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 18px;
        }
        .dm-goal-history-table {
            width: 100%;
            min-width: 760px;
            border-collapse: collapse;
            color: var(--dm-primary);
            font-size: 13px;
        }
        .dm-goal-history-table th,
        .dm-goal-history-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }
        .dm-goal-history-table th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #64748b;
        }
        .dm-goal-delete-cell {
            width: 54px;
            text-align: right;
        }
        .dm-goal-delete-btn {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
            border-radius: 8px;
            background: transparent;
            color: #b91c1c;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
        }
        .dm-goal-delete-btn:hover {
            background: #fff1f2;
            border-color: #fecdd3;
        }
        .dm-goal-delete-btn:focus,
        .dm-goal-delete-btn:focus-visible {
            outline: none;
            box-shadow: none;
        }
        .dm-confirm-copy {
            margin: 0;
            color: var(--dm-text-muted);
            font-size: 14px;
            line-height: 1.55;
        }
        .dm-btn-danger {
            background: #b91c1c;
            color: #fff;
        }
        .dm-btn-danger:hover {
            background: #991b1b;
        }
        .dm-spec-choice {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 22px;
            padding: 4px;
            border: 1px solid var(--dm-border);
            border-radius: 999px;
            background: #f8fafc;
        }
        .dm-spec-choice button {
            border: 0;
            border-radius: 999px;
            background: transparent;
            color: var(--dm-text-muted);
            font: inherit;
            font-size: 12px;
            font-weight: 800;
            padding: 7px 14px;
            cursor: pointer;
        }
        .dm-spec-choice button.active {
            background: var(--dm-primary);
            color: #fff;
            box-shadow: 0 6px 14px rgba(3, 40, 62, 0.18);
        }
        .dm-measure-tiles {
            max-width: 1080px;
            margin-top: 18px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }
        .dm-spec-tile {
            border: 1px solid #d9e4ea;
            border-radius: 8px;
            background: #fff;
            padding: 16px;
            min-height: 142px;
            box-shadow: 0 10px 22px rgba(3, 40, 62, 0.06);
        }
        .dm-spec-tile.featured {
            grid-column: span 2;
            background: #eff9fb;
            border-top: 4px solid #2a8f95;
        }
        .dm-spec-tile.wide { grid-column: span 2; }
        .dm-spec-tile strong {
            display: block;
            color: var(--dm-primary);
            font-size: 13px;
            font-weight: 900;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .dm-spec-tile p {
            color: #273849;
            font-size: 14px;
            line-height: 1.5;
            margin: 0;
        }
        .dm-spec-tile .dm-spec-value {
            color: var(--dm-primary);
            font-size: 28px;
            font-weight: 900;
            letter-spacing: 0;
            line-height: 1.1;
        }
        .dm-spec-tile .dm-spec-subtle {
            display: block;
            color: var(--dm-text-muted);
            font-size: 12px;
            margin-top: 6px;
        }
        .dm-trend-spec {
            max-width: 1080px;
            margin-top: 18px;
            color: var(--dm-primary);
        }
        .dm-trend-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 18px;
            align-items: center;
            border: 1px solid #d9e4ea;
            border-top: 4px solid #2a8f95;
            border-radius: 8px;
            background: #f6fbfc;
            padding: 22px;
            box-shadow: 0 10px 22px rgba(3, 40, 62, 0.06);
        }
        .dm-trend-eyebrow {
            display: block;
            color: #2a8f95;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .dm-trend-hero h2 {
            margin: 0;
            color: var(--dm-primary);
            font-size: 24px;
            line-height: 1.2;
            font-weight: 900;
        }
        .dm-trend-hero p {
            margin: 8px 0 0;
            color: #4d5f70;
            font-size: 14px;
            line-height: 1.5;
        }
        .dm-trend-pill {
            justify-self: end;
            border: 1px solid #b7d4d8;
            border-radius: 999px;
            background: #fff;
            color: var(--dm-primary);
            font-size: 12px;
            font-weight: 900;
            padding: 9px 13px;
            white-space: nowrap;
        }
        .dm-trend-kpis {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-top: 14px;
        }
        .dm-trend-kpi,
        .dm-trend-panel,
        .dm-trend-scale {
            border: 1px solid #d9e4ea;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 22px rgba(3, 40, 62, 0.05);
        }
        .dm-trend-kpi {
            padding: 15px;
            min-height: 110px;
        }
        .dm-trend-kpi strong,
        .dm-trend-panel strong,
        .dm-trend-scale strong {
            display: block;
            color: var(--dm-primary);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 9px;
        }
        .dm-trend-kpi span {
            display: block;
            color: var(--dm-primary);
            font-size: 28px;
            line-height: 1;
            font-weight: 900;
        }
        .dm-trend-kpi em {
            display: block;
            margin-top: 8px;
            color: var(--dm-text-muted);
            font-size: 12px;
            font-style: normal;
            line-height: 1.35;
        }
        .dm-trend-scale {
            margin-top: 14px;
            padding: 18px;
        }
        .dm-trend-track {
            position: relative;
            height: 12px;
            border-radius: 999px;
            background: #edf3f6;
            margin: 18px 0 28px;
            overflow: visible;
        }
        .dm-trend-track::before {
            content: '';
            position: absolute;
            inset: 0 auto 0 0;
            width: 60%;
            border-radius: inherit;
            background: #bfe3e4;
        }
        .dm-trend-marker {
            position: absolute;
            top: -9px;
            width: 2px;
            height: 30px;
            background: var(--dm-primary);
        }
        .dm-trend-marker span {
            position: absolute;
            top: 34px;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            color: #5f6f82;
            font-size: 11px;
            font-weight: 800;
        }
        .dm-trend-panel-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 14px;
        }
        .dm-trend-panel {
            padding: 17px;
        }
        .dm-trend-panel p {
            margin: 0;
            color: #273849;
            font-size: 14px;
            line-height: 1.5;
        }
        @media (max-width: 900px) {
            .dm-measure-tiles { grid-template-columns: 1fr; }
            .dm-spec-tile.featured,
            .dm-spec-tile.wide { grid-column: auto; }
            .dm-trend-hero,
            .dm-trend-kpis,
            .dm-trend-panel-grid { grid-template-columns: 1fr; }
            .dm-trend-pill { justify-self: start; }
        }

        .dm-breadcrumb { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            margin-bottom: 28px; 
            font-size: 13px; 
            color: var(--dm-text-muted);
            opacity: 0.8;
        }
        .dm-breadcrumb span { cursor: pointer; border-bottom: 1px solid transparent; }
        .dm-breadcrumb span:hover { color: var(--dm-primary); border-bottom-color: var(--dm-primary); }
        .dm-breadcrumb b { color: var(--dm-primary); font-weight: 600; }

        /* Grid & Cards */
        .dm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 16px;
        }
        .dm-grid:has(.dm-card:not(.dm-measure-card)) {
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }
        .dm-card { 
            background: #fff; 
            border: 1px solid var(--dm-border); 
            border-radius: 16px; 
            padding: 28px; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            display: flex;
            flex-direction: column;
            gap: 20px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
        }
        .dm-card:hover {
            border-color: var(--dm-hover-border);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.05);
            transform: translateY(-5px);
        }
        .dm-card i {
            width: 48px;
            height: 48px;
            background: #f1f5f9;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--dm-primary);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .dm-card:hover i { transform: scale(1.1); background: #eef2ff; }
        .dm-card h2 { font-size: 18px; font-weight: 700; color: var(--dm-primary); margin: 0; line-height: 1.3; }
        .dm-card p { font-size: 14px; color: var(--dm-text-muted); margin: 0; line-height: 1.55; }
        .dm-badge {
            align-self: flex-start;
            margin-top: auto;
            padding: 5px 12px;
            background: #f4f7fa;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        /* macOS-style folder measure cards */
        .dm-measure-card {
            min-height: auto !important;
            padding: 0 !important;
            gap: 0 !important;
            align-items: center !important;
            text-align: center;
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
        }
        .dm-measure-card:hover {
            transform: translateY(-3px) !important;
            box-shadow: none !important;
        }
        .dm-folder-shape {
            width: 100%;
            aspect-ratio: 1.25;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            border-radius: 2px 10px 10px 10px;
            background: linear-gradient(170deg, #a8d8ea 0%, #7cc0d8 40%, #5bafc9 100%);
            box-shadow: 0 4px 12px rgba(91, 175, 201, 0.3), inset 0 1px 0 rgba(255,255,255,0.35);
        }
        .dm-folder-shape::before {
            content: '';
            position: absolute;
            top: -8px;
            left: 0;
            width: 38%;
            height: 10px;
            background: linear-gradient(170deg, #93ccdf 0%, #6eb8cd 100%);
            border-radius: 6px 6px 0 0;
        }
        .dm-folder-shape::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 40%;
            background: linear-gradient(180deg, rgba(255,255,255,0.15) 0%, transparent 100%);
            border-radius: 2px 10px 0 0;
            pointer-events: none;
        }
        .dm-folder-count {
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            text-shadow: 0 1px 3px rgba(0,0,0,0.15);
            z-index: 1;
        }
        .dm-folder-files {
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.8);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 1px;
            z-index: 1;
        }
        .dm-folder-label {
            padding: 8px 6px 4px;
            font-size: 11px;
            font-weight: 600;
            color: var(--dm-primary);
            line-height: 1.3;
            text-align: center;
            width: 100%;
            word-break: break-word;
        }
        .dm-measure-card:hover .dm-folder-shape {
            background: linear-gradient(170deg, #96cee2 0%, #6ab4cc 40%, #4ea3bd 100%);
            box-shadow: 0 6px 16px rgba(91, 175, 201, 0.4), inset 0 1px 0 rgba(255,255,255,0.4);
        }
        .dm-measure-card h2 { display: none; }

        /* Input Controls */
        .dm-tabs { display: flex; gap: 32px; border-bottom: 2px solid #f1f5f9; margin-bottom: 36px; }
        .dm-tabs-row {
            display: flex;
            align-items: flex-start;
            gap: 24px;
            border-bottom: 2px solid #f1f5f9;
            margin-bottom: 36px;
            width: 100%;
            max-width: 1080px;
        }
        .dm-tabs-row .dm-tabs {
            flex: 1 1 auto;
            border-bottom: none;
            margin-bottom: 0;
        }
        .dm-report-owner-control {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            padding-top: 4px;
            flex: 0 0 auto;
            position: relative;
        }
        .dm-report-owner-control label {
            color: var(--dm-primary);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .dm-report-owner-control select {
            min-width: 220px;
            height: 42px;
            padding: 0 34px 0 12px;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            background: #fff;
            color: var(--dm-primary);
            font-size: 14px;
            font-weight: 600;
        }
        .dm-general-ownership-card {
            margin-top: 28px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }
        .dm-general-ownership-card h2 {
            margin: 0 0 8px;
            color: var(--dm-primary);
            font-size: 22px;
            font-weight: 800;
        }
        .dm-general-ownership-card > p {
            margin: 0 0 20px;
            color: var(--dm-text-muted);
            font-size: 14px;
            line-height: 1.5;
        }
        .dm-general-ownership-table-wrap {
            overflow-x: auto;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
        }
        .dm-general-ownership-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .dm-general-ownership-table th,
        .dm-general-ownership-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #eef2f7;
            text-align: left;
            vertical-align: middle;
        }
        .dm-general-ownership-table thead th {
            background: var(--dm-primary);
            color: #dbe7f1;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .dm-general-ownership-table th:nth-child(1),
        .dm-general-ownership-table td:nth-child(1) {
            width: 46%;
        }
        .dm-general-ownership-table th:nth-child(2),
        .dm-general-ownership-table td:nth-child(2) {
            width: 24%;
        }
        .dm-general-ownership-table th:nth-child(3),
        .dm-general-ownership-table td:nth-child(3) {
            width: 30%;
        }
        .dm-general-ownership-group td {
            background: #f8fafc;
            color: var(--dm-primary);
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .dm-general-ownership-measure {
            color: var(--dm-primary);
            font-weight: 700;
        }
        .dm-general-owner-select {
            width: 100%;
            min-width: 0;
            height: 42px;
            padding: 0 34px 0 12px;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            background: #fff;
            color: var(--dm-primary);
            font-size: 14px;
            font-weight: 600;
        }
        .dm-general-owner-select:focus,
        .dm-general-owner-select:focus-visible {
            outline: none !important;
            box-shadow: none !important;
            border-color: #d1d5db;
        }
        .dm-general-owner-status {
            min-width: 64px;
            color: var(--dm-text-muted);
            font-size: 18px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: center;
            padding-left: 20px;
        }
        .dm-general-owner-status .dm-status-success {
            color: #15803d;
        }
        .dm-general-owner-status .dm-status-saving {
            color: var(--dm-text-muted);
        }
        .dm-general-owner-status .dm-status-error {
            color: #b4342d;
        }
        .dm-general-manual-card {
            margin: 0 0 28px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }
        .dm-general-manual-card h2 {
            margin: 0 0 8px;
            color: var(--dm-primary);
            font-size: 22px;
            font-weight: 800;
        }
        .dm-general-manual-card > p {
            margin: 0 0 20px;
            color: var(--dm-text-muted);
            font-size: 14px;
            line-height: 1.5;
        }
        .dm-general-manual-controls {
            display: grid;
            grid-template-columns: minmax(260px, 1fr);
            gap: 14px;
            margin-bottom: 18px;
        }
        .dm-general-manual-field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }
        .dm-general-manual-field label {
            color: var(--dm-primary);
            font-size: 13px;
            font-weight: 800;
        }
        .dm-general-manual-field select,
        .dm-general-manual-field input {
            width: 100%;
            min-width: 0;
            height: 42px;
            padding: 0 12px;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            background: #fff;
            color: var(--dm-primary);
            font-size: 14px;
            font-weight: 600;
        }
        #dmUnifiedMeasureSelect:focus,
        #dmUnifiedMeasureSelect:focus-visible {
            outline: none !important;
            box-shadow: none !important;
            border-color: #d1d5db !important;
        }
        .dm-unified-measure-picker {
            position: relative;
        }
        .dm-unified-measure-trigger {
            width: 100%;
            min-height: 46px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 0 14px;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            background: #fff;
            color: var(--dm-primary);
            font-size: 14px;
            font-weight: 700;
            text-align: left;
            cursor: pointer;
        }
        .dm-unified-measure-trigger:focus,
        .dm-unified-measure-trigger:focus-visible,
        .dm-unified-measure-search:focus,
        .dm-unified-measure-search:focus-visible {
            outline: none !important;
            box-shadow: none !important;
            border-color: #d1d5db !important;
        }
        .dm-unified-measure-panel {
            position: static;
            z-index: 50;
            margin-top: 8px;
            padding: 10px;
            border: 1px solid #d8dee6;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.16);
        }
        .dm-unified-measure-search {
            width: 100%;
            height: 40px;
            margin-bottom: 8px;
            padding: 0 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            color: var(--dm-primary);
            font-size: 14px;
            font-weight: 600;
        }
        .dm-unified-measure-list {
            max-height: 340px;
            overflow-y: auto;
            overscroll-behavior: contain;
        }
        .dm-unified-measure-group {
            padding: 10px 10px 6px;
            color: #94a3b8;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .dm-unified-measure-option {
            width: 100%;
            display: block;
            padding: 10px 12px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: var(--dm-primary);
            font-size: 14px;
            font-weight: 700;
            text-align: left;
            cursor: pointer;
        }
        .dm-unified-measure-option:hover,
        .dm-unified-measure-option.active {
            background: #eaf5fb;
        }
        .dm-unified-measure-empty {
            padding: 14px 12px;
            color: var(--dm-text-muted);
            font-size: 14px;
            font-weight: 700;
        }
        .dm-coverage-layout {
            display: grid;
            gap: 18px;
        }
        .dm-coverage-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }
        .dm-coverage-toolbar h3 {
            margin: 0;
            color: var(--dm-primary);
            font-size: 18px;
            font-weight: 800;
        }
        .dm-coverage-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .dm-coverage-link {
            height: 34px;
            padding: 0 12px;
            border: 1px solid #dbe3ea;
            border-radius: 8px;
            background: #fff;
            color: var(--dm-primary);
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
        }
        .dm-coverage-group {
            padding: 18px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fbfd;
        }
        .dm-coverage-group + .dm-coverage-group {
            margin-top: 14px;
        }
        .dm-coverage-group h4 {
            margin: 0 0 12px;
            color: var(--dm-primary);
            font-size: 14px;
            font-weight: 900;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .dm-coverage-checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 10px;
        }
        .dm-coverage-checkbox {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            min-height: 46px;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            background: #fff;
            color: var(--dm-primary);
            font-size: 13px;
            font-weight: 750;
            line-height: 1.35;
            cursor: pointer;
        }
        .dm-coverage-checkbox input {
            width: 18px;
            height: 18px;
            margin: 0;
            accent-color: var(--dm-primary);
            flex: 0 0 auto;
        }
        .dm-coverage-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-top: 18px;
        }
        .dm-coverage-status {
            min-height: 20px;
            color: var(--dm-text-muted);
            font-size: 13px;
            font-weight: 750;
        }
        .dm-coverage-status.success {
            color: #15803d;
        }
        .dm-coverage-status.error {
            color: #b4342d;
        }
        .dm-general-manual-status {
            min-height: 20px;
            margin-top: 12px;
            color: var(--dm-text-muted);
            font-size: 13px;
            font-weight: 700;
        }
        .dm-general-manual-status.success {
            color: #15803d;
        }
        .dm-general-manual-status.error {
            color: #b4342d;
        }
        @media (max-width: 760px) {
            .dm-tabs-row {
                flex-direction: column;
                gap: 12px;
            }
            .dm-report-owner-control {
                width: 100%;
                margin-left: 0;
                padding-top: 0;
            }
            .dm-report-owner-control select {
                flex: 1 1 auto;
                min-width: 0;
            }
            .dm-measure-spec-section-head {
                flex-direction: column;
                gap: 10px;
            }
            .dm-measure-spec-section-head .dm-report-owner-control {
                width: 100%;
                margin-left: 0;
            }
            .dm-general-ownership-card {
                padding: 18px;
            }
            .dm-general-manual-card {
                padding: 18px;
            }
        }
        .dm-tab { padding: 14px 4px; font-weight: 600; color: var(--dm-text-muted); cursor: pointer; position: relative; font-size: 15px; }
        .dm-tab.active { color: var(--dm-primary); }
        .dm-tab.active::after { content: ''; position: absolute; bottom: -2px; left: 0; right: 0; height: 3px; background: var(--dm-primary); border-radius: 3px 3px 0 0; }

        .dm-input-pane { display: none; }
        .dm-input-pane.active {
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 1080px;
            margin-top: 20px;
            padding: 18px;
            border: 1px solid var(--dm-border);
            border-radius: 8px;
            background: #f8fbfc;
            box-sizing: border-box;
            overflow-x: auto;
        }
        .dm-input-pane > * { order: 20; }
        .dm-input-pane > h2 { display: none; }
        .dm-data-hub-view-container input[type="number"],
        .dm-shell input[type="number"],
        .dm-table input[type="number"],
        .dm-measure-goals input[type="number"],
        .dm-improvement-calculator input[type="number"] {
            appearance: textfield;
            -moz-appearance: textfield;
        }
        .dm-data-hub-view-container input[type="number"]::-webkit-outer-spin-button,
        .dm-data-hub-view-container input[type="number"]::-webkit-inner-spin-button,
        .dm-shell input[type="number"]::-webkit-outer-spin-button,
        .dm-shell input[type="number"]::-webkit-inner-spin-button,
        .dm-table input[type="number"]::-webkit-outer-spin-button,
        .dm-table input[type="number"]::-webkit-inner-spin-button,
        .dm-measure-goals input[type="number"]::-webkit-outer-spin-button,
        .dm-measure-goals input[type="number"]::-webkit-inner-spin-button,
        .dm-improvement-calculator input[type="number"]::-webkit-outer-spin-button,
        .dm-improvement-calculator input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            appearance: none;
            margin: 0;
        }
        .dm-entry-section-title {
            margin: 34px 0 14px;
            color: var(--dm-primary);
            font-size: 18px;
            font-weight: 800;
        }
        .dm-input-pane > .dm-entry-section-title { order: 1; }
        .dm-entry-section-title:first-child { margin-top: 0; }

        .dm-upload-box {
            background: #fcfdfe;
            border: 2px dashed #cbd5e1;
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            margin: 54px 0 30px;
            transition: border-color 0.2s;
            position: relative;
        }
        .dm-input-pane > .dm-upload-box { order: 8; }
        .dm-input-pane > .dm-upload-box::before {
            content: 'Upload Data';
            position: absolute;
            left: 0;
            top: -44px;
            color: var(--dm-primary);
            font-size: 18px;
            font-weight: 800;
            text-align: left;
        }
        .dm-upload-box:hover { border-color: var(--dm-primary); }
        .dm-upload-box:focus,
        .dm-upload-box:focus-visible,
        .dm-upload-box .dm-btn:focus,
        .dm-upload-box .dm-btn:focus-visible,
        .dm-upload-box .dm-btn:active,
        .dm-upload-box button:focus,
        .dm-upload-box button:focus-visible,
        .dm-upload-box button:active,
        .dm-upload-box label:focus,
        .dm-upload-box label:focus-visible,
        .dm-upload-box label:active,
        .dm-upload-box input:focus,
        .dm-upload-box input:focus-visible {
            outline: none !important;
            box-shadow: none !important;
            -webkit-tap-highlight-color: transparent;
        }
        .dm-upload-box > i { font-size: 48px; color: #94a3b8; margin-bottom: 20px; }
        .dm-check-cell { text-align: center; vertical-align: middle; }
        .dm-element-checkbox { width: 18px; height: 18px; accent-color: var(--dm-primary); cursor: pointer; }
        .dm-element-checkbox:focus,
        .dm-element-checkbox:focus-visible {
            outline: none !important;
            box-shadow: none !important;
        }
        .dm-component-muted { color: var(--dm-text-muted) !important; text-decoration: line-through; }
        .dm-table select:disabled { background: #f8fafc; color: #94a3b8; cursor: not-allowed; }
        .dm-table-wrap:has(.dm-den-warning-icon) {
            border-color: transparent;
        }
        .dm-den-cell {
            position: relative;
            padding-right: 52px !important;
        }
        .dm-den-warning-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #b91c1c;
            font-size: 18px;
            line-height: 1;
            pointer-events: none;
        }
        
        .dm-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            background: var(--dm-primary);
            color: #fff;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            font-size: 13px;
        }
        .dm-btn:hover { background: #0a3d5c; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(3,40,62,0.15); }
        .dm-btn-outline { background: #fff; color: var(--dm-primary); border: 1px solid #d1d5db; }
        .dm-btn-outline:hover { background: #f9fafb; border-color: var(--dm-primary); }
        .dm-btn:disabled { opacity: 0.55; cursor: not-allowed; }
        .dm-btn:disabled:hover { transform: none; box-shadow: none; }
        .dm-btn-outline:disabled:hover { background: #fff; border-color: #d1d5db; }

        .dm-btn i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            font-size: 14px;
            line-height: 1;
            margin: 0;
        }
        
        .dm-guide {
            background: #f8fafc;
            border: 1px solid var(--dm-border);
            padding: 20px 24px;
            border-radius: 14px;
            font-size: 14px;
            color: var(--dm-text-muted);
            margin-bottom: 32px;
            line-height: 1.6;
        }
        .dm-guide b { color: var(--dm-primary); font-weight: 700; }
        .dm-input-pane > .dm-guide { order: 6; }
        .dm-upload-error {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: -12px 0 24px;
            padding: 14px 16px;
            border: 1px solid #fecaca;
            border-radius: 10px;
            background: #fff7f7;
            color: #991b1b;
            font-size: 13px;
            line-height: 1.5;
        }
        .dm-input-pane > .dm-upload-error { order: 7; }
        .dm-upload-error i {
            margin-top: 2px;
            color: #dc2626;
        }
        .dm-upload-error-text {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .dm-raw-chart-card {
            background: #fff;
            border: 1px solid var(--dm-border);
            border-radius: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin: 24px 0;
            padding: 22px 24px 18px;
        }
        .dm-raw-chart-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 12px;
        }
        .dm-raw-chart-title {
            margin: 0;
            color: var(--dm-primary);
            font-size: 18px;
            font-weight: 800;
        }
        .dm-raw-chart-actions {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .dm-raw-chart-download {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1px solid var(--dm-border);
            border-radius: 10px;
            background: #fff;
            color: var(--dm-primary);
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
            padding: 10px 12px;
        }
        .dm-raw-chart-download:hover { background: #f8fafc; }
        .dm-raw-chart-icon-btn {
            width: 42px;
            height: 38px;
            padding: 0;
            font-size: 15px;
        }
        .dm-raw-chart-icon-btn.dm-copied,
        .dm-raw-chart-icon-btn.dm-copied:hover {
            background: #2f8f46;
            border-color: #2f8f46;
            color: #fff;
        }
        .dm-report-title-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
            width: 100%;
            max-width: 1080px;
            margin-bottom: 18px;
        }
        .dm-report-title-row h1 {
            margin: 0;
        }
        .dm-raw-chart-canvas-wrap {
            position: relative;
            width: 100%;
            height: 320px;
        }
        .dm-raw-chart-canvas {
            display: block;
            width: 100%;
            height: 100%;
        }
        .dm-missing-period-warning {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin: -4px 0 24px;
            padding: 15px 16px;
            border: 1px solid #facc15;
            border-radius: 12px;
            background: #fffbeb;
            color: #713f12;
            font-size: 13px;
            line-height: 1.5;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .dm-missing-period-warning i {
            flex: 0 0 auto;
            margin-top: 2px;
            color: #d97706;
            font-size: 15px;
        }
        .dm-missing-period-warning strong {
            display: block;
            margin-bottom: 3px;
            color: #713f12;
            font-size: 13px;
            font-weight: 800;
        }
        .dm-missing-period-warning span {
            display: block;
            color: #854d0e;
        }

        /* Input Table Styles */
        .dm-table-wrap { overflow-x: auto; background: #fff; border: 1px solid var(--dm-border); border-radius: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .dm-table { width: 100%; border-collapse: collapse; }
        .dm-table th { background: #fcfdfe; text-align: left; padding: 16px 20px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--dm-border); }
        .dm-table td { padding: 14px 20px; border-bottom: 1px solid #f1f5f9; }
        #dmGlobalChecklistTable td,
        #dmAntibioticChecklistTable td,
        #dmEdtcChecklistTable td {
            vertical-align: middle;
        }
        .dm-table td.dm-rate-cell {
            position: relative;
            vertical-align: middle;
            color: var(--dm-primary);
            font-size: 14px;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
        }
        .dm-table td.dm-row-action-cell {
            vertical-align: middle;
            text-align: center;
        }
        .dm-row-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: #dc4f4f;
            cursor: pointer;
            font-size: 16px;
            line-height: 1;
        }
        .dm-row-action-btn:hover {
            background: #fef2f2;
            color: #b91c1c;
        }
        .dm-table input { width: 100%; border: 1px solid #d1d5db; padding: 10px 14px; border-radius: 8px; font-size: 14px; transition: border-color 0.2s; }
        .dm-table input:focus { outline: none; border-color: var(--dm-primary); box-shadow: 0 0 0 3px rgba(3,40,62,0.1); }
        .dm-table select { width: 100%; border: 1px solid #d1d5db; padding: 10px 14px; border-radius: 8px; font-size: 14px; background: #fff; cursor: pointer; transition: border-color 0.2s; }
        .dm-table select:focus { outline: none; border-color: var(--dm-primary); box-shadow: 0 0 0 3px rgba(3,40,62,0.1); }
        .dm-manual-table-wrap {
            width: auto;
            max-width: 100%;
            display: inline-block;
            vertical-align: top;
        }
        .dm-manual-table {
            width: auto;
            min-width: 640px;
            max-width: 100%;
            table-layout: fixed;
        }
        .dm-manual-table th,
        .dm-manual-table td {
            padding-left: 12px;
            padding-right: 12px;
        }
        .dm-manual-table input,
        .dm-manual-table select {
            min-height: 42px;
            padding-left: 12px;
            padding-right: 12px;
        }
        .dm-manual-table-annual col.dm-col-year { width: 130px; }
        .dm-manual-table-annual col.dm-col-num,
        .dm-manual-table-annual col.dm-col-den { width: 230px; }
        .dm-manual-table-annual col.dm-col-rate { width: 110px; }
        .dm-manual-table-quarter-rate col.dm-col-year,
        .dm-manual-table-quarter-rate col.dm-col-quarter { width: 120px; }
        .dm-manual-table-quarter-rate col.dm-col-num,
        .dm-manual-table-quarter-rate col.dm-col-den { width: 210px; }
        .dm-manual-table-quarter-rate col.dm-col-rate { width: 110px; }
        .dm-manual-table-quarter-median col.dm-col-year,
        .dm-manual-table-quarter-median col.dm-col-quarter { width: 120px; }
        .dm-manual-table-quarter-median col.dm-col-median { width: 220px; }
        .dm-manual-table-monthly col.dm-col-month { width: 120px; }
        .dm-manual-table-monthly col.dm-col-year { width: 110px; }
        .dm-manual-table-monthly col.dm-col-num,
        .dm-manual-table-monthly col.dm-col-den { width: 200px; }
        .dm-manual-table-monthly col.dm-col-rate { width: 110px; }
        .dm-manual-table-monthly col.dm-col-action { width: 48px; }
        @media (max-width: 820px) {
            .dm-manual-table-wrap {
                display: block;
                width: 100%;
            }
            .dm-manual-table {
                min-width: 640px;
            }
        }
        .dm-edtc-table-wrap {
            overflow-y: visible;
        }
        .dm-edtc-table th {
            padding: 9px 14px;
            font-size: 10px;
        }
        .dm-edtc-table td {
            padding: 8px 14px;
        }
        .dm-edtc-table input {
            height: 34px;
            padding: 7px 10px;
            border-radius: 7px;
            font-size: 13px;
        }
        .dm-edtc-table td:first-child {
            font-size: 13px;
            line-height: 1.25;
        }
        .dm-edtc-table td.dm-rate-cell {
            font-size: 13px;
        }
        .dm-edtc-table .dm-den-cell {
            padding-right: 42px !important;
        }
        .dm-edtc-table .dm-den-warning-icon {
            right: 16px;
            font-size: 16px;
        }
        .dm-shell select.dm-year-select,
        .dm-shell select.dm-quarter-select {
            min-height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            background-color: #fff;
            color: var(--dm-primary);
            font-size: 14px;
            font-weight: 600;
            line-height: 1.2;
            padding: 0 34px 0 12px;
            cursor: pointer;
            box-sizing: border-box;
        }
        .dm-shell select.dm-year-select:focus,
        .dm-shell select.dm-year-select:focus-visible,
        .dm-shell select.dm-quarter-select:focus,
        .dm-shell select.dm-quarter-select:focus-visible {
            outline: none !important;
            box-shadow: none !important;
            border-color: #d1d5db !important;
        }
        .dm-guide-link:focus,
        .dm-guide-link:focus-visible {
            outline: none !important;
            box-shadow: none !important;
        }
        .dm-subtabs {
            display: inline-flex;
            gap: 6px;
            padding: 4px;
            border: 1px solid var(--dm-border);
            border-radius: 999px;
            background: #f8fafc;
        }
        .dm-subtabs button {
            border: 0;
            border-radius: 999px;
            background: transparent;
            color: var(--dm-text-muted);
            cursor: pointer;
            font: inherit;
            font-size: 13px;
            font-weight: 800;
            padding: 9px 16px;
        }
        .dm-subtabs button.active {
            background: var(--dm-primary);
            color: #fff;
        }
        .dm-improvement-table {
            min-width: 2240px;
            table-layout: fixed;
        }
        .dm-improvement-table th,
        .dm-improvement-table td {
            padding: 12px 12px;
            vertical-align: middle;
        }
        .dm-improvement-table th {
            white-space: normal;
            line-height: 1.35;
        }
        .dm-improvement-table th:first-child,
        .dm-improvement-table td:first-child {
            width: 88px;
            min-width: 88px;
            position: sticky;
            left: 0;
            z-index: 2;
            background: #fff;
        }
        .dm-improvement-table th:first-child {
            background: #173f5b;
            z-index: 3;
        }
        .dm-improvement-table input {
            box-sizing: border-box;
            min-width: 96px;
            height: 44px;
            padding: 9px 12px;
            text-align: right;
            font-size: 15px;
            color: #1f2937;
        }
        .dm-improvement-table td.dm-rate-cell {
            min-width: 128px;
            text-align: right;
        }

        .dm-back-btn { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; color: var(--dm-text-muted); margin-bottom: 32px; transition: all 0.2s; font-size: 14px; font-weight: 500; }
        .dm-back-btn:hover { color: var(--dm-primary); transform: translateX(-4px); }

        .dm-row-actions { display: flex; gap: 12px; margin-top: 16px; }
        .dm-row-actions.top { margin-bottom: 24px; }
        .dm-input-pane > .dm-row-actions.top {
            order: 1;
            flex-wrap: wrap;
            margin-top: 0 !important;
        }
        .dm-input-pane > .dm-table-wrap { order: 2; }
        .dm-input-pane > .dm-table-wrap + div[style*="display:grid"] { order: 3; }
        .dm-input-pane > .dm-edtc-entry-block { order: 8; }
        .dm-row-actions.bottom { justify-content: flex-end; }

        .dm-save-section {
            margin-top: 48px;
            padding-top: 32px;
            border-top: 1px solid #f1f5f9;
            text-align: right;
        }
        .dm-input-pane > .dm-save-section { order: 4; margin-bottom: 44px; }
        .dm-input-pane > .dm-assessment-panel { order: 9; margin-top: 24px; }
        .dm-saved-assessment-scroll {
            max-height: 420px;
            overflow: auto;
            border: 1px solid var(--dm-border);
            border-radius: 8px;
            background: #fff;
        }
        .dm-saved-assessment-scroll .dm-uploaded-files-list {
            margin-top: 0 !important;
            padding: 18px 20px;
        }
        .dm-saved-assessment-scroll .dm-uploaded-files-list h3 {
            margin-bottom: 14px !important;
        }
        .dm-saved-assessment-scroll .dm-uploaded-files-list .dm-file-item,
        .dm-saved-assessment-scroll .dm-uploaded-files-list li {
            margin-left: 0;
            margin-right: 0;
        }
        .dm-saved-assessment-scroll > ul {
            padding: 10px;
        }
        .dm-saved-assessment-scroll > .dm-guide {
            margin: 18px 20px;
            border: 0;
            border-radius: 0;
        }
        .dm-saved-assessment-scroll .dm-file-item {
            box-shadow: none;
        }

        .dm-toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #01A75C;
            color: #fff;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(1, 167, 92, 0.3);
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            z-index: 1000;
            transform: translateY(100px);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .dm-toast.active { transform: translateY(0); }

        @media (max-width: 1300px) { .dm-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 1024px) {
            .dm-data-hub-view-container { min-height: calc(100vh - 120px); }
            .dm-shell { display: block; min-height: auto; }
            .dm-sidebar { width: 100%; border-right: none; border-bottom: 1px solid var(--dm-border); height: auto; min-height: 0; }
            .dm-content { min-height: calc(100vh - 260px); }
        }
    </style>
    <?php
};

// In standalone mode, inject via wp_head. In embed mode, output inline immediately.
if ( empty( $GLOBALS['dh_embed_mode'] ) ) {
    add_action( 'wp_head', $dm_inline_css_and_js );
    get_header();
} else {
    $dm_inline_css_and_js();
}
?>

<?php if ( empty( $GLOBALS['dh_embed_mode'] ) ) : ?>
<div class="myorg-dashboard dm-data-hub-dashboard">
    <!-- Top Bar -->
    <header class="myorg-dashboard-topbar" style="display: flex; align-items: center; justify-content: space-between; padding: 14px 20px;">
        <h1 style="display: flex; align-items: center; gap: 12px; margin: 0; font-size: 1.5rem; color: #03283E;">
            <div style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; color: #03283E;">
                <?php echo str_replace('<svg', '<svg style="width:100%; height:100%;"', file_get_contents( dirname( dirname( __FILE__ ) ) . '/assets/images/icons/my-data.svg' ) ); ?>
            </div>
            Data Management
        </h1>
        <a href="<?php echo esc_url( home_url( '/my-org/' ) ); ?>" class="gv-back-btn">
            <i class="fas fa-arrow-left"></i> My Org
        </a>
    </header>
<?php endif; ?>

    <div class="dm-data-hub-view-container">
        <div class="dm-shell" id="dmAppPages">
            <aside class="dm-sidebar">
                <h3><?php echo esc_html( $dm_org_data_label ); ?></h3>
                <nav id="dmSidebarItems">
                    <!-- Sidebar items injected by JS -->
                </nav>
            </aside>

            <main class="dm-content">
                <div id="dmViewContainer">
                    <!-- Views injected by JS -->
                </div>
            </main>
        </div>
    </div>

    <div id="dmToast" class="dm-toast">
        <i class="fas fa-check-circle"></i>
        <span>Data saved successfully!</span>
    </div>

    <div id="dmGoalDatePickerPopover" class="dm-goal-date-popover" style="display:none;"></div>

    <div id="dmFileViewer" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.55); align-items:center; justify-content:center; padding:24px;">
        <div style="background:#fff; border-radius:12px; width:min(1100px, 100%); max-height:88vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35);">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:16px 20px; border-bottom:1px solid #e5e7eb;">
                <div style="display:flex; align-items:center; gap:10px; min-width:0;">
                    <i class="fas fa-file-csv" style="color:var(--dm-primary);"></i>
                    <strong id="dmFileViewerTitle" style="font-size:15px; color:var(--dm-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">File</strong>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <a id="dmFileViewerDownload" href="#" download class="dm-btn dm-btn-outline" style="padding:6px 12px; font-size:13px;">
                        <i class="fas fa-download"></i> Download
                    </a>
                    <button type="button" id="dmFileViewerClose" class="dm-btn dm-btn-outline" style="padding:6px 12px; font-size:13px;" onclick="dmApp.closeFileViewer()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
            <div id="dmFileViewerBody" style="flex:1; overflow:auto; padding:16px 20px; font-size:13px; color:#0f172a;">
                <div style="color:#64748b;">Loading…</div>
            </div>
        </div>
    </div>

    <div id="dmPasteModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.55); align-items:center; justify-content:center; padding:24px;">
        <div style="background:#fff; border-radius:12px; width:min(560px, 100%); display:flex; flex-direction:column; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35);">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:16px 20px; border-bottom:1px solid #e5e7eb;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-paste" style="color:var(--dm-primary);"></i>
                    <strong style="font-size:15px; color:var(--dm-primary);">Paste from Excel</strong>
                </div>
                <button type="button" class="dm-btn dm-btn-outline" style="padding:6px 12px; font-size:13px;" onclick="dmApp.closePasteModal()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <div style="padding:18px 20px;">
                <p style="margin:0 0 12px; font-size:13px; color:var(--dm-text-muted); line-height:1.55;">
                    Copy your rows from Excel or Google Sheets, click in the box below, and paste (<b>Ctrl/Cmd + V</b>). Use this column order: <b>Year, Month, Numerator, Denominator</b>. A header row is optional.
                </p>
                <textarea id="dmPasteArea" placeholder="2024&#9;Jan&#9;45&#9;100&#10;2024&#9;Feb&#9;52&#9;100" style="width:100%; min-height:150px; border:1px solid #d1d5db; border-radius:8px; padding:12px 14px; font-size:13px; font-family:ui-monospace, SFMono-Regular, Menlo, monospace; resize:vertical; box-sizing:border-box;"></textarea>
                <div id="dmPasteError" style="display:none; margin-top:10px; font-size:13px; color:#ef4444; align-items:center; gap:7px;">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="dmPasteErrorText"></span>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; padding:14px 20px; border-top:1px solid #e5e7eb;">
                <button type="button" class="dm-btn dm-btn-outline" onclick="dmApp.closePasteModal()">Cancel</button>
                <button type="button" class="dm-btn" onclick="dmApp.importPastedData()"><i class="fas fa-check"></i> Import Data</button>
            </div>
        </div>
    </div>

    <div id="dmImprovementPreviewModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.55); align-items:center; justify-content:center; padding:24px;">
        <div style="background:#fff; border-radius:12px; width:min(620px, 100%); display:flex; flex-direction:column; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35);">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:16px 20px; border-bottom:1px solid #e5e7eb;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-eye" style="color:var(--dm-primary);"></i>
                    <strong id="dmImprovementPreviewTitle" style="font-size:15px; color:var(--dm-primary);">Saved submission</strong>
                </div>
                <button type="button" class="dm-btn dm-btn-outline" style="padding:6px 12px; font-size:13px;" onclick="dmApp.closeImprovementSubmissionPreview()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <div id="dmImprovementPreviewBody" style="padding:18px 20px; color:var(--dm-primary);"></div>
        </div>
    </div>

    <div id="dmGoalArchiveModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.55); align-items:center; justify-content:center; padding:24px;">
        <div style="background:#fff; border-radius:12px; width:min(460px, 100%); display:flex; flex-direction:column; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35);">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:16px 20px; border-bottom:1px solid #e5e7eb;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-trash-alt" style="color:#b91c1c;"></i>
                    <strong style="font-size:15px; color:var(--dm-primary);">Remove Past Goal</strong>
                </div>
                <button type="button" class="dm-btn dm-btn-outline" style="padding:6px 12px; font-size:13px;" onclick="dmApp.closeGoalArchiveModal()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <div style="padding:18px 20px;">
                <p class="dm-confirm-copy">Remove this past goal from view?</p>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; padding:14px 20px; border-top:1px solid #e5e7eb;">
                <button type="button" class="dm-btn dm-btn-outline" onclick="dmApp.closeGoalArchiveModal()">Cancel</button>
                <button type="button" class="dm-btn dm-btn-danger" onclick="dmApp.confirmArchiveMeasureGoal()"><i class="fas fa-trash-alt"></i> Remove</button>
            </div>
        </div>
    </div>

    <div id="dmWorkbookConflictModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.55); align-items:center; justify-content:center; padding:24px;">
        <div style="background:#fff; border-radius:14px; width:min(720px, 100%); max-height:min(760px, calc(100vh - 48px)); display:flex; flex-direction:column; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35);">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:18px 22px; border-bottom:1px solid #e5e7eb;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <span style="width:42px; height:42px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; background:#fef3c7; color:#92400e;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </span>
                    <div>
                        <strong style="display:block; font-size:18px; color:var(--dm-primary); line-height:1.25;">Replace saved Data Hub records?</strong>
                        <span style="display:block; margin-top:3px; font-size:13px; color:var(--dm-text-muted);">This workbook contains periods that have already been saved.</span>
                    </div>
                </div>
                <button type="button" class="dm-btn dm-btn-outline" style="padding:6px 12px; font-size:13px;" onclick="dmApp.closeWorkbookConflictModal(true)">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <div style="padding:20px 22px; overflow:auto;">
                <p style="margin:0 0 14px; color:#334155; font-size:14px; line-height:1.55;">Uploading this workbook will replace the saved records listed below for the populated sheets.</p>
                <div id="dmWorkbookConflictBody" style="display:flex; flex-direction:column; gap:12px;"></div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; padding:16px 22px; border-top:1px solid #e5e7eb; background:#f8fafc;">
                <button type="button" class="dm-btn dm-btn-outline" onclick="dmApp.closeWorkbookConflictModal(true)">Cancel</button>
                <button type="button" id="dmWorkbookConflictContinue" class="dm-btn" onclick="dmApp.confirmWorkbookConflictOverwrite()"><i class="fas fa-sync-alt"></i> Replace Saved Records</button>
            </div>
        </div>
    </div>

    <div id="dmImprovementOverwriteModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.55); align-items:center; justify-content:center; padding:24px;">
        <div style="background:#fff; border-radius:14px; width:min(560px, 100%); display:flex; flex-direction:column; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35);">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:18px 22px; border-bottom:1px solid #e5e7eb;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <span style="width:42px; height:42px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; background:#fef3c7; color:#92400e;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </span>
                    <div>
                        <strong style="display:block; font-size:18px; color:var(--dm-primary); line-height:1.25;">Replace saved assessment?</strong>
                        <span style="display:block; margin-top:3px; font-size:13px; color:var(--dm-text-muted);">This period already has saved HACs &amp; HAIs data.</span>
                    </div>
                </div>
                <button type="button" class="dm-btn dm-btn-outline" style="padding:6px 12px; font-size:13px;" onclick="dmApp.closeImprovementOverwriteModal(true)">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <div style="padding:20px 22px;">
                <p id="dmImprovementOverwriteMessage" style="margin:0; color:#334155; font-size:14px; line-height:1.55;">This month and measure already has saved data. Replace it?</p>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; padding:16px 22px; border-top:1px solid #e5e7eb; background:#f8fafc;">
                <button type="button" class="dm-btn dm-btn-outline" onclick="dmApp.closeImprovementOverwriteModal(true)">Cancel</button>
                <button type="button" id="dmImprovementOverwriteContinue" class="dm-btn" onclick="dmApp.confirmImprovementOverwrite()"><i class="fas fa-sync-alt"></i> Replace Saved Assessment</button>
            </div>
        </div>
    </div>

    <div id="dmAssessmentOverwriteModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.55); align-items:center; justify-content:center; padding:24px;">
        <div style="background:#fff; border-radius:14px; width:min(560px, 100%); display:flex; flex-direction:column; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35);">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:18px 22px; border-bottom:1px solid #e5e7eb;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <span style="width:42px; height:42px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; background:#fef3c7; color:#92400e;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </span>
                    <div>
                        <strong id="dmAssessmentOverwriteTitle" style="display:block; font-size:18px; color:var(--dm-primary); line-height:1.25;">Replace saved assessment?</strong>
                        <span id="dmAssessmentOverwriteSubtitle" style="display:block; margin-top:3px; font-size:13px; color:var(--dm-text-muted);">This period already has a saved assessment.</span>
                    </div>
                </div>
                <button type="button" class="dm-btn dm-btn-outline" style="padding:6px 12px; font-size:13px;" onclick="dmApp.resolveAssessmentOverwriteModal(false)">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <div style="padding:20px 22px;">
                <p id="dmAssessmentOverwriteMessage" style="margin:0; color:#334155; font-size:14px; line-height:1.55;">A saved assessment already exists. Replace it?</p>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; padding:16px 22px; border-top:1px solid #e5e7eb; background:#f8fafc;">
                <button type="button" class="dm-btn dm-btn-outline" onclick="dmApp.resolveAssessmentOverwriteModal(false)">Cancel</button>
                <button type="button" id="dmAssessmentOverwriteContinue" class="dm-btn" onclick="dmApp.resolveAssessmentOverwriteModal(true)"><i class="fas fa-sync-alt"></i> Replace Saved Assessment</button>
            </div>
        </div>
    </div>

    <div id="dmCoverageUnsavedModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.55); align-items:center; justify-content:center; padding:24px;">
        <div style="background:#fff; border-radius:14px; width:min(560px, 100%); display:flex; flex-direction:column; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35);">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:18px 22px; border-bottom:1px solid #e5e7eb;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <span style="width:42px; height:42px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; background:#fef3c7; color:#92400e;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </span>
                    <div>
                        <strong style="display:block; font-size:18px; color:var(--dm-primary); line-height:1.25;">Unsaved measure changes</strong>
                        <span style="display:block; margin-top:3px; font-size:13px; color:var(--dm-text-muted);">Measure coverage has been changed but not saved.</span>
                    </div>
                </div>
                <button type="button" class="dm-btn dm-btn-outline" style="padding:6px 12px; font-size:13px;" onclick="dmApp.closeCoverageUnsavedModal()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <div style="padding:20px 22px;">
                <p class="dm-confirm-copy">Save your measure changes before leaving, discard the changes, or cancel to keep editing.</p>
                <div id="dmCoverageUnsavedStatus" style="min-height:20px; margin-top:12px; color:#64748b; font-size:13px; font-weight:700;"></div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; padding:16px 22px; border-top:1px solid #e5e7eb; background:#f8fafc;">
                <button type="button" class="dm-btn dm-btn-outline" onclick="dmApp.closeCoverageUnsavedModal()">Cancel</button>
                <button type="button" class="dm-btn dm-btn-outline" onclick="dmApp.discardCoverageChangesAndContinue()">Discard Changes</button>
                <button type="button" id="dmCoverageUnsavedSave" class="dm-btn" onclick="dmApp.saveCoverageChangesAndContinue()"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </div>
    </div>
</div>

<?php
// Bootstrap per-measure saved counts so the dashboard isn't blank on reload.
// Counts files whose filename starts with the measure's sanitized slug — that
// matches what dm_save_data_handler writes (`sanitize_file_name($measure) . '_' . time() . '.csv'`).
// Reading the DB and grouping by source_name overcounts because one CSV upload
// gets attributed to every measure in its folder during ingestion.
$initial_saved_measures   = array();
$initial_files_by_measure = array();
$dm_user_org_context      = array(
    'org_name'   => '',
    'org_slug'   => '',
    'org_key'    => '',
    'state_code' => '',
);
if ( is_user_logged_in() ) {
    $dm_user_id  = (int) get_current_user_id();
    if ( function_exists( 'qualinav_data_hub_get_org_context' ) ) {
        $dm_user_org_context = qualinav_data_hub_get_org_context( $dm_user_id );
        $dm_org_key = (string) ( $dm_user_org_context['org_key'] ?? '' );
    } else {
        $dm_org_name = trim( (string) get_user_meta( $dm_user_id, 'organization', true ) );
        if ( $dm_org_name === '' ) { $dm_org_name = 'User ' . $dm_user_id; }
        $dm_org_key = sanitize_title( $dm_org_name );
        $dm_user_org_context['org_name'] = $dm_org_name;
        $dm_user_org_context['org_slug'] = $dm_org_key;
        $dm_user_org_context['org_key']  = $dm_org_key;
    }
    if ( $dm_org_key === '' ) { $dm_org_key = 'user-' . $dm_user_id; }
    $dm_user_org_context['org_key'] = $dm_org_key;
    if ( '' === trim( (string) ( $dm_user_org_context['org_name'] ?? '' ) ) && '' !== $dm_page_org_name ) {
        $dm_user_org_context['org_name'] = $dm_page_org_name;
    }

    $dm_folder_files = get_option( 'dm_org_folder_files_' . $dm_org_key, array() );
    if ( ! is_array( $dm_folder_files ) ) {
        $dm_folder_files = array();
    }
    $dm_upload_dir = wp_upload_dir();
    $dm_base       = $dm_upload_dir['basedir'] . '/qualinav-dm/' . $dm_org_key;

    // Orphan-row cleanup: when the user deleted files previously (before the
    // delete handler also cleaned wp_qapi_metric_data), the rows stayed and
    // the dashboard kept charting them. Drop any DB rows whose source_name
    // isn't represented in the user's current option — once per page load.
    global $wpdb;
    $dm_metric_table = $wpdb->prefix . 'qapi_metric_data';
    if ( $dm_user_id > 0 && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $dm_metric_table ) ) === $dm_metric_table ) {
        $known_sources = array();
        foreach ( $dm_folder_files as $dm_files_list ) {
            if ( ! is_array( $dm_files_list ) ) { continue; }
            foreach ( $dm_files_list as $dm_file_entry ) {
                if ( is_array( $dm_file_entry ) && ! empty( $dm_file_entry['name'] ) ) {
                    $known_sources[ (string) $dm_file_entry['name'] ] = true;
                }
            }
        }
        $rows_deleted = 0;
        if ( empty( $known_sources ) ) {
            // No files at all → user has nothing; drop every row for them AND
            // any legacy NULL-user_id rows so the dashboard truly empties.
            $rows_deleted = (int) $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$dm_metric_table} WHERE user_id = %d OR user_id IS NULL",
                $dm_user_id
            ) );
        } else {
            $placeholders = implode( ',', array_fill( 0, count( $known_sources ), '%s' ) );
            $params       = array_merge( array( $dm_user_id ), array_keys( $known_sources ), array_keys( $known_sources ) );
            $rows_deleted = (int) $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$dm_metric_table}
                  WHERE (user_id = %d AND source_name NOT IN ({$placeholders}))
                     OR (user_id IS NULL AND source_name NOT IN ({$placeholders}))",
                $params
            ) );
        }
        // If we dropped any orphan rows, wipe every dashboard metrics
        // transient (no org_key dependency) so Dashboard Reports refreshes
        // from the truth in wp_qapi_metric_data instead of serving cache.
        if ( $rows_deleted > 0 ) {
            $wpdb->query(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_qaqd\_live\_metrics\_%' OR option_name LIKE '\_transient\_timeout\_qaqd\_live\_metrics\_%'"
            );
        }
    }

    $dm_extract_assessment_year = function ( $file_name ) use ( $dm_base ) {
        $path = $dm_base . '/' . sanitize_file_name( (string) $file_name );
        if ( ! is_readable( $path ) ) {
            return '';
        }
        $fh = fopen( $path, 'r' );
        if ( ! $fh ) {
            return '';
        }
        $headers = fgetcsv( $fh );
        $year_idx = false;
        if ( is_array( $headers ) ) {
            foreach ( $headers as $idx => $header ) {
                if ( strtolower( trim( (string) $header ) ) === 'year' ) {
                    $year_idx = $idx;
                    break;
                }
            }
        }
        $year = '';
        if ( $year_idx !== false ) {
            while ( ( $row = fgetcsv( $fh ) ) !== false ) {
                $candidate = trim( (string) ( $row[ $year_idx ] ?? '' ) );
                if ( preg_match( '/^[12][0-9]{3}$/', $candidate ) ) {
                    $year = $candidate;
                    break;
                }
            }
        }
        fclose( $fh );
        return $year;
    };

    $dm_extract_raw_rows = function ( $file_name ) use ( $dm_base ) {
        $path = $dm_base . '/' . sanitize_file_name( (string) $file_name );
        if ( ! is_readable( $path ) ) {
            return array();
        }
        $fh = fopen( $path, 'r' );
        if ( ! $fh ) {
            return array();
        }
        $headers = fgetcsv( $fh );
        if ( ! is_array( $headers ) ) {
            fclose( $fh );
            return array();
        }
        $normalized_headers = array_map( static function ( $header ) {
            return strtolower( trim( (string) $header ) );
        }, $headers );
        $rows = array();
        while ( ( $row = fgetcsv( $fh ) ) !== false ) {
            if ( ! is_array( $row ) || ! array_filter( $row, static function ( $cell ) {
                return trim( (string) $cell ) !== '';
            } ) ) {
                continue;
            }
            $record = array();
            foreach ( $normalized_headers as $idx => $header ) {
                $value = trim( (string) ( $row[ $idx ] ?? '' ) );
                if ( $header !== '' ) {
                    $record[ $header ] = $value;
                }
                switch ( $header ) {
                    case 'metric':
                        $record['metric'] = $value;
                        break;
                    case 'year':
                        $record['year'] = $value;
                        break;
                    case 'date reported':
                        $record['date_reported'] = $value;
                        break;
                    case 'month':
                    case 'quarter':
                        $record['period'] = $value;
                        break;
                    case 'vaccinated hcp':
                    case 'criteria met count':
                    case 'core elements met count':
                    case 'num':
                        $record['num'] = $value;
                        break;
                    case 'total eligible hcp':
                    case 'criteria count':
                    case 'core elements count':
                    case 'denom':
                        $record['den'] = $value;
                        break;
                    case 'rate':
                        $record['rate'] = $value;
                        break;
                    case 'median minutes':
                        $record['median_minutes'] = $value;
                        break;
                }
            }
            if ( ! empty( $record ) ) {
                $rows[] = $record;
            }
        }
        fclose( $fh );
        return $rows;
    };

    $dm_collect = function ( $measure ) use ( $dm_folder_files, $dm_extract_assessment_year, $dm_extract_raw_rows, &$initial_saved_measures, &$initial_files_by_measure ) {
        // Two-pass match: prefer the explicit `measure` field saved on the
        // record, fall back to a filename-prefix match for legacy records
        // that don't have it. The prefix path uses `sanitize_file_name` the
        // same way the save handler does, so casing/whitespace lines up.
        $prefix = strtolower( sanitize_file_name( $measure ) );
        $measure_normalized = strtolower( trim( $measure ) );
        $matched = array();
        foreach ( $dm_folder_files as $dm_files ) {
            if ( ! is_array( $dm_files ) ) { continue; }
            foreach ( $dm_files as $dm_file ) {
                if ( ! is_array( $dm_file ) ) { continue; }
                $name = (string) ( $dm_file['name'] ?? '' );
                if ( $name === '' ) { continue; }

                $record_measure = isset( $dm_file['measure'] ) ? strtolower( trim( (string) $dm_file['measure'] ) ) : '';
                $is_match = false;
                if ( $record_measure !== '' ) {
                    $is_match = ( $record_measure === $measure_normalized );
                } elseif ( $prefix !== '' ) {
                    $is_match = ( strpos( strtolower( $name ), $prefix . '_' ) === 0 );
                }
                if ( ! $is_match ) { continue; }

                $is_generated_manual = ( $prefix !== '' && strpos( strtolower( $name ), $prefix . '_' ) === 0 );
                $source = (string) ( $dm_file['source'] ?? '' );
                if ( $source === '' ) {
                    $source = $is_generated_manual ? 'manual' : 'upload';
                }
                $assessment_year = (string) ( $dm_file['assessment_year'] ?? '' );
                if ( $assessment_year === '' && $source === 'manual' ) {
                    $assessment_year = $dm_extract_assessment_year( $name );
                }
                $template_type = (string) ( $dm_file['template_type'] ?? '' );
                $raw_rows = ( ! empty( $dm_file['raw_rows'] ) && is_array( $dm_file['raw_rows'] ) )
                    ? $dm_file['raw_rows']
                    : $dm_extract_raw_rows( $name );
                $is_legacy_checklist_measure = in_array(
                    $measure,
                    array( 'CAH Quality Infrastructure Assessment', 'Antibiotic Stewardship' ),
                    true
                );
                if ( in_array( $template_type, array( 'elements_checklist', 'antibiotic_stewardship' ), true ) || $is_legacy_checklist_measure ) {
                    $csv_raw_rows = $dm_extract_raw_rows( $name );
                    if ( ! empty( $csv_raw_rows ) ) {
                        $raw_rows = $csv_raw_rows;
                    }
                }

                $matched[] = array(
                    'name'        => $name,
                    'url'         => (string) ( $dm_file['url'] ?? '' ),
                    'size_kb'     => (float) ( $dm_file['size_kb'] ?? 0 ),
                    'uploaded_at' => (string) ( $dm_file['uploaded_at'] ?? '' ),
                    'source'      => $source,
                    'archived'    => ! empty( $dm_file['archived'] ),
                    'archived_at' => (string) ( $dm_file['archived_at'] ?? '' ),
                    'template_type' => $template_type,
                    'assessment_year' => $assessment_year,
                    'assessment_years' => ( ! empty( $dm_file['assessment_years'] ) && is_array( $dm_file['assessment_years'] ) ) ? array_values( array_map( 'strval', $dm_file['assessment_years'] ) ) : array(),
                    'assessment_year_range' => (string) ( $dm_file['assessment_year_range'] ?? '' ),
                    'assessment_year_label' => (string) ( $dm_file['assessment_year_label'] ?? '' ),
                    'assessment_periods' => ( ! empty( $dm_file['assessment_periods'] ) && is_array( $dm_file['assessment_periods'] ) ) ? array_values( $dm_file['assessment_periods'] ) : array(),
                    'assessment_period_label' => (string) ( $dm_file['assessment_period_label'] ?? '' ),
                    'is_bulk_upload' => ! empty( $dm_file['is_bulk_upload'] ),
                    'assessment_month' => (string) ( $dm_file['assessment_month'] ?? '' ),
                    'drive_file_id' => (string) ( $dm_file['drive_file_id'] ?? '' ),
                    'drive_sync_status' => (string) ( $dm_file['drive_sync_status'] ?? '' ),
                    'drive_error' => (string) ( $dm_file['drive_error'] ?? '' ),
                    'raw_rows' => $raw_rows,
                );
            }
        }
        if ( ! empty( $matched ) ) {
            // Newest first.
            usort( $matched, function ( $a, $b ) {
                return strcmp( $b['uploaded_at'], $a['uploaded_at'] );
            } );
            $initial_saved_measures[ $measure ]   = count( array_filter( $matched, function ( $file ) {
                return empty( $file['archived'] );
            } ) );
            $initial_files_by_measure[ $measure ] = $matched;
        }
    };

    foreach ( $folders as $dm_folder ) {
        foreach ( (array) ( $dm_folder['measures'] ?? array() ) as $dm_measure ) {
            $dm_collect( $dm_measure );
        }
        foreach ( (array) ( $dm_folder['subfolders'] ?? array() ) as $dm_sub ) {
            foreach ( (array) ( $dm_sub['measures'] ?? array() ) as $dm_measure ) {
                $dm_collect( $dm_measure );
            }
        }
    }
}
$dm_current_user = wp_get_current_user();
$dm_current_user_display_name = '';
if ( $dm_current_user && $dm_current_user->exists() ) {
    $dm_current_user_display_name = trim( (string) $dm_current_user->display_name );
    if ( '' === $dm_current_user_display_name ) {
        $dm_current_user_display_name = trim( (string) $dm_current_user->user_login );
    }
}
$dm_ownership_users = array();
if ( is_user_logged_in() && function_exists( 'qualinav_data_hub_mbqip_same_org_users' ) ) {
    $dm_ownership_users = qualinav_data_hub_mbqip_same_org_users( (int) ( $dm_user_org_context['organization_id'] ?? 0 ) );
}
if ( empty( $dm_ownership_users ) && $dm_current_user && $dm_current_user->exists() ) {
    $dm_ownership_users[] = array(
        'id'    => (int) $dm_current_user->ID,
        'label' => $dm_current_user_display_name,
        'email' => (string) $dm_current_user->user_email,
    );
}
$dm_measure_coverage = array(
    'saved'      => false,
    'mbqip'      => array(),
    'hacs_hais'  => array(),
    'updated_at' => '',
    'updated_by' => 0,
);
if ( is_user_logged_in() && function_exists( 'qualinav_data_hub_get_measure_coverage' ) ) {
    $dm_measure_coverage = qualinav_data_hub_get_measure_coverage( $dm_user_org_context );
}
$dm_brand_logo_url = defined( 'QUALINAV_MY_ORG_PLUGIN_URL' )
    ? QUALINAV_MY_ORG_PLUGIN_URL . 'assets/images/qualinav-export-logo.png?v=' . rawurlencode( defined( 'QUALINAV_MY_ORG_VERSION' ) ? QUALINAV_MY_ORG_VERSION : '1' )
    : '';
$dm_brand_logo_base64 = '';
$dm_export_logo_path = defined( 'QUALINAV_MY_ORG_PLUGIN_DIR' )
    ? QUALINAV_MY_ORG_PLUGIN_DIR . 'assets/images/qualinav-export-logo.png'
    : '';
if ( $dm_export_logo_path && is_readable( $dm_export_logo_path ) ) {
    $dm_brand_logo_base64 = base64_encode( file_get_contents( $dm_export_logo_path ) );
}
?>
<script>
    const DM_DATA = <?php echo json_encode($folders); ?>;
    const DM_CONFIG = {
        ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('dm_save_nonce'); ?>',
        initialSavedMeasures: <?php echo wp_json_encode( $initial_saved_measures ); ?>,
        initialFilesByMeasure: <?php echo wp_json_encode( $initial_files_by_measure ); ?>,
        userOrgContext: <?php echo wp_json_encode( $dm_user_org_context ); ?>,
        ownershipUsers: <?php echo wp_json_encode( $dm_ownership_users ); ?>,
        measureCoverage: <?php echo wp_json_encode( $dm_measure_coverage ); ?>,
        currentUser: <?php echo wp_json_encode( array(
            'id' => get_current_user_id(),
            'displayName' => $dm_current_user_display_name,
        ) ); ?>,
        brandLogoUrl: <?php echo wp_json_encode( $dm_brand_logo_url ); ?>,
        brandLogoBase64: <?php echo wp_json_encode( $dm_brand_logo_base64 ); ?>
    };
    const DM_GLOBAL_INFRASTRUCTURE_MEASURE = 'CAH Quality Infrastructure Assessment';
    const DM_GLOBAL_INFRASTRUCTURE_COMPONENTS = [
        'Leadership Responsibility & Accountability',
        'Quality Embedded within the Organization’s Strategic Plan',
        'Workforce Engagement & Ownership',
        'Culture of Continuous Improvement through Behavior',
        'Culture of Continuous Improvement through Systems',
        'Engagement of Patients, Partners, and Community',
        'Collecting Meaningful and Accurate Data',
        'Using Data to Improve Quality'
    ];
    const DM_ANTIBIOTIC_STEWARDSHIP_MEASURE = 'Antibiotic Stewardship';
    const DM_ANTIBIOTIC_STEWARDSHIP_COMPONENTS = [
        'Leadership',
        'Accountability',
        'Drug Expertise',
        'Action',
        'Tracking',
        'Reporting',
        'Education'
    ];
    const DM_SAFE_USE_OPIOIDS_MEASURE = 'Safe Use of Opioids eCQM — MBQIP Submission';
    const DM_HWR_MEASURE = 'Hybrid Hospital-Wide Readmission (HWR)';
    const DM_EDTC_MEASURE = 'EDTC — Emergency Department Transfer Communication';
    const DM_EDTC_COMPONENTS = [
        'Home Medications',
        'Allergies and/or Reactions',
        'Medications Administered in ED',
        'ED Provider Note',
        'Mental Status/Orientation Assessment',
        'Reason for Transfer and/or Plan of Care',
        'Tests and/or Procedures Performed',
        'Tests and/or Procedures Results'
    ];
    const DM_EDTC_COMPOSITE_LABEL = 'Composite Score (All elements documented)';
    const DM_EDTC_COMPOSITE_KEY = 'composite';
    const DM_OP18_MEASURE = 'OP-18 — Median ED Arrival to Departure Time (Discharged Patients)';
    const DM_OP22_MEASURE = 'OP-22 — Patient Left Without Being Seen (LWBS) Rate';
    const DM_IMPROVEMENT_CALCULATOR_MEASURE = 'HACs & HAIs';
    const DM_IMPROVEMENT_CALCULATOR_MEASURES = [
        {
            id: 'c_diff',
            label: 'C. Diff',
            rateLabel: 'C. Diff Rate (%)',
            eventLabel: 'C. Diff Events',
            denominatorKey: 'inpatient_days',
            denominatorLabel: 'Total Inpatient Days',
            rateMultiplier: 1,
            rateUnit: '%',
            referenceKey: 'clostridioides_difficile'
        },
        {
            id: 'mrsa',
            label: 'MRSA',
            rateLabel: 'MRSA Rate (%)',
            eventLabel: 'MRSA Events',
            denominatorKey: 'inpatient_days',
            denominatorLabel: 'Total Inpatient Days',
            rateMultiplier: 1,
            rateUnit: '%',
            referenceKey: 'all_other_hacs'
        },
        {
            id: 'cauti',
            label: 'CAUTI',
            rateLabel: 'CAUTI Rate (%)',
            eventLabel: 'CAUTI Events',
            denominatorKey: 'catheter_days',
            denominatorLabel: 'Total Catheter Days',
            rateMultiplier: 1,
            rateUnit: '%',
            referenceKey: 'cauti'
        },
        {
            id: 'clabsi',
            label: 'CLABSI',
            rateLabel: 'CLABSI Rate (%)',
            eventLabel: 'CLABSI Events',
            denominatorKey: 'central_line_days',
            denominatorLabel: 'Total Central Line Days',
            rateMultiplier: 1,
            rateUnit: '%',
            referenceKey: 'clabsi'
        },
        {
            id: 'pressure_ulcers_3_plus',
            label: 'Pressure Ulcers 3+',
            rateLabel: 'Pressure Ulcers 3+ Rate (%)',
            eventLabel: 'Pressure Ulcer 3+ Events',
            denominatorKey: 'total_discharges',
            denominatorLabel: 'Total Discharges',
            rateMultiplier: 1,
            rateUnit: '%',
            referenceKey: 'pressure_ulcer'
        },
        {
            id: 'falls_with_injury',
            label: 'Inpatient Falls with Injury',
            rateLabel: 'Inpatient Falls with Injury Rate (%)',
            eventLabel: 'Inpatient Falls with Injury Events',
            denominatorKey: 'inpatient_days',
            denominatorLabel: 'Total Inpatient Days',
            rateMultiplier: 1,
            rateUnit: '%',
            referenceKey: 'all_other_hacs'
        },
        {
            id: 'sepsis_mortality',
            label: 'Sepsis Mortality',
            rateLabel: 'Sepsis Mortality Rate (%)',
            eventLabel: 'Sepsis Deaths',
            denominatorKey: 'sepsis_patients',
            denominatorLabel: 'Patients with Sepsis',
            rateMultiplier: 1,
            rateUnit: '%',
            referenceKey: 'sepsis_mortality'
        },
        {
            id: 'readmissions',
            label: 'Readmissions',
            rateLabel: 'Readmissions Rate (%)',
            eventLabel: 'Readmissions',
            denominatorKey: 'total_discharges',
            denominatorLabel: 'Total Discharges',
            rateMultiplier: 1,
            rateUnit: '%',
            referenceKey: 'readmissions'
        }
    ];
    const DM_IMPROVEMENT_CALCULATOR_DENOMINATORS = [
        { id: 'inpatient_days', label: 'Total Inpatient Days' },
        { id: 'catheter_days', label: 'Total Catheter Days' },
        { id: 'central_line_days', label: 'Total Central Line Days' },
        { id: 'sepsis_patients', label: 'Patients with Sepsis' },
        { id: 'total_discharges', label: 'Total Discharges' },
        { id: 'other_denominator', label: 'Other Denominator' }
    ];
    const DM_IMPROVEMENT_CALCULATOR_REFERENCES = {
        clabsi: { label: 'Central Line-Associated Bloodstream Infection (CLABSI)', costPerCase: 48108, mortalityRate: 0.09, excessMortalityRate: 0.15 },
        venous_thromboembolism: { label: 'Venous Thromboembolism (VTE) (post-surgery)', costPerCase: 17367, mortalityRate: 0.02, excessMortalityRate: 0.04 },
        pressure_ulcer: { label: 'Pressure Ulcer', costPerCase: 14506, mortalityRate: 0.02, excessMortalityRate: 0.04 },
        surgical_site_infection: { label: 'Surgical Site Infection (SSI)', costPerCase: 28219, mortalityRate: 0.01, excessMortalityRate: 0.03 },
        ventilator_associated_pneumonia: { label: 'Ventilator-Associated Pneumonia', costPerCase: 47238, mortalityRate: 0.30, excessMortalityRate: 0.14 },
        cauti: { label: 'Catheter-Associated Urinary Tract Infection (CAUTI)', costPerCase: 13793, mortalityRate: 0.07, excessMortalityRate: 0.04 },
        adverse_drug_event: { label: 'Adverse Drug Event', costPerCase: 5746, mortalityRate: 0.02, excessMortalityRate: 0.01 },
        clostridioides_difficile: { label: 'Clostridioides Difficile', costPerCase: 17260, mortalityRate: 0.07, excessMortalityRate: 0.04 },
        injury_from_fall: { label: 'Injury from Fall', costPerCase: 6694, mortalityRate: 0.02, excessMortalityRate: 0.05 },
        readmissions: { label: 'Readmissions', costPerCase: 15200, mortalityRate: null, excessMortalityRate: null },
        sepsis_mortality: { label: 'Sepsis Mortality', costPerCase: 32421, mortalityRate: 1, excessMortalityRate: 1 },
        all_other_hacs: { label: 'All Other HACs', costPerCase: 17000, mortalityRate: null, excessMortalityRate: null }
    };
    const DM_HCAHPS_MEASURES = [
        'HCAHPS — Composite 1: Communication with Nurses',
        'HCAHPS — Composite 2: Communication with Doctors',
        'HCAHPS — Composite 3: Restfulness of Hospital Environment',
        'HCAHPS — Composite 4: Responsiveness of Hospital Staff',
        'HCAHPS — Composite 5: Communication About Medicines',
        'HCAHPS — Composite 6: Discharge Information / Care Coordination',
        'HCAHPS — Composite 7: Transitions of Care',
        'HCAHPS — Q7: Cleanliness of Hospital Environment',
        'HCAHPS — Q20: Info About Symptoms to Watch For After Discharge',
        'HCAHPS — Q24: Overall Rating of Hospital (0-10)',
        'HCAHPS — Q5: Willingness to Recommend Hospital'
    ];
    const DM_HCAHPS_MEASURE_DETAILS = {
        'HCAHPS — Composite 1: Communication with Nurses': {
            description: `Measures patients' perceptions of how well nurses communicated with them, including how often nurses explained things clearly, listened carefully, and treated patients with courtesy and respect.`,
            numerator: `Number of respondents who answered "Always" to all three nurse communication questions.`,
            denominator: `All eligible adult inpatient discharges surveyed with completed HCAHPS surveys during the reporting period.`,
            exclusions: `Patients who died; patients discharged to hospice; court/law enforcement patients; patients under 18; no-publicity patients; patients with length of stay under one night.`,
            frequency: `Quarterly data collection; annual public reporting on Care Compare.`,
            benchmark: `National benchmark: 85.6%. National performance rate: 84.2%.`
        },
        'HCAHPS — Composite 2: Communication with Doctors': {
            description: `Measures patients' perceptions of physician communication quality, including whether doctors treated them respectfully, listened carefully, and explained things in understandable terms.`,
            numerator: `Number of respondents who answered "Always" to all three doctor communication questions.`,
            denominator: `All eligible adult inpatient discharges surveyed with completed HCAHPS surveys during the reporting period.`,
            exclusions: `Same as HCAHPS Composite 1 exclusions.`,
            frequency: `Quarterly; annual Care Compare reporting.`,
            benchmark: `National benchmark: 85.9%. National performance rate: 83.9%.`
        },
        'HCAHPS — Composite 3: Restfulness of Hospital Environment': {
            description: `Measures how often the patient's room and bathroom were kept clean and how often the hospital environment was quiet at night.`,
            numerator: `Number of respondents who answered "Always" to cleanliness and quiet-at-night questions.`,
            denominator: `All eligible adult inpatient discharges surveyed.`,
            exclusions: `Same as HCAHPS Composite 1 exclusions.`,
            frequency: `Quarterly; annual Care Compare reporting.`,
            benchmark: `National benchmark: 77.5%. National performance rate: 66.6%.`
        },
        'HCAHPS — Composite 4: Responsiveness of Hospital Staff': {
            description: `Measures patients' experience with how quickly hospital staff responded to calls for help and bathroom or bedpan needs.`,
            numerator: `Number of respondents who answered "Always" to staff responsiveness questions.`,
            denominator: `All eligible adult inpatient discharges surveyed.`,
            exclusions: `Same as HCAHPS Composite 1 exclusions.`,
            frequency: `Quarterly; annual Care Compare reporting.`,
            benchmark: `National benchmark: 77.2%. National performance rate: 74.8%.`
        },
        'HCAHPS — Composite 5: Communication About Medicines': {
            description: `Measures whether patients were told about new medications and their side effects before receiving them.`,
            numerator: `Number of respondents who answered "Always" to medicine communication questions.`,
            denominator: `All eligible adult inpatient discharges surveyed.`,
            exclusions: `Same as HCAHPS Composite 1 exclusions; patients who received no new medications during admission.`,
            frequency: `Quarterly; annual Care Compare reporting.`,
            benchmark: `National benchmark: 70.1%. National performance rate: 66.6%.`
        },
        'HCAHPS — Composite 6: Discharge Information / Care Coordination': {
            description: `Measures whether patients received written discharge instructions about symptoms to watch for and whether staff explained the purpose of prescribed medications.`,
            numerator: `Number of respondents who answered "Yes" to discharge information questions.`,
            denominator: `All eligible adult inpatient discharges surveyed.`,
            exclusions: `Same as HCAHPS Composite 1 exclusions.`,
            frequency: `Quarterly; annual Care Compare reporting.`,
            benchmark: `National benchmark: 91.1%. National performance rate: 89.1%.`
        },
        'HCAHPS — Composite 7: Transitions of Care': {
            description: `Measures patients' perceptions of care coordination at discharge, including whether they understood their care, had medication questions answered, and understood warning signs.`,
            numerator: `Number of respondents who answered "Strongly Agree" to all three care transitions items.`,
            denominator: `All eligible adult inpatient discharges surveyed.`,
            exclusions: `Same as HCAHPS Composite 1 exclusions.`,
            frequency: `Quarterly; annual Care Compare reporting.`,
            benchmark: `National benchmark: 60.9%. National performance rate: 56.5%.`
        },
        'HCAHPS — Q7: Cleanliness of Hospital Environment': {
            description: `Individual HCAHPS item measuring how often the patient's room and bathroom were kept clean.`,
            numerator: `Number of respondents who answered "Always" to the room and bathroom cleanliness question.`,
            denominator: `All eligible adult inpatient discharges surveyed.`,
            exclusions: `Same as HCAHPS Composite 1 exclusions.`,
            frequency: `Quarterly; annual Care Compare reporting.`,
            benchmark: `National benchmark: 77.5%. National performance rate: 80.2%.`
        },
        'HCAHPS — Q20: Info About Symptoms to Watch For After Discharge': {
            description: `Individual HCAHPS item measuring whether staff provided information about symptoms or health problems to watch for after leaving the hospital.`,
            numerator: `Number of respondents who answered "Yes" to receiving written information about symptoms or problems to watch for after discharge.`,
            denominator: `All eligible adult inpatient discharges surveyed.`,
            exclusions: `Same as HCAHPS Composite 1 exclusions.`,
            frequency: `Quarterly; annual Care Compare reporting.`,
            benchmark: `Benchmark and performance data were not provided in the workbook.`
        },
        'HCAHPS — Q24: Overall Rating of Hospital (0-10)': {
            description: `A single global rating item asking patients to rate the hospital overall on a 0-10 scale, where 0 is the worst possible hospital and 10 is the best possible hospital.`,
            numerator: `Number of respondents who answered 9 or 10 on the overall hospital rating scale.`,
            denominator: `All eligible adult inpatient discharges surveyed.`,
            exclusions: `Same as HCAHPS Composite 1 exclusions.`,
            frequency: `Quarterly; annual Care Compare reporting.`,
            benchmark: `National benchmark: 83.2%. National performance rate: 78.5%.`
        },
        'HCAHPS — Q5: Willingness to Recommend Hospital': {
            description: `Measures the percentage of patients who would definitely recommend the hospital to friends and family.`,
            numerator: `Number of respondents who answered "Definitely Yes" to recommending this hospital to friends and family.`,
            denominator: `All eligible adult inpatient discharges surveyed.`,
            exclusions: `Same as HCAHPS Composite 1 exclusions.`,
            frequency: `Quarterly; annual Care Compare reporting.`,
            benchmark: `National benchmark was not provided in the workbook. National performance rate: 75.6%.`
        }
    };
    
    const dmApp = {
        pendingWorkbookConflict: null,
        pendingCoverageNavigation: null,
        state: {
            view: 'unified-measures', // categories, measures, unified-measures, input
            currentCategory: null,
            currentSubfolder: null,
            currentMeasure: null,
            unifiedMeasuresMode: false,
            unifiedHacsMeasureId: '',
            selectedUnifiedMeasureValue: '',
            unifiedMeasurePickerOpen: false,
            unifiedMeasureSearch: '',
            measureCoverage: DM_CONFIG.measureCoverage || { saved: false, mbqip: [], hacs_hais: [] },
            measureCoverageDraft: null,
            measureCoverageStatus: '',
            measureCoverageSaving: false,
            inputTab: 'entry', // Default tab for measure input
            manualRows: [
                { month: '', year: '', num: '', den: '' /*, median: '' */ }
            ],
            checklistYear: String(new Date().getFullYear()),
            checklistQuarter: 'Q1',
            edtcCompositeNum: '',
            edtcCompositeDen: '',
            edtcReportSeries: DM_EDTC_COMPOSITE_KEY,
            checklistRows: [],
            // Snapshot of the rows from the last successful Save & Sync —
            // null until a save happens, gates the Download CSV button.
            lastSavedRows: null,
            uploadedFileName: '',
            uploadError: '',
            generalBulkStatus: '',
            assessmentListTab: 'saved',
            rawDataYearFilter: 'all',
            measureGoals: {},
            measureGoalHistory: {},
            measureGoalTab: 'current',
            improvementMeasureGoalTabs: {},
            measureGoalStatus: '',
            pendingImprovementOverwrite: null,
            reportOwnership: {},
            reportOwnershipUsers: Array.isArray(DM_CONFIG.ownershipUsers) ? DM_CONFIG.ownershipUsers : [],
            reportOwnershipStatus: '',
            reportOwnershipStatuses: {},
            generalOwnershipLoaded: false,
            generalOwnershipLoading: false,
            dataOwnershipTab: 'ownership',
            hacsHaisOwnershipLoaded: false,
            hacsHaisOwnershipLoading: false,
            improvementCalculator: {
                activeTab: 'data-entry',
                organizationName: (DM_CONFIG.userOrgContext && DM_CONFIG.userOrgContext.org_name) ? DM_CONFIG.userOrgContext.org_name : '',
	                referenceDate: '',
	                runChartMetric: 'c_diff',
                    reportYearFilter: 'all',
	                dataEntryMode: 'single',
	                singleEntry: {
	                    month: 'Jan',
	                    numeratorKey: 'c_diff',
	                    denominatorKey: 'inpatient_days',
	                    numeratorValue: '',
	                    denominatorValue: ''
	                },
	                otherMeasureName: 'Other',
	                monthlyRows: [],
	                saveStatus: '',
	                savedSubmissionId: null,
	                databaseTab: 'saved',
	                databaseStatus: '',
	                databaseLoaded: false,
	                submissions: [],
	                goals: {}
            },
            // Hydrated from the server so file counts persist across reloads.
            savedMeasures: Object.assign({}, DM_CONFIG.initialSavedMeasures || {}),
            // Map of measure label → array of { name, url, size_kb, uploaded_at }.
            filesByMeasure: Object.assign({}, DM_CONFIG.initialFilesByMeasure || {})
        },

        init() {
            if (!this.applyRouteFromUrl()) {
                this.render();
            }
            window.addEventListener('popstate', () => {
                if (!this.applyRouteFromUrl()) {
                    this.setState({
                        view: 'unified-measures',
                        currentCategory: null,
                        currentSubfolder: null,
                        currentMeasure: null,
                        unifiedMeasuresMode: false,
                        unifiedHacsMeasureId: '',
                        selectedUnifiedMeasureValue: '',
                        unifiedMeasurePickerOpen: false,
                        unifiedMeasureSearch: ''
                    }, { scrollToTop: false });
                }
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const fv = document.getElementById('dmFileViewer');
                    if (fv && fv.style.display !== 'none') this.closeFileViewer();
                    const pm = document.getElementById('dmPasteModal');
                    if (pm && pm.style.display !== 'none') this.closePasteModal();
                    const ipm = document.getElementById('dmImprovementPreviewModal');
                    if (ipm && ipm.style.display !== 'none') this.closeImprovementSubmissionPreview();
                    const gm = document.getElementById('dmGoalArchiveModal');
                    if (gm && gm.style.display !== 'none') this.closeGoalArchiveModal();
                    const wcm = document.getElementById('dmWorkbookConflictModal');
                    if (wcm && wcm.style.display !== 'none') this.closeWorkbookConflictModal(true);
                    const iom = document.getElementById('dmImprovementOverwriteModal');
                    if (iom && iom.style.display !== 'none') this.closeImprovementOverwriteModal(true);
                    const cum = document.getElementById('dmCoverageUnsavedModal');
                    if (cum && cum.style.display !== 'none') this.closeCoverageUnsavedModal();
                    this.closeGoalDatePicker();
                }
            });
            document.addEventListener('click', (e) => {
                const popover = document.getElementById('dmGoalDatePickerPopover');
                if (!popover || popover.style.display === 'none') return;
                const control = e.target && e.target.closest ? e.target.closest('.dm-goal-date-control') : null;
                if (popover.contains(e.target) || control) return;
                this.closeGoalDatePicker();
            });
            const overlay = document.getElementById('dmFileViewer');
            if (overlay) {
                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) this.closeFileViewer();
                });
            }
            const pasteOverlay = document.getElementById('dmPasteModal');
            if (pasteOverlay) {
                pasteOverlay.addEventListener('click', (e) => {
                    if (e.target === pasteOverlay) this.closePasteModal();
                });
            }
            const improvementPreviewOverlay = document.getElementById('dmImprovementPreviewModal');
            if (improvementPreviewOverlay) {
                improvementPreviewOverlay.addEventListener('click', (e) => {
                    if (e.target === improvementPreviewOverlay) this.closeImprovementSubmissionPreview();
                });
            }
            const goalArchiveOverlay = document.getElementById('dmGoalArchiveModal');
            if (goalArchiveOverlay) {
                goalArchiveOverlay.addEventListener('click', (e) => {
                    if (e.target === goalArchiveOverlay) this.closeGoalArchiveModal();
                });
            }
            const workbookConflictOverlay = document.getElementById('dmWorkbookConflictModal');
            if (workbookConflictOverlay) {
                workbookConflictOverlay.addEventListener('click', (e) => {
                    if (e.target === workbookConflictOverlay) this.closeWorkbookConflictModal(true);
                });
            }
            const improvementOverwriteOverlay = document.getElementById('dmImprovementOverwriteModal');
            if (improvementOverwriteOverlay) {
                improvementOverwriteOverlay.addEventListener('click', (e) => {
                    if (e.target === improvementOverwriteOverlay) this.closeImprovementOverwriteModal(true);
                });
            }
            const coverageUnsavedOverlay = document.getElementById('dmCoverageUnsavedModal');
            if (coverageUnsavedOverlay) {
                coverageUnsavedOverlay.addEventListener('click', (e) => {
                    if (e.target === coverageUnsavedOverlay) this.closeCoverageUnsavedModal();
                });
            }
            window.addEventListener('beforeunload', (e) => {
                if (!this.hasUnsavedMeasureCoverageChanges()) return;
                e.preventDefault();
                e.returnValue = '';
                return '';
            });
            const pasteArea = document.getElementById('dmPasteArea');
            if (pasteArea) {
                pasteArea.addEventListener('paste', (e) => this.handlePasteAreaPaste(e));
            }
        },

        setState(newState, options = {}) {
            this.state = { ...this.state, ...newState };
            this.render(options);
        },

        routeSlug(value) {
            return String(value || '')
                .trim()
                .toLowerCase()
                .replace(/&/g, ' and ')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        },

        categoryRouteSlug(cat) {
            if (!cat) return '';
            return cat.id === 'improvement-calculator' ? 'hacs-hais' : this.routeSlug(cat.id || cat.name);
        },

        subfolderRouteSlug(sub) {
            return this.routeSlug(sub ? (sub.id || sub.name) : '');
        },

        measureRouteSlug(measure) {
            return this.routeSlug(measure);
        },

        findCategoryByRoute(section) {
            const slug = this.routeSlug(section);
            if (!slug) return null;
            if (['measures', 'all-measures', 'unified-measures'].includes(slug)) {
                return { id: 'unified-measures', name: 'Quality Measures' };
            }
            if (['measure-management', 'data-ownership', 'ownership', 'measure-coverage', 'coverage'].includes(slug)) {
                return { id: 'measure-coverage', name: 'Measure Management' };
            }
            if (['general', 'bulk-upload'].includes(slug)) {
                return DM_DATA.find(cat => cat.id === 'general') || null;
            }
            if (['hacs-hais', 'hacs-and-hais', 'improvement-calculator'].includes(slug)) {
                return DM_DATA.find(cat => cat.id === 'improvement-calculator') || null;
            }
            return DM_DATA.find(cat => this.routeSlug(cat.id) === slug || this.routeSlug(cat.name) === slug) || null;
        },

        findSubfolderByRoute(cat, eventSlug) {
            if (!cat || !Array.isArray(cat.subfolders)) return null;
            const slug = this.routeSlug(eventSlug);
            if (!slug) return null;
            return cat.subfolders.find(sub => this.subfolderRouteSlug(sub) === slug || this.routeSlug(sub.name) === slug) || null;
        },

        findMeasureByRoute(cat, sub, measureSlug) {
            const slug = this.routeSlug(measureSlug);
            if (!cat || !slug) return '';
            const measures = sub
                ? (sub.measures || [])
                : [
                    ...(cat.measures || []),
                    ...((cat.subfolders || []).flatMap(item => item.measures || []))
                ];
            return measures.find(measure => this.measureRouteSlug(measure) === slug) || '';
        },

        findUnifiedOptionForLegacyRoute(cat, sub, measureSlug) {
            const slug = this.routeSlug(measureSlug);
            if (!cat || !slug) return null;
            const options = this.unifiedMeasureOptions();
            if (cat.id === 'improvement-calculator') {
                return options.find(item =>
                    item.program === 'hacs-hais'
                    && (this.routeSlug(item.id) === slug || this.routeSlug(item.label) === slug || this.routeSlug(item.measure) === slug)
                ) || null;
            }
            const measure = this.findMeasureByRoute(cat, sub, measureSlug);
            if (!measure) return null;
            return options.find(item =>
                item.program === 'mbqip'
                && item.catId === cat.id
                && (!sub || item.subId === sub.id)
                && item.measure === measure
            ) || null;
        },

        applyUnifiedMeasureRouteState(option, params = new URLSearchParams()) {
            const tabSlug = this.routeSlug(params.get('tab'));
            const listSlug = this.routeSlug(params.get('list'));
            if (option.program === 'hacs-hais') {
                const tabMap = {
                    instructions: 'instructions',
                    'measure-specifications': 'instructions',
                    'data-entry': 'instructions',
                    database: 'instructions',
                    'run-chart': 'run-chart'
                };
                const activeTab = tabMap[tabSlug] || 'instructions';
                this.setState({
                    improvementCalculator: {
                        ...this.improvementCalculatorState(),
                        activeTab,
                        databaseTab: ['archive', 'raw'].includes(listSlug) ? listSlug : 'saved'
                    }
                }, { scrollToTop: false });
                if (['instructions', 'run-chart'].includes(activeTab)) {
                    window.setTimeout(() => this.loadImprovementCalculatorDatabase({ silent: true }), 0);
                }
                if (activeTab === 'instructions') {
                    window.setTimeout(() => this.loadImprovementMeasureGoals(), 0);
                }
                return;
            }
            this.setState({
                inputTab: tabSlug === 'database' ? 'database' : 'entry',
                assessmentListTab: ['archive', 'raw'].includes(listSlug) ? listSlug : 'saved'
            }, { scrollToTop: false });
        },

        routeParamsForState() {
            const params = {};
            if (this.state.unifiedMeasuresMode || this.state.view === 'unified-measures') {
                params.section = 'measures';
                if (this.state.currentMeasure) {
                    params.measure = this.state.unifiedHacsMeasureId
                        ? this.routeSlug(this.state.unifiedHacsMeasureId)
                        : this.measureRouteSlug(this.state.currentMeasure);
                    if (this.state.currentCategory && this.state.currentCategory.id === 'improvement-calculator') {
                        const activeTab = (this.state.improvementCalculator || {}).activeTab;
                        const routeTab = activeTab === 'database' ? 'data-entry' : activeTab;
                        if (routeTab && routeTab !== 'instructions') {
                            params.tab = this.routeSlug(routeTab);
                        }
                    } else {
                        params.tab = this.state.inputTab === 'database' ? 'database' : 'data-entry';
                        if (this.state.inputTab === 'database' && this.state.assessmentListTab && this.state.assessmentListTab !== 'saved') {
                            params.list = this.routeSlug(this.state.assessmentListTab);
                        }
                    }
                }
                return params;
            }
            if (this.state.view === 'measure-coverage') {
                params.section = 'measure-management';
                return params;
            }
            const cat = this.state.currentCategory;
            if (!cat) return params;
            params.section = this.categoryRouteSlug(cat);
            if (this.state.currentSubfolder) {
                params.event = this.subfolderRouteSlug(this.state.currentSubfolder);
            }
            if (this.state.view === 'input' && this.state.currentMeasure) {
                params.measure = this.measureRouteSlug(this.state.currentMeasure);
            }
            if (cat.id === 'improvement-calculator') {
                const activeTab = (this.state.improvementCalculator || {}).activeTab;
                const routeTab = activeTab === 'database' ? 'data-entry' : activeTab;
                if (routeTab && routeTab !== 'instructions') {
                    params.tab = this.routeSlug(routeTab);
                }
                const databaseTab = (this.state.improvementCalculator || {}).databaseTab;
                if (routeTab === 'data-entry' && databaseTab && databaseTab !== 'saved') {
                    params.list = this.routeSlug(databaseTab);
                }
            } else if (this.state.view === 'input' && this.state.currentMeasure) {
                params.tab = this.state.inputTab === 'database' ? 'database' : 'data-entry';
                if (this.state.inputTab === 'database' && this.state.assessmentListTab && this.state.assessmentListTab !== 'saved') {
                    params.list = this.routeSlug(this.state.assessmentListTab);
                }
            }
            return params;
        },

        updateRouteUrl(options = {}) {
            if (this._applyingRoute || !window.history || !window.URL) return;
            const url = new URL(window.location.href);
            ['section', 'event', 'measure', 'tab', 'list'].forEach(key => url.searchParams.delete(key));
            const params = this.routeParamsForState();
            Object.entries(params).forEach(([key, value]) => {
                if (value) url.searchParams.set(key, value);
            });
            const next = `${url.pathname}${url.search}${url.hash}`;
            const current = `${window.location.pathname}${window.location.search}${window.location.hash}`;
            if (next === current) return;
            const method = options.replace ? 'replaceState' : 'pushState';
            window.history[method]({}, '', next);
        },

        applyRouteFromUrl() {
            if (!window.URLSearchParams) return false;
            const params = new URLSearchParams(window.location.search);
            const cat = this.findCategoryByRoute(params.get('section'));
            if (!cat) return false;

            this._applyingRoute = true;
            try {
                if (cat.id === 'unified-measures') {
                    const measureSlug = this.routeSlug(params.get('measure'));
                    if (measureSlug) {
                        const option = this.unifiedMeasureOptions().find(item =>
                            this.routeSlug(item.measure) === measureSlug || this.routeSlug(item.id) === measureSlug
                        );
                        if (option) {
                            this.navToUnifiedMeasure(option.value, { updateUrl: false, scrollToTop: false });
                            this.applyUnifiedMeasureRouteState(option, params);
                            return true;
                        }
                    }
                    this.navToUnifiedMeasuresPage({ updateUrl: false, scrollToTop: false });
                    return true;
                }
                if (cat.id === 'improvement-calculator') {
                    const option = this.findUnifiedOptionForLegacyRoute(cat, null, params.get('measure') || params.get('event'));
                    if (option) {
                        this.navToUnifiedMeasure(option.value, { updateUrl: false, scrollToTop: false });
                        this.applyUnifiedMeasureRouteState(option, params);
                        window.setTimeout(() => this.updateRouteUrl({ replace: true }), 0);
                    } else {
                        this.navToUnifiedMeasuresPage({ updateUrl: false, scrollToTop: false });
                        window.setTimeout(() => this.updateRouteUrl({ replace: true }), 0);
                    }
                    return true;
                }
                if (cat.id === 'measure-coverage') {
                    this.navToMeasureCoverage({ updateUrl: false, scrollToTop: false });
                    return true;
                }

                const sub = this.findSubfolderByRoute(cat, params.get('event'));
                const measure = this.findMeasureByRoute(cat, sub, params.get('measure'));
                if (cat.id === 'mbqip') {
                    const option = this.findUnifiedOptionForLegacyRoute(cat, sub, params.get('measure'));
                    if (option) {
                        this.navToUnifiedMeasure(option.value, { updateUrl: false, scrollToTop: false });
                        this.applyUnifiedMeasureRouteState(option, params);
                    } else {
                        this.navToUnifiedMeasuresPage({ updateUrl: false, scrollToTop: false });
                    }
                    window.setTimeout(() => this.updateRouteUrl({ replace: true }), 0);
                    return true;
                }
                if (measure) {
                    this.navToMeasure(cat.id, sub ? sub.id : null, measure, { updateUrl: false, scrollToTop: false });
                    const tabSlug = this.routeSlug(params.get('tab'));
                    const listSlug = this.routeSlug(params.get('list'));
                    this.setState({
                        inputTab: tabSlug === 'database' ? 'database' : 'entry',
                        assessmentListTab: ['archive', 'raw'].includes(listSlug) ? listSlug : 'saved'
                    }, { scrollToTop: false });
                    return true;
                }
                if (sub) {
                    this.navToSubfolder(cat.id, sub.id, { updateUrl: false, scrollToTop: false });
                    return true;
                }
                this.navToCategory(cat.id, { updateUrl: false, scrollToTop: false });
                return true;
            } finally {
                this._applyingRoute = false;
            }
        },

        // Tell the Dashboard Reports page that the user's metric data changed,
        // so it re-fetches and re-renders without a manual reload. Three paths
        // for resilience: same-tab hook, cross-tab BroadcastChannel, and a
        // localStorage fallback for browsers without BroadcastChannel.
        notifyMetricsChanged() {
            try {
                if (typeof window.QD_REFRESH_LIVE_METRICS === 'function') {
                    window.QD_REFRESH_LIVE_METRICS({ force: true });
                }
            } catch (e) { /* no-op */ }
            try {
                if (typeof BroadcastChannel !== 'undefined') {
                    if (!this._bc) { this._bc = new BroadcastChannel('qaqd_data_hub'); }
                    this._bc.postMessage({ type: 'metrics-changed', at: Date.now() });
                }
            } catch (e) { /* no-op */ }
            try {
                localStorage.setItem('qaqd_metrics_changed_at', String(Date.now()));
            } catch (e) { /* no-op */ }
        },

        render(options = {}) {
            const content = document.querySelector('.dm-content');
            const previousScrollTop = options.preserveScroll && content ? content.scrollTop : 0;
            const previousWindowScroll = options.preserveScroll ? window.scrollY : 0;
            this.renderSidebar();
            this.renderMain();
            this.scheduleRawDataRunChart();
            this.scheduleImprovementCalculatorRunChart();
            if (options.preserveScroll) {
                const updatedContent = document.querySelector('.dm-content');
                if (updatedContent) {
                    updatedContent.scrollTop = previousScrollTop;
                }
                window.scrollTo(0, previousWindowScroll);
                return;
            }
            if (options.scrollToTop !== false) {
                window.scrollTo(0, 0);
            }
        },

        renderSidebar() {
            const container = document.getElementById('dmSidebarItems');
            let html = '';
            const visibleSidebarCategories = DM_DATA.filter(cat => ['general'].includes(cat.id));
            const isUnifiedActive = this.state.view === 'unified-measures' || this.state.unifiedMeasuresMode;
            const isCoverageActive = this.state.view === 'measure-coverage';

            html += `
                <div class="dm-nav-item ${isUnifiedActive ? 'active' : ''}" onclick="dmApp.navToUnifiedMeasuresPage()">
                    <i class="fas fa-list-ul"></i>
                    <span>Quality Measures</span>
                </div>
            `;

            visibleSidebarCategories.forEach(cat => {
                const isActive = !isUnifiedActive && this.state.currentCategory && this.state.currentCategory.id === cat.id;
                html += `
                    <div class="dm-nav-item ${isActive ? 'active' : ''}" onclick="dmApp.navToCategory('${cat.id}')">
                        <i class="${cat.icon}"></i>
                        <span>${cat.name}</span>
                    </div>
                `;

                if (isActive && !['improvement-calculator', 'general'].includes(cat.id) && (cat.measures || cat.subfolders)) {
                    html += '<div class="dm-sub-nav">';
                    if (cat.subfolders) {
                        cat.subfolders.forEach(sub => {
                            if (cat.id === 'mbqip' && !(sub.measures || []).some(measure => this.measureCoverageAllows('mbqip', measure))) {
                                return;
                            }
                            const isSubActive = this.state.currentSubfolder && this.state.currentSubfolder.id === sub.id;
                            html += `
                                <div class="dm-sub-item ${isSubActive ? 'active' : ''}" onclick="dmApp.navToSubfolder('${cat.id}', '${sub.id}')">
                                    ${sub.name}
                                </div>
                            `;
                        });
                    } else if (Array.isArray(cat.measures)) {
                        cat.measures.forEach(m => {
                            const isMActive = this.state.currentMeasure === m;
                            html += `
                                <div class="dm-sub-item ${isMActive ? 'active' : ''}" onclick="dmApp.navToMeasure('${cat.id}', null, '${m.replace(/'/g, "\\'")}')">
                                    ${m}
                                </div>
                            `;
                        });
                    }
                    html += '</div>';
                }

            });

            html += `
                <div class="dm-nav-item ${isCoverageActive ? 'active' : ''}" onclick="dmApp.navToMeasureCoverage()">
                    <i class="fas fa-user-check"></i>
                    <span>Measure Management</span>
                </div>
            `;

            container.innerHTML = html;
        },

        renderMain() {
            const container = document.getElementById('dmViewContainer');
            
            if (this.state.view === 'categories') {
                container.innerHTML = this.renderCategoriesView();
            } else if (this.state.view === 'measures') {
                container.innerHTML = this.renderMeasuresView();
            } else if (this.state.view === 'unified-measures') {
                container.innerHTML = this.renderUnifiedMeasuresView();
                if (this.state.currentMeasure) {
                    this.attachInputListeners();
                }
            } else if (this.state.view === 'measure-coverage') {
                container.innerHTML = this.renderMeasureCoverageView();
            } else if (this.state.view === 'input') {
                container.innerHTML = this.renderInputView();
                this.attachInputListeners();
            }
        },

        pluralizeCount(count, singular, plural = null) {
            const normalizedCount = Number(count) || 0;
            return `${normalizedCount} ${normalizedCount === 1 ? singular : (plural || singular + 's')}`;
        },

        measureCountLabel(count) {
            return this.pluralizeCount(count, 'Measure');
        },

        formatPercent(value, decimals = 1) {
            const number = Number(value);
            if (!Number.isFinite(number)) return 'N/A';
            return `${number.toFixed(decimals)}%`;
        },

        formatCurrency(value) {
            const number = Number(value);
            if (!Number.isFinite(number)) return 'N/A';
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
                maximumFractionDigits: 0
            }).format(number);
        },

        escapeHtml(value) {
            return String(value == null ? '' : value).replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char]));
        },

        escapeJsString(value) {
            return String(value == null ? '' : value)
                .replace(/\\/g, '\\\\')
                .replace(/'/g, "\\'")
                .replace(/\r?\n/g, '\\n');
        },

        allMbqipCoverageKeys() {
            const mbqipCategory = DM_DATA.find(cat => cat.id === 'mbqip');
            if (!mbqipCategory || !Array.isArray(mbqipCategory.subfolders)) return [];
            return mbqipCategory.subfolders.flatMap(subfolder => subfolder.measures || []);
        },

        allHacsHaisCoverageKeys() {
            return DM_IMPROVEMENT_CALCULATOR_MEASURES.map(measure => measure.id);
        },

        normalizeMeasureCoverage(coverage = {}) {
            const uniqueStrings = (values) => Array.from(new Set((Array.isArray(values) ? values : [])
                .map(value => String(value || '').trim())
                .filter(Boolean)));
            return {
                saved: !!coverage.saved,
                mbqip: uniqueStrings(coverage.mbqip),
                hacs_hais: uniqueStrings(coverage.hacs_hais),
                updated_at: String(coverage.updated_at || ''),
                updated_by: Number(coverage.updated_by || 0)
            };
        },

        defaultMeasureCoverageSelection() {
            return {
                saved: false,
                mbqip: this.allMbqipCoverageKeys(),
                hacs_hais: this.allHacsHaisCoverageKeys(),
                updated_at: '',
                updated_by: 0
            };
        },

        activeMeasureCoverage() {
            const coverage = this.normalizeMeasureCoverage(this.state.measureCoverage);
            return coverage.saved ? coverage : this.defaultMeasureCoverageSelection();
        },

        measureCoverageDraft() {
            if (this.state.measureCoverageDraft) {
                return this.normalizeMeasureCoverage(this.state.measureCoverageDraft);
            }
            return this.activeMeasureCoverage();
        },

        measureCoverageAllows(program, key) {
            const savedCoverage = this.normalizeMeasureCoverage(this.state.measureCoverage);
            if (!savedCoverage.saved) return true;
            const normalizedKey = String(key || '').trim();
            if (!normalizedKey) return false;
            if (program === 'mbqip') {
                return savedCoverage.mbqip.includes(normalizedKey);
            }
            if (program === 'hacs-hais') {
                return savedCoverage.hacs_hais.includes(normalizedKey);
            }
            return true;
        },

        measureCoverageSignature(coverage) {
            const normalized = this.normalizeMeasureCoverage(coverage);
            const mbqip = [...(normalized.mbqip || [])].map(String).sort();
            const hacsHais = [...(normalized.hacs_hais || [])].map(String).sort();
            return JSON.stringify({
                saved: !!normalized.saved,
                mbqip,
                hacs_hais: hacsHais
            });
        },

        hasUnsavedMeasureCoverageChanges() {
            if (!this.state.measureCoverageDraft) return false;
            return this.measureCoverageSignature(this.state.measureCoverageDraft) !== this.measureCoverageSignature(this.activeMeasureCoverage());
        },

        guardUnsavedMeasureCoverage(action, options = {}) {
            if (options.skipCoverageGuard || !this.hasUnsavedMeasureCoverageChanges()) {
                return false;
            }
            this.openCoverageUnsavedModal(action);
            return true;
        },

        openCoverageUnsavedModal(action) {
            this.pendingCoverageNavigation = typeof action === 'function' ? action : null;
            const modal = document.getElementById('dmCoverageUnsavedModal');
            const status = document.getElementById('dmCoverageUnsavedStatus');
            if (status) status.textContent = '';
            if (modal) modal.style.display = 'flex';
        },

        closeCoverageUnsavedModal() {
            this.pendingCoverageNavigation = null;
            const modal = document.getElementById('dmCoverageUnsavedModal');
            if (modal) modal.style.display = 'none';
        },

        discardCoverageChangesAndContinue() {
            const action = this.pendingCoverageNavigation;
            this.pendingCoverageNavigation = null;
            const modal = document.getElementById('dmCoverageUnsavedModal');
            if (modal) modal.style.display = 'none';
            this.setState({
                measureCoverageDraft: this.activeMeasureCoverage(),
                measureCoverageStatus: ''
            }, { preserveScroll: true, scrollToTop: false });
            if (typeof action === 'function') {
                window.setTimeout(action, 0);
            }
        },

        saveCoverageChangesAndContinue() {
            const action = this.pendingCoverageNavigation;
            const status = document.getElementById('dmCoverageUnsavedStatus');
            const saveBtn = document.getElementById('dmCoverageUnsavedSave');
            if (status) status.textContent = 'Saving measure changes...';
            if (saveBtn) saveBtn.disabled = true;
            this.saveMeasureCoverage(null)
                .then(saved => {
                    if (!saved) {
                        if (status) status.textContent = 'Could not save measure changes. Please try again.';
                        return;
                    }
                    this.pendingCoverageNavigation = null;
                    const modal = document.getElementById('dmCoverageUnsavedModal');
                    if (modal) modal.style.display = 'none';
                    if (typeof action === 'function') {
                        window.setTimeout(action, 0);
                    }
                })
                .finally(() => {
                    if (saveBtn) saveBtn.disabled = false;
                });
        },

        setMeasureCoverageDraft(program, key, checked) {
            const draft = this.measureCoverageDraft();
            const listKey = program === 'mbqip' ? 'mbqip' : 'hacs_hais';
            const normalizedKey = String(key || '').trim();
            if (!normalizedKey) return;
            const nextList = new Set(draft[listKey] || []);
            if (checked) {
                nextList.add(normalizedKey);
            } else {
                nextList.delete(normalizedKey);
            }
            this.setState({
                measureCoverageDraft: {
                    ...draft,
                    saved: true,
                    [listKey]: Array.from(nextList)
                },
                measureCoverageStatus: ''
            }, { preserveScroll: true, scrollToTop: false });
        },

        setMeasureCoverageGroup(program, keys, checked) {
            const draft = this.measureCoverageDraft();
            const listKey = program === 'mbqip' ? 'mbqip' : 'hacs_hais';
            const nextList = new Set(draft[listKey] || []);
            (keys || []).forEach(key => {
                const normalizedKey = String(key || '').trim();
                if (!normalizedKey) return;
                if (checked) {
                    nextList.add(normalizedKey);
                } else {
                    nextList.delete(normalizedKey);
                }
            });
            this.setState({
                measureCoverageDraft: {
                    ...draft,
                    saved: true,
                    [listKey]: Array.from(nextList)
                },
                measureCoverageStatus: ''
            }, { preserveScroll: true, scrollToTop: false });
        },

        setDataOwnershipTab(tab) {
            const nextTab = tab === 'measures' ? 'measures' : 'ownership';
            if (this.state.dataOwnershipTab === 'measures' && nextTab !== 'measures' && this.guardUnsavedMeasureCoverage(() => this.setDataOwnershipTab(nextTab), {})) {
                return;
            }
            this.setState({
                dataOwnershipTab: nextTab
            }, { preserveScroll: true, scrollToTop: false });
        },

        saveMeasureCoverage(event) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            const draft = this.normalizeMeasureCoverage(this.measureCoverageDraft());
            this.setState({ measureCoverageSaving: true, measureCoverageStatus: 'Saving measure coverage...' }, { preserveScroll: true, scrollToTop: false });
            const formData = new FormData();
            formData.append('action', 'qualinav_measure_coverage_save');
            formData.append('nonce', DM_CONFIG.nonce);
            formData.append('mbqip', JSON.stringify(draft.mbqip));
            formData.append('hacs_hais', JSON.stringify(draft.hacs_hais));
            return fetch(DM_CONFIG.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (!data || !data.success) {
                        throw new Error((data && data.data) || 'Could not save measure coverage.');
                    }
                    const coverage = this.normalizeMeasureCoverage((data.data && data.data.coverage) || draft);
                    this.setState({
                        measureCoverage: coverage,
                        measureCoverageDraft: coverage,
                        measureCoverageSaving: false,
                        measureCoverageStatus: (data.data && data.data.message) || 'Measure coverage saved.'
                    }, { preserveScroll: true, scrollToTop: false });
                    return true;
                })
                .catch(error => {
                    this.setState({
                        measureCoverageSaving: false,
                        measureCoverageStatus: 'Could not save measure coverage: ' + error.message
                    }, { preserveScroll: true, scrollToTop: false });
                    return false;
                });
        },

        renderCategoriesView() {
            return `
                <div class="dm-header">
                    <h1>${this.escapeHtml(this.organizationDataTitle())}</h1>
                    <p>Select a category to manage quality and performance metrics.</p>
                </div>
                <div class="dm-grid">
                    <div class="dm-card" onclick="dmApp.navToUnifiedMeasuresPage()">
                        <i class="fas fa-list-ul"></i>
                        <div>
                            <h2>Quality Measures</h2>
                            <p>Search and open any MBQIP or HACs & HAIs measure from one place.</p>
                        </div>
                        <span class="dm-badge">${this.measureCountLabel(this.unifiedMeasureOptions().length)}</span>
                    </div>
                    ${DM_DATA.filter(cat => ['general'].includes(cat.id)).map(cat => {
                        const count = cat.subfolders ? cat.subfolders.length : (cat.measures || []).length;
                        const label = cat.subfolders ? this.pluralizeCount(count, 'Folder') : this.measureCountLabel(count);
                        return `
                            <div class="dm-card" onclick="dmApp.navToCategory('${cat.id}')">
                                <i class="${cat.icon}"></i>
                                <div>
                                    <h2>${cat.name}</h2>
                                    <p>${cat.desc}</p>
                                </div>
                                <span class="dm-badge">${label}</span>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        },

        unifiedMeasureOptions() {
            const mbqip = [];
            const mbqipCategory = DM_DATA.find(cat => cat.id === 'mbqip');
            if (mbqipCategory && Array.isArray(mbqipCategory.subfolders)) {
                mbqipCategory.subfolders.forEach(subfolder => {
                    (subfolder.measures || []).forEach(measure => {
                        if (!this.measureCoverageAllows('mbqip', measure)) return;
                        mbqip.push({
                            id: this.measureRouteSlug(measure),
                            value: `mbqip::${this.subfolderRouteSlug(subfolder)}::${this.measureRouteSlug(measure)}`,
                            program: 'mbqip',
                            group: `MBQIP - ${subfolder.name}`,
                            measure,
                            label: measure,
                            catId: 'mbqip',
                            subId: subfolder.id
                        });
                    });
                });
            }
            const hacs = DM_IMPROVEMENT_CALCULATOR_MEASURES
                .filter(measure => this.measureCoverageAllows('hacs-hais', measure.id))
                .map(measure => ({
                    id: measure.id,
                    value: `hacs-hais::${measure.id}`,
                    program: 'hacs-hais',
                    group: 'HACs & HAIs',
                    measure: measure.id,
                    label: measure.label,
                    catId: 'improvement-calculator',
                    subId: null
                }));
            return [...mbqip, ...hacs];
        },

        focusUnifiedMeasureSearch(selectText = false) {
            window.setTimeout(() => {
                const input = document.getElementById('dmUnifiedMeasureSearch');
                if (input) {
                    input.focus();
                    if (selectText) {
                        input.select();
                    } else if (typeof input.setSelectionRange === 'function') {
                        const end = String(input.value || '').length;
                        input.setSelectionRange(end, end);
                    }
                }
            }, 0);
        },

        toggleUnifiedMeasurePicker(open = null) {
            const shouldOpen = open === null ? !this.state.unifiedMeasurePickerOpen : !!open;
            this.setState({
                unifiedMeasurePickerOpen: shouldOpen
            }, { preserveScroll: true, scrollToTop: false });
            if (shouldOpen) {
                this.focusUnifiedMeasureSearch(true);
            }
        },

        closeUnifiedMeasurePicker() {
            if (!this.state.unifiedMeasurePickerOpen) return;
            this.setState({
                unifiedMeasurePickerOpen: false,
                unifiedMeasureSearch: ''
            }, { preserveScroll: true, scrollToTop: false });
        },

        updateUnifiedMeasureSearch(value) {
            this.setState({
                unifiedMeasurePickerOpen: true,
                unifiedMeasureSearch: String(value || '')
            }, { preserveScroll: true, scrollToTop: false });
            this.focusUnifiedMeasureSearch();
        },

        selectUnifiedMeasureFromPicker(value) {
            this.state = {
                ...this.state,
                unifiedMeasurePickerOpen: false,
                unifiedMeasureSearch: ''
            };
            this.navToUnifiedMeasure(value, { preserveScroll: true, scrollToTop: false });
        },

        renderUnifiedMeasuresView() {
            const options = this.unifiedMeasureOptions();
            const search = String(this.state.unifiedMeasureSearch || '').trim().toLowerCase();
            const filteredOptions = search
                ? options.filter(option => `${option.group} ${option.label}`.toLowerCase().includes(search))
                : options;
            const filteredGrouped = filteredOptions.reduce((groups, option) => {
                if (!groups[option.group]) groups[option.group] = [];
                groups[option.group].push(option);
                return groups;
            }, {});
            const capitalizeMeasureLabel = (label) => {
                const text = String(label || '').trim();
                return text ? text.charAt(0).toUpperCase() + text.slice(1) : '';
            };
            const selectedOption = options.find(option => option.value === this.state.selectedUnifiedMeasureValue);
            const pickerLabel = selectedOption ? capitalizeMeasureLabel(selectedOption.label) : 'Select a Measure...';
            const optionHtml = Object.entries(filteredGrouped).map(([group, items]) => `
                <div class="dm-unified-measure-group">${this.escapeHtml(group)}</div>
                ${items.map(item => `
                    <button
                        type="button"
                        class="dm-unified-measure-option ${this.state.selectedUnifiedMeasureValue === item.value ? 'active' : ''}"
                        onmousedown="event.preventDefault()"
                        onclick="dmApp.selectUnifiedMeasureFromPicker('${this.escapeJsString(item.value)}')"
                    >${this.escapeHtml(capitalizeMeasureLabel(item.label))}</button>
                `).join('')}
            `).join('') || '<div class="dm-unified-measure-empty">No measures found.</div>';
            const selectedMeasureContent = this.state.currentMeasure
                ? `<div class="dm-unified-measure-content" style="margin-top:34px;">${this.renderInputView()}</div>`
                : '';

            return `
                <div class="dm-breadcrumb">
                    <span onclick="dmApp.navToRoot()">${this.escapeHtml(this.organizationDataTitle())}</span>
                    <i class="fas fa-chevron-right" style="font-size:10px;"></i>
                    <b>Quality Measures</b>
                </div>
                <div class="dm-header">
                    <h1>Quality Measures</h1>
                </div>
                <section class="dm-general-manual-card" style="max-width:920px;">
                    <h2>Choose a Measure</h2>
                    <p>Open the dropdown to find the measure you want to work on.</p>
                    <div class="dm-general-manual-controls" style="grid-template-columns:minmax(280px, 1fr); align-items:end;">
                        <div class="dm-general-manual-field">
                            <label id="dmUnifiedMeasurePickerLabel">Measure</label>
                            <div class="dm-unified-measure-picker">
                                <button
                                    type="button"
                                    class="dm-unified-measure-trigger"
                                    aria-haspopup="listbox"
                                    aria-expanded="${this.state.unifiedMeasurePickerOpen ? 'true' : 'false'}"
                                    aria-labelledby="dmUnifiedMeasurePickerLabel"
                                    onclick="dmApp.toggleUnifiedMeasurePicker()"
                                >
                                    <span>${this.escapeHtml(pickerLabel)}</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                                ${this.state.unifiedMeasurePickerOpen ? `
                                    <div class="dm-unified-measure-panel">
                                        <input
                                            id="dmUnifiedMeasureSearch"
                                            class="dm-unified-measure-search"
                                            type="text"
                                            value="${this.escapeHtml(this.state.unifiedMeasureSearch || '')}"
                                            placeholder="Search Measures"
                                            oninput="dmApp.updateUnifiedMeasureSearch(this.value)"
                                            onkeydown="if(event.key === 'Escape'){ dmApp.closeUnifiedMeasurePicker(); }"
                                            autofocus
                                        >
                                        <div class="dm-unified-measure-list" role="listbox" aria-labelledby="dmUnifiedMeasurePickerLabel">
                                            ${optionHtml}
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </section>
                ${selectedMeasureContent}
            `;
        },

        renderMeasureCoverageView() {
            if (!this.state.generalOwnershipLoaded && !this.state.generalOwnershipLoading) {
                window.setTimeout(() => this.loadGeneralReportOwnership(), 0);
            }
            const coverage = this.measureCoverageDraft();
            const savedCoverage = this.normalizeMeasureCoverage(this.state.measureCoverage);
            const mbqipCategory = DM_DATA.find(cat => cat.id === 'mbqip');
            const mbqipGroups = mbqipCategory && Array.isArray(mbqipCategory.subfolders) ? mbqipCategory.subfolders : [];
            const checked = (program, key) => {
                const list = program === 'mbqip' ? coverage.mbqip : coverage.hacs_hais;
                return list.includes(String(key || '').trim());
            };
            const statusClass = this.state.measureCoverageStatus && this.state.measureCoverageStatus.toLowerCase().includes('could not') ? 'error' : (this.state.measureCoverageStatus ? 'success' : '');
            const coverageNote = savedCoverage.saved
                ? 'Only selected measures are available in Quality Measures and Universal Workbook for this organization.'
                : 'No coverage has been saved yet. All measures are currently available in Quality Measures and Universal Workbook for this organization.';
            const renderCheckbox = (program, key, label) => `
                <label class="dm-coverage-checkbox">
                    <input
                        type="checkbox"
                        ${checked(program, key) ? 'checked' : ''}
                        onchange="dmApp.setMeasureCoverageDraft('${program}', '${this.escapeJsString(key)}', this.checked)"
                    >
                    <span>${this.escapeHtml(label)}</span>
                </label>
            `;
            const mbqipHtml = mbqipGroups.map(group => {
                const keys = group.measures || [];
                return `
                    <div class="dm-coverage-group">
                        <div class="dm-coverage-toolbar">
                            <h4>${this.escapeHtml(group.name)}</h4>
                            <div class="dm-coverage-actions">
                                <button type="button" class="dm-coverage-link" onclick='dmApp.setMeasureCoverageGroup("mbqip", ${JSON.stringify(keys)}, true)'>Select all</button>
                                <button type="button" class="dm-coverage-link" onclick='dmApp.setMeasureCoverageGroup("mbqip", ${JSON.stringify(keys)}, false)'>Clear</button>
                            </div>
                        </div>
                        <div class="dm-coverage-checkbox-grid">
                            ${keys.map(measure => renderCheckbox('mbqip', measure, measure)).join('')}
                        </div>
                    </div>
                `;
            }).join('');
            const hacsKeys = this.allHacsHaisCoverageKeys();
            const hacsHtml = `
                <div class="dm-coverage-group">
                    <div class="dm-coverage-toolbar">
                        <h4>HACs & HAIs</h4>
                        <div class="dm-coverage-actions">
                            <button type="button" class="dm-coverage-link" onclick='dmApp.setMeasureCoverageGroup("hacs-hais", ${JSON.stringify(hacsKeys)}, true)'>Select all</button>
                            <button type="button" class="dm-coverage-link" onclick='dmApp.setMeasureCoverageGroup("hacs-hais", ${JSON.stringify(hacsKeys)}, false)'>Clear</button>
                        </div>
                    </div>
                    <div class="dm-coverage-checkbox-grid">
                        ${DM_IMPROVEMENT_CALCULATOR_MEASURES.map(measure => renderCheckbox('hacs-hais', measure.id, measure.label)).join('')}
                    </div>
                </div>
            `;
            const activeTab = this.state.dataOwnershipTab === 'measures' ? 'measures' : 'ownership';
            const tabContent = activeTab === 'measures'
                ? `
                    <section class="dm-general-manual-card">
                        <h2>Measures</h2>
                        <p>${this.escapeHtml(coverageNote)}</p>
                        <form onsubmit="dmApp.saveMeasureCoverage(event)">
                            <div class="dm-coverage-layout">
                                ${mbqipHtml}
                                ${hacsHtml}
                            </div>
                            <div class="dm-coverage-footer">
                                <div class="dm-coverage-status ${statusClass}">${this.escapeHtml(this.state.measureCoverageStatus || '')}</div>
                                <button type="submit" class="dm-save-btn" ${this.state.measureCoverageSaving ? 'disabled' : ''}>
                                    <i class="fas fa-save"></i>
                                    ${this.state.measureCoverageSaving ? 'Saving...' : 'Save Coverage'}
                                </button>
                            </div>
                        </form>
                    </section>
                `
                : this.renderGeneralOwnershipDashboard();

            return `
                <div class="dm-breadcrumb">
                    <span onclick="dmApp.navToRoot()">${this.escapeHtml(this.organizationDataTitle())}</span>
                    <i class="fas fa-chevron-right" style="font-size:10px;"></i>
                    <b>Measure Management</b>
                </div>
                <div class="dm-header">
                    <h1>Measure Management</h1>
                    <p>Assign measure owners and choose which MBQIP and HACs & HAIs measures this organization tracks.</p>
                </div>
                <div class="dm-tabs" style="margin-top:6px; margin-bottom:24px;">
                    <div class="dm-tab ${activeTab === 'ownership' ? 'active' : ''}" onclick="dmApp.setDataOwnershipTab('ownership')">Data Ownership</div>
                    <div class="dm-tab ${activeTab === 'measures' ? 'active' : ''}" onclick="dmApp.setDataOwnershipTab('measures')">Measures</div>
                </div>
                ${tabContent}
            `;
        },

        renderMeasuresView() {
            const cat = this.state.currentCategory;
            const sub = this.state.currentSubfolder;
            
            const breadcrumb = `
                <div class="dm-breadcrumb">
                    <span onclick="dmApp.navToRoot()">${this.escapeHtml(this.organizationDataTitle())}</span>
                    <i class="fas fa-chevron-right" style="font-size:10px;"></i>
                    ${sub ? `<span onclick="dmApp.navToCategory('${cat.id}')">${cat.name}</span>` : `<b>${cat.name}</b>`}
                    ${sub ? `<i class="fas fa-chevron-right" style="font-size:10px;"></i><b>${sub.name}</b>` : ''}
                </div>
            `;
            if (!sub && cat.subfolders) {
                const visibleSubfolders = cat.id === 'mbqip'
                    ? cat.subfolders.filter(sf => (sf.measures || []).some(measure => this.measureCoverageAllows('mbqip', measure)))
                    : cat.subfolders;
                itemsHtml = visibleSubfolders.length ? visibleSubfolders.map(sf => {
                    const visibleCount = cat.id === 'mbqip'
                        ? (sf.measures || []).filter(measure => this.measureCoverageAllows('mbqip', measure)).length
                        : sf.measures.length;
                    return `
                    <div class="dm-card" onclick="dmApp.navToSubfolder('${cat.id}', '${sf.id}')">
                        <i class="fas fa-folder"></i>
                        <div>
                            <h2>${sf.name}</h2>
                        </div>
                        <span class="dm-badge">${this.measureCountLabel(visibleCount)}</span>
                    </div>
                `}).join('') : '<div class="dm-guide" style="margin-top:0;">No measures are selected for this organization yet. Open Measure Management to update the available measures.</div>';
            } else {
                const measures = (sub ? sub.measures : (cat.measures || []))
                    .filter(measure => cat.id === 'mbqip' ? this.measureCoverageAllows('mbqip', measure) : true);
                itemsHtml = measures.map(m => {
                    const count = this.state.savedMeasures[m] || 0;
                    return `
                        <div class="dm-card dm-measure-card" onclick="dmApp.navToMeasure('${cat.id}', ${sub ? `'${sub.id}'` : 'null'}, '${m.replace(/'/g, "\\'")}')">
                            <div class="dm-folder-shape">
                                <span class="dm-folder-count">${count}</span>
                                <span class="dm-folder-files">${count === 1 ? 'file' : 'files'}</span>
                            </div>
                            <div class="dm-folder-label">${m}</div>
                        </div>
                    `;
                }).join('') || '<div class="dm-guide" style="margin-top:0;">No measures are selected for this organization yet. Open Measure Management to update the available measures.</div>';
            }
            const mbqipBulkUploadGuide = (!sub && cat && cat.id === 'mbqip')
                ? `<div class="dm-guide" style="margin-top:18px; margin-bottom:28px;">
                    <i class="fas fa-info-circle"></i>
                    If you are bulk uploading past data, use the <a href="#" class="dm-guide-link" onclick="event.preventDefault(); dmApp.navToCategory('general')" style="color:var(--dm-primary); font-weight:800; text-decoration:underline;">Universal Workbook</a> page.
                </div>`
                : '';

            return `
                ${breadcrumb}
                <div class="dm-header">
                    <h1>${sub ? sub.name : cat.name}</h1>
                </div>
                ${mbqipBulkUploadGuide}
                <div class="dm-grid">
                    ${itemsHtml}
                </div>
            `;
        },

        renderUploadedFilesList(mode = 'upload', limit = null, archiveState = 'active') {
            const m = this.state.currentMeasure;
            if (!m) return '';
            const allFiles = (this.state.filesByMeasure || {})[m] || [];
            const filteredFiles = allFiles.filter(f => {
                const isArchived = !!f.archived;
                if (archiveState === 'archive' && !isArchived) return false;
                if (archiveState !== 'archive' && isArchived) return false;
                if (mode === 'all') return true;
                const isManual = this.isManualAssessmentRecord(f);
                return mode === 'manual' ? isManual : !isManual;
            });
            const files = Number.isInteger(limit) && limit > 0 ? filteredFiles.slice(0, limit) : filteredFiles;
            if (!files.length) return '';
            const title = (mode === 'manual' || mode === 'all')
                ? (archiveState === 'archive' ? 'Archive' : (Number.isInteger(limit) && limit > 0 ? 'Recent saved assessments' : 'Saved assessments'))
                : 'Uploaded files';
            this.drivePollsStarted = this.drivePollsStarted || {};
            files.forEach(file => {
                const pendingName = String(file && file.name || '');
                if (!pendingName || String(file && file.drive_sync_status || '') !== 'pending' || this.drivePollsStarted[pendingName]) return;
                this.drivePollsStarted[pendingName] = true;
                window.setTimeout(() => this.pollDriveSyncStatus(pendingName, m), 0);
            });
            const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (c) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[c]));
            const escAttr = (s) => String(s).replace(/'/g, "\\'");
            const rows = files.map((f, i) => {
                const fileUrl = this.fileDownloadUrl(f);
                const canOpen = !!fileUrl;
                const syncStatus = String(f.drive_sync_status || '').trim();
                const syncError = String(f.drive_error || '').trim();
                const syncHint = syncStatus === 'pending'
                    ? (f.drive_sync_delayed
                        ? '<span style="color:#b45309;"> · Drive sync delayed</span>'
                        : '<span style="color:#64748b;"> · File syncing</span>')
                    : (syncStatus === 'failed' ? `<span style="color:#ef4444;" title="${escapeHtml(syncError || 'Drive sync failed')}"> · File sync failed</span>` : '');
                let yearLabel = '';
                if (f.assessment_period_label) {
                    yearLabel = `${escapeHtml(f.assessment_period_label)} · `;
                } else if (f.assessment_year_label) {
                    yearLabel = `${Array.isArray(f.assessment_years) && f.assessment_years.length > 1 ? 'Years' : 'Year'} ${escapeHtml(f.assessment_year_label)} · `;
                } else if (f.assessment_year_range) {
                    yearLabel = `Years ${escapeHtml(f.assessment_year_range)} · `;
                } else if (f.assessment_year) {
                    yearLabel = `${f.assessment_month ? escapeHtml(f.assessment_month) + ' ' : 'Year '}${escapeHtml(f.assessment_year)} · `;
                }
                return `
                <li style="display:flex; align-items:center; gap:12px; padding:10px 14px; background:#fff; border:1px solid #e5e7eb; border-radius:8px;">
                    <i class="fas fa-file-csv" style="color:var(--dm-primary); font-size:18px;"></i>
                    <div style="flex:1; min-width:0;">
                        <a href="${escapeHtml(fileUrl || '#')}"
                           onclick="event.preventDefault(); ${canOpen ? `dmApp.openFileViewer('${escAttr(f.name || '')}', '${escAttr(fileUrl)}')` : ''}"
                           style="color:var(--dm-primary); font-weight:600; text-decoration:none; word-break:break-all; cursor:${canOpen ? 'pointer' : 'default'};">
                            ${escapeHtml(f.name || `file-${i + 1}`)}
                        </a>
                        <div style="font-size:12px; color:var(--dm-text-muted); margin-top:2px;">
                            ${yearLabel}${escapeHtml(f.uploaded_at || '—')}${f.size_kb ? ' · ' + Number(f.size_kb).toFixed(1) + ' KB' : ''}${syncHint}
                        </div>
                    </div>
                    ${canOpen ? `<button type="button"
                            title="View file"
                            aria-label="View file"
                            onclick="dmApp.openFileViewer('${escAttr(f.name || '')}', '${escAttr(fileUrl)}')"
                            style="background:transparent; border:none; color:var(--dm-primary); cursor:pointer; padding:6px 8px; border-radius:6px; font-size:15px;"
                            onmouseover="this.style.background='#eef2ff';"
                            onmouseout="this.style.background='transparent';">
                        <i class="fas fa-eye"></i>
                    </button>` : ''}
                    <button type="button"
                            title="${archiveState === 'archive' ? 'Restore file' : 'Archive file'}"
                            aria-label="${archiveState === 'archive' ? 'Restore file' : 'Archive file'}"
                            onclick="${archiveState === 'archive' ? `dmApp.restoreUploadedFile('${escAttr(f.name || '')}')` : `dmApp.deleteUploadedFile('${escAttr(f.name || '')}')`}"
                            style="background:transparent; border:none; color:${archiveState === 'archive' ? 'var(--dm-primary)' : '#ef4444'}; cursor:pointer; padding:6px 8px; border-radius:6px; font-size:15px;"
                            onmouseover="this.style.background='${archiveState === 'archive' ? '#eef2ff' : '#fef2f2'}';"
                            onmouseout="this.style.background='transparent';">
                        <i class="fas ${archiveState === 'archive' ? 'fa-undo-alt' : 'fa-trash'}"></i>
                    </button>
                </li>
            `;
            }).join('');
            return `
                <div class="dm-uploaded-files-list" style="margin-top:24px;">
                    <h3 style="margin:0 0 12px; font-size:16px; font-weight:700; color:var(--dm-primary);">
                        ${title} (${files.length})
                    </h3>
                    <ul style="list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:8px;">
                        ${rows}
                    </ul>
                </div>
            `;
        },

        fileDownloadUrl(file) {
            if (!file) return '';
            const localUrl = String(file.url || '').trim();
            if (localUrl) return localUrl;
            const syncStatus = String(file.drive_sync_status || '').trim();
            if ((syncStatus === '' || syncStatus === 'synced') && String(file.drive_file_id || '').trim()) {
                const params = new URLSearchParams({
                    action: 'dm_download_saved_file',
                    nonce: DM_CONFIG.nonce,
                    file_name: String(file.name || '')
                });
                return `${DM_CONFIG.ajax_url}?${params.toString()}`;
            }
            return '';
        },

        collectRawDataRows() {
            const m = this.state.currentMeasure;
            if (!m) return [];
            const files = ((this.state.filesByMeasure || {})[m] || []).filter(f => !f.archived);
            const rows = [];
            const selectedEdtcSeries = this.state.edtcReportSeries || DM_EDTC_COMPOSITE_KEY;
            const firstPresent = (row, keys) => {
                for (const key of keys) {
                    if (Object.prototype.hasOwnProperty.call(row, key) && String(row[key] ?? '').trim() !== '') {
                        return row[key];
                    }
                }
                return '';
            };
            files.forEach(file => {
                const rawRows = Array.isArray(file.raw_rows) ? file.raw_rows : [];
                rawRows.forEach(row => {
                    if (this.isEdtcMeasure(m)) {
                        const seriesKey = this.edtcSeriesKeyForRow(row);
                        if (seriesKey !== selectedEdtcSeries) return;
                    }
                    const numValue = firstPresent(row, [
                        'num',
                        'criteria_met_count',
                        'Criteria Met Count',
                        'elements_met_count',
                        'Elements Met Count',
                        'core_elements_met_count',
                        'Core Elements Met Count',
                        'vaccinated_hcp',
                        'Vaccinated HCP'
                    ]);
                    const denValue = firstPresent(row, [
                        'den',
                        'denom',
                        'criteria_count',
                        'Criteria Count',
                        'elements_selected_count',
                        'Elements Selected Count',
                        'core_elements_count',
                        'Core Elements Count',
                        'total_eligible_hcp',
                        'Total Eligible HCP'
                    ]);
                    rows.push({
                        year: row.year || file.assessment_year || '',
                        period: row.period || file.assessment_month || '',
                        date_reported: row.date_reported || '',
                        num: numValue,
                        den: denValue,
                        rate: row.rate || '',
                        median_minutes: row.median_minutes || '',
                        file_name: file.name || ''
                    });
                });
            });
            return rows;
        },

        rawDataChartConfig() {
            if (this.isGlobalInfrastructureMeasure()) {
                return {
                    title: 'CAH Quality Infrastructure Criteria Met Rate',
                    numLabel: 'Criteria Met',
                    denLabel: 'Criteria Count',
                    benchmarkValue: 100,
                    benchmarkLabel: 'National benchmark 100%'
                };
            }
            if (this.isHcpInfluenzaMeasure()) {
                return {
                    title: 'HCP Flu Vaccination Rate',
                    numLabel: 'Vaccinated HCP',
                    denLabel: 'Total Eligible HCP',
                    benchmarkValue: 100,
                    benchmarkLabel: 'National benchmark 100%'
                };
            }
            if (this.isAntibioticStewardshipMeasure()) {
                return {
                    title: 'Antibiotic Stewardship Criteria Met Rate',
                    numLabel: 'Core Elements Met',
                    denLabel: 'Core Elements Count',
                    benchmarkValue: 100,
                    benchmarkLabel: 'National benchmark 100%'
                };
            }
            if (this.isEdtcMeasure()) {
                const seriesLabel = this.edtcSeriesLabel(this.state.edtcReportSeries);
                const isCompositeSeries = (this.state.edtcReportSeries || DM_EDTC_COMPOSITE_KEY) === DM_EDTC_COMPOSITE_KEY;
                return {
                    title: seriesLabel === 'Composite Score'
                        ? 'EDTC Composite Score Run Chart'
                        : `EDTC ${seriesLabel} Run Chart`,
                    periodLabel: 'Quarter',
                    numLabel: 'Numerator',
                    denLabel: 'Denominator',
                    timeScaledXAxis: true,
                    benchmarkValue: isCompositeSeries ? 100 : null,
                    benchmarkLabel: isCompositeSeries ? 'National benchmark 100%' : '',
                    suppressGoalReference: !isCompositeSeries
                };
            }
            if (this.isSafeUseOpioidsMeasure()) {
                return {
                    title: 'Safe Use of Opioids Rate',
                    periodLabel: 'Month',
                    numLabel: 'Numerator',
                    denLabel: 'Denominator',
                    timeScaledXAxis: true,
                    benchmarkValue: 16.6,
                    benchmarkLabel: 'National benchmark 16.6%'
                };
            }
            if (this.isHwrMeasure()) {
                return {
                    title: 'Hybrid Hospital-Wide Readmission Rate',
                    periodLabel: 'Month',
                    numLabel: 'Readmissions',
                    denLabel: 'Eligible Discharges',
                    timeScaledXAxis: true,
                    benchmarkValue: null,
                    benchmarkLabel: ''
                };
            }
            if (this.isHcahpsMeasure()) {
                const details = DM_HCAHPS_MEASURE_DETAILS[this.state.currentMeasure] || {};
                const reference = this.rawDataBenchmarkFromText(details.benchmark || '');
                return {
                    title: String(this.state.currentMeasure || 'HCAHPS Rate').replace(/^HCAHPS\s+—\s+/, 'HCAHPS: '),
                    periodLabel: 'Quarter',
                    numLabel: 'Top-box Responses',
                    denLabel: 'Eligible Surveys',
                    benchmarkValue: reference ? reference.value : null,
                    benchmarkLabel: reference ? reference.label : ''
                };
            }
            if (this.isOp18Measure()) {
                return {
                    title: 'OP-18 Median ED Arrival to Departure Time',
                    periodLabel: 'Quarter',
                    valueField: 'median_minutes',
                    valueLabel: 'Median Minutes',
                    valueUnit: 'min',
                    benchmarkValue: 84,
                    benchmarkLabel: 'National benchmark 84 min'
                };
            }
            if (this.isOp22Measure()) {
                return {
                    title: 'OP-22 LWBS Rate',
                    numLabel: 'LWBS Patients',
                    denLabel: 'ED Visits',
                    benchmarkValue: 0.1,
                    benchmarkLabel: 'National benchmark 0.1%'
                };
            }
            return null;
        },

        usesTimeScaledRawDataAxis(config = null) {
            config = config || this.rawDataChartConfig() || {};
            const periodLabel = String(config.periodLabel || '').toLowerCase();
            const hasChartConfig = !!(config.title || config.numLabel || config.valueField || config.periodLabel);
            return !!config.timeScaledXAxis || (hasChartConfig && (!periodLabel || ['month', 'quarter'].includes(periodLabel)));
        },

        hasRawDataChart() {
            return !!this.rawDataChartConfig();
        },

        rawDataBenchmarkFromText(text) {
            const benchmarkText = String(text || '');
            let match = benchmarkText.match(/National benchmark:\s*([0-9]+(?:\.[0-9]+)?)%/i);
            if (match) {
                return {
                    value: parseFloat(match[1]),
                    label: `National benchmark ${match[1]}%`
                };
            }
            return null;
        },

        getRawDataRunChartPoints(rows) {
            const config = this.rawDataChartConfig() || {};
            const useTimeScaledAxis = this.usesTimeScaledRawDataAxis(config);
            const monthOrder = this.monthOptions().reduce((acc, month, idx) => {
                acc[month.toLowerCase()] = idx;
                return acc;
            }, {});
            const quarterOrder = this.quarterOptions().reduce((acc, quarter, idx) => {
                acc[quarter.toLowerCase()] = idx;
                return acc;
            }, {});
            return rows
                .map(row => {
                    const year = parseInt(row.year, 10);
                    const value = config.valueField === 'median_minutes'
                        ? this.parseNumberValue(row.median_minutes)
                        : this.parsePercentValue(row.rate);
                    const period = String(row.period || '').trim();
                    const periodIndex = config.periodLabel === 'Quarter'
                        ? quarterOrder[period.toLowerCase()]
                        : monthOrder[period.toLowerCase()];
                    const periodMultiplier = config.periodLabel === 'Quarter' ? 4 : 12;
                    const sortKey = config.periodLabel
                        ? (Number.isFinite(year) && Number.isInteger(periodIndex) ? (year * periodMultiplier) + periodIndex : NaN)
                        : year;
                    let timeValue = NaN;
                    if (useTimeScaledAxis && Number.isFinite(year)) {
                        if (config.periodLabel === 'Quarter' && Number.isInteger(periodIndex)) {
                            timeValue = Date.UTC(year, periodIndex * 3, 1);
                        } else if (config.periodLabel === 'Month' && Number.isInteger(periodIndex)) {
                            timeValue = Date.UTC(year, periodIndex, 1);
                        } else if (!config.periodLabel) {
                            timeValue = Date.UTC(year, 0, 1);
                        }
                    }
                    const label = config.periodLabel
                        ? `${period} ${year}`.trim()
                        : String(year);
                    return Number.isFinite(year) && Number.isFinite(value)
                        ? {
                            label,
                            year,
                            period,
                            rate: value,
                            num: row.num,
                            den: row.den,
                            fileName: row.file_name || '',
                            sortKey,
                            timeValue
                        }
                        : null;
                })
                .filter(Boolean)
                .sort((a, b) => {
                    const aKey = Number.isFinite(a.sortKey) ? a.sortKey : a.year;
                    const bKey = Number.isFinite(b.sortKey) ? b.sortKey : b.year;
                    return aKey - bKey;
                });
        },

        rawDataMissingPeriodGaps(rows) {
            const config = this.rawDataChartConfig() || {};
            if (!['Month', 'Quarter'].includes(String(config.periodLabel || ''))) return [];

            const points = this.getRawDataRunChartPoints(rows)
                .filter(point => Number.isFinite(point.sortKey) && String(point.period || '').trim());
            if (points.length < 2) return [];

            const periodOptions = config.periodLabel === 'Quarter' ? this.quarterOptions() : this.monthOptions();
            const periodCount = periodOptions.length;
            const periodOrder = periodOptions.reduce((acc, period, idx) => {
                acc[period.toLowerCase()] = idx;
                return acc;
            }, {});
            const seen = new Set();
            points.forEach(point => {
                const index = periodOrder[String(point.period || '').toLowerCase()];
                if (Number.isInteger(index) && Number.isFinite(point.year)) {
                    seen.add((point.year * periodCount) + index);
                }
            });

            const uniqueKeys = [...seen].sort((a, b) => a - b);
            if (uniqueKeys.length < 2) return [];

            const missing = [];
            for (let key = uniqueKeys[0] + 1; key < uniqueKeys[uniqueKeys.length - 1]; key++) {
                if (seen.has(key)) continue;
                const year = Math.floor(key / periodCount);
                const periodIndex = key % periodCount;
                const period = periodOptions[periodIndex] || '';
                if (!period) continue;
                missing.push({
                    year,
                    period,
                    label: config.periodLabel === 'Quarter'
                        ? `${period} ${year}`
                        : `${period} ${year}`
                });
            }

            return missing;
        },

        renderRawDataMissingPeriodWarning(rows) {
            const missing = this.rawDataMissingPeriodGaps(rows);
            if (!missing.length) return '';

            const config = this.rawDataChartConfig() || {};
            const periodName = String(config.periodLabel || 'period').toLowerCase();
            const labels = missing.map(item => item.label);
            const displayLabels = labels.slice(0, 6).join(', ');
            const extraCount = labels.length - 6;
            const suffix = extraCount > 0 ? `, and ${extraCount} more` : '';
            const plural = missing.length === 1 ? periodName : `${periodName}s`;

            return `
                <div class="dm-missing-period-warning" role="status">
                    <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                    <div>
                        <strong>Possible missing ${this.escapeHtml(plural)} detected</strong>
                        <span>${this.escapeHtml(displayLabels + suffix)} ${missing.length === 1 ? 'is' : 'are'} missing between the first and latest saved data point. Add ${missing.length === 1 ? 'this entry' : 'these entries'} if data was collected for ${missing.length === 1 ? 'that' : 'those'} ${periodName}${missing.length === 1 ? '' : 's'}.</span>
                    </div>
                </div>
            `;
        },

        sortedRawDataRows(rows = null) {
            const config = this.rawDataChartConfig() || {};
            const sortedRows = Array.isArray(rows) ? [...rows] : this.collectRawDataRows();
            sortedRows.sort((a, b) => {
                const yearA = parseInt(a.year, 10) || 0;
                const yearB = parseInt(b.year, 10) || 0;
                if (yearA !== yearB) return yearB - yearA;
                if (config.periodLabel) {
                    const periodOptions = config.periodLabel === 'Quarter' ? this.quarterOptions() : this.monthOptions();
                    const periodOrder = periodOptions.reduce((acc, period, idx) => {
                        acc[period.toLowerCase()] = idx;
                        return acc;
                    }, {});
                    const periodA = periodOrder[String(a.period || '').toLowerCase()] ?? -1;
                    const periodB = periodOrder[String(b.period || '').toLowerCase()] ?? -1;
                    if (periodA !== periodB) return periodB - periodA;
                }
                const dateA = Date.parse(a.date_reported || '') || 0;
                const dateB = Date.parse(b.date_reported || '') || 0;
                return dateB - dateA;
            });
            return sortedRows;
        },

        rawDataYearOptions(rows = null) {
            const sourceRows = Array.isArray(rows) ? rows : this.collectRawDataRows();
            return [...new Set(sourceRows.map(row => String(row.year || '').trim()).filter(Boolean))]
                .sort((a, b) => (parseInt(b, 10) || 0) - (parseInt(a, 10) || 0));
        },

        filteredRawDataRows(rows = null) {
            const selectedYear = String(this.state.rawDataYearFilter || 'all');
            const sourceRows = Array.isArray(rows) ? rows : this.collectRawDataRows();
            const filtered = selectedYear === 'all'
                ? sourceRows
                : sourceRows.filter(row => String(row.year || '').trim() === selectedYear);
            return this.sortedRawDataRows(filtered);
        },

        setRawDataYearFilter(year) {
            this.setState({
                rawDataYearFilter: String(year || 'all')
            }, { preserveScroll: true, scrollToTop: false });
        },

        renderRawDataYearFilter(rows = null) {
            const years = this.rawDataYearOptions(rows);
            if (years.length <= 1) return '';
            const selected = years.includes(String(this.state.rawDataYearFilter || 'all'))
                ? String(this.state.rawDataYearFilter || 'all')
                : 'all';
            return `
                <label style="display:flex; align-items:center; gap:10px; font-weight:800; color:var(--dm-primary);">
                    Reporting Year
                    <select class="dm-year-select" onchange="dmApp.setRawDataYearFilter(this.value)" style="width:160px; height:40px; border:1px solid #d1d5db; padding:8px 12px; border-radius:8px; font-size:14px;">
                        <option value="all" ${selected === 'all' ? 'selected' : ''}>All years</option>
                        ${years.map(year => `<option value="${this.escapeHtml(year)}" ${selected === year ? 'selected' : ''}>${this.escapeHtml(year)}</option>`).join('')}
                    </select>
                </label>
            `;
        },

        parsePercentValue(value) {
            const parsed = parseFloat(String(value == null ? '' : value).replace('%', '').trim());
            return Number.isFinite(parsed) ? parsed : NaN;
        },

        parseNumberValue(value) {
            const parsed = parseFloat(String(value == null ? '' : value).trim());
            return Number.isFinite(parsed) ? parsed : NaN;
        },

        chartValueLabel(value, config = {}) {
            const decimals = config.valueUnit ? 0 : 1;
            const formatted = Number(value).toFixed(decimals);
            return config.valueUnit ? `${formatted} ${config.valueUnit}` : `${formatted}%`;
        },

        chartReferenceBaseLabel(label) {
            return String(label || 'Benchmark')
                .replace(/\s+\d+(\.\d+)?\s*(%|min|minutes)?$/i, '')
                .trim();
        },

        currentRawDataGoalReference() {
            const key = this.measureKey();
            const goal = (this.state.measureGoals || {})[key] || {};
            if (!goal || this.isPastMeasureGoal(goal)) return null;
            const value = this.parseNumberValue(goal.goal_rate);
            if (!Number.isFinite(value)) return null;
            const config = this.rawDataChartConfig() || {};
            if (config.suppressGoalReference) return null;
            return {
                value,
                label: `Current goal ${this.chartValueLabel(value, config)}`
            };
        },

        renderRawDataRunChart(rows) {
            const config = this.rawDataChartConfig();
            const points = this.getRawDataRunChartPoints(rows);
            if (!points.length) {
                return '<div class="dm-guide" style="margin-top:24px;">No rate data available for the run chart yet.</div>';
            }

            return `
                <div class="dm-raw-chart-card">
                    <div class="dm-raw-chart-head">
                        <h3 class="dm-raw-chart-title">${config.title}</h3>
                        <div class="dm-raw-chart-actions">
                            <button type="button" class="dm-raw-chart-download dm-raw-chart-icon-btn" onclick="dmApp.copyRawDataChartImage(this)" aria-label="Copy chart image" title="Copy image">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button type="button" class="dm-raw-chart-download dm-raw-chart-icon-btn" onclick="dmApp.downloadRawDataChartJpeg()" aria-label="Download chart JPEG" title="Download JPEG">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                    <div class="dm-raw-chart-canvas-wrap">
                        <canvas id="dmRawDataRunChart" class="dm-raw-chart-canvas"></canvas>
                    </div>
                </div>
            `;
        },

        chartPointerPosition(canvas, event) {
            const source = event.touches && event.touches.length ? event.touches[0] : event;
            const rect = canvas.getBoundingClientRect();
            return {
                x: source.clientX - rect.left,
                y: source.clientY - rect.top
            };
        },

        nearestChartPoint(hitPoints, pointer, radius = 14) {
            if (!Array.isArray(hitPoints) || !hitPoints.length || !pointer) return null;
            let nearest = null;
            let nearestDistance = Infinity;
            hitPoints.forEach(point => {
                const dx = pointer.x - point.x;
                const dy = pointer.y - point.y;
                const distance = Math.sqrt((dx * dx) + (dy * dy));
                if (distance <= radius && distance < nearestDistance) {
                    nearest = point;
                    nearestDistance = distance;
                }
            });
            return nearest;
        },

        bindChartPointTooltip(canvas, chartKey, drawMethodName) {
            if (!canvas || canvas.dataset.dmTooltipBound === chartKey) return;
            canvas.dataset.dmTooltipBound = chartKey;
            const hoverKey = `_${chartKey}HoverIndex`;
            const hitKey = `_${chartKey}HitPoints`;
            const updateHover = (event) => {
                const nearest = this.nearestChartPoint(this[hitKey], this.chartPointerPosition(canvas, event));
                canvas.style.cursor = nearest ? 'pointer' : 'default';
                const nextIndex = nearest ? nearest.index : null;
                if (this[hoverKey] === nextIndex) return;
                this[hoverKey] = nextIndex;
                if (typeof this[drawMethodName] === 'function') {
                    this[drawMethodName]();
                }
            };
            const clearHover = () => {
                canvas.style.cursor = 'default';
                if (this[hoverKey] === null || this[hoverKey] === undefined) return;
                this[hoverKey] = null;
                if (typeof this[drawMethodName] === 'function') {
                    this[drawMethodName]();
                }
            };
            canvas.addEventListener('mousemove', updateHover);
            canvas.addEventListener('click', updateHover);
            canvas.addEventListener('touchstart', updateHover, { passive: true });
            canvas.addEventListener('mouseleave', clearHover);
            canvas.addEventListener('touchend', () => window.setTimeout(clearHover, 1800), { passive: true });
        },

        drawChartPointTooltip(ctx, point, cssWidth, cssHeight) {
            if (!point || !Array.isArray(point.tooltipLines) || !point.tooltipLines.length) return;
            const paddingX = 12;
            const paddingY = 9;
            const lineHeight = 18;
            ctx.save();
            ctx.font = '600 13px Inter, system-ui, -apple-system, sans-serif';
            const width = Math.max(...point.tooltipLines.map(line => ctx.measureText(String(line)).width)) + (paddingX * 2);
            const height = (point.tooltipLines.length * lineHeight) + (paddingY * 2);
            let x = point.x + 14;
            let y = point.y - height - 14;
            if (x + width > cssWidth - 8) {
                x = point.x - width - 14;
            }
            if (y < 8) {
                y = point.y + 14;
            }
            x = Math.max(8, Math.min(x, cssWidth - width - 8));
            y = Math.max(8, Math.min(y, cssHeight - height - 8));

            ctx.fillStyle = 'rgba(15, 47, 68, 0.96)';
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.95)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            if (typeof ctx.roundRect === 'function') {
                ctx.roundRect(x, y, width, height, 8);
            } else {
                ctx.rect(x, y, width, height);
            }
            ctx.fill();
            ctx.stroke();

            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'top';
            point.tooltipLines.forEach((line, idx) => {
                ctx.fillText(String(line), x + paddingX, y + paddingY + (idx * lineHeight));
            });
            ctx.restore();
        },

        scheduleRawDataRunChart() {
            if (!this.hasRawDataChart() || this.state.inputTab !== 'database') {
                return;
            }
            window.requestAnimationFrame(() => this.drawRawDataRunChart());
        },

        drawRawDataRunChart(targetCanvas = null) {
            const canvas = targetCanvas || document.getElementById('dmRawDataRunChart');
            if (!canvas) return false;

            const points = this.getRawDataRunChartPoints(this.filteredRawDataRows());
            if (!points.length) return false;
            const config = this.rawDataChartConfig() || {};

            const wrap = canvas.parentElement;
            const cssWidth = Math.max(520, targetCanvas ? 1100 : (wrap ? wrap.clientWidth : canvas.clientWidth || 900));
            const cssHeight = targetCanvas ? 620 : (wrap ? wrap.clientHeight : canvas.clientHeight || 320);
            const ratio = targetCanvas ? 2 : (window.devicePixelRatio || 1);
            canvas.width = Math.round(cssWidth * ratio);
            canvas.height = Math.round(cssHeight * ratio);
            canvas.style.width = `${cssWidth}px`;
            canvas.style.height = `${cssHeight}px`;

            const ctx = canvas.getContext('2d');
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            ctx.clearRect(0, 0, cssWidth, cssHeight);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, cssWidth, cssHeight);

            const useTimeScaledAxis = this.usesTimeScaledRawDataAxis(config);
            const pad = { top: 28, right: useTimeScaledAxis ? 72 : 28, bottom: 48, left: 56 };
            const plotW = cssWidth - pad.left - pad.right;
            const plotH = cssHeight - pad.top - pad.bottom;
            const values = points.map(p => p.rate);
            const goalReference = this.currentRawDataGoalReference();
            const referenceValues = [
                ...values,
                ...(Number.isFinite(config.benchmarkValue) ? [config.benchmarkValue] : []),
                ...(goalReference && Number.isFinite(goalReference.value) ? [goalReference.value] : [])
            ];
            const rawMin = Math.min(...referenceValues, 0);
            const rawMax = Math.max(...referenceValues, 0);
            const tickStep = config.valueUnit ? 10 : 5;
            const yMin = Math.max(0, Math.floor((rawMin - tickStep) / tickStep) * tickStep);
            const uncappedYMax = Math.ceil((rawMax + tickStep) / tickStep) * tickStep || (config.valueUnit ? tickStep : 100);
            const yMax = config.valueUnit ? uncappedYMax : Math.min(100, uncappedYMax);
            const yRange = Math.max(1, yMax - yMin);
            const timeValues = useTimeScaledAxis
                ? points.map(point => point.timeValue).filter(value => Number.isFinite(value))
                : [];
            const hasTimeScale = useTimeScaledAxis && timeValues.length === points.length && timeValues.length > 1;
            const rawTimeMin = hasTimeScale ? Math.min(...timeValues) : NaN;
            const rawTimeMax = hasTimeScale ? Math.max(...timeValues) : NaN;
            const rawTimeRange = hasTimeScale ? Math.max(1, rawTimeMax - rawTimeMin) : NaN;
            const timePadding = hasTimeScale ? Math.max(15 * 24 * 60 * 60 * 1000, rawTimeRange * 0.025) : 0;
            const timeMin = hasTimeScale ? rawTimeMin - timePadding : NaN;
            const timeMax = hasTimeScale ? rawTimeMax + timePadding : NaN;
            const timeRange = hasTimeScale ? Math.max(1, timeMax - timeMin) : NaN;
            const xFor = (idx) => {
                if (hasTimeScale) {
                    return pad.left + ((points[idx].timeValue - timeMin) / timeRange) * plotW;
                }
                return pad.left + (points.length === 1 ? plotW / 2 : (idx / (points.length - 1)) * plotW);
            };
            const yFor = (value) => pad.top + ((yMax - value) / yRange) * plotH;

            ctx.strokeStyle = '#e5e7eb';
            ctx.lineWidth = 1;
            ctx.fillStyle = '#64748b';
            ctx.font = '13px Inter, system-ui, -apple-system, sans-serif';
            ctx.textAlign = 'right';
            ctx.textBaseline = 'middle';
            const tickCount = 5;
            for (let i = 0; i <= tickCount; i++) {
                const value = yMin + (yRange / tickCount) * i;
                const y = yFor(value);
                ctx.beginPath();
                ctx.moveTo(pad.left, y);
                ctx.lineTo(cssWidth - pad.right, y);
                ctx.stroke();
                ctx.fillText(this.chartValueLabel(value, config), pad.left - 12, y);
            }

            ctx.strokeStyle = '#cbd5e1';
            ctx.beginPath();
            ctx.moveTo(pad.left, pad.top);
            ctx.lineTo(pad.left, cssHeight - pad.bottom);
            ctx.lineTo(cssWidth - pad.right, cssHeight - pad.bottom);
            ctx.stroke();

            const sortedValues = [...values].sort((a, b) => a - b);
            const middle = Math.floor(sortedValues.length / 2);
            const median = sortedValues.length % 2
                ? sortedValues[middle]
                : (sortedValues[middle - 1] + sortedValues[middle]) / 2;
            const rightLabelX = cssWidth - pad.right - 8;
            const labelGap = 18;
            const referenceLines = [
                {
                    value: median,
                    label: `Median ${this.chartValueLabel(median, config)}`,
                    color: '#64748b',
                    stroke: '#94a3b8',
                    dash: [7, 7]
                },
                ...(Number.isFinite(config.benchmarkValue) ? [{
                    value: config.benchmarkValue,
                    label: config.benchmarkLabel || `National benchmark ${this.chartValueLabel(config.benchmarkValue, config)}`,
                    color: '#166534',
                    stroke: '#16a34a',
                    dash: [4, 6]
                }] : []),
                ...(goalReference ? [{
                    value: goalReference.value,
                    label: goalReference.label,
                    color: '#92400e',
                    stroke: '#f59e0b',
                    dash: [6, 5]
                }] : [])
            ].map(line => ({ ...line, y: yFor(line.value) }));

            referenceLines.forEach(line => {
                ctx.strokeStyle = line.stroke;
                ctx.setLineDash(line.dash);
                ctx.beginPath();
                ctx.moveTo(pad.left, line.y);
                ctx.lineTo(cssWidth - pad.right, line.y);
                ctx.stroke();
            });
            ctx.setLineDash([]);

            const sortedLabels = [...referenceLines].sort((a, b) => a.y - b.y);
            let previousLabelY = -Infinity;
            sortedLabels.forEach(line => {
                let labelY = line.y - 6;
                if (labelY - previousLabelY < labelGap) {
                    labelY = previousLabelY + labelGap;
                }
                labelY = Math.min(labelY, cssHeight - pad.bottom - 4);
                previousLabelY = labelY;
                ctx.fillStyle = line.color;
                ctx.textAlign = 'right';
                ctx.textBaseline = 'bottom';
                ctx.fillText(line.label, rightLabelX, labelY);
            });

            ctx.strokeStyle = '#285a7d';
            ctx.lineWidth = 4;
            ctx.lineJoin = 'round';
            ctx.lineCap = 'round';
            ctx.beginPath();
            points.forEach((point, idx) => {
                const x = xFor(idx);
                const y = yFor(point.rate);
                if (idx === 0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            });
            ctx.stroke();

            points.forEach((point, idx) => {
                const x = xFor(idx);
                const y = yFor(point.rate);
                ctx.fillStyle = '#285a7d';
                ctx.beginPath();
                ctx.arc(x, y, 6, 0, Math.PI * 2);
                ctx.fill();
                ctx.strokeStyle = '#ffffff';
                ctx.lineWidth = 2;
                ctx.stroke();
            });

            const hitPoints = points.map((point, idx) => {
                const tooltipLines = [
                    point.label,
                    `${config.valueLabel || 'Value'}: ${this.chartValueLabel(point.rate, config)}`
                ];
                if (String(point.num ?? '').trim() !== '') {
                    tooltipLines.push(`${config.numLabel || 'Numerator'}: ${point.num}`);
                }
                if (String(point.den ?? '').trim() !== '') {
                    tooltipLines.push(`${config.denLabel || 'Denominator'}: ${point.den}`);
                }
                return {
                    index: idx,
                    x: xFor(idx),
                    y: yFor(point.rate),
                    tooltipLines
                };
            });
            if (!targetCanvas) {
                this._rawDataRunChartHitPoints = hitPoints;
                this.bindChartPointTooltip(canvas, 'rawDataRunChart', 'drawRawDataRunChart');
            }

            ctx.fillStyle = '#475569';
            ctx.font = '13px Inter, system-ui, -apple-system, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'top';
            let previousLabelX = -Infinity;
            points.forEach((point, idx) => {
                const x = xFor(idx);
                const isEdge = idx === 0 || idx === points.length - 1;
                const hasRoom = x - previousLabelX >= 82;
                if (isEdge || hasRoom) {
                    const halfLabelWidth = ctx.measureText(point.label).width / 2;
                    const labelX = Math.min(Math.max(x, halfLabelWidth + 4), cssWidth - halfLabelWidth - 4);
                    ctx.fillText(point.label, labelX, cssHeight - pad.bottom + 16);
                    previousLabelX = x;
                }
            });

            if (!targetCanvas && this._rawDataRunChartHoverIndex !== null && this._rawDataRunChartHoverIndex !== undefined) {
                const activePoint = hitPoints.find(point => point.index === this._rawDataRunChartHoverIndex);
                if (activePoint) {
                    ctx.fillStyle = '#0f2f44';
                    ctx.beginPath();
                    ctx.arc(activePoint.x, activePoint.y, 8, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.strokeStyle = '#ffffff';
                    ctx.lineWidth = 2;
                    ctx.stroke();
                    this.drawChartPointTooltip(ctx, activePoint, cssWidth, cssHeight);
                }
            }

            return true;
        },

        loadExportImage(src) {
            return new Promise((resolve) => {
                if (!src) {
                    resolve(null);
                    return;
                }
                try {
                    const srcText = String(src);
                    if (!srcText.startsWith('data:image/') && !srcText.startsWith('blob:')) {
                        const imageUrl = new URL(src, window.location.href);
                        if (imageUrl.origin !== window.location.origin) {
                            resolve(null);
                            return;
                        }
                    }
                } catch (error) {
                    resolve(null);
                    return;
                }
                const img = new Image();
                img.onload = () => resolve(img);
                img.onerror = () => resolve(null);
                img.src = src;
            });
        },

        async loadExportLogoImage() {
            const base64 = String(DM_CONFIG.brandLogoBase64 || '').trim();
            if (base64) {
                try {
                    const binary = atob(base64);
                    const bytes = new Uint8Array(binary.length);
                    for (let i = 0; i < binary.length; i++) {
                        bytes[i] = binary.charCodeAt(i);
                    }
                    const blobUrl = URL.createObjectURL(new Blob([bytes], { type: 'image/png' }));
                    const image = await this.loadExportImage(blobUrl);
                    URL.revokeObjectURL(blobUrl);
                    if (image) return image;
                } catch (error) {
                    // Fall through to the same-origin plugin asset below.
                }
            }
            return this.loadExportImage(DM_CONFIG.brandLogoUrl);
        },

        formatExportDateTime(date = new Date()) {
            try {
                return date.toLocaleString('en-US', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: 'numeric',
                    minute: '2-digit'
                });
            } catch (error) {
                return date.toISOString();
            }
        },

        drawExportText(ctx, text, x, y, maxWidth, lineHeight) {
            const words = String(text || '').split(/\s+/).filter(Boolean);
            if (!words.length) return y;
            let line = '';
            words.forEach((word) => {
                const testLine = line ? `${line} ${word}` : word;
                if (ctx.measureText(testLine).width > maxWidth && line) {
                    ctx.fillText(line, x, y);
                    line = word;
                    y += lineHeight;
                } else {
                    line = testLine;
                }
            });
            if (line) {
                ctx.fillText(line, x, y);
                y += lineHeight;
            }
            return y;
        },

        async buildBrandedRawDataChartExport(chartCanvas, options = {}) {
            const exportRatio = Number.isFinite(options.exportRatio) && options.exportRatio > 0 ? options.exportRatio : 2;
            const cssWidth = 1400;
            const contentPad = 64;
            const headerH = 220;
            const footerH = 58;
            const chartW = cssWidth - (contentPad * 2);
            const chartH = Math.round(chartW * (chartCanvas.height / Math.max(1, chartCanvas.width)));
            const cssHeight = headerH + chartH + footerH + 44;
            const exportCanvas = document.createElement('canvas');
            exportCanvas.width = cssWidth * exportRatio;
            exportCanvas.height = cssHeight * exportRatio;
            exportCanvas.style.width = `${cssWidth}px`;
            exportCanvas.style.height = `${cssHeight}px`;

            const ctx = exportCanvas.getContext('2d');
            ctx.setTransform(exportRatio, 0, 0, exportRatio, 0, 0);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, cssWidth, cssHeight);

            ctx.fillStyle = '#e7fbfd';
            ctx.fillRect(0, 0, cssWidth, headerH);
            ctx.fillStyle = '#0b3045';
            ctx.fillRect(0, 0, cssWidth, 8);
            ctx.fillStyle = '#23bf65';
            ctx.fillRect(0, 8, cssWidth, 5);

            const logo = await this.loadExportLogoImage();
            const maxLogoW = 190;
            const maxLogoH = 96;
            if (logo) {
                const logoRatio = Math.min(maxLogoW / logo.width, maxLogoH / logo.height);
                const logoW = logo.width * logoRatio;
                const logoH = logo.height * logoRatio;
                ctx.drawImage(logo, contentPad, 36, logoW, logoH);
            }

            const orgName = String((DM_CONFIG.userOrgContext && DM_CONFIG.userOrgContext.org_name) || '').trim();
            const stateCode = String((DM_CONFIG.userOrgContext && DM_CONFIG.userOrgContext.state_code) || '').trim();
            const config = this.rawDataChartConfig() || {};
            const title = config.title || this.state.currentMeasure || 'Run Chart';
            const metadata = [
                orgName ? `Organization: ${orgName}${stateCode ? ` (${stateCode})` : ''}` : '',
                `Exported: ${this.formatExportDateTime()}`
            ].filter(Boolean);

            ctx.textAlign = 'right';
            ctx.textBaseline = 'top';
            ctx.fillStyle = '#445365';
            ctx.font = '500 15px Inter, system-ui, -apple-system, sans-serif';
            metadata.forEach((line, idx) => {
                ctx.fillText(line, cssWidth - contentPad, 40 + (idx * 24));
            });

            ctx.textAlign = 'left';
            ctx.fillStyle = '#1f2233';
            ctx.font = '700 24px Inter, system-ui, -apple-system, sans-serif';
            this.drawExportText(ctx, title, contentPad, headerH - 56, cssWidth - (contentPad * 2), 28);

            ctx.drawImage(chartCanvas, contentPad, headerH + 22, chartW, chartH);

            const footerY = headerH + 22 + chartH + 28;
            ctx.fillStyle = '#eff6f7';
            ctx.fillRect(0, footerY, cssWidth, footerH);

            return exportCanvas;
        },

        canvasToBlob(canvas, type = 'image/png', quality = 0.92) {
            return new Promise((resolve, reject) => {
                if (!canvas || typeof canvas.toBlob !== 'function') {
                    reject(new Error('Canvas export is not available.'));
                    return;
                }
                canvas.toBlob((blob) => {
                    if (blob) {
                        resolve(blob);
                    } else {
                        reject(new Error('Unable to create chart image.'));
                    }
                }, type, quality);
            });
        },

        async buildClipboardRawDataChartCanvas(chartCanvas) {
            return this.buildBrandedRawDataChartExport(chartCanvas, { exportRatio: 1 });
        },

        copyImageDataUrlViaSelection(dataUrl) {
            if (!dataUrl || !document.queryCommandSupported || !document.queryCommandSupported('copy')) {
                return false;
            }

            const wrapper = document.createElement('div');
            wrapper.setAttribute('contenteditable', 'true');
            wrapper.style.position = 'fixed';
            wrapper.style.left = '-9999px';
            wrapper.style.top = '0';
            wrapper.style.width = '1px';
            wrapper.style.height = '1px';
            wrapper.style.overflow = 'hidden';

            const image = document.createElement('img');
            image.src = dataUrl;
            image.alt = 'Data Hub run chart';
            wrapper.appendChild(image);
            document.body.appendChild(wrapper);

            const selection = window.getSelection ? window.getSelection() : null;
            if (!selection) {
                document.body.removeChild(wrapper);
                return false;
            }

            const range = document.createRange();
            range.selectNodeContents(wrapper);
            selection.removeAllRanges();
            selection.addRange(range);

            let copied = false;
            try {
                copied = document.execCommand('copy');
            } catch (err) {
                copied = false;
            }

            selection.removeAllRanges();
            document.body.removeChild(wrapper);
            return copied;
        },

        async copyRawDataChartImage(button = null) {
            const chartCanvas = document.createElement('canvas');
            const didDraw = this.drawRawDataRunChart(chartCanvas);
            if (!didDraw) return;

            const icon = button ? button.querySelector('i') : null;
            const originalIconClass = icon ? icon.className : '';
            if (button) {
                button.disabled = true;
            }

            try {
                const exportCanvas = await this.buildClipboardRawDataChartCanvas(chartCanvas);
                let copied = false;
                const ClipboardItemCtor = window.ClipboardItem;

                if (navigator.clipboard && typeof navigator.clipboard.write === 'function' && ClipboardItemCtor) {
                    try {
                        const blob = await this.canvasToBlob(exportCanvas, 'image/png');
                        await navigator.clipboard.write([
                            new ClipboardItemCtor({ 'image/png': blob })
                        ]);
                        copied = true;
                    } catch (err) {
                        copied = false;
                    }
                }

                if (!copied) {
                    copied = this.copyImageDataUrlViaSelection(exportCanvas.toDataURL('image/png'));
                }

                if (!copied) {
                    throw new Error('Image clipboard copy failed.');
                }

                if (button) {
                    button.classList.add('dm-copied');
                    button.setAttribute('aria-label', 'Chart image copied');
                    button.setAttribute('title', 'Copied');
                }
                if (icon) {
                    icon.className = 'fas fa-check';
                }
                this.showToast('Chart image copied.', 'fas fa-copy');
                setTimeout(() => {
                    if (button) {
                        button.classList.remove('dm-copied');
                        button.disabled = false;
                        button.setAttribute('aria-label', 'Copy chart image');
                        button.setAttribute('title', 'Copy image');
                    }
                    if (icon) {
                        icon.className = originalIconClass || 'fas fa-copy';
                    }
                }, 1600);
            } catch (err) {
                if (button) {
                    button.disabled = false;
                }
                if (icon) {
                    icon.className = originalIconClass || 'fas fa-copy';
                }
                this.showToast('Could not copy image. Please download the JPEG instead.', 'fas fa-exclamation-circle');
            }
        },

        async downloadRawDataChartJpeg() {
            const chartCanvas = document.createElement('canvas');
            const didDraw = this.drawRawDataRunChart(chartCanvas);
            if (!didDraw) return;
            const exportCanvas = await this.buildBrandedRawDataChartExport(chartCanvas);
            const link = document.createElement('a');
            const slug = String(this.state.currentMeasure || 'raw-data-run-chart')
                .replace(/[^a-z0-9]+/gi, '-')
                .replace(/^-|-$/g, '')
                .toLowerCase();
            link.href = exportCanvas.toDataURL('image/jpeg', 0.92);
            link.download = `${slug}-run-chart.jpg`;
            document.body.appendChild(link);
            link.click();
            link.remove();
        },

        downloadRawDataXlsx() {
            if (typeof XLSX === 'undefined') {
                alert('Excel export is not available yet. Please refresh the page and try again.');
                return;
            }
            const config = this.rawDataChartConfig() || {};
            const rows = this.filteredRawDataRows();
            if (!rows.length) return;

            const headers = ['Year'];
            if (config.periodLabel) {
                headers.push(config.periodLabel);
            }
            if (config.valueField === 'median_minutes') {
                headers.push(config.valueLabel || 'Value');
            } else {
                headers.push(config.numLabel || 'Num', config.denLabel || 'Denom', 'Rate');
            }

            const sheetRows = [
                headers,
                ...rows.map(row => {
                    const output = [row.year || ''];
                    if (config.periodLabel) {
                        output.push(row.period || '');
                    }
                    if (config.valueField === 'median_minutes') {
                        output.push(row.median_minutes || '');
                    } else {
                        output.push(row.num || '', row.den || '', row.rate || '');
                    }
                    return output;
                })
            ];
            const worksheet = XLSX.utils.aoa_to_sheet(sheetRows);
            worksheet['!cols'] = headers.map(header => ({
                wch: Math.max(12, Math.min(48, String(header).length + 8))
            }));
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Raw data');
            const slug = String(this.state.currentMeasure || 'raw-data')
                .replace(/[^a-z0-9]+/gi, '-')
                .replace(/^-|-$/g, '')
                .toLowerCase();
            XLSX.writeFile(workbook, `${slug}-raw-data.xlsx`);
        },

        measureKey(measure = null) {
            return String(measure || this.state.currentMeasure || '')
                .replace(/[—/:]+/g, '-')
                .replace(/[^a-z0-9]+/gi, '-')
                .replace(/^-|-$/g, '')
                .toLowerCase()
                .replace(/-/g, '_');
        },

        reportOwnershipScope(measure = null, subfolder = undefined, category = undefined) {
            const targetMeasure = measure || this.state.currentMeasure || '';
            const sub = subfolder === undefined ? (this.state.currentSubfolder || {}) : (subfolder || {});
            const cat = category === undefined ? (this.state.currentCategory || {}) : (category || {});
            return {
                module_key: 'mbqip',
                event_type_key: this.subfolderRouteSlug(sub) || this.categoryRouteSlug(cat) || 'mbqip',
                measure_key: this.measureKey(targetMeasure),
                measure_name: targetMeasure
            };
        },

        reportOwnershipScopeKey(scope = null) {
            const target = scope || this.reportOwnershipScope();
            return [target.module_key, target.event_type_key, target.measure_key].join('|');
        },

        isMbqipReportMeasure(measure = null) {
            const targetMeasure = measure || this.state.currentMeasure || '';
            return !!targetMeasure
                && this.state.currentCategory
                && this.state.currentCategory.id === 'mbqip'
                && !this.isImprovementCalculatorMeasure(targetMeasure);
        },

        mbqipOwnershipRows() {
            const mbqipCategory = DM_DATA.find(cat => cat.id === 'mbqip') || null;
            if (!mbqipCategory || !Array.isArray(mbqipCategory.subfolders)) return [];
            return mbqipCategory.subfolders.flatMap(subfolder => (subfolder.measures || [])
                .filter(measure => this.measureCoverageAllows('mbqip', measure))
                .map(measure => {
                const scope = this.reportOwnershipScope(measure, subfolder, mbqipCategory);
                return {
                    group: subfolder.name || 'MBQIP',
                    measure,
                    scope,
                    scopeKey: this.reportOwnershipScopeKey(scope)
                };
            }));
        },

        hacsHaisOwnershipScope(measureId = '', measureName = '') {
            const normalizedId = String(measureId || '').trim();
            const measure = DM_IMPROVEMENT_CALCULATOR_MEASURES.find(item => item.id === normalizedId);
            const resolvedName = measureName || (measure ? measure.label : normalizedId);
            return {
                module_key: 'hacs_hais',
                event_type_key: 'hacs-hais',
                measure_key: `hacs_hais_${normalizedId}`,
                measure_name: resolvedName
            };
        },

        hacsHaisOwnershipRows() {
            return DM_IMPROVEMENT_CALCULATOR_MEASURES
                .filter(measure => this.measureCoverageAllows('hacs-hais', measure.id))
                .map(measure => {
                const measureId = String(measure.id || '').trim();
                const measureName = measure.label || measure.name || measureId;
                const scope = this.hacsHaisOwnershipScope(measureId, measureName);
                return {
                    group: 'HACs & HAIs',
                    measure: measureName,
                    scope,
                    scopeKey: this.reportOwnershipScopeKey(scope)
                };
            });
        },

        dataOwnershipRows() {
            return [
                ...this.mbqipOwnershipRows(),
                ...this.hacsHaisOwnershipRows()
            ];
        },

        reportOwnershipFormData(action, scope, ownerUserId = null) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', DM_CONFIG.nonce);
            formData.append('module_key', scope.module_key);
            formData.append('event_type_key', scope.event_type_key);
            formData.append('measure_key', scope.measure_key);
            formData.append('measure_name', scope.measure_name);
            if (ownerUserId !== null) {
                formData.append('owner_user_id', ownerUserId || '0');
            }
            return formData;
        },

        fetchReportOwnership(scope) {
            return fetch(DM_CONFIG.ajax_url, {
                method: 'POST',
                body: this.reportOwnershipFormData('qualinav_data_ownership_load', scope)
            }).then(r => r.json());
        },

        loadReportOwnership(measure = null) {
            const targetMeasure = measure || this.state.currentMeasure;
            if (!this.isMbqipReportMeasure(targetMeasure)) return;
            const scope = this.reportOwnershipScope(targetMeasure);

            this.fetchReportOwnership(scope)
                .then(data => {
                    if (!data.success) return;
                    const ownership = data.data && data.data.ownership ? data.data.ownership : {};
                    const users = data.data && Array.isArray(data.data.users) ? data.data.users : [];
                    const ownerships = { ...(this.state.reportOwnership || {}) };
                    ownerships[this.reportOwnershipScopeKey(scope)] = ownership;
                    const stateUpdate = {
                        reportOwnership: ownerships,
                        reportOwnershipStatus: ''
                    };
                    if (users.length) {
                        stateUpdate.reportOwnershipUsers = users;
                    }
                    this.setState(stateUpdate, { preserveScroll: true, scrollToTop: false });
                })
                .catch(() => {});
        },

        loadGeneralReportOwnership() {
            if (this.state.generalOwnershipLoading) return;
            const rows = this.dataOwnershipRows();
            if (!rows.length) {
                this.setState({ generalOwnershipLoaded: true, generalOwnershipLoading: false }, { preserveScroll: true, scrollToTop: false });
                return;
            }
            this.setState({ generalOwnershipLoading: true }, { preserveScroll: true, scrollToTop: false });
            Promise.all(rows.map(row => this.fetchReportOwnership(row.scope).catch(() => null)))
                .then(results => {
                    const ownerships = { ...(this.state.reportOwnership || {}) };
                    let users = this.state.reportOwnershipUsers || [];
                    results.forEach((data, index) => {
                        if (!data || !data.success) return;
                        const row = rows[index];
                        ownerships[row.scopeKey] = data.data && data.data.ownership ? data.data.ownership : {};
                        if (data.data && Array.isArray(data.data.users) && data.data.users.length) {
                            users = data.data.users;
                        }
                    });
                    this.setState({
                        reportOwnership: ownerships,
                        reportOwnershipUsers: users,
                        generalOwnershipLoaded: true,
                        generalOwnershipLoading: false
                    }, { preserveScroll: true, scrollToTop: false });
                })
                .catch(() => {
                    this.setState({
                        generalOwnershipLoaded: false,
                        generalOwnershipLoading: false
                    }, { preserveScroll: true, scrollToTop: false });
                });
        },

        loadHacsHaisReportOwnership() {
            if (this.state.hacsHaisOwnershipLoading) return;
            const rows = this.hacsHaisOwnershipRows();
            if (!rows.length) return;
            this.setState({ hacsHaisOwnershipLoading: true }, { preserveScroll: true, scrollToTop: false });
            Promise.all(rows.map(row => this.fetchReportOwnership(row.scope).catch(() => null)))
                .then(results => {
                    const ownerships = { ...(this.state.reportOwnership || {}) };
                    let users = this.state.reportOwnershipUsers || [];
                    results.forEach((data, index) => {
                        if (!data || !data.success) return;
                        const row = rows[index];
                        ownerships[row.scopeKey] = data.data && data.data.ownership ? data.data.ownership : {};
                        if (data.data && Array.isArray(data.data.users) && data.data.users.length) {
                            users = data.data.users;
                        }
                    });
                    this.setState({
                        reportOwnership: ownerships,
                        reportOwnershipUsers: users,
                        hacsHaisOwnershipLoading: false,
                        hacsHaisOwnershipLoaded: true
                    }, { preserveScroll: true, scrollToTop: false });
                })
                .catch(() => {
                    this.setState({
                        hacsHaisOwnershipLoading: false,
                        hacsHaisOwnershipLoaded: false
                    }, { preserveScroll: true, scrollToTop: false });
                });
        },

        saveReportOwnership(ownerUserId) {
            if (!this.isMbqipReportMeasure()) return;
            const scope = this.reportOwnershipScope();
            this.saveReportOwnershipForScope(scope, ownerUserId);
        },

        saveReportOwnershipForScope(scope, ownerUserId, statusKey = '') {
            const targetStatusKey = statusKey || this.reportOwnershipScopeKey(scope);
            const statuses = { ...(this.state.reportOwnershipStatuses || {}) };
            statuses[targetStatusKey] = 'Saving...';
            const stateUpdate = { reportOwnershipStatuses: statuses };
            if (!statusKey) {
                stateUpdate.reportOwnershipStatus = 'Saving...';
            }
            this.setState(stateUpdate, { preserveScroll: true, scrollToTop: false });

            fetch(DM_CONFIG.ajax_url, {
                method: 'POST',
                body: this.reportOwnershipFormData('qualinav_data_ownership_save', scope, ownerUserId)
            })
                .then(r => r.json())
                .then(data => {
                    const nextStatuses = { ...(this.state.reportOwnershipStatuses || {}) };
                    if (!data.success) {
                        const errorMessage = (data.data && data.data.message) ? data.data.message : (typeof data.data === 'string' ? data.data : 'Could not save owner.');
                        nextStatuses[targetStatusKey] = errorMessage;
                        const errorState = { reportOwnershipStatuses: nextStatuses };
                        if (!statusKey) {
                            errorState.reportOwnershipStatus = errorMessage;
                        }
                        this.setState(errorState, { preserveScroll: true, scrollToTop: false });
                        return;
                    }
                    const ownership = data.data && data.data.ownership ? data.data.ownership : {};
                    const ownerships = { ...(this.state.reportOwnership || {}) };
                    ownerships[this.reportOwnershipScopeKey(scope)] = ownership;
                    nextStatuses[targetStatusKey] = statusKey ? 'Saved' : (data.data && data.data.message ? data.data.message : 'Saved');
                    const successState = {
                        reportOwnership: ownerships,
                        reportOwnershipStatuses: nextStatuses
                    };
                    if (data.data && Array.isArray(data.data.users) && data.data.users.length) {
                        successState.reportOwnershipUsers = data.data.users;
                    }
                    if (!statusKey) {
                        successState.reportOwnershipStatus = nextStatuses[targetStatusKey];
                    }
                    this.setState(successState, { preserveScroll: true, scrollToTop: false });
                })
                .catch(() => {
                    const nextStatuses = { ...(this.state.reportOwnershipStatuses || {}) };
                    nextStatuses[targetStatusKey] = 'Could not save owner.';
                    const errorState = { reportOwnershipStatuses: nextStatuses };
                    if (!statusKey) {
                        errorState.reportOwnershipStatus = 'Could not save owner.';
                    }
                    this.setState(errorState, { preserveScroll: true, scrollToTop: false });
                });
        },

        saveGeneralReportOwnership(scopeKey, ownerUserId) {
            this.saveDataOwnership(scopeKey, ownerUserId);
        },

        saveDataOwnership(scopeKey, ownerUserId) {
            const row = this.dataOwnershipRows().find(item => item.scopeKey === scopeKey);
            if (!row) return;
            this.saveReportOwnershipForScope(row.scope, ownerUserId, scopeKey);
        },

        reportOwnershipOptionsHtml(selectedOwner = '0') {
            const users = this.state.reportOwnershipUsers || [];
            const normalizedOwner = String(selectedOwner || 0);
            const hasSelectedUser = users.some(user => String(user.id || 0) === normalizedOwner);
            const options = [
                `<option value="0" ${normalizedOwner === '0' ? 'selected' : ''}>Unassigned</option>`,
                ...users.map(user => {
                    const value = String(user.id || 0);
                    return `<option value="${this.escapeHtml(value)}" ${value === normalizedOwner ? 'selected' : ''}>${this.escapeHtml(user.label || user.email || `User ${value}`)}</option>`;
                })
            ];
            if (normalizedOwner !== '0' && !hasSelectedUser) {
                options.push(`<option value="${this.escapeHtml(normalizedOwner)}" selected>User ${this.escapeHtml(normalizedOwner)}</option>`);
            }
            return options.join('');
        },

        renderDataOwnershipControl(scope, options = {}) {
            if (!scope) return '';
            const scopeKey = this.reportOwnershipScopeKey(scope);
            const ownership = (this.state.reportOwnership || {})[this.reportOwnershipScopeKey(scope)] || {};
            const selectedOwner = String(ownership.owner_user_id || 0);
            const controlId = options.controlId || 'dmReportOwner';
            const saveHandler = options.saveHandler || 'saveReportOwnership';
            const saveArg = saveHandler === 'saveReportOwnership'
                ? ''
                : `'${this.escapeHtml(scopeKey)}', `;
            return `
                <div class="dm-report-owner-control">
                    <label for="${this.escapeHtml(controlId)}">Owner</label>
                    <select id="${this.escapeHtml(controlId)}" onchange="dmApp.${saveHandler}(${saveArg}this.value)">
                        ${this.reportOwnershipOptionsHtml(selectedOwner)}
                    </select>
                </div>
            `;
        },

        renderReportOwnershipControl() {
            if (!this.isMbqipReportMeasure()) return '';
            return this.renderDataOwnershipControl(this.reportOwnershipScope());
        },

        renderHacsHaisOwnershipControl(measureId, measureName) {
            const scope = this.hacsHaisOwnershipScope(measureId, measureName);
            const scopeKey = this.reportOwnershipScopeKey(scope);
            return this.renderDataOwnershipControl(scope, {
                controlId: `dmHacsHaisOwner-${measureId}`,
                saveHandler: 'saveDataOwnership',
                statusKey: scopeKey
            });
        },

        renderMeasureTabs() {
            return `
                <div class="dm-tabs-row">
                    <div class="dm-tabs">
                        <div class="dm-tab ${this.state.inputTab !== 'database' ? 'active' : ''}" onclick="dmApp.setTab('entry')">Build Report</div>
                        <div class="dm-tab ${this.state.inputTab === 'database' ? 'active' : ''}" onclick="dmApp.setTab('database')">View Report</div>
                    </div>
                    ${this.renderReportOwnershipControl()}
                </div>
            `;
        },

        measureGoalDirection(measure = null) {
            const name = String(measure || this.state.currentMeasure || '');
            if (
                name === DM_OP22_MEASURE ||
                name === DM_OP18_MEASURE ||
                name === DM_HWR_MEASURE ||
                name === DM_SAFE_USE_OPIOIDS_MEASURE
            ) {
                return 'lower';
            }
            return 'higher';
        },

        measureSupportsNumberNeededGoal(measure = null) {
            const name = String(measure || this.state.currentMeasure || '');
            return [
                DM_SAFE_USE_OPIOIDS_MEASURE,
                DM_EDTC_MEASURE,
                DM_OP18_MEASURE,
                DM_OP22_MEASURE
            ].includes(name);
        },

        measureUsesPercentReductionGoal(measure = null) {
            const name = String(measure || this.state.currentMeasure || '');
            return [
                DM_SAFE_USE_OPIOIDS_MEASURE,
                DM_OP18_MEASURE,
                DM_OP22_MEASURE
            ].includes(name);
        },

        currentMeasureSourceRow() {
            const rows = this.filteredRawDataRows();
            return rows.length ? rows[0] : null;
        },

        currentMeasureRateValue() {
            const config = this.rawDataChartConfig() || {};
            const latest = this.currentMeasureSourceRow() || {};
            if (config.valueField === 'median_minutes') {
                const value = this.parseNumberValue(latest.median_minutes);
                return Number.isFinite(value) ? value : '';
            }
            const rate = this.parsePercentValue(latest.rate);
            return Number.isFinite(rate) ? rate : '';
        },

        currentMeasureRateDisplay() {
            const value = this.currentMeasureRateValue();
            if (value === '') return 'No saved data yet';
            const config = this.rawDataChartConfig() || {};
            return this.chartValueLabel(value, config);
        },

        calculateMeasureGoalDifference(currentRate, goalRate, direction = null, measure = null) {
            const current = this.parseNumberValue(currentRate);
            const goal = this.parseNumberValue(goalRate);
            if (!Number.isFinite(current) || !Number.isFinite(goal)) return '';
            if (this.measureUsesPercentReductionGoal(measure) && current > 0) {
                return Math.max(0, ((current - goal) / current) * 100);
            }
            return (direction || this.measureGoalDirection()) === 'lower'
                ? Math.max(0, current - goal)
                : Math.max(0, goal - current);
        },

        measureGoalDifferenceDisplay() {
            const key = this.measureKey();
            const goal = (this.state.measureGoals || {})[key] || {};
            const current = this.currentMeasureRateValue();
            const goalRate = goal.goal_rate ?? '';
            const diff = this.calculateMeasureGoalDifference(current, goalRate, goal.direction || this.measureGoalDirection(), this.state.currentMeasure);
            if (diff === '') return '-';
            if (this.measureUsesPercentReductionGoal()) return this.formatPercent(diff, 1);
            return this.chartValueLabel(diff, this.rawDataChartConfig() || {});
        },

        measureGoalNumberNeededDisplay(goal = null) {
            if (!this.measureSupportsNumberNeededGoal()) return '';
            const activeGoal = goal || (this.state.measureGoals || {})[this.measureKey()] || {};
            const latest = this.currentMeasureSourceRow();
            if (!latest) return '-';

            const currentRate = this.parseNumberValue(this.currentMeasureRateValue());
            const goalRate = this.parseNumberValue(activeGoal.goal_rate);
            if (!Number.isFinite(currentRate) || !Number.isFinite(goalRate)) return '-';

            if (this.isOp18Measure()) {
                const minutesNeeded = Math.max(0, Math.round(currentRate - goalRate));
                return `Reduce ${minutesNeeded} min`;
            }

            const num = this.parseNumberValue(latest.num);
            const den = this.parseNumberValue(latest.den);
            if (this.isEdtcMeasure()) {
                if (!Number.isFinite(num) || !Number.isFinite(den)) return '-';
                const additionalNeeded = Math.max(0, Math.round(((goalRate / 100) * den) - num));
                return `Add ${additionalNeeded} numerator event${additionalNeeded === 1 ? '' : 's'}`;
            }

            if (!Number.isFinite(num) || currentRate <= 0) return '-';
            const reductionFraction = Math.max(0, (currentRate - goalRate) / currentRate);
            const eventsNeeded = Math.max(0, Math.round(num * reductionFraction));
            return `Prevent ${eventsNeeded} event${eventsNeeded === 1 ? '' : 's'}`;
        },

        measureGoalValuesAreComplete(startDate, endDate, currentRate, goalRate, difference) {
            const current = this.parseNumberValue(currentRate);
            const goal = this.parseNumberValue(goalRate);
            return startDate !== ''
                && endDate !== ''
                && !this.measureGoalDateRangeError(startDate, endDate)
                && Number.isFinite(current)
                && Number.isFinite(goal)
                && difference !== ''
                && Number.isFinite(this.parseNumberValue(difference));
        },

        measureGoalDateRangeError(startDate, endDate) {
            if (!startDate || !endDate) return '';
            return endDate < startDate ? 'End date must be on or after start date.' : '';
        },

        measureGoalDraftIsComplete(goal = null) {
            const key = this.measureKey();
            const activeGoal = goal || (this.state.measureGoals || {})[key] || {};
            const currentRate = this.currentMeasureRateValue();
            const goalRate = activeGoal.goal_rate ?? '';
            const difference = this.calculateMeasureGoalDifference(
                currentRate,
                goalRate,
                activeGoal.direction || this.measureGoalDirection(),
                this.state.currentMeasure
            );
            return this.measureGoalValuesAreComplete(
                this.parseUsDate(activeGoal.start_date || ''),
                this.parseUsDate(activeGoal.end_date || ''),
                currentRate,
                goalRate,
                difference
            );
        },

        syncMeasureGoalSaveButton() {
            const saveBtn = document.getElementById('dmGoalSaveBtn');
            if (!saveBtn) return;
            const startDate = this.parseUsDate((document.getElementById('dmGoalStartDate') || {}).value || '');
            const endDate = this.parseUsDate((document.getElementById('dmGoalEndDate') || {}).value || '');
            const goalRate = String((document.getElementById('dmGoalRate') || {}).value || '').replace('%', '').trim();
            const currentRate = this.currentMeasureRateValue();
            const difference = this.calculateMeasureGoalDifference(currentRate, goalRate, this.measureGoalDirection(), this.state.currentMeasure);
            saveBtn.disabled = !this.measureGoalValuesAreComplete(startDate, endDate, currentRate, goalRate, difference);
        },

        loadMeasureGoal(measure = null) {
            const targetMeasure = measure || this.state.currentMeasure;
            if (!targetMeasure || this.isImprovementCalculatorMeasure(targetMeasure)) return;
            const formData = new FormData();
            formData.append('action', 'qualinav_mbqip_goal_load');
            formData.append('nonce', DM_CONFIG.nonce);
            formData.append('measure_name', targetMeasure);
            formData.append('measure_key', this.measureKey(targetMeasure));

            fetch(DM_CONFIG.ajax_url, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const goal = data.data && data.data.goal ? data.data.goal : null;
                    const allGoals = data.data && Array.isArray(data.data.goals) ? data.data.goals : [];
                    const key = this.measureKey(targetMeasure);
                    const goals = { ...(this.state.measureGoals || {}) };
                    const history = { ...(this.state.measureGoalHistory || {}) };
                    history[key] = allGoals;
                    if (goal) {
                        goals[key] = goal;
                    } else {
                        goals[key] = {
                            start_date: '',
                            end_date: '',
                            goal_rate: '',
                            direction: this.measureGoalDirection(targetMeasure)
                        };
                    }
                    this.setState({ measureGoals: goals, measureGoalHistory: history, measureGoalStatus: '' }, { preserveScroll: true, scrollToTop: false });
                })
                .catch(() => {});
        },

        saveMeasureGoal(event) {
            if (event && event.preventDefault) event.preventDefault();
            const measure = this.state.currentMeasure;
            if (!measure) return;
            const key = this.measureKey(measure);
            const direction = this.measureGoalDirection(measure);
            const startDate = this.parseUsDate((document.getElementById('dmGoalStartDate') || {}).value || '');
            const endDate = this.parseUsDate((document.getElementById('dmGoalEndDate') || {}).value || '');
            const goalRate = String((document.getElementById('dmGoalRate') || {}).value || '').replace('%', '').trim();
            const currentRate = this.currentMeasureRateValue();
            const difference = this.calculateMeasureGoalDifference(currentRate, goalRate, direction, measure);
            const dateError = this.measureGoalDateRangeError(startDate, endDate);
            if (dateError) {
                this.setState({ measureGoalStatus: dateError }, { preserveScroll: true, scrollToTop: false });
                return;
            }
            if (!this.measureGoalValuesAreComplete(startDate, endDate, currentRate, goalRate, difference)) {
                this.setState({ measureGoalStatus: 'Complete all goal fields before saving.' }, { preserveScroll: true, scrollToTop: false });
                return;
            }

            const formData = new FormData();
            formData.append('action', 'qualinav_mbqip_goal_save');
            formData.append('nonce', DM_CONFIG.nonce);
            formData.append('measure_name', measure);
            formData.append('measure_key', key);
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);
            formData.append('current_rate', currentRate === '' ? '' : String(currentRate));
            formData.append('goal_rate', goalRate);
            formData.append('difference_needed', difference === '' ? '' : String(difference));
            formData.append('direction', direction);

            const draftGoals = { ...(this.state.measureGoals || {}) };
            draftGoals[key] = {
                ...(draftGoals[key] || {}),
                start_date: startDate,
                end_date: endDate,
                current_rate: currentRate,
                goal_rate: goalRate,
                difference_needed: difference,
                direction
            };
            this.setState({ measureGoals: draftGoals, measureGoalStatus: 'Saving goal...' }, { preserveScroll: true, scrollToTop: false });
            fetch(DM_CONFIG.ajax_url, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        this.setState({ measureGoalStatus: data.data || 'Could not save goal.' }, { preserveScroll: true, scrollToTop: false });
                        return;
                    }
                    const goals = { ...(this.state.measureGoals || {}) };
                    const history = { ...(this.state.measureGoalHistory || {}) };
                    const allGoals = data.data && Array.isArray(data.data.goals) ? data.data.goals : null;
                    goals[key] = data.data && data.data.goal ? data.data.goal : {
                        start_date: startDate,
                        end_date: endDate,
                        current_rate: currentRate,
                        goal_rate: goalRate,
                        difference_needed: difference,
                        direction
                    };
                    const savedGoal = goals[key];
                    if (allGoals) {
                        history[key] = allGoals;
                    } else {
                        const existingHistory = Array.isArray(history[key]) ? history[key] : [];
                        history[key] = [savedGoal, ...existingHistory.filter(item => Number(item.id) !== Number(savedGoal.id))];
                    }
                    this.setState({ measureGoals: goals, measureGoalHistory: history, measureGoalStatus: data.data.message || 'Goal saved.' }, { preserveScroll: true, scrollToTop: false });
                })
                .catch(err => this.setState({ measureGoalStatus: 'Could not save goal: ' + err.message }, { preserveScroll: true, scrollToTop: false }));
        },

        updateMeasureGoalDraft(field, value) {
            const key = this.measureKey();
            const goals = { ...(this.state.measureGoals || {}) };
            goals[key] = {
                ...(goals[key] || {}),
                [field]: value,
                direction: this.measureGoalDirection()
            };
            this.setState({ measureGoals: goals, measureGoalStatus: '' }, { preserveScroll: true, scrollToTop: false });
        },

        updateMeasureGoalDraftInPlace(field, value) {
            const key = this.measureKey();
            const goals = this.state.measureGoals || {};
            goals[key] = {
                ...(goals[key] || {}),
                [field]: value,
                direction: this.measureGoalDirection()
            };
            this.state.measureGoals = goals;
            this.state.measureGoalStatus = '';
        },

        syncMeasureGoalDifference() {
            const goalInput = document.getElementById('dmGoalRate');
            const diffInput = document.getElementById('dmGoalDifference');
            const numberNeededInput = document.getElementById('dmGoalNumberNeeded');
            if (!goalInput || !diffInput) return;
            const diff = this.calculateMeasureGoalDifference(this.currentMeasureRateValue(), goalInput.value, this.measureGoalDirection(), this.state.currentMeasure);
            diffInput.value = diff === ''
                ? '-'
                : (this.measureUsesPercentReductionGoal() ? this.formatPercent(diff, 1) : this.chartValueLabel(diff, this.rawDataChartConfig() || {}));
            if (numberNeededInput) {
                numberNeededInput.value = this.measureGoalNumberNeededDisplay();
            }
            this.syncMeasureGoalSaveButton();
        },

        openGoalDatePicker(displayId, field, mode = 'measure', measureId = '') {
            const display = document.getElementById(displayId);
            const popover = document.getElementById('dmGoalDatePickerPopover');
            if (!display || !popover) return;
            const selectedIso = this.parseUsDate(display.value) || this.todayIsoDate();
            const selectedDate = this.isoDateToLocalDate(selectedIso) || new Date();
            this.goalDatePickerContext = {
                displayId,
                field,
                mode,
                measureId,
                selectedIso,
                viewYear: selectedDate.getFullYear(),
                viewMonth: selectedDate.getMonth()
            };
            this.renderGoalDatePicker();
        },

        closeGoalDatePicker() {
            const popover = document.getElementById('dmGoalDatePickerPopover');
            if (popover) popover.style.display = 'none';
            this.goalDatePickerContext = null;
        },

        isoDateToLocalDate(value) {
            const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (!match) return null;
            const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
            return Number.isNaN(date.getTime()) ? null : date;
        },

        localDateToIso(date) {
            if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        },

        goalDateMonthLabel(year, month) {
            const date = new Date(Number(year), Number(month), 1);
            return date.toLocaleString('en-US', { month: 'long', year: 'numeric' });
        },

        renderGoalDatePicker() {
            const ctx = this.goalDatePickerContext;
            const popover = document.getElementById('dmGoalDatePickerPopover');
            const display = ctx ? document.getElementById(ctx.displayId) : null;
            if (!ctx || !popover || !display) return;
            const todayIso = this.todayIsoDate();
            const first = new Date(ctx.viewYear, ctx.viewMonth, 1);
            const gridStart = new Date(ctx.viewYear, ctx.viewMonth, 1 - first.getDay());
            const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            const currentYear = new Date().getFullYear();
            const selectedYear = Number(ctx.viewYear) || currentYear;
            const startYear = Math.min(currentYear - 15, selectedYear - 5);
            const endYear = Math.max(currentYear + 5, selectedYear + 5);
            const yearOptions = [];
            for (let year = endYear; year >= startYear; year--) {
                yearOptions.push(`<option value="${year}" ${year === selectedYear ? 'selected' : ''}>${year}</option>`);
            }
            const monthOptions = Array.from({ length: 12 }, (_, month) => {
                const label = new Date(2000, month, 1).toLocaleString('en-US', { month: 'long' });
                return `<option value="${month}" ${month === Number(ctx.viewMonth) ? 'selected' : ''}>${this.escapeHtml(label)}</option>`;
            });
            const days = [];
            for (let i = 0; i < 42; i++) {
                const date = new Date(gridStart);
                date.setDate(gridStart.getDate() + i);
                const iso = this.localDateToIso(date);
                const classes = [
                    'dm-goal-date-day',
                    date.getMonth() === ctx.viewMonth ? '' : 'is-muted',
                    iso === ctx.selectedIso ? 'is-selected' : '',
                    iso === todayIso ? 'is-today' : ''
                ].filter(Boolean).join(' ');
                days.push(`<button type="button" class="${classes}" onclick="event.stopPropagation(); dmApp.selectGoalDate('${iso}')">${date.getDate()}</button>`);
            }
            popover.innerHTML = `
                <div class="dm-goal-date-popover-head">
                    <div class="dm-goal-date-title">
                        <select class="dm-goal-date-month" aria-label="Choose month" onchange="event.stopPropagation(); dmApp.setGoalDateMonth(this.value)" onclick="event.stopPropagation()">${monthOptions.join('')}</select>
                        <select class="dm-goal-date-year" aria-label="Choose year" onchange="event.stopPropagation(); dmApp.setGoalDateYear(this.value)" onclick="event.stopPropagation()">${yearOptions.join('')}</select>
                    </div>
                </div>
                <div class="dm-goal-date-weekdays">${weekdays.map(day => `<div>${day}</div>`).join('')}</div>
                <div class="dm-goal-date-grid">${days.join('')}</div>
                <div class="dm-goal-date-quick">
                    <button type="button" onclick="event.stopPropagation(); dmApp.selectGoalDate(dmApp.todayIsoDate())">Today</button>
                    <button type="button" onclick="event.stopPropagation(); dmApp.clearGoalDate()">Clear</button>
                </div>
            `;
            this.positionGoalDatePicker();
        },

        positionGoalDatePicker() {
            const ctx = this.goalDatePickerContext;
            const popover = document.getElementById('dmGoalDatePickerPopover');
            const display = ctx ? document.getElementById(ctx.displayId) : null;
            if (!ctx || !popover || !display) return;
            popover.style.display = 'block';
            const rect = display.getBoundingClientRect();
            const popRect = popover.getBoundingClientRect();
            const margin = 12;
            const left = Math.min(Math.max(margin, rect.left), window.innerWidth - popRect.width - margin);
            const below = rect.bottom + 8;
            const above = rect.top - popRect.height - 8;
            const top = below + popRect.height < window.innerHeight - margin ? below : Math.max(margin, above);
            popover.style.left = `${left}px`;
            popover.style.top = `${top}px`;
        },

        shiftGoalDateMonth(delta) {
            const ctx = this.goalDatePickerContext;
            if (!ctx) return;
            const view = new Date(ctx.viewYear, ctx.viewMonth + Number(delta || 0), 1);
            ctx.viewYear = view.getFullYear();
            ctx.viewMonth = view.getMonth();
            this.renderGoalDatePicker();
        },

        setGoalDateMonth(month) {
            const ctx = this.goalDatePickerContext;
            const parsedMonth = Number(month);
            if (!ctx || !Number.isFinite(parsedMonth) || parsedMonth < 0 || parsedMonth > 11) return;
            ctx.viewMonth = parsedMonth;
            this.renderGoalDatePicker();
        },

        setGoalDateYear(year) {
            const ctx = this.goalDatePickerContext;
            const parsedYear = Number(year);
            if (!ctx || !Number.isFinite(parsedYear)) return;
            ctx.viewYear = parsedYear;
            this.renderGoalDatePicker();
        },

        selectGoalDate(isoValue) {
            const ctx = this.goalDatePickerContext;
            const iso = this.parseUsDate(isoValue);
            if (!ctx || !iso) return;
            const display = document.getElementById(ctx.displayId);
            if (display) display.value = this.formatUsDate(iso);
            if (ctx.mode === 'improvement') {
                this.updateImprovementMeasureGoalDraft(ctx.measureId, ctx.field, iso);
            } else {
                this.updateMeasureGoalDraft(ctx.field, iso);
                setTimeout(() => this.syncMeasureGoalSaveButton(), 0);
            }
            this.closeGoalDatePicker();
        },

        clearGoalDate() {
            const ctx = this.goalDatePickerContext;
            if (!ctx) return;
            const display = document.getElementById(ctx.displayId);
            if (display) display.value = '';
            if (ctx.mode === 'improvement') {
                this.updateImprovementMeasureGoalDraft(ctx.measureId, ctx.field, '');
            } else {
                this.updateMeasureGoalDraft(ctx.field, '');
                setTimeout(() => this.syncMeasureGoalSaveButton(), 0);
            }
            this.closeGoalDatePicker();
        },

        setMeasureGoalTab(tab) {
            this.setState({ measureGoalTab: tab === 'past' ? 'past' : 'current' }, { preserveScroll: true, scrollToTop: false });
        },

        renderGoalArchiveButton(goal, goalKey) {
            const goalId = Number(goal && goal.id);
            if (!Number.isFinite(goalId) || goalId <= 0) return '';
            return `
                <button type="button" class="dm-goal-delete-btn" title="Remove past goal" aria-label="Remove past goal" data-goal-key="${this.escapeHtml(goalKey)}" onclick="dmApp.openGoalArchiveModal(${goalId}, this.dataset.goalKey)">
                    <i class="fas fa-trash-alt"></i>
                </button>
            `;
        },

        openGoalArchiveModal(goalId, goalKey) {
            this.pendingGoalArchive = {
                goalId: Number(goalId),
                goalKey: String(goalKey || '')
            };
            const modal = document.getElementById('dmGoalArchiveModal');
            if (modal) modal.style.display = 'flex';
        },

        closeGoalArchiveModal() {
            this.pendingGoalArchive = null;
            const modal = document.getElementById('dmGoalArchiveModal');
            if (modal) modal.style.display = 'none';
        },

        confirmArchiveMeasureGoal() {
            const pending = this.pendingGoalArchive || {};
            const goalId = Number(pending.goalId);
            const goalKey = String(pending.goalKey || '');
            if (!Number.isFinite(goalId) || goalId <= 0 || !goalKey) {
                this.closeGoalArchiveModal();
                return;
            }
            this.closeGoalArchiveModal();

            const formData = new FormData();
            formData.append('action', 'qualinav_mbqip_goal_archive');
            formData.append('nonce', DM_CONFIG.nonce);
            formData.append('goal_id', String(goalId));

            this.setState({ measureGoalStatus: 'Removing past goal...' }, { preserveScroll: true, scrollToTop: false });
            fetch(DM_CONFIG.ajax_url, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        this.setState({ measureGoalStatus: data.data || 'Could not remove past goal.' }, { preserveScroll: true, scrollToTop: false });
                        return;
                    }
                    const response = data.data || {};
                    const key = response.measure_key || goalKey;
                    const history = { ...(this.state.measureGoalHistory || {}) };
                    const goals = { ...(this.state.measureGoals || {}) };
                    const updatedHistory = Array.isArray(response.goals)
                        ? response.goals
                        : (Array.isArray(history[key]) ? history[key].filter(item => Number(item.id) !== goalId) : []);
                    history[key] = updatedHistory;
                    if (response.goal) {
                        goals[key] = response.goal;
                    } else if (Number(goals[key] && goals[key].id) === goalId || !goals[key]) {
                        goals[key] = {
                            start_date: '',
                            end_date: '',
                            goal_rate: '',
                            direction: key.indexOf('hacs_hais_') === 0 ? 'lower' : this.measureGoalDirection(this.state.currentMeasure)
                        };
                    }
                    this.setState({
                        measureGoals: goals,
                        measureGoalHistory: history,
                        measureGoalStatus: response.message || 'Past goal removed.'
                    }, { preserveScroll: true, scrollToTop: false });
                })
                .catch(err => this.setState({ measureGoalStatus: 'Could not remove past goal: ' + err.message }, { preserveScroll: true, scrollToTop: false }));
        },

        isPastMeasureGoal(goal) {
            if (!goal || !goal.end_date) return false;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const end = new Date(String(goal.end_date) + 'T00:00:00');
            return !Number.isNaN(end.getTime()) && end < today;
        },

        measureGoalMetLabel(goal) {
            const current = this.parseNumberValue(goal && goal.current_rate);
            const target = this.parseNumberValue(goal && goal.goal_rate);
            if (!Number.isFinite(current) || !Number.isFinite(target)) return 'Not assessed';
            const met = (goal.direction || 'higher') === 'lower' ? current <= target : current >= target;
            return met ? 'Met' : 'Not met';
        },

        measureGoalUserLabel(goal) {
            if (goal && goal.user_name) return goal.user_name;
            if (goal && goal.user_id) return `User ${goal.user_id}`;
            return '';
        },

        renderPastMeasureGoals(goals, escapeHtml) {
            const pastGoals = (Array.isArray(goals) ? goals : []).filter(goal => this.isPastMeasureGoal(goal));
            if (!pastGoals.length) {
                return '<div class="dm-measure-goals-body is-past"><div class="dm-guide" style="margin:0;">No past goals yet.</div></div>';
            }
            const valueLabel = (value) => value === '' || value === null || value === undefined
                ? '-'
                : this.chartValueLabel(value, this.rawDataChartConfig() || {});
            const differenceLabel = (value) => {
                if (value === '' || value === null || value === undefined) return '-';
                if (this.measureUsesPercentReductionGoal()) {
                    const number = this.parseNumberValue(value);
                    return Number.isFinite(number) ? this.formatPercent(number, 1) : '-';
                }
                return valueLabel(value);
            };
            const differenceHeader = this.measureUsesPercentReductionGoal() ? 'Percent Reduction' : 'Difference';
            const goalKey = this.measureKey();
            return `
                <div class="dm-measure-goals-body is-past">
                <table class="dm-goal-history-table">
                    <thead>
                        <tr>
                            <th>Start</th>
                            <th>End</th>
                            <th>Goal Set By</th>
                            <th>Current Rate</th>
                            <th>Goal Rate</th>
                            <th>${differenceHeader}</th>
                            <th>Status</th>
                            <th class="dm-goal-delete-cell"></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${pastGoals.map(goal => `
                            <tr>
                                <td>${escapeHtml(this.formatUsDate(goal.start_date || ''))}</td>
                                <td>${escapeHtml(this.formatUsDate(goal.end_date || ''))}</td>
                                <td>${escapeHtml(this.measureGoalUserLabel(goal))}</td>
                                <td>${escapeHtml(valueLabel(goal.current_rate))}</td>
                                <td>${escapeHtml(valueLabel(goal.goal_rate))}</td>
                                <td>${escapeHtml(differenceLabel(goal.difference_needed))}</td>
                                <td>${escapeHtml(this.measureGoalMetLabel(goal))}</td>
                                <td class="dm-goal-delete-cell">${this.renderGoalArchiveButton(goal, goalKey)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
                </div>
            `;
        },

        setImprovementMeasureGoalTab(measureId, tab) {
            const tabs = { ...(this.state.improvementMeasureGoalTabs || {}) };
            tabs[measureId] = tab === 'past' ? 'past' : 'current';
            this.setState({ improvementMeasureGoalTabs: tabs }, { preserveScroll: true, scrollToTop: false });
        },

        renderImprovementPastMeasureGoals(measureId, measure, escapeHtml) {
            const key = this.improvementGoalKey(measureId);
            const goals = ((this.state.measureGoalHistory || {})[key] || []).filter(goal => this.isPastMeasureGoal(goal));
            if (!goals.length) {
                return '<div class="dm-measure-goals-body is-past"><div class="dm-guide" style="margin:0;">No past goals yet.</div></div>';
            }
            const rateLabel = (value) => value === '' || value === null || value === undefined
                ? '-'
                : this.improvementGoalRateLabel(measure, value);
            const reductionLabel = (value) => {
                const number = this.parseNumberValue(value);
                return Number.isFinite(number) ? this.formatPercent(number, 1) : '-';
            };
            const numberNeededLabel = (goal) => {
                const current = this.parseNumberValue(goal.current_rate);
                const target = this.parseNumberValue(goal.goal_rate);
                const reductionFraction = Number.isFinite(current) && current > 0 && Number.isFinite(target)
                    ? Math.max(0, (current - target) / current)
                    : NaN;
                const latest = this.improvementLatestMeasureData(measureId);
                const eventCount = latest ? this.parseNumberValue(latest.eventCount) : NaN;
                return Number.isFinite(eventCount) && Number.isFinite(reductionFraction)
                    ? this.improvementGoalEventLabel(measure, eventCount * reductionFraction)
                    : '-';
            };
            return `
                <div class="dm-measure-goals-body is-past">
                <table class="dm-goal-history-table">
                    <thead>
                        <tr>
                            <th>Start</th>
                            <th>End</th>
                            <th>Goal Set By</th>
                            <th>Current Rate</th>
                            <th>Performance Goal</th>
                            <th>Reduction Needed</th>
                            <th>Number Needed</th>
                            <th>Status</th>
                            <th class="dm-goal-delete-cell"></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${goals.map(goal => `
                            <tr>
                                <td>${escapeHtml(this.formatUsDate(goal.start_date || ''))}</td>
                                <td>${escapeHtml(this.formatUsDate(goal.end_date || ''))}</td>
                                <td>${escapeHtml(this.measureGoalUserLabel(goal))}</td>
                                <td>${escapeHtml(rateLabel(goal.current_rate))}</td>
                                <td>${escapeHtml(rateLabel(goal.goal_rate))}</td>
                                <td>${escapeHtml(reductionLabel(goal.difference_needed))}</td>
                                <td>${escapeHtml(numberNeededLabel(goal))}</td>
                                <td>${escapeHtml(this.measureGoalMetLabel(goal))}</td>
                                <td class="dm-goal-delete-cell">${this.renderGoalArchiveButton(goal, key)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
                </div>
            `;
        },

        renderMeasureGoalsPanel() {
            if (!this.state.currentMeasure || this.isImprovementCalculatorMeasure()) return '';
            const escapeHtml = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[c]));
            const key = this.measureKey();
            const goal = (this.state.measureGoals || {})[key] || {};
            const allGoals = (this.state.measureGoalHistory || {})[key] || [];
            const direction = goal.direction || this.measureGoalDirection();
            const differenceLabel = this.measureUsesPercentReductionGoal()
                ? 'Percent Reduction Goal'
                : (direction === 'lower' ? 'Reduction Needed' : 'Difference Needed');
            const showNumberNeededGoal = this.measureSupportsNumberNeededGoal();
            const numberNeededLabel = this.isOp18Measure() ? 'Minutes Needed Goal' : 'Number Needed Goal';
            const goalRate = goal.goal_rate === undefined || goal.goal_rate === null ? '' : String(goal.goal_rate);
            const activeTab = this.state.measureGoalTab === 'past' ? 'past' : 'current';
            const title = `${this.state.currentMeasure} Goals`;
            const setByLabel = this.measureGoalUserLabel(goal);
            const dateRangeError = this.measureGoalDateRangeError(
                this.parseUsDate(goal.start_date || ''),
                this.parseUsDate(goal.end_date || '')
            );
            const canSaveGoal = this.measureGoalDraftIsComplete(goal);
            return `
                <section class="dm-measure-goals" aria-label="Measure specific goals">
                    <div class="dm-measure-goals-head">
                        <div>
                            <h2 class="dm-measure-goals-title">${escapeHtml(title)}</h2>
                            <div class="dm-measure-goals-status">${escapeHtml(this.state.measureGoalStatus || '')}</div>
                        </div>
                    </div>
                    <div class="dm-goal-tabs">
                        <div class="dm-goal-tab ${activeTab === 'current' ? 'active' : ''}" onclick="dmApp.setMeasureGoalTab('current')">Current goal</div>
                        <div class="dm-goal-tab ${activeTab === 'past' ? 'active' : ''}" onclick="dmApp.setMeasureGoalTab('past')">Past goals</div>
                    </div>
                    ${activeTab === 'past' ? this.renderPastMeasureGoals(allGoals, escapeHtml) : `<div class="dm-measure-goals-body is-current"><div class="dm-measure-goals-grid">
                        <div class="dm-goal-field">
                            <label for="dmGoalStartDate">Start Date</label>
                            <div class="dm-goal-date-control">
                                <input id="dmGoalStartDate" class="dm-goal-date-display" type="text" readonly placeholder="MM/DD/YYYY" value="${escapeHtml(this.formatUsDate(goal.start_date || ''))}" onclick="dmApp.openGoalDatePicker('dmGoalStartDate', 'start_date')">
                                <button type="button" class="dm-goal-date-trigger" aria-label="Choose start date" onclick="dmApp.openGoalDatePicker('dmGoalStartDate', 'start_date')"><i class="far fa-calendar"></i></button>
                            </div>
                        </div>
                        <div class="dm-goal-field ${dateRangeError ? 'has-error' : ''}">
                            <label for="dmGoalEndDate">End Date</label>
                            <div class="dm-goal-date-control ${dateRangeError ? 'has-error' : ''}">
                                <input id="dmGoalEndDate" class="dm-goal-date-display" type="text" readonly placeholder="MM/DD/YYYY" value="${escapeHtml(this.formatUsDate(goal.end_date || ''))}" onclick="dmApp.openGoalDatePicker('dmGoalEndDate', 'end_date')">
                                <button type="button" class="dm-goal-date-trigger" aria-label="Choose end date" onclick="dmApp.openGoalDatePicker('dmGoalEndDate', 'end_date')"><i class="far fa-calendar"></i></button>
                            </div>
                            ${dateRangeError ? `<div class="dm-goal-validation-error"><i class="fas fa-exclamation-circle"></i> ${escapeHtml(dateRangeError)}</div>` : ''}
                        </div>
                        <div class="dm-goal-field">
                            <label>Current Measure Rate</label>
                            <input type="text" readonly value="${escapeHtml(this.currentMeasureRateDisplay())}">
                        </div>
                        <div class="dm-goal-field">
                            <label for="dmGoalRate">Goal Rate</label>
                            <input id="dmGoalRate" type="number" step="0.1" min="0" placeholder="0.0" value="${escapeHtml(goalRate)}" oninput="dmApp.updateMeasureGoalDraftInPlace('goal_rate', this.value); dmApp.syncMeasureGoalDifference()" required>
                        </div>
                        <div class="dm-goal-field">
                            <label>${differenceLabel}</label>
                            <input id="dmGoalDifference" type="text" readonly value="${escapeHtml(this.measureGoalDifferenceDisplay())}">
                        </div>
                        ${showNumberNeededGoal ? `<div class="dm-goal-field">
                            <label>${numberNeededLabel}</label>
                            <input id="dmGoalNumberNeeded" type="text" readonly value="${escapeHtml(this.measureGoalNumberNeededDisplay(goal))}">
                        </div>` : ''}
                        <div class="dm-goal-field">
                            <label>Goal Set By</label>
                            <input type="text" readonly value="${escapeHtml(setByLabel)}">
                        </div>
                    </div>
                    <div class="dm-goal-actions">
                        <button type="button" id="dmGoalSaveBtn" class="dm-btn dm-goal-save" onclick="dmApp.saveMeasureGoal(event)" ${canSaveGoal ? '' : 'disabled'}>
                            <i class="fas fa-save"></i> Save Goal
                        </button>
                    </div></div>`}
                </section>
            `;
        },

        improvementGoalKey(measureId) {
            return `hacs_hais_${String(measureId || '').trim()}`;
        },

        improvementGoalMeasureName(measureId) {
            const measure = DM_IMPROVEMENT_CALCULATOR_MEASURES.find(item => item.id === measureId);
            return measure ? `HACs & HAIs — ${measure.label}` : `HACs & HAIs — ${measureId}`;
        },

        improvementGoalForMeasure(measureId) {
            const key = this.improvementGoalKey(measureId);
            const goals = this.state.measureGoals || {};
            return goals[key] || {
                start_date: '',
                end_date: '',
                goal_rate: '',
                direction: 'lower'
            };
        },

        improvementLatestMeasureData(measureId) {
            const measure = DM_IMPROVEMENT_CALCULATOR_MEASURES.find(item => item.id === measureId);
            if (!measure) return null;
            const calculator = this.improvementCalculatorState();
            const rawRows = this.improvementCalculatorRawRows(calculator.submissions || [])
                .filter(row => row.measureId === measureId)
                .sort((a, b) => {
                    if (b.year !== a.year) return b.year - a.year;
                    if (b.monthNum !== a.monthNum) return b.monthNum - a.monthNum;
                    return String(b.updatedAt || '').localeCompare(String(a.updatedAt || ''));
                });

            if (rawRows.length) {
                const latest = rawRows[0];
                const eventCount = this.parseNumberValue(latest.eventValue);
                const denominator = this.parseNumberValue(latest.denominatorValue);
                const storedRate = this.parseNumberValue(latest.rateValue);
                const rateValue = Number.isFinite(eventCount) && Number.isFinite(denominator) && denominator > 0
                    ? (eventCount / denominator) * (measure.rateMultiplier === 1 ? 100 : measure.rateMultiplier)
                    : (Number.isFinite(storedRate)
                        ? (measure.rateMultiplier === 1 ? storedRate * 100 : storedRate)
                        : NaN);
                return {
                    measure,
                    rateValue,
                    eventCount,
                    year: latest.year,
                    month: latest.month
                };
            }

            const points = (calculator.monthlyRows || [])
                .map((row, index) => {
                    const point = this.improvementMeasurePoint(measure, row);
                    return point ? { ...point, row, index } : null;
                })
                .filter(Boolean)
                .sort((a, b) => b.index - a.index);

            if (!points.length) {
                return { measure, rateValue: NaN, eventCount: NaN, year: '', month: '' };
            }

            const latestPoint = points[0];
            return {
                measure,
                rateValue: latestPoint.displayRate,
                eventCount: latestPoint.numerator,
                year: latestPoint.row.year,
                month: latestPoint.month
            };
        },

        improvementGoalRateLabel(measure, value) {
            const number = this.parseNumberValue(value);
            if (!Number.isFinite(number)) return 'No saved data yet';
            if (measure && measure.rateMultiplier === 1) {
                return this.formatPercent(number, 1);
            }
            return `${number.toFixed(1).replace(/\.0$/, '')} ${measure ? measure.rateUnit : ''}`.trim();
        },

        improvementGoalEventLabel(measure, count) {
            const rounded = Math.max(0, Math.round(Number(count) || 0));
            const label = String((measure && measure.eventLabel) || 'events').trim();
            const readable = label
                .replace(/\bEvents\b/g, rounded === 1 ? 'Event' : 'Events')
                .replace(/\bevents\b/g, rounded === 1 ? 'event' : 'events');
            return `Prevent ${rounded} ${readable}`;
        },

        improvementGoalSummary(measureId) {
            const latest = this.improvementLatestMeasureData(measureId);
            const measure = latest && latest.measure ? latest.measure : DM_IMPROVEMENT_CALCULATOR_MEASURES.find(item => item.id === measureId);
            const goal = this.improvementGoalForMeasure(measureId);
            const currentRate = latest ? this.parseNumberValue(latest.rateValue) : NaN;
            const goalRate = this.parseNumberValue(goal.goal_rate);
            const currentEventCount = latest ? this.parseNumberValue(latest.eventCount) : NaN;
            const hasCalculation = Number.isFinite(currentRate) && currentRate > 0 && Number.isFinite(goalRate);
            const reductionFraction = hasCalculation ? Math.max(0, (currentRate - goalRate) / currentRate) : NaN;
            const reductionPercent = Number.isFinite(reductionFraction) ? reductionFraction * 100 : NaN;
            const numberNeeded = Number.isFinite(currentEventCount) && Number.isFinite(reductionFraction)
                ? currentEventCount * reductionFraction
                : NaN;

            return {
                measure,
                currentRate: Number.isFinite(currentRate) ? currentRate : '',
                currentRateLabel: Number.isFinite(currentRate) ? this.improvementGoalRateLabel(measure, currentRate) : 'No saved data yet',
                performanceGoalLabel: Number.isFinite(goalRate) ? this.improvementGoalRateLabel(measure, goalRate) : '-',
                reductionNeeded: Number.isFinite(reductionPercent) ? reductionPercent : '',
                reductionNeededLabel: Number.isFinite(reductionPercent) ? this.formatPercent(reductionPercent, 1) : '-',
                numberNeeded: Number.isFinite(numberNeeded) ? numberNeeded : '',
                numberNeededLabel: Number.isFinite(numberNeeded) ? this.improvementGoalEventLabel(measure, numberNeeded) : '-'
            };
        },

        improvementGoalDraftIsComplete(measureId, goal = null, summary = null) {
            const activeGoal = goal || this.improvementGoalForMeasure(measureId);
            const activeSummary = summary || this.improvementGoalSummary(measureId);
            return this.measureGoalValuesAreComplete(
                this.parseUsDate(activeGoal.start_date || ''),
                this.parseUsDate(activeGoal.end_date || ''),
                activeSummary.currentRate,
                activeGoal.goal_rate ?? '',
                activeSummary.reductionNeeded
            );
        },

        loadImprovementMeasureGoals() {
            const requests = DM_IMPROVEMENT_CALCULATOR_MEASURES.map(measure => {
                const formData = new FormData();
                formData.append('action', 'qualinav_mbqip_goal_load');
                formData.append('nonce', DM_CONFIG.nonce);
                formData.append('measure_name', this.improvementGoalMeasureName(measure.id));
                formData.append('measure_key', this.improvementGoalKey(measure.id));
                return fetch(DM_CONFIG.ajax_url, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => ({ measure, data }))
                    .catch(() => ({ measure, data: null }));
            });

            Promise.all(requests).then(results => {
                const goals = { ...(this.state.measureGoals || {}) };
                const history = { ...(this.state.measureGoalHistory || {}) };
                results.forEach(({ measure, data }) => {
                    const key = this.improvementGoalKey(measure.id);
                    const allGoals = data && data.success && data.data && Array.isArray(data.data.goals) ? data.data.goals : [];
                    history[key] = allGoals;
                    goals[key] = data && data.success && data.data && data.data.goal
                        ? data.data.goal
                        : {
                            ...(goals[key] || {}),
                            start_date: '',
                            end_date: '',
                            goal_rate: '',
                            direction: 'lower'
                        };
                });
                this.setState({ measureGoals: goals, measureGoalHistory: history }, { preserveScroll: true, scrollToTop: false });
            });
        },

        updateImprovementMeasureGoalDraft(measureId, field, value) {
            const key = this.improvementGoalKey(measureId);
            const goals = { ...(this.state.measureGoals || {}) };
            goals[key] = {
                ...(goals[key] || {}),
                [field]: value,
                direction: 'lower'
            };
            this.setState({ measureGoals: goals, measureGoalStatus: '' }, { preserveScroll: true, scrollToTop: false });
        },

        saveImprovementMeasureGoal(measureId, event) {
            if (event && event.preventDefault) event.preventDefault();
            const measure = DM_IMPROVEMENT_CALCULATOR_MEASURES.find(item => item.id === measureId);
            if (!measure) return;
            const key = this.improvementGoalKey(measureId);
            const startDate = this.parseUsDate((document.getElementById(`dmImprovementGoalStart-${measureId}`) || {}).value || '');
            const endDate = this.parseUsDate((document.getElementById(`dmImprovementGoalEnd-${measureId}`) || {}).value || '');
            const goalRate = String((document.getElementById(`dmImprovementGoalRate-${measureId}`) || {}).value || '').replace('%', '').trim();
            const latest = this.improvementLatestMeasureData(measureId);
            const currentRateValue = latest ? this.parseNumberValue(latest.rateValue) : NaN;
            const goalRateValue = this.parseNumberValue(goalRate);
            const reductionNeededValue = Number.isFinite(currentRateValue) && currentRateValue > 0 && Number.isFinite(goalRateValue)
                ? Math.max(0, (currentRateValue - goalRateValue) / currentRateValue) * 100
                : NaN;
            const currentRate = Number.isFinite(currentRateValue) ? String(currentRateValue) : '';
            const reductionNeeded = Number.isFinite(reductionNeededValue) ? String(reductionNeededValue) : '';
            const dateError = this.measureGoalDateRangeError(startDate, endDate);
            if (dateError) {
                this.setState({ measureGoalStatus: dateError }, { preserveScroll: true, scrollToTop: false });
                return;
            }
            if (!this.measureGoalValuesAreComplete(startDate, endDate, currentRate, goalRate, reductionNeeded)) {
                this.setState({ measureGoalStatus: 'Complete start date, end date, and performance goal before saving.' }, { preserveScroll: true, scrollToTop: false });
                return;
            }

            const formData = new FormData();
            formData.append('action', 'qualinav_mbqip_goal_save');
            formData.append('nonce', DM_CONFIG.nonce);
            formData.append('measure_name', this.improvementGoalMeasureName(measureId));
            formData.append('measure_key', key);
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);
            formData.append('current_rate', currentRate);
            formData.append('goal_rate', goalRate);
            formData.append('difference_needed', reductionNeeded);
            formData.append('direction', 'lower');

            const draftGoals = { ...(this.state.measureGoals || {}) };
            draftGoals[key] = {
                ...(draftGoals[key] || {}),
                start_date: startDate,
                end_date: endDate,
                current_rate: currentRate,
                goal_rate: goalRate,
                difference_needed: reductionNeeded,
                direction: 'lower'
            };
            this.setState({ measureGoals: draftGoals, measureGoalStatus: 'Saving goal...' }, { preserveScroll: true, scrollToTop: false });

            fetch(DM_CONFIG.ajax_url, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        this.setState({ measureGoalStatus: data.data || 'Could not save goal.' }, { preserveScroll: true, scrollToTop: false });
                        return;
                    }
                    const goals = { ...(this.state.measureGoals || {}) };
                    const history = { ...(this.state.measureGoalHistory || {}) };
                    const allGoals = data.data && Array.isArray(data.data.goals) ? data.data.goals : null;
                    goals[key] = data.data && data.data.goal ? data.data.goal : draftGoals[key];
                    history[key] = allGoals || [goals[key], ...(Array.isArray(history[key]) ? history[key] : [])];
                    this.setState({
                        measureGoals: goals,
                        measureGoalHistory: history,
                        measureGoalStatus: data.data.message || 'Goal saved.'
                    }, { preserveScroll: true, scrollToTop: false });
                })
                .catch(err => this.setState({ measureGoalStatus: 'Could not save goal: ' + err.message }, { preserveScroll: true, scrollToTop: false }));
        },

        renderImprovementMeasureGoalCard(measureId, title) {
            const measure = DM_IMPROVEMENT_CALCULATOR_MEASURES.find(item => item.id === measureId);
            if (!measure) return '';
            const escapeHtml = this.escapeHtml.bind(this);
            const goal = this.improvementGoalForMeasure(measureId);
            const summary = this.improvementGoalSummary(measureId);
            const goalRate = goal.goal_rate === undefined || goal.goal_rate === null ? '' : String(goal.goal_rate);
            const activeTab = ((this.state.improvementMeasureGoalTabs || {})[measureId] === 'past') ? 'past' : 'current';
            const setByLabel = this.measureGoalUserLabel(goal);
            const dateRangeError = this.measureGoalDateRangeError(
                this.parseUsDate(goal.start_date || ''),
                this.parseUsDate(goal.end_date || '')
            );
            const canSaveGoal = this.improvementGoalDraftIsComplete(measureId, goal, summary);
            return `
                <section class="dm-measure-goals dm-improvement-measure-goals" aria-label="${escapeHtml(title)} goals">
                    <div class="dm-measure-goals-head">
                        <div>
                            <h2 class="dm-measure-goals-title">${escapeHtml(title)} Goals</h2>
                            <div class="dm-measure-goals-status">${escapeHtml(this.state.measureGoalStatus || '')}</div>
                        </div>
                    </div>
                    <div class="dm-goal-tabs">
                        <div class="dm-goal-tab ${activeTab === 'current' ? 'active' : ''}" onclick="dmApp.setImprovementMeasureGoalTab('${measureId}', 'current')">Current goal</div>
                        <div class="dm-goal-tab ${activeTab === 'past' ? 'active' : ''}" onclick="dmApp.setImprovementMeasureGoalTab('${measureId}', 'past')">Past goals</div>
                    </div>
                    ${activeTab === 'past' ? this.renderImprovementPastMeasureGoals(measureId, measure, escapeHtml) : `<div class="dm-measure-goals-body is-current"><div class="dm-measure-goals-grid">
                        <div class="dm-goal-field">
                            <label for="dmImprovementGoalStart-${measureId}">Start Date</label>
                            <div class="dm-goal-date-control">
                                <input id="dmImprovementGoalStart-${measureId}" class="dm-goal-date-display" type="text" readonly placeholder="MM/DD/YYYY" value="${escapeHtml(this.formatUsDate(goal.start_date || ''))}" onclick="dmApp.openGoalDatePicker('dmImprovementGoalStart-${measureId}', 'start_date', 'improvement', '${measureId}')">
                                <button type="button" class="dm-goal-date-trigger" aria-label="Choose start date" onclick="dmApp.openGoalDatePicker('dmImprovementGoalStart-${measureId}', 'start_date', 'improvement', '${measureId}')"><i class="far fa-calendar"></i></button>
                            </div>
                        </div>
                        <div class="dm-goal-field ${dateRangeError ? 'has-error' : ''}">
                            <label for="dmImprovementGoalEnd-${measureId}">End Date</label>
                            <div class="dm-goal-date-control ${dateRangeError ? 'has-error' : ''}">
                                <input id="dmImprovementGoalEnd-${measureId}" class="dm-goal-date-display" type="text" readonly placeholder="MM/DD/YYYY" value="${escapeHtml(this.formatUsDate(goal.end_date || ''))}" onclick="dmApp.openGoalDatePicker('dmImprovementGoalEnd-${measureId}', 'end_date', 'improvement', '${measureId}')">
                                <button type="button" class="dm-goal-date-trigger" aria-label="Choose end date" onclick="dmApp.openGoalDatePicker('dmImprovementGoalEnd-${measureId}', 'end_date', 'improvement', '${measureId}')"><i class="far fa-calendar"></i></button>
                            </div>
                            ${dateRangeError ? `<div class="dm-goal-validation-error"><i class="fas fa-exclamation-circle"></i> ${escapeHtml(dateRangeError)}</div>` : ''}
                        </div>
                        <div class="dm-goal-field">
                            <label>Current Rate</label>
                            <input type="text" readonly value="${escapeHtml(summary.currentRateLabel)}">
                        </div>
                        <div class="dm-goal-field">
                            <label for="dmImprovementGoalRate-${measureId}">Performance Goal</label>
                            <input id="dmImprovementGoalRate-${measureId}" type="number" step="0.1" min="0" placeholder="0.0" value="${escapeHtml(goalRate)}" onchange="dmApp.updateImprovementMeasureGoalDraft('${measureId}', 'goal_rate', this.value)">
                        </div>
                        <div class="dm-goal-field">
                            <label>Reduction Needed</label>
                            <input type="text" readonly value="${escapeHtml(summary.reductionNeededLabel)}">
                        </div>
                        <div class="dm-goal-field dm-goal-field-wide">
                            <label>Number Needed</label>
                            <input type="text" readonly value="${escapeHtml(summary.numberNeededLabel)}">
                        </div>
                        <div class="dm-goal-field">
                            <label>Goal Set By</label>
                            <input type="text" readonly value="${escapeHtml(setByLabel)}">
                        </div>
                    </div>
                    <div class="dm-goal-actions">
                        <button type="button" class="dm-btn dm-goal-save" onclick="dmApp.saveImprovementMeasureGoal('${measureId}', event)" ${canSaveGoal ? '' : 'disabled'}>
                            <i class="fas fa-save"></i> Save Goal
                        </button>
                    </div></div>`}
                </section>
            `;
        },

        renderRawDataTable() {
            const m = this.state.currentMeasure;
            if (!m) return '';
            const config = this.rawDataChartConfig() || {};
            const escapeHtml = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[c]));
            const allRows = this.collectRawDataRows();
            const rows = this.filteredRawDataRows(allRows);

            return `
                <div class="dm-row-actions top" style="justify-content:space-between; margin-top:0; margin-bottom:16px; gap:16px; flex-wrap:wrap;">
                    ${this.isEdtcMeasure()
                        ? `
                        <label style="display:flex; flex-direction:column; gap:8px; font-weight:800; color:var(--dm-primary); min-width:320px;">
                            EDTC Run Chart
                            <select class="dm-year-select" onchange="dmApp.updateEdtcReportSeries(this.value)" style="width:360px; max-width:100%; height:44px; border:1px solid #d1d5db; padding:10px 14px; border-radius:8px; font-size:14px;">
                                ${this.edtcSeriesOptions().map(option => `
                                    <option value="${this.escapeHtml(option.key)}" ${(this.state.edtcReportSeries || DM_EDTC_COMPOSITE_KEY) === option.key ? 'selected' : ''}>${this.escapeHtml(option.label)}</option>
                                `).join('')}
                            </select>
                        </label>
                        `
                        : '<div></div>'}
                    ${this.renderRawDataYearFilter(allRows)}
                </div>
                ${!allRows.length
                    ? '<div class="dm-guide" style="margin-top:24px;">No raw data rows to show for the selected EDTC series yet.</div>'
                    : (!rows.length ? '<div class="dm-guide" style="margin-top:24px;">No raw data rows match the selected reporting year.</div>' : `
                ${this.renderRawDataRunChart(rows)}
                ${this.renderRawDataMissingPeriodWarning(rows)}
                <div style="margin-top:24px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin:0 0 12px; flex-wrap:wrap;">
                        <h3 style="margin:0; font-size:16px; font-weight:700; color:var(--dm-primary);">
                            Raw data (${rows.length})
                        </h3>
                        <button type="button" class="dm-raw-chart-download" onclick="dmApp.downloadRawDataXlsx()">
                            <i class="fas fa-file-excel"></i>
                            Download Excel
                        </button>
                    </div>
                    <div class="dm-table-wrap">
                        <table class="dm-table">
                            <thead>
                                <tr>
                                    <th>Year</th>
                                    ${config.periodLabel ? `<th>${escapeHtml(config.periodLabel)}</th>` : ''}
                                    ${config.valueField === 'median_minutes'
                                        ? `<th>${escapeHtml(config.valueLabel || 'Value')}</th>`
                                        : `<th>${escapeHtml(config.numLabel || 'Num')}</th>
                                           <th>${escapeHtml(config.denLabel || 'Denom')}</th>
                                           <th>Rate</th>`}
                                </tr>
                            </thead>
                            <tbody>
                                ${rows.map(row => `
                                    <tr>
                                        <td>${escapeHtml(row.year)}</td>
                                        ${config.periodLabel ? `<td>${escapeHtml(row.period)}</td>` : ''}
                                        ${config.valueField === 'median_minutes'
                                            ? `<td>${escapeHtml(row.median_minutes)}</td>`
                                            : `<td>${escapeHtml(row.num)}</td>
                                               <td>${escapeHtml(row.den)}</td>
                                               <td>${escapeHtml(row.rate)}</td>`}
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>`)}
            `;
        },

        renderUploadError() {
            const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (c) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[c]));
            const message = String(this.state.uploadError || '');
            const lines = message.split('\n').filter(line => line.trim() !== '');
            return `
                <div class="dm-upload-error" role="alert" style="display:${this.state.uploadError ? 'flex' : 'none'};">
                    <i class="fas fa-exclamation-circle"></i>
                    <span class="dm-upload-error-text">${lines.length > 1
                        ? lines.map(line => `<span>${escapeHtml(line)}</span>`).join('')
                        : escapeHtml(message)}
                    </span>
                </div>
            `;
        },

        renderSavedAssessmentPanel(limit = null, withArchiveTabs = false, includeRawData = true) {
            if (!withArchiveTabs) {
                return this.renderUploadedFilesList('all', limit, 'active');
            }
            const showRawDataTab = includeRawData && this.hasRawDataChart();
            const activeTab = showRawDataTab ? this.state.assessmentListTab : (this.state.assessmentListTab === 'archive' ? 'archive' : 'saved');
            const isArchive = activeTab === 'archive';
            const isRaw = showRawDataTab && activeTab === 'raw';
            const active = isRaw
                ? this.renderRawDataTable()
                : this.renderUploadedFilesList('all', limit, isArchive ? 'archive' : 'active');
            return `
                <div class="dm-assessment-panel">
                    <div class="dm-tabs" style="margin-top:0; margin-bottom:18px;">
                        <div class="dm-tab ${(!isArchive && !isRaw) ? 'active' : ''}" onclick="dmApp.setAssessmentListTab('saved')">Saved assessments</div>
                        <div class="dm-tab ${isArchive ? 'active' : ''}" onclick="dmApp.setAssessmentListTab('archive')">Archive</div>
                        ${showRawDataTab ? `<div class="dm-tab ${isRaw ? 'active' : ''}" onclick="dmApp.setAssessmentListTab('raw')">Raw data</div>` : ''}
                    </div>
                    ${isRaw
                        ? (active || `<div class="dm-guide" style="margin-top:0;">No raw data rows to show.</div>`)
                        : `<div class="dm-saved-assessment-scroll">${active || `<div class="dm-guide" style="margin-top:0;">No ${isArchive ? 'archived' : 'saved'} assessments to show.</div>`}</div>`
                    }
                </div>
            `;
        },

        parseCsvPreviewRows(text) {
            const rows = [];
            let row = [];
            let cell = '';
            let inQuotes = false;
            const input = String(text || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');

            for (let i = 0; i < input.length; i++) {
                const ch = input[i];
                const next = input[i + 1];
                if (inQuotes && ch === '"' && next === '"') {
                    cell += '"';
                    i++;
                } else if (ch === '"') {
                    inQuotes = !inQuotes;
                } else if (!inQuotes && ch === ',') {
                    row.push(cell);
                    cell = '';
                } else if (!inQuotes && ch === '\n') {
                    row.push(cell);
                    rows.push(row);
                    row = [];
                    cell = '';
                } else {
                    cell += ch;
                }
            }
            if (cell !== '' || row.length) {
                row.push(cell);
                rows.push(row);
            }
            return rows.filter(r => r.some(c => String(c).trim() !== ''));
        },

        // In-page file viewer. Reads the saved CSV/XLS/XLSX from the upload
        // URL (same-origin) and renders the rows as an HTML table via SheetJS,
        // which is already loaded for the upload parser. Keeps PHI on our own
        // server instead of routing it through Office Online / Google Docs.
        async openFileViewer(fileName, fileUrl) {
            if (!fileUrl) return;
            const overlay = document.getElementById('dmFileViewer');
            const title   = document.getElementById('dmFileViewerTitle');
            const body    = document.getElementById('dmFileViewerBody');
            const dl      = document.getElementById('dmFileViewerDownload');
            if (!overlay || !title || !body || !dl) return;

            title.textContent = fileName || 'File';
            dl.setAttribute('href', fileUrl);
            dl.setAttribute('download', fileName || '');
            body.innerHTML = '<div style="color:#64748b;">Loading…</div>';
            overlay.style.display = 'flex';

            const ext = (fileName || '').toLowerCase().split('.').pop();
            try {
                const response = await fetch(fileUrl, { credentials: 'same-origin' });
                if (!response.ok) throw new Error('HTTP ' + response.status);

                let rows = [];
                if (ext === 'csv' || ext === 'txt') {
                    const text = await response.text();
                    rows = this.parseCsvPreviewRows(text);
                } else {
                    const buf = await response.arrayBuffer();
                    const workbook = XLSX.read(buf, { type: 'array' });
                    const sheet = workbook.Sheets[workbook.SheetNames[0]];
                    rows = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
                }

                if (!rows.length) {
                    body.innerHTML = '<div style="color:#64748b;">This file is empty.</div>';
                    return;
                }

                const isHcpAnnualFile = String(fileName || '').toLowerCase().includes('hcp-imm-3-healthcare-personnel-influenza-vaccination')
                    || String(fileName || '').toLowerCase().includes('hcpimm-3-healthcare-personnel-influenza-vaccination');
                if (isHcpAnnualFile && rows[0] && String(rows[0][0] || '').toLowerCase() === 'metric') {
                    const currentHeader = rows[0].map(cell => String(cell || '').toLowerCase().trim());
                    if (currentHeader.includes('month') || currentHeader.includes('num') || currentHeader.includes('denom')) {
                        rows = [
                            ['Metric', 'Year', 'Date Reported', 'Vaccinated HCP', 'Total Eligible HCP', 'Rate'],
                            ...rows.slice(1).map(row => [
                                row[0] || '',
                                row[1] || '',
                                '',
                                row[3] || '',
                                row[4] || '',
                                row[5] || ''
                            ])
                        ];
                    }
                }

                const escape = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
                }[c]));
                const [header, ...dataRows] = rows;
                const thead = '<thead><tr>' + header.map((h) =>
                    `<th style="text-align:left; padding:8px 12px; background:#f1f5f9; border-bottom:1px solid #e2e8f0; font-weight:700; color:var(--dm-primary); white-space:nowrap;">${escape(h)}</th>`
                ).join('') + '</tr></thead>';
                const tbody = '<tbody>' + dataRows.map((r) =>
                    '<tr>' + header.map((_, ci) =>
                        `<td style="padding:8px 12px; border-bottom:1px solid #f1f5f9;">${escape(r[ci])}</td>`
                    ).join('') + '</tr>'
                ).join('') + '</tbody>';
                body.innerHTML = `
                    <div style="overflow:auto;">
                        <table style="width:100%; border-collapse:collapse; font-size:13px;">${thead}${tbody}</table>
                    </div>
                `;
            } catch (err) {
                const safeMsg = String(err && err.message ? err.message : 'unknown error')
                    .replace(/[&<>"']/g, (c) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
                body.innerHTML = `<div style="color:#ef4444;">Could not preview this file (${safeMsg}). Use the Download button to open it locally.</div>`;
            }
        },

        closeFileViewer() {
            const overlay = document.getElementById('dmFileViewer');
            if (overlay) overlay.style.display = 'none';
        },

        openPasteModal() {
            const overlay = document.getElementById('dmPasteModal');
            const area    = document.getElementById('dmPasteArea');
            if (!overlay || !area) return;
            area.value = '';
            this.hidePasteError();
            overlay.style.display = 'flex';
            setTimeout(() => area.focus(), 50);
        },

        closePasteModal() {
            const overlay = document.getElementById('dmPasteModal');
            if (overlay) overlay.style.display = 'none';
        },

        showPasteError(msg) {
            const box = document.getElementById('dmPasteError');
            const txt = document.getElementById('dmPasteErrorText');
            if (txt) txt.textContent = msg;
            if (box) box.style.display = 'flex';
        },

        hidePasteError() {
            const box = document.getElementById('dmPasteError');
            if (box) box.style.display = 'none';
        },

        showUploadError(msg) {
            const message = msg || 'This file does not match the selected measure.';
            this.state.uploadError = message;
            document.querySelectorAll('.dm-upload-error-text').forEach(text => {
                text.textContent = message;
            });
            document.querySelectorAll('.dm-upload-error').forEach(box => {
                box.style.display = 'flex';
            });
            const input = document.getElementById('dmFileInput');
            if (input) input.value = '';
        },

        hideUploadError() {
            this.state.uploadError = '';
            document.querySelectorAll('.dm-upload-error-text').forEach(text => {
                text.textContent = '';
            });
            document.querySelectorAll('.dm-upload-error').forEach(box => {
                box.style.display = 'none';
            });
        },

        // Block the paste itself when the clipboard text isn't spreadsheet
        // data (e.g. a copied paragraph). Genuine tabular data passes
        // through to the textarea unchanged.
        handlePasteAreaPaste(e) {
            const data = e.clipboardData || window.clipboardData;
            const text = data ? data.getData('text') : '';
            if (!text) return;
            if (!this.looksLikeTabularData(text)) {
                e.preventDefault();
                this.showPasteError("That doesn't look like spreadsheet data. Paste rows copied from Excel, Google Sheets, or a CSV file.");
            } else {
                this.hidePasteError();
            }
        },

        importPastedData() {
            const area = document.getElementById('dmPasteArea');
            if (!area) return;
            if (!this.looksLikeTabularData(area.value)) {
                this.showPasteError("That doesn't look like spreadsheet data. Paste rows copied from Excel, Google Sheets, or a CSV file.");
                return;
            }
            this.hidePasteError();
            this.closePasteModal();
            this.setState({
                manualRows: this.parsePastedRows(area.value),
                inputTab: 'manual',
                uploadedFileName: ''
            });
        },

        // Parse tab- or comma-separated rows copied from Excel / Google
        // Sheets into manual-entry rows. Excel puts cells on the clipboard
        // as TSV. A header row is optional — when the first line names
        // columns we map by name, otherwise we assume the template column
        // order: Year, Month, Numerator, Denominator.
        parsePastedRows(text) {
            const lines = String(text || '').replace(/\r/g, '').split('\n').filter(l => l.trim());
            if (!lines.length) return [];

            const delim = lines[0].includes('\t') ? '\t' : ',';
            const splitRow = (line) => line.split(delim).map(c => c.trim().replace(/^"(.*)"$/, '$1'));

            const firstCells = splitRow(lines[0]).map(c => c.toLowerCase());
            const isHeader = firstCells.some(c =>
                c.includes('month') || c.includes('year') ||
                c.includes('num') || c.includes('den') || c.includes('time'));

            let idx = { year: 0, month: 1, num: 2, den: 3 };
            let dataLines = lines;
            if (isHeader) {
                idx = {
                    month: firstCells.findIndex(h => h.includes('month') || h.includes('time')),
                    year:  firstCells.findIndex(h => h.includes('year')),
                    num:   firstCells.findIndex(h => h.includes('num')),
                    den:   firstCells.findIndex(h => h.includes('den'))
                };
                dataLines = lines.slice(1);
            }

            const rows = [];
            dataLines.forEach(line => {
                const cols = splitRow(line);
                if (cols.length < 2) return;
                const cell = (i) => (i >= 0 && i < cols.length) ? cols[i] : '';
                const monthStr = cell(idx.month);
                if (monthStr.toLowerCase().includes('month') || monthStr.toLowerCase().includes('time')) return;
                rows.push({
                    month: this.normalizeMonth(monthStr),
                    year: String(cell(idx.year)).trim(),
                    num: parseFloat(cell(idx.num)) || 0,
                    den: parseFloat(cell(idx.den)) || 0
                });
            });
            return rows;
        },

        // Decide whether pasted text is genuine spreadsheet / CSV data
        // rather than free-form prose. Requires a tab or comma delimiter
        // and a majority of parsed rows that carry a recognizable month or
        // 4-digit year alongside a numeric value.
        looksLikeTabularData(text) {
            const lines = String(text || '').replace(/\r/g, '').split('\n').filter(l => l.trim());
            if (!lines.length) return false;

            const hasTabs   = lines.some(l => l.includes('\t'));
            const hasCommas = lines.some(l => l.includes(','));
            if (!hasTabs && !hasCommas) return false;

            const rows = this.parsePastedRows(text);
            if (!rows.length) return false;

            const yearRe = /^\d{4}$/;
            const validRows = rows.filter(r => {
                const hasWhen   = !!r.month || yearRe.test(String(r.year).trim());
                const hasNumber = Number(r.num) > 0 || Number(r.den) > 0;
                return hasWhen && hasNumber;
            });
            return validRows.length >= Math.ceil(rows.length / 2);
        },

        deleteUploadedFile(fileName) {
            if (!fileName) return;
            const measure = this.state.currentMeasure;
            const folder  = this.state.currentCategory ? this.state.currentCategory.id : '';
            if (!measure || !folder) return;
            if (!confirm(`Move "${fileName}" to Archive?`)) return;

            const formData = new FormData();
            formData.append('action', 'dm_delete_file');
            formData.append('folder_id', folder);
            formData.append('file_name', fileName);
            formData.append('nonce', DM_CONFIG.nonce);

            fetch(DM_CONFIG.ajax_url, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        alert('Could not archive: ' + (data.data || data.message || 'unknown error'));
                        return;
                    }
                    const newFiles = { ...this.state.filesByMeasure };
                    newFiles[measure] = (newFiles[measure] || []).map(f => {
                        if ((f.name || '') !== fileName) return f;
                        return {
                            ...f,
                            archived: true,
                            archived_at: (data.data && data.data.archived_at) ? data.data.archived_at : ''
                        };
                    });
                    const newSaved = { ...this.state.savedMeasures };
                    const activeCount = newFiles[measure].filter(f => !f.archived).length;
                    if (activeCount === 0) {
                        delete newSaved[measure];
                    } else {
                        newSaved[measure] = activeCount;
                    }
                    this.setState({
                        filesByMeasure: newFiles,
                        savedMeasures: newSaved,
                    }, { scrollToTop: false });
                    this.notifyMetricsChanged();
                })
                .catch(err => alert('Could not archive: ' + err.message));
        },

        restoreUploadedFile(fileName) {
            if (!fileName) return;
            const measure = this.state.currentMeasure;
            const folder  = this.state.currentCategory ? this.state.currentCategory.id : '';
            if (!measure || !folder) return;
            if (!confirm(`Restore "${fileName}" to Saved assessments?`)) return;

            const formData = new FormData();
            formData.append('action', 'dm_restore_file');
            formData.append('folder_id', folder);
            formData.append('file_name', fileName);
            formData.append('nonce', DM_CONFIG.nonce);

            fetch(DM_CONFIG.ajax_url, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        alert('Could not restore: ' + (data.data || data.message || 'unknown error'));
                        return;
                    }
                    const newFiles = { ...this.state.filesByMeasure };
                    newFiles[measure] = (newFiles[measure] || []).map(f => {
                        if ((f.name || '') !== fileName) return f;
                        return {
                            ...f,
                            archived: false,
                            archived_at: ''
                        };
                    });
                    const newSaved = { ...this.state.savedMeasures };
                    const activeCount = newFiles[measure].filter(f => !f.archived).length;
                    if (activeCount === 0) {
                        delete newSaved[measure];
                    } else {
                        newSaved[measure] = activeCount;
                    }
                    this.setState({
                        filesByMeasure: newFiles,
                        savedMeasures: newSaved,
                        assessmentListTab: 'saved'
                    }, { scrollToTop: false });
                    this.notifyMetricsChanged();
                })
                .catch(err => alert('Could not restore: ' + err.message));
        },

        renderReportTitle(title) {
            return `
                <div class="dm-report-title-row">
                    <h1>${this.escapeHtml(title || 'Report')}</h1>
                    <button type="button" class="dm-raw-chart-download" onclick="dmApp.shareCurrentReportUrl()">
                        <i class="fas fa-link"></i>
                        Share
                    </button>
                </div>
            `;
        },

        renderMeasureRail(content) {
            return `<div class="dm-measure-rail">${content}</div>`;
        },

        renderInputView() {
            const cat = this.state.currentCategory;
            const sub = this.state.currentSubfolder;
            const m = this.state.currentMeasure;
            const isImprovementCalculator = this.isImprovementCalculatorMeasure(m);
            const isGeneralBulkUpload = cat && cat.id === 'general' && m === 'Bulk Upload';
            const scopedHacsMeasure = this.state.unifiedHacsMeasureId
                ? DM_IMPROVEMENT_CALCULATOR_MEASURES.find(item => item.id === this.state.unifiedHacsMeasureId)
                : null;
            const displayMeasureTitle = scopedHacsMeasure ? scopedHacsMeasure.label : m;

            const breadcrumb = this.state.unifiedMeasuresMode ? `
                <div class="dm-breadcrumb">
                    <span onclick="dmApp.navToUnifiedMeasuresPage()">Quality Measures</span>
                    <i class="fas fa-chevron-right" style="font-size:10px;"></i>
                    <b>${this.escapeHtml(displayMeasureTitle)}</b>
                </div>
            ` : (isImprovementCalculator ? `
                <div class="dm-breadcrumb">
                    <span onclick="dmApp.navToRoot()">${this.escapeHtml(this.organizationDataTitle())}</span>
                    <i class="fas fa-chevron-right" style="font-size:10px;"></i>
                    <b>${cat.name}</b>
                </div>
            ` : `
                <div class="dm-breadcrumb">
                    <span onclick="dmApp.navToRoot()">${this.escapeHtml(this.organizationDataTitle())}</span>
                    <i class="fas fa-chevron-right" style="font-size:10px;"></i>
                    <span onclick="dmApp.navToCategory('${cat.id}')">${cat.name}</span>
                    <i class="fas fa-chevron-right" style="font-size:10px;"></i>
                    ${sub ? `<span onclick="dmApp.navToSubfolder('${cat.id}', '${sub.id}')">${sub.name}</span><i class="fas fa-chevron-right" style="font-size:10px;"></i>` : ''}
                    <b>${m}</b>
                </div>
            `);

            if (isGeneralBulkUpload) {
                return this.renderGeneralBulkUploadView(breadcrumb);
            }
            if (isImprovementCalculator) {
                return this.renderMeasureRail(this.renderImprovementCalculatorShellView(breadcrumb, displayMeasureTitle));
            }
            if (this.isGlobalInfrastructureMeasure(m)) {
                return this.renderMeasureRail(this.renderGlobalInfrastructureInputView(breadcrumb, m));
            }
            if (this.isHcpInfluenzaMeasure(m)) {
                return this.renderMeasureRail(this.renderHcpInfluenzaInputView(breadcrumb, m));
            }
            if (this.isAntibioticStewardshipMeasure(m)) {
                return this.renderMeasureRail(this.renderAntibioticStewardshipInputView(breadcrumb, m));
            }
            if (this.isSafeUseOpioidsMeasure(m)) {
                return this.renderMeasureRail(this.renderSafeUseOpioidsInputView(breadcrumb, m));
            }
            if (this.isHcahpsMeasure(m)) {
                return this.renderMeasureRail(this.renderHcahpsInputView(breadcrumb, m));
            }
            if (this.isHwrMeasure(m)) {
                return this.renderMeasureRail(this.renderHwrInputView(breadcrumb, m));
            }
            if (this.isEdtcMeasure(m)) {
                return this.renderMeasureRail(this.renderEdtcInputView(breadcrumb, m));
            }
            if (this.isOp18Measure(m)) {
                return this.renderMeasureRail(this.renderOp18InputView(breadcrumb, m));
            }
            if (this.isOp22Measure(m)) {
                return this.renderMeasureRail(this.renderOp22InputView(breadcrumb, m));
            }
            const isAnnualHcp = this.isHcpInfluenzaMeasure(m);
            const isHcahps = this.isHcahpsMeasure(m);
            const isQuarterRate = this.isQuarterRateMeasure(m);
            const isQuarterMedian = this.isQuarterMedianMeasure(m);
            const isHwr = this.isHwrMeasure(m);
            const manualTableClass = isAnnualHcp || isHwr
                ? 'dm-manual-table-annual'
                : (isQuarterRate
                    ? 'dm-manual-table-quarter-rate'
                    : (isQuarterMedian ? 'dm-manual-table-quarter-median' : 'dm-manual-table-monthly'));
            const manualTableColgroup = isAnnualHcp || isHwr
                ? '<colgroup><col class="dm-col-year"><col class="dm-col-num"><col class="dm-col-den"><col class="dm-col-rate"></colgroup>'
                : (isQuarterRate
                    ? '<colgroup><col class="dm-col-year"><col class="dm-col-quarter"><col class="dm-col-num"><col class="dm-col-den"><col class="dm-col-rate"></colgroup>'
                    : (isQuarterMedian
                        ? '<colgroup><col class="dm-col-year"><col class="dm-col-quarter"><col class="dm-col-median"></colgroup>'
                        : '<colgroup><col class="dm-col-month"><col class="dm-col-year"><col class="dm-col-num"><col class="dm-col-den"><col class="dm-col-rate"><col class="dm-col-action"></colgroup>'));

            return `
                ${breadcrumb}
                <div class="dm-header">
                    ${this.renderReportTitle(m)}
                </div>

                <div class="dm-tabs">
                    <div class="dm-tab ${this.state.inputTab !== 'database' ? 'active' : ''}" onclick="dmApp.setTab('entry')">Manual Entry</div>
                </div>

                <div class="dm-input-pane ${this.state.inputTab !== 'database' ? 'active' : ''}">
                    <div class="dm-entry-section-title" style="margin-top:0;">Manual Entry</div>
                    <div class="dm-row-actions top">
                         ${isAnnualHcp || isQuarterRate || isQuarterMedian || isHwr ? '' : `<button class="dm-btn dm-btn-outline" onclick="dmApp.openPasteModal()"><i class="fas fa-paste"></i> Paste from Excel</button>`}
                         <button id="dmDownloadBtn" class="dm-btn dm-btn-outline" style="margin-left:auto; padding:0; width:38px; height:38px;" onclick="dmApp.downloadSavedCsv()" title="Download CSV" aria-label="Download CSV" ${this.state.lastSavedRows ? '' : 'disabled'}>
                             <i class="fas fa-download" style="font-size:15px;"></i>
                         </button>
                    </div>

                    <div class="dm-table-wrap dm-manual-table-wrap">
                        <table class="dm-table dm-manual-table ${manualTableClass}" id="dmManualTable">
                            ${manualTableColgroup}
                            <thead>
                                ${isAnnualHcp || isHwr ? `
                                    <tr>
                                        <th>Year</th>
                                        <th>Numerator</th>
                                        <th>Denominator</th>
                                        <th>Rate (%)</th>
                                    </tr>
                                ` : isQuarterRate ? `
                                    <tr>
                                        <th>Year</th>
                                        <th>Quarter</th>
                                        <th>Numerator</th>
                                        <th>Denominator</th>
                                        <th>Rate (%)</th>
                                    </tr>
                                ` : isQuarterMedian ? `
                                    <tr>
                                        <th>Year</th>
                                        <th>Quarter</th>
                                        <th>Median Minutes</th>
                                    </tr>
                                ` : `
                                    <tr>
                                        <th>Month</th>
                                        <th>Year</th>
                                        <th>Numerator</th>
                                        <th>Denominator</th>
                                        <!-- Median column hidden — uncomment to restore: <th>Median</th> -->
                                        <th>Rate (%)</th>
                                        <th></th>
                                    </tr>
                                `}
                            </thead>
                            <tbody id="dmManualTbody">
                                ${isAnnualHcp || isHwr ? this.state.manualRows.slice(0, 1).map((row, idx) => `
                                    <tr>
                                        <td>
                                            <select class="dm-year-select" onchange="dmApp.updateManualRow(${idx}, 'year', this.value)">
                                                ${this.yearOptions().map(y => `<option value="${y}" ${String(row.year || new Date().getFullYear()) === String(y) ? 'selected' : ''}>${y}</option>`).join('')}
                                            </select>
                                        </td>
                                        <td><input type="number" min="0" placeholder="0" value="${row.num}" oninput="dmApp.updateManualRow(${idx}, 'num', this.value)"></td>
                                        <td class="dm-den-cell"><input type="number" min="0" placeholder="0" value="${row.den}" oninput="dmApp.updateManualRow(${idx}, 'den', this.value)">${this.renderNumDenWarning(row)}</td>
                                        <td class="dm-rate-cell">
                                            ${this.formatRatePercent(row.num, row.den)}
                                        </td>
                                    </tr>
                                `).join('') : isQuarterRate ? this.state.manualRows.slice(0, 1).map((row, idx) => `
                                    <tr>
                                        <td>
                                            <select class="dm-year-select" onchange="dmApp.updateManualRow(${idx}, 'year', this.value)">
                                                ${this.yearOptions().map(y => `<option value="${y}" ${String(row.year || new Date().getFullYear()) === String(y) ? 'selected' : ''}>${y}</option>`).join('')}
                                            </select>
                                        </td>
                                        <td>
                                            <select onchange="dmApp.updateManualRow(${idx}, 'month', this.value)">
                                                <option value="">Quarter</option>
                                                ${this.quarterOptions().map(q => `<option value="${q}" ${row.month === q ? 'selected' : ''}>${q}</option>`).join('')}
                                            </select>
                                        </td>
                                        <td><input type="number" min="0" placeholder="0" value="${row.num}" oninput="dmApp.updateManualRow(${idx}, 'num', this.value)"></td>
                                        <td class="dm-den-cell"><input type="number" min="0" placeholder="0" value="${row.den}" oninput="dmApp.updateManualRow(${idx}, 'den', this.value)">${this.renderNumDenWarning(row)}</td>
                                        <td class="dm-rate-cell">
                                            ${this.formatRatePercent(row.num, row.den)}
                                        </td>
                                    </tr>
                                `).join('') : isQuarterMedian ? this.state.manualRows.slice(0, 1).map((row, idx) => `
                                    <tr>
                                        <td>
                                            <select class="dm-year-select" onchange="dmApp.updateManualRow(${idx}, 'year', this.value)">
                                                ${this.yearOptions().map(y => `<option value="${y}" ${String(row.year || new Date().getFullYear()) === String(y) ? 'selected' : ''}>${y}</option>`).join('')}
                                            </select>
                                        </td>
                                        <td>
                                            <select onchange="dmApp.updateManualRow(${idx}, 'month', this.value)">
                                                <option value="">Quarter</option>
                                                ${this.quarterOptions().map(q => `<option value="${q}" ${row.month === q ? 'selected' : ''}>${q}</option>`).join('')}
                                            </select>
                                        </td>
                                        <td><input type="number" min="0" placeholder="0" value="${row.median || ''}" oninput="dmApp.updateManualRow(${idx}, 'median', this.value)"></td>
                                    </tr>
                                `).join('') : this.state.manualRows.map((row, idx) => `
                                    <tr>
                                        <td>
                                            <select onchange="dmApp.updateManualRow(${idx}, 'month', this.value)">
                                                <option value="">Month</option>
                                                ${this.monthOptions().map(m => `<option value="${m}" ${row.month === m ? 'selected' : ''}>${m}</option>`).join('')}
                                            </select>
                                        </td>
                                        <td>
                                            <select onchange="dmApp.updateManualRow(${idx}, 'year', this.value)">
                                                <option value="">Year</option>
                                                ${this.yearOptions().map(y => `<option value="${y}" ${String(row.year) === String(y) ? 'selected' : ''}>${y}</option>`).join('')}
                                            </select>
                                        </td>
                                        <td><input type="number" placeholder="0" value="${row.num}" oninput="dmApp.updateManualRow(${idx}, 'num', this.value)"></td>
                                        <td class="dm-den-cell"><input type="number" placeholder="0" value="${row.den}" oninput="dmApp.updateManualRow(${idx}, 'den', this.value)">${this.renderNumDenWarning(row)}</td>
                                        <!-- Median input hidden — uncomment to restore:
                                        <td><input type="number" placeholder="0" value="\${row.median}" oninput="dmApp.updateManualRow(\${idx}, 'median', this.value)"></td>
                                        -->
                                        <td class="dm-rate-cell">
                                            ${this.formatRatePercent(row.num, row.den)}
                                        </td>
                                        ${this.renderManualRowAction(idx)}
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    <div class="dm-save-section" style="display:flex; gap:12px; justify-content:flex-end; align-items:center; flex-wrap:wrap;">
                        <span id="dmSaveNote" style="font-size:13px; color:var(--dm-text-muted); display:${this.hasManualData() ? 'none' : 'inline-flex'}; align-items:center; gap:6px;">
                            <i class="fas fa-info-circle"></i> ${isAnnualHcp ? 'Enter the year, vaccinated healthcare personnel count, and total eligible healthcare personnel count before saving.' : (isHwr ? 'Enter the year, month, numerator, and denominator before saving.' : (isQuarterRate ? 'Enter the year, quarter, numerator, and denominator before saving.' : (isQuarterMedian ? 'Enter the year, quarter, and median minutes before saving.' : (this.isSafeUseOpioidsMeasure() ? 'Enter one complete month/year numerator and denominator row before saving.' : 'Enter at least one row of data before you can save.'))))}
                        </span>
                        <button type="button" id="dmSaveBtn" class="dm-btn" style="padding: 14px 40px; font-size: 15px; background: #03283E;" onclick="dmApp.saveAllData(event)" ${this.hasManualData() ? '' : 'disabled'}>
                            <i class="fas fa-cloud-upload-alt"></i> Save & Sync All Data
                        </button>
                    </div>
                    ${this.renderUploadedFilesList('manual')}
                </div>
            `;
        },

        mbqipBulkSheetDefinitions(includeCoverage = true) {
            const cahChecklistRows = Array.from({ length: 10 }).flatMap((_, groupIndex) => [
                ...(groupIndex > 0 ? [['', '', '']] : []),
                ...DM_GLOBAL_INFRASTRUCTURE_COMPONENTS.map((component, index) => ['', `${index + 1}. ${component}`, ''])
            ]);
            const antibioticStewardshipRows = Array.from({ length: 10 }).flatMap((_, groupIndex) => [
                ...(groupIndex > 0 ? [['', '', '']] : []),
                ...DM_ANTIBIOTIC_STEWARDSHIP_COMPONENTS.map(component => ['', component, ''])
            ]);
            const edtcChecklistRows = Array.from({ length: 20 }).flatMap((_, groupIndex) => [
                ...(groupIndex > 0 ? [['', '', '', '', '']] : []),
                ...DM_EDTC_COMPONENTS.map((component, index) => ['', '', `${index + 1}. ${component}`, '', ''])
            ]);
            const definitions = [
                {
                    sheet: 'CAH Quality',
                    measure: DM_GLOBAL_INFRASTRUCTURE_MEASURE,
                    type: 'checklist',
                    rows: [
                        ['Measure Name', DM_GLOBAL_INFRASTRUCTURE_MEASURE],
                        ['Instructions', 'Use one eight-row block per assessment year. Enter the year on each completed row, answer Criteria Met as Yes or No, and leave unused blocks blank.'],
                        [],
                        ['Hospital Data'],
                        [],
                        ['Year', 'CAH Global Measure Component', 'Criteria Met'],
                        ...cahChecklistRows
                    ]
                },
                {
                    sheet: 'HCP IMM-3',
                    measure: 'HCP/IMM-3 — Healthcare Personnel Influenza Vaccination',
                    type: 'annual_rate',
                    rows: [
                        ['Measure Name', 'HCP/IMM-3 — Healthcare Personnel Influenza Vaccination'],
                        ['Instructions', 'Enter annual influenza-season results. Fill one row or bulk add multiple years. Rate is calculated as Numerator divided by Denominator.'],
                        [],
                        ['Hospital Data'],
                        [],
                        ['Year', 'Numerator', 'Denominator', 'Rate'],
                        ...Array.from({ length: 12 }).map(() => ['', '', '', ''])
                    ]
                },
                {
                    sheet: 'Antibiotic Stewardship',
                    measure: DM_ANTIBIOTIC_STEWARDSHIP_MEASURE,
                    type: 'antibiotic_stewardship',
                    rows: [
                        ['Measure Name', DM_ANTIBIOTIC_STEWARDSHIP_MEASURE],
                        ['Instructions', 'Use one seven-row block per assessment year. Enter the year on each completed row, answer Criteria Met as Yes or No, and leave unused blocks blank.'],
                        [],
                        ['Hospital Data'],
                        [],
                        ['Year', 'CDC 7 Core Elements', 'Criteria Met'],
                        ...antibioticStewardshipRows
                    ]
                },
                {
                    sheet: 'Safe Use Opioids',
                    measure: DM_SAFE_USE_OPIOIDS_MEASURE,
                    type: 'period_rate',
                    rows: [
                        ['Measure Name', DM_SAFE_USE_OPIOIDS_MEASURE],
                        ['Instructions', 'Enter one row per reporting month. Leave unused rows blank. Rate is calculated as Numerator divided by Denominator.'],
                        [],
                        ['Hospital Data'],
                        [],
                        ['Year', 'Month', 'Num', 'Denom', 'Rate'],
                        ...Array.from({ length: 60 }).map(() => ['', '', '', '', ''])
                    ]
                },
                {
                    sheet: 'EDTC',
                    measure: DM_EDTC_MEASURE,
                    type: 'edtc_checklist',
                    rows: [
                        ['Measure Name', DM_EDTC_MEASURE],
                        ['Instructions', 'Use one nine-row block per reporting quarter. Enter one composite score row plus all eight EDTC element rows. Enter the same year and quarter on each completed row and leave unused blocks blank.'],
                        [],
                        ['Hospital Data'],
                        [],
                        ['Year', 'Quarter', 'EDTC Reporting Item', 'Num', 'Denom', 'Rate'],
                        ...[
                            ['', '', DM_EDTC_COMPOSITE_LABEL, '', '', ''],
                            ...DM_EDTC_COMPONENTS.map((component, index) => ['', '', `${index + 1}. ${component}`, '', '', '']),
                            []
                        ].concat(...Array.from({ length: 19 }).map(() => ([
                            ['', '', DM_EDTC_COMPOSITE_LABEL, '', '', ''],
                            ...DM_EDTC_COMPONENTS.map((component, index) => ['', '', `${index + 1}. ${component}`, '', '', '']),
                            []
                        ])))
                    ]
                },
                {
                    sheet: 'OP-18',
                    measure: DM_OP18_MEASURE,
                    type: 'quarter_median',
                    rows: [
                        ['Measure Name', DM_OP18_MEASURE],
                        ['Instructions', 'Enter quarterly median Emergency Department arrival-to-departure time results. Fill one row or bulk add multiple quarters.'],
                        [],
                        ['Hospital Data'],
                        [],
                        ['Year', 'Quarter', 'Median Minutes'],
                        ...Array.from({ length: 12 }).map(() => ['', '', ''])
                    ]
                },
                {
                    sheet: 'OP-22',
                    measure: DM_OP22_MEASURE,
                    type: 'annual_numden_rate',
                    rows: [
                        ['Measure Name', DM_OP22_MEASURE],
                        ['Instructions', 'Enter annual left-without-being-seen results. Fill one row or bulk add multiple years.'],
                        [],
                        ['Hospital Data'],
                        [],
                        ['Year', 'Num', 'Denom', 'Rate'],
                        ...Array.from({ length: 12 }).map(() => ['', '', '', ''])
                    ]
                }
            ];
            if (!includeCoverage) {
                return definitions;
            }
            return definitions.filter(def => this.measureCoverageAllows('mbqip', def.measure));
        },

        hacsHaisBulkSheetDefinitions(includeCoverage = true) {
            const rowsForMeasure = () => Array.from({ length: 180 }).map(() => ['', '', '', '', '']);
            const sheetPrefixForMeasure = (measure) => {
                const haiMeasures = ['c_diff', 'mrsa', 'cauti', 'clabsi'];
                return haiMeasures.includes(String(measure && measure.id || '')) ? 'HAI' : 'HAC';
            };
            const sheetNameForMeasure = (measure) => {
                const base = `${sheetPrefixForMeasure(measure)} ${String(measure.label || measure.id || '').replace(/[^a-z0-9 +.-]/gi, ' ').replace(/\s+/g, ' ').trim()}`;
                return base.slice(0, 31);
            };
            const definitions = DM_IMPROVEMENT_CALCULATOR_MEASURES.map(measure => ({
                sheet: sheetNameForMeasure(measure),
                program: 'hacs-hais',
                measure: measure.label,
                measureId: measure.id,
                type: 'hacs_hais_rate',
                rateMultiplier: measure.rateMultiplier,
                rateUnit: measure.rateUnit,
                denominatorKey: measure.denominatorKey,
                denominatorLabel: measure.denominatorLabel || this.improvementDenominatorLabel(measure.denominatorKey),
                rows: [
                    ['Measure Name', measure.label],
                    ['Instructions', `Enter one row per reporting month. Denominator: ${measure.denominatorLabel || this.improvementDenominatorLabel(measure.denominatorKey)}. Leave unused rows blank.`],
                    [],
                    ['Hospital Data'],
                    [],
                    ['Year', 'Month', 'Numerator', 'Denominator', 'Rate (%)'],
                    ...rowsForMeasure()
                ]
            }));
            if (!includeCoverage) {
                return definitions;
            }
            return definitions.filter(def => this.measureCoverageAllows('hacs-hais', def.measureId));
        },

        universalBulkSheetDefinitions(includeCoverage = true) {
            return [
                ...this.mbqipBulkSheetDefinitions(includeCoverage),
                ...this.hacsHaisBulkSheetDefinitions(includeCoverage)
            ];
        },

        renderGeneralOwnershipStatus(status) {
            const value = String(status || '').trim();
            if (!value) return '';
            if (value.toLowerCase().includes('saving')) {
                return `<i class="fas fa-spinner fa-spin dm-status-saving" title="Saving"></i>`;
            }
            if (value.toLowerCase() === 'saved' || value.toLowerCase().includes('saved')) {
                return `<i class="fas fa-check-circle dm-status-success" title="Saved"></i>`;
            }
            return `<i class="fas fa-exclamation-triangle dm-status-error" title="${this.escapeHtml(value)}"></i>`;
        },

        renderGeneralOwnershipDashboard() {
            const rows = this.dataOwnershipRows();
            const ownerships = this.state.reportOwnership || {};
            if (!rows.length) {
                return '';
            }
            let activeGroup = '';
            const bodyRows = rows.map(row => {
                const groupRow = row.group !== activeGroup
                    ? `<tr class="dm-general-ownership-group"><td colspan="3">${this.escapeHtml(row.group)}</td></tr>`
                    : '';
                activeGroup = row.group;
                const ownership = ownerships[row.scopeKey] || {};
                const selectedOwner = String(ownership.owner_user_id || 0);
                return `
                    ${groupRow}
                    <tr>
                        <td class="dm-general-ownership-measure">${this.escapeHtml(row.measure)}</td>
                        <td>${this.escapeHtml(row.group)}</td>
                        <td>
                            <select class="dm-general-owner-select" onchange="dmApp.saveGeneralReportOwnership('${this.escapeHtml(row.scopeKey)}', this.value)">
                                ${this.reportOwnershipOptionsHtml(selectedOwner)}
                            </select>
                        </td>
                    </tr>
                `;
            }).join('');
            const loadingText = this.state.generalOwnershipLoading
                ? '<p><i class="fas fa-spinner fa-spin"></i> Loading current ownership...</p>'
                : '<p>Assign ownership across Medicare Beneficiary Quality Improvement Project and HACs & HAIs measures. Changes save to the same Data Hub ownership records.</p>';
            return `
                <div class="dm-general-ownership-card">
                    <h2>Data Ownership</h2>
                    ${loadingText}
                    <div class="dm-general-ownership-table-wrap">
                        <table class="dm-general-ownership-table">
                            <thead>
                                <tr>
                                    <th>Measure</th>
                                    <th>Group</th>
                                    <th>Owner</th>
                                </tr>
                            </thead>
                            <tbody>${bodyRows}</tbody>
                        </table>
                    </div>
                </div>
            `;
        },

        renderGeneralBulkUploadView(breadcrumb) {
            const status = this.state.generalBulkStatus
                ? `<div class="dm-guide" style="margin-top:18px;"><i class="fas fa-info-circle"></i> ${this.escapeHtml(this.state.generalBulkStatus)}</div>`
                : '';
            return `
                ${breadcrumb}
                <div class="dm-header">
                    <h1>Universal Workbook</h1>
                    <p>Download and upload the universal Data Hub workbook.</p>
                </div>

                <div class="dm-guide">
                    <i class="fas fa-info-circle"></i>
                    <b>Universal Workbook:</b> Download one workbook with separate sheets for selected MBQIP and HACs & HAIs measures. Complete only the sheets you need; blank sheets are ignored.
                </div>
                ${this.renderUploadError()}
                ${status}

                <div class="dm-upload-box" id="dropZone" ondrop="dmApp.handleFileDrop(event)" ondragover="dmApp.handleDragOver(event)">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <h3 style="margin: 0 0 8px; color: var(--dm-primary);">Upload the Universal Workbook</h3>
                    <p style="margin: 0 0 24px; font-size: 14px; color: var(--dm-text-muted);">XLS/XLSX, maximum file size: 10MB</p>
                    <div style="display:flex; justify-content:center; gap: 12px; flex-wrap:wrap;">
                        <button class="dm-btn dm-btn-outline" onclick="dmApp.downloadGeneralMbqipWorkbook(false)">
                            <i class="fas fa-download"></i> Download Blank Workbook
                        </button>
                        <button class="dm-btn dm-btn-outline" onclick="dmApp.downloadGeneralMbqipWorkbook(true)">
                            <i class="fas fa-file-download"></i> Download Current Workbook
                        </button>
                        <label class="dm-btn">
                            <i class="fas fa-upload"></i> Upload Workbook
                            <input type="file" style="display:none;" id="dmFileInput" accept=".xls,.xlsx" onchange="dmApp.handleFileUpload(event)">
                        </label>
                    </div>
                </div>
            `;
        },

        bulkRawRowValue(row, keys) {
            const normalizeKey = (key) => String(key || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
            const normalizedRow = {};
            Object.entries(row || {}).forEach(([key, value]) => {
                normalizedRow[normalizeKey(key)] = value;
            });
            for (const key of keys) {
                const value = normalizedRow[normalizeKey(key)];
                if (value !== undefined && value !== null && String(value).trim() !== '') {
                    return String(value).trim();
                }
            }
            return '';
        },

        generalWorkbookSavedRowsForMeasure(measure) {
            const files = ((this.state.filesByMeasure || {})[measure] || [])
                .filter(file => !file.archived)
                .slice()
                .sort((a, b) => String(a.uploaded_at || '').localeCompare(String(b.uploaded_at || '')));
            const rows = [];
            files.forEach(file => {
                (Array.isArray(file.raw_rows) ? file.raw_rows : []).forEach(row => {
                    rows.push({
                        file,
                        row,
                        year: this.bulkRawRowValue(row, ['year']) || String(file.assessment_year || '').trim(),
                        period: this.bulkRawRowValue(row, ['period', 'month', 'quarter']) || String(file.assessment_month || '').trim()
                    });
                });
            });
            return rows;
        },

        uniqueLatestWorkbookRows(items, keyFn) {
            const map = new Map();
            (Array.isArray(items) ? items : []).forEach(item => {
                const key = keyFn(item);
                if (!key) return;
                map.set(key, item);
            });
            return Array.from(map.values());
        },

        workbookCsvRecordRows(text) {
            if (!text || typeof XLSX === 'undefined') return [];
            try {
                const workbook = XLSX.read(text, { type: 'string' });
                const sheet = workbook.Sheets[workbook.SheetNames[0]];
                const grid = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
                if (!Array.isArray(grid) || grid.length < 2) return [];
                const headers = (grid[0] || []).map(header => String(header == null ? '' : header).trim());
                return grid.slice(1)
                    .filter(row => Array.isArray(row) && row.some(cell => String(cell == null ? '' : cell).trim() !== ''))
                    .map(row => {
                        const mapped = {};
                        headers.forEach((header, index) => {
                            if (!header) return;
                            mapped[header] = row[index] == null ? '' : String(row[index]).trim();
                        });
                        return mapped;
                    });
            } catch (error) {
                return [];
            }
        },

        workbookRawRowHasComponents(rawRow, components, suffixes) {
            if (!rawRow || !components || !components.length) return false;
            return components.some((component, index) => {
                const keys = suffixes.flatMap(suffix => [
                    `${component} ${suffix}`,
                    `${index + 1}. ${component} ${suffix}`
                ]);
                return this.bulkRawRowValue(rawRow, keys) !== '';
            });
        },

        workbookRecordNeedsChecklistHydration(file, components, suffixes) {
            const rawRows = Array.isArray(file && file.raw_rows) ? file.raw_rows : [];
            if (!rawRows.length) return false;
            return rawRows.some(row => !this.workbookRawRowHasComponents(row, components, suffixes));
        },

        async hydrateLegacyChecklistWorkbookRows() {
            if (typeof fetch === 'undefined' || typeof XLSX === 'undefined') return;
            const hydrationTargets = [
                {
                    measure: DM_GLOBAL_INFRASTRUCTURE_MEASURE,
                    components: DM_GLOBAL_INFRASTRUCTURE_COMPONENTS,
                    suffixes: ['criteria met']
                },
                {
                    measure: DM_ANTIBIOTIC_STEWARDSHIP_MEASURE,
                    components: DM_ANTIBIOTIC_STEWARDSHIP_COMPONENTS,
                    suffixes: ['met']
                }
            ];
            await Promise.all(hydrationTargets.map(async target => {
                const files = (this.state.filesByMeasure && this.state.filesByMeasure[target.measure]) || [];
                await Promise.all(files.map(async file => {
                    if (!file || !file.name || !this.workbookRecordNeedsChecklistHydration(file, target.components, target.suffixes)) return;
                    try {
                        const params = new URLSearchParams({
                            action: 'dm_download_saved_file',
                            nonce: DM_CONFIG.nonce,
                            file_name: file.name
                        });
                        const response = await fetch(`${DM_CONFIG.ajax_url}?${params.toString()}`, { cache: 'no-store' });
                        if (!response.ok) return;
                        const rows = this.workbookCsvRecordRows(await response.text());
                        if (!rows.length) return;
                        const rowsByYear = new Map();
                        rows.forEach(row => {
                            const year = this.bulkRawRowValue(row, ['year']);
                            if (year) rowsByYear.set(year, row);
                        });
                        file.raw_rows = (Array.isArray(file.raw_rows) ? file.raw_rows : []).map(rawRow => {
                            const year = this.bulkRawRowValue(rawRow, ['year']) || String(file.assessment_year || '').trim();
                            const csvRow = rowsByYear.get(year);
                            return csvRow ? { ...rawRow, ...csvRow } : rawRow;
                        });
                    } catch (error) {
                        // Keep Current Workbook generation resilient if a legacy local CSV is unavailable.
                    }
                }));
            }));
        },

        generalWorkbookRowsWithSavedData(def) {
            const rows = def.rows.map(row => Array.isArray(row) ? [...row] : row);
            const saved = this.generalWorkbookSavedRowsForMeasure(def.measure);
            if (!saved.length) return rows;
            const dataStartIndex = 6;
            const cleanNumber = (value) => String(value == null ? '' : value).replace(/[%,$\s]/g, '').trim();
            const componentValue = (rawRow, component, suffixes, componentIndex = null) => {
                const keys = suffixes.flatMap(suffix => {
                    const baseKeys = [`${component} ${suffix}`];
                    if (componentIndex !== null && componentIndex !== undefined) {
                        baseKeys.unshift(`${componentIndex + 1}. ${component} ${suffix}`);
                    }
                    return baseKeys;
                });
                return this.bulkRawRowValue(rawRow, keys);
            };
            const writeRows = (items, writer) => {
                items.forEach((item, index) => writer(item, dataStartIndex + index));
            };
            const writeChecklistBlocks = (items, components, writer) => {
                items.forEach((item, blockIndex) => {
                    const blockSize = components.length + 1;
                    const blockStart = dataStartIndex + (blockIndex * blockSize);
                    components.forEach((component, componentIndex) => {
                        const rowIndex = blockStart + componentIndex;
                        if (!rows[rowIndex]) return;
                        writer(item, rowIndex, component, componentIndex);
                    });
                });
            };

            if (def.type === 'checklist') {
                const items = this.uniqueLatestWorkbookRows(saved, item => item.year).slice(0, 10);
                writeChecklistBlocks(items, DM_GLOBAL_INFRASTRUCTURE_COMPONENTS, (item, rowIndex, component, componentIndex) => {
                    rows[rowIndex][0] = componentIndex === 0 ? item.year : '';
                    rows[rowIndex][2] = componentValue(item.row, component, ['criteria met'], componentIndex) || '';
                });
                return rows;
            }

            if (def.type === 'antibiotic_stewardship') {
                const items = this.uniqueLatestWorkbookRows(saved, item => item.year).slice(0, 10);
                writeChecklistBlocks(items, DM_ANTIBIOTIC_STEWARDSHIP_COMPONENTS, (item, rowIndex, component, componentIndex) => {
                    rows[rowIndex][0] = item.year;
                    rows[rowIndex][2] = componentValue(item.row, component, ['met'], componentIndex) || '';
                });
                return rows;
            }

            if (def.type === 'edtc_checklist') {
                const groups = this.uniqueLatestWorkbookRows(
                    saved.filter(item => item.year && this.normalizeQuarter(item.period)),
                    item => `${item.year}|${this.normalizeQuarter(item.period)}|${this.edtcSeriesKeyForRow(item.row)}`
                );
                const groupKeys = [...new Set(groups.map(item => `${item.year}|${this.normalizeQuarter(item.period)}`))].slice(0, 20);
                groupKeys.forEach((groupKey, blockIndex) => {
                    const [year, quarter] = groupKey.split('|');
                    const blockStart = dataStartIndex + (blockIndex * 10);
                    const groupRows = groups.filter(item => `${item.year}|${this.normalizeQuarter(item.period)}` === groupKey);
                    const compositeRow = groupRows.find(item => this.edtcSeriesKeyForRow(item.row) === DM_EDTC_COMPOSITE_KEY);
                    if (rows[blockStart]) {
                        rows[blockStart][0] = year || '';
                        rows[blockStart][1] = quarter || '';
                        rows[blockStart][3] = cleanNumber(this.bulkRawRowValue(compositeRow && compositeRow.row, ['num', 'numerator']));
                        rows[blockStart][4] = cleanNumber(this.bulkRawRowValue(compositeRow && compositeRow.row, ['den', 'denom', 'denominator']));
                    }
                    DM_EDTC_COMPONENTS.forEach((component, componentIndex) => {
                        const rowIndex = blockStart + componentIndex + 1;
                        if (!rows[rowIndex]) return;
                        const seriesKey = this.edtcSeriesComponentKey(component);
                        const componentRow = groupRows.find(item => this.edtcSeriesKeyForRow(item.row) === seriesKey);
                        rows[rowIndex][0] = componentIndex === 0 ? year || '' : '';
                        rows[rowIndex][1] = componentIndex === 0 ? quarter || '' : '';
                        rows[rowIndex][3] = cleanNumber(this.bulkRawRowValue(componentRow && componentRow.row, ['num', 'numerator']));
                        rows[rowIndex][4] = cleanNumber(this.bulkRawRowValue(componentRow && componentRow.row, ['den', 'denom', 'denominator']));
                    });
                });
                return rows;
            }

            if (def.type === 'quarter_median') {
                const items = this.uniqueLatestWorkbookRows(saved, item => `${item.year}|${this.normalizeQuarter(item.period)}`).slice(0, 12);
                writeRows(items, (item, rowIndex) => {
                    if (!rows[rowIndex]) return;
                    rows[rowIndex][0] = item.year;
                    rows[rowIndex][1] = this.normalizeQuarter(item.period);
                    rows[rowIndex][2] = cleanNumber(this.bulkRawRowValue(item.row, ['median_minutes', 'median minutes', 'median']));
                });
                return rows;
            }

            const items = this.uniqueLatestWorkbookRows(saved, item => {
                if (def.type === 'period_rate' || def.type === 'quarter_rate') {
                    return `${item.year}|${item.period}`;
                }
                return item.year;
            }).slice(0, def.type === 'period_rate' ? 60 : 12);
            writeRows(items, (item, rowIndex) => {
                if (!rows[rowIndex]) return;
                const num = cleanNumber(this.bulkRawRowValue(item.row, ['num', 'numerator', 'vaccinated_hcp', 'vaccinated hcp', 'lwbs patients']));
                const den = cleanNumber(this.bulkRawRowValue(item.row, ['den', 'denom', 'denominator', 'total_eligible_hcp', 'total eligible hcp', 'ed visits']));
                rows[rowIndex][0] = item.year;
                if (def.type === 'period_rate' || def.type === 'quarter_rate') {
                    rows[rowIndex][1] = def.type === 'quarter_rate' ? this.normalizeQuarter(item.period) : item.period;
                    rows[rowIndex][2] = num;
                    rows[rowIndex][3] = den;
                } else {
                    rows[rowIndex][1] = num;
                    rows[rowIndex][2] = den;
                }
            });
            return rows;
        },

        hacsHaisWorkbookRowsWithSavedData(def) {
            const rows = def.rows.map(row => Array.isArray(row) ? [...row] : row);
            const submissions = (this.improvementCalculatorState().submissions || [])
                .filter(submission => String(submission.status || 'active') !== 'archived')
                .slice()
                .sort((a, b) => {
                    const yearDiff = Number(a.reporting_year || 0) - Number(b.reporting_year || 0);
                    if (yearDiff !== 0) return yearDiff;
                    return String(a.updated_at || '').localeCompare(String(b.updated_at || ''));
                });
            const entries = [];
            submissions.forEach(submission => {
                (submission.rows || []).forEach(row => {
                    const eventValue = row.events && row.events[def.measureId];
                    const denominatorValue = row.denominators && row.denominators[def.denominatorKey];
                    const hasEvent = String(eventValue == null ? '' : eventValue).trim() !== '';
                    const hasDenominator = String(denominatorValue == null ? '' : denominatorValue).trim() !== '';
                    if (!hasEvent || !hasDenominator) return;
                    entries.push({
                        year: String(submission.reporting_year || ''),
                        month: row.month || '',
                        num: eventValue == null ? '' : String(eventValue),
                        den: denominatorValue == null ? '' : String(denominatorValue)
                    });
                });
            });
            entries.slice(0, Math.max(0, rows.length - 6)).forEach((entry, index) => {
                const rowIndex = 6 + index;
                if (!rows[rowIndex]) return;
                rows[rowIndex][0] = entry.year;
                rows[rowIndex][1] = entry.month;
                rows[rowIndex][2] = entry.num;
                rows[rowIndex][3] = entry.den;
            });
            return rows;
        },

        async downloadGeneralMbqipWorkbook(prefillSavedData = false) {
            if (typeof ExcelJS === 'undefined') {
                alert('Workbook generation is unavailable. Please refresh and try again.');
                return;
            }
            if (prefillSavedData) {
                await this.loadImprovementCalculatorDatabase({ force: true, silent: true });
                await this.hydrateLegacyChecklistWorkbookRows();
            }

            const workbook = new ExcelJS.Workbook();
            workbook.creator = 'Qualinav';
            workbook.lastModifiedBy = 'Qualinav';
            workbook.created = new Date();
            workbook.modified = new Date();
            workbook.calcProperties = workbook.calcProperties || {};
            workbook.calcProperties.fullCalcOnLoad = true;

            const thinBorder = {
                top: { style: 'thin', color: { argb: 'FFB7B7B7' } },
                bottom: { style: 'thin', color: { argb: 'FFB7B7B7' } },
                left: { style: 'thin', color: { argb: 'FFB7B7B7' } },
                right: { style: 'thin', color: { argb: 'FFB7B7B7' } }
            };
            const headerFill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFD9E2F3' } };
            const entryFill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFF2CC' } };
            const yearOptions = Array.from({ length: Math.max(1, new Date().getFullYear() - 2012 + 1) }, (_, idx) => String(new Date().getFullYear() - idx));
            const monthOptions = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const quarterOptions = ['Q1', 'Q2', 'Q3', 'Q4'];
            const yesNoOptions = ['Yes', 'No'];
            const listValidation = (options) => ({
                type: 'list',
                allowBlank: true,
                showErrorMessage: true,
                formulae: [`"${options.join(',')}"`]
            });
            const columnLetter = (columnNumber) => {
                let value = '';
                let n = columnNumber;
                while (n > 0) {
                    const remainder = (n - 1) % 26;
                    value = String.fromCharCode(65 + remainder) + value;
                    n = Math.floor((n - 1) / 26);
                }
                return value;
            };
            const editableColumnsForBulkSheet = (def) => {
                if (def.type === 'checklist' || def.type === 'antibiotic_stewardship') return [1, 3];
                if (def.type === 'edtc_checklist') return [1, 2, 4, 5];
                if (def.type === 'hacs_hais_rate') return [1, 2, 3, 4];
                if (def.type === 'quarter_median') return [1, 2, 3];
                if (def.type === 'annual_rate' || def.type === 'annual_numden_rate') return [1, 2, 3];
                if (def.type === 'period_rate') return [1, 2, 3, 4];
                return [];
            };
            const validationOptionsForColumn = (def, colNumber) => {
                if (colNumber === 1) {
                    return yearOptions;
                }
                if ((def.type === 'period_rate' || def.type === 'hacs_hais_rate') && colNumber === 2) {
                    return monthOptions;
                }
                if ((def.type === 'edtc_checklist' || def.type === 'quarter_median') && colNumber === 2) {
                    return quarterOptions;
                }
                if ((def.type === 'checklist' || def.type === 'antibiotic_stewardship') && colNumber === 3) {
                    return yesNoOptions;
                }
                return null;
            };
            const isBulkSpacerRow = (def, rowValues) => {
                return (def.type === 'checklist' || def.type === 'antibiotic_stewardship' || def.type === 'edtc_checklist')
                    && rowValues.every(value => String(value == null ? '' : value).trim() === '');
            };
            const blockFormulaColumnsForSheet = (def) => {
                if (def.type === 'checklist' || def.type === 'antibiotic_stewardship') return [1];
                if (def.type === 'edtc_checklist') return [1, 2];
                return [];
            };
            const rateFormulaConfigForSheet = (def) => {
                if (def.type === 'annual_rate' || def.type === 'annual_numden_rate') {
                    return { numeratorColumn: 2, denominatorColumn: 3, rateColumn: 4 };
                }
                if (def.type === 'edtc_checklist') {
                    return { numeratorColumn: 4, denominatorColumn: 5, rateColumn: 6 };
                }
                if (def.type === 'hacs_hais_rate') {
                    return { numeratorColumn: 3, denominatorColumn: 4, rateColumn: 5, multiplier: Number(def.rateMultiplier) || 1 };
                }
                if (def.type === 'period_rate' || def.type === 'quarter_rate') {
                    return { numeratorColumn: 3, denominatorColumn: 4, rateColumn: 5 };
                }
                return null;
            };
            const applyRateFormulaForRow = (sheet, rowNumber, rateConfig) => {
                if (!rateConfig) return;
                const numeratorCell = `${columnLetter(rateConfig.numeratorColumn)}${rowNumber}`;
                const denominatorCell = `${columnLetter(rateConfig.denominatorColumn)}${rowNumber}`;
                const rateCell = sheet.getCell(rowNumber, rateConfig.rateColumn);
                const multiplier = Number(rateConfig.multiplier) || 1;
                rateCell.value = { formula: `IF(OR(${numeratorCell}="",${denominatorCell}="",${denominatorCell}=0),"",${numeratorCell}/${denominatorCell}${multiplier === 1 ? '' : `*${multiplier}`})`, result: '' };
                rateCell.numFmt = multiplier === 1 ? '0.0%' : '0.0';
                rateCell.fill = undefined;
                rateCell.protection = { locked: true };
            };
            const hideTrailingBulkColumns = (sheet, startColumn, endColumn = 26) => {
                for (let colNumber = startColumn; colNumber <= endColumn; colNumber++) {
                    const column = sheet.getColumn(colNumber);
                    column.hidden = true;
                    column.width = 0;
                }
            };
            const addValidationRanges = (sheet, def, lastColumn) => {
                const formulaColumns = blockFormulaColumnsForSheet(def);
                for (let colNumber = 1; colNumber <= lastColumn; colNumber++) {
                    const options = validationOptionsForColumn(def, colNumber);
                    if (!options) continue;
                    const column = columnLetter(colNumber);
                    let rangeStart = null;
                    let currentBlockStart = null;
                    for (let rowNumber = 7; rowNumber <= def.rows.length + 1; rowNumber++) {
                        const rowValues = Array.isArray(def.rows[rowNumber - 1]) ? def.rows[rowNumber - 1] : [];
                        const isValidDataRow = rowNumber <= def.rows.length && !isBulkSpacerRow(def, rowValues);
                        if (!isValidDataRow) {
                            currentBlockStart = null;
                        } else if (currentBlockStart === null) {
                            currentBlockStart = rowNumber;
                        }
                        const isFormulaControlledCell = isValidDataRow && formulaColumns.includes(colNumber) && rowNumber !== currentBlockStart;
                        const shouldValidate = isValidDataRow && !isFormulaControlledCell;
                        if (shouldValidate && rangeStart === null) {
                            rangeStart = rowNumber;
                        }
                        if ((!shouldValidate || rowNumber > def.rows.length) && rangeStart !== null) {
                            sheet.dataValidations.add(`${column}${rangeStart}:${column}${rowNumber - 1}`, listValidation(options));
                            rangeStart = null;
                        }
                    }
                }
            };
            const styleHeaderRow = (row) => {
                row.eachCell({ includeEmpty: true }, cell => {
                    cell.font = { bold: true };
                    cell.fill = headerFill;
                    cell.border = thinBorder;
                    cell.alignment = { wrapText: true, vertical: 'top' };
                });
            };
            const sheetDefinitions = this.universalBulkSheetDefinitions().map(def => {
                if (!prefillSavedData) return def;
                return def.program === 'hacs-hais'
                    ? { ...def, rows: this.hacsHaisWorkbookRowsWithSavedData(def) }
                    : { ...def, rows: this.generalWorkbookRowsWithSavedData(def) };
            });
            const workbookTitle = prefillSavedData
                ? 'Qualinav Data Hub Current Universal Workbook'
                : 'Qualinav Data Hub Universal Bulk Workbook';
            const workbookInstructions = prefillSavedData
                ? 'This workbook is regenerated from saved Data Hub records. Continue updating the populated sheets, then upload it again when ready.'
                : 'Complete only the sheets you need. Blank sheets are ignored during upload.';

            const readme = workbook.addWorksheet('README');
            readme.columns = [{ width: 28 }, { width: 80 }];
            [
                [workbookTitle],
                [workbookInstructions],
                ['Use Excel or export as .xlsx before uploading. Apple Numbers can alter workbook structure when saving.'],
                [],
                ['Sheet', 'Purpose'],
                ...sheetDefinitions.map(def => [def.sheet, def.measure])
            ].forEach(values => readme.addRow(values));
            styleHeaderRow(readme.getRow(1));
            styleHeaderRow(readme.getRow(5));
            readme.eachRow(row => row.eachCell({ includeEmpty: true }, cell => {
                cell.border = thinBorder;
                cell.alignment = { wrapText: true, vertical: 'top' };
            }));

            sheetDefinitions.forEach(def => {
                const sheet = workbook.addWorksheet(def.sheet);
                sheet.columns = (def.sheet === 'EDTC'
                    ? [18, 18, 60, 18, 18, 18]
                    : def.rows[5].map((_, index) => index === 1 ? 58 : 18)
                ).map(width => ({ width }));
                def.rows.forEach(values => sheet.addRow(values));
                [1, 2, 4, 6].forEach(rowNumber => styleHeaderRow(sheet.getRow(rowNumber)));
                sheet.getRow(2).height = def.sheet === 'CAH Quality' ? 70 : 58;

                const lastColumn = def.rows[5].length;
                const editableColumns = editableColumnsForBulkSheet(def);
                const formulaColumns = blockFormulaColumnsForSheet(def);
                const rateConfig = rateFormulaConfigForSheet(def);
                let currentBlockStart = null;
                if (def.type === 'edtc_checklist') {
                    hideTrailingBulkColumns(sheet, lastColumn + 1);
                }
                for (let rowNumber = 7; rowNumber <= def.rows.length; rowNumber++) {
                    const rowValues = Array.isArray(def.rows[rowNumber - 1]) ? def.rows[rowNumber - 1] : [];
                    const isSpacerRow = isBulkSpacerRow(def, rowValues);
                    if (isSpacerRow) {
                        sheet.getRow(rowNumber).height = 10;
                        currentBlockStart = null;
                    } else if (currentBlockStart === null) {
                        currentBlockStart = rowNumber;
                    }
                    for (let colNumber = 1; colNumber <= lastColumn; colNumber++) {
                        const cell = sheet.getCell(rowNumber, colNumber);
                        const isFormulaControlledCell = !isSpacerRow && formulaColumns.includes(colNumber) && rowNumber !== currentBlockStart;
                        cell.border = thinBorder;
                        cell.alignment = { wrapText: true, vertical: 'top' };
                        if (isFormulaControlledCell) {
                            const sourceCell = `${columnLetter(colNumber)}${currentBlockStart}`;
                            cell.value = { formula: `IF(${sourceCell}="","",${sourceCell})` };
                        }
                        if (!isSpacerRow && !isFormulaControlledCell && editableColumns.includes(colNumber)) {
                            cell.fill = entryFill;
                        }
                    }
                    if (!isSpacerRow) {
                        applyRateFormulaForRow(sheet, rowNumber, rateConfig);
                    }
                }
                addValidationRanges(sheet, def, lastColumn);
            });

            const buffer = await workbook.xlsx.writeBuffer();
            const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = prefillSavedData ? 'qualinav_data_hub_current_workbook.xlsx' : 'qualinav_data_hub_bulk_upload_workbook.xlsx';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        },

        withMbqipMeasureContext(measure, fn) {
            const previous = {
                currentCategory: this.state.currentCategory,
                currentSubfolder: this.state.currentSubfolder,
                currentMeasure: this.state.currentMeasure
            };
            const mbqipCategory = DM_DATA.find(cat => cat.id === 'mbqip') || { id: 'mbqip', name: 'MBQIP' };
            this.state.currentCategory = mbqipCategory;
            this.state.currentSubfolder = null;
            this.state.currentMeasure = measure;
            try {
                return fn();
            } finally {
                this.state.currentCategory = previous.currentCategory;
                this.state.currentSubfolder = previous.currentSubfolder;
                this.state.currentMeasure = previous.currentMeasure;
            }
        },

        submitBulkWorkbookRows(measure, templateType, rowsForSave, originalFileName, overwriteFileNames = []) {
            const formData = new FormData();
            formData.append('action', 'dm_save_data');
            formData.append('measure', measure);
            formData.append('folder_id', 'mbqip');
            formData.append('rows', JSON.stringify(rowsForSave));
            formData.append('nonce', DM_CONFIG.nonce);
            formData.append('template_type', templateType);
            if (originalFileName) {
                formData.append('original_filename', originalFileName);
            }
            if (Array.isArray(overwriteFileNames) && overwriteFileNames.length) {
                formData.append('overwrite_file_names', JSON.stringify(overwriteFileNames));
            }
            return fetch(DM_CONFIG.ajax_url, { method: 'POST', body: formData }).then(res => res.json());
        },

        submitHacsHaisBulkWorkbookRows(year, rowsForSave, overwrite = false) {
            const formData = new FormData();
            formData.append('action', 'qualinav_improvement_calculator_save');
            formData.append('nonce', DM_CONFIG.nonce);
            formData.append('reference_date', this.todayIsoDate());
            formData.append('reporting_year', String(year || ''));
            formData.append('rows', JSON.stringify(rowsForSave));
            if (overwrite) {
                formData.append('overwrite', '1');
            }
            return fetch(DM_CONFIG.ajax_url, { method: 'POST', body: formData }).then(res => res.json());
        },

        applyHacsHaisBulkSaveResult(data) {
            const submission = data && data.data && data.data.submission ? data.data.submission : null;
            if (!submission) return;
            const current = this.improvementCalculatorState();
            const submissions = Array.isArray(current.submissions) ? current.submissions.slice() : [];
            const withoutCurrent = submissions.filter(item => Number(item.id) !== Number(submission.id) && String(item.reporting_year || '') !== String(submission.reporting_year || ''));
            this.state.improvementCalculator = {
                ...current,
                submissions: [submission, ...withoutCurrent],
                databaseLoaded: true
            };
        },

        applyBulkWorkbookSaveResult(measure, data) {
            const newRecord = data && data.data && data.data.file ? data.data.file : null;
            if (!newRecord) return;
            const replacedNames = Array.isArray(data.data && data.data.replaced_file_names)
                ? data.data.replaced_file_names
                : ((data.data && data.data.replaced_file_name) ? [data.data.replaced_file_name] : []);
            const newFiles = { ...this.state.filesByMeasure };
            const existing = Array.isArray(newFiles[measure]) ? newFiles[measure] : [];
            const existingWithoutReplacement = replacedNames.length
                ? existing.filter(file => !replacedNames.includes(file.name || ''))
                : existing;
            newFiles[measure] = [newRecord, ...existingWithoutReplacement];
            const newSaved = { ...this.state.savedMeasures };
            newSaved[measure] = newFiles[measure].filter(file => !file.archived).length;
            this.state.filesByMeasure = newFiles;
            this.state.savedMeasures = newSaved;
            if (String(newRecord.drive_sync_status || '') === 'pending') {
                this.pollDriveSyncStatus(newRecord.name, measure);
            }
        },

        isBulkSheetBlank(grid, def = {}) {
            const rows = Array.isArray(grid) ? grid.slice(6) : [];
            const hasValue = (row, indexes) => indexes.some(index => String((row || [])[index] == null ? '' : (row || [])[index]).trim() !== '');
            return !rows.some(row => {
                if (!Array.isArray(row)) return false;
                if (def.type === 'checklist' || def.type === 'antibiotic_stewardship') {
                    return hasValue(row, [0, 2]);
                }
                if (def.type === 'edtc_checklist') {
                    return hasValue(row, [3, 4]);
                }
                if (def.type === 'hacs_hais_rate') {
                    return hasValue(row, [0, 1, 2, 3]);
                }
                if (def.type === 'annual_rate' || def.type === 'annual_numden_rate' || def.type === 'quarter_median') {
                    return hasValue(row, [0, 1, 2]);
                }
                return hasValue(row, [0, 1, 2, 3]);
            });
        },

        parseGeneralHcahpsRows(grid) {
            const allRows = Array.isArray(grid) ? grid : [];
            const normalized = (value) => this.normalizeChecklistText(value);
            const cleanCell = (value) => {
                if (value == null) return '';
                if (typeof value === 'object' && value.v != null) return String(value.v).trim();
                return String(value).trim();
            };
            const cleanNumber = (value) => cleanCell(value).replace(/[%,$\s]/g, '');
            const headerIndex = allRows.findIndex(rawRow => {
                const cells = Array.isArray(rawRow) ? rawRow.map(normalized) : [];
                return cells.includes('measure') && cells.includes('year') && cells.includes('quarter') && (cells.includes('num') || cells.includes('numerator'));
            });
            const header = headerIndex >= 0 && Array.isArray(allRows[headerIndex]) ? allRows[headerIndex].map(normalized) : [];
            const idx = {
                measure: header.findIndex(cell => cell === 'measure'),
                year: header.findIndex(cell => cell === 'year'),
                quarter: header.findIndex(cell => cell === 'quarter'),
                num: header.findIndex(cell => cell === 'num' || cell === 'numerator'),
                den: header.findIndex(cell => cell === 'denom' || cell === 'denominator')
            };
            const rowsByMeasure = {};
            const errors = [];
            const rowsToRead = headerIndex >= 0 ? allRows.slice(headerIndex + 1) : allRows;
            rowsToRead.forEach((rawRow, offset) => {
                if (!Array.isArray(rawRow) || !rawRow.some(cell => cleanCell(cell) !== '')) return;
                const cells = rawRow.map(cleanCell);
                const measure = idx.measure >= 0 ? cells[idx.measure] : '';
                const year = idx.year >= 0 ? cells[idx.year] : '';
                const quarter = idx.quarter >= 0 ? this.normalizeQuarter(cells[idx.quarter]) : '';
                const num = idx.num >= 0 ? cleanNumber(cells[idx.num]) : '';
                const den = idx.den >= 0 ? cleanNumber(cells[idx.den]) : '';
                if (!measure && !year && !quarter && num === '' && den === '') return;
                if (!DM_HCAHPS_MEASURES.includes(measure)) {
                    errors.push(`HCAHPS row ${headerIndex + offset + 2}: choose a valid HCAHPS measure name.`);
                    return;
                }
                if (!year || !quarter || num === '' || den === '' || Number(den) <= 0) {
                    errors.push(`HCAHPS row ${headerIndex + offset + 2}: enter measure, year, quarter, numerator, and denominator.`);
                    return;
                }
                const row = { month: quarter, year, num, den };
                if (this.hasNumDenInversion(row)) {
                    errors.push(`HCAHPS row ${headerIndex + offset + 2}: denominator cannot be lower than numerator.`);
                    return;
                }
                if (!rowsByMeasure[measure]) rowsByMeasure[measure] = [];
                rowsByMeasure[measure].push(row);
            });
            return { rowsByMeasure, errors };
        },

        parseHacsHaisBulkRows(grid, def) {
            const allRows = Array.isArray(grid) ? grid : [];
            const normalized = (value) => this.normalizeChecklistText(value);
            const cleanCell = (value) => {
                if (value == null) return '';
                if (typeof value === 'object' && value.v != null) return String(value.v).trim();
                return String(value).trim();
            };
            const cleanNumber = (value) => cleanCell(value).replace(/[%,$\s]/g, '');
            const headerIndex = allRows.findIndex(rawRow => {
                const cells = Array.isArray(rawRow) ? rawRow.map(normalized) : [];
                return cells.includes('year')
                    && cells.includes('month')
                    && (cells.includes('numerator') || cells.includes('num'))
                    && (cells.includes('denominator') || cells.includes('denom'));
            });
            const header = headerIndex >= 0 && Array.isArray(allRows[headerIndex]) ? allRows[headerIndex].map(normalized) : [];
            const idx = {
                year: header.findIndex(cell => cell === 'year'),
                month: header.findIndex(cell => cell === 'month'),
                num: header.findIndex(cell => cell === 'numerator' || cell === 'num'),
                den: header.findIndex(cell => cell === 'denominator' || cell === 'denom')
            };
            const rowsToRead = headerIndex >= 0 ? allRows.slice(headerIndex + 1) : allRows.slice(6);
            const rows = [];
            const errors = [];
            rowsToRead.forEach((rawRow, offset) => {
                if (!Array.isArray(rawRow) || !rawRow.some(cell => cleanCell(cell) !== '')) return;
                const cells = rawRow.map(cleanCell);
                const year = idx.year >= 0 ? cells[idx.year] : cells[0];
                const month = this.normalizeMonth(idx.month >= 0 ? cells[idx.month] : cells[1]);
                const num = idx.num >= 0 ? cleanNumber(cells[idx.num]) : cleanNumber(cells[2]);
                const den = idx.den >= 0 ? cleanNumber(cells[idx.den]) : cleanNumber(cells[3]);
                if (!year && !month && num === '' && den === '') return;
                const rowNumber = (headerIndex >= 0 ? headerIndex + offset + 2 : offset + 7);
                if (!/^[12][0-9]{3}$/.test(String(year || '').trim()) || !month || num === '' || den === '' || Number(den) <= 0) {
                    errors.push(`${def.sheet} row ${rowNumber}: enter year, month, numerator, and denominator.`);
                    return;
                }
                if (this.hasNumDenInversion({ num, den })) {
                    errors.push(`${def.sheet} row ${rowNumber}: denominator cannot be lower than numerator.`);
                    return;
                }
                rows.push({
                    year: String(year).trim(),
                    month,
                    measureId: def.measureId,
                    denominatorKey: def.denominatorKey,
                    num,
                    den
                });
            });
            return { rows, errors };
        },

        hacsHaisSubmissionByYear(year) {
            const submissions = (this.improvementCalculatorState().submissions || [])
                .filter(submission => String(submission.status || 'active') !== 'archived')
                .filter(submission => String(submission.reporting_year || '') === String(year || ''))
                .sort((a, b) => String(b.updated_at || '').localeCompare(String(a.updated_at || '')));
            return submissions[0] || null;
        },

        hacsHaisRowsForYearWithWorkbookRows(year, workbookRows) {
            const baseRows = this.defaultImprovementMonthlyRows(year);
            const existing = this.hacsHaisSubmissionByYear(year);
            const rowsByMonth = {};
            if (existing) {
                (existing.rows || []).forEach(row => {
                    rowsByMonth[String(row.month || '')] = row;
                });
            }
            const rows = baseRows.map(row => {
                const saved = rowsByMonth[row.month] || {};
                return {
                    ...row,
                    events: {
                        ...row.events,
                        ...(saved.events || {})
                    },
                    denominators: {
                        ...row.denominators,
                        ...(saved.denominators || {})
                    }
                };
            });
            const monthIndex = this.monthOptions().reduce((acc, month, index) => {
                acc[month] = index;
                return acc;
            }, {});
            (Array.isArray(workbookRows) ? workbookRows : []).forEach(item => {
                const index = monthIndex[item.month];
                if (index === undefined || !rows[index]) return;
                rows[index].events = { ...(rows[index].events || {}), [item.measureId]: item.num };
                rows[index].denominators = { ...(rows[index].denominators || {}), [item.denominatorKey]: item.den };
            });
            return rows;
        },

        buildHacsHaisBulkSaveJobs(workbook) {
            const errors = [];
            const rowsByYear = {};
            const denominatorValues = {};
            this.hacsHaisBulkSheetDefinitions(false).forEach(def => {
                const sheet = workbook.Sheets[def.sheet];
                if (!sheet) return;
                const grid = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
                if (this.isBulkSheetBlank(grid, def)) return;
                if (!this.measureCoverageAllows('hacs-hais', def.measureId)) {
                    errors.push(`${def.sheet}: ${def.measure} is not selected for this organization. Add it in Measure Management or remove this sheet before uploading.`);
                    return;
                }
                const parsed = this.parseHacsHaisBulkRows(grid, def);
                if (parsed.errors.length) {
                    errors.push(...parsed.errors);
                    return;
                }
                parsed.rows.forEach(row => {
                    const denominatorKey = `${row.year}|${row.month}|${row.denominatorKey}`;
                    if (denominatorValues[denominatorKey] !== undefined && String(denominatorValues[denominatorKey]) !== String(row.den)) {
                        errors.push(`${def.sheet}: ${row.month} ${row.year} uses a different denominator than another HACs & HAIs sheet that shares the same denominator.`);
                        return;
                    }
                    denominatorValues[denominatorKey] = row.den;
                    if (!rowsByYear[row.year]) rowsByYear[row.year] = [];
                    rowsByYear[row.year].push(row);
                });
            });
            const jobs = Object.entries(rowsByYear).map(([year, workbookRows]) => {
                const existing = this.hacsHaisSubmissionByYear(year);
                const measures = [...new Set(workbookRows.map(row => this.improvementMeasureLabel(row.measureId)))];
                return {
                    program: 'hacs-hais',
                    year,
                    measures,
                    existingSubmission: existing,
                    rowsForSave: this.hacsHaisRowsForYearWithWorkbookRows(year, workbookRows)
                };
            });
            return { jobs, errors };
        },

        buildGeneralMbqipSaveJobs(workbook) {
            const jobs = [];
            const errors = [];
            const addConflictInfo = (measure, rows, periodMode = 'year') => {
                return this.withMbqipMeasureContext(measure, () => {
                    const records = rows.map(row => {
                        const record = periodMode === 'period'
                            ? this.assessmentRecordForPeriod(row.month, row.year)
                            : this.assessmentRecordForYear(row.year);
                        const label = periodMode === 'period' ? `${row.month} ${row.year}` : String(row.year || '');
                        return { record, label };
                    }).filter(item => item.record);
                    return {
                        existingNames: [...new Set(records.map(item => item.record.name).filter(Boolean))],
                        existingLabels: [...new Set(records.map(item => item.label).filter(Boolean))]
                    };
                });
            };

            this.mbqipBulkSheetDefinitions(false).forEach(def => {
                const sheet = workbook.Sheets[def.sheet];
                if (!sheet) return;
                const grid = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
                if (this.isBulkSheetBlank(grid, def)) return;
                if (!this.measureCoverageAllows('mbqip', def.measure)) {
                    errors.push(`${def.sheet}: ${def.measure} is not selected for this organization. Add it in Measure Management or remove this sheet before uploading.`);
                    return;
                }

                this.withMbqipMeasureContext(def.measure, () => {
                    if (def.type === 'checklist' || def.type === 'antibiotic_stewardship') {
                        const parsed = this.parseGlobalChecklistRows(grid);
                        if (Array.isArray(parsed.incompleteAssessments) && parsed.incompleteAssessments.length) {
                            errors.push(`${def.sheet}: one or more years are partially completed.`);
                            return;
                        }
                        if (!Array.isArray(parsed.assessments) || !parsed.assessments.length) {
                            errors.push(`${def.sheet}: no complete yearly assessment was found.`);
                            return;
                        }
                        const rowsForSave = parsed.assessments.flatMap(item => this.buildGlobalChecklistSaveRowsFrom(item.year, item.rows));
                        const conflict = addConflictInfo(def.measure, parsed.assessments, 'year');
                        jobs.push({ measure: def.measure, templateType: def.type === 'antibiotic_stewardship' ? 'antibiotic_stewardship' : 'elements_checklist', rowsForSave, ...conflict });
                        return;
                    }
                    if (def.type === 'edtc_checklist') {
                        const parsed = this.parseEdtcNumDenRows(grid);
                        if (Array.isArray(parsed.invalidAssessments) && parsed.invalidAssessments.length) {
                            const labels = parsed.invalidAssessments
                                .map(item => `${this.normalizeQuarter(item.quarter)} ${String(item.year || '').trim()}`.trim())
                                .filter(Boolean);
                            errors.push(`${def.sheet}: denominator cannot be lower than numerator${labels.length ? ` for ${[...new Set(labels)].join(', ')}` : ''}.`);
                            return;
                        }
                        if (Array.isArray(parsed.incompleteAssessments) && parsed.incompleteAssessments.length) {
                            errors.push(`${def.sheet}: one or more quarters are partially completed.`);
                            return;
                        }
                        if (!Array.isArray(parsed.assessments) || !parsed.assessments.length) {
                            errors.push(`${def.sheet}: no complete EDTC quarter was found.`);
                            return;
                        }
                        const rowsForSave = parsed.assessments.flatMap(item => this.buildGlobalChecklistSaveRowsFrom(item.year, item.rows, item.quarter, {
                            compositeNum: item.compositeNum,
                            compositeDen: item.compositeDen
                        }));
                        const conflict = addConflictInfo(def.measure, rowsForSave, 'period');
                        jobs.push({ measure: def.measure, templateType: 'edtc_checklist', rowsForSave, ...conflict });
                        return;
                    }
                    if (def.type === 'annual_rate' || def.type === 'annual_numden_rate') {
                        const rows = this.parseAnnualRateRows(grid).filter(row => !this.isRowEmpty(row));
                        if (this.rowsHaveNumDenInversion(rows)) {
                            errors.push(`${def.sheet}: denominator cannot be lower than numerator.`);
                            return;
                        }
                        const complete = rows.length && rows.every(row => row.year && String(row.num).trim() !== '' && String(row.den).trim() !== '' && Number(row.den) > 0 && !this.hasNumDenInversion(row));
                        if (!complete) {
                            errors.push(`${def.sheet}: enter complete year, numerator, and denominator rows.`);
                            return;
                        }
                        const rowsForSave = rows.map(row => ({ ...row, month: '' }));
                        const conflict = addConflictInfo(def.measure, rowsForSave, 'year');
                        jobs.push({ measure: def.measure, templateType: def.type, rowsForSave, ...conflict });
                        return;
                    }
                    if (def.type === 'period_rate') {
                        const rows = this.parsePeriodRateRows(grid).filter(row => !this.isRowEmpty(row));
                        if (this.rowsHaveNumDenInversion(rows)) {
                            errors.push(`${def.sheet}: denominator cannot be lower than numerator.`);
                            return;
                        }
                        const complete = rows.length && rows.every(row => row.month && row.year && String(row.num).trim() !== '' && String(row.den).trim() !== '' && Number(row.den) > 0 && !this.hasNumDenInversion(row));
                        if (!complete) {
                            errors.push(`${def.sheet}: enter complete year, month, numerator, and denominator rows.`);
                            return;
                        }
                        const conflict = addConflictInfo(def.measure, rows, 'period');
                        jobs.push({ measure: def.measure, templateType: 'period_rate', rowsForSave: rows, ...conflict });
                        return;
                    }
                    if (def.type === 'quarter_median') {
                        const rows = this.parseQuarterMedianRows(grid).filter(row => !this.isRowEmpty(row));
                        const complete = rows.length && rows.every(row => row.month && row.year && String(row.median).trim() !== '' && Number(row.median) >= 0);
                        if (!complete) {
                            errors.push(`${def.sheet}: enter complete year, quarter, and median minutes rows.`);
                            return;
                        }
                        const conflict = addConflictInfo(def.measure, rows, 'period');
                        jobs.push({ measure: def.measure, templateType: 'quarter_median', rowsForSave: rows, ...conflict });
                    }
                });
            });

            return { jobs, errors };
        },

        async parseGeneralMbqipWorkbook(buffer, originalFileName = '') {
            try {
                if (typeof XLSX === 'undefined') {
                    this.showUploadError('Workbook parsing is unavailable. Please refresh and try again.');
                    return;
                }
                await this.loadImprovementCalculatorDatabase({ force: true, silent: true });
                const workbook = XLSX.read(buffer, { type: 'array' });
                const mbqipResult = this.buildGeneralMbqipSaveJobs(workbook);
                const hacsResult = this.buildHacsHaisBulkSaveJobs(workbook);
                const jobs = mbqipResult.jobs;
                const hacsJobs = hacsResult.jobs;
                const errors = [...mbqipResult.errors, ...hacsResult.errors];
                if (errors.length) {
                    this.showUploadError(errors.slice(0, 6).join('\n'));
                    this.setState({ generalBulkStatus: `${errors.length} issue${errors.length === 1 ? '' : 's'} found. Correct the workbook and upload again.` }, { preserveScroll: true, scrollToTop: false });
                    return;
                }
                if (!jobs.length && !hacsJobs.length) {
                    this.showUploadError('No completed MBQIP or HACs & HAIs sheets were found in this workbook.');
                    return;
                }
                const conflictJobs = jobs.filter(job => Array.isArray(job.existingNames) && job.existingNames.length);
                const hacsConflictJobs = hacsJobs.filter(job => job.existingSubmission);
                if (conflictJobs.length || hacsConflictJobs.length) {
                    this.openWorkbookConflictModal({
                        groups: this.workbookConflictGroups(conflictJobs, hacsConflictJobs),
                        jobs,
                        hacsJobs,
                        originalFileName
                    });
                    return;
                }
                await this.saveGeneralWorkbookJobs(jobs, hacsJobs, originalFileName);
            } catch (error) {
                this.showUploadError('Could not read this bulk workbook. Please download a fresh workbook and try again.');
                this.setState({ generalBulkStatus: 'Bulk upload failed while reading the workbook.' }, { preserveScroll: true, scrollToTop: false });
            }
        },

        workbookConflictGroups(conflictJobs, hacsConflictJobs) {
            const groups = [];
            (Array.isArray(conflictJobs) ? conflictJobs : []).forEach(job => {
                groups.push({
                    title: job.measure || 'MBQIP measure',
                    labels: (Array.isArray(job.existingLabels) ? job.existingLabels : []).filter(Boolean)
                });
            });
            (Array.isArray(hacsConflictJobs) ? hacsConflictJobs : []).forEach(job => {
                groups.push({
                    title: `HACs & HAIs ${job.year}`,
                    labels: (Array.isArray(job.measures) ? job.measures : []).filter(Boolean)
                });
            });
            return groups;
        },

        openWorkbookConflictModal(payload) {
            const modal = document.getElementById('dmWorkbookConflictModal');
            const body = document.getElementById('dmWorkbookConflictBody');
            const button = document.getElementById('dmWorkbookConflictContinue');
            if (!modal || !body) {
                this.saveGeneralWorkbookJobs(payload.jobs || [], payload.hacsJobs || [], payload.originalFileName || '');
                return;
            }
            const groups = Array.isArray(payload.groups) ? payload.groups : [];
            body.innerHTML = groups.map(group => {
                const labels = Array.isArray(group.labels) ? group.labels : [];
                return `
                    <section style="border:1px solid #e5e7eb; border-radius:10px; padding:12px 14px; background:#fff;">
                        <h3 style="margin:0 0 8px; color:var(--dm-primary); font-size:14px; font-weight:800;">${this.escapeHtml(group.title || 'Saved records')}</h3>
                        ${labels.length
                            ? `<ul style="margin:0; padding-left:20px; color:#475569; font-size:13px; line-height:1.55;">${labels.map(label => `<li>${this.escapeHtml(label)}</li>`).join('')}</ul>`
                            : '<p style="margin:0; color:#475569; font-size:13px;">Saved records will be replaced.</p>'}
                    </section>
                `;
            }).join('');
            this.pendingWorkbookConflict = {
                jobs: payload.jobs || [],
                hacsJobs: payload.hacsJobs || [],
                originalFileName: payload.originalFileName || ''
            };
            if (button) {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-sync-alt"></i> Replace Saved Records';
            }
            modal.style.display = 'flex';
        },

        closeWorkbookConflictModal(cancelled = false) {
            this.pendingWorkbookConflict = null;
            const modal = document.getElementById('dmWorkbookConflictModal');
            if (modal) modal.style.display = 'none';
            if (cancelled) {
                this.setState({ generalBulkStatus: 'Bulk upload cancelled.' }, { preserveScroll: true, scrollToTop: false });
            }
        },

        async confirmWorkbookConflictOverwrite() {
            const pending = this.pendingWorkbookConflict;
            if (!pending) {
                this.closeWorkbookConflictModal(false);
                return;
            }
            const button = document.getElementById('dmWorkbookConflictContinue');
            if (button) {
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Replacing...';
            }
            this.closeWorkbookConflictModal(false);
            await this.saveGeneralWorkbookJobs(pending.jobs || [], pending.hacsJobs || [], pending.originalFileName || '');
        },

        async saveGeneralWorkbookJobs(jobs, hacsJobs, originalFileName = '') {
            try {
                const totalJobs = jobs.length + hacsJobs.length;
                this.setState({ generalBulkStatus: `Saving ${totalJobs} populated Data Hub sheet${totalJobs === 1 ? '' : 's'}...` }, { preserveScroll: true, scrollToTop: false });
                let saved = 0;
                for (const job of jobs) {
                    const data = await this.submitBulkWorkbookRows(job.measure, job.templateType, job.rowsForSave, originalFileName, job.existingNames);
                    if (!data || !data.success) {
                        const message = data ? (data.data || data.message || 'Unknown error') : 'Unknown error';
                        this.showUploadError(`${job.measure}: ${message}`);
                        this.setState({ generalBulkStatus: `Stopped after saving ${saved} sheet${saved === 1 ? '' : 's'}.` }, { preserveScroll: true, scrollToTop: false });
                        this.render({ preserveScroll: true, scrollToTop: false });
                        return;
                    }
                    this.applyBulkWorkbookSaveResult(job.measure, data);
                    saved += 1;
                }
                for (const job of hacsJobs) {
                    const data = await this.submitHacsHaisBulkWorkbookRows(job.year, job.rowsForSave, !!job.existingSubmission);
                    if (!data || !data.success) {
                        const error = data ? data.data : null;
                        const message = error && error.message ? error.message : (typeof error === 'string' ? error : 'Unknown error');
                        this.showUploadError(`HACs & HAIs ${job.year}: ${message}`);
                        this.setState({ generalBulkStatus: `Stopped after saving ${saved} sheet${saved === 1 ? '' : 's'}.` }, { preserveScroll: true, scrollToTop: false });
                        this.render({ preserveScroll: true, scrollToTop: false });
                        return;
                    }
                    this.applyHacsHaisBulkSaveResult(data);
                    saved += 1;
                }
                this.hideUploadError();
                this.notifyMetricsChanged();
                this.setState({ generalBulkStatus: `Saved ${saved} Data Hub sheet${saved === 1 ? '' : 's'} successfully.` }, { preserveScroll: true, scrollToTop: false });
                this.showToast('Bulk workbook saved successfully.');
            } catch (error) {
                this.showUploadError('Could not read this bulk workbook. Please download a fresh workbook and try again.');
                this.setState({ generalBulkStatus: 'Bulk upload failed while reading the workbook.' }, { preserveScroll: true, scrollToTop: false });
            }
        },

        renderGlobalInfrastructureInputView(breadcrumb, measure) {
            const summary = this.globalChecklistSummary();
            const componentList = DM_GLOBAL_INFRASTRUCTURE_COMPONENTS.map(component => `<li>${component}</li>`).join('');
            return `
                ${breadcrumb}
                <div class="dm-header">
                    ${this.renderReportTitle(measure)}
                    <table class="dm-measure-spec">
                        <thead>
                            <tr>
                                <th>Measure name</th>
                                <th>${measure}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th>Description</th>
                                <td>
                                    Assesses the presence and maturity of quality improvement infrastructure elements within the CAH.
                                    <ol>${componentList}</ol>
                                </td>
                            </tr>
                            <tr>
                                <th>Numerator</th>
                                <td>Facility-level attestation or scored assessment of quality infrastructure components during the reporting period.</td>
                            </tr>
                            <tr>
                                <th>Denominator</th>
                                <td>One facility-level annual assessment submission per CAH.</td>
                            </tr>
                            <tr>
                                <th>Measure type</th>
                                <td>Facility-level structural/process measure.</td>
                            </tr>
                            <tr>
                                <th>Data source</th>
                                <td>Medicare Beneficiary Quality Improvement Project FMT online survey platform.</td>
                            </tr>
                            <tr>
                                <th>Data submission frequency</th>
                                <td>Annual — via FMT online survey.</td>
                            </tr>
                            <tr>
                                <th>Specifications/definitions</th>
                                <td>Credit is given when all eight required global measure criteria are met. The hospital score can be zero to eight points, with one point available for each component.</td>
                            </tr>
                            <tr>
                                <th>Exclusions</th>
                                <td>No patient-level exclusions — this is a facility-level structural/process measure.</td>
                            </tr>
                            <tr>
                                <th>Benchmark/performance</th>
                                <td>National benchmark: 100%. National performance rate: 27.5%. Most recent available 2024 data from the workbook.</td>
                            </tr>
                        </tbody>
                    </table>
                    ${this.renderMeasureGoalsPanel()}
                </div>

                ${this.renderMeasureTabs()}

                <div class="dm-input-pane ${this.state.inputTab !== 'database' ? 'active' : ''}">
                    <div class="dm-entry-section-title" style="margin-top:0;">Manual Entry</div>
                    <div class="dm-guide">
                        <i class="fas fa-info-circle"></i> <b>Assessment logic:</b> Credit is given when all eight required criteria are marked Yes.
                    </div>
                    ${this.renderUploadError()}

                    <div class="dm-row-actions top" style="align-items:center; margin-top:34px;">
                        <label style="display:flex; align-items:center; gap:8px; font-weight:700; color:var(--dm-primary);">
                            Year
                            <select class="dm-year-select" onchange="dmApp.updateChecklistYear(this.value)" style="width:120px;">
                                ${this.yearOptions().map(y => `<option value="${y}" ${String(this.state.checklistYear) === String(y) ? 'selected' : ''}>${y}</option>`).join('')}
                            </select>
                        </label>
                    </div>

                    <div class="dm-table-wrap">
                        <table class="dm-table" id="dmGlobalChecklistTable">
                            <thead>
                                <tr>
                                    <th>CAH Global Measure Component</th>
                                    <th style="width: 190px;">Criteria Met</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${this.state.checklistRows.map((row, idx) => `
                                    <tr>
                                        <td style="font-weight:600; color:var(--dm-primary);">${idx + 1}. ${row.component}</td>
                                        <td>
                                            <select onchange="dmApp.updateChecklistRow(${idx}, this.value)">
                                                <option value="">Select</option>
                                                <option value="Yes" ${row.met === 'Yes' ? 'selected' : ''}>Yes</option>
                                                <option value="No" ${row.met === 'No' ? 'selected' : ''}>No</option>
                                            </select>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>

                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:12px; margin-top:18px;">
                        <div class="dm-guide" style="margin:0;"><b>Criteria Met:</b> ${summary.met} of ${summary.total}</div>
                        <div class="dm-guide" style="margin:0;"><b>Rate:</b> ${summary.rate}%</div>
                    </div>

                    <div class="dm-save-section" style="display:flex; gap:12px; justify-content:flex-end; align-items:center; flex-wrap:wrap;">
                        <span id="dmSaveNote" style="font-size:13px; color:var(--dm-text-muted); display:${this.hasManualData() ? 'none' : 'inline-flex'}; align-items:center; gap:6px;">
                            <i class="fas fa-info-circle"></i> Answer Yes or No for all eight required criteria before saving.
                        </span>
                        <button type="button" id="dmSaveBtn" class="dm-btn" style="padding: 14px 40px; font-size: 15px; background: #03283E;" onclick="dmApp.saveAllData(event)" ${this.hasManualData() ? '' : 'disabled'}>
                            <i class="fas fa-cloud-upload-alt"></i> Save & Sync Assessment
                        </button>
                    </div>
                    ${this.renderSavedAssessmentPanel(null, true, false)}
                </div>

                <div class="dm-input-pane ${this.state.inputTab === 'database' ? 'active' : ''}">
                    ${this.renderRawDataTable()}
                </div>
            `;
        },

        renderHcpInfluenzaInputView(breadcrumb, measure) {
            const row = (this.state.manualRows || [])[0] || {};
            return `
                ${breadcrumb}
                <div class="dm-header">
                    ${this.renderReportTitle(measure)}
                    <table class="dm-measure-spec">
                        <thead>
                            <tr>
                                <th>Measure name</th>
                                <th>${measure}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th>Description</th>
                                <td>Measures the percentage of healthcare personnel (HCP) who received an influenza vaccination during the current influenza season.</td>
                            </tr>
                            <tr>
                                <th>Numerator</th>
                                <td>Number of HCP who received influenza vaccination, either on-site or documented off-site, during the current influenza season (October 1 - March 31).</td>
                            </tr>
                            <tr>
                                <th>Denominator</th>
                                <td>Total number of HCP working in the facility at any time during the influenza season.</td>
                            </tr>
                            <tr>
                                <th>Measure type</th>
                                <td>Annual vaccination rate.</td>
                            </tr>
                            <tr>
                                <th>Data source</th>
                                <td>NHSN HCP Influenza Vaccination Summary Protocol.</td>
                            </tr>
                            <tr>
                                <th>Data submission frequency</th>
                                <td>Annual — data collected Q4 (Oct-Mar); submitted to NHSN by March 31.</td>
                            </tr>
                            <tr>
                                <th>Specifications/definitions</th>
                                <td>Include all staff, licensed practitioners, students, and contractors with patient contact. Track vaccinations given on-site, documented off-site vaccinations, medical contraindications, and religious/philosophical declinations.</td>
                            </tr>
                            <tr>
                                <th>Exclusions</th>
                                <td>HCP who die or leave employment before vaccination season; HCP employed fewer than 30 days during the vaccination season; individuals with documented medical contraindications.</td>
                            </tr>
                            <tr>
                                <th>Benchmark/performance</th>
                                <td>National benchmark: 100%. National performance rate: 75.5%. Most recent available 2024 data from the workbook.</td>
                            </tr>
                        </tbody>
                    </table>
                    ${this.renderMeasureGoalsPanel()}
                </div>

                ${this.renderMeasureTabs()}

                <div class="dm-input-pane ${this.state.inputTab !== 'database' ? 'active' : ''}">
                    <div class="dm-entry-section-title" style="margin-top:0;">Manual Entry</div>
                    <div class="dm-guide">
                        <i class="fas fa-info-circle"></i> <b>Assessment logic:</b> Rate is calculated as Vaccinated HCP divided by Total Eligible HCP.
                    </div>
                    ${this.renderUploadError()}

                    <div class="dm-row-actions top" style="align-items:center; margin-top:34px;"></div>
                    <div class="dm-table-wrap dm-manual-table-wrap">
                        <table class="dm-table dm-manual-table dm-manual-table-annual" id="dmManualTable">
                            <colgroup><col class="dm-col-year"><col class="dm-col-num"><col class="dm-col-den"><col class="dm-col-rate"></colgroup>
                            <thead>
                                <tr>
                                    <th>Year</th>
                                    <th>Numerator</th>
                                    <th>Denominator</th>
                                    <th>Rate (%)</th>
                                </tr>
                            </thead>
                            <tbody id="dmManualTbody">
                                <tr>
                                    <td>
                                        <select class="dm-year-select" onchange="dmApp.updateManualRow(0, 'year', this.value)">
                                            ${this.yearOptions().map(y => `<option value="${y}" ${String(row.year || new Date().getFullYear()) === String(y) ? 'selected' : ''}>${y}</option>`).join('')}
                                        </select>
                                    </td>
                                    <td><input type="number" min="0" placeholder="0" value="${row.num || ''}" oninput="dmApp.updateManualRow(0, 'num', this.value)"></td>
                                    <td class="dm-den-cell"><input type="number" min="0" placeholder="0" value="${row.den || ''}" oninput="dmApp.updateManualRow(0, 'den', this.value)">${this.renderNumDenWarning(row)}</td>
                                    <td class="dm-rate-cell">
                                        ${this.formatRatePercent(row.num, row.den)}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="dm-save-section" style="display:flex; gap:12px; justify-content:flex-end; align-items:center; flex-wrap:wrap;">
                        <span id="dmSaveNote" style="font-size:13px; color:var(--dm-text-muted); display:${this.hasManualData() ? 'none' : 'inline-flex'}; align-items:center; gap:6px;">
                            <i class="fas fa-info-circle"></i> Enter the year, vaccinated healthcare personnel count, and total eligible healthcare personnel count before saving.
                        </span>
                        <button type="button" id="dmSaveBtn" class="dm-btn" style="padding: 14px 40px; font-size: 15px; background: #03283E;" onclick="dmApp.saveAllData(event)" ${this.hasManualData() ? '' : 'disabled'}>
                            <i class="fas fa-cloud-upload-alt"></i> Save & Sync Assessment
                        </button>
                    </div>
                    ${this.renderSavedAssessmentPanel(null, true, false)}
                </div>

                <div class="dm-input-pane ${this.state.inputTab === 'database' ? 'active' : ''}">
                    ${this.renderRawDataTable()}
                </div>
            `;
        },

        renderAntibioticStewardshipInputView(breadcrumb, measure) {
            const summary = this.globalChecklistSummary();
            const componentList = DM_ANTIBIOTIC_STEWARDSHIP_COMPONENTS.map(component => `<li>${component}</li>`).join('');
            return `
                ${breadcrumb}
                <div class="dm-header">
                    ${this.renderReportTitle(measure)}
                    <table class="dm-measure-spec">
                        <thead>
                            <tr>
                                <th>Measure name</th>
                                <th>${measure}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th>Description</th>
                                <td>
                                    Tracks implementation of Centers for Disease Control and Prevention Core Elements of Hospital Antibiotic Stewardship Programs, supporting structured antibiotic oversight to reduce Clostridioides difficile, resistance, and unnecessary antibiotic use.
                                    <ol>${componentList}</ol>
                                </td>
                            </tr>
                            <tr>
                                <th>Numerator</th>
                                <td>Attestation of implementation of all seven Centers for Disease Control and Prevention Core Elements: leadership commitment, accountability, drug expertise, action, tracking, reporting, and education.</td>
                            </tr>
                            <tr>
                                <th>Denominator</th>
                                <td>N/A — facility-level structural measure.</td>
                            </tr>
                            <tr>
                                <th>Measure type</th>
                                <td>Facility-level structural/process measure.</td>
                            </tr>
                            <tr>
                                <th>Data source</th>
                                <td>National Healthcare Safety Network Annual Facility Survey.</td>
                            </tr>
                            <tr>
                                <th>Data submission frequency</th>
                                <td>Annual — submitted via National Healthcare Safety Network Annual Facility Survey.</td>
                            </tr>
                            <tr>
                                <th>Specifications/definitions</th>
                                <td>Centers for Disease Control and Prevention 7 Core Elements assessed via National Healthcare Safety Network Annual Facility Survey. The page calculates the rate as core elements marked Yes out of seven and calculates improvement against the prior saved year when saved.</td>
                            </tr>
                            <tr>
                                <th>Exclusions</th>
                                <td>No patient-level exclusions — this is a facility-level structural measure. Facilities without an inpatient pharmacy may have modified implementation guidance.</td>
                            </tr>
                            <tr>
                                <th>Benchmark/performance</th>
                                <td>National benchmark: 100%. National performance rate: 93.6%. Most recent available 2024 data from the workbook.</td>
                            </tr>
                        </tbody>
                    </table>
                    ${this.renderMeasureGoalsPanel()}
                </div>

                ${this.renderMeasureTabs()}

                <div class="dm-input-pane ${this.state.inputTab !== 'database' ? 'active' : ''}">
                    <div class="dm-entry-section-title" style="margin-top:0;">Manual Entry</div>
                    <div class="dm-guide">
                        <i class="fas fa-info-circle"></i> <b>Assessment logic:</b> Rate is calculated as the number of core elements marked Yes out of 7. Improvement is calculated against the prior saved year when the file is saved.
                    </div>
                    ${this.renderUploadError()}

                    <div class="dm-row-actions top" style="align-items:center; margin-top:34px;">
                        <label style="display:flex; align-items:center; gap:8px; font-weight:700; color:var(--dm-primary);">
                            Year
                            <select class="dm-year-select" onchange="dmApp.updateChecklistYear(this.value)" style="width:120px;">
                                ${this.yearOptions().map(y => `<option value="${y}" ${String(this.state.checklistYear) === String(y) ? 'selected' : ''}>${y}</option>`).join('')}
                            </select>
                        </label>
                    </div>

                    <div class="dm-table-wrap">
                        <table class="dm-table" id="dmAntibioticChecklistTable">
                            <thead>
                                <tr>
                                    <th>CDC 7 Core Elements</th>
                                    <th style="width: 190px;">Criteria Met</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${this.state.checklistRows.map((row, idx) => `
                                    <tr>
                                        <td style="font-weight:600; color:var(--dm-primary);">${idx + 1}. ${row.component}</td>
                                        <td>
                                            <select onchange="dmApp.updateChecklistRow(${idx}, this.value)">
                                                <option value="">Select</option>
                                                <option value="Yes" ${row.met === 'Yes' ? 'selected' : ''}>Yes</option>
                                                <option value="No" ${row.met === 'No' ? 'selected' : ''}>No</option>
                                            </select>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>

                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-top:18px;">
                        <div class="dm-guide" style="margin:0;"><b>Elements Met:</b> ${summary.met} of ${summary.total}</div>
                        <div class="dm-guide" style="margin:0;"><b>Rate:</b> ${summary.rate}%</div>
                    </div>

                    <div class="dm-save-section" style="display:flex; gap:12px; justify-content:flex-end; align-items:center; flex-wrap:wrap;">
                        <span id="dmSaveNote" style="font-size:13px; color:var(--dm-text-muted); display:${this.hasManualData() ? 'none' : 'inline-flex'}; align-items:center; gap:6px;">
                            <i class="fas fa-info-circle"></i> Answer Yes or No for all seven core elements before saving.
                        </span>
                        <button type="button" id="dmSaveBtn" class="dm-btn" style="padding: 14px 40px; font-size: 15px; background: #03283E;" onclick="dmApp.saveAllData(event)" ${this.hasManualData() ? '' : 'disabled'}>
                            <i class="fas fa-cloud-upload-alt"></i> Save & Sync Assessment
                        </button>
                    </div>
                    ${this.renderSavedAssessmentPanel(null, true, false)}
                </div>

                <div class="dm-input-pane ${this.state.inputTab === 'database' ? 'active' : ''}">
                    ${this.renderRawDataTable()}
                </div>
            `;
        },

        renderSafeUseOpioidsInputView(breadcrumb, measure) {
            return `
                ${breadcrumb}
                <div class="dm-header">
                    ${this.renderReportTitle(measure)}
                    <table class="dm-measure-spec">
                        <thead>
                            <tr>
                                <th>Measure name</th>
                                <th>${measure}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th>Description</th>
                                <td>Electronic clinical quality measure measuring the percentage of hospitalized patients who are concurrently prescribed two or more opioids or an opioid with a benzodiazepine at discharge.</td>
                            </tr>
                            <tr>
                                <th>Numerator</th>
                                <td>Number of patients discharged with concurrent opioid prescriptions or an opioid + benzodiazepine combination.</td>
                            </tr>
                            <tr>
                                <th>Denominator</th>
                                <td>All inpatient discharges during the reporting period for patients aged 18+.</td>
                            </tr>
                            <tr>
                                <th>Measure type</th>
                                <td>Electronic clinical quality measure rate. Lower rate is better.</td>
                            </tr>
                            <tr>
                                <th>Data source</th>
                                <td>Certified electronic health record technology, per Centers for Medicare & Medicaid Services Electronic Clinical Quality Improvement Resource Center specifications.</td>
                            </tr>
                            <tr>
                                <th>Data submission frequency</th>
                                <td>Annual for Promoting Interoperability program; quarterly Medicare Beneficiary Quality Improvement Project submission.</td>
                            </tr>
                            <tr>
                                <th>Specifications/definitions</th>
                                <td>Report year, month, numerator, and denominator. Rate is calculated automatically as numerator divided by denominator and shown for verification.</td>
                            </tr>
                            <tr>
                                <th>Exclusions</th>
                                <td>Patients receiving concurrent opioids for documented active cancer pain management; palliative/hospice care patients; patients with documented pain specialist oversight of concurrent regimen; discharges to inpatient settings where prescriptions are not discharged with the patient.</td>
                            </tr>
                            <tr>
                                <th>Benchmark/performance</th>
                                <td>National benchmark: 16.6%. National performance rate: 16.6%. Most recent available 2024 data from the workbook.</td>
                            </tr>
                        </tbody>
                    </table>
                    ${this.renderMeasureGoalsPanel()}
                </div>

                ${this.renderMeasureTabs()}

                <div class="dm-input-pane ${this.state.inputTab !== 'database' ? 'active' : ''}">
                    <div class="dm-entry-section-title" style="margin-top:0;">Manual Entry</div>
                    <div class="dm-guide">
                        <i class="fas fa-info-circle"></i> <b>Assessment logic:</b> Rate is calculated as Numerator divided by Denominator. Lower rates are better.
                    </div>
                    ${this.renderUploadError()}

                    <div class="dm-table-wrap dm-manual-table-wrap">
                        <table class="dm-table dm-manual-table dm-manual-table-monthly" id="dmManualTable">
                            <colgroup><col class="dm-col-month"><col class="dm-col-year"><col class="dm-col-num"><col class="dm-col-den"><col class="dm-col-rate"></colgroup>
                            <thead>
                                <tr>
                                    <th>Year</th>
                                    <th>Month</th>
                                    <th>Numerator</th>
                                    <th>Denominator</th>
                                    <th>Rate (%)</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="dmManualTbody">
                                ${this.state.manualRows.map((row, idx) => `
                                    <tr>
                                        <td>
                                            <select onchange="dmApp.updateManualRow(${idx}, 'year', this.value)">
                                                <option value="">Year</option>
                                                ${this.yearOptions().map(y => `<option value="${y}" ${String(row.year) === String(y) ? 'selected' : ''}>${y}</option>`).join('')}
                                            </select>
                                        </td>
                                        <td>
                                            <select onchange="dmApp.updateManualRow(${idx}, 'month', this.value)">
                                                <option value="">Month</option>
                                                ${this.monthOptions().map(m => `<option value="${m}" ${row.month === m ? 'selected' : ''}>${m}</option>`).join('')}
                                            </select>
                                        </td>
                                        <td><input type="number" placeholder="0" value="${row.num}" oninput="dmApp.updateManualRow(${idx}, 'num', this.value)"></td>
                                        <td class="dm-den-cell"><input type="number" placeholder="0" value="${row.den}" oninput="dmApp.updateManualRow(${idx}, 'den', this.value)">${this.renderNumDenWarning(row)}</td>
                                        <td class="dm-rate-cell">
                                            ${this.formatRatePercent(row.num, row.den)}
                                        </td>
                                        ${this.renderManualRowAction(idx)}
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>

                    <div class="dm-save-section" style="display:flex; gap:12px; justify-content:flex-end; align-items:center; flex-wrap:wrap;">
                        <span id="dmSaveNote" style="font-size:13px; color:var(--dm-text-muted); display:${this.hasManualData() ? 'none' : 'inline-flex'}; align-items:center; gap:6px;">
                            <i class="fas fa-info-circle"></i> Enter one complete month/year numerator and denominator row before saving.
                        </span>
                        <button type="button" id="dmSaveBtn" class="dm-btn" style="padding: 14px 40px; font-size: 15px; background: #03283E;" onclick="dmApp.saveAllData(event)" ${this.hasManualData() ? '' : 'disabled'}>
                            <i class="fas fa-cloud-upload-alt"></i> Save & Sync Data
                        </button>
                    </div>
                    ${this.renderSavedAssessmentPanel(null, true, false)}
                </div>

                <div class="dm-input-pane ${this.state.inputTab === 'database' ? 'active' : ''}">
                    ${this.renderRawDataTable()}
                </div>
            `;
        },

        renderHcahpsInputView(breadcrumb, measure) {
            const row = (this.state.manualRows || [])[0] || {};
            const details = DM_HCAHPS_MEASURE_DETAILS[measure] || {};
            return `
                ${breadcrumb}
                <div class="dm-header">
                    ${this.renderReportTitle(measure)}
                    <table class="dm-measure-spec">
                        <thead>
                            <tr>
                                <th>Measure name</th>
                                <th>${measure}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th>Description</th>
                                <td>${details.description || 'HCAHPS patient experience measure.'}</td>
                            </tr>
                            <tr>
                                <th>Numerator</th>
                                <td>${details.numerator || 'Number of qualifying top-box responses for the selected HCAHPS measure.'}</td>
                            </tr>
                            <tr>
                                <th>Denominator</th>
                                <td>${details.denominator || 'All eligible adult inpatient discharges surveyed.'}</td>
                            </tr>
                            <tr>
                                <th>Measure type</th>
                                <td>Patient experience survey rate.</td>
                            </tr>
                            <tr>
                                <th>Data source</th>
                                <td>HCAHPS survey results.</td>
                            </tr>
                            <tr>
                                <th>Data submission frequency</th>
                                <td>${details.frequency || 'Quarterly; annual Care Compare reporting.'}</td>
                            </tr>
                            <tr>
                                <th>Specifications/definitions</th>
                                <td>Report one quarterly result using year, quarter, numerator, and denominator. Rate is calculated automatically as numerator divided by denominator.</td>
                            </tr>
                            <tr>
                                <th>Exclusions</th>
                                <td>${details.exclusions || 'Use the applicable HCAHPS exclusion rules for eligible adult inpatient discharges.'}</td>
                            </tr>
                            <tr>
                                <th>Benchmark/performance</th>
                                <td>${details.benchmark || 'Benchmark data was not provided in the workbook.'} Most recent available 2024 data from the workbook.</td>
                            </tr>
                        </tbody>
                    </table>
                    ${this.renderMeasureGoalsPanel()}
                </div>

                ${this.renderMeasureTabs()}

                <div class="dm-input-pane ${this.state.inputTab !== 'database' ? 'active' : ''}">
                    <div class="dm-entry-section-title" style="margin-top:0;">Manual Entry</div>
                    <div class="dm-guide">
                        <i class="fas fa-info-circle"></i> <b>Assessment logic:</b> Enter one HCAHPS quarterly result with Year, Quarter, Num, Denom, and Rate columns.
                    </div>
                    <div class="dm-table-wrap dm-manual-table-wrap">
                        <table class="dm-table dm-manual-table dm-manual-table-quarter-rate" id="dmManualTable">
                            <colgroup><col class="dm-col-year"><col class="dm-col-quarter"><col class="dm-col-num"><col class="dm-col-den"><col class="dm-col-rate"></colgroup>
                            <thead>
                                <tr>
                                    <th>Year</th>
                                    <th>Quarter</th>
                                    <th>Numerator</th>
                                    <th>Denominator</th>
                                    <th>Rate (%)</th>
                                </tr>
                            </thead>
                            <tbody id="dmManualTbody">
                                <tr>
                                    <td>
                                        <select class="dm-year-select" onchange="dmApp.updateManualRow(0, 'year', this.value)">
                                            ${this.yearOptions().map(y => `<option value="${y}" ${String(row.year || new Date().getFullYear()) === String(y) ? 'selected' : ''}>${y}</option>`).join('')}
                                        </select>
                                    </td>
                                    <td>
                                        <select onchange="dmApp.updateManualRow(0, 'month', this.value)">
                                            <option value="">Quarter</option>
                                            ${this.quarterOptions().map(q => `<option value="${q}" ${row.month === q ? 'selected' : ''}>${q}</option>`).join('')}
                                        </select>
                                    </td>
                                    <td><input type="number" min="0" placeholder="0" value="${row.num || ''}" oninput="dmApp.updateManualRow(0, 'num', this.value)"></td>
                                    <td class="dm-den-cell"><input type="number" min="0" placeholder="0" value="${row.den || ''}" oninput="dmApp.updateManualRow(0, 'den', this.value)">${this.renderNumDenWarning(row)}</td>
                                    <td class="dm-rate-cell">
                                        ${this.formatRatePercent(row.num, row.den)}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="dm-save-section" style="display:flex; gap:12px; justify-content:flex-end; align-items:center; flex-wrap:wrap;">
                        <span id="dmSaveNote" style="font-size:13px; color:var(--dm-text-muted); display:${this.hasManualData() ? 'none' : 'inline-flex'}; align-items:center; gap:6px;">
                            <i class="fas fa-info-circle"></i> Enter the year, quarter, numerator, and denominator before saving.
                        </span>
                        <button type="button" id="dmSaveBtn" class="dm-btn" style="padding: 14px 40px; font-size: 15px; background: #03283E;" onclick="dmApp.saveAllData(event)" ${this.hasManualData() ? '' : 'disabled'}>
                            <i class="fas fa-cloud-upload-alt"></i> Save & Sync Data
                        </button>
                    </div>
                    ${this.renderSavedAssessmentPanel(null, true, false)}
                </div>

                <div class="dm-input-pane ${this.state.inputTab === 'database' ? 'active' : ''}">
                    ${this.renderRawDataTable()}
                </div>
            `;
        },

        renderHwrInputView(breadcrumb, measure) {
            const row = (this.state.manualRows || [])[0] || {};
            return `
                ${breadcrumb}
                <div class="dm-header">
                    ${this.renderReportTitle(measure)}
                    <table class="dm-measure-spec">
                        <thead>
                            <tr>
                                <th>Measure name</th>
                                <th>${measure}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th>Description</th>
                                <td>All-cause unplanned hospital-wide readmissions within 30 days using a hybrid methodology combining administrative claims and electronic clinical data from the EHR.</td>
                            </tr>
                            <tr>
                                <th>Numerator</th>
                                <td>Number of unplanned all-cause inpatient readmissions to any acute care facility within 30 days of index discharge, using hybrid claims and EHR clinical data methodology.</td>
                            </tr>
                            <tr>
                                <th>Denominator</th>
                                <td>All inpatient discharges, excluding deaths and planned readmissions, during the reporting period.</td>
                            </tr>
                            <tr>
                                <th>Measure type</th>
                                <td>Monthly readmission rate. Lower rate is better.</td>
                            </tr>
                            <tr>
                                <th>Data source</th>
                                <td>Medicare claims data combined with EHR-extracted clinical variables for risk adjustment.</td>
                            </tr>
                            <tr>
                                <th>Data submission frequency</th>
                                <td>Monthly.</td>
                            </tr>
                            <tr>
                                <th>Specifications/definitions</th>
                                <td>Combines Medicare claims with EHR clinical variables, including vital signs and lab values, for risk adjustment. CAHs submit both HWR and standard 30-day readmission data for benchmarking.</td>
                            </tr>
                            <tr>
                                <th>Exclusions</th>
                                <td>Deaths during the index hospitalization; planned readmissions per CMS Planned Readmission Algorithm; transfers from acute care setting; patients lacking sufficient claims history for risk adjustment.</td>
                            </tr>
                            <tr>
                                <th>Benchmark/performance</th>
                                <td>National benchmark was not provided in the workbook. National performance rate: 15.0%. Most recent available 2024 data from the workbook.</td>
                            </tr>
                        </tbody>
                    </table>
                    ${this.renderMeasureGoalsPanel()}
                </div>

                ${this.renderMeasureTabs()}

                <div class="dm-input-pane ${this.state.inputTab !== 'database' ? 'active' : ''}">
                    <div class="dm-entry-section-title" style="margin-top:0;">Manual Entry</div>
                    <div class="dm-guide">
                        <i class="fas fa-info-circle"></i> <b>Assessment logic:</b> Rate is calculated as Numerator divided by Denominator. Lower rates are better.
                    </div>
                    ${this.renderUploadError()}

                    <div class="dm-row-actions top" style="align-items:center; margin-top:34px;"></div>
                    <div class="dm-table-wrap dm-manual-table-wrap">
                        <table class="dm-table dm-manual-table dm-manual-table-monthly" id="dmManualTable">
                            <colgroup><col class="dm-col-year"><col class="dm-col-month"><col class="dm-col-num"><col class="dm-col-den"><col class="dm-col-rate"></colgroup>
                            <thead>
                                <tr>
                                    <th>Year</th>
                                    <th>Month</th>
                                    <th>Numerator</th>
                                    <th>Denominator</th>
                                    <th>Rate (%)</th>
                                </tr>
                            </thead>
                            <tbody id="dmManualTbody">
                                <tr>
                                    <td>
                                        <select class="dm-year-select" onchange="dmApp.updateManualRow(0, 'year', this.value)">
                                            ${this.yearOptions().map(y => `<option value="${y}" ${String(row.year || new Date().getFullYear()) === String(y) ? 'selected' : ''}>${y}</option>`).join('')}
                                        </select>
                                    </td>
                                    <td>
                                        <select onchange="dmApp.updateManualRow(0, 'month', this.value)">
                                            <option value="">Month</option>
                                            ${this.monthOptions().map(month => `<option value="${month}" ${row.month === month ? 'selected' : ''}>${month}</option>`).join('')}
                                        </select>
                                    </td>
                                    <td><input type="number" min="0" placeholder="0" value="${row.num || ''}" oninput="dmApp.updateManualRow(0, 'num', this.value)"></td>
                                    <td class="dm-den-cell"><input type="number" min="0" placeholder="0" value="${row.den || ''}" oninput="dmApp.updateManualRow(0, 'den', this.value)">${this.renderNumDenWarning(row)}</td>
                                    <td class="dm-rate-cell">
                                        ${this.formatRatePercent(row.num, row.den)}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="dm-save-section" style="display:flex; gap:12px; justify-content:flex-end; align-items:center; flex-wrap:wrap;">
                        <span id="dmSaveNote" style="font-size:13px; color:var(--dm-text-muted); display:${this.hasManualData() ? 'none' : 'inline-flex'}; align-items:center; gap:6px;">
                            <i class="fas fa-info-circle"></i> Enter the year, month, numerator, and denominator before saving.
                        </span>
                        <button type="button" id="dmSaveBtn" class="dm-btn" style="padding: 14px 40px; font-size: 15px; background: #03283E;" onclick="dmApp.saveAllData(event)" ${this.hasManualData() ? '' : 'disabled'}>
                            <i class="fas fa-cloud-upload-alt"></i> Save & Sync Data
                        </button>
                    </div>
                    ${this.renderSavedAssessmentPanel(null, true, false)}
                </div>

                <div class="dm-input-pane ${this.state.inputTab === 'database' ? 'active' : ''}">
                    ${this.renderRawDataTable()}
                </div>
            `;
        },

        renderEdtcInputView(breadcrumb, measure) {
            const elementSummary = this.edtcNumDenSummary();
            const compositeSummary = this.edtcCompositeStateSummary();
            return `
                ${breadcrumb}
                <div class="dm-header">
                    ${this.renderReportTitle(measure)}
                    <table class="dm-measure-spec">
                        <thead>
                            <tr>
                                <th>Measure name</th>
                                <th>${measure}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th>Description</th>
                                <td>Evaluates the completeness, accuracy, and timeliness of clinical information communicated when transferring ED patients to receiving hospitals, addressing patient safety at care transitions.</td>
                            </tr>
                            <tr>
                                <th>Numerator</th>
                                <td>Composite numerator is the number of reviewed records where all required Emergency Department Transfer Communication elements were documented. Each individual element also has its own numerator count.</td>
                            </tr>
                            <tr>
                                <th>Denominator</th>
                                <td>Composite denominator is the total records reviewed for the selected quarter. Each individual element also has its own denominator count.</td>
                            </tr>
                            <tr>
                                <th>Measure type</th>
                                <td>Quarterly transfer communication rate. Higher rate is better.</td>
                            </tr>
                            <tr>
                                <th>Data source</th>
                                <td>MBQIP EDTC data specifications and local abstraction/source tracking for ED transfers.</td>
                            </tr>
                            <tr>
                                <th>Data submission frequency</th>
                                <td>Quarterly tracking in Data Hub.</td>
                            </tr>
                            <tr>
                                <th>Specifications/definitions</th>
                                <td>Choose the reporting year and quarter, enter the composite score for records with all eight elements documented, then enter numerator and denominator counts for each of the eight required ED transfer communication elements.</td>
                            </tr>
                            <tr>
                                <th>Exclusions</th>
                                <td>AMA/left against medical advice; expired; discharged home or to assisted living, residential care, law enforcement, home health, outpatient services, hospice at home, observation status, or an undocumented/undetermined discharge location.</td>
                            </tr>
                            <tr>
                                <th>Benchmark/performance</th>
                                <td>National benchmark: 100%. National performance rate: 92.3%. Most recent available 2024 data from the workbook.</td>
                            </tr>
                        </tbody>
                    </table>
                    ${this.renderMeasureGoalsPanel()}
                </div>

                ${this.renderMeasureTabs()}

                <div class="dm-input-pane ${this.state.inputTab !== 'database' ? 'active' : ''}">
                    <div class="dm-guide">
                        <i class="fas fa-info-circle"></i> <b>Assessment logic:</b> The composite score trends records where all elements were documented out of the total records reviewed. Each Emergency Department Transfer Communication element also has its own run chart.
                    </div>
                    ${this.renderUploadError()}

                    <div class="dm-edtc-entry-block">
                        <div class="dm-entry-section-title" style="margin-top:0;">Manual Entry</div>
                        <div class="dm-row-actions top" style="align-items:center; margin-top:0;">
                            <label style="display:flex; align-items:center; gap:8px; font-weight:700; color:var(--dm-primary);">
                                Year
                                <select class="dm-year-select" onchange="dmApp.updateChecklistYear(this.value)" style="width:120px;">
                                    ${this.yearOptions().map(y => `<option value="${y}" ${String(this.state.checklistYear) === String(y) ? 'selected' : ''}>${y}</option>`).join('')}
                                </select>
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-weight:700; color:var(--dm-primary);">
                                Quarter
                                <select class="dm-quarter-select" onchange="dmApp.updateChecklistQuarter(this.value)" style="width:120px;">
                                    ${this.quarterOptions().map(q => `<option value="${q}" ${String(this.state.checklistQuarter || 'Q1') === q ? 'selected' : ''}>${q}</option>`).join('')}
                                </select>
                            </label>
                        </div>
                        <div class="dm-manual-section" style="margin-top:18px;">
                            <div style="font-size:20px; font-weight:800; color:var(--dm-primary); margin-bottom:14px;">Composite Score</div>
                            <div class="dm-measure-goals-grid" style="grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));">
                                <div class="dm-goal-field">
                                    <label>Composite Numerator</label>
                                    <input type="number" min="0" placeholder="0" value="${this.escapeHtml(this.state.edtcCompositeNum || '')}" oninput="dmApp.updateEdtcCompositeField('edtcCompositeNum', this.value)">
                                </div>
                                <div class="dm-goal-field">
                                    <label>Composite Denominator</label>
                                    <div class="dm-den-cell" id="dmEdtcCompositeDenCell">
                                        <input type="number" min="0" placeholder="0" value="${this.escapeHtml(this.state.edtcCompositeDen || '')}" oninput="dmApp.updateEdtcCompositeField('edtcCompositeDen', this.value)">
                                        ${this.renderNumDenWarning({ num: this.state.edtcCompositeNum, den: this.state.edtcCompositeDen })}
                                    </div>
                                </div>
                                <div class="dm-goal-field">
                                    <label>Composite Rate</label>
                                    <input type="text" id="dmEdtcCompositeRate" readonly value="${this.escapeHtml(compositeSummary.rate)}%">
                                </div>
                            </div>
                            <div class="dm-guide" style="margin-top:16px;">
                                <i class="fas fa-info-circle"></i> Enter the number of reviewed records with all eight required Emergency Department Transfer Communication elements documented, out of the total records reviewed for the quarter. The composite rate calculates automatically from these two fields.
                            </div>
                        </div>
                        <div style="font-size:20px; font-weight:800; color:var(--dm-primary); margin:26px 0 14px;">EDTC Elements</div>
                        <div class="dm-table-wrap dm-edtc-table-wrap">
                            <table class="dm-table dm-edtc-table" id="dmEdtcChecklistTable">
                                <thead>
                                    <tr>
                                        <th>EDTC Transfer Communication Element</th>
                                        <th style="width: 130px;">Numerator</th>
                                        <th style="width: 130px;">Denominator</th>
                                        <th style="width: 110px;">Rate (%)</th>
                                    </tr>
                                </thead>
                                <tbody id="dmEdtcChecklistTbody">
                                    ${this.state.checklistRows.map((row, idx) => `
                                        <tr>
                                            <td style="font-weight:600; color:var(--dm-primary);">${idx + 1}. ${row.component}</td>
                                            <td><input type="number" min="0" placeholder="0" value="${row.num || ''}" oninput="dmApp.updateEdtcElementRow(${idx}, 'num', this.value)"></td>
                                            <td class="dm-den-cell"><input type="number" min="0" placeholder="0" value="${row.den || ''}" oninput="dmApp.updateEdtcElementRow(${idx}, 'den', this.value)">${this.renderNumDenWarning(row)}</td>
                                            <td class="dm-rate-cell">${this.formatRatePercent(row.num, row.den)}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>

                        <div class="dm-guide" style="margin-top:18px;">
                            <i class="fas fa-info-circle"></i>
                            <b>Entry progress:</b> <span id="dmEdtcElementSummaryComplete">${elementSummary.completeRows}</span> of <span id="dmEdtcElementSummaryTotal">${elementSummary.total}</span> element rows completed.
                            Composite summary: <span id="dmEdtcCompositeSummaryNum">${compositeSummary.num}</span> / <span id="dmEdtcCompositeSummaryDen">${compositeSummary.den}</span> (<span id="dmEdtcCompositeSummaryRate">${compositeSummary.rate}%</span>).
                        </div>

                        <div class="dm-save-section" style="display:flex; gap:12px; justify-content:flex-end; align-items:center; flex-wrap:wrap;">
                            <span id="dmSaveNote" style="font-size:13px; color:var(--dm-text-muted); display:${this.hasManualData() ? 'none' : 'inline-flex'}; align-items:center; gap:6px;">
                                <i class="fas fa-info-circle"></i> Select a year and quarter, enter the composite score, then enter numerator and denominator values for all eight Emergency Department Transfer Communication elements before saving.
                            </span>
                            <button type="button" id="dmSaveBtn" class="dm-btn" style="padding: 14px 40px; font-size: 15px; background: #03283E;" onclick="dmApp.saveAllData(event)" ${this.hasManualData() ? '' : 'disabled'}>
                                <i class="fas fa-cloud-upload-alt"></i> Save & Sync Assessment
                            </button>
                        </div>
                    </div>
                    ${this.renderSavedAssessmentPanel(null, true, false)}
                </div>

                <div class="dm-input-pane ${this.state.inputTab === 'database' ? 'active' : ''}">
                    ${this.renderRawDataTable()}
                </div>
            `;
        },

        renderOp18InputView(breadcrumb, measure) {
            const row = (this.state.manualRows || [])[0] || {};
            return `
                ${breadcrumb}
                <div class="dm-header">
                    ${this.renderReportTitle(measure)}
                    <table class="dm-measure-spec">
                        <thead>
                            <tr>
                                <th>Measure name</th>
                                <th>${measure}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th>Description</th>
                                <td>Tracks the median time from Emergency Department arrival to Emergency Department departure for patients who are discharged and not admitted.</td>
                            </tr>
                            <tr>
                                <th>Numerator</th>
                                <td>Median time, in minutes, from Emergency Department arrival to Emergency Department departure for Emergency Department patients who are discharged and not admitted to the hospital during the reporting period.</td>
                            </tr>
                            <tr>
                                <th>Denominator</th>
                                <td>All ED patient visits resulting in discharge, excluding inpatient admissions, during the reporting period.</td>
                            </tr>
                            <tr>
                                <th>Measure type</th>
                                <td>Quarterly median time measure. Lower time is better.</td>
                            </tr>
                            <tr>
                                <th>Data source</th>
                                <td>CMS OP-18 OQR specifications, submitted via CMS HQR through Outpatient CART tool or approved vendor.</td>
                            </tr>
                            <tr>
                                <th>Data submission frequency</th>
                                <td>Quarterly — submitted via HQR CART or vendor.</td>
                            </tr>
                            <tr>
                                <th>Specifications/definitions</th>
                                <td>Uses arrival time, such as registration or nurse triage, and departure time when the patient physically leaves the ED. This is measured in minutes, not as a rate.</td>
                            </tr>
                            <tr>
                                <th>Exclusions</th>
                                <td>Patients who die in the ED before discharge; patients transferred to inpatient status; patients who leave without being seen.</td>
                            </tr>
                            <tr>
                                <th>Benchmark/performance</th>
                                <td>National benchmark: 84 minutes. National performance rate: 114 minutes. Most recent available 2024 data from the workbook.</td>
                            </tr>
                        </tbody>
                    </table>
                    ${this.renderMeasureGoalsPanel()}
                </div>

                ${this.renderMeasureTabs()}

                <div class="dm-input-pane ${this.state.inputTab !== 'database' ? 'active' : ''}">
                    <div class="dm-entry-section-title" style="margin-top:0;">Manual Entry</div>
                    <div class="dm-guide">
                        <i class="fas fa-info-circle"></i> <b>Assessment logic:</b> Enter the quarterly median Emergency Department arrival-to-departure time in minutes. Lower times are better.
                    </div>
                    ${this.renderUploadError()}

                    <div class="dm-row-actions top" style="align-items:center; margin-top:34px;"></div>
                    <div class="dm-table-wrap dm-manual-table-wrap">
                        <table class="dm-table dm-manual-table dm-manual-table-quarter-median" id="dmManualTable">
                            <colgroup><col class="dm-col-year"><col class="dm-col-quarter"><col class="dm-col-median"></colgroup>
                            <thead>
                                <tr>
                                    <th>Year</th>
                                    <th>Quarter</th>
                                    <th>Median Minutes</th>
                                </tr>
                            </thead>
                            <tbody id="dmManualTbody">
                                <tr>
                                    <td>
                                        <select class="dm-year-select" onchange="dmApp.updateManualRow(0, 'year', this.value)">
                                            ${this.yearOptions().map(y => `<option value="${y}" ${String(row.year || new Date().getFullYear()) === String(y) ? 'selected' : ''}>${y}</option>`).join('')}
                                        </select>
                                    </td>
                                    <td>
                                        <select onchange="dmApp.updateManualRow(0, 'month', this.value)">
                                            <option value="">Quarter</option>
                                            ${this.quarterOptions().map(q => `<option value="${q}" ${row.month === q ? 'selected' : ''}>${q}</option>`).join('')}
                                        </select>
                                    </td>
                                    <td><input type="number" min="0" placeholder="0" value="${row.median || ''}" oninput="dmApp.updateManualRow(0, 'median', this.value)"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="dm-save-section" style="display:flex; gap:12px; justify-content:flex-end; align-items:center; flex-wrap:wrap;">
                        <span id="dmSaveNote" style="font-size:13px; color:var(--dm-text-muted); display:${this.hasManualData() ? 'none' : 'inline-flex'}; align-items:center; gap:6px;">
                            <i class="fas fa-info-circle"></i> Enter the year, quarter, and median minutes before saving.
                        </span>
                        <button type="button" id="dmSaveBtn" class="dm-btn" style="padding: 14px 40px; font-size: 15px; background: #03283E;" onclick="dmApp.saveAllData(event)" ${this.hasManualData() ? '' : 'disabled'}>
                            <i class="fas fa-cloud-upload-alt"></i> Save & Sync Data
                        </button>
                    </div>
                    ${this.renderSavedAssessmentPanel(null, true, false)}
                </div>

                <div class="dm-input-pane ${this.state.inputTab === 'database' ? 'active' : ''}">
                    ${this.renderRawDataTable()}
                </div>
            `;
        },

        renderOp22InputView(breadcrumb, measure) {
            const row = (this.state.manualRows || [])[0] || {};
            return `
                ${breadcrumb}
                <div class="dm-header">
                    ${this.renderReportTitle(measure)}
                    <div class="dm-spec-choice" aria-label="OP-22 measure detail view">
                        <button type="button" class="active" data-spec-key="op22" data-spec-view="tiles" onclick="dmApp.toggleMeasureSpecView('op22', 'tiles')">Tiles</button>
                        <button type="button" data-spec-key="op22" data-spec-view="trend" onclick="dmApp.toggleMeasureSpecView('op22', 'trend')">Trend</button>
                        <button type="button" data-spec-key="op22" data-spec-view="table" onclick="dmApp.toggleMeasureSpecView('op22', 'table')">Table</button>
                    </div>
                    <div id="dmSpecTiles-op22" class="dm-measure-tiles">
                        <div class="dm-spec-tile featured">
                            <strong>Measure name</strong>
                            <p class="dm-spec-value">OP-22</p>
                            <p>Patient Left Without Being Seen (LWBS) Rate</p>
                        </div>
                        <div class="dm-spec-tile">
                            <strong>Measure type</strong>
                            <p>Annual ED left-without-being-seen rate. Lower rate is better.</p>
                        </div>
                        <div class="dm-spec-tile wide">
                            <strong>Description</strong>
                            <p>Tracks the percentage of ED patients who leave before being evaluated by a provider, reflecting ED overcrowding and access-to-care challenges.</p>
                        </div>
                        <div class="dm-spec-tile">
                            <strong>Data submission</strong>
                            <p>Annual via HQR Secure Portal.</p>
                        </div>
                        <div class="dm-spec-tile">
                            <strong>Numerator</strong>
                            <p>Patients who leave the ED without being seen by a provider during the reporting period.</p>
                        </div>
                        <div class="dm-spec-tile">
                            <strong>Denominator</strong>
                            <p>Total ED patient visits during the reporting period.</p>
                        </div>
                        <div class="dm-spec-tile">
                            <strong>Benchmark</strong>
                            <p class="dm-spec-value">0.1%</p>
                            <span class="dm-spec-subtle">National benchmark</span>
                        </div>
                        <div class="dm-spec-tile">
                            <strong>Performance</strong>
                            <p class="dm-spec-value">1.2%</p>
                            <span class="dm-spec-subtle">National 2024 data</span>
                        </div>
                        <div class="dm-spec-tile wide">
                            <strong>Specifications / definitions</strong>
                            <p>EHR departure code documentation is required. LWBS means the patient leaves after triage but before provider evaluation. Distinguish from AMA after medical evaluation. Target is below 2% LWBS rate.</p>
                        </div>
                        <div class="dm-spec-tile wide">
                            <strong>Exclusions</strong>
                            <p>Patients triaged and redirected to another care setting by a provider; patients who depart with clinical contact and advice documented; patients redirected from ED to urgent care on hospital campus with appropriate clinical oversight.</p>
                        </div>
                    </div>
                    <div id="dmSpecTrend-op22" class="dm-trend-spec" style="display:none;">
                        <div class="dm-trend-hero">
                            <div>
                                <span class="dm-trend-eyebrow">Modern snapshot</span>
                                <h2>LWBS rate is an ED access pressure signal</h2>
                                <p>Use this view for a quicker client-facing read: lower is better, with attention on keeping the annual LWBS rate below the 2% target threshold.</p>
                            </div>
                            <div class="dm-trend-pill">Annual MBQIP submission</div>
                        </div>
                        <div class="dm-trend-kpis">
                            <div class="dm-trend-kpi">
                                <strong>Benchmark</strong>
                                <span>0.1%</span>
                                <em>National benchmark</em>
                            </div>
                            <div class="dm-trend-kpi">
                                <strong>National</strong>
                                <span>1.2%</span>
                                <em>Most recent available 2024 data</em>
                            </div>
                            <div class="dm-trend-kpi">
                                <strong>Target</strong>
                                <span>&lt;2%</span>
                                <em>Lower rate is better</em>
                            </div>
                        </div>
                        <div class="dm-trend-scale">
                            <strong>Performance range</strong>
                            <div class="dm-trend-track" aria-label="OP-22 benchmark, performance, and target range">
                                <div class="dm-trend-marker" style="left:5%;"><span>0.1% benchmark</span></div>
                                <div class="dm-trend-marker" style="left:60%;"><span>1.2% current</span></div>
                                <div class="dm-trend-marker" style="left:100%;"><span>2% target</span></div>
                            </div>
                        </div>
                        <div class="dm-trend-panel-grid">
                            <div class="dm-trend-panel">
                                <strong>What it captures</strong>
                                <p>Patients who leave the ED after triage but before provider evaluation. It reflects overcrowding, throughput strain, and access-to-care challenges.</p>
                            </div>
                            <div class="dm-trend-panel">
                                <strong>Important exclusions</strong>
                                <p>Exclude redirected patients, patients with clinical contact and advice documented before departure, and ED-to-urgent-care redirections with appropriate oversight.</p>
                            </div>
                        </div>
                    </div>
                    <table id="dmSpecTable-op22" class="dm-measure-spec" style="display:none;">
                            <thead>
                                <tr>
                                    <th>Measure name</th>
                                    <th>${measure}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th>Description</th>
                                    <td>Tracks the percentage of ED patients who leave before being evaluated by a provider, reflecting ED overcrowding and access-to-care challenges.</td>
                                </tr>
                                <tr>
                                    <th>Numerator</th>
                                    <td>Number of patients who leave the ED without being seen by a provider during the reporting period.</td>
                                </tr>
                                <tr>
                                    <th>Denominator</th>
                                    <td>Total number of ED patient visits during the reporting period.</td>
                                </tr>
                                <tr>
                                    <th>Measure type</th>
                                    <td>Annual ED left-without-being-seen rate. Lower rate is better.</td>
                                </tr>
                                <tr>
                                    <th>Data source</th>
                                    <td>CMS OP-22 OQR specifications. Annual data submission via HQR Secure Portal for MBQIP.</td>
                                </tr>
                                <tr>
                                    <th>Data submission frequency</th>
                                    <td>Annual — submitted via HQR Secure Portal.</td>
                                </tr>
                                <tr>
                                    <th>Specifications/definitions</th>
                                    <td>EHR departure code documentation is required. LWBS means the patient leaves after triage but before provider evaluation. Distinguish from AMA after medical evaluation. Target is below 2% LWBS rate.</td>
                                </tr>
                                <tr>
                                    <th>Exclusions</th>
                                    <td>Patients triaged and redirected to another care setting by a provider; patients who depart with clinical contact and advice documented; patients redirected from ED to urgent care on hospital campus with appropriate clinical oversight.</td>
                                </tr>
                                <tr>
                                    <th>Benchmark/performance</th>
                                    <td>National benchmark: 0.1%. National performance rate: 1.2%. Most recent available 2024 data from the workbook.</td>
                                </tr>
                            </tbody>
                        </table>
                    ${this.renderMeasureGoalsPanel()}
                </div>

                ${this.renderMeasureTabs()}

                <div class="dm-input-pane ${this.state.inputTab !== 'database' ? 'active' : ''}">
                    <div class="dm-entry-section-title" style="margin-top:0;">Manual Entry</div>
                    <div class="dm-row-actions top" style="align-items:center; margin-top:0;"></div>
                    <div class="dm-table-wrap dm-manual-table-wrap">
                        <table class="dm-table dm-manual-table dm-manual-table-annual" id="dmManualTable">
                            <colgroup><col class="dm-col-year"><col class="dm-col-num"><col class="dm-col-den"><col class="dm-col-rate"></colgroup>
                            <thead>
                                <tr>
                                    <th>Year</th>
                                    <th>Numerator</th>
                                    <th>Denominator</th>
                                    <th>Rate (%)</th>
                                </tr>
                            </thead>
                            <tbody id="dmManualTbody">
                                <tr>
                                    <td>
                                        <select class="dm-year-select" onchange="dmApp.updateManualRow(0, 'year', this.value)">
                                            ${this.yearOptions().map(y => `<option value="${y}" ${String(row.year || new Date().getFullYear()) === String(y) ? 'selected' : ''}>${y}</option>`).join('')}
                                        </select>
                                    </td>
                                    <td><input type="number" min="0" placeholder="0" value="${row.num || ''}" oninput="dmApp.updateManualRow(0, 'num', this.value)"></td>
                                    <td class="dm-den-cell"><input type="number" min="0" placeholder="0" value="${row.den || ''}" oninput="dmApp.updateManualRow(0, 'den', this.value)">${this.renderNumDenWarning(row)}</td>
                                    <td class="dm-rate-cell">
                                        ${this.formatRatePercent(row.num, row.den)}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="dm-save-section" style="display:flex; gap:12px; justify-content:flex-end; align-items:center; flex-wrap:wrap;">
                        <span id="dmSaveNote" style="font-size:13px; color:var(--dm-text-muted); display:${this.hasManualData() ? 'none' : 'inline-flex'}; align-items:center; gap:6px;">
                            <i class="fas fa-info-circle"></i> Enter the year, numerator, and denominator before saving.
                        </span>
                        <button type="button" id="dmSaveBtn" class="dm-btn" style="padding: 14px 40px; font-size: 15px; background: #03283E;" onclick="dmApp.saveAllData(event)" ${this.hasManualData() ? '' : 'disabled'}>
                            <i class="fas fa-cloud-upload-alt"></i> Save & Sync Data
                        </button>
                    </div>

                    <div class="dm-guide">
                        <i class="fas fa-info-circle"></i> <b>Assessment logic:</b> Rate is calculated as Numerator divided by Denominator. Lower rates are better.
                    </div>
                    ${this.renderUploadError()}

                    ${this.renderSavedAssessmentPanel(null, true, false)}
                </div>

                <div class="dm-input-pane ${this.state.inputTab === 'database' ? 'active' : ''}">
                    ${this.renderRawDataTable()}
                </div>
            `;
        },

        isGlobalInfrastructureMeasure(measure) {
            return String(measure || this.state.currentMeasure || '') === DM_GLOBAL_INFRASTRUCTURE_MEASURE;
        },

        isAntibioticStewardshipMeasure(measure) {
            return String(measure || this.state.currentMeasure || '') === DM_ANTIBIOTIC_STEWARDSHIP_MEASURE;
        },

        isHcpInfluenzaMeasure(measure) {
            return String(measure || this.state.currentMeasure || '') === 'HCP/IMM-3 — Healthcare Personnel Influenza Vaccination';
        },

        isHwrMeasure(measure) {
            return String(measure || this.state.currentMeasure || '') === DM_HWR_MEASURE;
        },

        isSafeUseOpioidsMeasure(measure) {
            return String(measure || this.state.currentMeasure || '') === DM_SAFE_USE_OPIOIDS_MEASURE;
        },

        isImprovementCalculatorMeasure(measure) {
            return String(measure || this.state.currentMeasure || '') === DM_IMPROVEMENT_CALCULATOR_MEASURE;
        },

        isHcahpsMeasure(measure) {
            return DM_HCAHPS_MEASURES.includes(String(measure || this.state.currentMeasure || ''));
        },

        isEdtcMeasure(measure) {
            return String(measure || this.state.currentMeasure || '') === DM_EDTC_MEASURE;
        },

        isOp18Measure(measure) {
            return String(measure || this.state.currentMeasure || '') === DM_OP18_MEASURE;
        },

        isOp22Measure(measure) {
            return String(measure || this.state.currentMeasure || '') === DM_OP22_MEASURE;
        },

        isQuarterRateMeasure(measure) {
            return this.isHcahpsMeasure(measure) || this.isEdtcMeasure(measure);
        },

        isMonthlyRateMeasure(measure) {
            return this.isSafeUseOpioidsMeasure(measure);
        },

        isQuarterMedianMeasure(measure) {
            return this.isOp18Measure(measure);
        },

        isManualAssessmentRecord(file) {
            if (!file) return false;
            if (!this.isGlobalInfrastructureMeasure() && !this.isHcpInfluenzaMeasure() && !this.isHwrMeasure() && !this.isOp22Measure() && !this.isAntibioticStewardshipMeasure() && !this.isEdtcMeasure() && !this.isSafeUseOpioidsMeasure() && !this.isQuarterRateMeasure() && !this.isQuarterMedianMeasure()) return false;
            if (file.source === 'manual') return true;
            if (file.source === 'upload') return false;
            const name = String(file.name || '').toLowerCase();
            return name.includes('cah-quality-infrastructure-assessment_')
                || name.includes('hcpimm-3-healthcare-personnel-influenza-vaccination_')
                || name.includes('hcp-imm-3-healthcare-personnel-influenza-vaccination_')
                || name.includes('hybrid-hospital-wide-readmission-hwr_')
                || name.includes('edtc-emergency-department-transfer-communication_')
                || name.includes('op-18-median-ed-arrival-to-departure-time')
                || name.includes('op-22-patient-left-without-being-seen-lwbs-rate_')
                || name.includes('antibiotic-stewardship_')
                || name.includes('safe-use-of-opioids-ecqm-mbqip-submission_')
                || name.includes('hcahps-');
        },

        savedAssessmentForYear(year) {
            const targetYear = String(year || '').trim();
            if (!targetYear) return null;
            const files = (this.state.filesByMeasure || {})[this.state.currentMeasure] || [];
            return files.find(file =>
                this.isManualAssessmentRecord(file) &&
                String(file.assessment_year || '').trim() === targetYear
            ) || null;
        },

        assessmentRecordForYear(year) {
            const targetYear = String(year || '').trim();
            if (!targetYear) return null;
            const files = (this.state.filesByMeasure || {})[this.state.currentMeasure] || [];
            return files.find(file =>
                (String(file.assessment_year || '').trim() === targetYear
                    || (Array.isArray(file.assessment_years) && file.assessment_years.map(String).includes(targetYear))) &&
                (file.template_type === 'elements_checklist' || file.template_type === 'annual_rate' || file.template_type === 'annual_numden_rate' || file.template_type === 'antibiotic_stewardship' || file.template_type === 'edtc_checklist' || file.template_type === 'period_rate' || file.template_type === 'quarter_median' || this.isManualAssessmentRecord(file))
            ) || null;
        },

        assessmentRecordForPeriod(month, year) {
            const targetMonth = String(month || '').trim();
            const targetYear = String(year || '').trim();
            if (!targetMonth || !targetYear) return null;
            const files = (this.state.filesByMeasure || {})[this.state.currentMeasure] || [];
            return files.find(file =>
                ((String(file.assessment_year || '').trim() === targetYear &&
                    String(file.assessment_month || '').trim() === targetMonth)
                    || (Array.isArray(file.assessment_periods) && file.assessment_periods.some(period =>
                        String(period && period.year || '').trim() === targetYear &&
                        String(period && period.month || '').trim() === targetMonth
                    ))) &&
                (file.template_type === 'edtc_checklist' || file.template_type === 'period_rate' || file.template_type === 'quarter_rate' || file.template_type === 'quarter_median' || this.isManualAssessmentRecord(file))
            ) || null;
        },

        checklistComponentsForCurrentMeasure(measure = null) {
            const targetMeasure = measure || this.state.currentMeasure;
            if (this.isAntibioticStewardshipMeasure(targetMeasure)) {
                return DM_ANTIBIOTIC_STEWARDSHIP_COMPONENTS;
            }
            if (this.isEdtcMeasure(targetMeasure)) {
                return DM_EDTC_COMPONENTS;
            }
            return DM_GLOBAL_INFRASTRUCTURE_COMPONENTS;
        },

        defaultChecklistRows(measure = null) {
            if (this.isEdtcMeasure(measure)) {
                return this.checklistComponentsForCurrentMeasure(measure).map(component => ({ component, selected: true, num: '', den: '', met: '' }));
            }
            return this.checklistComponentsForCurrentMeasure(measure).map(component => ({ component, selected: true, met: '' }));
        },

        edtcCompositeSummary(numValue = '', denValue = '') {
            const numText = String(numValue == null ? '' : numValue).trim();
            const denText = String(denValue == null ? '' : denValue).trim();
            const num = Number(numText);
            const den = Number(denText);
            const hasNum = numText !== '' && Number.isFinite(num);
            const hasDen = denText !== '' && Number.isFinite(den);
            const hasInversion = hasNum && hasDen && den < num;
            const rateValue = hasNum && hasDen && den > 0 ? (num / den) * 100 : 0;
            return {
                num: hasNum ? num : 0,
                den: hasDen ? den : 0,
                numText,
                denText,
                complete: hasNum && hasDen && den > 0 && !hasInversion,
                hasInversion,
                rate: rateValue.toFixed(1),
                credit: hasNum && hasDen && den > 0 && num >= den ? 'Yes' : 'No'
            };
        },

        edtcCompositeStateSummary() {
            return this.edtcCompositeSummary(this.state.edtcCompositeNum, this.state.edtcCompositeDen);
        },

        edtcSeriesComponentKey(component = '') {
            return `component:${String(component || '')
                .toLowerCase()
                .replace(/^[0-9]+[\).\s-]*/, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '')}`;
        },

        edtcSeriesOptions() {
            return [
                { key: DM_EDTC_COMPOSITE_KEY, label: 'Composite Score' },
                ...DM_EDTC_COMPONENTS.map(component => ({
                    key: this.edtcSeriesComponentKey(component),
                    label: component
                }))
            ];
        },

        edtcSeriesLabel(seriesKey = '') {
            const key = String(seriesKey || DM_EDTC_COMPOSITE_KEY);
            const match = this.edtcSeriesOptions().find(option => option.key === key);
            return match ? match.label : 'Composite Score';
        },

        edtcSeriesKeyForRow(row = {}) {
            const explicit = String(row.edtc_series_key || row.series_key || '').trim();
            if (explicit) {
                if (explicit === DM_EDTC_COMPOSITE_KEY) return DM_EDTC_COMPOSITE_KEY;
                if (explicit.startsWith('component:')) {
                    return this.edtcSeriesComponentKey(explicit.slice('component:'.length));
                }
                return explicit;
            }
            const label = String(row.edtc_series_label || row.series_label || row.metric || '').trim();
            if (!label || label === this.state.currentMeasure) {
                return DM_EDTC_COMPOSITE_KEY;
            }
            if (this.normalizeChecklistText(label) === this.normalizeChecklistText(DM_EDTC_COMPOSITE_LABEL)) {
                return DM_EDTC_COMPOSITE_KEY;
            }
            const component = DM_EDTC_COMPONENTS.find(item => this.normalizeChecklistText(item) === this.normalizeChecklistText(label));
            return component ? this.edtcSeriesComponentKey(component) : DM_EDTC_COMPOSITE_KEY;
        },

        normalizeChecklistText(value) {
            return String(value || '')
                .toLowerCase()
                .replace(/^[0-9]+[\).\s-]*/, '')
                .replace(/[^a-z0-9]+/g, ' ')
                .trim();
        },

        globalChecklistSummary() {
            return this.globalChecklistSummaryForRows(this.state.checklistRows || []);
        },

        globalChecklistSummaryForRows(rows) {
            rows = Array.isArray(rows) ? rows : [];
            const total = rows.length;
            const selected = total;
            const met = rows.filter(row => row.met === 'Yes').length;
            const answered = rows.filter(row => row.met === 'Yes' || row.met === 'No').length;
            return {
                total,
                selected,
                met,
                answered,
                complete: total > 0 && answered === total,
                rate: total > 0 ? ((met / total) * 100).toFixed(1) : '0.0',
                credit: total > 0 && met === total ? 'Yes, all criteria met' : 'No, not all criteria met'
            };
        },

        edtcNumDenSummary(rows = null) {
            rows = Array.isArray(rows) ? rows : (this.state.checklistRows || []);
            const total = rows.length;
            let num = 0;
            let den = 0;
            let completeRows = 0;
            let hasInversion = false;
            rows.forEach(row => {
                const numText = String(row && row.num != null ? row.num : '').trim();
                const denText = String(row && row.den != null ? row.den : '').trim();
                if (numText !== '' && denText !== '') {
                    completeRows += 1;
                }
                const rowNum = Number(numText);
                const rowDen = Number(denText);
                if (Number.isFinite(rowNum)) num += rowNum;
                if (Number.isFinite(rowDen)) den += rowDen;
                if (this.hasNumDenInversion(row)) hasInversion = true;
            });
            const rateValue = den > 0 ? (num / den) * 100 : 0;
            return {
                total,
                num,
                den,
                completeRows,
                complete: total > 0 && completeRows === total && den > 0 && !hasInversion,
                hasInversion,
                rate: rateValue.toFixed(1),
                credit: den > 0 && num >= den ? 'Yes' : 'No'
            };
        },

        updateChecklistYear(value) {
            this.setState({
                checklistYear: String(value || ''),
                checklistRows: this.defaultChecklistRows(),
                edtcCompositeNum: '',
                edtcCompositeDen: '',
                lastSavedRows: null
            }, { preserveScroll: true, scrollToTop: false });
        },

        updateChecklistQuarter(value) {
            this.setState({
                checklistQuarter: this.normalizeQuarter(value) || 'Q1',
                checklistRows: this.defaultChecklistRows(),
                edtcCompositeNum: '',
                edtcCompositeDen: '',
                lastSavedRows: null
            }, { preserveScroll: true, scrollToTop: false });
        },

        updateEdtcCompositeField(field, value) {
            if (!['edtcCompositeNum', 'edtcCompositeDen'].includes(field)) return;
            this.state[field] = value;
            this.state.lastSavedRows = null;
            this.syncEdtcControls();
        },

        updateEdtcReportSeries(value) {
            const next = this.edtcSeriesOptions().some(option => option.key === value) ? value : DM_EDTC_COMPOSITE_KEY;
            this.setState({ edtcReportSeries: next }, { preserveScroll: true, scrollToTop: false });
        },

        updateEdtcElementRow(index, field, value) {
            const rows = [...(this.state.checklistRows || [])];
            if (!rows[index]) return;
            rows[index] = { ...rows[index], [field]: value };
            this.state.checklistRows = rows;
            this.state.lastSavedRows = null;
            this.renderEdtcRate(index);
            this.syncEdtcControls();
        },

        updateChecklistRow(index, value) {
            const rows = [...(this.state.checklistRows || [])];
            if (!rows[index]) return;
            rows[index] = { ...rows[index], met: value === 'Yes' || value === 'No' ? value : '' };
            this.setState({ checklistRows: rows }, { scrollToTop: false });
        },

        updateChecklistSelected(index, checked) {
            const rows = [...(this.state.checklistRows || [])];
            if (!rows[index]) return;
            rows[index] = {
                ...rows[index],
                selected: !!checked,
                met: checked ? rows[index].met : ''
            };
            this.setState({ checklistRows: rows }, { scrollToTop: false });
        },

        buildGlobalChecklistSaveRows() {
            const quarter = this.isEdtcMeasure() ? this.state.checklistQuarter : '';
            return this.buildGlobalChecklistSaveRowsFrom(this.state.checklistYear, this.state.checklistRows, quarter);
        },

        buildGlobalChecklistSaveRowsFrom(yearValue, checklistRows, quarterValue = '', edtcOptions = {}) {
            const rows = Array.isArray(checklistRows) ? checklistRows : [];
            const summary = this.isEdtcMeasure() ? this.edtcNumDenSummary(rows) : this.globalChecklistSummaryForRows(rows);
            const edtcComposite = this.isEdtcMeasure()
                ? this.edtcCompositeSummary(
                    Object.prototype.hasOwnProperty.call(edtcOptions, 'compositeNum') ? edtcOptions.compositeNum : this.state.edtcCompositeNum,
                    Object.prototype.hasOwnProperty.call(edtcOptions, 'compositeDen') ? edtcOptions.compositeDen : this.state.edtcCompositeDen
                )
                : null;
            const year = String(yearValue || '').trim();
            const quarter = this.normalizeQuarter(quarterValue);
            const components = {};
            rows.forEach(row => {
                if (this.isEdtcMeasure()) {
                    const rowNum = String(row.num == null ? '' : row.num).trim();
                    const rowDen = String(row.den == null ? '' : row.den).trim();
                    const rate = rowDen !== '' && Number(rowDen) > 0 ? ((Number(rowNum || 0) / Number(rowDen)) * 100).toFixed(1) + '%' : '';
                    components[row.component] = {
                        selected: 'Yes',
                        num: rowNum,
                        den: rowDen,
                        rate,
                        met: rate
                    };
                } else {
                    components[row.component] = {
                        selected: 'Yes',
                        met: row.met || ''
                    };
                }
            });
            return [{
                year,
                month: quarter,
                date_reported: new Date().toLocaleDateString('en-US'),
                elements_met_count: this.isEdtcMeasure() ? String(edtcComposite.num) : summary.met,
                elements_selected_count: this.isEdtcMeasure() ? String(edtcComposite.den) : summary.selected,
                rate: this.isAntibioticStewardshipMeasure() ? summary.rate + '%' : (this.isEdtcMeasure() ? edtcComposite.rate + '%' : Number(summary.rate).toFixed(0) + '%'),
                credit: this.isAntibioticStewardshipMeasure()
                    ? (summary.total > 0 && summary.met === summary.total ? 'All 7 elements met' : 'Calculated on save')
                    : (this.isEdtcMeasure() ? edtcComposite.credit : (summary.total > 0 && summary.met === summary.total ? 'Yes' : 'No')),
                edtc_composite_num: this.isEdtcMeasure() ? String(edtcComposite.num) : '',
                edtc_composite_den: this.isEdtcMeasure() ? String(edtcComposite.den) : '',
                edtc_composite_rate: this.isEdtcMeasure() ? edtcComposite.rate + '%' : '',
                components
            }];
        },

        parseGlobalChecklistRows(grid) {
            const rows = this.defaultChecklistRows();
            let detectedYear = '';
            let detectedQuarter = '';
            const componentMap = {};
            rows.forEach((row, idx) => {
                componentMap[this.normalizeChecklistText(row.component)] = idx;
            });
            const assessmentGroups = {};
            const assessmentOrder = [];
            const defaultRowsForGroup = () => this.defaultChecklistRows();

            const allRows = Array.isArray(grid) ? grid : [];
            const hospitalDataIndex = allRows.findIndex(rawRow =>
                Array.isArray(rawRow) && rawRow.some(cell => this.normalizeChecklistText(cell) === 'hospital data')
            );
            const rowsToRead = hospitalDataIndex >= 0 ? allRows.slice(hospitalDataIndex + 1) : allRows;

            rowsToRead.forEach(rawRow => {
                const cells = Array.isArray(rawRow) ? rawRow.map(cell => String(cell == null ? '' : cell).trim()) : [];
                if (!cells.length) return;

                const yearCell = cells.find(cell => /^[12][0-9]{3}$/.test(cell));
                if (yearCell) detectedYear = yearCell;
                const quarterCell = cells.map(cell => this.normalizeQuarter(cell)).find(Boolean);
                if (quarterCell) detectedQuarter = quarterCell;

                const normalizedCells = cells.map(cell => this.normalizeChecklistText(cell));
                const quarterLabelIndex = normalizedCells.findIndex(cell => cell === 'quarter');
                if (quarterLabelIndex >= 0 && cells[quarterLabelIndex + 1]) {
                    const adjacentQuarter = this.normalizeQuarter(cells[quarterLabelIndex + 1]);
                    if (adjacentQuarter) detectedQuarter = adjacentQuarter;
                }
                Object.keys(componentMap).forEach(componentKey => {
                    const componentIndex = normalizedCells.findIndex(cell => {
                        if (!cell) return false;
                        if (cell === componentKey) return true;
                        return cell.length > 3 && componentKey.length > 3 && (cell.includes(componentKey) || componentKey.includes(cell));
                    });
                    if (componentIndex < 0) return;

                    const selectedRaw = cells[componentIndex - 1] || '';
                    const meetingRaw = cells[componentIndex + 1] || '';
                    const selectedText = this.normalizeChecklistText(selectedRaw);

                    if (/^(no|unchecked|not selected|not included|excluded|false|0)$/i.test(selectedText)) {
                        rows[componentMap[componentKey]].selected = false;
                        rows[componentMap[componentKey]].met = '';
                    } else {
                        rows[componentMap[componentKey]].selected = true;
                        if (/^(yes|no)$/i.test(meetingRaw)) {
                            rows[componentMap[componentKey]].met = /^yes$/i.test(meetingRaw) ? 'Yes' : 'No';
                        }
                    }
                    const groupYear = yearCell && /^[12][0-9]{3}$/.test(yearCell)
                        ? yearCell
                        : ((this.isAntibioticStewardshipMeasure() || this.isGlobalInfrastructureMeasure()) && detectedYear ? detectedYear : '');
                    if ((this.isAntibioticStewardshipMeasure() || this.isGlobalInfrastructureMeasure()) && groupYear && /^(yes|no)$/i.test(meetingRaw)) {
                        if (!assessmentGroups[groupYear]) {
                            assessmentGroups[groupYear] = {
                                year: groupYear,
                                rows: defaultRowsForGroup()
                            };
                            assessmentOrder.push(groupYear);
                        }
                        assessmentGroups[groupYear].rows[componentMap[componentKey]] = {
                            ...assessmentGroups[groupYear].rows[componentMap[componentKey]],
                            selected: true,
                            met: /^yes$/i.test(meetingRaw) ? 'Yes' : 'No'
                        };
                    }
                });
            });

            const assessments = assessmentOrder
                .map(key => assessmentGroups[key])
                .filter(item => {
                    const summary = this.globalChecklistSummaryForRows(item.rows);
                    return !!item.year && summary.complete;
                });
            const incompleteAssessments = assessmentOrder
                .map(key => assessmentGroups[key])
                .filter(item => {
                    const summary = this.globalChecklistSummaryForRows(item.rows);
                    return !!item.year && summary.answered > 0 && !summary.complete;
                });

            return { rows, year: detectedYear, quarter: detectedQuarter, assessments, incompleteAssessments };
        },

        parseEdtcNumDenRows(grid) {
            const rows = this.defaultChecklistRows(DM_EDTC_MEASURE);
            let detectedYear = '';
            let detectedQuarter = '';
            const componentMap = {};
            rows.forEach((row, idx) => {
                componentMap[this.normalizeChecklistText(row.component)] = idx;
            });

            const allRows = Array.isArray(grid) ? grid : [];
            const normalized = (value) => this.normalizeChecklistText(value);
            const headerIndex = allRows.findIndex(rawRow => {
                const cells = Array.isArray(rawRow) ? rawRow.map(normalized) : [];
                return cells.includes('year')
                    && cells.includes('quarter')
                    && cells.some(cell => cell.includes('edtc') || cell.includes('transfer communication') || cell.includes('reporting item'))
                    && (cells.includes('num') || cells.includes('numerator'));
            });
            const headerCells = headerIndex >= 0 && Array.isArray(allRows[headerIndex])
                ? allRows[headerIndex].map(normalized)
                : [];
            const columnIndex = {
                year: headerCells.findIndex(cell => cell === 'year'),
                quarter: headerCells.findIndex(cell => cell === 'quarter'),
                item: headerCells.findIndex(cell => cell.includes('edtc') || cell.includes('transfer communication') || cell.includes('reporting item')),
                num: headerCells.findIndex(cell => cell === 'num' || cell === 'numerator'),
                den: headerCells.findIndex(cell => cell === 'denom' || cell === 'denominator')
            };
            const rowsToRead = headerIndex >= 0 ? allRows.slice(headerIndex + 1) : allRows;
            const assessmentGroups = {};
            const assessmentOrder = [];
            const defaultRowsForGroup = () => this.defaultChecklistRows(DM_EDTC_MEASURE);
            const cleanCellValue = (value) => {
                if (value && typeof value === 'object') {
                    if (value.v !== undefined && value.v !== null) return String(value.v).trim();
                    if (value.w !== undefined && value.w !== null) return String(value.w).trim();
                    if (value.result !== undefined && value.result !== null) return String(value.result).trim();
                }
                return String(value == null ? '' : value).trim();
            };
            const cleanNumberCell = (value) => cleanCellValue(value).replace(/[%,$\s]/g, '').trim();
            const componentOrCompositeIndex = (cells, normalizedCells, targetKey) => {
                if (columnIndex.item >= 0) return columnIndex.item;
                return normalizedCells.findIndex(cell => cell && (cell.includes(targetKey) || targetKey.includes(cell)));
            };
            const identifyEdtcItem = (value) => {
                const normalizedValue = this.normalizeChecklistText(value);
                if (!normalizedValue) return null;
                if (normalizedValue.includes('composite') || normalizedValue.includes('all elements documented')) {
                    return { type: 'composite', key: DM_EDTC_COMPOSITE_KEY };
                }
                const component = Object.keys(componentMap).find(key => key && (normalizedValue.includes(key) || key.includes(normalizedValue)));
                return component ? { type: 'component', key: component } : null;
            };

            rowsToRead.forEach(rawRow => {
                const cells = Array.isArray(rawRow) ? rawRow.map(cleanCellValue) : [];
                if (!cells.some(Boolean)) return;
                const yearCell = columnIndex.year >= 0 ? cells[columnIndex.year] : cells.find(cell => /^[12][0-9]{3}$/.test(cell));
                if (yearCell && /^[12][0-9]{3}$/.test(yearCell)) detectedYear = yearCell;
                const quarterCell = columnIndex.quarter >= 0 ? this.normalizeQuarter(cells[columnIndex.quarter]) : cells.map(cell => this.normalizeQuarter(cell)).find(Boolean);
                if (quarterCell) detectedQuarter = quarterCell;

                const groupYear = yearCell && /^[12][0-9]{3}$/.test(yearCell) ? yearCell : detectedYear;
                const groupQuarter = quarterCell || detectedQuarter;
                const normalizedCells = cells.map(normalized);
                const itemLabel = columnIndex.item >= 0 ? cells[columnIndex.item] : cells.join(' ');
                const itemMeta = identifyEdtcItem(itemLabel);
                if (!itemMeta) return;

                const targetItemIndex = componentOrCompositeIndex(cells, normalizedCells, itemMeta.key);
                const numbersAfterItem = targetItemIndex >= 0
                    ? cells.slice(targetItemIndex + 1).map(cleanNumberCell).filter(value => /^-?\d+(\.\d+)?$/.test(value))
                    : [];
                const num = (columnIndex.num >= 0 && cleanNumberCell(cells[columnIndex.num]) !== '')
                    ? cleanNumberCell(cells[columnIndex.num])
                    : (numbersAfterItem[0] || '');
                const den = (columnIndex.den >= 0 && cleanNumberCell(cells[columnIndex.den]) !== '')
                    ? cleanNumberCell(cells[columnIndex.den])
                    : (numbersAfterItem[1] || '');
                if (num === '' && den === '') return;

                if (itemMeta.type === 'component' && componentMap[itemMeta.key] !== undefined) {
                    rows[componentMap[itemMeta.key]] = {
                        ...rows[componentMap[itemMeta.key]],
                        num,
                        den
                    };
                }

                if (groupYear && groupQuarter) {
                    const groupKey = `${groupYear}|${groupQuarter}`;
                    if (!assessmentGroups[groupKey]) {
                        assessmentGroups[groupKey] = {
                            year: groupYear,
                            quarter: groupQuarter,
                            rows: defaultRowsForGroup(),
                            compositeNum: '',
                            compositeDen: ''
                        };
                        assessmentOrder.push(groupKey);
                    }
                    if (itemMeta.type === 'composite') {
                        assessmentGroups[groupKey].compositeNum = num;
                        assessmentGroups[groupKey].compositeDen = den;
                    } else if (componentMap[itemMeta.key] !== undefined) {
                        assessmentGroups[groupKey].rows[componentMap[itemMeta.key]] = {
                            ...assessmentGroups[groupKey].rows[componentMap[itemMeta.key]],
                            num,
                            den
                        };
                    }
                }
            });

            const assessments = [];
            const incompleteAssessments = [];
            const invalidAssessments = [];

            assessmentOrder.forEach(key => {
                const item = assessmentGroups[key];
                const elementSummary = this.edtcNumDenSummary(item.rows);
                const compositeSummary = this.edtcCompositeSummary(item.compositeNum, item.compositeDen);
                const hasAnyComponentData = item.rows.some(row => String(row.num || '').trim() !== '' || String(row.den || '').trim() !== '');
                const hasAnyCompositeData = compositeSummary.numText !== '' || compositeSummary.denText !== '';

                if (!item.year || !item.quarter || (!hasAnyComponentData && !hasAnyCompositeData)) {
                    return;
                }

                const assessment = {
                    ...item,
                    compositeNum: item.compositeNum,
                    compositeDen: item.compositeDen
                };

                if (elementSummary.hasInversion || compositeSummary.hasInversion) {
                    invalidAssessments.push(assessment);
                    return;
                }

                if (elementSummary.complete && compositeSummary.complete) {
                    assessments.push(assessment);
                    return;
                }

                incompleteAssessments.push(assessment);
            });

            const firstAssessmentLike = assessments[0] || incompleteAssessments[0] || invalidAssessments[0] || null;
            return {
                rows,
                year: detectedYear || String(new Date().getFullYear()),
                quarter: detectedQuarter || 'Q1',
                compositeNum: firstAssessmentLike ? firstAssessmentLike.compositeNum : '',
                compositeDen: firstAssessmentLike ? firstAssessmentLike.compositeDen : '',
                assessments,
                incompleteAssessments,
                invalidAssessments
            };
        },

        uploadTemplateSpec() {
            if (this.isGlobalInfrastructureMeasure()) {
                return {
                    label: 'CAH Quality Infrastructure Assessment',
                    measureNames: [DM_GLOBAL_INFRASTRUCTURE_MEASURE],
                    columns: ['Year', 'CAH Global Measure Component', 'Criteria Met'],
                    type: 'checklist',
                    componentHints: DM_GLOBAL_INFRASTRUCTURE_COMPONENTS.slice(0, 3)
                };
            }
            if (this.isAntibioticStewardshipMeasure()) {
                return {
                    label: 'Antibiotic Stewardship',
                    measureNames: [DM_ANTIBIOTIC_STEWARDSHIP_MEASURE],
                    columns: ['Year', 'CDC 7 Core Elements', 'Criteria Met'],
                    type: 'checklist',
                    componentHints: DM_ANTIBIOTIC_STEWARDSHIP_COMPONENTS.slice(0, 3)
                };
            }
            if (this.isEdtcMeasure()) {
                return {
                    label: 'EDTC Emergency Department Transfer Communication',
                    measureNames: [DM_EDTC_MEASURE],
                    columns: ['Year', 'Quarter', 'EDTC Reporting Item', 'Num', 'Denom', 'Rate'],
                    type: 'edtc_numden',
                    componentHints: DM_EDTC_COMPONENTS.slice(0, 3)
                };
            }
            if (this.isHcpInfluenzaMeasure()) {
                return {
                    label: 'HCP/IMM-3 Healthcare Personnel Influenza Vaccination',
                    measureNames: ['HCP/IMM-3 — Healthcare Personnel Influenza Vaccination'],
                    columns: ['Year', 'Numerator', 'Denominator'],
                    type: 'annual_rate'
                };
            }
            if (this.isHwrMeasure()) {
                return {
                    label: 'Hybrid Hospital-Wide Readmission',
                    measureNames: [DM_HWR_MEASURE],
                    columns: ['Year', 'Month', 'Num', 'Denom'],
                    type: 'period_rate'
                };
            }
            if (this.isOp22Measure()) {
                return {
                    label: 'OP-22 Patient Left Without Being Seen',
                    measureNames: [DM_OP22_MEASURE],
                    columns: ['Year', 'Num', 'Denom'],
                    type: 'annual_numden'
                };
            }
            if (this.isSafeUseOpioidsMeasure()) {
                return {
                    label: 'Safe Use of Opioids eCQM — MBQIP Submission',
                    measureNames: [DM_SAFE_USE_OPIOIDS_MEASURE],
                    columns: ['Year', 'Month', 'Num', 'Denom'],
                    type: 'period_rate'
                };
            }
            if (this.isQuarterMedianMeasure()) {
                return {
                    label: 'OP-18 Median ED Arrival to Departure Time',
                    measureNames: [DM_OP18_MEASURE],
                    columns: ['Year', 'Quarter', 'Median Minutes'],
                    type: 'quarter_median'
                };
            }
            return null;
        },

        normalizedGridCells(grid) {
            return (Array.isArray(grid) ? grid : [])
                .flatMap(row => Array.isArray(row) ? row : [])
                .map(cell => this.normalizeChecklistText(cell))
                .filter(Boolean);
        },

        templateMeasureName(grid) {
            const rows = Array.isArray(grid) ? grid : [];
            for (const rawRow of rows) {
                const row = Array.isArray(rawRow) ? rawRow : [];
                for (let i = 0; i < row.length; i++) {
                    if (this.normalizeChecklistText(row[i]) !== 'measure name') continue;
                    const found = row.slice(i + 1).map(cell => String(cell == null ? '' : cell).trim()).find(Boolean);
                    if (found) return found;
                }
            }
            return '';
        },

        isExpectedMeasureName(foundName, spec) {
            if (!foundName || !spec || !Array.isArray(spec.measureNames)) return true;
            const found = this.normalizeChecklistText(foundName);
            return spec.measureNames.some(name => {
                const expected = this.normalizeChecklistText(name);
                return found === expected || found.includes(expected) || expected.includes(found);
            });
        },

        gridHasAny(cells, patterns) {
            return patterns.some(pattern => cells.some(cell => pattern.test(cell)));
        },

        uploadGridMatchesSpec(grid, spec) {
            const cells = this.normalizedGridCells(grid);
            if (!cells.length || !spec) return false;
            if (spec.type === 'checklist') {
                const hints = (Array.isArray(spec.componentHints) ? spec.componentHints : []).map(component => this.normalizeChecklistText(component));
                const hasComponent = hints.some(hint => cells.some(cell => cell === hint || cell.includes(hint) || hint.includes(cell)));
                return hasComponent && (cells.includes('criteria met') || cells.includes('meeting component') || cells.includes('cah global measure component') || cells.includes('cdc 7 core elements'));
            }
            if (spec.type === 'edtc_numden') {
                const hints = (Array.isArray(spec.componentHints) ? spec.componentHints : []).map(component => this.normalizeChecklistText(component));
                const hasComponent = hints.some(hint => cells.some(cell => cell === hint || cell.includes(hint) || hint.includes(cell)));
                const hasYear = cells.includes('year');
                const hasQuarter = cells.includes('quarter');
                const hasNum = cells.includes('num') || cells.includes('numerator');
                const hasDen = cells.includes('denom') || cells.includes('denominator');
                return hasComponent && hasYear && hasQuarter && hasNum && hasDen;
            }
            const hasYear = cells.includes('year') || cells.includes('assessment year');
            const hasNum = cells.includes('num') || cells.includes('numerator') || cells.some(cell => cell.includes('vaccinated hcp'));
            const hasDen = cells.includes('denom') || cells.includes('denominator') || cells.some(cell => cell.includes('total eligible hcp'));
            if (spec.type === 'annual_rate') {
                return hasYear && hasNum && hasDen;
            }
            if (spec.type === 'annual_numden') {
                return hasYear && hasNum && hasDen;
            }
            if (spec.type === 'period_rate') {
                return hasYear && cells.includes('month') && hasNum && hasDen;
            }
            if (spec.type === 'quarter_rate') {
                return hasYear && cells.includes('quarter') && hasNum && hasDen;
            }
            if (spec.type === 'quarter_median') {
                return hasYear && cells.includes('quarter') && cells.some(cell => cell.includes('median') || cell.includes('minutes'));
            }
            return false;
        },

        validateUploadGrid(grid, originalFileName = '') {
            const spec = this.uploadTemplateSpec();
            if (!spec) return true;
            const foundMeasureName = this.templateMeasureName(grid);
            if (foundMeasureName && !this.isExpectedMeasureName(foundMeasureName, spec)) {
                this.showUploadError(`This looks like the "${foundMeasureName}" template. Please upload the ${spec.label} template. Expected columns: ${spec.columns.join(', ')}.`);
                return false;
            }
            if (!this.uploadGridMatchesSpec(grid, spec)) {
                this.showUploadError(`This file does not match the selected measure. Please upload the ${spec.label} template. Expected columns: ${spec.columns.join(', ')}.`);
                return false;
            }
            this.hideUploadError();
            return true;
        },

        parseAnnualRateRows(grid) {
            const allRows = Array.isArray(grid) ? grid : [];
            const normalized = (value) => this.normalizeChecklistText(value);
            const cleanCell = (value) => {
                if (value == null) return '';
                if (typeof value === 'object' && value.v != null) return String(value.v).trim();
                return String(value).trim();
            };
            const cleanNumber = (value) => cleanCell(value).replace(/[%,$\s]/g, '');
            const isValidYear = (value) => /^[12][0-9]{3}$/.test(cleanCell(value));
            const isNumeric = (value) => /^-?\d+(\.\d+)?$/.test(cleanNumber(value));
            const dataHeaderIndex = allRows.findIndex(rawRow => {
                const cells = Array.isArray(rawRow) ? rawRow.map(normalized) : [];
                return cells.some(cell => cell === 'year' || cell === 'assessment year')
                    && cells.some(cell => cell === 'num' || cell === 'numerator' || cell.includes('vaccinated hcp'))
                    && cells.some(cell => cell === 'denom' || cell === 'denominator' || cell.includes('total eligible hcp'));
            });
            const hospitalDataIndex = allRows.findIndex(rawRow => {
                const cells = Array.isArray(rawRow) ? rawRow.map(normalized) : [];
                return cells.some(cell => cell === 'hospital data');
            });
            const headerIndex = dataHeaderIndex >= 0 ? dataHeaderIndex : hospitalDataIndex;
            const headerCells = headerIndex >= 0 && Array.isArray(allRows[headerIndex])
                ? allRows[headerIndex].map(normalized)
                : [];
            const columnIndex = {
                year: headerCells.findIndex(cell => cell === 'year' || cell === 'assessment year'),
                num: headerCells.findIndex(cell => cell === 'num' || cell === 'numerator' || cell.includes('vaccinated hcp')),
                den: headerCells.findIndex(cell => cell === 'denom' || cell === 'denominator' || cell.includes('total eligible hcp'))
            };
            const rowsToRead = headerIndex >= 0 ? allRows.slice(headerIndex + 1) : allRows;
            const rows = [];

            rowsToRead.forEach(rawRow => {
                if (!Array.isArray(rawRow)) return;
                if (!rawRow.some(cell => cleanCell(cell) !== '')) return;
                const normalizedCells = rawRow.map(normalized);
                if (normalizedCells.includes('year') && (normalizedCells.includes('num') || normalizedCells.includes('numerator') || normalizedCells.some(cell => cell.includes('vaccinated')))) return;

                const cells = rawRow.map(cleanCell);
                let year = columnIndex.year >= 0 ? cells[columnIndex.year] || '' : '';
                let num = columnIndex.num >= 0 ? cleanNumber(cells[columnIndex.num] || '') : '';
                let den = columnIndex.den >= 0 ? cleanNumber(cells[columnIndex.den] || '') : '';
                const extractFromRow = () => {
                    const detectedYear = year || cells.find(isValidYear) || '';
                    const yearIndex = cells.findIndex(cell => cell === detectedYear);
                    const numbers = cells
                        .map((cell, index) => ({ index, value: cleanNumber(cell) }))
                        .filter(item => item.index !== yearIndex && isNumeric(item.value))
                        .map(item => item.value);
                    return { detectedYear, numbers };
                };
                if (!year || num === '' || den === '') {
                    const fallback = extractFromRow();
                    year = year || fallback.detectedYear;
                    num = num || fallback.numbers[0] || '';
                    den = den || fallback.numbers[1] || '';
                }
                if (this.isHcpInfluenzaMeasure() || this.isOp22Measure()) {
                    if (!String(year || '').trim() || num === '' || den === '') return;
                } else {
                    const hasStartedDataRow = num !== '' || den !== '';
                    if (!hasStartedDataRow) return;
                }
                rows.push({ month: '', year, num, den });
            });

            return rows;
        },

        parsePeriodRateRows(grid) {
            const allRows = Array.isArray(grid) ? grid : [];
            const normalized = (value) => this.normalizeChecklistText(value);
            const isQuarterly = this.isQuarterRateMeasure();
            let detectedYear = '';
            allRows.forEach(rawRow => {
                if (detectedYear || !Array.isArray(rawRow)) return;
                const cells = rawRow.map(cell => String(cell == null ? '' : cell).trim());
                const normalizedCells = cells.map(normalized);
                const assessmentYearIndex = normalizedCells.findIndex(cell => cell === 'assessment year');
                if (assessmentYearIndex >= 0) {
                    detectedYear = cells.slice(assessmentYearIndex + 1).find(cell => /^[12][0-9]{3}$/.test(cell)) || '';
                    return;
                }
                detectedYear = cells.find(cell => /^[12][0-9]{3}$/.test(cell)) || '';
            });
            const headerIndex = allRows.findIndex(rawRow => {
                const cells = Array.isArray(rawRow) ? rawRow.map(normalized) : [];
                return (cells.some(cell => cell === 'year') && cells.some(cell => cell === 'month' || cell === 'quarter') && cells.some(cell => cell === 'num'))
                    || (cells.some(cell => cell === 'month' || cell === 'quarter') && cells.some(cell => cell === 'num') && cells.some(cell => cell === 'denom' || cell === 'denominator'));
            });
            const headerCells = headerIndex >= 0 && Array.isArray(allRows[headerIndex])
                ? allRows[headerIndex].map(normalized)
                : [];
            const columnIndex = {
                year: headerCells.findIndex(cell => cell === 'year'),
                month: headerCells.findIndex(cell => cell === 'month' || cell === 'quarter'),
                num: headerCells.findIndex(cell => cell === 'num' || cell === 'numerator'),
                den: headerCells.findIndex(cell => cell === 'denom' || cell === 'denominator')
            };
            const rowsToRead = headerIndex >= 0 ? allRows.slice(headerIndex + 1) : allRows.slice(1);
            const rows = [];

            rowsToRead.forEach(rawRow => {
                const cells = Array.isArray(rawRow) ? rawRow.map(cell => String(cell == null ? '' : cell).trim()) : [];
                if (!cells.some(Boolean)) return;
                const normalizedCells = cells.map(normalized);
                if (normalizedCells.includes('year') && (normalizedCells.includes('month') || normalizedCells.includes('quarter')) && normalizedCells.includes('num')) return;
                if ((normalizedCells.includes('month') || normalizedCells.includes('quarter')) && normalizedCells.includes('num')) return;
                let year = columnIndex.year >= 0 ? cells[columnIndex.year] || '' : detectedYear;
                let month = columnIndex.month >= 0
                    ? (isQuarterly ? this.normalizeQuarter(cells[columnIndex.month]) : this.normalizeMonth(cells[columnIndex.month]))
                    : '';
                let num = columnIndex.num >= 0 ? String(cells[columnIndex.num] || '').replace(/[%,$\s]/g, '') : '';
                let den = columnIndex.den >= 0 ? String(cells[columnIndex.den] || '').replace(/[%,$\s]/g, '') : '';

                if (!year && !month && columnIndex.year < 0) {
                    year = cells.find(cell => /^[12][0-9]{3}$/.test(cell)) || '';
                    month = cells.map(cell => isQuarterly ? this.normalizeQuarter(cell) : this.normalizeMonth(cell)).find(Boolean) || '';
                    const yearIndex = cells.findIndex(cell => cell === year);
                    const numbers = cells
                        .map((cell, index) => ({ index, value: String(cell).replace(/[%,$\s]/g, '') }))
                        .filter(item => item.index !== yearIndex && /^-?\d+(\.\d+)?$/.test(item.value))
                        .map(item => item.value);
                    num = numbers[0] || '';
                    den = numbers[1] || '';
                }
                if (num === '' && den === '') return;
                rows.push({
                    month,
                    year,
                    num,
                    den
                });
            });

            return rows;
        },

        parseQuarterMedianRows(grid) {
            const allRows = Array.isArray(grid) ? grid : [];
            const normalized = (value) => this.normalizeChecklistText(value);
            const headerIndex = allRows.findIndex(rawRow => {
                const cells = Array.isArray(rawRow) ? rawRow.map(normalized) : [];
                return cells.some(cell => cell === 'hospital data')
                    || (cells.some(cell => cell === 'year') && cells.some(cell => cell === 'quarter') && cells.some(cell => cell.includes('median')));
            });
            const headerCells = headerIndex >= 0 && Array.isArray(allRows[headerIndex])
                ? allRows[headerIndex].map(normalized)
                : [];
            const columnIndex = {
                year: headerCells.findIndex(cell => cell === 'year'),
                month: headerCells.findIndex(cell => cell === 'quarter'),
                median: headerCells.findIndex(cell => cell.includes('median') || cell.includes('minutes'))
            };
            const rowsToRead = headerIndex >= 0 ? allRows.slice(headerIndex + 1) : allRows.slice(1);
            const rows = [];

            rowsToRead.forEach(rawRow => {
                const cells = Array.isArray(rawRow) ? rawRow.map(cell => String(cell == null ? '' : cell).trim()) : [];
                if (!cells.some(Boolean)) return;
                const normalizedCells = cells.map(normalized);
                if (normalizedCells.includes('year') && normalizedCells.includes('quarter')) return;

                let year = columnIndex.year >= 0 ? cells[columnIndex.year] || '' : '';
                let month = columnIndex.month >= 0 ? this.normalizeQuarter(cells[columnIndex.month]) : '';
                let median = columnIndex.median >= 0 ? String(cells[columnIndex.median] || '').replace(/[%,$\s]/g, '') : '';

                if (!year && !month && columnIndex.year < 0) {
                    year = cells.find(cell => /^[12][0-9]{3}$/.test(cell)) || '';
                    month = cells.map(cell => this.normalizeQuarter(cell)).find(Boolean) || '';
                    const yearIndex = cells.findIndex(cell => cell === year);
                    const quarterIndex = cells.findIndex(cell => this.normalizeQuarter(cell) === month);
                    const numbers = cells
                        .map((cell, index) => ({ index, value: String(cell).replace(/[%,$\s]/g, '') }))
                        .filter(item => item.index !== yearIndex && item.index !== quarterIndex && /^-?\d+(\.\d+)?$/.test(item.value))
                        .map(item => item.value);
                    median = numbers[0] || '';
                }
                if (median === '') return;
                rows.push({
                    month,
                    year,
                    median
                });
            });

            return rows;
        },

        navToRoot(options = {}) {
            if (this.guardUnsavedMeasureCoverage(() => this.navToRoot({ ...options, skipCoverageGuard: true }), options)) return;
            this.setState({
                view: 'unified-measures',
                currentCategory: null,
                currentSubfolder: null,
                currentMeasure: null,
                unifiedMeasuresMode: false,
                unifiedHacsMeasureId: '',
                selectedUnifiedMeasureValue: '',
                unifiedMeasurePickerOpen: false,
                unifiedMeasureSearch: ''
            }, { scrollToTop: options.scrollToTop !== false });
            if (options.updateUrl !== false) {
                this.updateRouteUrl();
            }
        },

        navToUnifiedMeasuresPage(options = {}) {
            if (this.guardUnsavedMeasureCoverage(() => this.navToUnifiedMeasuresPage({ ...options, skipCoverageGuard: true }), options)) return;
            this.setState({
                view: 'unified-measures',
                currentCategory: null,
                currentSubfolder: null,
                currentMeasure: null,
                unifiedMeasuresMode: false,
                unifiedHacsMeasureId: '',
                selectedUnifiedMeasureValue: '',
                unifiedMeasurePickerOpen: false,
                unifiedMeasureSearch: ''
            }, { scrollToTop: options.scrollToTop !== false });
            if (options.updateUrl !== false) {
                this.updateRouteUrl();
            }
        },

        navToMeasureCoverage(options = {}) {
            if (
                this.state.view === 'measure-coverage'
                && this.state.dataOwnershipTab === 'measures'
                && this.guardUnsavedMeasureCoverage(() => this.navToMeasureCoverage({ ...options, skipCoverageGuard: true }), options)
            ) return;
            this.setState({
                view: 'measure-coverage',
                currentCategory: null,
                currentSubfolder: null,
                currentMeasure: null,
                unifiedMeasuresMode: false,
                unifiedHacsMeasureId: '',
                selectedUnifiedMeasureValue: '',
                unifiedMeasurePickerOpen: false,
                unifiedMeasureSearch: '',
                measureCoverageDraft: this.activeMeasureCoverage(),
                measureCoverageStatus: ''
            }, { scrollToTop: options.scrollToTop !== false });
            if (options.updateUrl !== false) {
                this.updateRouteUrl();
            }
        },

        navToUnifiedMeasure(value, options = {}) {
            if (this.guardUnsavedMeasureCoverage(() => this.navToUnifiedMeasure(value, { ...options, skipCoverageGuard: true }), options)) return;
            const selectedValue = String(value || '').trim();
            if (!selectedValue) return;
            const option = this.unifiedMeasureOptions().find(item => item.value === selectedValue);
            if (!option) return;
            if (option.program === 'hacs-hais') {
                this.navToMeasure('improvement-calculator', null, DM_IMPROVEMENT_CALCULATOR_MEASURE, {
                    ...options,
                    unifiedMode: true,
                    hacsMeasureId: option.id,
                    selectedUnifiedMeasureValue: selectedValue,
                    unifiedMeasurePickerOpen: false,
                    unifiedMeasureSearch: ''
                });
                return;
            }
            this.navToMeasure(option.catId, option.subId, option.measure, {
                ...options,
                unifiedMode: true,
                selectedUnifiedMeasureValue: selectedValue,
                unifiedMeasurePickerOpen: false,
                unifiedMeasureSearch: ''
            });
        },

        navToCategory(id, options = {}) {
            if (this.guardUnsavedMeasureCoverage(() => this.navToCategory(id, { ...options, skipCoverageGuard: true }), options)) return;
            const cat = DM_DATA.find(c => c.id === id);
            if (cat && cat.id === 'general') {
                this.navToMeasure(cat.id, null, 'Bulk Upload', { ...options, unifiedMode: false, hacsMeasureId: '' });
                return;
            }
            if (cat && cat.id === 'improvement-calculator') {
                this.navToMeasure(cat.id, null, DM_IMPROVEMENT_CALCULATOR_MEASURE, { ...options, unifiedMode: false, hacsMeasureId: '' });
                return;
            }
            this.setState({ view: 'measures', currentCategory: cat, currentSubfolder: null, currentMeasure: null, unifiedMeasuresMode: false, unifiedHacsMeasureId: '', selectedUnifiedMeasureValue: '', unifiedMeasurePickerOpen: false, unifiedMeasureSearch: '' }, { scrollToTop: options.scrollToTop !== false });
            if (options.updateUrl !== false) {
                this.updateRouteUrl();
            }
        },

        navToSubfolder(catId, subId, options = {}) {
            if (this.guardUnsavedMeasureCoverage(() => this.navToSubfolder(catId, subId, { ...options, skipCoverageGuard: true }), options)) return;
            const cat = DM_DATA.find(c => c.id === catId);
            const sub = cat.subfolders.find(s => s.id === subId);
            this.setState({ view: 'measures', currentCategory: cat, currentSubfolder: sub, currentMeasure: null, unifiedMeasuresMode: false, unifiedHacsMeasureId: '', selectedUnifiedMeasureValue: '', unifiedMeasurePickerOpen: false, unifiedMeasureSearch: '' }, { scrollToTop: options.scrollToTop !== false });
            if (options.updateUrl !== false) {
                this.updateRouteUrl();
            }
        },

        navToMeasure(catId, subId, measure, options = {}) {
            if (this.guardUnsavedMeasureCoverage(() => this.navToMeasure(catId, subId, measure, { ...options, skipCoverageGuard: true }), options)) return;
            const cat = DM_DATA.find(c => c.id === catId);
            const sub = subId ? cat.subfolders.find(s => s.id === subId) : null;
            const isHcpAnnual = this.isHcpInfluenzaMeasure(measure);
            const isHwr = this.isHwrMeasure(measure);
            const isOp22 = this.isOp22Measure(measure);
            const isQuarterRate = this.isQuarterRateMeasure(measure);
            const isQuarterMedian = this.isQuarterMedianMeasure(measure);
            const isChecklistMeasure = this.isGlobalInfrastructureMeasure(measure) || this.isAntibioticStewardshipMeasure(measure) || this.isEdtcMeasure(measure);
            const isImprovementCalculator = this.isImprovementCalculatorMeasure(measure);
            const currentYear = String(new Date().getFullYear());
            const scopedHacsMeasureId = isImprovementCalculator ? String(options.hacsMeasureId || '').trim() : '';
            const scopedHacsMeasure = scopedHacsMeasureId
                ? DM_IMPROVEMENT_CALCULATOR_MEASURES.find(item => item.id === scopedHacsMeasureId)
                : null;
	            this.setState({
	                view: options.unifiedMode ? 'unified-measures' : 'input',
                currentCategory: cat,
                currentSubfolder: sub,
                currentMeasure: measure,
                unifiedMeasuresMode: !!options.unifiedMode,
                unifiedHacsMeasureId: scopedHacsMeasureId,
                selectedUnifiedMeasureValue: options.unifiedMode ? String(options.selectedUnifiedMeasureValue || '') : '',
                unifiedMeasurePickerOpen: false,
                unifiedMeasureSearch: '',
                inputTab: 'entry',
                manualRows: [isHcpAnnual
                    ? { month: '', year: currentYear, num: '', den: '' }
                    : (isHwr || isOp22 ? { month: '', year: currentYear, num: '', den: '' }
                    : (isQuarterRate ? { month: 'Q1', year: currentYear, num: '', den: '' }
                    : (isQuarterMedian ? { month: 'Q1', year: currentYear, median: '' }
                    : { month: '', year: '', num: '', den: '' /*, median: '' */ })))],
                checklistYear: currentYear,
                checklistQuarter: 'Q1',
                edtcCompositeNum: '',
                edtcCompositeDen: '',
                edtcReportSeries: DM_EDTC_COMPOSITE_KEY,
                checklistRows: isChecklistMeasure ? this.defaultChecklistRows(measure) : [],
                rawDataYearFilter: 'all',
                measureGoalStatus: '',
                improvementCalculator: isImprovementCalculator
                    ? {
                        ...this.defaultImprovementCalculatorState(currentYear),
                        activeTab: 'instructions',
                        runChartMetric: scopedHacsMeasureId || 'c_diff',
                        singleEntry: {
                            month: 'Jan',
                            year: currentYear,
                            numeratorKey: scopedHacsMeasureId || 'c_diff',
                            denominatorKey: scopedHacsMeasure ? scopedHacsMeasure.denominatorKey : 'inpatient_days',
                            numeratorValue: '',
                            denominatorValue: ''
                        }
                    }
                    : this.state.improvementCalculator,
                lastSavedRows: null,
	                uploadError: ''
	            }, { preserveScroll: !!options.preserveScroll, scrollToTop: options.scrollToTop !== false });
                if (options.updateUrl !== false) {
                    this.updateRouteUrl();
                }
	            if (isImprovementCalculator) {
	                window.setTimeout(() => this.loadImprovementCalculatorSubmission(currentYear, { silent: true }), 0);
	                window.setTimeout(() => this.loadImprovementCalculatorDatabase({ silent: true }), 0);
	                window.setTimeout(() => this.loadImprovementMeasureGoals(), 0);
                    window.setTimeout(() => this.loadHacsHaisReportOwnership(), 0);
	            }
                if (!isImprovementCalculator) {
                    window.setTimeout(() => this.loadMeasureGoal(measure), 0);
                    window.setTimeout(() => this.loadReportOwnership(measure), 0);
                }
	        },

        goBack() {
            if (this.guardUnsavedMeasureCoverage(() => this.goBack(), {})) return;
            if (this.state.view === 'input') {
                if (this.state.unifiedMeasuresMode) {
                    this.setState({ view: 'unified-measures', currentCategory: null, currentSubfolder: null, currentMeasure: null, unifiedMeasuresMode: false, unifiedHacsMeasureId: '', selectedUnifiedMeasureValue: '', unifiedMeasurePickerOpen: false, unifiedMeasureSearch: '' });
                    this.updateRouteUrl();
                    return;
                }
                this.setState({ view: 'measures', currentMeasure: null });
            } else if (this.state.view === 'measures') {
                if (this.state.currentSubfolder) {
                    this.setState({ currentSubfolder: null });
                } else {
                    this.setState({ view: 'unified-measures', currentCategory: null, currentSubfolder: null, currentMeasure: null, unifiedMeasuresMode: false, unifiedHacsMeasureId: '', selectedUnifiedMeasureValue: '', unifiedMeasurePickerOpen: false, unifiedMeasureSearch: '' });
                }
            }
            this.updateRouteUrl();
        },

        defaultImprovementCalculatorState(year = String(new Date().getFullYear())) {
            return {
                activeTab: 'data-entry',
                organizationName: this.currentOrganizationName(),
                referenceDate: this.todayIsoDate(),
	                runChartMetric: 'c_diff',
	                selectedYear: String(year),
                    reportYearFilter: 'all',
	                dataEntryMode: 'single',
	                singleEntry: {
	                    month: 'Jan',
	                    numeratorKey: 'c_diff',
	                    denominatorKey: 'inpatient_days',
	                    numeratorValue: '',
	                    denominatorValue: ''
	                },
	                otherMeasureName: 'Other',
	                monthlyRows: this.defaultImprovementMonthlyRows(year),
	                saveStatus: '',
	                savedSubmissionId: null,
	                databaseTab: 'saved',
	                databaseStatus: '',
	                databaseLoaded: false,
	                submissions: [],
	                goals: {}
            };
        },

        currentOrganizationName() {
            return String((DM_CONFIG.userOrgContext && DM_CONFIG.userOrgContext.org_name) || '').trim();
        },

        organizationDataTitle() {
            const orgName = this.currentOrganizationName();
            return orgName ? `${orgName} Data` : 'Organization Data';
        },

        todayIsoDate() {
            const now = new Date();
            return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
        },

        formatUsDate(value) {
            const text = String(value || '').trim();
            const iso = text.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (iso) {
                return `${iso[2]}/${iso[3]}/${iso[1]}`;
            }
            const us = text.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
            if (us) {
                return `${String(us[1]).padStart(2, '0')}/${String(us[2]).padStart(2, '0')}/${us[3]}`;
            }
            return text;
        },

        parseUsDate(value) {
            const text = String(value || '').trim();
            const us = text.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
            if (us) {
                return `${us[3]}-${String(us[1]).padStart(2, '0')}-${String(us[2]).padStart(2, '0')}`;
            }
            const iso = text.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            return iso ? text : '';
        },

        updateImprovementReferenceDate(value) {
            this.updateImprovementCalculatorField('referenceDate', this.parseUsDate(value));
        },

        defaultImprovementMonthlyRows(year) {
            return this.monthOptions().map(month => {
                const events = {};
                const denominators = {};
                DM_IMPROVEMENT_CALCULATOR_MEASURES.forEach(measure => { events[measure.id] = ''; });
                DM_IMPROVEMENT_CALCULATOR_DENOMINATORS.forEach(denominator => { denominators[denominator.id] = ''; });
                return {
                    year: String(year),
                    month,
                    events,
                    denominators
                };
            });
        },

        improvementCalculatorState() {
            const current = this.state.improvementCalculator || {};
            if (Array.isArray(current.monthlyRows) && current.monthlyRows.length) {
                return current;
            }
            return this.defaultImprovementCalculatorState();
        },

        setImprovementCalculatorTab(tab) {
            const normalizedTab = tab === 'impact-dashboard' ? 'run-chart' : (tab === 'database' || tab === 'data-entry' ? 'instructions' : tab);
            const allowed = ['instructions', 'run-chart'];
            const current = this.improvementCalculatorState();
            const nextTab = allowed.includes(normalizedTab) ? normalizedTab : 'instructions';
            this.setState({
                improvementCalculator: {
                    ...current,
                    activeTab: nextTab
                }
            }, { scrollToTop: false });
            this.updateRouteUrl();
            if (['instructions', 'run-chart'].includes(nextTab)) {
                window.setTimeout(() => this.loadImprovementCalculatorDatabase({ silent: true }), 0);
            }
            if (nextTab === 'instructions') {
                window.setTimeout(() => this.loadImprovementMeasureGoals(), 0);
            }
        },

        updateImprovementCalculatorField(field, value) {
            const current = this.improvementCalculatorState();
            this.setState({
                improvementCalculator: {
                    ...current,
                    [field]: value
                }
            }, { preserveScroll: true, scrollToTop: false });
        },

	        updateImprovementCalculatorYear(year) {
	            const current = this.improvementCalculatorState();
                const currentSingleEntry = this.improvementSingleEntryState(current);
                const nextSingleEntry = {
                    ...currentSingleEntry,
                    year: String(year),
                    numeratorValue: '',
                    denominatorValue: ''
                };
                const nextSingleEntries = Object.entries(current.singleEntries || {}).reduce((acc, [measureId, entry]) => {
                    acc[measureId] = {
                        ...entry,
                        year: String(year),
                        numeratorValue: '',
                        denominatorValue: ''
                    };
                    return acc;
                }, {});
	            this.setState({
	                improvementCalculator: {
	                    ...current,
	                    selectedYear: String(year),
	                    monthlyRows: this.defaultImprovementMonthlyRows(year),
                        singleEntry: nextSingleEntry,
                        singleEntries: nextSingleEntries,
	                    saveStatus: 'Loading saved data...',
	                    savedSubmissionId: null
	                }
	            }, { preserveScroll: true, scrollToTop: false });
	            this.loadImprovementCalculatorSubmission(year, { silent: false });
	        },

	        hydrateImprovementCalculatorSubmission(submission, year, options = {}) {
	            const current = this.improvementCalculatorState();
	            const baseRows = this.defaultImprovementMonthlyRows(year);
	            if (!submission) {
	                this.setState({
	                    improvementCalculator: {
	                        ...current,
	                        selectedYear: String(year),
	                        monthlyRows: baseRows,
	                        savedSubmissionId: null,
	                        referenceDate: current.referenceDate || this.todayIsoDate(),
	                        saveStatus: options.silent ? '' : 'No saved calculator data for this reporting year yet.'
	                    }
	                }, { preserveScroll: true, scrollToTop: false });
	                return;
	            }

	            const rowsByMonth = {};
	            (submission.rows || []).forEach(row => {
	                rowsByMonth[String(row.month || '')] = row;
	            });
	            const monthlyRows = baseRows.map(row => {
	                const saved = rowsByMonth[row.month] || {};
	                return {
	                    ...row,
	                    events: {
	                        ...row.events,
	                        ...(saved.events || {})
	                    },
	                    denominators: {
	                        ...row.denominators,
	                        ...(saved.denominators || {})
	                    }
	                };
	            });

	            this.setState({
	                improvementCalculator: {
	                    ...current,
	                    selectedYear: String(submission.reporting_year || year),
	                    organizationName: submission.organization_name || current.organizationName || this.currentOrganizationName(),
	                    referenceDate: submission.reference_date || current.referenceDate || this.todayIsoDate(),
	                    monthlyRows,
	                    savedSubmissionId: submission.id || null,
	                    saveStatus: options.silent ? '' : 'Loaded saved calculator data for this reporting year.'
	                }
	            }, { preserveScroll: true, scrollToTop: false });
	        },

	        loadImprovementCalculatorSubmission(year, options = {}) {
	            const formData = new FormData();
	            formData.append('action', 'qualinav_improvement_calculator_load');
	            formData.append('nonce', DM_CONFIG.nonce);
	            formData.append('reporting_year', String(year || ''));

	            return fetch(DM_CONFIG.ajax_url, {
	                method: 'POST',
	                body: formData
	            })
	            .then(res => res.json())
	            .then(data => {
	                if (data && data.success) {
	                    this.hydrateImprovementCalculatorSubmission(data.data ? data.data.submission : null, year, options);
	                } else if (!options.silent) {
	                    const current = this.improvementCalculatorState();
	                    this.setState({
	                        improvementCalculator: {
	                            ...current,
	                            saveStatus: 'Could not load saved calculator data.'
	                        }
	                    }, { preserveScroll: true, scrollToTop: false });
	                }
	            })
	            .catch(() => {
	                if (!options.silent) {
	                    const current = this.improvementCalculatorState();
	                    this.setState({
	                        improvementCalculator: {
	                            ...current,
	                            saveStatus: 'Could not connect while loading saved calculator data.'
	                        }
	                    }, { preserveScroll: true, scrollToTop: false });
	                }
	            });
	        },

        updateImprovementRunChartMetric(metricId) {
            const current = this.improvementCalculatorState();
            const anchor = document.querySelector('[data-improvement-chart-anchor]');
            const anchorTop = anchor ? anchor.getBoundingClientRect().top : null;
            this.state = {
                ...this.state,
                improvementCalculator: {
                    ...current,
                    runChartMetric: metricId || 'c_diff'
                }
            };
            this.render({ scrollToTop: false });
            if (anchorTop !== null) {
                window.requestAnimationFrame(() => {
                    const nextAnchor = document.querySelector('[data-improvement-chart-anchor]');
                    if (!nextAnchor) return;
                    window.scrollBy(0, nextAnchor.getBoundingClientRect().top - anchorTop);
                });
            }
        },

        updateImprovementRunChartYearFilter(year) {
            const current = this.improvementCalculatorState();
            const anchor = document.querySelector('[data-improvement-chart-anchor]');
            const anchorTop = anchor ? anchor.getBoundingClientRect().top : null;
            this.state = {
                ...this.state,
                improvementCalculator: {
                    ...current,
                    reportYearFilter: String(year || 'all')
                }
            };
            this.render({ scrollToTop: false });
            if (anchorTop !== null) {
                window.requestAnimationFrame(() => {
                    const nextAnchor = document.querySelector('[data-improvement-chart-anchor]');
                    if (!nextAnchor) return;
                    window.scrollBy(0, nextAnchor.getBoundingClientRect().top - anchorTop);
                });
            }
        },

        setImprovementDataEntryMode(mode) {
            const current = this.improvementCalculatorState();
            this.setState({
                improvementCalculator: {
                    ...current,
                    dataEntryMode: 'single'
                }
            }, { preserveScroll: true, scrollToTop: false });
        },

        improvementDenominatorValueForMonth(month, denominatorKey, calculator = null) {
            calculator = calculator || this.improvementCalculatorState();
            if (!month || !denominatorKey) return '';
            const row = (calculator.monthlyRows || []).find(item => String(item.month || '') === String(month));
            if (!row || !row.denominators) return '';
            const value = row.denominators[denominatorKey];
            return String(value == null ? '' : value).trim();
        },

        improvementSingleEntryState(calculator = null, measureId = '') {
            calculator = calculator || this.improvementCalculatorState();
            const lockedMeasureId = String(measureId || '').trim();
            const saved = lockedMeasureId
                ? (((calculator.singleEntries || {})[lockedMeasureId]) || { numeratorKey: lockedMeasureId })
                : (calculator.singleEntry || {});
            const singleEntryMeasures = this.improvementSingleEntryMeasureOptions();
            const measure = (lockedMeasureId ? singleEntryMeasures.find(item => item.id === lockedMeasureId) : null)
                || singleEntryMeasures.find(item => item.id === saved.numeratorKey)
                || singleEntryMeasures[0];
            const denominatorOptions = this.improvementDenominatorOptionsForMeasure(measure);
            const savedDenominator = denominatorOptions.find(option => option.id === saved.denominatorKey);
            return {
                year: String(saved.year || calculator.selectedYear || new Date().getFullYear()),
                month: saved.month || 'Jan',
                numeratorKey: measure ? measure.id : '',
                denominatorKey: savedDenominator ? savedDenominator.id : ((denominatorOptions[0] && denominatorOptions[0].id) || ''),
                numeratorValue: saved.numeratorValue || '',
                denominatorValue: saved.denominatorValue || ''
            };
        },

        improvementSingleEntryMeasureOptions() {
            return DM_IMPROVEMENT_CALCULATOR_MEASURES.filter(measure => measure.id !== 'other');
        },

        improvementDenominatorOptionsForMeasure(measure) {
            if (!measure) return [];
            if (Array.isArray(measure.denominatorOptions) && measure.denominatorOptions.length) {
                return measure.denominatorOptions.map(option => {
                    if (typeof option === 'string') {
                        return {
                            id: option,
                            label: this.improvementDenominatorLabel(option)
                        };
                    }
                    return {
                        id: option.id || '',
                        label: option.label || this.improvementDenominatorLabel(option.id)
                    };
                }).filter(option => option.id);
            }
            return [{
                id: measure.denominatorKey,
                label: measure.denominatorLabel || this.improvementDenominatorLabel(measure.denominatorKey)
            }].filter(option => option.id);
        },

        improvementSingleEntryStorePatch(calculator, singleEntry, measureId = '') {
            const lockedMeasureId = String(measureId || '').trim();
            if (!lockedMeasureId) {
                return {
                    singleEntry
                };
            }
            return {
                singleEntries: {
                    ...(calculator.singleEntries || {}),
                    [lockedMeasureId]: {
                        ...singleEntry,
                        numeratorKey: lockedMeasureId
                    }
                }
            };
        },

        improvementSingleEntryDomSuffix(measureId = '') {
            const value = String(measureId || '').trim();
            return value ? `-${value}` : '';
        },

        updateImprovementSingleEntryField(field, value, measureId = '') {
            const current = this.improvementCalculatorState();
            const lockedMeasureId = String(measureId || '').trim();
            const previousEntry = this.improvementSingleEntryState(current, lockedMeasureId);
            const previousValue = previousEntry[field];
            const singleEntry = {
                ...previousEntry,
                [field]: value,
                numeratorKey: lockedMeasureId || previousEntry.numeratorKey
            };
            const periodChanged = ['year', 'month'].includes(field) && String(previousValue || '') !== String(value || '');
            if (periodChanged) {
                singleEntry.numeratorValue = '';
                singleEntry.denominatorValue = '';
            }
            if (field === 'numeratorKey' && !lockedMeasureId) {
                const measure = DM_IMPROVEMENT_CALCULATOR_MEASURES.find(item => item.id === value);
                const denominatorOptions = this.improvementDenominatorOptionsForMeasure(measure);
                const previousDenominatorKey = singleEntry.denominatorKey;
                singleEntry.denominatorKey = (denominatorOptions[0] && denominatorOptions[0].id) || '';
                singleEntry.numeratorValue = '';
                if (singleEntry.denominatorKey !== previousDenominatorKey || String(singleEntry.denominatorValue || '').trim() === '') {
                    singleEntry.denominatorValue = this.improvementDenominatorValueForMonth(singleEntry.month, singleEntry.denominatorKey, current);
                }
            }

            if (!periodChanged && (field === 'month' || field === 'denominatorKey')) {
                singleEntry.denominatorValue = this.improvementDenominatorValueForMonth(singleEntry.month, singleEntry.denominatorKey, current);
            }

            if (['numeratorValue', 'denominatorValue'].includes(field)) {
                this.state.improvementCalculator = {
                    ...current,
                    ...this.improvementSingleEntryStorePatch(current, singleEntry, lockedMeasureId),
                    saveStatus: ''
                };
                const note = document.getElementById(`dmImprovementSaveNote${this.improvementSingleEntryDomSuffix(lockedMeasureId)}`);
                if (note) {
                    note.textContent = '';
                }
                this.syncImprovementSingleEntryRate(singleEntry, lockedMeasureId);
                return;
            }

            this.setState({
                improvementCalculator: {
                    ...current,
                    ...this.improvementSingleEntryStorePatch(current, singleEntry, lockedMeasureId),
                    saveStatus: ''
                }
            }, { preserveScroll: true, scrollToTop: false });
            window.setTimeout(() => this.syncImprovementSingleEntryRate(null, lockedMeasureId), 0);
        },

        saveImprovementSingleMonthData(e, measureId = '') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            const calculator = this.improvementCalculatorState();
            const lockedMeasureId = String(measureId || '').trim();
            const singleEntry = this.improvementSingleEntryState(calculator, lockedMeasureId);
            const measure = DM_IMPROVEMENT_CALCULATOR_MEASURES.find(item => item.id === singleEntry.numeratorKey);
            const denominatorKey = singleEntry.denominatorKey || (measure ? measure.denominatorKey : '');
            const numeratorValue = String(singleEntry.numeratorValue || '').trim();
            const denominatorValue = String(singleEntry.denominatorValue || '').trim();
            if (!singleEntry.year || !singleEntry.month || !measure || !denominatorKey || numeratorValue === '' || denominatorValue === '') {
                this.setState({
                    improvementCalculator: {
                        ...calculator,
                        ...this.improvementSingleEntryStorePatch(calculator, singleEntry, lockedMeasureId),
                        saveStatus: 'Choose a year, month, numerator measure, and denominator before saving.'
                    }
                }, { preserveScroll: true, scrollToTop: false });
                return;
            }
            if (this.improvementSingleEntryHasNumDenInversion(singleEntry)) {
                this.setState({
                    improvementCalculator: {
                        ...calculator,
                        ...this.improvementSingleEntryStorePatch(calculator, singleEntry, lockedMeasureId),
                        saveStatus: 'Denominator cannot be lower than numerator.'
                    }
                }, { preserveScroll: true, scrollToTop: false });
                return;
            }

            const baseRows = calculator.monthlyRows && calculator.monthlyRows.length
                ? calculator.monthlyRows
                : this.defaultImprovementMonthlyRows(calculator.selectedYear || new Date().getFullYear());
            const monthlyRows = baseRows.map(row => {
                if (row.month !== singleEntry.month) return row;
                return {
                    ...row,
                    events: {
                        ...(row.events || {}),
                        [measure.id]: numeratorValue
                    },
                    denominators: {
                        ...(row.denominators || {}),
                        [denominatorKey]: denominatorValue
                    }
                };
            });

            this.state.improvementCalculator = {
                ...calculator,
                ...this.improvementSingleEntryStorePatch(calculator, {
                    ...singleEntry,
                    denominatorKey,
                    numeratorValue,
                    denominatorValue
                }, lockedMeasureId),
                monthlyRows,
                saveStatus: ''
            };
            this.saveImprovementCalculatorData(e, false, 'single_month', [
                {
                    month: singleEntry.month,
                    events: {
                        [measure.id]: numeratorValue
                    },
                    denominators: {
                        [denominatorKey]: denominatorValue
                    }
                }
            ], singleEntry.year);
        },

	        updateImprovementMonthlyValue(rowIndex, group, key, value) {
	            const current = this.improvementCalculatorState();
            const rows = (current.monthlyRows || []).map((row, index) => {
                if (index !== rowIndex) return row;
                return {
                    ...row,
                    [group]: {
                        ...(row[group] || {}),
                        [key]: value
                    }
                };
            });
		            this.state.improvementCalculator = {
		                ...current,
		                monthlyRows: rows,
		                saveStatus: ''
		            };
		            this.renderImprovementMonthlyPreview(rowIndex);
		            this.syncImprovementCalculatorControls();
		        },

	        syncImprovementCalculatorControls() {
	            const saveBtn = document.getElementById('dmImprovementSaveBtn');
	            if (saveBtn) {
	                saveBtn.disabled = !this.improvementCalculatorHasData();
	            }
	            const saveNote = document.getElementById('dmImprovementSaveNote');
	            if (saveNote) {
	                saveNote.textContent = '';
	            }
	        },

        renderImprovementMonthlyPreview(rowIndex) {
            const row = (this.improvementCalculatorState().monthlyRows || [])[rowIndex];
            if (!row) return;
            const totalCell = document.getElementById(`dmImprovementTotalHarms-${rowIndex}`);
            const rateCell = document.getElementById(`dmImprovementTotalHarmsRate-${rowIndex}`);
            if (totalCell) {
                totalCell.textContent = this.improvementTotalHarms(row);
            }
            if (rateCell) {
                const rate = this.improvementTotalHarmsRate(row);
                rateCell.textContent = rate === '' ? '-' : rate.toFixed(1);
            }
        },

        improvementTotalHarms(row) {
            return DM_IMPROVEMENT_CALCULATOR_MEASURES.reduce((sum, measure) => {
                const value = Number((row.events || {})[measure.id]);
                return sum + (Number.isFinite(value) ? value : 0);
            }, 0);
        },

        improvementTotalHarmsRate(row) {
            const inpatientDays = Number((row.denominators || {}).inpatient_days);
            if (!Number.isFinite(inpatientDays) || inpatientDays <= 0) return '';
            return (this.improvementTotalHarms(row) / inpatientDays) * 1000;
        },

        improvementSingleEntryRateDisplay(singleEntry = null, measure = null) {
            const entry = singleEntry || this.improvementSingleEntryState();
            const selectedMeasure = measure || DM_IMPROVEMENT_CALCULATOR_MEASURES.find(item => item.id === entry.numeratorKey);
            const numerator = this.parseNumberValue(entry.numeratorValue);
            const denominator = this.parseNumberValue(entry.denominatorValue);
            if (!selectedMeasure || !Number.isFinite(numerator) || !Number.isFinite(denominator) || denominator <= 0) {
                return '-';
            }
            return this.improvementRateDisplayForMeasure(selectedMeasure, numerator / denominator);
        },

        improvementSingleEntryHasNumDenInversion(singleEntry = null) {
            const entry = singleEntry || this.improvementSingleEntryState();
            return this.hasNumDenInversion({
                num: entry.numeratorValue,
                den: entry.denominatorValue
            });
        },

        improvementSingleEntryCanSave(singleEntry = null) {
            const entry = singleEntry || this.improvementSingleEntryState();
            return !!entry.year
                && !!entry.month
                && !!entry.numeratorKey
                && !!entry.denominatorKey
                && String(entry.numeratorValue || '').trim() !== ''
                && String(entry.denominatorValue || '').trim() !== ''
                && !this.improvementSingleEntryHasNumDenInversion(entry);
        },

        syncImprovementSingleEntryRate(singleEntry = null, measureId = '') {
            const suffix = this.improvementSingleEntryDomSuffix(measureId);
            const rateInput = document.getElementById(`dmImprovementSingleEntryRate${suffix}`);
            const entry = singleEntry || this.improvementSingleEntryState(null, measureId);
            const measure = DM_IMPROVEMENT_CALCULATOR_MEASURES.find(item => item.id === entry.numeratorKey);
            if (rateInput) {
                rateInput.value = this.improvementSingleEntryRateDisplay(entry, measure);
            }
            const rateField = document.getElementById(`dmImprovementSingleEntryRateField${suffix}`);
            if (rateField) {
                const existingWarning = rateField.querySelector('.dm-den-warning-icon');
                if (existingWarning) existingWarning.remove();
                if (this.improvementSingleEntryHasNumDenInversion(entry)) {
                    rateField.insertAdjacentHTML('beforeend', this.renderNumDenWarning({ num: entry.numeratorValue, den: entry.denominatorValue }));
                }
            }
            const saveBtn = document.getElementById(`dmImprovementSaveBtn${suffix}`);
            if (saveBtn) {
                saveBtn.disabled = !this.improvementSingleEntryCanSave(entry);
            }
        },

        improvementSavedGoalForChart(measureId) {
            const goal = (this.state.measureGoals || {})[this.improvementGoalKey(measureId)] || {};
            const goalValue = this.parseNumberValue(goal.goal_rate);
            return Number.isFinite(goalValue) ? goalValue : null;
        },

        improvementRunChartOptions() {
            return DM_IMPROVEMENT_CALCULATOR_MEASURES.map(measure => {
                const valueLabel = measure.rateMultiplier === 1 ? '%' : measure.rateUnit;
                const decimals = measure.rateMultiplier === 1 ? 1 : 2;
                const savedGoal = this.improvementSavedGoalForChart(measure.id);
                return {
                    id: measure.id,
                    label: measure.rateLabel || measure.label,
                    title: `${measure.label} Run Chart`,
                    valueLabel,
                    decimals,
                    goalValue: savedGoal,
                    goalLabel: Number.isFinite(savedGoal)
                        ? `Goal ${this.improvementChartValueLabel(savedGoal, valueLabel === '%' ? '%' : valueLabel, decimals)}`
                        : ''
                };
            });
        },

        improvementRunChartConfig(metricId) {
            const options = this.improvementRunChartOptions();
            return options.find(option => option.id === metricId) || options[0];
        },

	        improvementRunChartPoints(metricId) {
            const calculator = this.improvementCalculatorState();
            const config = this.improvementRunChartConfig(metricId);
            const measure = DM_IMPROVEMENT_CALCULATOR_MEASURES.find(item => item.id === metricId);
            const selectedYear = String(calculator.reportYearFilter || 'all');
            const submissions = (calculator.submissions || [])
                .filter(submission => String(submission.status || 'active') !== 'archived')
                .filter(submission => selectedYear === 'all' || String(submission.reporting_year || '') === selectedYear);
            const rows = [];
            submissions.forEach(submission => {
                (submission.rows || []).forEach(row => {
                    rows.push({
                        ...row,
                        reportingYear: String(submission.reporting_year || ''),
                        monthNum: Number(row.month_num) || (this.monthOptions().indexOf(row.month) + 1)
                    });
                });
            });
            rows.sort((a, b) => {
                const yearDiff = Number(a.reportingYear || 0) - Number(b.reportingYear || 0);
                if (yearDiff !== 0) return yearDiff;
                return (Number(a.monthNum) || 0) - (Number(b.monthNum) || 0);
            });
            return rows.map(row => {
                let value = null;
                let numerator = null;
                let denominator = null;
                if (metricId === 'total_harms') {
                    const rate = this.improvementTotalHarmsRate(row);
                    value = rate === '' ? null : Number(rate);
                    numerator = this.improvementTotalHarms(row);
                    denominator = Number((row.denominators || {}).inpatient_days);
                } else if (measure) {
                    numerator = Number((row.events || {})[measure.id]);
                    denominator = Number((row.denominators || {})[measure.denominatorKey]);
                    if (Number.isFinite(numerator) && Number.isFinite(denominator) && denominator > 0) {
                        value = (numerator / denominator) * (measure.rateMultiplier === 1 ? 100 : measure.rateMultiplier);
                    }
                }
                return {
                    label: selectedYear === 'all' ? `${row.month} ${row.reportingYear}`.trim() : row.month,
                    value,
                    numerator,
                    denominator,
                    denominatorLabel: measure ? this.improvementDenominatorLabel(measure.denominatorKey) : 'Inpatient Days',
                    title: config.title
                };
	            }).filter(point => Number.isFinite(point.value));
	        },

	        improvementCalculatorHasData(calculator = null) {
	            calculator = calculator || this.improvementCalculatorState();
	            return (calculator.monthlyRows || []).some(row => {
	                return ['events', 'denominators'].some(group => {
	                    const values = row[group] || {};
	                    return Object.values(values).some(value => String(value == null ? '' : value).trim() !== '');
	                });
	            });
	        },

            upsertImprovementCalculatorSubmission(submission = null) {
                if (!submission || !submission.id) {
                    return this.improvementCalculatorState().submissions || [];
                }
                const current = this.improvementCalculatorState();
                const submissionId = Number(submission.id);
                const next = (current.submissions || []).filter(item => Number(item.id) !== submissionId);
                next.unshift(submission);
                return next;
            },

            openImprovementOverwriteModal(payload = {}) {
                const modal = document.getElementById('dmImprovementOverwriteModal');
                const message = document.getElementById('dmImprovementOverwriteMessage');
                const button = document.getElementById('dmImprovementOverwriteContinue');
                if (!modal || !message || !button) return;

                this.pendingImprovementOverwrite = {
                    message: payload.message || 'This month and measure already has saved data. Replace it?',
                    saveMode: payload.saveMode || '',
                    rowsOverride: Array.isArray(payload.rowsOverride) ? payload.rowsOverride : null,
                    reportingYear: payload.reportingYear || ''
                };
                message.textContent = this.pendingImprovementOverwrite.message;
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-sync-alt"></i> Replace Saved Assessment';
                modal.style.display = 'flex';
            },

            closeImprovementOverwriteModal(cancelled = false) {
                this.pendingImprovementOverwrite = null;
                const modal = document.getElementById('dmImprovementOverwriteModal');
                if (modal) modal.style.display = 'none';
                if (cancelled) {
                    const current = this.improvementCalculatorState();
                    this.setState({
                        improvementCalculator: {
                            ...current,
                            saveStatus: ''
                        }
                    }, { preserveScroll: true, scrollToTop: false });
                }
            },

            confirmImprovementOverwrite() {
                const pending = this.pendingImprovementOverwrite;
                if (!pending) {
                    this.closeImprovementOverwriteModal(false);
                    return;
                }
                const button = document.getElementById('dmImprovementOverwriteContinue');
                if (button) {
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Replacing...';
                }
                this.closeImprovementOverwriteModal(false);
                this.saveImprovementCalculatorData(null, true, pending.saveMode, pending.rowsOverride, pending.reportingYear);
            },

	        saveImprovementCalculatorData(e, overwrite = false, saveMode = '', rowsOverride = null, reportingYearOverride = '') {
	            if (e && typeof e.preventDefault === 'function') {
	                e.preventDefault();
	            }
	            const calculator = this.improvementCalculatorState();
                const reportingYear = String(reportingYearOverride || calculator.selectedYear || new Date().getFullYear());
	            const rowsToSave = Array.isArray(rowsOverride) ? rowsOverride : (calculator.monthlyRows || []);
	            if (!this.improvementCalculatorHasData({ ...calculator, monthlyRows: rowsToSave })) {
	                this.setState({
	                    improvementCalculator: {
	                        ...calculator,
	                        saveStatus: 'Enter at least one event count or denominator before saving.'
	                    }
	                }, { preserveScroll: true, scrollToTop: false });
	                return;
	            }

	            const btn = e && e.currentTarget ? e.currentTarget : null;
	            const originalHtml = btn ? btn.innerHTML : '';
	            if (btn) {
	                btn.disabled = true;
	                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
	            }

	            const formData = new FormData();
	            formData.append('action', 'qualinav_improvement_calculator_save');
	            formData.append('nonce', DM_CONFIG.nonce);
	            formData.append('reference_date', calculator.referenceDate || this.todayIsoDate());
	            formData.append('reporting_year', reportingYear);
	            formData.append('rows', JSON.stringify(rowsToSave));
	            if (saveMode) {
	                formData.append('save_mode', saveMode);
	            }
	            if (overwrite) {
	                formData.append('overwrite', '1');
	            }

	            fetch(DM_CONFIG.ajax_url, {
	                method: 'POST',
	                body: formData
	            })
	            .then(res => res.json())
	            .then(data => {
	                if (btn) {
	                    btn.disabled = false;
	                    btn.innerHTML = originalHtml;
	                }

	                if (data && data.success) {
	                    const submission = data.data ? data.data.submission : null;
	                    this.hydrateImprovementCalculatorSubmission(
	                        submission,
	                        reportingYear,
	                        { silent: true }
	                    );
	                    const current = this.improvementCalculatorState();
                        const nextSubmissions = this.upsertImprovementCalculatorSubmission(submission);
	                    this.setState({
	                        improvementCalculator: {
	                            ...current,
                                submissions: nextSubmissions,
	                            saveStatus: overwrite ? 'Saved assessment replaced.' : (data.data && data.data.message ? data.data.message : 'Calculator data saved.'),
	                            databaseLoaded: false
	                        }
	                    }, { preserveScroll: true, scrollToTop: false });
                        window.setTimeout(() => this.loadImprovementCalculatorDatabase({ force: true, silent: true }), 0);
	                    return;
	                }

	                const error = data ? data.data : null;
	                if (error && error.code === 'existing_submission') {
                        this.openImprovementOverwriteModal({
                            message: 'A saved HACs & HAIs submission already exists for this reporting year. Replace it?',
                            saveMode,
                            rowsOverride,
                            reportingYear
                        });
	                    return;
	                }

	                if (error && error.code === 'existing_value') {
	                    const overwriteMessage = `${error.message || 'This month and measure already has saved data.'} Replace it?`;
                        this.openImprovementOverwriteModal({
                            message: overwriteMessage,
                            saveMode,
                            rowsOverride,
                            reportingYear
                        });
	                    return;
	                }

	                const message = error && error.message ? error.message : (typeof error === 'string' ? error : 'Could not save calculator data.');
	                this.setState({
	                    improvementCalculator: {
	                        ...this.improvementCalculatorState(),
	                        saveStatus: message
	                    }
	                }, { preserveScroll: true, scrollToTop: false });
	            })
	            .catch(() => {
	                if (btn) {
	                    btn.disabled = false;
	                    btn.innerHTML = originalHtml;
	                }
	                this.setState({
	                    improvementCalculator: {
	                        ...this.improvementCalculatorState(),
	                        saveStatus: 'Connection error while saving calculator data.'
	                    }
	                }, { preserveScroll: true, scrollToTop: false });
	            });
	        },

	        setImprovementCalculatorDatabaseTab(tab) {
	            const current = this.improvementCalculatorState();
	            this.setState({
	                improvementCalculator: {
	                    ...current,
	                    databaseTab: ['archive', 'raw'].includes(tab) ? tab : 'saved'
	                }
	            }, { preserveScroll: true, scrollToTop: false });
	            this.updateRouteUrl({ replace: true });
	            window.setTimeout(() => this.loadImprovementCalculatorDatabase({ silent: true }), 0);
	        },

	        loadImprovementCalculatorDatabase(options = {}) {
	            const calculator = this.improvementCalculatorState();
	            if (calculator.databaseLoaded && !options.force) {
	                return Promise.resolve();
	            }

	            const formData = new FormData();
	            formData.append('action', 'qualinav_improvement_calculator_list');
	            formData.append('nonce', DM_CONFIG.nonce);

	            if (!options.silent) {
	                this.setState({
	                    improvementCalculator: {
	                        ...calculator,
	                        databaseStatus: 'Loading calculator database...'
	                    }
	                }, { preserveScroll: true, scrollToTop: false });
	            }

	            return fetch(DM_CONFIG.ajax_url, {
	                method: 'POST',
	                body: formData
	            })
	            .then(res => res.json())
	            .then(data => {
	                const current = this.improvementCalculatorState();
	                if (data && data.success) {
	                    this.setState({
	                        improvementCalculator: {
	                            ...current,
	                            submissions: (data.data && Array.isArray(data.data.submissions)) ? data.data.submissions : [],
	                            databaseLoaded: true,
	                            databaseStatus: ''
	                        }
	                    }, { preserveScroll: true, scrollToTop: false });
	                    return;
	                }
	                this.setState({
	                    improvementCalculator: {
	                        ...current,
	                        databaseStatus: 'Could not load calculator database.'
	                    }
	                }, { preserveScroll: true, scrollToTop: false });
	            })
	            .catch(() => {
	                const current = this.improvementCalculatorState();
	                this.setState({
	                    improvementCalculator: {
	                        ...current,
	                        databaseStatus: 'Could not connect while loading calculator database.'
	                    }
	                }, { preserveScroll: true, scrollToTop: false });
	            });
	        },

	        loadImprovementSubmissionIntoEntry(submissionId) {
	            const calculator = this.improvementCalculatorState();
	            const submission = (calculator.submissions || []).find(item => Number(item.id) === Number(submissionId));
	            if (!submission) return;
	            this.hydrateImprovementCalculatorSubmission(submission, submission.reporting_year, { silent: true });
	            const current = this.improvementCalculatorState();
	            this.setState({
	                improvementCalculator: {
	                    ...current,
	                    activeTab: 'data-entry',
	                    saveStatus: 'Loaded calculator submission for editing.'
	                }
	            }, { preserveScroll: true, scrollToTop: false });
	        },

            improvementSavedRowById(rowId) {
                const rows = this.improvementCalculatorRawRows((this.improvementCalculatorState().submissions || []));
                return rows.find(row => String(row.id) === String(rowId)) || null;
            },

            openImprovementSubmissionPreview(rowId) {
                const row = this.improvementSavedRowById(rowId);
                const modal = document.getElementById('dmImprovementPreviewModal');
                const title = document.getElementById('dmImprovementPreviewTitle');
                const body = document.getElementById('dmImprovementPreviewBody');
                if (!row || !modal || !title || !body) return;
                const rate = this.improvementStoredRateDisplay(row.measureId, row.rateValue, row.eventValue, row.denominatorValue);
                title.textContent = `${row.measure} — ${row.month} ${row.year}`;
                body.innerHTML = `
                    <div style="display:grid; grid-template-columns:180px 1fr; gap:10px 16px; font-size:14px; line-height:1.45;">
                        <strong>Measure</strong><span>${this.escapeHtml(row.measure || '—')}</span>
                        <strong>Year</strong><span>${this.escapeHtml(row.year || '—')}</span>
                        <strong>Month</strong><span>${this.escapeHtml(row.month || '—')}</span>
                        <strong>Numerator Value</strong><span>${this.escapeHtml(row.eventValue === '' || row.eventValue == null ? '—' : row.eventValue)}</span>
                        <strong>Denominator</strong><span>${this.escapeHtml(row.denominator || '—')}</span>
                        <strong>Denominator Value</strong><span>${this.escapeHtml(row.denominatorValue === '' || row.denominatorValue == null ? '—' : row.denominatorValue)}</span>
                        <strong>Rate</strong><span>${this.escapeHtml(rate)}</span>
                        <strong>Reference Date</strong><span>${this.escapeHtml(this.improvementSubmissionDate(row.referenceDate))}</span>
                        <strong>Updated</strong><span>${this.escapeHtml(this.improvementSubmissionDateTime(row.updatedAt))}</span>
                    </div>
                `;
                modal.style.display = 'flex';
            },

            closeImprovementSubmissionPreview() {
                const modal = document.getElementById('dmImprovementPreviewModal');
                if (modal) modal.style.display = 'none';
            },

	        archiveImprovementCalculatorSubmission(submissionId) {
	            if (!submissionId) return;
	            if (!confirm('Archive this calculator submission?')) return;

	            const formData = new FormData();
	            formData.append('action', 'qualinav_improvement_calculator_archive');
	            formData.append('nonce', DM_CONFIG.nonce);
	            formData.append('submission_id', String(submissionId));

	            fetch(DM_CONFIG.ajax_url, { method: 'POST', body: formData })
	                .then(res => res.json())
	                .then(data => {
	                    if (!data || !data.success) {
	                        const error = data ? data.data : null;
	                        alert(typeof error === 'string' ? error : 'Could not archive calculator submission.');
	                        return;
	                    }
	                    const current = this.improvementCalculatorState();
	                    this.setState({
	                        improvementCalculator: {
	                            ...current,
	                            databaseLoaded: false,
	                            databaseStatus: 'Calculator submission archived.'
	                        }
	                    }, { preserveScroll: true, scrollToTop: false });
	                    this.loadImprovementCalculatorDatabase({ force: true, silent: true });
	                })
	                .catch(() => alert('Could not connect while archiving calculator submission.'));
	        },

	        restoreImprovementCalculatorSubmission(submissionId, overwrite = false) {
	            if (!submissionId) return;

	            const formData = new FormData();
	            formData.append('action', 'qualinav_improvement_calculator_restore');
	            formData.append('nonce', DM_CONFIG.nonce);
	            formData.append('submission_id', String(submissionId));
	            if (overwrite) {
	                formData.append('overwrite', '1');
	            }

	            fetch(DM_CONFIG.ajax_url, { method: 'POST', body: formData })
	                .then(res => res.json())
	                .then(data => {
	                    if (data && data.success) {
	                        const current = this.improvementCalculatorState();
	                        this.setState({
	                            improvementCalculator: {
	                                ...current,
	                                databaseLoaded: false,
	                                databaseTab: 'saved',
	                                databaseStatus: 'Calculator submission restored.'
	                            }
	                        }, { preserveScroll: true, scrollToTop: false });
	                        this.loadImprovementCalculatorDatabase({ force: true, silent: true });
	                        return;
	                    }

	                    const error = data ? data.data : null;
	                    if (error && error.code === 'existing_submission') {
	                        const shouldOverwrite = confirm('An active calculator submission already exists for this reporting year. Restore this archived submission and archive the active one?');
	                        if (shouldOverwrite) {
	                            this.restoreImprovementCalculatorSubmission(submissionId, true);
	                        }
	                        return;
	                    }

	                    alert(error && error.message ? error.message : (typeof error === 'string' ? error : 'Could not restore calculator submission.'));
	                })
	                .catch(() => alert('Could not connect while restoring calculator submission.'));
	        },

	        improvementSubmissionDate(value) {
	            if (!value) return '—';
	            const text = String(value).trim();
	            const iso = text.match(/^(\d{4})-(\d{2})-(\d{2})/);
	            return iso ? `${iso[2]}/${iso[3]}/${iso[1]}` : this.escapeHtml(text);
	        },

	        improvementSubmissionDateTime(value) {
	            if (!value) return '—';
	            const text = String(value).replace(' ', 'T');
	            const date = new Date(text);
	            if (!Number.isNaN(date.getTime())) {
	                return date.toLocaleString('en-US', {
	                    month: 'short',
	                    day: 'numeric',
	                    year: 'numeric',
	                    hour: 'numeric',
	                    minute: '2-digit'
	                });
	            }
	            return String(value);
	        },

	        improvementMeasureLabel(measureId) {
	            const measure = DM_IMPROVEMENT_CALCULATOR_MEASURES.find(item => item.id === measureId);
	            return measure ? measure.label : measureId;
	        },

	        improvementDenominatorLabel(denominatorId) {
	            const denominator = DM_IMPROVEMENT_CALCULATOR_DENOMINATORS.find(item => item.id === denominatorId);
	            return denominator ? denominator.label : denominatorId;
	        },

	        improvementStoredRateDisplay(measureId, value, eventValue = null, denominatorValue = null) {
	            const measure = DM_IMPROVEMENT_CALCULATOR_MEASURES.find(item => item.id === measureId);
	            const numerator = this.parseNumberValue(eventValue);
	            const denominator = this.parseNumberValue(denominatorValue);
	            if (measure && Number.isFinite(numerator) && Number.isFinite(denominator) && denominator > 0) {
	                return this.improvementRateDisplayForMeasure(measure, numerator / denominator);
	            }
	            const number = Number(value);
	            if (!Number.isFinite(number)) return '—';
	            if (measure && measure.rateMultiplier === 1) {
	                return this.formatPercent(number * 100, 1);
	            }
	            return number.toFixed(1).replace(/\.0$/, '');
	        },

	        improvementCalculatorRawRows(submissions) {
	            const activeSubmissions = (submissions || []).filter(item => String(item.status || 'active') !== 'archived');
	            const rawRows = [];
	            activeSubmissions.forEach(submission => {
	                (submission.rows || []).forEach(row => {
	                    const events = row.events || {};
	                    const denominators = row.denominators || {};
	                    const rates = row.rates || {};
	                    DM_IMPROVEMENT_CALCULATOR_MEASURES.forEach(measure => {
	                        const eventValue = events[measure.id];
	                        const denominatorValue = denominators[measure.denominatorKey];
	                        const hasEvent = String(eventValue == null ? '' : eventValue).trim() !== '';
	                        const hasDenominator = String(denominatorValue == null ? '' : denominatorValue).trim() !== '';
	                        if (!hasEvent && !hasDenominator) return;
                            const submissionId = Number(submission.id) || 0;
                            const monthNum = Number(row.month_num) || (this.monthOptions().indexOf(row.month) + 1);
	                        rawRows.push({
                                id: `${submissionId}-${measure.id}-${monthNum}`,
                                submissionId,
	                            year: Number(submission.reporting_year) || 0,
	                            referenceDate: submission.reference_date || '',
	                            monthNum,
	                            month: row.month || '',
	                            measure: measure.label,
	                            measureId: measure.id,
	                            eventValue,
	                            denominator: this.improvementDenominatorLabel(measure.denominatorKey),
	                            denominatorValue,
	                            rateValue: rates[measure.id],
	                            updatedAt: submission.updated_at || '',
	                            file: `Submission #${submission.id}`
	                        });
	                    });
	                });
	            });
	            return rawRows.sort((a, b) => {
	                if (b.year !== a.year) return b.year - a.year;
	                if (a.monthNum !== b.monthNum) return a.monthNum - b.monthNum;
	                return String(a.measure).localeCompare(String(b.measure));
	            });
	        },

	        improvementMeasurePoint(measure, row) {
	            const numerator = Number((row.events || {})[measure.id]);
	            const denominator = Number((row.denominators || {})[measure.denominatorKey]);
	            if (!Number.isFinite(numerator) || !Number.isFinite(denominator) || denominator <= 0) {
	                return null;
	            }
	            return {
	                month: row.month,
	                numerator,
	                denominator,
	                ratio: numerator / denominator,
	                displayRate: (numerator / denominator) * (measure.rateMultiplier === 1 ? 100 : measure.rateMultiplier)
	            };
	        },

	        improvementRateDisplayForMeasure(measure, ratio) {
	            const number = Number(ratio);
	            if (!Number.isFinite(number)) return '—';
	            if (measure.rateMultiplier === 1) {
	                return this.formatPercent(number * 100, 1);
	            }
	            return `${(number * measure.rateMultiplier).toFixed(1).replace(/\.0$/, '')} ${measure.rateUnit}`;
	        },

	        improvementImpactRows(calculator = null) {
	            calculator = calculator || this.improvementCalculatorState();
	            return DM_IMPROVEMENT_CALCULATOR_MEASURES.map(measure => {
	                const points = (calculator.monthlyRows || [])
	                    .map(row => this.improvementMeasurePoint(measure, row))
	                    .filter(Boolean);
	                if (points.length < 2) {
	                    return {
	                        measure,
	                        ready: false,
	                        points
	                    };
	                }

	                const baseline = points[0];
	                const current = points[points.length - 1];
	                const savedGoal = this.improvementSavedGoalForChart(measure.id);
	                const targetRatio = Number.isFinite(savedGoal)
	                    ? savedGoal / (measure.rateMultiplier === 1 ? 100 : measure.rateMultiplier)
	                    : null;
	                const reductionGoal = Number.isFinite(targetRatio) && baseline.ratio > 0
	                    ? Math.max(0, (baseline.ratio - targetRatio) / baseline.ratio)
	                    : null;
	                const harmDelta = Math.max(0, baseline.ratio - current.ratio);
	                const harmsPrevented = harmDelta * current.denominator;
	                const reference = DM_IMPROVEMENT_CALCULATOR_REFERENCES[measure.referenceKey] || {};
	                const costAvoided = harmsPrevented * (Number(reference.costPerCase) || 0);
	                const livesSaved = Number.isFinite(Number(reference.excessMortalityRate))
	                    ? harmsPrevented * Number(reference.excessMortalityRate)
	                    : null;

	                return {
	                    measure,
	                    ready: true,
	                    baseline,
	                    current,
	                    targetRatio,
	                    reductionGoal,
	                    harmsPrevented,
	                    costAvoided,
	                    livesSaved,
	                    reference
	                };
	            });
	        },

	        improvementChartValueLabel(value, unit = '', decimals = 1) {
            if (!Number.isFinite(value)) return '-';
            const formatted = Number(value).toFixed(decimals).replace(/\.0+$/, '');
            return unit === '%' ? `${formatted}%` : formatted;
        },

        improvementCompactChartLabel(label) {
            const text = String(label || '').trim();
            const monthYear = text.match(/^([A-Za-z]{3,9})\s+(\d{4})$/);
            if (monthYear) {
                return `${monthYear[1].slice(0, 3)} '${monthYear[2].slice(-2)}`;
            }
            return text;
        },

        improvementYearOptions() {
            const currentYear = new Date().getFullYear();
            const years = [];
            for (let year = 2019; year <= currentYear + 1; year += 1) {
                years.push(String(year));
            }
            return years;
        },

        improvementReportYearOptions(calculator = null) {
            calculator = calculator || this.improvementCalculatorState();
            const years = new Set(this.improvementYearOptions());
            (calculator.submissions || []).forEach(submission => {
                const year = String(submission.reporting_year || '').trim();
                if (year) years.add(year);
            });
            return Array.from(years).sort((a, b) => Number(a) - Number(b));
        },

        renderImprovementCalculatorRunChart(calculator) {
            const scopedMeasureId = String(this.state.unifiedHacsMeasureId || '').trim();
            const config = this.improvementRunChartConfig(scopedMeasureId || calculator.runChartMetric);
            const selectedMetric = config.id;
            const points = this.improvementRunChartPoints(selectedMetric);
            const options = this.improvementRunChartOptions().map(option => `
                <option value="${this.escapeHtml(option.id)}" ${option.id === selectedMetric ? 'selected' : ''}>${this.escapeHtml(option.label)}</option>
            `).join('');
            const selectedYear = String(calculator.reportYearFilter || 'all');
            const yearOptions = [
                `<option value="all" ${selectedYear === 'all' ? 'selected' : ''}>All years</option>`,
                ...this.improvementReportYearOptions(calculator).map(year => `
                <option value="${year}" ${selectedYear === year ? 'selected' : ''}>${year}</option>`)
            ].join('');
            const rawDataTable = this.renderImprovementCalculatorRawDataTable(calculator, {
                measureId: selectedMetric,
                title: 'Raw Data'
            });

            return `
                <div data-improvement-chart-anchor="1">
                    ${scopedMeasureId ? '' : `
                    <div class="dm-guide" style="margin-top:28px;">
                        <i class="fas fa-info-circle"></i>
                        <b>View Report:</b> Select a harm area to trend the monthly rate from the data-entry table.
                    </div>

                    <div class="dm-row-actions top" style="align-items:flex-start; gap:18px; margin-top:24px;">
                        <label style="display:flex; flex-direction:column; gap:8px; font-weight:800; color:var(--dm-primary); min-width:320px;">
                            Measure
                            <select class="dm-year-select" onchange="dmApp.updateImprovementRunChartMetric(this.value)" style="width:360px; max-width:100%; height:44px; border:1px solid #d1d5db; padding:10px 14px; border-radius:8px; font-size:14px;">
                                ${options}
                            </select>
                        </label>
                        <label style="display:flex; flex-direction:column; gap:8px; font-weight:800; color:var(--dm-primary); min-width:180px;">
                            Reporting Year
                            <select class="dm-year-select" onchange="dmApp.updateImprovementRunChartYearFilter(this.value)" style="width:180px; height:44px; border:1px solid #d1d5db; padding:10px 14px; border-radius:8px; font-size:14px;">
                                ${yearOptions}
                            </select>
                        </label>
                    </div>
                    `}

                    ${points.length ? `
                        <div class="dm-raw-chart-card">
                            <div class="dm-raw-chart-head">
                                <h3 class="dm-raw-chart-title">${this.escapeHtml(config.title)}</h3>
                                <div class="dm-raw-chart-actions">
                                    <button type="button" class="dm-raw-chart-download dm-raw-chart-icon-btn" onclick="dmApp.copyImprovementRunChartImage(this)" aria-label="Copy chart image" title="Copy image">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button type="button" class="dm-raw-chart-download dm-raw-chart-icon-btn" onclick="dmApp.downloadImprovementRunChartJpeg()" aria-label="Download chart JPEG" title="Download JPEG">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="dm-raw-chart-canvas-wrap">
                                <canvas id="dmImprovementRunChart" class="dm-raw-chart-canvas"></canvas>
                            </div>
                        </div>
                    ` : `
                        <div class="dm-guide" style="margin-top:24px;">
                            Add numerator and denominator values in Build Report to generate this run chart.
                        </div>
                    `}
                    ${rawDataTable}
                </div>
            `;
        },

        scheduleImprovementCalculatorRunChart() {
            const calculator = this.improvementCalculatorState();
            if (!this.isImprovementCalculatorMeasure() || calculator.activeTab !== 'run-chart') {
                return;
            }
            window.requestAnimationFrame(() => this.drawImprovementRunChart());
        },

        drawImprovementRunChart(targetCanvas = null) {
            const canvas = targetCanvas || document.getElementById('dmImprovementRunChart');
            if (!canvas) return false;
            const calculator = this.improvementCalculatorState();
            const metricId = this.improvementRunChartConfig(this.state.unifiedHacsMeasureId || calculator.runChartMetric).id;
            const config = this.improvementRunChartConfig(metricId);
            const points = this.improvementRunChartPoints(metricId);
            if (!points.length) return false;

            const wrap = canvas.parentElement;
            const cssWidth = Math.max(520, targetCanvas ? 1100 : (wrap ? wrap.clientWidth : canvas.clientWidth || 900));
            const cssHeight = targetCanvas ? 620 : (wrap ? wrap.clientHeight : canvas.clientHeight || 320);
            const ratio = targetCanvas ? 2 : (window.devicePixelRatio || 1);
            canvas.width = Math.round(cssWidth * ratio);
            canvas.height = Math.round(cssHeight * ratio);
            canvas.style.width = `${cssWidth}px`;
            canvas.style.height = `${cssHeight}px`;

            const ctx = canvas.getContext('2d');
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            ctx.clearRect(0, 0, cssWidth, cssHeight);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, cssWidth, cssHeight);

            const pad = { top: 34, right: 34, bottom: 52, left: 64 };
            const plotW = cssWidth - pad.left - pad.right;
            const plotH = cssHeight - pad.top - pad.bottom;
            const values = points.map(point => point.value);
            const referenceValues = Number.isFinite(config.goalValue) ? [...values, config.goalValue] : values;
            const maxValue = Math.max(...referenceValues, 0);
            const isPercent = config.valueLabel === '%';
            const tickStep = isPercent ? 20 : Math.max(1, Math.ceil(maxValue / 5));
            const yMin = 0;
            const yMax = isPercent ? Math.max(100, Math.ceil(maxValue / tickStep) * tickStep) : Math.max(tickStep, Math.ceil((maxValue + tickStep) / tickStep) * tickStep);
            const yRange = Math.max(1, yMax - yMin);
            const xFor = (idx) => pad.left + (points.length === 1 ? plotW / 2 : (idx / (points.length - 1)) * plotW);
            const yFor = (value) => pad.top + ((yMax - value) / yRange) * plotH;

            ctx.strokeStyle = '#e5e7eb';
            ctx.lineWidth = 1;
            ctx.fillStyle = '#64748b';
            ctx.font = '13px Inter, system-ui, -apple-system, sans-serif';
            ctx.textAlign = 'right';
            ctx.textBaseline = 'middle';
            const tickCount = 5;
            for (let i = 0; i <= tickCount; i++) {
                const value = yMin + (yRange / tickCount) * i;
                const y = yFor(value);
                ctx.beginPath();
                ctx.moveTo(pad.left, y);
                ctx.lineTo(cssWidth - pad.right, y);
                ctx.stroke();
                ctx.fillText(this.improvementChartValueLabel(value, isPercent ? '%' : '', config.decimals), pad.left - 12, y);
            }

            ctx.strokeStyle = '#cbd5e1';
            ctx.beginPath();
            ctx.moveTo(pad.left, pad.top);
            ctx.lineTo(pad.left, cssHeight - pad.bottom);
            ctx.lineTo(cssWidth - pad.right, cssHeight - pad.bottom);
            ctx.stroke();

            const sortedValues = [...values].sort((a, b) => a - b);
            const middle = Math.floor(sortedValues.length / 2);
            const median = sortedValues.length % 2 ? sortedValues[middle] : (sortedValues[middle - 1] + sortedValues[middle]) / 2;
            const medianY = yFor(median);
            const labelX = cssWidth - pad.right - 8;
            ctx.strokeStyle = '#94a3b8';
            ctx.setLineDash([7, 7]);
            ctx.beginPath();
            ctx.moveTo(pad.left, medianY);
            ctx.lineTo(cssWidth - pad.right, medianY);
            ctx.stroke();
            ctx.setLineDash([]);
            ctx.fillStyle = '#64748b';
            ctx.textAlign = 'right';
            ctx.textBaseline = 'bottom';
            ctx.fillText(`Median ${this.improvementChartValueLabel(median, isPercent ? '%' : '', config.decimals)}`, labelX, medianY - 6);

            if (Number.isFinite(config.goalValue)) {
                const goalY = yFor(config.goalValue);
                ctx.strokeStyle = '#16a34a';
                ctx.setLineDash([4, 6]);
                ctx.beginPath();
                ctx.moveTo(pad.left, goalY);
                ctx.lineTo(cssWidth - pad.right, goalY);
                ctx.stroke();
                ctx.setLineDash([]);
                ctx.fillStyle = '#166534';
                ctx.textAlign = 'right';
                ctx.textBaseline = 'bottom';
                const goalLabelY = Math.abs(goalY - medianY) < 18 ? goalY + 22 : goalY - 6;
                ctx.fillText(config.goalLabel, labelX, goalLabelY);
            }

            ctx.strokeStyle = '#285a7d';
            ctx.lineWidth = 4;
            ctx.lineJoin = 'round';
            ctx.lineCap = 'round';
            ctx.beginPath();
            points.forEach((point, idx) => {
                const x = xFor(idx);
                const y = yFor(point.value);
                if (idx === 0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            });
            ctx.stroke();

            points.forEach((point, idx) => {
                const x = xFor(idx);
                const y = yFor(point.value);
                ctx.fillStyle = '#285a7d';
                ctx.beginPath();
                ctx.arc(x, y, 6, 0, Math.PI * 2);
                ctx.fill();
                ctx.strokeStyle = '#ffffff';
                ctx.lineWidth = 2;
                ctx.stroke();
            });

            const hitPoints = points.map((point, idx) => {
                const tooltipLines = [
                    point.label,
                    `${config.label || 'Value'}: ${this.improvementChartValueLabel(point.value, isPercent ? '%' : '', config.decimals)}`
                ];
                if (Number.isFinite(Number(point.numerator))) {
                    tooltipLines.push(`Numerator: ${point.numerator}`);
                }
                if (Number.isFinite(Number(point.denominator))) {
                    tooltipLines.push(`${point.denominatorLabel || 'Denominator'}: ${point.denominator}`);
                }
                return {
                    index: idx,
                    x: xFor(idx),
                    y: yFor(point.value),
                    tooltipLines
                };
            });
            if (!targetCanvas) {
                this._improvementRunChartHitPoints = hitPoints;
                this.bindChartPointTooltip(canvas, 'improvementRunChart', 'drawImprovementRunChart');
            }

            ctx.fillStyle = '#475569';
            ctx.font = '13px Inter, system-ui, -apple-system, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'top';
            const axisLabels = points.map(point => this.improvementCompactChartLabel(point.label));
            const widestAxisLabel = axisLabels.reduce((max, label) => Math.max(max, ctx.measureText(label).width), 0);
            const minimumLabelGap = widestAxisLabel + 18;
            const labelStep = points.length <= 12 ? 1 : Math.max(1, Math.ceil((points.length * minimumLabelGap) / Math.max(plotW, 1)));
            points.forEach((point, idx) => {
                const shouldDrawLabel = idx === 0 || idx === points.length - 1 || idx % labelStep === 0;
                if (!shouldDrawLabel) return;
                const x = xFor(idx);
                const label = axisLabels[idx] || point.label;
                const halfLabelWidth = ctx.measureText(label).width / 2;
                const labelX = Math.min(Math.max(x, halfLabelWidth + 4), cssWidth - halfLabelWidth - 4);
                ctx.fillText(label, labelX, cssHeight - pad.bottom + 16);
            });

            if (!targetCanvas && this._improvementRunChartHoverIndex !== null && this._improvementRunChartHoverIndex !== undefined) {
                const activePoint = hitPoints.find(point => point.index === this._improvementRunChartHoverIndex);
                if (activePoint) {
                    ctx.fillStyle = '#0f2f44';
                    ctx.beginPath();
                    ctx.arc(activePoint.x, activePoint.y, 8, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.strokeStyle = '#ffffff';
                    ctx.lineWidth = 2;
                    ctx.stroke();
                    this.drawChartPointTooltip(ctx, activePoint, cssWidth, cssHeight);
                }
            }

            if (targetCanvas) {
                ctx.fillStyle = '#0f2f44';
                ctx.font = '700 16px Inter, system-ui, -apple-system, sans-serif';
                ctx.textAlign = 'left';
                ctx.textBaseline = 'top';
                ctx.fillText(config.title || 'Run Chart', pad.left, 0);
            }
            return true;
        },

        async copyImprovementRunChartImage(button = null) {
            const exportCanvas = document.createElement('canvas');
            const didDraw = this.drawImprovementRunChart(exportCanvas);
            if (!didDraw) return;

            const icon = button ? button.querySelector('i') : null;
            const originalIconClass = icon ? icon.className : '';
            if (button) {
                button.disabled = true;
            }

            try {
                let copied = false;
                const ClipboardItemCtor = window.ClipboardItem;

                if (navigator.clipboard && typeof navigator.clipboard.write === 'function' && ClipboardItemCtor) {
                    try {
                        const blob = await this.canvasToBlob(exportCanvas, 'image/png');
                        await navigator.clipboard.write([
                            new ClipboardItemCtor({ 'image/png': blob })
                        ]);
                        copied = true;
                    } catch (err) {
                        copied = false;
                    }
                }

                if (!copied) {
                    copied = this.copyImageDataUrlViaSelection(exportCanvas.toDataURL('image/png'));
                }

                if (!copied) {
                    throw new Error('Image clipboard copy failed.');
                }

                if (button) {
                    button.classList.add('dm-copied');
                    button.setAttribute('aria-label', 'Chart image copied');
                    button.setAttribute('title', 'Copied');
                }
                if (icon) {
                    icon.className = 'fas fa-check';
                }
                this.showToast('Chart image copied.', 'fas fa-copy');
                setTimeout(() => {
                    if (button) {
                        button.classList.remove('dm-copied');
                        button.disabled = false;
                        button.setAttribute('aria-label', 'Copy chart image');
                        button.setAttribute('title', 'Copy image');
                    }
                    if (icon) {
                        icon.className = originalIconClass || 'fas fa-copy';
                    }
                }, 1600);
            } catch (err) {
                if (button) {
                    button.disabled = false;
                }
                if (icon) {
                    icon.className = originalIconClass || 'fas fa-copy';
                }
                this.showToast('Could not copy image. Please download the JPEG instead.', 'fas fa-exclamation-circle');
            }
        },

        downloadImprovementRunChartJpeg() {
            const exportCanvas = document.createElement('canvas');
            const didDraw = this.drawImprovementRunChart(exportCanvas);
            if (!didDraw) return;
            const calculator = this.improvementCalculatorState();
            const metric = this.improvementRunChartConfig(this.state.unifiedHacsMeasureId || calculator.runChartMetric);
            const slug = String(metric.title || 'improvement-run-chart')
                .replace(/[^a-z0-9]+/gi, '-')
                .replace(/^-|-$/g, '')
                .toLowerCase();
            const link = document.createElement('a');
            link.href = exportCanvas.toDataURL('image/jpeg', 0.92);
            link.download = `${slug}.jpg`;
            document.body.appendChild(link);
            link.click();
            link.remove();
        },

        renderImprovementCalculatorShellView(breadcrumb, measure) {
            const calculator = this.improvementCalculatorState();
            const scopedMeasureId = String(this.state.unifiedHacsMeasureId || '').trim();
            const visibleTabs = ['instructions', 'run-chart'];
            const activeTab = visibleTabs.includes(calculator.activeTab) ? calculator.activeTab : 'instructions';
            const referenceRows = Object.values(DM_IMPROVEMENT_CALCULATOR_REFERENCES).map(item => `
                <tr>
                    <td>${item.label}</td>
                    <td>${this.formatCurrency(item.costPerCase)}</td>
                    <td>${item.mortalityRate === null ? 'NA' : Number(item.mortalityRate).toFixed(2)}</td>
                    <td>${item.excessMortalityRate === null ? 'NA' : Number(item.excessMortalityRate).toFixed(2)}</td>
                </tr>
            `).join('');
            return `
                ${breadcrumb}
                <div class="dm-header">
                    ${this.renderReportTitle(measure)}
                    <p>Track adverse-event trends, baseline performance, improvement goals, and harms prevented.</p>
                </div>

                ${scopedMeasureId ? this.renderImprovementCalculatorInstructions(scopedMeasureId, activeTab) : `
                <div class="dm-tabs">
                    <div class="dm-tab ${activeTab === 'instructions' ? 'active' : ''}" onclick="dmApp.setImprovementCalculatorTab('instructions')">Build Report</div>
                    <div class="dm-tab ${activeTab === 'run-chart' ? 'active' : ''}" onclick="dmApp.setImprovementCalculatorTab('run-chart')">View Report</div>
                </div>

                ${activeTab === 'instructions' ? this.renderImprovementCalculatorInstructions(scopedMeasureId) : ''}
                ${activeTab === 'run-chart' ? this.renderImprovementCalculatorRunChart(calculator) : ''}
                `}
            `;
        },

        renderImprovementCalculatorInstructions(scopedMeasureId = '', activeTab = 'instructions') {
            const calculator = this.improvementCalculatorState();
            const isScopedMeasure = Boolean(String(scopedMeasureId || '').trim());
            const scopedActiveTab = ['instructions', 'run-chart'].includes(activeTab) ? activeTab : 'instructions';
            const measures = [
                {
                    measureId: 'readmissions',
                    name: '30-Day Unplanned Readmission Rate',
                    description: 'Measures the percentage of patients who are readmitted to any acute care hospital within 30 days of discharge for an unplanned reason.',
                    numerator: 'Number of unplanned inpatient readmissions to any acute care hospital within 30 days of the index discharge during the reporting period.',
                    denominator: 'All inpatient discharges (excluding deaths and transfers to acute care) during the reporting period.',
                    specifications: 'CMS Planned Readmission Algorithm v4.0 applied to exclude planned readmissions.',
                    exclusions: 'Deaths during the index hospitalization; planned readmissions (per CMS Planned Readmission Algorithm); transfers to acute care as primary disposition; patients without 30 days of follow-up enrollment.',
                    frequency: 'Monthly; Quarterly MBQIP submission; Annual HRRP (PPS)'
                },
                {
                    measureId: 'clabsi',
                    name: 'CLABSI Rate',
                    description: 'Measures the rate of central line-associated bloodstream infections.',
                    numerator: 'Number of NHSN-defined CLABSI events (lab-confirmed BSI with central line in place &gt;=2 days on the date of event or day before) during the reporting period.',
                    denominator: 'Total central line days.',
                    specifications: 'NHSN CLABSI surveillance protocol required. Rate displayed as numerator divided by denominator, expressed as a percentage. Denominator context: total central line days.',
                    exclusions: 'Peripheral IVs; community-acquired BSIs (cultures drawn in clinic/ED before admission); BSIs secondary to another confirmed infection site; umbilical catheters (tracked separately).',
                    frequency: 'Monthly NHSN entry; Quarterly SIR'
                },
                {
                    measureId: 'sepsis_mortality',
                    name: 'Sepsis Mortality',
                    description: 'Measures in-hospital deaths with hospital-onset sepsis / septic shock as a primary or secondary diagnosis.',
                    numerator: 'Number of in-hospital deaths due to hospital-onset severe sepsis/septic shock.',
                    denominator: 'Number of patients with hospital-onset severe sepsis/septic shock.',
                    specifications: 'AHRQ',
                    exclusions: 'N/A',
                    frequency: 'N/A'
                },
                {
                    measureId: 'cauti',
                    name: 'CAUTI Rate',
                    description: 'Measures the rate of urinary tract infections associated with indwelling urinary catheters.',
                    numerator: 'Number of NHSN-defined CAUTI events in patients with indwelling catheter in place &gt;2 calendar days on the date of the event.',
                    denominator: 'Total urinary catheter days.',
                    specifications: 'NHSN CAUTI surveillance protocol. Rate displayed as numerator divided by denominator, expressed as a percentage. Denominator context: total urinary catheter days.',
                    exclusions: 'Patients using intermittent catheterization; external (condom) catheters; infections present on admission; catheter in place &lt;=2 calendar days; pediatric patients &lt;18 in adult locations.',
                    frequency: 'Monthly NHSN entry; Quarterly SIR'
                },
                {
                    measureId: 'falls_with_injury',
                    name: 'Inpatient Falls with Injury Rate',
                    description: 'Measures the rate of patient falls with any injury occurring during an inpatient stay.',
                    numerator: 'Number of inpatient falls resulting in injury occurring during the reporting period.',
                    denominator: 'Total inpatient days.',
                    specifications: 'Rate displayed as numerator divided by denominator, expressed as a percentage. Denominator context: total inpatient days. Classify by NDNQI injury level: 1=no injury, 2=minor, 3=moderate, 4=major/death. Include all falls regardless of whether patient was restrained, in bed, or ambulating. Capture via event reporting and EHR. Distinguish hospital-acquired from community (on-admission) falls.',
                    exclusions: 'Falls occurring prior to inpatient admission (present on entry); falls in outpatient or ED areas (tracked separately); falls that cannot be confirmed as occurring during the inpatient admission.',
                    frequency: 'Monthly; Quarterly NDNQI submission if participating'
                },
                {
                    measureId: 'pressure_ulcers_3_plus',
                    name: 'Hospital-Acquired Pressure Injuries (HAPIs) — Stage 3+',
                    description: 'Measures the rate of pressure injuries (Stage 3 or greater) that develop or worsen during a hospital stay.',
                    numerator: 'Number of hospital-acquired pressure injuries Stage 3 or greater identified in admitted patients during the reporting period.',
                    denominator: 'Total inpatient days.',
                    specifications: "NPIAP staging definitions required. Skin assessment on admission to document community-acquired injuries (excluded). Structured skin bundle (Braden scale, repositioning, moisture management, nutrition). Rate displayed as numerator divided by denominator, expressed as a percentage. Denominator context: total discharges. Wound care nurse/team review for confirmation. Unstageable and DTI included per NPIAP guidance. Stage 3/4 and unstageable are CMS 'never events.'",
                    exclusions: 'Community-acquired pressure injuries present on admission and documented in initial skin assessment; Kennedy Terminal Ulcers (KTU) occurring in actively dying patients meeting specific criteria per NPIAP guidance; Pressure injuries confirmed as device-related but excluded per facility policy.',
                    frequency: 'Monthly; Quarterly NDNQI submission; Annual CMS HAC data'
                },
                {
                    measureId: 'c_diff',
                    name: 'C. difficile (CDI) Rate',
                    description: 'Measures the rate of Clostridioides difficile infections identified in hospitalized patients.',
                    numerator: 'Number of NHSN-defined facility-onset CDI events (positive laboratory test &gt;3 calendar days after admission) identified during the reporting period.',
                    denominator: 'Total inpatient days.',
                    specifications: 'NHSN CDI FacWideIN protocol. Rate displayed as numerator divided by denominator, expressed as a percentage. Denominator context: total inpatient days.',
                    exclusions: 'Community-onset CDI (positive test within 3 days of admission); duplicate specimens from same patient within 14 days; positive tests in outpatient settings.',
                    frequency: 'Monthly NHSN entry; Quarterly SIR'
                },
                {
                    measureId: 'mrsa',
                    name: 'MRSA Bacteremia Rate',
                    description: 'Measures the rate of MRSA bacteremia events in inpatients across the facility.',
                    numerator: 'Number of NHSN-defined MRSA bacteremia events (positive blood culture for MRSA) identified in inpatients during the reporting period.',
                    denominator: 'Total inpatient days.',
                    specifications: 'NHSN MRSA FacWideIN protocol. Rate displayed as numerator divided by denominator, expressed as a percentage. Denominator context: total inpatient days.',
                    exclusions: 'Community-onset MRSA bacteremia (positive culture within 3 days of admission) not healthcare-associated; duplicate MRSA isolates within 14 days; positive cultures from outpatient encounters.',
                    frequency: 'Monthly NHSN entry; Quarterly SIR'
                }
            ];

            const row = (label, value) => `
                <tr>
                    <th>${label}</th>
                    <td>${value || 'N/A'}</td>
                </tr>
            `;
            const visibleMeasures = scopedMeasureId
                ? measures.filter(measure => measure.measureId === scopedMeasureId)
                : measures;

            return `
                <div class="dm-measure-spec-stack">
                    ${visibleMeasures.map(measure => `
                    <section class="dm-measure-spec-section">
                        <div class="dm-measure-spec-section-head">
                            <h2 class="dm-measure-spec-section-title">${this.escapeHtml(measure.name)}</h2>
                            ${this.renderHacsHaisOwnershipControl(measure.measureId, measure.name)}
                        </div>
                        <table class="dm-measure-spec">
                            <thead>
                                <tr>
                                    <th>Measure name</th>
                                    <th>${this.escapeHtml(measure.name)}</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${row('Description', measure.description)}
                                ${row('Numerator', measure.numerator)}
                                ${row('Denominator', measure.denominator)}
                                ${row('Measure Specifications', measure.specifications)}
                                ${row('Exclusions', measure.exclusions)}
                                ${row('Reporting Frequency', measure.frequency)}
                            </tbody>
                        </table>
                        ${this.renderImprovementMeasureGoalCard(measure.measureId, measure.name)}
                        ${isScopedMeasure ? `
                            ${this.renderImprovementCalculatorMeasureTabs(scopedActiveTab)}
                            <div class="dm-input-pane ${scopedActiveTab === 'instructions' ? 'active' : ''}">
                                ${this.renderImprovementSingleMonthEntry(calculator, measure.measureId)}
                                <div style="margin-top:40px;">
                                    ${this.renderImprovementCalculatorSubmissionManagement(calculator, { showGuide: false })}
                                </div>
                            </div>
                            <div class="dm-input-pane ${scopedActiveTab === 'run-chart' ? 'active' : ''}">
                                ${this.renderImprovementCalculatorRunChart(calculator)}
                            </div>
                        ` : `
                        ${this.renderImprovementSingleMonthEntry(calculator, measure.measureId)}
                        `}
                    </section>
                    `).join('')}
                    ${isScopedMeasure ? '' : `<div style="margin-top:40px;">
                        ${this.renderImprovementCalculatorSubmissionManagement(calculator, { showGuide: false })}
                    </div>`}
                </div>
            `;
        },

        renderImprovementCalculatorMeasureTabs(activeTab = 'instructions') {
            return `
                <div class="dm-tabs" style="margin-top:28px;">
                    <div class="dm-tab ${activeTab === 'instructions' ? 'active' : ''}" onclick="dmApp.setImprovementCalculatorTab('instructions')">Build Report</div>
                    <div class="dm-tab ${activeTab === 'run-chart' ? 'active' : ''}" onclick="dmApp.setImprovementCalculatorTab('run-chart')">View Report</div>
                </div>
            `;
        },

        renderImprovementCalculatorImpactDashboard(calculator) {
            const impactRows = this.improvementImpactRows(calculator);
            const readyRows = impactRows.filter(row => row.ready);
            const totalHarmsPrevented = readyRows.reduce((sum, row) => sum + row.harmsPrevented, 0);
            const totalCostAvoided = readyRows.reduce((sum, row) => sum + row.costAvoided, 0);
            const totalLivesSaved = readyRows.reduce((sum, row) => {
                return Number.isFinite(row.livesSaved) ? sum + row.livesSaved : sum;
            }, 0);
            const readyCount = readyRows.length;
            const tableRows = readyRows.map(row => `
                <tr>
                    <td>${this.escapeHtml(row.measure.label)}</td>
                    <td>${this.escapeHtml(row.baseline.month)}</td>
                    <td>${this.escapeHtml(this.improvementRateDisplayForMeasure(row.measure, row.baseline.ratio))}</td>
                    <td>${this.escapeHtml(row.current.month)}</td>
                    <td>${this.escapeHtml(this.improvementRateDisplayForMeasure(row.measure, row.current.ratio))}</td>
                    <td>${this.escapeHtml(this.improvementRateDisplayForMeasure(row.measure, row.targetRatio))}</td>
                    <td>${this.escapeHtml(row.harmsPrevented.toFixed(1).replace(/\.0$/, ''))}</td>
                    <td>${this.escapeHtml(this.formatCurrency(row.costAvoided))}</td>
                    <td>${row.livesSaved === null ? 'N/A' : this.escapeHtml(row.livesSaved.toFixed(2).replace(/\.00$/, ''))}</td>
                </tr>
            `).join('');

            return `
                <div class="dm-guide" style="margin-top:28px;">
                    <i class="fas fa-info-circle"></i>
                    <b>Improvement:</b> Uses the first available month as baseline and the latest available month as current performance. Estimates are based on workbook cost and mortality reference values.
                </div>

                <div style="display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:14px; margin-top:24px;">
                    <div style="padding:18px; min-height:96px; border:1px solid var(--dm-border); border-radius:8px; background:#f8fafc;">
                        <b>Measures Ready</b>
                        <div style="font-size:28px; font-weight:900; color:var(--dm-primary); margin-top:8px;">${readyCount}</div>
                        <span style="color:var(--dm-text-muted); font-size:13px;">of ${DM_IMPROVEMENT_CALCULATOR_MEASURES.length}</span>
                    </div>
                    <div style="padding:18px; min-height:96px; border:1px solid var(--dm-border); border-radius:8px; background:#f8fafc;">
                        <b>Harms Prevented</b>
                        <div style="font-size:28px; font-weight:900; color:var(--dm-primary); margin-top:8px;">${this.escapeHtml(totalHarmsPrevented.toFixed(1).replace(/\.0$/, ''))}</div>
                        <span style="color:var(--dm-text-muted); font-size:13px;">baseline to current</span>
                    </div>
                    <div style="padding:18px; min-height:96px; border:1px solid var(--dm-border); border-radius:8px; background:#f8fafc;">
                        <b>Cost Avoided</b>
                        <div style="font-size:28px; font-weight:900; color:var(--dm-primary); margin-top:8px;">${this.escapeHtml(this.formatCurrency(totalCostAvoided))}</div>
                        <span style="color:var(--dm-text-muted); font-size:13px;">estimated</span>
                    </div>
                    <div style="padding:18px; min-height:96px; border:1px solid var(--dm-border); border-radius:8px; background:#f8fafc;">
                        <b>Lives Saved</b>
                        <div style="font-size:28px; font-weight:900; color:var(--dm-primary); margin-top:8px;">${this.escapeHtml(totalLivesSaved.toFixed(2).replace(/\.00$/, ''))}</div>
                        <span style="color:var(--dm-text-muted); font-size:13px;">where mortality references exist</span>
                    </div>
                </div>

                ${readyRows.length ? `
                    <div class="dm-table-wrap" style="margin-top:24px;">
                        <table class="dm-table">
                            <thead>
                                <tr>
                                    <th>Measure</th>
                                    <th>Baseline Month</th>
                                    <th>Baseline Rate</th>
                                    <th>Current Month</th>
                                    <th>Current Rate</th>
                                    <th>Goal Rate</th>
                                    <th>Harms Prevented</th>
                                    <th>Cost Avoided</th>
                                    <th>Lives Saved</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${tableRows}
                            </tbody>
                        </table>
                    </div>
                ` : `
                    <div class="dm-guide" style="margin-top:24px;">
                        <i class="fas fa-info-circle"></i>
                        Enter at least two months of numerator and denominator data for a measure to generate impact estimates.
                    </div>
                `}
            `;
        },

        renderImprovementCalculatorRawDataTable(calculator, options = {}) {
            const selectedMeasureId = String(options.measureId || '').trim();
            const activeSubmissions = (Array.isArray(calculator.submissions) ? calculator.submissions : [])
                .filter(item => String(item.status || 'active') !== 'archived');
            const allRows = this.improvementCalculatorRawRows(activeSubmissions);
            const rawRows = selectedMeasureId
                ? allRows.filter(row => String(row.measureId || '') === selectedMeasureId)
                : allRows;
            const title = options.title || 'Raw Data';
            const rawHtml = rawRows.length ? `
                <div class="dm-table-wrap" style="margin-top:18px;">
                    <table class="dm-table">
                        <thead>
                            <tr>
                                <th>Year</th>
                                <th>Reference Date</th>
                                <th>Month</th>
                                <th>Measure</th>
                                <th>Event Count</th>
                                <th>Denominator</th>
                                <th>Denominator Value</th>
                                <th>Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rawRows.map(row => `
                                <tr>
                                    <td>${this.escapeHtml(row.year || '—')}</td>
                                    <td>${this.escapeHtml(this.improvementSubmissionDate(row.referenceDate))}</td>
                                    <td>${this.escapeHtml(row.month || '—')}</td>
                                    <td>${this.escapeHtml(row.measure || '—')}</td>
                                    <td>${this.escapeHtml(row.eventValue === '' || row.eventValue == null ? '—' : row.eventValue)}</td>
                                    <td>${this.escapeHtml(row.denominator || '—')}</td>
                                    <td>${this.escapeHtml(row.denominatorValue === '' || row.denominatorValue == null ? '—' : row.denominatorValue)}</td>
                                    <td>${this.escapeHtml(this.improvementStoredRateDisplay(row.measureId, row.rateValue, row.eventValue, row.denominatorValue))}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            ` : `
                <div class="dm-guide" style="margin-top:18px;">
                    Raw data will appear here after HACs & HAIs submissions are saved.
                </div>
            `;

            return `
                <div style="margin-top:24px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin:0 0 12px; flex-wrap:wrap;">
                        <h3 style="margin:0; font-size:16px; font-weight:700; color:var(--dm-primary);">
                            ${this.escapeHtml(title)} (${rawRows.length})
                        </h3>
                    </div>
                    ${rawHtml}
                </div>
            `;
        },

        renderImprovementCalculatorSubmissionManagement(calculator, options = {}) {
            const includeRawData = options.includeRawData === true;
            const databaseTab = includeRawData && calculator.databaseTab === 'raw'
                ? 'raw'
                : (calculator.databaseTab === 'archive' ? 'archive' : 'saved');
            const showGuide = options.showGuide !== false;
            const scopedMeasureId = String(this.state.unifiedHacsMeasureId || '').trim();
            const filterScopedRows = rows => scopedMeasureId
                ? rows.filter(row => String(row.measureId || '') === scopedMeasureId)
                : rows;
            const submissions = Array.isArray(calculator.submissions) ? calculator.submissions : [];
            const activeSubmissions = submissions.filter(item => String(item.status || 'active') !== 'archived');
            const archivedSubmissions = submissions.filter(item => String(item.status || '') === 'archived');
            const currentList = databaseTab === 'archive' ? archivedSubmissions : activeSubmissions;
            const status = String(calculator.databaseStatus || '').trim();
            const sortSavedRows = (rows) => rows.sort((a, b) => {
                if (b.year !== a.year) return b.year - a.year;
                if (b.monthNum !== a.monthNum) return b.monthNum - a.monthNum;
                const aUpdated = Date.parse(a.updatedAt || '') || 0;
                const bUpdated = Date.parse(b.updatedAt || '') || 0;
                if (bUpdated !== aUpdated) return bUpdated - aUpdated;
                return String(a.measure).localeCompare(String(b.measure));
            });
            const savedRows = sortSavedRows(filterScopedRows(this.improvementCalculatorRawRows(activeSubmissions)));
            const archivedRows = sortSavedRows(filterScopedRows(this.improvementCalculatorRawRows(archivedSubmissions)));
            const savedRowsHtml = savedRows.length ? savedRows.map(row => {
                const title = `${row.measure} — ${row.month} ${row.year}`;
                const rate = this.improvementStoredRateDisplay(row.measureId, row.rateValue, row.eventValue, row.denominatorValue);
                return `
                    <li style="display:flex; align-items:center; gap:12px; padding:10px 14px; background:#fff; border:1px solid #e5e7eb; border-radius:8px;">
                        <i class="fas fa-file-csv" style="color:var(--dm-primary); font-size:18px;"></i>
                        <div style="flex:1; min-width:0;">
                            <div style="color:var(--dm-primary); font-weight:600; text-decoration:none; word-break:break-word;">${this.escapeHtml(title)}</div>
                            <div style="font-size:12px; color:var(--dm-text-muted); margin-top:2px;">
                                Numerator ${this.escapeHtml(row.eventValue === '' || row.eventValue == null ? '—' : row.eventValue)} · Denominator ${this.escapeHtml(row.denominatorValue === '' || row.denominatorValue == null ? '—' : row.denominatorValue)} · Rate ${this.escapeHtml(rate)} · Updated ${this.escapeHtml(this.improvementSubmissionDateTime(row.updatedAt))}
                            </div>
                        </div>
                        <button type="button"
                                title="View saved submission"
                                aria-label="View saved submission"
                                data-row-id="${this.escapeHtml(row.id)}"
                                onclick="dmApp.openImprovementSubmissionPreview(this.dataset.rowId)"
                                style="background:transparent; border:none; color:var(--dm-primary); cursor:pointer; padding:6px 8px; border-radius:6px; font-size:15px;"
                                onmouseover="this.style.background='#eef2ff';"
                                onmouseout="this.style.background='transparent';">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button"
                                title="Archive submission"
                                aria-label="Archive submission"
                                onclick="dmApp.archiveImprovementCalculatorSubmission(${Number(row.submissionId) || 0})"
                                style="background:transparent; border:none; color:#ef4444; cursor:pointer; padding:6px 8px; border-radius:6px; font-size:15px;"
                                onmouseover="this.style.background='#fef2f2';"
                                onmouseout="this.style.background='transparent';">
                            <i class="fas fa-trash"></i>
                        </button>
                    </li>
                `;
            }).join('') : `
                <div class="dm-guide" style="margin:0; min-height:88px; display:flex; align-items:center;">
                    ${scopedMeasureId ? '' : '<i class="fas fa-info-circle"></i>'}
                    ${scopedMeasureId ? 'No saved assessments for this measure yet.' : 'No saved calculator submissions yet.'}
                </div>
            `;
            const archivedRowsHtml = archivedRows.length ? archivedRows.map(row => {
                const title = `${row.measure} — ${row.month} ${row.year}`;
                const rate = this.improvementStoredRateDisplay(row.measureId, row.rateValue, row.eventValue, row.denominatorValue);
                return `
                    <li style="display:flex; align-items:center; gap:12px; padding:10px 14px; background:#fff; border:1px solid #e5e7eb; border-radius:8px;">
                        <i class="fas fa-file-csv" style="color:var(--dm-primary); font-size:18px;"></i>
                        <div style="flex:1; min-width:0;">
                            <div style="color:var(--dm-primary); font-weight:600; text-decoration:none; word-break:break-word;">${this.escapeHtml(title)}</div>
                            <div style="font-size:12px; color:var(--dm-text-muted); margin-top:2px;">
                                Numerator ${this.escapeHtml(row.eventValue === '' || row.eventValue == null ? '—' : row.eventValue)} · Denominator ${this.escapeHtml(row.denominatorValue === '' || row.denominatorValue == null ? '—' : row.denominatorValue)} · Rate ${this.escapeHtml(rate)} · Updated ${this.escapeHtml(this.improvementSubmissionDateTime(row.updatedAt))}
                            </div>
                        </div>
                        <button type="button"
                                title="Restore submission"
                                aria-label="Restore submission"
                                onclick="dmApp.restoreImprovementCalculatorSubmission(${Number(row.submissionId) || 0})"
                                style="background:transparent; border:none; color:var(--dm-primary); cursor:pointer; padding:6px 8px; border-radius:6px; font-size:15px;"
                                onmouseover="this.style.background='#eef2ff';"
                                onmouseout="this.style.background='transparent';">
                            <i class="fas fa-undo-alt"></i>
                        </button>
                    </li>
                `;
            }).join('') : `
                <div class="dm-guide" style="margin:0; min-height:88px; display:flex; align-items:center;">
                    ${scopedMeasureId ? '' : '<i class="fas fa-info-circle"></i>'}
                    ${scopedMeasureId ? 'No archived assessments for this measure yet.' : 'No archived calculator submissions yet.'}
                </div>
            `;
            const rowsHtml = currentList.length ? currentList.map(submission => {
                const year = this.escapeHtml(submission.reporting_year || '—');
                const referenceDate = this.escapeHtml(this.improvementSubmissionDate(submission.reference_date));
                const updatedAt = this.escapeHtml(this.improvementSubmissionDateTime(submission.updated_at));
                const title = `HACs & HAIs — ${year}`;
                return `
                    <li style="display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border:1px solid #e5e7eb; border-radius:8px;">
                        <i class="fas fa-database" style="color:var(--dm-primary); font-size:18px;"></i>
                        <div style="flex:1; min-width:0;">
                            <div style="color:var(--dm-primary); font-weight:800; font-size:16px;">${this.escapeHtml(title)}</div>
                            <div style="font-size:12px; color:var(--dm-text-muted); margin-top:4px;">
                                Year ${year} · Reference date ${referenceDate} · Updated ${updatedAt}
                            </div>
                        </div>
                        <button type="button"
                                title="Restore submission"
                                aria-label="Restore submission"
                                onclick="dmApp.restoreImprovementCalculatorSubmission(${Number(submission.id) || 0})"
                                style="background:transparent; border:none; color:var(--dm-primary); cursor:pointer; padding:6px 8px; border-radius:6px; font-size:15px;">
                            <i class="fas fa-undo-alt"></i>
                        </button>
                    </li>
                `;
            }).join('') : `
                <div class="dm-guide" style="margin-top:0;">
                    <i class="fas fa-info-circle"></i>
                    ${databaseTab === 'archive' ? 'No archived calculator submissions yet.' : 'No saved calculator submissions yet.'}
                </div>
            `;
            const assessmentListTitle = databaseTab === 'archive'
                ? `Archive (${scopedMeasureId ? archivedRows.length : currentList.length})`
                : `Saved assessments (${savedRows.length})`;
            let assessmentListHtml = savedRows.length
                ? `<ul style="list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:8px;">${savedRowsHtml}</ul>`
                : savedRowsHtml;
            if (databaseTab === 'archive') {
                assessmentListHtml = scopedMeasureId
                    ? (archivedRows.length ? `<ul style="list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:8px;">${archivedRowsHtml}</ul>` : archivedRowsHtml)
                    : (currentList.length ? `<ul style="list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:8px;">${rowsHtml}</ul>` : rowsHtml);
            }

            return `
                ${showGuide ? `
                <div class="dm-guide" style="margin-top:28px;">
                    <i class="fas fa-info-circle"></i>
                    <b>Saved assessments:</b> Review saved HACs & HAIs assessments or archive old entries.
                </div>
                ` : ''}

                ${status ? `<div class="dm-guide" style="margin-top:16px;"><i class="fas fa-info-circle"></i>${this.escapeHtml(status)}</div>` : ''}

                <div class="dm-tabs" style="margin-top:28px;">
                    <div class="dm-tab ${databaseTab === 'saved' ? 'active' : ''}" onclick="dmApp.setImprovementCalculatorDatabaseTab('saved')">Saved assessments</div>
                    <div class="dm-tab ${databaseTab === 'archive' ? 'active' : ''}" onclick="dmApp.setImprovementCalculatorDatabaseTab('archive')">Archive</div>
                    ${includeRawData ? `<div class="dm-tab ${databaseTab === 'raw' ? 'active' : ''}" onclick="dmApp.setImprovementCalculatorDatabaseTab('raw')">Raw data</div>` : ''}
                </div>

                ${databaseTab === 'raw' ? `
                    ${this.renderImprovementCalculatorRawDataTable(calculator)}
                ` : `
                    <div class="dm-saved-assessment-scroll">
                        <div class="dm-uploaded-files-list">
                            <h3 style="margin:0 0 12px; font-size:16px; font-weight:700; color:var(--dm-primary);">
                                ${assessmentListTitle}
                            </h3>
                            ${assessmentListHtml}
                        </div>
                    </div>
                `}
            `;
        },

        renderImprovementCalculatorDatabase(calculator) {
            return this.renderImprovementCalculatorSubmissionManagement(calculator, { showGuide: true });
        },

        renderImprovementCalculatorPlaceholder(title, description) {
            return `
                <div class="dm-guide" style="margin-top:28px;">
                    <i class="fas fa-info-circle"></i>
                    <b>${title}:</b> ${description} This will be wired after the monthly data-entry model is approved.
                </div>
            `;
        },

        renderImprovementCalculatorReferences(referenceRows) {
            return `
                <div class="dm-guide" style="margin-top:28px;">
                    <i class="fas fa-info-circle"></i>
                    <b>Cost and Mortality References:</b> These estimates are mapped from the workbook's Cost and Mortality References sheet.
                </div>

                <div class="dm-table-wrap" style="margin-top:28px;">
                    <table class="dm-table">
                        <thead>
                            <tr>
                                <th>Harm</th>
                                <th>Cost per Case</th>
                                <th>Mortality Rate</th>
                                <th>Excess Mortality Rate*</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${referenceRows}
                        </tbody>
                    </table>
                </div>

                <div class="dm-guide" style="margin-top:18px;">
                    <i class="fas fa-info-circle"></i>
                    *Excess mortality is the rate among HAC patients and is used for mortality estimates in this data tool.
                </div>

                <div style="margin-top:24px; color:var(--dm-primary);">
                    <h3 style="margin:0 0 12px; font-size:16px; font-weight:800;">References:</h3>
                    <p style="margin:0 0 12px; color:#334155; line-height:1.5;">
                        AHRQ National Scorecard on Hospital-Acquired Conditions Updated Baseline Rates and Preliminary Results 2014-2017<br>
                        <a href="https://www.ahrq.gov/hai/pfp/index.html" target="_blank" rel="noopener">https://www.ahrq.gov/hai/pfp/index.html</a>
                    </p>
                    <p style="margin:0 0 12px; color:#334155; line-height:1.5;">
                        Estimating the Additional Hospital Inpatient Cost and Mortality Associated With Selected Hospital-Acquired Conditions<br>
                        <a href="https://www.ahrq.gov/sites/default/files/wysiwyg/professionals/quality-patient-safety/pfp/hac-cost-report2017.pdf" target="_blank" rel="noopener">https://www.ahrq.gov/sites/default/files/wysiwyg/professionals/quality-patient-safety/pfp/hac-cost-report2017.pdf</a>
                    </p>
                    <p style="margin:0 0 12px; color:#334155; line-height:1.5;">
                        Overview of Clinical Conditions With Frequent and Costly Hospital Readmissions by Payer, 2018: Statistical Brief #278<br>
                        <a href="https://www.hcup-us.ahrq.gov/reports/statbriefs/sb278-Conditions-Frequent-Readmissions-By-Payer-2018.jsp" target="_blank" rel="noopener">https://www.hcup-us.ahrq.gov/reports/statbriefs/sb278-Conditions-Frequent-Readmissions-By-Payer-2018.jsp</a>
                    </p>
                    <p style="margin:0; color:#334155; line-height:1.5;">
                        Arefian H, Heublein S, Scherag A, Brunkhorst FM, Younis MZ, Moerer O, Fischer D, Hartmann M. Hospital-related cost of sepsis: A systematic review. J Infect. 2017 Feb;74(2):107-117.<br>
                        <a href="https://pubmed.ncbi.nlm.nih.gov/27884733/" target="_blank" rel="noopener">https://pubmed.ncbi.nlm.nih.gov/27884733/</a>
                    </p>
                </div>
            `;
        },

        renderImprovementSingleMonthEntry(calculator, measureId = '') {
            const lockedMeasureId = String(measureId || '').trim();
            const suffix = this.improvementSingleEntryDomSuffix(lockedMeasureId);
            const singleEntry = this.improvementSingleEntryState(calculator, lockedMeasureId);
            const singleEntryMeasures = this.improvementSingleEntryMeasureOptions();
            const selectedMeasure = singleEntryMeasures.find(measure => measure.id === singleEntry.numeratorKey) || singleEntryMeasures[0];
            const denominatorChoices = this.improvementDenominatorOptionsForMeasure(selectedMeasure);
            const denominatorLocked = lockedMeasureId || denominatorChoices.length === 1;
            const selectedDenominatorKey = singleEntry.denominatorKey || (selectedMeasure ? selectedMeasure.denominatorKey : '');
            const selectedDenominator = denominatorChoices.find(denominator => denominator.id === selectedDenominatorKey)
                || DM_IMPROVEMENT_CALCULATOR_DENOMINATORS.find(denominator => denominator.id === selectedDenominatorKey);
            const yearOptions = this.improvementYearOptions().map(year => `
                <option value="${year}" ${String(singleEntry.year) === year ? 'selected' : ''}>${year}</option>
            `).join('');
            const singleMonthOptions = this.monthOptions().map(month => `
                <option value="${month}" ${singleEntry.month === month ? 'selected' : ''}>${month}</option>
            `).join('');
            const numeratorOptions = singleEntryMeasures.map(measure => `
                <option value="${measure.id}" ${singleEntry.numeratorKey === measure.id ? 'selected' : ''}>${this.escapeHtml(measure.label)}</option>
            `).join('');
            const denominatorOptions = denominatorChoices.length ? denominatorChoices.map(denominator => `
                <option value="${this.escapeHtml(denominator.id)}" ${selectedDenominatorKey === denominator.id ? 'selected' : ''}>${this.escapeHtml(denominator.label)}</option>
            `).join('') : '<option value="">Choose numerator first</option>';
            const singleEntryRateDisplay = this.improvementSingleEntryRateDisplay(singleEntry, selectedMeasure);
            const singleEntryHasInversion = this.improvementSingleEntryHasNumDenInversion(singleEntry);
            const singleEntryCanSave = this.improvementSingleEntryCanSave(singleEntry);
            const jsMeasureId = lockedMeasureId.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
            const fieldArg = lockedMeasureId ? `, '${jsMeasureId}'` : '';
            const measureLabel = selectedMeasure ? selectedMeasure.label : 'this measure';
            const isScopedMeasureLayout = !!lockedMeasureId;
            const denominatorHelper = (selectedDenominator && selectedDenominator.label) || (selectedMeasure && selectedMeasure.denominatorLabel) || 'the selected denominator';

            return `
                ${isScopedMeasureLayout ? '' : `
                <section class="dm-guide" style="margin-top:18px;">
                    <i class="fas fa-info-circle"></i>
                    <b>Single Month Entry:</b> Choose one year and month, then enter ${this.escapeHtml(measureLabel)} numerator and denominator data.
                </section>
                `}
                <div class="dm-manual-section" style="margin-top:${isScopedMeasureLayout ? '0' : '18px'};">
                    <div class="dm-entry-section-title" style="margin-top:0;">${isScopedMeasureLayout ? 'Build Report' : 'Manual Entry'}</div>
                    <div class="dm-measure-goals-grid dm-improvement-single-grid ${lockedMeasureId ? 'is-measure-scoped' : ''}">
                        <div class="dm-goal-field">
                            <label>Year</label>
                            <select class="dm-year-select" onchange="dmApp.updateImprovementSingleEntryField('year', this.value${fieldArg})">
                                ${yearOptions}
                            </select>
                        </div>
                        <div class="dm-goal-field">
                            <label>Month</label>
                            <select class="dm-year-select" onchange="dmApp.updateImprovementSingleEntryField('month', this.value${fieldArg})">
                                ${singleMonthOptions}
                            </select>
                        </div>
                        ${lockedMeasureId ? '' : `
                        <div class="dm-goal-field">
                            <label>Numerator</label>
                            <select class="dm-year-select" onchange="dmApp.updateImprovementSingleEntryField('numeratorKey', this.value)">
                                ${numeratorOptions}
                            </select>
                        </div>
                        `}
                        <div class="dm-goal-field">
                            <label>Numerator Value</label>
                            <input type="number" min="0" step="1" value="${this.escapeHtml(singleEntry.numeratorValue)}" oninput="dmApp.updateImprovementSingleEntryField('numeratorValue', this.value${fieldArg})" placeholder="0">
                        </div>
                        ${lockedMeasureId ? '' : `<div class="dm-goal-field">
                            <label>Denominator</label>
                            <select class="dm-year-select" onchange="dmApp.updateImprovementSingleEntryField('denominatorKey', this.value${fieldArg})" ${denominatorLocked ? 'disabled aria-disabled="true"' : ''} style="${denominatorLocked ? 'background:#f1f5f9; color:#64748b; cursor:not-allowed;' : ''}">
                                ${denominatorOptions}
                            </select>
                        </div>`}
                        <div class="dm-goal-field">
                            <label class="${isScopedMeasureLayout ? 'dm-field-label-inline' : ''}">
                                Denominator Value
                                ${isScopedMeasureLayout ? `<span class="dm-field-tooltip" tabindex="0" aria-label="${this.escapeHtml(measureLabel)} uses ${this.escapeHtml(denominatorHelper)}." data-tooltip="${this.escapeHtml(measureLabel)} uses ${this.escapeHtml(denominatorHelper)}."><i class="fas fa-info"></i></span>` : ''}
                            </label>
                            <input type="number" min="0" step="1" value="${this.escapeHtml(singleEntry.denominatorValue)}" oninput="dmApp.updateImprovementSingleEntryField('denominatorValue', this.value${fieldArg})" placeholder="0">
                        </div>
                        <div id="dmImprovementSingleEntryRateField${suffix}" class="dm-goal-field dm-improvement-rate-field">
                            <label>${isScopedMeasureLayout ? 'Rate (%)' : 'Rate'}</label>
                            <input id="dmImprovementSingleEntryRate${suffix}" type="text" readonly value="${this.escapeHtml(singleEntryRateDisplay)}">
                            ${singleEntryHasInversion ? this.renderNumDenWarning({ num: singleEntry.numeratorValue, den: singleEntry.denominatorValue }) : ''}
                        </div>
                    </div>
                    <div class="dm-row-actions" style="justify-content:flex-end; align-items:center; gap:20px; margin-top:${isScopedMeasureLayout ? '16px' : '20px'};">
                        <div id="dmImprovementSaveNote${suffix}" class="dm-save-note" style="color:#64748b; font-size:14px; font-weight:700;"></div>
                        <button type="button" id="dmImprovementSaveBtn${suffix}" class="dm-btn" style="padding: 14px 40px; font-size: 15px; background: #03283E;" onclick="dmApp.saveImprovementSingleMonthData(event${fieldArg})" ${singleEntryCanSave ? '' : 'disabled'}>
                            <i class="fas fa-cloud-upload-alt"></i> Save & Sync Calculator Data
                        </button>
                    </div>
                    ${isScopedMeasureLayout ? '' : `
                    <div class="dm-guide" style="margin-top:20px;">
                        <i class="fas fa-info-circle"></i>
                        <b>Denominator rule:</b> ${this.escapeHtml(measureLabel)} uses ${this.escapeHtml(denominatorHelper)}.
                    </div>
                    `}
                </div>
            `;
        },

	        renderImprovementCalculatorDataEntry(calculator) {
            const yearOptions = this.improvementYearOptions().map(year => `
                <option value="${year}" ${String(calculator.selectedYear) === year ? 'selected' : ''}>${year}</option>
            `).join('');
	            const organizationName = this.currentOrganizationName();
	            const hasData = this.improvementCalculatorHasData(calculator);
	            const saveStatus = String(calculator.saveStatus || '').trim();
	            const entryMode = 'single';
	            const singleEntry = this.improvementSingleEntryState(calculator);
	            const singleEntryMeasures = this.improvementSingleEntryMeasureOptions();
	            const selectedMeasure = singleEntryMeasures.find(measure => measure.id === singleEntry.numeratorKey) || singleEntryMeasures[0];
	            const denominatorChoices = this.improvementDenominatorOptionsForMeasure(selectedMeasure);
	            const denominatorLocked = denominatorChoices.length === 1;
	            const selectedDenominatorKey = singleEntry.denominatorKey || (selectedMeasure ? selectedMeasure.denominatorKey : '');
	            const selectedDenominator = denominatorChoices.find(denominator => denominator.id === selectedDenominatorKey)
	                || DM_IMPROVEMENT_CALCULATOR_DENOMINATORS.find(denominator => denominator.id === selectedDenominatorKey);
	            const singleMonthOptions = this.monthOptions().map(month => `
	                <option value="${month}" ${singleEntry.month === month ? 'selected' : ''}>${month}</option>
	            `).join('');
            const numeratorOptions = singleEntryMeasures.map(measure => `
	                <option value="${measure.id}" ${singleEntry.numeratorKey === measure.id ? 'selected' : ''}>${this.escapeHtml(measure.label)}</option>
	            `).join('');
	            const denominatorOptions = denominatorChoices.length ? denominatorChoices.map(denominator => `
	                <option value="${this.escapeHtml(denominator.id)}" ${selectedDenominatorKey === denominator.id ? 'selected' : ''}>${this.escapeHtml(denominator.label)}</option>
	            `).join('') : '<option value="">Choose numerator first</option>';
	            const singleEntryRateDisplay = this.improvementSingleEntryRateDisplay(singleEntry, selectedMeasure);
	            const singleEntryHasInversion = this.improvementSingleEntryHasNumDenInversion(singleEntry);
	            const singleEntryCanSave = this.improvementSingleEntryCanSave(singleEntry);
	            const eventHeaders = DM_IMPROVEMENT_CALCULATOR_MEASURES.map(measure => `<th>${this.escapeHtml(measure.label)}</th>`).join('');
            const denominatorHeaders = DM_IMPROVEMENT_CALCULATOR_DENOMINATORS.map(denominator => `<th>${this.escapeHtml(denominator.label)}</th>`).join('');
            const rows = (calculator.monthlyRows || this.defaultImprovementMonthlyRows(calculator.selectedYear || new Date().getFullYear())).map((row, index) => {
                const totalHarms = this.improvementTotalHarms(row);
                const totalHarmsRate = this.improvementTotalHarmsRate(row);
                const eventCells = DM_IMPROVEMENT_CALCULATOR_MEASURES.map(measure => `
                    <td>
                        <input type="number" min="0" step="1" value="${this.escapeHtml((row.events || {})[measure.id] || '')}" oninput="dmApp.updateImprovementMonthlyValue(${index}, 'events', '${measure.id}', this.value)" placeholder="0">
                    </td>
                `).join('');
                const denominatorCells = DM_IMPROVEMENT_CALCULATOR_DENOMINATORS.map(denominator => `
                    <td>
                        <input type="number" min="0" step="1" value="${this.escapeHtml((row.denominators || {})[denominator.id] || '')}" oninput="dmApp.updateImprovementMonthlyValue(${index}, 'denominators', '${denominator.id}', this.value)" placeholder="0">
                    </td>
                `).join('');
                return `
                    <tr>
                        <td><b>${this.escapeHtml(row.month)}</b></td>
                        ${eventCells}
                        ${denominatorCells}
                        <td class="dm-rate-cell" id="dmImprovementTotalHarms-${index}">${totalHarms}</td>
                        <td class="dm-rate-cell" id="dmImprovementTotalHarmsRate-${index}">${totalHarmsRate === '' ? '-' : totalHarmsRate.toFixed(1)}</td>
                    </tr>
                `;
            }).join('');

            return `
                <div class="dm-guide" style="margin-top:28px;">
                    <i class="fas fa-info-circle"></i>
                    <b>Build Report:</b> Enter one reporting year at a time. The table below captures the workbook's monthly event counts and opportunity denominators.
                </div>

                <div class="dm-row-actions top" style="align-items:flex-start; gap:18px; margin-top:28px;">
                    <label style="display:flex; flex-direction:column; gap:8px; font-weight:800; color:var(--dm-primary);">
                        Organization
                        <input type="text" value="${this.escapeHtml(organizationName || calculator.organizationName || '')}" readonly disabled placeholder="Hospital name" style="width:320px; border:1px solid #d1d5db; padding:10px 14px; border-radius:8px; font-size:14px; background:#f1f5f9; color:#64748b; cursor:not-allowed;">
                    </label>
                    <label style="display:flex; flex-direction:column; gap:8px; font-weight:800; color:var(--dm-primary);">
                        Reference Date
                        <input type="text" value="${this.escapeHtml(this.formatUsDate(calculator.referenceDate || ''))}" onchange="dmApp.updateImprovementReferenceDate(this.value)" placeholder="MM/DD/YYYY" style="width:180px; border:1px solid #d1d5db; padding:10px 14px; border-radius:8px; font-size:14px;">
                    </label>
                    <label style="display:flex; flex-direction:column; gap:8px; font-weight:800; color:var(--dm-primary);">
                        Reporting Year
                        <select class="dm-year-select" onchange="dmApp.updateImprovementCalculatorYear(this.value)" style="width:140px; height:44px; border:1px solid #d1d5db; padding:10px 14px; border-radius:8px; font-size:14px;">
                            ${yearOptions}
                        </select>
                    </label>
                </div>

                ${entryMode === 'single' ? `
                    <section class="dm-guide" style="margin-top:18px;">
                        <i class="fas fa-info-circle"></i>
                        <b>Single Month Entry:</b> Choose one month, then enter one numerator measure and its matching denominator.
                    </section>
                    <div class="dm-manual-section" style="margin-top:18px;">
                        <div class="dm-measure-goals-grid dm-improvement-single-grid">
                            <div class="dm-goal-field">
                                <label>Month</label>
                                <select class="dm-year-select" onchange="dmApp.updateImprovementSingleEntryField('month', this.value)">
                                    ${singleMonthOptions}
                                </select>
                            </div>
                            <div class="dm-goal-field">
                                <label>Numerator</label>
                                <select class="dm-year-select" onchange="dmApp.updateImprovementSingleEntryField('numeratorKey', this.value)">
                                    ${numeratorOptions}
                                </select>
                            </div>
                            <div class="dm-goal-field">
                                <label>Numerator Value</label>
                                <input type="number" min="0" step="1" value="${this.escapeHtml(singleEntry.numeratorValue)}" oninput="dmApp.updateImprovementSingleEntryField('numeratorValue', this.value)" placeholder="0">
                            </div>
                            <div class="dm-goal-field">
                                <label>Denominator</label>
                                <select class="dm-year-select" onchange="dmApp.updateImprovementSingleEntryField('denominatorKey', this.value)" ${denominatorLocked ? 'disabled aria-disabled="true"' : ''} style="${denominatorLocked ? 'background:#f1f5f9; color:#64748b; cursor:not-allowed;' : ''}">
                                    ${denominatorOptions}
                                </select>
                            </div>
                            <div class="dm-goal-field">
                                <label>Denominator Value</label>
                                <input type="number" min="0" step="1" value="${this.escapeHtml(singleEntry.denominatorValue)}" oninput="dmApp.updateImprovementSingleEntryField('denominatorValue', this.value)" placeholder="0">
                            </div>
                            <div id="dmImprovementSingleEntryRateField" class="dm-goal-field dm-improvement-rate-field">
                                <label>Rate</label>
                                <input id="dmImprovementSingleEntryRate" type="text" readonly value="${this.escapeHtml(singleEntryRateDisplay)}">
                                ${singleEntryHasInversion ? this.renderNumDenWarning({ num: singleEntry.numeratorValue, den: singleEntry.denominatorValue }) : ''}
                            </div>
                        </div>
                        <div class="dm-guide" style="margin-top:16px;">
                            <i class="fas fa-info-circle"></i>
                            <b>Denominator rule:</b> ${this.escapeHtml(selectedMeasure ? selectedMeasure.label : 'This measure')} uses ${this.escapeHtml((selectedDenominator && selectedDenominator.label) || (selectedMeasure && selectedMeasure.denominatorLabel) || 'the selected denominator')}.
                        </div>
                    </div>
                ` : `
	                <div class="dm-table-wrap" style="margin-top:18px;">
	                    <table class="dm-table dm-improvement-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                ${eventHeaders}
                                ${denominatorHeaders}
                                <th>Total Harms</th>
                                <th>Total Harms per 1,000 Pt Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows}
	                        </tbody>
	                    </table>
	                </div>
                `}

	                <div class="dm-row-actions" style="justify-content:flex-end; align-items:center; gap:20px; margin-top:28px;">
	                    <div id="dmImprovementSaveNote" class="dm-save-note" style="color:#64748b; font-size:14px; font-weight:700;">${saveStatus ? this.escapeHtml(saveStatus) : ''}</div>
	                    <button type="button" id="dmImprovementSaveBtn" class="dm-btn" style="padding: 14px 40px; font-size: 15px; background: #03283E;" onclick="${entryMode === 'single' ? 'dmApp.saveImprovementSingleMonthData(event)' : 'dmApp.saveImprovementCalculatorData(event)'}" ${entryMode === 'single' ? (singleEntryCanSave ? '' : 'disabled') : (hasData ? '' : 'disabled')}>
	                        <i class="fas fa-cloud-upload-alt"></i> Save & Sync Calculator Data
	                    </button>
	                </div>

                    <div style="margin-top:40px;">
                        ${this.renderImprovementCalculatorSubmissionManagement(calculator, { showGuide: false })}
                    </div>
	            `;
	        },

        setTab(tab) {
            const nextState = { inputTab: tab };
            if (tab === 'database') {
                nextState.assessmentListTab = 'saved';
            }
            this.setState(nextState, { scrollToTop: false });
            this.updateRouteUrl();
        },

        setAssessmentListTab(tab) {
            this.setState({ assessmentListTab: ['archive', 'raw'].includes(tab) ? tab : 'saved' }, { scrollToTop: false });
            this.updateRouteUrl({ replace: true });
        },

        monthOptions() {
            return ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        },

        quarterOptions() {
            return ['Q1','Q2','Q3','Q4'];
        },

        yearOptions() {
            // 2012 through the current year, newest first. The upper bound is
            // the live current year, so 2027 appears automatically in 2027.
            const now = new Date().getFullYear();
            const startYear = 2012;
            const years = [];
            for (let y = now; y >= startYear; y--) years.push(y);
            return years;
        },

        // Coerce an imported month value (e.g. "January", "01", "jan") into
        // the 3-letter abbreviation used by the dropdown, so it shows selected.
        normalizeMonth(raw) {
            const months = this.monthOptions();
            if (raw instanceof Date && !Number.isNaN(raw.getTime())) {
                return months[raw.getMonth()] || '';
            }
            const s = String(raw || '').trim().toLowerCase();
            if (!s) return '';
            if (/^\d+$/.test(s)) {
                const n = parseInt(s, 10);
                if (n >= 1 && n <= 12) return months[n - 1] || '';
                if (n > 31 && n < 60000) {
                    const date = new Date(Date.UTC(1899, 11, 30) + (n * 86400000));
                    return months[date.getUTCMonth()] || '';
                }
            }
            const isoDateMatch = s.match(/^[12][0-9]{3}[-\/](0?[1-9]|1[0-2])[-\/][0-3]?[0-9]/);
            if (isoDateMatch) {
                const n = parseInt(isoDateMatch[1], 10);
                return months[n - 1] || '';
            }
            const found = months.find(m => m.toLowerCase() === s.slice(0, 3));
            return found || '';
        },

        normalizeQuarter(raw) {
            const s = String(raw || '').trim().toLowerCase();
            if (!s) return '';
            if (/^q[1-4]$/.test(s)) return s.toUpperCase();
            if (/^[1-4]$/.test(s)) return `Q${s}`;
            const match = s.match(/quarter\s*([1-4])/);
            return match ? `Q${match[1]}` : '';
        },

        updateManualRow(index, field, value) {
            const rows = [...this.state.manualRows];
            const previousValue = rows[index] ? rows[index][field] : undefined;
            rows[index][field] = value;
            if (['year', 'month'].includes(field) && String(previousValue || '') !== String(value || '')) {
                rows[index] = {
                    ...rows[index],
                    num: '',
                    den: '',
                    ...(Object.prototype.hasOwnProperty.call(rows[index], 'median') ? { median: '' } : {})
                };
                this.setState({ manualRows: rows }, { preserveScroll: true, scrollToTop: false });
                return;
            }
            this.state.manualRows = rows; // Silent update then micro-render for rate
            this.renderRate(index);
            this.syncManualControls();
        },

        formatRatePercent(num, den) {
            const numText = String(num == null ? '' : num).trim();
            const denText = String(den == null ? '' : den).trim();
            if (numText === '' || denText === '') return '-';
            const numerator = Number(numText);
            const denominator = Number(denText);
            if (!Number.isFinite(numerator) || !Number.isFinite(denominator) || denominator <= 0) return '-';
            return ((numerator / denominator) * 100).toFixed(1) + '%';
        },

        hasNumDenInversion(row) {
            if (!row) return false;
            const numText = String(row.num == null ? '' : row.num).trim();
            const denText = String(row.den == null ? '' : row.den).trim();
            if (numText === '' || denText === '') return false;
            const numerator = Number(numText);
            const denominator = Number(denText);
            return Number.isFinite(numerator) && Number.isFinite(denominator) && denominator < numerator;
        },

        rowsHaveNumDenInversion(rows) {
            return (Array.isArray(rows) ? rows : []).some(row => this.hasNumDenInversion(row));
        },

        renderNumDenWarning(row) {
            if (!this.hasNumDenInversion(row)) return '';
            return '<span class="dm-den-warning-icon" aria-hidden="true"><i class="fas fa-exclamation-triangle"></i></span>';
        },

        renderManualRowAction(index) {
            const rows = this.state.manualRows || [];
            const isOnlyRow = rows.length <= 1;
            const icon = isOnlyRow ? 'fa-eraser' : 'fa-trash';
            const label = isOnlyRow ? 'Clear row' : 'Delete row';
            const action = isOnlyRow ? `dmApp.clearManualRow(${index})` : `dmApp.removeManualRow(${index})`;
            return `
                <td class="dm-row-action-cell">
                    <button type="button" class="dm-row-action-btn" onclick="${action}" title="${label}" aria-label="${label}">
                        <i class="fas ${icon}"></i>
                    </button>
                </td>
            `;
        },

        renderRate(index) {
            if (this.isQuarterMedianMeasure()) return;
            const row = this.state.manualRows[index];
            const tbody = document.getElementById('dmManualTbody');
            if (!tbody) return;
            const tr = tbody.children[index];
            if (!tr) return;
            const rateCell = tr.querySelector('.dm-rate-cell');
            if (!rateCell) return;
            rateCell.textContent = this.formatRatePercent(row.num, row.den);
            const denCell = tr.querySelector('.dm-den-cell');
            if (denCell) {
                const existingWarning = denCell.querySelector('.dm-den-warning-icon');
                if (existingWarning) existingWarning.remove();
                const warningHtml = this.renderNumDenWarning(row);
                if (warningHtml) denCell.insertAdjacentHTML('beforeend', warningHtml);
            }
        },

        blankManualRowLike(row = {}) {
            return {
                month: '',
                year: '',
                num: '',
                den: '',
                ...(Object.prototype.hasOwnProperty.call(row, 'median') ? { median: '' } : {})
            };
        },

        clearManualRow(index) {
            const rows = [...(this.state.manualRows || [])];
            rows[index] = this.blankManualRowLike(rows[index]);
            this.setState({ manualRows: rows.length ? rows : [this.blankManualRowLike()] }, { preserveScroll: true, scrollToTop: false });
        },

        removeManualRow(index) {
            const currentRows = this.state.manualRows || [];
            if (currentRows.length <= 1) {
                this.clearManualRow(0);
                return;
            }
            const rows = currentRows.filter((_, i) => i !== index);
            this.setState({ manualRows: rows }, { preserveScroll: true, scrollToTop: false });
        },

        // A row counts as empty when every field is blank. A num/den of 0 is
        // treated as real data (a pasted blank cell becomes 0).
        isRowEmpty(row) {
            if (!row) return true;
            return ['month', 'year', 'num', 'den', 'median']
                .every(k => String(row[k] == null ? '' : row[k]).trim() === '');
        },

        validManualRows() {
            return (this.state.manualRows || []).filter(r => !this.isRowEmpty(r));
        },

        annualPeriodRateYear(rows) {
            rows = Array.isArray(rows) ? rows : this.validManualRows();
            const years = [...new Set(rows.map(r => String(r.year || '').trim()).filter(Boolean))];
            return years.length === 1 ? years[0] : '';
        },

        periodRatePeriod(rows) {
            rows = Array.isArray(rows) ? rows : this.validManualRows();
            const months = [...new Set(rows.map(r => String(r.month || '').trim()).filter(Boolean))];
            const years = [...new Set(rows.map(r => String(r.year || '').trim()).filter(Boolean))];
            return {
                month: months.length === 1 ? months[0] : '',
                year: years.length === 1 ? years[0] : ''
            };
        },

        // True when at least one row holds real data — gates Save & Sync.
        hasManualData() {
            if (this.isEdtcMeasure()) {
                const summary = this.edtcNumDenSummary();
                const compositeSummary = this.edtcCompositeStateSummary();
                return !!String(this.state.checklistYear || '').trim()
                    && !!this.normalizeQuarter(this.state.checklistQuarter)
                    && summary.complete
                    && compositeSummary.complete;
            }
            if (this.isGlobalInfrastructureMeasure() || this.isAntibioticStewardshipMeasure()) {
                const summary = this.globalChecklistSummary();
                const hasPeriod = !!String(this.state.checklistYear || '').trim();
                return hasPeriod && summary.complete;
            }
            if (this.isHcpInfluenzaMeasure() || this.isOp22Measure()) {
                const row = (this.state.manualRows || [])[0] || {};
                return !!String(row.year || '').trim()
                    && String(row.num || '').trim() !== ''
                    && String(row.den || '').trim() !== ''
                    && Number(row.den) > 0
                    && !this.hasNumDenInversion(row);
            }
            if (this.isHwrMeasure()) {
                const row = (this.state.manualRows || [])[0] || {};
                return !!String(row.year || '').trim()
                    && !!String(row.month || '').trim()
                    && String(row.num || '').trim() !== ''
                    && String(row.den || '').trim() !== ''
                    && Number(row.den) > 0
                    && !this.hasNumDenInversion(row);
            }
            if (this.isMonthlyRateMeasure()) {
                const rows = this.validManualRows();
                const period = this.periodRatePeriod(rows);
                return rows.length > 0
                    && !!period.month
                    && !!period.year
                    && rows.every(row =>
                        !!String(row.month || '').trim()
                        && !!String(row.year || '').trim()
                        && String(row.num || '').trim() !== ''
                        && String(row.den || '').trim() !== ''
                        && Number(row.den) > 0
                        && !this.hasNumDenInversion(row)
                    );
            }
            if (this.isQuarterRateMeasure()) {
                const row = (this.state.manualRows || [])[0] || {};
                return !!String(row.year || '').trim()
                    && !!String(row.month || '').trim()
                    && String(row.num || '').trim() !== ''
                    && String(row.den || '').trim() !== ''
                    && Number(row.den) > 0
                    && !this.hasNumDenInversion(row);
            }
            if (this.isQuarterMedianMeasure()) {
                const row = (this.state.manualRows || [])[0] || {};
                return !!String(row.year || '').trim()
                    && !!String(row.month || '').trim()
                    && String(row.median || '').trim() !== ''
                    && Number(row.median) >= 0;
            }
            return (this.state.manualRows || []).some(r => !this.isRowEmpty(r));
        },

        // updateManualRow does a silent update (no full re-render, to keep
        // input focus), so toggle the row-dependent controls by hand.
        syncManualControls() {

            const hasData = this.hasManualData();
            const saveBtn = document.getElementById('dmSaveBtn');
            if (saveBtn) saveBtn.disabled = !hasData;
            const note = document.getElementById('dmSaveNote');
            if (note) note.style.display = hasData ? 'none' : 'inline-flex';
        },

        renderEdtcRate(index) {
            const row = (this.state.checklistRows || [])[index];
            const tbody = document.getElementById('dmEdtcChecklistTbody');
            if (!row || !tbody) return;
            const tr = tbody.children[index];
            if (!tr) return;
            const rateCell = tr.querySelector('.dm-rate-cell');
            if (rateCell) {
                rateCell.textContent = this.formatRatePercent(row.num, row.den);
            }
            const denCell = tr.querySelector('.dm-den-cell');
            if (denCell) {
                const existingWarning = denCell.querySelector('.dm-den-warning-icon');
                if (existingWarning) existingWarning.remove();
                const warningHtml = this.renderNumDenWarning(row);
                if (warningHtml) denCell.insertAdjacentHTML('beforeend', warningHtml);
            }
        },

        syncEdtcControls() {
            const compositeSummary = this.edtcCompositeStateSummary();
            const elementSummary = this.edtcNumDenSummary();

            const compositeRateInput = document.getElementById('dmEdtcCompositeRate');
            if (compositeRateInput) compositeRateInput.value = `${compositeSummary.rate}%`;

            const compositeDenCell = document.getElementById('dmEdtcCompositeDenCell');
            if (compositeDenCell) {
                const existingWarning = compositeDenCell.querySelector('.dm-den-warning-icon');
                if (existingWarning) existingWarning.remove();
                const warningHtml = this.renderNumDenWarning({
                    num: this.state.edtcCompositeNum,
                    den: this.state.edtcCompositeDen
                });
                if (warningHtml) compositeDenCell.insertAdjacentHTML('beforeend', warningHtml);
            }

            const compositeNumSummary = document.getElementById('dmEdtcCompositeSummaryNum');
            if (compositeNumSummary) compositeNumSummary.textContent = compositeSummary.num;
            const compositeDenSummary = document.getElementById('dmEdtcCompositeSummaryDen');
            if (compositeDenSummary) compositeDenSummary.textContent = compositeSummary.den;
            const compositeRateSummary = document.getElementById('dmEdtcCompositeSummaryRate');
            if (compositeRateSummary) compositeRateSummary.textContent = `${compositeSummary.rate}%`;
            const elementCompleteSummary = document.getElementById('dmEdtcElementSummaryComplete');
            if (elementCompleteSummary) elementCompleteSummary.textContent = elementSummary.completeRows;
            const elementTotalSummary = document.getElementById('dmEdtcElementSummaryTotal');
            if (elementTotalSummary) elementTotalSummary.textContent = elementSummary.total;

            const hasData = this.hasManualData();
            const saveBtn = document.getElementById('dmSaveBtn');
            if (saveBtn) saveBtn.disabled = !hasData;
            const note = document.getElementById('dmSaveNote');
            if (note) note.style.display = hasData ? 'none' : 'inline-flex';
        },

        toggleMeasureSpecView(key, view) {
            const selectedView = ['tiles', 'trend', 'table'].includes(view) ? view : 'tiles';
            const views = {
                tiles: document.getElementById(`dmSpecTiles-${key}`),
                trend: document.getElementById(`dmSpecTrend-${key}`),
                table: document.getElementById(`dmSpecTable-${key}`)
            };
            Object.entries(views).forEach(([viewName, element]) => {
                if (!element) return;
                const display = viewName === 'table' ? 'table' : (viewName === 'tiles' ? 'grid' : 'block');
                element.style.display = viewName === selectedView ? display : 'none';
            });
            document.querySelectorAll(`[data-spec-key="${key}"][data-spec-view]`).forEach(button => {
                button.classList.toggle('active', button.getAttribute('data-spec-view') === selectedView);
            });
        },

        // Build a CSV of the rows from the last successful Save & Sync and
        // hand it to the browser. This exports the actual entered data — not
        // a blank template — and the button only appears after a save.
        downloadSavedCsv() {
            const measure = this.state.currentMeasure || 'data';
            if (this.isGlobalInfrastructureMeasure() || this.isAntibioticStewardshipMeasure() || this.isEdtcMeasure()) {
                const rows = (Array.isArray(this.state.lastSavedRows) ? this.state.lastSavedRows : []);
                if (!rows.length) return;
                const row = rows[0];
                const components = row.components || {};
                const componentList = this.checklistComponentsForCurrentMeasure();
                const csvCell = (v) => {
                    const s = String(v == null ? '' : v);
                    return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
                };
                const componentHeaders = this.isAntibioticStewardshipMeasure()
                    ? componentList.map(component => `${component} Met`)
                    : (this.isEdtcMeasure()
                        ? componentList.flatMap(component => [`${component} Num`, `${component} Denom`, `${component} Rate`])
                        : componentList.map(component => `${component} Criteria Met`));
                const header = this.isAntibioticStewardshipMeasure()
                    ? ['Metric', 'Year', 'Core Elements Met Count', 'Core Elements Count', 'Rate', 'Improved From Previous Year', ...componentHeaders]
                    : (this.isEdtcMeasure()
                    ? ['Metric', 'Year', 'Quarter', 'Numerator', 'Denominator', 'Rate', 'Credit for Measure', ...componentHeaders]
                    : ['Metric', 'Year', 'Criteria Met Count', 'Criteria Count', 'Rate', 'Credit for Measure', ...componentHeaders]);
                const line = [
                    measure,
                    row.year,
                    ...(this.isEdtcMeasure() ? [row.month || this.state.checklistQuarter || ''] : []),
                    row.elements_met_count,
                    row.elements_selected_count,
                    row.rate,
                    row.credit,
                    ...(this.isEdtcMeasure() ? componentList.flatMap(component => {
                        const data = components[component] || {};
                        return [data.num || '', data.den || '', data.rate || ''];
                    }) : this.isAntibioticStewardshipMeasure() ? componentList.map(component => {
                        const data = components[component] || {};
                        return data.met || '';
                    }) : componentList.map(component => {
                        const data = components[component] || {};
                        return data.met || '';
                    }))
                ];
                const blob = new Blob([header.map(csvCell).join(',') + '\n' + line.map(csvCell).join(',')], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.setAttribute('href', url);
                a.setAttribute('download', this.isAntibioticStewardshipMeasure() ? 'antibiotic_stewardship.csv' : (this.isEdtcMeasure() ? 'edtc_emergency_department_transfer_communication.csv' : 'cah_quality_infrastructure_assessment.csv'));
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                return;
            }
            const rows = (Array.isArray(this.state.lastSavedRows) ? this.state.lastSavedRows : [])
                .filter(r => r && (r.month || r.year || String(r.num || '').trim() || String(r.den || '').trim() || String(r.median || '').trim()));
            const csvCell = (v) => {
                const s = String(v == null ? '' : v);
                return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
            };
            if (this.isQuarterMedianMeasure()) {
                const header = 'Metric,Year,Quarter,Median Minutes';
                const lines = rows.map(r => [measure, r.year, r.month, r.median].map(csvCell).join(','));
                const blob = new Blob([header + '\n' + lines.join('\n')], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.setAttribute('href', url);
                a.setAttribute('download', `${measure.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.csv`);
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                return;
            }
            const periodHeader = this.isQuarterRateMeasure() ? 'Quarter' : 'Month';
            const header = `Metric,Year,${periodHeader},Num,Denom,Rate`;
            const lines = rows.map(r => {
                const formattedRate = this.formatRatePercent(r.num, r.den);
                const rate = formattedRate === '-' ? '' : formattedRate;
                return [measure, r.year, r.month, r.num, r.den, rate].map(csvCell).join(',');
            });
            const blob = new Blob([header + '\n' + lines.join('\n')], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('href', url);
            a.setAttribute('download', `${measure.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.csv`);
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        },

        showToast(message = 'Data saved successfully!', iconClass = 'fas fa-check-circle') {
            const toast = document.getElementById('dmToast');
            if (!toast) return;

            const icon = toast.querySelector('i');
            const text = toast.querySelector('span');
            if (icon) {
                icon.className = iconClass;
            }
            if (text) {
                text.textContent = message;
            }
            toast.classList.add('active');
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => {
                toast.classList.remove('active');
            }, 3000);
        },

        shareCurrentReportUrl() {
            const url = window.location.href;
            const fallbackCopy = () => {
                const textarea = document.createElement('textarea');
                textarea.value = url;
                textarea.setAttribute('readonly', '');
                textarea.style.position = 'fixed';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.select();
                let copied = false;
                try {
                    copied = document.execCommand('copy');
                } catch (err) {
                    copied = false;
                }
                document.body.removeChild(textarea);
                if (copied) {
                    this.showToast('Share URL copied to clipboard.', 'fas fa-link');
                } else {
                    window.prompt('Copy this report URL:', url);
                }
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url)
                    .then(() => this.showToast('Share URL copied to clipboard.', 'fas fa-link'))
                    .catch(fallbackCopy);
                return;
            }

            fallbackCopy();
        },

        applySuccessfulSave(data, savedRowsSnapshot, nextState = {}) {
            const measure = this.state.currentMeasure;
            const newFiles = { ...this.state.filesByMeasure };
            const existing = Array.isArray(newFiles[measure]) ? newFiles[measure] : [];
            const newRecord = (data.data && data.data.file) ? data.data.file : null;
            const replacedName = (data.data && data.data.replaced_file_name) ? data.data.replaced_file_name : '';
            const replacedNames = Array.isArray(data.data && data.data.replaced_file_names)
                ? data.data.replaced_file_names
                : (replacedName ? [replacedName] : []);
            const existingWithoutReplacement = replacedNames.length
                ? existing.filter(file => !replacedNames.includes(file.name || ''))
                : existing;
            const updatedList = newRecord ? [newRecord, ...existingWithoutReplacement] : existingWithoutReplacement;
            newFiles[measure] = updatedList;

            const newSaved = { ...this.state.savedMeasures };
            newSaved[measure] = updatedList.length || ((newSaved[measure] || 0) + 1);

            this.setState({
                savedMeasures: newSaved,
                filesByMeasure: newFiles,
                uploadedFileName: '',
                lastSavedRows: savedRowsSnapshot,
                ...nextState
            }, { preserveScroll: true, scrollToTop: false });
            this.notifyMetricsChanged();

            this.showToast();
            if (newRecord && String(newRecord.drive_sync_status || '') === 'pending') {
                this.pollDriveSyncStatus(newRecord.name, measure);
            }
        },

        pollDriveSyncStatus(fileName, measure, attempt = 1) {
            if (!fileName || attempt > 12) return;
            window.setTimeout(() => {
                const formData = new FormData();
                formData.append('action', 'dm_drive_sync_status');
                formData.append('nonce', DM_CONFIG.nonce);
                formData.append('file_name', fileName);
                fetch(DM_CONFIG.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    const record = data && data.success && data.data ? data.data.file : null;
                    if (!record) {
                        this.pollDriveSyncStatus(fileName, measure, attempt + 1);
                        return;
                    }
                    const newFiles = { ...this.state.filesByMeasure };
                    const existing = Array.isArray(newFiles[measure]) ? newFiles[measure] : [];
                    newFiles[measure] = existing.map(file => String(file.name || '') === String(fileName) ? { ...file, ...record } : file);
                    this.setState({ filesByMeasure: newFiles }, { preserveScroll: true, scrollToTop: false });
                    if (String(record.drive_sync_status || '') === 'pending') {
                        this.pollDriveSyncStatus(fileName, measure, attempt + 1);
                    }
                })
                .catch(() => {
                    this.pollDriveSyncStatus(fileName, measure, attempt + 1);
                });
            }, attempt <= 3 ? 1500 : 3000);
        },

        submitChecklistRows(rowsForSave, originalFileName = '', overwriteFileName = '', overwriteFileNames = []) {
            const formData = new FormData();
            formData.append('action', 'dm_save_data');
            formData.append('measure', this.state.currentMeasure);
            formData.append('folder_id', this.state.currentCategory.id);
            formData.append('rows', JSON.stringify(rowsForSave));
            formData.append('nonce', DM_CONFIG.nonce);
            formData.append('template_type', this.isAntibioticStewardshipMeasure() ? 'antibiotic_stewardship' : (this.isEdtcMeasure() ? 'edtc_checklist' : 'elements_checklist'));
            if (originalFileName) {
                formData.append('original_filename', originalFileName);
            }
            if (overwriteFileName) {
                formData.append('overwrite_file_name', overwriteFileName);
            }
            if (Array.isArray(overwriteFileNames) && overwriteFileNames.length) {
                formData.append('overwrite_file_names', JSON.stringify(overwriteFileNames));
            }

            return fetch(DM_CONFIG.ajax_url, {
                method: 'POST',
                body: formData
            }).then(res => res.json());
        },

        submitAnnualRateRows(rowsForSave, originalFileName = '', overwriteFileName = '', templateType = 'annual_rate', overwriteFileNames = []) {
            const formData = new FormData();
            formData.append('action', 'dm_save_data');
            formData.append('measure', this.state.currentMeasure);
            formData.append('folder_id', this.state.currentCategory.id);
            formData.append('rows', JSON.stringify(rowsForSave));
            formData.append('nonce', DM_CONFIG.nonce);
            formData.append('template_type', templateType);
            if (originalFileName) {
                formData.append('original_filename', originalFileName);
            }
            if (overwriteFileName) {
                formData.append('overwrite_file_name', overwriteFileName);
            }
            if (Array.isArray(overwriteFileNames) && overwriteFileNames.length) {
                formData.append('overwrite_file_names', JSON.stringify(overwriteFileNames));
            }

            return fetch(DM_CONFIG.ajax_url, {
                method: 'POST',
                body: formData
            }).then(res => res.json());
        },

        submitPeriodRateRows(rowsForSave, originalFileName = '', overwriteFileName = '', templateType = 'period_rate', overwriteFileNames = []) {
            const formData = new FormData();
            formData.append('action', 'dm_save_data');
            formData.append('measure', this.state.currentMeasure);
            formData.append('folder_id', this.state.currentCategory.id);
            formData.append('rows', JSON.stringify(rowsForSave));
            formData.append('nonce', DM_CONFIG.nonce);
            formData.append('template_type', templateType);
            if (originalFileName) {
                formData.append('original_filename', originalFileName);
            }
            if (overwriteFileName) {
                formData.append('overwrite_file_name', overwriteFileName);
            }
            if (Array.isArray(overwriteFileNames) && overwriteFileNames.length) {
                formData.append('overwrite_file_names', JSON.stringify(overwriteFileNames));
            }

            return fetch(DM_CONFIG.ajax_url, {
                method: 'POST',
                body: formData
            }).then(res => res.json());
        },

        confirmAssessmentOverwrite(payload = {}) {
            const modal = document.getElementById('dmAssessmentOverwriteModal');
            const title = document.getElementById('dmAssessmentOverwriteTitle');
            const subtitle = document.getElementById('dmAssessmentOverwriteSubtitle');
            const message = document.getElementById('dmAssessmentOverwriteMessage');
            const button = document.getElementById('dmAssessmentOverwriteContinue');
            if (!modal || !message || !button) {
                return Promise.resolve(window.confirm(payload.message || 'A saved assessment already exists. Replace it?'));
            }
            if (title) title.textContent = payload.title || 'Replace saved assessment?';
            if (subtitle) subtitle.textContent = payload.subtitle || 'This period already has a saved assessment.';
            message.textContent = payload.message || 'A saved assessment already exists. Replace it?';
            button.innerHTML = `<i class="fas fa-sync-alt"></i> ${payload.confirmLabel || 'Replace Saved Assessment'}`;
            modal.style.display = 'flex';

            return new Promise(resolve => {
                this.pendingAssessmentOverwriteResolver = resolve;
            });
        },

        resolveAssessmentOverwriteModal(confirmed = false) {
            const modal = document.getElementById('dmAssessmentOverwriteModal');
            if (modal) modal.style.display = 'none';
            const resolver = this.pendingAssessmentOverwriteResolver;
            this.pendingAssessmentOverwriteResolver = null;
            if (typeof resolver === 'function') {
                resolver(!!confirmed);
            }
        },

        async autoSaveUploadedChecklist(parsed, originalFileName) {
            if (this.isGlobalInfrastructureMeasure() && parsed && Array.isArray(parsed.incompleteAssessments) && parsed.incompleteAssessments.length) {
                const first = parsed.incompleteAssessments[0];
                this.setState({
                    checklistRows: first.rows,
                    checklistYear: first.year,
                    inputTab: 'manual',
                    uploadedFileName: originalFileName,
                    uploadError: 'This Critical Access Hospital Quality Infrastructure template has a partially completed year. Please answer Yes or No for all eight required criteria before uploading.'
                }, { preserveScroll: true, scrollToTop: false });
                return;
            }
            if (this.isGlobalInfrastructureMeasure() && parsed && Array.isArray(parsed.assessments) && parsed.assessments.length) {
                const rowsForSave = parsed.assessments.flatMap(item =>
                    this.buildGlobalChecklistSaveRowsFrom(item.year, item.rows)
                );
                const yearRecords = parsed.assessments
                    .map(item => ({
                        year: String(item.year || '').trim(),
                        record: this.assessmentRecordForYear(item.year)
                    }))
                    .filter(item => item.record);
                const existingNames = [...new Set(yearRecords.map(item => item.record.name).filter(Boolean))];
                const existingYears = [...new Set(yearRecords.map(item => item.year).filter(Boolean))];
                const shouldOverwrite = !existingNames.length || await this.confirmAssessmentOverwrite({
                    title: 'Replace saved assessment?',
                    subtitle: 'Critical Access Hospital Quality Infrastructure data already exists.',
                    message: `Saved Critical Access Hospital Quality Infrastructure data already exists for ${existingYears.join(', ')}. Uploading this file will replace the saved record${existingYears.length === 1 ? '' : 's'} and save the uploaded years as one bulk assessment.`,
                    confirmLabel: 'Replace Saved Assessment'
                });
                if (!shouldOverwrite) {
                    this.setState({
                        checklistRows: parsed.assessments[0].rows,
                        checklistYear: parsed.assessments[0].year,
                        inputTab: 'manual',
                        uploadedFileName: originalFileName
                    });
                    return;
                }
                this.submitChecklistRows(rowsForSave, originalFileName, '', existingNames)
                    .then(data => {
                        if (data.success) {
                            this.applySuccessfulSave(data, rowsForSave.map(r => ({ ...r })), {
                                checklistRows: this.defaultChecklistRows(DM_GLOBAL_INFRASTRUCTURE_MEASURE),
                                checklistYear: String(new Date().getFullYear()),
                                inputTab: 'entry'
                            });
                        } else {
                            alert('Error: ' + (data.data || data.message || 'Unknown error occurred'));
                            this.setState({
                                checklistRows: parsed.assessments[0].rows,
                                checklistYear: parsed.assessments[0].year,
                                inputTab: 'manual',
                                uploadedFileName: originalFileName
                            });
                        }
                    })
                    .catch(() => {
                        alert('Connection error');
                        this.setState({
                            checklistRows: parsed.assessments[0].rows,
                            checklistYear: parsed.assessments[0].year,
                            inputTab: 'manual',
                            uploadedFileName: originalFileName
                        });
                    });
                return;
            }
            if (this.isEdtcMeasure() && parsed && Array.isArray(parsed.incompleteAssessments) && parsed.incompleteAssessments.length) {
                const first = parsed.incompleteAssessments[0];
                this.setState({
                    checklistRows: first.rows,
                    checklistYear: first.year,
                    checklistQuarter: this.normalizeQuarter(first.quarter) || 'Q1',
                    edtcCompositeNum: String(first.compositeNum || ''),
                    edtcCompositeDen: String(first.compositeDen || ''),
                    inputTab: 'manual',
                    uploadedFileName: originalFileName,
                    uploadError: 'This Emergency Department Transfer Communication template has a partially completed quarter. Please enter the composite score and valid numerator and denominator values for all eight Emergency Department Transfer Communication elements before uploading.'
                }, { preserveScroll: true, scrollToTop: false });
                return;
            }
            if (this.isEdtcMeasure() && parsed && Array.isArray(parsed.invalidAssessments) && parsed.invalidAssessments.length) {
                const first = parsed.invalidAssessments[0];
                this.setState({
                    checklistRows: first.rows,
                    checklistYear: first.year,
                    checklistQuarter: this.normalizeQuarter(first.quarter) || 'Q1',
                    edtcCompositeNum: String(first.compositeNum || ''),
                    edtcCompositeDen: String(first.compositeDen || ''),
                    inputTab: 'manual',
                    uploadedFileName: originalFileName,
                    uploadError: 'This Emergency Department Transfer Communication template has a denominator lower than its numerator. Please correct the numerator and denominator values before uploading.'
                }, { preserveScroll: true, scrollToTop: false });
                return;
            }
            if (this.isEdtcMeasure() && parsed && Array.isArray(parsed.assessments) && parsed.assessments.length) {
                const rowsForSave = parsed.assessments.flatMap(item =>
                    this.buildGlobalChecklistSaveRowsFrom(item.year, item.rows, item.quarter, {
                        compositeNum: item.compositeNum,
                        compositeDen: item.compositeDen
                    })
                );
                const periodRecords = parsed.assessments
                    .map(item => ({
                        label: `${this.normalizeQuarter(item.quarter)} ${String(item.year || '').trim()}`.trim(),
                        record: this.assessmentRecordForPeriod(this.normalizeQuarter(item.quarter), item.year)
                    }))
                    .filter(item => item.record);
                const existingNames = [...new Set(periodRecords.map(item => item.record.name).filter(Boolean))];
                const existingLabels = [...new Set(periodRecords.map(item => item.label).filter(Boolean))];
                if (existingNames.length && !confirm(`Saved Emergency Department Transfer Communication data already exists for ${existingLabels.join(', ')}. Uploading this file will replace that saved record${existingLabels.length === 1 ? '' : 's'} and save the uploaded quarters as one bulk assessment. Continue?`)) {
                    this.setState({
                        checklistRows: parsed.assessments[0].rows,
                        checklistYear: parsed.assessments[0].year,
                        checklistQuarter: this.normalizeQuarter(parsed.assessments[0].quarter) || 'Q1',
                        edtcCompositeNum: String(parsed.assessments[0].compositeNum || ''),
                        edtcCompositeDen: String(parsed.assessments[0].compositeDen || ''),
                        inputTab: 'manual',
                        uploadedFileName: originalFileName
                    });
                    return;
                }
                this.submitChecklistRows(rowsForSave, originalFileName, '', existingNames)
                    .then(data => {
                        if (data.success) {
                            this.applySuccessfulSave(data, rowsForSave.map(r => ({ ...r })), {
                                checklistRows: this.defaultChecklistRows(DM_EDTC_MEASURE),
                                checklistYear: String(new Date().getFullYear()),
                                checklistQuarter: 'Q1',
                                edtcCompositeNum: '',
                                edtcCompositeDen: '',
                                inputTab: 'entry'
                            });
                        } else {
                            alert('Error: ' + (data.data || data.message || 'Unknown error occurred'));
                            this.setState({
                                checklistRows: parsed.assessments[0].rows,
                                checklistYear: parsed.assessments[0].year,
                                checklistQuarter: this.normalizeQuarter(parsed.assessments[0].quarter) || 'Q1',
                                edtcCompositeNum: String(parsed.assessments[0].compositeNum || ''),
                                edtcCompositeDen: String(parsed.assessments[0].compositeDen || ''),
                                inputTab: 'manual',
                                uploadedFileName: originalFileName
                            });
                        }
                    })
                    .catch(() => {
                        alert('Connection error');
                        this.setState({
                            checklistRows: parsed.assessments[0].rows,
                            checklistYear: parsed.assessments[0].year,
                            checklistQuarter: this.normalizeQuarter(parsed.assessments[0].quarter) || 'Q1',
                            edtcCompositeNum: String(parsed.assessments[0].compositeNum || ''),
                            edtcCompositeDen: String(parsed.assessments[0].compositeDen || ''),
                            inputTab: 'manual',
                            uploadedFileName: originalFileName
                        });
                });
                return;
            }
            if (this.isAntibioticStewardshipMeasure() && parsed && Array.isArray(parsed.incompleteAssessments) && parsed.incompleteAssessments.length) {
                const first = parsed.incompleteAssessments[0];
                this.setState({
                    checklistRows: first.rows,
                    checklistYear: first.year,
                    inputTab: 'manual',
                    uploadedFileName: originalFileName,
                    uploadError: 'This Antibiotic Stewardship template has a partially completed year. Please answer Yes or No for all seven Centers for Disease Control and Prevention Core Elements before uploading.'
                }, { preserveScroll: true, scrollToTop: false });
                return;
            }
            if (this.isAntibioticStewardshipMeasure() && parsed && Array.isArray(parsed.assessments) && parsed.assessments.length) {
                const rowsForSave = parsed.assessments.flatMap(item =>
                    this.buildGlobalChecklistSaveRowsFrom(item.year, item.rows)
                );
                const yearRecords = parsed.assessments
                    .map(item => ({
                        year: String(item.year || '').trim(),
                        record: this.assessmentRecordForYear(item.year)
                    }))
                    .filter(item => item.record);
                const existingNames = [...new Set(yearRecords.map(item => item.record.name).filter(Boolean))];
                const existingYears = [...new Set(yearRecords.map(item => item.year).filter(Boolean))];
                if (existingNames.length && !confirm(`Saved Antibiotic Stewardship data already exists for ${existingYears.join(', ')}. Uploading this file will replace that saved record${existingYears.length === 1 ? '' : 's'} and save the uploaded years as one bulk assessment. Continue?`)) {
                    this.setState({
                        checklistRows: parsed.assessments[0].rows,
                        checklistYear: parsed.assessments[0].year,
                        inputTab: 'manual',
                        uploadedFileName: originalFileName
                    });
                    return;
                }
                this.submitChecklistRows(rowsForSave, originalFileName, '', existingNames)
                    .then(data => {
                        if (data.success) {
                            this.applySuccessfulSave(data, rowsForSave.map(r => ({ ...r })), {
                                checklistRows: this.defaultChecklistRows(),
                                checklistYear: String(new Date().getFullYear()),
                                inputTab: 'entry'
                            });
                        } else {
                            alert('Error: ' + (data.data || data.message || 'Unknown error occurred'));
                            this.setState({
                                checklistRows: parsed.assessments[0].rows,
                                checklistYear: parsed.assessments[0].year,
                                inputTab: 'manual',
                                uploadedFileName: originalFileName
                            });
                        }
                    })
                    .catch(() => {
                        alert('Connection error');
                        this.setState({
                            checklistRows: parsed.assessments[0].rows,
                            checklistYear: parsed.assessments[0].year,
                            inputTab: 'manual',
                            uploadedFileName: originalFileName
                        });
                    });
                return;
            }
            const rows = parsed && Array.isArray(parsed.rows) ? parsed.rows : [];
            const year = parsed && parsed.year ? parsed.year : this.state.checklistYear;
            const quarter = this.isEdtcMeasure()
                ? (parsed && parsed.quarter ? parsed.quarter : this.state.checklistQuarter)
                : '';
            const summary = this.isEdtcMeasure() ? this.edtcNumDenSummary(rows) : this.globalChecklistSummaryForRows(rows);
            const edtcCompositeSummary = this.isEdtcMeasure()
                ? this.edtcCompositeSummary(parsed && parsed.compositeNum != null ? parsed.compositeNum : this.state.edtcCompositeNum, parsed && parsed.compositeDen != null ? parsed.compositeDen : this.state.edtcCompositeDen)
                : null;
            if (!summary.complete || (this.isEdtcMeasure() && (!this.normalizeQuarter(quarter) || !edtcCompositeSummary.complete))) {
                const edtcHasInversion = this.isEdtcMeasure() && (summary.hasInversion || edtcCompositeSummary.hasInversion);
                this.setState({
                    checklistRows: rows,
                    checklistYear: year,
                    checklistQuarter: this.normalizeQuarter(quarter) || this.state.checklistQuarter || 'Q1',
                    edtcCompositeNum: this.isEdtcMeasure() ? String(parsed && parsed.compositeNum != null ? parsed.compositeNum : this.state.edtcCompositeNum || '') : this.state.edtcCompositeNum,
                    edtcCompositeDen: this.isEdtcMeasure() ? String(parsed && parsed.compositeDen != null ? parsed.compositeDen : this.state.edtcCompositeDen || '') : this.state.edtcCompositeDen,
                    inputTab: 'entry',
                    uploadedFileName: originalFileName,
                    uploadError: this.isEdtcMeasure() && !this.normalizeQuarter(quarter)
                        ? 'This is the correct template, but it is missing a quarter. Please choose Q1, Q2, Q3, or Q4 before uploading.'
                        : (edtcHasInversion
                            ? 'This Emergency Department Transfer Communication template has a denominator lower than its numerator. Please correct the numerator and denominator values before uploading.'
                            : (this.isEdtcMeasure()
                            ? 'This is the correct template, but it is incomplete. Please enter the composite score and valid numerator and denominator values for all eight Emergency Department Transfer Communication elements before uploading. Completed fields have been loaded below so you can finish them manually.'
                            : `This is the correct template, but it is incomplete. Please answer Yes or No for all ${summary.total || rows.length || 'required'} criteria before uploading. Completed fields have been loaded below so you can finish them manually.`))
                }, { preserveScroll: true, scrollToTop: false });
                return;
            }

            const rowsForSave = this.buildGlobalChecklistSaveRowsFrom(year, rows, quarter, {
                compositeNum: parsed && parsed.compositeNum != null ? parsed.compositeNum : this.state.edtcCompositeNum,
                compositeDen: parsed && parsed.compositeDen != null ? parsed.compositeDen : this.state.edtcCompositeDen
            });
            const existing = this.isEdtcMeasure()
                ? this.assessmentRecordForPeriod(this.normalizeQuarter(quarter), year)
                : this.assessmentRecordForYear(year);
            const periodLabel = this.isEdtcMeasure() ? `${this.normalizeQuarter(quarter)} ${year}` : year;
            const shouldOverwrite = !existing || (this.isGlobalInfrastructureMeasure()
                ? await this.confirmAssessmentOverwrite({
                    title: 'Replace saved assessment?',
                    subtitle: 'This period already has a saved CAH assessment.',
                    message: `A saved assessment for ${periodLabel} already exists. Replace it?`,
                    confirmLabel: 'Replace Saved Assessment'
                })
                : confirm(`A saved assessment for ${periodLabel} already exists. Do you want to overwrite it?`));
            if (!shouldOverwrite) {
                this.setState({
                    checklistRows: rows,
                    checklistYear: year,
                    checklistQuarter: this.normalizeQuarter(quarter) || this.state.checklistQuarter || 'Q1',
                    edtcCompositeNum: String(parsed && parsed.compositeNum != null ? parsed.compositeNum : this.state.edtcCompositeNum || ''),
                    edtcCompositeDen: String(parsed && parsed.compositeDen != null ? parsed.compositeDen : this.state.edtcCompositeDen || ''),
                    inputTab: 'manual',
                    uploadedFileName: originalFileName
                });
                return;
            }

            this.submitChecklistRows(rowsForSave, originalFileName, existing && existing.name ? existing.name : '')
                .then(data => {
                    if (data.success) {
                        this.applySuccessfulSave(data, rowsForSave.map(r => ({ ...r })), {
                            checklistRows: this.defaultChecklistRows(),
                            checklistYear: String(new Date().getFullYear()),
                            checklistQuarter: 'Q1',
                            edtcCompositeNum: '',
                            edtcCompositeDen: '',
                            inputTab: 'entry'
                        });
                    } else {
                        alert('Error: ' + (data.data || data.message || 'Unknown error occurred'));
                        this.setState({
                            checklistRows: rows,
                            checklistYear: year,
                            checklistQuarter: this.normalizeQuarter(quarter) || this.state.checklistQuarter || 'Q1',
                            edtcCompositeNum: String(parsed && parsed.compositeNum != null ? parsed.compositeNum : this.state.edtcCompositeNum || ''),
                            edtcCompositeDen: String(parsed && parsed.compositeDen != null ? parsed.compositeDen : this.state.edtcCompositeDen || ''),
                            inputTab: 'manual',
                            uploadedFileName: originalFileName
                        });
                    }
                })
                .catch(() => {
                    alert('Connection error');
                    this.setState({
                        checklistRows: rows,
                        checklistYear: year,
                        checklistQuarter: this.normalizeQuarter(quarter) || this.state.checklistQuarter || 'Q1',
                        edtcCompositeNum: String(parsed && parsed.compositeNum != null ? parsed.compositeNum : this.state.edtcCompositeNum || ''),
                        edtcCompositeDen: String(parsed && parsed.compositeDen != null ? parsed.compositeDen : this.state.edtcCompositeDen || ''),
                        inputTab: 'manual',
                        uploadedFileName: originalFileName
                    });
                });
        },

        autoSaveUploadedAnnualRate(row, originalFileName) {
            const hasInversion = this.hasNumDenInversion(row);
            if (!row || !String(row.year || '').trim() || String(row.num || '').trim() === '' || String(row.den || '').trim() === '' || Number(row.den) <= 0 || hasInversion) {
                this.setState({
                    manualRows: [row || { month: '', year: String(new Date().getFullYear()), num: '', den: '' }],
                    inputTab: hasInversion ? 'manual' : 'upload',
                    uploadedFileName: originalFileName,
                    uploadError: hasInversion
                        ? 'The uploaded numerator is greater than the denominator. Please correct the values before saving.'
                        : 'This is the correct template, but it is incomplete. Please enter the year, numerator, and denominator before uploading. Completed fields have been loaded below so you can finish them manually.'
                }, { preserveScroll: true, scrollToTop: false });
                return;
            }

            const year = String(row.year || '').trim();
            const existing = this.assessmentRecordForYear(year);
            if (existing && !confirm(`A saved assessment for ${year} already exists. Do you want to overwrite it?`)) {
                this.setState({
                    inputTab: 'entry',
                    uploadedFileName: ''
                });
                return;
            }

            const rowsForSave = [{ ...row, month: '' }];
            this.submitAnnualRateRows(rowsForSave, originalFileName, existing && existing.name ? existing.name : '', this.isOp22Measure() ? 'annual_numden_rate' : 'annual_rate')
                .then(data => {
                    if (data.success) {
                        this.applySuccessfulSave(data, rowsForSave.map(r => ({ ...r })), {
                            manualRows: [{ month: '', year: String(new Date().getFullYear()), num: '', den: '' }],
                            inputTab: 'entry'
                        });
                    } else {
                        alert('Error: ' + (data.data || data.message || 'Unknown error occurred'));
                        this.setState({
                            manualRows: [row],
                            inputTab: 'manual',
                            uploadedFileName: originalFileName
                        });
                    }
                })
                .catch(() => {
                    alert('Connection error');
                    this.setState({
                        manualRows: [row],
                        inputTab: 'manual',
                        uploadedFileName: originalFileName
                    });
                });
        },

        autoSaveUploadedAnnualRateRows(rows, originalFileName, options = {}) {
            const isOp22Bulk = options.templateType === 'annual_numden_rate' || this.isOp22Measure();
            const measureLabel = options.measureLabel || (isOp22Bulk ? 'OP-22' : 'HCP');
            const templateType = options.templateType || (isOp22Bulk ? 'annual_numden_rate' : 'annual_rate');
            const emptyRow = { month: '', year: String(new Date().getFullYear()), num: '', den: '' };
            rows = Array.isArray(rows) ? rows.filter(row => !this.isRowEmpty(row)) : [];
            const complete = rows.length > 0 && rows.every(row =>
                !!String(row.year || '').trim()
                && String(row.num || '').trim() !== ''
                && String(row.den || '').trim() !== ''
                && Number(row.den) > 0
                && !this.hasNumDenInversion(row)
            );
            if (!complete) {
                const hasInversion = this.rowsHaveNumDenInversion(rows);
                this.setState({
                    manualRows: rows.length ? rows : [{ month: '', year: String(new Date().getFullYear()), num: '', den: '' }],
                    inputTab: hasInversion ? 'manual' : 'upload',
                    uploadedFileName: originalFileName,
                    uploadError: hasInversion
                        ? `One or more uploaded ${measureLabel} rows has a numerator greater than the denominator. Please correct the values before saving.`
                        : `This is the correct ${measureLabel} template, but it is incomplete. Please enter the year, numerator, and denominator for at least one row before uploading.`
                }, { preserveScroll: true, scrollToTop: false });
                return;
            }

            const years = [...new Set(rows.map(row => String(row.year || '').trim()).filter(Boolean))];
            const existingRecords = years
                .map(year => ({ year, record: this.assessmentRecordForYear(year) }))
                .filter(item => item.record);
            const existingNames = [...new Set(existingRecords.map(item => item.record.name).filter(Boolean))];
            const existingYears = [...new Set(existingRecords.map(item => item.year).filter(Boolean))];
            if (existingNames.length) {
                const existingLabel = existingYears.join(', ');
                const uploadLabel = years.join(', ');
                const confirmMessage = `Saved ${measureLabel} data already exists for ${existingLabel}. Uploading this file will replace that saved record${existingYears.length === 1 ? '' : 's'} and save ${uploadLabel} as one bulk assessment. Continue?`;
                if (!confirm(confirmMessage)) {
                    this.setState({
                        inputTab: 'entry',
                        uploadedFileName: ''
                    });
                    return;
                }
            }

            const rowsForSave = rows.map(row => ({ ...row, month: '' }));
            this.submitAnnualRateRows(rowsForSave, originalFileName, '', templateType, existingNames)
                .then(data => {
                    if (data.success) {
                        this.applySuccessfulSave(data, rowsForSave.map(r => ({ ...r })), {
                            manualRows: [emptyRow],
                            inputTab: 'entry'
                        });
                    } else {
                        alert('Error: ' + (data.data || data.message || 'Unknown error occurred'));
                        this.setState({
                            manualRows: rowsForSave,
                            inputTab: 'manual',
                            uploadedFileName: originalFileName
                        });
                    }
                })
                .catch(() => {
                    alert('Connection error');
                    this.setState({
                        manualRows: rowsForSave,
                        inputTab: 'manual',
                        uploadedFileName: originalFileName
                    });
                });
        },

        autoSaveUploadedPeriodRate(rows, originalFileName) {
            rows = Array.isArray(rows) ? rows.filter(row => !this.isRowEmpty(row)) : [];
            const complete = rows.length > 0
                && rows.every(row =>
                    !!String(row.month || '').trim()
                    && !!String(row.year || '').trim()
                    && String(row.num || '').trim() !== ''
                    && String(row.den || '').trim() !== ''
                    && Number(row.den) > 0
                    && !this.hasNumDenInversion(row)
                );
            if (!complete) {
                const periodLabel = this.isQuarterRateMeasure() ? 'quarter' : 'month';
                const hasInversion = this.rowsHaveNumDenInversion(rows);
                this.setState({
                    manualRows: rows.length ? rows : [{ month: '', year: '', num: '', den: '' }],
                    inputTab: hasInversion ? 'manual' : 'upload',
                    uploadedFileName: originalFileName,
                    uploadError: hasInversion
                        ? 'One or more uploaded rows has a numerator greater than the denominator. Please correct the values before saving.'
                        : `This is the correct template, but it is incomplete. Please enter the year, ${periodLabel}, numerator, and denominator for each row before uploading. Completed fields have been loaded below so you can finish them manually.`
                }, { preserveScroll: true, scrollToTop: false });
                return;
            }

            const periodRecords = rows
                .map(row => ({
                    label: `${String(row.month || '').trim()} ${String(row.year || '').trim()}`.trim(),
                    record: this.assessmentRecordForPeriod(row.month, row.year)
                }))
                .filter(item => item.record);
            const existingNames = [...new Set(periodRecords.map(item => item.record.name).filter(Boolean))];
            const existingLabels = [...new Set(periodRecords.map(item => item.label).filter(Boolean))];
            if (existingNames.length && !confirm(`Saved data already exists for ${existingLabels.join(', ')}. Uploading this file will replace that saved record${existingLabels.length === 1 ? '' : 's'} and save the uploaded periods as one bulk assessment. Continue?`)) {
                this.setState({
                    inputTab: 'entry',
                    uploadedFileName: ''
                });
                return;
            }

            this.submitPeriodRateRows(rows, originalFileName, '', this.isQuarterRateMeasure() ? 'quarter_rate' : 'period_rate', existingNames)
                .then(data => {
                    if (data.success) {
                        this.applySuccessfulSave(data, rows.map(r => ({ ...r })), {
                            manualRows: [{ month: '', year: '', num: '', den: '' }],
                            inputTab: 'entry'
                        });
                    } else {
                        alert('Error: ' + (data.data || data.message || 'Unknown error occurred'));
                        this.setState({
                            manualRows: rows,
                            inputTab: 'manual',
                            uploadedFileName: originalFileName
                        });
                    }
                })
                .catch(() => {
                    alert('Connection error');
                    this.setState({
                        manualRows: rows,
                        inputTab: 'manual',
                        uploadedFileName: originalFileName
                    });
                });
        },

        autoSaveUploadedQuarterMedian(rows, originalFileName) {
            rows = Array.isArray(rows) ? rows.filter(row => !this.isRowEmpty(row)) : [];
            const complete = rows.length > 0
                && rows.every(row =>
                    !!String(row.month || '').trim()
                    && !!String(row.year || '').trim()
                    && String(row.median || '').trim() !== ''
                    && Number(row.median) >= 0
                );
            if (!complete) {
                this.setState({
                    manualRows: rows.length ? rows : [{ month: 'Q1', year: String(new Date().getFullYear()), median: '' }],
                    inputTab: 'entry',
                    uploadedFileName: originalFileName,
                    uploadError: 'This is the correct template, but it is incomplete. Please enter the year, quarter, and median minutes for each row before uploading. Completed fields have been loaded below so you can finish them manually.'
                }, { preserveScroll: true, scrollToTop: false });
                return;
            }

            const periodRecords = rows
                .map(row => ({
                    label: `${String(row.month || '').trim()} ${String(row.year || '').trim()}`.trim(),
                    record: this.assessmentRecordForPeriod(row.month, row.year)
                }))
                .filter(item => item.record);
            const existingNames = [...new Set(periodRecords.map(item => item.record.name).filter(Boolean))];
            const existingLabels = [...new Set(periodRecords.map(item => item.label).filter(Boolean))];
            if (existingNames.length && !confirm(`Saved OP-18 data already exists for ${existingLabels.join(', ')}. Uploading this file will replace that saved record${existingLabels.length === 1 ? '' : 's'} and save the uploaded quarters as one bulk assessment. Continue?`)) {
                this.setState({
                    inputTab: 'entry',
                    uploadedFileName: ''
                });
                return;
            }

            this.submitPeriodRateRows(rows, originalFileName, '', 'quarter_median', existingNames)
                .then(data => {
                    if (data.success) {
                        this.applySuccessfulSave(data, rows.map(r => ({ ...r })), {
                            manualRows: [{ month: 'Q1', year: String(new Date().getFullYear()), median: '' }],
                            inputTab: 'entry'
                        });
                    } else {
                        alert('Error: ' + (data.data || data.message || 'Unknown error occurred'));
                        this.setState({
                            manualRows: rows,
                            inputTab: 'manual',
                            uploadedFileName: originalFileName
                        });
                    }
                })
                .catch(() => {
                    alert('Connection error');
                    this.setState({
                        manualRows: rows,
                        inputTab: 'manual',
                        uploadedFileName: originalFileName
                    });
                });
        },

        saveAllData(e) {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            const btn = e.currentTarget;
            const originalHtml = btn.innerHTML;
            const isChecklist = this.isGlobalInfrastructureMeasure() || this.isAntibioticStewardshipMeasure() || this.isEdtcMeasure();
            const isQuarterRate = this.isQuarterRateMeasure();
            const isQuarterMedian = this.isQuarterMedianMeasure();
            const isHwr = this.isHwrMeasure();
            const isOp22 = this.isOp22Measure();
            const rowsForSave = isChecklist
                ? this.buildGlobalChecklistSaveRows()
                : (this.isHcpInfluenzaMeasure() || isHwr || isOp22 || isQuarterRate || isQuarterMedian ? (this.state.manualRows || []).slice(0, 1) : (this.isMonthlyRateMeasure() ? this.validManualRows() : this.state.manualRows));
            const savedRowsSnapshot = rowsForSave.map(r => ({ ...r }));
            let overwriteFileName = '';

            if (!isChecklist && !isQuarterMedian && this.rowsHaveNumDenInversion(rowsForSave)) {
                this.setState({
                    manualRows: rowsForSave.length ? rowsForSave : this.state.manualRows,
                    inputTab: 'manual',
                    uploadError: 'Please correct rows where the denominator is lower than the numerator before saving.'
                }, { preserveScroll: true, scrollToTop: false });
                return;
            }

            if (isChecklist || this.isHcpInfluenzaMeasure() || isHwr || isOp22 || this.isMonthlyRateMeasure() || isQuarterRate || isQuarterMedian) {
                const period = (isHwr || this.isEdtcMeasure() || this.isMonthlyRateMeasure() || isQuarterRate || isQuarterMedian) ? this.periodRatePeriod(rowsForSave) : null;
                const year = period ? period.year : (rowsForSave[0] ? rowsForSave[0].year : '');
                const existing = period ? this.assessmentRecordForPeriod(period.month, period.year) : this.assessmentRecordForYear(year);
                const periodLabel = period ? `${period.month} ${period.year}` : year;
                if (existing && !confirm(`A saved assessment for ${periodLabel} already exists. Do you want to overwrite it?`)) {
                    return;
                }
                overwriteFileName = existing && existing.name ? existing.name : '';
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            
            const formData = new FormData();
            formData.append('action', 'dm_save_data');
            formData.append('measure', this.state.currentMeasure);
            formData.append('folder_id', this.state.currentCategory.id);
            formData.append('rows', JSON.stringify(rowsForSave));
            formData.append('nonce', DM_CONFIG.nonce);
            if (isChecklist) {
                formData.append('template_type', this.isAntibioticStewardshipMeasure() ? 'antibiotic_stewardship' : (this.isEdtcMeasure() ? 'edtc_checklist' : 'elements_checklist'));
                if (overwriteFileName) {
                    formData.append('overwrite_file_name', overwriteFileName);
                }
            }
            if (this.isHcpInfluenzaMeasure()) {
                formData.append('template_type', 'annual_rate');
                if (overwriteFileName) {
                    formData.append('overwrite_file_name', overwriteFileName);
                }
            }
            if (isOp22) {
                formData.append('template_type', 'annual_numden_rate');
                if (overwriteFileName) {
                    formData.append('overwrite_file_name', overwriteFileName);
                }
            }
            if (isHwr || this.isMonthlyRateMeasure()) {
                formData.append('template_type', 'period_rate');
                if (overwriteFileName) {
                    formData.append('overwrite_file_name', overwriteFileName);
                }
            }
            if (isQuarterRate && !this.isEdtcMeasure()) {
                formData.append('template_type', 'quarter_rate');
                if (overwriteFileName) {
                    formData.append('overwrite_file_name', overwriteFileName);
                }
            }
            if (isQuarterMedian) {
                formData.append('template_type', 'quarter_median');
                if (overwriteFileName) {
                    formData.append('overwrite_file_name', overwriteFileName);
                }
            }
            // Preserve the source filename when these rows came from an upload.
            // Manual-entry-only saves omit this and fall back to the auto-named
            // <measure>_<timestamp>.csv on the server.
            if (this.state.uploadedFileName) {
                formData.append('original_filename', this.state.uploadedFileName);
            }

            fetch(DM_CONFIG.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                
                if (data.success) {
                    this.applySuccessfulSave(data, savedRowsSnapshot);
                } else {
                    alert('Error: ' + (data.data || data.message || 'Unknown error occurred'));
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                alert('Connection error');
            });
        },

        handleFileUpload(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.hideUploadError();

            const reader = new FileReader();
            const originalFileName = String(file.name || '');
            reader.onload = (e) => {
                const data = e.target.result;
                if (!this.state.currentCategory || this.state.currentCategory.id !== 'general') {
                    this.showUploadError('Individual measure uploads are no longer supported. Please use the Universal Workbook.');
                    return;
                }
                if (file.name.toLowerCase().endsWith('.csv')) {
                    this.showUploadError('Please upload the Universal Workbook as an Excel .xlsx file.');
                    return;
                }
                this.parseGeneralMbqipWorkbook(data, originalFileName);
            };

            if (file.name.endsWith('.csv')) {
                reader.readAsText(file);
            } else {
                reader.readAsArrayBuffer(file);
            }
        },

        handleDragOver(event) {
            event.preventDefault();
        },

        handleFileDrop(event) {
            event.preventDefault();
            const file = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files[0] : null;
            if (!file) return;
            this.handleFileUpload({ target: { files: [file] } });
        },

        parseCsv(text, originalFileName = '') {
            const lines = text.split('\n').filter(l => l.trim());
            if (lines.length < 2) {
                this.showUploadError('This file could not be read as a completed template. Please check that it includes the expected template rows and try again.');
                return;
            }

            if (this.isEdtcMeasure()) {
                const workbook = XLSX.read(text, { type: 'string' });
                const sheet = workbook.Sheets[workbook.SheetNames[0]];
                const grid = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
                if (!this.validateUploadGrid(grid, originalFileName)) return;
                const parsed = this.parseEdtcNumDenRows(grid);
                this.autoSaveUploadedChecklist(parsed, originalFileName);
                return;
            }

            if (this.isGlobalInfrastructureMeasure() || this.isAntibioticStewardshipMeasure()) {
                const workbook = XLSX.read(text, { type: 'string' });
                const sheet = workbook.Sheets[workbook.SheetNames[0]];
                const grid = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
                if (!this.validateUploadGrid(grid, originalFileName)) return;
                const parsed = this.parseGlobalChecklistRows(grid);
                this.autoSaveUploadedChecklist(parsed, originalFileName);
                return;
            }

            if (this.isHcpInfluenzaMeasure() || this.isOp22Measure()) {
                const workbook = XLSX.read(text, { type: 'string' });
                const sheet = workbook.Sheets[workbook.SheetNames[0]];
                const grid = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
                if (!this.validateUploadGrid(grid, originalFileName)) return;
                const parsed = this.parseAnnualRateRows(grid);
                if (this.isHcpInfluenzaMeasure()) {
                    this.autoSaveUploadedAnnualRateRows(parsed, originalFileName);
                } else {
                    this.autoSaveUploadedAnnualRateRows(parsed, originalFileName, {
                        measureLabel: 'OP-22',
                        templateType: 'annual_numden_rate'
                    });
                }
                return;
            }

            if (this.isHwrMeasure() || this.isMonthlyRateMeasure() || (this.isQuarterRateMeasure() && !this.isEdtcMeasure())) {
                const workbook = XLSX.read(text, { type: 'string' });
                const sheet = workbook.Sheets[workbook.SheetNames[0]];
                const grid = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
                if (!this.validateUploadGrid(grid, originalFileName)) return;
                const parsed = this.parsePeriodRateRows(grid);
                this.autoSaveUploadedPeriodRate(parsed, originalFileName);
                return;
            }

            if (this.isQuarterMedianMeasure()) {
                const workbook = XLSX.read(text, { type: 'string' });
                const sheet = workbook.Sheets[workbook.SheetNames[0]];
                const grid = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
                if (!this.validateUploadGrid(grid, originalFileName)) return;
                const parsed = this.parseQuarterMedianRows(grid);
                this.autoSaveUploadedQuarterMedian(parsed, originalFileName);
                return;
            }

            const rows = [];
            const headers = lines[0].toLowerCase().split(',').map(h => h.trim().replace(/"/g, ''));
            
            // Map headers
            const idx = {
                month: headers.findIndex(h => h.includes('month') || h.includes('time') || h === 'jan' || h === 'feb'),
                year: headers.findIndex(h => h.includes('year')),
                num: headers.findIndex(h => h.includes('num') || h.includes('numerator')),
                den: headers.findIndex(h => h.includes('den') || h.includes('denominator'))
                /* , med: headers.findIndex(h => h.includes('med')) */
            };

            lines.slice(1).forEach(line => {
                const cols = line.split(',').map(c => c.trim().replace(/"/g, ''));
                if (cols.length < 2) return;

                let monthStr = idx.month >= 0 ? cols[idx.month] : '';
                let yearStr = idx.year >= 0 ? cols[idx.year] : '';

                // If the value in the "time" row is actually the column name, skip
                if (monthStr.toLowerCase().includes('month') || monthStr.toLowerCase().includes('time')) return;

                rows.push({
                    month: this.normalizeMonth(monthStr),
                    year: yearStr.trim(),
                    num: parseFloat(cols[idx.num]) || 0,
                    den: parseFloat(cols[idx.den]) || 0
                    /* , median: parseFloat(cols[idx.med]) || 0 */
                });
            });

            if (rows.length > 0) this.setState({ manualRows: rows, inputTab: 'manual' });
        },

        parseExcel(buffer, originalFileName = '') {
            const workbook = XLSX.read(buffer, { type: 'array' });
            const sheetName = this.isGlobalInfrastructureMeasure() && workbook.Sheets['Global template']
                ? 'Global template'
                : (this.isAntibioticStewardshipMeasure() && workbook.Sheets['Antibiotic Stewardship']
                    ? 'Antibiotic Stewardship'
                    : (this.isEdtcMeasure() && workbook.Sheets['EDTC'] ? 'EDTC' : workbook.SheetNames[0]));
            const sheet = workbook.Sheets[sheetName];
            const json = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
            
            if (json.length < 2) {
                this.showUploadError('This file could not be read as a completed template. Please check that it includes the expected template rows and try again.');
                return;
            }

            if (this.isEdtcMeasure()) {
                if (!this.validateUploadGrid(json, originalFileName)) return;
                const parsed = this.parseEdtcNumDenRows(json);
                this.autoSaveUploadedChecklist(parsed, originalFileName);
                return;
            }

            if (this.isGlobalInfrastructureMeasure() || this.isAntibioticStewardshipMeasure()) {
                if (!this.validateUploadGrid(json, originalFileName)) return;
                const parsed = this.parseGlobalChecklistRows(json);
                this.autoSaveUploadedChecklist(parsed, originalFileName);
                return;
            }

            if (this.isHcpInfluenzaMeasure() || this.isOp22Measure()) {
                if (!this.validateUploadGrid(json, originalFileName)) return;
                const parsed = this.parseAnnualRateRows(json);
                if (this.isHcpInfluenzaMeasure()) {
                    this.autoSaveUploadedAnnualRateRows(parsed, originalFileName);
                } else {
                    this.autoSaveUploadedAnnualRateRows(parsed, originalFileName, {
                        measureLabel: 'OP-22',
                        templateType: 'annual_numden_rate'
                    });
                }
                return;
            }

            if (this.isHwrMeasure() || this.isMonthlyRateMeasure() || (this.isQuarterRateMeasure() && !this.isEdtcMeasure())) {
                if (!this.validateUploadGrid(json, originalFileName)) return;
                const parsed = this.parsePeriodRateRows(json);
                this.autoSaveUploadedPeriodRate(parsed, originalFileName);
                return;
            }

            if (this.isQuarterMedianMeasure()) {
                if (!this.validateUploadGrid(json, originalFileName)) return;
                const parsed = this.parseQuarterMedianRows(json);
                this.autoSaveUploadedQuarterMedian(parsed, originalFileName);
                return;
            }

            const headers = json[0].map(h => String(h || '').toLowerCase().trim());
            const idx = {
                month: headers.findIndex(h => h.includes('month') || h.includes('time')),
                year: headers.findIndex(h => h.includes('year')),
                num: headers.findIndex(h => h.includes('num') || h.includes('numerator')),
                den: headers.findIndex(h => h.includes('den') || h.includes('denominator'))
                /* , med: headers.findIndex(h => h.includes('med')) */
            };

            const rows = [];
            json.slice(1).forEach(cols => {
                if (!cols || cols.length < 2) return;

                let monthStr = idx.month >= 0 ? String(cols[idx.month] || '') : '';
                let yearStr = idx.year >= 0 ? String(cols[idx.year] || '') : '';

                if (monthStr.toLowerCase().includes('month') || monthStr.toLowerCase().includes('time')) return;

                rows.push({
                    month: this.normalizeMonth(monthStr),
                    year: yearStr.trim(),
                    num: parseFloat(cols[idx.num]) || 0,
                    den: parseFloat(cols[idx.den]) || 0
                    /* , median: parseFloat(cols[idx.med]) || 0 */
                });
            });

            if (rows.length > 0) this.setState({ manualRows: rows, inputTab: 'manual' });
        },

        attachInputListeners() {
            // Placeholder for specialized bindings if needed
        }
    };

    document.addEventListener('DOMContentLoaded', () => dmApp.init());
</script>

<?php if ( empty( $GLOBALS['dh_embed_mode'] ) ) : ?>
<?php get_footer(); ?>
<?php endif; ?>
