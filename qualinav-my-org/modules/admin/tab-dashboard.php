<?php
defined('ABSPATH') || exit;

$root = DTU_Config::get_root_folder();
?>

<div class="dtu-card">
    <h2>Configuration Overview</h2>

    <table class="widefat striped" style="max-width:650px;">
        <tbody>
            <tr>
                <th>Root Folder ID</th>
                <td>
                    <?php echo $root ? '<code>' . esc_html($root) . '</code>' : '<em style="color:red;">Not configured</em>'; ?>
                </td>
            </tr>
            <tr id="dtu-folder-status-row">
                <th>Folder Status</th>
                <td>
                    <span class="dtu-loading">Checking...</span>
                </td>
            </tr>
            <tr id="dtu-sa-status-row">
                <th>Service Account</th>
                <td>
                    <span class="dtu-loading">Checking...</span>
                </td>
            </tr>
        </tbody>
    </table>
</div>


<!-- ===============================
     UPLOAD SUMMARY (ASYNC)
================================ -->
<div class="dtu-card" style="margin-top:26px;">
    <h2>Upload Summary</h2>

    <div id="dtu-upload-summary-loading" class="dtu-loading">
        Loading upload summary…
    </div>

    <table id="dtu-upload-summary-table" class="widefat striped" style="max-width:650px; display:none;">
        <tbody>
            <tr><th>Total Files Uploaded</th><td id="dtu-total-files"></td></tr>
            <tr><th>Uploaded This Week</th><td id="dtu-files-week"></td></tr>
            <tr><th>Storage Used</th><td id="dtu-storage"></td></tr>
        </tbody>
    </table>
</div>


<!-- ===============================
     RECENT UPLOADS (ASYNC)
================================ -->
<div class="dtu-card" style="margin-top:26px;">
    <h2>Recent Uploads</h2>

    <div id="dtu-recent-loading" class="dtu-loading">
        Fetching latest uploads…
    </div>

    <table id="dtu-recent-table" class="widefat striped" style="max-width:650px; display:none;">
        <thead>
            <tr>
                <th>File</th>
                <th>User</th>
                <th>Site</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody id="dtu-recent-body"></tbody>
    </table>
</div>

<style>
.dtu-card {
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:10px;
    padding:22px 26px;
    max-width:760px;
    box-shadow:0 2px 6px rgba(0,0,0,.05);
}

.dtu-loading {
    padding:8px 0;
    font-style:italic;
    color:#666;
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const data = {
        action: "dtu_dashboard_analytics",
        _ajax_nonce: "<?php echo wp_create_nonce('dtu_dashboard_nonce'); ?>"
    };

    fetch(ajaxurl, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams(data)
    })
    .then(res => res.json())
    .then(res => {
        if (!res.success) {
            console.error("DTU Dashboard error:", res);
            return;
        }

        const d = res.data;

        // --------------------------
        // STATUS SECTION
        // --------------------------
        document.querySelector("#dtu-folder-status-row td").innerHTML = d.folder_html;
        document.querySelector("#dtu-sa-status-row td").innerHTML = d.sa_html;

        // --------------------------
        // UPLOAD SUMMARY
        // --------------------------
        document.querySelector("#dtu-upload-summary-loading").style.display = "none";
        document.querySelector("#dtu-upload-summary-table").style.display = "table";

        document.querySelector("#dtu-total-files").textContent = d.total_files;
        document.querySelector("#dtu-files-week").textContent = d.files_this_week;
        document.querySelector("#dtu-storage").textContent = d.storage_used;

        // --------------------------
        // RECENT UPLOADS
        // --------------------------
        const recentBody = document.querySelector("#dtu-recent-body");
        document.querySelector("#dtu-recent-loading").style.display = "none";

        if (d.recent.length === 0) {
            recentBody.innerHTML = '<tr><td colspan="4"><em>No uploads found.</em></td></tr>';
            document.querySelector("#dtu-recent-table").style.display = "table";
            return;
        }

        d.recent.forEach(f => {
            const row = document.createElement("tr");
            row.innerHTML = `
                <td>${f.name}</td>
                <td><code>${f.user}</code></td>
                <td><code>${f.site}</code></td>
                <td>${f.date}</td>
            `;
            recentBody.appendChild(row);
        });

        document.querySelector("#dtu-recent-table").style.display = "table";
    });
});
</script>
