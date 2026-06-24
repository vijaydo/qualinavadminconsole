<?php
/** @var array $def */
/** @var string $slug */
/** @var string $wrapperClass */
/** @var string $primaryButtonClass */
/** @var int $contextPostId */
/** @var bool $loggedIn */
/** @var array|null $initialResultPayload */

$scale = $def['scale'] ?? [1, 2, 3, 4, 5];
$legend = $def['scale_legend'] ?? ($def['scale_labels'] ?? []);
$sections = $def['sections'] ?? [];
$sectionCount = count($sections);
$totalQuestions = 0;
foreach ($sections as $s) {
  $totalQuestions += count($s['questions'] ?? []);
}
$estimatedMinutes = max(5, (int)ceil($totalQuestions * 0.5));
?>
<div class="<?php echo esc_attr($wrapperClass); ?> cah-assessment--pro is-restoring"
     data-assessment-slug="<?php echo esc_attr($slug); ?>"
     data-context-post-id="<?php echo esc_attr((string)$contextPostId); ?>"
     data-total-questions="<?php echo esc_attr((string)$totalQuestions); ?>"
     data-estimated-minutes="<?php echo esc_attr((string)$estimatedMinutes); ?>"
     <?php echo $loggedIn ? 'data-user-logged-in="1"' : ''; ?>>
  <?php if (!empty($initialResultPayload) && is_array($initialResultPayload)): ?>
    <script type="application/json" data-cah-initial-result><?php echo esc_html(wp_json_encode($initialResultPayload)); ?></script>
  <?php endif; ?>

  <div class="cah-overlay" data-cah-overlay hidden></div>

  <div class="cah-layout">
    <aside class="cah-sidebar" aria-label="Assessment progress">
      <button type="button" class="cah-sidebar__close" data-cah-close-drawer aria-label="Close section navigation">Close</button>

      <div class="cah-sidecard">
        <div class="cah-pie-wrap" aria-label="Assessment completion chart">
          <div class="cah-pie" data-cah-pie style="--cah-pct:0%;">
            <span class="cah-pie__text" data-cah-pie-text>0%</span>
          </div>
        </div>

        <div class="cah-progress" aria-label="Overall progress">
          <div class="cah-progress__top">
            <span class="cah-progress__label">Progress</span>
            <span class="cah-progress__count"><span data-cah-answered>0</span>/<span data-cah-total><?php echo (int)$totalQuestions; ?></span></span>
          </div>
          <div class="cah-progress__bar" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo (int)$totalQuestions; ?>" aria-valuenow="0">
            <div class="cah-progress__fill" style="width:0%"></div>
          </div>
          <div class="cah-progress__meta">
            <span><strong data-cah-remaining><?php echo (int)$totalQuestions; ?></strong> remaining</span>
            <span>Est. <strong data-cah-time-remaining><?php echo (int)$estimatedMinutes; ?></strong> min</span>
          </div>
        </div>
      </div>

      <nav class="cah-outline" aria-label="Assessment sections">
        <div class="cah-outline__title">Sections</div>
        <?php foreach ($sections as $index => $section):
          $sectionId = sanitize_key($section['id'] ?? '');
          $sectionTitle = $section['title'] ?? ('Section ' . ($index + 1));
          $sectionTotal = count($section['questions'] ?? []);
          if (!$sectionId) {
            continue;
          }
          ?>
          <a href="#cah-section-<?php echo esc_attr($sectionId); ?>" class="cah-outline__item" data-cah-outline-for="<?php echo esc_attr($sectionId); ?>">
            <span class="cah-outline__dot" aria-hidden="true"></span>
            <span class="cah-outline__label"><?php echo esc_html($sectionTitle); ?></span>
            <span class="cah-outline__meta"><span data-cah-section-answered="<?php echo esc_attr($sectionId); ?>">0</span>/<?php echo (int)$sectionTotal; ?></span>
            <span class="cah-outline__status" data-cah-section-status="<?php echo esc_attr($sectionId); ?>">Not started</span>
          </a>
        <?php endforeach; ?>
      </nav>
    </aside>

    <main class="cah-main">
      <h2 class="cah-main-title"><?php echo esc_html($def['title'] ?? 'Assessment'); ?></h2>
      <div class="cah-stepper">
        <button type="button" class="cah-drawer-toggle" data-cah-open-drawer aria-label="Open section navigation">Sections</button>
        <button type="button" class="cah-btn" data-cah-prev-section>Previous Section</button>
        <span class="cah-stepper__position" data-cah-section-position>Section 1 of <?php echo (int)$sectionCount; ?></span>
        <button type="button" class="cah-btn" data-cah-next-section>Next Section</button>
      </div>

      <?php if (!empty($def['instructions'])): ?>
        <details class="cah-notice-toggle">
          <summary data-cah-instructions-toggle>Show Instructions</summary>
          <div class="cah-notice">
            <?php echo wp_kses_post($def['instructions']); ?>
          </div>
        </details>
      <?php endif; ?>

      <div class="cah-message" aria-live="polite"></div>

      <div class="cah-sections">
        <?php foreach ($sections as $index => $section):
          $sectionId = sanitize_key($section['id'] ?? '');
          if (!$sectionId) {
            continue;
          }
          ?>
          <section class="cah-section<?php echo $index === 0 ? ' is-active' : ''; ?>" id="cah-section-<?php echo esc_attr($sectionId); ?>" data-section-id="<?php echo esc_attr($sectionId); ?>" data-section-index="<?php echo (int)$index; ?>" <?php echo $index === 0 ? '' : 'hidden'; ?>>
            <div class="cah-section__toggle">
              <span>
                <span class="cah-section__title"><?php echo esc_html($section['title'] ?? 'Section'); ?></span>
                <?php if (!empty($section['focus'])): ?>
                  <span class="cah-section__focus"><?php echo esc_html($section['focus']); ?></span>
                <?php endif; ?>
              </span>
              <span class="cah-section__count"><span data-cah-section-answered="<?php echo esc_attr($sectionId); ?>">0</span>/<?php echo (int)count($section['questions'] ?? []); ?></span>
            </div>
            <div class="cah-section__panel">
              <div class="cah-questions">
                <?php foreach (($section['questions'] ?? []) as $q):
                  $qid = sanitize_key($q['id'] ?? '');
                  if (!$qid) {
                    continue;
                  }
                  ?>
                  <article class="cah-qcard" data-qid="<?php echo esc_attr($qid); ?>">
                    <div class="cah-qcard__text">
                      <div class="cah-qcard__label"><?php echo esc_html($q['label'] ?? ''); ?></div>
                      <?php if (!empty($q['hint'])): ?>
                        <div class="cah-qcard__hint cah-muted"><?php echo esc_html($q['hint']); ?></div>
                      <?php endif; ?>
                    </div>

                    <div class="cah-likert" role="radiogroup" aria-label="<?php echo esc_attr($q['label'] ?? ''); ?>">
                      <?php foreach ($scale as $val): ?>
                        <label class="cah-pill">
                          <input class="cah-pill__input" type="radio" name="<?php echo esc_attr($qid); ?>" value="<?php echo esc_attr((string)$val); ?>">
                          <span class="cah-pill__num"><?php echo esc_html((string)$val); ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            </div>
          </section>
        <?php endforeach; ?>
      </div>

      <div class="cah-result"></div>

      <div class="cah-actions cah-actions--sticky">
        <div class="cah-actions__inner">
          <div class="cah-actions__left cah-muted">
            Complete all items to unlock scoring. Draft autosaves while you work.
            <span class="cah-actions__draft" data-cah-save-state-inline>Draft not saved yet</span>
          </div>
          <div class="cah-actions__right">
            <button type="button" class="cah-btn" data-cah-reset>
              Reset Answers
            </button>
            <button type="button" class="<?php echo esc_attr($primaryButtonClass); ?> cah-btn--wide" data-cah-submit>
              Save and Calculate Score
            </button>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>
