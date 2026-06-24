(function () {
  function qs(el, sel) { return el.querySelector(sel); }
  function qsa(el, sel) { return Array.from(el.querySelectorAll(sel)); }

  function escapeHtml(str) {
    return String(str || '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function getSections(root) {
    return qsa(root, '.cah-section[data-section-id]');
  }

  function getActiveSection(root) {
    return qs(root, '.cah-section.is-active') || getSections(root)[0] || null;
  }

  function collectAnswers(root) {
    const answers = {};
    qsa(root, 'input[type="radio"]:checked').forEach((r) => {
      answers[r.name] = parseInt(r.value, 10);
    });
    return answers;
  }

  function requiredQuestions(root) {
    return qsa(root, '[data-qid]').map((x) => x.getAttribute('data-qid')).filter(Boolean);
  }

  function draftKey(root) {
    const slug = root.getAttribute('data-assessment-slug') || 'assessment';
    const post = root.getAttribute('data-context-post-id') || '0';
    return `cah_assess_draft_${slug}_${post}`;
  }

  function resultKey(root) {
    const slug = root.getAttribute('data-assessment-slug') || 'assessment';
    const post = root.getAttribute('data-context-post-id') || '0';
    return `cah_assess_result_${slug}_${post}`;
  }

  function resultIgnoreKey(root) {
    const slug = root.getAttribute('data-assessment-slug') || 'assessment';
    const post = root.getAttribute('data-context-post-id') || '0';
    return `cah_assess_result_ignore_${slug}_${post}`;
  }

  function setSaveState(root, text) {
    qsa(root, '[data-cah-save-state], [data-cah-save-state-inline]').forEach((el) => {
      el.textContent = text;
    });
  }

  function saveDraft(root) {
    const key = draftKey(root);
    const payload = { savedAt: Date.now(), answers: collectAnswers(root) };

    try {
      localStorage.setItem(key, JSON.stringify(payload));
      const dt = new Date(payload.savedAt);
      setSaveState(root, `Draft saved at ${dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`);
    } catch (e) {
      setSaveState(root, 'Draft save unavailable in this browser.');
    }
  }

  function loadDraft(root) {
    const key = draftKey(root);
    try {
      const raw = localStorage.getItem(key);
      if (!raw) return;
      const parsed = JSON.parse(raw);
      const answers = parsed && parsed.answers ? parsed.answers : {};

      Object.keys(answers).forEach((qid) => {
        const val = String(answers[qid]);
        const input = qs(root, `input[type="radio"][name="${qid}"][value="${val}"]`);
        if (input) input.checked = true;
      });

      if (parsed.savedAt) {
        const dt = new Date(parsed.savedAt);
        setSaveState(root, `Draft loaded from ${dt.toLocaleString()}`);
      }
    } catch (e) {
      setSaveState(root, 'Draft could not be loaded.');
    }
  }

  function updateSelectedPills(root) {
    qsa(root, '.cah-pill').forEach((l) => l.classList.remove('is-selected'));
    qsa(root, 'input[type="radio"]:checked').forEach((r) => {
      const label = r.closest('.cah-pill');
      if (label) label.classList.add('is-selected');
    });
  }

  function sectionQuestionState(section) {
    const total = qsa(section, '[data-qid]').length;
    const answered = qsa(section, 'input[type="radio"]:checked').length;
    return { total, answered };
  }

  function sectionStatusLabel(answered, total) {
    if (!answered) return 'Not started';
    if (answered >= total) return 'Complete';
    return 'In progress';
  }

  function syncActiveSectionUI(root, activeSection) {
    const sections = getSections(root);
    if (!sections.length || !activeSection) return;

    sections.forEach((section) => {
      const isActive = section === activeSection;
      section.classList.toggle('is-active', isActive);
      section.hidden = !isActive;
    });

    const activeId = activeSection.getAttribute('data-section-id');
    const activeIndex = sections.indexOf(activeSection);

    qsa(root, '[data-cah-outline-for]').forEach((item) => {
      item.classList.toggle('is-active', item.getAttribute('data-cah-outline-for') === activeId);
    });

    const currentSection = qs(root, '[data-cah-current-section]');
    if (currentSection) {
      const title = qs(activeSection, '.cah-section__title');
      currentSection.textContent = title ? title.textContent : `Section ${activeIndex + 1}`;
    }

    const position = qs(root, '[data-cah-section-position]');
    if (position) position.textContent = `Section ${activeIndex + 1} of ${sections.length}`;

    const prevBtn = qs(root, '[data-cah-prev-section]');
    const nextBtn = qs(root, '[data-cah-next-section]');
    if (prevBtn) prevBtn.disabled = activeIndex <= 0;
    if (nextBtn) nextBtn.disabled = activeIndex >= sections.length - 1;
  }

  function activateSectionById(root, sid) {
    if (!sid) return;
    const target = qs(root, `#cah-section-${sid}`);
    if (!target) return;
    syncActiveSectionUI(root, target);
  }

  function activateSectionByIndex(root, index) {
    const sections = getSections(root);
    if (!sections.length) return;
    const safe = Math.max(0, Math.min(index, sections.length - 1));
    syncActiveSectionUI(root, sections[safe]);
  }

  function updateSectionProgress(root) {
    getSections(root).forEach((section) => {
      const sid = section.getAttribute('data-section-id');
      if (!sid) return;

      const state = sectionQuestionState(section);
      qsa(root, `[data-cah-section-answered="${sid}"]`).forEach((el) => {
        el.textContent = String(state.answered);
      });

      const statusText = sectionStatusLabel(state.answered, state.total);
      const statusEl = qs(root, `[data-cah-section-status="${sid}"]`);
      if (statusEl) statusEl.textContent = statusText;

      const outlineItem = qs(root, `[data-cah-outline-for="${sid}"]`);
      if (!outlineItem) return;
      outlineItem.classList.toggle('is-complete', state.total > 0 && state.answered === state.total);
      outlineItem.classList.toggle('is-started', state.answered > 0 && state.answered < state.total);
    });
  }

  function updateCurrentQuestion(root) {
    const order = requiredQuestions(root);
    const answers = collectAnswers(root);
    const total = order.length;
    let current = total;

    for (let i = 0; i < order.length; i += 1) {
      if (!(order[i] in answers)) {
        current = i + 1;
        break;
      }
    }

    const label = qs(root, '[data-cah-current-question]');
    if (label) label.textContent = `Question ${current} of ${total}`;
  }

  function updateProgress(root) {
    const total = parseInt(root.getAttribute('data-total-questions') || '0', 10);
    const answered = Object.keys(collectAnswers(root)).length;
    const remaining = Math.max(0, total - answered);
    const estimatedMinutes = parseInt(root.getAttribute('data-estimated-minutes') || '5', 10);
    const timeRemaining = Math.max(1, Math.ceil((remaining / Math.max(total, 1)) * estimatedMinutes));

    const answeredEl = qs(root, '[data-cah-answered]');
    const totalEl = qs(root, '[data-cah-total]');
    const remainingEl = qs(root, '[data-cah-remaining]');
    const timeEl = qs(root, '[data-cah-time-remaining]');
    if (answeredEl) answeredEl.textContent = String(answered);
    if (totalEl) totalEl.textContent = String(total);
    if (remainingEl) remainingEl.textContent = String(remaining);
    if (timeEl) timeEl.textContent = String(timeRemaining);

    const pct = total ? Math.round((answered / total) * 100) : 0;
    const fill = qs(root, '.cah-progress__fill');
    if (fill) fill.style.width = `${pct}%`;

    const bar = qs(root, '.cah-progress__bar');
    if (bar) bar.setAttribute('aria-valuenow', String(answered));

    const pie = qs(root, '[data-cah-pie]');
    if (pie) pie.style.setProperty('--cah-pct', `${pct}%`);
    const pieText = qs(root, '[data-cah-pie-text]');
    if (pieText) pieText.textContent = `${pct}%`;

    const btn = qs(root, '[data-cah-submit]');
    if (btn) btn.disabled = !(total && answered === total);

    updateSectionProgress(root);
    updateCurrentQuestion(root);
  }

  function validate(root) {
    const req = requiredQuestions(root);
    const ans = collectAnswers(root);
    const missing = req.filter((qid) => !(qid in ans));
    return { ok: missing.length === 0, missing, answers: ans };
  }

  function saveResultSnapshot(root, payload) {
    try {
      localStorage.setItem(resultKey(root), JSON.stringify({
        savedAt: Date.now(),
        payload: payload
      }));
    } catch (e) {
      // no-op
    }
  }

  function loadResultSnapshot(root) {
    try {
      const raw = localStorage.getItem(resultKey(root));
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== 'object') return null;
      if (!parsed.payload || typeof parsed.payload !== 'object') return null;
      return parsed.payload;
    } catch (e) {
      return null;
    }
  }

  function clearResultSnapshot(root) {
    try {
      localStorage.removeItem(resultKey(root));
    } catch (e) {
      // no-op
    }
  }

  function loadInitialResultPayload(root) {
    const el = qs(root, 'script[data-cah-initial-result]');
    if (!el) return null;
    try {
      const parsed = JSON.parse(el.textContent || '{}');
      if (!parsed || typeof parsed !== 'object') return null;
      if (!parsed.section_scores || typeof parsed.section_scores !== 'object') return null;
      return parsed;
    } catch (e) {
      return null;
    }
  }

  function setResultRestoreIgnored(root, ignored) {
    try {
      if (ignored) {
        localStorage.setItem(resultIgnoreKey(root), '1');
      } else {
        localStorage.removeItem(resultIgnoreKey(root));
      }
    } catch (e) {
      // no-op
    }
  }

  function isResultRestoreIgnored(root) {
    try {
      return localStorage.getItem(resultIgnoreKey(root)) === '1';
    } catch (e) {
      return false;
    }
  }

  async function fetchLatestSubmission(root) {
    const slug = root.getAttribute('data-assessment-slug');
    const postId = parseInt(root.getAttribute('data-context-post-id') || '0', 10);
    if (!slug || !CAH_ASSESS.latestUrl) return null;

    const url = new URL(CAH_ASSESS.latestUrl, window.location.origin);
    url.searchParams.set('assessment_slug', slug);
    if (postId > 0) {
      url.searchParams.set('context_post_id', String(postId));
    }

    const res = await fetch(url.toString(), {
      method: 'GET',
      headers: {
        'X-WP-Nonce': CAH_ASSESS.nonce
      }
    });
    if (!res.ok) return null;
    const data = await res.json();
    if (!data || !data.found) return null;
    return data;
  }

  function resetAssessment(root) {
    root.classList.remove('is-submitted');
    root.classList.remove('is-results-only');
    root._cahLastResult = null;
    clearResultSnapshot(root);
    setResultRestoreIgnored(root, true);

    qsa(root, 'input[type="radio"]:checked').forEach((input) => {
      input.checked = false;
    });

    const msg = qs(root, '.cah-message');
    if (msg) msg.innerHTML = '';

    const result = qs(root, '.cah-result');
    if (result) result.innerHTML = '';

    try {
      localStorage.removeItem(draftKey(root));
    } catch (e) {
      // no-op
    }

    setSaveState(root, 'Draft reset.');
    updateSelectedPills(root);
    updateProgress(root);
    activateSectionByIndex(root, 0);
  }

  function syncInstructionsToggleText(root) {
    const details = qs(root, '.cah-notice-toggle');
    const summary = qs(root, '[data-cah-instructions-toggle]');
    if (!details || !summary) return;
    summary.textContent = details.open ? 'Hide Instructions' : 'Show Instructions';
  }

  function openDrawer(root) {
    root.classList.add('is-drawer-open');
    const overlay = qs(root, '[data-cah-overlay]');
    if (overlay) overlay.hidden = false;
  }

  function closeDrawer(root) {
    root.classList.remove('is-drawer-open');
    const overlay = qs(root, '[data-cah-overlay]');
    if (overlay) overlay.hidden = true;
  }

  async function saveSubmission(root) {
    const slug = root.getAttribute('data-assessment-slug');
    const postId = parseInt(root.getAttribute('data-context-post-id') || '0', 10);

    if (CAH_ASSESS.requireLogin === 1 && !root.getAttribute('data-user-logged-in')) {
      throw new Error('Login required to submit this assessment.');
    }

    const v = validate(root);
    if (!v.ok) {
      throw new Error('Please answer all questions before submitting.');
    }

    const res = await fetch(CAH_ASSESS.restUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': CAH_ASSESS.nonce
      },
      body: JSON.stringify({
        assessment_slug: slug,
        context_post_id: postId,
        answers: v.answers
      })
    });

    const data = await res.json();
    if (!res.ok) {
      throw new Error((data && data.message) ? data.message : 'Save failed.');
    }

    try {
      localStorage.removeItem(draftKey(root));
    } catch (e) {
      // no-op
    }

    setSaveState(root, 'Submitted successfully.');

    return data;
  }

  function recommendationProfile(assessmentSlug) {
    return assessmentSlug === 'org-assessment' ? 'org' : 'readiness';
  }

  function recommendationFromAverage(avg, assessmentSlug) {
    const profile = recommendationProfile(assessmentSlug);

    if (profile === 'org') {
      if (avg >= 4.5) return 'Maintain high-performing governance and share successful workflows across departments.';
      if (avg >= 4.0) return 'Close minor organizational gaps and continue routine quality monitoring.';
      if (avg >= 3.0) return 'Build a 30-day organizational action plan with owners, milestones, and evidence tracking.';
      return 'Escalate to leadership and launch immediate corrective actions with weekly review.';
    }

    if (avg >= 4.5) return 'Maintain survey-ready controls and continue mock tracer cadence.';
    if (avg >= 4.0) return 'Address minor readiness gaps and validate evidence/document control.';
    if (avg >= 3.0) return 'Execute a 30-day readiness remediation plan with accountable owners.';
    return 'Initiate urgent survey-readiness intervention and run focused tracer follow-up.';
  }

  function overallRecommendation(status, overall, assessmentSlug) {
    const profile = recommendationProfile(assessmentSlug);

    if (profile === 'org') {
      if (status === 'compliant' || overall >= 4.0) {
        return 'Organization is stable. Sustain results and standardize best practices system-wide.';
      }
      if (status === 'partial' || overall >= 3.0) {
        return 'Target medium-risk organizational gaps and execute a 30-day improvement plan.';
      }
      return 'High organizational risk. Escalate immediately and track corrective actions weekly.';
    }

    if (status === 'compliant' || overall >= 4.0) {
      return 'Readiness is strong. Maintain mock survey cadence and keep evidence current.';
    }
    if (status === 'partial' || overall >= 3.0) {
      return 'Readiness is partial. Prioritize medium-risk findings and complete a 30-day remediation plan.';
    }
    return 'Readiness is at risk. Escalate immediately, run focused mock tracers, and verify closure weekly.';
  }

  function renderResult(root, payload) {
    const box = qs(root, '.cah-result');
    if (!box) return;

    const status = payload.status || '';
    const overall = Number(payload.overall_score || 0);

    const statusLabel = status === 'compliant'
      ? 'Compliant'
      : status === 'partial'
        ? 'Partial compliance'
        : 'At risk';
    const assessmentSlug = String(payload.assessment_slug || root.getAttribute('data-assessment-slug') || '');

    const sectionScores = payload.section_scores || {};
    let cards = '';
    Object.keys(sectionScores).forEach((sid) => {
      const s = sectionScores[sid];
      const avg = Number(s.average || 0);
      const pct = Math.max(0, Math.min(100, Math.round((avg / 5) * 100)));
      const rec = recommendationFromAverage(avg, assessmentSlug);
      cards += `
        <div class="cah-qcard">
          <div>
            <div class="cah-qcard__label">${escapeHtml(s.title)}</div>
            <div class="cah-muted">Score: ${avg.toFixed(2)}</div>
            <div class="cah-result__tip">${escapeHtml(rec)}</div>
          </div>
          <div class="cah-qcard__donut" style="--cah-pct:${pct}%;">
            <span>${avg.toFixed(2)}</span>
          </div>
        </div>
      `;
    });

    const overallRec = overallRecommendation(status, overall, assessmentSlug);

    box.innerHTML = `
      <div class="cah-qcard cah-result__overall">
        <div>
          <div class="cah-qcard__label">${escapeHtml(statusLabel)} - Overall score</div>
          <div class="cah-muted">Total score: ${overall.toFixed(2)}</div>
          <div class="cah-result__tip">${escapeHtml(overallRec)}</div>
        </div>
        <div class="cah-badge">${overall.toFixed(2)}</div>
      </div>
      <div class="cah-scoregrid">
        ${cards}
      </div>
      <div class="cah-result__actions">
        <button type="button" class="cah-btn" data-cah-download-pdf>Download PDF</button>
        <button type="button" class="cah-btn" data-cah-save-mydata>Save to My Data</button>
        <button type="button" class="cah-btn" data-cah-reset-inline>Reset Assessment</button>
      </div>
    `;

    box.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function downloadResultsPdf(root) {
    const payload = root._cahLastResult;
    if (!payload) return;

    const titleEl = qs(root, '.cah-main-title');
    const title = titleEl ? titleEl.textContent.trim() : 'Assessment Results';
    const status = String(payload.status || '');
    const statusLabel = status === 'compliant' ? 'Compliant' : (status === 'partial' ? 'Partial compliance' : 'At risk');
    const overall = Number(payload.overall_score || 0).toFixed(2);
    const sections = payload.section_scores || {};
    const assessmentSlug = String(payload.assessment_slug || root.getAttribute('data-assessment-slug') || '');
    const overallRec = overallRecommendation(status, Number(payload.overall_score || 0), assessmentSlug);

    let rows = '';
    Object.keys(sections).forEach((sid) => {
      const s = sections[sid] || {};
      const avg = Number(s.average || 0);
      rows += `<tr><td>${escapeHtml(s.title || sid)}</td><td>${avg.toFixed(2)}</td><td>${escapeHtml(recommendationFromAverage(avg, assessmentSlug))}</td></tr>`;
    });

    const now = new Date();
    const html = `
<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <title>${escapeHtml(title)} - Results</title>
    <style>
      body { font-family: Arial, sans-serif; margin: 24px; color: #111; }
      h1 { margin: 0 0 12px; font-size: 24px; }
      .meta { margin-bottom: 16px; color: #444; }
      .overall { border: 1px solid #ddd; border-radius: 8px; padding: 12px; margin-bottom: 16px; }
      table { width: 100%; border-collapse: collapse; margin-top: 14px; }
      th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
      th { background: #f5f5f5; }
      .rec { margin-top: 8px; color: #222; }
    </style>
  </head>
  <body>
    <h1>${escapeHtml(title)}</h1>
    <div class="meta">Generated: ${escapeHtml(now.toLocaleString())}</div>
    <div class="overall">
      <strong>${escapeHtml(statusLabel)} - Overall score:</strong> ${escapeHtml(overall)}
      <div class="rec">${escapeHtml(overallRec)}</div>
    </div>
    <table>
      <thead><tr><th>Section</th><th>Score</th><th>Recommended Action</th></tr></thead>
      <tbody>${rows}</tbody>
    </table>
  </body>
</html>`;

    const win = window.open('', '_blank', 'width=900,height=800');
    if (!win) return;
    win.document.open();
    win.document.write(html);
    win.document.close();
    win.focus();
    win.print();
  }

  async function saveToMyData(root, btn) {
    const payload = root._cahLastResult;
    if (!payload) return;
    if (!CAH_ASSESS.ajaxUrl) return;

    const titleEl = qs(root, '.cah-main-title');
    const title = titleEl ? titleEl.textContent.trim() : 'Assessment';
    const slug = String(payload.assessment_slug || root.getAttribute('data-assessment-slug') || '');

    btn.disabled = true;
    const oldText = btn.textContent;
    btn.textContent = 'Saving...';

    try {
      const body = new URLSearchParams({
        action: 'save_assessment_mydata',
        nonce: CAH_ASSESS.ajaxNonce,
        assessment_slug: slug,
        assessment_title: title,
        overall_score: String(payload.overall_score || 0),
        status: String(payload.status || ''),
        section_scores: JSON.stringify(payload.section_scores || {})
      });

      const res = await fetch(CAH_ASSESS.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      });

      const data = await res.json();
      if (data.success) {
        btn.textContent = 'Saved!';
        btn.style.background = '#53a661';
        btn.style.color = 'white';
      } else {
        btn.textContent = oldText;
        btn.disabled = false;
        alert(data.data?.message || 'Could not save. Please try again.');
      }
    } catch (err) {
      btn.textContent = oldText;
      btn.disabled = false;
      alert('Could not save. Please try again.');
    }
  }

  function applyResultMode(root, payload, saveStateText) {
    root.classList.add('is-submitted');
    root.classList.add('is-results-only');
    root._cahLastResult = payload;
    renderResult(root, payload);
    if (saveStateText) {
      setSaveState(root, saveStateText);
    }
  }

  async function init(root) {
    let saveTimer = null;
    root.classList.add('is-restoring');

    loadDraft(root);
    updateSelectedPills(root);
    activateSectionByIndex(root, 0);
    updateProgress(root);
    syncInstructionsToggleText(root);

    const ignored = isResultRestoreIgnored(root);
    const initialPayload = ignored ? null : loadInitialResultPayload(root);
    const localSavedResult = ignored ? null : loadResultSnapshot(root);
    const immediateResult = initialPayload || localSavedResult;

    if (immediateResult) {
      if (!localSavedResult && initialPayload) {
        saveResultSnapshot(root, initialPayload);
      }
      applyResultMode(root, immediateResult, 'Loaded latest submitted result.');
      root.classList.remove('is-restoring');

      fetchLatestSubmission(root).then((latest) => {
        if (!latest) return;
        saveResultSnapshot(root, latest);
        applyResultMode(root, latest, 'Loaded latest submitted result.');
      }).catch(() => {
        // Keep local snapshot rendering if remote refresh fails.
      });
    } else {
      let savedResult = null;
      if (!ignored) {
        try {
          savedResult = await fetchLatestSubmission(root);
        } catch (e) {
          savedResult = null;
        }
        if (savedResult) {
          saveResultSnapshot(root, savedResult);
        }
      }

      if (savedResult) {
        applyResultMode(root, savedResult, 'Loaded latest submitted result.');
      }
      root.classList.remove('is-restoring');
    }

    root.addEventListener('change', function (e) {
      const t = e.target;
      if (!t || t.type !== 'radio') return;

      updateSelectedPills(root);
      updateProgress(root);

      if (saveTimer) window.clearTimeout(saveTimer);
      saveTimer = window.setTimeout(() => saveDraft(root), 180);
    });

    root.addEventListener('click', function (e) {
      const outline = e.target.closest('[data-cah-outline-for]');
      if (outline) {
        e.preventDefault();
        activateSectionById(root, outline.getAttribute('data-cah-outline-for'));
        closeDrawer(root);
        return;
      }

      if (e.target.closest('[data-cah-open-drawer]')) {
        openDrawer(root);
        return;
      }

      if (e.target.closest('[data-cah-close-drawer]') || e.target.closest('[data-cah-overlay]')) {
        closeDrawer(root);
        return;
      }

      if (e.target.closest('[data-cah-prev-section]')) {
        const sections = getSections(root);
        const current = getActiveSection(root);
        const idx = Math.max(0, sections.indexOf(current) - 1);
        activateSectionByIndex(root, idx);
        return;
      }

      if (e.target.closest('[data-cah-next-section]')) {
        const sections = getSections(root);
        const current = getActiveSection(root);
        const idx = Math.min(sections.length - 1, sections.indexOf(current) + 1);
        activateSectionByIndex(root, idx);
        return;
      }

      if (e.target.closest('[data-cah-download-pdf]')) {
        downloadResultsPdf(root);
        return;
      }

      if (e.target.closest('[data-cah-save-mydata]')) {
        saveToMyData(root, e.target.closest('[data-cah-save-mydata]'));
        return;
      }

      if (e.target.closest('[data-cah-reset-inline]')) {
        if (!window.confirm('Reset all answers and clear your draft?')) return;
        resetAssessment(root);
      }
    });

    const details = qs(root, '.cah-notice-toggle');
    if (details) {
      details.addEventListener('toggle', function () {
        syncInstructionsToggleText(root);
      });
    }

    const submitBtn = qs(root, '[data-cah-submit]');
    const resetBtn = qs(root, '[data-cah-reset]');

    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        if (!window.confirm('Reset all answers and clear your draft?')) return;
        resetAssessment(root);
      });
    }

    if (submitBtn) {
      submitBtn.addEventListener('click', async function () {
        const msg = qs(root, '.cah-message');
        if (msg) msg.innerHTML = '';

        submitBtn.disabled = true;
        const oldText = submitBtn.textContent;
        submitBtn.textContent = 'Submitting...';

        try {
          const payload = await saveSubmission(root);
          applyResultMode(root, payload, null);
          setResultRestoreIgnored(root, false);
          saveResultSnapshot(root, payload);
        } catch (err) {
          if (msg) msg.innerHTML = `<div class="cah-error">${escapeHtml(err.message || 'Error')}</div>`;
        } finally {
          submitBtn.textContent = oldText;
          updateProgress(root);
        }
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    qsa(document, '.cah-assessment').forEach(init);
  });
})();
