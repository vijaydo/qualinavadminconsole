<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function dttc_has_shortcode( $content ) {
    return is_string( $content ) && has_shortcode( $content, 'dotank_table_chart' );
}

add_filter( 'the_posts', function ( $posts ) {
    if ( empty( $posts ) ) return $posts;

    foreach ( $posts as $post ) {
        if ( isset( $post->post_content ) && dttc_has_shortcode( $post->post_content ) ) {

            wp_enqueue_style( 'dttc-style' );
            wp_enqueue_script( 'dttc-chartjs' );
            wp_enqueue_script( 'dttc-jspdf' );
            wp_enqueue_script( 'dttc-script' );

            wp_localize_script( 'dttc-script', 'DTTC', array(
                'postId' => (int) $post->ID,
            ) );
            break;
        }
    }
    return $posts;
} );

add_shortcode( 'dotank_table_chart', function ( $atts ) {

    $atts = shortcode_atts(
        array(
            'title' => 'Do Tank – Table & Chart',
            'rows'  => 6,
            'id'    => '',
            'combined_label' => 'Series',
        ),
        $atts,
        'dotank_table_chart'
    );

    $title = esc_html( $atts['title'] );
    $rows  = max( 1, min( 50, absint( $atts['rows'] ) ) );

    $instance_id = sanitize_key( $atts['id'] );
    if ( empty( $instance_id ) ) {
        $instance_id = 'dttc_' . wp_generate_uuid4();
    }

    ob_start();
    ?>
    <div class="dttc-wrap" data-dttc-id="<?php echo esc_attr( $instance_id ); ?>" data-dttc-rows="<?php echo esc_attr( $rows ); ?>" data-dttc-combined-label="<?php echo esc_attr( $atts['combined_label'] ); ?>">
        <div class="dttc-card">
            <div class="dttc-head">
                <div class="dttc-title"><?php echo esc_html( $title ); ?></div>
                <div class="dttc-actions">
                    <div class="dttc-legend">
                        <span class="dttc-legend-label">Legend:</span>
                        <input type="text" class="dttc-legend-input" data-dttc-legend="combined" placeholder="Type your legend for the blue line" />
                        <input type="color" class="dttc-color" data-dttc-color="series" title="Series line colour" />
                        <input type="color" class="dttc-color" data-dttc-color="average" title="Median line colour" />
                        <span class="dttc-legend-fixed">Median</span>
                    </div>

                    <div class="dttc-action-group">
                        <button type="button" class="dttc-btn dttc-btn-ghost dttc-btn--sm" data-dttc-action="download-png">Download PNG</button>
                        <button type="button" class="dttc-btn dttc-btn-ghost dttc-btn--sm" data-dttc-action="download-pdf">Download PDF</button>
                        <button type="button" class="dttc-btn dttc-btn-ghost dttc-btn--sm" data-dttc-action="download">Download CSV</button>
                        <button type="button" class="dttc-btn dttc-btn-ghost dttc-btn--sm" data-dttc-action="open-import">Import CSV</button>
                        <button type="button" class="dttc-btn dttc-btn-ghost dttc-btn--sm" data-dttc-action="open-paste">Paste from Excel</button>
                    </div>

                    <div class="dttc-action-group">
                        <label class="dttc-check">
                            <input type="checkbox" data-dttc-toggle="minmax" />
                            <span>Min/Max</span>
                        </label>
                        <button type="button" class="dttc-btn dttc-btn-ghost dttc-btn--sm" data-dttc-action="add-row">+ Row</button>
                        <button type="button" class="dttc-btn dttc-btn-ghost dttc-btn--sm" data-dttc-action="clear">Clear</button>
                    </div>
                </div>
            </div>

            
            <div class="dttc-import-panel" data-dttc-import-panel hidden>
                <div class="dttc-import-card">
                    <div class="dttc-import-head">
                        <div class="dttc-import-title">Import CSV</div>
                        <button type="button" class="dttc-mini" data-dttc-action="close-import">Close</button>
                    </div>
                    <p class="dttc-import-help">CSV columns: <code>Time, Numerator, Denominator</code> (header row optional). Median is calculated automatically (median of Numerator).</p>
                    <input type="file" accept=".csv,text/csv" class="dttc-file" data-dttc-file />
                    <div class="dttc-import-actions">
                        <button type="button" class="dttc-btn" data-dttc-action="import-csv">Import into table</button>
                        <button type="button" class="dttc-btn dttc-btn-ghost" data-dttc-action="close-import">Cancel</button>
                    </div>
                </div>
            </div>

            <div class="dttc-paste-panel" data-dttc-paste-panel hidden>
                <div class="dttc-import-card">
                    <div class="dttc-import-head">
                        <div class="dttc-import-title">Paste from Excel / Google Sheets</div>
                        <button type="button" class="dttc-mini" data-dttc-action="close-paste">Close</button>
                    </div>
                    <p class="dttc-import-help">Paste rows like: <code>Label[TAB]A[TAB]B</code>. One row per line. Label is optional.</p>
                    <textarea class="dttc-textarea" rows="8" placeholder="Week 1	10	15&#10;Week 2	12	14" data-dttc-paste></textarea>
                    <div class="dttc-import-actions">
                        <button type="button" class="dttc-btn" data-dttc-action="apply-paste">Apply to table</button>
                        <button type="button" class="dttc-btn dttc-btn-ghost" data-dttc-action="close-paste">Cancel</button>
                    </div>
                </div>
            </div>

            <div class="dttc-table-scroll">
                <table class="dttc-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Numerator</th>
                            <th>Denominator</th>
                            <th>Median</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-dttc-body></tbody>
                </table>
            </div>

            <div class="dttc-chart-controls dttc-chart-controls--between">
                <input type="text" class="dttc-input dttc-input--sm" data-dttc-chart-title placeholder="Chart title" />
                <input type="text" class="dttc-input dttc-input--sm" data-dttc-axis-x placeholder="X axis title" />
                <input type="text" class="dttc-input dttc-input--sm" data-dttc-axis-y placeholder="Y axis title" />
            </div>

            
            <div class="dttc-chart">
                <canvas data-dttc-canvas></canvas>
            </div>

            <div class="dttc-notes" data-dttc-notes>
                <div class="dttc-notes-title">Insights</div>
                <div class="dttc-notes-body">Add at least 10 points to generate insights.</div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
} );
