(function () {
    document.documentElement.classList.add('qualinav-console-ready');

    var config = window.QualiNavConsole || (typeof QualiNavConsole !== 'undefined' ? QualiNavConsole : {});
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
        onboardingSwitchTimer: null,
        onboardingGuideAutoShown: false,
        workspaceWelcomeAutoShown: false,
        scoutRuns: [],
        latestScoutRun: null,
        scoutBridgeAvailable: false,
        scoutCanGenerate: false,
        scoutOnboardingSubmitted: false,
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

    var roleLabels = {
        qualinav_super_admin: 'QualiNav Super Admin',
        qualinav_admin: 'QualiNav Admin',
        quality_director: 'Quality Director',
        hospital_admin: 'Hospital Admin',
        backup_quality_user: 'Backup Quality User',
        reporting_user: 'Reporting User',
        policy_owner: 'Policy Owner',
        committee_user: 'Committee User',
        viewer: 'Viewer'
    };
    var statuses = ['invited', 'active', 'disabled', 'archived'];
    var inviteRolesByRole = {
        qualinav_super_admin: ['qualinav_admin', 'quality_director', 'hospital_admin', 'backup_quality_user', 'reporting_user', 'policy_owner', 'committee_user', 'viewer'],
        qualinav_admin: ['quality_director', 'hospital_admin', 'backup_quality_user', 'reporting_user', 'policy_owner', 'committee_user', 'viewer'],
        quality_director: ['hospital_admin', 'backup_quality_user', 'reporting_user', 'policy_owner', 'committee_user', 'viewer'],
        hospital_admin: ['reporting_user', 'policy_owner', 'committee_user', 'viewer']
    };
    var roleDescriptions = {
        quality_director: 'Leads the hospital quality workspace, setup, users, and Scout workflows.',
        hospital_admin: 'Can help manage hospital users and workspace administration.',
        backup_quality_user: 'Can support quality work and see backup coverage context.',
        reporting_user: 'Can support reporting workflows and related setup information.',
        policy_owner: 'Can support plan and policy ownership workflows.',
        committee_user: 'Can support committee and meeting workflow information.',
        viewer: 'Read-only access to the hospital workspace.'
    };
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
            'Choose clinical monitoring areas',
            'Identify contracted service touchpoints',
            'Tailor hospital-type prompts'
        ],
        committees_reporting: [
            'Build the meeting and report flow map',
            'Sequence committee work before board meetings',
            'Create report preparation reminders',
            'Identify approval requirements',
            'Build the master reporting schedule'
        ],
        plans_policies_monitoring: [
            'Build the required plan review queue',
            'Identify missing or overdue policies',
            'Schedule clinical monitoring tasks',
            'Flag survey-risk gaps',
            'Prepare templates and priority items'
        ],
        measures_qi_projects: [
            'Build measure upload reminders',
            'Power dashboard trend views',
            'Link QI projects to measures',
            'Create milestone check-ins',
            'Flag projects missing charters or baselines'
        ],
        goals_learning_contacts: [
            'Set your guidance level',
            'Activate the First 30 Days track if needed',
            'Build your learning journey',
            'Protect time for strategic goals',
            'Create your external contact directory'
        ],
        regulatory_tools_preferences: [
            'Monitor the right regulatory sources',
            'Set digest and alert preferences',
            'Configure reminder timing',
            'Identify backup visibility needs',
            'Generate your Scout setup preview after submission'
        ]
    };
    var onboardingMaterialsChecklist = [
        'Hospital profile details',
        'Survey/accreditation history',
        'Reporting obligations',
        'Committee schedule',
        'Required plans and last approval dates',
        'Policy review cycle',
        'Clinical monitoring processes',
        'Aggregate measure sources',
        'Active QI projects',
        'External contacts',
        'Regulatory monitoring preferences',
        'Tools/reminder preferences'
    ];

    function roleLabel(role) {
        if (!role) {
            return '-';
        }
        if (roleLabels[role]) {
            return roleLabels[role];
        }
        return String(role).replace(/_/g, ' ').replace(/\b\w/g, function (letter) {
            return letter.toUpperCase();
        });
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
            var request = new XMLHttpRequest();
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
                    reject(new Error(json.message || 'QualiNav request failed.'));
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

    function normalizePublicSetupCopy(value) {
        return text(value).replace(/\bday 0\b/gi, function (match) {
            return match === match.toUpperCase() ? 'HOSPITAL SETUP' : 'Hospital Setup';
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
        Promise.all([api('/me'), api('/brand'), api('/my-organizations')]).then(function (results) {
            state.me = results[0];
            applyBrand(results[1]);
            state.myOrganizations = results[2] || [];
            renderHospitalSwitcher();

            var previewOrganizationId = getUrlOrganizationId();
            if (previewOrganizationId && isGlobalAdmin()) {
                state.hospitalPeopleLoaded = true;
                state.hospitalPeoplePreviewMode = true;
                setTableMessage('qn-hospital-users-table-body', 5, 'Super Admin setup preview mode.');
                setTableMessage('qn-hospital-invitations-table-body', 6, 'Super Admin setup preview mode.');
                renderHospitalUsersOverview();
                renderHospitalDashboard();
                return loadOnboarding(previewOrganizationId, {showLoading: false}).then(function () {
                    return loadScoutRuns(previewOrganizationId);
                });
            }

            renderHospitalDashboard();
            loadHospitalPeopleData();
            return loadOnboarding(null, {showLoading: false}).then(function () {
                return loadScoutRuns();
            });
        }).catch(function (error) {
            setTableMessage('qn-hospital-users-table-body', 5, error.message);
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
            name.textContent = 'Hospital workspace';
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
                dashboardModule('shield', 'System Health', 'Healthy', 'Workspace services are reachable for this console session.', isGlobalAdmin() ? 'View Settings' : '', isGlobalAdmin() ? 'settings' : '', 'success')
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
            'Preview the Quality Director welcome experience for this hospital workspace without triggering it automatically.' : (canEdit ?
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
        page.classList.add('qn-clinical-page');
        var hospital = dashboardHospital() || currentHospitalContext() || {};
        var setup = dashboardSetupStatus(hospital);
        var scout = dashboardScoutStatus();
        var reporting = reportingPreviewData();
        var action = reportingPageAction(setup, scout);
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
            renderReportingAdminBanner(hospitalName) +
            '<div class="qn-reporting-header">' +
            '<div><p class="qn-eyebrow">Reporting</p><h2>Reporting Schedule</h2><p>Track recurring reports, due dates, owners, approvals, and preparation lead time.</p><div class="qn-reporting-context">' + chips + '</div></div>' +
            '<span class="qn-status-pill qn-status-' + escapeHtml(reportingStatusTone(setup, scout, reporting)) + '">' + escapeHtml(reportingStatusLabel(setup, scout, reporting)) + '</span>' +
            '</div>' +
            renderReportingSummary(setup, scout, reporting) +
            renderReportingPrimaryState(setup, scout, reporting, action) +
            renderReportingCapabilityCards(setup, scout, reporting) +
            renderReportingCtaPanel(action);
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
            ['chart-bar', 'Reporting status', reportingStatusLabel(setup, scout, reporting), reporting.items.length ? 'Scout returned reporting detail.' : 'Pending Scout', reportingStatusTone(setup, scout, reporting)],
            ['calendar-alt', 'Upcoming reports', reporting.items.length ? String(reporting.items.length) : '-', reporting.items.length ? 'Draft schedule items' : 'Pending Scout', reporting.items.length ? 'success' : 'neutral'],
            ['yes-alt', 'Pending approvals', reporting.items.length ? String(pendingApprovals) : '-', 'Approval requirements identified', pendingApprovals ? 'warning' : 'neutral'],
            ['editor-help', 'Missing due dates', reporting.items.length ? String(missingDueDates) : '-', 'Rows needing due date detail', missingDueDates ? 'warning' : 'neutral']
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
        return '<section class="qn-reporting-empty">' +
            '<span class="dashicons dashicons-' + escapeHtml(icon) + '"></span>' +
            '<div><h3>' + escapeHtml(title) + '</h3><p>' + escapeHtml(message) + '</p>' +
            '<button class="qn-button qn-button-primary" type="button" ' + (action.generate ? 'data-reporting-generate-scout' : 'data-section-target="' + escapeHtml(action.target) + '"') + '>' + escapeHtml(action.label) + '</button></div>' +
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
        var ready = scout.ready && reporting.items.length;
        var cards = [
            ['media-spreadsheet', 'Master Reporting Schedule', 'Recurring federal, state, accreditation, payer, and internal reports.'],
            ['calendar-alt', 'Due Date Reminders', 'Lead times, buffers, known dates, and event-triggered deadlines.'],
            ['admin-users', 'Owner & Backup Tracking', 'Primary preparers, backup coverage, and visibility needs.'],
            ['groups', 'Board / Committee Prep', 'Preparation timing before committee and board meetings.']
        ];
        return '<section class="qn-reporting-capabilities"><div class="qn-section-toolbar"><div><p class="qn-eyebrow">Capabilities</p><h3>What this module will support</h3></div></div><div class="qn-future-capability-grid">' + cards.map(function (card) {
            var status = ready ? ['Ready', 'success'] : [scout.ready ? 'Pending reporting detail' : 'Pending Scout', scout.ready ? 'neutral' : 'warning'];
            return '<article class="qn-future-capability-card qn-reporting-capability-card"><span class="dashicons dashicons-' + escapeHtml(card[0]) + '"></span><h3>' + escapeHtml(card[1]) + '</h3><p>' + escapeHtml(card[2]) + '</p><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(status[1]) + '">' + escapeHtml(status[0]) + '</span></article>';
        }).join('') + '</div></section>';
    }

    function renderReportingCtaPanel(action) {
        return '<section class="qn-reporting-cta-panel">' +
            '<div><p class="qn-eyebrow">Next step</p><h3>' + escapeHtml(action.helper) + '</h3><p>QualiNav will keep this page focused on reporting workflow setup, schedule readiness, and review actions for Quality Directors.</p></div>' +
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
            approval: firstReportingValue(item, ['approval', 'approval_required', 'approval_requirements', 'approver']) || '',
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
            '<div><p class="qn-eyebrow">Committees</p><h2>Committees</h2><p>Manage meeting cadence, report flow, committee relationships, and board reporting.</p><div class="qn-reporting-context qn-committees-context">' + chips + '</div></div>' +
            '<span class="qn-status-pill qn-status-' + escapeHtml(committeesStatusTone(setup, scout, committees)) + '">' + escapeHtml(committeesStatusLabel(setup, scout, committees)) + '</span>' +
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
            '<div class="qn-reporting-table-wrap"><table class="qn-reporting-table qn-committees-table"><thead><tr><th>Committee</th><th>Frequency / timing</th><th>Your role</th><th>Reports to</th><th>Prep lead time</th><th>Status</th></tr></thead><tbody>' +
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
            '<button class="qn-button qn-button-primary" type="button" ' + (action.generate ? 'data-committees-generate-scout' : 'data-section-target="' + escapeHtml(action.target) + '"') + '>' + escapeHtml(action.label) + '</button></div>' +
            '</section>';
    }

    function renderCommitteePreviewRow(item) {
        return '<tr>' +
            '<td><strong>' + escapeHtml(item.name || 'Committee') + '</strong></td>' +
            '<td>' + escapeHtml(item.frequency || 'Not set') + '</td>' +
            '<td>' + escapeHtml(item.role || 'Not set') + '</td>' +
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
            reportingDatum('Your role', item.role || 'Not set') +
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
        return '<section class="qn-reporting-cta-panel qn-committees-cta-panel">' +
            '<div><p class="qn-eyebrow">Next step</p><h3>' + escapeHtml(action.helper) + '</h3><p>Scout will use committee cadence and report relationships to help Quality Directors prepare work before each meeting.</p></div>' +
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
            return item.name || item.frequency || item.role || item.reportsTo || item.flow || item.sequence;
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
            return {name: describeScoutItem(item), frequency: '', role: '', reportsTo: '', leadTime: '', flow: '', sequence: '', status: 'Draft', tone: 'neutral'};
        }
        var status = firstCommitteeValue(item, ['status', 'readiness_status', 'workflow_status']) || 'Draft';
        return {
            name: firstCommitteeValue(item, ['committee_name', 'meeting_name', 'name', 'title', 'committee', 'meeting']) || 'Committee',
            frequency: firstCommitteeValue(item, ['frequency_timing', 'frequency', 'cadence', 'timing', 'meeting_cadence']) || '',
            role: firstCommitteeValue(item, ['user_role', 'your_role', 'role', 'quality_director_role']) || '',
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
            '<div><p class="qn-eyebrow">Plans & Policies</p><h2>Plans & Policies</h2><p>Track required plans, policy review cycles, templates, owners, and approval status.</p><div class="qn-reporting-context qn-plans-context">' + chips + '</div></div>' +
            '<span class="qn-status-pill qn-status-' + escapeHtml(plansPoliciesStatusTone(setup, scout, plans)) + '">' + escapeHtml(plansPoliciesStatusLabel(setup, scout, plans)) + '</span>' +
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
        return '<section class="qn-reporting-preview qn-plans-preview">' +
            '<div class="qn-panel-header"><div><p class="qn-eyebrow">Plan and policy preview</p><h3>Draft review queue</h3><p>' + escapeHtml(plans.summary || 'Scout returned plan and policy workflow details for review.') + '</p></div><span class="qn-status-pill qn-status-success">' + plans.items.length + ' items</span></div>' +
            (plans.items.length ? '<div class="qn-reporting-table-wrap"><table class="qn-reporting-table qn-plans-table"><thead><tr><th>Plan / policy</th><th>Current status</th><th>Owner</th><th>Last approved</th><th>Board approval</th><th>Action needed</th><th>Priority</th></tr></thead><tbody>' + plans.items.map(renderPlansPoliciesRow).join('') + '</tbody></table></div><div class="qn-reporting-card-list qn-plans-card-list">' + plans.items.map(renderPlansPoliciesCard).join('') + '</div>' : '') +
            renderPlansPolicyCyclePreview(plans) +
            renderPlansPriorityItems(plans.priorityItems) +
            '</section>';
    }

    function renderPlansPoliciesEmptyState(icon, title, message, action) {
        return '<section class="qn-reporting-empty qn-plans-empty">' +
            '<span class="dashicons dashicons-' + escapeHtml(icon) + '"></span>' +
            '<div><h3>' + escapeHtml(title) + '</h3><p>' + escapeHtml(message) + '</p>' +
            '<button class="qn-button qn-button-primary" type="button" ' + (action.generate ? 'data-plans-generate-scout' : 'data-section-target="' + escapeHtml(action.target) + '"') + '>' + escapeHtml(action.label) + '</button></div>' +
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
        return '<section class="qn-reporting-cta-panel qn-plans-cta-panel">' +
            '<div><p class="qn-eyebrow">Next step</p><h3>' + escapeHtml(action.helper) + '</h3><p>Scout will use setup answers to help Quality Directors prioritize required plans, policy reviews, templates, and approval routing.</p></div>' +
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
            '<div><p class="qn-eyebrow">Clinical Monitoring</p><h2>Clinical Monitoring</h2><p>Track required monitoring areas, review cadence, committee routing, aggregate uploads, and priority gaps.</p><div class="qn-reporting-context qn-clinical-context">' + chips + '</div></div>' +
            '<span class="qn-status-pill qn-status-' + escapeHtml(clinicalMonitoringStatusTone(setup, scout, clinical)) + '">' + escapeHtml(clinicalMonitoringStatusLabel(setup, scout, clinical)) + '</span>' +
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
            '<button class="qn-button qn-button-primary" type="button" ' + (action.generate ? 'data-clinical-generate-scout' : 'data-section-target="' + escapeHtml(action.target) + '"') + '>' + escapeHtml(action.label) + '</button></div>' +
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
            '<td>' + escapeHtml(item.action || 'Review with Scout preview') + '</td>' +
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
                return '<article class="qn-reporting-row-card qn-clinical-project-card"><div><h4>' + escapeHtml(item.project || 'Project ' + (index + 1)) + '</h4><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(item.tone || 'neutral') + '">' + escapeHtml(item.status || 'Draft') + '</span></div><dl>' +
                    reportingDatum('Method', item.method || 'Not set') +
                    reportingDatum('Measure', item.measure || 'Not set') +
                    reportingDatum('Next step', item.nextStep || 'Review with Scout preview') +
                    '</dl></article>';
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
                return '<article><div><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(item.tone || 'warning') + '">' + escapeHtml(item.priority || 'Priority') + '</span><h4>' + escapeHtml(item.item || 'Monitoring gap ' + (index + 1)) + '</h4></div>' +
                    (item.why ? '<p><strong>Why it matters</strong><span>' + escapeHtml(item.why) + '</span></p>' : '') +
                    (item.target ? '<p><strong>Target</strong><span>' + escapeHtml(item.target) + '</span></p>' : '') +
                    (item.area ? '<p><strong>Related area</strong><span>' + escapeHtml(item.area) + '</span></p>' : '') +
                    '</article>';
            }).join('') + '</div>' +
            '</section>';
    }

    function renderClinicalMonitoringCapabilityCards(setup, scout, clinical) {
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
        return '<section class="qn-reporting-cta-panel qn-clinical-cta-panel">' +
            '<div><p class="qn-eyebrow">Next step</p><h3>' + escapeHtml(action.helper) + '</h3><p>QualiNav will keep this page focused on monitoring cadence, aggregate data movement, committee review, and priority follow-up for Quality Directors.</p></div>' +
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
        var chips = [
            chip(hospitalName),
            chip(hospital.hospital_type_label || 'Hospital type not set'),
            chip(hospital.service_model_label || 'Service model not set'),
            chip('Scout: ' + scoutStatus)
        ].filter(Boolean).join('');
        page.innerHTML =
            renderSettingsAdminBanner(hospitalName) +
            '<div class="qn-reporting-header qn-settings-header">' +
            '<div><p class="qn-eyebrow">Settings</p><h2>Hospital Settings</h2><p>Review workspace preferences, regulatory monitoring, reminders, tools, and backup visibility.</p><div class="qn-reporting-context qn-settings-context">' + chips + '</div></div>' +
            '<span class="qn-status-pill qn-status-' + escapeHtml(settingsStatusTone(setup, scout)) + '">' + escapeHtml(settingsStatusLabel(setup, scout)) + '</span>' +
            '</div>' +
            renderSettingsWorkspaceContext(hospital, setup, scout) +
            '<div class="qn-settings-grid">' +
                renderSettingsRegulatory() +
                renderSettingsTools() +
                renderSettingsReminders() +
                renderSettingsBackupVisibility() +
            '</div>' +
            renderSettingsSetupStatus(setup, scout) +
            renderSettingsCtaPanel();
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
            ['location-alt', 'State', hospital.state_code || hospital.state_name || 'Not specified'],
            ['admin-home', 'Hospital type', hospital.hospital_type_label || 'Hospital type not set'],
            ['networking', 'Service model', hospital.service_model_label || 'Service model not set'],
            ['chart-pie', 'Payment model', hospital.payment_model_label || 'Available after setup'],
            ['admin-users', 'Current user role', state.me ? roleLabel(state.me.qualinav_role) : 'Workspace role'],
            ['visibility', 'Admin preview', isGlobalAdmin() && previewOrganizationId ? 'Active for this hospital' : 'Not active']
        ];
        return '<section class="qn-settings-card qn-settings-workspace">' +
            '<div class="qn-panel-header"><div><p class="qn-eyebrow">Workspace Context</p><h3>Hospital workspace</h3><p>These settings reflect the selected hospital workspace and the current Hospital Setup context.</p></div></div>' +
            '<div class="qn-settings-context-grid">' + rows.map(function (row) {
                return '<article><span class="dashicons dashicons-' + escapeHtml(row[0]) + '"></span><div><small>' + escapeHtml(row[1]) + '</small><strong>' + escapeHtml(row[2]) + '</strong></div></article>';
            }).join('') + '</div>' +
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
                ['Reminder status', configured ? 'Preferences captured' : 'Setup preferences pending'],
                ['Calendar sync', 'Not active yet']
            ]) +
            '<p class="qn-settings-note">Calendar sync is not active in this phase. Reminder preferences are readiness signals for future workflow scheduling.</p>' +
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
        var access = user.access_level ? optionLabelByValue(stepEightOptions('backup_access_level'), user.access_level) : '';
        return '<article>' +
            '<div><strong>' + escapeHtml(user.name || user.name_organization || 'Backup user ' + (index + 1)) + '</strong><span class="qn-scout-status-badge qn-scout-status-neutral">' + escapeHtml(access || 'Access not set') + '</span></div>' +
            '<dl>' +
                reportingDatum('Role', user.role || 'Not specified') +
                reportingDatum('Email', user.email || 'Not specified') +
                (user.notes || user.legacy ? reportingDatum('Notes', user.notes || user.legacy) : '') +
            '</dl>' +
            '</article>';
    }

    function renderSettingsSetupStatus(setup, scout) {
        var lastRun = state.latestScoutRun ? (state.latestScoutRun.created_at || state.latestScoutRun.updated_at || 'Available') : 'No run yet';
        var previews = scout.ready ? 'Reporting, committees, plans, and clinical monitoring previews available' : 'Module previews available after Scout Preview';
        return '<section class="qn-settings-card qn-settings-status-card">' +
            '<div class="qn-panel-header"><div><p class="qn-eyebrow">Setup Status</p><h3>Readiness status</h3><p>Use this to confirm whether setup-derived preferences are ready for module previews.</p></div><span class="qn-status-pill qn-status-' + escapeHtml(settingsStatusTone(setup, scout)) + '">' + escapeHtml(settingsStatusLabel(setup, scout)) + '</span></div>' +
            renderSettingsKeyValues([
                ['Setup status', setup.status + ' (' + setup.percent + '%)'],
                ['Scout Preview status', scout.status],
                ['Last Scout run', lastRun],
                ['Module previews', previews],
                ['Annual review', 'QualiNav will prompt you to review Hospital Setup information annually.']
            ]) +
            '</section>';
    }

    function renderSettingsCtaPanel() {
        var canEdit = canEditSettingsPreferences();
        return '<section class="qn-reporting-cta-panel qn-settings-cta-panel">' +
            '<div><p class="qn-eyebrow">Preferences</p><h3>' + escapeHtml(canEdit ? 'Edit preferences from Hospital Setup.' : 'View only') + '</h3><p>' + escapeHtml(canEdit ? 'This phase reviews setup-derived preferences. Updates still happen through the Hospital Setup workflow.' : 'Your current role can review these preferences but cannot edit hospital setup preferences.') + '</p></div>' +
            (canEdit ? '<button class="qn-button qn-button-primary" type="button" data-section-target="day-0-setup">Update preferences</button>' : '<span class="qn-status-pill qn-status-neutral">View only</span>') +
            '</section>';
    }

    function renderSettingsEmptyCard(icon, title, message) {
        return '<section class="qn-settings-card qn-settings-empty-card">' +
            '<div class="qn-settings-card-heading"><span class="dashicons dashicons-' + escapeHtml(icon) + '"></span><div><p class="qn-eyebrow">' + escapeHtml(title) + '</p><h3>' + escapeHtml(title) + '</h3></div></div>' +
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
            window.history.replaceState(null, '', '#' + section);
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

    function initSections() {
        var fallback = document.body.classList.contains('qn-admin-console-page') ? 'overview' : 'dashboard';
        var hash = window.location.hash ? window.location.hash.replace('#', '') : '';
        activateSection(hash || fallback, false);
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
        button.innerHTML = '<span class="dashicons dashicons-update"></span>' + escapeHtml(label || 'Working...');
        return function () {
            button.classList.remove('qn-is-loading');
            button.disabled = false;
            button.removeAttribute('aria-busy');
            button.innerHTML = button.getAttribute('data-original-html') || originalHtml;
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

    function setOnboardingSaveStatus(status, label) {
        var node = document.getElementById('qn-onboarding-save-status');
        if (!node) {
            return;
        }
        node.textContent = label || status || 'Ready';
        node.className = 'qn-save-status qn-save-status-' + (status || 'ready');
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
        return '<div class="qn-user-cell"><span class="qn-avatar">' + escapeHtml(initials(user.display_name, user.user_email)) + '</span>' +
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
                    actionMenuButton('Invite QD', 'data-invite-qd', hospital.id, 'email-alt'),
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
            if (invite) {
                var contextAttr = 'data-context="admin"';
                actionItems.push(actionMenuButton('Resend Invite', 'data-resend-invite', invite.id, 'update', '', contextAttr));
                actionItems.push(actionMenuButton('Revoke Invite', 'data-revoke-invite', invite.id, 'trash', 'danger', contextAttr));
            }
            if (organizationId && user.qualinav_status !== 'invited') {
                actionItems.push(actionMenuButton('Open Console', 'data-open-console', organizationId, 'dashboard'));
            }
            if (organizationId && user.qualinav_status === 'invited' && !invite) {
                actionItems.push(actionMenuButton('Open Console', 'data-open-console', organizationId, 'dashboard'));
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
            setTableMessage('qn-hospital-users-table-body', 5, 'No hospital users yet. Invite a user when you are ready.');
            return;
        }
        if (!users.length) {
            setTableMessage('qn-hospital-users-table-body', 5, 'No hospital users match your search or filters.');
            return;
        }
        body.innerHTML = users.map(function (user) {
            var actions = hospitalUserActions(user);
            return '<tr><td>' + userCell(user) + '</td>' +
                '<td>' + hospitalAccessCell(user) + '</td>' +
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
        var hospitalName = hospital ? (hospital.organization_name || hospital.name || 'this hospital') : 'this hospital';
        if (subtitle) {
            subtitle.textContent = 'Manage access for ' + hospitalName + '.';
        }
        if (context) {
            context.innerHTML = [
                chip(hospitalName),
                chip(state.me ? roleLabel(state.me.qualinav_role) : 'Current role'),
                chip(activeUsers.length + ' active users'),
                chip(pendingInvites.length + ' pending invites')
            ].join('');
        }
        if (summary) {
            summary.innerHTML = [
                usersSummaryCard('yes-alt', activeUsers.length, 'Active Users', 'Can access this workspace', 'success'),
                usersSummaryCard('email-alt', pendingInvites.length, 'Pending Invites', 'Awaiting acceptance', 'warning'),
                usersSummaryCard('businessperson', qualityDirectors.length, 'Quality Directors', 'Primary quality leads', 'info'),
                usersSummaryCard('hidden', disabledUsers.length, 'Disabled Users', 'Disabled or archived', 'danger')
            ].join('');
        }
        if (inviteButton) {
            var canInvite = canInviteHospitalUsers();
            inviteButton.hidden = false;
            inviteButton.disabled = !canInvite;
            inviteButton.title = canInvite ? 'Invite a user to this hospital workspace' : 'Your role has view-only access to invitations.';
        }
    }

    function usersSummaryCard(icon, count, label, detail, tone) {
        return '<article class="qn-users-summary-card qn-users-summary-' + escapeHtml(tone || 'neutral') + '">' +
            '<span class="dashicons dashicons-' + escapeHtml(icon) + '"></span><div><strong>' + escapeHtml(String(count)) + '</strong><span>' + escapeHtml(label) + '</span><small>' + escapeHtml(detail) + '</small></div></article>';
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

    function hospitalUserActions(user) {
        if (!canManageHospitalUsers()) {
            return [];
        }
        var userId = user.ID;
        var contextAttr = 'data-context="hospital"';
        var items = [];
        var roles = allowedInviteRoles().filter(function (role) {
            return role !== user.qualinav_role;
        });
        if (roles.length) {
            items.push('<span class="qn-action-menu-label">Change Role</span>');
            roles.forEach(function (role) {
                items.push(actionMenuButton('Make ' + (roleLabels[role] || roleLabel(role)), 'data-update-user-role', userId, 'admin-users', '', contextAttr + ' data-role="' + escapeHtml(role) + '"'));
            });
        }
        if (user.qualinav_status === 'active') {
            items.push(actionMenuButton('Disable User', 'data-update-user-status', userId, 'hidden', 'danger', contextAttr + ' data-status="disabled"'));
        }
        if (user.qualinav_status === 'disabled') {
            items.push(actionMenuButton('Reactivate User', 'data-update-user-status', userId, 'yes-alt', '', contextAttr + ' data-status="active"'));
        }
        if (user.qualinav_status !== 'archived') {
            items.push(actionMenuButton('Archive User', 'data-update-user-status', userId, 'trash', 'danger', contextAttr + ' data-status="archived"'));
        }
        var invite = findPendingInvitationForUser(user, userDefaultOrganizationId(user));
        if (invite) {
            items.push(actionMenuButton('Resend Invite', 'data-resend-invite', invite.id, 'update', '', contextAttr));
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
            var actions = canManage ? actionMenu([
                actionMenuButton('Resend', 'data-resend-invite', invite.id, 'update', '', contextAttr),
                actionMenuButton('Revoke', 'data-revoke-invite', invite.id, 'trash', 'danger', contextAttr)
            ]) : '<span class="qn-muted-text">No actions available</span>';
            return '<tr><td>' + cellPrimary(invite.full_name || invite.email, invite.email) + '</td>' +
                '<td>' + roleBadge(invite.qualinav_role) + '</td>' +
                (context === 'hospital' ? '<td>' + statusPill(invite.email_failed ? 'email failed' : 'email sent') + '</td>' : '') +
                '<td>' + statusPill(invite.status) + '</td>' +
                '<td>' + escapeHtml(text(invite.expires_at)) + '</td>' +
                (context === 'admin' ? '<td>' + escapeHtml(text(invite.invited_by_name || invite.invited_by)) + '</td>' : '') +
                '<td>' + actions + '</td></tr>';
        }).join('');
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

    function openInviteModal(options) {
        options = options || {};
        state.inviteContext = options.context || (document.body.classList.contains('qn-admin-console-page') ? 'admin' : 'hospital');
        state.fixedInviteRole = options.role || null;
        state.fixedInviteOrganization = options.organizationId || null;
        var modal = document.getElementById('qn-invite-modal');
        var form = document.getElementById('qn-invite-form');
        var org = document.getElementById('qn-invite-organization');
        var role = document.getElementById('qn-invite-role');
        var workspace = document.getElementById('qn-invite-workspace-name');
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
        if (org) {
            org.innerHTML = (state.allHospitals || state.hospitals).map(function (hospital) {
                var selected = state.fixedInviteOrganization && Number(state.fixedInviteOrganization) === Number(hospital.id) ? ' selected' : '';
                return '<option value="' + hospital.id + '"' + selected + '>' + escapeHtml(hospital.name) + '</option>';
            }).join('');
            org.disabled = !!state.fixedInviteOrganization;
            syncSearchableSelect(org);
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
        if (showLoading) {
            setWorkspaceLoading(true, 'Loading Hospital Setup...');
        }
        return api(path).then(function (payload) {
            state.onboarding = payload;
            state.onboardingOrganizationId = payload.current_organization_id;
            state.scoutOnboardingSubmitted = !!payload.onboarding_submitted || payload.onboarding_status === 'submitted' || state.scoutOnboardingSubmitted;
            renderOnboarding();
            renderHospitalContext(dashboardHospital());
            renderHospitalUsersOverview();
            renderHospitalDataSections();
        }).catch(function (error) {
            setText('#qn-onboarding-message', error.message);
        }).finally(function () {
            if (showLoading) {
                setWorkspaceLoading(false);
            }
        });
    }

    function loadScoutRuns(organizationId) {
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
        if (!body) {
            return;
        }
        renderScoutPageChrome();
        if (generate) {
            generate.hidden = true;
            generate.disabled = !state.scoutCanGenerate;
            generate.innerHTML = '<span class="dashicons dashicons-lightbulb"></span>Generate Preview';
        }

        if (!state.scoutOnboardingSubmitted && !state.latestScoutRun) {
            body.innerHTML = renderScoutEmptyState(
                'clipboard',
                'Complete Hospital Setup first',
                'Scout needs your hospital profile, services, reporting obligations, and priorities before it can generate your setup preview.',
                'Go to Hospital Setup',
                'day-0-setup'
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
                'Ready to generate Scout Preview',
                'Scout will create a draft reporting schedule, meeting flow map, survey readiness timeline, monitoring tasks, and priority queue.',
                'Generate Scout Preview',
                ''
            );
            if (generate && state.scoutCanGenerate) {
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
            scoutContextChip('Hospital type', hospital.hospital_type_label || context.hospital_category),
            scoutContextChip('Service model', hospital.service_model_label),
            scoutContextChip('Payment model', hospital.payment_model_label || context.payment_model),
            scoutContextChip('Survey pathway', context.survey_pathway),
            scoutContextChip('Guidance level', context.preferred_guidance_level)
        ].join('');
    }

    function scoutContextChip(label, value) {
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
        var cards = scoutWorkflowDefinitions().map(function (definition) {
            return renderScoutWorkflowCard(run, definition);
        }).join('');
        return renderScoutStatusHero(run) +
            renderScoutPersonaContext(run) +
            renderScoutAttentionPanel(run) +
            '<section class="qn-scout-workflow-section"><div class="qn-section-toolbar"><div><p class="qn-eyebrow">Workflow draft</p><h3>Generated operating system preview</h3></div></div>' +
            '<div class="qn-scout-grid">' + cards + '</div></section>' +
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
            scoutMetric('Warnings', counts.warnings) +
            scoutMetric('Missing inputs', counts.missing) +
            '</div>' +
            (cta ? '<div class="qn-scout-status-action">' + cta + '</div>' : '') +
            '</section>';
    }

    function scoutHeroCta(run) {
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
        var warnings = scoutWarnings(run).length;
        var missing = scoutMissingInputs(run).length;
        return {
            warnings: warnings,
            missing: missing,
            sources: scoutSources(run).length || (run && run.source_count !== null && run.source_count !== undefined ? run.source_count : 0)
        };
    }

    function renderScoutPersonaContext(run) {
        var context = run.persona_context || {};
        var summary = run.persona_summary || '';
        if (!summary && !Object.keys(context).length) {
            return '';
        }
        var rows = [
            ['Persona summary', summary || 'Not yet known'],
            ['Hospital category', context.hospital_category],
            ['Payment model', context.payment_model],
            ['Survey pathway', context.survey_pathway],
            ['Accreditation pathway', context.accreditation_pathway],
            ['Quality Director experience', context.quality_director_experience || context.quality_director_background],
            ['Guidance level', context.preferred_guidance_level],
            ['Program maturity', context.program_maturity],
            ['First 30 Days track', context.first_30_days_track ? 'Yes' : 'No']
        ];

        return '<section class="qn-scout-context-panel">' +
            '<div class="qn-panel-header"><div><p class="qn-eyebrow">Persona context</p><h3>Personalized for this hospital</h3></div><span class="dashicons dashicons-admin-users"></span></div>' +
            '<div class="qn-scout-persona-summary">' + escapeHtml(summary || 'Scout will personalize the preview as more Hospital Setup detail is available.') + '</div>' +
            '<div class="qn-scout-context-grid">' + rows.map(renderScoutContextRow).join('') + '</div>' +
            '</section>';
    }

    function renderScoutContextRow(row) {
        var missing = row[1] === null || row[1] === undefined || row[1] === '';
        return '<div class="qn-scout-kv"><span>' + escapeHtml(row[0]) + '</span><strong class="' + (missing ? 'qn-scout-muted-chip' : 'qn-scout-value-chip') + '">' + escapeHtml(formatScoutValue(row[1], 'Not yet known')) + '</strong></div>';
    }

    function renderScoutAttentionPanel(run) {
        var missing = scoutMissingInputs(run);
        var warnings = scoutWarnings(run);
        var backend = run && run.status === 'failed' ? [safeScoutError(run.error_message)] : [];
        if (!missing.length && !warnings.length && !backend.length) {
            return '<section class="qn-scout-attention qn-scout-attention-quiet"><div><p class="qn-eyebrow">Needs your attention</p><h3>No immediate issues returned</h3><p>Scout did not return warnings or missing-input flags for this preview.</p></div></section>';
        }
        return '<section class="qn-scout-attention">' +
            '<div class="qn-section-toolbar"><div><p class="qn-eyebrow">Needs your attention</p><h3>Inputs and warnings to review</h3></div></div>' +
            '<div class="qn-scout-attention-grid">' +
            renderScoutAttentionGroup('Missing inputs', missing, 'editor-help', 'danger') +
            renderScoutAttentionGroup('Scout warnings', warnings, 'warning', 'warning') +
            renderScoutAttentionGroup('Backend/contract warnings', backend, 'shield', 'warning') +
            '</div></section>';
    }

    function renderScoutAttentionGroup(title, items, icon, tone) {
        items = cleanScoutList(items);
        return '<article><h4><span class="dashicons dashicons-' + escapeHtml(icon) + '"></span>' + escapeHtml(title) + '</h4>' +
            (items.length ? '<div class="qn-scout-chip-list">' + items.map(function (item) {
                return '<span class="qn-warning-chip qn-warning-chip-' + escapeHtml(tone) + '">' + escapeHtml(describeScoutItem(item)) + '</span>';
            }).join('') + '</div>' : '<p class="qn-muted-note">None returned.</p>') +
            '</article>';
    }

    function renderScoutWorkflowCard(run, definition) {
        var group = findScoutGroup(run, definition);
        var counts = scoutGroupCounts(group);
        var status = scoutGroupStatus(group, counts);
        var previewText = scoutGroupPreviewText(group, status);
        return '<article class="qn-card qn-scout-card qn-scout-card-' + escapeHtml(status.tone) + '">' +
            '<span class="dashicons ' + scoutIcon(definition.key) + '"></span>' +
            '<div class="qn-scout-card-body"><div class="qn-scout-card-top"><h3>' + escapeHtml(definition.title) + '</h3><span class="qn-scout-status-badge qn-scout-status-' + escapeHtml(status.tone) + '">' + escapeHtml(status.label) + '</span></div>' +
            '<p>' + escapeHtml(previewText) + '</p>' +
            '<div class="qn-scout-card-metrics">' +
            '<span>' + escapeHtml(String(counts.items)) + ' items</span>' +
            '<span>' + escapeHtml(String(counts.warnings)) + ' warnings</span>' +
            '<span>' + escapeHtml(String(counts.missing)) + ' missing</span>' +
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

    function renderScoutSources(sources) {
        sources = Array.isArray(sources) ? sources : [];
        if (!sources.length) {
            return '<section class="qn-scout-sources"><div class="qn-panel-header"><div><p class="qn-eyebrow">Sources</p><h3>Sources used by Scout</h3></div><span class="qn-status-pill">0 listed</span></div><p class="qn-muted-note">No source references were returned for this preview.</p></section>';
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
            return '<div class="qn-scout-kv"><span>' + escapeHtml(key.replace(/_/g, ' ')) + '</span><strong>' + escapeHtml(describeScoutItem(value[key])) + '</strong></div>';
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
        return titles[key] || text(key).replace(/_/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
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
        return normalizePublicSetupCopy(text(value).replace(/_/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); }));
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
                return normalizePublicSetupCopy(key.replace(/_/g, ' ')) + ': ' + describeScoutItem(item[key]);
            }).join(' | ');
        }
        return String(item);
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
                return '<div><span>' + escapeHtml(key.replace(/_/g, ' ')) + '</span><strong>' + renderScoutReadableValue(value[key]) + '</strong></div>';
            }).join('') + '</div>';
        }
        return escapeHtml(describeScoutItem(value));
    }

    function generateScoutPreview(trigger) {
        var button = trigger || document.getElementById('qn-scout-generate-button');
        var restoreButton = setButtonLoading(button, 'Generating...');
        var body = {organization_id: state.onboardingOrganizationId};
        return api('/scout/generate', {method: 'POST', body: body, timeout: 60000}).then(function (result) {
            state.latestScoutRun = result.run || null;
            showToast(state.latestScoutRun && state.latestScoutRun.status === 'failed' ? 'Scout preview failed. Retry is available.' : 'Scout preview generated.', state.latestScoutRun && state.latestScoutRun.status === 'failed' ? 'warning' : 'success');
            renderHospitalDashboard();
            return loadScoutRuns(state.onboardingOrganizationId);
        }).catch(function (error) {
            showToast(error && error.message ? error.message : 'Scout preview could not be generated. Please try again.', 'warning');
            renderScoutPreview();
        }).finally(function () {
            restoreButton();
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
        setText('#qn-onboarding-step-description', step.section_key === 'plans_policies_monitoring' ? 'Set up required plans, policy workflows, and aggregate monitoring structure for Scout.' : step.description);
        var progress = state.onboarding.progress ? state.onboarding.progress.total_percent : 0;
        setText('#qn-onboarding-progress-text', isOnboardingSubmitted() ? 'Setup submitted - ' + progress + '% complete' : progress + '% complete');
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
        node.textContent = label;
        node.classList.toggle('qn-chip-warning', !!warning);
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
            return '<button type="button" class="qn-stepper-item qn-stepper-' + status + (active ? ' qn-stepper-active' : '') + '" data-onboarding-step="' + index + '">' +
                '<span class="qn-stepper-index"><span class="dashicons dashicons-' + icon + '"></span><b>' + (index + 1) + '</b></span>' +
                '<span class="qn-stepper-copy"><strong>' + escapeHtml(step.title) + '</strong><small>' + escapeHtml(stepStatusLabel(status)) + '</small></span>' +
            '</button>';
        }).join('');
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
        if (found && Number(found.percent_complete) >= 100) {
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
        var questions = (state.onboarding.questions || []).filter(function (question) {
            return getSectionKeyForQuestion(question) === step.section_key && conditionalVisible(question);
        });
        if (step.section_key === 'goals_learning_contacts') {
            questions = (state.onboarding.questions || []).filter(function (question) {
                return getSectionKeyForQuestion(question) === step.section_key;
            });
        }
        container.innerHTML = renderQuestionGroups(step, questions);
        updateStepOneBedWarning();
        updateStepTwoConditionalUI();
        updateStepThreeConditionalUI();
        updateStepSevenConditionalUI();
        if (!canEditOnboardingStep(step)) {
            container.querySelectorAll('input, textarea, select, button').forEach(function (node) {
                node.disabled = true;
            });
        }
    }

    function renderQuestionGroups(step, questions) {
        if (step.section_key === 'hospital_director_info') {
            var hospitalKeys = ['hospital_name', 'hospital_city', 'hospital_state', 'licensed_beds', 'acute_beds', 'swing_beds', 'is_critical_access_hospital', 'independent_or_system'];
            var directorKeys = ['quality_director_name', 'quality_director_role_start_date', 'quality_director_background'];
            return renderQuestionGroup('Hospital Information', 'building', questions.filter(function (question) {
                return hospitalKeys.indexOf(question.question_key) !== -1;
            }), '<div class="qn-bed-warning" id="qn-step1-bed-warning" hidden><span class="dashicons dashicons-warning"></span>Acute and swing beds exceed licensed beds. Please verify.</div>') + renderQuestionGroup('Quality Director Information', 'businessperson', questions.filter(function (question) {
                return directorKeys.indexOf(question.question_key) !== -1;
            }));
        }
        if (step.section_key === 'accreditation_survey_readiness') {
            var pathwayKeys = ['accreditation_status', 'accrediting_body', 'cms_certification_pathway', 'state_survey_agency', 'life_safety_survey_agency', 'accreditation_360'];
            var riskKeys = ['open_plans_of_correction', 'projected_next_survey_window', 'historical_deficiency_areas', 'current_readiness_activities'];
            return renderQuestionGroup('Accreditation & Certification Pathway', 'awards', questions.filter(function (question) {
                return pathwayKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepTwoQuestion) + renderQuestionGroup('Current Survey Risk', 'warning', questions.filter(function (question) {
                return riskKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepTwoQuestion) + renderQuestionGroup('Survey History', 'calendar-alt', questions.filter(function (question) {
                return question.question_key === 'survey_history';
            }), '', renderStepTwoQuestion);
        }
        if (step.section_key === 'services_clinical_model') {
            return renderQuestionGroup(step.title, 'admin-tools', questions, '', renderStepThreeQuestion);
        }
        if (step.section_key === 'committees_reporting') {
            var committeeKeys = ['committee_list'];
            var obligationKeys = ['reporting_obligations'];
            var defaultsKeys = ['mbqip_measure_set', 'backup_preparer', 'report_lead_time', 'approval_requirements', 'board_agenda_timing'];
            var hiddenCommitteeDefaults = renderPreservedStepFourFields(['committee_required_status', 'standing_agenda_items', 'minutes_owner_location']);
            return renderQuestionGroup('Committee Structure', 'groups', questions.filter(function (question) {
                return committeeKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepFourQuestion) + renderQuestionGroup('Reporting Obligations', 'media-spreadsheet', questions.filter(function (question) {
                return obligationKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepFourQuestion) + renderQuestionGroup('Reporting Defaults & Program Notes', 'clipboard', questions.filter(function (question) {
                return defaultsKeys.indexOf(question.question_key) !== -1;
            }), hiddenCommitteeDefaults, renderStepFourQuestion);
        }
        if (step.section_key === 'plans_policies_monitoring') {
            var planKeys = ['qapi_plan_status', 'patient_safety_plan_status', 'infection_prevention_plan_status', 'emergency_preparedness_plan_status', 'risk_management_plan_status'];
            var policyKeys = ['plan_location_authority', 'policy_management_system', 'annual_policy_review_cycle', 'templates_needed'];
            var monitoringKeys = ['morbidity_mortality_monitoring', 'blood_usage_review', 'medication_safety_monitoring', 'operative_invasive_review', 'anesthesia_sedation_monitoring', 'sentinel_never_event_protocol', 'ancillary_services_review', 'contracted_service_quality_data_flow'];
            var priorityKeys = ['weakest_monitoring_areas'];
            var phiWarning = '<div class="qn-step5-phi-warning qn-question-wide"><span class="dashicons dashicons-shield"></span><div><strong>Do not enter patient information</strong><p>Do not enter patient names, MRNs, provider case details, incident narratives, peer-review details, or specific adverse-event details. QualiNav only stores structural information and aggregate/de-identified data.</p></div></div>';
            var monitoringReminder = '<div class="qn-step5-monitoring-note qn-question-wide"><span class="dashicons dashicons-info"></span>Process information only - no case-level details.</div>';
            return renderQuestionGroup('Required Plans', 'portfolio', questions.filter(function (question) {
                return planKeys.indexOf(question.question_key) !== -1;
            }), phiWarning, renderStepFiveQuestion) + renderQuestionGroup('Policy Library & Templates', 'media-document', questions.filter(function (question) {
                return policyKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepFiveQuestion) + renderQuestionGroup('Clinical Monitoring Areas', 'heart', questions.filter(function (question) {
                return monitoringKeys.indexOf(question.question_key) !== -1;
            }), monitoringReminder, renderStepFiveQuestion) + renderQuestionGroup('Monitoring Gaps & Priorities', 'flag', questions.filter(function (question) {
                return priorityKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepFiveQuestion);
        }
        if (step.section_key === 'measures_qi_projects') {
            var uploadKeys = ['mbqip_upload', 'nhsn_hai_rates_upload', 'patient_experience_scores_upload', 'fall_rates_upload', 'pressure_injury_rates_upload', 'hand_hygiene_upload', 'other_dashboard_metrics'];
            var dashboardKeys = ['current_quality_dashboard', 'data_source_currency'];
            var projectKeys = ['active_qi_projects'];
            var defaultsKeys = ['qi_framework', 'project_charters_status', 'baseline_data_status'];
            return renderQuestionGroup('Measure Upload Plan', 'upload', questions.filter(function (question) {
                return uploadKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepSixQuestion) + renderQuestionGroup('Current Quality Dashboard', 'chart-line', questions.filter(function (question) {
                return dashboardKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepSixQuestion) + renderQuestionGroup('Active QI Projects', 'clipboard', questions.filter(function (question) {
                return projectKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepSixQuestion) + renderQuestionGroup('QI Program Defaults', 'admin-generic', questions.filter(function (question) {
                return defaultsKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepSixQuestion);
        }
        if (step.section_key === 'goals_learning_contacts') {
            var goalKeys = ['department_goals_this_year', 'department_goals_two_three_years', 'protected_workflow_goals', 'program_gaps', 'strategic_plan_alignment'];
            var experienceKeys = ['new_to_quality_director_role', 'time_in_current_role', 'quality_certifications', 'confidence_foundational', 'confidence_qi_patient_safety', 'confidence_specialized_areas', 'confidence_professional_development'];
            var learningKeys = ['activate_first_30_days_track', 'learning_format_preference'];
            var contactKeys = ['state_flex_contact', 'state_office_rural_health_contact', 'state_hospital_association_contact', 'state_survey_agency_contacts', 'peer_cah_contacts', 'accreditation_liaison', 'referral_hospital_contacts'];
            return renderQuestionGroup('Strategic Goals', 'star-filled', questions.filter(function (question) {
                return goalKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepSevenQuestion) + renderQuestionGroup('Quality Director Experience', 'businessperson', questions.filter(function (question) {
                return experienceKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepSevenQuestion) + renderQuestionGroup('Learning Journey', 'welcome-learn-more', questions.filter(function (question) {
                return learningKeys.indexOf(question.question_key) !== -1;
            }), renderStepSevenLearningNote(), renderStepSevenQuestion) + renderQuestionGroup('External Contacts', 'phone', questions.filter(function (question) {
                return contactKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepSevenQuestion);
        }
        if (step.section_key === 'regulatory_tools_preferences') {
            var monitoringKeys = ['monitored_sources', 'update_preference', 'auto_propose_task_adjustments'];
            var toolsKeys = ['current_tools', 'calendar_system', 'ehr_system', 'incident_reporting_system', 'nhsn_qualitynet_access'];
            var reminderKeys = ['reminder_lead_time', 'reminder_buffer_time', 'backup_visibility_users'];
            var confirmKeys = ['final_review_confirmation'];
            return renderQuestionGroup('Regulatory Monitoring', 'megaphone', questions.filter(function (question) {
                return monitoringKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepEightQuestion) + renderQuestionGroup('Tools & Systems', 'admin-tools', questions.filter(function (question) {
                return toolsKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepEightQuestion) + renderQuestionGroup('Reminders & Backup Coverage', 'clock', questions.filter(function (question) {
                return reminderKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepEightQuestion) + renderQuestionGroup('Review & Confirm', 'yes-alt', questions.filter(function (question) {
                return confirmKeys.indexOf(question.question_key) !== -1;
            }), '', renderStepEightQuestion);
        }
        return renderQuestionGroup(step.title, 'clipboard', questions);
    }

    function renderPreservedStepFourFields(keys) {
        return keys.map(function (key) {
            var value = state.onboarding && state.onboarding.answers ? state.onboarding.answers[key] : '';
            if (value === undefined || value === null || value === '') {
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

    function renderQuestionGroup(title, icon, questions, extraHtml, renderer) {
        if (!questions.length) {
            return '';
        }
        renderer = renderer || renderQuestion;
        return '<section class="qn-question-group"><header><span class="dashicons dashicons-' + icon + '"></span><h4>' + escapeHtml(title) + '</h4></header><div class="qn-question-grid">' +
            (extraHtml || '') +
            questions.map(renderer).join('') + '</div></section>';
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
        var guide = document.querySelector('.qn-onboarding-scout-guide');
        if (guide && !guide.dataset.disclosureBound) {
            var guideSummary = guide.querySelector('summary');
            guide.addEventListener('toggle', updateScoutGuideDisclosureState);
            if (guideSummary) {
                guideSummary.addEventListener('keydown', function (event) {
                    if (event.key !== 'Enter' && event.key !== ' ') {
                        return;
                    }
                    event.preventDefault();
                    guide.open = !guide.open;
                    updateScoutGuideDisclosureState();
                });
            }
            guide.dataset.disclosureBound = '1';
        }
        if (guide && guide.dataset.stepKey !== step.section_key) {
            guide.open = false;
            guide.dataset.stepKey = step.section_key;
        }
        updateScoutGuideDisclosureState();
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
            boundaryNote = '<li class="qn-data-boundary-note"><span class="dashicons dashicons-shield"></span><span>Process and aggregate information only. Do not enter patient, provider, peer-review, or event-specific details.</span></li>';
        }
        if (step.section_key === 'measures_qi_projects') {
            boundaryNote = '<li class="qn-data-boundary-note"><span class="dashicons dashicons-shield"></span><span>Only enter aggregate, de-identified measure and project information. Do not enter patient-level data.</span></li>';
        }
        if (step.section_key === 'goals_learning_contacts') {
            boundaryNote = '<li class="qn-data-boundary-note"><span class="dashicons dashicons-shield"></span><span>Only enter professional contact and program information. Do not enter patient or case-level details.</span></li>';
        }
        if (step.section_key === 'regulatory_tools_preferences') {
            boundaryNote = '<li class="qn-data-boundary-note"><span class="dashicons dashicons-shield"></span><span>Final check: do not submit PHI, patient identifiers, incident narratives, or peer-review case details.</span></li>';
        }
        node.innerHTML = items.map(function (item) {
            return '<li><span class="dashicons dashicons-yes-alt"></span><span>' + escapeHtml(item) + '</span></li>';
        }).join('') + boundaryNote;
    }

    function updateScoutGuideDisclosureState() {
        var guide = document.querySelector('.qn-onboarding-scout-guide');
        var summary = guide ? guide.querySelector('summary') : null;
        if (summary) {
            summary.setAttribute('aria-expanded', guide.open ? 'true' : 'false');
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

    function shouldAutoShowWorkspaceWelcome() {
        if (!state.me || isGlobalAdmin() || state.workspaceWelcomeAutoShown) {
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
            'Hospital Setup is the first step. It helps Scout understand how your hospital works so your workspace can be tailored to your actual reporting obligations, committee structure, monitoring processes, goals, and preferences.' :
            'Hospital Setup gives Scout the context for this hospital workspace. Your role can review the setup, Scout preview, reporting schedule, committee flow, monitoring areas, and preferences without changing saved answers.');
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
            var button = document.getElementById('qn-workspace-welcome-primary');
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
                if (window.localStorage && (!dismiss || dismiss.checked)) {
                    window.localStorage.setItem(workspaceWelcomeStorageKey(), '1');
                }
            } catch (error) {
                // Local storage is optional progressive enhancement.
            }
        }
    }

    function goToHospitalSetupFromWelcome() {
        closeWorkspaceWelcome(true);
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
        var content = '<div class="qn-question-list-warning"><span class="dashicons dashicons-shield"></span><p><strong>No PHI.</strong> Do not enter patient names, MRNs, provider case details, peer-review details, or adverse-event narratives. This preparation list does not include saved answers.</p></div>';
        content += '<div class="qn-question-list-materials"><h3>Helpful materials to gather</h3><ul>' + onboardingMaterialsChecklist.map(function (item) {
            return '<li>' + escapeHtml(item) + '</li>';
        }).join('') + '</ul></div>';
        content += steps.map(function (step, index) {
            var stepQuestions = questions.filter(function (question) {
                return getSectionKeyForQuestion(question) === step.section_key;
            });
            return '<section class="qn-question-list-section">' +
                '<p class="qn-eyebrow">Step ' + (index + 1) + '</p>' +
                '<h3>' + escapeHtml(step.title) + '</h3>' +
                '<p>' + escapeHtml(step.description || step.informs || '') + '</p>' +
                '<ol>' + stepQuestions.map(function (question) {
                    return '<li><strong>' + escapeHtml(question.label) + (question.is_required ? ' *' : '') + '</strong>' + (question.help_text ? '<small>' + escapeHtml(question.help_text) + '</small>' : '') + '</li>';
                }).join('') + '</ol>' +
                '</section>';
        }).join('');
        return content;
    }

    function printOnboardingQuestionList() {
        if (!state.onboarding) {
            showToast('Setup questions are still loading.', 'warning');
            return;
        }
        var printable = '<!doctype html><html><head><title>QualiNav Hospital Setup Question List</title><style>' +
            'body{font-family:Arial,sans-serif;color:#172033;margin:32px;line-height:1.5}h1{font-size:28px;margin:0 0 8px}h2,h3{break-after:avoid}section{border-top:1px solid #dbe5ef;padding-top:18px;margin-top:18px}ol{padding-left:22px}li{margin:0 0 10px}small{display:block;color:#5f7188}.warning{border:1px solid #f6c177;background:#fff7ed;border-radius:12px;padding:14px;margin:18px 0}.materials{columns:2;gap:28px}@media print{button{display:none}.materials{columns:2}}' +
            '</style></head><body>' +
            '<h1>QualiNav Hospital Setup Question List</h1>' +
            '<p>Use this preparation worksheet to gather operational information before entering Hospital Setup. It intentionally does not include saved answers.</p>' +
            '<div class="warning"><strong>No PHI.</strong> Do not enter patient names, MRNs, provider case details, peer-review details, or adverse-event narratives.</div>' +
            '<h2>Helpful materials to gather</h2><ul class="materials">' + onboardingMaterialsChecklist.map(function (item) { return '<li>' + escapeHtml(item) + '</li>'; }).join('') + '</ul>' +
            renderOnboardingQuestionListHtml().replace(/<div class="qn-question-list-warning">[\s\S]*?<\/div><div class="qn-question-list-materials">[\s\S]*?<\/div>/, '') +
            '<script>window.onload=function(){window.focus();window.print();};<\/script></body></html>';
        var win = window.open('', '_blank', 'noopener,noreferrer,width=980,height=760');
        if (!win) {
            showToast('Allow pop-ups to print or save the Hospital Setup question list.', 'warning');
            return;
        }
        win.document.open();
        win.document.write(printable);
        win.document.close();
    }

    function renderQuestion(question) {
        var value = onboardingQuestionValue(question);
        var required = question.is_required ? ' <span class="qn-required">*</span>' : '';
        var helpText = stepOneHelpText(question) || question.help_text;
        var help = helpText ? '<small>' + escapeHtml(helpText) + '</small>' : '';
        var tag = isSegmentedQuestion(question) ? 'div' : 'label';
        return '<' + tag + ' class="qn-question ' + questionLayoutClass(question) + '" data-question="' + escapeHtml(question.question_key) + '">' +
            '<span>' + escapeHtml(question.label) + required + '</span>' + renderField(question, value) + help + '</' + tag + '>';
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
        if (key === 'licensed_beds') {
            return state.onboarding.licensed_beds !== undefined && state.onboarding.licensed_beds !== null ? state.onboarding.licensed_beds : '';
        }
        if (key === 'acute_beds') {
            return state.onboarding.acute_beds !== undefined && state.onboarding.acute_beds !== null ? state.onboarding.acute_beds : '';
        }
        if (key === 'swing_beds') {
            return state.onboarding.swing_beds !== undefined && state.onboarding.swing_beds !== null ? state.onboarding.swing_beds : '';
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
        return '';
    }

    function questionLayoutClass(question) {
        if (question.field_type === 'textarea' || question.field_type === 'repeater' || question.field_type === 'plan_status') {
            return 'qn-question-wide';
        }
        if (['licensed_beds', 'acute_beds', 'swing_beds'].indexOf(question.question_key) !== -1) {
            return 'qn-question-third';
        }
        return '';
    }

    function optionLabel(option) {
        return text(option).replace(/_/g, ' ').replace(/\b\w/g, function (letter) {
            return letter.toUpperCase();
        });
    }

    function fieldValue(value) {
        return value === null || value === undefined || value === '-' ? '' : String(value);
    }

    function escapeFieldValue(value) {
        return fieldValue(value).replace(/[&<>"']/g, function (char) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
        });
    }

    function questionPlaceholder(question) {
        var placeholders = {
            hospital_name: 'Enter hospital name',
            hospital_city: 'Enter city',
            licensed_beds: 'Enter licensed beds',
            acute_beds: 'Enter acute beds',
            swing_beds: 'Enter swing beds',
            quality_director_name: 'Enter Quality Director name',
            quality_director_background: 'Example: RN with 10 years in quality, CPHQ certified, previously infection prevention lead.'
        };
        return placeholders[question.question_key] || '';
    }

    function stepOneHelpText(question) {
        if (question.question_key === 'quality_director_background') {
            return 'Scout uses this to calibrate guidance level and learning support. Do not include PHI.';
        }
        return '';
    }

    function stepTwoPlaceholder(key) {
        var placeholders = {
            state_survey_agency: 'Example: Tennessee Department of Health',
            life_safety_survey_agency: 'Example: State Fire Marshal\'s Office'
        };
        return placeholders[key] || '';
    }

    function stepOneOptions(key) {
        var optionMap = {
            is_critical_access_hospital: [
                {value: 'yes', label: 'Yes'},
                {value: 'no', label: 'No'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            independent_or_system: [
                {value: 'independent', label: 'Independent'},
                {value: 'system_owned', label: 'System-Owned'},
                {value: 'network_affiliated', label: 'Network-Affiliated'},
                {value: 'managed_services', label: 'Managed Services'},
                {value: 'other', label: 'Other'},
                {value: 'not_sure', label: 'Not sure'}
            ]
        };
        return optionMap[key] || [];
    }

    function renderStepOneSelect(key, value, options, placeholder) {
        value = fieldValue(value);
        return '<select data-onboarding-field="' + escapeHtml(key) + '"><option value="">' + escapeHtml(placeholder || 'Select') + '</option>' + options.map(function (option) {
            return '<option value="' + escapeFieldValue(option.value) + '"' + (value === option.value ? ' selected' : '') + '>' + escapeHtml(option.label) + '</option>';
        }).join('') + '</select>';
    }

    function stepTwoOptions(key) {
        var optionMap = {
            accreditation_status: [
                {value: 'accredited', label: 'Accredited'},
                {value: 'cms_state_survey_only', label: 'CMS/state survey only'},
                {value: 'not_accredited', label: 'Not accredited'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            accrediting_body: [
                {value: 'joint_commission', label: 'The Joint Commission'},
                {value: 'dnv', label: 'DNV'},
                {value: 'hfap', label: 'HFAP'},
                {value: 'cihq', label: 'CIHQ'},
                {value: 'other', label: 'Other'},
                {value: 'not_applicable', label: 'Not applicable'},
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
                {value: 'cms_recertification_survey', label: 'CMS recertification survey'},
                {value: 'state_licensure_survey', label: 'State licensure survey'},
                {value: 'complaint_survey', label: 'Complaint survey'},
                {value: 'life_safety_code_survey', label: 'Life Safety Code survey'},
                {value: 'focused_review', label: 'Focused review'},
                {value: 'mock_survey', label: 'Mock survey'},
                {value: 'other', label: 'Other'}
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
            accreditation_status: {
                cms_certified: 'cms_state_survey_only',
                in_progress: 'not_sure'
            },
            accrediting_body: {
                'The Joint Commission': 'joint_commission',
                DNV: 'dnv',
                HFAP: 'hfap',
                CIHQ: 'cihq',
                ACHC: 'other',
                'State/CMS': 'not_applicable',
                Other: 'other'
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
        var tag = ['historical_deficiency_areas', 'current_readiness_activities', 'survey_history'].indexOf(question.question_key) !== -1 ? 'div' : 'label';
        return '<' + tag + ' class="qn-question ' + questionLayoutClass(question) + '" data-question="' + escapeHtml(question.question_key) + '">' +
            '<span>' + escapeHtml(question.label) + required + '</span>' + renderStepTwoField(question, value) + help + renderStepTwoConditionalNote(question.question_key) + '</' + tag + '>';
    }

    function renderStepTwoField(question, value) {
        var key = question.question_key;
        if (['accreditation_status', 'accrediting_body', 'cms_certification_pathway', 'open_plans_of_correction', 'projected_next_survey_window'].indexOf(key) !== -1) {
            var placeholders = {
                accreditation_status: 'Select accreditation status',
                accrediting_body: 'Select accreditor',
                cms_certification_pathway: 'Select pathway',
                open_plans_of_correction: 'Select POC status',
                projected_next_survey_window: 'Select survey window'
            };
            return renderStepOneSelect(key, legacyStepTwoValue(key, value), stepTwoOptions(key), placeholders[key]);
        }
        if (key === 'historical_deficiency_areas' || key === 'current_readiness_activities') {
            return renderMultiselectField(key, value, stepTwoOptions(key), key === 'historical_deficiency_areas' ? 'Select deficiency areas' : 'Select readiness activities');
        }
        if (key === 'survey_history') {
            return renderSurveyHistoryRepeater(key, value);
        }
        if (key === 'accreditation_360') {
            return renderStepOneSelect(key, value, stepTwoOptions(key), 'Select Accreditation 360 status');
        }
        return '<input type="text" data-onboarding-field="' + escapeHtml(key) + '" value="' + escapeFieldValue(value) + '" placeholder="' + escapeHtml(stepTwoPlaceholder(key) || 'Enter agency name') + '">';
    }

    function stepTwoHelpText(question) {
        var help = {
            cms_certification_pathway: 'This tells Scout whether to organize readiness around accreditation standards or CMS Conditions of Participation.',
            state_survey_agency: 'Enter the agency responsible for state licensure or CMS survey activity.'
        };
        return help[question.question_key] || question.help_text || '';
    }

    function renderStepTwoConditionalNote(key) {
        if (key === 'accrediting_body') {
            return '<small class="qn-conditional-note" id="qn-joint-commission-note" hidden>Scout can account for Joint Commission readiness and Accreditation 360 prompts.</small><small class="qn-conditional-note" id="qn-accreditor-not-applicable-note" hidden>CMS/state survey-only or not accredited hospitals usually do not need an accrediting body.</small>';
        }
        if (key === 'open_plans_of_correction') {
            return '<small class="qn-conditional-warning" id="qn-poc-process-warning" hidden>Only describe process status. Do not enter patient, provider, peer-review, or case-level details.</small>';
        }
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
        var optionValues = options.map(function (option) { return option.value; });
        if (optionValues.indexOf(value) !== -1) {
            return [value];
        }
        return [value];
    }

    function optionLabelByValue(options, value) {
        var found = options.find(function (option) {
            return option.value === value;
        });
        return found ? found.label : value;
    }

    function multiselectOptionsForKey(key) {
        if (['historical_deficiency_areas', 'current_readiness_activities'].indexOf(key) !== -1) {
            return stepTwoOptions(key);
        }
        if (['surgery_procedure_types', 'radiology_model', 'anesthesia_moderate_sedation_model'].indexOf(key) !== -1) {
            return stepThreeOptions(key);
        }
        if (['mbqip_measure_set', 'approval_requirements'].indexOf(key) !== -1) {
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
        var optionValues = options.map(function (option) { return option.value; });
        var customValues = values.filter(function (item) { return optionValues.indexOf(item) === -1; });
        var chips = values.map(function (item) {
            return '<span class="qn-selected-chip" data-chip-value="' + escapeFieldValue(item) + '">' + escapeHtml(optionLabelByValue(options, item)) + '<button class="qn-chip-remove" type="button" data-multiselect-remove="' + escapeFieldValue(item) + '" aria-label="Remove ' + escapeHtml(optionLabelByValue(options, item)) + '"><span class="dashicons dashicons-no-alt"></span></button></span>';
        }).join('');
        return '<div class="qn-multiselect" data-checklist="' + escapeHtml(key) + '">' +
            '<button class="qn-multiselect-trigger" type="button" data-multiselect-trigger aria-expanded="false"><span data-multiselect-placeholder>' + escapeHtml(placeholder) + '</span><span class="dashicons dashicons-arrow-down-alt2"></span></button>' +
            '<div class="qn-selected-chips"' + (values.length ? '' : ' hidden') + '>' + chips + '</div>' +
            '<div class="qn-multiselect-menu" role="listbox" aria-label="' + escapeHtml(placeholder) + '">' + options.map(function (option) {
                return '<label class="qn-multiselect-option"><input type="checkbox" data-checklist-field="' + escapeHtml(key) + '" value="' + escapeFieldValue(option.value) + '"' + (values.indexOf(option.value) !== -1 ? ' checked' : '') + '><span>' + escapeHtml(option.label) + '</span></label>';
            }).join('') + customValues.map(function (item) {
                return '<input type="checkbox" data-checklist-field="' + escapeHtml(key) + '" value="' + escapeFieldValue(item) + '" checked hidden>';
            }).join('') + '</div>' +
            '</div>';
    }

    function renderSurveyHistoryRepeater(key, value) {
        value = Array.isArray(value) ? value : [];
        var columns = ['survey_date', 'survey_type', 'surveying_agency', 'deficiencies_cited', 'poc_due_followup'];
        return '<div class="qn-repeater qn-survey-history" data-repeater="' + escapeHtml(key) + '" data-repeater-style="survey-history" data-columns="' + escapeHtml(JSON.stringify(columns)) + '">' +
            (value.length ? '' : '<div class="qn-survey-empty"><strong>No survey history added yet.</strong><span>Add prior surveys if available; you can also skip this for now.</span></div>') +
            value.map(function (row, index) {
                return renderSurveyHistoryRow(key, columns, row, index);
            }).join('') + '<button class="qn-button qn-button-small qn-add-survey-row" type="button" data-add-repeater="' + escapeHtml(key) + '"><span class="dashicons dashicons-plus-alt2"></span>Add survey</button></div>';
    }

    function renderSurveyHistoryRow(key, columns, row, index) {
        row = row || {};
        return '<div class="qn-repeater-row qn-survey-history-row">' +
            '<div class="qn-survey-card-header"><strong>Survey ' + (index + 1) + '</strong><button class="qn-icon-button qn-delete-survey-row" type="button" data-delete-repeater-row aria-label="Delete survey history row"><span class="dashicons dashicons-trash"></span></button></div>' +
            '<div class="qn-survey-card-grid">' +
                '<label><span>Survey date</span><input type="date" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="survey_date" value="' + escapeFieldValue(row.survey_date || '') + '"></label>' +
                '<label><span>Survey type</span>' + renderRepeaterSelect(key, index, 'survey_type', row.survey_type || '', stepTwoOptions('survey_type'), 'Select type') + '</label>' +
                '<label><span>Surveying agency</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="surveying_agency" value="' + escapeFieldValue(row.surveying_agency || '') + '" placeholder="Enter agency name"></label>' +
                '<label><span>Deficiencies/findings</span><textarea data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="deficiencies_cited" placeholder="Short process-level summary">' + escapeFieldValue(row.deficiencies_cited || '') + '</textarea></label>' +
                '<label><span>POC/follow-up status</span><textarea data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="poc_due_followup" placeholder="Short follow-up status">' + escapeFieldValue(row.poc_due_followup || '') + '</textarea></label>' +
            '</div>' +
            '</div>';
    }

    function renderRepeaterSelect(key, index, column, value, options, placeholder) {
        value = fieldValue(value);
        return '<select data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="' + escapeHtml(column) + '"><option value="">' + escapeHtml(placeholder || 'Select') + '</option>' + options.map(function (option) {
            return '<option value="' + escapeFieldValue(option.value) + '"' + (value === option.value ? ' selected' : '') + '>' + escapeHtml(option.label) + '</option>';
        }).join('') + '</select>';
    }

    function stepThreeOptions(key) {
        var optionMap = {
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
        return optionMap[key] || [];
    }

    function renderStepThreeQuestion(question) {
        var value = onboardingQuestionValue(question);
        var required = question.is_required ? ' <span class="qn-required">*</span>' : '';
        var helpText = stepThreeHelpText(question);
        var help = helpText ? '<small>' + escapeHtml(helpText) + '</small>' : '';
        var tag = ['surgery_procedure_types', 'radiology_model', 'anesthesia_moderate_sedation_model'].indexOf(question.question_key) !== -1 ? 'div' : 'label';
        return '<' + tag + ' class="qn-question ' + questionLayoutClass(question) + '" data-question="' + escapeHtml(question.question_key) + '">' +
            '<span>' + escapeHtml(question.label) + required + '</span>' + renderStepThreeField(question, value) + help + renderStepThreeConditionalNote(question.question_key) + '</' + tag + '>';
    }

    function renderStepThreeField(question, value) {
        var key = question.question_key;
        if (['surgery_procedure_types', 'radiology_model', 'anesthesia_moderate_sedation_model'].indexOf(key) !== -1) {
            var placeholders = {
                surgery_procedure_types: 'Select procedure types',
                radiology_model: 'Select radiology services',
                anesthesia_moderate_sedation_model: 'Select anesthesia/sedation model'
            };
            return renderMultiselectField(key, value, stepThreeOptions(key), placeholders[key]);
        }
        if (['laboratory_model', 'pharmacy_model', 'blood_bank_model'].indexOf(key) !== -1) {
            var selectPlaceholders = {
                laboratory_model: 'Select laboratory model',
                pharmacy_model: 'Select pharmacy model',
                blood_bank_model: 'Select blood bank model'
            };
            return renderStepOneSelect(key, value, stepThreeOptions(key), selectPlaceholders[key]);
        }
        if (key === 'contracted_quality_monitoring_agreements') {
            return '<textarea data-onboarding-field="' + escapeHtml(key) + '" placeholder="Example: Radiology peer review agreement, contracted lab quality reports, telehealth specialist quality reporting.">' + escapeFieldValue(value) + '</textarea>';
        }
        return renderField(question, value);
    }

    function stepThreeHelpText(question) {
        var help = {
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
            user_role: [
                {value: 'chair_facilitator', label: 'Chair / facilitator'},
                {value: 'primary_presenter', label: 'Primary presenter'},
                {value: 'staff_support', label: 'Staff support'},
                {value: 'member', label: 'Member'},
                {value: 'contributor', label: 'Contributor'},
                {value: 'not_involved', label: 'Not involved'},
                {value: 'other', label: 'Other'}
            ],
            reports_to: [
                {value: 'qapi_committee', label: 'QAPI Committee'},
                {value: 'medical_executive_committee', label: 'Medical Executive Committee'},
                {value: 'board_quality_committee', label: 'Board Quality Committee'},
                {value: 'full_governing_board', label: 'Full Governing Board'},
                {value: 'governing_board', label: 'Governing Board'},
                {value: 'quality_safety_committee', label: 'Quality and Safety Committee'},
                {value: 'other', label: 'Other'}
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
            approval_required: [
                {value: 'none', label: 'None'},
                {value: 'ceo', label: 'CEO'},
                {value: 'cmo', label: 'CMO'},
                {value: 'cno', label: 'CNO'},
                {value: 'medical_staff', label: 'Medical Staff'},
                {value: 'board', label: 'Board'},
                {value: 'other', label: 'Other'}
            ],
            report_lead_time: [
                {value: 'one_week', label: '1 week'},
                {value: 'two_weeks', label: '2 weeks'},
                {value: 'three_weeks', label: '3 weeks'},
                {value: 'four_weeks', label: '4 weeks'},
                {value: 'six_weeks', label: '6 weeks'},
                {value: 'other', label: 'Other'},
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
            approval_requirements: [
                {value: 'ceo_approval', label: 'CEO approval'},
                {value: 'cmo_approval', label: 'CMO approval'},
                {value: 'cno_approval', label: 'CNO approval'},
                {value: 'medical_staff_approval', label: 'Medical Staff approval'},
                {value: 'board_approval', label: 'Board approval'},
                {value: 'no_approval_required', label: 'No approval required'},
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

    function renderStepFourQuestion(question) {
        var value = onboardingQuestionValue(question);
        var required = question.is_required ? ' <span class="qn-required">*</span>' : '';
        var tag = ['committee_list', 'reporting_obligations', 'mbqip_measure_set', 'approval_requirements'].indexOf(question.question_key) !== -1 ? 'div' : 'label';
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
            approval_requirements: 'Default approval requirements',
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
        if (key === 'approval_requirements') {
            return renderMultiselectField(key, value, stepFourOptions(key), 'Select approval requirements');
        }
        if (key === 'report_lead_time') {
            return renderStepOneSelect(key, value, stepFourOptions(key), 'Select default lead time');
        }
        if (key === 'backup_preparer') {
            return '<input type="text" data-onboarding-field="' + escapeHtml(key) + '" value="' + escapeFieldValue(value) + '" placeholder="Example: HIM Director, CNO, Infection Preventionist">';
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
            return '<option value="' + escapeFieldValue(option.value) + '"' + (value === option.value ? ' selected' : '') + '>' + escapeHtml(option.label) + '</option>';
        }).join('') + '</select>';
    }

    function isCriticalAccessContext() {
        return !!(state.onboarding && (state.onboarding.hospital_type === 'cah' || state.onboarding.hospital_type === 'critical_access_hospital'));
    }

    function renderCommitteeRepeater(key, value) {
        value = Array.isArray(value) ? value : [];
        var columns = ['committee_name', 'frequency_timing', 'user_role', 'reports_to', 'required_optional', 'minutes_owner', 'minutes_location', 'standing_agenda_items'];
        return '<div class="qn-repeater qn-card-repeater" data-repeater="' + escapeHtml(key) + '" data-repeater-style="committee-card" data-columns="' + escapeHtml(JSON.stringify(columns)) + '">' +
            (value.length ? '' : '<div class="qn-survey-empty"><strong>No committees added yet.</strong><span>Add the meetings where quality data is reviewed.</span></div>') +
            value.map(function (row, index) {
                return renderCommitteeRow(key, columns, row, index);
            }).join('') + '<button class="qn-button qn-button-small qn-add-survey-row" type="button" data-add-repeater="' + escapeHtml(key) + '"><span class="dashicons dashicons-plus-alt2"></span>Add committee</button></div>';
    }

    function renderCommitteeRow(key, columns, row, index) {
        row = row || {};
        return '<div class="qn-repeater-row qn-survey-history-row qn-flow-card">' +
            '<div class="qn-survey-card-header"><strong>Committee ' + (index + 1) + '</strong><button class="qn-icon-button qn-delete-survey-row" type="button" data-delete-repeater-row aria-label="Delete committee row"><span class="dashicons dashicons-trash"></span></button></div>' +
            '<div class="qn-survey-card-grid qn-flow-card-grid qn-committee-card-grid">' +
                '<label><span>Committee / meeting name</span>' + renderRepeaterSelect(key, index, 'committee_name', row.committee_name || '', stepFourOptions('committee_name'), 'Select committee') + '</label>' +
                '<label><span>Frequency and timing</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="frequency_timing" value="' + escapeFieldValue(row.frequency_timing || '') + '" placeholder="Example: Monthly, 2nd Tuesday, 1:00 PM"></label>' +
                '<label><span>Your role</span>' + renderRepeaterSelect(key, index, 'user_role', row.user_role || '', stepFourOptions('user_role'), 'Select role') + '</label>' +
                '<label><span>Reports to</span>' + renderRepeaterSelect(key, index, 'reports_to', row.reports_to || '', stepFourOptions('reports_to'), 'Select destination') + '</label>' +
                '<label><span>Required or optional</span>' + renderRepeaterSelect(key, index, 'required_optional', row.required_optional || '', stepFourOptions('required_optional'), 'Select status') + '</label>' +
                '<label><span>Minutes owner</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="minutes_owner" value="' + escapeFieldValue(row.minutes_owner || '') + '"></label>' +
                '<label class="qn-structured-wide"><span>Minutes location</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="minutes_location" value="' + escapeFieldValue(row.minutes_location || '') + '" placeholder="Example: SharePoint, policy system, board packet archive"></label>' +
                '<label class="qn-structured-wide"><span>Standing agenda items</span><textarea data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="standing_agenda_items">' + escapeFieldValue(row.standing_agenda_items || '') + '</textarea></label>' +
            '</div>' +
            '</div>';
    }

    function renderReportingRepeater(key, value) {
        value = Array.isArray(value) ? value : [];
        var columns = ['report_name', 'category', 'frequency', 'due_date_rule', 'due_date_details', 'due_dates', 'who_prepares', 'backup_preparer', 'submit_to_method', 'approval_required', 'prep_lead_time', 'payment_linked', 'event_triggered'];
        return '<div class="qn-repeater qn-card-repeater" data-repeater="' + escapeHtml(key) + '" data-repeater-style="report-card" data-columns="' + escapeHtml(JSON.stringify(columns)) + '">' +
            (value.length ? '' : '<div class="qn-survey-empty"><strong>No reporting obligations added yet.</strong><span>Add recurring or event-triggered reports.</span></div>') +
            value.map(function (row, index) {
                return renderReportingRow(key, columns, row, index);
            }).join('') + '<button class="qn-button qn-button-small qn-add-survey-row" type="button" data-add-repeater="' + escapeHtml(key) + '"><span class="dashicons dashicons-plus-alt2"></span>Add report</button></div>';
    }

    function renderReportingRow(key, columns, row, index) {
        row = row || {};
        return '<div class="qn-repeater-row qn-survey-history-row qn-flow-card">' +
            '<div class="qn-survey-card-header"><strong>Report ' + (index + 1) + '</strong><button class="qn-icon-button qn-delete-survey-row" type="button" data-delete-repeater-row aria-label="Delete report row"><span class="dashicons dashicons-trash"></span></button></div>' +
            '<div class="qn-survey-card-grid qn-flow-card-grid">' +
                '<label><span>Report name</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="report_name" value="' + escapeFieldValue(row.report_name || '') + '"></label>' +
                '<label><span>Category</span>' + renderRepeaterSelect(key, index, 'category', row.category || '', stepFourOptions('report_category'), 'Select category') + '</label>' +
                '<label><span>Frequency</span>' + renderRepeaterSelect(key, index, 'frequency', row.frequency || '', stepFourOptions('report_frequency'), 'Select frequency') + '</label>' +
                renderDueDateRuleFields(key, row, index) +
                '<label><span>Owner / preparer</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="who_prepares" value="' + escapeFieldValue(row.who_prepares || '') + '"></label>' +
                '<label><span>Backup preparer</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="backup_preparer" value="' + escapeFieldValue(row.backup_preparer || '') + '"></label>' +
                '<label><span>Submit to / method</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="submit_to_method" value="' + escapeFieldValue(row.submit_to_method || '') + '" placeholder="Example: QualityNet, NHSN, state portal, board packet"></label>' +
                '<label><span>Approval required</span>' + renderRepeaterSelect(key, index, 'approval_required', row.approval_required || '', stepFourOptions('approval_required'), 'Select approval') + '</label>' +
                '<label><span>Prep lead time</span>' + renderRepeaterSelect(key, index, 'prep_lead_time', row.prep_lead_time || '', stepFourOptions('report_lead_time'), 'Select lead time') + '</label>' +
                '<label><span>Payment-linked?</span>' + renderRepeaterSelect(key, index, 'payment_linked', row.payment_linked || '', stepFourOptions('yes_no_not_sure'), 'Select') + '</label>' +
                '<label><span>Event-triggered?</span>' + renderRepeaterSelect(key, index, 'event_triggered', row.event_triggered || '', stepFourOptions('yes_no_not_sure'), 'Select') + '</label>' +
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
            return '<option value="' + escapeFieldValue(option.value) + '"' + (value === option.value ? ' selected' : '') + '>' + escapeHtml(option.label) + '</option>';
        }).join('') + '</select>';
    }

    function stepFiveOptions(key) {
        var optionMap = {
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

    function renderStepFiveQuestion(question) {
        var value = onboardingQuestionValue(question);
        var key = question.question_key;
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
            return renderMultiselectField(key, value, stepFiveOptions(key), 'Select weakest monitoring areas');
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
            '<label><span>Last approved</span><input type="date" data-plan-field="' + escapeHtml(key) + '" data-plan-key="last_approved" value="' + escapeFieldValue(data.last_approved || '') + '"></label>' +
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
            return '<option value="' + escapeFieldValue(option.value) + '"' + (value === option.value ? ' selected' : '') + '>' + escapeHtml(option.label) + '</option>';
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
        if (isStepSixMeasureKey(key)) {
            return renderStepSixMeasureCard(question, value);
        }
        if (key === 'active_qi_projects') {
            return '<div class="qn-question qn-question-wide" data-question="' + escapeHtml(key) + '"><span>Active QI Projects</span>' + renderQiProjectsRepeater(key, value) + '</div>';
        }
        var label = stepSixDisplayLabel(question);
        return '<label class="qn-question ' + questionLayoutClass(question) + '" data-question="' + escapeHtml(key) + '">' +
            '<span>' + escapeHtml(label) + '</span>' + renderStepSixField(question, value) + stepSixHelpText(question) + '</label>';
    }

    function isStepSixMeasureKey(key) {
        return ['mbqip_upload', 'nhsn_hai_rates_upload', 'patient_experience_scores_upload', 'fall_rates_upload', 'pressure_injury_rates_upload', 'hand_hygiene_upload', 'other_dashboard_metrics'].indexOf(key) !== -1;
    }

    function stepSixDisplayLabel(question) {
        var labels = {
            mbqip_upload: 'MBQIP measures',
            nhsn_hai_rates_upload: 'NHSN HAI rates',
            patient_experience_scores_upload: 'Patient experience scores',
            fall_rates_upload: 'Fall rates',
            pressure_injury_rates_upload: 'Pressure injury rates',
            hand_hygiene_upload: 'Hand hygiene',
            other_dashboard_metrics: 'Other dashboard metrics',
            current_quality_dashboard: 'Current dashboard includes',
            data_source_currency: 'Data source currency',
            qi_framework: 'QI framework',
            project_charters_status: 'Project charters status',
            baseline_data_status: 'Baseline data status'
        };
        return labels[question.question_key] || question.label;
    }

    function renderStepSixField(question, value) {
        var key = question.question_key;
        if (key === 'current_quality_dashboard') {
            return '<textarea class="qn-compact-textarea" data-onboarding-field="' + escapeHtml(key) + '" placeholder="Example: MBQIP, infection surveillance, falls, pressure injuries, patient experience, hand hygiene.">' + escapeFieldValue(value) + '</textarea>';
        }
        if (key === 'data_source_currency') {
            return renderStepOneSelect(key, value, stepSixOptions(key), 'Select currency');
        }
        if (key === 'qi_framework' || key === 'project_charters_status' || key === 'baseline_data_status') {
            return renderStepOneSelect(key, value, stepSixOptions(key), 'Select status');
        }
        return renderField(question, value);
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
        var optionMap = {
            yes_no_not_sure: [
                {value: 'yes', label: 'Yes'},
                {value: 'no', label: 'No'},
                {value: 'not_sure', label: 'Not sure'}
            ],
            time_in_current_role: [
                {value: 'zero_to_6_months', label: '0-6 months'},
                {value: 'six_to_12_months', label: '6-12 months'},
                {value: 'one_to_3_years', label: '1-3 years'},
                {value: 'three_to_10_years', label: '3-10 years'},
                {value: 'ten_plus_years', label: '10+ years'},
                {value: 'not_sure', label: 'Not sure'}
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
        var tag = ['quality_certifications', 'learning_format_preference'].indexOf(key) !== -1 ? 'div' : 'label';
        return '<' + tag + ' class="qn-question ' + questionLayoutClass(question) + '" data-question="' + escapeHtml(key) + '">' +
            '<span>' + escapeHtml(stepSevenDisplayLabel(question)) + '</span>' + renderStepSevenField(question, value) + stepSevenHelpText(question) + '</' + tag + '>';
    }

    function renderStepSevenField(question, value) {
        var key = question.question_key;
        if (isStepSevenGoalKey(key)) {
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
            return 'New directors often benefit from the First 30 Days track, which helps locate key documents, map committees, and build the initial operating system.';
        }
        if (value === 'no') {
            return 'Experienced directors usually receive lighter refresher guidance.';
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
            return '<div class="qn-question qn-question-wide" data-question="' + escapeHtml(key) + '"><span>Backup visibility users</span>' + renderBackupUsersRepeater(key, value) + '</div>';
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
            update_preference: 'Update preference',
            auto_propose_task_adjustments: 'Auto-propose task adjustments',
            current_tools: 'Current tools',
            calendar_system: 'Calendar system',
            ehr_system: 'EHR system',
            incident_reporting_system: 'Incident reporting system',
            nhsn_qualitynet_access: 'NHSN / QualityNet access',
            reminder_lead_time: 'Reminder lead time',
            reminder_buffer_time: 'Reminder buffer time'
        };
        return labels[question.question_key] || question.label;
    }

    function stepEightPlaceholder(key) {
        var placeholders = {
            update_preference: 'Select update preference',
            auto_propose_task_adjustments: 'Select adjustment preference',
            calendar_system: 'Select calendar system',
            nhsn_qualitynet_access: 'Select access status',
            reminder_lead_time: 'Select lead time',
            reminder_buffer_time: 'Select buffer time'
        };
        return placeholders[key] || 'Select';
    }

    function stepEightHelpText(key) {
        if (key === 'auto_propose_task_adjustments') {
            return '<small>Scout will not automatically change your tasks without review unless your organization later enables that behavior.</small>';
        }
        return '';
    }

    function stepEightOptions(key) {
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
        var columns = ['name', 'role', 'email', 'access_level', 'notes'];
        return '<div class="qn-repeater qn-card-repeater qn-step8-backup-repeater" data-repeater="' + escapeHtml(key) + '" data-repeater-style="backup-user-card" data-columns="' + escapeHtml(JSON.stringify(columns)) + '">' +
            (value.length ? '' : '<div class="qn-survey-empty"><strong>No backup users added yet.</strong><span>Add backup visibility users such as the CNO, backup quality coordinator, or executive sponsor.</span></div>') +
            value.map(function (row, index) {
                return renderBackupUserRow(key, columns, row, index);
            }).join('') + '<button class="qn-button qn-button-small qn-add-survey-row" type="button" data-add-repeater="' + escapeHtml(key) + '"><span class="dashicons dashicons-plus-alt2"></span>Add backup user</button></div>';
    }

    function normalizeBackupUsersValue(value) {
        if (Array.isArray(value)) {
            return value;
        }
        if (value && typeof value === 'object') {
            return [value];
        }
        value = fieldValue(value);
        return value ? [{notes: value, legacy: value}] : [];
    }

    function renderBackupUserRow(key, columns, row, index) {
        row = row || {};
        return '<div class="qn-repeater-row qn-survey-history-row qn-step8-backup-row">' +
            '<div class="qn-survey-card-header"><strong>Backup user ' + (index + 1) + '</strong><button class="qn-icon-button qn-delete-survey-row" type="button" data-delete-repeater-row aria-label="Delete backup user"><span class="dashicons dashicons-trash"></span></button></div>' +
            '<div class="qn-survey-card-grid qn-step8-backup-grid">' +
                '<label><span>Name</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="name" value="' + escapeFieldValue(row.name || '') + '" placeholder="Name"></label>' +
                '<label><span>Role</span><input type="text" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="role" value="' + escapeFieldValue(row.role || '') + '" placeholder="Example: CNO"></label>' +
                '<label><span>Email</span><input type="email" data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="email" value="' + escapeFieldValue(row.email || '') + '" placeholder="email@example.org"></label>' +
                '<label><span>Access level</span>' + renderRepeaterSelect(key, index, 'access_level', row.access_level || '', stepEightOptions('backup_access_level'), 'Select access level') + '</label>' +
                '<label class="qn-structured-wide"><span>Notes</span><textarea data-repeater-row="' + escapeHtml(key) + '" data-index="' + index + '" data-column="notes" placeholder="Professional backup coverage notes only.">' + escapeFieldValue(row.notes || row.legacy || '') + '</textarea></label>' +
            '</div></div>';
    }

    function renderFinalReviewConfirmation(key, value) {
        return '<div class="qn-question qn-question-wide qn-step8-confirm-card" data-question="' + escapeHtml(key) + '">' +
            '<label class="qn-step8-confirm-label"><input type="checkbox" data-onboarding-field="' + escapeHtml(key) + '"' + (value ? ' checked' : '') + '><span>I confirm this setup is ready to submit and does not contain PHI, patient names, MRNs, incident narratives, peer-review details, or case-level details.</span></label>' +
            '<p>After submission, you can generate a Scout setup preview from this information. You can review and refine it before using it as your operating system.</p>' +
            '<small id="qn-final-review-message" class="qn-step8-confirm-message" hidden>Please confirm the final review statement before submitting.</small>' +
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
        if (question.question_key === 'independent_or_system') {
            return renderStepOneSelect(question.question_key, value, stepOneOptions(question.question_key), 'Select service model');
        }
        if (question.field_type === 'textarea') {
            return '<textarea data-onboarding-field="' + key + '"' + (placeholder ? ' placeholder="' + escapeHtml(placeholder) + '"' : '') + '>' + escapeFieldValue(value) + '</textarea>';
        }
        if (question.field_type === 'number') {
            return '<input type="number" min="0" step="1" inputmode="numeric" data-onboarding-field="' + key + '" value="' + escapeFieldValue(value) + '"' + (placeholder ? ' placeholder="' + escapeHtml(placeholder) + '"' : '') + '>';
        }
        if (question.field_type === 'date') {
            return '<input type="date" data-onboarding-field="' + key + '" value="' + escapeFieldValue(value) + '">';
        }
        if (question.field_type === 'select' || question.field_type === 'radio' || question.field_type === 'yes_no') {
            if (question.field_type === 'yes_no' && !options.length) {
                options = ['yes', 'no', 'not_sure'];
            }
            return '<select data-onboarding-field="' + key + '"><option value="">Select</option>' + options.map(function (option) {
                return '<option value="' + escapeFieldValue(option) + '"' + (fieldValue(value) === String(option) ? ' selected' : '') + '>' + escapeHtml(optionLabel(option)) + '</option>';
            }).join('') + '</select>';
        }
        if (question.field_type === 'multiselect') {
            value = Array.isArray(value) ? value : [];
            return '<select multiple data-onboarding-field="' + key + '">' + options.map(function (option) {
                return '<option value="' + escapeFieldValue(option) + '"' + (value.indexOf(option) !== -1 ? ' selected' : '') + '>' + escapeHtml(optionLabel(option)) + '</option>';
            }).join('') + '</select>';
        }
        if (question.field_type === 'checkbox') {
            return '<input type="checkbox" data-onboarding-field="' + key + '"' + (value ? ' checked' : '') + '>';
        }
        if (question.field_type === 'plan_status') {
            value = value && typeof value === 'object' ? value : {};
            return '<div class="qn-plan-status"><select data-plan-field="' + key + '" data-plan-key="exists"><option value="">Exists?</option><option value="yes"' + (value.exists === 'yes' ? ' selected' : '') + '>Exists</option><option value="no"' + (value.exists === 'no' ? ' selected' : '') + '>Does not exist</option></select><input type="date" data-plan-field="' + key + '" data-plan-key="last_approved" value="' + escapeFieldValue(value.last_approved || '') + '"><select data-plan-field="' + key + '" data-plan-key="board_approved"><option value="">Board approved?</option><option value="yes"' + (value.board_approved === 'yes' ? ' selected' : '') + '>Yes</option><option value="no"' + (value.board_approved === 'no' ? ' selected' : '') + '>No</option></select><input type="text" placeholder="Owner" data-plan-field="' + key + '" data-plan-key="owner" value="' + escapeFieldValue(value.owner || '') + '"></div>';
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

    function updateStepOneBedWarning() {
        var warning = document.getElementById('qn-step1-bed-warning');
        if (!warning) {
            return;
        }
        var licensed = Number(fieldValue((document.querySelector('[data-onboarding-field="licensed_beds"]') || {}).value));
        var acute = Number(fieldValue((document.querySelector('[data-onboarding-field="acute_beds"]') || {}).value));
        var swing = Number(fieldValue((document.querySelector('[data-onboarding-field="swing_beds"]') || {}).value));
        warning.hidden = !(licensed > 0 && acute + swing > licensed);
    }

    function updateStepTwoConditionalUI() {
        var status = document.querySelector('[data-onboarding-field="accreditation_status"]');
        var accreditor = document.querySelector('[data-onboarding-field="accrediting_body"]');
        var accreditorNote = document.getElementById('qn-accreditor-not-applicable-note');
        var jointNote = document.getElementById('qn-joint-commission-note');
        var poc = document.querySelector('[data-onboarding-field="open_plans_of_correction"]');
        var pocWarning = document.getElementById('qn-poc-process-warning');
        var statusValue = status ? status.value : '';
        var accreditorIsNotApplicable = statusValue === 'cms_state_survey_only' || statusValue === 'not_accredited';
        if (accreditorIsNotApplicable && accreditor && !accreditor.value) {
            accreditor.value = 'not_applicable';
        }
        if (accreditorNote) {
            accreditorNote.hidden = !accreditorIsNotApplicable;
        }
        if (jointNote) {
            jointNote.hidden = !(accreditor && accreditor.value === 'joint_commission');
        }
        if (pocWarning) {
            pocWarning.hidden = !(poc && poc.value === 'yes');
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

        var bloodBank = document.querySelector('[data-onboarding-field="blood_bank_model"]');
        var transfusions = document.querySelector('[data-onboarding-field="transfusions_per_year"]');
        var bloodNote = document.getElementById('qn-blood-not-applicable-note');
        var noBlood = bloodBank && bloodBank.value === 'no_blood_products_on_site';
        var zeroTransfusions = !transfusions || transfusions.value === '' || Number(transfusions.value) === 0;
        if (bloodNote) {
            bloodNote.hidden = !(noBlood && zeroTransfusions);
        }

        var visiting = document.querySelector('[data-onboarding-field="visiting_specialists"]');
        var contractedNote = document.getElementById('qn-contracted-monitoring-note');
        if (contractedNote) {
            contractedNote.hidden = !(visiting && visiting.value === 'yes');
        }
    }

    function updateStepFourConditionalUI() {
        var boardTiming = document.querySelector('[data-structured-field="board_agenda_timing"][data-structured-key="timing"]');
        var boardDetails = document.querySelector('[data-board-agenda-details]');
        if (boardDetails) {
            boardDetails.hidden = !(boardTiming && boardTiming.value === 'other');
        }
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
                title.textContent = prefix + ' ' + (index + 1);
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
            item[field.getAttribute('data-column') || 'note'] = field.value;
        });
        row.querySelectorAll('[data-repeater-detail]').forEach(function (field) {
            setNestedValue(item, field.getAttribute('data-detail-path'), field.value);
        });
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
        document.querySelectorAll('[data-onboarding-field]').forEach(function (field) {
            var key = field.getAttribute('data-onboarding-field');
            if (field.type === 'checkbox') {
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
                answers[key] = field.value;
            }
        });
        document.querySelectorAll('[data-plan-field]').forEach(function (field) {
            var key = field.getAttribute('data-plan-field');
            var planKey = field.getAttribute('data-plan-key');
            answers[key] = answers[key] || {};
            answers[key][planKey] = field.value;
        });
        document.querySelectorAll('[data-structured-field]').forEach(function (field) {
            var key = field.getAttribute('data-structured-field');
            var dataKey = field.getAttribute('data-structured-key');
            answers[key] = answers[key] || {};
            answers[key][dataKey] = field.value;
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
                    item[field.getAttribute('data-column') || 'note'] = field.value;
                });
                row.querySelectorAll('[data-repeater-detail]').forEach(function (field) {
                    setNestedValue(item, field.getAttribute('data-detail-path'), field.value);
                });
                answers[key].push(item);
            });
        });
        return answers;
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
        var restoreButton = setButtonLoading(trigger, advance ? 'Saving...' : 'Saving...');
        setText('#qn-onboarding-message', 'Saving...');
        setOnboardingSaveStatus('saving', 'Saving...');
        return api('/onboarding/save', {
            method: 'POST',
            timeout: options.timeout || 60000,
            body: {
                organization_id: state.onboardingOrganizationId,
                step_key: step.section_key,
                answers: collectOnboardingAnswers()
            }
        }).then(function () {
            setText('#qn-onboarding-message', 'Saved.');
            setOnboardingSaveStatus('saved', 'Saved');
            showToast('Onboarding saved.', 'success');
            if (advance && state.onboardingIndex < state.onboarding.steps.length - 1) {
                state.onboardingIndex++;
            }
            return loadOnboarding(state.onboardingOrganizationId, {showLoading: false});
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
        }).then(function () {
            setText('#qn-onboarding-message', 'Saved.');
            setOnboardingSaveStatus('saved', 'Saved');
        }).catch(function (error) {
            setText('#qn-onboarding-message', error.message);
            setOnboardingSaveStatus('error', 'Could not save');
        }).finally(function () {
            state.onboardingBackgroundSaving = false;
        });
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
            if (canEditOnboardingStep(previousStep)) {
                saveOnboardingStepInBackground(previousStep, answers);
            }
        }, 0);
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
            setText('#qn-onboarding-message', 'Please confirm the final review statement before submitting.');
            showToast('Please confirm the final review statement before submitting.', 'warning');
            finalConfirmation.focus();
            return;
        }
        state.onboardingSubmitting = true;
        var restoreButton = setButtonLoading(trigger, 'Submitting...');
        setText('#qn-onboarding-message', 'Submitting final setup...');
        saveOnboardingStep(false, null, {rejectOnError: true, timeout: 60000}).then(function () {
            setText('#qn-onboarding-message', 'Submitting final setup...');
            return api('/onboarding/submit', {method: 'POST', timeout: 60000, body: {organization_id: state.onboardingOrganizationId}});
        }).then(function (result) {
            var completionMessage = 'You\u2019re all set - Hospital Setup has been submitted. Scout can now build your hospital operating-system preview. You can return anytime to update this setup, and QualiNav will prompt an annual review of this information.';
            setText('#qn-onboarding-message', completionMessage);
            showToast('Hospital Setup submitted. Scout can now build your workspace preview.', 'success');
            return loadOnboarding(state.onboardingOrganizationId, {showLoading: false}).then(function () {
                return loadScoutRuns(state.onboardingOrganizationId);
            });
        }).catch(function (error) {
            var message = friendlyApiErrorMessage(error, 'Final setup could not be submitted. Please try again or contact support.');
            setText('#qn-onboarding-message', message);
            setOnboardingSaveStatus('error', 'Submit failed');
            showToast(message, 'warning');
        }).finally(function () {
            state.onboardingSubmitting = false;
            restoreButton();
            renderOnboarding();
        });
    }

    function closeInviteModal() {
        var modal = document.getElementById('qn-invite-modal');
        var org = document.getElementById('qn-invite-organization');
        var role = document.getElementById('qn-invite-role');
        var orgField = document.getElementById('qn-invite-organization-field');
        var roleField = document.getElementById('qn-invite-role-field');
        var fixedContext = document.getElementById('qn-invite-fixed-context');
        if (org) {
            org.disabled = false;
            syncSearchableSelect(org);
        }
        if (role) {
            role.disabled = false;
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
            if (!event.target.closest('.qn-searchable-select')) {
                closeSearchableSelects();
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
            var sectionTarget = event.target.closest('[data-section-target]');
            if (sectionTarget) {
                event.preventDefault();
                activateSection(sectionTarget.getAttribute('data-section-target'), true);
                return;
            }
            var menuToggle = event.target.closest('[data-action-menu-toggle]');
            if (menuToggle) {
                event.preventDefault();
                var menu = menuToggle.parentNode.querySelector('.qn-action-menu-list');
                var willOpen = menu ? menu.hidden : false;
                closeActionMenus(menu);
                if (menu) {
                    menu.hidden = !willOpen;
                    menuToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
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
                updateUserStatus(menuStatus.getAttribute('data-update-user-status'), menuStatus.getAttribute('data-status'), menuStatus.getAttribute('data-context'), menuStatus);
                closeActionMenus();
                return;
            }
            if (resend) {
                resendOrRevokeInvite('resend', resend.getAttribute('data-resend-invite'), resend.getAttribute('data-context'), resend);
                return;
            }
            if (revoke) {
                resendOrRevokeInvite('revoke', revoke.getAttribute('data-revoke-invite'), revoke.getAttribute('data-context'), revoke);
                return;
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeActionMenus();
                closeSearchableSelects();
                document.querySelectorAll('.qn-modal:not([hidden])').forEach(function (modal) {
                    modal.hidden = true;
                });
            }
        });
        document.addEventListener('change', function (event) {
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
                updateUserStatus(status.getAttribute('data-user-status'), status.value, status.getAttribute('data-context'), status);
            }
            if (menuRole) {
                updateUserRole(menuRole.getAttribute('data-update-user-role'), menuRole.getAttribute('data-role'), menuRole.getAttribute('data-context'), menuRole);
                closeActionMenus();
                return;
            }
            if (menuStatus) {
                updateUserStatus(menuStatus.getAttribute('data-update-user-status'), menuStatus.getAttribute('data-status'), menuStatus.getAttribute('data-context'), menuStatus);
                closeActionMenus();
                return;
            }
            if (event.target.matches('#qn-invite-role')) {
                updateInviteRoleDescription();
            }
            if (event.target.matches('[data-onboarding-field], [data-plan-field], [data-repeater-row], [data-repeater-detail], [data-structured-field], [data-checklist-field]')) {
                if (event.target.type === 'number' && event.target.value !== '') {
                    event.target.value = String(Math.max(0, Math.floor(Number(event.target.value) || 0)));
                }
                updateStepOneBedWarning();
                updateStepTwoConditionalUI();
                updateStepThreeConditionalUI();
                updateStepFourConditionalUI();
                updateStepSevenConditionalUI();
                if (event.target.matches('[data-checklist-field]')) {
                    updateMultiselectUI(event.target.closest('.qn-multiselect'));
                }
                if (event.target.matches('[data-repeater-row]')) {
                    refreshDueDatePanel(event.target);
                }
                setOnboardingSaveStatus('unsaved', 'Unsaved changes');
                window.clearTimeout(state.autosaveTimer);
                state.autosaveTimer = window.setTimeout(function () { saveOnboardingStep(false); }, 900);
            }
        });
        document.addEventListener('input', function (event) {
            if (event.target.matches('[data-onboarding-field], [data-plan-field], [data-repeater-row], [data-repeater-detail], [data-structured-field], [data-checklist-field]')) {
                updateStepOneBedWarning();
                updateStepTwoConditionalUI();
                updateStepThreeConditionalUI();
                updateStepFourConditionalUI();
                updateStepSevenConditionalUI();
                setOnboardingSaveStatus('unsaved', 'Unsaved changes');
                window.clearTimeout(state.autosaveTimer);
                state.autosaveTimer = window.setTimeout(function () { saveOnboardingStep(false); }, 900);
            }
        });
        document.addEventListener('click', function (event) {
            var stepButton = event.target.closest('[data-onboarding-step]');
            var addRepeater = event.target.closest('[data-add-repeater]');
            var multiselectTrigger = event.target.closest('[data-multiselect-trigger]');
            var multiselectRemove = event.target.closest('[data-multiselect-remove]');
            var passwordToggle = event.target.closest('[data-toggle-password]');
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
                state.autosaveTimer = window.setTimeout(function () { saveOnboardingStep(false); }, 900);
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
                if (row) {
                    var repeaterOwner = row.closest('[data-repeater]');
                    row.remove();
                    if (repeaterOwner && !repeaterOwner.querySelector('.qn-repeater-row')) {
                        var style = repeaterOwner.getAttribute('data-repeater-style');
                        var emptyText = '<div class="qn-survey-empty"><strong>No survey history added yet.</strong><span>Add prior surveys if available; you can also skip this for now.</span></div>';
                        if (style === 'committee-card') {
                            emptyText = '<div class="qn-survey-empty"><strong>No committees added yet.</strong><span>Add the meetings where quality data is reviewed.</span></div>';
                        }
                        if (style === 'report-card') {
                            emptyText = '<div class="qn-survey-empty"><strong>No reporting obligations added yet.</strong><span>Add recurring or event-triggered reports.</span></div>';
                        }
                        if (style === 'backup-user-card') {
                            emptyText = '<div class="qn-survey-empty"><strong>No backup users added yet.</strong><span>Add backup visibility users such as the CNO, backup quality coordinator, or executive sponsor.</span></div>';
                        }
                        repeaterOwner.insertAdjacentHTML('afterbegin', emptyText);
                    }
                    refreshRepeaterCardLabels(repeaterOwner);
                    setOnboardingSaveStatus('unsaved', 'Unsaved changes');
                    window.clearTimeout(state.autosaveTimer);
                    state.autosaveTimer = window.setTimeout(function () { saveOnboardingStep(false); }, 900);
                }
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
        if (!window.confirm('Deactivate ' + name + '? Hospitals will remain available.')) {
            return;
        }
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
