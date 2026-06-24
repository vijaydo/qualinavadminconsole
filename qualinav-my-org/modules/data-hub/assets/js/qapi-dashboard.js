document.addEventListener('DOMContentLoaded', function() {
    const qdConfig = (window.QD_CONFIG && typeof window.QD_CONFIG === 'object') ? window.QD_CONFIG : {};
    const QD_AJAX = (qdConfig.ajax && typeof qdConfig.ajax === 'object')
        ? qdConfig.ajax
        : { url: '', nonce: '' };
    const QD_SAVE_ACTION = (typeof qdConfig.saveAction === 'string' && qdConfig.saveAction)
        ? qdConfig.saveAction
        : 'qaqd_save_report';
    const QD_SAVE_ORG_METRICS_ACTION = (typeof qdConfig.saveOrgMetricsAction === 'string' && qdConfig.saveOrgMetricsAction)
        ? qdConfig.saveOrgMetricsAction
        : 'qaqd_save_org_metrics';
    const QD_ORG = (qdConfig.organization && typeof qdConfig.organization === 'object')
        ? qdConfig.organization
        : { key: '', label: 'My Organization' };
    const QD_ORG_METRICS = (qdConfig.orgMetrics && typeof qdConfig.orgMetrics === 'object')
        ? qdConfig.orgMetrics
        : {};
    const QD_QUALITY_MEASURES = (qdConfig.qualityMeasures && typeof qdConfig.qualityMeasures === 'object')
        ? qdConfig.qualityMeasures
        : { saved: false, groups: [] };
    let QD_MEASURE_GOALS = (qdConfig.measureGoals && typeof qdConfig.measureGoals === 'object')
        ? qdConfig.measureGoals
        : { byKey: {}, byName: {} };
    const QD_REPORT_PDFS = (qdConfig.reportPdfs && typeof qdConfig.reportPdfs === 'object')
        ? qdConfig.reportPdfs
        : {};
    const QD_METRICS_DATA_URL = (typeof qdConfig.metricsDataUrl === 'string' && qdConfig.metricsDataUrl)
        ? qdConfig.metricsDataUrl
        : '';
    const QD_RUN_CHART_URL = (typeof qdConfig.runChartUrl === 'string' && qdConfig.runChartUrl)
        ? qdConfig.runChartUrl
        : '/run-chart/';

    const reportDefinitions = {
        'board': {
            '1. Executive Summary (The "Pulse" Check)': ['CEO Narrative', 'Acute ADC', 'Swing Bed ADC', 'Regulatory Update', 'Strategic Progress'],
            '2. Financial Dashboard (The "Survival" Metrics)': ['Days Cash on Hand', 'Swing Bed ADC (Financial)', 'Swing Bed Conversion Rate', 'AR Days', 'Payor Mix Shift', 'Agency Labor Spend'],
            '3. Quality & Safety (QAPI)': ['Transfer Rate (ER)', 'ER LWBS Rate', 'Patient Experience (HCAHPS)', 'Readmission Rates', 'Infection Control (Zero Harm)'],
            '4. Medical Staff & Credentialing': ['Credentialing Actions', 'Peer Review Summary'],
            '5. Rural Health Clinic (RHC) Performance': ['Visits per Provider/Day', 'RHC No-Show Rate'],
            '6. Human Resources / People': ['RN Turnover Rate', 'Critical Vacancy Rate']
        },
                'committee': {
            '1. Patient Safety & Inpatient (NHSN/HAI)': ['HCP Flu (Staff Vaccination)', 'ASP (Antibiotic Stewardship)', 'Safe Use of Opioids', 'IMM-3 (Vaccination Coverage)', 'CAUTI Rate', 'Falls with Major Injury'],
            '2. Care Transitions (EDTC)': ['EDTC-All (Composite)', 'EDTC-Med (Medications Sent)', 'EDTC-Prov (Note/H&P Sent)'],
            '3. Outpatient & ED Efficiency': ['OP-18 (ED Arrival to Departure)', 'OP-3 (Time to Transfer)', 'OP-22 (Left Without Being Seen)', 'OP-2 (Fibrinolytic Therapy)'],
            '4. Patient Engagement (HCAHPS)': ['H-Comp-1 (Nurse Communication)', 'H-Comp-3 (Staff Style)', 'H-Global (Willingness to Recommend)', 'H-Clean (Cleanliness)', 'SDOH 1+2 (Social Determinants)', 'HWR (Hospital-Wide Readmission)'],
            '5. Swing Bed Quality': ['Functional Gains (Mobility/Self-care)', 'Discharge Disposition (Home/LTC/Acute)', 'Average Length of Stay (ALOS)'],
            '6. Performance Improvement Projects (PIPs)': ['Antibiotic Stewardship Program (PIP)', 'Reduction of Patient Falls (PIP)', 'ER: Throughput Efficiency (PIP)', 'PDSA Cycle Status (Plan-Do-Study-Act)', 'Monthly Interventions Summary'],
            '7. Risk Management & Grievances': ['Patient Grievances (Resolution Status)', 'Incident Reports (Variance Summary)', 'Sentinel Events (Root Cause Analysis)'],
            '8. Infection Control (Monthly)': ['CLABSI Rate', 'CAUTI Rate (Monthly)', 'Hand Hygiene Compliance'],
            '9. Rural Health Clinics (Quarterly)': ['Diabetes Control (A1c > 9)', 'Hypertension Control', 'Depression Screening'],
            '10. Utilization Review (Quarterly)': ['Medical Necessity Denials', 'Peer-to-Peer Review Outcomes'],
            '11. Regulatory & Survey Readiness': ['Mock Survey Findings (Internal Audits)', 'Life Safety (Fire Doors/Generator)']
        },
        'dashboard': {
            'Real-time Metrics': ['Occupancy', 'Emergency Wait', 'Staffing Ratios'],
            'Patient Flow': ['Admission Source', 'Discharge Tracking', 'Transfers']
        },
        'qapi': {
            'Performance Improvement': ['PIP Project Status', 'Data Validation', 'Root Cause'],
            'Survey Readiness': ['Mock Survey Results', 'Plan of Correction']
        }
    };
    const ALL_REPORT_TYPE = '__all_reports';
    const getReportDefinition = (type) => {
        if (type && reportDefinitions[type]) {
            return reportDefinitions[type];
        }
        const merged = {};
        Object.values(reportDefinitions).forEach((def) => {
            Object.keys(def).forEach((cat) => {
                merged[cat] = Array.from(new Set([...(merged[cat] || []), ...(def[cat] || [])]));
            });
        });
        return merged;
    };
    const normalizeReportType = (type) => (type && reportDefinitions[type]) ? type : ALL_REPORT_TYPE;
    const getSelectedReportType = () => normalizeReportType(reportTypeSelect ? reportTypeSelect.value : latestReportType);
    const getReportLabel = (type) => {
        if (reportTypeSelect && reportTypeSelect.selectedOptions && reportTypeSelect.selectedOptions[0]) {
            return reportTypeSelect.selectedOptions[0].text;
        }
        return normalizeReportType(type) === ALL_REPORT_TYPE ? 'All Measures' : String(type || 'All Measures');
    };

    const EMPTY_METRIC_STATE = Object.freeze({
        value: '-',
        benchmark: '-',
        status: '',
        trend: '',
        days_between: null,
        record_count: 0,
        series: []
    });

    const MBQIP_METRICS = [
        'CAH Quality Infrastructure Assessment',
        'CAH global measure',
        'HCP/IMM-3 — Healthcare Personnel Influenza Vaccination',
        'HCP IMM 3',
        'Antibiotic Stewardship',
        'Antibiotic Stewardship Implement',
        'Safe Use of Opioids eCQM — MBQIP Submission',
        'Safe Use of Opioids - Concurrent',
        'HCAHPS Comm with Nurses',
        'HCAHPS Comm with Docs',
        'HCAHPS Restfulness',
        'HCAHPS Care Coordination',
        'HCAHPS Responsiveness',
        'HCAHPS Medicine Comm',
        'HCAHPS Cleanliness',
        'HCAHPS Discharge',
        'HCAHPS Symptoms',
        'HCAHPS Overall Rating',
        'HCAHPS Willingness to Rec',
        'Hybrid Hospital-Wide Readmissio',
        'EDTC',
        'EDTC — Emergency Department Transfer Communication',
        'Median Time from ED',
        'OP-22 Left Without Being Seen'
    ];

    const DEFAULT_BENCHMARKS = Object.freeze({
        'CAH Quality Infrastructure Assessment': '100%',
        'CAH global measure': '>=75%',
        'HCP/IMM-3 — Healthcare Personnel Influenza Vaccination': '100%',
        'HCP IMM 3': '>=75%',
        'Antibiotic Stewardship': '100%',
        'Antibiotic Stewardship Implement': '>=3.5',
        'Safe Use of Opioids eCQM — MBQIP Submission': '16.6%',
        'Safe Use of Opioids - Concurrent': '<=15%',
        'HCAHPS Comm with Nurses': '>=3.5',
        'HCAHPS Comm with Docs': '>=3.5',
        'HCAHPS Restfulness': '>=3.5',
        'HCAHPS Care Coordination': '>=3.5',
        'HCAHPS Responsiveness': '>=3.5',
        'HCAHPS Medicine Comm': '>=3.5',
        'HCAHPS Cleanliness': '>=3.5',
        'HCAHPS Discharge': '>=3.5',
        'HCAHPS Symptoms': '>=3.5',
        'HCAHPS Overall Rating': '>=3.5',
        'HCAHPS Willingness to Rec': '>=3.5',
        'Hybrid Hospital-Wide Readmissio': '>=3.5',
        'EDTC': '>=90%',
        'EDTC — Emergency Department Transfer Communication': '>=90%',
        'Median Time from ED': '<=90 min',
        'OP-22 Left Without Being Seen': '>=3.5',
        'HCP Flu (Staff Vaccination)': '>=90%',
        'ASP (Antibiotic Stewardship)': '100% Compliance',
        'Safe Use of Opioids': '0 Events',
        'IMM-3 (Vaccination Coverage)': '>=90%',
        'CAUTI Rate': 'SIR < 1.0',
        'Falls with Major Injury': '0 Events',
        'EDTC-All (Composite)': '100%',
        'EDTC-Med (Medications Sent)': '100%',
        'EDTC-Prov (Note/H&P Sent)': '100%',
        'OP-18 (ED Arrival to Departure)': '<240 minutes',
        'OP-3 (Time to Transfer)': '<60 minutes',
        'OP-22 (Left Without Being Seen)': '<2%',
        'OP-2 (Fibrinolytic Therapy)': '<=30 minutes',
        'H-Comp-1 (Nurse Communication)': '>=80%',
        'H-Comp-3 (Staff Style)': '>=80%',
        'H-Global (Willingness to Recommend)': '>=70%',
        'H-Clean (Cleanliness)': '>=75%',
        'SDOH 1+2 (Social Determinants)': '>=90%',
        'HWR (Hospital-Wide Readmission)': '<15%',
        'Functional Gains (Mobility/Self-care)': '>=65-70%',
        'Discharge Disposition (Home/LTC/Acute)': '>=70% Home',
        'Average Length of Stay (ALOS)': '<14 days',
        'Antibiotic Stewardship Program (PIP)': '100% Compliance',
        'Reduction of Patient Falls (PIP)': '0 Falls w/ Injury',
        'ER: Throughput Efficiency (PIP)': '<120 minutes',
        'PDSA Cycle Status (Plan-Do-Study-Act)': 'On Track',
        'Monthly Interventions Summary': 'Documented',
        'Patient Grievances (Resolution Status)': '100% Resolved',
        'Incident Reports (Variance Summary)': 'Trending Down',
        'Sentinel Events (Root Cause Analysis)': '0 Events',
        'CLABSI Rate': 'SIR < 1.0',
        'CAUTI Rate (Monthly)': 'SIR < 1.0',
        'Hand Hygiene Compliance': '>=95%',
        'Diabetes Control (A1c > 9)': '<15%',
        'Hypertension Control': '>=60%',
        'Depression Screening': '>=90%',
        'Medical Necessity Denials': '<2-3%',
        'Peer-to-Peer Review Outcomes': '>=80% Overturned',
        'Mock Survey Findings (Internal Audits)': '0 Deficiencies',
        'Life Safety (Fire Drills/Generator)': '100% Compliance',
        'CEO Narrative': 'Documented',
        'Acute ADC': 'Target ADC',
        'Swing Bed ADC': 'Target ADC',
        'Regulatory Update': 'Documented',
        'Strategic Progress': 'On Track',
        'Days Cash on Hand': '>=60 days',
        'Swing Bed ADC (Financial)': 'Target ADC',
        'Swing Bed Conversion Rate': '>=50%',
        'AR Days': '<45 days',
        'Payor Mix Shift': 'Stable',
        'Agency Labor Spend': '<5% Total Labor',
        'Transfer Rate (ER)': '<5%',
        'ER LWBS Rate': '<2%',
        'Patient Experience (HCAHPS)': '>=70%',
        'Readmission Rates': '<15%',
        'Infection Control (Zero Harm)': '0 HAIs',
        'Credentialing Actions': 'Current',
        'Peer Review Summary': 'Documented',
        'Visits per Provider/Day': '>=18',
        'RHC No-Show Rate': '<10%',
        'RN Turnover Rate': '<18%',
        'Critical Vacancy Rate': '<5%',
        'Occupancy': 'Target Occupancy',
        'Emergency Wait': '<30 minutes',
        'Staffing Ratios': 'At Plan',
        'Admission Source': 'Tracked',
        'Discharge Tracking': 'Tracked',
        'Transfers': 'Tracked',
        'PIP Project Status': 'On Track',
        'Data Validation': 'Validated',
        'Root Cause': 'Documented',
        'Mock Survey Results': '0 High-Risk Findings',
        'Plan of Correction': 'Current'
    });

    const createDefaultMetricBenchmarks = () => {
        const metrics = new Set(MBQIP_METRICS);
        Object.values(reportDefinitions).forEach((group) => {
            Object.values(group).forEach((items) => {
                items.forEach((item) => metrics.add(item));
            });
        });
        return Array.from(metrics).reduce((acc, metricName) => {
            acc[metricName] = {
                ...EMPTY_METRIC_STATE,
                benchmark: DEFAULT_BENCHMARKS[metricName] || '-',
                direction: 'higher',
                lower_is_better: false
            };
            return acc;
        }, {});
    };

    const metricBenchmarks = createDefaultMetricBenchmarks();

    const col2 = document.getElementById('elementsCol2');
    const col3 = document.getElementById('elementsCol3');
    const col4Content = document.getElementById('elementsCol4');
    const reportTypeSelect = document.getElementById('qdReportType');
    const reportFocusDropdown = document.getElementById('qdReportFocusDropdown');
    const metricsDropdown = document.getElementById('qdMetricsDropdown');
    const addDataBtn = document.getElementById('addDataBtn');
    const saveStatus = document.getElementById('saveStatus');
    const hasBuilder = !!(col2 && col3 && col4Content);
    const analyticsSelect = document.getElementById('qdFilterAnalytics');
    const coreSetBoard = document.getElementById('qdCoreSetBoard');
    const coreSetReport = document.getElementById('qdCoreSetReport');
    const downloadPdfBtn = document.getElementById('qdDownloadPdf');
    const downloadPngBtn = document.getElementById('qdDownloadPng');
    const servicesRow = document.querySelector('.qd-services-row');
    const servicesList = document.getElementById('qdServicesList');
    const folderReportList = document.getElementById('qdFolderReportList');
    const folderFocusList = document.getElementById('qdFolderFocusList');
    const folderMetricsList = document.getElementById('qdFolderMetricsList');
    const orgScopeBanner = document.getElementById('qdOrgScopeBanner');
    const importMetricCsvBtn = document.getElementById('qdImportMetricCsv');
    const importMetricCsvInput = document.getElementById('qdImportMetricCsvInput');
    const metricEditorName = document.getElementById('qdMetricEditorName');
    const metricEditorValue = document.getElementById('qdMetricEditorValue');
    const metricEditorBenchmark = document.getElementById('qdMetricEditorBenchmark');
    const metricEditorStatus = document.getElementById('qdMetricEditorStatus');
    const metricEditorTrend = document.getElementById('qdMetricEditorTrend');
    const metricEditorSave = document.getElementById('qdMetricEditorSave');
    let latestServiceCategories = [];
    let latestReportType = 'board';
    let serviceSelections = {};
    let activeFolderMetric = '';
    let servicePanelOpen = false;
    let floatingTooltip = null;
    const TREND_HELP_TEXT = 'Trend appears once a measure has at least 3 records. It compares the earliest and latest records up to the current period. For higher-is-better measures, an increase is improving. For lower-is-better measures, a decrease is improving.';

    const normalizeMetricKey = (value) => String(value || '')
        .trim()
        .toLowerCase()
        .replace(/[\u2013\u2014]/g, '-')
        .replace(/[^a-z0-9]+/g, ' ')
        .replace(/\s+/g, ' ');

    const buildMetricsDataUrl = () => {
        if (!QD_METRICS_DATA_URL) return '';
        let saved = {};
        try {
            saved = JSON.parse(localStorage.getItem('qd_state') || '{}');
        } catch (e) {
            saved = {};
        }
        const filters = (saved && saved.filters && typeof saved.filters === 'object') ? saved.filters : {};
        const params = new URLSearchParams();
        const yearEl = document.getElementById('qdFilterYear');
        const hospitalTypeEl = document.getElementById('qdFilterHospitalType');
        const bedSizeEl = document.getElementById('qdFilterBedSize');
        const year = yearEl ? yearEl.value : (filters.qdFilterYear || 'all');
        const hospitalType = hospitalTypeEl ? hospitalTypeEl.value : (filters.qdFilterHospitalType || 'all');
        const bedSize = bedSizeEl ? bedSizeEl.value : (filters.qdFilterBedSize || 'all');

        if (year && year !== 'all') params.set('year', year);
        if (hospitalType && hospitalType !== 'all') params.set('hospital_type', hospitalType);
        if (bedSize && bedSize !== 'all') params.set('bed_size', bedSize);
        params.set('_ts', Date.now().toString());

        return QD_METRICS_DATA_URL
            + (QD_METRICS_DATA_URL.indexOf('?') === -1 ? '?' : '&')
            + params.toString();
    };

    const ensureFloatingTooltip = () => {
        if (floatingTooltip) return floatingTooltip;
        floatingTooltip = document.createElement('div');
        floatingTooltip.className = 'qd-floating-tooltip';
        floatingTooltip.setAttribute('role', 'tooltip');
        floatingTooltip.setAttribute('hidden', '');
        document.body.appendChild(floatingTooltip);
        return floatingTooltip;
    };

    const showFloatingTooltip = (trigger) => {
        if (!trigger) return;
        const text = trigger.getAttribute('data-qtip') || trigger.getAttribute('aria-label') || '';
        if (!text) return;
        const tip = ensureFloatingTooltip();
        tip.textContent = text;
        tip.removeAttribute('hidden');

        const rect = trigger.getBoundingClientRect();
        const tipRect = tip.getBoundingClientRect();
        const gap = 8;
        let left = rect.left + (rect.width / 2) - (tipRect.width / 2);
        left = Math.max(8, Math.min(left, window.innerWidth - tipRect.width - 8));

        let top = rect.bottom + gap;
        if (top + tipRect.height > window.innerHeight - 8) {
            top = rect.top - tipRect.height - gap;
        }
        tip.style.left = `${left}px`;
        tip.style.top = `${Math.max(8, top)}px`;
    };

    const hideFloatingTooltip = () => {
        if (!floatingTooltip) return;
        floatingTooltip.setAttribute('hidden', '');
    };

    const EXTERNAL_METRIC_ALIASES = {
        'cah quality infrastructure assessment': 'CAH global measure',
        'cah global measure': 'CAH Quality Infrastructure Assessment',
        'hcp imm-3 healthcare personnel influenza vaccination': 'HCP IMM 3',
        'hcp imm 3 healthcare personnel influenza vaccination': 'HCP IMM 3',
        'hcp imm 3': 'HCP/IMM-3 — Healthcare Personnel Influenza Vaccination',
        'antibiotic stewardship': 'Antibiotic Stewardship Implement',
        'antibiotic stewardship implement': 'Antibiotic Stewardship Implemen',
        'safe use of opioids ecqm mbqip submission': 'Safe Use of Opioids - Concurrent',
        'safe use of opioids concurrent': 'Safe Use of Opioids - Concurrent',
        'safe use of opioids - concurrent': 'Safe Use of Opioids - Concurrent',
        'edtc': 'EDTC — Emergency Department Transfer Communication',
        'edtc emergency department transfer communication': 'EDTC — Emergency Department Transfer Communication',
        'median time from ed': 'OP-18 — Median ED Arrival to Departure Time (Discharged Patients)',
        'op 22 left without being seen': 'OP-22 Left Without Being Seen',
        'op-22 left without being seen': 'OP-22 Left Without Being Seen',
    };

    const loadExternalMetricsData = async (opts) => {
        if (!QD_METRICS_DATA_URL) return;
        // Always cache-bust: the live metrics endpoint reflects current data
        // (files can be deleted), so a stale browser-cached response must never
        // be reused — that made deleted metrics appear to "still be there".
        const url = buildMetricsDataUrl();
        if (!url) return;
        try {
            const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) return;
            const payload = await response.json();
            const externalMetrics = (payload && payload.metrics && typeof payload.metrics === 'object')
                ? payload.metrics
                : null;
            if (!externalMetrics) return;
            if (payload && payload.measure_goals && typeof payload.measure_goals === 'object') {
                QD_MEASURE_GOALS = payload.measure_goals;
            }
            const strictLiveMode = (
                String(QD_METRICS_DATA_URL).indexOf('action=qaqd_live_metrics') !== -1
                || (payload && typeof payload.generated_from === 'string' && payload.generated_from.indexOf('dm_org_folder_files_') === 0)
            );

            const externalByNormalized = {};
            Object.keys(externalMetrics).forEach((key) => {
                externalByNormalized[normalizeMetricKey(key)] = externalMetrics[key];
            });

            Object.keys(externalMetrics).forEach((metricName) => {
                const record = externalMetrics[metricName];
                if (!record || typeof record !== 'object') return;
                const current = metricBenchmarks[metricName] || {};
                metricBenchmarks[metricName] = {
                    ...EMPTY_METRIC_STATE,
                    ...current,
                    value: record.value || current.value || '-',
                    benchmark: record.benchmark || current.benchmark || '-',
                    measure_key: record.measure_key || current.measure_key || '',
                    direction: record.direction || current.direction || 'higher',
                    lower_is_better: !!(record.lower_is_better ?? current.lower_is_better),
                    status: record.status || current.status || '',
                    trend: record.trend || current.trend || '',
                    days_between: record.days_between ?? current.days_between ?? null,
                    record_count: record.record_count ?? current.record_count ?? 0,
                    series: Array.isArray(record.series) ? record.series : (Array.isArray(current.series) ? current.series : []),
                    national_comparison: record.national_comparison || current.national_comparison || 'Not uploaded',
                    state_comparison: record.state_comparison || current.state_comparison || 'Not uploaded',
                    reporting_orgs: record.reporting_orgs ?? current.reporting_orgs ?? 0,
                    below_count: record.below_count ?? current.below_count ?? 0,
                    near_count: record.near_count ?? current.near_count ?? 0,
                    above_count: record.above_count ?? current.above_count ?? 0
                };
            });

            Object.keys(metricBenchmarks).forEach((metricName) => {
                // In strict live mode, Data Management uploads are authoritative.
                if (!strictLiveMode && Object.prototype.hasOwnProperty.call(QD_ORG_METRICS, metricName)) {
                    return;
                }
                const normalized = normalizeMetricKey(metricName);
                const alias = EXTERNAL_METRIC_ALIASES[normalized];
                const record = externalByNormalized[normalized]
                    || (alias ? (externalMetrics[alias] || externalByNormalized[normalizeMetricKey(alias)]) : null);
                const hasData = !!(record && record.value && record.value !== '-');

                if (!hasData) {
                    if (strictLiveMode) {
                        metricBenchmarks[metricName] = {
                            ...metricBenchmarks[metricName],
                            value: '-',
                            benchmark: record && record.benchmark ? record.benchmark : metricBenchmarks[metricName].benchmark,
                            measure_key: record && record.measure_key ? record.measure_key : metricBenchmarks[metricName].measure_key,
                            direction: record && record.direction ? record.direction : metricBenchmarks[metricName].direction,
                            lower_is_better: record && Object.prototype.hasOwnProperty.call(record, 'lower_is_better') ? !!record.lower_is_better : !!metricBenchmarks[metricName].lower_is_better,
                            status: '',
                            trend: '',
                            days_between: null,
                            record_count: record && record.record_count ? record.record_count : 0,
                            series: record && Array.isArray(record.series) ? record.series : [],
                            national_comparison: record && record.national_comparison ? record.national_comparison : 'Not uploaded',
                            state_comparison: record && record.state_comparison ? record.state_comparison : 'Not uploaded',
                            reporting_orgs: record && record.reporting_orgs ? record.reporting_orgs : 0,
                            below_count: record && record.below_count ? record.below_count : 0,
                            near_count: record && record.near_count ? record.near_count : 0,
                            above_count: record && record.above_count ? record.above_count : 0
                        };
                    }
                    return;
                }

                metricBenchmarks[metricName] = {
                    ...metricBenchmarks[metricName],
                    value: record.value || metricBenchmarks[metricName].value,
                    benchmark: record.benchmark || metricBenchmarks[metricName].benchmark,
                    measure_key: record.measure_key || metricBenchmarks[metricName].measure_key,
                    direction: record.direction || metricBenchmarks[metricName].direction || 'higher',
                    lower_is_better: !!(record.lower_is_better ?? metricBenchmarks[metricName].lower_is_better),
                    status: record.status || metricBenchmarks[metricName].status,
                    trend: record.trend || metricBenchmarks[metricName].trend,
                    days_between: record.days_between ?? metricBenchmarks[metricName].days_between ?? null,
                    record_count: record.record_count ?? metricBenchmarks[metricName].record_count ?? 0,
                    series: Array.isArray(record.series) ? record.series : (Array.isArray(metricBenchmarks[metricName].series) ? metricBenchmarks[metricName].series : []),
                    national_comparison: record.national_comparison || metricBenchmarks[metricName].national_comparison || 'Not uploaded',
                    state_comparison: record.state_comparison || metricBenchmarks[metricName].state_comparison || 'Not uploaded',
                    reporting_orgs: record.reporting_orgs ?? metricBenchmarks[metricName].reporting_orgs ?? 0,
                    below_count: record.below_count ?? metricBenchmarks[metricName].below_count ?? 0,
                    near_count: record.near_count ?? metricBenchmarks[metricName].near_count ?? 0,
                    above_count: record.above_count ?? metricBenchmarks[metricName].above_count ?? 0
                };
            });
        } catch (err) {
            // Keep dashboard usable even when external data file is unavailable.
        }
    };

    Object.keys(QD_ORG_METRICS).forEach((metricName) => {
        const override = QD_ORG_METRICS[metricName];
        if (!override || typeof override !== 'object') return;
        metricBenchmarks[metricName] = {
            ...(metricBenchmarks[metricName] || {}),
            value: override.value || '',
            benchmark: override.benchmark || '',
            measure_key: override.measure_key || '',
            direction: override.direction || 'higher',
            lower_is_better: !!override.lower_is_better,
            status: override.status || '',
            trend: override.trend || '',
            days_between: override.days_between ?? null,
            record_count: override.record_count ?? 0
        };
    });

    const persistOrgMetrics = async () => {
        const payload = {};
        Object.keys(metricBenchmarks).forEach((name) => {
            payload[name] = {
                value: metricBenchmarks[name].value || '',
                benchmark: metricBenchmarks[name].benchmark || '',
                direction: metricBenchmarks[name].direction || 'higher',
                lower_is_better: !!metricBenchmarks[name].lower_is_better,
                status: metricBenchmarks[name].status || '',
                trend: metricBenchmarks[name].trend || ''
            };
        });
        const body = new URLSearchParams({
            action: QD_SAVE_ORG_METRICS_ACTION,
            nonce: QD_AJAX.nonce,
            metrics: JSON.stringify(payload)
        });
        const res = await fetch(QD_AJAX.url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body
        });
        const json = await res.json();
        if (!json || !json.success) {
            const message = (json && json.data && json.data.message) ? json.data.message : 'Could not save organization metrics';
            throw new Error(message);
        }
    };

    const setMetricEditor = (metricName) => {
        activeFolderMetric = metricName || '';
        if (!metricEditorName || !metricEditorValue || !metricEditorBenchmark || !metricEditorStatus || !metricEditorTrend) return;
        if (!activeFolderMetric) {
            metricEditorName.value = '';
            metricEditorValue.value = '';
            metricEditorBenchmark.value = '';
            metricEditorStatus.value = 'yellow';
            metricEditorTrend.value = 'stable';
            return;
        }
        const bm = metricBenchmarks[activeFolderMetric] || {};
        metricEditorName.value = activeFolderMetric;
        metricEditorValue.value = bm.value || '';
        metricEditorBenchmark.value = bm.benchmark || '';
        metricEditorStatus.value = bm.status || 'yellow';
        metricEditorTrend.value = bm.trend || 'stable';
    };

    const renderFolderSidebar = (type, focusOptions, selectedFocusIds, scopedCategories) => {
        if (orgScopeBanner) {
            orgScopeBanner.textContent = `Organization data scope: ${QD_ORG.label || 'My Organization'}`;
        }

        if (folderReportList && reportTypeSelect) {
            folderReportList.innerHTML = Array.from(reportTypeSelect.options).map((opt) => `
                <button type="button" class="qd-folder-item ${opt.value === type ? 'is-active' : ''}" data-folder-report="${opt.value}">
                    <i class="fas fa-folder${opt.value === type ? '-open' : ''}"></i>
                    <span>${opt.text}</span>
                </button>
            `).join('');
        }

        if (folderFocusList) {
            folderFocusList.innerHTML = focusOptions.map((opt) => `
                <button type="button" class="qd-folder-item ${selectedFocusIds.includes(opt.id) ? 'is-active' : ''}" data-folder-focus="${opt.id}">
                    <i class="fas fa-folder-open"></i>
                    <span>${opt.label}</span>
                </button>
            `).join('');
        }

        if (folderMetricsList) {
            const metricItems = [];
            scopedCategories.forEach(({ cat, items }) => {
                metricItems.push(`<div class="qd-folder-subtitle">${cat}</div>`);
                items.forEach((item) => {
                    metricItems.push(`
                        <button type="button" class="qd-folder-item qd-folder-metric ${activeFolderMetric === item ? 'is-active' : ''}" data-folder-metric="${item}">
                            <i class="fas fa-file-medical"></i>
                            <span>${item}</span>
                        </button>
                    `);
                });
            });
            folderMetricsList.innerHTML = metricItems.join('') || '<div class="qd-folder-empty">No metrics in current scope.</div>';
        }
    };

    const runChartSection = document.getElementById('qdRunChartSection');

    const syncAnalyticsPanel = () => {
        if (!coreSetBoard) return;
        const analyticsValue = (analyticsSelect && analyticsSelect.value) ? analyticsSelect.value : 'Dashboard';
        const isRunChart = analyticsValue === 'Run Chart';
        const showCoreSet = !isRunChart;
        coreSetBoard.classList.toggle('is-visible', showCoreSet);

        // Toggle run chart section
        if (runChartSection) {
            runChartSection.style.display = isRunChart ? 'block' : 'none';
            if (isRunChart) renderRunCharts();
        }

        // Hide overview + services when showing run charts
        const overviewBoard = document.querySelector('.qd-overview-board');
        const servicesSection = document.querySelector('.qd-services-row');
        if (overviewBoard) overviewBoard.style.display = isRunChart ? 'none' : '';
        if (servicesSection) servicesSection.style.display = isRunChart ? 'none' : '';
    };

    const qdChartParseNumber = (value) => {
        const parsed = parseFloat(String(value == null ? '' : value).replace(/[^0-9.\-]/g, ''));
        return Number.isFinite(parsed) ? parsed : NaN;
    };

    const qdChartValueLabel = (value, unit = '') => {
        const number = Number(value);
        if (!Number.isFinite(number)) return '-';
        if (unit === 'min') return `${number.toFixed(0)} min`;
        return `${number.toFixed(1)}%`;
    };

    const qdChartUnit = (metricRecord = {}) => {
        const context = [
            metricRecord.value,
            metricRecord.benchmark,
            metricRecord.national_comparison,
            metricRecord.state_comparison
        ].join(' ').toLowerCase();
        return context.includes('min') ? 'min' : '%';
    };

    const qdChartMedian = (values) => {
        const sorted = values.filter(Number.isFinite).sort((a, b) => a - b);
        if (!sorted.length) return NaN;
        const mid = Math.floor(sorted.length / 2);
        return sorted.length % 2 ? sorted[mid] : ((sorted[mid - 1] + sorted[mid]) / 2);
    };

    const qdGoalLookup = (measureName, metricRecord = {}) => {
        const normalizeGoalKey = (value) => String(value || '')
            .toLowerCase()
            .replace(/&amp;/g, '&')
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
        const byKey = (QD_MEASURE_GOALS.byKey && typeof QD_MEASURE_GOALS.byKey === 'object') ? QD_MEASURE_GOALS.byKey : {};
        const byName = (QD_MEASURE_GOALS.byName && typeof QD_MEASURE_GOALS.byName === 'object') ? QD_MEASURE_GOALS.byName : {};
        const keyCandidates = [
            metricRecord.measure_key,
            normalizeMetricKey(metricRecord.measure_key || ''),
            normalizeGoalKey(metricRecord.measure_key || ''),
            normalizeMetricKey(measureName),
            normalizeGoalKey(measureName)
        ].filter(Boolean);
        for (const key of keyCandidates) {
            if (byKey[key]) return byKey[key];
            if (byName[key]) return byName[key];
        }
        const nameCandidates = [
            measureName,
            'CAH global measure',
            'CAH Quality Infrastructure Assessment'
        ].map(normalizeGoalKey);
        for (const key of nameCandidates) {
            if (byName[key]) return byName[key];
        }
        return null;
    };

    const qdComparisonLineValue = (currentValue, comparisonValue, metricRecord = {}) => {
        const current = Number(currentValue);
        const delta = qdChartParseNumber(comparisonValue);
        if (!Number.isFinite(current) || !Number.isFinite(delta)) return NaN;
        const lowerIsBetter = !!metricRecord.lower_is_better;
        return lowerIsBetter ? current + delta : current - delta;
    };

    const RUN_CHART_CANONICAL_KEYS = {
        'cah quality infrastructure assessment': 'cah-quality-infrastructure-assessment',
        'cah global measure': 'cah-quality-infrastructure-assessment',
        'hcp imm 3': 'hcp-imm-3',
        'hcp imm-3 healthcare personnel influenza vaccination': 'hcp-imm-3',
        'hcp imm 3 healthcare personnel influenza vaccination': 'hcp-imm-3',
        'antibiotic stewardship': 'antibiotic-stewardship',
        'antibiotic stewardship implement': 'antibiotic-stewardship',
        'antibiotic stewardship implemen': 'antibiotic-stewardship',
        'safe use of opioids ecqm mbqip submission': 'safe-use-of-opioids',
        'safe use of opioids concurrent': 'safe-use-of-opioids',
        'safe use of opioids - concurrent': 'safe-use-of-opioids',
        'edtc': 'edtc',
        'edtc emergency department transfer communication': 'edtc',
        'median time from ed': 'op-18',
        'op 18 median ed arrival to departure time discharged patients': 'op-18',
        'op-18 median ed arrival to departure time discharged patients': 'op-18',
        'op 22 left without being seen': 'op-22',
        'op-22 left without being seen': 'op-22',
        'op 22 patient left without being seen lwbs rate': 'op-22',
        'op-22 patient left without being seen lwbs rate': 'op-22'
    };

    const qdCanonicalRunChartKey = (value) => {
        const normalized = normalizeMetricKey(value);
        return RUN_CHART_CANONICAL_KEYS[normalized] || normalized;
    };

    const qdRunChartKey = (metricName, metricRecord = {}) => {
        const candidates = [
            metricRecord.measure_key,
            metricName,
            EXTERNAL_METRIC_ALIASES[normalizeMetricKey(metricName)]
        ].filter(Boolean);
        for (const candidate of candidates) {
            const normalized = normalizeMetricKey(candidate);
            if (RUN_CHART_CANONICAL_KEYS[normalized]) {
                return RUN_CHART_CANONICAL_KEYS[normalized];
            }
        }
        return qdCanonicalRunChartKey(candidates[0] || metricName);
    };

    const qdRunChartSeries = (metricRecord = {}) => (
        Array.isArray(metricRecord.series)
            ? metricRecord.series
                .map((point, idx) => ({
                    label: String(point.label || `P${idx + 1}`).trim(),
                    value: qdChartParseNumber(point.value),
                    num: point.num,
                    den: point.den
                }))
                .filter(point => Number.isFinite(point.value))
            : []
    );

    const qdRunChartTitle = (metricName) => (
        normalizeMetricKey(metricName) === normalizeMetricKey('CAH Quality Infrastructure Assessment')
            ? 'CAH Quality Infrastructure Criteria Met Rate'
            : metricName
    );

    const qdTooltipValueLines = (point, metricName, metricRecord = {}) => {
        const unit = qdChartUnit(metricRecord);
        const isCahMeasure = normalizeMetricKey(metricName) === normalizeMetricKey('CAH Quality Infrastructure Assessment');
        const numeratorLabel = isCahMeasure ? 'Criteria Met' : 'Numerator';
        const denominatorLabel = isCahMeasure ? 'Criteria Count' : 'Denominator';
        return [
            point.label,
            `Value: ${qdChartValueLabel(point.value, unit)}`,
            ...(String(point.num ?? '').trim() !== '' ? [`${numeratorLabel}: ${point.num}`] : []),
            ...(String(point.den ?? '').trim() !== '' ? [`${denominatorLabel}: ${point.den}`] : [])
        ];
    };

    const qdDrawPointTooltip = (ctx, point, cssWidth, cssHeight) => {
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
        if (x + width > cssWidth - 8) x = point.x - width - 14;
        if (y < 8) y = point.y + 14;
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
    };

    const qdDrawDataHubRunChart = (canvas, metricName, metricRecord, hoverIndex = null) => {
        if (!canvas || !metricRecord) return [];
        const series = qdRunChartSeries(metricRecord);
        if (!series.length) return [];

        const wrap = canvas.parentElement;
        const cssWidth = Math.max(520, wrap ? wrap.clientWidth : canvas.clientWidth || 900);
        const cssHeight = wrap ? wrap.clientHeight || 320 : canvas.clientHeight || 320;
        const ratio = window.devicePixelRatio || 1;
        canvas.width = Math.round(cssWidth * ratio);
        canvas.height = Math.round(cssHeight * ratio);
        canvas.style.width = `${cssWidth}px`;
        canvas.style.height = `${cssHeight}px`;

        const ctx = canvas.getContext('2d');
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.clearRect(0, 0, cssWidth, cssHeight);
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, cssWidth, cssHeight);

        const unit = qdChartUnit(metricRecord);
        const values = series.map(point => point.value);
        const benchmarkValue = qdChartParseNumber(metricRecord.benchmark);
        const goal = qdGoalLookup(metricName, metricRecord);
        const goalValue = goal ? qdChartParseNumber(goal.goal_rate) : NaN;
        const currentValue = values[values.length - 1];
        const nationalComparisonValue = qdComparisonLineValue(currentValue, metricRecord.national_comparison, metricRecord);
        const stateComparisonValue = qdComparisonLineValue(currentValue, metricRecord.state_comparison, metricRecord);
        const median = qdChartMedian(values);
        const references = [
            ...values,
            ...(Number.isFinite(benchmarkValue) ? [benchmarkValue] : []),
            ...(Number.isFinite(goalValue) ? [goalValue] : []),
            ...(Number.isFinite(nationalComparisonValue) ? [nationalComparisonValue] : []),
            ...(Number.isFinite(stateComparisonValue) ? [stateComparisonValue] : [])
        ];
        const rawMin = Math.min(...references, 0);
        const rawMax = Math.max(...references, 0);
        const tickStep = 5;
        const yMin = Math.max(0, Math.floor((rawMin - tickStep) / tickStep) * tickStep);
        const yMax = Math.min(100, Math.ceil((rawMax + tickStep) / tickStep) * tickStep || 100);
        const yRange = Math.max(1, yMax - yMin);
        const pad = { top: 28, right: 72, bottom: 48, left: 56 };
        const plotW = cssWidth - pad.left - pad.right;
        const plotH = cssHeight - pad.top - pad.bottom;
        const xFor = (idx) => pad.left + (series.length === 1 ? plotW / 2 : (idx / (series.length - 1)) * plotW);
        const yFor = (value) => pad.top + ((yMax - value) / yRange) * plotH;

        ctx.strokeStyle = '#e5e7eb';
        ctx.lineWidth = 1;
        ctx.fillStyle = '#64748b';
        ctx.font = '13px Inter, system-ui, -apple-system, sans-serif';
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';
        for (let i = 0; i <= 5; i++) {
            const value = yMin + (yRange / 5) * i;
            const y = yFor(value);
            ctx.beginPath();
            ctx.moveTo(pad.left, y);
            ctx.lineTo(cssWidth - pad.right, y);
            ctx.stroke();
            ctx.fillText(qdChartValueLabel(value, unit), pad.left - 12, y);
        }

        ctx.strokeStyle = '#cbd5e1';
        ctx.beginPath();
        ctx.moveTo(pad.left, pad.top);
        ctx.lineTo(pad.left, cssHeight - pad.bottom);
        ctx.lineTo(cssWidth - pad.right, cssHeight - pad.bottom);
        ctx.stroke();

        const referenceLines = [
            {
                value: median,
                label: `Median ${qdChartValueLabel(median, unit)}`,
                color: '#64748b',
                stroke: '#94a3b8',
                dash: [7, 7]
            },
            ...(Number.isFinite(benchmarkValue) ? [{
                value: benchmarkValue,
                label: `National benchmark ${qdChartValueLabel(benchmarkValue, unit)}`,
                color: '#166534',
                stroke: '#16a34a',
                dash: [4, 6]
            }] : []),
            ...(Number.isFinite(goalValue) ? [{
                value: goalValue,
                label: `Current goal ${qdChartValueLabel(goalValue, unit)}`,
                color: '#92400e',
                stroke: '#f59e0b',
                dash: [6, 5]
            }] : []),
            ...(Number.isFinite(nationalComparisonValue) ? [{
                value: nationalComparisonValue,
                label: `Nat. comparison ${qdChartValueLabel(nationalComparisonValue, unit)}`,
                color: '#6d28d9',
                stroke: '#8b5cf6',
                dash: [3, 5]
            }] : []),
            ...(Number.isFinite(stateComparisonValue) ? [{
                value: stateComparisonValue,
                label: `State comparison ${qdChartValueLabel(stateComparisonValue, unit)}`,
                color: '#0f766e',
                stroke: '#14b8a6',
                dash: [2, 6]
            }] : [])
        ].filter(line => Number.isFinite(line.value)).map(line => ({ ...line, y: yFor(line.value) }));

        referenceLines.forEach(line => {
            ctx.strokeStyle = line.stroke;
            ctx.setLineDash(line.dash);
            ctx.beginPath();
            ctx.moveTo(pad.left, line.y);
            ctx.lineTo(cssWidth - pad.right, line.y);
            ctx.stroke();
        });
        ctx.setLineDash([]);

        let previousLabelY = -Infinity;
        referenceLines.sort((a, b) => a.y - b.y).forEach(line => {
            let labelY = line.y - 6;
            if (labelY - previousLabelY < 18) labelY = previousLabelY + 18;
            labelY = Math.min(labelY, cssHeight - pad.bottom - 4);
            previousLabelY = labelY;
            ctx.fillStyle = line.color;
            ctx.textAlign = 'right';
            ctx.textBaseline = 'bottom';
            ctx.fillText(line.label, cssWidth - pad.right - 8, labelY);
        });

        ctx.strokeStyle = '#285a7d';
        ctx.lineWidth = 4;
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';
        ctx.beginPath();
        series.forEach((point, idx) => {
            const x = xFor(idx);
            const y = yFor(point.value);
            if (idx === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        });
        ctx.stroke();

        const hitPoints = series.map((point, idx) => {
            const x = xFor(idx);
            const y = yFor(point.value);
            ctx.fillStyle = '#285a7d';
            ctx.beginPath();
            ctx.arc(x, y, 6, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth = 2;
            ctx.stroke();
            return {
                index: idx,
                x,
                y,
                tooltipLines: qdTooltipValueLines(point, metricName, metricRecord)
            };
        });

        ctx.fillStyle = '#475569';
        ctx.font = '13px Inter, system-ui, -apple-system, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';
        let previousLabelX = -Infinity;
        series.forEach((point, idx) => {
            const x = xFor(idx);
            const isEdge = idx === 0 || idx === series.length - 1;
            if (!isEdge && x - previousLabelX < 64) return;
            ctx.fillText(point.label, x, cssHeight - pad.bottom + 18);
            previousLabelX = x;
        });

        if (hoverIndex !== null && hitPoints[hoverIndex]) {
            qdDrawPointTooltip(ctx, hitPoints[hoverIndex], cssWidth, cssHeight);
        }
        return hitPoints;
    };

    const qdBindCahRunChart = (canvas, metricName, metricRecord) => {
        if (!canvas || canvas.dataset.qdCahTooltipBound === '1') return;
        canvas.dataset.qdCahTooltipBound = '1';
        let hoverIndex = null;
        let hitPoints = qdDrawDataHubRunChart(canvas, metricName, metricRecord, hoverIndex);
        const pointFromEvent = (event) => {
            const source = event.touches && event.touches.length ? event.touches[0] : event;
            const rect = canvas.getBoundingClientRect();
            const pointer = { x: source.clientX - rect.left, y: source.clientY - rect.top };
            let nearest = null;
            let nearestDistance = Infinity;
            hitPoints.forEach(point => {
                const dx = point.x - pointer.x;
                const dy = point.y - pointer.y;
                const distance = Math.sqrt((dx * dx) + (dy * dy));
                if (distance <= 14 && distance < nearestDistance) {
                    nearest = point;
                    nearestDistance = distance;
                }
            });
            return nearest;
        };
        const updateHover = (event) => {
            const nearest = pointFromEvent(event);
            const nextIndex = nearest ? nearest.index : null;
            canvas.style.cursor = nearest ? 'pointer' : 'default';
            if (nextIndex === hoverIndex) return;
            hoverIndex = nextIndex;
            hitPoints = qdDrawDataHubRunChart(canvas, metricName, metricRecord, hoverIndex);
        };
        const clearHover = () => {
            if (hoverIndex === null) return;
            hoverIndex = null;
            canvas.style.cursor = 'default';
            hitPoints = qdDrawDataHubRunChart(canvas, metricName, metricRecord, hoverIndex);
        };
        canvas.addEventListener('mousemove', updateHover);
        canvas.addEventListener('click', updateHover);
        canvas.addEventListener('touchstart', updateHover, { passive: true });
        canvas.addEventListener('mouseleave', clearHover);
        canvas.addEventListener('touchend', () => window.setTimeout(clearHover, 1800), { passive: true });
        window.addEventListener('resize', () => {
            hitPoints = qdDrawDataHubRunChart(canvas, metricName, metricRecord, hoverIndex);
        });
    };

    const qdDownloadCahChart = (canvas, metricName) => {
        if (!canvas) return;
        const link = document.createElement('a');
        link.download = `${String(metricName || 'run-chart').toLowerCase().replace(/[^a-z0-9]+/g, '-')}-run-chart.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    };

    const qdCopyCahChart = async (canvas) => {
        if (!canvas || !navigator.clipboard || typeof ClipboardItem === 'undefined') return;
        canvas.toBlob(async (blob) => {
            if (!blob) return;
            await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
        }, 'image/png');
    };

    const qdRunChartInsightHtml = (badge, headline, whats, why, evidence, action) => (
        '<div class="qd-run-insight-card">' +
            '<div class="qd-run-insight-head">' +
                '<span class="qd-run-insight-badge qd-run-insight-badge--' + badge.tone + '">' + badge.label + '</span>' +
                '<span class="qd-run-insight-title">' + headline + '</span>' +
            '</div>' +
            '<div class="qd-run-insight-body">' +
                '<p><strong>What\u2019s happening:</strong> ' + whats + '</p>' +
                '<p><strong>Why this matters:</strong> ' + why + '</p>' +
                '<p><strong>Evidence behind this conclusion:</strong></p>' +
                '<ul>' + evidence.map(function(e) { return '<li>' + e + '</li>'; }).join('') + '</ul>' +
                '<p><strong>Suggested action:</strong> ' + action + '</p>' +
            '</div>' +
        '</div>'
    );

    /* ---- RUN CHART RENDERER (uses the Data Hub canvas chart style) ---- */
    function renderRunCharts() {
        var grid = document.getElementById('qdRunChartGrid');
        if (!grid) return;

        var metrics = metricBenchmarks;
        var chartItemsByKey = {};
        Object.keys(metrics).forEach(function(metricName) {
            var metricRecord = metrics[metricName];
            var series = qdRunChartSeries(metricRecord);
            if (!metricRecord || !series.length) return;
            var key = qdRunChartKey(metricName, metricRecord);
            var item = { metricName: metricName, metricRecord: metricRecord, series: series, key: key };
            var existing = chartItemsByKey[key];
            if (
                !existing
                || item.series.length > existing.series.length
                || (item.series.length === existing.series.length && item.metricName.length > existing.metricName.length)
            ) {
                chartItemsByKey[key] = item;
            }
        });
        var chartItems = Object.keys(chartItemsByKey).map(function(key) {
            return chartItemsByKey[key];
        });

        if (!chartItems.length) {
            grid.innerHTML = '<div style="text-align:center;padding:40px;color:#64748b;">No metric data available. Upload data to generate run charts.</div>';
            return;
        }

        grid.innerHTML = '';

        chartItems.forEach(function(item, mi) {
            var metricName = item.metricName;
            var m = item.metricRecord;
            var points = item.series.map(function(point) { return +(point.value).toFixed(2); });
            var chartTitle = qdRunChartTitle(metricName);

            // Median
            var sorted = [...points].sort(function(a, b) { return a - b; });
            var midIdx = Math.floor(sorted.length / 2);
            var med = sorted.length % 2 !== 0 ? sorted[midIdx] : +((sorted[midIdx - 1] + sorted[midIdx]) / 2).toFixed(2);

            // SPC — reuse dttc logic
            var shiftLen = 0, shiftSide = 0, maxShift = 0, maxShiftSide = 0;
            for (var si = 0; si < points.length; si++) {
                var side = points[si] > med ? 1 : (points[si] < med ? -1 : 0);
                if (side === 0) continue;
                if (side === shiftSide) { shiftLen++; } else { shiftLen = 1; shiftSide = side; }
                if (shiftLen > maxShift) { maxShift = shiftLen; maxShiftSide = shiftSide; }
            }
            var shiftDetected = maxShift >= 6;

            var trendUp = 0, trendDown = 0, maxTrendUp = 0, maxTrendDown = 0;
            for (var ti = 1; ti < points.length; ti++) {
                if (points[ti] > points[ti - 1]) { trendUp++; trendDown = 0; }
                else if (points[ti] < points[ti - 1]) { trendDown++; trendUp = 0; }
                else { trendUp = 0; trendDown = 0; }
                if (trendUp > maxTrendUp) maxTrendUp = trendUp;
                if (trendDown > maxTrendDown) maxTrendDown = trendDown;
            }
            var trendUpDetected = maxTrendUp >= 5;
            var trendDownDetected = maxTrendDown >= 5;

            var runsCount = 1;
            for (var ri = 1; ri < points.length; ri++) {
                if ((points[ri] >= med) !== (points[ri - 1] >= med)) runsCount++;
            }
            var nNonMedian = points.filter(function(v) { return v !== med; }).length;
            var runsExpLow = Math.max(1, Math.round(nNonMedian * 0.35));
            var runsExpHigh = Math.round(nNonMedian * 0.65);

            // Verdict (same priority as dttc.js)
            var badge = { label: 'Stable', tone: 'neutral' };
            var headline = 'Stable performance (random variation)';
            var whats = 'The data fluctuates around a typical level with no strong evidence of sustained change.';
            var why = 'Patterns observed are consistent with normal variation rather than a structural change.';
            var action = 'No immediate action required. Continue to monitor and avoid reacting to single points.';

            if ((shiftDetected && maxShiftSide === -1) || trendDownDetected) {
                badge = { label: 'Deteriorating', tone: 'negative' };
                headline = 'Sustained deterioration detected';
                whats = 'Values show a consistent downward movement and remain below the typical level.';
                why = 'This pattern is unlikely to be caused by chance alone and suggests the process has genuinely worsened.';
                action = 'Investigate what changed around the start of the decline and intervene to stabilise performance.';
            } else if ((shiftDetected && maxShiftSide === 1) || trendUpDetected) {
                badge = { label: 'Improving', tone: 'positive' };
                headline = 'Sustained improvement detected';
                whats = 'The data has moved to a higher level and continues to increase over time.';
                why = 'This pattern is very unlikely to occur by chance alone and suggests a meaningful positive change.';
                action = 'Identify what changed at the start of the improvement and consider reinforcing those factors.';
            }

            // Evidence
            var evidence = [];
            if (shiftDetected) {
                evidence.push('Shift: ' + maxShift + ' consecutive points ' + (maxShiftSide === 1 ? 'above' : 'below') + ' the median (rule: 6+).');
            } else {
                evidence.push('Shift: not detected (need 6+ consecutive points above or below the median).');
            }
            if (trendUpDetected) {
                evidence.push('Trend: ' + maxTrendUp + ' consecutive increases (rule: 5+; ties are ignored).');
            } else if (trendDownDetected) {
                evidence.push('Trend: ' + maxTrendDown + ' consecutive decreases (rule: 5+; ties are ignored).');
            } else {
                evidence.push('Trend: not detected (need 5+ consecutive increases or decreases; ties are ignored).');
            }
            evidence.push('Runs: within the expected range (' + runsCount + ' runs; expected ' + runsExpLow + '\u2013' + runsExpHigh + ').');
            evidence.push('Outliers: no unusual points flagged.');
            evidence.push('Reference median used for analysis: ' + med.toFixed(1) + '.');

            // Build card using dttc CSS classes
            var canvasId = 'qdRcCanvas_' + mi;
            var card = document.createElement('div');
            card.className = 'qd-rc-card';
            var insightHtml = qdRunChartInsightHtml(badge, headline, whats, why, evidence, action);
            card.innerHTML =
                '<div class="dm-raw-chart-head" style="margin-bottom:12px;">' +
                    '<h4 class="dm-raw-chart-title" style="margin:0;">' + chartTitle + '</h4>' +
                    '<div class="dm-raw-chart-actions">' +
                        '<button type="button" class="dm-raw-chart-download dm-raw-chart-icon-btn qd-cah-copy" aria-label="Copy chart image" title="Copy image"><i class="fas fa-copy"></i></button>' +
                        '<button type="button" class="dm-raw-chart-download dm-raw-chart-icon-btn qd-cah-download" aria-label="Download chart PNG" title="Download PNG"><i class="fas fa-download"></i></button>' +
                    '</div>' +
                '</div>' +
                '<div class="dm-raw-chart-canvas-wrap" style="height:320px;"><canvas id="' + canvasId + '" class="dm-raw-chart-canvas"></canvas></div>' +
                insightHtml;
            grid.appendChild(card);
            var canvas = document.getElementById(canvasId);
            qdBindCahRunChart(canvas, metricName, m);
            var copyButton = card.querySelector('.qd-cah-copy');
            var downloadButton = card.querySelector('.qd-cah-download');
            if (copyButton) copyButton.addEventListener('click', function() { qdCopyCahChart(canvas); });
            if (downloadButton) downloadButton.addEventListener('click', function() { qdDownloadCahChart(canvas, metricName); });
        });
    }

        const committeeFocusMap = [
        { id: 'all', label: 'All Focus Areas', categories: [] },
        { id: 'patient-safety', label: '1. Patient Safety & Inpatient', categories: ['1. Patient Safety & Inpatient (NHSN/HAI)'] },
        { id: 'edtc', label: '2. Care Transitions (EDTC)', categories: ['2. Care Transitions (EDTC)'] },
        { id: 'outpatient-ed', label: '3. Outpatient & ED Efficiency', categories: ['3. Outpatient & ED Efficiency'] },
        { id: 'hcahps', label: '4. Patient Engagement (HCAHPS)', categories: ['4. Patient Engagement (HCAHPS)'] },
        { id: 'swing-bed', label: '5. Swing Bed Quality', categories: ['5. Swing Bed Quality'] },
        { id: 'pips', label: '6. Performance Improvement Projects', categories: ['6. Performance Improvement Projects (PIPs)'] },
        { id: 'risk-management', label: '7. Risk Management & Grievances', categories: ['7. Risk Management & Grievances'] },
        { id: 'infection-control', label: '8. Infection Control (Monthly)', categories: ['8. Infection Control (Monthly)'] },
        { id: 'rural-health', label: '9. Rural Health Clinics (Quarterly)', categories: ['9. Rural Health Clinics (Quarterly)'] },
        { id: 'utilization-review', label: '10. Utilization Review (Quarterly)', categories: ['10. Utilization Review (Quarterly)'] },
        { id: 'regulatory', label: '11. Regulatory & Survey Readiness', categories: ['11. Regulatory & Survey Readiness'] },
    ];

    const getFocusOptions = (type) => {
        if (normalizeReportType(type) === ALL_REPORT_TYPE) {
            return [{ id: 'all', label: 'All Focus Areas', categories: [] }];
        }
        if (type === 'committee') {
            return committeeFocusMap;
        }
        const def = getReportDefinition(type);
        const cats = Object.keys(def);
        return [
            { id: 'all', label: 'All Focus Areas', categories: [] },
            ...cats.map((cat) => ({
                id: cat.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''),
                label: cat,
                categories: [cat]
            }))
        ];
    };

    const renderCheckboxDropdown = (dropdownEl, options, selectedValues, allValue, fallbackLabel, allowEmpty = false) => {
        if (!dropdownEl) return [allValue];
        const selectedIds = Array.isArray(selectedValues) ? selectedValues : [selectedValues || allValue];
        const validSelected = selectedIds.filter((id) => options.some(opt => opt.id === id));
        const finalSelected = validSelected.length ? validSelected : (allowEmpty ? [] : [allValue]);
        const triggerLabel = dropdownEl.querySelector('.qd-multi-label');
        const menu = dropdownEl.querySelector('.qd-multi-menu');
        if (!menu || !triggerLabel) return finalSelected;

        menu.innerHTML = options.map(opt => `
            <label class="qd-multi-option">
                <input type="checkbox" value="${opt.id}" ${finalSelected.includes(opt.id) ? 'checked' : ''}>
                <span>${opt.label}</span>
            </label>
        `).join('');

        const selectedLabels = options
            .filter(opt => finalSelected.includes(opt.id) && opt.id !== allValue)
            .map(opt => opt.label);
        triggerLabel.textContent = finalSelected.includes(allValue)
            ? fallbackLabel
            : (selectedLabels.length ? selectedLabels.join(', ') : (allowEmpty ? 'None selected' : fallbackLabel));

        return finalSelected;
    };

    const getSelectedValues = (dropdownEl) => {
        if (!dropdownEl) return [];
        return Array.from(dropdownEl.querySelectorAll('input[type="checkbox"]:checked')).map((el) => el.value);
    };

    const MBQIP_METRIC_GROUPS = [
        {
            id: 'global-measures',
            label: 'Global Measures',
            categories: []
        },
        {
            id: 'patient-safety',
            label: 'Patient Safety',
            categories: ['Patient Safety & Inpatient (NHSN/HAI)']
        },
        {
            id: 'patient-experience',
            label: 'Patient Experience',
            categories: ['Patient Engagement (HCAHPS)']
        },
        {
            id: 'care-coordination',
            label: 'Care Coordination',
            categories: ['Care Transitions (EDTC)']
        },
        {
            id: 'emergency-department',
            label: 'Emergency Department',
            categories: ['Outpatient & ED Efficiency']
        }
    ];

    const MBQIP_SERVICE_DEFINITIONS = [
        { id: 'patient-safety', cat: '1. Patient Safety & Inpatient (NHSN/HAI)', items: ['HCP Flu (Staff Vaccination)', 'ASP (Antibiotic Stewardship)', 'Safe Use of Opioids', 'IMM-3 (Vaccination Coverage)', 'CAUTI Rate', 'Falls with Major Injury'] },
        { id: 'edtc', cat: '2. Care Transitions (EDTC)', items: ['EDTC-All (Composite)', 'EDTC-Med (Medications Sent)', 'EDTC-Prov (Note/H&P Sent)'] },
        { id: 'outpatient-ed', cat: '3. Outpatient & ED Efficiency', items: ['OP-18 (ED Arrival to Departure)', 'OP-3 (Time to Transfer)', 'OP-22 (Left Without Being Seen)', 'OP-2 (Fibrinolytic Therapy)'] },
        { id: 'hcahps', cat: '4. Patient Engagement (HCAHPS)', items: ['H-Comp-1 (Nurse Communication)', 'H-Comp-3 (Staff Style)', 'H-Global (Willingness to Recommend)', 'H-Clean (Cleanliness)', 'SDOH 1+2 (Social Determinants)', 'HWR (Hospital-Wide Readmission)'] },
        { id: 'swing-bed', cat: '5. Swing Bed Quality', items: ['Functional Gains (Mobility/Self-care)', 'Discharge Disposition (Home/LTC/Acute)', 'Average Length of Stay (ALOS)'] },
        { id: 'pips', cat: '6. Performance Improvement Projects (PIPs)', items: ['Antibiotic Stewardship Program (PIP)', 'Reduction of Patient Falls (PIP)', 'ER: Throughput Efficiency (PIP)', 'PDSA Cycle Status (Plan-Do-Study-Act)', 'Monthly Interventions Summary'] },
        { id: 'risk-management', cat: '7. Risk Management & Grievances', items: ['Patient Grievances (Resolution Status)', 'Incident Reports (Variance Summary)', 'Sentinel Events (Root Cause Analysis)'] },
        { id: 'infection-control', cat: '8. Infection Control (Monthly)', items: ['CLABSI Rate', 'CAUTI Rate (Monthly)', 'Hand Hygiene Compliance'] },
        { id: 'rural-health', cat: '9. Rural Health Clinics (Quarterly)', items: ['Diabetes Control (A1c > 9)', 'Hypertension Control', 'Depression Screening'] },
        { id: 'utilization-review', cat: '10. Utilization Review (Quarterly)', items: ['Medical Necessity Denials', 'Peer-to-Peer Review Outcomes'] },
        { id: 'regulatory', cat: '11. Regulatory & Survey Readiness', items: ['Mock Survey Findings (Internal Audits)', 'Life Safety (Fire Doors/Generator)'] }
    ];

    const getMetricOptions = (type, allowedCategories = null, selectedFocusIds = []) => {
        type = normalizeReportType(type);
        const def = getReportDefinition(type);
        let categories = Object.keys(def);
        if (Array.isArray(allowedCategories) && allowedCategories.length) {
            const allowedSet = new Set(allowedCategories);
            categories = categories.filter((cat) => allowedSet.has(cat));
        }

        const mbqipOnly = type === 'committee'
            && Array.isArray(selectedFocusIds)
            && selectedFocusIds.length === 1
            && selectedFocusIds[0] === 'mbqip';
        if (mbqipOnly) {
            return [
                { id: 'all', label: 'All Metrics', category: null, categories: [] },
                ...MBQIP_METRIC_GROUPS.map((g) => ({
                    id: g.id,
                    label: g.label,
                    category: null,
                    categories: g.categories.filter((cat) => categories.includes(cat))
                }))
            ];
        }

        return [
            { id: 'all', label: 'All Metrics', category: null, categories: [] },
            ...categories.map((cat) => ({
                id: cat.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''),
                label: cat,
                category: cat,
                categories: [cat]
            }))
        ];
    };

    const populateMetricOptions = (type, selectedMetricIds, allowedCategories = null, selectedFocusIds = []) => {
        const options = getMetricOptions(type, allowedCategories, selectedFocusIds);
        return renderCheckboxDropdown(metricsDropdown, options, selectedMetricIds, 'all', 'All Metrics', true);
    };

    const populateFocusOptions = (type, selectedFocusId) => {
        const options = getFocusOptions(type);
        return renderCheckboxDropdown(reportFocusDropdown, options, selectedFocusId, 'all', 'All Focus Areas', true);
    };

    const updateOverview = (items) => {
        const total = items.length;
        const greens = items.filter(m => m.status === 'green').length;
        const yellows = items.filter(m => m.status === 'yellow').length;
        const reds = items.filter(m => m.status === 'red').length;
        const pct = total ? Math.round((greens / total) * 100) : 0;

        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        };

        setText('qdKpiTotal', total.toString());
        setText('qdKpiTotalSub', total ? 'Using your selected quality measures' : 'No metrics match current filters');
        setText('qdKpiGreen', greens.toString());
        setText('qdKpiGreenSub', `${pct}% green`);
        setText('qdKpiYellow', yellows.toString());
        setText('qdKpiYellowSub', `${yellows} yellow status`);
        setText('qdKpiRed', reds.toString());
        setText('qdKpiRedSub', `${reds} red status`);

        const statusBars = document.getElementById('qdStatusBars');
        if (statusBars) {
            const bars = statusBars.querySelectorAll('span');
            const values = [greens, yellows, reds];
            const max = Math.max(1, ...values);
            bars.forEach((bar, i) => {
                const heightPct = Math.max(8, Math.round((values[i] / max) * 100));
                bar.style.height = `${heightPct}%`;
            });
        }

        const trendLine = document.getElementById('qdTrendLine');
        const trendArea = document.getElementById('qdTrendArea');
        const trendPoints = document.getElementById('qdTrendPoints');
        const trendBase = document.getElementById('qdTrendBase');
        const trendLabels = document.getElementById('qdTrendLabels');
        const trendCurrent = document.getElementById('qdTrendCurrent');
        const trendAvg = document.getElementById('qdTrendAvg');
        const trendDirection = document.getElementById('qdTrendDirection');
        const trendCaption = document.getElementById('qdTrendCaption');
        if (trendLine && trendBase) {
            const weights = { green: 3, yellow: 2, red: 1 };
            const chunks = Math.max(6, Math.min(10, total || 6));
            const pointValues = [];
            for (let i = 0; i < chunks; i++) {
                const sample = items.filter((_, idx) => idx % chunks === i);
                const avg = sample.length
                    ? sample.reduce((sum, m) => sum + (weights[m.status] || 2), 0) / sample.length
                    : 2;
                pointValues.push(avg);
            }
            const xStart = 20;
            const xEnd = 500;
            const yMin = 40;
            const yMax = 140;
            const xStep = (xEnd - xStart) / Math.max(1, pointValues.length - 1);
            const points = pointValues.map((val, i) => {
                const norm = (val - 1) / 2;
                const y = yMax - (norm * (yMax - yMin));
                return `${Math.round(xStart + (i * xStep))},${Math.round(y)}`;
            }).join(' ');
            trendLine.setAttribute('points', points);
            if (trendArea) {
                trendArea.setAttribute('points', `${points} ${xEnd},${yMax} ${xStart},${yMax}`);
            }
            trendBase.setAttribute('y1', `${yMax}`);
            trendBase.setAttribute('y2', `${yMax}`);

            if (trendPoints) {
                trendPoints.innerHTML = pointValues.map((val, i) => {
                    const x = Math.round(xStart + (i * xStep));
                    const norm = (val - 1) / 2;
                    const y = Math.round(yMax - (norm * (yMax - yMin)));
                    return `<circle cx="${x}" cy="${y}" r="4" fill="#9b6cc4" stroke="#ffffff" stroke-width="1.5"></circle>`;
                }).join('');
            }

            if (trendLabels) {
                trendLabels.innerHTML = pointValues.map((_, i) => `<span>P${i + 1}</span>`).join('');
                trendLabels.style.gridTemplateColumns = `repeat(${pointValues.length}, minmax(0, 1fr))`;
            }

            const current = pointValues.length ? pointValues[pointValues.length - 1] : 2;
            const recent = pointValues.slice(-3);
            const avgRecent = recent.length ? (recent.reduce((s, n) => s + n, 0) / recent.length) : current;
            const prev = pointValues.length > 1 ? pointValues[pointValues.length - 2] : current;
            const direction = current > prev ? 'improving' : (current < prev ? 'declining' : 'stable');

            if (trendCurrent) trendCurrent.textContent = current.toFixed(1);
            if (trendAvg) trendAvg.textContent = avgRecent.toFixed(1);
            if (trendDirection) trendDirection.textContent = direction;
            if (trendCaption) trendCaption.textContent = `Rolling quality score across ${chunks} recent reporting periods (higher is better).`;
        }
    };

    const updateServices = (serviceCategories) => {
        if (!servicesList) return;
        if (!Array.isArray(serviceCategories) || serviceCategories.length === 0) {
            servicesList.innerHTML = '<div class="qd-services-toggle-row"><span class="qd-services-empty">No quality measures are in scope for the current filters.</span></div>';
            return;
        }

        const selectionSummary = serviceCategories.reduce((acc, group) => {
            const childGroups = Array.isArray(group.children) ? group.children : [];
            const addItems = (cat, items) => {
                const sel = serviceSelections[cat];
                (Array.isArray(items) ? items : []).forEach((item) => {
                    acc.total += 1;
                    if (sel && sel.enabled && sel.items && sel.items[item]) {
                        acc.selected += 1;
                    }
                });
            };
            if (childGroups.length) {
                childGroups.forEach((child) => addItems(child.cat, child.items));
            } else {
                addItems(group.cat, group.items);
            }
            return acc;
        }, { selected: 0, total: 0 });

        const renderMeasureList = (cat, items) => `
            <ul class="qd-service-metrics">
                ${items.map((item) => `
                    <li>
                        <label class="qd-service-item">
                            <input type="checkbox" class="qd-service-metric-toggle" data-cat="${cat}" data-item="${item}" ${serviceSelections[cat] && serviceSelections[cat].items && serviceSelections[cat].items[item] ? 'checked' : ''}>
                            <span>${item}</span>
                        </label>
                    </li>
                `).join('')}
            </ul>
        `;

        const renderChildGroup = (parentCat, child) => {
            const childCat = child.cat || '';
            const childItems = Array.isArray(child.items) ? child.items : [];
            return `
                <details class="qd-service-child-card" open>
                    <summary class="qd-service-child-summary">
                        <label class="qd-service-head-toggle">
                            <input type="checkbox" class="qd-service-section-toggle" data-cat="${childCat}" data-parent-cat="${parentCat}" data-id="${child.id || ''}" ${serviceSelections[childCat] && serviceSelections[childCat].enabled ? 'checked' : ''}>
                            <span class="qd-service-title">${childCat} (${childItems.length})</span>
                        </label>
                    </summary>
                    ${renderMeasureList(childCat, childItems)}
                </details>
            `;
        };

        const serviceCards = serviceCategories.map(({ id, cat, items, children }, idx) => {
            const childGroups = Array.isArray(children) ? children : [];
            const childCount = childGroups.reduce((sum, child) => sum + (Array.isArray(child.items) ? child.items.length : 0), 0);
            const directItems = Array.isArray(items) ? items : [];
            const totalItems = childGroups.length ? childCount : directItems.length;
            return `
            <details class="qd-service-card" open>
                <summary class="qd-service-summary">
                    <label class="qd-service-head-toggle">
                        <input type="checkbox" class="qd-service-section-toggle" data-cat="${cat}" data-id="${id || ''}" ${serviceSelections[cat] && serviceSelections[cat].enabled ? 'checked' : ''}>
                        <span class="qd-service-title">${idx + 1}. ${cat} (${totalItems})</span>
                    </label>
                </summary>
                ${childGroups.length ? `<div class="qd-service-children">${childGroups.map((child) => renderChildGroup(cat, child)).join('')}</div>` : renderMeasureList(cat, directItems)}
            </details>
            `;
        }).join('');

        servicesList.innerHTML = `
            <div class="qd-services-toggle-row">
                <div class="qd-services-toggle-copy">
                    <span class="qd-services-count">${selectionSummary.selected}/${selectionSummary.total} measures selected</span>
                </div>
                <button type="button" class="qd-services-toggle" aria-expanded="${servicePanelOpen ? 'true' : 'false'}" aria-controls="qdServicesPanel">
                    <span>${servicePanelOpen ? 'Hide' : 'Show'} measures</span>
                    <span class="qd-services-chevron" aria-hidden="true"></span>
                </button>
            </div>
            <div id="qdServicesPanel" class="qd-services-panel ${servicePanelOpen ? 'is-open' : ''}" ${servicePanelOpen ? '' : 'hidden'}>
                ${serviceCards}
            </div>
        `;
    };

    const ensureServiceSelections = (serviceCategories) => {
        const next = {};
        const addGroup = ({ id, cat, items }, defaultEnabled = true) => {
            const prev = serviceSelections[cat];
            const itemMap = {};
            (Array.isArray(items) ? items : []).forEach((item) => {
                itemMap[item] = prev && prev.items && Object.prototype.hasOwnProperty.call(prev.items, item)
                    ? !!prev.items[item]
                    : true;
            });
            const enabled = Object.values(itemMap).some(Boolean) && defaultEnabled && (prev ? prev.enabled !== false : true);
            next[cat] = { id: id || (prev && prev.id) || '', enabled, items: itemMap };
        };
        serviceCategories.forEach(({ id, cat, items, children }) => {
            const childGroups = Array.isArray(children) ? children : [];
            addGroup({ id, cat, items: Array.isArray(items) ? items : [] }, true);
            childGroups.forEach((child) => addGroup(child, serviceSelections[cat] ? serviceSelections[cat].enabled !== false : true));
            if (childGroups.length) {
                next[cat].enabled = childGroups.some((child) => next[child.cat] && next[child.cat].enabled);
            }
        });
        serviceSelections = next;
    };

    const configuredQualityMeasureCategories = () => {
        const groups = Array.isArray(QD_QUALITY_MEASURES.groups) ? QD_QUALITY_MEASURES.groups : [];
        const normalizeGroup = (group) => ({
            id: String(group.id || '').trim(),
            cat: String(group.cat || '').trim(),
            items: Array.isArray(group.items)
                ? group.items.map((item) => String(item || '').trim()).filter(Boolean)
                : [],
            children: Array.isArray(group.children)
                ? group.children.map(normalizeGroup).filter((child) => child.cat && (child.items.length || child.children.length))
                : []
        });
        return groups
            .map(normalizeGroup)
            .filter((group) => group.cat && (group.items.length || group.children.length));
    };

    const getFilteredServiceCategories = () => {
        return latestServiceCategories
            .map(({ id, cat, items, children }) => {
                const sel = serviceSelections[cat];
                if (!sel || !sel.enabled) return null;
                const childGroups = Array.isArray(children) ? children : [];
                if (childGroups.length) {
                    const chosenChildren = childGroups
                        .map((child) => {
                            const childSel = serviceSelections[child.cat];
                            if (!childSel || !childSel.enabled) return null;
                            const chosen = (Array.isArray(child.items) ? child.items : []).filter((item) => childSel.items && childSel.items[item]);
                            if (!chosen.length) return null;
                            return { id: child.id, cat: child.cat, items: chosen };
                        })
                        .filter(Boolean);
                    if (!chosenChildren.length) return null;
                    return {
                        id,
                        cat,
                        items: chosenChildren.flatMap((child) => child.items),
                        children: chosenChildren,
                    };
                }
                const chosen = (Array.isArray(items) ? items : []).filter((item) => sel.items && sel.items[item]);
                if (!chosen.length) return null;
                return { id, cat, items: chosen };
            })
            .filter(Boolean);
    };

    const syncMetricsFromServices = () => {
        const activeType = reportTypeSelect ? reportTypeSelect.value : latestReportType;
        const filteredCategories = getFilteredServiceCategories();
        const selectedIds = filteredCategories.map((c) => c.id).filter(Boolean);
        const availableIds = latestServiceCategories.map((c) => c.id).filter(Boolean);
        if (!availableIds.length) return;

        let metricsSelection = selectedIds;
        if (selectedIds.length && selectedIds.length === availableIds.length) {
            metricsSelection = ['all', ...selectedIds];
        }

        const state = JSON.parse(localStorage.getItem('qd_state') || '{"selections":{}}');
        state.filters = state.filters || {};
        state.filters.qdFilterMetrics = metricsSelection;
        localStorage.setItem('qd_state', JSON.stringify(state));
        render(activeType);
    };

    const updateFromServiceSelections = () => {
        const filteredCategories = getFilteredServiceCategories();
        const scopedMetricsData = [];
        filteredCategories.forEach(({ cat, items }) => {
            items.forEach((item) => {
                const bm = metricBenchmarks[item] || {};
                scopedMetricsData.push({
                    name: item,
                    category: cat,
                    status: bm.status || 'yellow',
                    trend: bm.trend || 'stable'
                });
            });
        });
        updateOverview(scopedMetricsData);
        updateCoreSetReport(filteredCategories, latestReportType);
    };

    const updateCoreSetReport = (serviceCategories, reportType) => {
        if (!coreSetReport) return;
        if (!Array.isArray(serviceCategories) || serviceCategories.length === 0) {
            coreSetReport.innerHTML = '<div class="qd-core-empty">No core measure data for current filters.</div>';
            return;
        }

        const reportLabel = getReportLabel(reportType);
        const now = new Date();
        const period = now.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        const analyticsMode = (analyticsSelect && analyticsSelect.value) ? analyticsSelect.value : 'Dashboard';

        const statusClass = (status) => {
            if (!status) return 'qd-stat-neutral';
            if (status === 'green') return 'qd-stat-green';
            if (status === 'red') return 'qd-stat-red';
            return 'qd-stat-yellow';
        };
        const statusScore = (status, seed) => {
            const base = status === 'green' ? 4.2 : (status === 'yellow' ? 3.0 : 1.8);
            const jitter = ((seed % 7) - 3) * 0.1;
            const val = Math.max(0, Math.min(5, base + jitter));
            return val.toFixed(1);
        };
        const seededSegments = (seed, status) => {
            const base = status === 'green'
                ? [5, 10, 20, 30, 35]
                : (status === 'yellow' ? [8, 18, 30, 28, 16] : [20, 30, 28, 16, 6]);
            const jittered = base.map((v, i) => {
                const delta = ((seed + (i * 3)) % 5) - 2;
                return Math.max(2, v + delta);
            });
            const totalSeg = jittered.reduce((s, n) => s + n, 0) || 100;
            const normalized = jittered.map((n) => Math.round((n / totalSeg) * 100));
            const diff = 100 - normalized.reduce((s, n) => s + n, 0);
            normalized[normalized.length - 1] += diff;
            return normalized;
        };
        const seedFromText = (text) => String(text || '').split('').reduce((s, ch) => s + ch.charCodeAt(0), 0);
        const trendClass = (trend) => {
            if (!trend) return 'qd-trend-missing';
            if (trend === 'improving') return 'qd-trend-up';
            if (trend === 'declining') return 'qd-trend-down';
            return 'qd-trend-flat';
        };
        const cleanComparisonValue = (val) => {
            const text = String(val || '').trim();
            return (!text || text === 'Not uploaded' || text === 'N/A') ? '-' : text;
        };
        const comparisonValues = (bm) => ({
            national: cleanComparisonValue(bm && bm.national_comparison),
            state: cleanComparisonValue(bm && bm.state_comparison),
            days: 'N/A'
        });
        const comparisonClass = (val) => {
            const text = String(val || '').trim();
            if (text === 'Not uploaded' || text === 'N/A' || text === '-') return 'qd-compare-missing';
            if (text.startsWith('-')) return 'qd-compare-neg';
            if (text === '0%' || text === '0') return 'qd-compare-neutral';
            return 'qd-compare-pos';
        };
        const valueBadge = (text, status, isBenchmark = false) => {
            const cls = isBenchmark ? 'qd-value-neutral' : 'qd-value-neutral';
            return `<span class="qd-value-badge ${cls}">${text}</span>`;
        };
        const compactMetricLabel = (text) => {
            const raw = String(text || '').trim();
            if (!raw || raw === '-') return raw || '-';
            const match = raw.match(/^(-?\d+(?:\.\d+)?)(.*)$/);
            if (!match) return raw;
            const number = Number(match[1]);
            if (!Number.isFinite(number)) return raw;
            const suffix = match[2] || '';
            const formatted = Number.isInteger(number)
                ? String(number)
                : String(parseFloat(number.toFixed(1)));
            return `${formatted}${suffix}`;
        };
        const benchmarkDisplayLabel = (text) => {
            const raw = String(text || '').trim();
            if (!raw || raw === '-') return raw || '-';
            return compactMetricLabel(raw.replace(/^(>=|<=|>|<)\s*/, ''));
        };
        const hasMetricData = (bm) => {
            const value = String((bm && bm.value) || '').trim();
            return value !== '' && value !== '-';
        };
        const statusBadge = (status) => {
            if (!status) return '<span class="qd-stat-pill qd-stat-neutral">-</span>';
            return `<span class="qd-stat-pill ${statusClass(status)}">${status.toUpperCase()}</span>`;
        };
        const metricDirection = (metricRecord = {}, goal = null) => {
            if (String(metricRecord.direction || '').toLowerCase() === 'lower' || metricRecord.lower_is_better === true) {
                return 'lower';
            }
            if (String(metricRecord.direction || '').toLowerCase() === 'higher') {
                return 'higher';
            }
            return String((goal && goal.direction) || 'higher').toLowerCase() === 'lower' ? 'lower' : 'higher';
        };
        const goalGapLabel = (valueText, goalText, goal, metricRecord = {}) => {
            const current = parseMetricNumber(valueText);
            const target = parseMetricNumber(goalText);
            if (current === null || target === null) return '-';
            const direction = metricDirection(metricRecord, goal);
            const context = `${valueText || ''} ${goalText || ''}`.toLowerCase();
            const unit = context.includes('%') ? '%' : (context.includes('min') ? ' min' : (context.includes('day') ? ' days' : ''));
            const formatGap = (gap) => {
                const rounded = Math.abs(gap) >= 10 ? Math.round(Math.abs(gap)) : parseFloat(Math.abs(gap).toFixed(1));
                return `${rounded}${unit}`;
            };
            const tolerance = unit === '%' ? 0.1 : 0.01;
            if (direction === 'lower') {
                if (current <= target) {
                    const gap = target - current;
                    return gap <= tolerance ? '0%' : `+${formatGap(gap)}`;
                }
                return `-${formatGap(current - target)}`;
            }
            if (current >= target) {
                const gap = current - target;
                return gap <= tolerance ? '0%' : `+${formatGap(gap)}`;
            }
            return `-${formatGap(target - current)}`;
        };
        const statusGoalBadge = (status, goalText, valueText, goal, metricRecord = {}) => {
            if (!status) return '<span class="qd-stat-pill qd-stat-neutral">-</span>';
            const gap = goalGapLabel(valueText, goalText, goal, metricRecord);
            return `<span class="qd-stat-pill ${statusClass(status)}">${gap}</span>`;
        };
        const trendBadge = (trend) => {
            if (!trend) {
                return '<span class="qd-trend-badge qd-trend-missing" title="Trend requires at least 3 records">-</span>';
            }
            const cls = trend === 'improving' ? 'qd-trend-up' : (trend === 'declining' ? 'qd-trend-down' : 'qd-trend-flat');
            return `<span class="qd-trend-badge ${cls}" title="${trend}"></span>`;
        };
        const scoreFromMetric = (value, status, seed) => {
            const text = String(value || '').trim();
            const num = parseFloat(text.replace(/[^0-9.\-]/g, ''));
            if (!Number.isNaN(num)) {
                if (text.includes('%')) return Math.max(0, Math.min(5, num / 20));
                if (/day|min/i.test(text)) return Math.max(0, Math.min(5, 5 - (num / 30)));
                if (num <= 5) return Math.max(0, Math.min(5, num));
            }
            return parseFloat(statusScore(status, seed));
        };
        const parseMetricNumber = (text) => {
            const raw = String(text || '').trim();
            if (!raw || raw === '-') return null;
            const num = parseFloat(raw.replace(/[^0-9.\-]/g, ''));
            return Number.isNaN(num) ? null : num;
        };
        const parseBenchmarkTarget = (text) => {
            const raw = String(text || '').trim();
            if (!raw || raw === '-') return null;
            const num = parseMetricNumber(raw);
            if (num === null) return null;
            let direction = 'gte';
            if (raw.includes('<=' ) || raw.includes('<')) direction = 'lte';
            if (raw.includes('min')) direction = 'lte';
            return { target: num, direction };
        };
        const goalNameKey = (value) => String(value || '')
            .trim()
            .toLowerCase()
            .replace(/[\u2013\u2014/:]+/g, '-')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
        const goalLookup = (measureName, metricRecord = {}) => {
            const byKey = (QD_MEASURE_GOALS.byKey && typeof QD_MEASURE_GOALS.byKey === 'object')
                ? QD_MEASURE_GOALS.byKey
                : {};
            const byName = (QD_MEASURE_GOALS.byName && typeof QD_MEASURE_GOALS.byName === 'object')
                ? QD_MEASURE_GOALS.byName
                : {};
            const measureKey = String(metricRecord.measure_key || '').trim();
            if (measureKey && byKey[measureKey]) return byKey[measureKey];
            const keyCandidates = measureKey
                ? [
                    measureKey,
                    measureKey.replace(/-/g, '_'),
                    measureKey.replace(/_/g, '-'),
                    `hacs_hais_${measureKey}`,
                    `hacs_hais_${measureKey.replace(/-/g, '_')}`
                ].filter(Boolean)
                : [];
            for (const key of keyCandidates) {
                if (byKey[key]) return byKey[key];
            }
            const candidates = [
                goalNameKey(measureName),
                goalNameKey(`HACs & HAIs — ${measureName}`)
            ].filter(Boolean);
            for (const key of candidates) {
                if (byName[key]) return byName[key];
            }
            return null;
        };
        const goalLabel = (goal, valueText, benchmarkText) => {
            if (!goal) return '-';
            const rawGoal = goal.goal_rate;
            if (rawGoal === '' || rawGoal === null || rawGoal === undefined || Number.isNaN(Number(rawGoal))) return '-';
            const value = Number(rawGoal);
            const rounded = Number.isInteger(value) ? String(value) : String(parseFloat(value.toFixed(2)));
            const context = `${valueText || ''} ${benchmarkText || ''}`.toLowerCase();
            if (context.includes('%')) return `${rounded}%`;
            if (context.includes('min')) return `${rounded} min`;
            if (context.includes('day')) return `${rounded} days`;
            return rounded;
        };
        const statusFromGoal = (valueText, goal, metricRecord = {}) => {
            if (!goal) return '';
            const current = parseMetricNumber(valueText);
            const target = parseMetricNumber(goal.goal_rate);
            if (current === null || target === null) return '';
            const direction = metricDirection(metricRecord, goal);
            const tolerance = target === 0 ? 0.5 : Math.abs(target) * 0.1;
            if (direction === 'lower') {
                if (current <= target) return 'green';
                if (current <= target + tolerance) return 'yellow';
                return 'red';
            }
            if (current >= target) return 'green';
            if (current >= target - tolerance) return 'yellow';
            return 'red';
        };
        const benchmarkGapScore = (valueText, benchmarkText, status) => {
            const value = parseMetricNumber(valueText);
            const benchmark = parseBenchmarkTarget(benchmarkText);
            if (value === null || !benchmark) {
                return status === 'red' ? 1 : (status === 'yellow' ? 0.5 : 0);
            }
            const base = Math.abs(benchmark.target) || 1;
            if (benchmark.direction === 'lte') {
                return Math.max(0, (value - benchmark.target) / base);
            }
            return Math.max(0, (benchmark.target - value) / base);
        };
        const derivePriorityScore = (valueText, benchmarkText, status, trend) => {
            const value = parseMetricNumber(valueText);
            const benchmark = parseBenchmarkTarget(benchmarkText);
            if (value !== null) {
                if (benchmark && benchmark.direction === 'lte') {
                    const target = Math.abs(benchmark.target) || 1;
                    if (value <= benchmark.target) return 100;
                    const overTarget = ((value - benchmark.target) / target) * 100;
                    return Math.max(0, Math.min(100, 100 - overTarget));
                }
                return Math.max(0, Math.min(100, value));
            }
            const statusBase = status === 'red' ? 28 : (status === 'yellow' ? 58 : 86);
            const trendAdj = trend === 'declining' ? -8 : (trend === 'stable' ? 0 : 6);
            return Math.max(0, Math.min(100, statusBase + trendAdj));
        };
        const priorityTone = (score) => {
            if (score <= 44) return 'high';
            if (score <= 74) return 'medium';
            return 'low';
        };
        const priorityLabel = (score) => {
            if (score <= 44) return 'Critical action';
            if (score <= 74) return 'Prioritize';
            return 'Monitor';
        };
        const comparativeScore = (valueText, benchmarkText, status) => {
            const value = parseMetricNumber(valueText);
            const benchmark = parseBenchmarkTarget(benchmarkText);
            if (value === null || !benchmark || !benchmark.target) {
                if (status === 'green') return 4.2;
                if (status === 'yellow') return 2.8;
                if (status === 'red') return 1.2;
                return null;
            }
            let ratio;
            if (benchmark.direction === 'lte') {
                ratio = value <= 0 ? 1 : (benchmark.target / value);
            } else {
                ratio = value / benchmark.target;
            }
            return Math.max(0, Math.min(5, ratio * 5));
        };
        const parseComparisonNumber = (valueText) => {
            if (!valueText || /not uploaded/i.test(valueText)) return null;
            return parseMetricNumber(valueText);
        };
        const prioritySummaryLabel = (score) => {
            if (score <= 44) return 'Critical Priority';
            if (score <= 74) return 'Medium Priority';
            return 'Low Priority';
        };
        const benchmarkSummary = (valueText, benchmarkText, status) => {
            const gap = benchmarkGapScore(valueText, benchmarkText, status);
            if (gap <= 0) return 'On or better than target';
            return `${Math.round(gap * 100)}% away from target`;
        };
        const benchmarkDistribution = (bm) => {
            const reporting = Number(bm.reporting_orgs || 0);
            const below = Number(bm.below_count || 0);
            const near = Number(bm.near_count || 0);
            const above = Number(bm.above_count || 0);
            if (reporting <= 0) {
                return { reporting, below, near, above, belowPct: 0, nearPct: 0, abovePct: 0 };
            }
            return {
                reporting,
                below,
                near,
                above,
                belowPct: (below / reporting) * 100,
                nearPct: (near / reporting) * 100,
                abovePct: (above / reporting) * 100
            };
        };

        const renderMeasureTable = (items) => {
            const rows = items.map((item) => {
                const bm = metricBenchmarks[item] || {};
                const value = compactMetricLabel(bm.value || '-');
                const benchmark = compactMetricLabel(bm.benchmark || '-');
                const benchmarkDisplay = benchmarkDisplayLabel(benchmark);
                const hasData = hasMetricData(bm);
                const goal = goalLookup(item, bm);
                const goalStatus = hasData ? statusFromGoal(value, goal, bm) : '';
                const status = goalStatus || '';
                const displayedGoal = goalLabel(goal, value, benchmark);
                const trend = hasData && Number(bm.record_count || 0) >= 3 ? (bm.trend || '') : '';
                const comp = comparisonValues(bm);
                return `
                    <tr>
                        <td>${item}</td>
                        <td class="qd-num-col qd-current-col">${valueBadge(value, status, false)}</td>
                        <td class="qd-trend-col"><span class="qd-trend ${trendClass(trend)}">${trendBadge(trend)}</span></td>
                        <td class="qd-num-col qd-goal-col">${valueBadge(displayedGoal, status, true)}</td>
                        <td class="qd-status-col">${statusGoalBadge(status, displayedGoal, value, goal, bm)}</td>
                        <td class="qd-num-col qd-benchmark-col">${valueBadge(benchmarkDisplay, status, true)}</td>
                        <td class="qd-compare-col"><span class="qd-compare-chip ${comparisonClass(comp.national)}">${comp.national}</span></td>
                        <td class="qd-compare-col"><span class="qd-compare-chip ${comparisonClass(comp.state)}">${comp.state}</span></td>
                    </tr>
                `;
            }).join('');
            return `
                <table class="qd-core-table">
                    <thead>
                        <tr>
                            <th>Measure</th>
                            <th class="qd-current-col">Current</th>
                            <th class="qd-trend-col">
                                <span class="qd-th-with-tip">Trend<span class="qd-header-tip" tabindex="0" aria-label="${TREND_HELP_TEXT}" data-qtip="${TREND_HELP_TEXT}">i</span></span>
                            </th>
                            <th class="qd-goal-col">Goal</th>
                            <th class="qd-status-col">Status</th>
                            <th class="qd-benchmark-col">Nat. Benchmark</th>
                            <th class="qd-compare-col">Nat. Comparison</th>
                            <th class="qd-compare-col">State Comparison</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            `;
        };

        const sectionsHtml = serviceCategories.map(({ cat, items, children }) => {
            const childGroups = Array.isArray(children) ? children.filter((child) => Array.isArray(child.items) && child.items.length) : [];
            const bodyHtml = childGroups.length
                ? `<div class="qd-core-subsections">
                    ${childGroups.map((child) => `
                        <section class="qd-core-subsection">
                            <div class="qd-core-subsection-title">${child.cat}</div>
                            ${renderMeasureTable(child.items)}
                        </section>
                    `).join('')}
                </div>`
                : renderMeasureTable(Array.isArray(items) ? items : []);
            return `
                <article class="qd-core-section">
                    <div class="qd-core-section-title">${cat}</div>
                    ${bodyHtml}
                </article>
            `;
        }).join('');

        if (analyticsMode === 'Prioritization Report (Heat Map)') {
            const metricsInScope = serviceCategories.flatMap(({ cat, items }) => (
                items.map((item) => {
                    const bm = metricBenchmarks[item] || {};
                    const hasData = hasMetricData(bm);
                    if (!hasData) {
                        return {
                            cat,
                            item,
                            missing: true
                        };
                    }
                    const score = derivePriorityScore(bm.value, bm.benchmark, bm.status || '', bm.trend || '');
                    return {
                        cat,
                        item,
                        missing: false,
                        status: bm.status || '',
                        trend: bm.trend || '',
                        value: bm.value || '-',
                        benchmark: bm.benchmark || '-',
                        score
                    };
                })
            ));

            const scoredMetrics = metricsInScope.filter((m) => !m.missing).sort((a, b) => b.score - a.score);
            const missingMetrics = metricsInScope.filter((m) => m.missing);

            const highLane = scoredMetrics.filter((m) => priorityTone(m.score) === 'high');
            const mediumLane = scoredMetrics.filter((m) => priorityTone(m.score) === 'medium');
            const lowLane = scoredMetrics.filter((m) => priorityTone(m.score) === 'low');
            const avgPriority = scoredMetrics.length
                ? Math.round(scoredMetrics.reduce((sum, m) => sum + m.score, 0) / scoredMetrics.length)
                : 0;

            const renderLane = (title, tone, items) => `
                <article class="qd-heat-lane qd-heat-lane-${tone}">
                    <div class="qd-heat-lane-head">
                        <h5>${title}</h5>
                        <span>${items.length}</span>
                    </div>
                    <div class="qd-heat-lane-list">
                        ${items.length ? items.map((m) => `
                            <div class="qd-heat-lane-item">
                                <div class="qd-heat-lane-item-main">
                                    <strong>${m.item}</strong>
                                    <small>${m.cat} • ${m.value} vs ${m.benchmark} • ${m.trend}</small>
                                </div>
                                <span class="qd-heat-lane-chip">${m.value || '-'}</span>
                            </div>
                        `).join('') : '<div class="qd-heat-lane-empty">No metrics</div>'}
                    </div>
                </article>
            `;
            const priorityHighlights = serviceCategories.map(({ cat, items }, idx) => {
                const catLabel = String(cat || '').replace(/^\s*\d+\.\s*/, '');
                const cards = items.map((item) => {
                    const bm = metricBenchmarks[item] || {};
                    const hasData = hasMetricData(bm);
                    const status = hasData ? (bm.status || '') : '';
                    const trend = hasData ? (bm.trend || '') : '';
                    const score = hasData ? derivePriorityScore(bm.value, bm.benchmark, status, trend) : null;
                    const tone = hasData ? priorityTone(score) : 'nodata';
                    const displayValue = hasData ? (bm.value || '-') : 'No data';
                    return `
                        <div class="qd-highlight-card qd-highlight-card-${tone}">
                            <strong>${item}</strong>
                            <div class="qd-highlight-score ${tone === 'nodata' ? 'qd-highlight-score-nodata' : ''}">${displayValue}</div>
                            <small>${hasData ? `${bm.value || '-'} • ${trend || '-'}` : 'No uploaded data'}</small>
                        </div>
                    `;
                }).join('');
                return `
                    <article class="qd-highlight-col">
                        <div class="qd-highlight-col-head">${idx + 1}. ${catLabel} (${items.length})</div>
                        <div class="qd-highlight-col-list">${cards}</div>
                    </article>
                `;
            }).join('');

            coreSetReport.innerHTML = `
                <div class="qd-core-meta">
                    <div><strong>Report:</strong> ${reportLabel}</div>
                    <div><strong>Period:</strong> ${period}</div>
                    <div><strong>Mode:</strong> Prioritization Heat Map</div>
                </div>
                <div class="qd-priority-snapshot">
                    <div class="qd-priority-highlights">
                        <div class="qd-priority-highlights-title">Priority Highlights</div>
                        <div class="qd-priority-highlights-grid">${priorityHighlights}</div>
                    </div>
                    <div class="qd-priority-snapshot-box">
                        <div class="qd-priority-snapshot-head">
                            <div>
                                <div class="qd-heat-summary-title">Priority Snapshot</div>
                                <div class="qd-heat-summary-sub">${scoredMetrics.length ? `${prioritySummaryLabel(avgPriority)} average priority score` : 'No scored metrics available yet'}</div>
                            </div>
                            <div class="qd-heat-summary-value">${scoredMetrics.length ? avgPriority : '-'}</div>
                        </div>
                        <div class="qd-heat-legend">
                            <span><i class="qd-heat-dot qd-heat-high"></i>0-44 Critical action</span>
                            <span><i class="qd-heat-dot qd-heat-medium"></i>45-74 Prioritize this cycle</span>
                            <span><i class="qd-heat-dot qd-heat-low"></i>75-100 Monitor / maintain</span>
                        </div>
                        <div class="qd-heat-lanes">
                            ${renderLane('Critical Action', 'high', highLane)}
                            ${renderLane('Prioritize This Cycle', 'medium', mediumLane)}
                            ${renderLane('Monitor / Maintain', 'low', lowLane)}
                        </div>
                    </div>
                </div>
            `;
            return;
        }

        if (analyticsMode === 'Comparative Benchmarking Report (Catepillar plot)') {
            const orgMetrics = serviceCategories.flatMap(({ cat, items }) => (
                items.map((item) => {
                    const bm = metricBenchmarks[item] || {};
                    const status = bm.status || 'yellow';
                    const trend = bm.trend || 'stable';
                    const value = bm.value || '-';
                    const benchmark = bm.benchmark || '-';
                    const score = comparativeScore(value, benchmark, status);
                    const nationalComparison = bm.national_comparison || 'Not uploaded';
                    const daysBetween = (bm.days_between !== undefined && bm.days_between !== null) ? `${bm.days_between} days` : 'N/A';
                    return { cat, item, status, trend, value, benchmark, score, nationalComparison, daysBetween };
                })
            )).filter((m) => typeof m.score === 'number').sort((a, b) => a.score - b.score);

            if (!orgMetrics.length) {
                coreSetReport.innerHTML = `
                    <div class="qd-core-meta">
                        <div><strong>Report:</strong> ${reportLabel}</div>
                        <div><strong>Period:</strong> ${period}</div>
                        <div><strong>Mode:</strong> Comparative Benchmarking</div>
                    </div>
                    <div class="qd-core-empty">No organization metrics available for comparative benchmarking.</div>
                `;
                return;
            }

            const avgScore = orgMetrics.reduce((sum, m) => sum + m.score, 0) / orgMetrics.length;
            const focusMetric = orgMetrics.find((m) => parseComparisonNumber(m.nationalComparison) !== null) || orgMetrics[orgMetrics.length - 1];
            const focusIndex = orgMetrics.findIndex((m) => m.item === focusMetric.item);
            const percentileRank = Math.round(((focusIndex + 1) / orgMetrics.length) * 100);
            const chartWidth = 920;
            const chartHeight = 380;
            const padLeft = 54;
            const padRight = 26;
            const padTop = 22;
            const padBottom = 28;
            const innerWidth = chartWidth - padLeft - padRight;
            const innerHeight = chartHeight - padTop - padBottom;
            const yToPx = (score) => padTop + (innerHeight - ((score / 5) * innerHeight));
            const xForIndex = (index) => padLeft + (orgMetrics.length === 1 ? innerWidth / 2 : (index / (orgMetrics.length - 1)) * innerWidth);
            const avgY = yToPx(avgScore);
            const focusX = xForIndex(focusIndex);
            const focusY = yToPx(focusMetric.score);
            const points = orgMetrics.map((m, index) => {
                const x = xForIndex(index);
                const y = yToPx(m.score);
                const isFocus = m.item === focusMetric.item;
                if (isFocus) {
                    return `<circle class="qd-cater-point-focus" cx="${x}" cy="${y}" r="5.5"></circle>`;
                }
                return `<circle class="qd-cater-point" cx="${x}" cy="${y}" r="3.6"></circle>`;
            }).join('');
            const gridLines = [0,1,2,3,4,5].map((tick) => {
                const y = yToPx(tick);
                const label = (5 - tick).toFixed(1);
                return `
                    <line class="qd-cater-grid" x1="${padLeft}" y1="${y}" x2="${chartWidth - padRight}" y2="${y}"></line>
                    <text class="qd-cater-axis" x="${padLeft - 18}" y="${y + 4}" text-anchor="end">${label}</text>
                `;
            }).join('');
            const calloutX = Math.min(chartWidth - 200, focusX + 38);
            const calloutY = Math.max(26, focusY - 24);
            const nationalDelta = parseComparisonNumber(focusMetric.nationalComparison);
            const orgDisplayName = (QD_ORG && (QD_ORG.label || QD_ORG.name || QD_ORG.key))
                ? String(QD_ORG.label || QD_ORG.name || QD_ORG.key)
                : 'Your Org';
            const nationalDisplay = nationalDelta === null
                ? 'Not uploaded'
                : `${nationalDelta > 0 ? '+' : ''}${nationalDelta.toFixed(1)}`;

            coreSetReport.innerHTML = `
                <div class="qd-core-meta">
                    <div><strong>Report:</strong> ${reportLabel}</div>
                    <div><strong>Period:</strong> ${period}</div>
                    <div><strong>Mode:</strong> Comparative Benchmarking</div>
                </div>
                <div class="qd-cater-layout">
                    <article class="qd-cater-chart-card">
                        <div class="qd-cater-head">
                            <h4>Comparative Benchmarking Distribution</h4>
                            <p>Caterpillar view of comparative scores across selected metrics (0.0 to 5.0)</p>
                        </div>
                        <svg class="qd-cater-svg" viewBox="0 0 ${chartWidth} ${chartHeight}" role="img" aria-label="Comparative benchmarking distribution">
                            ${gridLines}
                            <line class="qd-cater-avg" x1="${padLeft}" y1="${avgY}" x2="${chartWidth - padRight}" y2="${avgY}"></line>
                            <text class="qd-cater-avg-text" x="${padLeft + 6}" y="${avgY - 8}">Avg ${avgScore.toFixed(1)}</text>
                            ${points}
                            <line class="qd-cater-callout-line" x1="${focusX}" y1="${focusY}" x2="${calloutX}" y2="${calloutY + 18}"></line>
                            <rect class="qd-cater-callout-box" x="${calloutX}" y="${calloutY}" rx="10" ry="10" width="184" height="34"></rect>
                            <text class="qd-cater-callout-text" x="${calloutX + 12}" y="${calloutY + 22}">${orgDisplayName} ${focusMetric.score.toFixed(1)}</text>
                        </svg>
                    </article>
                    <aside class="qd-cater-stats">
                        <article>
                            <h5>Current Position</h5>
                            <div class="qd-cater-stat-value">${focusMetric.score.toFixed(1)}</div>
                            <p>${focusMetric.item}</p>
                        </article>
                        <article>
                            <h5>National Comparison</h5>
                            <div class="qd-cater-stat-value">${nationalDisplay}</div>
                            <p>${nationalDelta === null ? 'comparison not uploaded' : 'vs peer average'}</p>
                        </article>
                        <article>
                            <h5>Percentile Rank</h5>
                            <div class="qd-cater-stat-value">${percentileRank}th</div>
                            <p>among visible metrics</p>
                        </article>
                        <article>
                            <h5>Days Between</h5>
                            <div class="qd-cater-stat-value">${focusMetric.daysBetween}</div>
                            <p>comparative refresh cadence</p>
                        </article>
                    </aside>
                </div>
            `;
            return;
        }

        if (analyticsMode === 'Benchmarking') {
            const sectionRows = serviceCategories.map(({ cat, items }) => {
                const rows = items.map((item) => {
                    const bm = metricBenchmarks[item] || {};
                    const value = bm.value || '-';
                    const benchmark = bm.benchmark || '-';
                    const hasData = hasMetricData(bm);
                    const status = hasData ? (bm.status || '') : '';
                    const scoreClass = status ? `qd-bench-score-${status}` : 'qd-bench-score-neutral';
                    const dist = benchmarkDistribution(bm);
                    const enoughComparative = dist.reporting >= 2;
                    const reportingText = dist.reporting === 0
                        ? 'No data to display'
                        : (dist.reporting === 1 ? 'Only 1 organization reporting' : `${dist.reporting} organizations reporting`);
                    return `
                        <div class="qd-bench-row">
                            <div class="qd-bench-metric">
                                <strong>${item}</strong><br>
                                <span class="qd-analytics-sub">${reportingText}</span>
                            </div>
                            ${enoughComparative ? `
                                <div class="qd-bench-dist">
                                    <div class="qd-bench-bar qd-bench-bar-stacked" role="img" aria-label="Benchmark distribution across reporting organizations">
                                        ${dist.belowPct > 0 ? `<span class="qd-bench-seg qd-bench-red" style="width:${dist.belowPct.toFixed(2)}%">${dist.belowPct >= 16 ? `${dist.below} (${dist.belowPct.toFixed(0)}%)` : ''}</span>` : ''}
                                        ${dist.nearPct > 0 ? `<span class="qd-bench-seg qd-bench-yellow" style="width:${dist.nearPct.toFixed(2)}%">${dist.nearPct >= 16 ? `${dist.near} (${dist.nearPct.toFixed(0)}%)` : ''}</span>` : ''}
                                        ${dist.abovePct > 0 ? `<span class="qd-bench-seg qd-bench-green" style="width:${dist.abovePct.toFixed(2)}%">${dist.abovePct >= 16 ? `${dist.above} (${dist.abovePct.toFixed(0)}%)` : ''}</span>` : ''}
                                    </div>
                                </div>
                            ` : `
                                <div class="qd-bench-empty">
                                    <div class="qd-bench-empty-title">${dist.reporting === 0 ? 'No data available' : 'Insufficient comparative data'}</div>
                                    <div class="qd-bench-empty-sub">${dist.reporting === 0 ? 'No organizations have reported this metric yet.' : 'At least 2 organizations are needed for comparative benchmarking.'}</div>
                                </div>
                            `}
                            <div class="qd-bench-score ${scoreClass}">${value}</div>
                        </div>
                    `;
                }).join('');
                return `
                    <article class="qd-bench-section">
                        <div class="qd-bench-section-title">${cat}</div>
                        <div class="qd-bench-rows">${rows}</div>
                    </article>
                `;
            }).join('');

            coreSetReport.innerHTML = `
                <div class="qd-core-meta">
                    <div><strong>Report:</strong> ${reportLabel}</div>
                    <div><strong>Period:</strong> ${period}</div>
                    <div><strong>Mode:</strong> Nationwide Benchmarking</div>
                </div>
                <div class="qd-bench-head">Composite Metric Score and National Implementation Status</div>
                <div class="qd-bench-legend">
                    <span class="qd-bench-legend-item"><i class="qd-bench-dot qd-bench-red"></i>Below comparative</span>
                    <span class="qd-bench-legend-item"><i class="qd-bench-dot qd-bench-yellow"></i>Near comparative</span>
                    <span class="qd-bench-legend-item"><i class="qd-bench-dot qd-bench-green"></i>At/above comparative</span>
                </div>
                <div class="qd-bench-wrap">${sectionRows}</div>
            `;
            return;
        }

        const allRows = serviceCategories.flatMap(({ items }) => items);
        const stats = allRows.reduce((acc, item) => {
            const bm = metricBenchmarks[item] || {};
            const hasData = hasMetricData(bm);
            const status = hasData ? statusFromGoal(bm.value || '-', goalLookup(item, bm), bm) : '';
            const trend = hasData ? (bm.trend || 'stable') : '';
            acc.total += 1;
            if (status === 'green') acc.green += 1;
            if (status === 'yellow') acc.yellow += 1;
            if (status === 'red') acc.red += 1;
            if (trend === 'improving') acc.improving += 1;
            if (trend === 'declining') acc.declining += 1;
            return acc;
        }, { total: 0, green: 0, yellow: 0, red: 0, improving: 0, declining: 0 });
        coreSetReport.innerHTML = `
            <div class="qd-core-meta">
                <div><strong>Report:</strong> ${reportLabel}</div>
                <div><strong>Period:</strong> ${period}</div>
                <div><strong>Sections:</strong> ${serviceCategories.length}</div>
            </div>
            <div class="qd-core-purpose" aria-label="Purpose and summary">
                <div class="qd-core-purpose-title">How To Read This Report</div>
                <div class="qd-core-purpose-text">
                    This table shows the current value for each measure and compares it with the saved organization goal when one is available.
                </div>
                <div class="qd-core-purpose-grid">
                    <span><strong>Current:</strong> most recent uploaded or saved value.</span>
                    <span><strong>National Benchmark:</strong> benchmark/performance reference from the measure description where available.</span>
                    <span><strong>Goal:</strong> active organization goal saved in Measure Management.</span>
                    <span><strong>Status:</strong> colour-coded gap between last recorded value and goal.</span>
                    <span><strong>Trend:</strong> direction versus prior period (up/flat/down).</span>
                </div>
                <div class="qd-core-summary-chips">
                    <span class="qd-summary-chip">Metrics: ${stats.total}</span>
                    <span class="qd-summary-chip qd-summary-green">Green: ${stats.green}</span>
                    <span class="qd-summary-chip qd-summary-yellow">Yellow: ${stats.yellow}</span>
                    <span class="qd-summary-chip qd-summary-red">Red: ${stats.red}</span>
                    <span class="qd-summary-chip">Improving: ${stats.improving}</span>
                    <span class="qd-summary-chip">Declining: ${stats.declining}</span>
                </div>
            </div>
            <div class="qd-comparison-legend" aria-label="Comparison legend">
                <div class="qd-comparison-legend-title">Comparative uploads are not connected yet</div>
                <div class="qd-comparison-legend-row"><span class="qd-legend-red">RED:</span> Last recorded value is outside the saved goal range</div>
                <div class="qd-comparison-legend-row"><span class="qd-legend-yellow">YELLOW:</span> Last recorded value is near the saved goal</div>
                <div class="qd-comparison-legend-row"><span class="qd-legend-green">GREEN:</span> Last recorded value is meeting the saved goal</div>
            </div>
            <div class="qd-core-sections">${sectionsHtml}</div>
        `;
    };

    // Verify required elements exist
    if (!reportTypeSelect) {
        console.error('QD: Required report filter elements not found');
        return;
    }

    const render = (type) => {
        type = normalizeReportType(type);

        if (analyticsSelect && !analyticsSelect.value) {
            analyticsSelect.value = 'Dashboard';
        }
        const def = getReportDefinition(type);
        syncAnalyticsPanel();

        let saved = { selections: {}, values: {} };
        try {
            const savedState = localStorage.getItem('qd_state');
            if (savedState) {
                saved = JSON.parse(savedState);
            }
        } catch (e) {
            console.warn('QD: Error parsing state in render');
        }

        const selected = (saved.selections && saved.selections[type]) ? saved.selections[type] : [];
        const focusByType = saved.focusByType || {};
        const selectedFocus = populateFocusOptions(type, focusByType[type] || saved.focus || ['all']) || ['all'];
        const focusOptions = getFocusOptions(type);
        const selectedFocusIds = Array.isArray(selectedFocus) ? selectedFocus : [selectedFocus];

        // Clear builder columns when present
        if (hasBuilder) {
            col2.innerHTML = '';
            col3.innerHTML = '';
            col4Content.innerHTML = '';
        }

        const allCats = Object.keys(def);
        let cats = allCats;
        if (!selectedFocusIds.includes('all')) {
            const allowedCats = new Set();
            selectedFocusIds.forEach((focusId) => {
                const focusObj = focusOptions.find(opt => opt.id === focusId);
                if (focusObj && Array.isArray(focusObj.categories)) {
                    focusObj.categories.forEach((cat) => allowedCats.add(cat));
                }
            });
            cats = allCats.filter(cat => allowedCats.has(cat));
        }

        const selectedMetricGroups = populateMetricOptions(
            type,
            (saved.filters && saved.filters.qdFilterMetrics) ? saved.filters.qdFilterMetrics : ['all'],
            cats,
            selectedFocusIds
        );
        const metricOptions = getMetricOptions(type, cats, selectedFocusIds);
        const isAllMetricsSelected = selectedMetricGroups.includes('all');
        const hasMetricsSelection = isAllMetricsSelected || selectedMetricGroups.length > 0;
        const selectedMetricCategories = isAllMetricsSelected
            ? []
            : Array.from(new Set(metricOptions
                .filter((opt) => selectedMetricGroups.includes(opt.id))
                .flatMap((opt) => Array.isArray(opt.categories) ? opt.categories : (opt.category ? [opt.category] : []))));

        saved.filters = saved.filters || {};
        saved.filters.qdFilterMetrics = selectedMetricGroups;
        localStorage.setItem('qd_state', JSON.stringify(saved));

        const isMbqipFocus = type === 'committee'
            && Array.isArray(selectedFocusIds)
            && selectedFocusIds.length === 1
            && selectedFocusIds[0] === 'mbqip';

        if (isAllMetricsSelected) {
            // keep all categories in scope
        } else if (selectedMetricCategories.length) {
            cats = cats.filter((cat) => selectedMetricCategories.includes(cat));
        } else {
            cats = [];
        }
        const scopedCategories = cats.map((cat) => ({ cat, items: (def[cat] || []) }))
            .filter((entry) => entry.items.length > 0);
        renderFolderSidebar(type, focusOptions, selectedFocusIds, scopedCategories);

        let serviceCategories;
        if (isMbqipFocus) {
            if (isAllMetricsSelected) {
                serviceCategories = MBQIP_SERVICE_DEFINITIONS.map(({ id, cat, items }) => ({ id, cat, items: [...items] }));
            } else {
                serviceCategories = MBQIP_SERVICE_DEFINITIONS
                    .filter(({ id }) => selectedMetricGroups.includes(id))
                    .map(({ id, cat, items }) => ({ id, cat, items: [...items] }));
            }
        } else {
            serviceCategories = scopedCategories.map(({ cat, items }) => ({
                id: cat.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''),
                cat,
                items: [...items]
            }));
        }

        const qualityMeasureCategories = configuredQualityMeasureCategories();
        latestServiceCategories = qualityMeasureCategories.length ? qualityMeasureCategories : serviceCategories;
        latestReportType = type;
        ensureServiceSelections(latestServiceCategories);
        const hasQualityMeasures = latestServiceCategories.length > 0;
        if (servicesRow) {
            servicesRow.style.display = hasQualityMeasures ? 'flex' : 'none';
        }
        if (hasQualityMeasures) {
            updateServices(latestServiceCategories);
            updateFromServiceSelections();
        } else if (servicesList) {
            servicesList.innerHTML = '';
            updateOverview([]);
            updateCoreSetReport([], type);
        }

        if (hasBuilder) {
            const mid = Math.ceil(scopedCategories.length / 2);
            const isFocusedSubset = !selectedFocusIds.includes('all');

            scopedCategories.forEach(({ cat, items }, i) => {
                let target;
                if (isFocusedSubset) {
                    const focusColumns = [col2, col3, col4Content];
                    target = focusColumns[i % focusColumns.length];
                } else if (type === 'board') {
                    const boardCol2 = [
                        '1. Executive Summary (The "Pulse" Check)',
                        '2. Financial Dashboard (The "Survival" Metrics)'
                    ];
                    const boardCol3 = [
                        '3. Quality & Safety (QAPI)',
                        '4. Medical Staff & Credentialing'
                    ];
                    target = boardCol2.includes(cat) ? col2 : (boardCol3.includes(cat) ? col3 : col4Content);
                } else if (type === 'committee') {
                    const committeeCol2 = [
                        'Patient Safety & Inpatient (NHSN/HAI)',
                        'Care Transitions (EDTC)',
                        'Outpatient & ED Efficiency',
                        'Patient Engagement (HCAHPS)'
                    ];
                    const committeeCol3 = [
                        'Swing Bed Quality',
                        'Performance Improvement Projects (PIPs)',
                        'Risk Management & Grievances',
                        'Infection Control (Monthly Deep-Dive)',
                        'Rural Health Clinics (Quarterly Deep-Dive)'
                    ];
                    target = committeeCol2.includes(cat) ? col2 : (committeeCol3.includes(cat) ? col3 : col4Content);
                } else {
                    target = i < mid ? col2 : col3;
                }

                const isCommittee = type === 'committee';
                const html = `
                    <details class="qd-category-group qd-dropdown-group" ${i === 0 ? 'open' : ''}>
                        <summary class="qd-dropdown-summary" style="${isCommittee ? 'font-size: 11px;' : ''}">
                            <span>${cat}</span>
                            <i class="fas fa-chevron-down" aria-hidden="true"></i>
                        </summary>
                        <div class="qd-category-items">
                            ${items.map(item => {
                                const isSelected = Array.isArray(selected) && selected.includes(item);
                                return `
                                    <div class="qd-element-item-wrap">
                                        <div class="qd-element-item ${isSelected ? 'selected' : ''}" data-item="${item}">
                                            <div class="qd-checkbox"><i class="fas fa-check"></i></div>
                                            <span class="qd-element-label" style="font-size: 11px;">${item}</span>
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </details>
                `;
                if (target) target.insertAdjacentHTML('beforeend', html);
            });

            if (scopedCategories.length === 0) {
                col2.innerHTML = '<div class="qd-empty-state">No metrics match the selected filters.</div>';
            }

            document.querySelectorAll('.qd-element-item').forEach(el => {
                el.addEventListener('click', function() {
                    this.classList.toggle('selected');

                    const state = JSON.parse(localStorage.getItem('qd_state') || '{"active":"","selections":{}}');
                    state.selections[type] = Array.from(this.closest('.qd-menu-columns').querySelectorAll('.qd-element-item.selected'))
                                                .map(i => i.getAttribute('data-item'));

                    localStorage.setItem('qd_state', JSON.stringify(state));
                    if (saveStatus) {
                        saveStatus.style.display = 'flex';
                        setTimeout(() => { saveStatus.style.display = 'none'; }, 2000);
                    }
                });
            });
        }
    };

    // Initialize with safe defaults
    let init = { active: '', focus: ['all'], focusByType: {}, selections: {}, values: {}, filters: {} };
    try {
        const savedState = localStorage.getItem('qd_state');
        if (savedState) {
            const parsed = JSON.parse(savedState);
            if (parsed && typeof parsed === 'object') {
                init = { ...init, ...parsed };
            }
        }
    } catch (e) {
        console.warn('QD: Could not parse saved state, using defaults');
        localStorage.removeItem('qd_state');
    }

    if (reportTypeSelect && init.active) {
        reportTypeSelect.value = init.active;
    }
    if (reportTypeSelect) {
        init.active = reportTypeSelect.value || '';
    }
    const initialReportType = getSelectedReportType();
    populateFocusOptions(initialReportType, (init.focusByType && init.focusByType[initialReportType]) || init.focus || ['all']);

    const filterIds = ['qdFilterAnalytics', 'qdFilterYear', 'qdFilterHospitalType', 'qdFilterBedSize'];
    filterIds.forEach((id) => {
        const filterEl = document.getElementById(id);
        if (!filterEl) return;
        const fallbackValue = (id === 'qdFilterAnalytics')
            ? 'Dashboard'
            : (filterEl.options[0] ? filterEl.options[0].value : '');
        if (init.filters && init.filters[id]) {
            const optionExists = Array.from(filterEl.options).some((opt) => opt.value === init.filters[id]);
            filterEl.value = optionExists ? init.filters[id] : fallbackValue;
        } else {
            filterEl.value = fallbackValue;
        }
        filterEl.addEventListener('change', function() {
            const state = JSON.parse(localStorage.getItem('qd_state') || '{"selections":{}}');
            state.filters = state.filters || {};
            state.filters[id] = this.value;
            localStorage.setItem('qd_state', JSON.stringify(state));
            const activeType = reportTypeSelect ? reportTypeSelect.value : '';
            if (id === 'qdFilterYear' || id === 'qdFilterHospitalType' || id === 'qdFilterBedSize') {
                loadExternalMetricsData({ force: true }).finally(() => render(activeType));
            } else {
                render(activeType);
            }
        });
    });

    reportTypeSelect.addEventListener('change', function() {
        const type = this.value;
        const activeType = normalizeReportType(type);
        const state = JSON.parse(localStorage.getItem('qd_state') || '{"selections":{}}');
        state.active = type;
        state.focusByType = state.focusByType || {};
        state.focusByType[activeType] = state.focusByType[activeType] || ['all'];
        state.focus = state.focusByType[activeType];
        localStorage.setItem('qd_state', JSON.stringify(state));
        render(type);
    });

    if (folderReportList) {
        folderReportList.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-folder-report]');
            if (!btn || !reportTypeSelect) return;
            const nextType = btn.getAttribute('data-folder-report');
            if (!nextType) return;
            reportTypeSelect.value = nextType;
            reportTypeSelect.dispatchEvent(new Event('change'));
        });
    }

    if (folderFocusList) {
        folderFocusList.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-folder-focus]');
            if (!btn || !reportTypeSelect) return;
            const focusId = btn.getAttribute('data-folder-focus');
            if (!focusId) return;
            const type = getSelectedReportType();
            const state = JSON.parse(localStorage.getItem('qd_state') || '{"selections":{}}');
            state.focusByType = state.focusByType || {};
            state.focusByType[type] = [focusId];
            state.focus = [focusId];
            localStorage.setItem('qd_state', JSON.stringify(state));
            render(type);
        });
    }

    if (folderMetricsList) {
        folderMetricsList.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-folder-metric]');
            if (!btn) return;
            const metricName = btn.getAttribute('data-folder-metric');
            if (!metricName) return;
            setMetricEditor(metricName);
            folderMetricsList.querySelectorAll('[data-folder-metric]').forEach((node) => node.classList.remove('is-active'));
            btn.classList.add('is-active');
        });
    }

    const setupMultiDropdown = (dropdownEl, storageKey) => {
        if (!dropdownEl) return;
        const trigger = dropdownEl.querySelector('.qd-multi-trigger');
        const menu = dropdownEl.querySelector('.qd-multi-menu');
        if (!trigger || !menu) return;

        trigger.addEventListener('click', function() {
            document.querySelectorAll('.qd-multi-dropdown.open').forEach((other) => {
                if (other !== dropdownEl) other.classList.remove('open');
            });
            dropdownEl.classList.toggle('open');
        });

        menu.addEventListener('change', function(e) {
            const target = e.target;
            if (!target || target.type !== 'checkbox') return;
            const state = JSON.parse(localStorage.getItem('qd_state') || '{"selections":{}}');
            const activeType = getSelectedReportType();

            let selected = getSelectedValues(dropdownEl);
            if (selected.length === 0) {
                selected = [];
                const allCheckbox = menu.querySelector('input[value="all"]');
                if (allCheckbox) allCheckbox.checked = false;
            } else if (target.value === 'all' && target.checked) {
                menu.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
                    cb.checked = true;
                });
                selected = Array.from(menu.querySelectorAll('input[type="checkbox"]')).map((cb) => cb.value);
            } else if (target.value === 'all' && !target.checked) {
                menu.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
                    cb.checked = false;
                });
                selected = [];
            } else {
                const allCheckbox = menu.querySelector('input[value="all"]');
                const nonAll = Array.from(menu.querySelectorAll('input[type="checkbox"]')).filter((cb) => cb.value !== 'all');
                const allNonAllChecked = nonAll.length > 0 && nonAll.every((cb) => cb.checked);
                if (allCheckbox) {
                    allCheckbox.checked = allNonAllChecked;
                }
                selected = getSelectedValues(dropdownEl);
            }

            if (storageKey === 'focus') {
                state.focusByType = state.focusByType || {};
                state.focusByType[activeType] = selected;
                state.focus = selected;
            } else if (storageKey === 'metrics') {
                state.filters = state.filters || {};
                state.filters.qdFilterMetrics = selected;
            }

            localStorage.setItem('qd_state', JSON.stringify(state));
            render(activeType);
        });
    };

    setupMultiDropdown(reportFocusDropdown, 'focus');
    setupMultiDropdown(metricsDropdown, 'metrics');

    const loadScript = (src) => new Promise((resolve, reject) => {
        const existing = document.querySelector(`script[src="${src}"]`);
        if (existing) {
            existing.addEventListener('load', () => resolve());
            if (existing.dataset.loaded === 'true') resolve();
            return;
        }
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = () => {
            script.dataset.loaded = 'true';
            resolve();
        };
        script.onerror = reject;
        document.head.appendChild(script);
    });

    const getCoreReportNode = () => document.getElementById('qdCoreSetBoard');

    const exportAsPng = async () => {
        const node = getCoreReportNode();
        if (!node) return;
        if (typeof window.html2canvas === 'undefined') {
            await loadScript('https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js');
        }
        const canvas = await window.html2canvas(node, { scale: 2, backgroundColor: '#ffffff' });
        const url = canvas.toDataURL('image/png');
        const link = document.createElement('a');
        link.href = url;
        link.download = 'core-measure-set-report.png';
        link.click();
    };

    const exportAsPdf = async () => {
        const node = getCoreReportNode();
        if (!node) return;
        if (typeof window.html2canvas === 'undefined') {
            await loadScript('https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js');
        }
        if (typeof window.jspdf === 'undefined') {
            await loadScript('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js');
        }
        const canvas = await window.html2canvas(node, { scale: 2, backgroundColor: '#ffffff' });
        const img = canvas.toDataURL('image/png');
        const jsPDF = window.jspdf && window.jspdf.jsPDF ? window.jspdf.jsPDF : null;
        if (!jsPDF) return;
        const pdf = new jsPDF('p', 'mm', 'a4');
        const pageW = pdf.internal.pageSize.getWidth();
        const pageH = pdf.internal.pageSize.getHeight();
        const ratio = Math.min(pageW / canvas.width, pageH / canvas.height);
        const width = canvas.width * ratio;
        const height = canvas.height * ratio;
        pdf.addImage(img, 'PNG', (pageW - width) / 2, 6, width, height);
        pdf.save('core-measure-set-report.pdf');
    };

    if (downloadPngBtn) {
        downloadPngBtn.addEventListener('click', function() {
            exportAsPng().catch(() => window.alert('Could not export PNG.'));
        });
    }

    if (downloadPdfBtn) {
        downloadPdfBtn.addEventListener('click', function() {
            exportAsPdf().catch(() => window.alert('Could not export PDF.'));
        });
    }

    if (servicesList) {
        servicesList.addEventListener('click', function(e) {
            const toggle = e.target.closest('.qd-services-toggle');
            if (!toggle) return;
            servicePanelOpen = !servicePanelOpen;
            updateServices(latestServiceCategories);
        });

        servicesList.addEventListener('change', function(e) {
            const target = e.target;
            if (!target || target.type !== 'checkbox') return;

            if (target.classList.contains('qd-service-section-toggle')) {
                const cat = target.getAttribute('data-cat');
                if (!cat || !serviceSelections[cat]) return;
                serviceSelections[cat].enabled = target.checked;
                Object.keys(serviceSelections[cat].items || {}).forEach((item) => {
                    serviceSelections[cat].items[item] = target.checked;
                });
                const group = latestServiceCategories.find((entry) => entry.cat === cat);
                if (group && Array.isArray(group.children)) {
                    group.children.forEach((child) => {
                        if (!serviceSelections[child.cat]) return;
                        serviceSelections[child.cat].enabled = target.checked;
                        Object.keys(serviceSelections[child.cat].items || {}).forEach((item) => {
                            serviceSelections[child.cat].items[item] = target.checked;
                        });
                    });
                }
                const parentCat = target.getAttribute('data-parent-cat');
                if (parentCat && serviceSelections[parentCat]) {
                    const parent = latestServiceCategories.find((entry) => entry.cat === parentCat);
                    serviceSelections[parentCat].enabled = parent && Array.isArray(parent.children)
                        ? parent.children.some((child) => serviceSelections[child.cat] && serviceSelections[child.cat].enabled)
                        : target.checked;
                }
                updateServices(latestServiceCategories);
                updateFromServiceSelections();
                return;
            }

            if (target.classList.contains('qd-service-metric-toggle')) {
                const cat = target.getAttribute('data-cat');
                const item = target.getAttribute('data-item');
                if (!cat || !item || !serviceSelections[cat]) return;
                serviceSelections[cat].items[item] = target.checked;
                serviceSelections[cat].enabled = Object.values(serviceSelections[cat].items).some(Boolean);
                const parent = latestServiceCategories.find((entry) => Array.isArray(entry.children) && entry.children.some((child) => child.cat === cat));
                if (parent && serviceSelections[parent.cat]) {
                    serviceSelections[parent.cat].enabled = parent.children.some((child) => serviceSelections[child.cat] && serviceSelections[child.cat].enabled);
                }
                updateServices(latestServiceCategories);
                updateFromServiceSelections();
                return;
            }

            updateServices(latestServiceCategories);
            updateFromServiceSelections();
        });
    }

    document.addEventListener('mouseover', function(e) {
        const trigger = e.target.closest('.qd-header-tip');
        if (trigger) showFloatingTooltip(trigger);
    });

    document.addEventListener('mouseout', function(e) {
        const trigger = e.target.closest('.qd-header-tip');
        if (!trigger) return;
        const related = e.relatedTarget;
        if (!related || !trigger.contains(related)) hideFloatingTooltip();
    });

    document.addEventListener('focusin', function(e) {
        const trigger = e.target.closest('.qd-header-tip');
        if (trigger) showFloatingTooltip(trigger);
    });

    document.addEventListener('focusout', function(e) {
        if (e.target.closest('.qd-header-tip')) hideFloatingTooltip();
    });

    window.addEventListener('scroll', hideFloatingTooltip, true);
    window.addEventListener('resize', hideFloatingTooltip);

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.qd-multi-dropdown')) {
            document.querySelectorAll('.qd-multi-dropdown.open').forEach((el) => el.classList.remove('open'));
        }
    });

    if (metricEditorSave) {
        metricEditorSave.addEventListener('click', async function() {
            if (!activeFolderMetric) {
                window.alert('Select a metric folder first.');
                return;
            }
            metricBenchmarks[activeFolderMetric] = {
                ...(metricBenchmarks[activeFolderMetric] || {}),
                value: metricEditorValue ? metricEditorValue.value : '',
                benchmark: metricEditorBenchmark ? metricEditorBenchmark.value : '',
                status: metricEditorStatus ? metricEditorStatus.value : 'yellow',
                trend: metricEditorTrend ? metricEditorTrend.value : 'stable'
            };
            const prev = this.textContent;
            this.disabled = true;
            this.textContent = 'Saving...';
            try {
                await persistOrgMetrics();
                render(reportTypeSelect ? reportTypeSelect.value : '');
            } catch (err) {
                window.alert(err.message || 'Could not save organization metrics.');
            } finally {
                this.disabled = false;
                this.textContent = prev;
            }
        });
    }

    if (importMetricCsvBtn && importMetricCsvInput) {
        importMetricCsvBtn.addEventListener('click', function() {
            importMetricCsvInput.click();
        });

        importMetricCsvInput.addEventListener('change', async function() {
            if (!this.files || !this.files.length) return;
            const file = this.files[0];
            const text = await file.text();
            const lines = text.split(/\r?\n/).filter((l) => l.trim().length > 0);
            if (lines.length < 2) {
                window.alert('CSV needs header and at least one row.');
                this.value = '';
                return;
            }

            const parseRow = (row) => {
                const out = [];
                let cur = '';
                let inQuotes = false;
                for (let i = 0; i < row.length; i++) {
                    const ch = row[i];
                    if (ch === '"' && row[i + 1] === '"') { cur += '"'; i++; continue; }
                    if (ch === '"') { inQuotes = !inQuotes; continue; }
                    if (ch === ',' && !inQuotes) { out.push(cur.trim()); cur = ''; continue; }
                    cur += ch;
                }
                out.push(cur.trim());
                return out;
            };

            const headers = parseRow(lines[0]).map((h) => h.toLowerCase());
            const idxName = headers.indexOf('metric') >= 0 ? headers.indexOf('metric') : headers.indexOf('name');
            const idxValue = headers.indexOf('value');
            const idxBenchmark = headers.indexOf('benchmark');
            const idxStatus = headers.indexOf('status');
            const idxTrend = headers.indexOf('trend');
            if (idxName < 0) {
                window.alert('CSV must include a "metric" or "name" column.');
                this.value = '';
                return;
            }

            lines.slice(1).forEach((line) => {
                const cols = parseRow(line);
                const name = cols[idxName] || '';
                if (!name) return;
                metricBenchmarks[name] = {
                    ...(metricBenchmarks[name] || {}),
                    value: idxValue >= 0 ? (cols[idxValue] || '') : ((metricBenchmarks[name] && metricBenchmarks[name].value) || ''),
                    benchmark: idxBenchmark >= 0 ? (cols[idxBenchmark] || '') : ((metricBenchmarks[name] && metricBenchmarks[name].benchmark) || ''),
                    status: idxStatus >= 0 ? (cols[idxStatus] || 'yellow') : ((metricBenchmarks[name] && metricBenchmarks[name].status) || 'yellow'),
                    trend: idxTrend >= 0 ? (cols[idxTrend] || 'stable') : ((metricBenchmarks[name] && metricBenchmarks[name].trend) || 'stable')
                };
            });

            try {
                await persistOrgMetrics();
                render(reportTypeSelect ? reportTypeSelect.value : '');
                window.alert('Organization metric data imported.');
            } catch (err) {
                window.alert(err.message || 'Import failed.');
            }
            this.value = '';
        });
    }

    // Run Chart button removed — now in Analytics dropdown

    // Initial render
    loadExternalMetricsData().finally(() => {
        render(reportTypeSelect ? reportTypeSelect.value : init.active);
    });

    // Live refresh: re-fetch + re-render on signals from Data Management
    // (saves/deletes). Cross-tab via BroadcastChannel and storage events,
    // same-tab via the window-level refresh hook below. Also re-fetches when
    // the user switches back to a hidden tab.
    let lastRefreshAt = Date.now();
    const refreshLiveMetrics = async (opts) => {
        const force = !!(opts && opts.force);
        const now = Date.now();
        if (!force && now - lastRefreshAt < 1500) return;
        lastRefreshAt = now;
        await loadExternalMetricsData({ force: true });
        const activeType = (typeof reportTypeSelect !== 'undefined' && reportTypeSelect)
            ? reportTypeSelect.value
            : init.active;
        render(activeType);
    };
    window.QD_REFRESH_LIVE_METRICS = refreshLiveMetrics;

    try {
        if (typeof BroadcastChannel !== 'undefined') {
            const qdBc = new BroadcastChannel('qaqd_data_hub');
            qdBc.addEventListener('message', (event) => {
                if (event && event.data && event.data.type === 'metrics-changed') {
                    refreshLiveMetrics({ force: true });
                }
            });
        }
    } catch (err) { /* BroadcastChannel unsupported — fall back to storage */ }

    window.addEventListener('storage', (e) => {
        if (e && e.key === 'qaqd_metrics_changed_at') {
            refreshLiveMetrics({ force: true });
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            refreshLiveMetrics();
        }
    });

    const generateReportBtn = document.getElementById('generateReport');
    if (generateReportBtn) {
        generateReportBtn.addEventListener('click', function() {
        const state = JSON.parse(localStorage.getItem('qd_state') || '{"active":"","selections":{}}');
        const activeType = getSelectedReportType();
        const selections = state.selections[activeType] || [];
        const typeLabel = getReportLabel(activeType);

        if (selections.length === 0) {
            alert('Please select at least one element to include in your report.');
            return;
        }

        // If a PDF exists for this report type, open it and save to My Data
        if (QD_REPORT_PDFS && QD_REPORT_PDFS[activeType]) {
            window.open(QD_REPORT_PDFS[activeType], '_blank');

            // Build metrics data for saving
            const now = new Date();
            const dateStr = now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            const def = getReportDefinition(activeType);
            const metricsForSave = [];
            Object.keys(def).forEach(cat => {
                def[cat].filter(item => selections.includes(item)).forEach(item => {
                    const bm = metricBenchmarks[item] || {};
                    metricsForSave.push({
                        name: item, category: cat,
                        value: bm.value || 'â€”', benchmark: bm.benchmark || 'â€”',
                        status: bm.status || 'green', trend: bm.trend || 'stable'
                    });
                });
            });

            // Save to My Data (qd_report) via AJAX
            fetch(QD_AJAX.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: QD_SAVE_ACTION,
                    report_type: activeType,
                    report_label: typeLabel,
                    report_date: dateStr,
                    report_metrics: JSON.stringify(metricsForSave),
                    nonce: QD_AJAX.nonce
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    var msg = document.createElement('div');
                    msg.style.cssText = 'position:fixed;top:20px;right:20px;z-index:999999;background:#03283E;color:#fff;padding:16px 24px;border-radius:12px;font-size:14px;font-weight:600;box-shadow:0 8px 32px rgba(0,0,0,0.3);display:flex;align-items:center;gap:10px;';
                    msg.innerHTML = '<i class="fas fa-check-circle" style="color:#22c55e;font-size:18px;"></i> Report saved to <a href="/my-data/" style="color:#a8dbe6;text-decoration:underline;">My Data</a>';
                    document.body.appendChild(msg);
                    setTimeout(function() {
                        msg.style.transition = 'opacity 0.5s';
                        msg.style.opacity = '0';
                        setTimeout(function() { msg.remove(); }, 500);
                    }, 4000);
                }
            })
            .catch(function() {});

            return;
        }

        // Fallback: generate HTML report if no PDF exists
        this.innerHTML = 'Generating...';

        setTimeout(() => {
            const reportWindow = window.open('', '_blank');
            const now = new Date();
            const dateStr = now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });

            // Group selected metrics by category
            const def = getReportDefinition(activeType);
            const quarter = 'Q' + (Math.ceil((now.getMonth() + 1) / 3)) + ' ' + now.getFullYear();
            const groupedMetrics = [];
            Object.keys(def).forEach(cat => {
                const items = def[cat].filter(item => selections.includes(item));
                if (items.length > 0) {
                    groupedMetrics.push({ category: cat, items: items });
                }
            });

            const metricsData = [];
            groupedMetrics.forEach(group => {
                group.items.forEach(item => {
                    const bm = metricBenchmarks[item] || {};
                    metricsData.push({
                        name: item,
                        category: group.category,
                        value: bm.value || 'â€”',
                        benchmark: bm.benchmark || 'â€”',
                        status: bm.status || 'green',
                        trend: bm.trend || 'stable'
                    });
                });
            });

            const reportHtml = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>${typeLabel} - ${dateStr}</title>
                    <style>
                        * { box-sizing: border-box; margin: 0; padding: 0; }
                        body {
                            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                            color: #03283e;
                            background: white;
                            line-height: 1.4;
                        }

                        /* â”€â”€ Toolbar (hidden on print) â”€â”€ */
                        .report-toolbar {
                            display: flex; gap: 12px; justify-content: center;
                            padding: 14px 30px; background: #f8fafc;
                            border-bottom: 1px solid #e2e8f0;
                        }
                        .report-btn {
                            display: inline-flex; align-items: center; gap: 8px;
                            padding: 10px 22px; border-radius: 8px;
                            font-size: 13px; font-weight: 700; cursor: pointer;
                            border: none; transition: all 0.2s;
                        }
                        .report-btn-primary { background: #03283e; color: white; }
                        .report-btn-primary:hover { background: #064066; }
                        .report-btn-secondary { background: #0891b2; color: white; }
                        .report-btn-secondary:hover { background: #0e7490; }
                        .report-btn svg { width: 18px; height: 18px; }
                        .save-status {
                            display: none; align-items: center; gap: 8px;
                            padding: 10px 20px; background: #f0fdf4; color: #16a34a;
                            border-radius: 8px; font-weight: 600; font-size: 13px;
                        }

                        /* â”€â”€ Page canvas â”€â”€ */
                        .qcr-page {
                            position: relative;
                            width: 1440px;
                            min-height: 820px;
                            margin: 0 auto;
                            background: white;
                            overflow: hidden;
                        }

                        /* â”€â”€ Scattered decorations â”€â”€ */
                        .deco { position: absolute; pointer-events: none; z-index: 0; }
                        .deco-plus {
                            font-size: 14px; font-weight: 900; color: #5cc8d9;
                            opacity: 0.45; line-height: 1;
                        }
                        .deco-dot {
                            width: 6px; height: 6px; border-radius: 50%;
                            background: #5cc8d9; opacity: 0.35;
                        }
                        .deco-sq {
                            width: 7px; height: 7px; border-radius: 1px;
                            background: #5cc8d9; opacity: 0.35;
                        }

                        /* â”€â”€ Two-column layout â”€â”€ */
                        .qcr-grid {
                            display: grid;
                            grid-template-columns: 48.5% 48.5%;
                            gap: 22px;
                            padding: 32px 32px 36px;
                            position: relative;
                            z-index: 1;
                            align-items: start;
                        }
                        .qcr-left, .qcr-right {
                            display: flex; flex-direction: column; gap: 20px;
                        }

                        /* â”€â”€ Header badge â”€â”€ */
                        .qcr-header {
                            background: #b2e4f2;
                            border-radius: 24px;
                            padding: 26px 30px;
                            display: flex;
                            align-items: center;
                            gap: 20px;
                        }
                        .qcr-header-icon {
                            width: 72px; height: 72px;
                            background: rgba(255,255,255,0.55);
                            border-radius: 50%;
                            display: flex; align-items: center; justify-content: center;
                            flex-shrink: 0;
                        }
                        .qcr-header-icon svg { width: 36px; height: 36px; fill: #03283e; opacity: 0.7; }
                        .qcr-header-title {
                            font-size: 34px; font-weight: 900; color: #03283e;
                            line-height: 1.1;
                        }
                        .qcr-header-sub {
                            font-size: 14px; font-weight: 600; color: #03283e;
                            opacity: 0.65; margin-top: 5px;
                        }

                        /* â”€â”€ Section card â”€â”€ */
                        .qcr-card {
                            background: #b2e4f2;
                            border-radius: 18px;
                            overflow: hidden;
                            padding-bottom: 16px;
                        }
                        .qcr-card-title {
                            font-size: 38px; font-weight: 900; color: #03283e;
                            padding: 22px 26px 14px; line-height: 1.1;
                        }

                        /* â”€â”€ Data table â”€â”€ */
                        .qcr-table {
                            width: calc(100% - 24px);
                            margin: 0 12px;
                            border-collapse: separate;
                            border-spacing: 0;
                            background: white;
                            border-radius: 8px;
                            overflow: hidden;
                            border: 2px solid #c8c8c8;
                        }
                        .qcr-table thead th {
                            background: #22a6b8;
                            color: white;
                            font-size: 13px;
                            font-weight: 800;
                            text-align: center;
                            padding: 14px 12px;
                            border-right: 2px solid rgba(255,255,255,0.25);
                            line-height: 1.25;
                        }
                        .qcr-table thead th:last-child { border-right: none; }
                        .qcr-table tbody td {
                            padding: 18px 14px;
                            font-size: 14px;
                            font-weight: 700;
                            color: #03283e;
                            text-align: center;
                            border-bottom: 2px solid #d4d4d4;
                            border-right: 2px solid #d4d4d4;
                            background: white;
                            vertical-align: middle;
                        }
                        .qcr-table tbody td:last-child { border-right: none; }
                        .qcr-table tbody tr:last-child td { border-bottom: none; }
                        .qcr-table tbody td:first-child {
                            text-align: left; padding-left: 18px; font-weight: 800;
                        }

                        /* â”€â”€ Status rectangle â”€â”€ */
                        .st-block {
                            display: inline-block;
                            width: 72px; height: 36px;
                            border-radius: 5px;
                            vertical-align: middle;
                        }
                        .st-green  { background: #22a845; }
                        .st-yellow { background: #f5a623; }
                        .st-red    { background: #e03e3e; }

                        /* â”€â”€ Trend â”€â”€ */
                        .tr-trend {
                            display: inline-flex; align-items: center; gap: 6px;
                            font-size: 14px; font-weight: 700; white-space: nowrap;
                            color: #03283e;
                        }
                        .tr-up   { color: #22a845; font-size: 15px; }
                        .tr-side { color: #03283e; font-size: 15px; }
                        .tr-down { color: #e03e3e; font-size: 15px; }

                        /* â”€â”€ Deco images (bottom-left) â”€â”€ */
                        .qcr-deco-imgs {
                            display: flex; align-items: flex-end; gap: 14px;
                            padding-top: 8px;
                        }
                        .qcr-deco-photo {
                            width: 120px; height: 90px;
                            background: #d5eef5;
                            border-radius: 14px;
                            border: 3px solid #b2e4f2;
                            display: flex; align-items: center; justify-content: center;
                            position: relative; overflow: hidden;
                        }
                        .qcr-deco-photo::before {
                            content: '';
                            position: absolute; top: 0; left: 0; right: 0; bottom: 0;
                            background: linear-gradient(135deg, #b2e4f2 0%, #7ecfe0 50%, #a8d8ea 100%);
                            opacity: 0.6;
                        }
                        .qcr-deco-photo svg {
                            width: 40px; height: 40px; fill: #03283e;
                            opacity: 0.35; position: relative; z-index: 1;
                        }
                        .qcr-deco-photo .flag-overlay {
                            position: absolute; top: 5px; right: 8px;
                            display: flex; gap: 3px; z-index: 2;
                        }
                        .qcr-deco-photo .flag-sq {
                            width: 8px; height: 8px; border-radius: 1px;
                        }
                        .qcr-deco-state {
                            width: 90px; height: 90px;
                            display: flex; align-items: center; justify-content: center;
                        }
                        .qcr-deco-state svg {
                            width: 70px; height: 85px; fill: #b2e4f2; opacity: 0.7;
                        }

                        @media print {
                            body { background: white; }
                            .report-toolbar { display: none !important; }
                            .qcr-page { width: 100%; }
                            .qcr-card { break-inside: avoid; }
                        }
                        @media screen and (max-width: 1500px) {
                            .qcr-page { width: 100%; }
                        }
                    </style>
                </head>
                <body>
                    <!-- Toolbar -->
                    <div class="report-toolbar">
                        <button class="report-btn report-btn-primary" onclick="window.print()">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            Download PDF
                        </button>
                        <button class="report-btn report-btn-secondary" id="saveToMyData">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                            Save to My Data
                        </button>
                        <div class="save-status" id="saveStatus">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Report Saved!
                        </div>
                    </div>

                    <div class="qcr-page">
                        <!-- Scattered decorative elements -->
                        <div class="deco deco-plus" style="top:18px;left:12px;">+</div>
                        <div class="deco deco-dot" style="top:45px;left:42px;"></div>
                        <div class="deco deco-plus" style="top:70px;left:8px;">+</div>
                        <div class="deco deco-sq" style="top:30px;left:70px;"></div>
                        <div class="deco deco-dot" style="top:95px;left:55px;"></div>
                        <div class="deco deco-plus" style="top:140px;left:18px;">+</div>
                        <div class="deco deco-dot" style="top:170px;left:50px;"></div>
                        <div class="deco deco-sq" style="top:200px;left:10px;"></div>
                        <div class="deco deco-plus" style="top:250px;left:35px;">+</div>
                        <div class="deco deco-dot" style="top:300px;left:15px;"></div>
                        <div class="deco deco-plus" style="top:400px;left:22px;">+</div>
                        <div class="deco deco-dot" style="top:450px;left:8px;"></div>
                        <div class="deco deco-sq" style="top:500px;left:40px;"></div>
                        <div class="deco deco-plus" style="top:550px;left:12px;">+</div>
                        <div class="deco deco-dot" style="top:620px;left:28px;"></div>
                        <div class="deco deco-plus" style="top:680px;left:5px;">+</div>
                        <div class="deco deco-dot" style="top:720px;left:50px;"></div>
                        <div class="deco deco-sq" style="top:760px;left:18px;"></div>
                        <div class="deco deco-plus" style="bottom:40px;left:60px;">+</div>
                        <div class="deco deco-dot" style="bottom:65px;left:30px;"></div>
                        <div class="deco deco-plus" style="bottom:20px;left:10px;">+</div>
                        <div class="deco deco-sq" style="bottom:80px;left:55px;"></div>

                        <!-- Two-column grid -->
                        <div class="qcr-grid">

                            <!-- LEFT COLUMN -->
                            <div class="qcr-left">

                                <!-- Header badge -->
                                <div class="qcr-header">
                                    <div class="qcr-header-icon">
                                        <svg viewBox="0 0 24 24"><path d="M12 15.5A3.5 3.5 0 0 1 8.5 12 3.5 3.5 0 0 1 12 8.5a3.5 3.5 0 0 1 3.5 3.5 3.5 3.5 0 0 1-3.5 3.5m7.43-2.53c.04-.32.07-.64.07-.97s-.03-.66-.07-1l2.11-1.63c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.3-.61-.22l-2.49 1c-.52-.4-1.08-.73-1.69-.98l-.38-2.65C14.46 2.18 14.25 2 14 2h-4c-.25 0-.46.18-.49.42l-.38 2.65c-.61.25-1.17.59-1.69.98l-2.49-1c-.23-.09-.49 0-.61.22l-2 3.46c-.13.22-.07.49.12.64L4.57 11c-.04.34-.07.67-.07 1s.03.65.07.97l-2.11 1.66c-.19.15-.25.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1.01c.52.4 1.08.73 1.69.98l.38 2.65c.03.24.24.42.49.42h4c.25 0 .46-.18.49-.42l.38-2.65c.61-.25 1.17-.58 1.69-.98l2.49 1.01c.22.08.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64L19.43 12.97Z"/></svg>
                                    </div>
                                    <div>
                                        <div class="qcr-header-title">${typeLabel}</div>
                                        <div class="qcr-header-sub">Anytown, Florida CAH / Reporting Period: ${quarter}</div>
                                    </div>
                                </div>

                                <!-- First section -->
                                ${groupedMetrics.length > 0 ? (function() {
                                    const g = groupedMetrics[0];
                                    const rows = g.items.map(item => {
                                        const bm = metricBenchmarks[item] || {};
                                        const v = bm.value || 'â€”';
                                        const b = bm.benchmark || 'â€”';
                                        const s = bm.status || 'green';
                                        const t = bm.trend || 'stable';
                                        const tl = t === 'improving' ? '(Improving)' : t === 'stable' ? '(Stable)' : '(Declining)';
                                        const ta = t === 'improving' ? '<span class="tr-up">&#9650;</span>' : t === 'stable' ? '<span class="tr-side">&#9654;</span>' : '<span class="tr-down">&#9660;</span>';
                                        return '<tr><td>' + item + '</td><td>' + v + '</td><td>' + b + '</td><td><span class="st-block st-' + s + '"></span></td><td><span class="tr-trend">' + ta + ' ' + tl + '</span></td></tr>';
                                    }).join('');
                                    return '<div class="qcr-card"><div class="qcr-card-title">' + g.category + '</div>' +
                                        '<table class="qcr-table"><thead><tr><th>Metric</th><th>' + quarter + '</th><th>National<br>Benchmark</th><th>Status</th><th>12-Month<br>Trend</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
                                })() : ''}

                                <!-- Decorative images -->
                                <div class="qcr-deco-imgs">
                                    <div class="qcr-deco-photo">
                                        <div class="flag-overlay">
                                            <div class="flag-sq" style="background:#03283e;"></div>
                                            <div class="flag-sq" style="background:#22a845;"></div>
                                        </div>
                                        <svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V7l8-4v18M13 21V3l6 3v15M9 9v.01M9 12v.01M9 15v.01M9 18v.01M17 9v.01M17 12v.01M17 15v.01"/></svg>
                                    </div>
                                    <div class="qcr-deco-photo">
                                        <div class="flag-overlay">
                                            <div class="flag-sq" style="background:#03283e;"></div>
                                            <div class="flag-sq" style="background:#22a845;"></div>
                                            <div class="flag-sq" style="background:#e03e3e;"></div>
                                        </div>
                                        <svg viewBox="0 0 24 24"><path d="M3 21h18M9 21V12h6v9M12 3l9 9H3l9-9zM7 12V9M17 12V9M5 21v-5M19 21v-5"/></svg>
                                    </div>
                                    <div class="qcr-deco-state">
                                        <svg viewBox="0 0 50 65"><path d="M28 1c3 0 6 1 9 3 3 2 5 4 7 7 2 3 3 6 4 10 1 4 2 7 3 10 1 3 2 6 2 9 0 4-1 7-3 10-2 3-4 6-7 9-2 2-4 5-5 8-1 2-2 4-4 5-1 1-3 2-5 2s-3-1-5-3c-1-1-3-4-5-7-2-3-4-6-6-9-2-3-4-6-5-9-1-3-2-7-2-10 0-3 0-6 1-9 1-3 2-6 4-9 2-3 4-5 7-7 3-2 6-3 10-3z" opacity="0.5"/></svg>
                                    </div>
                                </div>
                            </div>

                            <!-- RIGHT COLUMN -->
                            <div class="qcr-right">
                                ${groupedMetrics.slice(1).map(group => {
                                    const rows = group.items.map(item => {
                                        const bm = metricBenchmarks[item] || {};
                                        const v = bm.value || 'â€”';
                                        const b = bm.benchmark || 'â€”';
                                        const s = bm.status || 'green';
                                        const t = bm.trend || 'stable';
                                        const tl = t === 'improving' ? '(Improving)' : t === 'stable' ? '(Stable)' : '(Declining)';
                                        const ta = t === 'improving' ? '<span class="tr-up">&#9650;</span>' : t === 'stable' ? '<span class="tr-side">&#9654;</span>' : '<span class="tr-down">&#9660;</span>';
                                        return '<tr><td>' + item + '</td><td>' + v + '</td><td>' + b + '</td><td><span class="st-block st-' + s + '"></span></td><td><span class="tr-trend">' + ta + ' ' + tl + '</span></td></tr>';
                                    }).join('');
                                    return '<div class="qcr-card"><div class="qcr-card-title">' + group.category + '</div>' +
                                        '<table class="qcr-table"><thead><tr><th>Metric</th><th>' + quarter + '</th><th>National<br>Benchmark</th><th>Status</th><th>12-Month<br>Trend</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
                                }).join('')}
                            </div>

                        </div>
                    </div>

                    <scr` + `ipt>
                        // Report data for saving
                        const reportData = {
                            type: '${activeType}',
                            typeLabel: '${typeLabel}',
                            date: '${dateStr}',
                            metrics: ${JSON.stringify(metricsData)}
                        };

                        // AJAX config from parent window
                        const ajaxUrl = '${QD_AJAX.url}';
                        const ajaxNonce = '${QD_AJAX.nonce}';

                        document.getElementById('saveToMyData').addEventListener('click', function() {
                            const btn = this;
                            btn.disabled = true;
                            btn.innerHTML = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18" style="animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke-width="4" stroke-dasharray="30 70" fill="none"></circle></svg> Saving...';

                            fetch(ajaxUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({
                                    action: QD_SAVE_ACTION,
                                    report_type: reportData.type,
                                    report_label: reportData.typeLabel,
                                    report_date: reportData.date,
                                    report_metrics: JSON.stringify(reportData.metrics),
                                    nonce: ajaxNonce
                                })
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    btn.style.display = 'none';
                                    document.getElementById('saveStatus').style.display = 'flex';
                                } else {
                                    btn.disabled = false;
                                    btn.innerHTML = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg> Save to My Data';
                                    alert(data.data?.message || 'Could not save report. Please try again.');
                                }
                            })
                            .catch(err => {
                                console.error('Save error:', err);
                                btn.disabled = false;
                                btn.innerHTML = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg> Save to My Data';
                                alert('Could not save report. Please try again.');
                            });
                        });
                    </scr` + `ipt>
                    <sty` + `le>
                        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
                    </sty` + `le>
                </body>
                </html>
            `;

            reportWindow.document.write(reportHtml);
            reportWindow.document.close();

            this.innerHTML = 'Generate Report <i class="fas fa-magic"></i>';
        }, 1200);
        });
    }
});
