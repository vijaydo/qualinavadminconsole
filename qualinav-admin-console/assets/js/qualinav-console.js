(function () {
    document.documentElement.classList.add('qualinav-console-ready');

    var config = readConsoleConfig();
    var restUrl = config.restUrl || '';
    var nonce = config.nonce || '';
    var state = {
        me: null,
        hospitals: [],
        allHospitals: [],
        hospitalFilter: null,
        hospitalPage: 1,
        hospitalPageSize: 10,
        hospitalSearch: '',
        healthSystems: [],
        hospitalTypes: [],
        serviceModels: [],
        states: [],
        users: [],
        invitations: [],
        hospitalPeopleLoaded: false,
        hospitalPeoplePreviewMode: false,
        editingHospital: null,
        inviteContext: 'admin',
        fixedInviteRole: null,
        fixedInviteOrganization: null,
        onboarding: null,
        onboardingIndex: 0,
        onboardingOrganizationId: null,
        onboardingSubmitting: false,
        onboardingBackgroundSaving: false,
        onboardingSaveStatusTimer: null,
        onboardingSwitchTimer: null,
        onboardingUrlSectionApplied: false,
        onboardingUrlQuestionApplied: false,
        onboardingGuideAutoShown: false,
        workspaceWelcomeAutoShown: false,
        scoutRuns: [],
        latestScoutRun: null,
        scoutBridgeAvailable: false,
        scoutCanGenerate: false,
        scoutOnboardingSubmitted: false,
        scoutGenerationInFlight: false,
        scoutAutoGenerationOrganizationId: null,
        autosaveTimer: null,
        systemCheck: null,
        adminPeopleLoaded: false,
        adminPeopleLoading: false,
        adminSystemCheckLoaded: false,
        adminSystemCheckLoading: false,
        systemHospitalAssignmentSystem: null,
        systemHospitalAssignmentSelection: {},
        systemHospitalAssignmentSearch: '',
        systemSearch: '',
        systemStatusFilter: '',
        userSearch: '',
        hospitalUserSearch: '',
        adminInvitationSearch: '',
        hospitalInvitationSearch: ''
    };
    var usDatePickerPopover = null;
    var usDatePickerContext = null;
    var destructiveConfirmation = null;
    var destructiveConfirmationFocus = null;

    var roleLabels = {
        qualinav_super_admin: 'QualiNav Super Admin',
        qualinav_admin: 'QualiNav Admin',
        quality_director: 'Hospital Quality Director',
        executive_leader: 'Executive Leader (CEO or CFO)',
        clinical_ancillary_services_leader: 'Clinical or Ancillary Services Leader or Director',
        hospital_admin: 'Hospital Admin',
        backup_quality_user: 'Backup Quality User',
        reporting_user: 'Reporting User',
        policy_owner: 'Policy Owner',
        committee_user: 'Committee User',
        viewer: 'Viewer'
    };
    var statuses = ['invited', 'active', 'disabled', 'archived'];
    var inviteRolesByRole = {
        qualinav_super_admin: ['qualinav_admin', 'quality_director', 'executive_leader', 'clinical_ancillary_services_leader', 'hospital_admin', 'backup_quality_user', 'reporting_user', 'policy_owner', 'committee_user', 'viewer'],
        qualinav_admin: ['quality_director', 'executive_leader', 'clinical_ancillary_services_leader', 'hospital_admin', 'backup_quality_user', 'reporting_user', 'policy_owner', 'committee_user', 'viewer'],
        quality_director: ['executive_leader', 'clinical_ancillary_services_leader', 'hospital_admin', 'backup_quality_user', 'reporting_user', 'policy_owner', 'committee_user', 'viewer'],
        executive_leader: ['clinical_ancillary_services_leader', 'reporting_user', 'policy_owner', 'committee_user', 'viewer'],
        hospital_admin: ['clinical_ancillary_services_leader', 'reporting_user', 'policy_owner', 'committee_user', 'viewer']
    };
    var roleDescriptions = {
        quality_director: 'Leads the hospital quality workspace, setup, users, and Scout workflows.',
        executive_leader: 'Executive persona for hospital leadership, workspace oversight, and reporting visibility.',
        clinical_ancillary_services_leader: 'Clinical or ancillary services persona for quality work and reporting visibility.',
        hospital_admin: 'Can help manage hospital users and workspace administration.',
        backup_quality_user: 'Quality Director-level access for designated backup coverage.',
        reporting_user: 'Can support reporting workflows and related setup information.',
        policy_owner: 'Can support plan and policy ownership workflows.',
        committee_user: 'Can support committee and meeting workflow information.',
        viewer: 'Read-only access to the hospital workspace.'
    };

    function readConsoleConfig() {
        var win = browserWindow();
        var inlineConfig = (win && win.QualiNavConsole) || (typeof QualiNavConsole !== 'undefined' ? QualiNavConsole : {});
        if (inlineConfig && inlineConfig.restUrl) {
            return inlineConfig;
        }

        var configNode = document.getElementById('qn-console-config-json');
        if (!configNode) {
            return inlineConfig || {};
        }

        try {
            var parsedConfig = JSON.parse(configNode.textContent || '{}');
            return Object.assign({}, parsedConfig, inlineConfig || {});
        } catch (error) {
            return inlineConfig || {};
        }
    }

    function browserWindow() {
        if (typeof window !== 'undefined') {
            return window;
        }
        return document && document.defaultView ? document.defaultView : null;
    }

    var onboardingHelpByStep = {
        hospital_director_info: [
            'Build the hospital profile',
            'Confirm hospital type and pathway',
            'Personalize the director experience level',
            'Set the default setup path',
            'Keep PHI out of Hospital Setup'
        ],
        accreditation_survey_readiness: [
            'Select the right regulatory pathway',
            'Build the survey readiness timeline',
            'Weight readiness work toward past deficiency areas',
            'Track open POC follow-up',
            'Monitor the right accreditation/CMS sources'
        ],
        services_clinical_model: [
            'Select applicable service lines',
            'Help Scout understand which requirements may apply',
            'Identify clinical-model context',
            'Tailor hospital-type prompts'
        ],
        committees_reporting: [
            'Record local meeting names and functions',
            'Capture parent and roll-up relationships',
            'Add preparation lead time and backup coverage',
            'Help Scout prepare information before meetings'
        ],
        plans_policies_monitoring: [
            'Build the required plan review queue',
            'Identify missing or overdue plans and policies',
            'Index approved documents for authorized Scout answers',
            'Reuse a document when it covers multiple requirements',
            'Assess coverage and readiness without declaring compliance'
        ],
        measures_qi_projects: [
            'Open the hospital Data Hub',
            'Manage measures and submissions in one source of truth',
            'Track reporting deadlines and owners',
            'Review aggregate performance trends'
        ],
        regulatory_tools_preferences: [
            'Review incomplete setup details once',
            'Identify backup visibility needs',
            'Confirm setup is ready',
            'Generate the Scout setup preview after submission'
        ]
    };
    var onboardingMaterialsChecklist = [
        'Hospital profile details',
        'Survey/accreditation history: last survey dates and related action plans',
        'Reporting obligations for federal, state, or other outside reporting entities',
        'Meeting schedule for all hospital meetings that include quality reporting',
        'Quality and Patient Safety-related policies and plans, and last approval dates',
        'Policy review cycle process or policy',
        'List of hospital-wide and department-specific Clinical quality measures routinely monitored',
        'Aggregate measure sources',
        'Active QI projects'
    ];

    function roleLabel(role) {
        if (!role) {
            return '-';
        }
        if (roleLabels[role]) {
            return roleLabels[role];
        }
        return normalizePublicSetupCopy(String(role).replace(/_/g, ' ').replace(/\b\w/g, function (letter) {
            return letter.toUpperCase();
        }));
    }

    function currentWorkspaceRole() {
        return state.me && state.me.qualinav_role ? state.me.qualinav_role : '';
    }

    function isExecutiveReviewRole() {
        return currentWorkspaceRole() === 'executive_leader';
    }

    function isReadOnlyWorkspaceRole() {
        if (isGlobalAdmin()) {
            return false;
        }
        return !canEditOnboarding();
    }

    function readOnlyReviewBadge() {
        if (!isReadOnlyWorkspaceRole()) {
            return '';
        }
        return '<span class="qn-status-pill qn-status-neutral">' + escapeHtml(isExecutiveReviewRole() ? 'Executive review' : 'Read-only view') + '</span>';
    }

    function readOnlyPageAction() {
        return {
            target: '',
            label: '',
            helper: 'Review-only access. Setup and workflow changes are managed by authorized workspace users.',
            generate: false,
            readOnly: true
        };
    }

    function api(path, options) {
        options = options || {};
        options.headers = options.headers || {};
        options.headers['X-WP-Nonce'] = nonce;
        options.headers['Content-Type'] = 'application/json';

        if (options.body && typeof options.body !== 'string') {
            options.body = JSON.stringify(options.body);
        }

        return new Promise(function (resolve, reject) {
            var win = browserWindow();
            var BrowserXMLHttpRequest = (typeof XMLHttpRequest !== 'undefined' && XMLHttpRequest) || (win && win.XMLHttpRequest);
            if (!BrowserXMLHttpRequest) {
                reject(new Error('Your browser could not start the request. Please refresh and try again.'));
                return;
            }
            var request = new BrowserXMLHttpRequest();
            request.timeout = options.timeout || 60000;
            request.open(options.method || 'GET', restUrl + path, true);
            Object.keys(options.headers).forEach(function (key) {
                request.setRequestHeader(key, options.headers[key]);
            });
            request.onload = function () {
                var json = {};
                try {
                    json = request.responseText ? JSON.parse(request.responseText) : {};
                } catch (error) {
                    reject(new Error('QualiNav returned an unreadable response.'));
                    return;
                }
                if (request.status < 200 || request.status >= 300) {
                    var apiError = new Error(json.message || 'QualiNav request failed.');
                    apiError.code = json.code || '';
                    apiError.status = request.status;
                    apiError.questionKey = json.data && json.data.question_key ? json.data.question_key : '';
                    reject(apiError);
                    return;
                }
                resolve(json);
            };
            request.onerror = function () {
                reject(new Error('QualiNav request failed.'));
            };
            request.ontimeout = function () {
                reject(new Error('QualiNav request timed out. Please try again or contact support.'));
            };
            request.send(options.body || null);
        });
    }

    function apiForm(path, formData, options) {
        options = options || {};
        return new Promise(function (resolve, reject) {
            var win = browserWindow();
            var BrowserXMLHttpRequest = (typeof XMLHttpRequest !== 'undefined' && XMLHttpRequest) || (win && win.XMLHttpRequest);
            if (!BrowserXMLHttpRequest) {
                reject(new Error('Your browser could not start the request. Please refresh and try again.'));
                return;
            }
            var request = new BrowserXMLHttpRequest();
            request.timeout = options.timeout || 360000;
            request.open('POST', restUrl + path, true);
            request.setRequestHeader('X-WP-Nonce', nonce);
            request.onload = function () {
                var json = {};
                try {
                    json = request.responseText ? JSON.parse(request.responseText) : {};
                } catch (error) {
                    reject(new Error('QualiNav returned an unreadable response.'));
                    return;
                }
                if (request.status < 200 || request.status >= 300) {
                    reject(new Error(json.message || 'QualiNav request failed.'));
                    return;
                }
                resolve(json);
            };
            request.onerror = function () {
                reject(new Error('QualiNav request failed.'));
            };
            request.ontimeout = function () {
                reject(new Error('Document processing timed out. The upload may still be processing; refresh before retrying.'));
            };
            request.send(formData);
        });
    }

    function friendlyApiErrorMessage(error, fallback) {
        var message = error && error.message ? error.message : '';
        if (!message) {
            return fallback;
        }
        if (/timed out/i.test(message)) {
            return 'Final setup could not be submitted because the request timed out. Please try again or contact support.';
        }
        if (/permission|forbidden|cannot submit|cannot edit/i.test(message)) {
            return 'Final setup could not be submitted because you do not have permission for this hospital.';
        }
        if (/required field|validation|incomplete/i.test(message)) {
            return 'Final setup could not be submitted because required information is incomplete. ' + message;
        }
        if (/unreadable response|request failed|server/i.test(message)) {
            return fallback;
        }
        return message;
    }

    function text(value) {
        return value === null || value === undefined || value === '' ? '-' : String(value);
    }

    var publicAcronymLabels = {
        acc: 'ACC',
        achc: 'ACHC',
        ahrq: 'AHRQ',
        ai: 'AI',
        ami: 'AMI',
        api: 'API',
        cah: 'CAH',
        cauti: 'CAUTI',
        ccn: 'CCN',
        cdc: 'CDC',
        ceo: 'CEO',
        cfo: 'CFO',
        cihq: 'CIHQ',
        clabsi: 'CLABSI',
        clia: 'CLIA',
        cms: 'CMS',
        cphq: 'CPHQ',
        cpps: 'CPPS',
        crna: 'CRNA',
        dexa: 'DEXA',
        dnv: 'DNV',
        docx: 'DOCX',
        ecqm: 'eCQM',
        ecqms: 'eCQMs',
        edpec: 'EDPEC',
        edtc: 'EDTC',
        ehr: 'EHR',
        elr: 'ELR',
        ems: 'EMS',
        fmea: 'FMEA',
        hac: 'HAC',
        hai: 'HAI',
        hapi: 'HAPI',
        hcahps: 'HCAHPS',
        hcp: 'HCP',
        hfap: 'HFAP',
        hipaa: 'HIPAA',
        hqic: 'HQIC',
        hqr: 'HQR',
        hrrp: 'HRRP',
        ipps: 'IPPS',
        iqr: 'IQR',
        lwbs: 'LWBS',
        mbqip: 'MBQIP',
        mri: 'MRI',
        mrsa: 'MRSA',
        ncdr: 'NCDR',
        nhsn: 'NHSN',
        ocr: 'OCR',
        oqr: 'OQR',
        pdf: 'PDF',
        pdsa: 'PDSA',
        phi: 'PHI',
        poc: 'POC',
        pps: 'PPS',
        pso: 'PSO',
        pssm: 'PSSM',
        qapi: 'QAPI',
        qi: 'QI',
        rca: 'RCA',
        rn: 'RN',
        safer: 'SAFER',
        smtp: 'SMTP',
        ssi: 'SSI',
        tjc: 'TJC',
        txt: 'TXT',
        ui: 'UI',
        url: 'URL',
        utc: 'UTC',
        vbp: 'VBP',
        vp: 'VP',
        zip: 'ZIP'
    };

    function normalizePublicSetupCopy(value) {
        var normalized = text(value).replace(/\bday 0\b/gi, function (match) {
            return match === match.toUpperCase() ? 'HOSPITAL SETUP' : 'Hospital Setup';
        });
        return normalized.replace(/\b[A-Za-z][A-Za-z0-9]*\b/g, function (token) {
            return publicAcronymLabels[token.toLowerCase()] || token;
        });
    }

    function escapeHtml(value) {
        return text(value).replace(/[&<>"']/g, function (char) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
        });
    }

    function setText(selector, value) {
        var node = document.querySelector(selector);
        if (node) {
            node.textContent = value === null || value === undefined ? '-' : String(value);
        }
    }

    function bindInstantSearch(id, callback) {
        var node = document.getElementById(id);
        if (node) {
            node.addEventListener('input', function () {
                callback(node.value || '');
            });
        }
    }

    function bindFilterChanges(ids, callback) {
        ids.forEach(function (id) {
            var node = document.getElementById(id);
            if (node) {
                node.addEventListener('change', callback);
            }
        });
    }

    function isMyOrgEmbeddedShell() {
        return !!(config.isEmbeddedShell || config.shellMode === 'my-org' || document.body.classList.contains('qn-myorg-embedded-console'));
    }

    function applyBrand(brand) {
        if (!brand) {
            return;
        }
        var root = document.documentElement;
        var pairs = {
            '--qn-primary': brand.primary_color,
            '--qn-secondary': brand.secondary_color,
            '--qn-accent': brand.accent_color,
            '--qn-sidebar': brand.sidebar_color,
            '--qn-bg': brand.background_color,
            '--qn-card': brand.card_color,
            '--qn-text': brand.text_color,
            '--qn-radius-button': brand.button_radius,
            '--qn-radius-card': brand.card_radius
        };
        Object.keys(pairs).forEach(function (key) {
            if (pairs[key]) {
                root.style.setProperty(key, pairs[key]);
            }
        });
    }

    function getInitialAdminData() {
        var node = document.getElementById('qn-initial-admin-data');
        if (!node || !node.textContent) {
            return {};
        }
        try {
            return JSON.parse(node.textContent);
        } catch (error) {
            return {};
        }
    }

    function loadHospitalConsole() {
        var previewOrganizationId = getUrlOrganizationId();
        api('/me').then(function (me) {
            state.me = me;
            if (previewOrganizationId && isGlobalAdmin()) {
                state.hospitalPeopleLoaded = true;
                state.hospitalPeoplePreviewMode = true;
                setTableMessage('qn-hospital-users-table-body', 5, 'Super Admin setup preview mode.');
                setTableMessage('qn-hospital-invitations-table-body', 6, 'Super Admin setup preview mode.');
                renderHospitalUsersOverview();
                renderHospitalDashboard();
                loadHospitalShellSupport();
                return loadOnboarding(previewOrganizationId, {showLoading: false}).then(function () {
                    return loadScoutRuns(previewOrganizationId);
                });
            }

            return Promise.all([api('/brand'), api('/my-organizations')]).then(function (results) {
                applyBrand(results[0]);
                state.myOrganizations = results[1] || [];
                renderHospitalSwitcher();
            }).then(function () {
                renderHospitalDashboard();
                loadHospitalPeopleData();
                return loadOnboarding(null, {showLoading: false}).then(function () {
                    return loadScoutRuns();
                });
            });
        }).catch(function (error) {
            renderOnboardingLoadError(error);
            setTableMessage('qn-hospital-users-table-body', 5, error.message);
        });
    }

    function loadHospitalShellSupport() {
        Promise.all([api('/brand'), api('/my-organizations')]).then(function (results) {
            applyBrand(results[0]);
            state.myOrganizations = results[1] || [];
            renderHospitalSwitcher();
        }).catch(function (error) {
            showToast(error.message || 'Hospital switcher data could not load.', 'warning');
        });
    }

    function loadHomeWelcome() {
        Promise.all([api('/me'), api('/brand'), api('/my-organizations')]).then(function (results) {
            state.me = results[0];
            applyBrand(results[1]);
            state.myOrganizations = results[2] || [];
            if (state.myOrganizations.length) {
                var welcomeOrganizationId = config.welcomeOrganizationId ? Number(config.welcomeOrganizationId) : 0;
                var currentId = welcomeOrganizationId || (state.me && state.me.organization_id ? Number(state.me.organization_id) : 0);
                state.currentHospital = state.myOrganizations.find(function (item) {
                    return Number(item.organization_id || item.id) === currentId;
                }) || state.myOrganizations[0];
                if (welcomeOrganizationId) {
                    state.onboardingOrganizationId = welcomeOrganizationId;
                }
            }
            maybeShowWorkspaceWelcome();
        }).catch(function (error) {
            showToast(error.message || 'Unable to load QualiNav welcome.', 'warning');
        });
    }


    function loadAdminConsole() {
        var initialData = getInitialAdminData();
        var initial = initialData && Array.isArray(initialData.hospitals) ? initialData.hospitals : null;
        if (initial) {
            hydrateAdminHospitals(initial);
            window.setTimeout(loadAdminSupportData, 0);
            return;
        }
        api('/admin/hospitals').then(function (hospitals) {
            hydrateAdminHospitals(hospitals || []);
            window.setTimeout(loadAdminSupportData, 0);
        }).catch(function (error) {
            setTableMessage('qn-hospitals-table-body', 6, error.message);
        });
    }

    function hydrateAdminHospitals(hospitals) {
        state.hospitals = hospitals || [];
        state.allHospitals = state.hospitals.slice();
        state.hospitalFilter = null;
        resetHospitalPagination();
        renderHospitals();
        renderAdminFilters();
    }

    function loadAdminSupportData() {
        return Promise.all([
            api('/me'),
            api('/admin/dashboard'),
            api('/admin/states'),
            api('/brand'),
            api('/admin/health-systems'),
            api('/hospital-types'),
            api('/service-models')
        ]).then(function (results) {
            state.me = results[0];
            renderMetrics(results[1]);
            state.states = results[2] || [];
            applyBrand(results[3]);
            renderBrandPreview(results[3], 'Default QualiNav Theme');
            state.healthSystems = results[4] || [];
            state.hospitalTypes = results[5] || [];
            state.serviceModels = results[6] || [];
            updateEnterpriseMetrics();
            renderStateOptions();
            renderSystemOptions();
            renderClassificationOptions();
            renderHealthSystems();
        }).catch(function (error) {
            showToast(error.message || 'Some admin data could not be loaded.', 'warning');
        });
    }

    function loadAdminPeopleData() {
        if (state.adminPeopleLoaded || state.adminPeopleLoading) {
            return Promise.resolve();
        }
        state.adminPeopleLoading = true;
        return Promise.all([api('/admin/users'), api('/admin/invitations')]).then(function (results) {
            state.users = results[0] || [];
            state.invitations = results[1] || [];
            state.adminPeopleLoaded = true;
            updateEnterpriseMetrics();
            renderAdminFilters();
            renderAdminUsers();
            renderInvitations('admin');
        }).catch(function (error) {
            setTableMessage('qn-admin-users-table-body', 6, error.message);
            setTableMessage('qn-admin-invitations-table-body', 6, error.message);
        }).then(function () {
            state.adminPeopleLoading = false;
        });
    }

    function loadAdminSystemCheckData() {
        if (state.adminSystemCheckLoaded || state.adminSystemCheckLoading) {
            return Promise.resolve();
        }
        state.adminSystemCheckLoading = true;
        return api('/admin/system-check').then(function (result) {
            state.systemCheck = result || null;
            state.adminSystemCheckLoaded = true;
            renderSystemCheck();
        }).catch(function (error) {
            showToast(error.message || 'Unable to load system check.', 'warning');
        }).then(function () {
            state.adminSystemCheckLoading = false;
        });
    }

    function ensureAdminSectionData(section) {
        if (!document.body.classList.contains('qn-admin-console-page')) {
            return;
        }
        if (section === 'users' || section === 'invitations') {
            loadAdminPeopleData();
        }
        if (section === 'system-check') {
            loadAdminSystemCheckData();
        }
    }

    function refreshAdminPeople() {
        return Promise.all([api('/admin/users'), api('/admin/invitations'), api('/admin/dashboard')]).then(function (results) {
            state.users = results[0] || [];
            state.invitations = results[1] || [];
            state.adminPeopleLoaded = true;
            renderAdminUsers();
            renderInvitations('admin');
            renderMetrics(results[2]);
        });
    }

    function refreshHospitalPeople() {
        return Promise.all([api('/me'), api('/my-organizations'), api('/hospital/users'), api('/hospital/invitations')]).then(function (results) {
            state.me = results[0];
            state.myOrganizations = results[1] || [];
            state.users = results[2] || [];
            state.invitations = results[3] || [];
            state.hospitalPeopleLoaded = true;
            state.hospitalPeoplePreviewMode = false;
            renderHospitalSwitcher();
            renderHospitalFilters();
            renderHospitalUsers();
            renderInvitations('hospital');
            renderHospitalDashboard();
            return loadOnboarding(null, {showLoading: false}).then(function () {
                return loadScoutRuns();
            });
        });
    }

    function loadHospitalPeopleData() {
        return Promise.all([api('/hospital/users'), api('/hospital/invitations')]).then(function (people) {
            state.users = people[0] || [];
            state.invitations = people[1] || [];
            state.hospitalPeopleLoaded = true;
            state.hospitalPeoplePreviewMode = false;
            renderHospitalUsers();
            renderInvitations('hospital');
            renderHospitalDashboard();
        }).catch(function (error) {
            setTableMessage('qn-hospital-users-table-body', 5, error.message);
            setTableMessage('qn-hospital-invitations-table-body', 6, error.message);
        });
    }

    function refreshHospitalWorkspace() {
        return Promise.all([api('/me'), api('/my-organizations')]).then(function (results) {
            state.me = results[0];
            state.myOrganizations = results[1] || [];
            state.hospitalPeopleLoaded = false;
            state.hospitalPeoplePreviewMode = false;
            renderHospitalSwitcher();
            renderHospitalFilters();
            renderHospitalDashboard();
            loadHospitalPeopleData();
            return loadOnboarding(null, {showLoading: false}).then(function () {
                loadScoutRuns();
            });
        });
    }

    function renderHospitalSwitcher() {
        var name = document.getElementById('qn-current-hospital-name');
        var switcher = document.getElementById('qn-hospital-switcher');
        if (!name || !switcher || !state.myOrganizations) {
            return;
        }
        var currentId = state.me ? Number(state.me.organization_id) : 0;
        var previewOrganizationId = getUrlOrganizationId();
        var selectedId = previewOrganizationId ? Number(previewOrganizationId) : currentId;
        var current = state.myOrganizations.find(function (item) {
            return Number(item.organization_id) === selectedId;
        });
        state.currentHospital = current || null;
        renderHospitalContext(current);
        if (state.myOrganizations.length > 1) {
            name.textContent = isMyOrgEmbeddedShell() && current ? current.organization_name : 'Hospital workspace';
            name.classList.add('qn-switcher-label');
            switcher.hidden = false;
            switcher.innerHTML = state.myOrganizations.map(function (item) {
                var selected = Number(item.organization_id) === selectedId ? ' selected' : '';
                return '<option value="' + item.organization_id + '"' + selected + '>' + escapeHtml(hospitalSwitcherLabel(item)) + '</option>';
            }).join('');
            syncSearchableSelect(switcher);
        } else {
            name.textContent = current ? current.organization_name : 'Hospital workspace';
            name.classList.remove('qn-switcher-label');
            switcher.hidden = true;
            syncSearchableSelect(switcher);
        }
        renderHospitalDashboard();
    }

    function hospitalSwitcherLabel(item) {
        return [
            text(item.organization_name),
            text(item.parent_system_name || 'Independent'),
            text(item.hospital_type_label || 'Not specified.'),
            text(item.state_code || item.state_name)
        ].filter(Boolean).join(' | ');
    }

    function renderHospitalContext(item) {
        if (!item) {
            renderHospitalDashboard();
            return;
        }
        setText('[data-context="system"]', item.parent_system_name || 'Independent');
        setText('[data-context="type"]', item.hospital_type_label || 'Not specified.');
        setText('[data-context="service"]', item.service_model_label || 'Not specified.');
        setText('[data-context="payment"]', item.payment_model_label || 'Available after setup');
        setText('[data-context="state"]', item.state_code || item.state_name);
        setText('[data-context="workspace"]', item.organization_name || 'Hospital workspace');
        renderHospitalUsersOverview();
        renderReportingPage();
        renderCommitteesPage();
        renderPlansPoliciesPage();
        renderHospitalDashboard();
    }

    function updateEmbeddedHospitalHeader(hospital) {
        if (!isMyOrgEmbeddedShell() || !hospital) {
            return;
        }
        var name = document.getElementById('qn-current-hospital-name');
        if (name) {
            name.textContent = hospital.organization_name || hospital.name || 'Hospital workspace';
        }
    }

    function renderHospitalDataSections() {
        renderScoutPreview();
        renderReportingPage();
        renderCommitteesPage();
        renderPlansPoliciesPage();
        renderFutureModules();
        renderClinicalMonitoringPage();
        renderSettingsPage();
        renderHospitalDashboard();
    }

    function renderHospitalDashboard() {
        var dashboard = document.getElementById('qn-hospital-dashboard');
        if (!dashboard) {
            return;
        }
        var hospital = dashboardHospital();
        var hero = document.getElementById('qn-dashboard-hero');
        var summary = document.getElementById('qn-dashboard-summary-grid');
        var modules = document.getElementById('qn-dashboard-module-grid');
        if (!hospital) {
            if (!(state.me && state.myOrganizations && !state.myOrganizations.length && !getUrlOrganizationId())) {
                return;
            }
            if (hero) {
                hero.classList.remove('qn-dashboard-loading');
                hero.innerHTML = '<div class="qn-dashboard-empty">' +
                    '<span class="dashicons dashicons-building"></span>' +
                    '<div><p class="qn-eyebrow">Hospital Workspace</p><h2>No hospital selected</h2>' +
                    '<p>Select a hospital workspace to view its dashboard.</p></div>' +
                    '</div>';
            }
            if (summary) {
                summary.innerHTML = '';
            }
            if (modules) {
                modules.innerHTML = '';
            }
            return;
        }
        if (hero) {
            hero.classList.remove('qn-dashboard-loading');
        }
        updateEmbeddedHospitalHeader(hospital);
        var setup = dashboardSetupStatus(hospital);
        var scout = dashboardScoutStatus();
        var role = state.me ? roleLabel(state.me.qualinav_role) : 'Workspace role';
        var subtitle = dashboardHospitalSubtitle(hospital);
        setText('[data-dashboard="hospital_name"]', hospital.organization_name || hospital.name || 'Hospital workspace');
        setText('[data-dashboard="hospital_subtitle"]', subtitle);
        setText('[data-dashboard="role_badge"]', role);
        setText('[data-dashboard="setup_percent"]', setup.percent + '%');
        setText('[data-dashboard="scout_status"]', scout.status);
        var setupBar = document.querySelector('[data-dashboard="setup_bar"]');
        if (setupBar) {
            setupBar.style.width = setup.percent + '%';
        }
        var chips = document.querySelector('[data-dashboard="hero_chips"]');
        if (chips) {
            chips.innerHTML = [
                chip(hospital.hospital_type_label || 'Hospital type not set'),
                chip(hospital.service_model_label || 'Service model not set'),
                hospital.payment_model_label ? chip(hospital.payment_model_label) : '',
                chip(hospital.state_code || hospital.state_name || 'State not set')
            ].filter(Boolean).join('');
        }
        setText('[data-context="system"]', hospital.parent_system_name || 'Independent');
        setText('[data-context="type"]', hospital.hospital_type_label || 'Not specified');
        setText('[data-context="service"]', hospital.service_model_label || 'Not specified');
        setText('[data-context="payment"]', hospital.payment_model_label || 'Available after setup');
        setText('[data-context="state"]', hospital.state_code || hospital.state_name || 'Not specified');
        setText('[data-context="workspace"]', hospital.organization_name || hospital.name || 'Hospital workspace');

        if (summary) {
            summary.innerHTML = [
                dashboardCard({
                    icon: 'clipboard',
                    eyebrow: 'Hospital Setup',
                    title: setup.status,
                    detail: setup.detail,
                    progress: setup.percent,
                    status: setup.tone,
                    cta: setup.percent >= 100 ? 'Review Setup' : 'Continue Setup',
                    target: 'day-0-setup',
                    primary: setup.percent < 100
                }),
                dashboardCard({
                    icon: 'lightbulb',
                    eyebrow: 'Scout Setup Preview',
                    title: scout.status,
                    detail: scout.detail,
                    status: scout.tone,
                    cta: scout.cta,
                    target: 'scout-preview',
                    primary: scout.tone === 'warning' || scout.tone === 'success'
                }),
                dashboardCard(dashboardUsersCard())
            ].join('');
        }
        if (modules) {
            modules.innerHTML = [
                dashboardModule('chart-bar', 'Reporting Schedule', scout.ready ? 'Ready' : 'Available after Scout preview', scout.ready ? 'Reporting workflow can be reviewed from the Scout preview.' : 'Scout preview will shape the reporting schedule.', 'View Reporting', 'reporting', scout.ready ? 'success' : 'neutral'),
                dashboardModule('businessperson', 'Committees', scout.ready ? 'Ready' : 'Available after Scout preview', scout.ready ? 'Committee flow and meeting cadence are available from Scout.' : 'Committee workflow opens after Scout preview.', 'View Committees', 'committees', scout.ready ? 'success' : 'neutral'),
                dashboardModule('media-document', 'Plans & Policies', scout.ready ? 'Ready' : 'Available after Scout preview', scout.ready ? 'Plan and policy tasks are ready to review.' : 'Scout preview will identify plan and policy priorities.', 'View Plans', 'plans', scout.ready ? 'success' : 'neutral'),
                dashboardModule('heart', 'Clinical Monitoring', scout.ready ? 'Ready' : 'Available after Scout preview', scout.ready ? 'Clinical monitoring areas are ready to review.' : 'Scout preview will tailor clinical monitoring areas.', 'View Monitoring', 'clinical', scout.ready ? 'success' : 'neutral'),
                dashboardModule('flag', 'Priority Queue', scout.ready ? 'Items Found' : 'Pending Scout', scout.ready ? 'Priority items are available in the generated setup preview.' : 'Priority queue appears after Scout preview.', 'View Priorities', 'scout-preview', scout.ready ? 'warning' : 'neutral'),
                dashboardModule('shield', 'System Health', 'Healthy', 'Workspace services are reachable for this console session.', '', '', 'success')
            ].join('');
        }
        renderWorkspaceGuideCard(setup);
        maybeShowWorkspaceWelcome();
        renderReportingPage();
        renderCommitteesPage();
        renderPlansPoliciesPage();
        renderFutureModules();
        renderClinicalMonitoringPage();
        renderSettingsPage();
    }

    function renderWorkspaceGuideCard(setup) {
        var card = document.getElementById('qn-workspace-guide-card');
        if (!card) {
            return;
        }
        card.hidden = false;
        var canEdit = canUseHospitalSetupEditCopy();
        setText('#qn-workspace-guide-card-copy', isGlobalAdmin() ?
            'Preview the Hospital Quality Director welcome experience for this hospital workspace without triggering it automatically.' : (canEdit ?
            'A quick guide to Hospital Setup, Scout, and the workspace modules that organize your quality work.' :
            'A quick guide to reviewing Hospital Setup, Scout, and the workspace modules available to your role.'));
        setText('#qn-workspace-guide-setup-button', isGlobalAdmin() ? 'Review Hospital Setup' : (canEdit && setup && setup.percent > 0 ? 'Continue Hospital Setup' : (canEdit ? 'Start Hospital Setup' : 'View Hospital Setup')));
    }

    function dashboardHospital() {
        if (state.currentHospital) {
            return state.currentHospital;
        }
        if (!state.onboarding) {
            return null;
        }
        return {
            organization_name: state.onboarding.current_organization_name,
            parent_system_name: state.onboarding.parent_system_name || 'Independent',
            hospital_type: state.onboarding.hospital_type,
            hospital_type_label: state.onboarding.hospital_type_label,
            service_model: state.onboarding.service_model,
            service_model_label: state.onboarding.service_model_label,
            payment_model_label: state.onboarding.payment_model_label,
            state_code: state.onboarding.state_code,
            state_name: state.onboarding.state_name,
            onboarding_percent: state.onboarding.progress ? state.onboarding.progress.total_percent : 0,
            onboarding_status: state.onboarding.onboarding_status || '',
            onboarding_submitted: !!state.onboarding.onboarding_submitted
        };
    }

    function dashboardHospitalSubtitle(hospital) {
        return [
            hospital.hospital_type_label || 'Hospital type not set',
            hospital.parent_system_name || 'Independent',
            hospital.state_code || hospital.state_name || 'State not set'
        ].filter(Boolean).join(' · ');
    }

    function dashboardSetupStatus(hospital) {
        var percent = 0;
        if (state.onboarding && state.onboarding.progress) {
            percent = Number(state.onboarding.progress.total_percent) || 0;
        } else if (hospital && hospital.onboarding_percent !== undefined) {
            percent = Number(hospital.onboarding_percent) || 0;
        }
        percent = Math.max(0, Math.min(100, Math.round(percent)));
        if (percent >= 100) {
            return {percent: percent, status: 'Complete', detail: 'Hospital Setup is ready for review.', tone: 'success'};
        }
        if (isOnboardingSubmitted(hospital)) {
            return {percent: percent, status: 'Submitted', detail: 'Hospital Setup was submitted. Some optional answers may still be incomplete.', tone: 'success'};
        }
        if (percent > 0) {
            return {percent: percent, status: 'In progress', detail: 'Continue setup to improve Scout readiness.', tone: 'warning'};
        }
        return {percent: percent, status: 'Not started', detail: 'Start Hospital Setup to configure this workspace.', tone: 'neutral'};
    }

    function isOnboardingSubmitted(hospital) {
        return !!state.scoutOnboardingSubmitted ||
            !!state.latestScoutRun ||
            !!(state.onboarding && state.onboarding.onboarding_submitted) ||
            !!(state.onboarding && state.onboarding.onboarding_status === 'submitted') ||
            !!(hospital && hospital.onboarding_submitted) ||
            !!(hospital && hospital.onboarding_status === 'submitted');
    }

    function isDay0Pending(setup) {
        return setup.percent < 100 && !isOnboardingSubmitted();
    }

    function dashboardScoutStatus() {
        if (state.latestScoutRun && state.latestScoutRun.status === 'failed') {
            return {status: 'Failed', detail: 'Retry the preview after reviewing setup inputs.', cta: 'Retry', tone: 'danger', ready: false};
        }
        if (state.latestScoutRun && (state.latestScoutRun.status === 'running' || state.latestScoutRun.status === 'pending')) {
            return {status: 'Generating', detail: 'Scout is preparing setup recommendations.', cta: 'Open Preview', tone: 'warning', ready: false};
        }
        if (state.latestScoutRun) {
            return {status: 'Ready', detail: 'Last generated ' + text(state.latestScoutRun.created_at), cta: 'Open Preview', tone: 'success', ready: true};
        }
        if (!isOnboardingSubmitted()) {
            return {status: 'Not generated', detail: 'Available after Hospital Setup is submitted.', cta: 'Generate Preview', tone: 'neutral', ready: false};
        }
        if (!state.scoutBridgeAvailable) {
            return {status: 'Not available', detail: 'Scout preview is not available right now.', cta: 'Open Preview', tone: 'warning', ready: false};
        }
        return {status: 'Not generated', detail: 'Generate a setup preview when Hospital Setup is ready.', cta: 'Generate Preview', tone: 'neutral', ready: false};
    }

    function futureModuleDefinitions() {
        return {
            reporting: {
                icon: 'chart-bar',
                eyebrow: 'Reporting',
                title: 'Reporting Schedule',
                subtitle: 'Track recurring reports, due dates, owners, approvals, and preparation lead time.',
                message: 'Reporting workflows will be created from your Hospital Setup and Scout preview.',
                capabilities: [
                    ['media-spreadsheet', 'Master Reporting Schedule', 'Recurring federal, state, accreditation, payer, and internal reports.'],
                    ['calendar-alt', 'Due Date Reminders', 'Lead times, buffers, known dates, and event-triggered deadlines.'],
                    ['admin-users', 'Owner & Backup Tracking', 'Primary preparers, backup coverage, and visibility needs.'],
                    ['groups', 'Board / Committee Prep', 'Preparation timing before committee and board meetings.']
                ]
            },
            committees: {
                icon: 'businessperson',
                eyebrow: 'Committees',
                title: 'Committees',
                subtitle: 'Manage meeting cadence, report flow, committee relationships, and board reporting.',
                message: 'Committee workflows will be mapped from your meeting structure, report flow, and Scout preview.',
                capabilities: [
                    ['calendar-alt', 'Meeting Calendar', 'Standing committees, cadence, timing, and participation.'],
                    ['networking', 'Report Flow Map', 'How quality information moves from workgroups to leadership.'],
                    ['media-text', 'Agenda & Minutes Tracking', 'Standing agenda items, minutes owners, and storage locations.'],
                    ['chart-line', 'Board Quality Reporting', 'Board packet timing and quality reporting relationships.']
                ]
            },
            plans: {
                icon: 'media-document',
                eyebrow: 'Plans & Policies',
                title: 'Plans & Policies',
                subtitle: 'Track required plans, policy review cycles, templates, owners, and approval status.',
                message: 'Plan and policy queues will be shaped by your required plan status, review cycles, and Scout preview.',
                capabilities: [
                    ['portfolio', 'Required Plan Review', 'QAPI, patient safety, infection prevention, emergency preparedness, and risk plans.'],
                    ['update', 'Policy Review Cycle', 'Annual review cadence, owners, and overdue policy signals.'],
                    ['clipboard', 'Templates Needed', 'Project, RCA, FMEA, board report, and survey readiness templates.'],
                    ['yes-alt', 'Approval Routing', 'Board, executive, medical staff, and committee approval pathways.']
                ]
            },
            clinical: {
                icon: 'heart',
                eyebrow: 'Clinical Monitoring',
                title: 'Clinical Monitoring',
                subtitle: 'Track applicable monitoring areas, review cadence, committees, and priority gaps.',
                message: 'Clinical monitoring tasks will be created from your service model, monitoring gaps, and Scout preview.',
                capabilities: [
                    ['list-view', 'Required Monitoring Areas', 'M&M, blood usage, medication safety, procedures, anesthesia, and ancillary services.'],
                    ['clock', 'Review Cadence', 'Monthly, quarterly, event-triggered, and not-applicable monitoring rhythms.'],
                    ['flag', 'Priority Gaps', 'High-risk monitoring areas Scout should prioritize first.'],
                    ['groups', 'Committee Routing', 'Where monitoring data is reviewed and escalated.']
                ]
            },
            settings: {
                icon: 'admin-settings',
                eyebrow: 'Settings',
                title: 'Hospital Settings',
                subtitle: 'Manage workspace preferences, reminders, and hospital context.',
                message: 'Settings editing will be available after initial setup is complete.',
                capabilities: [
                    ['building', 'Hospital Workspace', 'Hospital type, service model, state, and workspace context.'],
                    ['bell', 'Reminder Preferences', 'Lead time, buffer, digest, and alert preferences.'],
                    ['visibility', 'Backup Visibility', 'Backup users and workspace visibility needs.'],
                    ['admin-customizer', 'Brand / Display Preferences', 'Workspace display and future brand preferences.']
                ],
                settings: true
            }
        };
    }

    function renderFutureModules() {
        document.querySelectorAll('[data-future-module]').forEach(function (node) {
            var definition = futureModuleDefinitions()[node.getAttribute('data-future-module')];
            if (definition) {
                node.innerHTML = renderFutureModule(definition);
            }
        });
    }

    function renderFutureModule(definition) {
        var hospital = dashboardHospital() || currentHospitalContext() || {};
        var setup = dashboardSetupStatus(hospital);
        var scout = dashboardScoutStatus();
        var action = futureModuleAction(setup, scout, definition);
        var status = futureModuleStatus(setup, scout, definition);
        var hospitalName = hospital.organization_name || hospital.name || 'Hospital workspace';
        var chips = [
            chip(hospitalName),
            chip(hospital.hospital_type_label || 'Hospital type not set'),
            chip(hospital.service_model_label || 'Service model not set'),
            chip(hospital.state_code || hospital.state_name || 'State not set')
        ].join('');
        return '<div class="qn-future-hero">' +
            '<div><p class="qn-eyebrow">' + escapeHtml(definition.eyebrow) + '</p><h2>' + escapeHtml(definition.title) + '</h2><p>' + escapeHtml(definition.subtitle) + '</p><div class="qn-future-context">' + chips + '</div></div>' +
            '<span class="qn-status-pill qn-status-' + escapeHtml(status.tone) + '">' + escapeHtml(status.label) + '</span>' +
            '</div>' +
            '<section class="qn-future-main-card">' +
            '<span class="dashicons dashicons-' + escapeHtml(definition.icon) + '"></span>' +
            '<div><h3>' + escapeHtml(definition.message) + '</h3><p>' + escapeHtml(futureModuleDetail(setup, scout, definition)) + '</p><div class="qn-future-actions"><button class="qn-button qn-button-primary" type="button" data-section-target="' + escapeHtml(action.target) + '">' + escapeHtml(action.label) + '</button><span>' + escapeHtml(action.helper) + '</span></div></div>' +
            '</section>' +
            '<div class="qn-future-capability-grid">' + definition.capabilities.map(function (item) {
                return '<article class="qn-future-capability-card"><span class="dashicons dashicons-' + escapeHtml(item[0]) + '"></span><h3>' + escapeHtml(item[1]) + '</h3><p>' + escapeHtml(item[2]) + '</p></article>';
            }).join('') + '</div>';
    }

    function futureModuleStatus(setup, scout, definition) {
        if (definition.settings && setup.percent >= 100) {
            return {label: 'Coming Soon', tone: 'neutral'};
        }
        if (isDay0Pending(setup)) {
            return {label: 'Hospital Setup pending', tone: 'pending'};
        }
        if (!scout.ready) {
            return {label: 'Available after Scout Preview', tone: 'warning'};
        }
        return {label: 'Coming Soon', tone: 'neutral'};
    }

    function futureModuleAction(setup, scout, definition) {
        if (isDay0Pending(setup)) {
            return {target: 'day-0-setup', label: 'Continue Hospital Setup', helper: 'Complete setup so Scout can build the operating-system draft.'};
        }
        if (scout.ready) {
            return {target: 'scout-preview', label: 'Open Scout Preview', helper: definition.settings ? 'Review setup-derived preferences before settings editing opens.' : 'Review the generated draft that will feed this module.'};
        }
        return {target: 'scout-preview', label: 'Generate Scout Preview', helper: 'Scout preview is the next step after Hospital Setup is submitted.'};
    }

    function futureModuleDetail(setup, scout, definition) {
        if (definition.settings) {
            return 'QualiNav will use your setup preferences for reminders, backup visibility, and workspace context. Editing controls will open after setup is complete.';
        }
        if (isDay0Pending(setup)) {
            return 'This section depends on Hospital Setup answers and will become more specific as setup is completed.';
        }
        if (!scout.ready) {
            return 'Generate Scout Preview to turn setup answers into draft schedules, tasks, workflows, and monitoring priorities.';
        }
        return 'Scout has setup context available. This module will use the preview output as future workflow tools are enabled.';
    }

    function renderReportingPage() {
        var page = document.getElementById('reporting');
        if (!page) {
            return;
        }
        page.classList.add('qn-reporting-page');
        page.classList.toggle('qn-readonly-module', isReadOnlyWorkspaceRole());
        var hospital = dashboardHospital() || currentHospitalContext() || {};
        var setup = dashboardSetupStatus(hospital);
        var scout = dashboardScoutStatus();
        var reporting = reportingPreviewData();
        var action = reportingPageAction(setup, scout);
        var hospitalName = hospital.organization_name || hospital.name || 'Hospital workspace';
        var showScoutChip = !isDay0Pending(setup);
        var scoutStatus = state.latestScoutRun ? scout.status : (state.scoutOnboardingSubmitted ? 'Not generated' : 'Pending Scout');
        var chips = [
            chip(hospitalName),
            chip(hospital.hospital_type_label || 'Hospital type not set'),
            chip(hospital.service_model_label || 'Service model not set'),
            hospital.payment_model_label ? chip(hospital.payment_model_label) : '',
            showScoutChip ? chip('Scout: ' + scoutStatus) : ''
        ].filter(Boolean).join('');
        var headerStatus = readOnlyReviewBadge() + (isDay0Pending(setup) ? '' : '<span class="qn-status-pill qn-status-' + escapeHtml(reportingStatusTone(setup, scout, reporting)) + '">' + escapeHtml(reportingStatusLabel(setup, scout, reporting)) + '</span>');
        page.innerHTML =
            renderReportingAdminBanner(hospitalName) +
            '<div class="qn-reporting-header">' +
            '<div><p class="qn-eyebrow">Reporting</p><h2>Reporting Schedule</h2><p>' + escapeHtml(isReadOnlyWorkspaceRole() ? 'Review reporting readiness, schedule signals, and missing due-date detail.' : 'Track recurring reports, due dates, owners, approvals, and preparation lead time.') + '</p><div class="qn-reporting-context">' + chips + '</div></div>' +
            headerStatus +
            '</div>' +
            renderReportingSummary(setup, scout, reporting) +
            renderReportingPrimaryState(setup, scout, reporting, action) +
            renderReportingCapabilityCards(setup, scout, reporting);
    }

    function renderReportingAdminBanner(hospitalName) {
        var previewOrganizationId = getUrlOrganizationId();
        if (!(isGlobalAdmin() && previewOrganizationId)) {
            return '';
        }
        return '<div class="qn-scout-admin-banner qn-reporting-admin-banner">' +
            '<span class="dashicons dashicons-visibility"></span>' +
            '<strong>Admin Preview Mode</strong>' +
            '<span>viewing Reporting for ' + escapeHtml(hospitalName || 'this hospital') + '.</span>' +
            '<a class="qn-button qn-button-small" href="' + escapeHtml((config.homeUrl || '/') + 'qualinav/admin') + '">Back to Admin Console</a>' +
            '</div>';
    }

    function reportingStatusTone(setup, scout, reporting) {
        if (isDay0Pending(setup)) {
            return 'pending';
        }
        if (!scout.ready) {
            return 'warning';
        }
        return reporting.items.length ? 'success' : 'neutral';
    }

    function reportingStatusLabel(setup, scout, reporting) {
        if (isDay0Pending(setup)) {
            return 'Hospital Setup pending';
        }
        if (!scout.ready) {
            return 'Available after Scout Preview';
        }
        return reporting.items.length ? 'Ready' : 'Pending Scout';
    }

    function reportingPageAction(setup, scout) {
        if (isReadOnlyWorkspaceRole()) {
            return readOnlyPageAction();
        }
        if (isDay0Pending(setup)) {
            return {target: 'day-0-setup', label: 'Continue Hospital Setup', helper: 'Complete Hospital Setup to build your reporting schedule.', generate: false};
        }
        if (!scout.ready) {
            return {target: 'scout-preview', label: 'Generate Scout Preview', helper: 'Generate Scout Preview to create your draft reporting schedule.', generate: !!state.scoutCanGenerate};
        }
        return {target: 'scout-preview', label: 'Open Scout Preview', helper: 'Review the Scout draft that feeds this reporting module.', generate: false};
    }

    function renderReportingSummary(setup, scout, reporting) {
        var missingDueDates = reporting.items.filter(function (item) {
            return !item.due;
        }).length;
        var pendingApprovals = reporting.items.filter(function (item) {
            return item.approval && !/none|not applicable|n\/a/i.test(item.approval);
        }).length;
        var cards = [
            ['chart-bar', 'Readiness', isDay0Pending(setup) ? 'Setup required' : reportingStatusLabel(setup, scout, reporting), reporting.items.length ? 'Scout returned detail' : 'Complete setup first', isDay0Pending(setup) ? 'neutral' : reportingStatusTone(setup, scout, reporting)],
            ['calendar-alt', 'Reports', reporting.items.length ? String(reporting.items.length) : '0', reporting.items.length ? 'Draft schedule items' : 'Not scheduled yet', reporting.items.length ? 'success' : 'neutral'],
            ['yes-alt', 'Approvals', reporting.items.length ? String(pendingApprovals) : '0', 'Requirements found', pendingApprovals ? 'warning' : 'neutral'],
            ['editor-help', 'Due dates', reporting.items.length ? String(missingDueDates) : '0', 'Missing detail', missingDueDates ? 'warning' : 'neutral']
        ];
        return '<div class="qn-reporting-summary-grid">' + cards.map(function (card) {
            return '<article class="qn-reporting-summary-card qn-reporting-summary-' + escapeHtml(card[4]) + '">' +
                '<span class="dashicons dashicons-' + escapeHtml(card[0]) + '"></span><div><span>' + escapeHtml(card[1]) + '</span><strong>' + escapeHtml(card[2]) + '</strong><small>' + escapeHtml(card[3]) + '</small></div>' +
                '</article>';
        }).join('') + '</div>';
    }

    function renderReportingPrimaryState(setup, scout, reporting, action) {
        if (isDay0Pending(setup)) {
            return renderReportingEmptyState('clipboard', 'Complete Hospital Setup to build your reporting schedule.', 'Reports will be scheduled after Scout reviews your Hospital Setup.', action);
        }
        if (!scout.ready) {
            return renderReportingEmptyState('lightbulb', 'Generate Scout Preview to create your draft reporting schedule.', 'Scout will use your reporting obligations, committee timing, owners, approvals, and lead times to draft the schedule.', action);
        }
        if (!reporting.items.length) {
            return renderReportingEmptyState('chart-bar', 'Scout has not returned reporting schedule details yet.', 'Reporting schedule will appear here once Scout returns reporting workflow details.', action);
        }
        return '<section class="qn-reporting-preview">' +
            '<div class="qn-panel-header"><div><p class="qn-eyebrow">Reporting preview</p><h3>Draft reporting schedule</h3><p>' + escapeHtml(reporting.summary || 'Scout returned structured reporting details for review.') + '</p></div><span class="qn-status-pill qn-status-success">' + reporting.items.length + ' reports</span></div>' +
            '<div class="qn-reporting-table-wrap"><table class="qn-reporting-table"><thead><tr><th>Report</th><th>Frequency</th><th>Due date</th><th>Owner</th><th>Backup</th><th>Approval</th><th>Status</th></tr></thead><tbody>' +
            reporting.items.map(renderReportingPreviewRow).join('') +
            '</tbody></table></div>' +
            '<div class="qn-reporting-card-list">' + reporting.items.map(renderReportingCard).join('') + '</div>' +
            '</section>';
    }

    function renderReportingEmptyState(icon, title, message, action) {
        return '<section class="qn-reporting-empty qn-reporting-action-strip">' +
            '<span class="dashicons dashicons-' + escapeHtml(icon) + '"></span>' +
            '<div><h3>' + escapeHtml(title) + '</h3><p>' + escapeHtml(message) + '</p>' +
            (action && action.label && !action.readOnly ? '<button class="qn-button qn-button-primary" type="button" ' + (action.generate ? 'data-reporting-generate-scout' : 'data-section-target="' + escapeHtml(action.target) + '"') + '>' + escapeHtml(action.label) + '</button>' : '') + '</div>' +
            '</section>';
    }

    function renderReportingPreviewRow(item) {
        return '<tr>' +
            '<td><strong>' + escapeHtml(item.name || 'Untitled report') + '</strong></td>' +
            '<td>' + escapeHtml(item.frequency || 'Not set') + '</td>' +
            '<td>' + escapeHtml(item.due || 'Missing') + '</td>' +
            '<td>' + escapeHtml(item.owner || 'Not assigned') + '</td>' +
            '<td>' + escapeHtml(item.backup || 'Not assigned') + '</td>' +
            '<td>' + escapeHtml(item.approval || 'Not set') + '</td>' +
            '<td><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(item.tone || 'neutral') + '">' + escapeHtml(item.status || 'Draft') + '</span></td>' +
            '</tr>';
    }

    function renderReportingCard(item, index) {
        return '<article class="qn-reporting-row-card">' +
            '<div><h4>' + escapeHtml(item.name || 'Report ' + (index + 1)) + '</h4><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(item.tone || 'neutral') + '">' + escapeHtml(item.status || 'Draft') + '</span></div>' +
            '<dl>' +
            reportingDatum('Frequency', item.frequency || 'Not set') +
            reportingDatum('Due date', item.due || 'Missing') +
            reportingDatum('Owner', item.owner || 'Not assigned') +
            reportingDatum('Backup', item.backup || 'Not assigned') +
            reportingDatum('Approval', item.approval || 'Not set') +
            '</dl></article>';
    }

    function reportingDatum(label, value) {
        return '<div><dt>' + escapeHtml(label) + '</dt><dd>' + escapeHtml(value) + '</dd></div>';
    }

    function renderReportingCapabilityCards(setup, scout, reporting) {
        if (isReadOnlyWorkspaceRole()) {
            return '';
        }
        var ready = scout.ready && reporting.items.length;
        var cards = [
            ['media-spreadsheet', 'Master Reporting Schedule', 'Recurring federal, state, accreditation, payer, and internal reports.'],
            ['calendar-alt', 'Due Date Reminders', 'Lead times, buffers, known dates, and event-triggered deadlines.'],
            ['admin-users', 'Owner & Backup Tracking', 'Primary preparers, backup coverage, and visibility needs.'],
            ['groups', 'Board / Committee Prep', 'Preparation timing before committee and board meetings.']
        ];
        return '<section class="qn-reporting-capabilities qn-reporting-planned">' +
            '<div class="qn-reporting-planned-head"><p class="qn-eyebrow">Planned after Scout</p><h3>Reporting workflow will include</h3></div>' +
            '<div class="qn-reporting-planned-list">' + cards.map(function (card) {
                return '<article><span class="dashicons dashicons-' + escapeHtml(card[0]) + '"></span><div><h4>' + escapeHtml(card[1]) + '</h4><p>' + escapeHtml(card[2]) + '</p></div></article>';
            }).join('') + '</div></section>';
    }

    function renderReportingCtaPanel(action) {
        if (action && action.readOnly) {
            return '';
        }
        return '<section class="qn-reporting-cta-panel">' +
            '<div><p class="qn-eyebrow">Next step</p><h3>' + escapeHtml(action.helper) + '</h3><p>QualiNav will keep this page focused on reporting workflow setup, schedule readiness, and review actions for Hospital Quality Directors.</p></div>' +
            '<button class="qn-button qn-button-primary" type="button" ' + (action.generate ? 'data-reporting-generate-scout' : 'data-section-target="' + escapeHtml(action.target) + '"') + '>' + escapeHtml(action.label) + '</button>' +
            '</section>';
    }

    function reportingPreviewData() {
        var run = state.latestScoutRun;
        var candidates = [
            {key: 'master_reporting_schedule', aliases: ['reporting_schedule', 'reporting_obligations', 'report_schedule']},
            {key: 'reporting_schedule', aliases: ['master_reporting_schedule', 'reporting_obligations', 'report_schedule']},
            {key: 'reporting_obligations', aliases: ['master_reporting_schedule', 'reporting_schedule', 'report_schedule']},
            {key: 'report_schedule', aliases: ['master_reporting_schedule', 'reporting_schedule', 'reporting_obligations']}
        ];
        var group = null;
        candidates.some(function (definition) {
            group = findScoutGroup(run, definition);
            return !!group;
        });
        if (!group) {
            group = reportingPreviewLooseGroup(run);
        }
        var items = reportingItemsFromGroup(group).map(normalizeReportingItem).filter(function (item) {
            return item.name || item.frequency || item.due || item.owner || item.backup || item.approval;
        });
        return {
            group: group,
            summary: group && (group.summary || group.description) ? describeScoutItem(group.summary || group.description) : '',
            items: items
        };
    }

    function reportingPreviewLooseGroup(run) {
        var preview = run && run.preview ? run.preview : {};
        var keys = ['master_reporting_schedule', 'reporting_schedule', 'reporting_obligations', 'report_schedule'];
        var found = null;
        keys.some(function (key) {
            if (preview[key]) {
                found = {key: key, items: Array.isArray(preview[key]) ? preview[key] : [preview[key]]};
                return true;
            }
            return false;
        });
        return found;
    }

    function reportingItemsFromGroup(group) {
        if (!group) {
            return [];
        }
        if (Array.isArray(group.items)) {
            return group.items;
        }
        if (Array.isArray(group.reports)) {
            return group.reports;
        }
        if (Array.isArray(group.schedule)) {
            return group.schedule;
        }
        if (group.item && typeof group.item === 'object') {
            return [group.item];
        }
        return [];
    }

    function normalizeReportingItem(item) {
        if (!item || typeof item !== 'object' || Array.isArray(item)) {
            return {name: describeScoutItem(item), frequency: '', due: '', owner: '', backup: '', approval: '', status: 'Draft', tone: 'neutral'};
        }
        var due = firstReportingValue(item, ['due_date', 'due_dates', 'due_date_rule', 'deadline', 'deadline_rule', 'due', 'date']);
        var status = firstReportingValue(item, ['status', 'readiness_status', 'schedule_status']) || (due ? 'Draft' : 'Missing due date');
        return {
            name: firstReportingValue(item, ['report_name', 'name', 'title', 'label', 'report']) || 'Untitled report',
            frequency: firstReportingValue(item, ['frequency', 'cadence', 'reporting_frequency']) || '',
            due: due || '',
            owner: firstReportingValue(item, ['owner', 'preparer', 'owner_preparer', 'responsible_party']) || '',
            backup: firstReportingValue(item, ['backup', 'backup_preparer', 'secondary_owner']) || '',
            approval: firstReportingValue(item, ['approval', 'approval_required', 'approver']) || '',
            status: status,
            tone: /missing|not set|needed|review/i.test(status) || !due ? 'warning' : 'success'
        };
    }

    function firstReportingValue(item, keys) {
        var value = '';
        keys.some(function (key) {
            if (item[key] !== null && item[key] !== undefined && item[key] !== '') {
                value = describeScoutItem(item[key]);
                return true;
            }
            return false;
        });
        return value;
    }

    function renderCommitteesPage() {
        var page = document.getElementById('committees');
        if (!page) {
            return;
        }
        page.classList.add('qn-committees-page');
        page.classList.toggle('qn-readonly-module', isReadOnlyWorkspaceRole());
        var hospital = dashboardHospital() || currentHospitalContext() || {};
        var setup = dashboardSetupStatus(hospital);
        var scout = dashboardScoutStatus();
        var committees = committeesPreviewData();
        var action = committeesPageAction(setup, scout);
        var hospitalName = hospital.organization_name || hospital.name || 'Hospital workspace';
        var scoutStatus = state.latestScoutRun ? scout.status : (state.scoutOnboardingSubmitted ? 'Not generated' : 'Hospital Setup pending');
        var chips = [
            chip(hospitalName),
            chip(hospital.hospital_type_label || 'Hospital type not set'),
            chip(hospital.service_model_label || 'Service model not set'),
            chip('Scout: ' + scoutStatus)
        ].join('');
        page.innerHTML =
            renderCommitteesAdminBanner(hospitalName) +
            '<div class="qn-reporting-header qn-committees-header">' +
            '<div><p class="qn-eyebrow">Committees</p><h2>Committees</h2><p>' + escapeHtml(isReadOnlyWorkspaceRole() ? 'Review committee cadence, meeting flow, and board-reporting signals.' : 'Manage meeting cadence, report flow, committee relationships, and board reporting.') + '</p><div class="qn-reporting-context qn-committees-context">' + chips + '</div></div>' +
            readOnlyReviewBadge() + '<span class="qn-status-pill qn-status-' + escapeHtml(committeesStatusTone(setup, scout, committees)) + '">' + escapeHtml(committeesStatusLabel(setup, scout, committees)) + '</span>' +
            '</div>' +
            renderCommitteesSummary(setup, scout, committees) +
            renderCommitteesPrimaryState(setup, scout, committees, action) +
            renderCommitteesCapabilityCards(setup, scout, committees) +
            renderCommitteesCtaPanel(action);
    }

    function renderCommitteesAdminBanner(hospitalName) {
        var previewOrganizationId = getUrlOrganizationId();
        if (!(isGlobalAdmin() && previewOrganizationId)) {
            return '';
        }
        return '<div class="qn-scout-admin-banner qn-committees-admin-banner">' +
            '<span class="dashicons dashicons-visibility"></span>' +
            '<strong>Admin Preview Mode</strong>' +
            '<span>viewing Committees for ' + escapeHtml(hospitalName || 'this hospital') + '.</span>' +
            '<a class="qn-button qn-button-small" href="' + escapeHtml((config.homeUrl || '/') + 'qualinav/admin') + '">Back to Admin Console</a>' +
            '</div>';
    }

    function committeesStatusTone(setup, scout, committees) {
        if (isDay0Pending(setup)) {
            return 'pending';
        }
        if (!scout.ready) {
            return 'warning';
        }
        return committees.items.length ? 'success' : 'neutral';
    }

    function committeesStatusLabel(setup, scout, committees) {
        if (isDay0Pending(setup)) {
            return 'Hospital Setup pending';
        }
        if (!scout.ready) {
            return 'Available after Scout Preview';
        }
        return committees.items.length ? 'Ready' : 'Pending Scout';
    }

    function committeesPageAction(setup, scout) {
        if (isReadOnlyWorkspaceRole()) {
            return readOnlyPageAction();
        }
        if (isDay0Pending(setup)) {
            return {target: 'day-0-setup', label: 'Continue Hospital Setup', helper: 'Complete Hospital Setup to build your committee flow map.', generate: false};
        }
        if (!scout.ready) {
            return {target: 'scout-preview', label: 'Generate Scout Preview', helper: 'Generate Scout Preview to create your draft committee and report flow map.', generate: !!state.scoutCanGenerate};
        }
        return {target: 'scout-preview', label: 'Open Scout Preview', helper: 'Review the Scout draft that feeds committee workflow planning.', generate: false};
    }

    function renderCommitteesSummary(setup, scout, committees) {
        var boardFacing = committees.items.filter(function (item) {
            return /board|governing/i.test([item.name, item.reportsTo, item.flow].join(' '));
        }).length;
        var dependencies = committees.items.filter(function (item) {
            return item.flow || item.sequence || item.reportsTo;
        }).length;
        var missing = committees.items.filter(function (item) {
            return !item.frequency || !item.reportsTo;
        }).length;
        var cards = [
            ['businessperson', 'Committees identified', committees.items.length ? String(committees.items.length) : '-', committees.items.length ? 'Draft meeting workflow' : 'Pending Scout', committees.items.length ? 'success' : 'neutral'],
            ['networking', 'Reporting dependencies', committees.items.length ? String(dependencies) : '-', 'Report flow relationships', dependencies ? 'success' : 'neutral'],
            ['chart-line', 'Board-facing meetings', committees.items.length ? String(boardFacing) : '-', 'Board or governing body links', boardFacing ? 'warning' : 'neutral'],
            ['editor-help', 'Missing meeting details', committees.items.length ? String(missing) : '-', 'Cadence or report-to gaps', missing ? 'warning' : 'neutral']
        ];
        return '<div class="qn-reporting-summary-grid qn-committees-summary-grid">' + cards.map(function (card) {
            return '<article class="qn-reporting-summary-card qn-committees-summary-card qn-reporting-summary-' + escapeHtml(card[4]) + '">' +
                '<span class="dashicons dashicons-' + escapeHtml(card[0]) + '"></span><div><span>' + escapeHtml(card[1]) + '</span><strong>' + escapeHtml(card[2]) + '</strong><small>' + escapeHtml(card[3]) + '</small></div>' +
                '</article>';
        }).join('') + '</div>';
    }

    function renderCommitteesPrimaryState(setup, scout, committees, action) {
        if (isDay0Pending(setup)) {
            return renderCommitteesEmptyState('clipboard', 'Complete Hospital Setup to build your committee flow map.', 'Scout will use your committee cadence to schedule report preparation before each meeting.', action);
        }
        if (!scout.ready) {
            return renderCommitteesEmptyState('lightbulb', 'Generate Scout Preview to create your draft committee and report flow map.', 'Scout will map meeting cadence, report routing, board-facing work, and preparation timing from Hospital Setup.', action);
        }
        if (!committees.items.length) {
            return renderCommitteesEmptyState('businessperson', 'Committee flow details will appear here once Scout returns meeting workflow details.', 'Scout has not returned committee flow details yet. You can still review the setup preview for related context.', action);
        }
        return '<section class="qn-reporting-preview qn-committees-preview">' +
            '<div class="qn-panel-header"><div><p class="qn-eyebrow">Committee preview</p><h3>Draft committee and report flow map</h3><p>' + escapeHtml(committees.summary || 'Scout returned committee workflow details for review.') + '</p></div><span class="qn-status-pill qn-status-success">' + committees.items.length + ' committees</span></div>' +
            '<div class="qn-reporting-table-wrap"><table class="qn-reporting-table qn-committees-table"><thead><tr><th>Committee</th><th>Frequency / timing</th><th>Reports to</th><th>Prep lead time</th><th>Status</th></tr></thead><tbody>' +
            committees.items.map(renderCommitteePreviewRow).join('') +
            '</tbody></table></div>' +
            '<div class="qn-reporting-card-list qn-committees-card-list">' + committees.items.map(renderCommitteeCard).join('') + '</div>' +
            renderCommitteeFlowDetails(committees) +
            '</section>';
    }

    function renderCommitteesEmptyState(icon, title, message, action) {
        return '<section class="qn-reporting-empty qn-committees-empty">' +
            '<span class="dashicons dashicons-' + escapeHtml(icon) + '"></span>' +
            '<div><h3>' + escapeHtml(title) + '</h3><p>' + escapeHtml(message) + '</p>' +
            (action && action.label && !action.readOnly ? '<button class="qn-button qn-button-primary" type="button" ' + (action.generate ? 'data-committees-generate-scout' : 'data-section-target="' + escapeHtml(action.target) + '"') + '>' + escapeHtml(action.label) + '</button>' : '') + '</div>' +
            '</section>';
    }

    function renderCommitteePreviewRow(item) {
        return '<tr>' +
            '<td><strong>' + escapeHtml(item.name || 'Committee') + '</strong></td>' +
            '<td>' + escapeHtml(item.frequency || 'Not set') + '</td>' +
            '<td>' + escapeHtml(item.reportsTo || 'Not set') + '</td>' +
            '<td>' + escapeHtml(item.leadTime || 'Not set') + '</td>' +
            '<td><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(item.tone || 'neutral') + '">' + escapeHtml(item.status || 'Draft') + '</span></td>' +
            '</tr>';
    }

    function renderCommitteeCard(item, index) {
        return '<article class="qn-reporting-row-card qn-committee-row-card">' +
            '<div><h4>' + escapeHtml(item.name || 'Committee ' + (index + 1)) + '</h4><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(item.tone || 'neutral') + '">' + escapeHtml(item.status || 'Draft') + '</span></div>' +
            '<dl>' +
            reportingDatum('Frequency / timing', item.frequency || 'Not set') +
            reportingDatum('Reports to', item.reportsTo || 'Not set') +
            reportingDatum('Prep lead time', item.leadTime || 'Not set') +
            '</dl></article>';
    }

    function renderCommitteeFlowDetails(committees) {
        var details = committees.items.filter(function (item) {
            return item.flow || item.sequence || item.reportsTo;
        });
        if (!details.length) {
            return '';
        }
        return '<div class="qn-committee-flow-grid">' + details.slice(0, 6).map(function (item) {
            return '<article><h4>' + escapeHtml(item.name || 'Committee flow') + '</h4>' +
                (item.flow ? '<p><strong>Information flow</strong><span>' + escapeHtml(item.flow) + '</span></p>' : '') +
                (item.sequence ? '<p><strong>Sequencing rule</strong><span>' + escapeHtml(item.sequence) + '</span></p>' : '') +
                (item.reportsTo ? '<p><strong>Reports to</strong><span>' + escapeHtml(item.reportsTo) + '</span></p>' : '') +
                '</article>';
        }).join('') + '</div>';
    }

    function renderCommitteesCapabilityCards(setup, scout, committees) {
        if (isReadOnlyWorkspaceRole()) {
            return '';
        }
        var ready = scout.ready && committees.items.length;
        var cards = [
            ['calendar-alt', 'Meeting Cadence', 'Standing committees, cadence, timing, and preparation rhythm.'],
            ['networking', 'Report Flow Map', 'How quality information moves from workgroups to leadership.'],
            ['media-text', 'Agenda & Minutes Tracking', 'Standing agenda items, minutes owners, and storage locations.'],
            ['chart-line', 'Board Quality Reporting', 'Board packet timing and quality reporting relationships.']
        ];
        return '<section class="qn-reporting-capabilities qn-committees-capabilities"><div class="qn-section-toolbar"><div><p class="qn-eyebrow">Capabilities</p><h3>What this module will support</h3></div></div><div class="qn-future-capability-grid">' + cards.map(function (card) {
            var status = ready ? ['Ready', 'success'] : [scout.ready ? 'Pending meeting detail' : 'Pending Scout', scout.ready ? 'neutral' : 'warning'];
            return '<article class="qn-future-capability-card qn-reporting-capability-card qn-committees-capability-card"><span class="dashicons dashicons-' + escapeHtml(card[0]) + '"></span><h3>' + escapeHtml(card[1]) + '</h3><p>' + escapeHtml(card[2]) + '</p><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(status[1]) + '">' + escapeHtml(status[0]) + '</span></article>';
        }).join('') + '</div></section>';
    }

    function renderCommitteesCtaPanel(action) {
        if (action && action.readOnly) {
            return '';
        }
        return '<section class="qn-reporting-cta-panel qn-committees-cta-panel">' +
            '<div><p class="qn-eyebrow">Next step</p><h3>' + escapeHtml(action.helper) + '</h3><p>Scout will use committee cadence and report relationships to help Hospital Quality Directors prepare work before each meeting.</p></div>' +
            '<button class="qn-button qn-button-primary" type="button" ' + (action.generate ? 'data-committees-generate-scout' : 'data-section-target="' + escapeHtml(action.target) + '"') + '>' + escapeHtml(action.label) + '</button>' +
            '</section>';
    }

    function committeesPreviewData() {
        var run = state.latestScoutRun;
        var candidates = [
            {key: 'meeting_report_flow_map', aliases: ['committee_flow_map', 'committees', 'meeting_flow_map', 'report_flow_map']},
            {key: 'committee_flow_map', aliases: ['meeting_report_flow_map', 'committees', 'meeting_flow_map', 'report_flow_map']},
            {key: 'committees', aliases: ['meeting_report_flow_map', 'committee_flow_map', 'meeting_flow_map', 'report_flow_map']},
            {key: 'meeting_flow_map', aliases: ['meeting_report_flow_map', 'committee_flow_map', 'committees', 'report_flow_map']},
            {key: 'report_flow_map', aliases: ['meeting_report_flow_map', 'committee_flow_map', 'committees', 'meeting_flow_map']}
        ];
        var group = null;
        candidates.some(function (definition) {
            group = findScoutGroup(run, definition);
            return !!group;
        });
        if (!group) {
            group = committeesPreviewLooseGroup(run);
        }
        var items = committeeItemsFromGroup(group).map(normalizeCommitteeItem).filter(function (item) {
            return item.name || item.frequency || item.reportsTo || item.flow || item.sequence;
        });
        return {
            group: group,
            summary: group && (group.summary || group.description) ? describeScoutItem(group.summary || group.description) : '',
            items: items
        };
    }

    function committeesPreviewLooseGroup(run) {
        var preview = run && run.preview ? run.preview : {};
        var keys = ['meeting_report_flow_map', 'committee_flow_map', 'committees', 'meeting_flow_map', 'report_flow_map'];
        var found = null;
        keys.some(function (key) {
            if (preview[key]) {
                found = {key: key, items: Array.isArray(preview[key]) ? preview[key] : [preview[key]]};
                return true;
            }
            return false;
        });
        return found;
    }

    function committeeItemsFromGroup(group) {
        if (!group) {
            return [];
        }
        if (Array.isArray(group.items)) {
            return group.items;
        }
        if (Array.isArray(group.committees)) {
            return group.committees;
        }
        if (Array.isArray(group.meetings)) {
            return group.meetings;
        }
        if (Array.isArray(group.flow)) {
            return group.flow;
        }
        if (Array.isArray(group.dependencies)) {
            return group.dependencies;
        }
        return [];
    }

    function normalizeCommitteeItem(item) {
        if (!item || typeof item !== 'object' || Array.isArray(item)) {
            return {name: describeScoutItem(item), frequency: '', reportsTo: '', leadTime: '', flow: '', sequence: '', status: 'Draft', tone: 'neutral'};
        }
        var status = firstCommitteeValue(item, ['status', 'readiness_status', 'workflow_status']) || 'Draft';
        return {
            name: firstCommitteeValue(item, ['committee_name', 'meeting_name', 'name', 'title', 'committee', 'meeting']) || 'Committee',
            frequency: firstCommitteeValue(item, ['frequency_timing', 'frequency', 'cadence', 'timing', 'meeting_cadence']) || '',
            reportsTo: firstCommitteeValue(item, ['reports_to', 'reporting_to', 'parent_committee', 'board_reporting', 'destination']) || '',
            leadTime: firstCommitteeValue(item, ['preparation_lead_time', 'prep_lead_time', 'lead_time', 'report_lead_time']) || '',
            flow: firstCommitteeValue(item, ['information_flow', 'flow', 'report_flow', 'dependency', 'dependencies']) || '',
            sequence: firstCommitteeValue(item, ['sequencing_rule', 'sequence', 'timing_rule', 'rule']) || '',
            status: status,
            tone: /missing|not set|needed|review/i.test(status) ? 'warning' : 'success'
        };
    }

    function firstCommitteeValue(item, keys) {
        var value = '';
        keys.some(function (key) {
            if (item[key] !== null && item[key] !== undefined && item[key] !== '') {
                value = describeScoutItem(item[key]);
                return true;
            }
            return false;
        });
        return value;
    }

    function renderPlansPoliciesPage() {
        var page = document.getElementById('plans');
        if (!page) {
            return;
        }
        page.classList.add('qn-plans-page');
        page.classList.toggle('qn-readonly-module', isReadOnlyWorkspaceRole());
        var hospital = dashboardHospital() || currentHospitalContext() || {};
        var setup = dashboardSetupStatus(hospital);
        var scout = dashboardScoutStatus();
        var plans = plansPoliciesPreviewData();
        var action = plansPoliciesPageAction(setup, scout);
        var hospitalName = hospital.organization_name || hospital.name || 'Hospital workspace';
        var scoutStatus = state.latestScoutRun ? scout.status : (state.scoutOnboardingSubmitted ? 'Not generated' : 'Hospital Setup pending');
        var chips = [
            chip(hospitalName),
            chip(hospital.hospital_type_label || 'Hospital type not set'),
            chip(hospital.service_model_label || 'Service model not set'),
            chip('Scout: ' + scoutStatus)
        ].join('');
        page.innerHTML =
            renderPlansPoliciesAdminBanner(hospitalName) +
            '<div class="qn-reporting-header qn-plans-header">' +
            '<div><p class="qn-eyebrow">Plans & Policies</p><h2>Plans & Policies</h2><p>' + escapeHtml(isReadOnlyWorkspaceRole() ? 'Review plan and policy readiness, template needs, and approval signals.' : 'Track required plans, policy review cycles, templates, owners, and approval status.') + '</p><div class="qn-reporting-context qn-plans-context">' + chips + '</div></div>' +
            readOnlyReviewBadge() + '<span class="qn-status-pill qn-status-' + escapeHtml(plansPoliciesStatusTone(setup, scout, plans)) + '">' + escapeHtml(plansPoliciesStatusLabel(setup, scout, plans)) + '</span>' +
            '</div>' +
            renderPlansPoliciesSummary(setup, scout, plans) +
            renderPlansPoliciesPrimaryState(setup, scout, plans, action) +
            renderPlansPoliciesCapabilityCards(setup, scout, plans) +
            renderPlansPoliciesCtaPanel(action);
    }

    function renderPlansPoliciesAdminBanner(hospitalName) {
        var previewOrganizationId = getUrlOrganizationId();
        if (!(isGlobalAdmin() && previewOrganizationId)) {
            return '';
        }
        return '<div class="qn-scout-admin-banner qn-plans-admin-banner">' +
            '<span class="dashicons dashicons-visibility"></span>' +
            '<strong>Admin Preview Mode</strong>' +
            '<span>viewing Plans & Policies for ' + escapeHtml(hospitalName || 'this hospital') + '.</span>' +
            '<a class="qn-button qn-button-small" href="' + escapeHtml((config.homeUrl || '/') + 'qualinav/admin') + '">Back to Admin Console</a>' +
            '</div>';
    }

    function plansPoliciesStatusTone(setup, scout, plans) {
        if (isDay0Pending(setup)) {
            return 'pending';
        }
        if (!scout.ready) {
            return 'warning';
        }
        return plans.items.length || plans.priorityItems.length ? 'success' : 'neutral';
    }

    function plansPoliciesStatusLabel(setup, scout, plans) {
        if (isDay0Pending(setup)) {
            return 'Hospital Setup pending';
        }
        if (!scout.ready) {
            return 'Available after Scout Preview';
        }
        return plans.items.length || plans.priorityItems.length ? 'Ready' : 'Pending Scout';
    }

    function plansPoliciesPageAction(setup, scout) {
        if (isReadOnlyWorkspaceRole()) {
            return readOnlyPageAction();
        }
        if (isDay0Pending(setup)) {
            return {target: 'day-0-setup', label: 'Continue Hospital Setup', helper: 'Complete Hospital Setup to identify required plans and policy review needs.', generate: false};
        }
        if (!scout.ready) {
            return {target: 'scout-preview', label: 'Generate Scout Preview', helper: 'Generate Scout Preview to create your draft plan and policy review queue.', generate: !!state.scoutCanGenerate};
        }
        return {target: 'scout-preview', label: 'Open Scout Preview', helper: 'Review the Scout draft that feeds plan and policy workflow planning.', generate: false};
    }

    function renderPlansPoliciesSummary(setup, scout, plans) {
        var review = plans.items.filter(function (item) {
            return /review|update|overdue|route|create|verify/i.test([item.action, item.status, item.priority].join(' '));
        }).length;
        var templates = plans.items.filter(function (item) {
            return /template/i.test([item.name, item.type, item.action, item.priority].join(' '));
        }).length + plans.templateItems.length;
        var approvals = plans.items.filter(function (item) {
            return /board|approval|approve|route/i.test([item.boardApproval, item.action, item.status].join(' '));
        }).length;
        var cards = [
            ['media-document', 'Required Plans', plans.items.length ? String(plans.items.length) : '-', plans.items.length ? 'Draft plan and policy items' : 'Pending Scout', plans.items.length ? 'success' : 'neutral'],
            ['update', 'Plans Needing Review', plans.items.length ? String(review) : '-', 'Review or update actions', review ? 'warning' : 'neutral'],
            ['clipboard', 'Templates Needed', plans.items.length || plans.templateItems.length ? String(templates) : '-', 'Template support signals', templates ? 'warning' : 'neutral'],
            ['yes-alt', 'Approval Items', plans.items.length ? String(approvals) : '-', 'Board or approval routing', approvals ? 'warning' : 'neutral']
        ];
        return '<div class="qn-reporting-summary-grid qn-plans-summary-grid">' + cards.map(function (card) {
            return '<article class="qn-reporting-summary-card qn-plans-summary-card qn-reporting-summary-' + escapeHtml(card[4]) + '">' +
                '<span class="dashicons dashicons-' + escapeHtml(card[0]) + '"></span><div><span>' + escapeHtml(card[1]) + '</span><strong>' + escapeHtml(card[2]) + '</strong><small>' + escapeHtml(card[3]) + '</small></div>' +
                '</article>';
        }).join('') + '</div>';
    }

    function renderPlansPoliciesPrimaryState(setup, scout, plans, action) {
        if (isDay0Pending(setup)) {
            return renderPlansPoliciesEmptyState('clipboard', 'Complete Hospital Setup to identify required plans and policy review needs.', 'Scout will use your setup answers to flag missing plans, overdue reviews, and template needs.', action);
        }
        if (!scout.ready) {
            return renderPlansPoliciesEmptyState('lightbulb', 'Generate Scout Preview to create your draft plan and policy review queue.', 'Scout will identify required plans, review cadence, template needs, owners, approvals, and policy priorities.', action);
        }
        if (!plans.items.length && !plans.priorityItems.length) {
            return renderPlansPoliciesEmptyState('media-document', 'Plan and policy details will appear here once Scout returns workflow details.', 'Scout has not returned plan or policy workflow details yet. You can still review the setup preview for related context.', action);
        }
        var tableMarkup = '';
        if (plans.items.length) {
            tableMarkup = isReadOnlyWorkspaceRole() ?
                '<div class="qn-reporting-table-wrap"><table class="qn-reporting-table qn-plans-table qn-review-table"><thead><tr><th>Plan / policy</th><th>Status</th><th>Owner</th><th>Priority</th></tr></thead><tbody>' + plans.items.map(renderPlansPoliciesReviewRow).join('') + '</tbody></table></div><div class="qn-reporting-card-list qn-plans-card-list">' + plans.items.map(renderPlansPoliciesCard).join('') + '</div>' :
                '<div class="qn-reporting-table-wrap"><table class="qn-reporting-table qn-plans-table"><thead><tr><th>Plan / policy</th><th>Current status</th><th>Owner</th><th>Last approved</th><th>Board approval</th><th>Action needed</th><th>Priority</th></tr></thead><tbody>' + plans.items.map(renderPlansPoliciesRow).join('') + '</tbody></table></div><div class="qn-reporting-card-list qn-plans-card-list">' + plans.items.map(renderPlansPoliciesCard).join('') + '</div>';
        }
        return '<section class="qn-reporting-preview qn-plans-preview">' +
            '<div class="qn-panel-header"><div><p class="qn-eyebrow">Plan and policy preview</p><h3>Draft review queue</h3><p>' + escapeHtml(plans.summary || 'Scout returned plan and policy workflow details for review.') + '</p></div><span class="qn-status-pill qn-status-success">' + plans.items.length + ' items</span></div>' +
            tableMarkup +
            renderPlansPolicyCyclePreview(plans) +
            renderPlansPriorityItems(plans.priorityItems) +
            '</section>';
    }

    function renderPlansPoliciesEmptyState(icon, title, message, action) {
        return '<section class="qn-reporting-empty qn-plans-empty">' +
            '<span class="dashicons dashicons-' + escapeHtml(icon) + '"></span>' +
            '<div><h3>' + escapeHtml(title) + '</h3><p>' + escapeHtml(message) + '</p>' +
            (action && action.label && !action.readOnly ? '<button class="qn-button qn-button-primary" type="button" ' + (action.generate ? 'data-plans-generate-scout' : 'data-section-target="' + escapeHtml(action.target) + '"') + '>' + escapeHtml(action.label) + '</button>' : '') + '</div>' +
            '</section>';
    }

    function renderPlansPoliciesRow(item) {
        return '<tr>' +
            '<td><strong>' + escapeHtml(item.name || 'Plan or policy') + '</strong></td>' +
            '<td>' + escapeHtml(item.status || 'Not set') + '</td>' +
            '<td>' + escapeHtml(item.owner || 'Not assigned') + '</td>' +
            '<td>' + escapeHtml(item.lastApproved || 'Not set') + '</td>' +
            '<td>' + escapeHtml(item.boardApproval || 'Not set') + '</td>' +
            '<td>' + escapeHtml(item.action || 'Not set') + '</td>' +
            '<td><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(item.tone || 'neutral') + '">' + escapeHtml(item.priority || 'Draft') + '</span></td>' +
            '</tr>';
    }

    function renderPlansPoliciesReviewRow(item) {
        return '<tr>' +
            '<td><strong>' + escapeHtml(item.name || 'Plan or policy') + '</strong></td>' +
            '<td>' + escapeHtml(item.status || 'Draft') + '</td>' +
            '<td>' + escapeHtml(item.owner || 'Not assigned') + '</td>' +
            '<td><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(item.tone || 'neutral') + '">' + escapeHtml(item.priority || 'Review') + '</span></td>' +
            '</tr>';
    }

    function renderPlansPoliciesCard(item, index) {
        return '<article class="qn-reporting-row-card qn-plan-row-card">' +
            '<div><h4>' + escapeHtml(item.name || 'Plan or policy ' + (index + 1)) + '</h4><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(item.tone || 'neutral') + '">' + escapeHtml(item.priority || 'Draft') + '</span></div>' +
            '<dl>' +
            reportingDatum('Current status', item.status || 'Not set') +
            reportingDatum('Owner', item.owner || 'Not assigned') +
            reportingDatum('Last approved', item.lastApproved || 'Not set') +
            reportingDatum('Board approval', item.boardApproval || 'Not set') +
            reportingDatum('Action needed', item.action || 'Not set') +
            '</dl></article>';
    }

    function renderPlansPolicyCyclePreview(plans) {
        if (!plans.cycleItems.length && !plans.templateItems.length) {
            return '';
        }
        return '<div class="qn-plans-secondary-grid">' +
            renderPlansSecondaryCard('Policy review cycle', plans.cycleItems, 'update') +
            renderPlansSecondaryCard('Template needs', plans.templateItems, 'clipboard') +
            '</div>';
    }

    function renderPlansSecondaryCard(title, items, icon) {
        return '<article><h4><span class="dashicons dashicons-' + escapeHtml(icon) + '"></span>' + escapeHtml(title) + '</h4>' +
            (items.length ? '<ul>' + items.slice(0, 8).map(function (item) {
                return '<li>' + escapeHtml(title === 'Template needs' ? describePlanPolicyTemplateItem(item) : describeScoutItem(item)) + '</li>';
            }).join('') + '</ul>' : '<p class="qn-muted-note">No details returned yet.</p>') +
            '</article>';
    }

    function renderPlansPriorityItems(items) {
        if (!items.length) {
            return '';
        }
        return '<div class="qn-plans-priority-panel"><div><p class="qn-eyebrow">Priority items</p><h3>Plan and policy items Scout flagged</h3></div><div class="qn-scout-chip-list">' +
            items.slice(0, 10).map(function (item) {
                return '<span class="qn-warning-chip qn-warning-chip-warning">' + escapeHtml(describeScoutItem(item)) + '</span>';
            }).join('') + '</div></div>';
    }

    function renderPlansPoliciesCapabilityCards(setup, scout, plans) {
        if (isReadOnlyWorkspaceRole()) {
            return '';
        }
        var ready = scout.ready && (plans.items.length || plans.priorityItems.length);
        var cards = [
            ['portfolio', 'Required Plan Review', 'QAPI, patient safety, infection prevention, emergency preparedness, and risk plans.'],
            ['update', 'Policy Review Cycle', 'Annual review cadence, owners, and overdue policy signals.'],
            ['clipboard', 'Template Support', 'Project, RCA, FMEA, board report, and survey readiness templates.'],
            ['yes-alt', 'Approval Routing', 'Board, executive, medical staff, and committee approval pathways.']
        ];
        return '<section class="qn-reporting-capabilities qn-plans-capabilities"><div class="qn-section-toolbar"><div><p class="qn-eyebrow">Capabilities</p><h3>What this module will support</h3></div></div><div class="qn-future-capability-grid">' + cards.map(function (card) {
            var status = ready ? ['Ready', 'success'] : [scout.ready ? 'Pending plan detail' : 'Pending Scout', scout.ready ? 'neutral' : 'warning'];
            return '<article class="qn-future-capability-card qn-reporting-capability-card qn-plans-capability-card"><span class="dashicons dashicons-' + escapeHtml(card[0]) + '"></span><h3>' + escapeHtml(card[1]) + '</h3><p>' + escapeHtml(card[2]) + '</p><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(status[1]) + '">' + escapeHtml(status[0]) + '</span></article>';
        }).join('') + '</div></section>';
    }

    function renderPlansPoliciesCtaPanel(action) {
        if (action && action.readOnly) {
            return '';
        }
        return '<section class="qn-reporting-cta-panel qn-plans-cta-panel">' +
            '<div><p class="qn-eyebrow">Next step</p><h3>' + escapeHtml(action.helper) + '</h3><p>Scout will use setup answers to help Hospital Quality Directors prioritize required plans, policy reviews, templates, and approval routing.</p></div>' +
            '<button class="qn-button qn-button-primary" type="button" ' + (action.generate ? 'data-plans-generate-scout' : 'data-section-target="' + escapeHtml(action.target) + '"') + '>' + escapeHtml(action.label) + '</button>' +
            '</section>';
    }

    function plansPoliciesPreviewData() {
        var run = state.latestScoutRun;
        var candidates = [
            {key: 'plan_policy_tasks', aliases: ['plans_policies', 'required_plan_review', 'policy_review_cycle', 'template_needs']},
            {key: 'plans_policies', aliases: ['plan_policy_tasks', 'required_plan_review', 'policy_review_cycle', 'template_needs']},
            {key: 'required_plan_review', aliases: ['plan_policy_tasks', 'plans_policies']},
            {key: 'policy_review_cycle', aliases: ['plan_policy_tasks', 'plans_policies']},
            {key: 'template_needs', aliases: ['plan_policy_tasks', 'plans_policies']}
        ];
        var group = null;
        candidates.some(function (definition) {
            group = findScoutGroup(run, definition);
            return !!group;
        });
        if (!group) {
            group = plansPoliciesLooseGroup(run);
        }
        var items = plansPolicyItemsFromGroup(group).map(normalizePlanPolicyItem).filter(function (item) {
            return item.name || item.status || item.owner || item.action || item.priority;
        });
        return {
            group: group,
            summary: group && (group.summary || group.description) ? describeScoutItem(group.summary || group.description) : '',
            items: items,
            cycleItems: plansPolicyLooseList(run, ['policy_review_cycle']),
            templateItems: plansPolicyTemplateItems(run),
            priorityItems: plansPolicyPriorityItems(run)
        };
    }

    function plansPoliciesLooseGroup(run) {
        var preview = run && run.preview ? run.preview : {};
        var keys = ['plan_policy_tasks', 'plans_policies', 'required_plan_review', 'policy_review_cycle', 'template_needs'];
        var found = null;
        keys.some(function (key) {
            if (preview[key]) {
                found = {key: key, items: Array.isArray(preview[key]) ? preview[key] : [preview[key]]};
                return true;
            }
            return false;
        });
        return found;
    }

    function plansPolicyItemsFromGroup(group) {
        if (!group) {
            return [];
        }
        if (Array.isArray(group.items)) {
            return group.items;
        }
        if (Array.isArray(group.plans)) {
            return group.plans;
        }
        if (Array.isArray(group.policies)) {
            return group.policies;
        }
        if (Array.isArray(group.tasks)) {
            return group.tasks;
        }
        return [];
    }

    function normalizePlanPolicyItem(item) {
        if (!item || typeof item !== 'object' || Array.isArray(item)) {
            return {name: describeScoutItem(item), status: '', owner: '', lastApproved: '', boardApproval: '', action: '', priority: 'Draft', tone: 'neutral'};
        }
        var priority = firstPlanPolicyValue(item, ['priority', 'priority_level', 'risk_level']) || firstPlanPolicyValue(item, ['status']) || 'Draft';
        return {
            name: firstPlanPolicyValue(item, ['plan_name', 'policy_name', 'name', 'title', 'plan', 'policy', 'task']) || 'Plan or policy',
            type: firstPlanPolicyValue(item, ['type', 'category']) || '',
            status: firstPlanPolicyValue(item, ['exists_current_status', 'current_status', 'status', 'exists']) || '',
            owner: firstPlanPolicyValue(item, ['owner', 'responsible_owner', 'plan_owner', 'policy_owner']) || '',
            lastApproved: firstPlanPolicyValue(item, ['last_approved', 'last_reviewed', 'last_approved_review_date', 'review_date']) || '',
            boardApproval: firstPlanPolicyValue(item, ['board_approval', 'board_approved', 'approval_required', 'approval']) || '',
            action: firstPlanPolicyValue(item, ['action_needed', 'next_action', 'action', 'recommended_action']) || '',
            priority: priority,
            tone: /high|missing|overdue|create|route|review|update/i.test(priority + ' ' + firstPlanPolicyValue(item, ['action_needed', 'action'])) ? 'warning' : 'success'
        };
    }

    function firstPlanPolicyValue(item, keys) {
        var value = '';
        keys.some(function (key) {
            if (item[key] !== null && item[key] !== undefined && item[key] !== '') {
                value = describeScoutItem(item[key]);
                return true;
            }
            return false;
        });
        return value;
    }

    function plansPolicyLooseList(run, keys) {
        var preview = run && run.preview ? run.preview : {};
        var values = [];
        keys.forEach(function (key) {
            if (Array.isArray(preview[key])) {
                values = values.concat(preview[key]);
            } else if (preview[key]) {
                values.push(preview[key]);
            }
        });
        return cleanScoutList(values);
    }

    function plansPolicyTemplateItems(run) {
        var values = plansPolicyLooseList(run, ['template_needs', 'templates_needed']);
        var workflowKeys = ['template_needs', 'templates_needed'];
        workflowKeys.forEach(function (key) {
            values = values.concat(plansPolicyWorkflowList(run, key));
        });
        ['plan_policy_tasks', 'plans_policies', 'required_plan_review', 'policy_review_cycle'].forEach(function (key) {
            values = values.concat(plansPolicyNestedValues(run, key, ['template_needs', 'templates_needed', 'templates']));
        });
        return cleanScoutList(values);
    }

    function plansPolicyWorkflowList(run, key) {
        var values = [];
        var response = run && run.response ? run.response : {};
        var workflows = response.workflows || {};
        [workflows[key], response[key]].forEach(function (value) {
            if (Array.isArray(value)) {
                values = values.concat(value);
            } else if (value) {
                values.push(value);
            }
        });
        var group = findScoutGroup(run, {key: key, aliases: []});
        if (group) {
            values = values.concat(plansPolicyItemsFromGroup(group));
        }
        return values;
    }

    function plansPolicyNestedValues(run, workflowKey, nestedKeys) {
        var response = run && run.response ? run.response : {};
        var workflows = response.workflows || {};
        var values = [];
        [workflows[workflowKey], response[workflowKey]].forEach(function (source) {
            if (!source || typeof source !== 'object' || Array.isArray(source)) {
                return;
            }
            nestedKeys.forEach(function (key) {
                if (Array.isArray(source[key])) {
                    values = values.concat(source[key]);
                } else if (source[key]) {
                    values.push(source[key]);
                }
            });
        });
        return values;
    }

    function describePlanPolicyTemplateItem(item) {
        if (!item || typeof item !== 'object' || Array.isArray(item)) {
            return describeScoutItem(item);
        }
        var name = firstPlanPolicyValue(item, ['template_name', 'name', 'title', 'template', 'item']) || describeScoutItem(item);
        var details = [
            firstPlanPolicyValue(item, ['status']),
            firstPlanPolicyValue(item, ['reason', 'why', 'why_it_matters']),
            firstPlanPolicyValue(item, ['owner', 'next_step', 'action_needed'])
        ].filter(Boolean);
        return details.length ? name + ' - ' + details.join(' | ') : name;
    }

    function plansPolicyPriorityItems(run) {
        var group = findScoutGroup(run, {key: 'priority_queue', aliases: []});
        var items = reportingItemsFromGroup(group).concat(plansPolicyLooseList(run, ['priority_queue']));
        return cleanScoutList(items).filter(function (item) {
            return isPlanPolicyPriorityItem(item);
        });
    }

    function isPlanPolicyPriorityItem(item) {
        var label = describeScoutItem(item);
        var search = label;
        if (item && typeof item === 'object' && !Array.isArray(item)) {
            search = [
                item.item,
                item.name,
                item.title,
                item.type,
                item.category,
                item.domain,
                item.source_section,
                item.related_field,
                item.related_area,
                item.related_workflow,
                item.plan_name,
                item.policy_name,
                item.template_name
            ].map(describeScoutItem).join(' ');
        }
        var strong = /plans?|polic(?:y|ies)|templates?|documents?|documentation|protocol|approval|board approval|qapi plan|patient safety plan|infection prevention(?: and control)? plan|emergency preparedness plan|risk management plan|utilization review plan|annual review|last approved|review cycle/i;
        var weakClinical = /monitoring|blood usage|morbidity|medication safety|sentinel|ancillary|aggregate data|uploads?|routine task|committee routing|review ownership|ownership/i;
        if (strong.test(search)) {
            return true;
        }
        if (weakClinical.test(search)) {
            return false;
        }
        return strong.test(label);
    }

    function renderClinicalMonitoringPage() {
        var page = document.getElementById('clinical');
        if (!page) {
            return;
        }
        page.classList.add('qn-clinical-page');
        page.classList.toggle('qn-readonly-module', isReadOnlyWorkspaceRole());
        var hospital = dashboardHospital() || currentHospitalContext() || {};
        var setup = dashboardSetupStatus(hospital);
        var scout = dashboardScoutStatus();
        var clinical = clinicalMonitoringPreviewData();
        var action = clinicalMonitoringPageAction(setup, scout);
        var hospitalName = hospital.organization_name || hospital.name || 'Hospital workspace';
        var scoutStatus = state.latestScoutRun ? scout.status : (state.scoutOnboardingSubmitted ? 'Not generated' : 'Hospital Setup pending');
        var chips = [
            chip(hospitalName),
            chip(hospital.hospital_type_label || 'Hospital type not set'),
            chip(hospital.service_model_label || 'Service model not set'),
            hospital.payment_model_label ? chip(hospital.payment_model_label) : '',
            chip('Scout: ' + scoutStatus)
        ].filter(Boolean).join('');
        page.innerHTML =
            renderClinicalMonitoringAdminBanner(hospitalName) +
            '<div class="qn-reporting-header qn-clinical-header">' +
            '<div><p class="qn-eyebrow">Clinical Monitoring</p><h2>Clinical Monitoring</h2><p>' + escapeHtml(isReadOnlyWorkspaceRole() ? 'Review monitoring readiness, aggregate upload signals, and priority follow-up areas.' : 'Track required monitoring areas, review cadence, committee routing, aggregate uploads, and priority gaps.') + '</p><div class="qn-reporting-context qn-clinical-context">' + chips + '</div></div>' +
            readOnlyReviewBadge() + '<span class="qn-status-pill qn-status-' + escapeHtml(clinicalMonitoringStatusTone(setup, scout, clinical)) + '">' + escapeHtml(clinicalMonitoringStatusLabel(setup, scout, clinical)) + '</span>' +
            '</div>' +
            renderClinicalMonitoringSummary(setup, scout, clinical) +
            renderClinicalMonitoringPrimaryState(setup, scout, clinical, action) +
            renderClinicalMonitoringCapabilityCards(setup, scout, clinical) +
            renderClinicalMonitoringCtaPanel(action);
    }

    function renderClinicalMonitoringAdminBanner(hospitalName) {
        var previewOrganizationId = getUrlOrganizationId();
        if (!(isGlobalAdmin() && previewOrganizationId)) {
            return '';
        }
        return '<div class="qn-scout-admin-banner qn-clinical-admin-banner">' +
            '<span class="dashicons dashicons-visibility"></span>' +
            '<strong>Admin Preview Mode</strong>' +
            '<span>viewing Clinical Monitoring for ' + escapeHtml(hospitalName || 'this hospital') + '.</span>' +
            '<a class="qn-button qn-button-small" href="' + escapeHtml((config.homeUrl || '/') + 'qualinav/admin') + '">Back to Admin Console</a>' +
            '</div>';
    }

    function clinicalMonitoringStatusTone(setup, scout, clinical) {
        if (isDay0Pending(setup)) {
            return 'pending';
        }
        if (!scout.ready) {
            return 'warning';
        }
        return clinical.hasData ? 'success' : 'neutral';
    }

    function clinicalMonitoringStatusLabel(setup, scout, clinical) {
        if (isDay0Pending(setup)) {
            return 'Hospital Setup pending';
        }
        if (!scout.ready) {
            return 'Available after Scout Preview';
        }
        return clinical.hasData ? 'Ready' : 'Pending Scout';
    }

    function clinicalMonitoringPageAction(setup, scout) {
        if (isReadOnlyWorkspaceRole()) {
            return readOnlyPageAction();
        }
        if (isDay0Pending(setup)) {
            return {target: 'day-0-setup', label: 'Continue Hospital Setup', helper: 'Complete Hospital Setup to identify required clinical monitoring areas.', generate: false};
        }
        if (!scout.ready) {
            return {target: 'scout-preview', label: 'Generate Scout Preview', helper: 'Generate Scout Preview to create your draft monitoring calendar.', generate: !!state.scoutCanGenerate};
        }
        return {target: 'scout-preview', label: 'Open Scout Preview', helper: 'Review the Scout draft that feeds this monitoring module.', generate: false};
    }

    function renderClinicalMonitoringSummary(setup, scout, clinical) {
        var cards = [
            ['heart', 'Monitoring areas', clinical.recurring.length ? String(clinical.recurring.length) : '-', clinical.recurring.length ? 'Draft recurring areas' : 'Pending Scout', clinical.recurring.length ? 'success' : 'neutral'],
            ['flag', 'Priority gaps', clinical.gaps.length ? String(clinical.gaps.length) : '-', clinical.gaps.length ? 'Needs review' : 'Pending Scout', clinical.gaps.length ? 'warning' : 'neutral'],
            ['upload', 'Aggregate uploads', clinical.uploads.length ? String(clinical.uploads.length) : '-', clinical.uploads.length ? 'Measure upload signals' : 'Pending Scout', clinical.uploads.length ? 'success' : 'neutral'],
            ['performance', 'Active projects', clinical.projects.length ? String(clinical.projects.length) : '-', clinical.projects.length ? 'QI projects linked' : 'Pending Scout', clinical.projects.length ? 'success' : 'neutral']
        ];
        if (isDay0Pending(setup)) {
            cards = [
                ['heart', 'Monitoring areas', '-', 'Hospital Setup pending', 'pending'],
                ['flag', 'Priority gaps', '-', 'Hospital Setup pending', 'pending'],
                ['upload', 'Aggregate uploads', '-', 'Hospital Setup pending', 'pending'],
                ['performance', 'Active projects', '-', 'Hospital Setup pending', 'pending']
            ];
        } else if (!scout.ready) {
            cards = cards.map(function (card) {
                card[2] = '-';
                card[3] = 'Pending Scout';
                card[4] = 'warning';
                return card;
            });
        }
        return '<div class="qn-reporting-summary-grid qn-clinical-summary-grid">' + cards.map(function (card) {
            return '<article class="qn-reporting-summary-card qn-clinical-summary-card qn-reporting-summary-' + escapeHtml(card[4]) + '">' +
                '<span class="dashicons dashicons-' + escapeHtml(card[0]) + '"></span><div><span>' + escapeHtml(card[1]) + '</span><strong>' + escapeHtml(card[2]) + '</strong><small>' + escapeHtml(card[3]) + '</small></div>' +
                '</article>';
        }).join('') + '</div>';
    }

    function renderClinicalMonitoringPrimaryState(setup, scout, clinical, action) {
        if (isDay0Pending(setup)) {
            return renderClinicalMonitoringEmptyState('clipboard', 'Complete Hospital Setup to identify required clinical monitoring areas.', 'Scout will use your services, monitoring gaps, plans, and quality measures to tailor the monitoring calendar.', action);
        }
        if (!scout.ready) {
            return renderClinicalMonitoringEmptyState('lightbulb', 'Generate Scout Preview to create your draft monitoring calendar.', 'Scout will translate setup answers into recurring monitoring, aggregate upload, and priority gap recommendations.', action);
        }
        if (!clinical.hasData) {
            return renderClinicalMonitoringEmptyState('heart', 'Clinical monitoring details will appear here once Scout returns monitoring workflow details.', 'Monitoring gaps flagged by Scout will appear here with priority and target timing.', action);
        }
        return '<section class="qn-reporting-preview qn-clinical-preview">' +
            '<div class="qn-panel-header"><div><p class="qn-eyebrow">Monitoring preview</p><h3>Draft clinical monitoring workspace</h3><p>' + escapeHtml(clinical.summary || 'Scout returned monitoring workflow details for review.') + '</p></div><span class="qn-status-pill qn-status-success">Preview ready</span></div>' +
            renderClinicalMonitoringRecurring(clinical.recurring) +
            renderClinicalMonitoringUploads(clinical.uploads) +
            renderClinicalMonitoringRhythm(clinical.rhythm) +
            renderClinicalMonitoringProjects(clinical.projects) +
            renderClinicalMonitoringGaps(clinical.gaps) +
            '</section>';
    }

    function renderClinicalMonitoringEmptyState(icon, title, message, action) {
        return '<section class="qn-reporting-empty qn-clinical-empty">' +
            '<span class="dashicons dashicons-' + escapeHtml(icon) + '"></span>' +
            '<div><h3>' + escapeHtml(title) + '</h3><p>' + escapeHtml(message) + '</p>' +
            (action && !action.readOnly && action.label ? '<button class="qn-button qn-button-primary" type="button" ' + (action.generate ? 'data-clinical-generate-scout' : 'data-section-target="' + escapeHtml(action.target) + '"') + '>' + escapeHtml(action.label) + '</button>' : '') + '</div>' +
            '</section>';
    }

    function renderClinicalMonitoringRecurring(items) {
        if (!items.length) {
            return '';
        }
        return '<section class="qn-clinical-section">' +
            '<div class="qn-panel-header"><div><p class="qn-eyebrow">Recurring Clinical Monitoring</p><h3>Monitoring areas Scout identified</h3><p>Scout will route each monitoring area to the committee that reviews it.</p></div><span class="qn-status-pill">' + items.length + ' areas</span></div>' +
            '<div class="qn-reporting-table-wrap"><table class="qn-reporting-table qn-clinical-table"><thead><tr><th>Monitoring area</th><th>Cadence</th><th>Reviewed by</th><th>Current state</th><th>Priority</th><th>Action</th></tr></thead><tbody>' + items.map(renderClinicalMonitoringRow).join('') + '</tbody></table></div>' +
            '<div class="qn-reporting-card-list qn-clinical-card-list">' + items.map(renderClinicalMonitoringCard).join('') + '</div>' +
            '</section>';
    }

    function renderClinicalMonitoringRow(item) {
        return '<tr>' +
            '<td><strong>' + escapeHtml(item.area || 'Monitoring area') + '</strong>' + (item.notes ? '<small>' + escapeHtml(item.notes) + '</small>' : '') + '</td>' +
            '<td>' + escapeHtml(item.cadence || 'Not set') + '</td>' +
            '<td>' + escapeHtml(item.reviewedBy || 'Not assigned') + '</td>' +
            '<td>' + escapeHtml(item.currentState || 'Not set') + '</td>' +
            '<td><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(item.tone || 'neutral') + '">' + escapeHtml(item.priority || 'Draft') + '</span></td>' +
            '<td><span class="qn-clinical-action-chip">' + escapeHtml(item.action || 'Review with Scout preview') + '</span></td>' +
            '</tr>';
    }

    function renderClinicalMonitoringCard(item, index) {
        return '<article class="qn-reporting-row-card qn-clinical-row-card">' +
            '<div><h4>' + escapeHtml(item.area || 'Monitoring area ' + (index + 1)) + '</h4><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(item.tone || 'neutral') + '">' + escapeHtml(item.priority || 'Draft') + '</span></div>' +
            '<dl>' +
            reportingDatum('Cadence', item.cadence || 'Not set') +
            reportingDatum('Reviewed by', item.reviewedBy || 'Not assigned') +
            reportingDatum('Current state', item.currentState || 'Not set') +
            reportingDatum('Action needed', item.action || 'Review with Scout preview') +
            (item.notes ? reportingDatum('Notes', item.notes) : '') +
            '</dl></article>';
    }

    function renderClinicalMonitoringUploads(items) {
        if (!items.length) {
            return '';
        }
        return '<section class="qn-clinical-section">' +
            '<div class="qn-panel-header"><div><p class="qn-eyebrow">Aggregate Data Uploads</p><h3>Measure upload signals</h3><p>Only aggregate, de-identified measure workflows should appear here.</p></div><span class="qn-status-pill">' + items.length + ' uploads</span></div>' +
            '<div class="qn-reporting-table-wrap"><table class="qn-reporting-table qn-clinical-table"><thead><tr><th>Measure / source</th><th>Cadence</th><th>Source system</th><th>Owner</th><th>Status</th></tr></thead><tbody>' + items.map(renderClinicalUploadRow).join('') + '</tbody></table></div>' +
            '<div class="qn-reporting-card-list qn-clinical-card-list">' + items.map(renderClinicalUploadCard).join('') + '</div>' +
            '</section>';
    }

    function renderClinicalUploadRow(item) {
        return '<tr>' +
            '<td><strong>' + escapeHtml(item.measure || 'Measure upload') + '</strong></td>' +
            '<td>' + escapeHtml(item.cadence || 'Not set') + '</td>' +
            '<td>' + escapeHtml(item.source || 'Not set') + '</td>' +
            '<td>' + escapeHtml(item.owner || 'Not assigned') + '</td>' +
            '<td><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(item.tone || 'neutral') + '">' + escapeHtml(item.status || 'Draft') + '</span></td>' +
            '</tr>';
    }

    function renderClinicalUploadCard(item, index) {
        return '<article class="qn-reporting-row-card qn-clinical-row-card">' +
            '<div><h4>' + escapeHtml(item.measure || 'Upload ' + (index + 1)) + '</h4><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(item.tone || 'neutral') + '">' + escapeHtml(item.status || 'Draft') + '</span></div>' +
            '<dl>' +
            reportingDatum('Cadence', item.cadence || 'Not set') +
            reportingDatum('Source system', item.source || 'Not set') +
            reportingDatum('Owner', item.owner || 'Not assigned') +
            '</dl></article>';
    }

    function renderClinicalMonitoringRhythm(items) {
        if (!items.length) {
            return '';
        }
        var grouped = {};
        items.forEach(function (item) {
            var cadence = item.cadence || 'Other';
            grouped[cadence] = grouped[cadence] || [];
            grouped[cadence].push(item);
        });
        var preferred = ['Weekly', 'Monthly', 'Quarterly', 'Annual'];
        var keys = preferred.filter(function (key) { return grouped[key]; }).concat(Object.keys(grouped).filter(function (key) { return preferred.indexOf(key) === -1; }));
        return '<section class="qn-clinical-section">' +
            '<div class="qn-panel-header"><div><p class="qn-eyebrow">Routine Task Rhythm</p><h3>Recurring work rhythm</h3><p>Scout groups routine tasks by cadence so review work is easier to plan.</p></div><span class="qn-status-pill">' + items.length + ' tasks</span></div>' +
            '<div class="qn-clinical-rhythm-grid">' + keys.map(function (key) {
                return '<article><h4>' + escapeHtml(key) + '</h4><ul>' + grouped[key].map(function (item) {
                    return '<li><strong>' + escapeHtml(item.task || 'Task') + '</strong><span>' + escapeHtml(item.owner || item.area || 'Owner not assigned') + '</span></li>';
                }).join('') + '</ul></article>';
            }).join('') + '</div>' +
            '</section>';
    }

    function renderClinicalMonitoringProjects(items) {
        if (!items.length) {
            return '';
        }
        return '<section class="qn-clinical-section">' +
            '<div class="qn-panel-header"><div><p class="qn-eyebrow">Active Improvement Projects</p><h3>Projects linked to monitoring</h3><p>Scout can connect project milestones to the measures they are intended to improve.</p></div><span class="qn-status-pill">' + items.length + ' projects</span></div>' +
            '<div class="qn-clinical-project-grid">' + items.map(function (item, index) {
                return '<article class="qn-reporting-row-card qn-clinical-project-card"><span class="dashicons dashicons-performance"></span><div class="qn-clinical-project-body"><div class="qn-clinical-project-title"><h4>' + escapeHtml(item.project || 'Project ' + (index + 1)) + '</h4><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(item.tone || 'neutral') + '">' + escapeHtml(item.status || 'Draft') + '</span></div><dl>' +
                    reportingDatum('Method', item.method || 'Not set') +
                    reportingDatum('Measure', item.measure || 'Not set') +
                    reportingDatum('Next step', item.nextStep || 'Review with Scout preview') +
                    '</dl></div></article>';
            }).join('') + '</div>' +
            '</section>';
    }

    function renderClinicalMonitoringGaps(items) {
        if (!items.length) {
            return '';
        }
        return '<section class="qn-clinical-section qn-clinical-gap-section">' +
            '<div class="qn-panel-header"><div><p class="qn-eyebrow">Priority Monitoring Gaps</p><h3>Monitoring gaps Scout flagged</h3><p>Monitoring gaps flagged by Scout will appear here with priority and target timing.</p></div><span class="qn-status-pill">' + items.length + ' gaps</span></div>' +
            '<div class="qn-clinical-gap-grid">' + items.map(function (item, index) {
                return '<article><span class="dashicons dashicons-warning"></span><div class="qn-clinical-gap-body"><div class="qn-clinical-gap-title"><h4>' + escapeHtml(item.item || 'Monitoring gap ' + (index + 1)) + '</h4><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(item.tone || 'warning') + '">' + escapeHtml(item.priority || 'Priority') + '</span></div>' +
                    '<div class="qn-clinical-gap-meta">' +
                    (item.why ? '<p><strong>Why it matters</strong><span>' + escapeHtml(item.why) + '</span></p>' : '') +
                    (item.target ? '<p><strong>Target</strong><span>' + escapeHtml(item.target) + '</span></p>' : '') +
                    (item.area ? '<p><strong>Related area</strong><span>' + escapeHtml(item.area) + '</span></p>' : '') +
                    '</div></div>' +
                    '</article>';
            }).join('') + '</div>' +
            '</section>';
    }

    function renderClinicalMonitoringCapabilityCards(setup, scout, clinical) {
        if (isReadOnlyWorkspaceRole()) {
            return '';
        }
        var ready = scout.ready && clinical.hasData;
        var cards = [
            ['heart', 'Recurring Monitoring', 'Applicable monitoring areas, review cadence, and committee routing.'],
            ['upload', 'Aggregate Data Uploads', 'MBQIP, NHSN, patient experience, and dashboard measure upload rhythms.'],
            ['clock', 'Routine Task Rhythm', 'Weekly, monthly, quarterly, and annual monitoring work.'],
            ['flag', 'Priority Gap Tracking', 'Monitoring gaps, targets, and improvement follow-up.']
        ];
        return '<section class="qn-reporting-capabilities qn-clinical-capabilities"><div class="qn-section-toolbar"><div><p class="qn-eyebrow">Capabilities</p><h3>What this module will support</h3></div></div><div class="qn-future-capability-grid">' + cards.map(function (card) {
            var status = ready ? ['Ready', 'success'] : [scout.ready ? 'Pending monitoring detail' : 'Pending Scout', scout.ready ? 'neutral' : 'warning'];
            return '<article class="qn-future-capability-card qn-reporting-capability-card qn-clinical-capability-card"><span class="dashicons dashicons-' + escapeHtml(card[0]) + '"></span><h3>' + escapeHtml(card[1]) + '</h3><p>' + escapeHtml(card[2]) + '</p><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(status[1]) + '">' + escapeHtml(status[0]) + '</span></article>';
        }).join('') + '</div></section>';
    }

    function renderClinicalMonitoringCtaPanel(action) {
        if (action && action.readOnly) {
            return '';
        }
        return '<section class="qn-reporting-cta-panel qn-clinical-cta-panel">' +
            '<div><p class="qn-eyebrow">Next step</p><h3>' + escapeHtml(action.helper) + '</h3><p>QualiNav will keep this page focused on monitoring cadence, aggregate data movement, committee review, and priority follow-up for Hospital Quality Directors.</p></div>' +
            '<button class="qn-button qn-button-primary" type="button" ' + (action.generate ? 'data-clinical-generate-scout' : 'data-section-target="' + escapeHtml(action.target) + '"') + '>' + escapeHtml(action.label) + '</button>' +
            '</section>';
    }

    function clinicalMonitoringPreviewData() {
        var run = state.latestScoutRun;
        var recurring = clinicalItemsForKeys(run, ['recurring_clinical_monitoring', 'clinical_monitoring_tasks', 'active_monitoring_improvement_tasks', 'monitoring_tasks', 'required_monitoring_areas']).map(normalizeClinicalMonitoringItem).filter(function (item) {
            return item.area || item.cadence || item.reviewedBy || item.currentState || item.priority || item.action;
        });
        var uploads = clinicalItemsForKeys(run, ['aggregate_data_uploads', 'aggregate_measure_uploads', 'measure_uploads', 'data_uploads']).map(normalizeClinicalUploadItem).filter(function (item) {
            return item.measure || item.cadence || item.source || item.owner || item.status;
        });
        var rhythm = clinicalItemsForKeys(run, ['routine_task_rhythm', 'task_rhythm', 'recurring_tasks']).map(normalizeClinicalRhythmItem).filter(function (item) {
            return item.task || item.cadence || item.owner || item.area;
        });
        var projects = clinicalItemsForKeys(run, ['active_improvement_projects', 'qi_project_milestones', 'improvement_projects']).map(normalizeClinicalProjectItem).filter(function (item) {
            return item.project || item.method || item.measure || item.status || item.nextStep;
        });
        var gaps = clinicalPriorityGapItems(run);
        var firstGroup = clinicalGroupForKeys(run, ['recurring_clinical_monitoring', 'clinical_monitoring_tasks', 'active_monitoring_improvement_tasks']);
        return {
            summary: firstGroup && (firstGroup.summary || firstGroup.description) ? describeScoutItem(firstGroup.summary || firstGroup.description) : '',
            recurring: recurring,
            uploads: uploads,
            rhythm: rhythm,
            projects: projects,
            gaps: gaps,
            hasData: !!(recurring.length || uploads.length || rhythm.length || projects.length || gaps.length)
        };
    }

    function clinicalGroupForKeys(run, keys) {
        var group = null;
        keys.some(function (key) {
            group = findScoutGroup(run, {key: key, aliases: keys.filter(function (alias) { return alias !== key; })});
            return !!group;
        });
        if (group) {
            return group;
        }
        var preview = run && run.preview ? run.preview : {};
        keys.some(function (key) {
            if (preview[key]) {
                if (preview[key] && typeof preview[key] === 'object' && !Array.isArray(preview[key])) {
                    group = Object.assign({key: key}, preview[key]);
                } else {
                    group = {key: key, items: Array.isArray(preview[key]) ? preview[key] : [preview[key]]};
                }
                return true;
            }
            return false;
        });
        return group;
    }

    function clinicalItemsForKeys(run, keys) {
        var group = clinicalGroupForKeys(run, keys);
        return clinicalItemsFromGroup(group);
    }

    function clinicalItemsFromGroup(group) {
        if (!group) {
            return [];
        }
        if (Array.isArray(group.items)) {
            return group.items;
        }
        if (Array.isArray(group.tasks)) {
            return group.tasks;
        }
        if (Array.isArray(group.monitoring_areas)) {
            return group.monitoring_areas;
        }
        if (Array.isArray(group.required_monitoring_areas)) {
            return group.required_monitoring_areas;
        }
        if (Array.isArray(group.areas)) {
            return group.areas;
        }
        if (Array.isArray(group.uploads)) {
            return group.uploads;
        }
        if (Array.isArray(group.projects)) {
            return group.projects;
        }
        if (group.item && typeof group.item === 'object') {
            return [group.item];
        }
        return [];
    }

    function normalizeClinicalMonitoringItem(item) {
        if (!item || typeof item !== 'object' || Array.isArray(item)) {
            return {area: describeScoutItem(item), cadence: '', reviewedBy: '', currentState: '', priority: 'Draft', action: '', notes: '', tone: 'neutral'};
        }
        var priority = firstClinicalValue(item, ['priority', 'priority_level', 'gap_priority', 'risk_level']) || firstClinicalValue(item, ['status']) || 'Draft';
        var action = firstClinicalValue(item, ['action_needed', 'recommended_action', 'next_action', 'task', 'follow_up']) || '';
        return {
            area: firstClinicalValue(item, ['monitoring_area', 'area', 'name', 'title', 'clinical_area', 'review_area']) || 'Monitoring area',
            cadence: firstClinicalValue(item, ['cadence', 'review_cadence', 'frequency', 'rhythm', 'schedule']) || '',
            reviewedBy: firstClinicalValue(item, ['reviewed_by', 'committee', 'review_body', 'routed_to', 'owner', 'responsible_party']) || '',
            currentState: firstClinicalValue(item, ['current_state', 'state', 'status', 'readiness_status']) || '',
            priority: priority,
            action: action,
            notes: firstClinicalValue(item, ['notes', 'description', 'rationale', 'summary']) || '',
            tone: /high|missing|gap|overdue|not in place|review|needed/i.test(priority + ' ' + action) ? 'warning' : 'success'
        };
    }

    function normalizeClinicalUploadItem(item) {
        if (!item || typeof item !== 'object' || Array.isArray(item)) {
            return {measure: describeScoutItem(item), cadence: '', source: '', owner: '', status: 'Draft', tone: 'neutral'};
        }
        var status = firstClinicalValue(item, ['status', 'upload_status', 'readiness_status']) || 'Draft';
        return {
            measure: firstClinicalValue(item, ['measure', 'measure_name', 'data_source', 'source_name', 'name', 'title']) || 'Measure upload',
            cadence: firstClinicalValue(item, ['cadence', 'upload_cadence', 'frequency', 'schedule']) || '',
            source: firstClinicalValue(item, ['source_system', 'system', 'source', 'platform']) || '',
            owner: firstClinicalValue(item, ['owner', 'responsible_party', 'preparer']) || '',
            status: status,
            tone: /missing|needed|not set|pending/i.test(status) ? 'warning' : 'success'
        };
    }

    function normalizeClinicalRhythmItem(item) {
        if (!item || typeof item !== 'object' || Array.isArray(item)) {
            return {task: describeScoutItem(item), cadence: 'Other', owner: '', area: ''};
        }
        return {
            task: firstClinicalValue(item, ['task', 'name', 'title', 'activity', 'work_item']) || 'Task',
            cadence: normalizeClinicalCadence(firstClinicalValue(item, ['cadence', 'frequency', 'rhythm', 'schedule']) || 'Other'),
            owner: firstClinicalValue(item, ['owner', 'responsible_party', 'reviewed_by', 'committee']) || '',
            area: firstClinicalValue(item, ['area', 'monitoring_area', 'clinical_area']) || ''
        };
    }

    function normalizeClinicalProjectItem(item) {
        if (!item || typeof item !== 'object' || Array.isArray(item)) {
            return {project: describeScoutItem(item), method: '', measure: '', status: 'Draft', nextStep: '', tone: 'neutral'};
        }
        var status = firstClinicalValue(item, ['status', 'current_status', 'project_status']) || 'Draft';
        return {
            project: firstClinicalValue(item, ['project', 'project_aim', 'aim', 'name', 'title']) || 'Improvement project',
            method: firstClinicalValue(item, ['method', 'qi_method', 'framework']) || '',
            measure: firstClinicalValue(item, ['measure', 'primary_measure', 'metric']) || '',
            status: status,
            nextStep: firstClinicalValue(item, ['next_step', 'next_milestone', 'milestone', 'action_needed']) || '',
            tone: /hold|missing|not started|needed|risk/i.test(status) ? 'warning' : 'success'
        };
    }

    function clinicalPriorityGapItems(run) {
        var priorityGroup = clinicalGroupForKeys(run, ['priority_queue']);
        var priorityItems = clinicalItemsFromGroup(priorityGroup).concat(clinicalItemsForKeys(run, ['active_monitoring_improvement_tasks']));
        return priorityItems.map(normalizeClinicalGapItem).filter(function (item) {
            return item.item && (/monitor|clinical|measure|upload|medication|infection|blood|anesthesia|sedation|procedure|ancillary|quality|gap/i.test(item.searchText) || item.fromActiveMonitoring);
        });
    }

    function normalizeClinicalGapItem(item) {
        if (!item || typeof item !== 'object' || Array.isArray(item)) {
            var textValue = describeScoutItem(item);
            return {item: textValue, priority: 'Priority', why: '', target: '', area: '', tone: 'warning', searchText: textValue, fromActiveMonitoring: false};
        }
        var priority = firstClinicalValue(item, ['priority', 'priority_level', 'risk_level', 'urgency']) || 'Priority';
        var title = firstClinicalValue(item, ['item', 'name', 'title', 'task', 'gap', 'action_needed', 'recommended_action']) || 'Monitoring gap';
        var searchText = describeScoutItem(item);
        return {
            item: title,
            priority: priority,
            why: firstClinicalValue(item, ['why_it_matters', 'rationale', 'reason', 'description']) || '',
            target: firstClinicalValue(item, ['target', 'target_timing', 'timeline', 'due', 'deadline']) || '',
            area: firstClinicalValue(item, ['related_monitoring_area', 'monitoring_area', 'area', 'clinical_area']) || '',
            tone: /high|urgent|missing|gap|overdue|needed/i.test(priority + ' ' + title) ? 'warning' : 'neutral',
            searchText: searchText,
            fromActiveMonitoring: /active_monitoring/i.test(firstClinicalValue(item, ['source', 'type', 'category']) || '')
        };
    }

    function normalizeClinicalCadence(value) {
        var lower = String(value || '').toLowerCase();
        if (lower.indexOf('week') !== -1) {
            return 'Weekly';
        }
        if (lower.indexOf('month') !== -1) {
            return 'Monthly';
        }
        if (lower.indexOf('quarter') !== -1) {
            return 'Quarterly';
        }
        if (lower.indexOf('annual') !== -1 || lower.indexOf('year') !== -1) {
            return 'Annual';
        }
        return value || 'Other';
    }

    function firstClinicalValue(item, keys) {
        var value = '';
        keys.some(function (key) {
            if (item[key] !== null && item[key] !== undefined && item[key] !== '') {
                value = describeScoutItem(item[key]);
                return true;
            }
            return false;
        });
        return value;
    }

    function renderSettingsPage() {
        var page = document.getElementById('settings');
        if (!page) {
            return;
        }
        page.classList.add('qn-settings-page');
        var hospital = dashboardHospital() || currentHospitalContext() || {};
        var setup = dashboardSetupStatus(hospital);
        var scout = dashboardScoutStatus();
        var hospitalName = hospital.organization_name || hospital.name || 'Hospital workspace';
        var scoutStatus = state.latestScoutRun ? scout.status : (state.scoutOnboardingSubmitted ? 'Not generated' : 'Hospital Setup pending');
        var pendingSetup = isDay0Pending(setup);
        var chips = [
            chip(hospitalName),
            hospital.hospital_type_label ? chip(hospital.hospital_type_label) : '',
            hospital.state_code || hospital.state_name ? chip(hospital.state_code || hospital.state_name) : '',
            pendingSetup ? '' : chip('Scout: ' + scoutStatus)
        ].filter(Boolean).join('');
        page.innerHTML =
            renderSettingsAdminBanner(hospitalName) +
            '<div class="qn-reporting-header qn-settings-header">' +
            '<div><p class="qn-eyebrow">Settings</p><h2>Hospital Settings</h2><p>Review workspace preference status and setup readiness.</p><div class="qn-reporting-context qn-settings-context">' + chips + '</div></div>' +
            (pendingSetup ? '' : '<span class="qn-status-pill qn-status-' + escapeHtml(settingsStatusTone(setup, scout)) + '">' + escapeHtml(settingsStatusLabel(setup, scout)) + '</span>') +
            '</div>' +
            renderSettingsWorkspaceContext(hospital, setup, scout) +
            '<div class="qn-settings-compact-stack">' +
                renderSettingsPreferenceSource(setup) +
                renderSettingsCompactPreferenceSummary() +
                renderSettingsCompactReadiness(setup, scout) +
                renderSettingsCompactActions() +
            '</div>';
    }

    function renderSettingsAdminBanner(hospitalName) {
        var previewOrganizationId = getUrlOrganizationId();
        if (!(isGlobalAdmin() && previewOrganizationId)) {
            return '';
        }
        return '<div class="qn-scout-admin-banner qn-settings-admin-banner">' +
            '<span class="dashicons dashicons-visibility"></span>' +
            '<strong>Admin Preview Mode</strong>' +
            '<span>viewing Settings for ' + escapeHtml(hospitalName || 'this hospital') + '.</span>' +
            '<a class="qn-button qn-button-small" href="' + escapeHtml((config.homeUrl || '/') + 'qualinav/admin') + '">Back to Admin Console</a>' +
            '</div>';
    }

    function settingsStatusTone(setup, scout) {
        if (isDay0Pending(setup)) {
            return 'pending';
        }
        if (!scout.ready) {
            return 'warning';
        }
        return 'success';
    }

    function settingsStatusLabel(setup, scout) {
        if (isDay0Pending(setup)) {
            return 'Hospital Setup pending';
        }
        if (!scout.ready) {
            return 'Available after Scout Preview';
        }
        return 'Preferences ready';
    }

    function renderSettingsWorkspaceContext(hospital, setup, scout) {
        var previewOrganizationId = getUrlOrganizationId();
        var rows = [
            ['building', 'Hospital name', hospital.organization_name || hospital.name || 'Hospital workspace'],
            ['location-alt', 'State', hospital.state_code || hospital.state_name || 'Not set'],
            ['admin-home', 'Hospital type', hospital.hospital_type_label || 'Not set'],
            ['admin-users', 'Current user role', state.me ? roleLabel(state.me.qualinav_role) : 'Workspace role']
        ].filter(function (row) {
            return row[2] && row[2] !== 'Not specified';
        });
        if (isGlobalAdmin() && previewOrganizationId) {
            rows.push(['visibility', 'Admin preview', 'Active for this hospital']);
        }
        return '<section class="qn-settings-card qn-settings-workspace">' +
            '<div class="qn-panel-header"><div><p class="qn-eyebrow">Workspace context</p><h3>Hospital workspace</h3><p>Settings reflect this hospital workspace and your current access level.</p></div></div>' +
            '<div class="qn-settings-context-grid">' + rows.map(function (row) {
                return '<article><span class="dashicons dashicons-' + escapeHtml(row[0]) + '"></span><div><small>' + escapeHtml(row[1]) + '</small><strong>' + escapeHtml(row[2]) + '</strong></div></article>';
            }).join('') + '</div>' +
            '</section>';
    }

    function renderSettingsPendingReadiness() {
        var rows = [
            ['visibility', 'Regulatory sources', 'Monitoring sources will populate from Hospital Setup.'],
            ['admin-tools', 'Tools & systems', 'Workspace systems and access notes will summarize here.'],
            ['bell', 'Reminder timing', 'Lead time and buffer preferences will support future follow-up workflows.'],
            ['groups', 'Backup visibility', 'Backup users and visibility preferences will appear here.']
        ];
        return '<section class="qn-settings-card qn-settings-readiness-card">' +
            '<div class="qn-settings-card-heading"><span class="dashicons dashicons-admin-settings"></span><div><p class="qn-eyebrow">Setup-derived preferences</p><h3>Complete Hospital Setup to activate settings</h3><p>Settings are built from the Regulatory Monitoring & Preferences section of Hospital Setup. Once submitted, this page will summarize monitoring sources, reminder timing, tools, and backup visibility.</p></div></div>' +
            '<div class="qn-settings-readiness-list">' + rows.map(function (row) {
                return '<article><span class="dashicons dashicons-' + escapeHtml(row[0]) + '"></span><div><strong>' + escapeHtml(row[1]) + '</strong><p>' + escapeHtml(row[2]) + '</p></div></article>';
            }).join('') + '</div>' +
            '</section>';
    }

    function renderSettingsPreferenceSource(setup) {
        return '<section class="qn-settings-card qn-settings-source-card">' +
            '<div><p class="qn-eyebrow">Preferences source</p><h3>Preferences are managed in Hospital Setup</h3><p>Regulatory sources, tools, reminder timing, and backup visibility are captured in the Regulatory Monitoring & Preferences section of Hospital Setup.</p>' +
            (isDay0Pending(setup) ? '<span class="qn-status-pill qn-status-warning">Hospital Setup is still in progress.</span>' : '') +
            '</div>' +
            '</section>';
    }

    function renderSettingsCompactPreferenceSummary() {
        var sources = settingsListValue('monitored_sources', 'monitored_sources');
        var tools = settingsListValue('current_tools', 'current_tools');
        var lead = settingsAnswerLabel('reminder_lead_time');
        var buffer = settingsAnswerLabel('reminder_buffer_time');
        var users = normalizeBackupUsersValue(settingsAnswerRaw('backup_visibility_users'));
        var cards = [];

        if (sources.length) {
            cards.push(renderSettingsSummaryItem('visibility', 'Monitored sources', renderSettingsInlineChips(sources)));
        }
        if (tools.length) {
            cards.push(renderSettingsSummaryItem('admin-tools', 'Tools & systems', renderSettingsInlineChips(tools)));
        }
        if (lead || buffer) {
            cards.push(renderSettingsSummaryItem('bell', 'Reminder timing', '<p>' + escapeHtml([lead ? 'Lead time: ' + lead : '', buffer ? 'Buffer: ' + buffer : ''].filter(Boolean).join(' / ')) + '</p>'));
        }
        if (users.length) {
            cards.push(renderSettingsSummaryItem('groups', 'Backup visibility', '<p>' + escapeHtml(users.map(function (user) {
                var linked = organizationUserOptions().find(function (option) { return Number(option.user_id) === Number(user.user_id); });
                return linked ? linked.display_name : (user.name || user.name_organization || 'Unlinked backup user');
            }).join(', ')) + '</p>'));
        }

        if (!cards.length) {
            return '';
        }

        return '<section class="qn-settings-card qn-settings-summary-card">' +
            '<div><p class="qn-eyebrow">Captured preferences</p><h3>Setup preference summary</h3></div>' +
            '<div class="qn-settings-summary-grid">' + cards.join('') + '</div>' +
            '</section>';
    }

    function renderSettingsSummaryItem(icon, title, body) {
        return '<article class="qn-settings-summary-item"><span class="dashicons dashicons-' + escapeHtml(icon) + '"></span><div><strong>' + escapeHtml(title) + '</strong>' + body + '</div></article>';
    }

    function renderSettingsInlineChips(values) {
        return '<div class="qn-settings-inline-chips">' + values.map(function (value) {
            return '<span class="qn-scout-muted-chip">' + escapeHtml(value) + '</span>';
        }).join('') + '</div>';
    }

    function renderSettingsCompactReadiness(setup, scout) {
        return '<section class="qn-settings-card qn-settings-compact-status">' +
            '<div><p class="qn-eyebrow">Readiness</p><h3>Current readiness</h3></div>' +
            '<div class="qn-settings-status-list">' +
                renderSettingsStatusRow('Hospital Setup', setup.status + ' (' + setup.percent + '%)') +
                renderSettingsStatusRow('Scout Preview', scout.status) +
                renderSettingsStatusRow('Calendar sync', 'Not active in this phase') +
                renderSettingsStatusRow('Annual review', 'Annual review prompt planned') +
            '</div>' +
            '</section>';
    }

    function renderSettingsStatusRow(label, value) {
        return '<div class="qn-settings-status-row"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value) + '</strong></div>';
    }

    function renderSettingsCompactActions() {
        var canEdit = canEditSettingsPreferences();
        return '<section class="qn-settings-card qn-settings-actions-card">' +
            '<div><p class="qn-eyebrow">Actions</p><h3>Update preferences</h3><p>' + escapeHtml(canEdit ? 'Preferences are edited through Hospital Setup.' : 'Your current role can review setup-managed preferences.') + '</p></div>' +
            '<div class="qn-settings-cta-actions">' +
                '<button class="qn-button qn-button-secondary" type="button" data-section-target="day-0-setup">' + escapeHtml(canEdit ? 'Continue Hospital Setup' : 'View Hospital Setup') + '</button>' +
                '<button class="qn-button qn-button-primary" type="button" data-section-target="day-0-setup" data-onboarding-section="regulatory_tools_preferences">' + escapeHtml(canEdit ? 'Open Regulatory Monitoring section' : 'View Regulatory Monitoring section') + '</button>' +
            '</div>' +
            '</section>';
    }

    function renderSettingsRegulatory() {
        var sources = settingsListValue('monitored_sources', 'monitored_sources');
        var update = settingsAnswerLabel('update_preference');
        var adjustments = settingsAnswerLabel('auto_propose_task_adjustments');
        if (!sources.length && !update && !adjustments) {
            return renderSettingsEmptyCard('visibility', 'Regulatory Monitoring Preferences', 'Regulatory monitoring preferences will appear after Hospital Setup is completed.');
        }
        return '<section class="qn-settings-card">' +
            '<div class="qn-settings-card-heading"><span class="dashicons dashicons-visibility"></span><div><p class="qn-eyebrow">Regulatory Monitoring</p><h3>Monitoring preferences</h3></div></div>' +
            renderSettingsChipBlock('Monitored sources', sources, 'No sources selected yet') +
            renderSettingsKeyValues([
                ['Digest / alert preference', update || 'Not selected'],
                ['Proposed adjustment behavior', adjustments || 'Not selected']
            ]) +
            '<p class="qn-settings-note">Scout will not automatically change tasks without review unless your organization later enables that behavior.</p>' +
            '</section>';
    }

    function renderSettingsTools() {
        var tools = settingsListValue('current_tools', 'current_tools');
        var rows = [
            ['Calendar system', settingsAnswerLabel('calendar_system') || 'Not selected'],
            ['EHR system', settingsAnswerText('ehr_system') || 'Not specified'],
            ['Incident reporting system', settingsAnswerText('incident_reporting_system') || 'Not specified'],
            ['NHSN / QualityNet access', settingsAnswerLabel('nhsn_qualitynet_access') || 'Not selected']
        ];
        if (!tools.length && rows.every(function (row) { return /Not selected|Not specified/.test(row[1]); })) {
            return renderSettingsEmptyCard('admin-tools', 'Tools & Systems', 'Tool and system preferences will appear after Hospital Setup is completed.');
        }
        return '<section class="qn-settings-card">' +
            '<div class="qn-settings-card-heading"><span class="dashicons dashicons-admin-tools"></span><div><p class="qn-eyebrow">Tools & Systems</p><h3>Workspace tools</h3></div></div>' +
            renderSettingsChipBlock('Current tools', tools, 'No tools selected yet') +
            renderSettingsKeyValues(rows) +
            '<p class="qn-settings-note">Do not store passwords, credentials, event narratives, or patient information here.</p>' +
            '</section>';
    }

    function renderSettingsReminders() {
        var lead = settingsAnswerLabel('reminder_lead_time') || 'Not selected';
        var buffer = settingsAnswerLabel('reminder_buffer_time') || 'Not selected';
        var configured = lead !== 'Not selected' || buffer !== 'Not selected';
        return '<section class="qn-settings-card">' +
            '<div class="qn-settings-card-heading"><span class="dashicons dashicons-bell"></span><div><p class="qn-eyebrow">Reminder Preferences</p><h3>Reminder timing</h3></div></div>' +
            renderSettingsKeyValues([
                ['Lead time', lead],
                ['Buffer time', buffer],
                ['Reminder readiness', configured ? 'Preferences captured' : 'Complete Hospital Setup first'],
                ['Calendar sync', 'Not active in this phase']
            ]) +
            '<p class="qn-settings-note">Reminder preferences captured during Hospital Setup will support future scheduling and follow-up workflows.</p>' +
            '</section>';
    }

    function renderSettingsBackupVisibility() {
        var users = normalizeBackupUsersValue(settingsAnswerRaw('backup_visibility_users'));
        if (!users.length) {
            return renderSettingsEmptyCard('visibility', 'Backup Visibility', 'Backup visibility users will appear after Hospital Setup is completed.');
        }
        return '<section class="qn-settings-card qn-settings-backup-card">' +
            '<div class="qn-settings-card-heading"><span class="dashicons dashicons-groups"></span><div><p class="qn-eyebrow">Backup Visibility</p><h3>Backup users</h3></div></div>' +
            '<div class="qn-settings-backup-list">' + users.map(renderSettingsBackupUser).join('') + '</div>' +
            '</section>';
    }

    function renderSettingsBackupUser(user, index) {
        user = user || {};
        var linked = organizationUserOptions().find(function (option) { return Number(option.user_id) === Number(user.user_id); });
        var name = linked ? linked.display_name : (user.name || user.name_organization || 'Unlinked backup user ' + (index + 1));
        var role = linked && linked.role ? linked.role.replace(/_/g, ' ') : (user.role || 'Not specified');
        return '<article>' +
            '<div><strong>' + escapeHtml(name) + '</strong><span class="qn-scout-status-badge qn-scout-status-neutral">' + escapeHtml(linked ? 'Linked hospital user' : 'Needs linking') + '</span></div>' +
            '<dl>' +
                reportingDatum('Role', role) +
                (user.notes || user.legacy ? reportingDatum('Notes', user.notes || user.legacy) : '') +
            '</dl>' +
            '</article>';
    }

    function renderSettingsSetupStatus(setup, scout) {
        var previews = scout.ready ? 'Reporting, committees, plans, and clinical monitoring previews available' : 'Module previews available after Scout Preview';
        return '<section class="qn-settings-card qn-settings-status-card">' +
            '<div class="qn-panel-header"><div><p class="qn-eyebrow">Setup status</p><h3>Readiness status</h3><p>One summary of setup, Scout Preview, and module readiness.</p></div></div>' +
            renderSettingsKeyValues([
                ['Hospital Setup', setup.status + ' (' + setup.percent + '%)'],
                ['Scout Preview', scout.status],
                ['Module previews', previews],
                ['Annual review', 'Annual review prompt planned']
            ]) +
            '</section>';
    }

    function renderSettingsCtaPanel() {
        var canEdit = canEditSettingsPreferences();
        return '<section class="qn-reporting-cta-panel qn-settings-cta-panel">' +
            '<div><p class="qn-eyebrow">Hospital Setup</p><h3>' + escapeHtml(canEdit ? 'Continue setup to update settings.' : 'View only') + '</h3><p>' + escapeHtml(canEdit ? 'Regulatory sources, tools, reminders, and backup visibility are edited in Hospital Setup.' : 'Your current role can review these preferences but cannot edit hospital setup preferences.') + '</p></div>' +
            (canEdit ? '<div class="qn-settings-cta-actions"><button class="qn-button qn-button-secondary" type="button" data-section-target="day-0-setup">Continue Hospital Setup</button><button class="qn-button qn-button-primary" type="button" data-section-target="day-0-setup" data-onboarding-section="regulatory_tools_preferences">Open Regulatory Monitoring section</button></div>' : '<span class="qn-status-pill qn-status-neutral">View only</span>') +
            '</section>';
    }

    function renderSettingsEmptyCard(icon, title, message) {
        return '<section class="qn-settings-card qn-settings-empty-card">' +
            '<div class="qn-settings-card-heading"><span class="dashicons dashicons-' + escapeHtml(icon) + '"></span><div><p class="qn-eyebrow">Setup-derived preferences</p><h3>' + escapeHtml(title) + '</h3></div></div>' +
            '<p class="qn-muted-note">' + escapeHtml(message) + '</p>' +
            '</section>';
    }

    function renderSettingsChipBlock(label, values, emptyText) {
        return '<div class="qn-settings-chip-block"><small>' + escapeHtml(label) + '</small>' +
            (values.length ? '<div class="qn-scout-chip-list">' + values.map(function (value) {
                return '<span class="qn-scout-muted-chip">' + escapeHtml(value) + '</span>';
            }).join('') + '</div>' : '<p class="qn-muted-note">' + escapeHtml(emptyText) + '</p>') +
            '</div>';
    }

    function renderSettingsKeyValues(rows) {
        return '<div class="qn-settings-kv-grid">' + rows.map(function (row) {
            return '<div><span>' + escapeHtml(row[0]) + '</span><strong>' + escapeHtml(row[1]) + '</strong></div>';
        }).join('') + '</div>';
    }

    function canEditSettingsPreferences() {
        return state.onboarding && state.onboarding.can_edit && state.me &&
            (state.me.qualinav_role === 'quality_director' || isGlobalAdmin());
    }

    function settingsAnswerRaw(key) {
        return state.onboarding && state.onboarding.answers ? state.onboarding.answers[key] : null;
    }

    function settingsAnswerText(key) {
        var value = settingsAnswerRaw(key);
        if (value === null || value === undefined || value === '') {
            return '';
        }
        return describeScoutItem(value);
    }

    function settingsAnswerLabel(key) {
        var value = settingsAnswerRaw(key);
        if (value === null || value === undefined || value === '') {
            return '';
        }
        return optionLabelByValue(stepEightOptions(key), value) || describeScoutItem(value);
    }

    function settingsListValue(key, optionKey) {
        var raw = settingsAnswerRaw(key);
        var values = Array.isArray(raw) ? raw : (raw ? [raw] : []);
        return cleanScoutList(values.map(function (value) {
            return optionLabelByValue(stepEightOptions(optionKey || key), value) || describeScoutItem(value);
        }));
    }

    function dashboardUsersCard() {
        var pending = (state.invitations || []).filter(function (invite) {
            return invite.status === 'pending' || invite.status === 'invited';
        }).length;
        var active = (state.users || []).filter(function (user) {
            return user.qualinav_status === 'active';
        }).length;
        if (state.hospitalPeoplePreviewMode) {
            return {
                icon: 'groups',
                eyebrow: 'Hospital Users',
                title: 'Preview mode',
                detail: 'User counts are available from the live hospital user view.',
                status: 'neutral',
                cta: 'Manage Users',
                target: 'hospital-users'
            };
        }
        if (!state.hospitalPeopleLoaded && !state.users.length && !state.invitations.length) {
            return {
                icon: 'groups',
                eyebrow: 'Hospital Users',
                title: 'Loading workspace',
                detail: 'User and invitation counts are loading.',
                status: 'neutral',
                cta: 'Manage Users',
                target: 'hospital-users'
            };
        }
        return {
            icon: 'groups',
            eyebrow: 'Hospital Users',
            title: active + ' active',
            detail: pending ? pending + ' pending invite' + (pending === 1 ? '' : 's') : 'No pending invites',
            status: pending ? 'warning' : 'success',
            cta: 'Manage Users',
            target: 'hospital-users'
        };
    }

    function dashboardCard(card) {
        return '<article class="qn-card qn-dashboard-card qn-dashboard-card-' + escapeHtml(card.status || 'neutral') + '">' +
            '<span class="dashicons dashicons-' + escapeHtml(card.icon || 'dashboard') + '"></span>' +
            '<div><p class="qn-label">' + escapeHtml(card.eyebrow) + '</p><h3>' + escapeHtml(card.title) + '</h3><p>' + escapeHtml(card.detail) + '</p>' +
            (card.progress !== undefined ? '<div class="qn-dashboard-card-progress">' + progressMini(card.progress) + '</div>' : '') +
            '</div>' + dashboardCta(card) + '</article>';
    }

    function dashboardModule(icon, title, status, detail, cta, target, tone) {
        return dashboardCard({
            icon: icon,
            eyebrow: title,
            title: status,
            detail: detail,
            status: tone || 'neutral',
            cta: cta,
            target: target
        });
    }

    function dashboardCta(card) {
        if (!card.cta || !card.target) {
            return '';
        }
        return '<a class="qn-button qn-button-small' + (card.primary ? ' qn-button-primary' : '') + '" href="#' + escapeHtml(card.target) + '" data-section-target="' + escapeHtml(card.target) + '">' + escapeHtml(card.cta) + '</a>';
    }

    function renderMetrics(metrics) {
        Object.keys(metrics || {}).forEach(function (key) {
            var node = document.querySelector('[data-metric="' + key + '"]');
            if (node && typeof metrics[key] !== 'object') {
                node.textContent = text(metrics[key]);
            }
        });
    }

    function updateEnterpriseMetrics() {
        setText('#qn-metric-health-systems', state.healthSystems.length);
        var pending = (state.invitations || []).filter(function (invite) {
            return invite.status === 'pending';
        }).length;
        setText('#qn-metric-pending-invites', pending);
    }

    function renderSystemCheck() {
        var check = state.systemCheck;
        if (!check) {
            return;
        }
        var userMissing = check.required_user_columns && check.required_user_columns.missing ? check.required_user_columns.missing : [];
        var tableMissing = check.required_plugin_tables && check.required_plugin_tables.missing ? check.required_plugin_tables.missing : [];
        var orgMissing = check.organization_classification_columns && check.organization_classification_columns.missing ? check.organization_classification_columns.missing : [];
        setText('[data-system-check="plugin_version"]', check.plugin_version);
        setText('[data-system-check="environment"]', check.environment);
        setText('[data-system-check="db_prefix"]', check.db_prefix);
        setText('[data-system-check="current_role"]', (check.current_user ? roleLabel(check.current_user.qualinav_role) : '-') + ' / ' + (check.current_user ? check.current_user.qualinav_status : '-'));
        setText('[data-system-check="user_columns"]', userMissing.length ? 'Missing: ' + userMissing.join(', ') : 'All present');
        setText('[data-system-check="plugin_tables"]', tableMissing.length ? 'Missing: ' + tableMissing.length : 'All present');
        setText('[data-system-check="org_columns"]', orgMissing.length ? 'Missing: ' + orgMissing.join(', ') : 'All present');
        setText('[data-system-check="questionnaire"]', text(check.questionnaire_sections) + ' sections / ' + text(check.questionnaire_questions) + ' questions');
        setText('[data-system-check="scout_bridge"]', check.scout_bridge_available ? 'Available' : 'Not available');
        setText('[data-system-check="scout_runs"]', text(check.scout_run_count));
    }

    function setTableMessage(id, colspan, message) {
        var body = document.getElementById(id);
        if (body) {
            body.innerHTML = '<tr><td colspan="' + colspan + '">' + emptyState('info', message, '') + '</td></tr>';
        }
    }

    function activateSection(section, updateHash) {
        section = normalizeSectionTarget(section);
        var target = document.querySelector('[data-section="' + section + '"]');
        if (!target) {
            return;
        }
        document.querySelectorAll('[data-section]').forEach(function (panel) {
            var active = panel === target;
            panel.hidden = !active;
            panel.classList.toggle('qn-section-active', active);
        });
        document.querySelectorAll('[data-section-target]').forEach(function (item) {
            var active = item.getAttribute('data-section-target') === section;
            if (item.classList.contains('qn-nav-item')) {
                item.classList.toggle('qn-nav-item-active', active);
            }
            if (item.classList.contains('qn-myorg-subnav-link')) {
                item.classList.toggle('qn-myorg-subnav-active', active);
            }
            if (active && item.hasAttribute('data-title')) {
                var title = document.getElementById('qn-page-title');
                var eyebrow = document.getElementById('qn-page-eyebrow');
                if (title) {
                    title.textContent = item.getAttribute('data-title') || item.textContent.trim();
                }
                if (eyebrow) {
                    eyebrow.textContent = item.getAttribute('data-subtitle') || '';
                }
            }
        });
        if (updateHash) {
            window.history.replaceState(null, '', window.location.pathname + window.location.search + '#' + sectionHash(section));
        }
        if (section === 'reporting') {
            renderReportingPage();
        }
        if (section === 'committees') {
            renderCommitteesPage();
        }
        if (section === 'plans') {
            renderPlansPoliciesPage();
        }
        if (section === 'clinical') {
            renderClinicalMonitoringPage();
        }
        if (section === 'settings') {
            renderSettingsPage();
        }
        ensureAdminSectionData(section);
    }

    function normalizeSectionTarget(section) {
        if (section === 'settings') {
            return config.isSiteShellConsole ? 'day-0-setup' : 'dashboard';
        }
        if (config.isSiteShellConsole && section === 'dashboard') {
            return 'day-0-setup';
        }
        if (config.isSiteShellConsole && !section) {
            return 'day-0-setup';
        }
        if (document.body.classList.contains('qn-hospital-console-page')) {
            if (section === 'users' || section === 'invitations' || section === 'hospital-invitations') {
                return 'hospital-users';
            }
            if (section === 'clinical-monitoring') {
                return 'clinical';
            }
        }
        return section;
    }

    function sectionHash(section) {
        if (document.body.classList.contains('qn-hospital-console-page') && section === 'hospital-users') {
            return 'users';
        }
        if (document.body.classList.contains('qn-hospital-console-page') && section === 'clinical') {
            return 'clinical-monitoring';
        }
        return section;
    }

    function initSections() {
        var fallback = document.body.classList.contains('qn-admin-console-page') ? 'overview' : (config.defaultSection || 'dashboard');
        var hash = window.location.hash ? window.location.hash.replace('#', '') : '';
        var target = hash || fallback;
        if (config.isSiteShellConsole && !target) {
            target = 'day-0-setup';
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', window.location.pathname + window.location.search + '#day-0-setup');
            }
        }
        activateSection(target, false);
        if (config.isSiteShellConsole) {
            window.requestAnimationFrame(function () {
                window.scrollTo(0, 0);
            });
            window.setTimeout(function () {
                window.scrollTo(0, 0);
            }, 120);
        }
    }

    function initSidebar() {
        var shell = document.querySelector('.qn-app-shell');
        var sidebar = document.querySelector('.qn-sidebar');
        if (!shell || !sidebar) {
            return;
        }

        var toggle = sidebar.querySelector('[data-sidebar-toggle]');
        var storageKey = 'qualinavSidebarExpanded';

        function setExpanded(expanded) {
            shell.classList.toggle('qn-sidebar-expanded', expanded);
            shell.classList.remove('qn-sidebar-hover');
            if (toggle) {
                toggle.setAttribute('aria-pressed', expanded ? 'true' : 'false');
                toggle.setAttribute('aria-label', expanded ? 'Collapse sidebar' : 'Keep sidebar expanded');
                toggle.title = expanded ? 'Collapse sidebar' : 'Keep sidebar expanded';
            }
        }

        sidebar.querySelectorAll('.qn-home-link, .qn-nav-item').forEach(function (item) {
            var label = item.getAttribute('data-title') || item.textContent.replace(/\s+/g, ' ').trim();
            if (label) {
                item.setAttribute('title', label);
                item.setAttribute('aria-label', label);
            }
        });

        try {
            setExpanded(window.localStorage.getItem(storageKey) === '1');
        } catch (error) {
            setExpanded(false);
        }

        sidebar.addEventListener('mouseenter', function () {
            if (!shell.classList.contains('qn-sidebar-expanded')) {
                shell.classList.add('qn-sidebar-hover');
            }
        });
        sidebar.addEventListener('mouseleave', function () {
            shell.classList.remove('qn-sidebar-hover');
        });
        sidebar.addEventListener('focusin', function () {
            if (!shell.classList.contains('qn-sidebar-expanded')) {
                shell.classList.add('qn-sidebar-hover');
            }
        });
        sidebar.addEventListener('focusout', function (event) {
            if (!sidebar.contains(event.relatedTarget)) {
                shell.classList.remove('qn-sidebar-hover');
            }
        });
        if (toggle) {
            toggle.addEventListener('click', function () {
                var expanded = !shell.classList.contains('qn-sidebar-expanded');
                setExpanded(expanded);
                try {
                    window.localStorage.setItem(storageKey, expanded ? '1' : '0');
                } catch (error) {
                    // Sidebar preference is progressive enhancement only.
                }
            });
        }
    }

    function closeActionMenus(except) {
        document.querySelectorAll('.qn-action-menu-list').forEach(function (menu) {
            if (menu !== except) {
                menu.hidden = true;
            }
        });
        document.querySelectorAll('[data-action-menu-toggle]').forEach(function (button) {
            if (!except || !button.parentNode || button.parentNode.querySelector('.qn-action-menu-list') !== except) {
                button.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function actionMenu(items) {
        return '<div class="qn-action-menu">' +
            '<button class="qn-btn qn-btn-menu" type="button" data-action-menu-toggle aria-expanded="false" aria-label="Open actions menu">' +
            '<span class="dashicons dashicons-ellipsis"></span><span class="qn-sr-only">Actions</span></button>' +
            '<div class="qn-action-menu-list" hidden>' + items.join('') + '</div></div>';
    }

    function actionMenuButton(label, attr, value, icon, variant, extraAttrs) {
        return '<button type="button" class="' + (variant === 'danger' ? 'qn-menu-danger' : '') + '" ' + (extraAttrs || '') + ' ' + attr + '="' + escapeHtml(value) + '">' +
            '<span class="dashicons dashicons-' + escapeHtml(icon || 'admin-generic') + '"></span>' + escapeHtml(label) + '</button>';
    }

    function setActionLoading(button, label) {
        if (!button) {
            return function () {};
        }
        var originalHtml = button.getAttribute('data-original-html') || button.innerHTML;
        var icon = button.querySelector('.dashicons');
        var iconClass = icon ? icon.className : 'dashicons dashicons-update';
        button.setAttribute('data-original-html', originalHtml);
        button.classList.add('qn-action-loading');
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.innerHTML = '<span class="' + escapeHtml(iconClass) + '"></span>' + escapeHtml(label || 'Working...');
        return function () {
            button.classList.remove('qn-action-loading');
            button.disabled = false;
            button.removeAttribute('aria-busy');
            button.innerHTML = button.getAttribute('data-original-html') || originalHtml;
            button.removeAttribute('data-original-html');
        };
    }

    function setButtonLoading(button, label) {
        if (!button) {
            return function () {};
        }
        var originalHtml = button.getAttribute('data-original-html') || button.innerHTML;
        button.setAttribute('data-original-html', originalHtml);
        button.classList.add('qn-is-loading');
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        if (button.classList.contains('qn-plan-policy-document-action')) {
            button.setAttribute('aria-label', label || 'Working');
            button.innerHTML = '<span class="dashicons dashicons-update"></span><span class="qn-sr-only">' + escapeHtml(label || 'Working') + '</span>';
        } else {
            button.innerHTML = '<span class="dashicons dashicons-update"></span>' + escapeHtml(label || 'Working...');
        }
        return function () {
            button.classList.remove('qn-is-loading');
            button.disabled = false;
            button.removeAttribute('aria-busy');
            button.innerHTML = button.getAttribute('data-original-html') || originalHtml;
            if (button.classList.contains('qn-plan-policy-document-action') && button.title) {
                button.setAttribute('aria-label', button.title);
            }
            button.removeAttribute('data-original-html');
        };
    }

    function setControlLoading(control, loading) {
        if (!control) {
            return;
        }
        control.disabled = !!loading;
        control.classList.toggle('qn-control-loading', !!loading);
        if (loading) {
            control.setAttribute('aria-busy', 'true');
        } else {
            control.removeAttribute('aria-busy');
        }
    }

    function setWorkspaceLoading(loading, message) {
        var shell = document.querySelector('.qn-app-shell');
        if (!shell) {
            return;
        }
        var overlay = document.getElementById('qn-workspace-loading');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'qn-workspace-loading';
            overlay.className = 'qn-workspace-loading';
            overlay.setAttribute('role', 'status');
            overlay.setAttribute('aria-live', 'polite');
            overlay.innerHTML = '<div class="qn-workspace-loading-card"><span class="dashicons dashicons-update"></span><strong></strong><small>Please wait while the selected hospital workspace loads.</small></div>';
            shell.appendChild(overlay);
        }
        overlay.querySelector('strong').textContent = message || 'Loading workspace...';
        overlay.hidden = !loading;
        shell.classList.toggle('qn-workspace-is-loading', !!loading);
    }

    function setOnboardingPanelLoading(loading) {
        var panel = document.getElementById('day-0-setup');
        if (panel) {
            panel.classList.toggle('qn-onboarding-loading', !!loading);
        }
    }

    function renderOnboardingLoadError(error) {
        var message = error && error.message ? error.message : 'Hospital Setup could not load. Please refresh and try again.';
        setText('#qn-onboarding-message', message);
        setOnboardingPanelLoading(false);
        var container = document.getElementById('qn-onboarding-fields');
        if (container && !container.querySelector('.qn-load-error')) {
            container.innerHTML = '<div class="qn-readonly-notice qn-load-error"><span class="dashicons dashicons-warning"></span><div><strong>Hospital Setup could not load</strong><p>' + escapeHtml(message) + '</p></div></div>';
        }
    }

    function setOnboardingSaveStatus(status, label) {
        var node = document.getElementById('qn-onboarding-save-status');
        if (!node) {
            return;
        }
        if (state.onboardingSaveStatusTimer) {
            clearTimeout(state.onboardingSaveStatusTimer);
            state.onboardingSaveStatusTimer = null;
        }
        var nextStatus = status || 'ready';
        node.textContent = label || (nextStatus === 'ready' ? 'Ready' : nextStatus);
        node.className = 'qn-site-header-progress-status qn-save-status qn-save-status-' + nextStatus;
        if (nextStatus === 'saved') {
            state.onboardingSaveStatusTimer = setTimeout(function () {
                setOnboardingSaveStatus('ready', 'Ready');
            }, 2400);
        }
    }

    function cellPrimary(title, subtitle) {
        return '<div class="qn-cell-primary"><strong>' + escapeHtml(text(title)) + '</strong>' +
            (subtitle ? '<small>' + escapeHtml(text(subtitle)) + '</small>' : '') + '</div>';
    }

    function chip(label, title) {
        var display = text(label || 'Not specified');
        var tooltip = text(title || display);
        return '<span class="qn-chip" title="' + escapeHtml(tooltip) + '">' + escapeHtml(display) + '</span>';
    }

    function progressMini(value) {
        var percent = Math.max(0, Math.min(100, Number(value) || 0));
        return '<div class="qn-mini-progress"><span style="width:' + percent + '%"></span></div><small>' + percent + '%</small>';
    }

    function initials(name, email) {
        var base = text(name || email || 'Q');
        var parts = base.replace(/@.*/, '').split(/[.\s_-]+/).filter(Boolean);
        return parts.slice(0, 2).map(function (part) { return part.charAt(0).toUpperCase(); }).join('') || 'Q';
    }

    function userCell(user) {
        var fallback = initials(user.display_name, user.user_email);
        var avatarUrl = text(user.avatar_url || user.profile_image_url || '');
        var avatar = '<span class="qn-avatar qn-user-avatar-fallback" aria-hidden="true">' + escapeHtml(fallback) + '</span>';
        if (avatarUrl) {
            avatar = '<span class="qn-avatar qn-user-avatar"><span class="qn-user-avatar-fallback" aria-hidden="true">' + escapeHtml(fallback) + '</span><img src="' + escapeHtml(avatarUrl) + '" alt="' + escapeHtml(text(user.display_name || 'User avatar')) + '" loading="lazy" onerror="this.hidden=true;this.parentNode.classList.add(\'qn-avatar-image-failed\');"></span>';
        }
        return '<div class="qn-user-cell">' + avatar +
            '<div><strong>' + escapeHtml(text(user.display_name || 'Unnamed user')) + '</strong><small>' + escapeHtml(text(user.user_email)) + '</small></div></div>';
    }

    function renderHospitals() {
        var body = document.getElementById('qn-hospitals-table-body');
        if (!body) {
            return;
        }
        renderHospitalFilterContext();
        var rows = filteredHospitalsForDisplay();
        renderHospitalPagination(rows.length);
        if (!state.hospitals.length) {
            setTableMessage('qn-hospitals-table-body', 6, state.hospitalFilter ? 'No hospitals are assigned to this health system yet.' : 'No hospitals yet. Create the first hospital to begin setup.');
            return;
        }
        if (!rows.length) {
            setTableMessage('qn-hospitals-table-body', 6, 'No hospitals match your search.');
            return;
        }
        var totalPages = Math.max(1, Math.ceil(rows.length / state.hospitalPageSize));
        state.hospitalPage = Math.min(Math.max(1, state.hospitalPage), totalPages);
        var start = (state.hospitalPage - 1) * state.hospitalPageSize;
        var visibleRows = rows.slice(start, start + state.hospitalPageSize);
        body.innerHTML = visibleRows.map(function (hospital) {
            var qd = hospital.primary_quality_director;
            var qdText = qd ? cellPrimary(qd.display_name || qd.user_email, qd.user_email) : '<span class="qn-muted-text">Not assigned</span>';
            var cityState = [hospital.city, hospital.state_name || hospital.state_code].filter(Boolean).join(', ');
            return '<tr>' +
                '<td>' + cellPrimary(hospital.name, cityState) + '</td>' +
                '<td>' + hospitalClassificationCell(hospital) + '</td>' +
                '<td>' + escapeHtml(text(hospital.state_code || hospital.state_name || hospital.state_id)) + '</td>' +
                '<td>' + hospitalStatusCell(hospital) + '</td>' +
                '<td>' + qdText + '</td>' +
                '<td>' + actionMenu([
                    actionMenuButton('Open Console', 'data-open-console', hospital.id, 'dashboard'),
                    actionMenuButton('View/Edit', 'data-edit-hospital', hospital.id, 'edit'),
                    actionMenuButton('Brand', 'data-brand-hospital', hospital.id, 'admin-customizer'),
                    actionMenuButton('Invite Hospital QD', 'data-invite-qd', hospital.id, 'email-alt'),
                    actionMenuButton('View Setup', 'data-view-setup', hospital.id, 'clipboard'),
                    actionMenuButton('Scout Preview', 'data-view-scout', hospital.id, 'lightbulb')
                ]) + '</td>' +
                '</tr>';
        }).join('');
        renderHospitalPagination(rows.length);
    }

    function filteredHospitalsForDisplay() {
        var query = (state.hospitalSearch || '').trim().toLowerCase();
        if (!query) {
            return state.hospitals.slice();
        }
        return state.hospitals.filter(function (hospital) {
            var qd = hospital.primary_quality_director || {};
            var haystack = [
                hospital.name,
                hospital.city,
                hospital.state_name,
                hospital.state_code,
                hospital.parent_system_name,
                hospital.hospital_type_label,
                hospital.service_model_label,
                hospital.status,
                qd.display_name,
                qd.user_email
            ].map(function (value) {
                return value ? String(value).toLowerCase() : '';
            }).join(' ');
            return haystack.indexOf(query) !== -1;
        });
    }

    function renderHospitalPagination(totalRows) {
        var summary = document.getElementById('qn-hospital-pagination-summary');
        var prev = document.getElementById('qn-hospital-prev-page');
        var next = document.getElementById('qn-hospital-next-page');
        if (!summary || !prev || !next) {
            return;
        }
        var totalPages = Math.max(1, Math.ceil(totalRows / state.hospitalPageSize));
        state.hospitalPage = Math.min(Math.max(1, state.hospitalPage), totalPages);
        if (!totalRows) {
            summary.textContent = 'No hospitals to show';
        } else {
            var start = ((state.hospitalPage - 1) * state.hospitalPageSize) + 1;
            var end = Math.min(totalRows, state.hospitalPage * state.hospitalPageSize);
            summary.textContent = 'Showing ' + start + '-' + end + ' of ' + totalRows;
        }
        prev.disabled = state.hospitalPage <= 1;
        next.disabled = state.hospitalPage >= totalPages;
    }

    function resetHospitalPagination() {
        state.hospitalPage = 1;
    }

    function renderHospitalFilterContext() {
        var banner = document.getElementById('qn-hospital-filter-context');
        var title = document.getElementById('qn-hospital-filter-title');
        var note = document.getElementById('qn-hospital-filter-note');
        if (!banner) {
            return;
        }
        if (!state.hospitalFilter) {
            banner.hidden = true;
            return;
        }
        banner.hidden = false;
        if (title) {
            title.textContent = state.hospitalFilter.name || 'Filtered hospitals';
        }
        if (note) {
            var coverage = qualityDirectorCoverage(state.hospitalFilter.id);
            note.textContent = coverage.total + ' hospitals in this system · ' + coverage.assigned + ' with QD assigned';
        }
    }

    function clearHospitalFilter() {
        state.hospitalFilter = null;
        state.hospitals = (state.allHospitals || []).slice();
        resetHospitalPagination();
        renderHospitals();
        activateSection('hospitals', true);
    }

    function returnToHealthSystems() {
        state.hospitalFilter = null;
        state.hospitals = (state.allHospitals || []).slice();
        resetHospitalPagination();
        renderHospitals();
        activateSection('health-systems', true);
    }

    function showSystemHospitals(systemId) {
        closeActionMenus();
        var selected = findSystem(systemId);
        state.hospitalFilter = {
            id: Number(systemId),
            name: selected ? selected.name : 'Selected health system'
        };
        var localMatches = (state.allHospitals || []).filter(function (hospital) {
            return Number(hospital.parent_system_id) === Number(systemId);
        });
        if (localMatches.length || state.allHospitals.length) {
            state.hospitals = localMatches;
            resetHospitalPagination();
            activateSection('hospitals', true);
            renderHospitals();
            showToast('Showing hospitals for ' + state.hospitalFilter.name + '.', 'success');
            return;
        }
        setTableMessage('qn-hospitals-table-body', 6, 'Loading hospitals for this system...');
        api('/admin/health-systems/' + systemId + '/hospitals').then(function (hospitals) {
            state.hospitals = hospitals || [];
            resetHospitalPagination();
            activateSection('hospitals', true);
            renderHospitals();
            showToast('Showing hospitals for ' + state.hospitalFilter.name + '.', 'success');
        }).catch(function (error) {
            showToast(error.message, 'warning');
        });
    }

    function hospitalClassificationCell(hospital) {
        return '<div class="qn-detail-stack">' +
            '<div class="qn-chip-row">' + chip(hospital.parent_system_name || 'Independent') + '</div>' +
            '<div class="qn-chip-row">' + chip(hospital.hospital_type_label) + chip(hospital.service_model_label) + '</div>' +
            '</div>';
    }

    function hospitalStatusCell(hospital) {
        var setup = text(hospital.onboarding_status || 'Setup open');
        var sectionSummary = hospitalOnboardingSectionSummary(hospital);
        return '<div class="qn-detail-stack qn-status-stack">' +
            '<div>' + statusPill(hospital.status) + '<span class="qn-muted-inline">' + escapeHtml(setup) + '</span></div>' +
            '<div class="qn-progress-inline">' + progressMini(hospital.onboarding_percent) + '</div>' +
            (sectionSummary ? '<div class="qn-admin-section-progress" title="' + escapeHtml(sectionSummary.title) + '">' + escapeHtml(sectionSummary.label) + '</div>' : '') +
            '</div>';
    }

    function hospitalOnboardingSectionSummary(hospital) {
        var progress = hospital && hospital.onboarding_section_progress ? hospital.onboarding_section_progress : null;
        if (!progress || !progress.total) {
            return null;
        }
        var sections = Array.isArray(progress.sections) ? progress.sections : [];
        var title = sections.map(function (section) {
            return (section.title || section.section_key || 'Section') + ': ' + stepStatusLabel(section.status === 'complete' ? 'complete' : (section.status === 'in_progress' ? 'in-progress' : 'not-started')) + ' (' + (Number(section.percent_complete) || 0) + '%)';
        }).join('\n');
        return {
            label: progress.complete + '/' + progress.total + ' Hospital Setup sections complete' + (progress.in_progress ? ' - ' + progress.in_progress + ' in progress' : ''),
            title: title || 'Hospital Setup section progress'
        };
    }

    function systemHospitals(systemId) {
        return (state.allHospitals || []).filter(function (hospital) {
            return Number(hospital.parent_system_id) === Number(systemId);
        });
    }

    function qualityDirectorCoverage(systemId) {
        var hospitals = systemHospitals(systemId);
        var assigned = hospitals.filter(function (hospital) {
            return !!hospital.primary_quality_director;
        }).length;
        return {
            assigned: assigned,
            missing: Math.max(0, hospitals.length - assigned),
            total: hospitals.length
        };
    }

    function qualityDirectorCoverageCell(system) {
        var coverage = qualityDirectorCoverage(system.id);
        if (!coverage.total) {
            return '<span class="qn-muted-text">No hospitals</span>';
        }
        var complete = coverage.assigned === coverage.total;
        return '<div class="qn-detail-stack qn-qd-coverage-cell">' +
            '<strong>' + escapeHtml(coverage.assigned + ' / ' + coverage.total) + '</strong>' +
            '<span class="qn-status-pill qn-status-' + (complete ? 'active' : 'warning') + '">' + escapeHtml(complete ? 'Covered' : coverage.missing + ' missing') + '</span>' +
            '</div>';
    }

    function renderHealthSystems() {
        var body = document.getElementById('qn-health-systems-table-body');
        if (!body) {
            return;
        }
        var systems = filterHealthSystems();
        if (!state.healthSystems.length) {
            setTableMessage('qn-health-systems-table-body', 6, 'No health systems yet. Create a system or leave hospitals independent.');
            return;
        }
        if (!systems.length) {
            setTableMessage('qn-health-systems-table-body', 6, 'No health systems match your search or filters.');
            return;
        }
        body.innerHTML = systems.map(function (system) {
            return '<tr><td>' + cellPrimary(system.name, system.slug) + '</td>' +
                '<td>' + escapeHtml(text(system.headquarters_state_name)) + '</td>' +
                '<td>' + escapeHtml(text(system.hospital_count)) + '</td>' +
                '<td>' + qualityDirectorCoverageCell(system) + '</td>' +
                '<td>' + statusPill(system.is_active ? 'active' : 'inactive') + '</td>' +
                '<td>' + actionMenu([
                    actionMenuButton('Edit System', 'data-edit-system', system.id, 'edit'),
                    actionMenuButton('View Hospitals', 'data-view-system-hospitals', system.id, 'building'),
                    actionMenuButton('Assign Hospitals', 'data-assign-system-hospitals', system.id, 'plus-alt2'),
                    actionMenuButton('Deactivate', 'data-deactivate-system', system.id, 'trash', 'danger')
                ]) + '</td></tr>';
        }).join('');
    }

    function filterHealthSystems() {
        var query = (state.systemSearch || '').trim().toLowerCase();
        var status = getValue('qn-system-filter-status') || state.systemStatusFilter || '';
        return state.healthSystems.filter(function (system) {
            var activeStatus = system.is_active ? 'active' : 'inactive';
            var haystack = [system.name, system.slug, system.headquarters_state_name, activeStatus].map(function (value) {
                return value ? String(value).toLowerCase() : '';
            }).join(' ');
            return (!query || haystack.indexOf(query) !== -1) && (!status || activeStatus === status);
        });
    }

    function renderAdminUsers() {
        var body = document.getElementById('qn-admin-users-table-body');
        if (!body) {
            return;
        }
        var org = getValue('qn-user-filter-organization');
        var role = getValue('qn-user-filter-role');
        var status = getValue('qn-user-filter-status');
        var query = (state.userSearch || '').trim().toLowerCase();
        var users = state.users.filter(function (user) {
            var matchesSearch = !query || userSearchHaystack(user).indexOf(query) !== -1;
            return (!org || userMatchesOrganization(user, org)) &&
                (!role || user.qualinav_role === role) &&
                (!status || user.qualinav_status === status) &&
                matchesSearch;
        });
        if (!users.length) {
            setTableMessage('qn-admin-users-table-body', 6, 'No users match the current filters.');
            return;
        }
        body.innerHTML = users.map(function (user) {
            var organizationId = userDefaultOrganizationId(user);
            var invite = findPendingInvitationForUser(user, organizationId);
            var actionItems = [];
            var contextAttr = 'data-context="admin"';
            if (invite) {
                actionItems.push(actionMenuButton('Resend Invite', 'data-resend-invite', invite.id, 'update', '', contextAttr));
                actionItems.push(actionMenuButton('Revoke Invite', 'data-revoke-invite', invite.id, 'trash', 'danger', contextAttr));
            }
            if (organizationId && user.qualinav_status !== 'invited') {
                actionItems.push(actionMenuButton('Open Console', 'data-open-console', organizationId, 'dashboard'));
            }
            if (organizationId && user.qualinav_status === 'invited' && !invite) {
                actionItems.push(actionMenuButton('Open Console', 'data-open-console', organizationId, 'dashboard'));
            }
            if (!isCurrentHospitalUser(user)) {
                if (user.qualinav_status === 'active') {
                    actionItems.push(actionMenuButton('Disable User', 'data-update-user-status', user.ID, 'hidden', 'danger', contextAttr + ' data-status="disabled"'));
                    actionItems.push(actionMenuButton('Remove Access', 'data-update-user-status', user.ID, 'trash', 'danger', contextAttr + ' data-status="archived"'));
                } else if (user.qualinav_status === 'disabled') {
                    actionItems.push(actionMenuButton('Reactivate User', 'data-update-user-status', user.ID, 'yes-alt', '', contextAttr + ' data-status="active"'));
                    actionItems.push(actionMenuButton('Remove Access', 'data-update-user-status', user.ID, 'trash', 'danger', contextAttr + ' data-status="archived"'));
                } else if (user.qualinav_status === 'archived') {
                    actionItems.push(actionMenuButton('Reactivate User', 'data-update-user-status', user.ID, 'yes-alt', '', contextAttr + ' data-status="active"'));
                }
            }
            var actions = actionItems.length ? actionMenu(actionItems) : '<span class="qn-muted-text">No actions</span>';
            return '<tr><td>' + userCell(user) + '</td>' +
                '<td>' + userHospitalChips(user) + '</td>' +
                '<td>' + escapeHtml(text(user.state_code || user.state_name)) + '</td>' +
                '<td>' + roleSelect(user.ID, user.qualinav_role, 'admin') + '</td>' +
                '<td>' + statusSelect(user.ID, user.qualinav_status, 'admin') + '</td>' +
                '<td>' + actions + '</td></tr>';
        }).join('');
    }

    function renderHospitalUsers() {
        var body = document.getElementById('qn-hospital-users-table-body');
        if (!body) {
            return;
        }
        renderHospitalUsersOverview();
        var role = getValue('qn-hospital-user-filter-role');
        var status = getValue('qn-hospital-user-filter-status');
        var query = (state.hospitalUserSearch || '').trim().toLowerCase();
        var users = state.users.filter(function (user) {
            return (!role || user.qualinav_role === role) &&
                (!status || user.qualinav_status === status) &&
                (!query || userSearchHaystack(user).indexOf(query) !== -1);
        });
        if (!state.users.length) {
            setTableMessage('qn-hospital-users-table-body', 4, 'No hospital users yet. Invite a user when you are ready.');
            return;
        }
        if (!users.length) {
            setTableMessage('qn-hospital-users-table-body', 4, 'No hospital users match your search or filters.');
            return;
        }
        body.innerHTML = users.map(function (user) {
            var actions = hospitalUserActions(user);
            return '<tr><td>' + userCell(user) + '</td>' +
                '<td>' + roleBadge(user.qualinav_role) + '</td>' +
                '<td>' + statusPill(user.qualinav_status) + '</td>' +
                '<td>' + (actions.length ? actionMenu(actions) : '<span class="qn-muted-text">No actions available</span>') + '</td></tr>';
        }).join('');
    }

    function renderHospitalUsersOverview() {
        var hospital = currentHospitalContext();
        var subtitle = document.getElementById('qn-hospital-users-subtitle');
        var context = document.getElementById('qn-hospital-users-context');
        var summary = document.getElementById('qn-hospital-users-summary');
        var inviteButton = document.getElementById('qn-hospital-invite-user-button');
        var users = state.users || [];
        var invites = state.invitations || [];
        var pendingInvites = invites.filter(function (invite) { return invite.status === 'pending'; });
        var activeUsers = users.filter(function (user) { return user.qualinav_status === 'active'; });
        var disabledUsers = users.filter(function (user) { return user.qualinav_status === 'disabled' || user.qualinav_status === 'archived'; });
        var qualityDirectors = users.filter(function (user) { return user.qualinav_role === 'quality_director'; });
        if (subtitle) {
            subtitle.textContent = 'Manage workspace access and invitations.';
        }
        if (context) {
            context.innerHTML = [
                chip(state.me ? roleLabel(state.me.qualinav_role) : 'Current role'),
                chip(activeUsers.length + ' active users'),
                chip(pendingInvites.length + ' pending invites')
            ].join('');
        }
        if (summary) {
            summary.innerHTML = [
                usersSummaryCard('yes-alt', activeUsers.length, 'Active Users', 'Can access this workspace', 'success'),
                usersSummaryCard('email-alt', pendingInvites.length, 'Pending Invites', 'Awaiting acceptance', 'warning'),
                usersSummaryCard('businessperson', qualityDirectors.length, 'Hospital Quality Directors', 'Primary quality leads', 'info'),
                usersSummaryCard('hidden', disabledUsers.length, 'Disabled Users', 'Disabled or archived', 'danger')
            ].join('');
        }
        if (inviteButton) {
            var canInvite = canInviteHospitalUsers();
            inviteButton.hidden = !canInvite;
            inviteButton.disabled = !canInvite;
            inviteButton.title = canInvite ? 'Invite a user to this hospital workspace' : 'Your role has view-only access to invitations.';
        }
    }

    function usersSummaryCard(icon, count, label, detail, tone) {
        return '<article class="qn-users-summary-card qn-users-summary-' + escapeHtml(tone || 'neutral') + '">' +
            '<span class="dashicons dashicons-' + escapeHtml(icon) + '"></span><div><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(String(count)) + '</strong><small>' + escapeHtml(detail) + '</small></div></article>';
    }

    function currentHospitalContext() {
        if (state.currentHospital) {
            return state.currentHospital;
        }
        if (state.onboarding && state.onboarding.current_organization_name) {
            return {
                organization_id: state.onboarding.current_organization_id,
                id: state.onboarding.current_organization_id,
                organization_name: state.onboarding.current_organization_name,
                name: state.onboarding.current_organization_name,
                parent_system_id: state.onboarding.parent_system_id,
                parent_system_name: state.onboarding.parent_system_name || 'Independent',
                hospital_type: state.onboarding.hospital_type,
                hospital_type_label: state.onboarding.hospital_type_label,
                service_model: state.onboarding.service_model,
                service_model_label: state.onboarding.service_model_label,
                payment_model_label: state.onboarding.payment_model_label,
                state_id: state.onboarding.state_id,
                state_code: state.onboarding.state_code,
                state_name: state.onboarding.state_name,
                onboarding_percent: state.onboarding.progress ? state.onboarding.progress.total_percent : 0
            };
        }
        if (state.myOrganizations && state.myOrganizations.length) {
            var previewOrganizationId = getUrlOrganizationId();
            var currentId = previewOrganizationId ? Number(previewOrganizationId) : (state.me ? Number(state.me.organization_id) : 0);
            return state.myOrganizations.find(function (item) {
                return Number(item.organization_id) === currentId;
            }) || state.myOrganizations[0];
        }
        return null;
    }

    function hospitalAccessCell(user) {
        var hospital = currentHospitalContext();
        var label = hospital ? (hospital.organization_name || hospital.name) : (user.organization_name || user.organization_id || 'Hospital workspace');
        var multiple = user.organizations && user.organizations.length > 1 ? chip('+' + (user.organizations.length - 1), userHospitalSummary(user)) : '';
        return '<div class="qn-chip-row qn-chip-row-compact">' + chip(label, userHospitalSummary(user) || label) + multiple + '</div>';
    }

    function roleBadge(role) {
        return '<span class="qn-role-badge qn-role-' + escapeHtml(text(role).replace(/_/g, '-')) + '">' + escapeHtml(roleLabels[role] || roleLabel(role)) + '</span>';
    }

    function canInviteHospitalUsers() {
        return allowedInviteRoles().length > 0;
    }

    function canManageHospitalUsers() {
        return canInviteHospitalUsers();
    }

    function isCurrentHospitalUser(user) {
        var currentId = state.me && state.me.user_id ? Number(state.me.user_id) : 0;
        var targetId = user && user.ID ? Number(user.ID) : 0;
        if (currentId && targetId && currentId === targetId) {
            return true;
        }
        var currentEmail = state.me && state.me.user_email ? text(state.me.user_email).toLowerCase() : '';
        var targetEmail = user && user.user_email ? text(user.user_email).toLowerCase() : '';
        return !!(currentEmail && targetEmail && currentEmail === targetEmail);
    }

    function activeQualityDirectorCount() {
        return (state.users || []).filter(function (item) {
            return item.qualinav_role === 'quality_director' && item.qualinav_status === 'active';
        }).length;
    }

    function isLastActiveQualityDirector(user) {
        return !!(user &&
            user.qualinav_role === 'quality_director' &&
            user.qualinav_status === 'active' &&
            activeQualityDirectorCount() <= 1);
    }

    function hospitalUserActions(user) {
        if (!canManageHospitalUsers()) {
            return [];
        }
        if (isCurrentHospitalUser(user) || isLastActiveQualityDirector(user)) {
            return [];
        }
        var userId = user.ID;
        var contextAttr = 'data-context="hospital"';
        var items = [];
        var roles = allowedInviteRoles().filter(function (role) {
            return role !== user.qualinav_role;
        });
        if (roles.length) {
            items.push('<span class="qn-action-menu-label">Change role</span>');
            roles.forEach(function (role) {
                items.push(actionMenuButton('Make ' + (roleLabels[role] || roleLabel(role)), 'data-update-user-role', userId, 'admin-users', '', contextAttr + ' data-role="' + escapeHtml(role) + '"'));
            });
        }
        var accountItems = [];
        if (user.qualinav_status === 'active') {
            accountItems.push(actionMenuButton('Disable User', 'data-update-user-status', userId, 'hidden', 'danger', contextAttr + ' data-status="disabled"'));
        }
        if (user.qualinav_status === 'disabled') {
            accountItems.push(actionMenuButton('Reactivate User', 'data-update-user-status', userId, 'yes-alt', '', contextAttr + ' data-status="active"'));
        }
        if (user.qualinav_status !== 'archived') {
            accountItems.push(actionMenuButton('Archive User', 'data-update-user-status', userId, 'trash', 'danger', contextAttr + ' data-status="archived"'));
        }
        var invite = findPendingInvitationForUser(user, userDefaultOrganizationId(user));
        if (invite) {
            accountItems.push(actionMenuButton('Resend Invite', 'data-resend-invite', invite.id, 'update', '', contextAttr));
        }
        if (accountItems.length) {
            items.push('<span class="qn-action-menu-label">Account</span>');
            items = items.concat(accountItems);
        }
        return items;
    }

    function renderInvitations(context) {
        var bodyId = context === 'admin' ? 'qn-admin-invitations-table-body' : 'qn-hospital-invitations-table-body';
        var colspan = context === 'admin' ? 6 : 6;
        var body = document.getElementById(bodyId);
        if (!body) {
            return;
        }
        var invitations = filterInvitations(context);
        var totalRelevant = context === 'hospital' ? (state.invitations || []).filter(function (invite) { return invite.status === 'pending'; }).length : state.invitations.length;
        var tools = context === 'hospital' ? document.getElementById('qn-hospital-invitation-tools') : null;
        if (tools) {
            tools.hidden = !totalRelevant;
        }
        if (!totalRelevant) {
            if (context === 'hospital') {
                body.innerHTML = '<tr><td colspan="' + colspan + '"><div class="qn-empty-state qn-users-empty-state"><span class="dashicons dashicons-email-alt"></span><h3>No pending invitations.</h3><p>Invitations you send will appear here until they are accepted, revoked, or expire.</p></div></td></tr>';
            } else {
                setTableMessage(bodyId, colspan, 'No invitations yet.');
            }
            return;
        }
        if (!invitations.length) {
            setTableMessage(bodyId, colspan, 'No invitations match your search or filters.');
            return;
        }
        body.innerHTML = invitations.map(function (invite) {
            var contextAttr = 'data-context="' + escapeHtml(context) + '"';
            var canManage = context === 'admin' || canManageHospitalUsers();
            var actionItems = [];
            if (canManage && isInvitationResendable(invite)) {
                actionItems.push(actionMenuButton('Resend', 'data-resend-invite', invite.id, 'update', '', contextAttr));
            }
            if (canManage && isInvitationRevokable(invite)) {
                actionItems.push(actionMenuButton('Revoke', 'data-revoke-invite', invite.id, 'trash', 'danger', contextAttr));
            }
            var actions = actionItems.length ? actionMenu(actionItems) : '<span class="qn-muted-text">No actions</span>';
            return '<tr><td>' + cellPrimary(invite.full_name || invite.email, invite.email) + '</td>' +
                '<td>' + roleBadge(invite.qualinav_role) + '</td>' +
                (context === 'hospital' ? '<td>' + statusPill(invite.email_failed ? 'email failed' : 'email sent') + '</td>' : '') +
                '<td>' + statusPill(invite.status) + '</td>' +
                '<td>' + escapeHtml(text(invite.expires_at)) + '</td>' +
                (context === 'admin' ? '<td>' + escapeHtml(text(invite.invited_by_name || invite.invited_by)) + '</td>' : '') +
                '<td>' + actions + '</td></tr>';
        }).join('');
    }

    function isInvitationResendable(invite) {
        return invite && invite.status !== 'accepted' && invite.status !== 'revoked';
    }

    function isInvitationRevokable(invite) {
        return invite && invite.status !== 'accepted' && invite.status !== 'revoked';
    }

    function filterInvitations(context) {
        var prefix = context === 'admin' ? 'qn-admin-invitation' : 'qn-hospital-invitation';
        var role = getValue(prefix + '-filter-role');
        var status = getValue(prefix + '-filter-status');
        var query = (context === 'admin' ? state.adminInvitationSearch : state.hospitalInvitationSearch || '').trim().toLowerCase();
        return state.invitations.filter(function (invite) {
            if (context === 'hospital' && invite.status !== 'pending') {
                return false;
            }
            var haystack = [
                invite.full_name,
                invite.email,
                invite.organization_name,
                invite.qualinav_role,
                roleLabels[invite.qualinav_role],
                invite.status,
                invite.invited_by_name,
                invite.expires_at
            ].map(function (value) {
                return value ? String(value).toLowerCase() : '';
            }).join(' ');
            return (!role || invite.qualinav_role === role) &&
                (!status || invite.status === status) &&
                (!query || haystack.indexOf(query) !== -1);
        });
    }

    function userSearchHaystack(user) {
        return [
            user.display_name,
            user.user_email,
            user.organization_name,
            userHospitalSummary(user),
            user.state_code,
            user.state_name,
            user.qualinav_role,
            roleLabels[user.qualinav_role],
            user.qualinav_status,
            user.last_login
        ].map(function (value) {
            return value ? String(value).toLowerCase() : '';
        }).join(' ');
    }

    function userMatchesOrganization(user, organizationId) {
        if (Number(user.organization_id) === Number(organizationId)) {
            return true;
        }
        return !!(user.organizations || []).find(function (organization) {
            return Number(organization.organization_id) === Number(organizationId);
        });
    }

    function userDefaultOrganizationId(user) {
        if (user.organization_id) {
            return user.organization_id;
        }
        if (user.organizations && user.organizations.length) {
            var active = user.organizations.find(function (organization) {
                return !organization.status || organization.status === 'active';
            });
            return (active || user.organizations[0]).organization_id;
        }
        return '';
    }

    function findPendingInvitationForUser(user, organizationId) {
        var userEmail = text(user.user_email).toLowerCase();
        return (state.invitations || []).find(function (invite) {
            var sameUser = invite.user_id && user.ID && Number(invite.user_id) === Number(user.ID);
            var sameEmail = userEmail && text(invite.email).toLowerCase() === userEmail;
            var sameOrganization = !organizationId || !invite.organization_id || Number(invite.organization_id) === Number(organizationId);
            return invite.status === 'pending' && sameOrganization && (sameUser || sameEmail);
        });
    }

    function openHospitalConsole(organizationId) {
        if (!organizationId) {
            showToast('This user is not assigned to a hospital yet.', 'warning');
            return;
        }
        window.location.href = (config.homeUrl || '/') + 'qualinav?organization_id=' + encodeURIComponent(organizationId) + '#dashboard';
    }

    function togglePasswordVisibility(button) {
        var field = button.closest('.qn-password-field');
        var input = field ? field.querySelector('input') : null;
        var icon = button.querySelector('.dashicons');
        if (!input) {
            return;
        }
        var willShow = input.type === 'password';
        input.type = willShow ? 'text' : 'password';
        button.setAttribute('aria-pressed', willShow ? 'true' : 'false');
        button.setAttribute('aria-label', willShow ? 'Hide password' : 'Show password');
        if (icon) {
            icon.classList.toggle('dashicons-visibility', !willShow);
            icon.classList.toggle('dashicons-hidden', willShow);
        }
    }

    function userHospitalChips(user) {
        if (user.organizations && user.organizations.length) {
            var visible = user.organizations.slice(0, 1).map(function (item) {
                return chip(item.organization_name || ('Organization ' + item.organization_id), userHospitalSummary(user));
            }).join('');
            return '<div class="qn-chip-row qn-chip-row-compact">' + visible + (user.organizations.length > 1 ? chip('+' + (user.organizations.length - 1), userHospitalSummary(user)) : '') + '</div>';
        }
        return '<div class="qn-chip-row qn-chip-row-compact">' + chip(user.organization_name || user.organization_id) + '</div>';
    }

    function userHospitalSummary(user) {
        if (user.organizations && user.organizations.length) {
            return user.organizations.map(function (item) {
                return item.organization_name || ('Organization ' + item.organization_id);
            }).join(', ') + ' (' + user.organizations.length + ')';
        }

        return text(user.organization_name || user.organization_id);
    }

    function renderAdminFilters() {
        var org = document.getElementById('qn-user-filter-organization');
        var role = document.getElementById('qn-user-filter-role');
        var status = document.getElementById('qn-user-filter-status');
        if (org) {
            org.innerHTML = '<option value="">All hospitals</option>' + (state.allHospitals || state.hospitals).map(function (hospital) {
                return '<option value="' + hospital.id + '">' + escapeHtml(hospital.name) + '</option>';
            }).join('');
            syncSearchableSelect(org);
        }
        if (role) {
            role.innerHTML = '<option value="">All roles</option>' + Object.keys(roleLabels).map(function (key) {
                return '<option value="' + key + '">' + escapeHtml(roleLabels[key]) + '</option>';
            }).join('');
        }
        if (status) {
            status.innerHTML = '<option value="">All statuses</option>' + statuses.map(function (item) {
                return '<option value="' + item + '">' + item + '</option>';
            }).join('');
        }
        populateRoleFilter('qn-admin-invitation-filter-role');
        populateStatusFilter('qn-admin-invitation-filter-status', invitationStatuses());
    }

    function renderHospitalFilters() {
        populateRoleFilter('qn-hospital-user-filter-role');
        populateStatusFilter('qn-hospital-user-filter-status', statuses);
        populateRoleFilter('qn-hospital-invitation-filter-role');
        populateStatusFilter('qn-hospital-invitation-filter-status', invitationStatuses());
    }

    function populateRoleFilter(id) {
        var select = document.getElementById(id);
        if (!select) {
            return;
        }
        var current = select.value;
        select.innerHTML = '<option value="">All roles</option>' + Object.keys(roleLabels).map(function (key) {
            return '<option value="' + key + '"' + (current === key ? ' selected' : '') + '>' + escapeHtml(roleLabels[key]) + '</option>';
        }).join('');
    }

    function populateStatusFilter(id, values) {
        var select = document.getElementById(id);
        if (!select) {
            return;
        }
        var current = select.value;
        select.innerHTML = '<option value="">All statuses</option>' + values.map(function (item) {
            return '<option value="' + item + '"' + (current === item ? ' selected' : '') + '>' + escapeHtml(item) + '</option>';
        }).join('');
    }

    function invitationStatuses() {
        var map = {};
        (state.invitations || []).forEach(function (invite) {
            if (invite.status) {
                map[invite.status] = true;
            }
        });
        ['pending', 'accepted', 'revoked', 'expired'].forEach(function (item) {
            map[item] = true;
        });
        return Object.keys(map);
    }

    function roleSelect(userId, selected, context) {
        var roles = context === 'admin' ? Object.keys(roleLabels) : allowedInviteRoles();
        return '<select class="qn-inline-select" data-user-role="' + userId + '" data-context="' + context + '" data-original-value="' + escapeHtml(selected || '') + '">' + roles.map(function (role) {
            return '<option value="' + role + '"' + (role === selected ? ' selected' : '') + '>' + escapeHtml(roleLabels[role] || role) + '</option>';
        }).join('') + '</select>';
    }

    function statusSelect(userId, selected, context) {
        return '<select class="qn-inline-select" data-user-status="' + userId + '" data-context="' + context + '" data-original-value="' + escapeHtml(selected || '') + '">' + statuses.map(function (status) {
            return '<option value="' + status + '"' + (status === selected ? ' selected' : '') + '>' + status + '</option>';
        }).join('') + '</select>';
    }

    function statusPill(status) {
        var className = text(status).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        return '<span class="qn-status-pill qn-status-' + escapeHtml(className) + '">' + escapeHtml(text(status)) + '</span>';
    }

    function renderStateOptions(selectedId) {
        var select = document.getElementById('qn-hospital-state');
        if (!select) {
            return;
        }
        select.innerHTML = '<option value="">Select state</option>' + state.states.map(function (item) {
            return '<option value="' + item.id + '"' + (selectedId && Number(selectedId) === Number(item.id) ? ' selected' : '') + '>' + escapeHtml(item.name) + '</option>';
        }).join('');
        syncSearchableSelect(select);

        var systemState = document.getElementById('qn-system-state');
        if (systemState) {
            systemState.innerHTML = '<option value="">Select state</option>' + state.states.map(function (item) {
                return '<option value="' + item.id + '">' + escapeHtml(item.name) + '</option>';
            }).join('');
            syncSearchableSelect(systemState);
        }
    }

    function renderSystemOptions(selectedId) {
        var select = document.getElementById('qn-hospital-system');
        if (!select) {
            return;
        }
        select.innerHTML = '<option value="">Independent / no system</option>' + state.healthSystems.map(function (system) {
            return '<option value="' + system.id + '"' + (selectedId && Number(selectedId) === Number(system.id) ? ' selected' : '') + '>' + escapeHtml(system.name) + '</option>';
        }).join('');
        syncSearchableSelect(select);
    }

    function renderClassificationOptions(hospitalType, serviceModel, paymentModel) {
        var type = document.getElementById('qn-hospital-type');
        var service = document.getElementById('qn-hospital-service-model');
        var payment = document.getElementById('qn-hospital-payment-model');
        if (type) {
            type.innerHTML = '<option value="">Not specified</option>' + state.hospitalTypes.map(function (item) {
                return '<option value="' + item.value + '"' + (hospitalType === item.value ? ' selected' : '') + '>' + escapeHtml(item.label) + '</option>';
            }).join('');
        }
        if (service) {
            service.innerHTML = '<option value="">Not specified</option>' + state.serviceModels.map(function (item) {
                return '<option value="' + item.value + '"' + (serviceModel === item.value ? ' selected' : '') + '>' + escapeHtml(item.label) + '</option>';
            }).join('');
        }
        if (payment) {
            var paymentOptions = [
                {value: '', label: 'Not specified'},
                {value: 'cah', label: 'Critical Access Hospital'},
                {value: 'pps', label: 'Prospective Payment System'},
                {value: 'other', label: 'Other'},
                {value: 'unknown', label: 'Unknown'}
            ];
            payment.innerHTML = paymentOptions.map(function (item) {
                return '<option value="' + item.value + '"' + (paymentModel === item.value ? ' selected' : '') + '>' + escapeHtml(item.label) + '</option>';
            }).join('');
        }
    }

    function openHospitalModal(hospital) {
        state.editingHospital = hospital || null;
        var modal = document.getElementById('qn-hospital-modal');
        if (!modal) {
            return;
        }
        setText('#qn-hospital-modal-title', hospital ? 'Edit Hospital' : 'Create Hospital');
        setField('qn-hospital-id', hospital ? hospital.id : '');
        setField('qn-hospital-name', hospital ? hospital.name : '');
        setField('qn-hospital-city', hospital ? hospital.city : '');
        setField('qn-hospital-zip', hospital ? hospital.zip : '');
        setField('qn-hospital-beds', hospital ? (hospital.beds || hospital.licensed_beds || '') : '');
        setField('qn-hospital-status', hospital ? hospital.status : 'active');
        setField('qn-hospital-timezone', hospital ? hospital.timezone : '');
        setField('qn-hospital-ccn', hospital ? hospital.ccn : '');
        renderStateOptions(hospital ? hospital.state_id : null);
        renderSystemOptions(hospital ? hospital.parent_system_id : (state.hospitalFilter ? state.hospitalFilter.id : null));
        renderClassificationOptions(hospital ? hospital.hospital_type : '', hospital ? hospital.service_model : '', hospital ? hospital.payment_model : '');
        modal.hidden = false;
    }

    function closeHospitalModal() {
        var modal = document.getElementById('qn-hospital-modal');
        if (modal) {
            modal.hidden = true;
        }
    }

    function saveHospital(event) {
        event.preventDefault();
        var form = event.currentTarget;
        var restoreButton = setButtonLoading(form.querySelector('[type="submit"]'), 'Saving...');
        var id = form.querySelector('[name="id"]').value;
        var activeFilter = state.hospitalFilter ? {
            id: state.hospitalFilter.id,
            name: state.hospitalFilter.name
        } : null;
        var payload = {
            organization_name: form.querySelector('[name="organization_name"]').value,
            city: form.querySelector('[name="city"]').value,
            zip: form.querySelector('[name="zip"]').value,
            beds: form.querySelector('[name="beds"]').value,
            state_id: form.querySelector('[name="state_id"]').value,
            status: form.querySelector('[name="status"]').value,
            timezone: form.querySelector('[name="timezone"]').value,
            ccn: form.querySelector('[name="ccn"]').value,
            parent_system_id: form.querySelector('[name="parent_system_id"]').value,
            hospital_type: form.querySelector('[name="hospital_type"]').value,
            service_model: form.querySelector('[name="service_model"]').value,
            payment_model: form.querySelector('[name="payment_model"]').value
        };
        setText('#qn-hospital-form-message', 'Saving...');
        api(id ? '/admin/hospitals/' + id : '/admin/hospitals', {method: id ? 'PUT' : 'POST', body: payload}).then(function () {
            closeHospitalModal();
            return Promise.all([api('/admin/dashboard'), api('/admin/hospitals')]);
        }).then(function (results) {
            renderMetrics(results[0]);
            state.allHospitals = results[1] || [];
            state.hospitalFilter = activeFilter;
            state.hospitals = activeFilter ? state.allHospitals.filter(function (hospital) {
                return Number(hospital.parent_system_id) === Number(activeFilter.id);
            }) : state.allHospitals.slice();
            resetHospitalPagination();
            renderHospitals();
            renderAdminFilters();
            return api('/admin/health-systems').then(function (systems) {
                state.healthSystems = systems || [];
                renderHealthSystems();
                renderSystemOptions();
            });
        }).catch(function (error) {
            setText('#qn-hospital-form-message', error.message);
        }).finally(function () {
            restoreButton();
        });
    }

    function openSystemModal(system) {
        var modal = document.getElementById('qn-system-modal');
        if (!modal) {
            return;
        }
        setText('#qn-system-modal-title', system ? 'Edit System' : 'Create System');
        setField('qn-system-id', system ? system.id : '');
        setField('qn-system-name', system ? system.name : '');
        setField('qn-system-description', system ? system.description : '');
        setField('qn-system-active', system && !system.is_active ? '0' : '1');
        renderStateOptions();
        setField('qn-system-state', system ? system.headquarters_state_id : '');
        setText('#qn-system-form-message', '');
        modal.hidden = false;
    }

    function closeSystemModal() {
        var modal = document.getElementById('qn-system-modal');
        if (modal) {
            modal.hidden = true;
        }
    }

    function openSystemHospitalsModal(systemId) {
        closeActionMenus();
        var system = findSystem(systemId);
        var modal = document.getElementById('qn-system-hospitals-modal');
        if (!modal || !system) {
            showToast('Select a health system before assigning hospitals.', 'warning');
            return;
        }
        state.systemHospitalAssignmentSystem = system;
        state.systemHospitalAssignmentSearch = '';
        state.systemHospitalAssignmentSelection = {};
        (state.allHospitals || []).forEach(function (hospital) {
            state.systemHospitalAssignmentSelection[hospital.id] = Number(hospital.parent_system_id) === Number(system.id);
        });
        setField('qn-system-hospitals-system-id', system.id);
        setField('qn-system-hospitals-search', '');
        setText('#qn-system-hospitals-modal-title', 'Assign hospitals to ' + (system.name || 'health system'));
        setText('#qn-system-hospitals-message', '');
        renderSystemHospitalsAssignmentList();
        modal.hidden = false;
    }

    function closeSystemHospitalsModal() {
        var modal = document.getElementById('qn-system-hospitals-modal');
        if (modal) {
            modal.hidden = true;
        }
    }

    function systemHospitalAssignmentHaystack(hospital) {
        return [
            hospital.name,
            hospital.city,
            hospital.state_name,
            hospital.state_code,
            hospital.parent_system_name,
            hospital.hospital_type_label,
            hospital.service_model_label,
            hospital.status
        ].map(function (value) {
            return value ? String(value).toLowerCase() : '';
        }).join(' ');
    }

    function renderSystemHospitalsAssignmentList() {
        var list = document.getElementById('qn-system-hospitals-list');
        var count = document.getElementById('qn-system-hospitals-selected-count');
        var system = state.systemHospitalAssignmentSystem;
        if (!list || !system) {
            return;
        }
        var hospitals = state.allHospitals || [];
        var query = (state.systemHospitalAssignmentSearch || '').trim().toLowerCase();
        var visible = query ? hospitals.filter(function (hospital) {
            return systemHospitalAssignmentHaystack(hospital).indexOf(query) !== -1;
        }) : hospitals.slice();
        var selectedCount = Object.keys(state.systemHospitalAssignmentSelection || {}).filter(function (id) {
            return !!state.systemHospitalAssignmentSelection[id];
        }).length;
        if (count) {
            count.textContent = selectedCount + ' selected';
        }
        if (!hospitals.length) {
            list.innerHTML = '<div class="qn-empty-state"><span class="dashicons dashicons-building"></span><h3>No hospitals available.</h3><p>Create hospital records first, then assign them to this health system.</p></div>';
            return;
        }
        if (!visible.length) {
            list.innerHTML = '<div class="qn-empty-state"><span class="dashicons dashicons-search"></span><h3>No hospitals match this search.</h3><p>Clear the search to review all available hospital records.</p></div>';
            return;
        }
        list.innerHTML = visible.map(function (hospital) {
            var assigned = !!state.systemHospitalAssignmentSelection[hospital.id];
            var currentSystem = hospital.parent_system_name || 'Independent';
            var cityState = [hospital.city, hospital.state_name || hospital.state_code].filter(Boolean).join(', ');
            var alreadyInSystem = Number(hospital.parent_system_id) === Number(system.id);
            return '<label class="qn-system-hospital-option' + (assigned ? ' qn-system-hospital-option-assigned' : '') + '">' +
                '<span class="qn-system-hospital-check"><input type="checkbox" data-system-hospital-assignment="' + escapeHtml(hospital.id) + '"' + (assigned ? ' checked' : '') + '></span>' +
                '<span class="qn-system-hospital-name"><strong>' + escapeHtml(hospital.name || 'Unnamed hospital') + '</strong></span>' +
                '<span class="qn-system-hospital-location">' + escapeHtml(cityState || 'Location not set') + '</span>' +
                '<span class="qn-system-hospital-current"><span>Current system</span><b>' + escapeHtml(currentSystem) + '</b>' + (alreadyInSystem ? '<em>Assigned</em>' : '') + '</span>' +
                '</label>';
        }).join('');
    }

    function saveSystemHospitals(event) {
        event.preventDefault();
        var form = event.currentTarget;
        var system = state.systemHospitalAssignmentSystem;
        if (!system) {
            setText('#qn-system-hospitals-message', 'Select a health system before saving assignments.');
            return;
        }
        var restoreButton = setButtonLoading(form.querySelector('[type="submit"]'), 'Saving...');
        var selection = state.systemHospitalAssignmentSelection || {};
        var updates = (state.allHospitals || []).filter(function (hospital) {
            var selected = !!selection[hospital.id];
            var currentlyAssigned = Number(hospital.parent_system_id) === Number(system.id);
            return selected !== currentlyAssigned;
        }).map(function (hospital) {
            return api('/admin/hospitals/' + hospital.id, {
                method: 'PUT',
                body: {
                    parent_system_id: selection[hospital.id] ? system.id : ''
                }
            });
        });
        if (!updates.length) {
            setText('#qn-system-hospitals-message', 'No assignment changes to save.');
            restoreButton();
            return;
        }
        setText('#qn-system-hospitals-message', 'Saving hospital assignments...');
        Promise.all(updates).then(function () {
            return Promise.all([api('/admin/health-systems'), api('/admin/hospitals'), api('/admin/dashboard')]);
        }).then(function (results) {
            state.healthSystems = results[0] || [];
            state.allHospitals = results[1] || [];
            renderMetrics(results[2]);
            var refreshed = findSystem(system.id) || system;
            state.hospitalFilter = {
                id: Number(system.id),
                name: refreshed.name || system.name || 'Selected health system'
            };
            state.hospitals = state.allHospitals.filter(function (hospital) {
                return Number(hospital.parent_system_id) === Number(system.id);
            });
            resetHospitalPagination();
            renderHealthSystems();
            renderSystemOptions();
            renderHospitals();
            closeSystemHospitalsModal();
            activateSection('hospitals', true);
            showToast('Hospital assignments updated for ' + state.hospitalFilter.name + '.', 'success');
        }).catch(function (error) {
            setText('#qn-system-hospitals-message', error.message || 'Unable to save hospital assignments.');
        }).finally(function () {
            restoreButton();
        });
    }

    function saveSystem(event) {
        event.preventDefault();
        var form = event.currentTarget;
        var restoreButton = setButtonLoading(form.querySelector('[type="submit"]'), 'Saving...');
        var id = form.querySelector('[name="id"]').value;
        var payload = {
            name: form.querySelector('[name="name"]').value,
            headquarters_state_id: form.querySelector('[name="headquarters_state_id"]').value,
            description: form.querySelector('[name="description"]').value,
            is_active: form.querySelector('[name="is_active"]').value
        };
        setText('#qn-system-form-message', 'Saving...');
        api(id ? '/admin/health-systems/' + id : '/admin/health-systems', {method: id ? 'PUT' : 'POST', body: payload}).then(function () {
            closeSystemModal();
            return Promise.all([api('/admin/health-systems'), api('/admin/hospitals')]);
        }).then(function (results) {
            state.healthSystems = results[0] || [];
            state.hospitals = results[1] || [];
            state.allHospitals = state.hospitals.slice();
            state.hospitalFilter = null;
            resetHospitalPagination();
            renderHealthSystems();
            renderSystemOptions();
            renderHospitals();
        }).catch(function (error) {
            setText('#qn-system-form-message', error.message);
        }).finally(function () {
            restoreButton();
        });
    }

    function inviteHospitalList() {
        var hospitals = state.allHospitals && state.allHospitals.length ? state.allHospitals : (state.hospitals || []);
        return hospitals.slice().sort(function (a, b) {
            var stateA = inviteHospitalStateLabel(a).toLowerCase();
            var stateB = inviteHospitalStateLabel(b).toLowerCase();
            var nameA = text(a.name).toLowerCase();
            var nameB = text(b.name).toLowerCase();
            if (stateA !== stateB) {
                return stateA < stateB ? -1 : 1;
            }
            return nameA < nameB ? -1 : (nameA > nameB ? 1 : 0);
        });
    }

    function inviteHospitalStateValue(hospital) {
        return text(hospital.state_id || hospital.state_code || hospital.state_name || 'unknown');
    }

    function inviteHospitalStateLabel(hospital) {
        var value = inviteHospitalStateValue(hospital);
        var matchedState = (state.states || []).find(function (item) {
            return text(item.id) === value || text(item.code) === value || text(item.name) === value;
        });
        return matchedState ? text(matchedState.code || matchedState.name) : text(hospital.state_code || hospital.state_name || 'State not set');
    }

    function inviteStateOptions(hospitals) {
        var seen = {};
        hospitals.forEach(function (hospital) {
            var value = inviteHospitalStateValue(hospital);
            if (!seen[value]) {
                seen[value] = inviteHospitalStateLabel(hospital);
            }
        });
        return Object.keys(seen).map(function (value) {
            return {value: value, label: seen[value]};
        }).sort(function (a, b) {
            return a.label.toLowerCase() < b.label.toLowerCase() ? -1 : 1;
        });
    }

    function renderInviteStateOptions(selectedValue) {
        var select = document.getElementById('qn-invite-state');
        if (!select) {
            return;
        }
        var hospitals = inviteHospitalList();
        var selected = text(selectedValue || select.value);
        select.innerHTML = '<option value="">All states</option>' + inviteStateOptions(hospitals).map(function (item) {
            return '<option value="' + escapeHtml(item.value) + '"' + (selected === item.value ? ' selected' : '') + '>' + escapeHtml(item.label) + '</option>';
        }).join('');
        syncSearchableSelect(select);
    }

    function renderInviteHospitalOptions(selectedId) {
        var select = document.getElementById('qn-invite-organization');
        if (!select) {
            return;
        }
        var selectedState = text(getValue('qn-invite-state'));
        var selected = text(selectedId || select.value);
        var hospitals = inviteHospitalList().filter(function (hospital) {
            return !selectedState || inviteHospitalStateValue(hospital) === selectedState;
        });
        if (!hospitals.length) {
            select.innerHTML = '<option value="">No hospitals available for this state</option>';
            select.disabled = true;
            syncSearchableSelect(select);
            return;
        }
        select.disabled = false;
        select.innerHTML = hospitals.map(function (hospital) {
            var stateLabel = inviteHospitalStateLabel(hospital);
            var label = selectedState ? text(hospital.name) : text(hospital.name) + ' - ' + stateLabel;
            return '<option value="' + escapeHtml(hospital.id) + '"' + (selected && Number(selected) === Number(hospital.id) ? ' selected' : '') + '>' + escapeHtml(label) + '</option>';
        }).join('');
        if (selected && !select.value) {
            select.selectedIndex = 0;
        }
        syncSearchableSelect(select);
    }

    function openInviteModal(options) {
        options = options || {};
        state.inviteContext = options.context || (document.body.classList.contains('qn-admin-console-page') ? 'admin' : 'hospital');
        state.fixedInviteRole = options.role || null;
        state.fixedInviteOrganization = options.organizationId || null;
        var modal = document.getElementById('qn-invite-modal');
        var form = document.getElementById('qn-invite-form');
        var inviteState = document.getElementById('qn-invite-state');
        var org = document.getElementById('qn-invite-organization');
        var role = document.getElementById('qn-invite-role');
        var workspace = document.getElementById('qn-invite-workspace-name');
        var stateField = document.getElementById('qn-invite-state-field');
        var orgField = document.getElementById('qn-invite-organization-field');
        var roleField = document.getElementById('qn-invite-role-field');
        var fixedContext = document.getElementById('qn-invite-fixed-context');
        var fixedHospitalRow = document.getElementById('qn-invite-fixed-hospital-row');
        var fixedHospital = document.getElementById('qn-invite-fixed-hospital');
        var fixedRoleRow = document.getElementById('qn-invite-fixed-role-row');
        var fixedRole = document.getElementById('qn-invite-fixed-role');
        if (!modal || !form || !role) {
            return;
        }
        form.reset();
        setText('#qn-invite-form-message', '');
        setText('#qn-invite-modal-title', state.inviteContext === 'admin' ? 'Invite User' : 'Invite hospital user');
        setText('#qn-invite-modal-helper', state.inviteContext === 'admin' ? 'Send a secure invitation to a selected workspace.' : 'Send a secure invitation to this hospital workspace.');
        if (workspace) {
            var hospital = currentHospitalContext();
            workspace.textContent = hospital ? (hospital.organization_name || hospital.name || 'Current hospital') : 'Current hospital';
        }
        if (inviteState) {
            renderInviteStateOptions('');
        }
        if (org) {
            renderInviteHospitalOptions(state.fixedInviteOrganization || '');
            org.disabled = !!state.fixedInviteOrganization;
        }
        var selectedHospital = state.fixedInviteOrganization ? findHospital(state.fixedInviteOrganization) : null;
        if (fixedContext) {
            fixedContext.hidden = !(state.fixedInviteOrganization || state.fixedInviteRole);
        }
        if (fixedHospitalRow) {
            fixedHospitalRow.hidden = !state.fixedInviteOrganization;
        }
        if (fixedHospital) {
            fixedHospital.textContent = selectedHospital ? (selectedHospital.name || 'Selected hospital') : 'Selected hospital';
        }
        if (stateField) {
            stateField.hidden = !!state.fixedInviteOrganization;
        }
        if (orgField) {
            orgField.hidden = !!state.fixedInviteOrganization;
        }
        var roles = state.fixedInviteRole ? [state.fixedInviteRole] : allowedInviteRoles();
        role.innerHTML = roles.map(function (item) {
            return '<option value="' + item + '">' + escapeHtml(roleLabels[item] || item) + ' - ' + escapeHtml(roleDescriptions[item] || 'Workspace access role') + '</option>';
        }).join('');
        role.disabled = !!state.fixedInviteRole;
        if (fixedRoleRow) {
            fixedRoleRow.hidden = !state.fixedInviteRole;
        }
        if (fixedRole) {
            fixedRole.textContent = state.fixedInviteRole ? (roleLabels[state.fixedInviteRole] || roleLabel(state.fixedInviteRole)) : 'Selected role';
        }
        if (roleField) {
            roleField.hidden = !!state.fixedInviteRole;
        }
        updateInviteRoleDescription();
        modal.hidden = false;
    }

    function updateInviteRoleDescription() {
        var role = document.getElementById('qn-invite-role');
        var description = document.getElementById('qn-invite-role-description');
        if (role && description) {
            description.textContent = roleDescriptions[role.value] || 'Select the access level this user should have in the workspace.';
        }
    }

    function loadOnboarding(organizationId, options) {
        options = options || {};
        var showLoading = !!options.showLoading;
        var path = '/onboarding';
        if (organizationId) {
            path += '?organization_id=' + encodeURIComponent(organizationId);
        }
        setOnboardingPanelLoading(true);
        if (showLoading) {
            setWorkspaceLoading(true, 'Loading Hospital Setup...');
        }
        return api(path).then(function (payload) {
            state.onboarding = payload;
            state.onboardingOrganizationId = payload.current_organization_id;
            state.scoutOnboardingSubmitted = !!payload.onboarding_submitted || payload.onboarding_status === 'submitted' || state.scoutOnboardingSubmitted;
            try {
                renderOnboarding();
            } catch (error) {
                renderOnboardingLoadError(error);
                throw error;
            }
            renderHospitalContext(dashboardHospital());
            renderHospitalUsersOverview();
            renderHospitalDataSections();
            maybeShowWorkspaceWelcome();
        }).catch(function (error) {
            renderOnboardingLoadError(error);
        }).finally(function () {
            setOnboardingPanelLoading(false);
            if (showLoading) {
                setWorkspaceLoading(false);
            }
        });
    }

    function loadScoutRuns(organizationId, options) {
        options = options || {};
        var path = '/scout/runs';
        if (organizationId) {
            path += '?organization_id=' + encodeURIComponent(organizationId);
        }
        return api(path).then(function (payload) {
            state.scoutRuns = payload.runs || [];
            state.latestScoutRun = payload.latest_run || null;
            state.scoutBridgeAvailable = !!payload.bridge_available;
            state.scoutCanGenerate = !!payload.can_generate;
            state.scoutOnboardingSubmitted = !!payload.onboarding_submitted || payload.onboarding_status === 'submitted' || !!state.latestScoutRun;
            renderHospitalDataSections();
            return options.skipAutoGenerate ? false : maybeAutoGenerateScoutPreview();
        }).catch(function (error) {
            var body = document.getElementById('qn-scout-preview-body');
            if (body) {
                body.innerHTML = emptyState('warning', 'Scout preview unavailable', error.message);
            }
            renderHospitalDataSections();
        });
    }

    function renderScoutPreview() {
        var body = document.getElementById('qn-scout-preview-body');
        var generate = document.getElementById('qn-scout-generate-button');
        var readOnly = isReadOnlyWorkspaceRole();
        if (!body) {
            return;
        }
        body.classList.toggle('qn-readonly-module', readOnly);
        renderScoutPageChrome();
        if (generate) {
            generate.hidden = true;
            generate.disabled = readOnly || !state.scoutCanGenerate || state.scoutGenerationInFlight;
            generate.innerHTML = state.scoutGenerationInFlight
                ? '<span class="dashicons dashicons-update"></span>Generating...'
                : '<span class="dashicons dashicons-lightbulb"></span>Generate Preview';
        }

        if (state.scoutGenerationInFlight) {
            body.innerHTML = renderScoutEmptyState(
                'update',
                'Scout is building your hospital preview',
                'Your Hospital Setup has been saved. Scout is now creating the reporting schedule, committee flow, monitoring work, and priority queue automatically.',
                '',
                ''
            );
            if (generate && !readOnly) {
                generate.hidden = false;
            }
            return;
        }

        if (!state.scoutOnboardingSubmitted && !state.latestScoutRun) {
            body.innerHTML = renderScoutEmptyState(
                'clipboard',
                readOnly ? 'Scout Preview not ready yet' : 'Complete Hospital Setup first',
                readOnly ? 'This hospital workspace does not have a Scout setup preview ready for review yet.' : 'Scout needs your hospital profile, services, meeting cadence, and plans and policies before it can generate your setup preview. Measures, deadlines, and reporting status remain in Data Hub.',
                readOnly ? '' : 'Go to Hospital Setup',
                readOnly ? '' : 'day-0-setup'
            );
            return;
        }

        if (!state.latestScoutRun && !state.scoutBridgeAvailable) {
            var adminMessage = 'GrapevineAI Scout bridge is unavailable. Check the GrapevineAI plugin configuration.';
            var hospitalMessage = 'Scout Preview is temporarily unavailable. Contact QualiNav support.';
            body.innerHTML = renderScoutUnavailable(isGlobalAdmin() ? adminMessage : hospitalMessage);
            return;
        }

        if (!state.latestScoutRun) {
            body.innerHTML = renderScoutEmptyState(
                'lightbulb',
                readOnly ? 'Scout Preview not generated yet' : 'Ready to generate Scout Preview',
                readOnly ? 'Authorized workspace users can generate the draft operating-system preview from Hospital Setup answers.' : 'Scout will create a draft reporting schedule, meeting flow map, survey readiness timeline, monitoring tasks, and priority queue.',
                readOnly ? '' : 'Generate Scout Preview',
                ''
            );
            if (generate && !readOnly && state.scoutCanGenerate) {
                generate.hidden = false;
            }
            return;
        }

        if (state.latestScoutRun.status === 'running' || state.latestScoutRun.status === 'pending') {
            body.innerHTML = renderScoutGenerating(state.latestScoutRun);
            return;
        }

        if (state.latestScoutRun.status === 'failed') {
            body.innerHTML = renderScoutFailed(state.latestScoutRun);
            return;
        }

        body.innerHTML = renderScoutCompleted(state.latestScoutRun);
    }

    function renderScoutPageChrome() {
        var hospital = dashboardHospital() || {};
        var context = state.latestScoutRun && state.latestScoutRun.persona_context ? state.latestScoutRun.persona_context : {};
        var banner = document.getElementById('qn-scout-admin-banner');
        var bannerText = document.getElementById('qn-scout-admin-preview-text');
        var previewOrganizationId = getUrlOrganizationId();
        if (banner) {
            banner.hidden = !(isGlobalAdmin() && previewOrganizationId);
        }
        if (bannerText) {
            bannerText.textContent = 'viewing Scout Preview for ' + (hospital.organization_name || hospital.name || 'this hospital') + '.';
        }
        var chips = document.getElementById('qn-scout-context-chips');
        if (!chips) {
            return;
        }
        chips.innerHTML = [
            scoutContextChip('Hospital type', scoutKnownValue(hospital.hospital_type_label) || scoutKnownValue(context.hospital_category) || scoutHospitalTypeFromPayment(hospital.payment_model_label || context.payment_model)),
            scoutContextChip('Service model', scoutKnownValue(hospital.service_model_label)),
            scoutContextChip('Payment model', scoutKnownValue(hospital.payment_model_label) || scoutKnownValue(context.payment_model)),
            scoutContextChip('Survey pathway', scoutKnownValue(context.survey_pathway)),
            scoutContextChip('Guidance level', scoutKnownValue(context.preferred_guidance_level))
        ].filter(Boolean).join('');
    }

    function scoutContextChip(label, value) {
        if (!scoutKnownValue(value)) {
            return '';
        }
        return '<span><b>' + escapeHtml(label) + '</b>' + escapeHtml(formatScoutValue(value, 'Not yet known')) + '</span>';
    }

    function renderScoutEmptyState(icon, title, message, cta, target) {
        return '<section class="qn-scout-empty-card">' +
            '<span class="dashicons dashicons-' + escapeHtml(icon) + '"></span>' +
            '<h3>' + escapeHtml(title) + '</h3>' +
            '<p>' + escapeHtml(message) + '</p>' +
            (cta ? '<button class="qn-button qn-button-primary" type="button" ' + (target ? 'data-section-target="' + escapeHtml(target) + '"' : 'id="qn-scout-empty-generate"') + '>' + escapeHtml(cta) + '</button>' : '') +
            '</section>';
    }

    function renderScoutUnavailable(message) {
        return '<section class="qn-scout-empty-card qn-scout-empty-warning">' +
            '<span class="dashicons dashicons-warning"></span>' +
            '<h3>Scout Preview is unavailable</h3>' +
            '<p>' + escapeHtml(message) + '</p>' +
            '</section>';
    }

    function renderScoutGenerating(run) {
        return renderScoutStatusHero(run) +
            '<section class="qn-scout-empty-card">' +
            '<span class="dashicons dashicons-update qn-spin-icon"></span>' +
            '<h3>Generating Scout Preview</h3>' +
            '<p>Scout is preparing your hospital-specific quality operating system draft. This may take a moment.</p>' +
            '</section>';
    }

    function renderScoutFailed(run) {
        return renderScoutStatusHero(run) +
            '<section class="qn-scout-failed">' +
            '<span class="dashicons dashicons-warning"></span>' +
            '<div><span class="qn-scout-status-badge qn-scout-status-danger">Failed</span><h3>Scout preview failed</h3>' +
            '<p>' + escapeHtml(safeScoutError(run.error_message)) + '</p>' +
            '<small>Your Hospital Setup was saved. You can retry generation without re-entering the form.</small></div>' +
            (state.scoutCanGenerate ? '<button class="qn-button qn-button-primary" type="button" data-retry-scout="' + escapeHtml(run.id) + '">Retry Generation</button>' : '') +
            '</section>' +
            renderScoutPersonaContext(run) +
            renderScoutAttentionPanel(run);
    }

    function safeScoutError(message) {
        if (!message) {
            return 'Scout could not generate a setup preview. Retry generation or contact QualiNav support if this continues.';
        }
        return 'Scout could not generate a setup preview. Your saved setup is still available for retry.';
    }

    function renderScoutCompleted(run) {
        var rows = scoutWorkflowDefinitions().map(function (definition) {
            return renderScoutWorkflowCard(run, definition);
        }).filter(Boolean).join('');
        var workflowList = rows ?
            '<div class="qn-scout-workflow-list">' + rows + '</div>' :
            '<div class="qn-empty-state"><span class="dashicons dashicons-lightbulb"></span><h3>No workflow sections returned</h3><p>Scout did not return structured workflow sections for this preview.</p></div>';
        return renderScoutStatusHero(run) +
            renderScoutPersonaContext(run) +
            renderScoutAttentionPanel(run) +
            '<section class="qn-scout-workflow-section"><div class="qn-section-toolbar"><div><p class="qn-eyebrow">Workflow draft</p><h3>Generated operating system preview</h3></div></div>' +
            workflowList + '</section>' +
            renderScoutSources(scoutSources(run));
    }

    function renderScoutStatusHero(run) {
        var status = scoutPreviewStatus(run);
        var counts = scoutRunCounts(run);
        var cta = scoutHeroCta(run);
        return '<section class="qn-scout-status-hero qn-scout-status-hero-' + escapeHtml(status.tone) + '">' +
            '<div class="qn-scout-status-main"><span class="dashicons dashicons-lightbulb"></span><div>' +
            '<p class="qn-eyebrow">Scout status</p><h3>' + escapeHtml(status.label) + '</h3><p>' + escapeHtml(status.detail) + '</p></div></div>' +
            '<div class="qn-scout-status-metrics">' +
            scoutMetric('Last generated', run && run.created_at ? run.created_at : 'Not yet generated') +
            scoutMetric('Sources', counts.sources) +
            (counts.warnings ? scoutMetric('Warnings', counts.warnings) : '') +
            (counts.missing ? scoutMetric('Missing inputs', counts.missing) : '') +
            '</div>' +
            (cta ? '<div class="qn-scout-status-action">' + cta + '</div>' : '') +
            '</section>';
    }

    function scoutHeroCta(run) {
        if (isReadOnlyWorkspaceRole()) {
            return '';
        }
        if (!run) {
            return state.scoutCanGenerate ? '<button class="qn-button qn-button-primary" type="button" id="qn-scout-hero-generate">Generate Preview</button>' : '';
        }
        if (run.status === 'failed') {
            return state.scoutCanGenerate ? '<button class="qn-button qn-button-primary" type="button" data-retry-scout="' + escapeHtml(run.id) + '">Retry Generation</button>' : '';
        }
        if (run.status === 'completed') {
            return '<button class="qn-button qn-button-primary" type="button" data-scout-open-latest>Open Latest Preview</button>';
        }
        return '';
    }

    function scoutMetric(label, value) {
        return '<div><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(text(value)) + '</strong></div>';
    }

    function scoutPreviewStatus(run) {
        if (!state.scoutBridgeAvailable) {
            return {label: 'Unavailable', tone: 'warning', detail: 'Scout Preview is temporarily unavailable.'};
        }
        if (!run) {
            return {label: 'Not generated', tone: 'neutral', detail: 'No Scout preview has been generated yet.'};
        }
        if (run.status === 'failed') {
            return {label: 'Failed', tone: 'danger', detail: 'Scout could not generate the preview. Your Hospital Setup is still saved.'};
        }
        if (run.status === 'running' || run.status === 'pending') {
            return {label: 'Generating', tone: 'warning', detail: 'Scout is preparing setup recommendations.'};
        }
        return {label: 'Ready', tone: 'success', detail: 'Your hospital-specific draft is ready to review.'};
    }

    function scoutRunCounts(run) {
        var attention = scoutAttentionReport(run);
        return {
            warnings: attention.warnings.length,
            missing: attention.missing.length,
            sources: scoutSources(run).length || (run && run.source_count !== null && run.source_count !== undefined ? run.source_count : 0)
        };
    }

    function renderScoutPersonaContext(run) {
        var context = run.persona_context || {};
        var summary = scoutHospitalContextSummary(run);
        if (!summary && !Object.keys(context).length) {
            return '';
        }
        var hospital = dashboardHospital() || {};
        var rows = [
            ['Hospital category', scoutKnownValue(context.hospital_category) || scoutHospitalTypeFromPayment(context.payment_model || hospital.payment_model_label)],
            ['Payment model', scoutKnownValue(context.payment_model) || scoutKnownValue(hospital.payment_model_label)],
            ['Survey pathway', scoutKnownValue(context.survey_pathway)],
            ['Accreditation pathway', scoutKnownValue(context.accreditation_pathway)],
            ['Program maturity', scoutKnownValue(context.program_maturity)],
            ['Guidance level', scoutKnownValue(context.preferred_guidance_level)],
            ['First 30 days track', context.first_30_days_track ? 'Yes' : '']
        ].filter(function (row) {
            return row[1] !== null && row[1] !== undefined && row[1] !== '';
        });

        return '<section class="qn-scout-context-panel">' +
            '<div class="qn-panel-header"><div><p class="qn-eyebrow">Hospital context</p><h3>How Scout shaped this preview</h3></div><span class="dashicons dashicons-admin-users"></span></div>' +
            '<div class="qn-scout-persona-summary">' + escapeHtml(summary || 'Scout will personalize the preview as more Hospital Setup detail is available.') + '</div>' +
            '<div class="qn-scout-context-grid">' + rows.map(renderScoutContextRow).join('') + '</div>' +
            '</section>';
    }

    function scoutHospitalContextSummary(run) {
        var context = run && run.persona_context ? run.persona_context : {};
        var hospital = dashboardHospital() || {};
        var category = scoutKnownValue(context.hospital_category) || scoutHospitalTypeFromPayment(context.payment_model || hospital.payment_model_label);
        var pathway = scoutKnownValue(context.survey_pathway) || scoutKnownValue(context.accreditation_pathway);
        var maturity = scoutKnownValue(context.program_maturity);
        var parts = [];
        if (category) {
            parts.push(formatScoutValue(category, ''));
        }
        if (pathway) {
            parts.push(formatScoutValue(pathway, ''));
        }
        if (maturity) {
            parts.push(formatScoutValue(maturity, '') + ' setup maturity');
        }
        if (parts.length) {
            return 'Scout used Hospital Setup answers to draft this operating-system preview for a ' + parts.join(' with ') + '.';
        }
        return run && run.persona_summary ? normalizePublicSetupCopy(run.persona_summary) : '';
    }

    function renderScoutContextRow(row) {
        var missing = row[1] === null || row[1] === undefined || row[1] === '';
        return '<div class="qn-scout-kv"><span>' + escapeHtml(row[0]) + '</span><strong class="' + (missing ? 'qn-scout-muted-chip' : 'qn-scout-value-chip') + '">' + escapeHtml(formatScoutValue(row[1], 'Not yet known')) + '</strong></div>';
    }

    function renderScoutAttentionPanel(run) {
        var report = scoutAttentionReport(run);
        var capabilities = scoutReadyCapabilities(run);
        var hasReview = report.missing.length || report.warnings.length || report.technical.length;
        return '<section class="qn-scout-attention">' +
            '<div class="qn-scout-readiness-summary"><span class="dashicons dashicons-superhero-alt"></span><div><p class="qn-eyebrow">Your initial workspace is ready</p><h3>Scout has already turned your setup into practical quality workflows</h3>' +
            '<p>Use the generated plan now. Adding or confirming the details below will make dates, reminders, and recommendations more precise.</p>' +
            (capabilities.length ? '<div class="qn-scout-capability-list" aria-label="Scout capabilities ready">' + capabilities.map(function (label) { return '<span><span class="dashicons dashicons-yes-alt"></span>' + escapeHtml(label) + '</span>'; }).join('') + '</div>' : '') +
            '</div></div>' +
            (hasReview ? '<div class="qn-section-toolbar qn-scout-review-heading"><div><p class="qn-eyebrow">Improve Scout\'s accuracy</p><h3>A few details can make this workspace even more useful</h3><p class="qn-muted-note">Each item explains why it matters. Add, confirm, change, or review it later without blocking your work.</p></div></div>' +
                '<div class="qn-scout-attention-grid">' +
                (report.missing.length ? renderScoutAttentionGroup('Information that would help', report.missing, 'editor-help', 'missing') : '') +
                (report.warnings.length ? renderScoutAttentionGroup('Assumptions to confirm', report.warnings, 'warning', 'warning') : '') +
                (report.technical.length ? renderScoutAttentionGroup('Technical notice', report.technical, 'shield', 'technical') : '') +
                '</div>' : '<div class="qn-scout-all-ready"><span class="dashicons dashicons-yes-alt"></span><div><strong>No additional details need review right now.</strong><p>Scout can use the current Hospital Setup as provided.</p></div></div>') +
            renderScoutResolvedAttention(report.resolved) + '</section>';
    }

    function renderScoutAttentionGroup(title, items, icon, tone) {
        return '<article class="qn-scout-attention-group qn-scout-attention-group-' + escapeHtml(tone) + '">' +
            '<div class="qn-scout-attention-heading"><span class="dashicons dashicons-' + escapeHtml(icon) + '"></span><div><h4>' + escapeHtml(title) + '</h4><span>' + escapeHtml(items.length + (items.length === 1 ? ' item' : ' items')) + '</span></div></div>' +
            '<div class="qn-scout-attention-list">' + items.map(function (item) {
                return renderScoutAttentionItem(item, tone);
            }).join('') + '</div>' +
            '</article>';
    }

    function renderScoutAttentionItem(item, tone) {
        var detail = item && item.key ? item : normalizeScoutAttentionItem(item, tone);
        var canEdit = canEditOnboarding() && tone !== 'technical';
        var primaryLabel = tone === 'warning' ? 'Change' : detail.actionLabel;
        var actions = canEdit ? '<div class="qn-scout-attention-actions">' +
            (tone === 'warning' ? '<button class="qn-button qn-button-small qn-button-primary" type="button" data-scout-attention-preference="confirmed" data-scout-attention-key="' + escapeHtml(detail.key) + '"><span class="dashicons dashicons-yes"></span>Confirm</button>' : '') +
            '<button class="qn-button qn-button-small qn-button-secondary" type="button" data-scout-attention-open="' + escapeHtml(detail.key) + '"><span class="dashicons dashicons-edit"></span>' + escapeHtml(primaryLabel) + '</button>' +
            '<button class="qn-button qn-button-small qn-button-quiet" type="button" data-scout-attention-preference="ignored" data-scout-attention-key="' + escapeHtml(detail.key) + '">Review later</button>' +
            '</div>' : '';
        return '<div class="qn-scout-attention-item">' +
            '<strong>' + escapeHtml(detail.title) + '</strong>' +
            (detail.description ? '<p>' + escapeHtml(detail.description) + '</p>' : '') +
            (detail.benefit ? '<div class="qn-scout-attention-benefit"><span class="dashicons dashicons-lightbulb"></span><span><b>What this improves:</b> ' + escapeHtml(detail.benefit) + '</span></div>' : '') +
            (detail.basis ? '<span class="qn-scout-attention-basis"><span class="dashicons dashicons-info-outline"></span>Based on ' + escapeHtml(detail.basis) + '</span>' : '') +
            actions + '</div>';
    }

    function renderScoutResolvedAttention(items) {
        if (!items.length) {
            return '';
        }
        return '<details class="qn-scout-resolved"><summary>' + escapeHtml(items.length + (items.length === 1 ? ' item set aside or confirmed' : ' items set aside or confirmed')) + '</summary><div>' + items.map(function (item) {
            return '<div><span><strong>' + escapeHtml(item.title) + '</strong><small>' + escapeHtml(item.preferenceStatus === 'confirmed' ? 'Confirmed' : 'Review later') + '</small></span>' +
                (canEditOnboarding() ? '<button class="qn-button qn-button-small qn-button-quiet" type="button" data-scout-attention-preference="active" data-scout-attention-key="' + escapeHtml(item.key) + '">Restore</button>' : '') + '</div>';
        }).join('') + '</div></details>';
    }

    function scoutAttentionReport(run) {
        var byKey = {};
        var order = [];
        scoutMissingInputs(run).forEach(function (item) {
            var detail = normalizeScoutAttentionItem(item, 'missing');
            if (!byKey[detail.key]) {
                byKey[detail.key] = detail;
                order.push(detail.key);
            }
        });
        scoutWarnings(run).forEach(function (item) {
            var detail = normalizeScoutAttentionItem(item, 'warning');
            if (!byKey[detail.key]) {
                byKey[detail.key] = detail;
                order.push(detail.key);
            }
        });
        var preferences = state.onboarding && state.onboarding.scout_attention_preferences ? state.onboarding.scout_attention_preferences : {};
        var report = {missing: [], warnings: [], technical: [], resolved: []};
        order.forEach(function (key) {
            var detail = byKey[key];
            var preference = preferences[key] || {};
            detail.preferenceStatus = preference.status || '';
            if (detail.preferenceStatus === 'ignored' || detail.preferenceStatus === 'confirmed') {
                report.resolved.push(detail);
            } else if (detail.tone === 'warning') {
                report.warnings.push(detail);
            } else {
                report.missing.push(detail);
            }
        });
        if (run && run.status === 'failed') {
            report.technical.push(normalizeScoutAttentionItem(safeScoutError(run.error_message), 'technical'));
        }
        return report;
    }

    function normalizeScoutAttentionItem(item, tone) {
        var value = item;
        if (typeof value === 'string') {
            value = parseScoutObjectText(value) || {description: normalizePublicSetupCopy(value)};
        }
        value = value && typeof value === 'object' && !Array.isArray(value) ? value : {description: describeScoutItem(value)};
        var rawKey = value.input_key || value.key || '';
        var title = value.title || value.label || value.name || '';
        var description = value.description || value.detail || value.message || value.reason || '';
        if (!title && rawKey) {
            title = scoutAttentionTitle(rawKey);
        }
        if (!title) {
            title = tone === 'warning' ? 'Confirm this planning assumption' : (tone === 'technical' ? 'Scout could not complete this step' : 'Additional information needed');
        }
        if (!description && value.value) {
            description = describeScoutItem(value.value);
        }
        var key = scoutAttentionCanonicalKey(rawKey, title, description);
        var guidance = scoutAttentionGuidance(key);
        return {
            key: key,
            tone: tone,
            title: guidance.title || normalizePublicSetupCopy(title),
            description: normalizePublicSetupCopy(description),
            basis: scoutEvidenceBasis(value.evidence_basis || value.source || ''),
            benefit: guidance.benefit,
            actionLabel: guidance.actionLabel
        };
    }

    function scoutAttentionCanonicalKey(rawKey, title, description) {
        var source = [rawKey, title, description].join(' ').toLowerCase().replace(/[_-]+/g, ' ');
        if (/policy.{0,20}review.{0,20}cycle|review cycle.{0,20}polic/.test(source)) {
            return 'policy_review_cycle';
        }
        if (/governing board|board quality committee|full board/.test(source)) {
            return 'governing_board_schedule';
        }
        if (/aggregate.{0,25}(upload|data)|routine data upload|reporting schedule/.test(source)) {
            return 'aggregate_data_schedule';
        }
        if (/quality improvement|\bqi project/.test(source)) {
            return 'quality_improvement_projects';
        }
        var fallback = String(rawKey || title || 'scout_review_item').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
        return fallback.slice(0, 64) || 'scout_review_item';
    }

    function scoutAttentionGuidance(key) {
        var guidance = {
            quality_improvement_projects: {title: 'Quality improvement project details', benefit: 'Adds project owners, milestones, progress checks, and useful reminders to the workspace.', actionLabel: 'Open Data Hub'},
            aggregate_data_schedule: {title: 'Routine data and submission schedule', benefit: 'Helps Scout place recurring submissions on the right dates and remind the right people earlier.', actionLabel: 'Open Data Hub'},
            governing_board_schedule: {title: 'Governing Board meeting schedule', benefit: 'Makes committee dates, board reporting, and preparation reminders more accurate.', actionLabel: 'Add schedule'},
            policy_review_cycle: {title: 'Policy review cycle', benefit: 'Lets Scout create reliable policy review reminders instead of estimating when reviews are due.', actionLabel: 'Add review cycle'}
        };
        return guidance[key] || {title: '', benefit: 'Helps Scout make the hospital\'s schedules, reminders, and recommendations more precise.', actionLabel: 'Add details'};
    }

    function scoutReadyCapabilities(run) {
        var labels = {
            master_reporting_schedule: 'Reporting schedule',
            meeting_report_flow_map: 'Meeting and board flow',
            survey_readiness_timeline: 'Survey readiness timeline',
            active_monitoring_improvement_tasks: 'Monitoring tasks',
            recurring_clinical_monitoring: 'Clinical monitoring',
            active_improvement_projects: 'Improvement projects',
            priority_queue: 'Priority queue',
            plan_policy_tasks: 'Plan and policy reviews',
            reminder_rules: 'Reminders'
        };
        return scoutWorkflowDefinitions().filter(function (definition) {
            var group = findScoutGroup(run, definition);
            return group && scoutGroupCounts(group).items > 0 && labels[definition.key];
        }).map(function (definition) {
            return labels[definition.key];
        }).slice(0, 6);
    }

    function openScoutAttentionTarget(key) {
        if (key === 'quality_improvement_projects' || key === 'aggregate_data_schedule') {
            var homeUrl = String(config.homeUrl || '/').replace(/\/?$/, '/');
            window.location.assign(homeUrl + 'data-hub/#dm');
            return;
        }
        var targets = {
            governing_board_schedule: {section: 'committees_reporting', question: 'committee_list'},
            policy_review_cycle: {section: 'plans_policies_monitoring', question: 'annual_policy_review_cycle'}
        };
        var target = targets[key] || {section: 'hospital_director_info', question: ''};
        activateSection('day-0-setup', true);
        window.setTimeout(function () {
            switchOnboardingStepBySection(target.section);
            window.setTimeout(function () {
                var field = target.question ? findOnboardingQuestionElement(target.question) : null;
                if (!field) {
                    scrollOnboardingStepToTop();
                    return;
                }
                var disclosure = field.closest('details');
                if (disclosure) {
                    disclosure.open = true;
                }
                field.classList.add('qn-question-url-focus');
                field.scrollIntoView({behavior: 'smooth', block: 'center'});
                var control = field.querySelector('input:not([type="hidden"]), select, textarea, button');
                if (control && typeof control.focus === 'function') {
                    try {
                        control.focus({preventScroll: true});
                    } catch (error) {
                        control.focus();
                    }
                }
                window.setTimeout(function () {
                    field.classList.remove('qn-question-url-focus');
                }, 4200);
            }, 160);
        }, 80);
    }

    function updateScoutAttentionPreference(key, status, trigger) {
        if (!key || !status || !state.onboardingOrganizationId || !canEditOnboarding()) {
            return;
        }
        var restore = setButtonLoading(trigger, status === 'active' ? 'Restoring...' : 'Saving...');
        api('/scout/attention-preference', {
            method: 'POST',
            body: {
                organization_id: state.onboardingOrganizationId,
                item_key: key,
                status: status
            }
        }).then(function (result) {
            state.onboarding.scout_attention_preferences = result.preferences || {};
            renderScoutPreview();
            showToast(status === 'confirmed' ? 'Assumption confirmed.' : (status === 'ignored' ? 'Moved to review later.' : 'Item restored.'), 'success');
        }).catch(function (error) {
            restore();
            showToast(error.message || 'Scout could not save that choice.', 'error');
        });
    }

    function parseScoutObjectText(raw) {
        raw = String(raw || '').trim();
        if (raw.charAt(0) !== '{' || raw.indexOf(':') === -1) {
            return null;
        }
        var result = {};
        ['input_key', 'key', 'title', 'label', 'name', 'description', 'detail', 'message', 'reason', 'evidence_basis', 'source'].forEach(function (key) {
            var value = extractScoutObjectField(raw, key);
            if (value !== '') {
                result[key] = value;
            }
        });
        return Object.keys(result).length ? result : null;
    }

    function extractScoutObjectField(raw, key) {
        var marker = new RegExp("[\\\"']?" + key + "[\\\"']?\\s*:\\s*", 'i');
        var match = marker.exec(raw);
        if (!match) {
            return '';
        }
        var index = match.index + match[0].length;
        var quote = raw.charAt(index);
        if (quote !== "'" && quote !== '"') {
            return raw.slice(index).split(/,\s*[\"']?[a-z_]+[\"']?\s*:/i)[0].replace(/[},]\s*$/, '').trim();
        }
        index += 1;
        var output = '';
        for (; index < raw.length; index += 1) {
            var character = raw.charAt(index);
            if (character === '\\' && index + 1 < raw.length) {
                output += raw.charAt(index + 1);
                index += 1;
                continue;
            }
            if (character === quote) {
                break;
            }
            output += character;
        }
        return output.trim();
    }

    function scoutAttentionTitle(key) {
        var labels = {
            qi_project_details: 'Quality improvement project details',
            aggregate_data_upload_schedule: 'Routine data upload schedule',
            full_governing_board_schedule: 'Governing Board meeting schedule',
            policy_review_cycle_definition: 'Policy review cycle'
        };
        return labels[key] || normalizePublicSetupCopy(String(key).replace(/_/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); }));
    }

    function scoutEvidenceBasis(value) {
        var labels = {
            hospital_setup: 'Hospital Setup',
            quality_work: 'your quality program information',
            reporting_obligations: 'your reporting schedule'
        };
        var key = String(value || '').trim().toLowerCase().replace(/[\s-]+/g, '_');
        return labels[key] || (key ? normalizePublicSetupCopy(key.replace(/_/g, ' ')) : '');
    }

    function renderScoutWorkflowCard(run, definition) {
        var group = findScoutGroup(run, definition);
        var counts = scoutGroupCounts(group);
        if (!group || (counts.items === 0 && counts.warnings === 0 && counts.missing === 0)) {
            return '';
        }
        var status = scoutGroupStatus(group, counts);
        var preview = scoutGroupPreviewContent(definition, group, counts);
        return '<article class="qn-scout-row qn-scout-card-' + escapeHtml(status.tone) + '">' +
            '<span class="dashicons ' + scoutIcon(definition.key) + '"></span>' +
            '<div class="qn-scout-row-main"><div class="qn-scout-row-title"><h3>' + escapeHtml(definition.title) + '</h3><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(status.tone) + '">' + escapeHtml(status.label) + '</span></div>' +
            '<p>' + escapeHtml(preview.summary) + '</p>' +
            (preview.examples.length ? '<ul class="qn-scout-card-examples">' + preview.examples.map(function (example) { return '<li>' + escapeHtml(example) + '</li>'; }).join('') + '</ul>' : '') +
            '</div>' +
            '<div class="qn-scout-row-meta"><div class="qn-scout-card-metrics">' +
            '<span>' + escapeHtml(String(counts.items)) + ' items</span>' +
            (counts.warnings ? '<span>' + escapeHtml(String(counts.warnings)) + ' warnings</span>' : '') +
            (counts.missing ? '<span>' + escapeHtml(String(counts.missing)) + ' missing</span>' : '') +
            '</div>' +
            '<button class="qn-button qn-button-small" type="button" data-scout-details="' + escapeHtml(definition.key) + '">View Details</button></div>' +
            '</article>';
    }

    function findScoutGroup(run, definition) {
        var preview = run && run.preview ? run.preview : {};
        var groups = preview.groups || [];
        var keys = [definition.key].concat(definition.aliases || []);
        return groups.find(function (group) {
            return keys.indexOf(group.key) !== -1;
        }) || null;
    }

    function scoutGroupCounts(group) {
        if (!group) {
            return {items: 0, warnings: 0, missing: 0};
        }
        var items = Array.isArray(group.items) ? group.items : [];
        return {
            items: Number(group.item_count) || items.length,
            warnings: cleanScoutList(group.warnings || group.warning || []).length,
            missing: cleanScoutList(group.missing_inputs || group.missing || []).length
        };
    }

    function scoutGroupStatus(group, counts) {
        if (!group) {
            return {label: 'Not returned', tone: 'neutral'};
        }
        if (counts.missing > 0) {
            return {label: 'Missing info', tone: 'danger'};
        }
        if (counts.warnings > 0) {
            return {label: 'Needs review', tone: 'warning'};
        }
        return {label: 'Ready', tone: 'success'};
    }

    function scoutGroupPreviewText(group, status) {
        if (!group) {
            return 'Scout did not return this section in the latest preview.';
        }
        if (group.summary || group.description) {
            return describeScoutItem(group.summary || group.description);
        }
        var items = group.items || [];
        if (items.length) {
            return describeScoutItem(items[0]);
        }
        return status.label === 'Ready' ? 'Scout returned this section without item-level detail.' : 'Review this section for completeness.';
    }

    function scoutGroupPreviewContent(definition, group, counts) {
        var samples = scoutGroupSamples(group, 3);
        var count = counts.items || samples.length;
        var summaries = {
            master_reporting_schedule: count + ' reporting obligations drafted for review.',
            meeting_report_flow_map: count + ' committee and report-flow items mapped.',
            survey_readiness_timeline: count + ' survey readiness windows drafted.',
            active_monitoring_improvement_tasks: count + ' monitoring and improvement actions drafted.',
            recurring_clinical_monitoring: count + ' recurring monitoring activities drafted.',
            aggregate_data_uploads: count + ' aggregate data upload needs drafted.',
            routine_task_rhythm: 'Routine work rhythm drafted from setup answers.',
            active_improvement_projects: count + ' improvement project signals drafted.',
            priority_queue: count + ' priority items identified for follow-up.',
            plan_policy_tasks: count + ' plan and policy priorities drafted.',
            regulatory_monitoring_preferences: count + ' regulatory monitoring preferences drafted.',
            external_contact_directory: count + ' external contact items drafted.',
            first_30_days_learning_journey: count + ' first-30-days learning steps drafted.',
            learning_journey: count + ' learning items drafted.',
            reminder_rules: count + ' reminder rules drafted.'
        };
        return {
            summary: group.summary || group.description ? describeScoutItem(group.summary || group.description) : (summaries[definition.key] || (count + ' draft items returned for review.')),
            examples: samples
        };
    }

    function scoutGroupSamples(group, limit) {
        var items = group && Array.isArray(group.items) ? group.items : [];
        var samples = [];
        items.some(function (item) {
            var label = scoutItemTitle(item);
            if (label && samples.indexOf(label) === -1) {
                samples.push(label);
            }
            return samples.length >= limit;
        });
        return samples;
    }

    function scoutItemTitle(item) {
        if (item === null || item === undefined || item === '') {
            return '';
        }
        if (typeof item === 'string' || typeof item === 'number' || typeof item === 'boolean') {
            return describeScoutItem(item);
        }
        if (Array.isArray(item)) {
            return item.map(scoutItemTitle).filter(Boolean).slice(0, 2).join(', ');
        }
        if (typeof item === 'object') {
            var keys = ['title', 'name', 'report_name', 'committee', 'meeting', 'monitoring_activity', 'activity', 'dataset', 'project', 'priority', 'topic', 'rule', 'focus', 'timeframe'];
            for (var i = 0; i < keys.length; i += 1) {
                if (item[keys[i]]) {
                    return describeScoutItem(item[keys[i]]);
                }
            }
            return describeScoutItem(item);
        }
        return '';
    }

    function renderScoutSources(sources) {
        sources = Array.isArray(sources) ? sources : [];
        if (!sources.length) {
            return '';
        }
        return '<section class="qn-scout-sources">' +
            '<div class="qn-panel-header"><div><p class="qn-eyebrow">Sources</p><h3>Sources used by Scout</h3></div><span class="qn-status-pill">' + sources.length + ' listed</span></div>' +
            '<div class="qn-scout-source-list">' + sources.slice(0, 12).map(function (source) {
                return '<article>' + renderScoutSourceCard(source) + '</article>';
            }).join('') + '</div>' +
            (sources.length > 12 ? '<p class="qn-muted-note">Showing first 12 sources.</p>' : '') +
            '</section>';
    }

    function renderScoutSourceCard(source) {
        if (!source || typeof source !== 'object' || Array.isArray(source)) {
            return '<h4>' + escapeHtml(describeScoutItem(source)) + '</h4><p class="qn-muted-note">Source reference</p>';
        }
        var title = source.title || source.name || source.label || source.source || 'Scout source';
        var type = source.type || source.source_type || source.category || 'Reference';
        var status = source.confidence || source.status || source.relevance || '';
        return '<h4>' + escapeHtml(describeScoutItem(title)) + '</h4>' +
            '<div class="qn-scout-chip-list"><span class="qn-scout-muted-chip">' + escapeHtml(formatScoutValue(type, 'Reference')) + '</span>' +
            (status ? '<span class="qn-scout-value-chip">' + escapeHtml(formatScoutValue(status, '')) + '</span>' : '') + '</div>';
    }

    function renderScoutKeyValues(value) {
        if (!value || typeof value !== 'object' || Array.isArray(value)) {
            return '<p>' + escapeHtml(describeScoutItem(value)) + '</p>';
        }
        return Object.keys(value).slice(0, 8).map(function (key) {
            return '<div class="qn-scout-kv"><span>' + escapeHtml(normalizePublicSetupCopy(key.replace(/_/g, ' '))) + '</span><strong>' + escapeHtml(describeScoutItem(value[key])) + '</strong></div>';
        }).join('');
    }

    function emptyState(icon, title, message) {
        return '<div class="qn-empty-state"><span class="dashicons dashicons-' + escapeHtml(icon) + '"></span><h3>' + escapeHtml(title) + '</h3><p>' + escapeHtml(message) + '</p></div>';
    }

    function scoutWorkflowDefinitions() {
        return [
            {key: 'persona_experience_summary', title: 'Scout Experience Summary'},
            {key: 'master_reporting_schedule', title: 'Master Reporting Schedule', aliases: ['reporting_schedule']},
            {key: 'meeting_report_flow_map', title: 'Meeting & Report Flow Map', aliases: ['committee_flow_map']},
            {key: 'survey_readiness_timeline', title: 'Survey Readiness Timeline'},
            {key: 'active_monitoring_improvement_tasks', title: 'Active Monitoring & Improvement Tasks', aliases: ['clinical_monitoring_tasks']},
            {key: 'recurring_clinical_monitoring', title: 'Recurring Clinical Monitoring'},
            {key: 'aggregate_data_uploads', title: 'Aggregate Data Uploads'},
            {key: 'routine_task_rhythm', title: 'Routine Task Rhythm'},
            {key: 'active_improvement_projects', title: 'Active Improvement Projects', aliases: ['qi_project_milestones']},
            {key: 'priority_queue', title: 'Priority Queue'},
            {key: 'plan_policy_tasks', title: 'Plans & Policies'},
            {key: 'regulatory_monitoring_preferences', title: 'Regulatory Monitoring'},
            {key: 'external_contact_directory', title: 'External Contacts'},
            {key: 'first_30_days_learning_journey', title: 'First 30 Days & Learning Journey'},
            {key: 'learning_journey', title: 'Learning Journey'},
            {key: 'reminder_rules', title: 'Reminder Rules'}
        ];
    }

    function scoutDefinitionByKey(key) {
        return scoutWorkflowDefinitions().find(function (definition) {
            return definition.key === key || (definition.aliases || []).indexOf(key) !== -1;
        }) || {key: key, title: scoutTitle(key), aliases: []};
    }

    function scoutIcon(key) {
        var icons = {
            persona_experience_summary: 'dashicons-admin-users',
            master_reporting_schedule: 'dashicons-chart-bar',
            reporting_schedule: 'dashicons-chart-bar',
            meeting_report_flow_map: 'dashicons-networking',
            committee_flow_map: 'dashicons-businessperson',
            survey_readiness_timeline: 'dashicons-calendar-alt',
            active_monitoring_improvement_tasks: 'dashicons-analytics',
            recurring_clinical_monitoring: 'dashicons-heart',
            plan_policy_tasks: 'dashicons-media-document',
            clinical_monitoring_tasks: 'dashicons-heart',
            aggregate_data_uploads: 'dashicons-upload',
            routine_task_rhythm: 'dashicons-clock',
            active_improvement_projects: 'dashicons-performance',
            qi_project_milestones: 'dashicons-performance',
            priority_queue: 'dashicons-list-view',
            external_contact_directory: 'dashicons-id',
            regulatory_monitoring_preferences: 'dashicons-visibility',
            first_30_days_learning_journey: 'dashicons-welcome-learn-more',
            learning_journey: 'dashicons-welcome-learn-more',
            reminder_rules: 'dashicons-bell',
            scout_recommendations: 'dashicons-lightbulb',
            scout_summary: 'dashicons-text-page'
        };
        return icons[key] || 'dashicons-lightbulb';
    }

    function scoutTitle(key) {
        var titles = {
            persona_experience_summary: 'Scout Experience Summary',
            master_reporting_schedule: 'Master Reporting Schedule',
            reporting_schedule: 'Reporting Schedule',
            meeting_report_flow_map: 'Meeting & Report Flow Map',
            committee_flow_map: 'Committee Flow Map',
            survey_readiness_timeline: 'Survey Readiness Timeline',
            active_monitoring_improvement_tasks: 'Active Monitoring & Improvement Tasks',
            recurring_clinical_monitoring: 'Recurring Clinical Monitoring',
            clinical_monitoring_tasks: 'Clinical Monitoring',
            aggregate_data_uploads: 'Aggregate Data Uploads',
            routine_task_rhythm: 'Routine Task Rhythm',
            active_improvement_projects: 'Active Improvement Projects',
            qi_project_milestones: 'QI Project Milestones',
            priority_queue: 'Priority Queue',
            plan_policy_tasks: 'Plans & Policies',
            regulatory_monitoring_preferences: 'Regulatory Monitoring',
            external_contact_directory: 'External Contacts',
            first_30_days_learning_journey: 'First 30 Days & Learning Journey',
            learning_journey: 'Learning Journey',
            reminder_rules: 'Reminder Rules'
        };
        return titles[key] || normalizePublicSetupCopy(text(key).replace(/_/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); }));
    }

    function formatScoutValue(value, missingLabel) {
        if (value === true) {
            return 'Yes';
        }
        if (value === false) {
            return 'No';
        }
        if (value === null || value === undefined || value === '') {
            return missingLabel || 'Not yet known';
        }
        var raw = text(value).trim();
        var mapped = scoutValueLabel(raw);
        if (mapped) {
            return mapped;
        }
        return normalizePublicSetupCopy(raw.replace(/_/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); }));
    }

    function scoutKnownValue(value) {
        if (value === null || value === undefined || value === '') {
            return '';
        }
        var raw = text(value).trim();
        var normalized = raw.toLowerCase().replace(/\.$/, '').replace(/[_\s-]+/g, ' ');
        if (!normalized || normalized === 'not specified' || normalized === 'not yet known' || normalized === 'unknown' || normalized === 'n/a' || normalized === '-') {
            return '';
        }
        return raw;
    }

    function scoutValueLabel(value) {
        var key = text(value).trim().toLowerCase().replace(/[\s-]+/g, '_');
        var labels = {
            cah: 'Critical Access Hospital',
            critical_access_hospital: 'Critical Access Hospital',
            cms_state_survey: 'CMS/state survey',
            cms_state_survey_only: 'CMS/state survey only',
            tjc: 'The Joint Commission',
            joint_commission: 'The Joint Commission',
            the_joint_commission: 'The Joint Commission',
            independent: 'Independent',
            guided: 'Guided',
            partial: 'Partial',
            new: 'New'
        };
        return labels[key] || '';
    }

    function scoutHospitalTypeFromPayment(value) {
        var label = scoutValueLabel(value);
        return label === 'Critical Access Hospital' ? label : '';
    }

    function describeScoutItem(item) {
        if (item === null || item === undefined || item === '') {
            return '-';
        }
        if (typeof item === 'string' || typeof item === 'number' || typeof item === 'boolean') {
            return normalizePublicSetupCopy(String(item));
        }
        if (Array.isArray(item)) {
            return item.map(describeScoutItem).join(', ');
        }
        if (typeof item === 'object') {
            if (item.title) {
                return normalizePublicSetupCopy(item.title);
            }
            if (item.label && item.value) {
                return normalizePublicSetupCopy(item.label) + ': ' + describeScoutItem(item.value);
            }
            if (item.name) {
                return normalizePublicSetupCopy(item.name);
            }
            return Object.keys(item).slice(0, 4).map(function (key) {
                return scoutFriendlyKey(key) + ': ' + describeScoutItem(item[key]);
            }).join(' | ');
        }
        return String(item);
    }

    function scoutFriendlyKey(key) {
        var labels = {
            report_name: 'Report',
            committee: 'Committee',
            frequency: 'Frequency',
            owner: 'Owner',
            destination: 'Destination',
            sequence: 'Step',
            quality_director_role: 'Quality director role',
            monitoring_activity: 'Monitoring activity',
            review_body: 'Review body',
            dataset: 'Dataset',
            source: 'Source',
            timeframe: 'Timeframe',
            focus: 'Focus',
            detail: 'Detail',
            priority: 'Priority',
            domain: 'Domain',
            reason: 'Reason',
            urgency: 'Urgency',
            project: 'Project',
            method: 'Method',
            measure: 'Measure',
            status: 'Status',
            week: 'Week',
            action: 'Action',
            topic: 'Topic',
            format: 'Format',
            rule: 'Rule',
            trigger: 'Trigger',
            lead_time: 'Lead time'
        };
        return labels[key] || normalizePublicSetupCopy(key.replace(/_/g, ' '));
    }

    function cleanScoutList(items) {
        items = Array.isArray(items) ? items : (items ? [items] : []);
        var seen = {};
        return items.filter(function (item) {
            var label = describeScoutItem(item);
            var key = label.toLowerCase();
            if (label.replace(/[-\s]/g, '') === '' || seen[key]) {
                return false;
            }
            seen[key] = true;
            return true;
        });
    }

    function scoutWarnings(run) {
        var preview = run && run.preview ? run.preview : {};
        return cleanScoutList((preview.warnings || []).concat(run && run.warnings ? run.warnings : []));
    }

    function scoutMissingInputs(run) {
        var preview = run && run.preview ? run.preview : {};
        return cleanScoutList((preview.missing_inputs || []).concat(run && run.missing_inputs ? run.missing_inputs : []));
    }

    function scoutSources(run) {
        var preview = run && run.preview ? run.preview : {};
        var sources = preview.sources || (run && run.sources) || [];
        return Array.isArray(sources) ? sources.filter(Boolean) : [];
    }

    function openScoutDetails(key) {
        var modal = document.getElementById('qn-scout-detail-modal');
        var title = document.getElementById('qn-scout-detail-title');
        var body = document.getElementById('qn-scout-detail-body');
        if (!modal || !title || !body) {
            return;
        }
        var definition = scoutDefinitionByKey(key);
        var group = findScoutGroup(state.latestScoutRun, definition);
        title.textContent = definition.title;
        body.innerHTML = renderScoutDetailBody(definition, group);
        modal.hidden = false;
    }

    function closeScoutDetails() {
        var modal = document.getElementById('qn-scout-detail-modal');
        if (modal) {
            modal.hidden = true;
        }
    }

    function renderScoutDetailBody(definition, group) {
        if (!group) {
            return '<section class="qn-scout-detail-section"><span class="qn-scout-status-badge qn-scout-status-neutral">Not returned</span><p>Scout did not return structured content for this section in the latest preview.</p></section>';
        }
        var items = Array.isArray(group.items) ? group.items : [];
        return '<section class="qn-scout-detail-section">' +
            '<span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(scoutGroupStatus(group, scoutGroupCounts(group)).tone) + '">' + escapeHtml(scoutGroupStatus(group, scoutGroupCounts(group)).label) + '</span>' +
            (group.summary || group.description ? '<p>' + escapeHtml(describeScoutItem(group.summary || group.description)) + '</p>' : '') +
            (items.length ? renderScoutReadableValue(items) : '<p class="qn-muted-note">No item-level details were returned.</p>') +
            renderScoutDetailList('Warnings', group.warnings || group.warning || []) +
            renderScoutDetailList('Missing inputs', group.missing_inputs || group.missing || []) +
            (group.sources ? '<h4>Sources</h4>' + renderScoutReadableValue(group.sources) : '') +
            (isGlobalAdmin() ? '<details class="qn-scout-raw-details"><summary>View raw response</summary><pre>' + escapeHtml(JSON.stringify(group, null, 2)) + '</pre></details>' : '') +
            '</section>';
    }

    function renderScoutDetailList(title, items) {
        items = cleanScoutList(items);
        if (!items.length) {
            return '';
        }
        return '<h4>' + escapeHtml(title) + '</h4><ul class="qn-scout-detail-list">' + items.map(function (item) {
            return '<li>' + escapeHtml(describeScoutItem(item)) + '</li>';
        }).join('') + '</ul>';
    }

    function renderScoutReadableValue(value) {
        if (Array.isArray(value)) {
            return '<ul class="qn-scout-detail-list">' + value.map(function (item) {
                return '<li>' + renderScoutReadableValue(item) + '</li>';
            }).join('') + '</ul>';
        }
        if (value && typeof value === 'object') {
            return '<div class="qn-scout-detail-kv">' + Object.keys(value).filter(function (key) {
                return value[key] !== null && value[key] !== undefined && value[key] !== '';
            }).map(function (key) {
                return '<div><span>' + escapeHtml(normalizePublicSetupCopy(key.replace(/_/g, ' '))) + '</span><strong>' + renderScoutReadableValue(value[key]) + '</strong></div>';
            }).join('') + '</div>';
        }
        return escapeHtml(describeScoutItem(value));
    }

    function shouldAutoGenerateScoutPreview() {
        var organizationId = Number(state.onboardingOrganizationId) || 0;
        return !!organizationId &&
            state.scoutOnboardingSubmitted &&
            state.scoutBridgeAvailable &&
            state.scoutCanGenerate &&
            !state.latestScoutRun &&
            !state.scoutGenerationInFlight &&
            !isReadOnlyWorkspaceRole() &&
            Number(state.scoutAutoGenerationOrganizationId || 0) !== organizationId;
    }

    function maybeAutoGenerateScoutPreview() {
        if (!shouldAutoGenerateScoutPreview()) {
            return Promise.resolve(false);
        }
        state.scoutAutoGenerationOrganizationId = Number(state.onboardingOrganizationId) || 0;
        return generateScoutPreview(null, {automatic: true}).then(function () {
            return true;
        });
    }

    function generateScoutPreview(trigger, options) {
        options = options || {};
        if (state.scoutGenerationInFlight) {
            return Promise.resolve(state.latestScoutRun);
        }
        var candidate = trigger && trigger.currentTarget ? trigger.currentTarget : trigger;
        var button = candidate && typeof candidate.getAttribute === 'function'
            ? candidate
            : document.getElementById('qn-scout-generate-button');
        state.scoutGenerationInFlight = true;
        var restoreButton = setButtonLoading(button, 'Generating...');
        renderScoutPreview();
        if (options.automatic) {
            showToast('Hospital Setup saved. Scout is building your preview automatically.', 'success');
        }
        var body = {organization_id: state.onboardingOrganizationId};
        return api('/scout/generate', {method: 'POST', body: body, timeout: 60000}).then(function (result) {
            state.latestScoutRun = result.run || null;
            showToast(state.latestScoutRun && state.latestScoutRun.status === 'failed' ? 'Scout preview failed. Retry is available.' : 'Scout preview generated.', state.latestScoutRun && state.latestScoutRun.status === 'failed' ? 'warning' : 'success');
            renderHospitalDashboard();
            return loadScoutRuns(state.onboardingOrganizationId);
        }).catch(function (error) {
            showToast(error && error.message ? error.message : 'Scout preview could not be generated. Please try again.', 'warning');
        }).finally(function () {
            state.scoutGenerationInFlight = false;
            restoreButton();
            renderScoutPreview();
        });
    }

    function retryScoutRun(runId, trigger) {
        var restoreButton = setButtonLoading(trigger, 'Retrying...');
        return api('/scout/runs/' + encodeURIComponent(runId) + '/retry', {method: 'POST'}).then(function (result) {
            state.latestScoutRun = result.run || null;
            showToast(state.latestScoutRun && state.latestScoutRun.status === 'failed' ? 'Scout retry failed.' : 'Scout preview regenerated.', state.latestScoutRun && state.latestScoutRun.status === 'failed' ? 'warning' : 'success');
            renderHospitalDashboard();
            return loadScoutRuns(state.onboardingOrganizationId);
        }).catch(function (error) {
            showToast(error.message, 'warning');
        }).finally(function () {
            restoreButton();
        });
    }

    function renderOnboarding() {
        if (!state.onboarding) {
            return;
        }
        var steps = state.onboarding.steps || [];
        var step = steps[state.onboardingIndex] || steps[0];
        if (!step) {
            return;
        }
        setText('#qn-onboarding-hospital-name', state.onboarding.current_organization_name || 'Onboarding Wizard');
        setOnboardingChip('#qn-onboarding-system-context', state.onboarding.parent_system_name || 'Independent');
        setOnboardingChip('#qn-onboarding-type-context', state.onboarding.hospital_type_label || 'Hospital type not set', !state.onboarding.hospital_type);
        setOnboardingChip('#qn-onboarding-service-context', state.onboarding.service_model_label || 'Service model not set', !state.onboarding.service_model);
        setOnboardingChip('#qn-onboarding-state-context', state.onboarding.state_code || state.onboarding.state_name || 'State not set', !(state.onboarding.state_code || state.onboarding.state_name));
        setText('#qn-onboarding-step-count', 'Step ' + (state.onboardingIndex + 1) + ' of ' + steps.length);
        setText('#qn-onboarding-step-title', step.title);
        setText('#qn-onboarding-step-description', step.description);
        var progress = state.onboarding.progress ? state.onboarding.progress.total_percent : 0;
        var headerProgressText = document.querySelector('.qn-site-header-progress #qn-onboarding-progress-text');
        if (headerProgressText) {
            headerProgressText.textContent = progress + '%';
            if (!state.onboardingBackgroundSaving && !state.onboardingSubmitting) {
                setOnboardingSaveStatus(progress > 0 ? 'ready' : 'not-started', progress > 0 ? 'Ready' : 'Not started');
            }
        } else {
            setText('#qn-onboarding-progress-text', isOnboardingSubmitted() ? 'Ready for Scout - ' + progress + '% of optional detail added' : progress + '% of optional detail added');
        }
        setText('#qn-onboarding-step-summary', 'Step ' + (state.onboardingIndex + 1) + ' of ' + steps.length + ' - ' + (isOnboardingSubmitted() ? 'submitted, ' + progress + '% answer completeness' : progress + '% complete'));
        var bar = document.getElementById('qn-onboarding-progress-bar');
        if (bar) {
            bar.style.width = progress + '%';
            bar.textContent = '';
        }
        var warning = document.getElementById('qn-phi-warning');
        if (warning) {
            warning.hidden = true;
        }
        renderStepper(steps);
        renderOnboardingHelp(step);
        renderOnboardingMaterials();
        renderOnboardingFields(step);
        renderOnboardingReadonlyNotice(step);
        initializeSearchableSelects(document.getElementById('qn-onboarding-fields') || document);
        updateStepFourConditionalUI();
        updateStepSevenConditionalUI();
        if (!applyOnboardingUrlSection()) {
            applyOnboardingUrlQuestionFocus();
        }
        var prev = document.getElementById('qn-onboarding-prev');
        var next = document.getElementById('qn-onboarding-next');
        var submit = document.getElementById('qn-onboarding-submit');
        if (prev) {
            prev.disabled = state.onboardingIndex === 0;
        }
        if (next) {
            next.hidden = state.onboardingIndex >= steps.length - 1;
            next.disabled = false;
            next.innerHTML = canEditOnboardingStep(step) ? 'Save & Continue<span class="dashicons dashicons-arrow-right-alt2"></span>' : 'Next section<span class="dashicons dashicons-arrow-right-alt2"></span>';
            next.title = canEditOnboardingStep(step) ? '' : 'View-only navigation. Your role cannot save Hospital Setup changes.';
        }
        if (submit) {
            submit.hidden = state.onboardingIndex < steps.length - 1 || !canSubmitOnboarding();
            submit.disabled = state.onboardingSubmitting || !canSubmitOnboarding();
        }
        var save = document.getElementById('qn-onboarding-save');
        if (save) {
            save.disabled = !canEditOnboardingStep(step);
        }
        maybeShowOnboardingGuide();
    }

    function renderOnboardingReadonlyNotice(step) {
        var container = document.getElementById('qn-onboarding-fields');
        if (!container || canEditOnboardingStep(step)) {
            return;
        }
        container.insertAdjacentHTML('afterbegin', '<div class="qn-readonly-notice"><span class="dashicons dashicons-visibility"></span><div><strong>View-only access</strong><p>You can review this Hospital Setup, but your role cannot make changes.</p></div></div>');
    }

    function setOnboardingChip(selector, label, warning) {
        var node = document.querySelector(selector);
        if (!node) {
            return;
        }
        node.hidden = !!warning;
        if (warning) {
            node.textContent = '';
            node.classList.remove('qn-chip-warning');
            return;
        }
        node.textContent = label;
        node.classList.remove('qn-chip-warning');
    }

    function renderStepper(steps) {
        var stepper = document.getElementById('qn-onboarding-stepper');
        if (!stepper) {
            return;
        }
        stepper.innerHTML = steps.map(function (step, index) {
            var status = onboardingStepStatus(step);
            var active = index === state.onboardingIndex;
            var icon = status === 'complete' ? 'yes-alt' : 'marker';
            var shortTitle = onboardingStepShortTitle(step);
            var fullTitle = step.title || shortTitle;
            return '<button type="button" class="qn-stepper-item qn-stepper-' + status + (active ? ' qn-stepper-active' : '') + '" data-onboarding-step="' + index + '" title="' + escapeHtml(fullTitle) + '" aria-label="Step ' + (index + 1) + ': ' + escapeHtml(fullTitle) + ', ' + escapeHtml(stepStatusLabel(status)) + '">' +
                '<span class="qn-stepper-index"><span class="dashicons dashicons-' + icon + '"></span><b>' + (index + 1) + '</b></span>' +
                '<span class="qn-stepper-copy"><strong>' + escapeHtml(shortTitle) + '</strong><small>' + escapeHtml(stepStatusLabel(status)) + '</small></span>' +
            '</button>';
        }).join('');
    }

    function onboardingStepShortTitle(step) {
        var key = step && step.section_key ? step.section_key : '';
        var titles = {
            hospital_director_info: 'Hospital Info',
            accreditation_survey_readiness: 'Survey',
            services_clinical_model: 'Services',
            committees_reporting: 'Meetings',
            plans_policies_monitoring: 'Plans & Policies',
            measures_qi_projects: 'Data Hub',
            regulatory_tools_preferences: 'Review'
        };
        return titles[key] || (step && step.title ? step.title : 'Step');
    }

    function markOnboardingStepActive(targetIndex) {
        document.querySelectorAll('[data-onboarding-step]').forEach(function (button) {
            var active = Number(button.getAttribute('data-onboarding-step')) === Number(targetIndex);
            button.classList.toggle('qn-stepper-active', active);
            if (active) {
                button.setAttribute('aria-current', 'step');
            } else {
                button.removeAttribute('aria-current');
            }
        });
    }

    function onboardingStepStatus(step) {
        var progress = state.onboarding && state.onboarding.progress && state.onboarding.progress.step_progress ? state.onboarding.progress.step_progress : [];
        var found = Array.isArray(progress) ? progress.find(function (item) {
            return item.section_key === step.section_key;
        }) : null;
        if (found && (found.status === 'completed' || Number(found.percent_complete) >= 100)) {
            return 'complete';
        }
        if (found && Number(found.percent_complete) > 0) {
            return 'in-progress';
        }
        if (stepHasAnyAnswer(step)) {
            return 'in-progress';
        }
        return 'not-started';
    }

    function stepHasAnyAnswer(step) {
        var answers = state.onboarding ? state.onboarding.answers || {} : {};
        return (state.onboarding.questions || []).some(function (question) {
            return getSectionKeyForQuestion(question) === step.section_key &&
                answers[question.question_key] !== undefined &&
                answers[question.question_key] !== '' &&
                answers[question.question_key] !== null;
        });
    }

    function stepStatusLabel(status) {
        if (status === 'complete') {
            return 'Complete';
        }
        if (status === 'in-progress') {
            return 'In progress';
        }
        return 'Not started';
    }

    function renderOnboardingFields(step) {
        var container = document.getElementById('qn-onboarding-fields');
        if (!container || !state.onboarding) {
            return;
        }
        var panel = document.getElementById('day-0-setup');
        if (panel) {
            panel.classList.remove('qn-onboarding-loading');
        }
        var allSectionQuestions = (state.onboarding.questions || []).filter(function (question) {
            return getSectionKeyForQuestion(question) === step.section_key;
        });
        var questions = allSectionQuestions.filter(function (question) {
            return getSectionKeyForQuestion(question) === step.section_key && conditionalVisible(question);
        });
        if (step.section_key === 'accreditation_survey_readiness') {
            questions = allSectionQuestions;
        }
        if (step.section_key === 'goals_learning_contacts') {
            questions = (state.onboarding.questions || []).filter(function (question) {
                return getSectionKeyForQuestion(question) === step.section_key;
            });
        }
        if (step.section_key === 'hospital_director_info') {
            questions = allSectionQuestions;
        }
        container.innerHTML = renderQuestionGroups(step, questions);
        updateStepOneBedWarning();
        updateSwingBedConsistencyWarning();
        updateStepOneAffiliationUI();
        updateStepOneQualityLeaderTitleUI();
        updateStepTwoConditionalUI();
        updateStepThreeConditionalUI();
        updateStepSevenConditionalUI();
        if (step.section_key === 'plans_policies_monitoring') {
            window.setTimeout(resumePlanPolicyDocumentPolling, 0);
        }
        if (!canEditOnboardingStep(step)) {
            container.querySelectorAll('input, textarea, select, button').forEach(function (node) {
                node.disabled = true;
            });
        }
    }

    function renderQuestionGroups(step, questions) {
        if (step.section_key === 'hospital_director_info') {
            var hospitalKeys = ['hospital_name', 'ccn', 'hospital_city', 'hospital_state', 'hospital_zip', 'licensed_beds', 'swing_beds', 'hospital_type', 'independent_or_system'];
            var directorKeys = ['quality_leader_name', 'quality_leader_email', 'quality_leader_title', 'quality_leader_title_other'];
            var allQuestions = state.onboarding.questions || [];
            var affiliationQuestion = allQuestions.find(function (question) {
                return question.question_key === 'system_network_name';
            });
            var legacyStepOne = renderPreservedStepFourFields(['is_critical_access_hospital', 'acute_beds', 'licensed_for_swing_beds', 'quality_director_name']);
            return renderQuestionGroup('Hospital Information', 'building', questions.filter(function (question) {
                return hospitalKeys.indexOf(question.question_key) !== -1;
            }), '<div class="qn-bed-warning" id="qn-step1-bed-warning" hidden><span class="dashicons dashicons-warning"></span>Swing beds exceed licensed beds. Please verify.</div>' + renderSwingBedConsistencyWarning() + legacyStepOne, function (question) {
                if (question.question_key === 'independent_or_system' && affiliationQuestion) {
                    return '<div class="qn-affiliation-cluster">' + renderQuestion(question) + renderQuestion(affiliationQuestion) + '</div>';
                }
                return renderQuestion(question);
            }) + renderQuestionGroup('Quality Leader Information', 'businessperson', questions.filter(function (question) {
                return directorKeys.indexOf(question.question_key) !== -1;
            }));
        }
        if (step.section_key === 'accreditation_survey_readiness') {
            var pathwayKeys = ['survey_compliance_process', 'accrediting_body', 'accrediting_body_other'];
            var agencyKeys = ['state_survey_agency', 'state_survey_agency_url', 'life_safety_survey_agency_status', 'life_safety_survey_agency', 'life_safety_survey_agency_url'];
            var historyReadinessKeys = ['last_accreditation_licensing_survey_date'];
            var otherSurveyKeys = ['other_certification_licensing_surveys_status', 'other_certification_licensing_surveys'];
            return renderSurveyPathwayGroup(questions.filter(function (question) {
                return pathwayKeys.indexOf(question.question_key) !== -1;
            })) + renderQuestionGroup('Survey History', 'calendar-alt', questions.filter(function (question) {
                return historyReadinessKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepTwoQuestion, 'qn-survey-readiness-grid') + renderStateSurveyAgencyGroup(questions.filter(function (question) {
                return agencyKeys.indexOf(question.question_key) !== -1;
            })) + renderQuestionGroup('Other Certification & Licensing Surveys', 'clipboard', questions.filter(function (question) {
                return otherSurveyKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepTwoQuestion, 'qn-survey-readiness-grid');
        }
        if (step.section_key === 'services_clinical_model') {
            var serviceLineKeys = ['service_lines_core', 'service_lines_common', 'service_lines_growth_expansion', 'service_lines_other'];
            var modelKeys = ['laboratory_model', 'laboratory_model_other', 'radiology_model', 'radiology_model_other', 'pharmacy_model', 'pharmacy_model_other', 'anesthesia_moderate_sedation_model', 'anesthesia_moderate_sedation_model_other', 'blood_bank_model', 'blood_bank_model_other'];
            var legacyServices = renderPreservedStepFourFields(['emergency_department', 'surgery_invasive_procedures', 'surgery_procedure_types', 'obstetrics_labor_delivery', 'respiratory_therapy', 'rehabilitation_services', 'dietary_nutrition_services', 'visiting_specialists', 'contracted_quality_monitoring_agreements', 'transfusions_per_year']);
            return renderStepThreeServiceLinesGroup(questions.filter(function (question) {
                return serviceLineKeys.indexOf(question.question_key) !== -1;
            }), legacyServices) + renderStepThreeClinicalModelsGroup(questions.filter(function (question) {
                return modelKeys.indexOf(question.question_key) !== -1;
            }));
        }
        if (step.section_key === 'committees_reporting') {
            var committeeKeys = ['committee_list'];
            return renderQuestionGroup('Where Quality Is Reviewed', 'groups', questions.filter(function (question) {
                return committeeKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepFourQuestion, '', 'Use the local meeting name, identify what it reports to, and record its normal cadence and preparation time. If a topic is covered by another meeting, use that meeting name instead. Reporting measures and submission deadlines remain in Data Hub.');
        }
        if (step.section_key === 'plans_policies_monitoring') {
            var inventoryKeys = ['plan_policy_inventory'];
            return renderQuestionGroup('Plan & Policy Inventory', 'portfolio', questions.filter(function (question) {
                return inventoryKeys.indexOf(question.question_key) !== -1;
            }), renderDocumentPrivacyNotice(), renderStepFiveQuestion, '', 'Confirm which plans and policies are in place. One indexed document can be linked to more than one requirement when it legitimately covers multiple areas. Scout evaluates coverage and readiness; it does not declare compliance.');
        }
        if (step.section_key === 'measures_qi_projects') {
            return renderDataHubHandoff();
        }
        if (step.section_key === 'regulatory_tools_preferences') {
            var backupKeys = ['backup_visibility_users'];
            var confirmKeys = ['final_review_confirmation'];
            return renderOnboardingReviewSummary() + renderQuestionGroup('Backup Access & Final Confirmation', 'yes-alt', questions.filter(function (question) {
                return backupKeys.indexOf(question.question_key) !== -1 || confirmKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepEightQuestion, 'qn-step8-final-grid', 'Add backup users who may help maintain Hospital Setup, then confirm the information is ready. You can return and update it later.');
        }
        return renderQuestionGroup(step.title, 'clipboard', questions);
    }

    function renderDataHubHandoff() {
        var hospitalType = state.onboarding && state.onboarding.hospital_type_label ? state.onboarding.hospital_type_label.replace(/\.$/, '') : 'your hospital';
        var dataHubUrl = (config.homeUrl || '/') + 'data-hub/#dm';
        return '<section class="qn-question-group qn-data-hub-handoff">' +
            '<header><span class="dashicons dashicons-chart-area"></span><h4>Measures and reporting live in Data Hub</h4></header>' +
            '<div class="qn-data-hub-handoff-body">' +
                '<div><p>Data Hub is the source of truth for measure selection, hospital results, reporting deadlines, owners, submission status, and performance trends.</p>' +
                '<p>Scout uses this hospital profile to help Data Hub prioritize the programs and measures that may apply to ' + escapeHtml(hospitalType) + '. You remain in control of the final selections.</p></div>' +
                '<a class="qn-button qn-button-primary" href="' + escapeHtml(dataHubUrl) + '"><span class="dashicons dashicons-external"></span>Open Data Hub</a>' +
            '</div>' +
            '<div class="qn-data-hub-boundary"><span class="dashicons dashicons-yes-alt"></span><span>Hospital Setup does not duplicate measure values or external reporting calendars.</span></div>' +
        '</section>';
    }

    function onboardingAnswerHasValue(value) {
        if (Array.isArray(value)) {
            return value.some(onboardingAnswerHasValue);
        }
        if (value && typeof value === 'object') {
            return Object.keys(value).some(function (key) {
                return onboardingAnswerHasValue(value[key]);
            });
        }
        return value !== undefined && value !== null && value !== '' && value !== false;
    }

    function renderOnboardingReviewSummary() {
        var onboarding = state.onboarding || {};
        var answers = onboarding.answers || {};
        var missingByStep = [];
        var savedByStep = [];
        (onboarding.steps || []).forEach(function (step) {
            if (step.section_key === 'measures_qi_projects' || step.section_key === 'regulatory_tools_preferences') {
                return;
            }
            var stepQuestions = (onboarding.questions || []).filter(function (question) {
                return getSectionKeyForQuestion(question) === step.section_key &&
                    question.question_key !== 'plan_policy_inventory' &&
                    !(question.question_key === 'backup_preparer' && !backupPreparerUserOptions().length) &&
                    conditionalVisible(question);
            });
            var missing = stepQuestions.filter(function (question) {
                return getSectionKeyForQuestion(question) === step.section_key &&
                    !onboardingAnswerHasValue(answers[question.question_key]);
            }).map(function (question) {
                return question.label;
            });
            var saved = stepQuestions.filter(function (question) {
                return onboardingAnswerHasValue(answers[question.question_key]);
            }).filter(function (question) {
                return question.question_key !== 'quality_leader_title_other';
            }).map(function (question) {
                var answer = answers[question.question_key];
                if (
                    question.question_key === 'quality_leader_title' &&
                    fieldValue(answer).trim().toLowerCase() === 'other' &&
                    onboardingAnswerHasValue(answers.quality_leader_title_other)
                ) {
                    answer = answers.quality_leader_title_other;
                }
                return {
                    label: question.label,
                    value: onboardingReviewValue(question, answer)
                };
            }).filter(function (item) {
                return item.value;
            });
            if (step.section_key === 'plans_policies_monitoring') {
                var inventory = normalizePlanPolicyInventory(answers.plan_policy_inventory);
                var requiredInventory = inventory.filter(function (row) {
                    return !isAdditionalPlanPolicyRow(row);
                });
                var additionalInventory = inventory.filter(isAdditionalPlanPolicyRow);
                var unfinished = requiredInventory.filter(function (row) {
                    return !row.status;
                }).length;
                if (unfinished) {
                    missing.push(unfinished + ' plan or policy status' + (unfinished === 1 ? '' : 'es'));
                }
                var uniqueDocuments = {};
                var foldedLinks = 0;
                inventory.forEach(function (row) {
                    if (row.document_id && row.upload_status === 'ready') {
                        uniqueDocuments[row.document_id] = true;
                    }
                    if (row.status === 'folded_into_another' && row.folded_into_document_id) {
                        foldedLinks++;
                    }
                });
                saved.push({label: 'Required plan and policy items', value: String(requiredInventory.length)});
                saved.push({label: 'Unique indexed documents', value: String(Object.keys(uniqueDocuments).length)});
                saved.push({label: 'Requirements linked to another plan', value: String(foldedLinks)});
                saved.push({
                    label: 'Additional plans and policies',
                    value: additionalInventory.length ?
                        additionalInventory.map(function (row) { return row.policy_name; }).join(', ') :
                        'None'
                });
            }
            if (missing.length) {
                missingByStep.push({title: step.title, items: missing});
            }
            if (saved.length) {
                savedByStep.push({title: step.title, items: saved});
            }
        });
        var swingBedMismatch = getSwingBedConsistencyMismatch();
        if (swingBedMismatch) {
            missingByStep.unshift({
                title: 'Swing-bed consistency',
                items: [swingBedMismatch.message]
            });
        }
        var savedContent = savedByStep.length ? '<div class="qn-review-saved-sections">' + savedByStep.map(function (group) {
            return '<section class="qn-review-saved-group"><h5>' + escapeHtml(group.title) + '</h5><dl>' +
                group.items.map(function (item) {
                    return '<div><dt>' + escapeHtml(item.label) + '</dt><dd>' + escapeHtml(item.value) + '</dd></div>';
                }).join('') +
            '</dl></section>';
        }).join('') + '</div>' : '<p>No saved values are available yet.</p>';
        var content = missingByStep.length ? missingByStep.map(function (group) {
            var visible = group.items.slice(0, 5);
            return '<div class="qn-review-missing-group"><strong>' + escapeHtml(group.title) + '</strong><ul>' +
                visible.map(function (item) { return '<li>' + escapeHtml(item) + '</li>'; }).join('') +
                (group.items.length > visible.length ? '<li>' + (group.items.length - visible.length) + ' more item' + (group.items.length - visible.length === 1 ? '' : 's') + '</li>' : '') +
            '</ul></div>';
        }).join('') : '<div class="qn-review-complete"><span class="dashicons dashicons-yes-alt"></span><p>The active Hospital Setup sections have the information Scout needs. You can still revise them at any time.</p></div>';
        return '<section class="qn-question-group qn-onboarding-review"><header><span class="dashicons dashicons-clipboard"></span><h4>Setup Review</h4></header>' +
            '<div class="qn-onboarding-review-body"><p>Review the saved values Scout will use. Missing items do not block movement between steps; required confirmations are checked when you submit.</p>' +
            savedContent + '<div class="qn-review-attention"><h5>Needs attention</h5>' + content + '</div></div></section>';
    }

    function onboardingReviewValue(question, value) {
        if (value === true) {
            return 'Yes';
        }
        if (value === false) {
            return 'No';
        }
        if (question.question_key === 'backup_preparer' && String(value) === '0') {
            return 'Not assigned';
        }
        if (Array.isArray(value)) {
            if (!value.length) {
                return '';
            }
            if (value[0] && typeof value[0] === 'object') {
                var names = value.map(function (item) {
                    return fieldValue(item.committee_name || item.report_name || item.name || item.title || '');
                }).filter(Boolean).map(onboardingReviewScalar);
                return names.length ? names.join(', ') : value.length + ' saved item' + (value.length === 1 ? '' : 's');
            }
            return value.map(function (item) {
                return onboardingReviewScalar(optionLabelByValue(question.options || question.options_json || [], item) || item);
            }).filter(Boolean).join(', ');
        }
        if (value && typeof value === 'object') {
            var parts = Object.keys(value).filter(function (key) {
                return onboardingAnswerHasValue(value[key]);
            }).map(function (key) {
                return onboardingReviewScalar(value[key]);
            }).filter(Boolean);
            return parts.join(' · ');
        }
        return onboardingReviewScalar(optionLabelByValue(question.options || question.options_json || [], fieldValue(value)) || value);
    }

    function onboardingReviewScalar(value) {
        var raw = fieldValue(value);
        return /^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/.test(raw) ? optionLabel(raw) : raw;
    }

    function renderDocumentPrivacyNotice() {
        var key = 'qn_hospital_setup_document_privacy_ack';
        try {
            if (window.localStorage && window.localStorage.getItem(key) === '1') {
                return '';
            }
        } catch (error) {
            // Browser storage can be unavailable in privacy-restricted sessions.
        }
        return '<div class="qn-document-privacy-notice" data-document-privacy-notice><span class="dashicons dashicons-shield"></span><div><strong>Before uploading a hospital document</strong><p>Use approved operational documents only. Do not upload patient identifiers, incident narratives, peer-review case details, or other case-level information.</p></div><button type="button" class="qn-button qn-button-secondary" data-document-privacy-ack>I understand</button></div>';
    }

    function renderSurveyPathwayGroup(questions, extraHtml) {
        var processQuestion = questions.find(function (question) {
            return question.question_key === 'survey_compliance_process';
        });
        var branchQuestions = questions.filter(function (question) {
            return question.question_key === 'accrediting_body' || question.question_key === 'accrediting_body_other';
        });
        if (!processQuestion) {
            return '';
        }
        return '<section class="qn-question-group qn-survey-pathway-group"><header><span class="dashicons dashicons-awards"></span><h4>Survey Pathway</h4></header>' +
            '<div class="qn-survey-pathway-layout">' +
                renderStepTwoQuestion(processQuestion) +
                '<div class="qn-survey-branch-row"><div class="qn-survey-branch-spacer" aria-hidden="true"></div><div class="qn-survey-branch-panel">' +
                    branchQuestions.map(renderStepTwoQuestion).join('') +
                '</div></div>' +
                (extraHtml || '') +
            '</div>' +
        '</section>';
    }

    function renderPreservedStepFourFields(keys) {
        return keys.map(function (key) {
            var value = state.onboarding && state.onboarding.answers ? state.onboarding.answers[key] : '';
            if (value === undefined || value === null || value === '') {
                return '';
            }
            if (Array.isArray(value)) {
                return '';
            }
            if (value && typeof value === 'object' && !Array.isArray(value)) {
                return Object.keys(value).map(function (dataKey) {
                    return '<input type="hidden" data-structured-field="' + escapeHtml(key) + '" data-structured-key="' + escapeHtml(dataKey) + '" value="' + escapeFieldValue(value[dataKey] || '') + '">';
                }).join('');
            }
            return '<input type="hidden" data-onboarding-field="' + escapeHtml(key) + '" value="' + escapeFieldValue(value) + '">';
        }).join('');
    }

    function renderQuestionGroup(title, icon, questions, extraHtml, renderer, gridClass, titleInfo) {
        if (!questions.length) {
            return '';
        }
        renderer = renderer || renderQuestion;
        var groupClass = title === 'Plan & Policy Inventory' ? ' qn-plan-policy-group' : '';
        var titleInfoHtml = titleInfo ? fieldInfoIcon(titleInfo) : '';
        return '<section class="qn-question-group' + groupClass + '"><header><span class="dashicons dashicons-' + icon + '"></span><h4>' + escapeHtml(title) + titleInfoHtml + '</h4></header><div class="qn-question-grid ' + escapeHtml(gridClass || '') + '">' +
            (extraHtml || '') +
            questions.map(renderer).join('') + '</div></section>';
    }

    function fieldInfoIcon(message) {
        if (!message) {
            return '';
        }
        return '<span class="qn-field-info-icon" tabindex="0" role="img" aria-label="' + escapeHtml(message) + '" data-tooltip="' + escapeHtml(message) + '"></span>';
    }

    function renderStateSurveyAgencyGroup(questions) {
        var primaryKeys = ['state_survey_agency', 'state_survey_agency_url', 'life_safety_survey_agency_status'];
        var branchKeys = ['life_safety_survey_agency', 'life_safety_survey_agency_url'];
        var primaryQuestions = questions.filter(function (question) {
            return primaryKeys.indexOf(question.question_key) !== -1;
        });
        var branchQuestions = questions.filter(function (question) {
            return branchKeys.indexOf(question.question_key) !== -1;
        });
        if (!primaryQuestions.length) {
            return '';
        }
        return '<section class="qn-question-group qn-state-survey-agency-group"><header><span class="dashicons dashicons-building"></span><h4>State Survey Agency</h4></header>' +
            '<div class="qn-question-grid qn-state-survey-primary-grid">' + primaryQuestions.map(renderStepTwoQuestion).join('') + '</div>' +
            '<div class="qn-life-safety-branch-panel" data-life-safety-branch>' +
                '<div class="qn-life-safety-branch-label"><span class="dashicons dashicons-arrow-right-alt"></span><span>Different agency details</span></div>' +
                '<div class="qn-question-grid qn-life-safety-branch-grid">' + branchQuestions.map(renderStepTwoQuestion).join('') + '</div>' +
            '</div>' +
        '</section>';
    }

    function renderStepThreeServiceLinesGroup(questions, extraHtml) {
        var orderedKeys = ['service_lines_core', 'service_lines_common', 'service_lines_growth_expansion'];
        var otherQuestion = questions.find(function (question) {
            return question.question_key === 'service_lines_other';
        });
        var groupHtml = orderedKeys.map(function (key) {
            var question = questions.find(function (item) {
                return item.question_key === key;
            });
            if (!question) {
                return '';
            }
            return '<div class="qn-service-line-category" data-service-line-category="' + escapeHtml(key) + '">' +
                '<h5>' + escapeHtml(question.label) + '</h5>' +
                renderInlineChecklistField(question.question_key, onboardingQuestionValue(question), stepThreeOptions(question.question_key)) +
            '</div>';
        }).join('');
        return '<section class="qn-question-group qn-service-lines-group"><header><span class="dashicons dashicons-list-view"></span><h4>Hospital Service Lines</h4></header>' +
            '<p class="qn-service-lines-intro">Please check all services currently offered at your hospital.</p>' +
            '<div class="qn-service-lines-layout">' + groupHtml + '</div>' +
            (otherQuestion ? '<div class="qn-service-lines-other">' + renderStepThreeQuestion(otherQuestion) + '</div>' : '') +
            renderSwingBedConsistencyWarning() +
            (extraHtml || '') +
        '</section>';
    }

    function renderStepThreeClinicalModelsGroup(questions) {
        if (!questions.length) {
            return '';
        }
        var leftPairs = [
            ['laboratory_model', 'laboratory_model_other'],
            ['pharmacy_model', 'pharmacy_model_other'],
            ['blood_bank_model', 'blood_bank_model_other']
        ];
        var rightPairs = [
            ['radiology_model', 'radiology_model_other'],
            ['anesthesia_moderate_sedation_model', 'anesthesia_moderate_sedation_model_other']
        ];
        var renderColumn = function (pairs) {
            return '<div class="qn-clinical-model-column">' + pairs.map(function (pair) {
                return pair.map(function (key) {
                    var question = questions.find(function (item) {
                        return item.question_key === key;
                    });
                    return question ? renderStepThreeQuestion(question) : '';
                }).join('');
            }).join('') + '</div>';
        };
        return '<section class="qn-question-group qn-clinical-models-group"><header><span class="dashicons dashicons-clipboard"></span><h4>Clinical Service Models</h4></header>' +
            '<div class="qn-clinical-model-columns">' +
                renderColumn(leftPairs) +
                renderColumn(rightPairs) +
            '</div>' +
        '</section>';
    }

    function renderMonitoringChecklistGroup(title, intro, icon, questions, orderedKeys) {
        if (!questions.length) {
            return '';
        }
        var otherQuestion = questions.find(function (question) {
            return question.question_key === 'internal_monitoring_other' || question.question_key === 'external_reporting_other';
        });
        var categoryHtml = orderedKeys.map(function (key) {
            var question = questions.find(function (item) {
                return item.question_key === key;
            });
            if (!question || question === otherQuestion) {
                return '';
            }
            return renderMonitoringChecklistCategory(question);
        }).join('');
        return '<section class="qn-question-group qn-monitoring-checklist-group"><header><span class="dashicons dashicons-' + icon + '"></span><h4>' + escapeHtml(title) + '</h4></header>' +
            '<p class="qn-monitoring-intro">' + escapeHtml(intro) + '</p>' +
            '<div class="qn-monitoring-category-layout">' + categoryHtml + '</div>' +
            (otherQuestion ? '<div class="qn-monitoring-other">' + renderStepSixQuestion(otherQuestion) + '</div>' : '') +
        '</section>';
    }

    function externalReportingQuestionVisibleForHospitalType(question) {
        var hospitalType = currentOnboardingFieldValue('hospital_type') || (state.onboarding && state.onboarding.hospital_type) || '';
        hospitalType = fieldValue(hospitalType).trim().toLowerCase().replace(/[\s-]+/g, '_');
        var key = question ? question.question_key : '';
        var isCah = hospitalType === 'cah' || hospitalType === 'critical_access_hospital';
        var isPps = hospitalType === 'rural_pps_hospital' || hospitalType === 'general_acute_care_ipps_hospital' || hospitalType === 'ipps_hospital';
        if (isCah && ['external_reporting_cms_iqr', 'external_reporting_cms_oqr', 'external_reporting_cms_payment_programs'].indexOf(key) !== -1) {
            return false;
        }
        if (isPps && key === 'external_reporting_flex_mbqip') {
            return false;
        }
        return true;
    }

    function renderMonitoringChecklistCategory(question) {
        return '<div class="qn-monitoring-category" data-monitoring-category="' + escapeHtml(question.question_key) + '">' +
            '<h5>' + escapeHtml(question.label) + '</h5>' +
            renderInlineChecklistField(question.question_key, onboardingQuestionValue(question), stepSixOptions(question.question_key)) +
        '</div>';
    }

    function canEditOnboardingStep(step) {
        if (!canEditOnboarding() || !step) {
            return false;
        }
        if (state.me && state.me.qualinav_role === 'hospital_admin') {
            return step.section_key === 'hospital_director_info';
        }

        return true;
    }

    function canEditOnboarding() {
        if (!state.onboarding || !state.onboarding.can_edit || !state.me) {
            return false;
        }
        return isGlobalAdmin() || state.me.qualinav_role === 'quality_director' || state.me.qualinav_role === 'hospital_admin';
    }

    function documentUploadsEnabled() {
        return config.documentUploadsEnabled !== false;
    }

    function canUseHospitalSetupEditCopy() {
        if (canEditOnboarding()) {
            return true;
        }
        return !!(state.me && (state.me.qualinav_role === 'quality_director' || state.me.qualinav_role === 'hospital_admin'));
    }

    function canSubmitOnboarding() {
        return canEditOnboarding() &&
            (state.me.qualinav_role === 'quality_director' || isGlobalAdmin());
    }

    function getSectionKeyForQuestion(question) {
        var section = (state.onboarding.sections || []).find(function (item) {
            return Number(item.id) === Number(question.section_id);
        });
        return section ? section.section_key : '';
    }

    function conditionalVisible(question) {
        var logic = question.conditional_logic || {};
        var answers = state.onboarding.answers || {};
        if (logic.hide_if) {
            for (var hideKey in logic.hide_if) {
                if (answers[hideKey] === logic.hide_if[hideKey]) {
                    return false;
                }
            }
        }
        if (logic.show_if) {
            for (var showKey in logic.show_if) {
                if (answers[showKey] !== logic.show_if[showKey]) {
                    return false;
                }
            }
        }
        return true;
    }

    function renderOnboardingHelp(step) {
        var node = document.getElementById('qn-onboarding-help');
        if (!node) {
            return;
        }
        setText('#qn-scout-context-description', step.description || step.informs || 'Scout uses these answers to tailor the hospital workspace.');
        var items = onboardingHelpByStep[step.section_key] || [
            step.informs || 'Prepare Scout setup recommendations',
            'Identify missing inputs',
            'Shape the next setup workflow'
        ];
        if (step.section_key === 'hospital_director_info' && state.onboarding && !state.onboarding.hospital_type) {
            items = items.concat(['Prompt hospital type confirmation']);
        }
        var boundaryNote = '';
        if (step.section_key === 'accreditation_survey_readiness') {
            boundaryNote = '<li class="qn-data-boundary-note"><span class="dashicons dashicons-shield"></span><span>Do not enter case-level deficiency details, patient information, or peer-review information.</span></li>';
        }
        if (step.section_key === 'committees_reporting') {
            boundaryNote = '<li class="qn-data-boundary-note"><span class="dashicons dashicons-shield"></span><span>Do not enter patient, provider, peer-review, or case-level details. Only enter committee structure and report process information.</span></li>';
        }
        if (step.section_key === 'plans_policies_monitoring') {
            boundaryNote = '<li class="qn-data-boundary-note"><span class="dashicons dashicons-shield"></span><span>Scout analyzes plan and policy coverage and readiness. It does not certify compliance.</span></li>';
        }
        if (step.section_key === 'measures_qi_projects') {
            boundaryNote = '<li class="qn-data-boundary-note"><span class="dashicons dashicons-database"></span><span>Measure values, reporting obligations, and performance trends are managed in Data Hub.</span></li>';
        }
        if (step.section_key === 'regulatory_tools_preferences') {
            boundaryNote = '<li class="qn-data-boundary-note"><span class="dashicons dashicons-shield"></span><span>Final review shows missing setup context in one place. You can return and update it later.</span></li>';
        }
        node.innerHTML = items.map(function (item) {
            return '<li><span class="dashicons dashicons-yes-alt"></span><span>' + escapeHtml(item) + '</span></li>';
        }).join('') + boundaryNote;
    }

    function openScoutContextModal() {
        var modal = document.getElementById('qn-onboarding-scout-context-modal');
        if (!modal) {
            return;
        }
        closeOpenModals('qn-onboarding-scout-context-modal');
        modal.hidden = false;
        window.setTimeout(function () {
            var button = modal.querySelector('[data-close-scout-context]');
            if (button) {
                button.focus();
            }
        }, 0);
    }

    function closeScoutContextModal() {
        var modal = document.getElementById('qn-onboarding-scout-context-modal');
        if (modal) {
            modal.hidden = true;
        }
    }

    function renderOnboardingMaterials() {
        var node = document.getElementById('qn-onboarding-materials-list');
        if (!node) {
            return;
        }
        node.innerHTML = onboardingMaterialsChecklist.map(function (item) {
            return '<li><span class="dashicons dashicons-yes-alt"></span><span>' + escapeHtml(item) + '</span></li>';
        }).join('');
    }

    function onboardingGuideStorageKey() {
        var orgId = state.onboardingOrganizationId || (state.onboarding ? state.onboarding.current_organization_id : 'workspace');
        return 'qualinav_day0_guide_seen_' + orgId;
    }

    function workspaceWelcomeStorageKey() {
        var hospital = dashboardHospital() || {};
        var orgId = state.onboardingOrganizationId || hospital.organization_id || hospital.id || 'workspace';
        var role = state.me && state.me.qualinav_role ? state.me.qualinav_role : 'user';
        return 'qualinav_workspace_welcome_seen_' + orgId + '_' + role;
    }

    function isModalOpen(id) {
        var modal = document.getElementById(id);
        return !!(modal && !modal.hidden);
    }

    function closeOpenModals(exceptId) {
        document.querySelectorAll('.qn-modal:not([hidden])').forEach(function (modal) {
            if (!exceptId || modal.id !== exceptId) {
                modal.hidden = true;
            }
        });
    }

    function ensureDestructiveConfirmationModal() {
        var existing = document.getElementById('qn-destructive-confirmation-modal');
        if (existing) {
            return existing;
        }
        document.body.insertAdjacentHTML('beforeend',
            '<div class="qn-modal qn-confirm-modal" id="qn-destructive-confirmation-modal" hidden>' +
                '<div class="qn-modal-panel qn-confirm-modal-panel" role="alertdialog" aria-modal="true" aria-labelledby="qn-confirm-modal-title" aria-describedby="qn-confirm-modal-description">' +
                    '<div class="qn-panel-header"><div><p class="qn-eyebrow">Please confirm</p><h2 id="qn-confirm-modal-title">Confirm action</h2></div>' +
                        '<button class="qn-icon-button" type="button" data-confirm-modal-close aria-label="Close confirmation"><span aria-hidden="true">&times;</span></button></div>' +
                    '<div class="qn-confirm-modal-body"><div class="qn-confirm-modal-icon"><span class="dashicons dashicons-trash" aria-hidden="true"></span></div>' +
                        '<p id="qn-confirm-modal-description"></p><div class="qn-confirm-modal-item" id="qn-confirm-modal-item" hidden><span>Selected item</span><strong></strong></div>' +
                        '<p class="qn-confirm-modal-note" id="qn-confirm-modal-note" hidden></p></div>' +
                    '<div class="qn-confirm-modal-actions"><button class="qn-button qn-button-secondary" type="button" data-confirm-modal-close>Cancel</button>' +
                        '<button class="qn-button qn-button-danger" type="button" id="qn-confirm-modal-accept">Delete</button></div>' +
                '</div></div>'
        );
        var modal = document.getElementById('qn-destructive-confirmation-modal');
        modal.querySelectorAll('[data-confirm-modal-close]').forEach(function (button) {
            button.addEventListener('click', closeDestructiveConfirmation);
        });
        modal.querySelector('#qn-confirm-modal-accept').addEventListener('click', function () {
            var action = destructiveConfirmation && destructiveConfirmation.onConfirm;
            closeDestructiveConfirmation();
            if (typeof action === 'function') {
                action();
            }
        });
        return modal;
    }

    function openDestructiveConfirmation(options) {
        options = options || {};
        var modal = ensureDestructiveConfirmationModal();
        destructiveConfirmation = options;
        destructiveConfirmationFocus = document.activeElement;
        setText('#qn-confirm-modal-title', options.title || 'Confirm deletion');
        setText('#qn-confirm-modal-description', options.description || 'Please confirm that you want to continue.');
        var item = modal.querySelector('#qn-confirm-modal-item');
        var itemName = item ? item.querySelector('strong') : null;
        if (item && itemName) {
            item.hidden = !options.itemName;
            itemName.textContent = options.itemName || '';
        }
        var note = modal.querySelector('#qn-confirm-modal-note');
        if (note) {
            note.hidden = !options.note;
            note.textContent = options.note || '';
        }
        var accept = modal.querySelector('#qn-confirm-modal-accept');
        if (accept) {
            accept.textContent = options.confirmLabel || 'Delete';
        }
        modal.hidden = false;
        var close = modal.querySelector('[data-confirm-modal-close]');
        if (close) {
            close.focus();
        }
    }

    function closeDestructiveConfirmation() {
        var modal = document.getElementById('qn-destructive-confirmation-modal');
        if (modal) {
            modal.hidden = true;
        }
        destructiveConfirmation = null;
        if (destructiveConfirmationFocus && typeof destructiveConfirmationFocus.focus === 'function') {
            try {
                destructiveConfirmationFocus.focus({preventScroll: true});
            } catch (error) {
                destructiveConfirmationFocus.focus();
            }
        }
        destructiveConfirmationFocus = null;
    }

    function shouldAutoShowWorkspaceWelcome() {
        if (!state.me || isGlobalAdmin() || state.workspaceWelcomeAutoShown) {
            return false;
        }
        if (state.me.qualinav_role === 'viewer') {
            return false;
        }
        if (!dashboardHospital() && !state.onboarding) {
            return false;
        }
        try {
            if (window.localStorage && window.localStorage.getItem(workspaceWelcomeStorageKey()) === '1') {
                return false;
            }
        } catch (error) {
            // Local storage is optional progressive enhancement.
        }
        return true;
    }

    function maybeShowWorkspaceWelcome() {
        if (!shouldAutoShowWorkspaceWelcome()) {
            return;
        }
        state.workspaceWelcomeAutoShown = true;
        openWorkspaceWelcome(true);
    }

    function openWorkspaceWelcome(auto) {
        var modal = document.getElementById('qn-workspace-welcome-modal');
        if (!modal) {
            return;
        }
        closeOpenModals('qn-workspace-welcome-modal');
        var canEdit = canUseHospitalSetupEditCopy();
        setText('#qn-workspace-welcome-primary', canEdit ? (isOnboardingSubmitted() ? 'Review Hospital Setup' : 'Continue Hospital Setup') : 'View Hospital Setup');
        setText('#qn-workspace-welcome-setup-copy', canEdit ?
            'Hospital Setup gives Scout the durable context it needs: hospital profile, survey pathway, services, meeting cadence, and approved plans and policies. Measures, deadlines, and performance remain in Data Hub.' :
            'Hospital Setup gives Scout durable hospital context. Your role can review the setup and Scout preview without changing saved answers.');
        setText('#qn-workspace-welcome-reassurance', canEdit ?
            'You do not have to complete everything today. Start with what you know, skip what you need to look up, and come back anytime. Your answers save as you go.' :
            'You can review the workspace at your own pace. Editing and submission controls are limited by your role.');
        modal.hidden = false;
        modal.setAttribute('data-auto-open', auto ? '1' : '0');
        var dismiss = document.getElementById('qn-workspace-welcome-dismiss-check');
        if (dismiss) {
            dismiss.checked = true;
        }
        window.setTimeout(function () {
            var button = auto ? document.getElementById('qn-workspace-welcome-primary') : modal.querySelector('[data-close-workspace-welcome]');
            if (button) {
                button.focus();
            }
        }, 0);
    }

    function closeWorkspaceWelcome(markSeen) {
        var modal = document.getElementById('qn-workspace-welcome-modal');
        if (modal) {
            modal.hidden = true;
        }
        if (markSeen) {
            try {
                var dismiss = document.getElementById('qn-workspace-welcome-dismiss-check');
                if (window.localStorage) {
                    if (!dismiss || dismiss.checked) {
                        window.localStorage.setItem(workspaceWelcomeStorageKey(), '1');
                    } else {
                        window.localStorage.removeItem(workspaceWelcomeStorageKey());
                    }
                }
            } catch (error) {
                // Local storage is optional progressive enhancement.
            }
        }
    }

    function clearWorkspaceWelcomeDismissal() {
        try {
            if (window.localStorage) {
                window.localStorage.removeItem(workspaceWelcomeStorageKey());
            }
        } catch (error) {
            // Local storage is optional progressive enhancement.
        }
    }

    function goToHospitalSetupFromWelcome() {
        closeWorkspaceWelcome(true);
        if (config.isHomeWelcomePage) {
            var url = (config.homeUrl || '/') + 'organization-setup/';
            var welcomeOrganizationId = config.welcomeOrganizationId ? Number(config.welcomeOrganizationId) : 0;
            if (welcomeOrganizationId) {
                url += '?organization_id=' + encodeURIComponent(welcomeOrganizationId);
            }
            window.location.href = url + '#day-0-setup';
            return;
        }
        activateSection('day-0-setup', true);
    }

    function printQuestionsFromWelcome() {
        closeWorkspaceWelcome(true);
        if (!state.onboarding) {
            activateSection('day-0-setup', true);
            window.setTimeout(openOnboardingQuestionList, 400);
            return;
        }
        openOnboardingQuestionList();
    }

    function maybeShowOnboardingGuide() {
        if (!state.onboarding || isOnboardingSubmitted()) {
            return;
        }
        if (isModalOpen('qn-workspace-welcome-modal')) {
            return;
        }
        if (state.onboardingGuideAutoShown) {
            return;
        }
        state.onboardingGuideAutoShown = true;
        try {
            if (window.localStorage && window.localStorage.getItem(onboardingGuideStorageKey()) === '1') {
                return;
            }
        } catch (error) {
            // Local storage is optional progressive enhancement.
        }
        openOnboardingGuide(true);
    }

    function openOnboardingGuide(auto) {
        renderOnboardingMaterials();
        var modal = document.getElementById('qn-onboarding-guide-modal');
        var start = document.getElementById('qn-onboarding-guide-start');
        if (!modal) {
            return;
        }
        closeOpenModals('qn-onboarding-guide-modal');
        if (start) {
            start.textContent = state.onboardingIndex > 0 || stepHasAnyAnswer((state.onboarding.steps || [])[0] || {}) ? 'Continue setup' : 'Start setup';
        }
        modal.hidden = false;
        modal.setAttribute('data-auto-open', auto ? '1' : '0');
        window.setTimeout(function () {
            var button = document.getElementById('qn-onboarding-guide-start');
            if (button) {
                button.focus();
            }
        }, 0);
    }

    function closeOnboardingGuide(markSeen) {
        var modal = document.getElementById('qn-onboarding-guide-modal');
        if (modal) {
            modal.hidden = true;
        }
        if (markSeen) {
            try {
                if (window.localStorage) {
                    window.localStorage.setItem(onboardingGuideStorageKey(), '1');
                }
            } catch (error) {
                // Local storage is optional progressive enhancement.
            }
        }
    }

    function openOnboardingQuestionList() {
        var modal = document.getElementById('qn-onboarding-question-list-modal');
        var body = document.getElementById('qn-onboarding-question-list-body');
        if (!modal || !body || !state.onboarding) {
            return;
        }
        closeOpenModals('qn-onboarding-question-list-modal');
        body.innerHTML = renderOnboardingQuestionListHtml();
        modal.hidden = false;
    }

    function closeOnboardingQuestionList() {
        var modal = document.getElementById('qn-onboarding-question-list-modal');
        if (modal) {
            modal.hidden = true;
        }
    }

    function renderOnboardingQuestionListHtml() {
        var steps = state.onboarding && state.onboarding.steps ? state.onboarding.steps : [];
        var questions = state.onboarding && state.onboarding.questions ? state.onboarding.questions : [];
        var content = '<section class="qn-question-list-intro">' +
            '<p class="qn-eyebrow">Preparation worksheet</p>' +
            '<h3>Print setup questions</h3>' +
            '<p>Use this worksheet to gather operational information before entering Hospital Setup. It intentionally does not include saved answers.</p>' +
            '</section>';
        content += '<div class="qn-question-list-warning"><span class="dashicons dashicons-shield"></span><p><strong>No PHI.</strong> When entering information throughout Hospital Setup, do not enter patient names, MRNs, provider case details, peer-review details, adverse-event narratives, or any information that may include protected health information.</p></div>';
        content += '<div class="qn-question-list-materials"><h3>Helpful materials to gather</h3><p>You do not need every item before starting. Bring what is available and return later for details that require follow-up.</p><ul>' + onboardingMaterialsChecklist.map(function (item) {
            return '<li><span class="dashicons dashicons-yes-alt"></span><span>' + escapeHtml(item) + '</span></li>';
        }).join('') + '</ul></div>';
        content += '<div class="qn-question-list-divider"><p class="qn-eyebrow">Setup questions by step</p></div>';
        content += steps.map(function (step, index) {
            var questionGroups = printableQuestionGroupsForStep(step, questions);
            return '<section class="qn-question-list-section">' +
                '<p class="qn-eyebrow">Step ' + (index + 1) + '</p>' +
                '<h3>' + escapeHtml(step.title) + '</h3>' +
                '<p>' + escapeHtml(step.description || step.informs || '') + '</p>' +
                questionGroups.map(renderPrintableQuestionGroup).join('') +
                '</section>';
        }).join('');
        return content;
    }

    function printableQuestionsByKeys(questions, keys) {
        return keys.map(function (key) {
            return questions.find(function (question) {
                return question.question_key === key;
            });
        }).filter(Boolean);
    }

    function printableQuestionGroupsForStep(step, questions) {
        var stepQuestions = questions.filter(function (question) {
            return getSectionKeyForQuestion(question) === step.section_key && !isLegacyPrintableQuestion(question);
        });
        if (step.section_key === 'hospital_director_info') {
            return [
                {
                    title: 'Hospital Information',
                    questions: printableQuestionsByKeys(questions, ['hospital_name', 'ccn', 'hospital_city', 'hospital_state', 'hospital_zip', 'licensed_beds', 'swing_beds', 'hospital_type', 'independent_or_system', 'system_network_name'])
                },
                {
                    title: 'Quality Leader Information',
                    questions: printableQuestionsByKeys(questions, ['quality_leader_name', 'quality_leader_email', 'quality_leader_title', 'quality_leader_title_other'])
                }
            ];
        }
        if (step.section_key === 'committees_reporting') {
            return [
                {
                    title: 'Hospital Data Reporting Calendar',
                    questions: printableQuestionsByKeys(questions, ['reporting_obligations', 'report_lead_time', 'backup_preparer'])
                },
                {
                    title: 'Meeting Cadence for Committees Where Quality Data Is Shared',
                    questions: printableQuestionsByKeys(questions, ['committee_list'])
                }
            ];
        }
        return [{title: '', questions: stepQuestions}];
    }

    function isLegacyPrintableQuestion(question) {
        if (!question) {
            return true;
        }
        if (/^Legacy\b/i.test(question.label || '')) {
            return true;
        }
        return [
            'is_critical_access_hospital',
            'acute_beds',
            'licensed_for_swing_beds',
            'quality_director_name',
            'accreditation_status',
            'cms_certification_pathway',
            'open_plans_of_correction',
            'historical_deficiency_areas',
            'accreditation_360',
            'mbqip_upload',
            'nhsn_hai_rates_upload',
            'patient_experience_scores_upload',
            'fall_rates_upload',
            'pressure_injury_rates_upload',
            'hand_hygiene_upload',
            'other_dashboard_metrics',
            'current_quality_dashboard',
            'data_source_currency',
            'active_qi_projects',
            'qi_framework',
            'project_charters_status',
            'baseline_data_status',
            'mbqip_measure_set',
            'committee_required_status',
            'standing_agenda_items',
            'minutes_owner_location',
            'board_agenda_timing'
        ].indexOf(question.question_key) !== -1;
    }

    function printableQuestionHelpText(question) {
        if (!question) {
            return '';
        }
        if (question.question_key === 'accrediting_body') {
            return '';
        }
        return question.help_text || '';
    }

    function renderPrintableQuestionGroup(group) {
        if (!group.questions.length) {
            return '';
        }
        return (group.title ? '<h4>' + escapeHtml(group.title) + '</h4>' : '') +
            '<ol>' + group.questions.map(function (question) {
                var helpText = printableQuestionHelpText(question);
                return '<li><strong>' + escapeHtml(question.label) + (question.is_required ? ' *' : '') + '</strong>' + (helpText ? '<small>' + escapeHtml(helpText) + '</small>' : '') + renderQuestionChoiceList(question) + '</li>';
            }).join('') + '</ol>';
    }

    function normalizeQuestionChoice(option) {
        return optionLabel(option);
    }

    function questionChoiceOptions(question) {
        var key = question.question_key;
        var options = [];
        if (key === 'survey_history') {
            return [
                'Last survey/review date',
                'Survey/review type',
                'Surveying agency',
                'Outcome',
                'Plan of correction status',
                'Follow-up timing'
            ];
        }
        [
            stepOneOptions,
            stepTwoOptions,
            stepThreeOptions,
            stepFourOptions,
            stepFiveOptions,
            stepSevenOptions,
            stepEightOptions
        ].some(function (resolver) {
            options = resolver(key) || [];
            return options.length;
        });
        if (!options.length && question.field_type === 'yes_no') {
            options = [{label: 'Yes'}, {label: 'No'}, {label: 'Not sure'}];
        }
        if (!options.length && (question.field_type === 'select' || question.field_type === 'radio' || question.field_type === 'multiselect')) {
            options = question.options || [];
        }
        if (!options.length && question.field_type === 'checkbox') {
            options = [{label: 'Checked'}, {label: 'Not checked'}];
        }
        if (!options.length && question.field_type === 'plan_status') {
            options = [{label: 'Exists'}, {label: 'Does not exist'}, {label: 'Board approved: Yes'}, {label: 'Board approved: No'}];
        }
        return options.map(normalizeQuestionChoice).filter(Boolean);
    }

    function renderQuestionChoiceList(question) {
        var choices = questionChoiceOptions(question);
        if (!choices.length) {
            return '';
        }
        return '<small class="qn-question-choices"><span>Choices:</span> ' + choices.map(escapeHtml).join(', ') + '</small>';
    }

    function printOnboardingQuestionList() {
        if (!state.onboarding) {
            showToast('Setup questions are still loading.', 'warning');
            return;
        }
        downloadOnboardingQuestionListPdf();
    }

    function pdfSafeText(value) {
        return fieldValue(value)
            .replace(/[“”]/g, '"')
            .replace(/[‘’]/g, "'")
            .replace(/[–—]/g, '-')
            .replace(/•/g, '-')
            .replace(/[^\x09\x0A\x0D\x20-\x7E]/g, '');
    }

    function pdfEscape(value) {
        return pdfSafeText(value).replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');
    }

    function splitPdfText(text, maxChars) {
        var words = pdfSafeText(text).split(/\s+/).filter(Boolean);
        var lines = [];
        var line = '';
        words.forEach(function (word) {
            if (!line) {
                line = word;
                return;
            }
            if ((line + ' ' + word).length <= maxChars) {
                line += ' ' + word;
            } else {
                lines.push(line);
                line = word;
            }
        });
        if (line) {
            lines.push(line);
        }
        return lines.length ? lines : [''];
    }

    function buildOnboardingQuestionListPdfLines() {
        var steps = state.onboarding && state.onboarding.steps ? state.onboarding.steps : [];
        var questions = state.onboarding && state.onboarding.questions ? state.onboarding.questions : [];
        var lines = [
            {text: 'QualiNav Hospital Setup Question List', size: 18, bold: true, spaceAfter: 8},
            {text: 'Preparation worksheet', size: 12, bold: true, spaceAfter: 6},
            {text: 'Use this worksheet to gather operational information before entering Hospital Setup. It intentionally does not include saved answers.', size: 10, spaceAfter: 8},
            {text: 'No PHI: When entering information throughout Hospital Setup, do not enter patient names, MRNs, provider case details, peer-review details, adverse-event narratives, or any information that may include protected health information.', size: 10, bold: true, spaceAfter: 12},
            {text: 'Helpful materials to gather', size: 13, bold: true, spaceAfter: 4}
        ];
        onboardingMaterialsChecklist.forEach(function (item) {
            lines.push({text: '- ' + item, size: 9, indent: 12});
        });
        steps.forEach(function (step, index) {
            var questionGroups = printableQuestionGroupsForStep(step, questions);
            lines.push({text: 'Step ' + (index + 1) + ': ' + step.title, size: 14, bold: true, spaceBefore: 12, spaceAfter: 4});
            if (step.description || step.informs) {
                lines.push({text: step.description || step.informs, size: 9, spaceAfter: 4});
            }
            questionGroups.forEach(function (group) {
                if (!group.questions.length) {
                    return;
                }
                if (group.title) {
                    lines.push({text: group.title, size: 11, bold: true, spaceBefore: 6, spaceAfter: 2});
                }
                group.questions.forEach(function (question, questionIndex) {
                    var label = (questionIndex + 1) + '. ' + question.label + (question.is_required ? ' *' : '');
                    lines.push({text: label, size: 10, bold: true, spaceBefore: 4});
                    var helpText = printableQuestionHelpText(question);
                    if (helpText) {
                        lines.push({text: helpText, size: 8, indent: 12});
                    }
                    var choices = questionChoiceOptions(question);
                    if (choices.length) {
                        lines.push({text: 'Choices: ' + choices.join(', '), size: 8, indent: 12, spaceAfter: 2});
                    }
                });
            });
        });
        return lines;
    }

    function createQuestionListPdfBlob() {
        var pageWidth = 595;
        var pageHeight = 842;
        var margin = 48;
        var yStart = pageHeight - margin;
        var yMin = margin;
        var pages = [];
        var commands = [];
        var y = yStart;

        function startPage() {
            commands = [];
            y = yStart;
            pages.push(commands);
        }

        function addTextLine(text, size, bold, indent) {
            var lineHeight = Math.max(11, Math.round(size * 1.35));
            if (y - lineHeight < yMin) {
                startPage();
            }
            commands.push('BT /' + (bold ? 'F2' : 'F1') + ' ' + size + ' Tf ' + (margin + (indent || 0)) + ' ' + y + ' Td (' + pdfEscape(text) + ') Tj ET');
            y -= lineHeight;
        }

        startPage();
        buildOnboardingQuestionListPdfLines().forEach(function (item) {
            var size = item.size || 10;
            var indent = item.indent || 0;
            var maxChars = Math.max(28, Math.floor((pageWidth - (margin * 2) - indent) / (size * 0.48)));
            if (item.spaceBefore) {
                y -= item.spaceBefore;
            }
            splitPdfText(item.text, maxChars).forEach(function (line, lineIndex) {
                addTextLine(lineIndex && indent ? '  ' + line : line, size, item.bold, indent);
            });
            if (item.spaceAfter) {
                y -= item.spaceAfter;
            }
        });

        var objects = [];
        function addObject(body) {
            objects.push(body);
            return objects.length;
        }

        var catalogId = addObject('<< /Type /Catalog /Pages 2 0 R >>');
        var pagesId = addObject('');
        var fontId = addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
        var boldFontId = addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>');
        var pageIds = [];
        pages.forEach(function (pageCommands) {
            var stream = pageCommands.join('\n');
            var contentId = addObject('<< /Length ' + stream.length + ' >>\nstream\n' + stream + '\nendstream');
            var pageId = addObject('<< /Type /Page /Parent ' + pagesId + ' 0 R /MediaBox [0 0 ' + pageWidth + ' ' + pageHeight + '] /Resources << /Font << /F1 ' + fontId + ' 0 R /F2 ' + boldFontId + ' 0 R >> >> /Contents ' + contentId + ' 0 R >>');
            pageIds.push(pageId);
        });
        objects[pagesId - 1] = '<< /Type /Pages /Kids [' + pageIds.map(function (id) { return id + ' 0 R'; }).join(' ') + '] /Count ' + pageIds.length + ' >>';

        var pdf = '%PDF-1.4\n';
        var offsets = [0];
        objects.forEach(function (body, index) {
            offsets.push(pdf.length);
            pdf += (index + 1) + ' 0 obj\n' + body + '\nendobj\n';
        });
        var xrefOffset = pdf.length;
        pdf += 'xref\n0 ' + (objects.length + 1) + '\n0000000000 65535 f \n';
        offsets.slice(1).forEach(function (offset) {
            pdf += String(offset).padStart(10, '0') + ' 00000 n \n';
        });
        pdf += 'trailer\n<< /Size ' + (objects.length + 1) + ' /Root ' + catalogId + ' 0 R >>\nstartxref\n' + xrefOffset + '\n%%EOF';
        return new Blob([pdf], {type: 'application/pdf'});
    }

    function downloadOnboardingQuestionListPdf() {
        var blob = createQuestionListPdfBlob();
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = 'qualinav-hospital-setup-question-list.pdf';
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(function () {
            URL.revokeObjectURL(url);
        }, 1000);
    }

    function renderQuestion(question) {
        var value = onboardingQuestionValue(question);
        if (['quality_leader_name', 'quality_leader_email'].indexOf(question.question_key) !== -1) {
            var inputType = question.question_key === 'quality_leader_email' ? 'email' : 'text';
            return '<div class="qn-question ' + questionLayoutClass(question) + ' qn-canonical-readonly" data-question="' + escapeHtml(question.question_key) + '"><span class="qn-question-label">' + escapeHtml(question.label) + '</span><input type="' + inputType + '" value="' + escapeFieldValue(value) + '" readonly><small>Linked to the active Quality Director in My Org &gt; People.</small></div>';
        }
        var required = question.is_required ? ' <span class="qn-required">*</span>' : '';
        var helpText = stepOneHelpText(question);
        if (helpText === null) {
            helpText = '';
        } else if (!helpText) {
            helpText = question.help_text;
        }
        var help = helpText ? '<small>' + escapeHtml(helpText) + '</small>' : '';
        var info = questionInfoIcon(question);
        var tag = isSegmentedQuestion(question) ? 'div' : 'label';
        var hidden = questionIsHiddenOnRender(question) ? ' hidden style="display:none"' : '';
        return '<' + tag + ' class="qn-question ' + questionLayoutClass(question) + '" data-question="' + escapeHtml(question.question_key) + '"' + hidden + '>' +
            '<span class="qn-question-label">' + escapeHtml(question.label) + required + info + '</span>' + renderField(question, value) + help + '</' + tag + '>';
    }

    function renderCanonicalQiProjects() {
        var answers = state.onboarding && state.onboarding.answers ? state.onboarding.answers : {};
        var projects = Array.isArray(answers.qi_project_references) ? answers.qi_project_references : [];
        var body = projects.length ? projects.map(function (project) {
            return '<article class="qn-canonical-project-card"><div><strong>' + escapeHtml(project.title || ('Project #' + project.project_id)) + '</strong><span class="qn-status-pill qn-status-neutral">' + escapeHtml(String(project.status || 'draft').replace(/_/g, ' ')) + '</span></div><p>' + escapeHtml([project.focus_area, project.owner_name].filter(Boolean).join(' · ') || 'Project details are managed in QI Projects.') + '</p><small>' + escapeHtml(String(project.measure_count || 0) + ' measures · ' + String(project.member_count || 0) + ' members · Canonical project #' + String(project.project_id || '')) + '</small></article>';
        }).join('') : '<div class="qn-calendar-empty-state"><span class="dashicons dashicons-lightbulb"></span><div><strong>No QI projects yet</strong><p>Create and manage projects in the QI Projects module; Hospital Setup does not duplicate them.</p></div></div>';
        return '<section class="qn-question-group qn-canonical-projects"><header><span class="dashicons dashicons-lightbulb"></span><h4>Active QI Projects</h4></header><p class="qn-canonical-source-note">Linked directly from the QI Projects module for this hospital.</p><div class="qn-canonical-project-grid">' + body + '</div></section>';
    }

    function questionInfoIcon(question) {
        var copy = {
            licensed_beds: 'For Critical Access Hospitals, this is usually the 25 licensed-bed limit.',
            swing_beds: 'For Critical Access Hospitals, enter the swing-bed count within the 25 licensed beds.',
            survey_compliance_process: 'Choose the pathway your hospital uses to demonstrate Medicare Conditions of Participation compliance.',
            accrediting_body: 'Only needed when your hospital uses deemed status through an accrediting organization.',
            state_survey_agency: 'Example: California Department of Public Health.',
            state_survey_agency_url: 'Link to the applicable state survey body website.',
            life_safety_survey_agency_status: 'Life safety may be surveyed by the same state agency or by a separate fire marshal or life safety authority.',
            life_safety_survey_agency: 'Example: State Fire Marshal.',
            life_safety_survey_agency_url: 'Link to the applicable life safety agency website.',
            other_certification_licensing_surveys_status: 'Use this for other certification or licensing surveys, such as CLIA, stroke center, Magnet, or similar programs.'
        };
        var message = copy[question.question_key] || '';
        if (!message) {
            return '';
        }
        return '<span class="qn-field-info-icon" tabindex="0" role="img" aria-label="' + escapeHtml(message) + '" data-tooltip="' + escapeHtml(message) + '"></span>';
    }

    function questionIsHiddenOnRender(question) {
        if (question.question_key === 'system_network_name') {
            return !affiliationSettingsForStatus(onboardingQuestionValue({question_key: 'independent_or_system'}));
        }
        return false;
    }

    function isSegmentedQuestion(question) {
        return false;
    }

    function onboardingQuestionValue(question) {
        var answers = state.onboarding && state.onboarding.answers ? state.onboarding.answers : {};
        var key = question.question_key;
        if (answers[key] !== undefined && answers[key] !== null && answers[key] !== '' && answers[key] !== '-') {
            return answers[key];
        }
        if (key === 'hospital_name') {
            return state.onboarding.current_organization_name || '';
        }
        if (key === 'hospital_city') {
            return state.onboarding.city || '';
        }
        if (key === 'hospital_state') {
            return state.onboarding.state_code || state.onboarding.state_id || state.onboarding.state_name || '';
        }
        if (key === 'hospital_zip') {
            return state.onboarding.zip || '';
        }
        if (key === 'licensed_beds') {
            return state.onboarding.licensed_beds !== undefined && state.onboarding.licensed_beds !== null ? state.onboarding.licensed_beds : '';
        }
        if (key === 'hospital_type' && state.onboarding.hospital_type) {
            var type = String(state.onboarding.hospital_type || '').toLowerCase();
            if (type === 'cah' || type === 'critical_access_hospital') {
                return 'Critical Access Hospital';
            }
            if (type === 'rural_pps_hospital') {
                return 'Rural PPS Hospital';
            }
            if (type === 'general_acute_care_ipps_hospital') {
                return 'General Acute Care IPPS Hospital';
            }
            if (type === 'rural_emergency_hospital') {
                return 'Rural Emergency Hospital';
            }
            return state.onboarding.hospital_type;
        }
        if (key === 'acute_beds') {
            return state.onboarding.acute_beds !== undefined && state.onboarding.acute_beds !== null ? state.onboarding.acute_beds : '';
        }
        if (key === 'swing_beds') {
            return state.onboarding.swing_beds !== undefined && state.onboarding.swing_beds !== null ? state.onboarding.swing_beds : '';
        }
        if (key === 'licensed_for_swing_beds') {
            var hospitalType = state.onboarding ? String(state.onboarding.hospital_type || '').toLowerCase() : '';
            var isCah = hospitalType === 'cah' || hospitalType === 'critical_access_hospital';
            if (!isCah) {
                return '';
            }
            var swingBeds = Number(fieldValue(state.onboarding.swing_beds));
            return swingBeds > 0 ? 'yes' : '';
        }
        if (key === 'independent_or_system' && state.onboarding.service_model) {
            return state.onboarding.service_model;
        }
        if (key === 'independent_or_system' && !state.onboarding.parent_system_id) {
            return 'independent';
        }
        if (key === 'is_critical_access_hospital' && state.onboarding.hospital_type) {
            return state.onboarding.hospital_type === 'cah' || state.onboarding.hospital_type === 'critical_access_hospital' ? 'yes' : '';
        }
        if (key === 'quality_director_name' && state.onboarding.primary_quality_director) {
            return state.onboarding.primary_quality_director.display_name || '';
        }
        if (key === 'quality_leader_name') {
            if (answers.quality_director_name) {
                return answers.quality_director_name;
            }
            return state.onboarding.primary_quality_director ? (state.onboarding.primary_quality_director.display_name || '') : '';
        }
        if (key === 'quality_leader_email') {
            if (state.onboarding.primary_quality_director && state.onboarding.primary_quality_director.user_email) {
                return state.onboarding.primary_quality_director.user_email;
            }
            return state.me && state.me.user_email ? state.me.user_email : '';
        }
        return '';
    }

    function questionLayoutClass(question) {
        if (question.question_key === 'survey_compliance_process') {
            return 'qn-question-wide qn-survey-pathway-question';
        }
        if (question.field_type === 'textarea' || question.field_type === 'repeater' || question.field_type === 'plan_status') {
            return 'qn-question-wide';
        }
        if (question.question_key === 'system_network_name') {
            return 'qn-question-affiliation-detail';
        }
        if (question.question_key === 'other_certification_licensing_surveys' || question.question_key === 'current_readiness_activities') {
            return 'qn-question-wide';
        }
        if (['licensed_beds', 'acute_beds', 'licensed_for_swing_beds', 'swing_beds'].indexOf(question.question_key) !== -1) {
            return 'qn-question-third';
        }
        return '';
    }

    function optionLabel(option) {
        if (option && typeof option === 'object') {
            return normalizePublicSetupCopy(text(option.label || option.name || option.value));
        }
        return normalizePublicSetupCopy(text(option).replace(/_/g, ' ').replace(/\b\w/g, function (letter) {
            return letter.toUpperCase();
        }));
    }

    function fieldValue(value) {
        return value === null || value === undefined || value === '-' ? '' : String(value);
    }

    function optionValue(option) {
        if (option && typeof option === 'object') {
            return fieldValue(option.value !== undefined ? option.value : option.label);
        }
        return fieldValue(option);
    }

    function questionOptionsForKey(key) {
        var questions = state.onboarding && Array.isArray(state.onboarding.questions) ? state.onboarding.questions : [];
        var question = questions.find(function (item) {
            return item.question_key === key;
        });
        return question && Array.isArray(question.options) ? question.options : [];
    }

    function escapeFieldValue(value) {
        return fieldValue(value).replace(/[&<>"']/g, function (char) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
        });
    }

    function padDatePart(value) {
        value = String(value || '');
        return value.length === 1 ? '0' + value : value;
    }

    function isValidDateParts(month, day, year) {
        month = Number(month);
        day = Number(day);
        year = Number(year);
        if (!month || !day || year < 1000 || month < 1 || month > 12 || day < 1 || day > 31) {
            return false;
        }
        var test = new Date(year, month - 1, day);
        return test.getFullYear() === year && test.getMonth() === month - 1 && test.getDate() === day;
    }

    function formatDateForDisplay(value) {
        value = fieldValue(value).trim();
        var iso = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (iso && isValidDateParts(iso[2], iso[3], iso[1])) {
            return iso[2] + '/' + iso[3] + '/' + iso[1];
        }
        var us = value.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (us && isValidDateParts(us[1], us[2], us[3])) {
            return padDatePart(us[1]) + '/' + padDatePart(us[2]) + '/' + us[3];
        }
        return value;
    }

    function normalizeDateForStorage(value) {
        value = fieldValue(value).trim();
        if (!value) {
            return '';
        }
        var iso = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (iso && isValidDateParts(iso[2], iso[3], iso[1])) {
            return iso[1] + '-' + iso[2] + '-' + iso[3];
        }
        var us = value.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (us && isValidDateParts(us[1], us[2], us[3])) {
            return us[3] + '-' + padDatePart(us[1]) + '-' + padDatePart(us[2]);
        }
        return value;
    }

    function isIncompleteUsDate(value) {
        value = fieldValue(value).trim();
        if (!value) {
            return false;
        }
        return normalizeDateForStorage(value) === value && !/^\d{4}-\d{2}-\d{2}$/.test(value);
    }

    function dateFieldValue(field) {
        return field && field.getAttribute('data-date-format') === 'us' ? normalizeDateForStorage(field.value) : field.value;
    }

    function renderUsDateInput(attrs, value) {
        return '<span class="qn-us-date-wrap">' +
            '<input type="text" inputmode="numeric" autocomplete="off" placeholder="mm/dd/yyyy" data-date-format="us" pattern="\\d{1,2}/\\d{1,2}/\\d{4}" aria-haspopup="dialog" ' + attrs + ' value="' + escapeFieldValue(formatDateForDisplay(value)) + '">' +
            '<button class="qn-us-date-trigger" type="button" data-us-date-trigger aria-label="Open calendar" aria-haspopup="dialog" aria-expanded="false"><span class="dashicons dashicons-calendar-alt"></span></button>' +
        '</span>';
    }

    function localDateToIso(date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return '';
        }
        return date.getFullYear() + '-' + padDatePart(date.getMonth() + 1) + '-' + padDatePart(date.getDate());
    }

    function isoToLocalDate(value) {
        var match = fieldValue(value).match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!match || !isValidDateParts(match[2], match[3], match[1])) {
            return null;
        }
        return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    }

    function todayIsoDate() {
        return localDateToIso(new Date());
    }

    function ensureUsDatePickerPopover() {
        if (usDatePickerPopover && document.body.contains(usDatePickerPopover)) {
            return usDatePickerPopover;
        }
        usDatePickerPopover = document.createElement('div');
        usDatePickerPopover.className = 'qn-date-picker-popover';
        usDatePickerPopover.setAttribute('role', 'dialog');
        usDatePickerPopover.setAttribute('aria-label', 'Choose date');
        usDatePickerPopover.hidden = true;
        document.body.appendChild(usDatePickerPopover);
        return usDatePickerPopover;
    }

    function positionUsDatePicker() {
        if (!usDatePickerContext || !usDatePickerContext.wrapper) {
            return;
        }
        var popover = ensureUsDatePickerPopover();
        popover.hidden = false;
        var rect = usDatePickerContext.wrapper.getBoundingClientRect();
        var popRect = popover.getBoundingClientRect();
        var margin = 12;
        var left = Math.min(Math.max(margin, rect.left), window.innerWidth - popRect.width - margin);
        var below = rect.bottom + 8;
        var above = rect.top - popRect.height - 8;
        var top = below + popRect.height < window.innerHeight - margin ? below : Math.max(margin, above);
        popover.style.left = left + 'px';
        popover.style.top = top + 'px';
    }

    function closeUsDatePicker() {
        if (usDatePickerPopover) {
            usDatePickerPopover.hidden = true;
        }
        if (usDatePickerContext && usDatePickerContext.wrapper) {
            var trigger = usDatePickerContext.wrapper.querySelector('[data-us-date-trigger]');
            if (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
            }
        }
        usDatePickerContext = null;
    }

    function renderUsDatePicker() {
        if (!usDatePickerContext) {
            return;
        }
        var popover = ensureUsDatePickerPopover();
        var context = usDatePickerContext;
        var first = new Date(context.viewYear, context.viewMonth, 1);
        var gridStart = new Date(context.viewYear, context.viewMonth, 1 - first.getDay());
        var currentYear = new Date().getFullYear();
        var selectedYear = Number(context.viewYear) || currentYear;
        var startYear = Math.min(currentYear - 15, selectedYear - 5);
        var endYear = Math.max(currentYear + 5, selectedYear + 5);
        var yearOptions = [];
        var monthOptions = [];
        var days = [];
        var weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        for (var year = endYear; year >= startYear; year -= 1) {
            yearOptions.push('<option value="' + year + '"' + (year === selectedYear ? ' selected' : '') + '>' + year + '</option>');
        }
        for (var month = 0; month < 12; month += 1) {
            var monthLabel = new Date(2000, month, 1).toLocaleString('en-US', {month: 'long'});
            monthOptions.push('<option value="' + month + '"' + (month === Number(context.viewMonth) ? ' selected' : '') + '>' + escapeHtml(monthLabel) + '</option>');
        }
        for (var index = 0; index < 42; index += 1) {
            var date = new Date(gridStart);
            date.setDate(gridStart.getDate() + index);
            var iso = localDateToIso(date);
            var classes = [
                'qn-date-picker-day',
                date.getMonth() === context.viewMonth ? '' : 'is-muted',
                iso === context.selectedIso ? 'is-selected' : '',
                iso === todayIsoDate() ? 'is-today' : ''
            ].filter(Boolean).join(' ');
            days.push('<button type="button" class="' + classes + '" data-qn-date="' + iso + '" aria-label="' + escapeHtml(date.toLocaleDateString('en-US', {month: 'long', day: 'numeric', year: 'numeric'})) + '">' + date.getDate() + '</button>');
        }

        popover.innerHTML =
            '<div class="qn-date-picker-popover-head">' +
                '<div class="qn-date-picker-title">' +
                    '<select class="qn-date-picker-month" data-qn-date-month aria-label="Choose month">' + monthOptions.join('') + '</select>' +
                    '<select class="qn-date-picker-year" data-qn-date-year aria-label="Choose year">' + yearOptions.join('') + '</select>' +
                '</div>' +
            '</div>' +
            '<div class="qn-date-picker-weekdays">' + weekdays.map(function (day) { return '<div>' + day + '</div>'; }).join('') + '</div>' +
            '<div class="qn-date-picker-grid">' + days.join('') + '</div>' +
            '<div class="qn-date-picker-quick">' +
                '<button type="button" data-qn-date-today>Today</button>' +
                '<button type="button" data-qn-date-clear>Clear</button>' +
            '</div>';
        positionUsDatePicker();
    }

    function openUsDatePicker(control) {
        var wrapper = control ? control.closest('.qn-us-date-wrap') : null;
        var input = wrapper ? wrapper.querySelector('[data-date-format="us"]') : null;
        if (!wrapper || !input || input.disabled) {
            return;
        }
        var selectedIso = normalizeDateForStorage(input.value);
        var selectedDate = isoToLocalDate(selectedIso) || new Date();
        usDatePickerContext = {
            wrapper: wrapper,
            input: input,
            selectedIso: isoToLocalDate(selectedIso) ? selectedIso : '',
            viewYear: selectedDate.getFullYear(),
            viewMonth: selectedDate.getMonth()
        };
        var trigger = wrapper.querySelector('[data-us-date-trigger]');
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'true');
        }
        renderUsDatePicker();
    }

    function selectUsDate(isoValue) {
        if (!usDatePickerContext || !usDatePickerContext.input) {
            return;
        }
        var input = usDatePickerContext.input;
        input.value = isoValue ? formatDateForDisplay(isoValue) : '';
        input.setCustomValidity('');
        closeUsDatePicker();
        input.dispatchEvent(new Event('input', {bubbles: true}));
        input.dispatchEvent(new Event('change', {bubbles: true}));
        input.focus();
    }

    function questionPlaceholder(question) {
        var placeholders = {
            hospital_name: 'Enter hospital name',
            hospital_city: 'Enter city',
            licensed_beds: 'Enter licensed beds',
            acute_beds: 'Enter acute beds',
            swing_beds: 'Enter licensed swing beds',
            ccn: 'Enter CMS Certification Number',
            system_network_name: 'Enter system or network name',
            quality_leader_name: 'Enter Quality Leader name',
            quality_leader_email: 'Enter Quality Leader email',
            quality_director_name: 'Enter Quality Leader name',
            quality_director_background: 'Example: RN with 10 years in quality, CPHQ certified, previously infection prevention lead.'
        };
        return placeholders[question.question_key] || '';
    }

    function stepOneHelpText(question) {
        if (question.question_key === 'licensed_beds' || question.question_key === 'swing_beds') {
            return null;
        }
        if (question.question_key === 'system_network_name') {
            return 'Scout uses this to understand reporting, committee, and system-level context.';
        }
        if (question.question_key === 'quality_director_background') {
            return 'Scout uses this to calibrate guidance level and learning support.';
        }
        if (question.question_key === 'acute_beds') {
            return 'For non-CAH hospitals only. Critical Access Hospitals do not need to split the 25 licensed beds into acute beds.';
        }
        if (question.question_key === 'licensed_for_swing_beds') {
            return 'If yes, enter how many of the 25 licensed beds are licensed for swing-bed use.';
        }
        return '';
    }

    function stepTwoPlaceholder(key) {
        var placeholders = {
            state_survey_agency: 'Example: California Department of Public Health',
            state_survey_agency_url: 'Enter state survey body website',
            life_safety_survey_agency: 'Example: State Fire Marshal',
            life_safety_survey_agency_url: 'Enter life safety agency website',
            accrediting_body_other: 'Enter accreditation body'
        };
        return placeholders[key] || '';
    }

    function stepOneOptions(key) {
        var apiOptions = questionOptionsForKey(key);
        if (apiOptions.length) {
            return apiOptions;
        }
        var optionMap = {
            is_critical_access_hospital: [
                {value: 'yes', label: 'Yes'},
                {value: 'no', label: 'No'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            licensed_for_swing_beds: [
                {value: 'yes', label: 'Yes'},
                {value: 'no', label: 'No'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            independent_or_system: [
                {value: 'independent', label: 'Independent'},
                {value: 'system_owned', label: 'System-Owned'},
                {value: 'network_affiliated', label: 'Network-Affiliated'},
                {value: 'other', label: 'Other'}
            ],
            time_in_current_role: [
                {value: 'less_than_one_year', label: 'Less than one year'},
                {value: 'one_to_5_years', label: '1 - 5 years'},
                {value: 'six_to_10_years', label: '6 - 10 years'},
                {value: 'more_than_10_years', label: 'More than 10 years'}
            ]
        };
        return optionMap[key] || [];
    }

    function renderStepOneSelect(key, value, options, placeholder) {
        value = fieldValue(value);
        return '<select data-onboarding-field="' + escapeHtml(key) + '"><option value="">' + escapeHtml(placeholder || 'Select') + '</option>' + options.map(function (option) {
            var optionValueText = optionValue(option);
            return '<option value="' + escapeFieldValue(optionValueText) + '"' + (value === optionValueText ? ' selected' : '') + '>' + escapeHtml(optionLabel(option)) + '</option>';
        }).join('') + '</select>';
    }

    function stepTwoOptions(key) {
        var apiOptions = questionOptionsForKey(key);
        if (apiOptions.length) {
            return apiOptions;
        }
        var optionMap = {
            survey_compliance_process: [
                {value: 'direct_cms_state_survey', label: 'Direct certification through a triennial CMS survey, conducted by our state survey body on behalf of CMS'},
                {value: 'deemed_accreditation_body_survey', label: 'Deemed status through a triennial accreditation body survey, such as The Joint Commission'}
            ],
            accreditation_status: [
                {value: 'accredited', label: 'Accredited'},
                {value: 'cms_state_survey_only', label: 'CMS/state survey only'},
                {value: 'not_accredited', label: 'Not accredited'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            accrediting_body: [
                {value: 'joint_commission', label: 'The Joint Commission'},
                {value: 'dnv', label: 'DNV'},
                {value: 'hfap_hqic', label: 'HFAP / HQIC'},
                {value: 'other', label: 'Other'}
            ],
            life_safety_survey_agency_status: [
                {value: 'same_as_state_survey_agency', label: 'Same as state survey agency'},
                {value: 'different_agency', label: 'Different agency'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            other_certification_licensing_surveys_status: [
                {value: 'yes', label: 'Yes'},
                {value: 'no', label: 'No'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            cms_certification_pathway: [
                {value: 'cms_state_survey', label: 'CMS certification through state survey agency'},
                {value: 'accreditor_deeming_authority', label: 'Accreditation with deeming authority'},
                {value: 'not_sure', label: 'Not sure'},
                {value: 'not_applicable', label: 'Not applicable'}
            ],
            open_plans_of_correction: [
                {value: 'no', label: 'No open POCs'},
                {value: 'yes', label: 'Yes, active POC'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            projected_next_survey_window: [
                {value: 'next_6_months', label: 'Next 6 months'},
                {value: 'six_to_12_months', label: '6-12 months'},
                {value: 'twelve_to_24_months', label: '12-24 months'},
                {value: 'twentyfour_plus_months', label: '24+ months'},
                {value: 'unknown', label: 'Unknown'}
            ],
            historical_deficiency_areas: [
                {value: 'medication_management', label: 'Medication management'},
                {value: 'infection_prevention', label: 'Infection prevention'},
                {value: 'physical_environment_life_safety', label: 'Physical environment / Life Safety'},
                {value: 'qapi_governance', label: 'QAPI / Governance'},
                {value: 'medical_staff_credentialing', label: 'Medical staff / Credentialing'},
                {value: 'nursing_services', label: 'Nursing services'},
                {value: 'emergency_preparedness', label: 'Emergency preparedness'},
                {value: 'patient_rights', label: 'Patient rights'},
                {value: 'records_documentation', label: 'Records / Documentation'},
                {value: 'dietary_nutrition', label: 'Dietary / Nutrition'},
                {value: 'pharmacy', label: 'Pharmacy'},
                {value: 'laboratory_radiology', label: 'Laboratory / Radiology'},
                {value: 'other', label: 'Other'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            current_readiness_activities: [
                {value: 'environment_of_care_rounds', label: 'Environment of care rounds'},
                {value: 'mock_tracers', label: 'Mock tracers'},
                {value: 'policy_review_rotation', label: 'Policy review rotation'},
                {value: 'log_spot_checks', label: 'Log spot-checks'},
                {value: 'high_risk_record_reviews', label: 'High-risk record reviews'},
                {value: 'emergency_drills', label: 'Emergency drills'},
                {value: 'staff_education', label: 'Staff education'},
                {value: 'leadership_gap_assessment', label: 'Leadership gap assessment'},
                {value: 'external_mock_survey', label: 'External mock survey'},
                {value: 'none_currently', label: 'None currently'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            survey_type: [
                {value: 'accreditation_survey', label: 'Accreditation survey'},
                {value: 'cms_recertification_survey', label: 'CMS/state certification survey'},
                {value: 'state_licensure_survey', label: 'State licensure survey'},
                {value: 'complaint_survey', label: 'Complaint survey'},
                {value: 'life_safety_code_survey', label: 'Life Safety Code survey'},
                {value: 'focused_review', label: 'Focused review'},
                {value: 'mock_survey', label: 'Mock survey'},
                {value: 'other', label: 'Other'}
            ],
            survey_outcome: [
                {value: 'no_findings', label: 'No findings'},
                {value: 'findings_closed', label: 'Findings closed'},
                {value: 'poc_open', label: 'Plan of correction open'},
                {value: 'follow_up_pending', label: 'Follow-up pending'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            poc_status: [
                {value: 'not_applicable', label: 'Not applicable'},
                {value: 'closed', label: 'Closed'},
                {value: 'open', label: 'Open'},
                {value: 'pending_submission', label: 'Pending submission'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            follow_up_window: [
                {value: 'none_expected', label: 'No follow-up expected'},
                {value: 'next_30_days', label: 'Next 30 days'},
                {value: 'next_90_days', label: 'Next 90 days'},
                {value: 'next_6_months', label: 'Next 6 months'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            accreditation_360: [
                {value: 'yes', label: 'Yes'},
                {value: 'no', label: 'No'},
                {value: 'not_sure', label: 'Not sure'},
                {value: 'not_applicable', label: 'Not applicable'}
            ]
        };
        return optionMap[key] || [];
    }

    function legacyStepTwoValue(key, value) {
        var map = {
            survey_compliance_process: {
                'Direct certification through triennial CMS/state survey': 'direct_cms_state_survey',
                'Direct certification through a triennial CMS survey, conducted by our state survey body on behalf of CMS': 'direct_cms_state_survey',
                'Deemed status through accreditation body survey': 'deemed_accreditation_body_survey',
                'Deemed status through a triennial accreditation body survey, such as The Joint Commission': 'deemed_accreditation_body_survey'
            },
            accreditation_status: {
                cms_certified: 'cms_state_survey_only',
                in_progress: 'not_sure'
            },
            accrediting_body: {
                'The Joint Commission': 'joint_commission',
                'joint_commission': 'joint_commission',
                DNV: 'dnv',
                dnv: 'dnv',
                HFAP: 'hfap_hqic',
                'HFAP / HQIC': 'hfap_hqic',
                hfap: 'hfap_hqic',
                hfap_hqic: 'hfap_hqic',
                CIHQ: 'cihq',
                cihq: 'cihq',
                ACHC: 'other',
                'State/CMS': 'not_applicable',
                Other: 'other',
                other: 'other'
            },
            open_plans_of_correction: {
                yes: 'yes',
                no: 'no',
                not_sure: 'not_sure'
            }
        };
        value = fieldValue(value);
        return map[key] && map[key][value] ? map[key][value] : value;
    }

    function renderStepTwoQuestion(question) {
        var value = onboardingQuestionValue(question);
        var required = question.is_required ? ' <span class="qn-required">*</span>' : '';
        var helpText = stepTwoHelpText(question);
        var help = helpText ? '<small>' + escapeHtml(helpText) + '</small>' : '';
        var info = questionInfoIcon(question);
        var tag = ['survey_compliance_process', 'historical_deficiency_areas', 'current_readiness_activities', 'survey_history'].indexOf(question.question_key) !== -1 ? 'div' : 'label';
        var hidden = stepTwoQuestionHiddenOnRender(question.question_key) ? ' hidden style="display:none"' : '';
        return '<' + tag + ' class="qn-question ' + questionLayoutClass(question) + '" data-question="' + escapeHtml(question.question_key) + '"' + hidden + '>' +
            '<span class="qn-question-label">' + escapeHtml(question.label) + required + info + '</span>' + renderStepTwoField(question, value) + help + renderStepTwoConditionalNote(question.question_key) + '</' + tag + '>';
    }

    function renderStepTwoField(question, value) {
        var key = question.question_key;
        if (key === 'survey_compliance_process') {
            return renderSurveyPathwayCards(key, legacyStepTwoValue(key, value), stepTwoOptions(key));
        }
        if (['accreditation_status', 'accrediting_body', 'cms_certification_pathway', 'open_plans_of_correction', 'projected_next_survey_window', 'life_safety_survey_agency_status', 'other_certification_licensing_surveys_status'].indexOf(key) !== -1) {
            var placeholders = {
                accreditation_status: 'Select accreditation status',
                accrediting_body: 'Select accreditation body',
                cms_certification_pathway: 'Select pathway',
                open_plans_of_correction: 'Select POC status',
                projected_next_survey_window: 'Select survey window',
                life_safety_survey_agency_status: 'Select agency relationship',
                other_certification_licensing_surveys_status: 'Select'
            };
            return renderStepOneSelect(key, legacyStepTwoValue(key, value), stepTwoOptions(key), placeholders[key]);
        }
        if (key === 'historical_deficiency_areas' || key === 'current_readiness_activities') {
            if (key === 'current_readiness_activities') {
                return renderInlineChecklistField(key, value, stepTwoOptions(key));
            }
            return renderMultiselectField(key, value, stepTwoOptions(key), key === 'historical_deficiency_areas' ? 'Select deficiency areas' : 'Select readiness activities');
        }
        if (key === 'survey_history') {
            return renderSurveyHistoryRepeater(key, value);
        }
        if (key === 'accreditation_360') {
            return renderStepOneSelect(key, value, stepTwoOptions(key), 'Select Accreditation 360 status');
        }
        if (key === 'other_certification_licensing_surveys') {
            return '<textarea class="qn-compact-textarea" data-onboarding-field="' + escapeHtml(key) + '" placeholder="Specify the survey organization, purpose of survey, frequency, and date of last survey. Example: CLIA survey, lab certification, every two years, last survey 03/2025. Do not paste survey reports.">' + escapeFieldValue(value) + '</textarea>';
        }
        if (key === 'last_accreditation_licensing_survey_date') {
            return renderUsDateInput('data-onboarding-field="' + escapeHtml(key) + '"', value);
        }
        return '<input type="text" data-onboarding-field="' + escapeHtml(key) + '" value="' + escapeFieldValue(value) + '" placeholder="' + escapeHtml(stepTwoPlaceholder(key) || 'Enter agency name') + '">';
    }

    function renderSurveyPathwayCards(key, value, options) {
        value = fieldValue(value);
        var display = {
            direct_cms_state_survey: {
                title: 'CMS / state survey',
                description: 'Direct certification through a triennial CMS survey conducted by the state survey body on behalf of CMS.'
            },
            deemed_accreditation_body_survey: {
                title: 'Accreditation / deemed status',
                description: 'Deemed status through a triennial accreditation body survey, such as The Joint Commission.'
            }
        };
        return '<div class="qn-survey-pathway-cards" role="radiogroup" aria-label="Survey compliance process">' + options.map(function (option) {
            var optionValueText = optionValue(option);
            var meta = display[optionValueText] || {title: optionLabel(option), description: ''};
            var checked = value === optionValueText;
            return '<label class="qn-survey-pathway-card' + (checked ? ' qn-survey-pathway-selected' : '') + '">' +
                '<input type="radio" name="' + escapeHtml(key) + '" data-onboarding-field="' + escapeHtml(key) + '" value="' + escapeFieldValue(optionValueText) + '"' + (checked ? ' checked' : '') + '>' +
                '<span class="qn-survey-pathway-marker" aria-hidden="true"></span>' +
                '<span class="qn-survey-pathway-copy"><strong>' + escapeHtml(meta.title) + '</strong><small>' + escapeHtml(meta.description || optionLabel(option)) + '</small></span>' +
            '</label>';
        }).join('') + '</div>';
    }

    function stepTwoQuestionHiddenOnRender(key) {
        if (key === 'accrediting_body') {
            return legacyStepTwoValue('survey_compliance_process', onboardingQuestionValue({question_key: 'survey_compliance_process'})) !== 'deemed_accreditation_body_survey';
        }
        if (key === 'accrediting_body_other') {
            return legacyStepTwoValue('accrediting_body', onboardingQuestionValue({question_key: 'accrediting_body'})) !== 'other';
        }
        if (key === 'life_safety_survey_agency' || key === 'life_safety_survey_agency_url') {
            return onboardingQuestionValue({question_key: 'life_safety_survey_agency_status'}) !== 'different_agency';
        }
        if (key === 'other_certification_licensing_surveys') {
            return onboardingQuestionValue({question_key: 'other_certification_licensing_surveys_status'}) !== 'yes';
        }
        return false;
    }

    function stepTwoHelpText(question) {
        var help = {};
        return help[question.question_key] || question.help_text || '';
    }

    function renderStepTwoConditionalNote(key) {
        return '';
    }

    function normalizeChecklistValues(value, options) {
        if (Array.isArray(value)) {
            return value.map(fieldValue).filter(Boolean);
        }
        value = fieldValue(value);
        if (!value) {
            return [];
        }
        var optionValues = options.map(optionValue);
        if (optionValues.indexOf(value) !== -1) {
            return [value];
        }
        return [value];
    }

    function optionLabelByValue(options, value) {
        var found = options.find(function (option) {
            return optionValue(option) === value;
        });
        return found ? optionLabel(found) : value;
    }

    function multiselectOptionsForKey(key) {
        if (['historical_deficiency_areas', 'current_readiness_activities'].indexOf(key) !== -1) {
            return stepTwoOptions(key);
        }
        if (['surgery_procedure_types', 'radiology_model', 'anesthesia_moderate_sedation_model'].indexOf(key) !== -1) {
            return stepThreeOptions(key);
        }
        if (key === 'mbqip_measure_set') {
            return stepFourOptions(key);
        }
        if (['templates_needed', 'weakest_monitoring_areas'].indexOf(key) !== -1) {
            return stepFiveOptions(key);
        }
        if (['quality_certifications', 'learning_format_preference'].indexOf(key) !== -1) {
            return stepSevenOptions(key);
        }
        if (['monitored_sources', 'current_tools'].indexOf(key) !== -1) {
            return stepEightOptions(key);
        }
        return [];
    }

    function renderMultiselectField(key, value, options, placeholder) {
        var values = normalizeChecklistValues(value, options);
        var optionValues = options.map(optionValue);
        var customValues = values.filter(function (item) { return optionValues.indexOf(item) === -1; });
        var chips = values.map(function (item) {
            return '<span class="qn-selected-chip" data-chip-value="' + escapeFieldValue(item) + '">' + escapeHtml(optionLabelByValue(options, item)) + '<button class="qn-chip-remove" type="button" data-multiselect-remove="' + escapeFieldValue(item) + '" aria-label="Remove ' + escapeHtml(optionLabelByValue(options, item)) + '"><span class="dashicons dashicons-no-alt"></span></button></span>';
        }).join('');
        return '<div class="qn-multiselect" data-checklist="' + escapeHtml(key) + '">' +
            '<button class="qn-multiselect-trigger" type="button" data-multiselect-trigger aria-expanded="false"><span data-multiselect-placeholder>' + escapeHtml(placeholder) + '</span><span class="dashicons dashicons-arrow-down-alt2"></span></button>' +
            '<div class="qn-selected-chips"' + (values.length ? '' : ' hidden') + '>' + chips + '</div>' +
            '<div class="qn-multiselect-menu" role="listbox" aria-label="' + escapeHtml(placeholder) + '">' + options.map(function (option) {
                var optionValueText = optionValue(option);
                return '<label class="qn-multiselect-option"><input type="checkbox" data-checklist-field="' + escapeHtml(key) + '" value="' + escapeFieldValue(optionValueText) + '"' + (values.indexOf(optionValueText) !== -1 ? ' checked' : '') + '><span>' + escapeHtml(optionLabel(option)) + '</span></label>';
            }).join('') + customValues.map(function (item) {
                return '<input type="checkbox" data-checklist-field="' + escapeHtml(key) + '" value="' + escapeFieldValue(item) + '" checked hidden>';
            }).join('') + '</div>' +
            '</div>';
    }

    function renderSurveyHistoryRepeater(key, value) {
        value = Array.isArray(value) ? value : [];
        var columns = ['survey_date', 'survey_type', 'surveying_agency', 'survey_outcome', 'poc_status', 'follow_up_window'];
        return '<div class="qn-repeater qn-survey-history" data-repeater="' + escapeHtml(key) + '" data-repeater-style="survey-history" data-columns="' + escapeHtml(JSON.stringify(columns)) + '">' +
            '<div class="qn-data-boundary-note"><span class="dashicons dashicons-shield"></span><span>Enter high-level, non-sensitive survey information only. Do not upload or paste survey reports, deficiency narratives, patient details, case-level information, or PHI.</span></div>' +
            (value.length ? '' : '<div class="qn-survey-empty"><strong>No accreditation or survey history added yet.</strong><span>Add the most recent review if available. Do not enter deficiency narratives, patient details, or case-level information.</span></div>') +
            value.map(function (row, index) {
                return renderSurveyHistoryRow(key, columns, row, index);
            }).join('') + '<button class="qn-button qn-button-small qn-add-survey-row" type="button" data-add-repeater="' + escapeHtml(key) + '"><span class="dashicons dashicons-plus-alt2"></span>Add survey / review</button></div>';
    }

    function renderSurveyHistoryRow(key, columns, row, index) {
        row = row || {};
        var legacyPocText = row.poc_closed_date || row.poc_due_followup || '';
        var inferredPocStatus = row.poc_status || (legacyPocText ? (String(legacyPocText).toLowerCase().indexOf('active') !== -1 ? 'open' : 'closed') : '');
        return '<div class="qn-repeater-row qn-survey-history-row">' +
            '<div class="qn-survey-card-header"><strong>Survey / review ' + (index + 1) + '</strong><button class="qn-icon-button qn-delete-survey-row" type="button" data-delete-repeater-row aria-label="Delete survey history row"><span class="dashicons dashicons-trash"></span></button></div>' +
            '<div class="qn-survey-card-grid">' +
                '<label><span>Last date</span>' + renderUsDateInput('data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="survey_date"', row.survey_date || '') + '</label>' +
                '<label><span>Type</span>' + renderRepeaterSelect(key, index, 'survey_type', row.survey_type || '', stepTwoOptions('survey_type'), 'Select type') + '</label>' +
                '<label><span>Agency</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="surveying_agency" value="' + escapeFieldValue(row.surveying_agency || '') + '" placeholder="State agency, CMS, accreditor, or reviewer"></label>' +
                '<label><span>Outcome</span>' + renderRepeaterSelect(key, index, 'survey_outcome', row.survey_outcome || '', stepTwoOptions('survey_outcome'), 'Select outcome') + '</label>' +
                '<label><span>POC status</span>' + renderRepeaterSelect(key, index, 'poc_status', inferredPocStatus, stepTwoOptions('poc_status'), 'Select POC status') + '</label>' +
                '<label><span>Follow-up</span>' + renderRepeaterSelect(key, index, 'follow_up_window', row.follow_up_window || '', stepTwoOptions('follow_up_window'), 'Select timing') + '</label>' +
            '</div>' +
            '</div>';
    }

    function renderInlineChecklistField(key, value, options) {
        var values = normalizeChecklistValues(value, options);
        return '<div class="qn-inline-checklist" data-checklist="' + escapeHtml(key) + '">' + options.map(function (option) {
            var optionValueText = optionValue(option);
            var checked = values.indexOf(optionValueText) !== -1;
            return '<label class="qn-inline-checklist-option"><input type="checkbox" data-checklist-field="' + escapeHtml(key) + '" value="' + escapeFieldValue(optionValueText) + '"' + (checked ? ' checked' : '') + '><span>' + escapeHtml(optionLabel(option)) + '</span></label>';
        }).join('') + '</div>';
    }

    function renderStepSevenGoalTiles(key, value, options, limit) {
        var values = normalizeChecklistValues(value, options);
        var optionValues = options.map(optionValue);
        var customOptions = values.filter(function (item) { return optionValues.indexOf(item) === -1; }).map(function (item) {
            return {value: item, label: item};
        });
        var visibleOptions = options.concat(customOptions);
        var selectedCount = values.length;
        var hasLimit = limit && limit > 0;
        var countText = hasLimit ? selectedCount + ' of ' + limit + ' selected' : selectedCount + ' selected';
        var meter = '<div class="qn-step7-goal-meter"><span>' + (hasLimit ? 'Choose up to ' + limit : 'Choose all that apply') + '</span><strong data-goal-count>' + escapeHtml(countText) + '</strong></div>';
        return '<div class="qn-step7-goal-tiles" data-checklist="' + escapeHtml(key) + '"' + (hasLimit ? ' data-goal-limit="' + limit + '"' : '') + '>' + meter +
            '<div class="qn-step7-goal-grid">' + visibleOptions.map(function (option) {
                var optionValueText = optionValue(option);
                var checked = values.indexOf(optionValueText) !== -1;
                var disabled = hasLimit && selectedCount >= limit && !checked;
                return '<label class="qn-step7-goal-tile">' +
                    '<input type="checkbox" data-checklist-field="' + escapeHtml(key) + '" value="' + escapeFieldValue(optionValueText) + '"' + (checked ? ' checked' : '') + (disabled ? ' disabled' : '') + '>' +
                    '<span class="qn-step7-goal-check" aria-hidden="true"></span>' +
                    '<span class="qn-step7-goal-title">' + escapeHtml(optionLabel(option)) + '</span>' +
                '</label>';
            }).join('') + '</div></div>';
    }

    function renderRepeaterSelect(key, index, column, value, options, placeholder) {
        value = fieldValue(value);
        return '<select data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="' + escapeHtml(column) + '"><option value="">' + escapeHtml(placeholder || 'Select') + '</option>' + options.map(function (option) {
            var optionValueText = optionValue(option);
            return '<option value="' + escapeFieldValue(optionValueText) + '"' + (value === optionValueText ? ' selected' : '') + '>' + escapeHtml(optionLabel(option)) + '</option>';
        }).join('') + '</select>';
    }

    function stepThreeOptions(key) {
        var optionMap = {
            service_lines_core: [
                {value: 'emergency_department', label: 'Emergency Department'},
                {value: 'inpatient_acute_care', label: 'Inpatient Acute Care'},
                {value: 'swing_bed_services', label: 'Swing Bed Services'},
                {value: 'observation_services', label: 'Observation Services'},
                {value: 'laboratory_services', label: 'Laboratory Services'},
                {value: 'diagnostic_imaging', label: 'Diagnostic Imaging'},
                {value: 'pharmacy', label: 'Pharmacy'},
                {value: 'respiratory_therapy', label: 'Respiratory Therapy'},
                {value: 'physical_therapy', label: 'Physical Therapy'},
                {value: 'infusion_services', label: 'Infusion Services'},
                {value: 'anesthesia_coverage', label: 'Anesthesia Coverage'},
                {value: 'dietary_services', label: 'Dietary Services'}
            ],
            service_lines_common: [
                {value: 'rural_health_clinic_primary_care', label: 'Rural Health Clinic / Primary Care'},
                {value: 'general_surgery', label: 'General Surgery'},
                {value: 'endoscopy_colonoscopy', label: 'Endoscopy and Colonoscopy'},
                {value: 'obstetrics_labor_delivery', label: 'Obstetrics / Labor and Delivery'},
                {value: 'gynecology', label: 'Gynecology'},
                {value: 'orthopedics', label: 'Orthopedics'},
                {value: 'occupational_therapy', label: 'Occupational Therapy'},
                {value: 'speech_language_pathology', label: 'Speech-Language Pathology'},
                {value: 'cardiac_rehabilitation', label: 'Cardiac Rehabilitation'},
                {value: 'pulmonary_rehabilitation', label: 'Pulmonary Rehabilitation'},
                {value: 'sleep_studies', label: 'Sleep Studies'},
                {value: 'wound_care', label: 'Wound Care'},
                {value: 'visiting_specialist_clinics', label: 'Visiting Specialist Clinics'},
                {value: 'telehealth_services', label: 'Telehealth Services'},
                {value: 'ambulance_ems', label: 'Ambulance and EMS'},
                {value: 'occupational_health_services', label: 'Occupational Health Services'},
                {value: 'diabetes_education', label: 'Diabetes Education'},
                {value: 'nutrition_counseling', label: 'Nutrition Counseling'},
                {value: 'mammography', label: 'Mammography'},
                {value: 'bone_density_dexa', label: 'Bone Density Screening (DEXA)'},
                {value: 'echocardiography_cardiac_diagnostics', label: 'Echocardiography / Cardiac Diagnostics'}
            ],
            service_lines_growth_expansion: [
                {value: 'behavioral_health_integration', label: 'Behavioral Health Integration'},
                {value: 'senior_behavioral_health_unit', label: 'Senior Behavioral Health Unit'},
                {value: 'skilled_nursing_long_term_care', label: 'Skilled Nursing / Long-Term Care'},
                {value: 'retail_340b_contract_pharmacy', label: 'Retail or 340B Contract Pharmacy'},
                {value: 'oncology_chemotherapy', label: 'Oncology / Chemotherapy'},
                {value: 'dialysis', label: 'Dialysis'},
                {value: 'pain_management', label: 'Pain Management'},
                {value: 'specialty_clinic_expansion', label: 'Specialty Clinic Expansion'},
                {value: 'home_health_hospice_partnerships', label: 'Home Health / Hospice Partnerships'},
                {value: 'other_growth_service', label: 'Other Growth Service'}
            ],
            surgery_procedure_types: [
                {value: 'endoscopy', label: 'Endoscopy'},
                {value: 'general_surgery', label: 'General surgery'},
                {value: 'orthopedics', label: 'Orthopedics'},
                {value: 'pain_management_injections', label: 'Pain management injections'},
                {value: 'cardiac_catheterization', label: 'Cardiac catheterization'},
                {value: 'other', label: 'Other'}
            ],
            laboratory_model: [
                {value: 'on_site_routine_lab', label: 'On-site routine lab'},
                {value: 'on_site_plus_reference_lab', label: 'On-site lab plus reference lab for complex testing'},
                {value: 'reference_lab_only', label: 'Reference lab only'},
                {value: 'not_offered', label: 'Not offered'},
                {value: 'other', label: 'Other'}
            ],
            radiology_model: [
                {value: 'plain_film_on_site', label: 'Plain film on-site'},
                {value: 'ct_on_site', label: 'CT on-site'},
                {value: 'ultrasound_on_site', label: 'Ultrasound on-site'},
                {value: 'mri_on_site', label: 'MRI on-site'},
                {value: 'teleradiology_interpretation', label: 'Teleradiology interpretation'},
                {value: 'not_offered', label: 'Not offered'},
                {value: 'other', label: 'Other'}
            ],
            pharmacy_model: [
                {value: 'on_site_pharmacist_24_7', label: 'On-site pharmacist 24/7'},
                {value: 'on_site_pharmacist_limited_hours', label: 'On-site pharmacist limited hours'},
                {value: 'remote_order_verification', label: 'Remote order verification'},
                {value: 'contracted_pharmacy', label: 'Contracted pharmacy'},
                {value: 'consulting_pharmacist_visits', label: 'Consulting pharmacist visits'},
                {value: 'other', label: 'Other'}
            ],
            anesthesia_moderate_sedation_model: [
                {value: 'crna_on_staff', label: 'CRNA on staff'},
                {value: 'contracted_crna_coverage', label: 'Contracted CRNA coverage'},
                {value: 'anesthesiologist_coverage', label: 'Anesthesiologist coverage'},
                {value: 'moderate_sedation_credentialed_providers', label: 'Moderate sedation by credentialed providers'},
                {value: 'most_surgical_patients_transferred', label: 'Most surgical patients transferred'},
                {value: 'not_applicable', label: 'Not applicable'},
                {value: 'other', label: 'Other'}
            ],
            blood_bank_model: [
                {value: 'on_site_blood_bank', label: 'On-site blood bank'},
                {value: 'regional_blood_center_supply', label: 'Regional blood center supply'},
                {value: 'no_blood_products_on_site', label: 'No blood products on site'},
                {value: 'other', label: 'Other'}
            ]
        };
        if (key.indexOf('service_lines_') === 0 && optionMap[key]) {
            return optionMap[key];
        }
        var apiOptions = questionOptionsForKey(key);
        if (apiOptions.length) {
            return apiOptions;
        }
        return optionMap[key] || [];
    }

    function renderStepThreeQuestion(question) {
        var value = onboardingQuestionValue(question);
        var required = question.is_required ? ' <span class="qn-required">*</span>' : '';
        var selectionHint = clinicalModelSelectionHint(question.question_key);
        var helpText = stepThreeHelpText(question);
        var help = helpText ? '<small>' + escapeHtml(helpText) + '</small>' : '';
        var info = question.question_key === 'laboratory_model_other'
            ? fieldInfoIcon('Use this only for additional laboratory models or arrangements not listed above. Enter one per line.')
            : (question.question_key === 'service_lines_other'
                ? fieldInfoIcon('Use this only for additional services not listed above. Enter one service per line.')
                : '');
        var tag = question.question_key.indexOf('service_lines_') === 0 || ['laboratory_model', 'radiology_model', 'pharmacy_model', 'anesthesia_moderate_sedation_model', 'blood_bank_model', 'surgery_procedure_types'].indexOf(question.question_key) !== -1 ? 'div' : 'label';
        return '<' + tag + ' class="qn-question ' + questionLayoutClass(question) + '" data-question="' + escapeHtml(question.question_key) + '">' +
            '<span class="qn-question-label">' + escapeHtml(question.label) + (selectionHint ? ' <span class="qn-selection-hint">(' + escapeHtml(selectionHint) + ')</span>' : '') + required + info + '</span>' + renderStepThreeField(question, value) + help + renderStepThreeConditionalNote(question.question_key) + '</' + tag + '>';
    }

    function clinicalModelSelectionHint(key) {
        if (['laboratory_model', 'pharmacy_model', 'blood_bank_model'].indexOf(key) !== -1) {
            return 'Select one';
        }
        if (key === 'radiology_model' || key === 'anesthesia_moderate_sedation_model') {
            return 'Select all that apply';
        }
        return '';
    }

    function renderStepThreeField(question, value) {
        var key = question.question_key;
        if (key.indexOf('service_lines_') === 0 && question.field_type === 'multiselect') {
            return renderInlineChecklistField(key, value, stepThreeOptions(key));
        }
        if (key === 'radiology_model' || key === 'anesthesia_moderate_sedation_model') {
            return renderClinicalModelChecklist(key, value, stepThreeOptions(key));
        }
        if (['laboratory_model', 'pharmacy_model', 'blood_bank_model'].indexOf(key) !== -1) {
            return renderClinicalModelChoices(key, value, stepThreeOptions(key));
        }
        if (key === 'service_lines_other') {
            return '<textarea data-onboarding-field="' + escapeHtml(key) + '" placeholder="Enter one additional service per line.">' + escapeFieldValue(value) + '</textarea>';
        }
        if (/_model_other$/.test(key)) {
            return '<textarea data-onboarding-field="' + escapeHtml(key) + '" placeholder="Enter one additional model or arrangement per line.">' + escapeFieldValue(value) + '</textarea>';
        }
        if (key === 'contracted_quality_monitoring_agreements') {
            return '<textarea data-onboarding-field="' + escapeHtml(key) + '" placeholder="Example: Radiology peer review agreement, contracted lab quality reports, telehealth specialist quality reporting.">' + escapeFieldValue(value) + '</textarea>';
        }
        return renderField(question, value);
    }

    function renderClinicalModelChoices(key, value, options) {
        value = fieldValue(value);
        return '<div class="qn-clinical-choice-grid qn-clinical-single-select-grid" role="radiogroup" aria-label="' + escapeHtml(key.replace(/_/g, ' ')) + '">' + options.map(function (option) {
            var optionValueText = optionValue(option);
            var checked = value === optionValueText;
            return '<label class="qn-clinical-choice-option"><input type="checkbox" role="radio" aria-checked="' + (checked ? 'true' : 'false') + '" data-single-select-field="' + escapeHtml(key) + '" data-onboarding-field="' + escapeHtml(key) + '" value="' + escapeFieldValue(optionValueText) + '"' + (checked ? ' checked' : '') + '><span>' + escapeHtml(optionLabel(option)) + '</span></label>';
        }).join('') + '</div>';
    }

    function renderClinicalModelChecklist(key, value, options) {
        var values = normalizeChecklistValues(value, options);
        return '<div class="qn-clinical-choice-grid qn-clinical-checkbox-grid" data-checklist="' + escapeHtml(key) + '">' + options.map(function (option) {
            var optionValueText = optionValue(option);
            var checked = values.indexOf(optionValueText) !== -1;
            return '<label class="qn-clinical-choice-option"><input type="checkbox" data-checklist-field="' + escapeHtml(key) + '" value="' + escapeFieldValue(optionValueText) + '"' + (checked ? ' checked' : '') + '><span>' + escapeHtml(optionLabel(option)) + '</span></label>';
        }).join('') + '</div>';
    }

    function stepThreeHelpText(question) {
        var help = {
            service_lines_other: 'Enter each additional service on a separate line.',
            laboratory_model_other: 'Enter each additional model or arrangement on a separate line.',
            radiology_model_other: 'Enter each additional model or arrangement on a separate line.',
            pharmacy_model_other: 'Enter each additional model or arrangement on a separate line.',
            anesthesia_moderate_sedation_model_other: 'Enter each additional model or arrangement on a separate line.',
            blood_bank_model_other: 'Enter each additional model or arrangement on a separate line.',
            contracted_quality_monitoring_agreements: 'List service-level monitoring agreements only. Do not include patient, provider case, or peer-review details.'
        };
        return help[question.question_key] || question.help_text || '';
    }

    function renderStepThreeConditionalNote(key) {
        if (key === 'surgery_procedure_types' || key === 'anesthesia_moderate_sedation_model') {
            return '<small class="qn-conditional-note qn-surgery-not-offered-note" hidden>Surgery/invasive procedures are marked not offered, so Scout can de-emphasize this area.</small>';
        }
        if (key === 'blood_bank_model') {
            return '<small class="qn-conditional-note" id="qn-blood-not-applicable-note" hidden>With no blood products on site and zero transfusions, Scout can mark blood usage review not applicable.</small>';
        }
        if (key === 'contracted_quality_monitoring_agreements') {
            return '<small class="qn-conditional-note" id="qn-contracted-monitoring-note" hidden>Because visiting specialists are used, Scout can track whether contracted services send quality monitoring data back to the hospital.</small>';
        }
        return '';
    }

    function stepFourOptions(key) {
        var apiOptions = questionOptionsForKey(key);
        if (apiOptions.length) {
            return apiOptions;
        }
        var optionMap = {
            committee_name: [
                {value: 'qapi_committee', label: 'QAPI Committee'},
                {value: 'infection_control_committee', label: 'Infection Control Committee'},
                {value: 'medical_executive_committee', label: 'Medical Executive Committee'},
                {value: 'pharmacy_therapeutics', label: 'Pharmacy & Therapeutics'},
                {value: 'board_quality_committee', label: 'Board Quality Committee'},
                {value: 'full_governing_board', label: 'Full Governing Board'},
                {value: 'safety_environment_of_care', label: 'Safety / Environment of Care'},
                {value: 'utilization_review_committee', label: 'Utilization Review Committee'},
                {value: 'peer_review_credentials', label: 'Peer Review / Credentials'},
                {value: 'quality_safety_committee', label: 'Quality and Safety Committee'},
                {value: 'patient_experience_workgroup', label: 'Patient Experience Workgroup'},
                {value: 'other', label: 'Other'}
            ],
            reports_to: [
                {value: 'qapi_committee', label: 'QAPI Committee'},
                {value: 'medical_executive_committee', label: 'Medical Executive Committee'},
                {value: 'medical_executive_and_governing_board', label: 'Medical Executive Committee and Governing Board'},
                {value: 'board_quality_committee', label: 'Board Quality Committee'},
                {value: 'full_governing_board', label: 'Full Governing Board'},
                {value: 'governing_board', label: 'Governing Board'},
                {value: 'quality_safety_committee', label: 'Quality and Safety Committee'},
                {value: 'other', label: 'Other'}
            ],
            committee_frequency: [
                {value: 'monthly', label: 'Monthly'},
                {value: 'quarterly', label: 'Quarterly'},
                {value: 'annually', label: 'Annually'},
                {value: 'as_needed', label: 'Ad hoc / as needed'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            committee_week_of_month: [
                {value: 'first', label: '1st'},
                {value: 'second', label: '2nd'},
                {value: 'third', label: '3rd'},
                {value: 'fourth', label: '4th'},
                {value: 'last', label: 'Last'},
                {value: 'before_full_board', label: 'Before full Board'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            committee_weekday: [
                {value: 'monday', label: 'Monday'},
                {value: 'tuesday', label: 'Tuesday'},
                {value: 'wednesday', label: 'Wednesday'},
                {value: 'thursday', label: 'Thursday'},
                {value: 'friday', label: 'Friday'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            committee_time: [
                {value: '8am', label: '8:00 AM'},
                {value: '9am', label: '9:00 AM'},
                {value: '10am', label: '10:00 AM'},
                {value: '11am', label: '11:00 AM'},
                {value: '12pm', label: '12:00 PM'},
                {value: '1pm', label: '1:00 PM'},
                {value: '2pm', label: '2:00 PM'},
                {value: '3pm', label: '3:00 PM'},
                {value: '4pm', label: '4:00 PM'},
                {value: '5pm', label: '5:00 PM'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            required_optional: [
                {value: 'required', label: 'Required by regulation/accreditation/bylaws'},
                {value: 'optional_internal', label: 'Optional/internal'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            report_category: [
                {value: 'federal', label: 'Federal'},
                {value: 'state', label: 'State'},
                {value: 'accreditation', label: 'Accreditation'},
                {value: 'internal', label: 'Internal'},
                {value: 'payer', label: 'Payer'},
                {value: 'voluntary', label: 'Voluntary'},
                {value: 'other', label: 'Other'}
            ],
            report_frequency: [
                {value: 'monthly', label: 'Monthly'},
                {value: 'weekly', label: 'Weekly'},
                {value: 'quarterly', label: 'Quarterly'},
                {value: 'annual', label: 'Annual'},
                {value: 'event_triggered', label: 'Event-triggered'},
                {value: 'per_notice_contract', label: 'Per notice / contract'},
                {value: 'other', label: 'Other'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            due_date_rule: [
                {value: 'specific_dates', label: 'Known date(s)'},
                {value: 'day_of_month', label: 'Same day each month'},
                {value: 'before_meeting', label: 'Before a meeting'},
                {value: 'per_external_notice', label: 'When an external notice arrives'},
                {value: 'event_triggered_timeline', label: 'After a triggering event'},
                {value: 'not_sure', label: 'Not sure yet'}
            ],
            report_lead_time: [
                {value: 'one_week', label: '1 week'},
                {value: 'two_weeks', label: '2 weeks'},
                {value: 'three_weeks', label: '3 weeks'},
                {value: 'four_weeks', label: '4 weeks'},
                {value: 'six_weeks', label: '6 weeks'},
                {value: 'custom', label: 'Custom'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            committee_report_lead_time: [
                {value: 'no_lead_time', label: 'No lead time'},
                {value: 'one_week_prior', label: '1 week prior'},
                {value: 'two_weeks_prior', label: '2 weeks prior'},
                {value: 'one_month_prior', label: '1 month prior'},
                {value: 'custom', label: 'Custom'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            yes_no_not_sure: [
                {value: 'yes', label: 'Yes'},
                {value: 'no', label: 'No'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            mbqip_measure_set: [
                {value: 'mbqip_quarterly_measures', label: 'MBQIP quarterly measures'},
                {value: 'mbqip_patient_experience_survey', label: 'MBQIP patient experience survey'},
                {value: 'nhsn_hcp_flu_vaccination', label: 'NHSN HCP flu vaccination'},
                {value: 'nhsn_annual_facility_survey', label: 'NHSN annual facility survey'},
                {value: 'iqr', label: 'IQR'},
                {value: 'oqr', label: 'OQR'},
                {value: 'hcahps', label: 'HCAHPS'},
                {value: 'ecqms', label: 'eCQMs'},
                {value: 'promoting_interoperability', label: 'Promoting Interoperability'},
                {value: 'value_based_purchasing_monitoring', label: 'Value-Based Purchasing monitoring'},
                {value: 'readmissions_reduction_monitoring', label: 'Readmissions Reduction monitoring'},
                {value: 'hac_reduction_monitoring', label: 'HAC Reduction monitoring'},
                {value: 'not_applicable', label: 'Not applicable'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            committee_required_status: [
                {value: 'required', label: 'Required by regulation/accreditation/bylaws'},
                {value: 'optional_internal', label: 'Optional/internal'},
                {value: 'mixed', label: 'Mixed'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            minutes_location: [
                {value: 'sharepoint', label: 'SharePoint'},
                {value: 'policy_management_system', label: 'Policy management system'},
                {value: 'board_portal', label: 'Board portal'},
                {value: 'shared_drive', label: 'Shared drive'},
                {value: 'local_file', label: 'Local file'},
                {value: 'other', label: 'Other'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            board_agenda_timing: [
                {value: 'one_week_before_board', label: '1 week before board meeting'},
                {value: 'two_weeks_before_board', label: '2 weeks before board meeting'},
                {value: 'three_weeks_before_board', label: '3 weeks before board meeting'},
                {value: 'board_packet_deadline', label: 'By board packet deadline'},
                {value: 'at_committee_meeting', label: 'At committee meeting'},
                {value: 'other', label: 'Other'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            month: [
                {value: 'Jan', label: 'Jan'},
                {value: 'Feb', label: 'Feb'},
                {value: 'Mar', label: 'Mar'},
                {value: 'Apr', label: 'Apr'},
                {value: 'May', label: 'May'},
                {value: 'Jun', label: 'Jun'},
                {value: 'Jul', label: 'Jul'},
                {value: 'Aug', label: 'Aug'},
                {value: 'Sep', label: 'Sep'},
                {value: 'Oct', label: 'Oct'},
                {value: 'Nov', label: 'Nov'},
                {value: 'Dec', label: 'Dec'}
            ],
            day_of_month: Array.from({length: 31}, function (_, index) {
                return {value: String(index + 1), label: String(index + 1)};
            }).concat([{value: 'last_day', label: 'Last day of month'}]),
            before_meeting_days: [
                {value: 'one_business_day', label: '1 business day'},
                {value: 'two_business_days', label: '2 business days'},
                {value: 'one_week', label: '1 week'},
                {value: 'two_weeks', label: '2 weeks'}
            ],
            event_timeline: [
                {value: 'within_24_hours', label: 'Within 24 hours'},
                {value: 'within_5_business_days', label: 'Within 5 business days'},
                {value: 'per_state_requirement', label: 'Per state requirement'},
                {value: 'other', label: 'Other'}
            ]
        };
        return optionMap[key] || [];
    }

    function renderDataReportingIntro() {
        return '<section class="qn-cadence-intro" aria-label="Data reporting cadence guidance">' +
            '<div><span class="dashicons dashicons-calendar-alt"></span><div><strong>Data Reporting Cadence</strong><p>Only monitoring and reporting items selected in Step 4 appear here. Confirm where each report is submitted, then add owner, lead time, and backup coverage.</p></div></div>' +
            '<small>Known cadence text is a setup aid only. Current-year due dates should be verified against the linked source when live deadline automation is added.</small>' +
        '</section>';
    }

    function buildReportingRowsFromStepFourSelections() {
        var answers = state.onboarding && state.onboarding.answers ? state.onboarding.answers : {};
        var canonicalRows = Array.isArray(answers.data_hub_reporting_rows) ? answers.data_hub_reporting_rows : [];
        var canonicalRoutes = {};
        var selected = canonicalRows.map(function (row) {
            (Array.isArray(row.setup_routes) ? row.setup_routes : []).forEach(function (route) {
                canonicalRoutes[String(route.question_key || '') + '|' + normalizeReportingValue(route.setup_value)] = true;
            });
            return Object.assign({
                is_reported: '1',
                due_date_rule: '',
                due_dates: '',
                due_date_details: {},
                who_prepares: '',
                backup_preparer: '',
                owner_user_id: 0,
                backup_user_id: 0,
                submit_to_method: '',
                approval_required: '',
                prep_lead_time: '',
                payment_linked: '',
                event_triggered: '',
                source_link: '',
                canonical_source: 'data_hub',
                from_step4: true
            }, row || {});
        });
        var questionLookup = {};
        (state.onboarding && state.onboarding.questions ? state.onboarding.questions : []).forEach(function (question) {
            questionLookup[question.question_key] = question;
        });
        var externalKeys = ['external_reporting_flex_mbqip', 'external_reporting_cms_iqr', 'external_reporting_cms_oqr', 'external_reporting_cms_payment_programs', 'external_reporting_nhsn', 'external_reporting_medicare_pi', 'external_reporting_state_other', 'external_reporting_voluntary', 'external_reporting_other'];
        var internalKeys = ['internal_monitoring_patient_safety_events', 'internal_monitoring_infection_prevention', 'internal_monitoring_medication_safety', 'internal_monitoring_clinical_case_review', 'internal_monitoring_ed_care_transitions', 'internal_monitoring_patient_experience', 'internal_monitoring_other'];
        externalKeys.concat(internalKeys).forEach(function (key) {
            var question = questionLookup[key] || {question_key: key, label: key, options: []};
            var values = normalizeChecklistValues(answers[key], questionOptionsForKey(key));
            values.forEach(function (value) {
                if (canonicalRoutes[key + '|' + normalizeReportingValue(value)]) {
                    return;
                }
                selected.push(buildReportingRowFromSelection(question, value));
            });
        });
        return selected.filter(Boolean);
    }

    function buildReportingRowFromSelection(question, value) {
        var displayLabel = optionLabelByValue(questionOptionsForKey(question.question_key), value);
        var label = canonicalReportingMeasureName(value || displayLabel);
        var key = String(question.question_key || '');
        return {
            report_name: label,
            measure_key: '',
            category: reportCategoryForStepFourKey(key),
            frequency: '',
            due_date_rule: '',
            due_dates: '',
            who_prepares: '',
            backup_preparer: '',
            owner_user_id: 0,
            backup_user_id: 0,
            submit_to_method: '',
            approval_required: '',
            prep_lead_time: '',
            payment_linked: '',
            event_triggered: '',
            source_link: '',
            program_tags: reportingProgramTags(displayLabel),
            canonical_source: '',
            from_step4: true
        };
    }

    function normalizeReportingValue(value) {
        return String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
    }

    function canonicalReportingMeasureName(value) {
        return String(value || '')
            .replace(/\s+\((MBQIP|IQR|OQR|NHSN|Internal|CDC|CMS|Flex|\/|\s)+\)$/i, '')
            .trim();
    }

    function reportingProgramTags(label) {
        var match = String(label || '').match(/\(([^)]+)\)\s*$/);
        if (!match) {
            return '';
        }
        return match[1].split('/').map(function (item) {
            return item.trim();
        }).filter(Boolean).join(', ');
    }

    function reportCategoryForStepFourKey(key) {
        if (key.indexOf('internal_monitoring_') === 0) {
            return 'internal';
        }
        if (key === 'external_reporting_state_other') {
            return 'state';
        }
        if (key === 'external_reporting_voluntary') {
            return 'voluntary';
        }
        if (key === 'external_reporting_cms_payment_programs') {
            return 'payer';
        }
        if (key === 'external_reporting_nhsn') {
            return 'federal';
        }
        if (key === 'external_reporting_medicare_pi') {
            return 'federal';
        }
        return 'federal';
    }

    function renderStepFourQuestion(question) {
        var value = onboardingQuestionValue(question);
        if (question.question_key === 'reporting_obligations') {
            return '<div class="qn-question qn-question-wide" data-question="' + escapeHtml(question.question_key) + '">' + renderStepFourField(question, value) + '</div>';
        }
        var required = question.is_required ? ' <span class="qn-required">*</span>' : '';
        var tag = ['committee_list', 'reporting_obligations', 'mbqip_measure_set'].indexOf(question.question_key) !== -1 ? 'div' : 'label';
        var help = stepFourHelpText(question);
        var label = stepFourDisplayLabel(question);
        return '<' + tag + ' class="qn-question ' + questionLayoutClass(question) + '" data-question="' + escapeHtml(question.question_key) + '">' +
            '<span>' + escapeHtml(label) + required + '</span>' + renderStepFourField(question, value) + (help ? '<small>' + escapeHtml(help) + '</small>' : '') + '</' + tag + '>';
    }

    function stepFourDisplayLabel(question) {
        var labels = {
            mbqip_measure_set: 'MBQIP / CMS reporting programs',
            backup_preparer: 'Default backup preparer',
            report_lead_time: 'Default report lead time',
            board_agenda_timing: 'When are board materials due?'
        };
        return labels[question.question_key] || question.label;
    }

    function renderStepFourField(question, value) {
        var key = question.question_key;
        if (key === 'committee_list') {
            return renderCommitteeRepeater(key, value);
        }
        if (key === 'reporting_obligations') {
            return renderReportingRepeater(key, value);
        }
        if (key === 'committee_required_status') {
            return renderStepOneSelect(key, value, stepFourOptions(key), 'Select committee status');
        }
        if (key === 'mbqip_measure_set') {
            return renderMultiselectField(key, value, stepFourOptions(key), 'Select reporting programs');
        }
        if (key === 'report_lead_time') {
            return renderMeetingPreparationLeadTimeField(key, value);
        }
        if (key === 'backup_preparer') {
            return renderBackupPreparerField(key, value);
        }
        if (key === 'minutes_owner_location') {
            return renderMinutesOwnerLocationField(key, value);
        }
        if (key === 'board_agenda_timing') {
            return renderBoardAgendaTimingField(key, value);
        }
        if (key === 'standing_agenda_items') {
            return '<textarea data-onboarding-field="' + escapeHtml(key) + '" placeholder="List recurring committee agenda items, such as quality dashboard, infection prevention, medication safety, incidents, and policy review.">' + escapeFieldValue(value) + '</textarea>';
        }
        return renderField(question, value);
    }

    function renderMeetingPreparationLeadTimeField(key, value) {
        var answers = state.onboarding && state.onboarding.answers ? state.onboarding.answers : {};
        var customValue = fieldValue(answers.report_lead_time_custom);
        var showCustom = fieldValue(value) === 'custom';
        return renderStepOneSelect(key, value, stepFourOptions(key), 'Select default lead time') +
            '<span class="qn-meeting-prep-custom" data-meeting-prep-custom-default' + (showCustom ? '' : ' hidden') + '>' +
                '<span>Custom preparation lead time <span class="qn-required">*</span></span>' +
                '<input type="text" data-onboarding-field="report_lead_time_custom" data-meeting-prep-custom-input value="' + escapeFieldValue(customValue) + '" placeholder="Example: 10 business days"' + (showCustom ? ' required' : '') + '>' +
            '</span>';
    }

    function stepFourHelpText(question) {
        if (question.question_key === 'mbqip_measure_set') {
            return 'Critical Access Hospitals often use MBQIP. Rural PPS hospitals may track IQR, OQR, HCAHPS, eCQMs, Promoting Interoperability, and value-based program reports.';
        }
        return question.help_text || '';
    }

    function normalizeStructuredValue(value) {
        if (value && typeof value === 'object' && !Array.isArray(value)) {
            return value;
        }
        value = fieldValue(value);
        return value ? {legacy: value} : {};
    }

    function renderMinutesOwnerLocationField(key, value) {
        var data = normalizeStructuredValue(value);
        return '<div class="qn-structured-field qn-minutes-structure">' +
            '<label><span>Minutes owner</span><input type="text" data-structured-field="' + escapeHtml(key) + '" data-structured-key="minutes_owner" value="' + escapeFieldValue(data.minutes_owner || '') + '"></label>' +
            '<label><span>Minutes location</span>' + renderStructuredSelect(key, 'minutes_location', data.minutes_location || '', stepFourOptions('minutes_location'), 'Select location') + '</label>' +
            '<label class="qn-structured-wide"><span>Location details</span><input type="text" data-structured-field="' + escapeHtml(key) + '" data-structured-key="location_details" value="' + escapeFieldValue(data.location_details || '') + '" placeholder="Folder, portal area, or archive details"></label>' +
            (data.legacy ? '<small class="qn-legacy-note">Previous value: ' + escapeHtml(data.legacy) + '</small>' : '') +
            '</div>';
    }

    function renderBoardAgendaTimingField(key, value) {
        var data = normalizeStructuredValue(value);
        var showDetails = data.timing === 'other';
        return '<div class="qn-structured-field">' +
            '<label><span>Timing</span>' + renderStructuredSelect(key, 'timing', data.timing || '', stepFourOptions('board_agenda_timing'), 'Select timing') + '</label>' +
            '<label class="qn-structured-wide" data-board-agenda-details' + (showDetails ? '' : ' hidden') + '><span>Timing details</span><input type="text" data-structured-field="' + escapeHtml(key) + '" data-structured-key="details" value="' + escapeFieldValue(data.details || '') + '" placeholder="Add details if Other is selected"></label>' +
            (data.legacy ? '<small class="qn-legacy-note">Previous value: ' + escapeHtml(data.legacy) + '</small>' : '') +
            '</div>';
    }

    function renderStructuredSelect(key, dataKey, value, options, placeholder) {
        value = fieldValue(value);
        return '<select data-structured-field="' + escapeHtml(key) + '" data-structured-key="' + escapeHtml(dataKey) + '"><option value="">' + escapeHtml(placeholder || 'Select') + '</option>' + options.map(function (option) {
            var optionValueText = optionValue(option);
            return '<option value="' + escapeFieldValue(optionValueText) + '"' + (value === optionValueText ? ' selected' : '') + '>' + escapeHtml(optionLabel(option)) + '</option>';
        }).join('') + '</select>';
    }

    function isCriticalAccessContext() {
        return !!(state.onboarding && (state.onboarding.hospital_type === 'cah' || state.onboarding.hospital_type === 'critical_access_hospital'));
    }

    function renderCommitteeRepeater(key, value) {
        value = buildCommitteeRows(Array.isArray(value) ? value : []);
        var columns = ['committee_name', 'local_name', 'committee_frequency', 'committee_week_of_month', 'committee_weekday', 'committee_time', 'frequency_timing', 'user_role', 'reports_to', 'prep_lead_time', 'prep_lead_time_custom'];
        return '<div class="qn-repeater qn-card-repeater" data-repeater="' + escapeHtml(key) + '" data-repeater-style="committee-card" data-columns="' + escapeHtml(JSON.stringify(columns)) + '">' +
            '<div class="qn-committee-seed-note"><span class="dashicons dashicons-info"></span><p>QualiNav starts with common quality-data committees from the setup guide. Confirm each meeting cadence, report flow, and lead time for this hospital.</p></div>' +
            value.map(function (row, index) {
                return renderCommitteeRow(key, columns, row, index);
            }).join('') + '<button class="qn-button qn-button-small qn-add-survey-row" type="button" data-add-repeater="' + escapeHtml(key) + '"><span class="dashicons dashicons-plus-alt2"></span>Add committee</button></div>';
    }

    function buildCommitteeRows(savedRows) {
        var rows = savedRows.filter(function (row) {
            return row && (row.committee_name || row.local_name || row.frequency_timing || row.committee_frequency || row.committee_week_of_month || row.committee_weekday || row.committee_time || row.user_role || row.reports_to || row.prep_lead_time);
        });
        if (rows.length) {
            return rows.map(normalizeCommitteeTimingRow);
        }
        return [
            {committee_name: 'qapi_committee', committee_frequency: 'monthly', committee_week_of_month: 'second', committee_weekday: 'tuesday', reports_to: 'medical_executive_and_governing_board', prep_lead_time: 'not_sure'},
            {committee_name: 'infection_control_committee', committee_frequency: 'monthly', committee_week_of_month: 'last', committee_weekday: 'tuesday', reports_to: 'qapi_committee', prep_lead_time: 'not_sure'},
            {committee_name: 'medical_executive_committee', committee_frequency: 'monthly', committee_week_of_month: 'third', committee_weekday: 'thursday', reports_to: 'full_governing_board', prep_lead_time: 'not_sure'},
            {committee_name: 'pharmacy_therapeutics', committee_frequency: 'quarterly', reports_to: 'medical_executive_committee', prep_lead_time: 'not_sure'},
            {committee_name: 'board_quality_committee', committee_frequency: 'quarterly', committee_week_of_month: 'before_full_board', reports_to: 'full_governing_board', prep_lead_time: 'not_sure'}
        ];
    }

    function normalizeCommitteeTimingRow(row) {
        row = Object.assign({}, row || {});
        if (row.committee_frequency || row.committee_week_of_month || row.committee_weekday || row.committee_time) {
            return row;
        }
        return Object.assign(row, parseCommitteeTimingText(row.frequency_timing || ''));
    }

    function parseCommitteeTimingText(text) {
        var value = String(text || '').toLowerCase();
        var parsed = {};
        if (value.indexOf('monthly') !== -1) {
            parsed.committee_frequency = 'monthly';
        } else if (value.indexOf('quarterly') !== -1) {
            parsed.committee_frequency = 'quarterly';
        } else if (value.indexOf('annual') !== -1) {
            parsed.committee_frequency = 'annually';
        }
        [
            ['first', 'first'], ['1st', 'first'],
            ['second', 'second'], ['2nd', 'second'],
            ['third', 'third'], ['3rd', 'third'],
            ['fourth', 'fourth'], ['4th', 'fourth'],
            ['last', 'last']
        ].some(function (match) {
            if (value.indexOf(match[0]) !== -1) {
                parsed.committee_week_of_month = match[1];
                return true;
            }
            return false;
        });
        if (value.indexOf('before full board') !== -1) {
            parsed.committee_week_of_month = 'before_full_board';
        }
        ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'].some(function (day) {
            if (value.indexOf(day) !== -1) {
                parsed.committee_weekday = day;
                return true;
            }
            return false;
        });
        ['8am', '9am', '10am', '11am', '12pm', '1pm', '2pm', '3pm', '4pm', '5pm'].some(function (time) {
            if (value.replace(/\s+/g, '').indexOf(time) !== -1 || value.indexOf(time.replace('am', ':00 am').replace('pm', ':00 pm')) !== -1) {
                parsed.committee_time = time;
                return true;
            }
            return false;
        });
        return parsed;
    }

    function committeeTimingSummary(row) {
        row = row || {};
        var frequency = optionLabelByValue(stepFourOptions('committee_frequency'), row.committee_frequency || '');
        var week = optionLabelByValue(stepFourOptions('committee_week_of_month'), row.committee_week_of_month || '');
        var weekday = optionLabelByValue(stepFourOptions('committee_weekday'), row.committee_weekday || '');
        if (!frequency) {
            return row.frequency_timing || '';
        }
        if (row.committee_week_of_month === 'before_full_board') {
            return frequency + ' before full Board';
        }
        var detail = [week, weekday].filter(function (part) {
            return part && part !== 'Not sure';
        }).join(' ');
        return detail ? frequency + ' - ' + detail : frequency;
    }

    function renderCommitteeRow(key, columns, row, index) {
        row = normalizeCommitteeTimingRow(row || {});
        var header = row.local_name || optionLabelByValue(stepFourOptions('committee_name'), row.committee_name || '') || ('Meeting ' + (index + 1));
        var preservedHidden = '';
        if (row.committee_time) {
            preservedHidden += '<input type="hidden" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="committee_time" value="' + escapeFieldValue(row.committee_time) + '">';
        }
        if (row.user_role) {
            preservedHidden += '<input type="hidden" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="user_role" value="' + escapeFieldValue(row.user_role) + '">';
        }
        return '<div class="qn-repeater-row qn-survey-history-row qn-flow-card">' +
            '<div class="qn-survey-card-header"><strong>' + escapeHtml(header) + '</strong><button class="qn-icon-button qn-delete-survey-row" type="button" data-delete-repeater-row aria-label="Delete committee row"><span class="dashicons dashicons-trash"></span></button></div>' +
            '<div class="qn-survey-card-grid qn-flow-card-grid qn-committee-card-grid">' +
                '<label><span>Meeting function</span>' + renderRepeaterSelect(key, index, 'committee_name', row.committee_name || '', stepFourOptions('committee_name'), 'Select function') + '</label>' +
                '<label><span>Local meeting name</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="local_name" value="' + escapeFieldValue(row.local_name || '') + '" placeholder="Example: Quality Council"></label>' +
                '<div class="qn-committee-timing-field"><span>Frequency and timing</span><div class="qn-committee-timing-grid">' +
                    renderRepeaterSelect(key, index, 'committee_frequency', row.committee_frequency || '', stepFourOptions('committee_frequency'), 'Frequency') +
                    renderRepeaterSelect(key, index, 'committee_week_of_month', row.committee_week_of_month || '', stepFourOptions('committee_week_of_month'), 'Week') +
                    renderRepeaterSelect(key, index, 'committee_weekday', row.committee_weekday || '', stepFourOptions('committee_weekday'), 'Day') +
                    '<input type="hidden" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="frequency_timing" value="' + escapeFieldValue(committeeTimingSummary(row)) + '">' +
                    preservedHidden +
                '</div></div>' +
                '<label><span>Reports flow to</span>' + renderRepeaterSelect(key, index, 'reports_to', row.reports_to || '', stepFourOptions('reports_to'), 'Select destination') + '</label>' +
                '<div class="qn-meeting-prep-field"><label><span>Report lead time</span>' + renderRepeaterSelect(key, index, 'prep_lead_time', row.prep_lead_time || '', stepFourOptions('committee_report_lead_time'), 'Select lead time') + '</label>' +
                    '<label class="qn-meeting-prep-custom" data-meeting-prep-custom-row' + (row.prep_lead_time === 'custom' ? '' : ' hidden') + '><span>Custom lead time <span class="qn-required">*</span></span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="prep_lead_time_custom" data-meeting-prep-custom-input value="' + escapeFieldValue(row.prep_lead_time_custom || '') + '" placeholder="Example: 10 business days"' + (row.prep_lead_time === 'custom' ? ' required' : '') + '></label></div>' +
            '</div>' +
            '</div>';
    }

    function renderReportingRepeater(key, value) {
        var rows = mergeReportingCalendarRows(Array.isArray(value) ? value : []);
        var columns = ['is_reported', 'measure_key', 'report_name', 'category', 'program_tags', 'frequency', 'due_date_rule', 'due_date_details', 'due_dates', 'source_link', 'who_prepares', 'backup_preparer', 'owner_user_id', 'backup_user_id', 'submit_to_method', 'approval_required', 'prep_lead_time', 'payment_linked', 'event_triggered', 'measure_version_id', 'measure_version_label', 'effective_start_date', 'effective_end_date', 'canonical_source', 'from_step4'];
        var rowIndex = 0;
        var groupsHtml = rows.map(function (group) {
            var visibleRows = group.rows || [];
            if (!visibleRows.length) {
                return '';
            }
            var groupHtml = '<section class="qn-cadence-group"><div class="qn-cadence-group-title">' + escapeHtml(reportingCalendarGroupTitle(group.title)) + '</div>';
            visibleRows.forEach(function (row) {
                groupHtml += renderReportingCalendarRow(key, row, rowIndex);
                rowIndex++;
            });
            return groupHtml + '</section>';
        }).join('');
        if (!groupsHtml) {
            groupsHtml = '<div class="qn-calendar-empty-state"><span class="dashicons dashicons-info"></span><div><strong>No reporting items selected</strong><p>Select measures in Step 4 to populate this cadence list automatically.</p></div></div>';
        }
        return '<div class="qn-repeater qn-reporting-calendar-repeater" data-repeater="' + escapeHtml(key) + '" data-repeater-style="reporting-calendar" data-columns="' + escapeHtml(JSON.stringify(columns)) + '">' +
            '<div class="qn-reporting-calendar-head" aria-hidden="true"><span></span><span>Measure / Submission</span><span>Reported To / Through</span><span>Reporting Cadence and Due Dates</span></div>' +
            groupsHtml +
            renderReportingOtherRow(key, rowIndex) +
        '</div>';
    }

    function normalizeReportingIdentity(row) {
        return String((row && row.category ? row.category : '') + '|' + (row && row.report_name ? row.report_name : '')).toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
    }

    function normalizeReportingName(row) {
        return String(row && row.report_name ? row.report_name : '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
    }

    function mergeReportingCalendarRows(savedRows) {
        var selectedRows = buildReportingRowsFromStepFourSelections();
        var savedByIdentity = {};
        var savedByName = {};
        var savedByMeasureKey = {};
        savedRows.forEach(function (row) {
            if (!row || !row.report_name) {
                return;
            }
            savedByIdentity[normalizeReportingIdentity(row)] = row;
            savedByName[normalizeReportingName(row)] = row;
            if (row.measure_key) {
                savedByMeasureKey[String(row.measure_key)] = row;
            }
        });
        var grouped = {};
        var seenSelected = {};
        selectedRows.forEach(function (selected) {
            if (!selected || !selected.report_name) {
                return;
            }
            var selectedKey = normalizeReportingName(selected);
            if (seenSelected[selectedKey]) {
                return;
            }
            seenSelected[selectedKey] = true;
            var saved = (selected.measure_key ? savedByMeasureKey[String(selected.measure_key)] : null) || savedByIdentity[normalizeReportingIdentity(selected)] || savedByName[normalizeReportingName(selected)] || {};
            var merged = Object.assign({}, selected, saved, {from_step4: true});
            if (selected.canonical_source === 'data_hub') {
                ['measure_key', 'report_name', 'category', 'program_tags', 'frequency', 'due_dates', 'source_link', 'measure_version_id', 'measure_version_label', 'effective_start_date', 'effective_end_date', 'canonical_source'].forEach(function (field) {
                    merged[field] = selected[field] || '';
                });
                merged.owner_user_id = selected.owner_user_id || 0;
            }
            merged.program_tags = merged.program_tags || selected.program_tags || '';
            merged.is_reported = truthyReportingValue(saved.is_reported) || !saved.report_name ? '1' : (truthyReportingValue(merged.is_reported) ? '1' : '');
            var title = merged.category || reportCategoryForStepFourKey('');
            grouped[title] = grouped[title] || [];
            grouped[title].push(merged);
        });
        var extraRows = savedRows.filter(function (row) {
            return row && row.report_name && truthyReportingValue(row.is_reported) && !row.from_step4 && !savedByName[normalizeReportingName(row)].from_step4;
        });
        if (extraRows.length) {
            grouped['Other / added reporting'] = (grouped['Other / added reporting'] || []).concat(extraRows);
        }
        return Object.keys(grouped).map(function (title) {
            return {title: title, rows: grouped[title]};
        });
    }

    function reportingCalendarGroupTitle(title) {
        var labels = {
            federal: 'External / federal reporting',
            internal: 'Internal monitoring and reporting',
            state: 'State and other mandatory reporting',
            voluntary: 'Voluntary registries and programs',
            payer: 'Payer quality programs'
        };
        return labels[title] || title;
    }

    function truthyReportingValue(value) {
        return value === true || value === 1 || value === '1' || value === 'yes' || value === 'true' || value === 'on';
    }

    function organizationUserOptions() {
        var answers = state.onboarding && state.onboarding.answers ? state.onboarding.answers : {};
        return Array.isArray(answers.organization_user_options) ? answers.organization_user_options : [];
    }

    function backupPreparerUserOptions() {
        var currentUserId = state.me && state.me.user_id ? Number(state.me.user_id) : 0;
        return organizationUserOptions().filter(function (user) {
            var userId = Number(user && user.user_id ? user.user_id : 0);
            return !!userId && (!currentUserId || userId !== currentUserId);
        });
    }

    function renderBackupPreparerField(key, value) {
        var users = backupPreparerUserOptions();
        var savedValue = String(value || '0');
        if (!users.length) {
            return '<div class="qn-backup-preparer-empty" data-backup-preparer-empty>' +
                '<span class="dashicons dashicons-groups"></span>' +
                '<div><strong>No backup users are available yet.</strong>' +
                '<small>You can assign a default backup preparer after adding another hospital user. This does not block Hospital Setup.</small></div>' +
                (savedValue !== '0' ? '<input type="hidden" data-onboarding-field="' + escapeHtml(key) + '" value="' + escapeFieldValue(savedValue) + '">' : '') +
            '</div>';
        }
        return renderOrganizationUserField(key, savedValue, 'Select default backup user', users);
    }

    function renderOrganizationUserSelect(key, index, column, value, placeholder) {
        value = String(value || '0');
        return '<select data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="' + escapeHtml(column) + '">' +
            '<option value="0">' + escapeHtml(placeholder || 'Select hospital user') + '</option>' +
            organizationUserOptions().map(function (user) {
                var userId = String(user.user_id || 0);
                var role = user.role ? ' — ' + String(user.role).replace(/_/g, ' ') : '';
                return '<option value="' + escapeFieldValue(userId) + '"' + (userId === value ? ' selected' : '') + '>' + escapeHtml((user.display_name || ('User #' + userId)) + role) + '</option>';
            }).join('') + '</select>';
    }

    function renderOrganizationUserField(key, value, placeholder, users) {
        value = String(value || '0');
        users = Array.isArray(users) ? users : organizationUserOptions();
        if (!/^\d+$/.test(value)) {
            var legacyMatch = users.find(function (user) {
                return String(user.display_name || '').toLowerCase().trim() === value.toLowerCase().trim();
            });
            value = legacyMatch ? String(legacyMatch.user_id) : '0';
        }
        var selectedAvailable = value === '0' || users.some(function (user) {
            return String(user.user_id || 0) === value;
        });
        var unavailableOption = !selectedAvailable ?
            '<option value="' + escapeFieldValue(value) + '" selected disabled>Previously assigned user (unavailable)</option>' :
            '';
        return '<select data-onboarding-field="' + escapeHtml(key) + '"><option value="0">' + escapeHtml(placeholder || 'Select hospital user') + '</option>' + unavailableOption + users.map(function (user) {
            var userId = String(user.user_id || 0);
            return '<option value="' + escapeFieldValue(userId) + '"' + (userId === value ? ' selected' : '') + '>' + escapeHtml(user.display_name || ('User #' + userId)) + '</option>';
        }).join('') + '</select>';
    }

    function renderReportingCalendarRow(key, row, index) {
        row = row || {};
        var checked = truthyReportingValue(row.is_reported);
        var sourceHtml = row.source_link ? '<span class="qn-cadence-source"><span>Source</span>' + escapeHtml(row.source_link) + '</span>' : '';
        var badge = row.from_step4 ? '<span class="qn-step4-source-badge">Selected in Step 4</span>' : '';
        var programTags = row.program_tags ? '<span class="qn-cadence-program-tags">' + escapeHtml(row.program_tags) + '</span>' : '';
        return '<div class="qn-repeater-row qn-reporting-calendar-row' + (checked ? ' is-selected' : '') + '">' +
            '<label class="qn-cadence-check"><input type="checkbox" data-cadence-report-toggle ' + (checked ? 'checked' : '') + '><span aria-hidden="true"></span></label>' +
            '<div class="qn-cadence-measure"><strong>' + escapeHtml(row.report_name || 'Reporting item') + '</strong>' + programTags + badge + '</div>' +
            '<div class="qn-cadence-through">' + escapeHtml(row.submit_to_method || 'Confirm submission pathway') + '</div>' +
            '<div class="qn-cadence-due">' + renderCadenceControls(key, row, index) + sourceHtml + '</div>' +
            '<div class="qn-cadence-ownership"><label><span>Data owner</span>' + renderOrganizationUserSelect(key, index, 'owner_user_id', row.owner_user_id, 'Select owner') + '</label><label><span>Backup</span>' + renderOrganizationUserSelect(key, index, 'backup_user_id', row.backup_user_id, 'Select backup') + '</label></div>' +
            renderReportingHiddenFields(key, row, index, checked) +
        '</div>';
    }

    function renderCadenceControls(key, row, index) {
        var details = row.due_date_details && typeof row.due_date_details === 'object' && !Array.isArray(row.due_date_details) ? row.due_date_details : {};
        var nextDueDate = details.next_due_date || '';
        var internalOnly = isInternalReportingRow(row);
        return '<div class="qn-cadence-control-grid">' +
            renderCadenceSelect(key, row, index) +
            (internalOnly ? '<small class="qn-muted-note">Internal reporting uses cadence only; no single due date is required.</small>' : '<label class="qn-cadence-date"><span>Due date</span>' + renderUsDateInput('data-repeater-detail="' + escapeHtml(key) + '" data-index="' + index + '" data-detail-path="due_date_details.next_due_date"', nextDueDate) + '</label>' + renderKnownCadenceText(key, row, index)) +
        '</div>';
    }

    function isInternalReportingRow(row) {
        row = row || {};
        var routes = Array.isArray(row.setup_routes) ? row.setup_routes : [];
        var routeKeys = routes.map(function (route) {
            return route && route.question_key ? String(route.question_key) : '';
        }).filter(Boolean);
        if (routeKeys.length) {
            var hasExternalRoute = routeKeys.some(function (key) { return key.indexOf('external_reporting_') === 0; });
            var hasInternalRoute = routeKeys.some(function (key) { return key.indexOf('internal_monitoring_') === 0; });
            return hasInternalRoute && !hasExternalRoute;
        }
        var category = String(row.category || row.program_tags || '').toLowerCase();
        var destination = String(row.submit_to_method || '').toLowerCase();
        return category.indexOf('internal') !== -1 || (!row.measure_key && /committee|board|leadership|internal/.test(destination));
    }

    function renderCadenceSelect(key, row, index) {
        var value = row.frequency || frequencyFromCadenceText(row.due_dates || '');
        var options = cadenceSelectOptions(value);
        return '<label class="qn-cadence-select"><span>Cadence</span><select data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="frequency">' +
            '<option value="">Select cadence</option>' +
            options.map(function (option) {
                return '<option value="' + escapeFieldValue(option.value) + '"' + (option.value === value ? ' selected' : '') + '>' + escapeHtml(optionLabel(option)) + '</option>';
            }).join('') +
        '</select></label>';
    }

    function cadenceSelectOptions(currentValue) {
        var options = stepFourOptions('report_frequency');
        if (currentValue && !options.some(function (option) { return option.value === currentValue; })) {
            options.push({value: currentValue, label: currentValue});
        }
        return options;
    }

    function frequencyFromCadenceText(text) {
        text = String(text || '').toLowerCase();
        if (text.indexOf('weekly') !== -1) {
            return 'weekly';
        }
        if (text.indexOf('monthly') !== -1) {
            return 'monthly';
        }
        if (text.indexOf('quarter') !== -1) {
            return 'quarterly';
        }
        if (text.indexOf('annual') !== -1 || text.indexOf('year') !== -1) {
            return 'annual';
        }
        if (text.indexOf('event') !== -1) {
            return 'event_triggered';
        }
        if (text.indexOf('claims') !== -1 || text.indexOf('notice') !== -1 || text.indexOf('contract') !== -1) {
            return 'per_notice_contract';
        }
        return '';
    }

    function renderKnownCadenceText(key, row, index) {
        if (!fieldValue(row.due_dates || '')) {
            return '';
        }
        return '<small class="qn-known-cadence">Known guidance: ' + escapeHtml(row.due_dates) + '</small>' +
            '<input type="hidden" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="due_dates" value="' + escapeFieldValue(row.due_dates) + '">';
    }

    function renderReportingOtherRow(key, index) {
        return '<div class="qn-repeater-row qn-reporting-calendar-row qn-reporting-other-row">' +
            '<div></div><label class="qn-reporting-other-field"><span>Other reporting</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="report_name" placeholder="Add an item not listed in Step 4"></label>' +
            '<label class="qn-reporting-other-field qn-reporting-other-through"><span>Reported to / through</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="submit_to_method" placeholder="Portal, agency, vendor, or committee"></label>' +
            '<div class="qn-reporting-other-cadence">' + renderCadenceControls(key, {frequency: '', due_date_details: {}}, index) + '</div>' +
            '<div class="qn-cadence-ownership"><label><span>Owner</span>' + renderOrganizationUserSelect(key, index, 'owner_user_id', 0, 'Select owner') + '</label><label><span>Backup</span>' + renderOrganizationUserSelect(key, index, 'backup_user_id', 0, 'Select backup') + '</label></div>' +
            '<input type="hidden" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="category" value="Other reporting not listed above">' +
            '<input type="hidden" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="is_reported" value="1">' +
            '<input type="hidden" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="from_step4" value="">' +
            '</div>';
    }

    function renderReportingHiddenFields(key, row, index, checked) {
        var columns = ['is_reported', 'measure_key', 'report_name', 'category', 'program_tags', 'due_date_rule', 'source_link', 'who_prepares', 'backup_preparer', 'submit_to_method', 'approval_required', 'prep_lead_time', 'payment_linked', 'event_triggered', 'measure_version_id', 'measure_version_label', 'effective_start_date', 'effective_end_date', 'canonical_source', 'from_step4'];
        return columns.map(function (column) {
            var value = row[column] || '';
            if (column === 'is_reported') {
                value = checked ? '1' : '';
            }
            if (column === 'from_step4') {
                value = row.from_step4 ? 'yes' : '';
            }
            if (column === 'due_date_rule' && isInternalReportingRow(row)) {
                value = '';
            }
            return '<input type="hidden" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="' + escapeHtml(column) + '" value="' + escapeFieldValue(value) + '">';
        }).join('');
    }

    function renderReportingRow(key, columns, row, index) {
        row = row || {};
        var sourceHtml = row.source_link ? '<span class="qn-report-source-chip"><span class="dashicons dashicons-admin-links"></span>' + escapeHtml(row.source_link) + '</span>' : '';
        return '<div class="qn-repeater-row qn-survey-history-row qn-flow-card">' +
            '<div class="qn-survey-card-header"><strong>' + escapeHtml(row.report_name || 'Report ' + (index + 1)) + '</strong><div class="qn-report-card-header-actions">' + (row.from_step4 ? '<span class="qn-step4-source-badge">From Step 4</span>' : '') + '<button class="qn-icon-button qn-delete-survey-row" type="button" data-delete-repeater-row aria-label="Delete report row"><span class="dashicons dashicons-trash"></span></button></div></div>' +
            (row.due_dates || sourceHtml ? '<div class="qn-report-cadence-summary">' + (row.due_dates ? '<span>' + escapeHtml(row.due_dates) + '</span>' : '') + sourceHtml + '</div>' : '') +
            '<div class="qn-survey-card-grid qn-flow-card-grid">' +
                '<label><span>Report name</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="report_name" value="' + escapeFieldValue(row.report_name || '') + '"></label>' +
                '<label><span>Category</span>' + renderRepeaterSelect(key, index, 'category', row.category || '', stepFourOptions('report_category'), 'Select category') + '</label>' +
                '<label><span>Frequency</span>' + renderRepeaterSelect(key, index, 'frequency', row.frequency || '', stepFourOptions('report_frequency'), 'Select frequency') + '</label>' +
                renderDueDateRuleFields(key, row, index) +
                '<label><span>Source link or reference</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="source_link" value="' + escapeFieldValue(row.source_link || '') + '" placeholder="Example: HQR submission deadlines"></label>' +
                '<label><span>Owner / preparer</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="who_prepares" value="' + escapeFieldValue(row.who_prepares || '') + '"></label>' +
                '<label><span>Backup preparer</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="backup_preparer" value="' + escapeFieldValue(row.backup_preparer || '') + '"></label>' +
                '<label><span>Submit to / method</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="submit_to_method" value="' + escapeFieldValue(row.submit_to_method || '') + '" placeholder="Example: QualityNet, NHSN, state portal, board packet"></label>' +
                '<label><span>Prep lead time</span>' + renderRepeaterSelect(key, index, 'prep_lead_time', row.prep_lead_time || '', stepFourOptions('report_lead_time'), 'Select lead time') + '</label>' +
                '<label><span>Payment-linked?</span>' + renderRepeaterSelect(key, index, 'payment_linked', row.payment_linked || '', stepFourOptions('yes_no_not_sure'), 'Select') + '</label>' +
                '<label><span>Event-triggered?</span>' + renderRepeaterSelect(key, index, 'event_triggered', row.event_triggered || '', stepFourOptions('yes_no_not_sure'), 'Select') + '</label>' +
                '<input type="hidden" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="from_step4" value="' + escapeFieldValue(row.from_step4 ? 'yes' : '') + '">' +
            '</div>' +
            '</div>';
    }

    function renderDueDateRuleFields(key, row, index) {
        var rule = fieldValue(row.due_date_rule || '');
        var details = row.due_date_details && typeof row.due_date_details === 'object' && !Array.isArray(row.due_date_details) ? row.due_date_details : {};
        var html = '<label class="qn-structured-wide"><span>How is the deadline determined?</span>' + renderRepeaterSelect(key, index, 'due_date_rule', rule, stepFourOptions('due_date_rule'), 'Select deadline method') + '<small>This helps Scout create accurate reminders instead of relying on free-text due dates.</small></label>';
        html += '<div class="qn-due-rule-panel qn-structured-wide" data-due-rule-panel>';
        html += renderDueDateDetails(key, index, row.frequency || '', rule, details);
        if (fieldValue(row.due_dates || '')) {
            html += '<small class="qn-legacy-note">Previous due date text: ' + escapeHtml(row.due_dates) + '</small>';
            html += '<input type="hidden" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="due_dates" value="' + escapeFieldValue(row.due_dates) + '">';
        }
        html += '</div>';
        return html;
    }

    function renderDueDateDetails(key, index, frequency, rule, details) {
        if (rule === 'specific_dates' && frequency === 'quarterly') {
            return ['q1', 'q2', 'q3', 'q4'].map(function (quarter) {
                var quarterData = details[quarter] || {};
                return '<div class="qn-date-pair"><label><span>' + quarter.toUpperCase() + ' due</span>' + renderRepeaterDetailSelect(key, index, 'due_date_details.' + quarter + '.month', quarterData.month || '', stepFourOptions('month'), 'Month') + '</label><label><span>Day</span>' + renderRepeaterDetailSelect(key, index, 'due_date_details.' + quarter + '.day', quarterData.day || '', stepFourOptions('day_of_month'), 'Day') + '</label></div>';
            }).join('');
        }
        if (rule === 'specific_dates' && frequency === 'annual') {
            return '<div class="qn-date-pair"><label><span>Annual due</span>' + renderRepeaterDetailSelect(key, index, 'due_date_details.annual.month', (details.annual || {}).month || '', stepFourOptions('month'), 'Month') + '</label><label><span>Day</span>' + renderRepeaterDetailSelect(key, index, 'due_date_details.annual.day', (details.annual || {}).day || '', stepFourOptions('day_of_month'), 'Day') + '</label></div>';
        }
        if (rule === 'specific_dates') {
            return '<div class="qn-date-pair"><label><span>Due month</span>' + renderRepeaterDetailSelect(key, index, 'due_date_details.default.month', (details.default || {}).month || '', stepFourOptions('month'), 'Month') + '</label><label><span>Due day</span>' + renderRepeaterDetailSelect(key, index, 'due_date_details.default.day', (details.default || {}).day || '', stepFourOptions('day_of_month'), 'Day') + '</label></div>';
        }
        if (rule === 'day_of_month') {
            return '<label><span>Day of month</span>' + renderRepeaterDetailSelect(key, index, 'due_date_details.day_of_month', details.day_of_month || '', stepFourOptions('day_of_month'), 'Select day') + '</label>';
        }
        if (rule === 'before_meeting') {
            return '<div class="qn-date-pair"><label><span>Meeting</span><input type="text" data-repeater-detail="' + escapeHtml(key) + '" data-index="' + index + '" data-detail-path="due_date_details.meeting_reference" value="' + escapeFieldValue(details.meeting_reference || '') + '" placeholder="Example: Board Quality Committee"></label><label><span>How far before?</span>' + renderRepeaterDetailSelect(key, index, 'due_date_details.days_before_meeting', details.days_before_meeting || '', stepFourOptions('before_meeting_days'), 'Select lead time') + '</label></div>';
        }
        if (rule === 'per_external_notice') {
            return '<small class="qn-conditional-note">Scout will create a placeholder reminder and ask you to enter the date when notice is received.</small>';
        }
        if (rule === 'event_triggered_timeline') {
            return '<div class="qn-date-pair"><label><span>Trigger</span><input type="text" data-repeater-detail="' + escapeHtml(key) + '" data-index="' + index + '" data-detail-path="due_date_details.trigger_description" value="' + escapeFieldValue(details.trigger_description || '') + '"></label><label><span>Timeline</span>' + renderRepeaterDetailSelect(key, index, 'due_date_details.timeline', details.timeline || '', stepFourOptions('event_timeline'), 'Select timeline') + '</label></div>';
        }
        if (rule === 'not_sure') {
            return '<small class="qn-muted-note">No extra fields needed now. Scout will remind you to confirm this deadline later.</small>';
        }
        return '<small class="qn-muted-note">Select a frequency and deadline method to structure reminder timing.</small>';
    }

    function renderRepeaterDetailSelect(key, index, path, value, options, placeholder) {
        value = fieldValue(value);
        return '<select data-repeater-detail="' + escapeHtml(key) + '" data-index="' + index + '" data-detail-path="' + escapeHtml(path) + '"><option value="">' + escapeHtml(placeholder || 'Select') + '</option>' + options.map(function (option) {
            var optionValueText = optionValue(option);
            return '<option value="' + escapeFieldValue(optionValueText) + '"' + (value === optionValueText ? ' selected' : '') + '>' + escapeHtml(optionLabel(option)) + '</option>';
        }).join('') + '</select>';
    }

    function stepFiveOptions(key) {
        var apiOptions = questionOptionsForKey(key);
        if (apiOptions.length) {
            return apiOptions;
        }
        var optionMap = {
            plan_policy_status: [{value: 'in_place', label: 'In place'}, {value: 'not_currently_in_place', label: 'Not currently in place'}, {value: 'folded_into_another', label: 'Folded into another plan / policy'}, {value: 'not_sure', label: 'Not sure'}],
            yes_no_not_sure: [{value: 'yes', label: 'Yes'}, {value: 'no', label: 'No'}, {value: 'not_sure', label: 'Not sure'}],
            board_approved: [{value: 'yes', label: 'Yes'}, {value: 'no', label: 'No'}, {value: 'not_required', label: 'Not required'}, {value: 'not_sure', label: 'Not sure'}],
            action_needed: [{value: 'none', label: 'None'}, {value: 'create', label: 'Create'}, {value: 'review_update', label: 'Review/update'}, {value: 'route_for_approval', label: 'Route for approval'}, {value: 'verify_owner_date', label: 'Verify owner/date'}, {value: 'not_sure', label: 'Not sure'}],
            policy_management_system: [{value: 'formal_policy_management_system', label: 'Yes, formal policy management system'}, {value: 'spreadsheet_or_index', label: 'Spreadsheet or index'}, {value: 'shared_drive_folder', label: 'Shared drive / folder'}, {value: 'paper_manual_system', label: 'Paper/manual system'}, {value: 'no_current_system', label: 'No current system'}, {value: 'not_sure', label: 'Not sure'}, {value: 'other', label: 'Other'}],
            annual_policy_review_cycle: [{value: 'annual_review_with_owners', label: 'Yes, annual review cycle with owners'}, {value: 'partial_informal_cycle', label: 'Partial / informal cycle'}, {value: 'no_formal_cycle', label: 'No formal cycle'}, {value: 'not_sure', label: 'Not sure'}],
            templates_needed: [{value: 'qapi_project_charter', label: 'QAPI project charter'}, {value: 'pdsa_worksheet', label: 'PDSA worksheet'}, {value: 'root_cause_analysis_template', label: 'Root cause analysis template'}, {value: 'fmea_template', label: 'FMEA template'}, {value: 'corrective_action_plan_template', label: 'Corrective action plan template'}, {value: 'board_quality_report_template', label: 'Board quality report template'}, {value: 'run_chart_template', label: 'Run chart template'}, {value: 'survey_readiness_checklist', label: 'Survey readiness checklist'}, {value: 'transfer_communication_checklist', label: 'Transfer communication checklist'}, {value: 'sentinel_event_response_protocol', label: 'Sentinel event response protocol'}, {value: 'other', label: 'Other'}, {value: 'not_sure', label: 'Not sure'}],
            applies: [{value: 'yes', label: 'Yes'}, {value: 'no', label: 'No'}, {value: 'not_sure', label: 'Not sure'}, {value: 'not_applicable', label: 'Not applicable'}],
            current_state: [{value: 'functioning_well', label: 'Functioning well'}, {value: 'developing', label: 'Developing'}, {value: 'inconsistent', label: 'Inconsistent'}, {value: 'not_yet_in_place', label: 'Not yet in place'}, {value: 'not_sure', label: 'Not sure'}],
            review_cadence: [{value: 'as_occurring', label: 'As occurring'}, {value: 'weekly', label: 'Weekly'}, {value: 'monthly', label: 'Monthly'}, {value: 'quarterly', label: 'Quarterly'}, {value: 'semiannual', label: 'Semiannual'}, {value: 'annual', label: 'Annual'}, {value: 'not_currently_reviewed', label: 'Not currently reviewed'}, {value: 'not_applicable', label: 'Not applicable'}, {value: 'not_sure', label: 'Not sure'}],
            reviewed_by: [{value: 'qapi_committee', label: 'QAPI Committee'}, {value: 'medical_executive_committee', label: 'Medical Executive Committee'}, {value: 'pharmacy_therapeutics', label: 'Pharmacy & Therapeutics'}, {value: 'infection_control_committee', label: 'Infection Control Committee'}, {value: 'board_quality_committee', label: 'Board Quality Committee'}, {value: 'quality_safety_committee', label: 'Quality and Safety Committee'}, {value: 'medical_staff', label: 'Medical Staff'}, {value: 'other', label: 'Other'}, {value: 'not_sure', label: 'Not sure'}],
            review_method: [{value: 'review_all', label: 'Review all'}, {value: 'sampling', label: 'Sampling'}, {value: 'aggregate_trend_review', label: 'Aggregate trend review'}, {value: 'event_triggered_review', label: 'Event-triggered review'}, {value: 'department_report', label: 'Department report'}, {value: 'not_currently_defined', label: 'Not currently defined'}, {value: 'not_sure', label: 'Not sure'}],
            priority_gap: [{value: 'high_priority_gap', label: 'High priority gap'}, {value: 'medium_priority_gap', label: 'Medium priority gap'}, {value: 'low_priority_gap', label: 'Low priority gap'}, {value: 'no_current_gap', label: 'No current gap'}, {value: 'not_sure', label: 'Not sure'}],
            weakest_monitoring_areas: [{value: 'morbidity_mortality_review', label: 'Morbidity and mortality review'}, {value: 'blood_usage_review', label: 'Blood usage review'}, {value: 'medication_safety', label: 'Medication safety'}, {value: 'operative_invasive_procedures', label: 'Operative / invasive procedures'}, {value: 'anesthesia_moderate_sedation', label: 'Anesthesia / moderate sedation'}, {value: 'sentinel_never_event_protocol', label: 'Sentinel / never event protocol'}, {value: 'ancillary_services', label: 'Ancillary services'}, {value: 'contracted_service_data_flow', label: 'Contracted service data flow'}, {value: 'infection_prevention', label: 'Infection prevention'}, {value: 'dietary_monitoring', label: 'Dietary monitoring'}, {value: 'policy_review', label: 'Policy review'}, {value: 'other', label: 'Other'}, {value: 'not_sure', label: 'Not sure'}]
        };
        return optionMap[key] || [];
    }

    function planPolicyInventorySections() {
        return [
            {
                key: 'core_plans',
                title: 'Core Required Plans',
                description: 'Confirm the plans that define your quality, safety, infection prevention, emergency preparedness, and risk program structure.',
                rows: [
                    {key: 'qapi_plan', name: 'QAPI Plan', category: 'Core Plan', guidance: 'Program goals; committee structure and meeting schedule; how data is collected and used; current performance improvement projects; how findings are communicated to leadership and the board.'},
                    {key: 'patient_safety_plan', name: 'Patient Safety Plan', category: 'Core Plan', guidance: 'Safety goals and priorities; event reporting system; RCA/investigation processes; safety rounds; high-risk medication management; fall prevention; infection prevention integration.'},
                    {key: 'infection_prevention_control_plan', name: 'Infection Prevention and Control Plan', category: 'Core Plan', guidance: 'IP program goals; surveillance methods and target infections; prevention policies; outbreak response; antibiotic stewardship; employee health elements.'},
                    {key: 'emergency_preparedness_plan', name: 'Emergency Preparedness Plan', category: 'Core Plan', guidance: 'Hazard vulnerability analysis; emergency operations plan; communication plan; resource management; staff training and drills; community coordination.'},
                    {key: 'risk_management_plan', name: 'Risk Management Plan', category: 'Core Plan', guidance: 'Risk identification, mitigation, reporting, claims coordination, grievance linkage, patient safety event escalation, and leadership review process.'}
                ]
            },
            {
                key: 'safety_event_response',
                title: 'Safety & Event Response',
                description: 'Confirm how safety concerns, serious events, peer review, and protected review information are governed.',
                rows: [
                    {key: 'patient_rights_responsibilities', name: 'Patient Rights and Responsibilities', category: 'Patient Rights', guidance: 'How staff report near misses, adverse events, and safety concerns. Your event reporting system depends on staff knowing and following this policy.'},
                    {key: 'patient_safety_event_reporting', name: 'Patient Safety Event Reporting', category: 'Event Response', guidance: 'What triggers a root cause analysis, who leads it, and what the timeline is. Essential for consistent adverse event response.'},
                    {key: 'sentinel_serious_event_response', name: 'Sentinel Event and Serious Event Response', category: 'Event Response', guidance: 'How serious events are identified, escalated, investigated, documented, and communicated through leadership channels.'},
                    {key: 'peer_review_confidentiality', name: 'Peer Review and Confidentiality', category: 'Medical Staff', guidance: 'How medical staff peer review is conducted and how peer review information is protected. Protects the integrity of the process and physician relationships.'},
                    {key: 'restraint_seclusion', name: 'Restraint and Seclusion', category: 'Patient Safety', guidance: 'Required by CoPs with specific documentation requirements. Surveyed consistently; restraint events require monitoring and quality review.'}
                ]
            },
            {
                key: 'clinical_operational_policies',
                title: 'Clinical & Operational Policies',
                description: 'Confirm policies that support recurring clinical monitoring and department-level quality review.',
                rows: [
                    {key: 'medication_management', name: 'Medication Management', category: 'Clinical Policy', guidance: 'High-alert medications, medication reconciliation, error reporting. Medication safety is one of the highest-risk quality areas in any hospital.'},
                    {key: 'infection_control_policies', name: 'Infection Control Policies', category: 'Clinical Policy', guidance: 'Hand hygiene, standard precautions, isolation protocols, environmental cleaning. Foundation of the HAI prevention program.'},
                    {key: 'blood_blood_products', name: 'Blood and Blood Products', category: 'Clinical Policy', guidance: 'Informed consent, administration, adverse reaction management. Blood use is a required clinical monitoring area under QAPI.'},
                    {key: 'organ_donation', name: 'Organ Donation', category: 'Clinical Policy', guidance: 'Required agreements and staff education. Surveyed under CoPs; failure to address results can result in deficiency findings.'}
                ]
            },
            {
                key: 'records_transitions',
                title: 'Records & Care Transitions',
                description: 'Confirm policies connected to documentation quality, discharge planning, medical records, and transfer communication.',
                rows: [
                    {key: 'discharge_planning', name: 'Discharge Planning', category: 'Care Transitions', guidance: 'Process, documentation, coordination with community resources. Connects to care transitions, readmission prevention, and MBQIP metrics.'},
                    {key: 'medical_records', name: 'Medical Records', category: 'Records', guidance: 'Content requirements, retention periods, access, confidentiality. Documentation quality underlies virtually all quality measurement.'},
                    {key: 'transfer_policy', name: 'Transfer Policy', category: 'Care Transitions', guidance: 'Documentation requirements and transfer agreements with receiving facilities. Directly tied to the MBQIP ED transfer communication measure for CAHs.'}
                ]
            }
        ];
    }

    function planPolicyInventoryRows() {
        return planPolicyInventorySections().reduce(function (rows, section) {
            return rows.concat(section.rows.map(function (row) {
                return Object.assign({section_key: section.key, section_title: section.title}, row);
            }));
        }, []);
    }

    function normalizePlanPolicyInventory(value) {
        var byKey = {};
        var templates = planPolicyInventoryRows();
        var templateKeys = {};
        templates.forEach(function (row) {
            templateKeys[row.key] = true;
        });
        if (Array.isArray(value)) {
            value.forEach(function (item) {
                if (item && item.policy_key) {
                    byKey[item.policy_key] = item;
                }
            });
        }
        var required = templates.map(function (row) {
            var item = Object.assign({}, row, byKey[row.key] || {});
            item.policy_key = row.key;
            item.policy_name = row.name;
            item.category = row.category;
            item.guidance = row.guidance;
            item.status = item.status || legacyPlanStatusForInventory(row.key);
            item.date_last_approved = item.date_last_approved || legacyPlanDateForInventory(row.key);
            item.upload_status = item.upload_status || 'not_configured';
            item.scout_status = item.scout_status || 'structured_ready';
            return item;
        });
        var additional = Array.isArray(value) ? value.filter(function (item) {
            return item && item.policy_key && !templateKeys[item.policy_key] &&
                (String(item.is_additional_plan || '') === '1' || item.is_additional_plan === true);
        }).map(function (item) {
            var additionalItem = Object.assign({}, item);
            additionalItem.section_key = 'additional_plans';
            additionalItem.section_title = 'Additional plans & policies';
            additionalItem.category = additionalItem.category || 'Additional plan';
            additionalItem.guidance = additionalItem.guidance || 'An additional hospital plan or policy that contains one or more required quality or patient-safety elements.';
            additionalItem.status = additionalItem.status || 'in_place';
            additionalItem.upload_status = additionalItem.upload_status || 'not_configured';
            additionalItem.scout_status = additionalItem.scout_status || 'structured_ready';
            additionalItem.is_additional_plan = '1';
            return additionalItem;
        }) : [];
        return required.concat(additional);
    }

    function isAdditionalPlanPolicyRow(row) {
        return !!row && (String(row.is_additional_plan || '') === '1' || row.is_additional_plan === true);
    }

    function legacyPlanStatusForInventory(policyKey) {
        var legacyMap = {
            qapi_plan: 'qapi_plan_status',
            patient_safety_plan: 'patient_safety_plan_status',
            infection_prevention_control_plan: 'infection_prevention_plan_status',
            emergency_preparedness_plan: 'emergency_preparedness_plan_status',
            risk_management_plan: 'risk_management_plan_status'
        };
        var legacyKey = legacyMap[policyKey];
        var legacy = legacyKey && state.onboarding && state.onboarding.answers ? state.onboarding.answers[legacyKey] : null;
        if (!legacy || typeof legacy !== 'object') {
            return '';
        }
        if (legacy.exists === 'yes') {
            return 'in_place';
        }
        if (legacy.exists === 'no') {
            return 'not_currently_in_place';
        }
        if (legacy.exists === 'not_sure') {
            return 'not_sure';
        }
        return '';
    }

    function legacyPlanDateForInventory(policyKey) {
        var legacyMap = {
            qapi_plan: 'qapi_plan_status',
            patient_safety_plan: 'patient_safety_plan_status',
            infection_prevention_control_plan: 'infection_prevention_plan_status',
            emergency_preparedness_plan: 'emergency_preparedness_plan_status',
            risk_management_plan: 'risk_management_plan_status'
        };
        var legacyKey = legacyMap[policyKey];
        var legacy = legacyKey && state.onboarding && state.onboarding.answers ? state.onboarding.answers[legacyKey] : null;
        return legacy && typeof legacy === 'object' ? fieldValue(legacy.last_approved || '') : '';
    }

    function renderStepFiveQuestion(question) {
        var value = onboardingQuestionValue(question);
        var key = question.question_key;
        if (key === 'plan_policy_inventory') {
            return renderPlanPolicyInventory(question, value);
        }
        if (isStepFivePlanKey(key)) {
            return renderStepFivePlanCard(question, value);
        }
        if (isStepFiveMonitoringKey(key)) {
            return renderStepFiveMonitoringCard(question, value);
        }
        var tag = ['templates_needed', 'weakest_monitoring_areas'].indexOf(key) !== -1 ? 'div' : 'label';
        var help = stepFiveHelpText(question);
        return '<' + tag + ' class="qn-question ' + questionLayoutClass(question) + '" data-question="' + escapeHtml(key) + '">' +
            '<span>' + escapeHtml(stepFiveDisplayLabel(question)) + '</span>' + renderStepFiveField(question, value) + (help ? '<small>' + escapeHtml(help) + '</small>' : '') + '</' + tag + '>';
    }

    function renderPlanPolicyInventory(question, value) {
        var rows = normalizePlanPolicyInventory(value);
        var requiredRows = rows.filter(function (row) { return !isAdditionalPlanPolicyRow(row); });
        var additionalRows = rows.filter(isAdditionalPlanPolicyRow);
        var uniqueDocuments = {};
        rows.forEach(function (row) {
            if (row.document_id && row.upload_status === 'ready') {
                uniqueDocuments[row.document_id] = true;
            }
        });
        var counts = requiredRows.reduce(function (summary, row) {
            summary.total++;
            if (row.status === 'in_place') {
                summary.confirmed++;
            }
            if (row.status === 'not_currently_in_place' || row.status === 'folded_into_another' || row.status === 'not_sure') {
                summary.followUp++;
            }
            return summary;
        }, {total: 0, confirmed: 0, followUp: 0});
        counts.documents = Object.keys(uniqueDocuments).length;
        return '<div class="qn-question qn-plan-policy-inventory" data-question="' + escapeHtml(question.question_key) + '" data-plan-policy-inventory="' + escapeHtml(question.question_key) + '">' +
            '<div class="qn-plan-policy-summary" aria-label="Plan and policy inventory progress">' +
                renderPlanPolicySummaryPill(String(counts.total), 'Required items') +
                renderPlanPolicySummaryPill(String(counts.documents), 'Unique indexed documents') +
                (additionalRows.length ? renderPlanPolicySummaryPill(String(additionalRows.length), 'Additional plans') : '') +
            '</div>' +
            planPolicyInventorySections().map(function (section) {
                var sectionRows = requiredRows.filter(function (row) {
                    return row.section_key === section.key;
                });
                return renderPlanPolicyInventorySection(section, sectionRows);
            }).join('') +
            (additionalRows.length ? renderPlanPolicyInventorySection({
                key: 'additional_plans',
                title: 'Additional plans & policies',
                description: 'Hospital documents not represented by one of the 17 required rows. These documents can be linked to any requirement they support.'
            }, additionalRows) : '') +
        '</div>';
    }

    function renderPlanPolicySummaryPill(value, label) {
        return '<span><strong>' + escapeHtml(value) + '</strong>' + escapeHtml(label) + '</span>';
    }

    function renderPlanPolicyInventorySection(section, rows) {
        return '<section class="qn-plan-policy-section" data-plan-policy-section="' + escapeHtml(section.key) + '">' +
            '<header><div><h5>' + escapeHtml(section.title) + '</h5><p>' + escapeHtml(section.description) + '</p></div><span>' + rows.length + ' items</span></header>' +
            '<div class="qn-plan-policy-table" role="table" aria-label="' + escapeHtml(section.title) + '">' +
                '<div class="qn-plan-policy-head" role="row"><span>Plan / policy</span><span>Date last approved</span><span>Status</span><span>Current version</span><span>Guidance</span></div>' +
                rows.map(renderPlanPolicyInventoryRow).join('') +
            '</div>' +
        '</section>';
    }

    function renderPlanPolicyInventoryRow(row) {
        var isAdditionalPlan = isAdditionalPlanPolicyRow(row);
        var statusLabel = row.status ? optionLabelByValue(stepFiveOptions('plan_policy_status'), row.status) : 'Select status';
        var foldedOpen = !isAdditionalPlan && row.status === 'folded_into_another';
        var rowId = 'qn-policy-row-' + row.policy_key;
        var hasDocument = !!row.document_id && ['ready', 'ocr_required', 'queued', 'processing'].indexOf(row.upload_status) !== -1;
        var documentStatus = row.upload_status === 'ready' ? 'Ready' :
            (row.upload_status === 'ocr_required' ? 'Needs readable text' :
            (['queued', 'processing'].indexOf(row.upload_status) !== -1 ? 'Working…' :
            (row.upload_status === 'failed' ? 'Needs attention' : 'No document')));
        var documentBusy = ['queued', 'processing'].indexOf(row.upload_status) !== -1;
        var documentStatusClass = documentBusy ? ' qn-plan-policy-document-status-busy' :
            (row.upload_status === 'ready' ? ' qn-plan-policy-document-status-ready' : '');
        var documentAction = documentBusy ? 'Document is indexing' : (hasDocument ? 'Replace document' : 'Upload document');
        var reusableDocuments = planPolicyReusableDocuments(row.policy_key);
        var uploadEnabled = documentUploadsEnabled() && canEditOnboarding() && !documentBusy;
        var uploadTitle = !documentUploadsEnabled() ? ' title="Document upload is temporarily unavailable."' :
            (documentBusy ? ' title="This document is already uploaded and indexing."' : '');
        var documentActions = hasDocument ?
            '<span class="qn-plan-policy-document-actions" aria-label="Document actions">' +
                '<button type="button" class="qn-plan-policy-document-action" data-plan-policy-view="' + escapeHtml(row.policy_key) + '" title="View document" aria-label="View document"><span class="dashicons dashicons-visibility" aria-hidden="true"></span></button>' +
                '<button type="button" class="qn-plan-policy-document-action qn-plan-policy-upload" data-plan-policy-upload="' + escapeHtml(row.policy_key) + '" title="' + escapeHtml(documentAction) + '" aria-label="' + escapeHtml(documentAction) + '"' + (uploadEnabled ? '' : ' disabled') + uploadTitle + '><span class="dashicons dashicons-update" aria-hidden="true"></span></button>' +
                (canEditOnboarding() ? '<button type="button" class="qn-plan-policy-document-action qn-plan-policy-document-action-danger qn-plan-policy-remove" data-plan-policy-remove="' + escapeHtml(row.policy_key) + '"' + (isAdditionalPlan ? ' data-remove-plan-record="1"' : '') + ' title="' + (isAdditionalPlan ? 'Delete plan' : 'Remove document') + '" aria-label="' + (isAdditionalPlan ? 'Delete plan' : 'Remove document') + '"' + (documentBusy ? ' disabled' : '') + '><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>' : '') +
            '</span>' :
            '<span class="qn-plan-policy-document-actions" aria-label="Document actions"><button type="button" class="qn-plan-policy-document-action qn-plan-policy-upload" data-plan-policy-upload="' + escapeHtml(row.policy_key) + '" title="Upload document" aria-label="Upload document"' + (uploadEnabled ? '' : ' disabled') + uploadTitle + '><span class="dashicons dashicons-upload" aria-hidden="true"></span></button></span>';
        var documentControls = '<span class="qn-plan-policy-document-controls">' +
            documentActions +
            (documentUploadsEnabled() ? '<input type="file" hidden data-plan-policy-file="' + escapeHtml(row.policy_key) + '" accept=".pdf,.docx,.txt,.md,.html,.json,.jsonl">' : '') +
            '<small class="qn-plan-policy-document-status' + documentStatusClass + '"' + (row.document_name ? ' title="' + escapeHtml(row.document_name) + '"' : '') + '>' + escapeHtml(documentStatus) + '</small>' +
            '<input type="hidden" data-plan-policy-field="' + escapeHtml(row.policy_key) + '" data-plan-policy-key="upload_status" value="' + escapeFieldValue(row.upload_status || 'not_configured') + '">' +
            '<input type="hidden" data-plan-policy-field="' + escapeHtml(row.policy_key) + '" data-plan-policy-key="document_id" value="' + escapeFieldValue(row.document_id || '') + '">' +
            '<input type="hidden" data-plan-policy-field="' + escapeHtml(row.policy_key) + '" data-plan-policy-key="document_name" value="' + escapeFieldValue(row.document_name || '') + '">' +
            '<input type="hidden" data-plan-policy-field="' + escapeHtml(row.policy_key) + '" data-plan-policy-key="document_version_id" value="' + escapeFieldValue(row.document_version_id || '') + '">' +
            '<input type="hidden" data-plan-policy-field="' + escapeHtml(row.policy_key) + '" data-plan-policy-key="ingestion_job_id" value="' + escapeFieldValue(row.ingestion_job_id || '') + '">' +
            '<input type="hidden" data-plan-policy-field="' + escapeHtml(row.policy_key) + '" data-plan-policy-key="storage_path" value="' + escapeFieldValue(row.storage_path || '') + '">' +
            '<input type="hidden" data-plan-policy-field="' + escapeHtml(row.policy_key) + '" data-plan-policy-key="document_sha256" value="' + escapeFieldValue(row.document_sha256 || '') + '">' +
            '<input type="hidden" data-plan-policy-field="' + escapeHtml(row.policy_key) + '" data-plan-policy-key="document_size_bytes" value="' + escapeFieldValue(row.document_size_bytes || '') + '">' +
        '</span>';
        var selectedFoldedKey = row.folded_into_policy_key || '';
        if (!selectedFoldedKey && row.folded_into_document_id) {
            var inferredSource = reusableDocuments.find(function (document) {
                return document.document_id === row.folded_into_document_id &&
                    (!row.folded_into || document.policy_name === row.folded_into);
            });
            selectedFoldedKey = inferredSource ? inferredSource.policy_key : '__new_plan__';
        } else if (!selectedFoldedKey && row.folded_into && row.document_id) {
            selectedFoldedKey = '__new_plan__';
        }
        var foldedSourceControls = isAdditionalPlan ? '' : '<div class="qn-plan-policy-folded-source" data-folded-field="' + escapeHtml(row.policy_key) + '"' + (foldedOpen ? '' : ' hidden') + '>' +
            '<label><span>Plan or policy that contains this requirement <em>Required</em></span>' +
                '<select data-plan-policy-link-source="' + escapeHtml(row.policy_key) + '" data-plan-policy-field="' + escapeHtml(row.policy_key) + '" data-plan-policy-key="folded_into_policy_key"' + (foldedOpen ? ' required' : '') + '>' +
                    '<option value="">Choose an uploaded plan...</option>' +
                    reusableDocuments.map(function (document) {
                        var statusSuffix = document.upload_status === 'ready' ? ' — Ready' : ' — Working...';
                        return '<option value="' + escapeFieldValue(document.policy_key) + '"' + (selectedFoldedKey === document.policy_key ? ' selected' : '') + '>' + escapeHtml(document.policy_name + ' — ' + document.document_name + statusSuffix) + '</option>';
                    }).join('') +
                    '<option value="__new_plan__"' + (selectedFoldedKey === '__new_plan__' ? ' selected' : '') + '>Add another plan or policy...</option>' +
                '</select>' +
            '</label>' +
            '<div class="qn-plan-policy-new-source"' + (selectedFoldedKey === '__new_plan__' ? '' : ' hidden') + ' data-plan-policy-new-source="' + escapeHtml(row.policy_key) + '">' +
                '<label><span>Plan or policy name <em>Required</em></span><input type="text" data-plan-policy-new-name="' + escapeHtml(row.policy_key) + '" value="" placeholder="Example: Hospital-Wide Safety Management Plan"></label>' +
                '<label><span>Document <em>Required</em></span><input type="file" data-plan-policy-new-file="' + escapeHtml(row.policy_key) + '" accept=".pdf,.docx,.txt,.md,.html,.json,.jsonl"></label>' +
                '<p>This creates an independent additional-plan record, indexes the document once, and links this requirement to it.</p>' +
                (canEditOnboarding() ? '<button type="button" class="qn-button qn-button-primary" data-plan-policy-create-source="' + escapeHtml(row.policy_key) + '"><span class="dashicons dashicons-upload"></span>Save, upload and link</button>' : '') +
            '</div>' +
            '<input type="hidden" data-plan-policy-field="' + escapeHtml(row.policy_key) + '" data-plan-policy-key="folded_into" value="' + escapeFieldValue(row.folded_into || '') + '">' +
            '<input type="hidden" data-plan-policy-field="' + escapeHtml(row.policy_key) + '" data-plan-policy-key="folded_into_document_id" value="' + escapeFieldValue(row.folded_into_document_id || '') + '">' +
            '<input type="hidden" data-plan-policy-field="' + escapeHtml(row.policy_key) + '" data-plan-policy-key="coverage_review_status" value="' + escapeFieldValue(row.coverage_review_status || 'not_reviewed') + '">' +
            (canEditOnboarding() ? '<button type="button" class="qn-button qn-button-secondary" data-plan-policy-link="' + escapeHtml(row.policy_key) + '"' + (selectedFoldedKey === '__new_plan__' ? ' hidden' : '') + '>Link selected plan</button>' : '') +
            '<small>Linking records the relationship. Scout must review the indexed content before coverage can be confirmed.</small>' +
        '</div>';
        return '<details class="qn-plan-policy-row" data-plan-policy-row="' + escapeHtml(row.policy_key) + '"' + (foldedOpen ? ' open' : '') + '>' +
            '<summary role="row">' +
                '<span class="qn-plan-policy-name"><strong>' + escapeHtml(row.policy_name) + '</strong><small>' + escapeHtml(row.category) + '</small></span>' +
                '<span>' + renderUsDateInput('data-plan-policy-field="' + escapeHtml(row.policy_key) + '" data-plan-policy-key="date_last_approved"', row.date_last_approved || '') + '</span>' +
                '<span>' + (isAdditionalPlan ? '<span class="qn-status-pill qn-status-neutral">Additional plan</span><input type="hidden" data-plan-policy-field="' + escapeHtml(row.policy_key) + '" data-plan-policy-key="status" value="in_place">' : renderPlanPolicyStatusSelect(row.policy_key, row.status || '')) + '</span>' +
                documentControls +
                '<span class="qn-plan-policy-guidance-preview">' + escapeHtml(row.guidance) + '<em>Notes</em></span>' +
            '</summary>' +
            '<div class="qn-plan-policy-detail" id="' + escapeHtml(rowId) + '">' +
                '<input type="hidden" data-plan-policy-field="' + escapeHtml(row.policy_key) + '" data-plan-policy-key="policy_key" value="' + escapeFieldValue(row.policy_key) + '">' +
                '<input type="hidden" data-plan-policy-field="' + escapeHtml(row.policy_key) + '" data-plan-policy-key="policy_name" value="' + escapeFieldValue(row.policy_name) + '">' +
                '<input type="hidden" data-plan-policy-field="' + escapeHtml(row.policy_key) + '" data-plan-policy-key="category" value="' + escapeFieldValue(row.category) + '">' +
                '<input type="hidden" data-plan-policy-field="' + escapeHtml(row.policy_key) + '" data-plan-policy-key="scout_status" value="' + escapeFieldValue(row.scout_status || 'structured_ready') + '">' +
                '<input type="hidden" data-plan-policy-field="' + escapeHtml(row.policy_key) + '" data-plan-policy-key="is_additional_plan" value="' + (isAdditionalPlan ? '1' : '') + '">' +
                foldedSourceControls +
                '<div class="qn-plan-policy-detail-grid">' +
                    '<label><span>Internal notes</span><textarea data-plan-policy-field="' + escapeHtml(row.policy_key) + '" data-plan-policy-key="notes" placeholder="Process notes only. Do not include patient, provider, peer-review, or case-level details.">' + escapeFieldValue(row.notes || '') + '</textarea></label>' +
                '</div>' +
            '</div>' +
        '</details>';
    }

    function planPolicyReusableDocuments(policyKey) {
        var seen = {};
        return normalizePlanPolicyInventory(state.onboarding && state.onboarding.answers ? state.onboarding.answers.plan_policy_inventory : []).sort(function (a, b) {
            return (a.folded_into_document_id ? 1 : 0) - (b.folded_into_document_id ? 1 : 0);
        }).filter(function (row) {
            if (!row.document_id || row.policy_key === policyKey || ['ready', 'queued', 'processing'].indexOf(row.upload_status) === -1 || seen[row.document_id]) {
                return false;
            }
            seen[row.document_id] = true;
            return true;
        });
    }

    function updatePlanPolicyFoldedUI(field) {
        var row = field ? field.closest('.qn-plan-policy-row') : null;
        if (!row) {
            return;
        }
        var policyKey = row.getAttribute('data-plan-policy-row');
        var statusField = row.querySelector('[data-plan-policy-key="status"]');
        var sourceField = row.querySelector('[data-plan-policy-link-source="' + policyKey + '"]');
        var foldedPanel = row.querySelector('[data-folded-field="' + policyKey + '"]');
        var newSourcePanel = row.querySelector('[data-plan-policy-new-source="' + policyKey + '"]');
        var existingLinkButton = row.querySelector('[data-plan-policy-link="' + policyKey + '"]');
        var folded = statusField && statusField.value === 'folded_into_another';
        if (foldedPanel) {
            foldedPanel.hidden = !folded;
        }
        if (sourceField) {
            sourceField.required = folded;
        }
        if (newSourcePanel) {
            newSourcePanel.hidden = !folded || !sourceField || sourceField.value !== '__new_plan__';
            var newName = newSourcePanel.querySelector('[data-plan-policy-new-name]');
            if (newName) {
                newName.required = !newSourcePanel.hidden;
            }
        }
        if (existingLinkButton) {
            existingLinkButton.hidden = !folded || !sourceField || sourceField.value === '__new_plan__';
        }
        if (!folded) {
            if (sourceField) {
                sourceField.value = '';
            }
            ['folded_into', 'folded_into_policy_key', 'folded_into_document_id'].forEach(function (key) {
                var hidden = row.querySelector('[data-plan-policy-key="' + key + '"]');
                if (hidden) {
                    hidden.value = '';
                }
            });
            var reviewStatus = row.querySelector('[data-plan-policy-key="coverage_review_status"]');
            if (reviewStatus) {
                reviewStatus.value = 'not_reviewed';
            }
        }
        if (folded) {
            row.open = true;
        }
    }

    function linkPlanPolicyDocument(policyKey) {
        var select = document.querySelector('[data-plan-policy-link-source="' + policyKey + '"]');
        var sourceKey = select ? select.value : '';
        if (!sourceKey || !state.onboarding || !canEditOnboarding()) {
            showToast('Choose an indexed document to use.', 'warning');
            return;
        }
        var rows = normalizePlanPolicyInventory(state.onboarding.answers.plan_policy_inventory);
        var target = rows.find(function (row) { return row.policy_key === policyKey; });
        if (!target) {
            showToast('That plan or policy is no longer available. Refresh and try again.', 'warning');
            return;
        }
        if (sourceKey === '__new_plan__') {
            showToast('Enter the plan name, choose its document, and use Save, upload and link.', 'warning');
            return;
        }
        var source = rows.find(function (row) { return row.policy_key === sourceKey; });
        if (!target || !source || !source.document_id || ['ready', 'queued', 'processing'].indexOf(source.upload_status) === -1) {
            showToast('That document is no longer available. Refresh and try again.', 'warning');
            return;
        }
        target.folded_into = source.policy_name;
        target.folded_into_policy_key = source.policy_key;
        target.folded_into_document_id = source.document_id;
        target.coverage_review_status = source.upload_status === 'ready' ? 'pending_scout_review' : 'pending_indexing';
        state.onboarding.answers.plan_policy_inventory = rows;
        renderOnboardingFields(state.onboarding.steps[state.onboardingIndex]);
        setOnboardingSaveStatus('unsaved', 'Document linked - saving...');
        window.clearTimeout(state.autosaveTimer);
        state.autosaveTimer = window.setTimeout(autosaveOnboardingStep, 100);
        showToast(source.upload_status === 'ready' ?
            'Existing indexed plan linked. Scout coverage review is still required.' :
            'Plan linked while Scout indexes it. Coverage review will begin automatically when indexing finishes.', 'success');
    }

    function createAdditionalPlanAndUpload(requirementKey, trigger) {
        var panel = document.querySelector('[data-plan-policy-new-source="' + requirementKey + '"]');
        var nameField = panel ? panel.querySelector('[data-plan-policy-new-name]') : null;
        var fileField = panel ? panel.querySelector('[data-plan-policy-new-file]') : null;
        var planName = nameField ? nameField.value.trim() : '';
        var file = fileField && fileField.files ? fileField.files[0] : null;
        if (!planName) {
            showToast('Enter the plan or policy name.', 'warning');
            return;
        }
        if (!file) {
            showToast('Choose the plan or policy document to upload.', 'warning');
            return;
        }
        if (file.size <= 0 || file.size > 26214400) {
            showToast('The document must be between 1 byte and 25 MB.', 'warning');
            return;
        }
        var restoreButton = setButtonLoading(trigger, 'Uploading...');
        var form = new FormData();
        form.append('organization_id', state.onboardingOrganizationId);
        form.append('additional_plan_name', planName);
        form.append('link_requirement_key', requirementKey);
        form.append('file', file, file.name);
        setOnboardingSaveStatus('saving', 'Creating and indexing additional plan...');
        apiForm('/onboarding/plan-policy-document', form, {timeout: 360000}).then(function (result) {
            var policy = result && result.policy ? result.policy : {};
            var policyKey = fieldValue(policy.policy_key || '');
            var status = result && result.document ? fieldValue(result.document.status).toLowerCase().replace(/[^a-z0-9_]+/g, '_') : 'failed';
            if (!policyKey) {
                throw new Error('The additional plan was uploaded but its inventory key was not returned.');
            }
            var reused = !!(result && result.reused_existing_document);
            showToast(reused ?
                (status === 'ready' ? 'This document was already uploaded. The existing indexed plan was linked.' : 'This document is already uploaded and indexing. The existing plan was linked.') :
                (status === 'ready' ? 'Additional plan indexed and linked. Scout coverage review is pending.' : 'Additional plan saved and queued for secure indexing.'), 'success');
            return loadOnboarding(state.onboardingOrganizationId, {showLoading: false}).then(function () {
                if (['queued', 'processing'].indexOf(status) !== -1) {
                    return trackPlanPolicyDocumentStatus(policyKey);
                }
                setOnboardingSaveStatus(status === 'ready' ? 'saved' : 'error', status === 'ready' ? 'Additional plan ready' : 'Document needs review');
            });
        }).catch(function (error) {
            showToast(error.message || 'The additional plan could not be created.', 'warning');
            setOnboardingSaveStatus('error', 'Additional plan upload failed');
        }).finally(function () {
            if (fileField) {
                fileField.value = '';
            }
            restoreButton();
        });
    }

    function uploadPlanPolicyDocument(input) {
        var file = input && input.files ? input.files[0] : null;
        var policyKey = input ? input.getAttribute('data-plan-policy-file') : '';
        if (!file || !policyKey || !state.onboarding || !canEditOnboarding()) {
            return;
        }
        if (file.size > 26214400) {
            showToast('The document must be 25 MB or smaller.', 'warning');
            input.value = '';
            return;
        }
        var controls = input.closest('.qn-plan-policy-document-controls');
        var button = controls ? controls.querySelector('[data-plan-policy-upload]') : null;
        var restoreButton = setButtonLoading(button, 'Processing...');
        var visibleStatus = controls ? controls.querySelector('.qn-plan-policy-document-status') : null;
        var originalVisibleStatus = visibleStatus ? visibleStatus.textContent : '';
        var originalVisibleStatusClass = visibleStatus ? visibleStatus.className : '';
        if (visibleStatus) {
            visibleStatus.textContent = 'Working…';
            visibleStatus.classList.remove('qn-plan-policy-document-status-ready');
            visibleStatus.classList.add('qn-plan-policy-document-status-busy');
        }
        var step = state.onboarding.steps[state.onboardingIndex];
        var currentAnswers = collectOnboardingAnswers();
        window.clearTimeout(state.autosaveTimer);
        setOnboardingSaveStatus('saving', 'Processing document...');
        api('/onboarding/save', {
            method: 'POST',
            timeout: 60000,
            body: {
                organization_id: state.onboardingOrganizationId,
                step_key: step.section_key,
                answers: currentAnswers
            }
        }).then(function () {
            var form = new FormData();
            form.append('organization_id', state.onboardingOrganizationId);
            form.append('policy_key', policyKey);
            form.append('file', file, file.name);
            return apiForm('/onboarding/plan-policy-document', form, {timeout: 360000});
        }).then(function (result) {
            var status = result && result.document ? result.document.status : 'ready';
            if (['queued', 'processing'].indexOf(status) !== -1) {
                showToast('Document uploaded. Scout is indexing it securely in the background.', 'success');
                setOnboardingSaveStatus('saving', 'Indexing document...');
                return loadOnboarding(state.onboardingOrganizationId, {showLoading: false}).then(function () {
                    return trackPlanPolicyDocumentStatus(policyKey);
                });
            }
            showToast(status === 'ocr_required' ? 'Document saved, but readable text could not be extracted.' : 'Document indexed and ready for Scout.', status === 'ocr_required' ? 'warning' : 'success');
            setOnboardingSaveStatus('saved', status === 'ocr_required' ? 'Text extraction needs review' : 'Document ready');
            return loadOnboarding(state.onboardingOrganizationId, {showLoading: false});
        }).catch(function (error) {
            if (visibleStatus && visibleStatus.isConnected) {
                visibleStatus.textContent = originalVisibleStatus;
                visibleStatus.className = originalVisibleStatusClass;
            }
            showToast(error.message || 'The document could not be processed.', 'warning');
            setOnboardingSaveStatus('error', 'Document failed');
        }).finally(function () {
            input.value = '';
            restoreButton();
        });
    }

    function viewPlanPolicyDocument(policyKey, trigger) {
        if (!policyKey || !state.onboardingOrganizationId) {
            return;
        }
        var targetWindow = browserWindow();
        var restoreButton = setButtonLoading(trigger, '');
        setOnboardingSaveStatus('saving', 'Opening document...');
        api('/onboarding/plan-policy-document/view', {
            method: 'POST',
            body: {
                organization_id: state.onboardingOrganizationId,
                policy_key: policyKey
            }
        }).then(function (result) {
            if (!result || !result.url) {
                throw new Error('This document could not be opened securely.');
            }
            if (!targetWindow) {
                throw new Error('This document could not be opened in your browser.');
            }
            setOnboardingSaveStatus('saved', 'Document ready');
            targetWindow.location.assign(result.url);
        }).catch(function (error) {
            setOnboardingSaveStatus('error', 'Document could not be opened');
            showToast(error && error.message ? error.message : 'The document could not be opened.', 'warning');
        }).finally(function () {
            restoreButton();
        });
    }

    function pollPlanPolicyDocumentStatus(policyKey, attempt) {
        if (!policyKey || attempt >= 90) {
            setOnboardingSaveStatus('error', 'Indexing is taking longer than expected');
            showToast('The document is still indexing. Its status will be available when you return to this step.', 'warning');
            return Promise.resolve();
        }
        return new Promise(function (resolve) {
            window.setTimeout(resolve, 2000);
        }).then(function () {
            return api('/onboarding/plan-policy-document/status', {
                method: 'POST',
                timeout: 30000,
                body: {organization_id: state.onboardingOrganizationId, policy_key: policyKey}
            });
        }).then(function (result) {
            var status = result && result.document ? result.document.status : 'failed';
            if (!result.terminal && ['queued', 'processing'].indexOf(status) !== -1) {
                setOnboardingSaveStatus('saving', status === 'processing' ? 'Reading and indexing document...' : 'Document queued for indexing...');
                return pollPlanPolicyDocumentStatus(policyKey, attempt + 1);
            }
            if (status === 'ready') {
                showToast('Document indexed and ready for Scout.', 'success');
                setOnboardingSaveStatus('saved', 'Document ready');
            } else if (status === 'ocr_required') {
                showToast('Document saved, but readable text could not be extracted. Try a text-based PDF or DOCX.', 'warning');
                setOnboardingSaveStatus('error', 'Text extraction needs review');
            } else {
                showToast('Scout could not index this document. The file remains listed so you can replace it.', 'warning');
                setOnboardingSaveStatus('error', 'Document indexing failed');
            }
            return loadOnboarding(state.onboardingOrganizationId, {showLoading: false});
        }).catch(function (error) {
            showToast(error.message || 'Document indexing status could not be checked.', 'warning');
            setOnboardingSaveStatus('error', 'Indexing status unavailable');
        });
    }

    function trackPlanPolicyDocumentStatus(policyKey) {
        if (!policyKey) {
            return Promise.resolve();
        }
        state.planPolicyStatusPolls = state.planPolicyStatusPolls || {};
        if (state.planPolicyStatusPolls[policyKey]) {
            return state.planPolicyStatusPolls[policyKey];
        }
        state.planPolicyStatusPolls[policyKey] = pollPlanPolicyDocumentStatus(policyKey, 0).finally(function () {
            delete state.planPolicyStatusPolls[policyKey];
        });
        return state.planPolicyStatusPolls[policyKey];
    }

    function resumePlanPolicyDocumentPolling() {
        if (!state.onboarding || !state.onboarding.answers) {
            return;
        }
        normalizePlanPolicyInventory(state.onboarding.answers.plan_policy_inventory).forEach(function (row) {
            if (row.policy_key && row.ingestion_job_id && ['queued', 'processing'].indexOf(row.upload_status) !== -1) {
                trackPlanPolicyDocumentStatus(row.policy_key);
            }
        });
    }

    function deletePlanPolicyDocument(policyKey, trigger) {
        if (!policyKey || !canEditOnboarding()) {
            return;
        }
        var removePlanRecord = trigger && trigger.getAttribute('data-remove-plan-record') === '1';
        var restoreButton = setButtonLoading(trigger, 'Removing...');
        api('/onboarding/plan-policy-document/delete', {
            method: 'POST',
            timeout: 120000,
            body: {
                organization_id: state.onboardingOrganizationId,
                policy_key: policyKey,
                remove_plan_record: removePlanRecord
            }
        }).then(function () {
            showToast(removePlanRecord ? 'Additional plan and document deleted.' : 'Document removed from Scout.', 'success');
            setOnboardingSaveStatus('saved', removePlanRecord ? 'Additional plan deleted' : 'Document removed');
            return loadOnboarding(state.onboardingOrganizationId, {showLoading: false});
        }).catch(function (error) {
            showToast(error.message || 'The document could not be removed.', 'warning');
            setOnboardingSaveStatus('error', 'Remove failed');
        }).finally(function () {
            restoreButton();
        });
    }

    function confirmPlanPolicyDocumentDeletion(policyKey, trigger) {
        var row = trigger ? trigger.closest('.qn-plan-policy-row') : null;
        var removePlanRecord = trigger && trigger.getAttribute('data-remove-plan-record') === '1';
        var planNameNode = row ? row.querySelector('.qn-plan-policy-name strong') : null;
        var documentNameField = row ? row.querySelector('[data-plan-policy-key="document_name"]') : null;
        var planName = planNameNode ? planNameNode.textContent.trim() : 'this plan or policy';
        var documentName = documentNameField ? documentNameField.value.trim() : '';
        openDestructiveConfirmation({
            title: removePlanRecord ? 'Delete additional plan?' : 'Remove document?',
            description: removePlanRecord ?
                'This removes the additional plan record and its document from Scout. Requirements linked to it must be unlinked first.' :
                'This removes the document from Scout. The required plan or policy row and the information you entered will remain.',
            itemName: documentName || planName,
            note: documentName && planName ? 'Plan or policy: ' + planName : '',
            confirmLabel: removePlanRecord ? 'Delete plan' : 'Remove document',
            onConfirm: function () {
                deletePlanPolicyDocument(policyKey, trigger);
            }
        });
    }

    function confirmOnboardingRepeaterDeletion(row) {
        if (!row) {
            return;
        }
        var repeater = row.closest('[data-repeater]');
        var style = repeater ? repeater.getAttribute('data-repeater-style') : '';
        var heading = row.querySelector('.qn-survey-card-header strong');
        var itemName = heading ? heading.textContent.trim() : 'This entry';
        var copy = {
            'survey-history': {title: 'Delete survey history entry?', description: 'This removes the survey or review entry from Hospital Setup.', label: 'Delete entry'},
            'committee-card': {title: 'Delete committee?', description: 'This removes the committee and its meeting details from Hospital Setup.', label: 'Delete committee'},
            'report-card': {title: 'Delete reporting obligation?', description: 'This removes the report, timing, owner, and submission details from Hospital Setup.', label: 'Delete report'},
            'qi-project-card': {title: 'Delete improvement project?', description: 'This removes the project details and milestones from Hospital Setup.', label: 'Delete project'},
            'backup-user-card': {title: 'Delete backup user entry?', description: 'This removes the backup coverage entry from Hospital Setup. It does not delete the person’s QualiNav account.', label: 'Delete entry'}
        }[style] || {title: 'Delete this entry?', description: 'This removes the selected entry from Hospital Setup.', label: 'Delete entry'};
        openDestructiveConfirmation({
            title: copy.title,
            description: copy.description,
            itemName: itemName,
            note: 'This change will be saved automatically.',
            confirmLabel: copy.label,
            onConfirm: function () {
                deleteOnboardingRepeaterRow(row);
            }
        });
    }

    function deleteOnboardingRepeaterRow(row) {
        var repeaterOwner = row ? row.closest('[data-repeater]') : null;
        if (!row || !repeaterOwner) {
            return;
        }
        row.remove();
        if (!repeaterOwner.querySelector('.qn-repeater-row')) {
            var style = repeaterOwner.getAttribute('data-repeater-style');
            var emptyText = '<div class="qn-survey-empty"><strong>No survey history added yet.</strong><span>Add prior surveys if available; you can also skip this for now.</span></div>';
            if (style === 'committee-card') {
                emptyText = '<div class="qn-survey-empty"><strong>No committees added yet.</strong><span>Add the meetings where quality data is reviewed.</span></div>';
            }
            if (style === 'report-card') {
                emptyText = '<div class="qn-survey-empty"><strong>No reporting obligations added yet.</strong><span>Add recurring or event-triggered reports.</span></div>';
            }
            if (style === 'backup-user-card') {
                emptyText = '<div class="qn-survey-empty qn-step8-backup-empty"><strong>No backup users added.</strong><span>This can be completed later if backup coverage is not decided yet.</span></div>';
            }
            repeaterOwner.insertAdjacentHTML('afterbegin', emptyText);
        }
        refreshRepeaterCardLabels(repeaterOwner);
        setOnboardingSaveStatus('unsaved', 'Unsaved changes');
        window.clearTimeout(state.autosaveTimer);
        state.autosaveTimer = window.setTimeout(autosaveOnboardingStep, 900);
    }

    function renderPlanPolicyStatusSelect(policyKey, value) {
        value = fieldValue(value);
        return '<select data-plan-policy-field="' + escapeHtml(policyKey) + '" data-plan-policy-key="status"><option value="">Select status</option>' + stepFiveOptions('plan_policy_status').map(function (option) {
            var optionValueText = optionValue(option);
            return '<option value="' + escapeFieldValue(optionValueText) + '"' + (value === optionValueText ? ' selected' : '') + '>' + escapeHtml(optionLabel(option)) + '</option>';
        }).join('') + '</select>';
    }

    function isStepFivePlanKey(key) {
        return ['qapi_plan_status', 'patient_safety_plan_status', 'infection_prevention_plan_status', 'emergency_preparedness_plan_status', 'risk_management_plan_status'].indexOf(key) !== -1;
    }

    function isStepFiveMonitoringKey(key) {
        return ['morbidity_mortality_monitoring', 'blood_usage_review', 'medication_safety_monitoring', 'operative_invasive_review', 'anesthesia_sedation_monitoring', 'sentinel_never_event_protocol', 'ancillary_services_review', 'contracted_service_quality_data_flow'].indexOf(key) !== -1;
    }

    function stepFiveDisplayLabel(question) {
        var labels = {
            qapi_plan_status: 'QAPI Plan',
            patient_safety_plan_status: 'Patient Safety Plan',
            infection_prevention_plan_status: 'Infection Prevention and Control Plan',
            emergency_preparedness_plan_status: 'Emergency Preparedness Plan',
            risk_management_plan_status: 'Risk Management Plan',
            templates_needed: 'Templates needed',
            morbidity_mortality_monitoring: 'Morbidity and mortality review',
            blood_usage_review: 'Blood usage review',
            medication_safety_monitoring: 'Medication safety monitoring',
            operative_invasive_review: 'Operative and invasive procedures review',
            anesthesia_sedation_monitoring: 'Anesthesia and moderate sedation monitoring',
            sentinel_never_event_protocol: 'Sentinel and never event protocol',
            ancillary_services_review: 'Ancillary services review',
            contracted_service_quality_data_flow: 'Contracted service quality data flow'
        };
        return labels[question.question_key] || question.label;
    }

    function renderStepFiveField(question, value) {
        var key = question.question_key;
        if (key === 'policy_management_system' || key === 'annual_policy_review_cycle') {
            return renderStepOneSelect(key, value, stepFiveOptions(key), key === 'policy_management_system' ? 'Select policy system' : 'Select review cycle');
        }
        if (key === 'templates_needed') {
            return renderMultiselectField(key, value, stepFiveOptions(key), 'Select templates');
        }
        if (key === 'weakest_monitoring_areas') {
            return '<div class="qn-step5-priority-checklist">' + renderInlineChecklistField(key, value, stepFiveOptions(key)) + '</div>';
        }
        if (key === 'plan_location_authority') {
            return '<textarea data-onboarding-field="' + escapeHtml(key) + '" placeholder="Describe where plans live and who can route them for approval.">' + escapeFieldValue(value) + '</textarea>';
        }
        return renderField(question, value);
    }

    function stepFiveHelpText(question) {
        if (question.question_key === 'plan_location_authority') {
            return 'Describe the plan library and approval routing process. Do not include patient or case details.';
        }
        if (question.question_key === 'templates_needed') {
            return 'Scout can use this to prepare starter templates and priority items.';
        }
        return '';
    }

    function renderStepFivePlanCard(question, value) {
        var key = question.question_key;
        var data = normalizePlanValue(value);
        return '<div class="qn-question qn-step5-plan-card" data-question="' + escapeHtml(key) + '">' +
            '<header><strong>' + escapeHtml(stepFiveDisplayLabel(question)) + '</strong><span class="qn-step5-status-badge">' + escapeHtml(stepFivePlanStatusLabel(data)) + '</span></header>' +
            '<div class="qn-step5-card-grid">' +
            '<label><span>Exists?</span>' + renderPlanSelect(key, 'exists', data.exists || '', stepFiveOptions('yes_no_not_sure'), 'Select') + '</label>' +
            '<label><span>Last approved</span>' + renderUsDateInput('data-plan-field="' + escapeHtml(key) + '" data-plan-key="last_approved"', data.last_approved || '') + '</label>' +
            '<label><span>Board approved?</span>' + renderPlanSelect(key, 'board_approved', data.board_approved || '', stepFiveOptions('board_approved'), 'Select') + '</label>' +
            '<label><span>Owner</span><input type="text" data-plan-field="' + escapeHtml(key) + '" data-plan-key="owner" value="' + escapeFieldValue(data.owner || '') + '" placeholder="Owner"></label>' +
            '<label><span>Location</span><input type="text" data-plan-field="' + escapeHtml(key) + '" data-plan-key="location" value="' + escapeFieldValue(data.location || '') + '" placeholder="Example: policy system, SharePoint, board packet archive"></label>' +
            '<label><span>Action needed</span>' + renderPlanSelect(key, 'action_needed', data.action_needed || '', stepFiveOptions('action_needed'), 'Select action') + '</label>' +
            (data.legacy ? '<small class="qn-legacy-note">Previous value: ' + escapeHtml(data.legacy) + '</small>' : '') +
            '</div></div>';
    }

    function normalizePlanValue(value) {
        if (value && typeof value === 'object' && !Array.isArray(value)) {
            return value;
        }
        value = fieldValue(value);
        return value ? {legacy: value, notes: value} : {};
    }

    function stepFivePlanStatusLabel(data) {
        if (data.action_needed && data.action_needed !== 'none') {
            return optionLabelByValue(stepFiveOptions('action_needed'), data.action_needed);
        }
        if (data.exists === 'yes') {
            return 'Exists';
        }
        if (data.exists === 'no') {
            return 'Missing';
        }
        return 'Not set';
    }

    function renderPlanSelect(key, dataKey, value, options, placeholder) {
        value = fieldValue(value);
        return '<select data-plan-field="' + escapeHtml(key) + '" data-plan-key="' + escapeHtml(dataKey) + '"><option value="">' + escapeHtml(placeholder || 'Select') + '</option>' + options.map(function (option) {
            var optionValueText = optionValue(option);
            return '<option value="' + escapeFieldValue(optionValueText) + '"' + (value === optionValueText ? ' selected' : '') + '>' + escapeHtml(optionLabel(option)) + '</option>';
        }).join('') + '</select>';
    }

    function renderStepFiveMonitoringCard(question, value) {
        var key = question.question_key;
        var data = normalizeMonitoringValue(key, value);
        var highlight = key === 'contracted_service_quality_data_flow' && visitingSpecialistsUsed();
        return '<details class="qn-question qn-step5-monitoring-card' + (highlight ? ' qn-step5-highlight' : '') + '" data-question="' + escapeHtml(key) + '" open>' +
            '<summary><span><strong>' + escapeHtml(stepFiveDisplayLabel(question)) + '</strong><small>' + escapeHtml(stepFiveMonitoringSummary(data)) + '</small></span><span class="dashicons dashicons-arrow-down-alt2"></span></summary>' +
            (highlight ? '<small class="qn-conditional-note">Visiting specialists are used, so Scout will treat contracted service data flow as important.</small>' : '') +
            '<div class="qn-step5-card-grid">' +
            '<label><span>Applies?</span>' + renderStructuredSelect(key, 'applies', data.applies || '', stepFiveOptions('applies'), 'Select') + '</label>' +
            '<label><span>Current state</span>' + renderStructuredSelect(key, 'current_state', data.current_state || '', stepFiveOptions('current_state'), 'Select state') + '</label>' +
            '<label><span>Review cadence</span>' + renderStructuredSelect(key, 'review_cadence', data.review_cadence || '', stepFiveOptions('review_cadence'), 'Select cadence') + '</label>' +
            '<label><span>Reviewed by</span>' + renderStructuredSelect(key, 'reviewed_by', data.reviewed_by || '', stepFiveOptions('reviewed_by'), 'Select group') + '</label>' +
            '<label><span>Review method</span>' + renderStructuredSelect(key, 'review_method', data.review_method || '', stepFiveOptions('review_method'), 'Select method') + '</label>' +
            '<label><span>Priority/gap?</span>' + renderStructuredSelect(key, 'priority_gap', data.priority_gap || '', stepFiveOptions('priority_gap'), 'Select priority') + '</label>' +
            '<label class="qn-structured-wide"><span>Notes</span><textarea data-structured-field="' + escapeHtml(key) + '" data-structured-key="notes" placeholder="Process notes only. Do not include patient, provider, or case-level details.">' + escapeFieldValue(data.notes || '') + '</textarea></label>' +
            (data.legacy ? '<small class="qn-legacy-note">Previous value preserved in notes.</small>' : '') +
            '</div></details>';
    }

    function normalizeMonitoringValue(key, value) {
        var data = value && typeof value === 'object' && !Array.isArray(value) ? Object.assign({}, value) : {};
        if (!(value && typeof value === 'object') && fieldValue(value)) {
            data.notes = fieldValue(value);
            data.legacy = fieldValue(value);
        }
        if (!data.applies) {
            if ((key === 'operative_invasive_review' || key === 'anesthesia_sedation_monitoring') && surgeryNotOffered()) {
                data.applies = 'not_applicable';
            }
            if (key === 'blood_usage_review' && bloodReviewNotApplicable()) {
                data.applies = 'not_applicable';
            }
        }
        return data;
    }

    function stepFiveMonitoringSummary(data) {
        var pieces = [];
        if (data.applies) {
            pieces.push(optionLabelByValue(stepFiveOptions('applies'), data.applies));
        }
        if (data.current_state) {
            pieces.push(optionLabelByValue(stepFiveOptions('current_state'), data.current_state));
        }
        if (data.priority_gap) {
            pieces.push(optionLabelByValue(stepFiveOptions('priority_gap'), data.priority_gap));
        }
        return pieces.length ? pieces.join(' - ') : 'Not configured';
    }

    function surgeryNotOffered() {
        var answers = state.onboarding && state.onboarding.answers ? state.onboarding.answers : {};
        return answers.surgery_invasive_procedures === 'not_offered';
    }

    function bloodReviewNotApplicable() {
        var answers = state.onboarding && state.onboarding.answers ? state.onboarding.answers : {};
        var noBloodProducts = answers.blood_bank_model === 'no_blood_products_on_site';
        var transfusions = answers.transfusions_per_year;
        return noBloodProducts && (transfusions === '' || transfusions === undefined || transfusions === null || Number(transfusions) === 0);
    }

    function visitingSpecialistsUsed() {
        var answers = state.onboarding && state.onboarding.answers ? state.onboarding.answers : {};
        return answers.visiting_specialists === 'yes';
    }

    function stepSixOptions(key) {
        var apiOptions = questionOptionsForKey(key);
        if (apiOptions.length) {
            return apiOptions;
        }
        var optionMap = {
            upload_status: [
                {value: 'yes', label: 'Yes'},
                {value: 'no', label: 'No'},
                {value: 'not_sure', label: 'Not sure'},
                {value: 'not_applicable', label: 'Not applicable'}
            ],
            cadence: [
                {value: 'monthly', label: 'Monthly'},
                {value: 'quarterly', label: 'Quarterly'},
                {value: 'annual', label: 'Annual'},
                {value: 'per_cycle', label: 'Per cycle'},
                {value: 'event_triggered', label: 'Event-triggered'},
                {value: 'not_applicable', label: 'Not applicable'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            data_source_currency: [
                {value: 'real_time', label: 'Real-time'},
                {value: 'same_month', label: 'Same month'},
                {value: 'one_month_lag', label: '1 month lag'},
                {value: 'quarterly', label: 'Quarterly'},
                {value: 'manual_as_available', label: 'Manual/as available'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            qi_method: [
                {value: 'model_for_improvement_pdsa', label: 'Model for Improvement / PDSA'},
                {value: 'lean', label: 'Lean'},
                {value: 'six_sigma', label: 'Six Sigma'},
                {value: 'rca_corrective_action', label: 'RCA / corrective action'},
                {value: 'fmea', label: 'FMEA'},
                {value: 'combination', label: 'Combination'},
                {value: 'other', label: 'Other'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            project_status: [
                {value: 'idea_not_started', label: 'Idea / not started'},
                {value: 'planning', label: 'Planning'},
                {value: 'active_testing', label: 'Active testing'},
                {value: 'implementing', label: 'Implementing'},
                {value: 'monitoring_results', label: 'Monitoring results'},
                {value: 'completed', label: 'Completed'},
                {value: 'on_hold', label: 'On hold'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            yes_no_progress: [
                {value: 'yes', label: 'Yes'},
                {value: 'no', label: 'No'},
                {value: 'in_progress', label: 'In progress'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            qi_framework: [
                {value: 'model_for_improvement_pdsa', label: 'Model for Improvement / PDSA'},
                {value: 'lean', label: 'Lean'},
                {value: 'six_sigma', label: 'Six Sigma'},
                {value: 'rca_corrective_action', label: 'RCA / corrective action'},
                {value: 'fmea', label: 'FMEA'},
                {value: 'combination', label: 'Combination'},
                {value: 'not_standardized', label: 'Not standardized'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            project_charters_status: [
                {value: 'charters_in_place', label: 'Charters in place for active projects'},
                {value: 'some_projects_have_charters', label: 'Some projects have charters'},
                {value: 'no_formal_charters', label: 'No formal charters'},
                {value: 'need_template_support', label: 'Need template/support'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            baseline_data_status: [
                {value: 'baselines_collected', label: 'Baselines collected'},
                {value: 'some_baselines_collected', label: 'Some baselines collected'},
                {value: 'not_yet_collected', label: 'Not yet collected'},
                {value: 'need_help_defining_baselines', label: 'Need help defining baselines'},
                {value: 'not_sure', label: 'Not sure'}
            ]
        };
        return optionMap[key] || [];
    }

    function renderStepSixQuestion(question) {
        var value = onboardingQuestionValue(question);
        var key = question.question_key;
        if ((key.indexOf('internal_monitoring_') === 0 || key.indexOf('external_reporting_') === 0) && question.field_type === 'multiselect') {
            return '<div class="qn-question qn-question-wide qn-step4-inline-question" data-question="' + escapeHtml(key) + '"><span>' + escapeHtml(question.label) + '</span>' + renderInlineChecklistField(key, value, stepSixOptions(key)) + '</div>';
        }
        if (key === 'active_qi_projects') {
            return '<div class="qn-question qn-question-wide" data-question="' + escapeHtml(key) + '"><span>Active QI Projects</span>' + renderQiProjectsRepeater(key, value) + '</div>';
        }
        var label = stepSixDisplayLabel(question);
        return '<label class="qn-question ' + questionLayoutClass(question) + '" data-question="' + escapeHtml(key) + '">' +
            '<span>' + escapeHtml(label) + '</span>' + renderStepSixField(question, value) + stepSixHelpText(question) + '</label>';
    }

    function isStepSixMeasureKey(key) {
        return false;
    }

    function stepSixDisplayLabel(question) {
        var labels = {
            mbqip_upload: 'MBQIP data status',
            nhsn_hai_rates_upload: 'NHSN HAI rates',
            patient_experience_scores_upload: 'Patient experience data',
            fall_rates_upload: 'Fall rates data',
            pressure_injury_rates_upload: 'Pressure injury data',
            hand_hygiene_upload: 'Hand hygiene data',
            other_dashboard_metrics: 'Other dashboard metric data',
            current_quality_dashboard: 'Current quality dashboard',
            data_source_currency: 'Data source currency',
            qi_framework: 'QI framework',
            project_charters_status: 'Project charters status',
            baseline_data_status: 'Baseline data status'
        };
        return labels[question.question_key] || question.label;
    }

    function renderStepSixField(question, value) {
        var key = question.question_key;
        if ((key.indexOf('internal_monitoring_') === 0 || key.indexOf('external_reporting_') === 0) && question.field_type === 'multiselect') {
            return renderInlineChecklistField(key, value, stepSixOptions(key));
        }
        if (key === 'current_quality_dashboard') {
            return '<textarea class="qn-compact-textarea" data-onboarding-field="' + escapeHtml(key) + '" placeholder="Example: MBQIP, infection surveillance, falls, pressure injuries, patient experience, hand hygiene.">' + escapeFieldValue(value) + '</textarea>';
        }
        if (isStepSixMetricStatusKey(key)) {
            return renderStepOneSelect(key, normalizeStepSixMetricStatus(value), stepSixOptions('upload_status'), 'Select status');
        }
        if (key === 'data_source_currency') {
            return renderStepOneSelect(key, value, stepSixOptions(key), 'Select currency');
        }
        if (key === 'qi_framework' || key === 'project_charters_status' || key === 'baseline_data_status') {
            return renderStepOneSelect(key, value, stepSixOptions(key), 'Select status');
        }
        return renderField(question, value);
    }

    function isStepSixMetricStatusKey(key) {
        return ['mbqip_upload', 'nhsn_hai_rates_upload', 'patient_experience_scores_upload', 'fall_rates_upload', 'pressure_injury_rates_upload', 'hand_hygiene_upload', 'other_dashboard_metrics'].indexOf(key) !== -1;
    }

    function normalizeStepSixMetricStatus(value) {
        if (value && typeof value === 'object' && !Array.isArray(value)) {
            return fieldValue(value.will_upload || value.status || value.applies || '');
        }
        return fieldValue(value);
    }

    function stepSixHelpText(question) {
        if (question.question_key === 'current_quality_dashboard') {
            return '<small>Only enter aggregate dashboard categories, not patient-level data.</small>';
        }
        return '';
    }

    function renderStepSixMeasureCard(question, value) {
        var key = question.question_key;
        var data = normalizeStepSixMeasureValue(value);
        return '<div class="qn-question qn-step6-measure-card" data-question="' + escapeHtml(key) + '">' +
            '<header><strong>' + escapeHtml(stepSixDisplayLabel(question)) + '</strong><span class="qn-step5-status-badge">' + escapeHtml(stepSixMeasureSummary(data)) + '</span></header>' +
            '<div class="qn-step6-card-grid">' +
                '<label><span>Will upload?</span>' + renderStructuredSelect(key, 'will_upload', data.will_upload || '', stepSixOptions('upload_status'), 'Select') + '</label>' +
                '<label><span>Cadence</span>' + renderStructuredSelect(key, 'cadence', data.cadence || '', stepSixOptions('cadence'), 'Select cadence') + '</label>' +
                '<label class="qn-structured-wide"><span>Source system</span><input type="text" data-structured-field="' + escapeHtml(key) + '" data-structured-key="source_system" value="' + escapeFieldValue(data.source_system || '') + '" placeholder="Example: QualityNet, NHSN, survey vendor, EHR, manual audit"></label>' +
                '<label class="qn-structured-wide"><span>Notes</span><textarea data-structured-field="' + escapeHtml(key) + '" data-structured-key="notes" placeholder="Aggregate/de-identified process notes only.">' + escapeFieldValue(data.notes || '') + '</textarea></label>' +
                (data.legacy ? '<small class="qn-legacy-note">Previous value preserved in notes.</small>' : '') +
            '</div></div>';
    }

    function normalizeStepSixMeasureValue(value) {
        if (value && typeof value === 'object' && !Array.isArray(value)) {
            return value;
        }
        value = fieldValue(value);
        return value ? {legacy: value, notes: value} : {};
    }

    function stepSixMeasureSummary(data) {
        if (data.will_upload) {
            return optionLabelByValue(stepSixOptions('upload_status'), data.will_upload);
        }
        return 'Not set';
    }

    function renderQiProjectsRepeater(key, value) {
        value = Array.isArray(value) ? value : [];
        var columns = ['project_aim', 'method', 'measure', 'current_status', 'next_milestone', 'has_charter', 'baseline_data_collected', 'status_next_milestone'];
        return '<div class="qn-repeater qn-card-repeater" data-repeater="' + escapeHtml(key) + '" data-repeater-style="qi-project-card" data-columns="' + escapeHtml(JSON.stringify(columns)) + '">' +
            (value.length ? '' : '<div class="qn-survey-empty"><strong>No QI projects added yet.</strong><span>Add active improvement projects if available; you can also skip this for now.</span></div>') +
            value.map(function (row, index) {
                return renderQiProjectRow(key, columns, row, index);
            }).join('') + '<button class="qn-button qn-button-small qn-add-survey-row" type="button" data-add-repeater="' + escapeHtml(key) + '"><span class="dashicons dashicons-plus-alt2"></span>Add project</button></div>';
    }

    function renderQiProjectRow(key, columns, row, index) {
        row = row || {};
        var legacyMilestone = row.status_next_milestone && !row.next_milestone ? row.status_next_milestone : '';
        return '<div class="qn-repeater-row qn-survey-history-row qn-flow-card">' +
            '<div class="qn-survey-card-header"><strong>Project ' + (index + 1) + '</strong><button class="qn-icon-button qn-delete-survey-row" type="button" data-delete-repeater-row aria-label="Delete project row"><span class="dashicons dashicons-trash"></span></button></div>' +
            '<div class="qn-survey-card-grid qn-flow-card-grid qn-step6-project-grid">' +
                '<label class="qn-structured-wide"><span>Project aim</span><textarea data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="project_aim" placeholder="What is this project trying to improve?">' + escapeFieldValue(row.project_aim || '') + '</textarea></label>' +
                '<label><span>Method</span>' + renderRepeaterSelect(key, index, 'method', row.method || '', stepSixOptions('qi_method'), 'Select method') + '</label>' +
                '<label><span>Measure</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="measure" value="' + escapeFieldValue(row.measure || '') + '"></label>' +
                '<label><span>Current status</span>' + renderRepeaterSelect(key, index, 'current_status', row.current_status || row.status || '', stepSixOptions('project_status'), 'Select status') + '</label>' +
                '<label><span>Next milestone</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="next_milestone" value="' + escapeFieldValue(row.next_milestone || legacyMilestone) + '"></label>' +
                '<label><span>Has charter?</span>' + renderRepeaterSelect(key, index, 'has_charter', row.has_charter || '', stepSixOptions('yes_no_progress'), 'Select') + '</label>' +
                '<label><span>Baseline data collected?</span>' + renderRepeaterSelect(key, index, 'baseline_data_collected', row.baseline_data_collected || '', stepSixOptions('yes_no_progress'), 'Select') + '</label>' +
                (row.status_next_milestone ? '<input type="hidden" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="status_next_milestone" value="' + escapeFieldValue(row.status_next_milestone) + '">' : '') +
                (legacyMilestone ? '<small class="qn-legacy-note">Previous status/milestone text moved into Next milestone.</small>' : '') +
            '</div></div>';
    }

    function stepSevenOptions(key) {
        var apiOptions = questionOptionsForKey(key);
        if (apiOptions.length) {
            return apiOptions;
        }
        var optionMap = {
            yes_no_not_sure: [
                {value: 'yes', label: 'Yes'},
                {value: 'no', label: 'No'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            time_in_current_role: [
                {value: 'less_than_one_year', label: 'Less than one year'},
                {value: 'one_to_5_years', label: '1 - 5 years'},
                {value: 'six_to_10_years', label: '6 - 10 years'},
                {value: 'more_than_10_years', label: 'More than 10 years'}
            ],
            quality_certifications: [
                {value: 'cphq', label: 'CPHQ'},
                {value: 'cpps', label: 'CPPS'},
                {value: 'rn', label: 'RN'},
                {value: 'mph', label: 'MPH'},
                {value: 'mba', label: 'MBA'},
                {value: 'pursuing_cphq', label: 'Pursuing CPHQ'},
                {value: 'pursuing_cpps', label: 'Pursuing CPPS'},
                {value: 'other', label: 'Other'},
                {value: 'none', label: 'None'}
            ],
            confidence: [
                {value: 'new', label: 'New'},
                {value: 'developing', label: 'Developing'},
                {value: 'confident', label: 'Confident'}
            ],
            learning_format_preference: [
                {value: 'short_on_demand_modules', label: 'Short on-demand modules'},
                {value: 'structured_learning_path', label: 'Structured learning path'},
                {value: 'peer_cohort_discussion', label: 'Peer/cohort discussion'},
                {value: 'live_coaching_checkins', label: 'Live coaching/check-ins'},
                {value: 'templates_examples', label: 'Templates and examples'},
                {value: 'certification_prep', label: 'Certification prep'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            organization_quality_goals: [
                {value: 'Establish or mature the QAPI program', label: 'Establish or mature the QAPI program'},
                {value: 'Achieve continuous survey readiness', label: 'Achieve continuous survey readiness'},
                {value: 'Drive MBQIP & publicly reported measures', label: 'Drive MBQIP & publicly reported measures'},
                {value: 'Strengthen culture of safety & high reliability', label: 'Strengthen culture of safety & high reliability'},
                {value: 'Reduce infections & advance antibiotic stewardship', label: 'Reduce infections & advance antibiotic stewardship'},
                {value: 'Improve care transitions & reduce readmissions', label: 'Improve care transitions & reduce readmissions'},
                {value: 'Elevate board & executive quality oversight', label: 'Elevate board & executive quality oversight'},
                {value: 'Integrate medical staff engagement with quality', label: 'Integrate medical staff engagement with quality'},
                {value: 'Advance patient & family engagement', label: 'Advance patient & family engagement'},
                {value: 'Other', label: 'Other'}
            ],
            personal_professional_goals: [
                {value: 'Build Quality Director Capability and Professional Sustainability', label: 'Build Quality Director Capability and Professional Sustainability'},
                {value: 'Pursue National Certification and Specialty Membership', label: 'Pursue National Certification and Specialty Membership'},
                {value: 'Build a Personal Peer Network and Mentorship System', label: 'Build a Personal Peer Network and Mentorship System'},
                {value: 'Other', label: 'Other'}
            ]
        };
        return optionMap[key] || [];
    }

    function renderStepSevenQuestion(question) {
        var value = onboardingQuestionValue(question);
        var key = question.question_key;
        if (isStepSevenContactKey(key)) {
            return renderStepSevenContactCard(question, value);
        }
        var tag = isStepSevenGoalKey(key) || ['quality_certifications', 'learning_format_preference'].indexOf(key) !== -1 ? 'div' : 'label';
        return '<' + tag + ' class="qn-question ' + questionLayoutClass(question) + '" data-question="' + escapeHtml(key) + '">' +
            '<span>' + escapeHtml(stepSevenDisplayLabel(question)) + '</span>' + renderStepSevenField(question, value) + stepSevenHelpText(question) + '</' + tag + '>';
    }

    function renderStepSevenField(question, value) {
        var key = question.question_key;
        if (isStepSevenGoalKey(key)) {
            if (key === 'department_goals_this_year') {
                return renderStepSevenGoalTiles(key, value, stepSevenOptions('organization_quality_goals'), 3);
            }
            if (key === 'department_goals_two_three_years') {
                return renderStepSevenGoalTiles(key, value, stepSevenOptions('personal_professional_goals'), 0);
            }
            return '<textarea class="qn-compact-textarea" data-onboarding-field="' + escapeHtml(key) + '" placeholder="' + escapeHtml(stepSevenGoalPlaceholder(key)) + '">' + escapeFieldValue(value) + '</textarea>';
        }
        if (key === 'new_to_quality_director_role' || key === 'activate_first_30_days_track') {
            return renderStepOneSelect(key, value, stepSevenOptions('yes_no_not_sure'), 'Select');
        }
        if (key === 'time_in_current_role') {
            return renderStepOneSelect(key, value, stepSevenOptions(key), 'Select time in role');
        }
        if (key === 'quality_certifications') {
            return renderMultiselectField(key, value, stepSevenOptions(key), 'Select certifications');
        }
        if (key === 'learning_format_preference') {
            return renderMultiselectField(key, value, stepSevenOptions(key), 'Select learning formats');
        }
        if (isStepSevenConfidenceKey(key)) {
            return renderStepOneSelect(key, normalizeConfidenceValue(value), stepSevenOptions('confidence'), 'Select confidence');
        }
        return renderField(question, value);
    }

    function stepSevenDisplayLabel(question) {
        var labels = {
            department_goals_this_year: 'Hospital quality goals this year',
            department_goals_two_three_years: 'Personal professional goals',
            quality_director_role_start_date: 'Quality Leader experience',
            time_in_current_role: 'Time in current role',
            quality_director_background: 'Quality Leader background',
            new_to_quality_director_role: 'New to Quality Leader role?',
            confidence_foundational: 'Foundational knowledge',
            confidence_qi_patient_safety: 'QI and patient safety science',
            confidence_specialized_areas: 'Specialized areas',
            confidence_professional_development: 'Professional development',
            state_flex_contact: 'State Flex Program contact',
            state_office_rural_health_contact: 'State Office of Rural Health contact',
            state_hospital_association_contact: 'State Hospital Association contact',
            state_survey_agency_contacts: 'State survey agency contacts',
            peer_cah_contacts: 'Peer CAH / rural QD contacts',
            accreditation_liaison: 'Accreditation liaison',
            referral_hospital_contacts: 'Referral hospital contacts'
        };
        return labels[question.question_key] || question.label;
    }

    function isStepSevenGoalKey(key) {
        return ['department_goals_this_year', 'department_goals_two_three_years', 'protected_workflow_goals', 'program_gaps', 'strategic_plan_alignment'].indexOf(key) !== -1;
    }

    function isStepSevenConfidenceKey(key) {
        return ['confidence_foundational', 'confidence_qi_patient_safety', 'confidence_specialized_areas', 'confidence_professional_development'].indexOf(key) !== -1;
    }

    function isStepSevenContactKey(key) {
        return ['state_flex_contact', 'state_office_rural_health_contact', 'state_hospital_association_contact', 'state_survey_agency_contacts', 'peer_cah_contacts', 'accreditation_liaison', 'referral_hospital_contacts'].indexOf(key) !== -1;
    }

    function stepSevenGoalPlaceholder(key) {
        var placeholders = {
            department_goals_this_year: 'Example: strengthen medication safety, prepare for survey, improve QAPI dashboard.',
            department_goals_two_three_years: 'Example: build succession plan, improve physician engagement, mature the quality program.',
            protected_workflow_goals: 'Example: protect weekly time for QI projects and policy review.',
            program_gaps: 'Example: inconsistent infection surveillance, missing templates, overdue plans.',
            strategic_plan_alignment: 'Example: aligned with board priorities around safety, patient experience, and rural access.'
        };
        return placeholders[key] || '';
    }

    function stepSevenHelpText(question) {
        if (question.question_key === 'activate_first_30_days_track') {
            return '<small id="qn-step7-first-30-note">' + escapeHtml(stepSevenFirst30Help()) + '</small>';
        }
        return '';
    }

    function renderStepSevenLearningNote() {
        return '<div class="qn-step7-learning-note qn-question-wide" id="qn-step7-learning-note"><span class="dashicons dashicons-welcome-learn-more"></span><span>' + escapeHtml(stepSevenFirst30Help()) + '</span></div>';
    }

    function stepSevenFirst30Help() {
        var answers = state.onboarding && state.onboarding.answers ? state.onboarding.answers : {};
        return stepSevenFirst30HelpForValue(answers.new_to_quality_director_role || '');
    }

    function stepSevenFirst30HelpForValue(value) {
        if (value === 'yes') {
            return 'New Quality Leaders often benefit from the First 30 Days track, which helps locate key documents, map committees, and build the initial operating system.';
        }
        if (value === 'no') {
            return 'Experienced Quality Leaders usually receive lighter refresher guidance.';
        }
        return 'Scout can tailor the First 30 Days track based on your role experience.';
    }

    function normalizeConfidenceValue(value) {
        value = fieldValue(value);
        if (['new', 'developing', 'confident'].indexOf(value) !== -1) {
            return value;
        }
        if (value === '1') {
            return 'new';
        }
        if (value === '2' || value === '3') {
            return 'developing';
        }
        if (value === '4' || value === '5') {
            return 'confident';
        }
        return value;
    }

    function renderStepSevenContactCard(question, value) {
        var key = question.question_key;
        var data = normalizeContactValue(value);
        return '<div class="qn-question qn-step7-contact-card" data-question="' + escapeHtml(key) + '">' +
            '<header><strong>' + escapeHtml(stepSevenDisplayLabel(question)) + '</strong></header>' +
            '<div class="qn-step7-contact-grid">' +
                '<label><span>Name / organization</span><input type="text" data-structured-field="' + escapeHtml(key) + '" data-structured-key="name_organization" value="' + escapeFieldValue(data.name_organization || '') + '" placeholder="' + escapeHtml(stepSevenContactPlaceholder(key)) + '"></label>' +
                '<label><span>Email</span><input type="email" data-structured-field="' + escapeHtml(key) + '" data-structured-key="email" value="' + escapeFieldValue(data.email || '') + '" placeholder="email@example.org"></label>' +
                '<label><span>Phone</span><input type="tel" data-structured-field="' + escapeHtml(key) + '" data-structured-key="phone" value="' + escapeFieldValue(data.phone || '') + '" placeholder="Phone"></label>' +
                '<label class="qn-structured-wide"><span>Notes</span><textarea data-structured-field="' + escapeHtml(key) + '" data-structured-key="notes" placeholder="Professional contact or program notes only.">' + escapeFieldValue(data.notes || '') + '</textarea></label>' +
                (data.legacy ? '<small class="qn-legacy-note">Previous value preserved in notes.</small>' : '') +
            '</div></div>';
    }

    function normalizeContactValue(value) {
        if (value && typeof value === 'object' && !Array.isArray(value)) {
            return value;
        }
        value = fieldValue(value);
        return value ? {legacy: value, notes: value, name_organization: value} : {};
    }

    function stepSevenContactPlaceholder(key) {
        var placeholders = {
            state_flex_contact: 'Example: Name, State Flex Program',
            state_office_rural_health_contact: 'Example: Name, State Office of Rural Health',
            state_hospital_association_contact: 'Example: Name, state hospital association',
            state_survey_agency_contacts: 'Example: survey agency program contact',
            peer_cah_contacts: 'Example: peer quality directors you can call for practical advice',
            accreditation_liaison: 'Example: Joint Commission liaison or accreditor contact, if applicable',
            referral_hospital_contacts: 'Example: quality contact at primary referral/receiving hospital'
        };
        return placeholders[key] || 'Name and organization';
    }

    function renderStepEightQuestion(question) {
        var value = onboardingQuestionValue(question);
        var key = question.question_key;
        if (key === 'backup_visibility_users') {
            return '<div class="qn-question qn-question-wide qn-step8-backup-question" data-question="' + escapeHtml(key) + '"><span>Backup visibility users</span><small>Optional. Add people who should see reminders or coverage context when the Quality Leader is unavailable.</small>' + renderBackupUsersRepeater(key, value) + '</div>';
        }
        if (key === 'final_review_confirmation') {
            return renderFinalReviewConfirmation(key, value);
        }
        var wide = ['monitored_sources', 'current_tools'].indexOf(key) !== -1 ? ' qn-question-wide' : '';
        return '<label class="qn-question' + wide + '" data-question="' + escapeHtml(key) + '">' +
            '<span>' + escapeHtml(stepEightDisplayLabel(question)) + '</span>' + renderStepEightField(question, value) + stepEightHelpText(key) + '</label>';
    }

    function renderStepEightField(question, value) {
        var key = question.question_key;
        if (key === 'monitored_sources') {
            return renderMultiselectField(key, value, stepEightOptions(key), 'Select monitoring sources');
        }
        if (key === 'current_tools') {
            return renderMultiselectField(key, value, stepEightOptions(key), 'Select current tools');
        }
        if (['update_preference', 'auto_propose_task_adjustments', 'calendar_system', 'nhsn_qualitynet_access', 'reminder_lead_time', 'reminder_buffer_time'].indexOf(key) !== -1) {
            return renderStepOneSelect(key, value, stepEightOptions(key), stepEightPlaceholder(key));
        }
        if (key === 'ehr_system') {
            return '<input type="text" data-onboarding-field="' + escapeHtml(key) + '" value="' + escapeFieldValue(value) + '" placeholder="Example: Epic, Meditech, Evident, MEDHOST">';
        }
        if (key === 'incident_reporting_system') {
            return '<input type="text" data-onboarding-field="' + escapeHtml(key) + '" value="' + escapeFieldValue(value) + '" placeholder="Example: RLDatix, Origami, internal paper process, vendor system">';
        }
        return renderField(question, value);
    }

    function stepEightDisplayLabel(question) {
        var labels = {
            monitored_sources: 'Monitored sources',
            update_preference: 'How should QualiNav notify you?',
            auto_propose_task_adjustments: 'When Scout identifies a regulatory change',
            current_tools: 'Current tools',
            calendar_system: 'Email / calendar system',
            ehr_system: 'EHR system',
            incident_reporting_system: 'Incident reporting system',
            nhsn_qualitynet_access: 'NHSN / QualityNet access',
            reminder_lead_time: 'Default reminder lead time',
            reminder_buffer_time: 'Reminder buffer'
        };
        return labels[question.question_key] || question.label;
    }

    function stepEightPlaceholder(key) {
        var placeholders = {
            update_preference: 'Select notification preference',
            auto_propose_task_adjustments: 'Select Scout behavior',
            calendar_system: 'Select system',
            nhsn_qualitynet_access: 'Select access status',
            reminder_lead_time: 'Select lead time',
            reminder_buffer_time: 'Select buffer time'
        };
        return placeholders[key] || 'Select';
    }

    function stepEightHelpText(key) {
        if (key === 'auto_propose_task_adjustments') {
            return '<small>Scout will suggest changes for review; it will not silently change tasks.</small>';
        }
        return '';
    }

    function stepEightOptions(key) {
        var apiOptions = questionOptionsForKey(key);
        if (apiOptions.length) {
            return apiOptions;
        }
        var options = {
            monitored_sources: [
                {value: 'cms_conditions_of_participation', label: 'CMS Conditions of Participation'},
                {value: 'cms_survey_certification_memos', label: 'CMS survey/certification memos'},
                {value: 'state_survey_agency', label: 'State survey agency'},
                {value: 'state_health_department', label: 'State health department'},
                {value: 'accreditor_standards_updates', label: 'Accreditor standards updates'},
                {value: 'joint_commission_perspectives', label: 'Joint Commission Perspectives'},
                {value: 'mbqip_flex_program_updates', label: 'MBQIP / Flex program updates'},
                {value: 'qualitynet_hqr_announcements', label: 'QualityNet / HQR announcements'},
                {value: 'sentinel_event_alerts', label: 'Sentinel Event Alerts'},
                {value: 'state_hospital_association_updates', label: 'State hospital association updates'},
                {value: 'other', label: 'Other'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            update_preference: [
                {value: 'weekly_digest', label: 'Weekly digest'},
                {value: 'immediate_high_impact', label: 'Immediate alerts for high-impact changes'},
                {value: 'digest_and_immediate', label: 'Both digest and immediate alerts'},
                {value: 'no_alerts_yet', label: 'No alerts yet'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            auto_propose_task_adjustments: [
                {value: 'yes_review', label: 'Yes, propose adjustments for review'},
                {value: 'no_flag_only', label: 'No, only flag changes'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            current_tools: [
                {value: 'excel_spreadsheets', label: 'Excel / spreadsheets'},
                {value: 'outlook_calendar', label: 'Outlook calendar'},
                {value: 'outlook_tasks', label: 'Outlook tasks'},
                {value: 'google_calendar', label: 'Google Calendar'},
                {value: 'microsoft_teams_exchange', label: 'Microsoft Teams / Exchange'},
                {value: 'project_management_tool', label: 'Project management tool'},
                {value: 'paper_checklist', label: 'Paper checklist'},
                {value: 'policy_management_system', label: 'Policy management system'},
                {value: 'shared_drive_sharepoint', label: 'Shared drive / SharePoint'},
                {value: 'other', label: 'Other'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            calendar_system: [
                {value: 'outlook', label: 'Outlook'},
                {value: 'google_calendar', label: 'Google Calendar'},
                {value: 'microsoft_teams_exchange', label: 'Microsoft Teams / Exchange'},
                {value: 'paper_manual', label: 'Paper/manual'},
                {value: 'other', label: 'Other'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            nhsn_qualitynet_access: [
                {value: 'access_confirmed', label: 'Access confirmed'},
                {value: 'access_pending', label: 'Access pending'},
                {value: 'need_help_setting_up', label: 'Need help setting up'},
                {value: 'not_applicable', label: 'Not applicable'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            reminder_lead_time: [
                {value: 'one_week', label: '1 week'},
                {value: 'two_weeks', label: '2 weeks'},
                {value: 'three_weeks', label: '3 weeks'},
                {value: 'four_weeks', label: '4 weeks'},
                {value: 'six_weeks', label: '6 weeks'},
                {value: 'custom', label: 'Custom'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            reminder_buffer_time: [
                {value: 'no_buffer', label: 'No buffer'},
                {value: 'three_days', label: '3 days'},
                {value: 'one_week', label: '1 week'},
                {value: 'two_weeks', label: '2 weeks'},
                {value: 'custom', label: 'Custom'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            backup_access_level: [
                {value: 'viewer', label: 'Viewer'},
                {value: 'backup_quality_user', label: 'Backup Quality User'},
                {value: 'hospital_admin', label: 'Hospital Admin'},
                {value: 'not_sure', label: 'Not sure'}
            ]
        };
        return options[key] || [];
    }

    function renderBackupUsersRepeater(key, value) {
        value = normalizeBackupUsersValue(value);
        var columns = ['user_id', 'notes'];
        return '<div class="qn-repeater qn-card-repeater qn-step8-backup-repeater" data-repeater="' + escapeHtml(key) + '" data-repeater-style="backup-user-card" data-columns="' + escapeHtml(JSON.stringify(columns)) + '">' +
            (value.length ? '' : '<div class="qn-survey-empty qn-step8-backup-empty"><strong>No backup users added.</strong><span>This can be completed later if backup coverage is not decided yet.</span></div>') +
            value.map(function (row, index) {
                return renderBackupUserRow(key, columns, row, index);
            }).join('') + '<button class="qn-button qn-button-small qn-add-survey-row" type="button" data-add-repeater="' + escapeHtml(key) + '"><span class="dashicons dashicons-plus-alt2"></span>Add backup user</button></div>';
    }

    function normalizeBackupUsersValue(value) {
        if (Array.isArray(value)) {
            return value.map(linkLegacyBackupUser).filter(Boolean);
        }
        if (value && typeof value === 'object') {
            return [linkLegacyBackupUser(value)].filter(Boolean);
        }
        value = fieldValue(value);
        return value ? [{notes: value, legacy: value}] : [];
    }

    function linkLegacyBackupUser(row) {
        row = row && typeof row === 'object' ? Object.assign({}, row) : {};
        if (row.user_id) {
            row.user_id = Number(row.user_id) || 0;
            return row;
        }
        var name = String(row.name || row.name_organization || '').toLowerCase().trim();
        var match = organizationUserOptions().find(function (user) {
            return name && String(user.display_name || '').toLowerCase().trim() === name;
        });
        row.user_id = match ? Number(match.user_id) : 0;
        return row;
    }

    function renderBackupUserRow(key, columns, row, index) {
        row = row || {};
        return '<div class="qn-repeater-row qn-survey-history-row qn-step8-backup-row">' +
            '<div class="qn-survey-card-header"><strong>Backup user ' + (index + 1) + '</strong><button class="qn-icon-button qn-delete-survey-row" type="button" data-delete-repeater-row aria-label="Delete backup user"><span class="dashicons dashicons-trash"></span></button></div>' +
            '<div class="qn-survey-card-grid qn-step8-backup-grid">' +
                '<label><span>Existing hospital user</span>' + renderOrganizationUserSelect(key, index, 'user_id', row.user_id, 'Select user') + '</label>' +
                '<label class="qn-structured-wide"><span>Notes</span><textarea data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="notes" placeholder="Professional backup coverage notes only.">' + escapeFieldValue(row.notes || row.legacy || '') + '</textarea></label>' +
                (!row.user_id && (row.name || row.email) ? '<small class="qn-legacy-note">Previous free-text user could not be matched. Select an existing hospital user before saving.</small>' : '') +
            '</div></div>';
    }

    function renderFinalReviewConfirmation(key, value) {
        return '<div class="qn-question qn-question-wide qn-step8-confirm-card" data-question="' + escapeHtml(key) + '">' +
            '<label class="qn-step8-confirm-label"><input type="checkbox" data-onboarding-field="' + escapeHtml(key) + '"' + (value ? ' checked' : '') + '><span class="qn-step8-confirm-box" aria-hidden="true"></span><span class="qn-step8-confirm-text">This information is as complete as I can make it today and is ready for Scout to build my initial workspace.</span></label>' +
            '<p>This is not a certification. You can return and update the setup whenever your hospital changes.</p>' +
            '<small id="qn-final-review-message" class="qn-step8-confirm-message" hidden>Please confirm that the information is ready for Scout.</small>' +
            '</div>';
    }

    function normalizeStateValue(value) {
        value = fieldValue(value);
        if (!value) {
            return '';
        }
        var states = state.onboarding && Array.isArray(state.onboarding.states) ? state.onboarding.states : [];
        var found = states.find(function (item) {
            return String(item.id) === value || String(item.abbreviation || '').toLowerCase() === value.toLowerCase() || String(item.name || '').toLowerCase() === value.toLowerCase();
        });
        return found ? fieldValue(found.abbreviation || found.id) : value;
    }

    function renderStateSelect(key, value) {
        var states = state.onboarding && Array.isArray(state.onboarding.states) ? state.onboarding.states : [];
        if (!states.length) {
            return '<select disabled><option value="">States unavailable</option></select>';
        }
        value = normalizeStateValue(value);
        return '<select class="qn-searchable-select-source" data-onboarding-field="' + escapeHtml(key) + '"><option value="">Select state</option>' + states.map(function (item) {
            var optionValue = fieldValue(item.abbreviation || item.id);
            var label = item.name || item.abbreviation || optionValue;
            return '<option value="' + escapeFieldValue(optionValue) + '"' + (value === optionValue ? ' selected' : '') + '>' + escapeHtml(label) + '</option>';
        }).join('') + '</select>';
    }

    function renderField(question, value) {
        var key = escapeHtml(question.question_key);
        var options = question.options || [];
        var placeholder = questionPlaceholder(question);
        if (question.question_key === 'hospital_state') {
            return renderStateSelect(question.question_key, value);
        }
        if (question.question_key === 'is_critical_access_hospital') {
            return renderStepOneSelect(question.question_key, value, stepOneOptions(question.question_key), 'Select CAH status');
        }
        if (question.question_key === 'licensed_for_swing_beds') {
            return renderStepOneSelect(question.question_key, value, stepOneOptions(question.question_key), 'Select swing-bed license status');
        }
        if (question.question_key === 'independent_or_system') {
            return renderStepOneSelect(question.question_key, value, stepOneOptions(question.question_key), 'Select ownership model');
        }
        if (question.field_type === 'textarea') {
            return '<textarea data-onboarding-field="' + key + '"' + (placeholder ? ' placeholder="' + escapeHtml(placeholder) + '"' : '') + '>' + escapeFieldValue(value) + '</textarea>';
        }
        if (question.field_type === 'number') {
            return '<input type="number" min="0" step="1" inputmode="numeric" data-onboarding-field="' + key + '" value="' + escapeFieldValue(value) + '"' + (placeholder ? ' placeholder="' + escapeHtml(placeholder) + '"' : '') + '>';
        }
        if (question.field_type === 'date') {
            return renderUsDateInput('data-onboarding-field="' + key + '"', value);
        }
        if (question.field_type === 'url') {
            return '<input type="url" inputmode="url" data-onboarding-field="' + key + '" value="' + escapeFieldValue(value) + '"' + (placeholder ? ' placeholder="' + escapeHtml(placeholder) + '"' : '') + '>';
        }
        if (question.field_type === 'select' || question.field_type === 'radio' || question.field_type === 'yes_no') {
            if (question.field_type === 'yes_no' && !options.length) {
                options = ['yes', 'no', 'not_sure'];
            }
            return '<select data-onboarding-field="' + key + '"><option value="">Select</option>' + options.map(function (option) {
                var optionValueText = optionValue(option);
                return '<option value="' + escapeFieldValue(optionValueText) + '"' + (fieldValue(value) === optionValueText ? ' selected' : '') + '>' + escapeHtml(optionLabel(option)) + '</option>';
            }).join('') + '</select>';
        }
        if (question.field_type === 'multiselect') {
            value = Array.isArray(value) ? value : [];
            return '<select multiple data-onboarding-field="' + key + '">' + options.map(function (option) {
                var optionValueText = optionValue(option);
                return '<option value="' + escapeFieldValue(optionValueText) + '"' + (value.indexOf(optionValueText) !== -1 ? ' selected' : '') + '>' + escapeHtml(optionLabel(option)) + '</option>';
            }).join('') + '</select>';
        }
        if (question.field_type === 'checkbox') {
            return '<input type="checkbox" data-onboarding-field="' + key + '"' + (value ? ' checked' : '') + '>';
        }
        if (question.field_type === 'plan_status') {
            value = value && typeof value === 'object' ? value : {};
            return '<div class="qn-plan-status"><select data-plan-field="' + key + '" data-plan-key="exists"><option value="">Exists?</option><option value="yes"' + (value.exists === 'yes' ? ' selected' : '') + '>Exists</option><option value="no"' + (value.exists === 'no' ? ' selected' : '') + '>Does not exist</option></select>' + renderUsDateInput('data-plan-field="' + key + '" data-plan-key="last_approved"', value.last_approved || '') + '<select data-plan-field="' + key + '" data-plan-key="board_approved"><option value="">Board approved?</option><option value="yes"' + (value.board_approved === 'yes' ? ' selected' : '') + '>Yes</option><option value="no"' + (value.board_approved === 'no' ? ' selected' : '') + '>No</option></select><input type="text" placeholder="Owner" data-plan-field="' + key + '" data-plan-key="owner" value="' + escapeFieldValue(value.owner || '') + '"></div>';
        }
        if (question.field_type === 'repeater') {
            value = Array.isArray(value) && value.length ? value : [{}];
            var columns = question.options && question.options.length ? question.options : ['note'];
            return '<div class="qn-repeater" data-repeater="' + key + '" data-columns="' + escapeHtml(JSON.stringify(columns)) + '">' +
                '<div class="qn-repeater-head">' + columns.map(function (column) { return '<span>' + escapeHtml(optionLabel(column)) + '</span>'; }).join('') + '</div>' +
                value.map(function (row, index) {
                    return renderRepeaterRow(key, columns, row, index);
                }).join('') + '<button class="qn-button qn-button-small" type="button" data-add-repeater="' + key + '"><span class="dashicons dashicons-plus-alt2"></span>Add Row</button></div>';
        }
        return '<input type="text" data-onboarding-field="' + key + '" value="' + escapeFieldValue(value) + '"' + (placeholder ? ' placeholder="' + escapeHtml(placeholder) + '"' : '') + '>';
    }

    function renderRepeaterRow(key, columns, row, index) {
        row = row || {};
        return '<div class="qn-repeater-row">' + columns.map(function (column) {
            return '<input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="' + escapeHtml(column) + '" value="' + escapeFieldValue(row[column] || '') + '">';
        }).join('') + '</div>';
    }

    function isCriticalAccessHospitalValue(value) {
        return ['yes', 'true', '1', 'critical_access_hospital', 'cah'].indexOf(String(value || '').toLowerCase()) !== -1;
    }

    function isCriticalAccessHospitalSelected() {
        var field = document.querySelector('[data-onboarding-field="is_critical_access_hospital"]');
        var value = field ? field.value : onboardingQuestionValue({question_key: 'is_critical_access_hospital'});
        return isCriticalAccessHospitalValue(value);
    }

    function updateStepOneCahBedFields() {
        var isCah = isCriticalAccessHospitalSelected();
        var acuteQuestion = document.querySelector('[data-question="acute_beds"]');
        var swingLicenseQuestion = document.querySelector('[data-question="licensed_for_swing_beds"]');
        var swingQuestion = document.querySelector('[data-question="swing_beds"]');
        if (acuteQuestion) {
            acuteQuestion.hidden = isCah;
        }
        if (swingLicenseQuestion) {
            swingLicenseQuestion.hidden = true;
        }
        if (swingQuestion) {
            swingQuestion.hidden = false;
        }
    }

    function updateStepOneBedWarning() {
        var warning = document.getElementById('qn-step1-bed-warning');
        if (!warning) {
            return;
        }
        updateStepOneCahBedFields();
        if (isCriticalAccessHospitalSelected()) {
            warning.hidden = true;
            return;
        }
        var licensed = Number(fieldValue((document.querySelector('[data-onboarding-field="licensed_beds"]') || {}).value));
        var swing = Number(fieldValue((document.querySelector('[data-onboarding-field="swing_beds"]') || {}).value));
        warning.hidden = !(licensed > 0 && swing > licensed);
    }

    function renderSwingBedConsistencyWarning() {
        return '<div class="qn-bed-warning qn-swing-bed-consistency-warning" data-swing-bed-consistency-warning role="status" hidden>' +
            '<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>' +
            '<div class="qn-swing-bed-consistency-copy">' +
                '<strong data-swing-bed-consistency-title></strong>' +
                '<span data-swing-bed-consistency-message></span>' +
                '<div class="qn-swing-bed-consistency-actions">' +
                    '<button type="button" class="qn-button qn-button-small" data-swing-bed-action="primary"></button>' +
                    '<button type="button" class="qn-button qn-button-small qn-button-secondary" data-swing-bed-action="secondary"></button>' +
                '</div>' +
            '</div>' +
        '</div>';
    }

    function swingBedServicesSelected(value) {
        var values = Array.isArray(value) ? value : (value ? [value] : []);
        return values.some(function (item) {
            var normalized = String(item || '').trim().toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '');
            return normalized === 'swing_bed_services';
        });
    }

    function getSwingBedConsistencyMismatch() {
        var onboarding = state.onboarding || {};
        var answers = onboarding.answers || {};
        var countField = document.querySelector('[data-onboarding-field="swing_beds"]');
        var serviceField = document.querySelector('[data-checklist="service_lines_core"] [data-checklist-field][value="swing_bed_services"]');
        var storedCount = answers.swing_beds !== undefined ? answers.swing_beds : onboarding.swing_beds;
        var swingBedCount = Number(fieldValue(countField ? countField.value : storedCount));
        var licensedBedField = document.querySelector('[data-onboarding-field="licensed_beds"]');
        var licensedBedCount = Number(fieldValue(licensedBedField ? licensedBedField.value : onboarding.licensed_beds));
        var offersSwingBedServices = serviceField ? serviceField.checked : swingBedServicesSelected(answers.service_lines_core);
        var hasSwingBedCount = swingBedCount > 0;

        if (hasSwingBedCount && !offersSwingBedServices) {
            return {
                code: 'count_without_service',
                title: 'Please confirm your swing-bed service',
                message: 'You entered ' + swingBedCount + ' swing bed' + (swingBedCount === 1 ? '' : 's') + ', but Swing Bed Services is not selected. Tell Scout which answer is correct.',
                primaryAction: 'add_service',
                primaryLabel: 'We offer this service',
                secondaryAction: 'clear_count',
                secondaryLabel: 'Clear the bed count'
            };
        }
        if (!hasSwingBedCount && offersSwingBedServices) {
            return {
                code: 'service_without_count',
                title: 'One detail needed for Swing Bed Services',
                message: 'You selected Swing Bed Services. How many' + (licensedBedCount > 0 ? ' of your ' + licensedBedCount + ' licensed beds' : ' licensed beds') + ' are approved for swing-bed use?',
                primaryAction: 'add_count',
                primaryLabel: 'Add swing-bed count',
                secondaryAction: 'remove_service',
                secondaryLabel: 'We do not offer this service'
            };
        }
        return null;
    }

    function updateSwingBedConsistencyWarning() {
        var mismatch = getSwingBedConsistencyMismatch();
        document.querySelectorAll('[data-swing-bed-consistency-warning]').forEach(function (warning) {
            var title = warning.querySelector('[data-swing-bed-consistency-title]');
            var message = warning.querySelector('[data-swing-bed-consistency-message]');
            var primary = warning.querySelector('[data-swing-bed-action="primary"]');
            var secondary = warning.querySelector('[data-swing-bed-action="secondary"]');
            warning.hidden = !mismatch;
            warning.setAttribute('data-mismatch', mismatch ? mismatch.code : '');
            if (title) {
                title.textContent = mismatch ? mismatch.title : '';
            }
            if (message) {
                message.textContent = mismatch ? mismatch.message : '';
            }
            [primary, secondary].forEach(function (button, index) {
                if (!button) {
                    return;
                }
                var prefix = index === 0 ? 'primary' : 'secondary';
                button.hidden = !mismatch;
                button.textContent = mismatch ? mismatch[prefix + 'Label'] : '';
                button.setAttribute('data-swing-bed-resolution', mismatch ? mismatch[prefix + 'Action'] : '');
            });
        });
    }

    function focusSwingBedCountField() {
        switchOnboardingStepBySection('hospital_director_info');
        window.setTimeout(function () {
            var field = document.querySelector('[data-onboarding-field="swing_beds"]');
            if (!field) {
                return;
            }
            field.scrollIntoView({behavior: 'smooth', block: 'center'});
            field.focus();
        }, 140);
    }

    function saveSwingBedResolution(action, trigger) {
        if (action === 'add_count') {
            focusSwingBedCountField();
            return;
        }

        var answers = state.onboarding && state.onboarding.answers ? state.onboarding.answers : {};
        var stepKey = '';
        var changes = {};
        var successMessage = '';
        if (action === 'remove_service' || action === 'add_service') {
            var services = Array.isArray(answers.service_lines_core) ? answers.service_lines_core.slice() : [];
            services = services.filter(function (service) { return service !== 'swing_bed_services'; });
            if (action === 'add_service') {
                services.push('swing_bed_services');
                successMessage = 'Swing Bed Services selected.';
            } else {
                successMessage = 'Swing Bed Services removed.';
            }
            stepKey = 'services_clinical_model';
            changes.service_lines_core = services;
        } else if (action === 'clear_count') {
            stepKey = 'hospital_director_info';
            changes.swing_beds = '';
            successMessage = 'Swing-bed count cleared.';
        } else {
            return;
        }

        var restoreButton = setButtonLoading(trigger, 'Saving...');
        api('/onboarding/save', {
            method: 'POST',
            body: {
                organization_id: state.onboardingOrganizationId,
                step_key: stepKey,
                answers: changes
            }
        }).then(function () {
            state.onboarding.answers = Object.assign({}, answers, changes);
            if (changes.swing_beds !== undefined) {
                state.onboarding.swing_beds = changes.swing_beds;
            }
            showToast(successMessage, 'success');
            return loadOnboarding(state.onboardingOrganizationId, {showLoading: false});
        }).catch(function (error) {
            showToast(friendlyApiErrorMessage(error, 'Scout could not update this answer. Please try again.'), 'warning');
        }).finally(function () {
            restoreButton();
        });
    }

    function updateStepOneAffiliationUI() {
        var status = document.querySelector('[data-onboarding-field="independent_or_system"]');
        var question = document.querySelector('[data-question="system_network_name"]');
        var field = document.querySelector('[data-onboarding-field="system_network_name"]');
        if (!status || !question || !field) {
            return;
        }
        var settings = affiliationSettingsForStatus(status.value);
        question.hidden = !settings;
        question.style.display = settings ? '' : 'none';
        question.classList.toggle('qn-affiliation-visible', !!settings);
        if (!settings) {
            field.value = '';
            return;
        }
        var label = question.querySelector('span');
        if (label) {
            label.textContent = settings.label;
        }
        field.placeholder = settings.placeholder;
    }

    function updateStepOneQualityLeaderTitleUI() {
        var title = document.querySelector('[data-onboarding-field="quality_leader_title"]');
        var otherQuestion = document.querySelector('[data-question="quality_leader_title_other"]');
        var otherField = document.querySelector('[data-onboarding-field="quality_leader_title_other"]');
        if (!title || !otherQuestion || !otherField) {
            return;
        }
        var showOther = fieldValue(title.value).trim().toLowerCase() === 'other';
        otherQuestion.hidden = !showOther;
        otherQuestion.style.display = showOther ? '' : 'none';
        otherQuestion.classList.toggle('qn-quality-title-other-visible', showOther);
        if (!showOther) {
            otherField.value = '';
        }
        otherField.placeholder = 'Enter Quality Leader title';
    }

    function affiliationSettingsForStatus(value) {
        var selected = normalizeAffiliationStatus(value);
        var copy = {
            system_owned: {
                label: 'System name',
                placeholder: 'Enter health system name'
            },
            network_affiliated: {
                label: 'Network name',
                placeholder: 'Enter network name'
            },
            other: {
                label: 'Describe affiliation',
                placeholder: 'Enter affiliation or ownership description'
            }
        };
        return copy[selected] || null;
    }

    function normalizeAffiliationStatus(value) {
        value = fieldValue(value).trim().toLowerCase().replace(/[\s-]+/g, '_');
        if (value === 'system_owned' || value === 'system') {
            return 'system_owned';
        }
        if (value === 'network_affiliated' || value === 'network') {
            return 'network_affiliated';
        }
        if (value === 'other' || value === 'managed_services') {
            return 'other';
        }
        return 'independent';
    }

    function updateStepTwoConditionalUI() {
        var accreditorQuestion = document.querySelector('[data-question="accrediting_body"]');
        var accreditor = document.querySelector('[data-onboarding-field="accrediting_body"]');
        var accreditorOtherQuestion = document.querySelector('[data-question="accrediting_body_other"]');
        var accreditorOther = document.querySelector('[data-onboarding-field="accrediting_body_other"]');
        var lifeStatus = document.querySelector('[data-onboarding-field="life_safety_survey_agency_status"]');
        var lifeNameQuestion = document.querySelector('[data-question="life_safety_survey_agency"]');
        var lifeUrlQuestion = document.querySelector('[data-question="life_safety_survey_agency_url"]');
        var lifeName = document.querySelector('[data-onboarding-field="life_safety_survey_agency"]');
        var lifeUrl = document.querySelector('[data-onboarding-field="life_safety_survey_agency_url"]');
        var lifeSafetyBranch = document.querySelector('[data-life-safety-branch]');
        var otherSurveyStatus = document.querySelector('[data-onboarding-field="other_certification_licensing_surveys_status"]');
        var otherSurveyQuestion = document.querySelector('[data-question="other_certification_licensing_surveys"]');
        var otherSurveyDetails = document.querySelector('[data-onboarding-field="other_certification_licensing_surveys"]');
        var processValue = currentOnboardingFieldValue('survey_compliance_process');
        var showAccreditor = legacyStepTwoValue('survey_compliance_process', processValue) === 'deemed_accreditation_body_survey';
        var showOtherAccreditor = showAccreditor && accreditor && legacyStepTwoValue('accrediting_body', accreditor.value) === 'other';
        var showDifferentLifeSafetyAgency = lifeStatus && lifeStatus.value === 'different_agency';
        var showOtherSurveyDetails = otherSurveyStatus && otherSurveyStatus.value === 'yes';
        var branchRow = document.querySelector('.qn-survey-branch-row');

        updateSurveyPathwayCardState();
        if (branchRow) {
            branchRow.hidden = !showAccreditor;
            branchRow.style.display = showAccreditor ? '' : 'none';
        }
        toggleConditionalQuestion(accreditorQuestion, showAccreditor, accreditor);
        toggleConditionalQuestion(accreditorOtherQuestion, showOtherAccreditor, accreditorOther);
        if (lifeSafetyBranch) {
            lifeSafetyBranch.hidden = !showDifferentLifeSafetyAgency;
            lifeSafetyBranch.style.display = showDifferentLifeSafetyAgency ? '' : 'none';
        }
        toggleConditionalQuestion(lifeNameQuestion, showDifferentLifeSafetyAgency, lifeName);
        toggleConditionalQuestion(lifeUrlQuestion, showDifferentLifeSafetyAgency, lifeUrl);
        toggleConditionalQuestion(otherSurveyQuestion, showOtherSurveyDetails, otherSurveyDetails);
    }

    function currentOnboardingFieldValue(key) {
        var fields = Array.prototype.slice.call(document.querySelectorAll('[data-onboarding-field="' + key + '"]'));
        if (!fields.length) {
            return '';
        }
        if (fields[0].type === 'radio') {
            var checked = fields.find(function (field) {
                return field.checked;
            });
            return checked ? checked.value : '';
        }
        if (fields[0].type === 'checkbox') {
            return fields[0].checked ? 'yes' : '';
        }
        return fields[0].value || '';
    }

    function updateSurveyPathwayCardState() {
        document.querySelectorAll('.qn-survey-pathway-card').forEach(function (card) {
            var input = card.querySelector('input[type="radio"]');
            card.classList.toggle('qn-survey-pathway-selected', !!(input && input.checked));
        });
    }

    function toggleConditionalQuestion(question, shouldShow, field) {
        if (!question) {
            return;
        }
        question.hidden = !shouldShow;
        question.style.display = shouldShow ? '' : 'none';
        if (!shouldShow && field && field.value) {
            field.value = '';
        }
    }

    function updateStepThreeConditionalUI() {
        var surgery = document.querySelector('[data-onboarding-field="surgery_invasive_procedures"]');
        var surgeryNotOffered = surgery && surgery.value === 'not_offered';
        document.querySelectorAll('[data-question="surgery_procedure_types"], [data-question="anesthesia_moderate_sedation_model"]').forEach(function (node) {
            node.classList.toggle('qn-question-deemphasized', !!surgeryNotOffered);
            var note = node.querySelector('.qn-surgery-not-offered-note');
            if (note) {
                note.hidden = !surgeryNotOffered;
            }
        });

        var bloodBankValue = currentOnboardingFieldValue('blood_bank_model');
        var transfusions = document.querySelector('[data-onboarding-field="transfusions_per_year"]');
        var bloodNote = document.getElementById('qn-blood-not-applicable-note');
        var noBlood = bloodBankValue === 'no_blood_products_on_site';
        var zeroTransfusions = !transfusions || transfusions.value === '' || Number(transfusions.value) === 0;
        if (bloodNote) {
            bloodNote.hidden = !(noBlood && zeroTransfusions);
        }

        var visiting = document.querySelector('[data-onboarding-field="visiting_specialists"]');
        var contractedNote = document.getElementById('qn-contracted-monitoring-note');
        if (contractedNote) {
            contractedNote.hidden = !(visiting && visiting.value === 'yes');
        }

        var otherModelParents = {
            laboratory_model_other: 'laboratory_model',
            radiology_model_other: 'radiology_model',
            pharmacy_model_other: 'pharmacy_model',
            anesthesia_moderate_sedation_model_other: 'anesthesia_moderate_sedation_model',
            blood_bank_model_other: 'blood_bank_model'
        };
        Object.keys(otherModelParents).forEach(function (detailKey) {
            var parentKey = otherModelParents[detailKey];
            var selected = Array.prototype.slice.call(document.querySelectorAll('[data-onboarding-field="' + parentKey + '"]:checked, [data-checklist-field="' + parentKey + '"]:checked')).map(function (field) {
                return field.value;
            });
            var detailQuestion = document.querySelector('[data-question="' + detailKey + '"]');
            var showDetail = selected.indexOf('other') !== -1;
            if (detailQuestion) {
                detailQuestion.hidden = !showDetail;
                detailQuestion.style.display = showDetail ? '' : 'none';
            }
        });
    }

    function updateStepFourConditionalUI() {
        var boardTiming = document.querySelector('[data-structured-field="board_agenda_timing"][data-structured-key="timing"]');
        var boardDetails = document.querySelector('[data-board-agenda-details]');
        if (boardDetails) {
            boardDetails.hidden = !(boardTiming && boardTiming.value === 'other');
        }
        var defaultLeadTime = document.querySelector('[data-onboarding-field="report_lead_time"]');
        var defaultCustom = document.querySelector('[data-meeting-prep-custom-default]');
        if (defaultCustom) {
            var showDefaultCustom = !!(defaultLeadTime && defaultLeadTime.value === 'custom');
            defaultCustom.hidden = !showDefaultCustom;
            var defaultCustomInput = defaultCustom.querySelector('[data-meeting-prep-custom-input]');
            if (defaultCustomInput) {
                defaultCustomInput.required = showDefaultCustom;
                if (!showDefaultCustom) {
                    defaultCustomInput.setCustomValidity('');
                }
            }
        }
        document.querySelectorAll('[data-meeting-prep-custom-row]').forEach(function (customPanel) {
            var row = customPanel.closest('.qn-repeater-row');
            var leadTime = row ? row.querySelector('[data-column="prep_lead_time"]') : null;
            var showCustom = !!(leadTime && leadTime.value === 'custom');
            customPanel.hidden = !showCustom;
            var customInput = customPanel.querySelector('[data-meeting-prep-custom-input]');
            if (customInput) {
                customInput.required = showCustom;
                if (!showCustom) {
                    customInput.setCustomValidity('');
                }
            }
        });
    }

    function validateMeetingPreparationCustomFields(step) {
        if (!step || step.section_key !== 'committees_reporting') {
            return true;
        }
        var missing = null;
        document.querySelectorAll('[data-meeting-prep-custom-default], [data-meeting-prep-custom-row]').forEach(function (panel) {
            var input = panel.querySelector('[data-meeting-prep-custom-input]');
            if (!panel.hidden && input) {
                var isMissing = !fieldValue(input.value).trim();
                input.setCustomValidity(isMissing ? 'Enter the custom preparation lead time.' : '');
                if (isMissing && !missing) {
                    missing = input;
                }
            }
        });
        if (!missing) {
            return true;
        }
        setText('#qn-onboarding-message', 'Enter the custom preparation lead time before continuing.');
        setOnboardingSaveStatus('unsaved', 'Custom lead time required');
        showToast('Enter the custom preparation lead time before continuing.', 'warning');
        missing.focus();
        return false;
    }

    function updateStepSevenConditionalUI() {
        var newRole = document.querySelector('[data-onboarding-field="new_to_quality_director_role"]');
        var first30Question = document.querySelector('[data-question="activate_first_30_days_track"]');
        var note = document.getElementById('qn-step7-learning-note');
        var inlineNote = document.getElementById('qn-step7-first-30-note');
        var value = newRole ? newRole.value : '';
        var help = stepSevenFirst30HelpForValue(value);
        if (first30Question) {
            first30Question.classList.toggle('qn-step7-first-30-highlight', value === 'yes');
        }
        if (note) {
            note.classList.toggle('qn-step7-first-30-highlight', value === 'yes');
            var noteText = note.querySelector('span:last-child');
            if (noteText) {
                noteText.textContent = help;
            }
        }
        if (inlineNote) {
            inlineNote.textContent = help;
        }
    }

    function updateMultiselectUI(multiselect) {
        if (!multiselect) {
            return;
        }
        var key = multiselect.getAttribute('data-checklist');
        var options = key ? multiselectOptionsForKey(key) : [];
        var checked = Array.prototype.slice.call(multiselect.querySelectorAll('[data-checklist-field]:checked')).map(function (field) {
            return field.value;
        });
        var chips = multiselect.querySelector('.qn-selected-chips');
        if (chips) {
            chips.hidden = !checked.length;
            chips.innerHTML = checked.map(function (item) {
                return '<span class="qn-selected-chip" data-chip-value="' + escapeFieldValue(item) + '">' + escapeHtml(optionLabelByValue(options, item)) + '<button class="qn-chip-remove" type="button" data-multiselect-remove="' + escapeFieldValue(item) + '" aria-label="Remove ' + escapeHtml(optionLabelByValue(options, item)) + '"><span class="dashicons dashicons-no-alt"></span></button></span>';
            }).join('');
        }
    }

    function updateStepSevenGoalTiles(tileGroup) {
        if (!tileGroup) {
            return;
        }
        var limit = parseInt(tileGroup.getAttribute('data-goal-limit') || '0', 10);
        var fields = Array.prototype.slice.call(tileGroup.querySelectorAll('[data-checklist-field]'));
        var checked = fields.filter(function (field) { return field.checked; });
        var count = tileGroup.querySelector('[data-goal-count]');
        if (count) {
            count.textContent = limit > 0 ? checked.length + ' of ' + limit + ' selected' : checked.length + ' selected';
        }
        if (limit > 0) {
            fields.forEach(function (field) {
                field.disabled = !field.checked && checked.length >= limit;
            });
            tileGroup.classList.toggle('qn-step7-goal-limit-reached', checked.length >= limit);
        }
    }

    function closeMultiselects(except) {
        document.querySelectorAll('.qn-multiselect.qn-multiselect-open').forEach(function (multiselect) {
            if (except && multiselect === except) {
                return;
            }
            multiselect.classList.remove('qn-multiselect-open');
            var trigger = multiselect.querySelector('[data-multiselect-trigger]');
            if (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function refreshRepeaterCardLabels(repeater) {
        if (!repeater) {
            return;
        }
        var prefix = 'Survey';
        if (repeater.getAttribute('data-repeater-style') === 'survey-history') {
            prefix = 'Survey / review';
        }
        if (repeater.getAttribute('data-repeater-style') === 'committee-card') {
            prefix = 'Committee';
        }
        if (repeater.getAttribute('data-repeater-style') === 'report-card') {
            prefix = 'Report';
        }
        if (repeater.getAttribute('data-repeater-style') === 'backup-user-card') {
            prefix = 'Backup user';
        }
        repeater.querySelectorAll('.qn-survey-history-row').forEach(function (row, index) {
            var title = row.querySelector('.qn-survey-card-header strong');
            if (title) {
                if (repeater.getAttribute('data-repeater-style') === 'committee-card') {
                    var committeeField = row.querySelector('[data-column="committee_name"]');
                    title.textContent = optionLabelByValue(stepFourOptions('committee_name'), committeeField ? committeeField.value : '') || (prefix + ' ' + (index + 1));
                } else {
                    title.textContent = prefix + ' ' + (index + 1);
                }
            }
            row.querySelectorAll('[data-repeater-row]').forEach(function (field) {
                field.setAttribute('data-index', index);
            });
        });
    }

    function collectRepeaterRowData(row) {
        var item = {};
        if (!row) {
            return item;
        }
        row.querySelectorAll('[data-repeater-row]').forEach(function (field) {
            item[field.getAttribute('data-column') || 'note'] = dateFieldValue(field);
        });
        row.querySelectorAll('[data-repeater-detail]').forEach(function (field) {
            setNestedValue(item, field.getAttribute('data-detail-path'), dateFieldValue(field));
        });
        var repeater = row.closest('[data-repeater]');
        if (repeater && repeater.getAttribute('data-repeater-style') === 'committee-card') {
            item.frequency_timing = committeeTimingSummary(item);
        }
        return item;
    }

    function refreshDueDatePanel(field) {
        if (!field || !['frequency', 'due_date_rule'].includes(field.getAttribute('data-column'))) {
            return;
        }
        var row = field.closest('.qn-repeater-row');
        if (!row) {
            return;
        }
        var panel = row.querySelector('[data-due-rule-panel]');
        if (!panel) {
            return;
        }
        var data = collectRepeaterRowData(row);
        var repeater = row.closest('[data-repeater]');
        var key = repeater ? repeater.getAttribute('data-repeater') : 'reporting_obligations';
        var index = Number(field.getAttribute('data-index')) || 0;
        panel.innerHTML = renderDueDateDetails(key, index, data.frequency || '', data.due_date_rule || '', data.due_date_details || {}) +
            (fieldValue(data.due_dates || '') ? '<small class="qn-legacy-note">Previous due date text: ' + escapeHtml(data.due_dates) + '</small><input type="hidden" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="due_dates" value="' + escapeFieldValue(data.due_dates) + '">' : '');
    }

    function collectOnboardingAnswers() {
        var answers = {};
        var activeQuestionKeys = {};
        (state.onboarding && state.onboarding.questions ? state.onboarding.questions : []).forEach(function (question) {
            if (question && question.question_key) {
                activeQuestionKeys[question.question_key] = true;
            }
        });
        document.querySelectorAll('[data-onboarding-field]').forEach(function (field) {
            var owner = field.closest('[data-question]');
            if (owner && owner.hidden) {
                return;
            }
            var key = field.getAttribute('data-onboarding-field');
            if (field.hasAttribute('data-single-select-field')) {
                if (field.checked) {
                    answers[key] = field.value;
                } else if (answers[key] === undefined) {
                    answers[key] = '';
                }
            } else if (field.type === 'checkbox') {
                answers[key] = field.checked;
            } else if (field.type === 'radio') {
                if (field.checked) {
                    answers[key] = field.value;
                } else if (answers[key] === undefined) {
                    answers[key] = '';
                }
            } else if (field.multiple) {
                answers[key] = Array.prototype.slice.call(field.selectedOptions).map(function (option) { return option.value; });
            } else if (field.type === 'number') {
                var numeric = field.value === '' ? '' : Math.max(0, Math.floor(Number(field.value) || 0));
                answers[key] = numeric === '' ? '' : String(numeric);
            } else {
                answers[key] = dateFieldValue(field);
            }
        });
        if (answers.independent_or_system !== undefined) {
            answers.independent_or_system = normalizeAffiliationStatus(answers.independent_or_system);
            if (answers.independent_or_system === 'independent' || !answers.independent_or_system) {
                answers.system_network_name = '';
            }
        }
        document.querySelectorAll('[data-plan-field]').forEach(function (field) {
            var key = field.getAttribute('data-plan-field');
            var planKey = field.getAttribute('data-plan-key');
            answers[key] = answers[key] || {};
            answers[key][planKey] = dateFieldValue(field);
        });
        document.querySelectorAll('[data-structured-field]').forEach(function (field) {
            var key = field.getAttribute('data-structured-field');
            var dataKey = field.getAttribute('data-structured-key');
            answers[key] = answers[key] || {};
            answers[key][dataKey] = dateFieldValue(field);
        });
        document.querySelectorAll('[data-plan-policy-inventory]').forEach(function (inventory) {
            var key = inventory.getAttribute('data-plan-policy-inventory');
            var rows = {};
            inventory.querySelectorAll('[data-plan-policy-field]').forEach(function (field) {
                var policyKey = field.getAttribute('data-plan-policy-field');
                var dataKey = field.getAttribute('data-plan-policy-key');
                rows[policyKey] = rows[policyKey] || {};
                rows[policyKey][dataKey] = dateFieldValue(field);
            });
            var requiredRows = planPolicyInventoryRows().map(function (template) {
                var item = rows[template.key] || {};
                item.policy_key = template.key;
                item.policy_name = template.name;
                item.category = template.category;
                item.guidance = template.guidance;
                item.upload_status = item.upload_status || 'not_configured';
                item.scout_status = item.scout_status || 'structured_ready';
                return item;
            });
            var requiredKeys = {};
            requiredRows.forEach(function (row) {
                requiredKeys[row.policy_key] = true;
            });
            var additionalRows = Object.keys(rows).filter(function (policyKey) {
                return !requiredKeys[policyKey] &&
                    (String(rows[policyKey].is_additional_plan || '') === '1' || rows[policyKey].is_additional_plan === true);
            }).map(function (policyKey) {
                var item = rows[policyKey];
                item.policy_key = policyKey;
                item.policy_name = fieldValue(item.policy_name);
                item.category = item.category || 'Additional plan';
                item.status = 'in_place';
                item.is_additional_plan = '1';
                item.upload_status = item.upload_status || 'not_configured';
                item.scout_status = item.scout_status || 'structured_ready';
                return item;
            });
            answers[key] = requiredRows.concat(additionalRows);
        });
        document.querySelectorAll('[data-checklist]').forEach(function (checklist) {
            var key = checklist.getAttribute('data-checklist');
            answers[key] = [];
            checklist.querySelectorAll('[data-checklist-field]:checked').forEach(function (field) {
                answers[key].push(field.value);
            });
        });
        document.querySelectorAll('[data-repeater]').forEach(function (repeater) {
            var key = repeater.getAttribute('data-repeater');
            answers[key] = [];
            repeater.querySelectorAll('.qn-repeater-row').forEach(function (row) {
                var item = {};
                row.querySelectorAll('[data-repeater-row]').forEach(function (field) {
                    item[field.getAttribute('data-column') || 'note'] = dateFieldValue(field);
                });
                row.querySelectorAll('[data-repeater-detail]').forEach(function (field) {
                    setNestedValue(item, field.getAttribute('data-detail-path'), dateFieldValue(field));
                });
                if (repeater.getAttribute('data-repeater-style') === 'committee-card') {
                    item.frequency_timing = committeeTimingSummary(item);
                }
                if (row.classList.contains('qn-reporting-other-row') && !reportingOtherRowHasValue(item)) {
                    return;
                }
                answers[key].push(item);
            });
        });
        return Object.keys(answers).reduce(function (activeAnswers, key) {
            if (activeQuestionKeys[key]) {
                activeAnswers[key] = answers[key];
            }
            return activeAnswers;
        }, {});
    }

    function reportingOtherRowHasValue(item) {
        if (!item) {
            return false;
        }
        return !!(fieldValue(item.report_name || '') ||
            fieldValue(item.submit_to_method || '') ||
            fieldValue(item.frequency || '') ||
            fieldValue(item.due_dates || '') ||
            (item.due_date_details && fieldValue(item.due_date_details.next_due_date || '')));
    }

    function setNestedValue(target, path, value) {
        if (!path) {
            return;
        }
        var parts = path.split('.');
        var cursor = target;
        parts.forEach(function (part, index) {
            if (index === parts.length - 1) {
                cursor[part] = value;
                return;
            }
            cursor[part] = cursor[part] && typeof cursor[part] === 'object' && !Array.isArray(cursor[part]) ? cursor[part] : {};
            cursor = cursor[part];
        });
    }

    function saveOnboardingStep(advance, trigger, options) {
        options = options || {};
        if (!state.onboarding || !canEditOnboarding()) {
            if (advance && state.onboarding && state.onboarding.steps && state.onboardingIndex < state.onboarding.steps.length - 1) {
                state.onboardingIndex++;
                renderOnboarding();
            }
            return Promise.resolve();
        }
        var step = state.onboarding.steps[state.onboardingIndex];
        if (!canEditOnboardingStep(step)) {
            if (advance && state.onboardingIndex < state.onboarding.steps.length - 1) {
                state.onboardingIndex++;
                renderOnboarding();
            }
            return Promise.resolve();
        }
        if (!validateMeetingPreparationCustomFields(step)) {
            return Promise.resolve(false);
        }
        var restoreButton = setButtonLoading(trigger, advance ? 'Saving...' : 'Saving...');
        setText('#qn-onboarding-message', 'Saving...');
        setOnboardingSaveStatus('saving', 'Saving...');
        return api('/onboarding/save', {
            method: 'POST',
            timeout: options.timeout || 60000,
            body: {
                organization_id: state.onboardingOrganizationId,
                step_key: step.section_key,
                answers: collectOnboardingAnswers(),
                mark_reviewed: !!advance
            }
        }).then(function (result) {
            if (result && result.progress && state.onboarding) {
                state.onboarding.progress = result.progress;
                renderStepper(state.onboarding.steps || []);
            }
            setText('#qn-onboarding-message', 'Saved.');
            setOnboardingSaveStatus('saved', 'Saved');
            showToast('Onboarding saved.', 'success');
            if (advance && state.onboardingIndex < state.onboarding.steps.length - 1) {
                state.onboardingIndex++;
            }
            return loadOnboarding(state.onboardingOrganizationId, {showLoading: false}).then(function (result) {
                if (advance) {
                    scrollOnboardingStepToTop();
                }
                return result;
            });
        }).catch(function (error) {
            setText('#qn-onboarding-message', error.message);
            setOnboardingSaveStatus('error', 'Could not save');
            if (options.rejectOnError) {
                throw error;
            }
        }).finally(function () {
            restoreButton();
        });
    }

    function saveOnboardingStepInBackground(step, answers) {
        if (!state.onboarding || !state.onboarding.can_edit || !step || !canEditOnboardingStep(step)) {
            return Promise.resolve();
        }
        state.onboardingBackgroundSaving = true;
        setText('#qn-onboarding-message', 'Saving...');
        setOnboardingSaveStatus('saving', 'Saving...');
        return api('/onboarding/save', {
            method: 'POST',
            body: {
                organization_id: state.onboardingOrganizationId,
                step_key: step.section_key,
                answers: answers || {}
            }
        }).then(function (result) {
            if (result && result.progress && state.onboarding) {
                state.onboarding.progress = result.progress;
                renderStepper(state.onboarding.steps || []);
            }
            setText('#qn-onboarding-message', 'Saved.');
            setOnboardingSaveStatus('saved', 'Saved');
        }).catch(function (error) {
            setText('#qn-onboarding-message', error.message);
            setOnboardingSaveStatus('error', 'Could not save');
        }).finally(function () {
            state.onboardingBackgroundSaving = false;
        });
    }

    function scrollOnboardingStepToTop() {
        window.setTimeout(function () {
            var form = document.getElementById('qn-onboarding-form');
            if (!form) {
                return;
            }
            form.scrollIntoView({behavior: 'smooth', block: 'start'});
            var heading = document.getElementById('qn-onboarding-step-title');
            if (heading) {
                heading.setAttribute('tabindex', '-1');
                try {
                    heading.focus({preventScroll: true});
                } catch (error) {
                    heading.focus();
                }
            }
        }, 60);
    }

    function autosaveOnboardingStep() {
        if (!state.onboarding || !Array.isArray(state.onboarding.steps)) {
            return Promise.resolve();
        }
        var step = state.onboarding.steps[state.onboardingIndex];
        var answers = collectOnboardingAnswers();
        if (canEditOnboardingStep(step)) {
            state.onboarding.answers = Object.assign({}, state.onboarding.answers || {}, answers);
        }
        return saveOnboardingStepInBackground(step, answers);
    }

    function switchOnboardingStepInstant(targetIndex) {
        if (!state.onboarding || !state.onboarding.steps) {
            return;
        }
        targetIndex = Math.max(0, Math.min(state.onboarding.steps.length - 1, Number(targetIndex) || 0));
        if (targetIndex === state.onboardingIndex) {
            return;
        }
        window.clearTimeout(state.autosaveTimer);
        var previousStep = state.onboarding.steps[state.onboardingIndex];
        var answers = collectOnboardingAnswers();
        if (canEditOnboardingStep(previousStep)) {
            state.onboarding.answers = Object.assign({}, state.onboarding.answers || {}, answers);
        }
        state.onboardingIndex = targetIndex;
        markOnboardingStepActive(targetIndex);
        window.clearTimeout(state.onboardingSwitchTimer);
        state.onboardingSwitchTimer = window.setTimeout(function () {
            renderOnboarding();
            scrollOnboardingStepToTop();
            if (canEditOnboardingStep(previousStep)) {
                saveOnboardingStepInBackground(previousStep, answers);
            }
        }, 0);
    }

    function switchOnboardingStepBySection(sectionKey) {
        if (!sectionKey || !state.onboarding || !Array.isArray(state.onboarding.steps)) {
            return;
        }
        var targetIndex = state.onboarding.steps.findIndex(function (step) {
            return step && step.section_key === sectionKey;
        });
        if (targetIndex >= 0) {
            switchOnboardingStepInstant(targetIndex);
        }
    }

    function applyOnboardingUrlSection() {
        if (state.onboardingUrlSectionApplied || !state.onboarding || !Array.isArray(state.onboarding.steps)) {
            return false;
        }
        var sectionKey = getUrlOnboardingSection();
        if (!sectionKey) {
            state.onboardingUrlSectionApplied = true;
            return false;
        }
        if (normalizeSectionTarget(window.location.hash ? window.location.hash.replace('#', '') : '') !== 'day-0-setup') {
            return false;
        }
        var targetIndex = state.onboarding.steps.findIndex(function (step) {
            return step && step.section_key === sectionKey;
        });
        if (targetIndex < 0) {
            state.onboardingUrlSectionApplied = true;
            return false;
        }
        if (targetIndex === state.onboardingIndex) {
            state.onboardingUrlSectionApplied = true;
            return false;
        }
        state.onboardingUrlSectionApplied = true;
        switchOnboardingStepInstant(targetIndex);
        return true;
    }

    function applyOnboardingUrlQuestionFocus() {
        if (state.onboardingUrlQuestionApplied || !state.onboarding) {
            return;
        }
        var questionKey = getUrlOnboardingQuestion();
        if (!questionKey) {
            state.onboardingUrlQuestionApplied = true;
            return;
        }
        if (normalizeSectionTarget(window.location.hash ? window.location.hash.replace('#', '') : '') !== 'day-0-setup') {
            return;
        }
        state.onboardingUrlQuestionApplied = true;
        window.setTimeout(function () {
            var target = findOnboardingQuestionElement(questionKey);
            if (!target) {
                return;
            }
            target.classList.add('qn-question-url-focus');
            target.scrollIntoView({behavior: 'smooth', block: 'center'});
            var field = target.querySelector('input:not([type="hidden"]), select, textarea, button');
            if (field && typeof field.focus === 'function') {
                try {
                    field.focus({preventScroll: true});
                } catch (error) {
                    field.focus();
                }
            }
            window.setTimeout(function () {
                target.classList.remove('qn-question-url-focus');
            }, 4200);
        }, 120);
    }

    function findOnboardingQuestionElement(questionKey) {
        var questions = document.querySelectorAll('[data-question]');
        for (var index = 0; index < questions.length; index += 1) {
            if (questions[index].getAttribute('data-question') === questionKey) {
                return questions[index];
            }
        }
        return null;
    }

    function submitOnboarding(trigger) {
        if (state.onboardingSubmitting) {
            return;
        }
        if (!state.onboarding || state.onboardingIndex < state.onboarding.steps.length - 1) {
            setText('#qn-onboarding-message', 'Final setup can only be submitted from the last step.');
            return;
        }
        var finalConfirmation = document.querySelector('[data-onboarding-field="final_review_confirmation"]');
        if (finalConfirmation && !finalConfirmation.checked) {
            var message = document.getElementById('qn-final-review-message');
            if (message) {
                message.hidden = false;
            }
            setText('#qn-onboarding-message', 'Please confirm that the information is ready for Scout.');
            showToast('Please confirm that the information is ready for Scout.', 'warning');
            finalConfirmation.focus();
            return;
        }
        state.onboardingSubmitting = true;
        var restoreButton = setButtonLoading(trigger, 'Starting Scout...');
        setText('#qn-onboarding-message', 'Saving your setup and starting Scout...');
        saveOnboardingStep(false, null, {rejectOnError: true, timeout: 60000}).then(function () {
            setText('#qn-onboarding-message', 'Starting your initial Scout workspace...');
            return api('/onboarding/submit', {method: 'POST', timeout: 60000, body: {organization_id: state.onboardingOrganizationId}});
        }).then(function (result) {
            var completionMessage = 'You\u2019re all set. Scout is now building your initial hospital workspace from the information you reviewed. You can return anytime to update it.';
            setText('#qn-onboarding-message', completionMessage);
            showToast('Hospital Setup saved. Scout is building your initial workspace.', 'success');
            return loadOnboarding(state.onboardingOrganizationId, {showLoading: false}).then(function () {
                return loadScoutRuns(state.onboardingOrganizationId, {skipAutoGenerate: true});
            }).then(function () {
                generateScoutPreview(null, {automatic: true});
                return true;
            });
        }).catch(function (error) {
            var message = friendlyApiErrorMessage(error, 'Final setup could not be submitted. Please try again or contact support.');
            setText('#qn-onboarding-message', message);
            setOnboardingSaveStatus('error', 'Submit failed');
            showToast(message, 'warning');
            focusOnboardingValidationError(error);
        }).finally(function () {
            state.onboardingSubmitting = false;
            restoreButton();
            renderOnboarding();
        });
    }

    function focusOnboardingValidationError(error) {
        var questionKey = error && error.questionKey ? error.questionKey : '';
        if (!questionKey || !state.onboarding) {
            return;
        }
        var question = (state.onboarding.questions || []).find(function (item) {
            return item.question_key === questionKey;
        });
        var section = question ? (state.onboarding.sections || []).find(function (item) {
            return Number(item.id) === Number(question.section_id);
        }) : null;
        var targetIndex = section ? (state.onboarding.steps || []).findIndex(function (step) {
            return step.section_key === section.section_key;
        }) : -1;
        if (targetIndex >= 0 && targetIndex !== state.onboardingIndex) {
            state.onboardingIndex = targetIndex;
            renderOnboarding();
        }
        window.setTimeout(function () {
            var target = findOnboardingQuestionElement(questionKey);
            if (!target) {
                return;
            }
            target.classList.add('qn-question-url-focus');
            target.scrollIntoView({behavior: 'smooth', block: 'center'});
            var field = target.querySelector('input:not([type="hidden"]), select, textarea, button');
            if (field) {
                field.focus();
            }
        }, 120);
    }

    function closeInviteModal() {
        var modal = document.getElementById('qn-invite-modal');
        var inviteState = document.getElementById('qn-invite-state');
        var org = document.getElementById('qn-invite-organization');
        var role = document.getElementById('qn-invite-role');
        var stateField = document.getElementById('qn-invite-state-field');
        var orgField = document.getElementById('qn-invite-organization-field');
        var roleField = document.getElementById('qn-invite-role-field');
        var fixedContext = document.getElementById('qn-invite-fixed-context');
        if (inviteState) {
            inviteState.value = '';
            inviteState.disabled = false;
            syncSearchableSelect(inviteState);
        }
        if (org) {
            org.disabled = false;
            syncSearchableSelect(org);
        }
        if (role) {
            role.disabled = false;
        }
        if (stateField) {
            stateField.hidden = false;
        }
        if (orgField) {
            orgField.hidden = false;
        }
        if (roleField) {
            roleField.hidden = false;
        }
        if (fixedContext) {
            fixedContext.hidden = true;
        }
        if (modal) {
            modal.hidden = true;
        }
    }

    function saveInvite(event) {
        event.preventDefault();
        var form = event.currentTarget;
        var restoreButton = setButtonLoading(form.querySelector('[type="submit"]'), 'Sending...');
        var context = state.inviteContext;
        var payload = {
            full_name: form.querySelector('[name="full_name"]').value,
            email: form.querySelector('[name="email"]').value,
            qualinav_role: state.fixedInviteRole || form.querySelector('[name="qualinav_role"]').value
        };
        var orgField = form.querySelector('[name="organization_id"]');
        if (context === 'admin') {
            payload.organization_id = state.fixedInviteOrganization || (orgField ? orgField.value : '');
        }
        setText('#qn-invite-form-message', 'Sending...');
        api(context === 'admin' ? '/admin/users/invite' : '/hospital/users/invite', {method: 'POST', body: payload}).then(function (invitation) {
            if (invitation && invitation.email_failed) {
                showToast(invitation.email_error || 'Invite was created, but email delivery failed. Configure SMTP/mail transport or resend after mail is fixed.', 'warning');
            } else {
                showToast('Invite sent.', 'success');
            }
            closeInviteModal();
            return context === 'admin' ? refreshAdminPeople() : refreshHospitalPeople();
        }).catch(function (error) {
            setText('#qn-invite-form-message', error.message);
        }).finally(function () {
            restoreButton();
        });
    }

    function allowedInviteRoles() {
        var role = state.me ? state.me.qualinav_role : '';
        return inviteRolesByRole[role] || [];
    }

    function isGlobalAdmin() {
        return state.me && (state.me.qualinav_role === 'qualinav_super_admin' || state.me.qualinav_role === 'qualinav_admin');
    }

    function getUrlOrganizationId() {
        var params = new URLSearchParams(window.location.search);
        return params.get('organization_id');
    }

    function getUrlOnboardingSection() {
        var params = new URLSearchParams(window.location.search);
        var raw = params.get('setup_section') || params.get('onboarding_section') || '';
        var section = String(raw).toLowerCase().replace(/[^a-z0-9_ -]/g, '').replace(/[-\s]+/g, '_');
        var aliases = {
            day_0_setup: 'hospital_director_info',
            hospital_setup: 'hospital_director_info',
            hospital_director: 'hospital_director_info',
            hospital_director_info: 'hospital_director_info',
            accreditation: 'accreditation_survey_readiness',
            survey: 'accreditation_survey_readiness',
            survey_readiness: 'accreditation_survey_readiness',
            accreditation_survey_readiness: 'accreditation_survey_readiness',
            services: 'services_clinical_model',
            clinical_model: 'services_clinical_model',
            services_clinical_model: 'services_clinical_model',
            committees: 'committees_reporting',
            reporting_setup: 'committees_reporting',
            committees_reporting: 'committees_reporting',
            plans: 'plans_policies_monitoring',
            policies: 'plans_policies_monitoring',
            monitoring_setup: 'plans_policies_monitoring',
            plans_policies_monitoring: 'plans_policies_monitoring',
            measures: 'measures_qi_projects',
            qi_projects: 'measures_qi_projects',
            measures_qi_projects: 'measures_qi_projects',
            goals: 'goals_learning_contacts',
            learning: 'goals_learning_contacts',
            contacts: 'goals_learning_contacts',
            goals_learning_contacts: 'goals_learning_contacts',
            regulatory: 'regulatory_tools_preferences',
            preferences: 'regulatory_tools_preferences',
            regulatory_tools_preferences: 'regulatory_tools_preferences'
        };
        return aliases[section] || '';
    }

    function getUrlOnboardingQuestion() {
        var params = new URLSearchParams(window.location.search);
        var raw = params.get('setup_question') || params.get('onboarding_question') || '';
        return String(raw).toLowerCase().replace(/[^a-z0-9_ -]/g, '').replace(/[-\s]+/g, '_');
    }

    function updateUserRole(userId, role, context, field) {
        setControlLoading(field, true);
        return api('/' + context + '/users/' + userId + '/role', {method: 'PUT', body: {qualinav_role: role}}).then(function () {
            showToast('User role updated.', 'success');
            return context === 'admin' ? refreshAdminPeople() : refreshHospitalPeople();
        }).catch(function (error) {
            if (field) {
                field.value = field.getAttribute('data-original-value') || field.value;
            }
            showToast(error.message, 'warning');
        }).finally(function () {
            setControlLoading(field, false);
        });
    }

    function updateUserStatus(userId, status, context, field) {
        setControlLoading(field, true);
        return api('/' + context + '/users/' + userId + '/status', {method: 'PUT', body: {qualinav_status: status}}).then(function () {
            showToast('User status updated.', 'success');
            return context === 'admin' ? refreshAdminPeople() : refreshHospitalPeople();
        }).catch(function (error) {
            if (field) {
                field.value = field.getAttribute('data-original-value') || field.value;
            }
            showToast(error.message, 'warning');
        }).finally(function () {
            setControlLoading(field, false);
        });
    }

    function findConsoleUser(userId) {
        return (state.users || []).find(function (user) {
            return Number(user.ID) === Number(userId);
        }) || null;
    }

    function confirmUserStatusUpdate(userId, status, context, field) {
        if (['disabled', 'archived'].indexOf(status) === -1) {
            updateUserStatus(userId, status, context, field);
            return;
        }
        var user = findConsoleUser(userId);
        var userName = user ? text(user.display_name || user.user_email) : 'This user';
        var userEmail = user ? text(user.user_email) : '';
        var isArchive = status === 'archived';
        var originalValue = field && field.matches && field.matches('select') ? field.getAttribute('data-original-value') : '';
        if (originalValue !== null && originalValue !== '' && field && field.matches && field.matches('select')) {
            field.value = originalValue;
        }
        openDestructiveConfirmation({
            title: isArchive ? 'Remove user access?' : 'Disable user?',
            description: isArchive ?
                'This removes the user’s active access to the QualiNav workspace. Their historical activity and audit records will remain.' :
                'This temporarily prevents the user from accessing the QualiNav workspace. An authorized administrator can reactivate them later.',
            itemName: userName,
            note: userEmail && userEmail !== userName ? userEmail : '',
            confirmLabel: isArchive ? 'Remove access' : 'Disable user',
            onConfirm: function () {
                if (field && field.matches && field.matches('select')) {
                    field.value = status;
                }
                updateUserStatus(userId, status, context, field);
            }
        });
    }

    function findConsoleInvitation(id) {
        return (state.invitations || []).find(function (invitation) {
            return Number(invitation.id) === Number(id);
        }) || null;
    }

    function confirmInvitationRevocation(id, context, trigger) {
        var invitation = findConsoleInvitation(id);
        var email = invitation ? text(invitation.email) : 'This invitation';
        var name = invitation ? text(invitation.full_name) : '';
        openDestructiveConfirmation({
            title: 'Revoke invitation?',
            description: 'The invitation link will stop working. You can send a new invitation later if access is still needed.',
            itemName: email,
            note: name && name !== email ? name : '',
            confirmLabel: 'Revoke invitation',
            onConfirm: function () {
                resendOrRevokeInvite('revoke', id, context, trigger);
            }
        });
    }

    function resendOrRevokeInvite(action, id, context, trigger) {
        var restoreButton = setActionLoading(trigger, action === 'resend' ? 'Resending...' : 'Revoking...');
        return api('/' + context + '/invitations/' + id + '/' + action, {method: 'POST'}).then(function (invitation) {
            if (invitation && invitation.email_failed) {
                showToast(invitation.email_error || 'Invite was created, but email delivery failed. Configure SMTP/mail transport or resend after mail is fixed.', 'warning');
            } else {
                showToast(action === 'resend' ? 'Invitation resent.' : 'Invitation revoked.', 'success');
            }
            return context === 'admin' ? refreshAdminPeople() : refreshHospitalPeople();
        }).catch(function (error) {
            showToast(error.message, 'warning');
        }).finally(function () {
            restoreButton();
        });
    }

    function renderBrandPreview(brand, title) {
        var titleNode = document.getElementById('qn-brand-preview-title');
        var preview = document.getElementById('qn-brand-preview');
        var primary = brand && brand.primary_color ? brand.primary_color : '#003B5C';
        var secondary = brand && brand.secondary_color ? brand.secondary_color : '#007C89';
        var accent = brand && brand.accent_color ? brand.accent_color : '#14B8A6';
        var sidebar = brand && brand.sidebar_color ? brand.sidebar_color : '#072B49';
        var bg = brand && brand.background_color ? brand.background_color : '#F7FAFC';
        var card = brand && brand.card_color ? brand.card_color : '#FFFFFF';
        var textColor = brand && brand.text_color ? brand.text_color : '#102A43';
        var logo = brand && brand.logo_url ? brand.logo_url : '';
        if (titleNode) {
            titleNode.textContent = title || 'Brand Preview';
        }
        if (preview && brand) {
            preview.style.setProperty('--preview-primary', primary);
            preview.style.setProperty('--preview-secondary', secondary);
            preview.style.setProperty('--preview-accent', accent);
            preview.style.setProperty('--preview-sidebar', sidebar);
            preview.style.setProperty('--preview-bg', bg);
            preview.style.setProperty('--preview-card', card);
            preview.style.setProperty('--preview-text', textColor);
            preview.innerHTML = '<div class="qn-brand-preview-shell" style="background:' + escapeHtml(bg) + ';color:' + escapeHtml(textColor) + '">' +
                '<div class="qn-brand-preview-top" style="background:' + escapeHtml(sidebar) + '">' +
                '<div class="qn-brand-preview-logo">' + (logo ? '<img src="' + escapeHtml(logo) + '" alt="Logo preview">' : '<span>QualiNav</span>') + '</div>' +
                '<div class="qn-brand-preview-nav"><i></i><i></i><i></i></div></div>' +
                '<div class="qn-brand-preview-workspace">' +
                '<div class="qn-brand-preview-header"><div><small>Theme Preview</small><strong>Hospital Workspace</strong></div>' +
                '<span class="qn-brand-preview-badge" style="background:' + escapeHtml(accent) + ';color:' + escapeHtml(contrastText(accent)) + '">Active</span></div>' +
                '<div class="qn-brand-preview-grid">' +
                '<section style="background:' + escapeHtml(card) + ';color:' + escapeHtml(textColor) + '"><span class="dashicons dashicons-chart-bar" style="color:' + escapeHtml(secondary) + '"></span><h3>Quality Dashboard</h3><p>Preview cards, typography, active states, and healthcare SaaS workspace surfaces.</p><div class="qn-brand-preview-actions"><button type="button" style="background:' + escapeHtml(primary) + ';color:' + escapeHtml(contrastText(primary)) + '">Primary Action</button><button type="button" class="secondary" style="border-color:' + escapeHtml(secondary) + ';color:' + escapeHtml(secondary) + '">Secondary</button></div></section>' +
                '<aside style="background:' + escapeHtml(card) + ';color:' + escapeHtml(textColor) + '"><h3>Brand Tokens</h3><div class="qn-brand-swatches">' +
                brandSwatch('Primary', primary) + brandSwatch('Secondary', secondary) + brandSwatch('Accent', accent) + brandSwatch('Sidebar', sidebar) + brandSwatch('Background', bg) + brandSwatch('Card', card) +
                '</div></aside></div></div></div>';
        }
    }

    function brandSwatch(label, value) {
        return '<span><i style="background:' + escapeHtml(value) + '"></i><b>' + escapeHtml(label) + '</b><small>' + escapeHtml(value) + '</small></span>';
    }

    function contrastText(background) {
        var hex = String(background || '').replace('#', '');
        if (hex.length === 3) {
            hex = hex.split('').map(function (char) { return char + char; }).join('');
        }
        if (!/^[0-9a-f]{6}$/i.test(hex)) {
            return '#102A43';
        }
        var red = parseInt(hex.substr(0, 2), 16);
        var green = parseInt(hex.substr(2, 2), 16);
        var blue = parseInt(hex.substr(4, 2), 16);
        var brightness = ((red * 299) + (green * 587) + (blue * 114)) / 1000;
        return brightness > 150 ? '#102A43' : '#FFFFFF';
    }

    function bindEvents() {
        initializeSearchableSelects();
        var createButton = document.getElementById('qn-create-hospital-button');
        var hospitalForm = document.getElementById('qn-hospital-form');
        var systemForm = document.getElementById('qn-system-form');
        var systemHospitalsForm = document.getElementById('qn-system-hospitals-form');
        var inviteForm = document.getElementById('qn-invite-form');
        if (createButton) {
            createButton.addEventListener('click', function () { openHospitalModal(null); });
        }
        if (hospitalForm) {
            hospitalForm.addEventListener('submit', saveHospital);
        }
        if (systemForm) {
            systemForm.addEventListener('submit', saveSystem);
        }
        if (systemHospitalsForm) {
            systemHospitalsForm.addEventListener('submit', saveSystemHospitals);
        }
        var createSystem = document.getElementById('qn-create-system-button');
        if (createSystem) {
            createSystem.addEventListener('click', function () { openSystemModal(null); });
        }
        ['qn-system-modal-close', 'qn-system-cancel'].forEach(function (id) {
            var node = document.getElementById(id);
            if (node) {
                node.addEventListener('click', closeSystemModal);
            }
        });
        ['qn-system-hospitals-modal-close', 'qn-system-hospitals-cancel'].forEach(function (id) {
            var node = document.getElementById(id);
            if (node) {
                node.addEventListener('click', closeSystemHospitalsModal);
            }
        });
        if (inviteForm) {
            inviteForm.addEventListener('submit', saveInvite);
        }
        ['qn-hospital-modal-close', 'qn-hospital-cancel'].forEach(function (id) {
            var node = document.getElementById(id);
            if (node) {
                node.addEventListener('click', closeHospitalModal);
            }
        });
        document.querySelectorAll('[data-close-invite]').forEach(function (node) {
            node.addEventListener('click', closeInviteModal);
        });
        var onboardingGuideButton = document.getElementById('qn-onboarding-guide-button');
        if (onboardingGuideButton) {
            onboardingGuideButton.addEventListener('click', function () { openOnboardingGuide(false); });
        }
        var onboardingScoutContextButton = document.getElementById('qn-onboarding-scout-context-button');
        if (onboardingScoutContextButton) {
            onboardingScoutContextButton.addEventListener('click', openScoutContextModal);
        }
        document.querySelectorAll('[data-close-scout-context]').forEach(function (node) {
            node.addEventListener('click', closeScoutContextModal);
        });
        var onboardingWorkspaceGuideButton = document.getElementById('qn-onboarding-workspace-guide-button');
        if (onboardingWorkspaceGuideButton) {
            onboardingWorkspaceGuideButton.addEventListener('click', function () { openWorkspaceWelcome(false); });
        }
        var workspaceGuideButton = document.getElementById('qn-workspace-guide-button');
        if (workspaceGuideButton) {
            workspaceGuideButton.addEventListener('click', function () { openWorkspaceWelcome(false); });
        }
        var workspaceGuideSetupButton = document.getElementById('qn-workspace-guide-setup-button');
        if (workspaceGuideSetupButton) {
            workspaceGuideSetupButton.addEventListener('click', function () { activateSection('day-0-setup', true); });
        }
        var workspaceWelcomePrimary = document.getElementById('qn-workspace-welcome-primary');
        if (workspaceWelcomePrimary) {
            workspaceWelcomePrimary.addEventListener('click', goToHospitalSetupFromWelcome);
        }
        var workspaceWelcomeExplore = document.getElementById('qn-workspace-welcome-explore');
        if (workspaceWelcomeExplore) {
            workspaceWelcomeExplore.addEventListener('click', function () { closeWorkspaceWelcome(true); });
        }
        var workspaceWelcomePrint = document.getElementById('qn-workspace-welcome-print');
        if (workspaceWelcomePrint) {
            workspaceWelcomePrint.addEventListener('click', printQuestionsFromWelcome);
        }
        var workspaceWelcomeDismiss = document.getElementById('qn-workspace-welcome-dismiss-check');
        if (workspaceWelcomeDismiss) {
            workspaceWelcomeDismiss.addEventListener('change', function () {
                if (!workspaceWelcomeDismiss.checked) {
                    clearWorkspaceWelcomeDismissal();
                }
            });
        }
        document.querySelectorAll('[data-close-workspace-welcome]').forEach(function (node) {
            node.addEventListener('click', function () { closeWorkspaceWelcome(true); });
        });
        var onboardingGuideStart = document.getElementById('qn-onboarding-guide-start');
        if (onboardingGuideStart) {
            onboardingGuideStart.addEventListener('click', function () { closeOnboardingGuide(true); });
        }
        var onboardingGuidePrint = document.getElementById('qn-onboarding-guide-print');
        if (onboardingGuidePrint) {
            onboardingGuidePrint.addEventListener('click', function () {
                closeOnboardingGuide(true);
                openOnboardingQuestionList();
            });
        }
        document.querySelectorAll('[data-close-onboarding-guide]').forEach(function (node) {
            node.addEventListener('click', function () { closeOnboardingGuide(true); });
        });
        var questionListButton = document.getElementById('qn-onboarding-question-list-button');
        if (questionListButton) {
            questionListButton.addEventListener('click', openOnboardingQuestionList);
        }
        var printQuestionList = document.getElementById('qn-onboarding-print-question-list');
        if (printQuestionList) {
            printQuestionList.addEventListener('click', printOnboardingQuestionList);
        }
        document.querySelectorAll('[data-close-onboarding-question-list]').forEach(function (node) {
            node.addEventListener('click', closeOnboardingQuestionList);
        });
        var adminInvite = document.getElementById('qn-admin-invite-user-button');
        if (adminInvite) {
            adminInvite.addEventListener('click', function () { openInviteModal({context: 'admin'}); });
        }
        var hospitalInvite = document.getElementById('qn-hospital-invite-user-button');
        if (hospitalInvite) {
            hospitalInvite.addEventListener('click', function () { openInviteModal({context: 'hospital'}); });
        }
        var clearHospitalFilterButton = document.getElementById('qn-clear-hospital-filter');
        if (clearHospitalFilterButton) {
            clearHospitalFilterButton.addEventListener('click', clearHospitalFilter);
        }
        var returnHealthSystemsButton = document.getElementById('qn-return-health-systems');
        if (returnHealthSystemsButton) {
            returnHealthSystemsButton.addEventListener('click', returnToHealthSystems);
        }
        var assignSystemHospitalsButton = document.getElementById('qn-assign-system-hospitals');
        if (assignSystemHospitalsButton) {
            assignSystemHospitalsButton.addEventListener('click', function () {
                if (state.hospitalFilter && state.hospitalFilter.id) {
                    openSystemHospitalsModal(state.hospitalFilter.id);
                }
            });
        }
        var systemHospitalsSearch = document.getElementById('qn-system-hospitals-search');
        if (systemHospitalsSearch) {
            systemHospitalsSearch.addEventListener('input', function () {
                state.systemHospitalAssignmentSearch = systemHospitalsSearch.value || '';
                renderSystemHospitalsAssignmentList();
            });
        }
        var hospitalSearch = document.getElementById('qn-hospital-search');
        if (hospitalSearch) {
            hospitalSearch.addEventListener('input', function () {
                state.hospitalSearch = hospitalSearch.value || '';
                resetHospitalPagination();
                renderHospitals();
            });
        }
        var hospitalPrev = document.getElementById('qn-hospital-prev-page');
        if (hospitalPrev) {
            hospitalPrev.addEventListener('click', function () {
                if (state.hospitalPage > 1) {
                    state.hospitalPage--;
                    renderHospitals();
                }
            });
        }
        var hospitalNext = document.getElementById('qn-hospital-next-page');
        if (hospitalNext) {
            hospitalNext.addEventListener('click', function () {
                var totalRows = filteredHospitalsForDisplay().length;
                var totalPages = Math.max(1, Math.ceil(totalRows / state.hospitalPageSize));
                if (state.hospitalPage < totalPages) {
                    state.hospitalPage++;
                    renderHospitals();
                }
            });
        }
        bindInstantSearch('qn-system-search', function (value) {
            state.systemSearch = value;
            renderHealthSystems();
        });
        bindInstantSearch('qn-user-search', function (value) {
            state.userSearch = value;
            renderAdminUsers();
        });
        bindInstantSearch('qn-admin-invitation-search', function (value) {
            state.adminInvitationSearch = value;
            renderInvitations('admin');
        });
        bindInstantSearch('qn-hospital-user-search', function (value) {
            state.hospitalUserSearch = value;
            renderHospitalUsers();
        });
        bindInstantSearch('qn-hospital-invitation-search', function (value) {
            state.hospitalInvitationSearch = value;
            renderInvitations('hospital');
        });
        bindFilterChanges(['qn-system-filter-status'], renderHealthSystems);
        ['qn-user-filter-organization', 'qn-user-filter-role', 'qn-user-filter-status'].forEach(function (id) {
            var node = document.getElementById(id);
            if (node) {
                node.addEventListener('change', renderAdminUsers);
            }
        });
        bindFilterChanges(['qn-admin-invitation-filter-role', 'qn-admin-invitation-filter-status'], function () {
            renderInvitations('admin');
        });
        bindFilterChanges(['qn-hospital-user-filter-role', 'qn-hospital-user-filter-status'], renderHospitalUsers);
        bindFilterChanges(['qn-hospital-invitation-filter-role', 'qn-hospital-invitation-filter-status'], function () {
            renderInvitations('hospital');
        });
        document.addEventListener('click', function (event) {
            if (event.target.closest('.qn-plan-policy-row > summary input, .qn-plan-policy-row > summary select, .qn-plan-policy-row > summary button')) {
                event.stopPropagation();
            }
            if (!event.target.closest('.qn-searchable-select')) {
                closeSearchableSelects();
            }
            var privacyAck = event.target.closest('[data-document-privacy-ack]');
            if (privacyAck) {
                event.preventDefault();
                try {
                    if (window.localStorage) {
                        window.localStorage.setItem('qn_hospital_setup_document_privacy_ack', '1');
                    }
                } catch (error) {
                    // The notice can still be dismissed for the current render.
                }
                var privacyNotice = privacyAck.closest('[data-document-privacy-notice]');
                if (privacyNotice) {
                    privacyNotice.remove();
                }
                return;
            }
            var planPolicyLink = event.target.closest('[data-plan-policy-link]');
            if (planPolicyLink) {
                event.preventDefault();
                linkPlanPolicyDocument(planPolicyLink.getAttribute('data-plan-policy-link'));
                return;
            }
            var planPolicyCreateSource = event.target.closest('[data-plan-policy-create-source]');
            if (planPolicyCreateSource) {
                event.preventDefault();
                createAdditionalPlanAndUpload(planPolicyCreateSource.getAttribute('data-plan-policy-create-source'), planPolicyCreateSource);
                return;
            }
            var planPolicyView = event.target.closest('[data-plan-policy-view]');
            if (planPolicyView) {
                event.preventDefault();
                viewPlanPolicyDocument(planPolicyView.getAttribute('data-plan-policy-view'), planPolicyView);
                return;
            }
            var planPolicyUpload = event.target.closest('[data-plan-policy-upload]');
            if (planPolicyUpload) {
                event.preventDefault();
                var uploadControls = planPolicyUpload.closest('.qn-plan-policy-document-controls');
                var fileInput = uploadControls ? uploadControls.querySelector('[data-plan-policy-file]') : null;
                if (fileInput) {
                    fileInput.click();
                }
                return;
            }
            var planPolicyRemove = event.target.closest('[data-plan-policy-remove]');
            if (planPolicyRemove) {
                event.preventDefault();
                confirmPlanPolicyDocumentDeletion(planPolicyRemove.getAttribute('data-plan-policy-remove'), planPolicyRemove);
                return;
            }
            var reportingGenerate = event.target.closest('[data-reporting-generate-scout]');
            if (reportingGenerate) {
                event.preventDefault();
                generateScoutPreview(reportingGenerate);
                return;
            }
            var committeesGenerate = event.target.closest('[data-committees-generate-scout]');
            if (committeesGenerate) {
                event.preventDefault();
                generateScoutPreview(committeesGenerate);
                return;
            }
            var plansGenerate = event.target.closest('[data-plans-generate-scout]');
            if (plansGenerate) {
                event.preventDefault();
                generateScoutPreview(plansGenerate);
                return;
            }
            var clinicalGenerate = event.target.closest('[data-clinical-generate-scout]');
            if (clinicalGenerate) {
                event.preventDefault();
                generateScoutPreview(clinicalGenerate);
                return;
            }
            var scoutAttentionOpen = event.target.closest('[data-scout-attention-open]');
            if (scoutAttentionOpen) {
                event.preventDefault();
                openScoutAttentionTarget(scoutAttentionOpen.getAttribute('data-scout-attention-open'));
                return;
            }
            var scoutAttentionPreference = event.target.closest('[data-scout-attention-preference]');
            if (scoutAttentionPreference) {
                event.preventDefault();
                updateScoutAttentionPreference(
                    scoutAttentionPreference.getAttribute('data-scout-attention-key'),
                    scoutAttentionPreference.getAttribute('data-scout-attention-preference'),
                    scoutAttentionPreference
                );
                return;
            }
            var sectionTarget = event.target.closest('[data-section-target]');
            if (sectionTarget) {
                event.preventDefault();
                activateSection(sectionTarget.getAttribute('data-section-target'), true);
                switchOnboardingStepBySection(sectionTarget.getAttribute('data-onboarding-section'));
                return;
            }
            var menuToggle = event.target.closest('[data-action-menu-toggle]');
            if (menuToggle) {
                event.preventDefault();
                var menu = menuToggle.parentNode.querySelector('.qn-action-menu-list');
                var willOpen = menu ? menu.hidden : false;
                closeActionMenus(menu);
                if (menu) {
                    menu.classList.remove('qn-action-menu-list-left', 'qn-action-menu-list-up');
                    menu.hidden = !willOpen;
                    menuToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                    if (willOpen) {
                        window.requestAnimationFrame(function () {
                            var rect = menu.getBoundingClientRect();
                            if (rect.left < 8) {
                                menu.classList.add('qn-action-menu-list-left');
                            }
                            if (rect.bottom > window.innerHeight - 8) {
                                menu.classList.add('qn-action-menu-list-up');
                            }
                        });
                    }
                }
                return;
            }
            if (!event.target.closest('.qn-action-menu')) {
                closeActionMenus();
            }
            var editButton = event.target.closest('[data-edit-hospital]');
            var brandButton = event.target.closest('[data-brand-hospital]');
            var inviteQd = event.target.closest('[data-invite-qd]');
            var editSystem = event.target.closest('[data-edit-system]');
            var viewSystemHospitals = event.target.closest('[data-view-system-hospitals]');
            var assignSystemHospitals = event.target.closest('[data-assign-system-hospitals]');
            var deactivateSystem = event.target.closest('[data-deactivate-system]');
            var resend = event.target.closest('[data-resend-invite]');
            var revoke = event.target.closest('[data-revoke-invite]');
            var menuRole = event.target.closest('[data-update-user-role]');
            var menuStatus = event.target.closest('[data-update-user-status]');
            var scout = event.target.closest('[data-view-scout]');
            var openConsole = event.target.closest('[data-open-console]');
            if (openConsole) {
                openHospitalConsole(openConsole.getAttribute('data-open-console'));
                return;
            }
            if (editButton) {
                openHospitalModal(findHospital(editButton.getAttribute('data-edit-hospital')));
            }
            if (brandButton) {
                var id = brandButton.getAttribute('data-brand-hospital');
                var selected = findHospital(id);
                api('/admin/brand/' + id).then(function (brand) {
                    renderBrandPreview(brand, selected ? selected.name : 'Hospital Brand');
                });
            }
            if (inviteQd) {
                openInviteModal({context: 'admin', role: 'quality_director', organizationId: inviteQd.getAttribute('data-invite-qd')});
            }
            if (editSystem) {
                openSystemModal(findSystem(editSystem.getAttribute('data-edit-system')));
            }
            if (viewSystemHospitals) {
                showSystemHospitals(viewSystemHospitals.getAttribute('data-view-system-hospitals'));
                return;
            }
            if (assignSystemHospitals) {
                openSystemHospitalsModal(assignSystemHospitals.getAttribute('data-assign-system-hospitals'));
                return;
            }
            if (deactivateSystem) {
                deactivateHealthSystem(deactivateSystem.getAttribute('data-deactivate-system'), deactivateSystem);
                return;
            }
            var setup = event.target.closest('[data-view-setup]');
            if (setup) {
                window.location.href = (config.homeUrl || '/') + 'qualinav?organization_id=' + encodeURIComponent(setup.getAttribute('data-view-setup')) + '#day-0-setup';
            }
            if (scout) {
                window.location.href = (config.homeUrl || '/') + 'qualinav?organization_id=' + encodeURIComponent(scout.getAttribute('data-view-scout')) + '#scout-preview';
            }
            if (menuRole) {
                updateUserRole(menuRole.getAttribute('data-update-user-role'), menuRole.getAttribute('data-role'), menuRole.getAttribute('data-context'), menuRole);
                closeActionMenus();
                return;
            }
            if (menuStatus) {
                confirmUserStatusUpdate(menuStatus.getAttribute('data-update-user-status'), menuStatus.getAttribute('data-status'), menuStatus.getAttribute('data-context'), menuStatus);
                closeActionMenus();
                return;
            }
            if (resend) {
                resendOrRevokeInvite('resend', resend.getAttribute('data-resend-invite'), resend.getAttribute('data-context'), resend);
                return;
            }
            if (revoke) {
                confirmInvitationRevocation(revoke.getAttribute('data-revoke-invite'), revoke.getAttribute('data-context'), revoke);
                return;
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                var destructiveModal = document.getElementById('qn-destructive-confirmation-modal');
                if (destructiveModal && !destructiveModal.hidden) {
                    return;
                }
                closeActionMenus();
                closeSearchableSelects();
                closeUsDatePicker();
                document.querySelectorAll('.qn-modal:not([hidden])').forEach(function (modal) {
                    modal.hidden = true;
                });
            }
        });
        document.addEventListener('change', function (event) {
            if (event.target.matches('[data-qn-date-month]') && usDatePickerContext) {
                usDatePickerContext.viewMonth = Number(event.target.value);
                renderUsDatePicker();
                return;
            }
            if (event.target.matches('[data-qn-date-year]') && usDatePickerContext) {
                usDatePickerContext.viewYear = Number(event.target.value);
                renderUsDatePicker();
                return;
            }
            if (event.target.matches('[data-plan-policy-file]')) {
                uploadPlanPolicyDocument(event.target);
                return;
            }
            if (event.target.id === 'qn-hospital-switcher') {
                setControlLoading(event.target, true);
                setWorkspaceLoading(true, 'Switching hospital workspace...');
                api('/switch-organization', {method: 'POST', body: {organization_id: event.target.value}}).then(function () {
                    showToast('Hospital switched.', 'success');
                    return refreshHospitalWorkspace();
                }).catch(function (error) {
                    showToast(error.message, 'warning');
                }).finally(function () {
                    setControlLoading(event.target, false);
                    setWorkspaceLoading(false);
                });
                return;
            }
            if (event.target.matches('[data-cadence-report-toggle]')) {
                var cadenceRow = event.target.closest('.qn-reporting-calendar-row');
                var reportHidden = cadenceRow ? cadenceRow.querySelector('[data-column="is_reported"]') : null;
                if (cadenceRow) {
                    cadenceRow.classList.toggle('is-selected', event.target.checked);
                }
                if (reportHidden) {
                    reportHidden.value = event.target.checked ? '1' : '';
                    reportHidden.dispatchEvent(new Event('input', {bubbles: true}));
                }
                setOnboardingSaveStatus('unsaved', 'Unsaved changes');
                window.clearTimeout(state.autosaveTimer);
                state.autosaveTimer = window.setTimeout(autosaveOnboardingStep, 900);
                return;
            }
            var role = event.target.closest('[data-user-role]');
            var status = event.target.closest('[data-user-status]');
            var menuRole = event.target.closest('[data-update-user-role]');
            var menuStatus = event.target.closest('[data-update-user-status]');
            var hospitalAssignment = event.target.closest('[data-system-hospital-assignment]');
            if (hospitalAssignment) {
                state.systemHospitalAssignmentSelection[hospitalAssignment.getAttribute('data-system-hospital-assignment')] = hospitalAssignment.checked;
                renderSystemHospitalsAssignmentList();
                return;
            }
            if (role) {
                updateUserRole(role.getAttribute('data-user-role'), role.value, role.getAttribute('data-context'), role);
            }
            if (status) {
                confirmUserStatusUpdate(status.getAttribute('data-user-status'), status.value, status.getAttribute('data-context'), status);
            }
            if (menuRole) {
                updateUserRole(menuRole.getAttribute('data-update-user-role'), menuRole.getAttribute('data-role'), menuRole.getAttribute('data-context'), menuRole);
                closeActionMenus();
                return;
            }
            if (menuStatus) {
                confirmUserStatusUpdate(menuStatus.getAttribute('data-update-user-status'), menuStatus.getAttribute('data-status'), menuStatus.getAttribute('data-context'), menuStatus);
                closeActionMenus();
                return;
            }
            if (event.target.matches('#qn-invite-role')) {
                updateInviteRoleDescription();
            }
            if (event.target.matches('#qn-invite-state')) {
                renderInviteHospitalOptions('');
            }
            if (event.target.matches('[data-onboarding-field], [data-plan-field], [data-plan-policy-field], [data-repeater-row], [data-repeater-detail], [data-structured-field], [data-checklist-field]')) {
                if (event.target.matches('[data-single-select-field]') && event.target.checked) {
                    var singleSelectKey = event.target.getAttribute('data-single-select-field');
                    document.querySelectorAll('[data-single-select-field="' + singleSelectKey + '"]').forEach(function (field) {
                        var selected = field === event.target;
                        field.checked = selected;
                        field.setAttribute('aria-checked', selected ? 'true' : 'false');
                    });
                } else if (event.target.matches('[data-single-select-field]')) {
                    // Match radio behavior: clicking the selected option does not
                    // clear the group.
                    event.target.checked = true;
                    event.target.setAttribute('aria-checked', 'true');
                }
                if (event.target.type === 'number' && event.target.value !== '') {
                    event.target.value = String(Math.max(0, Math.floor(Number(event.target.value) || 0)));
                }
                if (event.target.getAttribute('data-date-format') === 'us') {
                    event.target.value = formatDateForDisplay(event.target.value);
                    if (isIncompleteUsDate(event.target.value)) {
                        event.target.setCustomValidity('Use mm/dd/yyyy.');
                        setOnboardingSaveStatus('unsaved', 'Unsaved changes');
                        return;
                    }
                    event.target.setCustomValidity('');
                }
                if (event.target.matches('[data-meeting-prep-custom-input]') && fieldValue(event.target.value).trim()) {
                    event.target.setCustomValidity('');
                }
                updateStepOneBedWarning();
                updateSwingBedConsistencyWarning();
                updateStepOneAffiliationUI();
                updateStepOneQualityLeaderTitleUI();
                if (event.target.matches('[data-onboarding-field="independent_or_system"]')) {
                    window.setTimeout(updateStepOneAffiliationUI, 0);
                }
                if (event.target.matches('[data-onboarding-field="quality_leader_title"]')) {
                    window.setTimeout(updateStepOneQualityLeaderTitleUI, 0);
                }
                updateStepTwoConditionalUI();
                updateStepThreeConditionalUI();
                updateStepFourConditionalUI();
                updateStepSevenConditionalUI();
                if (event.target.matches('[data-checklist-field]')) {
                    updateMultiselectUI(event.target.closest('.qn-multiselect'));
                    updateStepSevenGoalTiles(event.target.closest('.qn-step7-goal-tiles'));
                }
                if (event.target.matches('[data-repeater-row]')) {
                    refreshDueDatePanel(event.target);
                }
                if (event.target.matches('[data-plan-policy-key="status"], [data-plan-policy-link-source]')) {
                    updatePlanPolicyFoldedUI(event.target);
                }
                setOnboardingSaveStatus('unsaved', 'Unsaved changes');
                window.clearTimeout(state.autosaveTimer);
                state.autosaveTimer = window.setTimeout(autosaveOnboardingStep, 900);
            }
        });
        document.addEventListener('input', function (event) {
            if (event.target.matches('[data-onboarding-field], [data-plan-field], [data-repeater-row], [data-repeater-detail], [data-structured-field], [data-checklist-field]')) {
                if (event.target.getAttribute('data-date-format') === 'us' && isIncompleteUsDate(event.target.value)) {
                    event.target.setCustomValidity('Use mm/dd/yyyy.');
                    setOnboardingSaveStatus('unsaved', 'Unsaved changes');
                    window.clearTimeout(state.autosaveTimer);
                    return;
                }
                if (event.target.getAttribute('data-date-format') === 'us') {
                    event.target.setCustomValidity('');
                }
                updateStepOneBedWarning();
                updateSwingBedConsistencyWarning();
                updateStepOneAffiliationUI();
                updateStepOneQualityLeaderTitleUI();
                if (event.target.matches('[data-onboarding-field="independent_or_system"]')) {
                    window.setTimeout(updateStepOneAffiliationUI, 0);
                }
                if (event.target.matches('[data-onboarding-field="quality_leader_title"]')) {
                    window.setTimeout(updateStepOneQualityLeaderTitleUI, 0);
                }
                updateStepTwoConditionalUI();
                updateStepThreeConditionalUI();
                updateStepFourConditionalUI();
                updateStepSevenConditionalUI();
                setOnboardingSaveStatus('unsaved', 'Unsaved changes');
                window.clearTimeout(state.autosaveTimer);
                state.autosaveTimer = window.setTimeout(autosaveOnboardingStep, 900);
            }
        });
        document.addEventListener('click', function (event) {
            var swingBedAction = event.target.closest('[data-swing-bed-action]');
            var datePickerDay = event.target.closest('[data-qn-date]');
            var datePickerToday = event.target.closest('[data-qn-date-today]');
            var datePickerClear = event.target.closest('[data-qn-date-clear]');
            var dateControl = event.target.closest('[data-us-date-trigger], [data-date-format="us"]');
            var stepButton = event.target.closest('[data-onboarding-step]');
            var addRepeater = event.target.closest('[data-add-repeater]');
            var multiselectTrigger = event.target.closest('[data-multiselect-trigger]');
            var multiselectRemove = event.target.closest('[data-multiselect-remove]');
            var passwordToggle = event.target.closest('[data-toggle-password]');
            if (swingBedAction) {
                saveSwingBedResolution(swingBedAction.getAttribute('data-swing-bed-resolution'), swingBedAction);
                return;
            }
            if (datePickerDay) {
                selectUsDate(datePickerDay.getAttribute('data-qn-date'));
                return;
            }
            if (datePickerToday) {
                selectUsDate(todayIsoDate());
                return;
            }
            if (datePickerClear) {
                selectUsDate('');
                return;
            }
            if (dateControl) {
                openUsDatePicker(dateControl);
                return;
            }
            if (usDatePickerPopover && !usDatePickerPopover.hidden && !usDatePickerPopover.contains(event.target)) {
                closeUsDatePicker();
            }
            if (!event.target.closest('.qn-multiselect')) {
                closeMultiselects();
            }
            if (multiselectTrigger) {
                var multiselect = multiselectTrigger.closest('.qn-multiselect');
                var willOpen = !multiselect.classList.contains('qn-multiselect-open');
                closeMultiselects(multiselect);
                multiselect.classList.toggle('qn-multiselect-open', willOpen);
                multiselectTrigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                return;
            }
            if (multiselectRemove) {
                var owner = multiselectRemove.closest('.qn-multiselect');
                var value = multiselectRemove.getAttribute('data-multiselect-remove');
                if (owner) {
                    Array.prototype.slice.call(owner.querySelectorAll('[data-checklist-field]')).forEach(function (field) {
                        if (field.value === value) {
                            field.checked = false;
                        }
                    });
                }
                updateMultiselectUI(owner);
                setOnboardingSaveStatus('unsaved', 'Unsaved changes');
                window.clearTimeout(state.autosaveTimer);
                state.autosaveTimer = window.setTimeout(autosaveOnboardingStep, 900);
                return;
            }
            if (passwordToggle) {
                togglePasswordVisibility(passwordToggle);
                return;
            }
            if (stepButton) {
                switchOnboardingStepInstant(stepButton.getAttribute('data-onboarding-step'));
                return;
            }
            if (addRepeater) {
                var repeater = document.querySelector('[data-repeater="' + addRepeater.getAttribute('data-add-repeater') + '"]');
                if (repeater) {
                    var index = repeater.querySelectorAll('.qn-repeater-row').length;
                    var columns = JSON.parse(repeater.getAttribute('data-columns') || '["note"]');
                    var style = repeater.getAttribute('data-repeater-style');
                    var rowHtml = renderRepeaterRow(addRepeater.getAttribute('data-add-repeater'), columns, {}, index);
                    if (style === 'survey-history') {
                        rowHtml = renderSurveyHistoryRow(addRepeater.getAttribute('data-add-repeater'), columns, {}, index);
                    }
                    if (style === 'committee-card') {
                        rowHtml = renderCommitteeRow(addRepeater.getAttribute('data-add-repeater'), columns, {}, index);
                    }
                    if (style === 'report-card') {
                        rowHtml = renderReportingRow(addRepeater.getAttribute('data-add-repeater'), columns, {}, index);
                    }
                    if (style === 'qi-project-card') {
                        rowHtml = renderQiProjectRow(addRepeater.getAttribute('data-add-repeater'), columns, {}, index);
                    }
                    if (style === 'backup-user-card') {
                        rowHtml = renderBackupUserRow(addRepeater.getAttribute('data-add-repeater'), columns, {}, index);
                    }
                    var empty = repeater.querySelector('.qn-survey-empty');
                    if (empty) {
                        empty.remove();
                    }
                    addRepeater.insertAdjacentHTML('beforebegin', rowHtml);
                    refreshRepeaterCardLabels(repeater);
                }
            }
            var deleteRepeaterRow = event.target.closest('[data-delete-repeater-row]');
            if (deleteRepeaterRow) {
                var row = deleteRepeaterRow.closest('.qn-repeater-row');
                confirmOnboardingRepeaterDeletion(row);
                return;
            }
            var scoutDetails = event.target.closest('[data-scout-details]');
            if (scoutDetails) {
                openScoutDetails(scoutDetails.getAttribute('data-scout-details'));
            }
            if (event.target.closest('[data-close-scout-detail]')) {
                closeScoutDetails();
                return;
            }
            if (event.target.id === 'qn-scout-empty-generate' || event.target.id === 'qn-scout-hero-generate') {
                generateScoutPreview();
                return;
            }
            if (event.target.closest('[data-scout-open-latest]')) {
                var workflow = document.querySelector('.qn-scout-workflow-section');
                if (workflow) {
                    workflow.scrollIntoView({behavior: 'smooth', block: 'start'});
                }
                return;
            }
            var scoutRetry = event.target.closest('[data-retry-scout]');
            if (scoutRetry) {
                retryScoutRun(scoutRetry.getAttribute('data-retry-scout'), scoutRetry);
            }
        });
        window.addEventListener('resize', positionUsDatePicker);
        window.addEventListener('scroll', positionUsDatePicker, true);
        var scoutGenerate = document.getElementById('qn-scout-generate-button');
        if (scoutGenerate) {
            scoutGenerate.addEventListener('click', generateScoutPreview);
        }
        var prev = document.getElementById('qn-onboarding-prev');
        var save = document.getElementById('qn-onboarding-save');
        var next = document.getElementById('qn-onboarding-next');
        var submit = document.getElementById('qn-onboarding-submit');
        if (prev) {
            prev.addEventListener('click', function () {
                saveOnboardingStep(false, prev).then(function () {
                    state.onboardingIndex = Math.max(0, state.onboardingIndex - 1);
                    renderOnboarding();
                });
            });
        }
        if (save) {
            save.addEventListener('click', function () { saveOnboardingStep(false, save); });
        }
        if (next) {
            next.addEventListener('click', function () { saveOnboardingStep(true, next); });
        }
        if (submit) {
            submit.addEventListener('click', function () { submitOnboarding(submit); });
        }
    }

    function findHospital(id) {
        return (state.allHospitals || state.hospitals).find(function (hospital) {
            return Number(hospital.id) === Number(id);
        });
    }

    function findSystem(id) {
        return state.healthSystems.find(function (system) {
            return Number(system.id) === Number(id);
        });
    }

    function deactivateHealthSystem(id, trigger) {
        var system = findSystem(id);
        var name = system ? system.name : 'this health system';
        openDestructiveConfirmation({
            title: 'Deactivate health system?',
            description: 'The health system will become inactive. Its hospitals and their information will remain available and will not be deleted.',
            itemName: name,
            confirmLabel: 'Deactivate system',
            onConfirm: function () {
                performHealthSystemDeactivation(id, trigger);
            }
        });
    }

    function performHealthSystemDeactivation(id, trigger) {
        var restoreButton = setActionLoading(trigger, 'Deactivating...');
        api('/admin/health-systems/' + id, {method: 'DELETE'}).then(function () {
            showToast('Health system deactivated.', 'success');
            return api('/admin/health-systems');
        }).then(function (systems) {
            state.healthSystems = systems || [];
            renderHealthSystems();
            updateEnterpriseMetrics();
        }).catch(function (error) {
            showToast(error.message, 'warning');
        }).finally(function () {
            restoreButton();
        });
    }

    function getValue(id) {
        var node = document.getElementById(id);
        return node ? node.value : '';
    }

    function setField(id, value) {
        var field = document.getElementById(id);
        if (field) {
            field.value = value || '';
            syncSearchableSelect(field);
        }
    }

    function initializeSearchableSelects(root) {
        (root || document).querySelectorAll('select.qn-searchable-select-source').forEach(function (select) {
            enhanceSearchableSelect(select);
        });
    }

    function selectedOptionText(select) {
        if (!select || !select.options || select.selectedIndex < 0) {
            return 'Select';
        }
        return select.options[select.selectedIndex].textContent || 'Select';
    }

    function searchableSelectOptions(select, query) {
        var normalized = String(query || '').trim().toLowerCase();
        return Array.prototype.slice.call(select.options || []).filter(function (option) {
            return !normalized || option.textContent.toLowerCase().indexOf(normalized) !== -1;
        });
    }

    function renderSearchableSelectOptions(select, query) {
        var shell = select.nextElementSibling;
        if (!shell || !shell.classList.contains('qn-searchable-select')) {
            return;
        }
        var list = shell.querySelector('[data-searchable-options]');
        if (!list) {
            return;
        }
        var normalized = String(query || '').trim();
        var options = searchableSelectOptions(select, query);
        if (!options.length) {
            list.innerHTML = '<div class="qn-searchable-empty">' + (normalized ? 'No matches found.' : 'No options available.') + '</div>';
            return;
        }
        list.innerHTML = options.map(function (option) {
            var selected = option.value === select.value;
            return '<button type="button" class="qn-searchable-option' + (selected ? ' qn-searchable-option-selected' : '') + '" data-searchable-value="' + escapeHtml(option.value) + '">' +
                '<span>' + escapeHtml(option.textContent) + '</span>' +
                (selected ? '<span class="dashicons dashicons-yes" aria-hidden="true"></span>' : '') +
                '</button>';
        }).join('');
    }

    function closeSearchableSelects(except) {
        document.querySelectorAll('.qn-searchable-select.qn-searchable-open').forEach(function (shell) {
            if (except && shell === except) {
                return;
            }
            shell.classList.remove('qn-searchable-open');
            var trigger = shell.querySelector('[data-searchable-trigger]');
            var popover = shell.querySelector('[data-searchable-popover]');
            if (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
            }
            if (popover) {
                popover.hidden = true;
            }
        });
    }

    function syncSearchableSelect(select) {
        if (!select || !select.classList || !select.classList.contains('qn-searchable-select-source')) {
            return;
        }
        var shell = select.nextElementSibling;
        if (!shell || !shell.classList.contains('qn-searchable-select')) {
            enhanceSearchableSelect(select);
            shell = select.nextElementSibling;
        }
        if (!shell || !shell.classList.contains('qn-searchable-select')) {
            return;
        }
        shell.hidden = !!select.hidden;
        shell.classList.toggle('qn-searchable-disabled', !!select.disabled);
        var trigger = shell.querySelector('[data-searchable-trigger]');
        var search = shell.querySelector('[data-searchable-input]');
        if (trigger) {
            trigger.disabled = !!select.disabled;
            trigger.querySelector('span').textContent = selectedOptionText(select);
        }
        if (search) {
            search.disabled = !!select.disabled;
            search.value = '';
        }
        renderSearchableSelectOptions(select, '');
    }

    function enhanceSearchableSelect(select) {
        if (!select || !select.classList || !select.classList.contains('qn-searchable-select-source')) {
            return;
        }
        var shell = select.nextElementSibling;
        if (!shell || !shell.classList.contains('qn-searchable-select')) {
            shell = document.createElement('div');
            shell.className = 'qn-searchable-select';
            shell.innerHTML = '<button type="button" class="qn-searchable-trigger" data-searchable-trigger aria-expanded="false"><span></span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>' +
                '<div class="qn-searchable-popover" data-searchable-popover hidden><input type="search" data-searchable-input placeholder="Search..." autocomplete="off"><div class="qn-searchable-options" data-searchable-options></div></div>';
            select.parentNode.insertBefore(shell, select.nextSibling);
            select.classList.add('qn-searchable-enhanced');
            shell.querySelector('[data-searchable-trigger]').addEventListener('click', function () {
                if (select.disabled) {
                    return;
                }
                var isOpen = shell.classList.contains('qn-searchable-open');
                closeSearchableSelects(shell);
                shell.classList.toggle('qn-searchable-open', !isOpen);
                shell.querySelector('[data-searchable-popover]').hidden = isOpen;
                shell.querySelector('[data-searchable-trigger]').setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                renderSearchableSelectOptions(select, '');
                if (!isOpen) {
                    var input = shell.querySelector('[data-searchable-input]');
                    input.value = '';
                    input.focus();
                }
            });
            shell.querySelector('[data-searchable-input]').addEventListener('input', function (event) {
                renderSearchableSelectOptions(select, event.target.value);
            });
            shell.querySelector('[data-searchable-options]').addEventListener('click', function (event) {
                var option = event.target.closest('[data-searchable-value]');
                if (!option) {
                    return;
                }
                select.value = option.getAttribute('data-searchable-value');
                select.dispatchEvent(new Event('change', {bubbles: true}));
                syncSearchableSelect(select);
                closeSearchableSelects();
                var popover = shell.querySelector('[data-searchable-popover]');
                if (popover) {
                    popover.hidden = true;
                }
            });
        }
        var popover = shell.querySelector('[data-searchable-popover]');
        if (popover) {
            popover.hidden = !shell.classList.contains('qn-searchable-open');
        }
        syncSearchableSelect(select);
    }

    function showToast(message, type) {
        var host = document.getElementById('qn-toast-region');
        if (!host) {
            host = document.createElement('div');
            host.id = 'qn-toast-region';
            host.className = 'qn-toast-region';
            host.setAttribute('aria-live', 'polite');
            document.body.appendChild(host);
        }
        var toast = document.createElement('div');
        toast.className = 'qn-toast qn-toast-' + (type || 'info');
        toast.innerHTML = '<span class="dashicons ' + (type === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning') + '"></span><span>' + escapeHtml(message) + '</span>';
        host.appendChild(toast);
        window.setTimeout(function () {
            toast.classList.add('qn-toast-hide');
            window.setTimeout(function () { toast.remove(); }, 250);
        }, 3200);
    }

    function initConsole() {
        if (!document.body) {
            return;
        }
        try {
            initSidebar();
            bindEvents();
            initSections();
        } catch (error) {
            setTableMessage('qn-hospitals-table-body', 6, error.message || 'Unable to initialize QualiNav controls.');
            setTableMessage('qn-hospital-users-table-body', 5, error.message || 'Unable to initialize QualiNav controls.');
            return;
        }
        try {
            if (config.isHomeWelcomePage) {
                loadHomeWelcome();
                return;
            }
            if (document.body.classList.contains('qn-admin-console-page')) {
                loadAdminConsole();
            }
            if (document.body.classList.contains('qn-hospital-console-page')) {
                loadHospitalConsole();
            }
        } catch (error) {
            setTableMessage('qn-hospitals-table-body', 6, error.message || 'Unable to load QualiNav data.');
            setTableMessage('qn-hospital-users-table-body', 5, error.message || 'Unable to load QualiNav data.');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initConsole, {once: true});
    } else {
        initConsole();
    }
}());
