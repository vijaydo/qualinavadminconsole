<?php
    ob_start();
    ?>
    <div class="dm-layout" id="dmApp"
         data-delete-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
         data-delete-nonce="<?php echo esc_attr(wp_create_nonce('mydata_delete_report')); ?>"
         data-update-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
         data-update-nonce="<?php echo esc_attr(wp_create_nonce('dm_update_report_metrics')); ?>"
         data-upload-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
         data-upload-nonce="<?php echo esc_attr(wp_create_nonce('dm_upload_folder_file')); ?>">
        <aside class="dm-side">
            <label class="dm-search-wrap">
                <input type="search" id="dmSearch" class="dm-search" placeholder="Search sessions...">
            </label>
            <div class="dm-nav-group">
                <h3><?php echo esc_html($scope_label); ?></h3>
                <div class="dm-folder-tree"></div>
            </div>

        </aside>

        <main class="dm-main">
            <header class="dm-main-head">
                <h2>Quality Dashboard Reports</h2>
                <p>Manage real saved QAPI data and drill into report metrics by category. Scope: <?php echo esc_html($scope_label); ?>.</p>
            </header>

            <section class="dm-list-panel">
                <div class="dm-list-head">
                    <h3 id="dmListTitle">Organization Data</h3>
                    <span id="dmListCount">4 folders</span>
                </div>
                <div class="dm-breadcrumb" id="dmBreadcrumb"></div>
                <div class="dm-list" id="dmReportList"></div>
                <div class="dm-empty" id="dmListEmpty" style="display:none;">No reports match current filters.</div>
            </section>
        </main>

    </div>

    <style id="dm-live-override">
    #dmApp, #dmApp * {
        font-family: var(--scout-font-sans, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif) !important;
    }
    #dmApp.dm-layout {
        display: grid !important;
        grid-template-columns: 304px minmax(0, 1fr) !important;
        gap: 0 !important;
        min-height: calc(100vh - 170px) !important;
        align-items: stretch !important;
    }
    #dmApp .dm-side {
        background: #ffffff !important;
        border-right: 1px solid #e5e7eb !important;
        padding: 0 !important;
        min-width: 0 !important;
    }
    #dmApp .dm-main {
        min-width: 0 !important;
        padding: 18px 20px !important;
    }
    #dmApp .dm-main-head {
        margin: 0 0 14px !important;
    }
    #dmApp .dm-main-head h2 {
        margin: 0 !important;
        color: #0f2740 !important;
        font-size: 34px !important;
        line-height: 1.08 !important;
        font-weight: 800 !important;
        letter-spacing: -0.02em !important;
    }
    #dmApp .dm-main-head p {
        margin: 8px 0 0 !important;
        color: #627489 !important;
        font-size: 14px !important;
        line-height: 1.45 !important;
    }
    #dmApp .dm-list-head {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 12px !important;
        padding: 14px 16px !important;
    }
    #dmApp .dm-list-head h3 {
        margin: 0 !important;
        color: #0f2740 !important;
        font-size: 20px !important;
        font-weight: 800 !important;
    }
    #dmApp .dm-list-head span {
        color: #6c7c8e !important;
        font-size: 13px !important;
        font-weight: 600 !important;
    }
    #dmApp .dm-empty {
        margin: 12px !important;
        padding: 12px 14px !important;
        border: 1px dashed #c9d7e5 !important;
        border-radius: 12px !important;
        color: #687b90 !important;
        background: #fbfdff !important;
    }
    #dmApp .dm-nav-group h3 {
        display: none !important;
    }
    #dmApp .dm-search-wrap {
        display: block !important;
        padding: 16px !important;
        margin: 0 !important;
        border-bottom: 1px solid #e5e7eb !important;
    }
    #dmApp .dm-search {
        height: 40px !important;
        width: 100% !important;
        padding: 10px 36px 10px 12px !important;
        border: 1px solid #d1d5db !important;
        border-radius: 8px !important;
        background-color: #fff !important;
        color: #4b5563 !important;
        font-size: 14px !important;
        font-weight: 400 !important;
    }
    #dmApp .dm-search::placeholder {
        color: #6b7280 !important;
    }
    #dmApp .dm-folder-tree {
        background: transparent !important;
        border: 0 !important;
        padding: 8px !important;
    }
    #dmApp .dm-subfolder-list {
        margin-left: 14px !important;
        padding-left: 0 !important;
        border-left: 0 !important;
    }
    #dmApp .dm-subfolder-list {
        margin-left: 16px !important;
        padding-left: 8px !important;
        border-left: 1px solid #e7edf3 !important;
    }
    #dmApp .dm-nav-item {
        min-height: 34px !important;
        margin: 2px 0 !important;
        padding: 8px 10px !important;
        border-radius: 10px !important;
        border: 1px solid transparent !important;
        background: transparent !important;
        color: #4b627a !important;
        font-size: 12px !important;
        font-weight: 500 !important;
    }
    #dmApp .dm-nav-item > span:last-child {
        color: #9ca3af !important;
        font-size: 12px !important;
        font-weight: 500 !important;
    }
    #dmApp .dm-nav-item:hover {
        background: #f3f4f6 !important;
    }
    #dmApp .dm-nav-item.is-active {
        background: #dce8f1 !important;
        border-color: #dce8f1 !important;
        color: #111827 !important;
        font-weight: 600 !important;
    }
    #dmApp .dm-nav-item i {
        display: inline-block !important;
        width: 12px !important;
        margin-right: 8px !important;
        color: #9ca3af !important;
        font-size: 12px !important;
        line-height: 1 !important;
        vertical-align: -1px !important;
    }
    #dmApp .dm-nav-item > span:first-child {
        position: relative !important;
        padding-left: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
    }
    #dmApp .dm-nav-item > span:first-child::before,
    #dmApp .dm-nav-item > span:first-child::after {
        content: none !important;
        display: none !important;
    }
    #dmApp .dm-nav-folder-icon {
        position: relative !important;
        display: inline-block !important;
        width: 10px !important;
        height: 7px !important;
        margin-right: 8px !important;
        border-radius: 2px !important;
        background: #9ca3af !important;
        flex: 0 0 auto !important;
    }
    #dmApp .dm-nav-folder-icon::before {
        content: "" !important;
        position: absolute !important;
        left: 0 !important;
        top: -2px !important;
        width: 5px !important;
        height: 3px !important;
        border-radius: 2px 2px 0 0 !important;
        background: #9ca3af !important;
    }
    #dmApp .dm-list-panel {
        background: #ffffff !important;
        border: 1px solid #e5ebf2 !important;
        border-radius: 14px !important;
        box-shadow: 0 2px 10px rgba(15,43,68,0.06) !important;
    }
    #dmApp .dm-folder-card {
        background: #ffffff !important;
        border: 1px solid #d6e1ec !important;
        box-shadow: 0 2px 8px rgba(15,43,68,0.06) !important;
    }
    #dmApp .dm-folder-card-body strong {
        color: #173d5b !important;
        font-weight: 700 !important;
    }
    #dmApp .dm-folder-card-body p {
        color: #5f768d !important;
    }

    /* Final requested alignment:
       - left nav same as Scout reference
       - reduce borders in folder cards area */
    #dmApp .dm-side {
        background: #ffffff !important;
        border-right: 1px solid #e4e9ef !important;
    }
    #dmApp .dm-folder-tree {
        padding: 8px !important;
    }
    #dmApp .dm-subfolder-list {
        margin-left: 18px !important;
    }
    #dmApp .dm-nav-item {
        color: #4e647c !important;
        font-size: 12px !important;
        font-weight: 500 !important;
        border-radius: 12px !important;
        border: 0 !important;
        min-height: 34px !important;
    }
    #dmApp .dm-nav-item > span:last-child {
        color: #9caabc !important;
        font-size: 12px !important;
    }
    #dmApp .dm-nav-item:hover {
        background: #f1f5f8 !important;
    }
    #dmApp .dm-nav-item.is-active {
        background: #dbe8f2 !important;
        color: #102f49 !important;
        font-weight: 600 !important;
    }
    #dmApp .dm-nav-item i {
        display: inline-block !important;
        width: 12px !important;
        margin-right: 8px !important;
        color: #9ca3af !important;
        font-size: 12px !important;
        line-height: 1 !important;
        vertical-align: -1px !important;
    }
    #dmApp .dm-nav-item > span:first-child {
        position: relative !important;
        padding-left: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
    }
    #dmApp .dm-nav-item > span:first-child::before,
    #dmApp .dm-nav-item > span:first-child::after {
        content: none !important;
        display: none !important;
    }
    #dmApp .dm-nav-folder-icon {
        position: relative !important;
        display: inline-block !important;
        width: 10px !important;
        height: 7px !important;
        margin-right: 8px !important;
        border-radius: 2px !important;
        background: #9ca3af !important;
        flex: 0 0 auto !important;
    }
    #dmApp .dm-nav-folder-icon::before {
        content: "" !important;
        position: absolute !important;
        left: 0 !important;
        top: -2px !important;
        width: 5px !important;
        height: 3px !important;
        border-radius: 2px 2px 0 0 !important;
        background: #9ca3af !important;
    }

    #dmApp .dm-list-panel {
        border: 0 !important;
        box-shadow: none !important;
        background: transparent !important;
    }
    #dmApp .dm-breadcrumb {
        border-bottom: 0 !important;
        background: transparent !important;
        padding: 10px 14px !important;
    }
    #dmApp .dm-list.is-folder-mode {
        display: grid !important;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)) !important;
        padding: 12px !important;
        gap: 12px !important;
    }
    #dmApp .dm-folder-card {
        border: 0 !important;
        background: #ffffff !important;
        box-shadow: 0 2px 8px rgba(15,43,68,0.06) !important;
        border-radius: 14px !important;
    }
    #dmApp .dm-folder-card:hover {
        box-shadow: 0 6px 14px rgba(15,43,68,0.10) !important;
        transform: translateY(-1px) !important;
    }
    #dmApp .dm-folder-card-icon {
        border: 0 !important;
        background: #edf3f9 !important;
    }
    #dmApp .dm-folder-badge {
        border: 0 !important;
        background: #eef4fa !important;
    }
    /* Card refinement: cleaner hierarchy, consistent rhythm, softer borders */
    #dmApp .dm-list.is-folder-mode {
        padding: 14px !important;
        gap: 14px !important;
    }
    #dmApp .dm-folder-card {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        min-height: 194px !important;
        padding: 14px !important;
        border: 1px solid #dfe8f1 !important;
        border-radius: 16px !important;
        background: #ffffff !important;
        box-shadow: 0 1px 4px rgba(15,43,68,0.05) !important;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease !important;
    }
    #dmApp .dm-folder-card:hover {
        transform: translateY(-2px) !important;
        border-color: #b7cadf !important;
        box-shadow: 0 10px 24px rgba(15,43,68,0.10) !important;
    }
    #dmApp .dm-folder-card-icon {
        width: 36px !important;
        height: 36px !important;
        border-radius: 10px !important;
        background: #edf3f9 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        margin-bottom: 12px !important;
    }
    #dmApp .dm-folder-card-body {
        display: flex !important;
        flex-direction: column !important;
        flex: 1 1 auto !important;
        min-height: 122px !important;
    }
    #dmApp .dm-folder-card-body strong {
        font-size: 20px !important;
        line-height: 1.2 !important;
        font-weight: 700 !important;
        letter-spacing: -0.01em !important;
        color: #173d5b !important;
        margin-bottom: 8px !important;
    }
    #dmApp .dm-folder-card-body p {
        font-size: 14px !important;
        line-height: 1.4 !important;
        color: #5f768d !important;
        margin: 0 0 10px !important;
    }
    #dmApp .dm-folder-badge {
        margin-top: auto !important;
        align-self: flex-start !important;
        padding: 5px 10px !important;
        border-radius: 999px !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        letter-spacing: 0 !important;
        color: #3f5f7b !important;
        background: #eef4fa !important;
        border: 1px solid #d7e4f0 !important;
        line-height: 1 !important;
    }
    #dmApp .dm-badge-icon {
        position: relative !important;
        display: inline-block !important;
        width: 11px !important;
        height: 8px !important;
        margin-right: 6px !important;
        vertical-align: -1px !important;
    }
    #dmApp .dm-badge-icon::before {
        content: "" !important;
        position: absolute !important;
        left: 0 !important;
        top: 2px !important;
        width: 11px !important;
        height: 6px !important;
        border-radius: 2px !important;
        background: #5f7b96 !important;
    }
    #dmApp .dm-badge-icon::after {
        content: "" !important;
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 5px !important;
        height: 3px !important;
        border-radius: 2px 2px 0 0 !important;
        background: #5f7b96 !important;
    }
    /* Prevent preview/file area from being clipped by legacy max-height/overflow rules */
    #dmApp .dm-main {
        overflow: visible !important;
    }
    #dmApp .dm-list-panel {
        overflow: visible !important;
    }
    #dmApp .dm-list {
        max-height: calc(100vh - 360px) !important;
        overflow-y: auto !important;
    }
    @media (max-width: 1240px) {
        #dmApp.dm-layout {
            grid-template-columns: 280px minmax(0, 1fr) !important;
        }
    }
    @media (max-width: 860px) {
        #dmApp.dm-layout {
            grid-template-columns: 1fr !important;
        }
        #dmApp .dm-side {
            border-right: 0 !important;
            border-bottom: 1px solid #e5e7eb !important;
        }
        #dmApp .dm-main {
            padding: 16px !important;
        }
    }
    #dmApp .dm-list {
        max-height: none !important;
        overflow: visible !important;
    }
    #dmApp .dm-file-pane,
    #dmApp .dm-data-preview,
    #dmApp .dm-data-preview-chart {
        overflow: visible !important;
    }
    #dmApp .dm-data-preview-chart svg {
        display: block !important;
        width: 100% !important;
        height: auto !important;
    }
    </style>

        <script>
    (function() {
        const app = document.getElementById('dmApp');
        if (!app) return;

        const searchInput = app.querySelector('#dmSearch');
        const list = app.querySelector('#dmReportList');
        const empty = app.querySelector('#dmListEmpty');
        const listTitle = app.querySelector('#dmListTitle');
        const listCount = app.querySelector('#dmListCount');
        const breadcrumb = app.querySelector('#dmBreadcrumb');
        const totalEl = app.querySelector('#dmTotalReports');
        const sideTree = app.querySelector('.dm-folder-tree');

        const folderFiles = <?php echo wp_json_encode($folder_files); ?> || {};

        const folders = [
            { id: 'all', parent: null, label: 'Organization Data', description: 'Organization-scoped QAPI data explorer.' },
            { id: 'board-report', parent: 'all', label: 'Board Report', description: 'Board-level quality and performance reports.' },
            { id: 'quality-committee', parent: 'all', label: 'Quality Committee', description: 'Committee reporting and MBQIP materials.' },
            { id: 'quality-dashboard', parent: 'all', label: 'Quality Dashboard', description: 'Metrics, dashboards, and tracking.' },
            { id: 'qapi-report', parent: 'all', label: 'QAPI Report', description: 'Program reports and documentation.' },

            { id: 'mbqip', parent: 'quality-committee', label: 'MBQIP', description: 'Medicare Beneficiary Quality Improvement Project.' },
            { id: 'swing-bed-quality', parent: 'quality-committee', label: 'Swing Bed Quality', description: 'Swing bed quality indicator files.' },
            { id: 'quality-and-patient-safety-data', parent: 'quality-committee', label: 'Quality and Patient Safety Data', description: 'Patient safety and quality source files.' },
            { id: 'regulatory-and-survey', parent: 'quality-committee', label: 'Regulatory and Survey', description: 'Regulatory and survey documents.' },

            { id: 'global-measures', parent: 'mbqip', label: 'Global Measures', description: 'Core global quality measures.' },
            { id: 'patient-safety', parent: 'mbqip', label: 'Patient Safety', description: 'Patient safety measure files.' },
            { id: 'patient-experience', parent: 'mbqip', label: 'Patient Experience', description: 'Patient experience metrics files.' },
            { id: 'care-coordination', parent: 'mbqip', label: 'Care Coordination', description: 'Care coordination measures.' },
            { id: 'emergency-department', parent: 'mbqip', label: 'Emergency Department', description: 'Emergency department transfer communication.' },

            { id: 'cah-quality-infrastructure', parent: 'global-measures', label: 'CAH Quality Infrastructure', description: 'Leaf folder for files.' },
            { id: 'hcp-imm-3', parent: 'patient-safety', label: 'HCP/IMM-3', description: 'Leaf folder for files.' },
            { id: 'antibiotic-stewardship', parent: 'patient-safety', label: 'Antibiotic Stewardship', description: 'Leaf folder for files.' },
            { id: 'safe-use-of-opioids-ecqm', parent: 'patient-safety', label: 'Safe Use of Opioids (eCQM)', description: 'Leaf folder for files.' },
            { id: 'hcahps-comm-with-nurses', parent: 'patient-experience', label: 'HCAHPS Comm with Nurses', description: 'Leaf folder for files.' },
            { id: 'hcahps-comm-with-docs', parent: 'patient-experience', label: 'HCAHPS Comm with Docs', description: 'Leaf folder for files.' },
            { id: 'hcahps-restfulness', parent: 'patient-experience', label: 'HCAHPS Restfulness', description: 'Leaf folder for files.' },
            { id: 'hcahps-care-coordination', parent: 'patient-experience', label: 'HCAHPS Care Coordination', description: 'Leaf folder for files.' },
            { id: 'hcahps-responsiveness', parent: 'patient-experience', label: 'HCAHPS Responsiveness', description: 'Leaf folder for files.' },
            { id: 'hcahps-medicine-comm', parent: 'patient-experience', label: 'HCAHPS Medicine Comm', description: 'Leaf folder for files.' },
            { id: 'hcahps-cleanliness', parent: 'patient-experience', label: 'HCAHPS Cleanliness', description: 'Leaf folder for files.' },
            { id: 'hcahps-discharge', parent: 'patient-experience', label: 'HCAHPS Discharge', description: 'Leaf folder for files.' },
            { id: 'hcahps-symptoms', parent: 'patient-experience', label: 'HCAHPS Symptoms', description: 'Leaf folder for files.' },
            { id: 'hcahps-overall-rating', parent: 'patient-experience', label: 'HCAHPS Overall Rating', description: 'Leaf folder for files.' },
            { id: 'hcahps-willingness-to-rec', parent: 'patient-experience', label: 'HCAHPS Willingness to Rec', description: 'Leaf folder for files.' },
            { id: 'readmissions', parent: 'care-coordination', label: 'Readmissions', description: 'Leaf folder for files.' },
            { id: 'edtc', parent: 'emergency-department', label: 'Emergency Department Transfer Communication (EDTC)', description: 'Leaf folder for files.' },
            { id: 'median-time-from-ed', parent: 'emergency-department', label: 'Median Time from ED', description: 'Leaf folder for files.' },
            { id: 'op-22-left-without-being-seen', parent: 'emergency-department', label: 'OP-22 Left Without Being Seen', description: 'Leaf folder for files.' }
        ];

        const folderMetricDefinitions = {
            'cah-quality-infrastructure': ['CAH global measure'],
            'hcp-imm-3': ['HCP IMM-3'],
            'antibiotic-stewardship': ['Antibiotic Stewardship Implement'],
            'safe-use-of-opioids-ecqm': ['Safe Use of Opioids - Concurrent'],
            'hcahps-comm-with-nurses': ['HCAHPS Comm with Nurses'],
            'hcahps-comm-with-docs': ['HCAHPS Comm with Docs'],
            'hcahps-restfulness': ['HCAHPS Restfulness'],
            'hcahps-care-coordination': ['HCAHPS Care Coordination'],
            'hcahps-responsiveness': ['HCAHPS Responsiveness'],
            'hcahps-medicine-comm': ['HCAHPS Medicine Comm'],
            'hcahps-cleanliness': ['HCAHPS Cleanliness'],
            'hcahps-discharge': ['HCAHPS Discharge'],
            'hcahps-symptoms': ['HCAHPS Symptoms'],
            'hcahps-overall-rating': ['HCAHPS Overall Rating'],
            'hcahps-willingness-to-rec': ['HCAHPS Willingness to Rec'],
            'readmissions': ['Hybrid Hospital-Wide Readmission'],
            'edtc': ['EDTC'],
            'median-time-from-ed': ['Median Time from ED'],
            'op-22-left-without-being-seen': ['OP-22 Left Without Being Seen']
        };

        const esc = (value) => String(value || '').replace(/[&<>"']/g, (ch) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[ch]));

        const byId = {};
        folders.forEach((folder) => {
            byId[folder.id] = { ...folder, children: [] };
        });
        folders.forEach((folder) => {
            if (!folder.parent) return;
            if (byId[folder.parent]) {
                byId[folder.parent].children.push(folder.id);
            }
        });

        let currentFolder = 'all';
        let pendingReplaceIndex = -1;
        let previewState = null;

        const getFiles = (folderId) => {
            const rows = folderFiles[folderId];
            return Array.isArray(rows) ? rows : [];
        };

        const getPath = (folderId) => {
            const path = [];
            let cursor = byId[folderId] ? folderId : 'all';
            while (cursor && byId[cursor]) {
                path.unshift(cursor);
                cursor = byId[cursor].parent;
            }
            return path;
        };

        const fileCount = (folderId) => getFiles(folderId).length;
        const getExpectedMetrics = (folderId) => Array.isArray(folderMetricDefinitions[folderId]) ? folderMetricDefinitions[folderId] : [];

        const descendantLeafCount = (folderId) => {
            const folder = byId[folderId];
            if (!folder) return 0;
            if (!folder.children.length) return fileCount(folderId);
            return folder.children.reduce((sum, childId) => sum + descendantLeafCount(childId), 0);
        };

        const getTopMainFolder = (folderId) => {
            let cursor = folderId;
            while (cursor && byId[cursor] && byId[cursor].parent && byId[cursor].parent !== 'all') {
                cursor = byId[cursor].parent;
            }
            if (byId[cursor] && byId[cursor].parent === 'all') return cursor;
            return null;
        };

        const renderSideTree = () => {
            if (!sideTree) return;
            const pathSet = new Set(getPath(currentFolder));
            const activeMain = getTopMainFolder(currentFolder);

            const renderNode = (folderId, level) => {
                const node = byId[folderId];
                if (!node) return '';
                const hasChildren = node.children.length > 0;
                const isActive = currentFolder === folderId || (level === 0 && activeMain === folderId);
                const count = hasChildren ? node.children.length : fileCount(folderId);
                const isInPath = pathSet.has(folderId);
                const shouldExpand = hasChildren && (
                    isInPath ||
                    (level === 0 && folderId === activeMain)
                );

                const childrenHtml = shouldExpand
                    ? `<div class="dm-subfolder-list">${node.children.map((childId) => renderNode(childId, level + 1)).join('')}</div>`
                    : '';
                return `
                    <button type="button" class="dm-nav-item dm-subfolder-item ${isActive ? 'is-active' : ''}" data-folder-id="${esc(folderId)}">
                        <span><span class="dm-nav-folder-icon" aria-hidden="true"></span>${esc(node.label)}</span>
                        <span>${esc(String(count))}</span>
                    </button>
                    ${childrenHtml}
                `;
            };

            const mainFolders = byId.all.children;
            sideTree.innerHTML = mainFolders.map((folderId) => renderNode(folderId, 0)).join('');
        };

        const renderBreadcrumb = () => {
            const path = getPath(currentFolder);
            breadcrumb.innerHTML = path.map((folderId, index) => {
                const label = folderId === 'all' ? 'Organization Data' : (byId[folderId] ? byId[folderId].label : folderId);
                const isLast = index === path.length - 1;
                if (isLast) {
                    return `<span class="dm-crumb-current">${esc(label)}</span>`;
                }
                return `<button type="button" class="dm-crumb ${index === 0 ? 'is-active' : ''}" data-crumb="${esc(folderId)}">${esc(label)}</button><span class="dm-crumb-sep">></span>`;
            }).join('');
        };

        const renderFolderCards = (folderIds) => {
            list.classList.add('is-folder-mode');
            if (!folderIds.length) {
                list.innerHTML = '';
                empty.textContent = 'No subfolders found for this level.';
                empty.style.display = '';
                return;
            }
            empty.style.display = 'none';
            list.innerHTML = folderIds.map((folderId) => {
                const node = byId[folderId];
                const subCount = node.children.length;
                const files = fileCount(folderId);
                const iconClass = folderId === 'board-report'
                    ? 'is-board'
                    : (folderId === 'quality-committee'
                        ? 'is-committee'
                        : (folderId === 'quality-dashboard'
                            ? 'is-dashboard'
                            : (folderId === 'qapi-report' ? 'is-qapi' : 'is-default')));
                const badgeText = subCount > 0
                    ? `${subCount} subfolder${subCount === 1 ? '' : 's'}`
                    : `${files} file${files === 1 ? '' : 's'}`;

                return `
                    <button type="button" class="dm-folder-card" data-open-folder="${esc(folderId)}">
                        <div class="dm-folder-card-icon"><span class="dm-folder-glyph ${iconClass}"></span></div>
                        <div class="dm-folder-card-body">
                            <strong>${esc(node.label)}</strong>
                            <p>${esc(node.description || '')}</p>
                            <span class="dm-folder-badge"><span class="dm-badge-icon" aria-hidden="true"></span>${esc(badgeText)}</span>
                        </div>
                    </button>
                `;
            }).join('');
        };

        const renderLeafFiles = (folderId) => {
            list.classList.remove('is-folder-mode');
            const allFiles = getFiles(folderId);
            const term = (searchInput.value || '').trim().toLowerCase();
            const files = !term
                ? allFiles
                : allFiles.filter((row) => String(row && row.name ? row.name : '').toLowerCase().includes(term));
            const expectedMetrics = getExpectedMetrics(folderId);
            const isCahGlobal = folderId === 'cah-quality-infrastructure';
            const isAntibiotic = folderId === 'antibiotic-stewardship';
            const isHcahpsFolder = String(folderId || '').indexOf('hcahps-') === 0;
            const isReadmissions = folderId === 'readmissions';
            const isEdtc = folderId === 'edtc';
            const isMedianFromEd = folderId === 'median-time-from-ed';
            const isOp22 = folderId === 'op-22-left-without-being-seen';
            const uploadColumnsText = isCahGlobal
                ? 'Upload CSV/XLS/XLSX with columns: <strong>Year, Period, Elements Met Count, Elements Selected Count, Leadership Responsibility & Accountability, Quality Embedded in Strategic Plan, Workforce Engagement & Ownership, Culture of Continuous Improvement (Behaviors), Culture of Continuous Improvement (Systems), Engagement of Patients Partners and Community, Collecting Meaningful and Accurate Data, Using Data to Improve Quality, Notes</strong>.'
                : (isAntibiotic
                    ? 'Upload CSV/XLS/XLSX with columns: <strong>Year, Period, Core Elements Met Count, Core Elements Selected Count, Leadership, Accountability, Drug Expertise, Action, Tracking, Reporting, Education, Notes</strong>.'
                    : (isReadmissions
                        ? 'Upload two-part readmissions data as CSV/XLS/XLSX with columns: <strong>Period, Num 30-day Unplanned Readmissions, Denom Eligible Discharges, Rate, CCN, NPI/MBI, Admission Date, Discharge Date, Heart Rate, Systolic Blood Pressure, Respiratory Rate, Temperature, Oxygen Saturation, Weight, Hematocrit, White Blood Cell Count, Potassium, Sodium, Bicarbonate, Creatinine, Glucose, Notes</strong>.'
                        : (isEdtc
                            ? 'Upload quarterly EDTC data as CSV/XLS/XLSX with columns: <strong>Year, Quarter, EDTC Reporting Item, Num, Denom, Rate</strong>. Enter one composite score row plus numerator and denominator counts for all eight required EDTC elements.'
                            : (isMedianFromEd
                                ? 'Upload quarterly OP-18 data as CSV/XLS/XLSX with columns: <strong>Period, Median Minutes</strong>. Use one file per measure set if your source separates the 4 OP-18 measure IDs.'
                                : (isOp22
                                    ? 'Upload annual OP-22 data as CSV/XLS/XLSX with columns: <strong>Year, Num, Denom, Rate</strong>. Fill one row or bulk add multiple years. Improvement is demonstrated by decreasing rate.'
                        : (isHcahpsFolder
                        ? 'Upload quarterly HCAHPS vendor data as CSV/XLS/XLSX with columns: <strong>Period, Num, Denom, Rate</strong>.'
                        : 'Upload monthly data as CSV/XLS/XLSX with columns: <strong>Metric, Year, Month, Num, Denom, Rate</strong>.'))))));

            list.innerHTML = `
                <div class="dm-file-pane">
                    <div class="dm-upload-guide">
                        <h4>Upload Guide</h4>
                        <p>${uploadColumnsText}</p>
                        ${expectedMetrics.length ? `<p><strong>Expected metrics (${expectedMetrics.length}):</strong> ${expectedMetrics.map((m) => esc(m)).join(', ')}</p>` : ''}
                    </div>
                    <div class="dm-file-actions">
                        <button type="button" class="dm-file-btn dm-file-btn-ghost" data-download-template>Download Template (With Examples)</button>
                        <button type="button" class="dm-file-btn" data-upload-trigger>Upload CSV/Excel</button>
                        <input type="file" id="dmUploadInput" accept=".csv,.xls,.xlsx" style="display:none;">
                    </div>
                    <div class="dm-file-table-wrap">
                        <table class="dm-file-table">
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Updated</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${files.length ? files.map((row, index) => `
                                    <tr>
                                        <td><a href="${esc(row.url || '#')}" target="_blank" rel="noopener">${esc(row.name || 'file')}</a></td>
                                        <td>${esc((row.type || '').split('/').pop() || '-')}</td>
                                        <td>${esc(typeof row.size_kb !== 'undefined' ? row.size_kb + ' KB' : '-')}</td>
                                        <td>${esc(row.uploaded_at || '-')}</td>
                                        <td>
                                            <button type="button" class="dm-file-btn dm-file-btn-ghost" data-preview-index="${index}">Preview Data</button>
                                            <button type="button" class="dm-file-btn dm-file-btn-ghost" data-replace-index="${index}">Replace</button>
                                        </td>
                                    </tr>
                                `).join('') : '<tr><td colspan="5">No files yet in this folder.</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                    ${previewState && previewState.folderId === folderId ? `
                        <div class="dm-data-preview">
                            <h4>Data Preview: ${esc(previewState.fileName || 'CSV')}</h4>
                            ${previewState.error ? `<p class="dm-data-preview-error">${esc(previewState.error)}</p>` : ''}
                            ${previewState.chartSvg ? `<div class="dm-data-preview-chart">${previewState.chartSvg}</div>` : ''}
                            ${previewState.tableHtml ? `<div class="dm-data-preview-table-wrap">${previewState.tableHtml}</div>` : ''}
                        </div>
                    ` : ''}
                </div>
            `;
            empty.style.display = 'none';
        };

        const csvCell = (value) => `"${String(value).replace(/"/g, '""')}"`;

        const buildTemplateCsv = (folderId) => {
            if (folderId === 'cah-quality-infrastructure') {
                const rows = [[
                    'Year',
                    'Period',
                    'Elements Met Count',
                    'Elements Selected Count',
                    'Leadership Responsibility & Accountability',
                    'Quality Embedded in Strategic Plan',
                    'Workforce Engagement & Ownership',
                    'Culture of Continuous Improvement (Behaviors)',
                    'Culture of Continuous Improvement (Systems)',
                    'Engagement of Patients Partners and Community',
                    'Collecting Meaningful and Accurate Data',
                    'Using Data to Improve Quality',
                    'Notes'
                ]];

                rows.push(['2026', 'Q1', '3', '8', 'Met', 'In Progress', 'Met', 'In Progress', 'Not Met', 'Met', 'Met', 'In Progress', 'Baseline quarter']);
                rows.push(['2026', 'Q2', '4', '8', 'Met', 'Met', 'In Progress', 'In Progress', 'In Progress', 'Met', 'Met', 'In Progress', 'Improvement plan updated']);
                rows.push(['2026', 'Q3', '5', '8', 'Met', 'Met', 'Met', 'In Progress', 'In Progress', 'Met', 'Met', 'Met', 'Leadership review completed']);
                rows.push(['2026', 'Q4', '6', '8', 'Met', 'Met', 'Met', 'Met', 'In Progress', 'Met', 'Met', 'Met', 'Year-end progress']);

                return rows.map((row) => row.map(csvCell).join(',')).join('\n');
            }

            if (folderId === 'antibiotic-stewardship') {
                const rows = [[
                    'Year',
                    'Period',
                    'Core Elements Met Count',
                    'Core Elements Selected Count',
                    'Leadership',
                    'Accountability',
                    'Drug Expertise',
                    'Action',
                    'Tracking',
                    'Reporting',
                    'Education',
                    'Notes'
                ]];

                rows.push(['2026', 'Q1', '3', '7', 'Met', 'In Progress', 'Met', 'In Progress', 'Not Met', 'In Progress', 'Met', 'Baseline']);
                rows.push(['2026', 'Q2', '4', '7', 'Met', 'Met', 'Met', 'In Progress', 'In Progress', 'In Progress', 'Met', 'Policy updates']);
                rows.push(['2026', 'Q3', '5', '7', 'Met', 'Met', 'Met', 'Met', 'In Progress', 'In Progress', 'Met', 'Audit cycle started']);
                rows.push(['2026', 'Q4', '6', '7', 'Met', 'Met', 'Met', 'Met', 'Met', 'In Progress', 'Met', 'Year-end review']);

                return rows.map((row) => row.map(csvCell).join(',')).join('\n');
            }

            if (folderId === 'safe-use-of-opioids-ecqm') {
                const rows = [['Year', 'Month', 'Num', 'Denom', 'Rate']];
                const months = ['Jan', 'Feb', 'Mar', 'April', 'May', 'June', 'July', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'];

                const buildYear = (year, startNum, startDenom) => {
                    months.forEach((month, idx) => {
                        const num = ((startNum + (idx * 3)) % 5) + 1; // values 1..5
                        const denom = ((startDenom + (idx * 5)) % 12) + 10; // values 10..21
                        const rate = denom > 0 ? `${((num / denom) * 100).toFixed(2)}%` : '0.00%';
                        rows.push([String(year), month, String(num), String(denom), rate]);
                    });
                };

                buildYear(2021, 1, 5);
                buildYear(2022, 2, 7);
                buildYear(2023, 3, 9);
                buildYear(2024, 1, 11);

                return rows.map((row) => row.map(csvCell).join(',')).join('\n');
            }

            if (folderId === 'hcahps-comm-with-nurses') {
                const rows = [['Period', 'Num', 'Denom', 'Rate']];
                const q = (year, quarter, start, end, num, denom, rate) =>
                    `Q${quarter} ${year} (${start} \u2013 ${end})`;

                rows.push([q(2021, 1, 'January 1', 'March 31', 15, 115, '13.04%'), '15', '115', '13.04%']);
                rows.push([q(2021, 2, 'April 1', 'June 30', 26, 200, '13.00%'), '26', '200', '13.00%']);
                rows.push([q(2021, 3, 'July 1', 'September 30', 19, 165, '11.52%'), '19', '165', '11.52%']);
                rows.push([q(2021, 4, 'October 1', 'December 31', 27, 174, '15.52%'), '27', '174', '15.52%']);

                rows.push([q(2022, 1, 'January 1', 'March 31', 31, 219, '14.16%'), '31', '219', '14.16%']);
                rows.push([q(2022, 2, 'April 1', 'June 30', 18, 126, '14.29%'), '18', '126', '14.29%']);
                rows.push([q(2022, 3, 'July 1', 'September 30', 22, 115, '19.13%'), '22', '115', '19.13%']);
                rows.push([q(2022, 4, 'October 1', 'December 31', 15, 200, '7.50%'), '15', '200', '7.50%']);

                rows.push([q(2023, 1, 'January 1', 'March 31', 26, 165, '15.76%'), '26', '165', '15.76%']);
                rows.push([q(2023, 2, 'April 1', 'June 30', 19, 174, '10.92%'), '19', '174', '10.92%']);
                rows.push([q(2023, 3, 'July 1', 'September 30', 27, 219, '12.33%'), '27', '219', '12.33%']);
                rows.push([q(2023, 4, 'October 1', 'December 31', 31, 126, '24.60%'), '31', '126', '24.60%']);

                rows.push([q(2024, 1, 'January 1', 'March 31', 18, 115, '15.65%'), '18', '115', '15.65%']);
                rows.push([q(2024, 2, 'April 1', 'June 30', 22, 200, '11.00%'), '22', '200', '11.00%']);
                rows.push([q(2024, 3, 'July 1', 'September 30', 15, 165, '9.09%'), '15', '165', '9.09%']);
                rows.push([q(2024, 4, 'October 1', 'December 31', 26, 174, '14.94%'), '26', '174', '14.94%']);

                rows.push([q(2025, 1, 'January 1', 'March 31', 19, 219, '8.68%'), '19', '219', '8.68%']);
                rows.push([q(2025, 2, 'April 1', 'June 30', 27, 126, '21.43%'), '27', '126', '21.43%']);
                rows.push([q(2025, 3, 'July 1', 'September 30', 31, 115, '26.96%'), '31', '115', '26.96%']);
                rows.push([q(2025, 4, 'October 1', 'December 31', 18, 200, '9.00%'), '18', '200', '9.00%']);

                return rows.map((row) => row.map(csvCell).join(',')).join('\n');
            }

            if (folderId === 'hcahps-comm-with-docs') {
                const rows = [['Period', 'Num', 'Denom', 'Rate']];
                const q = (year, quarter, start, end) =>
                    `Q${quarter} ${year} (${start} \u2013 ${end})`;

                rows.push([q(2021, 1, 'January 1', 'March 31'), '13', '115', '11.30%']);
                rows.push([q(2021, 2, 'April 1', 'June 30'), '19', '200', '9.50%']);
                rows.push([q(2021, 3, 'July 1', 'September 30'), '20', '165', '12.12%']);
                rows.push([q(2021, 4, 'October 1', 'December 31'), '18', '174', '10.34%']);

                rows.push([q(2022, 1, 'January 1', 'March 31'), '17', '219', '7.76%']);
                rows.push([q(2022, 2, 'April 1', 'June 30'), '20', '126', '15.87%']);
                rows.push([q(2022, 3, 'July 1', 'September 30'), '22', '115', '19.13%']);
                rows.push([q(2022, 4, 'October 1', 'December 31'), '24', '200', '12.00%']);

                rows.push([q(2023, 1, 'January 1', 'March 31'), '17', '165', '10.30%']);
                rows.push([q(2023, 2, 'April 1', 'June 30'), '19', '174', '10.92%']);
                rows.push([q(2023, 3, 'July 1', 'September 30'), '27', '219', '12.33%']);
                rows.push([q(2023, 4, 'October 1', 'December 31'), '31', '126', '24.60%']);

                rows.push([q(2024, 1, 'January 1', 'March 31'), '18', '115', '15.65%']);
                rows.push([q(2024, 2, 'April 1', 'June 30'), '22', '200', '11.00%']);
                rows.push([q(2024, 3, 'July 1', 'September 30'), '15', '165', '9.09%']);
                rows.push([q(2024, 4, 'October 1', 'December 31'), '26', '174', '14.94%']);

                rows.push([q(2025, 1, 'January 1', 'March 31'), '19', '219', '8.68%']);
                rows.push([q(2025, 2, 'April 1', 'June 30'), '27', '126', '21.43%']);
                rows.push([q(2025, 3, 'July 1', 'September 30'), '31', '115', '26.96%']);
                rows.push([q(2025, 4, 'October 1', 'December 31'), '18', '200', '9.00%']);

                return rows.map((row) => row.map(csvCell).join(',')).join('\n');
            }

            if (folderId === 'hcahps-restfulness') {
                const rows = [['Period', 'Num', 'Denom', 'Rate']];
                const q = (year, quarter, start, end) =>
                    `Q${quarter} ${year} (${start} \u2013 ${end})`;

                rows.push([q(2021, 1, 'January 1', 'March 31'), '2', '115', '1.74%']);
                rows.push([q(2021, 2, 'April 1', 'June 30'), '4', '200', '2.00%']);
                rows.push([q(2021, 3, 'July 1', 'September 30'), '6', '165', '3.64%']);
                rows.push([q(2021, 4, 'October 1', 'December 31'), '8', '174', '4.60%']);

                rows.push([q(2022, 1, 'January 1', 'March 31'), '10', '219', '4.57%']);
                rows.push([q(2022, 2, 'April 1', 'June 30'), '8', '126', '6.35%']);
                rows.push([q(2022, 3, 'July 1', 'September 30'), '6', '115', '5.22%']);
                rows.push([q(2022, 4, 'October 1', 'December 31'), '12', '200', '6.00%']);

                rows.push([q(2023, 1, 'January 1', 'March 31'), '13', '165', '7.88%']);
                rows.push([q(2023, 2, 'April 1', 'June 30'), '19', '174', '10.92%']);
                rows.push([q(2023, 3, 'July 1', 'September 30'), '2', '219', '0.91%']);
                rows.push([q(2023, 4, 'October 1', 'December 31'), '4', '126', '3.17%']);

                rows.push([q(2024, 1, 'January 1', 'March 31'), '6', '115', '5.22%']);
                rows.push([q(2024, 2, 'April 1', 'June 30'), '8', '200', '4.00%']);
                rows.push([q(2024, 3, 'July 1', 'September 30'), '10', '165', '6.06%']);
                rows.push([q(2024, 4, 'October 1', 'December 31'), '8', '174', '4.60%']);

                rows.push([q(2025, 1, 'January 1', 'March 31'), '6', '219', '2.74%']);
                rows.push([q(2025, 2, 'April 1', 'June 30'), '12', '126', '9.52%']);
                rows.push([q(2025, 3, 'July 1', 'September 30'), '13', '115', '11.30%']);
                rows.push([q(2025, 4, 'October 1', 'December 31'), '19', '200', '9.50%']);

                return rows.map((row) => row.map(csvCell).join(',')).join('\n');
            }

            if (folderId === 'hcahps-care-coordination') {
                const rows = [['Period', 'Num', 'Denom', 'Rate']];
                const q = (year, quarter, start, end) =>
                    `Q${quarter} ${year} (${start} \u2013 ${end})`;

                rows.push([q(2021, 1, 'January 1', 'March 31'), '8', '115', '6.96%']);
                rows.push([q(2021, 2, 'April 1', 'June 30'), '6', '200', '3.00%']);
                rows.push([q(2021, 3, 'July 1', 'September 30'), '12', '165', '7.27%']);
                rows.push([q(2021, 4, 'October 1', 'December 31'), '13', '174', '7.47%']);

                rows.push([q(2022, 1, 'January 1', 'March 31'), '19', '219', '8.68%']);
                rows.push([q(2022, 2, 'April 1', 'June 30'), '2', '126', '1.59%']);
                rows.push([q(2022, 3, 'July 1', 'September 30'), '4', '115', '3.48%']);
                rows.push([q(2022, 4, 'October 1', 'December 31'), '8', '200', '4.00%']);

                rows.push([q(2023, 1, 'January 1', 'March 31'), '10', '165', '6.06%']);
                rows.push([q(2023, 2, 'April 1', 'June 30'), '12', '174', '6.90%']);
                rows.push([q(2023, 3, 'July 1', 'September 30'), '14', '219', '6.39%']);
                rows.push([q(2023, 4, 'October 1', 'December 31'), '8', '126', '6.35%']);

                rows.push([q(2024, 1, 'January 1', 'March 31'), '6', '115', '5.22%']);
                rows.push([q(2024, 2, 'April 1', 'June 30'), '8', '200', '4.00%']);
                rows.push([q(2024, 3, 'July 1', 'September 30'), '10', '165', '6.06%']);
                rows.push([q(2024, 4, 'October 1', 'December 31'), '8', '174', '4.60%']);

                rows.push([q(2025, 1, 'January 1', 'March 31'), '6', '219', '2.74%']);
                rows.push([q(2025, 2, 'April 1', 'June 30'), '12', '126', '9.52%']);
                rows.push([q(2025, 3, 'July 1', 'September 30'), '13', '115', '11.30%']);
                rows.push([q(2025, 4, 'October 1', 'December 31'), '19', '200', '9.50%']);

                return rows.map((row) => row.map(csvCell).join(',')).join('\n');
            }

            if (folderId === 'hcahps-responsiveness') {
                const rows = [['Period', 'Num', 'Denom', 'Rate']];
                const q = (year, quarter, start, end) =>
                    `Q${quarter} ${year} (${start} \u2013 ${end})`;

                rows.push([q(2021, 1, 'January 1', 'March 31'), '2', '115', '1.74%']);
                rows.push([q(2021, 2, 'April 1', 'June 30'), '1', '200', '0.50%']);
                rows.push([q(2021, 3, 'July 1', 'September 30'), '6', '165', '3.64%']);
                rows.push([q(2021, 4, 'October 1', 'December 31'), '7', '174', '4.02%']);

                rows.push([q(2022, 1, 'January 1', 'March 31'), '8', '219', '3.65%']);
                rows.push([q(2022, 2, 'April 1', 'June 30'), '2', '126', '1.59%']);
                rows.push([q(2022, 3, 'July 1', 'September 30'), '4', '115', '3.48%']);
                rows.push([q(2022, 4, 'October 1', 'December 31'), '8', '200', '4.00%']);

                rows.push([q(2023, 1, 'January 1', 'March 31'), '10', '165', '6.06%']);
                rows.push([q(2023, 2, 'April 1', 'June 30'), '5', '174', '2.87%']);
                rows.push([q(2023, 3, 'July 1', 'September 30'), '3', '219', '1.37%']);
                rows.push([q(2023, 4, 'October 1', 'December 31'), '9', '126', '7.14%']);

                rows.push([q(2024, 1, 'January 1', 'March 31'), '7', '115', '6.09%']);
                rows.push([q(2024, 2, 'April 1', 'June 30'), '5', '200', '2.50%']);
                rows.push([q(2024, 3, 'July 1', 'September 30'), '10', '165', '6.06%']);
                rows.push([q(2024, 4, 'October 1', 'December 31'), '8', '174', '4.60%']);

                rows.push([q(2025, 1, 'January 1', 'March 31'), '6', '219', '2.74%']);
                rows.push([q(2025, 2, 'April 1', 'June 30'), '6', '126', '4.76%']);
                rows.push([q(2025, 3, 'July 1', 'September 30'), '7', '115', '6.09%']);
                rows.push([q(2025, 4, 'October 1', 'December 31'), '10', '200', '5.00%']);

                return rows.map((row) => row.map(csvCell).join(',')).join('\n');
            }

            if (folderId === 'hcahps-medicine-comm') {
                const rows = [['Period', 'Num', 'Denom', 'Rate']];
                const q = (year, quarter, start, end) =>
                    `Q${quarter} ${year} (${start} \u2013 ${end})`;

                rows.push([q(2021, 1, 'January 1', 'March 31'), '15', '115', '13.04%']);
                rows.push([q(2021, 2, 'April 1', 'June 30'), '16', '200', '8.00%']);
                rows.push([q(2021, 3, 'July 1', 'September 30'), '17', '165', '10.30%']);
                rows.push([q(2021, 4, 'October 1', 'December 31'), '19', '174', '10.92%']);

                rows.push([q(2022, 1, 'January 1', 'March 31'), '19', '219', '8.68%']);
                rows.push([q(2022, 2, 'April 1', 'June 30'), '24', '126', '19.05%']);
                rows.push([q(2022, 3, 'July 1', 'September 30'), '25', '115', '21.74%']);
                rows.push([q(2022, 4, 'October 1', 'December 31'), '19', '200', '9.50%']);

                rows.push([q(2023, 1, 'January 1', 'March 31'), '18', '165', '10.91%']);
                rows.push([q(2023, 2, 'April 1', 'June 30'), '26', '174', '14.94%']);
                rows.push([q(2023, 3, 'July 1', 'September 30'), '25', '219', '11.42%']);
                rows.push([q(2023, 4, 'October 1', 'December 31'), '16', '126', '12.70%']);

                rows.push([q(2024, 1, 'January 1', 'March 31'), '17', '115', '14.78%']);
                rows.push([q(2024, 2, 'April 1', 'June 30'), '19', '200', '9.50%']);
                rows.push([q(2024, 3, 'July 1', 'September 30'), '19', '165', '11.52%']);
                rows.push([q(2024, 4, 'October 1', 'December 31'), '24', '174', '13.79%']);

                rows.push([q(2025, 1, 'January 1', 'March 31'), '16', '219', '7.31%']);
                rows.push([q(2025, 2, 'April 1', 'June 30'), '12', '126', '9.52%']);
                rows.push([q(2025, 3, 'July 1', 'September 30'), '13', '115', '11.30%']);
                rows.push([q(2025, 4, 'October 1', 'December 31'), '19', '200', '9.50%']);

                return rows.map((row) => row.map(csvCell).join(',')).join('\n');
            }

            if (folderId === 'hcahps-cleanliness') {
                const rows = [['Period', 'Num', 'Denom', 'Rate']];
                const q = (year, quarter, start, end) =>
                    `Q${quarter} ${year} (${start} \u2013 ${end})`;

                rows.push([q(2021, 1, 'January 1', 'March 31'), '8', '115', '6.96%']);
                rows.push([q(2021, 2, 'April 1', 'June 30'), '6', '200', '3.00%']);
                rows.push([q(2021, 3, 'July 1', 'September 30'), '4', '165', '2.42%']);
                rows.push([q(2021, 4, 'October 1', 'December 31'), '8', '174', '4.60%']);

                rows.push([q(2022, 1, 'January 1', 'March 31'), '10', '219', '4.57%']);
                rows.push([q(2022, 2, 'April 1', 'June 30'), '5', '126', '3.97%']);
                rows.push([q(2022, 3, 'July 1', 'September 30'), '6', '115', '5.22%']);
                rows.push([q(2022, 4, 'October 1', 'December 31'), '6', '200', '3.00%']);

                rows.push([q(2023, 1, 'January 1', 'March 31'), '7', '165', '4.24%']);
                rows.push([q(2023, 2, 'April 1', 'June 30'), '10', '174', '5.75%']);
                rows.push([q(2023, 3, 'July 1', 'September 30'), '3', '219', '1.37%']);
                rows.push([q(2023, 4, 'October 1', 'December 31'), '9', '126', '7.14%']);

                rows.push([q(2024, 1, 'January 1', 'March 31'), '7', '115', '6.09%']);
                rows.push([q(2024, 2, 'April 1', 'June 30'), '5', '200', '2.50%']);
                rows.push([q(2024, 3, 'July 1', 'September 30'), '10', '165', '6.06%']);
                rows.push([q(2024, 4, 'October 1', 'December 31'), '8', '174', '4.60%']);

                rows.push([q(2025, 1, 'January 1', 'March 31'), '6', '219', '2.74%']);
                rows.push([q(2025, 2, 'April 1', 'June 30'), '6', '126', '4.76%']);
                rows.push([q(2025, 3, 'July 1', 'September 30'), '7', '115', '6.09%']);
                rows.push([q(2025, 4, 'October 1', 'December 31'), '10', '200', '5.00%']);

                return rows.map((row) => row.map(csvCell).join(',')).join('\n');
            }

            if (folderId === 'hcahps-discharge') {
                const rows = [['Period', 'Num', 'Denom', 'Rate']];
                const q = (year, quarter, start, end) =>
                    `Q${quarter} ${year} (${start} \u2013 ${end})`;

                rows.push([q(2021, 1, 'January 1', 'March 31'), '7', '115', '6.09%']);
                rows.push([q(2021, 2, 'April 1', 'June 30'), '10', '200', '5.00%']);
                rows.push([q(2021, 3, 'July 1', 'September 30'), '3', '165', '1.82%']);
                rows.push([q(2021, 4, 'October 1', 'December 31'), '9', '174', '5.17%']);

                rows.push([q(2022, 1, 'January 1', 'March 31'), '7', '219', '3.20%']);
                rows.push([q(2022, 2, 'April 1', 'June 30'), '5', '126', '3.97%']);
                rows.push([q(2022, 3, 'July 1', 'September 30'), '6', '115', '5.22%']);
                rows.push([q(2022, 4, 'October 1', 'December 31'), '6', '200', '3.00%']);

                rows.push([q(2023, 1, 'January 1', 'March 31'), '7', '165', '4.24%']);
                rows.push([q(2023, 2, 'April 1', 'June 30'), '10', '174', '5.75%']);
                rows.push([q(2023, 3, 'July 1', 'September 30'), '3', '219', '1.37%']);
                rows.push([q(2023, 4, 'October 1', 'December 31'), '9', '126', '7.14%']);

                rows.push([q(2024, 1, 'January 1', 'March 31'), '7', '115', '6.09%']);
                rows.push([q(2024, 2, 'April 1', 'June 30'), '9', '200', '4.50%']);
                rows.push([q(2024, 3, 'July 1', 'September 30'), '7', '165', '4.24%']);
                rows.push([q(2024, 4, 'October 1', 'December 31'), '5', '174', '2.87%']);

                rows.push([q(2025, 1, 'January 1', 'March 31'), '6', '219', '2.74%']);
                rows.push([q(2025, 2, 'April 1', 'June 30'), '6', '126', '4.76%']);
                rows.push([q(2025, 3, 'July 1', 'September 30'), '7', '115', '6.09%']);
                rows.push([q(2025, 4, 'October 1', 'December 31'), '10', '200', '5.00%']);

                return rows.map((row) => row.map(csvCell).join(',')).join('\n');
            }

            if (folderId === 'hcahps-symptoms') {
                const rows = [['Period', 'Num', 'Denom', 'Rate']];
                const q = (year, quarter, start, end) =>
                    `Q${quarter} ${year} (${start} \u2013 ${end})`;

                rows.push([q(2021, 1, 'January 1', 'March 31'), '25', '115', '21.74%']);
                rows.push([q(2021, 2, 'April 1', 'June 30'), '32', '200', '16.00%']);
                rows.push([q(2021, 3, 'July 1', 'September 30'), '24', '165', '14.55%']);
                rows.push([q(2021, 4, 'October 1', 'December 31'), '26', '174', '14.94%']);

                rows.push([q(2022, 1, 'January 1', 'March 31'), '36', '219', '16.44%']);
                rows.push([q(2022, 2, 'April 1', 'June 30'), '19', '126', '15.08%']);
                rows.push([q(2022, 3, 'July 1', 'September 30'), '18', '115', '15.65%']);
                rows.push([q(2022, 4, 'October 1', 'December 31'), '25', '200', '12.50%']);

                rows.push([q(2023, 1, 'January 1', 'March 31'), '32', '165', '19.39%']);
                rows.push([q(2023, 2, 'April 1', 'June 30'), '24', '174', '13.79%']);
                rows.push([q(2023, 3, 'July 1', 'September 30'), '26', '219', '11.87%']);
                rows.push([q(2023, 4, 'October 1', 'December 31'), '36', '126', '28.57%']);

                rows.push([q(2024, 1, 'January 1', 'March 31'), '19', '115', '16.52%']);
                rows.push([q(2024, 2, 'April 1', 'June 30'), '18', '200', '9.00%']);
                rows.push([q(2024, 3, 'July 1', 'September 30'), '25', '165', '15.15%']);
                rows.push([q(2024, 4, 'October 1', 'December 31'), '32', '174', '18.39%']);

                rows.push([q(2025, 1, 'January 1', 'March 31'), '24', '219', '10.96%']);
                rows.push([q(2025, 2, 'April 1', 'June 30'), '26', '126', '20.63%']);
                rows.push([q(2025, 3, 'July 1', 'September 30'), '36', '115', '31.30%']);
                rows.push([q(2025, 4, 'October 1', 'December 31'), '19', '200', '9.50%']);

                return rows.map((row) => row.map(csvCell).join(',')).join('\n');
            }

            if (folderId === 'hcahps-overall-rating') {
                const rows = [['Period', 'Num', 'Denom', 'Rate']];
                const q = (year, quarter, start, end) =>
                    `Q${quarter} ${year} (${start} \u2013 ${end})`;

                rows.push([q(2021, 1, 'January 1', 'March 31'), '7', '115', '6.09%']);
                rows.push([q(2021, 2, 'April 1', 'June 30'), '3', '200', '1.50%']);
                rows.push([q(2021, 3, 'July 1', 'September 30'), '3', '165', '1.82%']);
                rows.push([q(2021, 4, 'October 1', 'December 31'), '9', '174', '5.17%']);

                rows.push([q(2022, 1, 'January 1', 'March 31'), '7', '219', '3.20%']);
                rows.push([q(2022, 2, 'April 1', 'June 30'), '5', '126', '3.97%']);
                rows.push([q(2022, 3, 'July 1', 'September 30'), '6', '115', '5.22%']);
                rows.push([q(2022, 4, 'October 1', 'December 31'), '6', '200', '3.00%']);

                rows.push([q(2023, 1, 'January 1', 'March 31'), '7', '165', '4.24%']);
                rows.push([q(2023, 2, 'April 1', 'June 30'), '2', '174', '1.15%']);
                rows.push([q(2023, 3, 'July 1', 'September 30'), '3', '219', '1.37%']);
                rows.push([q(2023, 4, 'October 1', 'December 31'), '9', '126', '7.14%']);

                rows.push([q(2024, 1, 'January 1', 'March 31'), '7', '115', '6.09%']);
                rows.push([q(2024, 2, 'April 1', 'June 30'), '9', '200', '4.50%']);
                rows.push([q(2024, 3, 'July 1', 'September 30'), '7', '165', '4.24%']);
                rows.push([q(2024, 4, 'October 1', 'December 31'), '5', '174', '2.87%']);

                rows.push([q(2025, 1, 'January 1', 'March 31'), '6', '219', '2.74%']);
                rows.push([q(2025, 2, 'April 1', 'June 30'), '6', '126', '4.76%']);
                rows.push([q(2025, 3, 'July 1', 'September 30'), '7', '115', '6.09%']);
                rows.push([q(2025, 4, 'October 1', 'December 31'), '5', '200', '2.50%']);

                return rows.map((row) => row.map(csvCell).join(',')).join('\n');
            }

            if (folderId === 'hcahps-willingness-to-rec') {
                const rows = [['Period', 'Num', 'Denom', 'Rate']];
                const q = (year, quarter, start, end) =>
                    `Q${quarter} ${year} (${start} \u2013 ${end})`;

                rows.push([q(2021, 1, 'January 1', 'March 31'), '10', '115', '8.70%']);
                rows.push([q(2021, 2, 'April 1', 'June 30'), '9', '200', '4.50%']);
                rows.push([q(2021, 3, 'July 1', 'September 30'), '8', '165', '4.85%']);
                rows.push([q(2021, 4, 'October 1', 'December 31'), '6', '174', '3.45%']);

                rows.push([q(2022, 1, 'January 1', 'March 31'), '8', '219', '3.65%']);
                rows.push([q(2022, 2, 'April 1', 'June 30'), '7', '126', '5.56%']);
                rows.push([q(2022, 3, 'July 1', 'September 30'), '3', '115', '2.61%']);
                rows.push([q(2022, 4, 'October 1', 'December 31'), '5', '200', '2.50%']);

                rows.push([q(2023, 1, 'January 1', 'March 31'), '7', '165', '4.24%']);
                rows.push([q(2023, 2, 'April 1', 'June 30'), '2', '174', '1.15%']);
                rows.push([q(2023, 3, 'July 1', 'September 30'), '10', '219', '4.57%']);
                rows.push([q(2023, 4, 'October 1', 'December 31'), '9', '126', '7.14%']);

                rows.push([q(2024, 1, 'January 1', 'March 31'), '8', '115', '6.96%']);
                rows.push([q(2024, 2, 'April 1', 'June 30'), '6', '200', '3.00%']);
                rows.push([q(2024, 3, 'July 1', 'September 30'), '8', '165', '4.85%']);
                rows.push([q(2024, 4, 'October 1', 'December 31'), '7', '174', '4.02%']);

                rows.push([q(2025, 1, 'January 1', 'March 31'), '3', '219', '1.37%']);
                rows.push([q(2025, 2, 'April 1', 'June 30'), '5', '126', '3.97%']);
                rows.push([q(2025, 3, 'July 1', 'September 30'), '7', '115', '6.09%']);
                rows.push([q(2025, 4, 'October 1', 'December 31'), '5', '200', '2.50%']);

                return rows.map((row) => row.map(csvCell).join(',')).join('\n');
            }

            if (folderId === 'readmissions') {
                const rows = [[
                    'Period',
                    'Num 30-day Unplanned Readmissions',
                    'Denom Eligible Discharges',
                    'Rate',
                    'CCN',
                    'NPI/MBI',
                    'Admission Date',
                    'Discharge Date',
                    'Heart Rate',
                    'Systolic Blood Pressure',
                    'Respiratory Rate',
                    'Temperature',
                    'Oxygen Saturation',
                    'Weight',
                    'Hematocrit',
                    'White Blood Cell Count',
                    'Potassium',
                    'Sodium',
                    'Bicarbonate',
                    'Creatinine',
                    'Glucose',
                    'Notes'
                ]];

                rows.push([
                    'Q1 2026 (January 1 – March 31)',
                    '12',
                    '180',
                    '6.67%',
                    '123456',
                    'A123456789',
                    '2026-01-12',
                    '2026-01-18',
                    '84',
                    '128',
                    '18',
                    '98.4',
                    '95%',
                    '76',
                    '39',
                    '8.2',
                    '4.2',
                    '138',
                    '24',
                    '1.1',
                    '108',
                    'Example patient record 1'
                ]);
                rows.push([
                    'Q1 2026 (January 1 – March 31)',
                    '12',
                    '180',
                    '6.67%',
                    '123456',
                    'B987654321',
                    '2026-02-03',
                    '2026-02-08',
                    '79',
                    '122',
                    '17',
                    '98.1',
                    '97%',
                    '69',
                    '37',
                    '7.6',
                    '4.0',
                    '140',
                    '23',
                    '0.9',
                    '102',
                    'Example patient record 2'
                ]);

                return rows.map((row) => row.map(csvCell).join(',')).join('\n');
            }

            if (folderId === 'edtc') {
                const rows = [['Year', 'Quarter', 'EDTC Reporting Item', 'Num', 'Denom', 'Rate']];
                const components = [
                    'Home Medications',
                    'Allergies and/or Reactions',
                    'Medications Administered in ED',
                    'ED Provider Note',
                    'Mental Status/Orientation Assessment',
                    'Reason for Transfer and/or Plan of Care',
                    'Tests and/or Procedures Performed',
                    'Tests and/or Procedures Results'
                ];

                [2024, 2025].forEach((year, yIdx) => {
                    ['Q1', 'Q2', 'Q3', 'Q4'].forEach((quarter, qIdx) => {
                        const compositeDenom = 45;
                        const compositeNum = compositeDenom - ((yIdx + qIdx) % 4);
                        rows.push([
                            String(year),
                            quarter,
                            'Composite Score (All elements documented)',
                            String(compositeNum),
                            String(compositeDenom),
                            `${((compositeNum / compositeDenom) * 100).toFixed(1)}%`
                        ]);
                        components.forEach((component, cIdx) => {
                            const denom = 10 + cIdx;
                            const num = (yIdx + qIdx + cIdx) % 7 === 0 ? denom - 1 : denom;
                            rows.push([
                                String(year),
                                quarter,
                                component,
                                String(num),
                                String(denom),
                                `${((num / denom) * 100).toFixed(1)}%`
                            ]);
                        });
                    });
                });

                return rows.map((row) => row.map(csvCell).join(',')).join('\n');
            }

            if (folderId === 'median-time-from-ed') {
                const rows = [['Period', 'Median Minutes']];
                const q = (year, quarter, start, end) =>
                    `Q${quarter} ${year} (${start} \u2013 ${end})`;

                rows.push([q(2021, 1, 'January 1', 'March 31'), '120']);
                rows.push([q(2021, 2, 'April 1', 'June 30'), '95']);
                rows.push([q(2021, 3, 'July 1', 'September 30'), '110']);
                rows.push([q(2021, 4, 'October 1', 'December 31'), '80']);

                rows.push([q(2022, 1, 'January 1', 'March 31'), '80']);
                rows.push([q(2022, 2, 'April 1', 'June 30'), '90']);
                rows.push([q(2022, 3, 'July 1', 'September 30'), '70']);
                rows.push([q(2022, 4, 'October 1', 'December 31'), '60']);

                rows.push([q(2023, 1, 'January 1', 'March 31'), '80']);
                rows.push([q(2023, 2, 'April 1', 'June 30'), '90']);
                rows.push([q(2023, 3, 'July 1', 'September 30'), '70']);
                rows.push([q(2023, 4, 'October 1', 'December 31'), '70']);

                rows.push([q(2024, 1, 'January 1', 'March 31'), '80']);
                rows.push([q(2024, 2, 'April 1', 'June 30'), '90']);
                rows.push([q(2024, 3, 'July 1', 'September 30'), '110']);
                rows.push([q(2024, 4, 'October 1', 'December 31'), '70']);

                rows.push([q(2025, 1, 'January 1', 'March 31'), '80']);
                rows.push([q(2025, 2, 'April 1', 'June 30'), '90']);
                rows.push([q(2025, 3, 'July 1', 'September 30'), '80']);
                rows.push([q(2025, 4, 'October 1', 'December 31'), '70']);

                return rows.map((row) => row.map(csvCell).join(',')).join('\n');
            }

            if (folderId === 'op-22-left-without-being-seen') {
                const rows = [['Year', 'Month', 'Num', 'Denom', 'Rate']];
                const months = ['Jan', 'Feb', 'Mar', 'April', 'May', 'June', 'July', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'];

                const buildYear = (year, startNum, startDenom) => {
                    months.forEach((month, idx) => {
                        const num = ((startNum + (idx * 3)) % 5) + 1; // 1..5
                        const denom = ((startDenom + (idx * 5)) % 12) + 10; // 10..21
                        const rate = denom > 0 ? `${((num / denom) * 100).toFixed(2)}%` : '0.00%';
                        rows.push([String(year), month, String(num), String(denom), rate]);
                    });
                };

                buildYear(2021, 1, 5);
                buildYear(2022, 2, 7);
                buildYear(2023, 3, 9);
                buildYear(2024, 1, 11);
                buildYear(2025, 2, 6);

                return rows.map((row) => row.map(csvCell).join(',')).join('\n');
            }

            // HCP/IMM-3 template requested in Year/Month/Num/Denom/Rate format.
            if (folderId === 'hcp-imm-3') {
                const rows = [['Year', 'Month', 'Num', 'Denom', 'Rate']];
                const months = ['Jan', 'Feb', 'Mar', 'April', 'May', 'June', 'July', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'];

                months.forEach((month, idx) => {
                    const num = idx >= 9 ? 26 : 20;
                    const rate = idx >= 9 ? '57.78%' : '44.44%';
                    rows.push(['2021', month, String(num), '45', rate]);
                });

                months.forEach((month, idx) => {
                    const num = idx >= 10 ? 32 : 26;
                    const rate = idx >= 10 ? '71.11%' : '57.78%';
                    rows.push(['2022', month, String(num), '45', rate]);
                });

                months.forEach((month, idx) => {
                    const num = idx >= 11 ? 35 : 32;
                    const rate = idx >= 11 ? '77.78%' : '71.11%';
                    rows.push(['2023', month, String(num), '45', rate]);
                });

                return rows.map((row) => row.map(csvCell).join(',')).join('\n');
            }

            const metrics = getExpectedMetrics(folderId);
            const headers = ['Metric', 'Year', 'Month', 'Num', 'Denom', 'Rate'];
            const rows = [headers];
            const metricRows = metrics.length ? metrics : ['Example Metric'];
            metricRows.forEach((metric) => {
                rows.push([metric, '2026', 'Jan', '20', '45', '44.44%']);
                rows.push([metric, '2026', 'Feb', '21', '45', '46.67%']);
            });
            return rows.map((row) => row.map(csvCell).join(',')).join('\n');
        };

        const downloadTextFile = (filename, content, type) => {
            const blob = new Blob([content], { type: type || 'text/plain;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        };

        const parseCsvRows = (text) => {
            const rows = [];
            let row = [];
            let cur = '';
            let inQuotes = false;
            for (let i = 0; i < text.length; i++) {
                const ch = text[i];
                if (ch === '"' && text[i + 1] === '"') {
                    cur += '"';
                    i++;
                    continue;
                }
                if (ch === '"') {
                    inQuotes = !inQuotes;
                    continue;
                }
                if (ch === ',' && !inQuotes) {
                    row.push(cur);
                    cur = '';
                    continue;
                }
                if ((ch === '\n' || ch === '\r') && !inQuotes) {
                    if (ch === '\r' && text[i + 1] === '\n') i++;
                    row.push(cur);
                    cur = '';
                    if (row.some((cell) => String(cell).trim() !== '')) {
                        rows.push(row.map((cell) => String(cell).trim()));
                    }
                    row = [];
                    continue;
                }
                cur += ch;
            }
            if (cur.length || row.length) {
                row.push(cur);
                if (row.some((cell) => String(cell).trim() !== '')) {
                    rows.push(row.map((cell) => String(cell).trim()));
                }
            }
            return rows;
        };

        const toNumber = (value) => {
            const normalized = String(value || '').replace('%', '').replace(/,/g, '').trim();
            const n = parseFloat(normalized);
            return Number.isFinite(n) ? n : null;
        };

        const buildChartSvg = (points) => {
            if (!Array.isArray(points) || points.length < 2) return '';
            const width = 760;
            const height = 220;
            const padX = 44;
            const padY = 20;
            const ys = points.map((p) => p.y).filter((n) => Number.isFinite(n));
            if (!ys.length) return '';
            const minY = Math.min(...ys);
            const maxY = Math.max(...ys);
            const span = Math.max(1, maxY - minY);
            const xStep = (width - padX * 2) / Math.max(1, points.length - 1);
            const poly = points.map((p, idx) => {
                const x = padX + idx * xStep;
                const y = height - padY - ((p.y - minY) / span) * (height - padY * 2);
                return `${x.toFixed(1)},${y.toFixed(1)}`;
            }).join(' ');
            const minLabel = `${minY.toFixed(2)}%`;
            const maxLabel = `${maxY.toFixed(2)}%`;
            return `
                <svg viewBox="0 0 ${width} ${height}" role="img" aria-label="Rate trend chart">
                    <line x1="${padX}" y1="${height - padY}" x2="${width - padX}" y2="${height - padY}" stroke="#bfd3e4" />
                    <line x1="${padX}" y1="${padY}" x2="${padX}" y2="${height - padY}" stroke="#bfd3e4" />
                    <polyline fill="none" stroke="#1b5f8d" stroke-width="3" points="${poly}" />
                    <text x="6" y="${padY + 4}" font-size="11" fill="#456585">${esc(maxLabel)}</text>
                    <text x="6" y="${height - padY}" font-size="11" fill="#456585">${esc(minLabel)}</text>
                    <text x="${padX}" y="${height - 4}" font-size="11" fill="#456585">${esc(points[0].x)}</text>
                    <text x="${width - padX - 40}" y="${height - 4}" font-size="11" fill="#456585">${esc(points[points.length - 1].x)}</text>
                </svg>
            `;
        };

        const previewCsvFile = async (index) => {
            const files = getFiles(currentFolder);
            const file = files[index];
            if (!file) return;

            const fileName = String(file.name || '');
            const isCsv = fileName.toLowerCase().endsWith('.csv') || String(file.type || '').toLowerCase().includes('csv');
            if (!isCsv) {
                previewState = {
                    folderId: currentFolder,
                    fileName,
                    error: 'Preview is available for CSV files. XLS/XLSX can be stored and replaced.',
                    chartSvg: '',
                    tableHtml: ''
                };
                renderLeafFiles(currentFolder);
                return;
            }

            try {
                const response = await fetch(file.url, { credentials: 'same-origin' });
                const text = await response.text();
                const parsed = parseCsvRows(text);
                if (parsed.length < 2) {
                    previewState = {
                        folderId: currentFolder,
                        fileName,
                        error: 'CSV has no data rows.',
                        chartSvg: '',
                        tableHtml: ''
                    };
                    renderLeafFiles(currentFolder);
                    return;
                }

                const headers = parsed[0].map((h) => String(h || '').trim());
                const rows = parsed.slice(1).map((row) => {
                    const obj = {};
                    headers.forEach((h, i) => {
                        obj[h] = row[i] || '';
                    });
                    return obj;
                });

                const hMap = headers.reduce((acc, h) => {
                    acc[String(h).toLowerCase()] = h;
                    return acc;
                }, {});
                const yearKey = hMap.year || null;
                const monthKey = hMap.month || null;
                const numKey = hMap.num || null;
                const denomKey = hMap.denom || null;
                const rateKey = hMap.rate || null;

                const points = rows.map((row, idx) => {
                    let y = null;
                    if (rateKey) {
                        y = toNumber(row[rateKey]);
                    }
                    if (!Number.isFinite(y) && numKey && denomKey) {
                        const n = toNumber(row[numKey]);
                        const d = toNumber(row[denomKey]);
                        if (Number.isFinite(n) && Number.isFinite(d) && d > 0) {
                            y = (n / d) * 100;
                        }
                    }
                    const x = [yearKey ? row[yearKey] : '', monthKey ? row[monthKey] : ''].join(' ').trim() || `Row ${idx + 1}`;
                    return Number.isFinite(y) ? { x, y } : null;
                }).filter(Boolean);

                const previewHeaders = headers.slice(0, 8);
                const previewRows = rows.slice(0, 24);
                const tableHtml = `
                    <table class="dm-file-table">
                        <thead>
                            <tr>${previewHeaders.map((h) => `<th>${esc(h)}</th>`).join('')}</tr>
                        </thead>
                        <tbody>
                            ${previewRows.map((row) => `<tr>${previewHeaders.map((h) => `<td>${esc(row[h] || '')}</td>`).join('')}</tr>`).join('')}
                        </tbody>
                    </table>
                `;

                previewState = {
                    folderId: currentFolder,
                    fileName,
                    error: '',
                    chartSvg: buildChartSvg(points),
                    tableHtml
                };
                renderLeafFiles(currentFolder);
            } catch (err) {
                previewState = {
                    folderId: currentFolder,
                    fileName,
                    error: 'Could not read CSV preview.',
                    chartSvg: '',
                    tableHtml: ''
                };
                renderLeafFiles(currentFolder);
            }
        };

        const renderExplorer = () => {
            const node = byId[currentFolder] || byId.all;
            const children = node.children || [];
            const term = (searchInput.value || '').trim().toLowerCase();
            const filteredChildren = !term
                ? children
                : children.filter((childId) => String(byId[childId].label || '').toLowerCase().includes(term));

            renderSideTree();
            renderBreadcrumb();

            if (totalEl) {
                totalEl.textContent = String(descendantLeafCount('all'));
            }

            listTitle.textContent = node.label || 'Organization Data';

            if (children.length) {
                listCount.textContent = `${filteredChildren.length} folder${filteredChildren.length === 1 ? '' : 's'}`;
                renderFolderCards(filteredChildren);
                return;
            }

            const files = getFiles(currentFolder);
            const filteredFiles = !term
                ? files
                : files.filter((row) => String(row && row.name ? row.name : '').toLowerCase().includes(term));
            listCount.textContent = `${filteredFiles.length} file${filteredFiles.length === 1 ? '' : 's'}`;
            renderLeafFiles(currentFolder);
        };

        const showSyncToast = (syncSummary) => {
            const existing = document.getElementById('dmSyncToast');
            if (existing) existing.remove();

            const count = (syncSummary && syncSummary.metrics_with_data) || 0;
            const green = (syncSummary && syncSummary.green) || 0;
            const yellow = (syncSummary && syncSummary.yellow) || 0;
            const red = (syncSummary && syncSummary.red) || 0;

            const toast = document.createElement('div');
            toast.id = 'dmSyncToast';
            toast.style.cssText = 'position:fixed;top:24px;right:24px;z-index:999999;background:#03283E;color:#fff;padding:18px 24px;border-radius:14px;font-size:14px;font-weight:500;box-shadow:0 12px 40px rgba(0,0,0,0.35);display:flex;flex-direction:column;gap:10px;max-width:380px;animation:dmToastIn 0.4s ease;';
            toast.innerHTML = '<div style="display:flex;align-items:center;gap:10px;">'
                + '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
                + '<strong style="font-size:15px;">Dashboard Synced!</strong>'
                + '</div>'
                + '<div style="font-size:13px;color:#b0c4d8;line-height:1.5;">'
                + '<strong style="color:#fff;">' + count + ' metric' + (count === 1 ? '' : 's') + '</strong> updated in the Quality Dashboard. '
                + '<span style="display:inline-flex;gap:8px;margin-left:4px;">'
                + '<span style="color:#22c55e;">\u25cf ' + green + '</span>'
                + '<span style="color:#eab308;">\u25cf ' + yellow + '</span>'
                + '<span style="color:#ef4444;">\u25cf ' + red + '</span>'
                + '</span>'
                + '</div>'
                + '<a href="/data-hub/#dashboard" style="display:inline-flex;align-items:center;gap:6px;color:#7ccae2;font-size:13px;font-weight:600;text-decoration:none;">View Quality Dashboard \u2192</a>';
            document.body.appendChild(toast);

            setTimeout(function() {
                toast.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-10px)';
                setTimeout(function() { toast.remove(); }, 500);
            }, 5000);
        };

        const updateSyncStatusBar = (syncSummary) => {
            let bar = app.querySelector('.dm-sync-status-bar');
            if (!bar) {
                bar = document.createElement('div');
                bar.className = 'dm-sync-status-bar';
                bar.style.cssText = 'display:flex;align-items:center;gap:10px;padding:10px 16px;margin:0 0 14px;background:linear-gradient(135deg,#edf7ed 0%,#e8f4fd 100%);border:1px solid #c3e0d0;border-radius:10px;font-size:13px;color:#1a5632;font-weight:500;';
                const header = app.querySelector('.dm-main-head');
                if (header && header.nextSibling) {
                    header.parentNode.insertBefore(bar, header.nextSibling);
                }
            }
            if (!syncSummary) return;
            const count = syncSummary.metrics_with_data || 0;
            const green = syncSummary.green || 0;
            const yellow = syncSummary.yellow || 0;
            const red = syncSummary.red || 0;
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
            bar.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1a8a4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>'
                + '<span>Dashboard synced at ' + esc(timeStr) + ' \u2014 <strong>' + count + '</strong> metric' + (count === 1 ? '' : 's') + ' active'
                + ' <span style="margin-left:6px;color:#16a34a;">\u25cf' + green + '</span>'
                + ' <span style="margin-left:4px;color:#ca8a04;">\u25cf' + yellow + '</span>'
                + ' <span style="margin-left:4px;color:#dc2626;">\u25cf' + red + '</span>'
                + '</span>'
                + '<a href="/data-hub/#dashboard" style="margin-left:auto;color:#1565a0;font-weight:600;font-size:12px;text-decoration:none;">Open Dashboard \u2192</a>';
        };

        const uploadFile = async (file) => {
            if (!file) return;
            const uploadUrl = app.getAttribute('data-upload-url') || '';
            const nonce = app.getAttribute('data-upload-nonce') || '';
            if (!uploadUrl || !nonce) {
                alert('Upload endpoint not configured.');
                return;
            }

            const form = new FormData();
            form.append('action', 'dm_upload_folder_file');
            form.append('_nonce', nonce);
            form.append('folder_id', currentFolder);
            form.append('replace_index', String(pendingReplaceIndex));
            form.append('file', file);

            try {
                const response = await fetch(uploadUrl, { method: 'POST', body: form });
                const json = await response.json();
                if (!json || !json.success) {
                    alert((json && json.data) ? json.data : 'Upload failed.');
                    return;
                }
                folderFiles[currentFolder] = Array.isArray(json.data && json.data.files) ? json.data.files : [];
                pendingReplaceIndex = -1;
                previewState = null;
                renderExplorer();

                // Show sync feedback
                if (json.data && json.data.synced) {
                    showSyncToast(json.data.sync_summary);
                    updateSyncStatusBar(json.data.sync_summary);
                }
            } catch (err) {
                alert('Upload failed.');
            }
        };

        if (sideTree) {
            sideTree.addEventListener('click', (e) => {
                const button = e.target.closest('[data-folder-id]');
                if (!button) return;
                const nextFolder = button.getAttribute('data-folder-id') || 'all';
                currentFolder = nextFolder;
                pendingReplaceIndex = -1;
                previewState = null;
                renderExplorer();
            });
        }

        if (breadcrumb) {
            breadcrumb.addEventListener('click', (e) => {
                const button = e.target.closest('[data-crumb]');
                if (!button) return;
                currentFolder = button.getAttribute('data-crumb') || 'all';
                pendingReplaceIndex = -1;
                previewState = null;
                renderExplorer();
            });
        }

        searchInput.addEventListener('input', renderExplorer);

        list.addEventListener('click', (e) => {
            const folderCard = e.target.closest('[data-open-folder]');
            if (folderCard) {
                currentFolder = folderCard.getAttribute('data-open-folder') || 'all';
                pendingReplaceIndex = -1;
                previewState = null;
                renderExplorer();
                return;
            }

            const uploadBtn = e.target.closest('[data-upload-trigger]');
            if (uploadBtn) {
                pendingReplaceIndex = -1;
                const input = list.querySelector('#dmUploadInput');
                if (input) input.click();
                return;
            }

            const downloadTemplateBtn = e.target.closest('[data-download-template]');
            if (downloadTemplateBtn) {
                const csv = buildTemplateCsv(currentFolder);
                downloadTextFile(currentFolder + '-template.csv', csv, 'text/csv;charset=utf-8;');
                return;
            }

            const replaceBtn = e.target.closest('[data-replace-index]');
            if (replaceBtn) {
                pendingReplaceIndex = parseInt(replaceBtn.getAttribute('data-replace-index') || '-1', 10);
                const input = list.querySelector('#dmUploadInput');
                if (input) input.click();
                return;
            }

            const previewBtn = e.target.closest('[data-preview-index]');
            if (previewBtn) {
                const idx = parseInt(previewBtn.getAttribute('data-preview-index') || '-1', 10);
                if (idx >= 0) {
                    previewCsvFile(idx);
                }
            }
        });

        list.addEventListener('change', (e) => {
            const input = e.target.closest('#dmUploadInput');
            if (!input || !input.files || !input.files.length) return;
            uploadFile(input.files[0]);
            input.value = '';
        });

        renderExplorer();
    })();
    </script>
    <?php
    return ob_get_clean();
