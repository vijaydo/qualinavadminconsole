# Qualinav Data Hub Repo

Repository for the Qualinav My Org WordPress plugin work, with Data Hub as the current focus.

## Checkpoint: Quality Measures owns measure workflows

Date: 19 June 2026  
Plugin version: 0.5.26  
Commit baseline: `080ca46`

This checkpoint marks the point where **Quality Measures** is the canonical user-facing workspace for MBQIP and HACs & HAIs measures.

Confirmed behavior:

- Data Hub opens to **Quality Measures** by default.
- MBQIP and HACs & HAIs are no longer shown as separate sidebar pages.
- Old MBQIP and HACs & HAIs routes resolve into Quality Measures where possible.
- Quality Measures owns the user workflow for:
  - manual entry
  - saved assessments
  - archive
  - raw data
  - run charts
  - goals
  - ownership
- Universal Workbook and Data Ownership remains separate for workbook downloads/uploads, current workbook generation, ownership dashboards, and measure coverage.
- Measure Coverage controls which measures appear in Quality Measures and the Universal Workbook.
- Individual per-measure upload/template controls have been removed from Quality Measures.
- The Universal Workbook is now the only supported upload route for bulk/import workflows.

Important note:

The older MBQIP and HACs & HAIs internals have **not** been deleted. They are still used underneath Quality Measures for storage, rendering, Universal Workbook parsing, run charts, goals, ownership, saved assessments, and raw data. Do not remove those internals until the dependencies have been refactored safely.

Verification completed for this checkpoint:

- `php -l qualinav-my-org.php`
- `php -l modules/data-hub/templates/page-data-management.php`
- inline Data Hub JavaScript parsed with `node --check`
- Browser/user verification that Quality Measures manual entry, saved assessments, archive, raw data, run charts, and Universal Workbook upload/download still work after removing individual measure uploads.

## Checkpoint: My Org decoupled from bundled File Manager

Date: 19 June 2026  
Plugin version: 0.5.77

This checkpoint stops the My Org plugin from booting the bundled `modules/do-tank-filemanager/` copy on every request. Data Hub Drive loading now uses explicit/site-level autoload paths or the standalone `wp-content/plugins/do-tank-filemanager/` plugin when present, rather than the internal bundled copy.

Reason:

- The bundled File Manager vendor tree was incomplete and caused a production fatal when WordPress tried to load My Org.
- My Org/Data Hub only need focused Drive mirroring behavior, not the full File Manager UI module at startup.
- The standalone File Manager plugin remains separate for any feature that truly needs it.

Verification completed for this checkpoint:

- `php -l qualinav-my-org.php`
- `php -l modules/data-hub/includes/drive-service.php`
- `php -l modules/data-hub/templates/page-data-management.php`
- full plugin PHP lint excluding `vendor/`
- PHP-level Data Hub Drive SDK load check using the standalone File Manager vendor path
