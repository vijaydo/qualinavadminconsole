<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>

<div class="qd-wizard-wrap">
    <section class="qd-dashboard-command" aria-label="Dashboard Reports">
        <div class="qd-dashboard-command-copy">
            <span class="qd-dashboard-eyebrow">Dashboard Reports</span>
            <h2>Quality Performance Explorer</h2>
        </div>
        <div class="qd-dashboard-report-actions">
            <label class="qd-dashboard-report-picker">
                <span class="qd-filter-label">Choose Report</span>
                <select class="qd-select" id="qdReportType"></select>
            </label>
            <button type="button" class="qd-report-create-btn" disabled aria-disabled="true" aria-label="Create report">
                <span aria-hidden="true">+</span>
            </button>
        </div>
    </section>

    <section class="qd-controls-panel" aria-label="Report controls">
        <div class="qd-top-filters">
            <div class="qd-filter-grid">
                <label class="qd-filter-control">
                    <span class="qd-filter-label">Analytics</span>
                    <select class="qd-select" id="qdFilterAnalytics">
                        <option>Dashboard</option>
                        <option>Benchmarking</option>
                        <option>Prioritization Report (Heat Map)</option>
                        <option>Comparative Benchmarking Report (Catepillar plot)</option>
                        <option>Run Chart</option>
                    </select>
                </label>

                <label class="qd-filter-control">
                    <span class="qd-filter-label">Year</span>
                    <select class="qd-select" id="qdFilterYear">
                        <option value="all">All Years</option>
                        <?php
                        // 2012 through the current year, newest first. The upper
                        // bound is the live current year, so 2027 appears in 2027.
                        $qd_year_now = (int) date( 'Y' );
                        for ( $qd_y = $qd_year_now; $qd_y >= 2012; $qd_y-- ) {
                            echo '<option value="' . esc_attr( $qd_y ) . '">' . esc_html( $qd_y ) . '</option>';
                        }
                        ?>
                    </select>
                </label>

                <label class="qd-filter-control">
                    <span class="qd-filter-label">Organization Type</span>
                    <select class="qd-select" id="qdFilterHospitalType">
                        <option value="all">All Types</option>
                        <option value="cah">Critical Access Hospital</option>
                        <option value="rural_pps">Rural PPS</option>
                        <option value="ipps_general_acute">IPPS General Acute</option>
                    </select>
                </label>

                <label class="qd-filter-control">
                    <span class="qd-filter-label">Hospital Size</span>
                    <select class="qd-select" id="qdFilterBedSize">
                        <option value="all">All Sizes</option>
                        <option value="1-10">1-10 Beds</option>
                        <option value="11-25">11-25 Beds</option>
                        <option value="26-50">26-50 Beds</option>
                        <option value="51-100">51-100 Beds</option>
                        <option value="101-plus">101+ Beds</option>
                    </select>
                </label>
            </div>

            <!-- Metrics filter retired. Kept
                 hidden so the dashboard scope pipeline still resolves it. -->
            <div id="qdMetricsDropdown" class="qd-multi-dropdown" style="display:none;">
                <button type="button" class="qd-multi-trigger">
                    <span class="qd-multi-label">All Metrics</span>
                </button>
                <div class="qd-multi-menu" role="listbox" aria-multiselectable="true"></div>
            </div>
        </div>
    </section>

    <section class="qd-services-row" aria-label="Quality Measures">
        <span class="qd-services-label">Quality Measures</span>
        <div class="qd-services-list" id="qdServicesList"></div>
    </section>

    <section class="qd-core-set-board" id="qdCoreSetBoard" aria-label="Measures report">
        <div class="qd-core-set-head">Measures Report</div>
        <div class="qd-core-actions">
            <button type="button" class="qd-core-action qd-core-download" id="qdDownloadPdf">Download PDF</button>
            <button type="button" class="qd-core-action qd-core-download" id="qdDownloadPng">Download PNG</button>
        </div>
        <div class="qd-core-report" id="qdCoreSetReport"></div>
    </section>

    <!-- RUN CHART INLINE VIEW -->
    <section class="qd-run-chart-section" id="qdRunChartSection" style="display:none;" aria-label="Run Charts">
        <div class="qd-run-chart-grid" id="qdRunChartGrid"></div>
    </section>
</div>
