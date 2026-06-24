(function () {
  'use strict';

  function escHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // =========================
  // Stats & Insights (from dttc.js)
  // =========================
  function median(values) {
    var arr = values
      .filter(function (v) { return v !== null; })
      .slice()
      .sort(function (a, b) { return a - b; });
    var n = arr.length;
    if (!n) return null;
    var mid = Math.floor(n / 2);
    if (n % 2) return arr[mid];
    return (arr[mid - 1] + arr[mid]) / 2;
  }

  function computeStatsA(rows) {
    var arr = rows
      .map(function (r) { return (r && r.a !== null) ? r.a : null; })
      .filter(function (v) { return v !== null; })
      .slice()
      .sort(function (a, b) { return a - b; });
    if (!arr.length) return { median: null, min: null, max: null };
    var mid = Math.floor(arr.length / 2);
    var med = (arr.length % 2) ? arr[mid] : (arr[mid - 1] + arr[mid]) / 2;
    return { median: med, min: arr[0], max: arr[arr.length - 1] };
  }

  function detectShift(series, med) {
    if (med === null) return { len: 0, side: 0 };
    var bestLen = 0, bestSide = 0, curLen = 0, curSide = 0;
    for (var i = 0; i < series.length; i++) {
      var v = series[i];
      if (v === null) { curLen = 0; curSide = 0; continue; }
      if (v === med) continue;
      var side = (v > med) ? 1 : -1;
      if (side === curSide) curLen++;
      else { curSide = side; curLen = 1; }
      if (curLen > bestLen) { bestLen = curLen; bestSide = curSide; }
    }
    return { len: bestLen, side: bestSide };
  }

  function detectTrend(series) {
    var bestUp = 0, bestDown = 0, up = 1, down = 1;
    for (var i = 1; i < series.length; i++) {
      var prev = series[i - 1], cur = series[i];
      if (prev === null || cur === null) { up = 1; down = 1; continue; }
      if (cur === prev) continue;
      if (cur > prev) { up++; down = 1; } else { down++; up = 1; }
      if (up > bestUp) bestUp = up;
      if (down > bestDown) bestDown = down;
    }
    return { up: bestUp, down: bestDown };
  }

  var RUN_LIMITS = {
    10:{lo:3,hi:9}, 11:{lo:3,hi:10}, 12:{lo:3,hi:11}, 13:{lo:4,hi:11}, 14:{lo:4,hi:12},
    15:{lo:5,hi:12}, 16:{lo:5,hi:13}, 17:{lo:5,hi:13}, 18:{lo:6,hi:14}, 19:{lo:6,hi:15},
    20:{lo:6,hi:16}, 21:{lo:7,hi:16}, 22:{lo:7,hi:17}, 23:{lo:7,hi:17}, 24:{lo:8,hi:18},
    25:{lo:8,hi:18}, 26:{lo:9,hi:19}, 27:{lo:10,hi:19}, 28:{lo:10,hi:20}, 29:{lo:10,hi:20},
    30:{lo:11,hi:21}, 31:{lo:11,hi:22}, 32:{lo:11,hi:23}, 33:{lo:12,hi:23}, 34:{lo:12,hi:24},
    35:{lo:12,hi:24}, 36:{lo:13,hi:25}, 37:{lo:13,hi:25}, 38:{lo:14,hi:26}, 39:{lo:14,hi:26},
    40:{lo:15,hi:27}, 41:{lo:15,hi:27}, 42:{lo:16,hi:28}, 43:{lo:16,hi:28}, 44:{lo:17,hi:29},
    45:{lo:17,hi:30}, 46:{lo:17,hi:31}, 47:{lo:18,hi:31}, 48:{lo:18,hi:32}, 49:{lo:19,hi:32},
    50:{lo:19,hi:33}, 51:{lo:20,hi:33}, 52:{lo:20,hi:34}, 53:{lo:21,hi:34}, 54:{lo:21,hi:35},
    55:{lo:22,hi:35}, 56:{lo:22,hi:36}, 57:{lo:23,hi:36}, 58:{lo:23,hi:37}, 59:{lo:24,hi:38},
    60:{lo:24,hi:38}
  };

  function countRuns(series, med) {
    if (med === null) return { runs: 0, nNonMedian: 0 };
    var sides = [];
    for (var i = 0; i < series.length; i++) {
      var v = series[i];
      if (v === null || v === med) continue;
      sides.push(v > med ? 1 : -1);
    }
    var n = sides.length;
    if (!n) return { runs: 0, nNonMedian: 0 };
    var crossings = 0;
    for (var j = 1; j < n; j++) {
      if (sides[j] !== sides[j - 1]) crossings++;
    }
    return { runs: crossings + 1, nNonMedian: n };
  }

  function quantile(sortedArr, q) {
    var pos = (sortedArr.length - 1) * q;
    var base = Math.floor(pos);
    var rest = pos - base;
    if (sortedArr[base + 1] !== undefined) {
      return sortedArr[base] + rest * (sortedArr[base + 1] - sortedArr[base]);
    }
    return sortedArr[base];
  }

  function detectAstronomical(series) {
    var arr = series.filter(function (v) { return v !== null; }).slice().sort(function (a, b) { return a - b; });
    if (arr.length < 8) return null;
    var q1 = quantile(arr, 0.25);
    var q3 = quantile(arr, 0.75);
    var iqr = q3 - q1;
    if (iqr === 0) return null;
    var low = q1 - 1.5 * iqr;
    var high = q3 + 1.5 * iqr;
    if (arr[0] < low) return { type: 'low', value: arr[0] };
    if (arr[arr.length - 1] > high) return { type: 'high', value: arr[arr.length - 1] };
    return null;
  }

  function buildInsights(series) {
    var nonNull = series.filter(function (v) { return v !== null; });
    if (nonNull.length < 10) {
      return {
        badge: { label: 'Collect more data', tone: 'neutral' },
        headline: 'Not enough data yet',
        whats: 'At least 10 data points are needed to reliably detect non-random patterns.',
        why: 'With fewer points, apparent patterns can be misleading.',
        evidence: ['Add more data points to enable full run chart analysis.'],
        action: 'Continue monitoring and collecting data.'
      };
    }

    var med = median(series);
    var shift = detectShift(series, med);
    var trend = detectTrend(series);
    var runsInfo = countRuns(series, med);
    var limits = RUN_LIMITS[runsInfo.nNonMedian] || null;
    var astro = detectAstronomical(series);

    var shiftDetected = shift.len >= 6;
    var shiftUp = shiftDetected && shift.side === 1;
    var shiftDown = shiftDetected && shift.side === -1;
    var trendUp = trend.up >= 5;
    var trendDown = trend.down >= 5;
    var runsTooFew = false, runsTooMany = false;
    if (limits) {
      runsTooFew = runsInfo.runs < limits.lo;
      runsTooMany = runsInfo.runs > limits.hi;
    }

    var badge = { label: 'Stable', tone: 'neutral' };
    var headline = 'Stable performance (random variation)';
    var whats = 'The data fluctuates around a typical level with no strong evidence of sustained change.';
    var why = 'Patterns observed are consistent with normal variation rather than a structural change.';
    var action = 'No immediate action required. Continue to monitor and avoid reacting to single points.';

    if (shiftDown || trendDown) {
      badge = { label: 'Deteriorating', tone: 'negative' };
      headline = 'Sustained deterioration detected';
      whats = 'Values show a consistent downward movement and remain below the typical level.';
      why = 'This pattern is unlikely to be caused by chance alone and suggests the process has genuinely worsened.';
      action = 'Investigate what changed around the start of the decline and intervene to stabilise performance.';
    } else if (shiftUp || trendUp) {
      badge = { label: 'Improving', tone: 'positive' };
      headline = 'Sustained improvement detected';
      whats = 'The data has moved to a higher level and continues to increase over time.';
      why = 'This pattern is very unlikely to occur by chance alone and suggests a meaningful positive change in the system.';
      action = 'Identify what changed at the start of the improvement and consider reinforcing or standardising those factors.';
    } else if (astro) {
      badge = { label: 'Unusual event', tone: 'warning' };
      headline = 'Unusual event detected';
      whats = 'One data point appears unusually extreme compared to the rest of the series.';
      why = 'This may reflect a special cause (e.g., one-off event, incident, data issue, or exceptional performance).';
      action = 'Investigate what happened at that point in time and decide whether it is a one-off or repeatable.';
    } else if (runsTooMany) {
      badge = { label: 'Unstable', tone: 'warning' };
      headline = 'Unstable pattern detected';
      whats = 'The series crosses the median very frequently, indicating an unusually high number of runs.';
      why = 'This can suggest over-control or frequent reactive adjustments, which may increase instability.';
      action = 'Reduce frequent changes and focus on sustained interventions rather than reacting to every fluctuation.';
    } else if (runsTooFew) {
      badge = { label: 'Possible shift', tone: 'warning' };
      headline = 'Non-random pattern detected';
      whats = 'The series crosses the median less often than expected, indicating clustering on one side.';
      why = 'This may indicate an underlying shift, stratification, or a hidden change in the process.';
      action = 'Check whether the data includes mixed sub-groups and investigate what changed around the clustered period.';
    }

    var evidence = [];
    if (shiftDetected) {
      evidence.push('Shift: ' + shift.len + ' consecutive points ' + (shift.side === 1 ? 'above' : 'below') + ' the median (rule: 6+).');
    } else {
      evidence.push('Shift: not detected (need 6+ consecutive points above or below the median).');
    }
    if (trendUp) {
      evidence.push('Trend: ' + trend.up + ' consecutive increases (rule: 5+; ties are ignored).');
    } else if (trendDown) {
      evidence.push('Trend: ' + trend.down + ' consecutive decreases (rule: 5+; ties are ignored).');
    } else {
      evidence.push('Trend: not detected (need 5+ consecutive increases or decreases; ties are ignored).');
    }
    if (limits) {
      var expected = limits.lo + '\u2013' + limits.hi;
      if (runsTooFew) {
        evidence.push('Runs: too few (' + runsInfo.runs + ' runs; expected ' + expected + ').');
      } else if (runsTooMany) {
        evidence.push('Runs: too many (' + runsInfo.runs + ' runs; expected ' + expected + ').');
      } else {
        evidence.push('Runs: within the expected range (' + runsInfo.runs + ' runs; expected ' + expected + ').');
      }
    } else {
      evidence.push('Runs: have ' + runsInfo.nNonMedian + ' non-median points. Runs limits are implemented for 10\u201360 non-median points.');
    }
    if (astro) {
      evidence.push('Outliers: possible astronomical point flagged (' + astro.type + '): ' + astro.value + '.');
    } else {
      evidence.push('Outliers: no unusual points flagged.');
    }
    evidence.push('Reference median used for analysis: ' + (med === null ? 'N/A' : Number(med.toFixed(2))) + '.');

    return { badge: badge, headline: headline, whats: whats, why: why, evidence: evidence, action: action };
  }

  // =========================
  // Read-only chart renderer
  // =========================
  function createReadOnlyChart(canvas, state) {
    if (!window.Chart) return null;

    var rows = (state && state.rows) ? state.rows : [];
    var labels = rows.map(function (r) { return r.label || ''; });
    var series = rows.map(function (r) { return (r.a !== null && r.a !== undefined) ? r.a : null; });
    var stats = computeStatsA(rows);
    var medLine = rows.map(function () { return stats.median; });
    var showMinMax = !!(state.toggles && state.toggles.minmax);

    var c1 = (state.colors && state.colors.series) ? state.colors.series : '#03283E';
    var c2 = (state.colors && state.colors.average) ? state.colors.average : '#a8dbe6';
    var legendLabel = (state.legend && state.legend.combined) ? state.legend.combined : 'Numerator';

    var datasets = [
      {
        label: legendLabel,
        data: series,
        fill: false,
        tension: 0.25,
        borderWidth: 3,
        pointRadius: 3,
        borderColor: c1,
        backgroundColor: c1,
        pointBackgroundColor: c1,
        pointBorderColor: c1
      },
      {
        label: 'Median',
        data: medLine,
        fill: false,
        tension: 0,
        borderWidth: 2,
        pointRadius: 0,
        borderColor: c2,
        backgroundColor: c2
      }
    ];

    if (showMinMax && stats.min !== null && stats.max !== null) {
      datasets.push({
        label: 'Min',
        data: rows.map(function () { return stats.min; }),
        fill: false,
        borderWidth: 1,
        borderDash: [6, 6],
        tension: 0,
        pointRadius: 0,
        borderColor: '#ef4444'
      });
      datasets.push({
        label: 'Max',
        data: rows.map(function () { return stats.max; }),
        fill: false,
        borderWidth: 1,
        borderDash: [6, 6],
        tension: 0,
        pointRadius: 0,
        borderColor: '#22c55e'
      });
    }

    var title = (state.chart && state.chart.title) ? state.chart.title : '';
    var xTitle = (state.chart && state.chart.xTitle) ? state.chart.xTitle : '';
    var yTitle = (state.chart && state.chart.yTitle) ? state.chart.yTitle : '';

    new Chart(canvas.getContext('2d'), {
      type: 'line',
      data: { labels: labels, datasets: datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top' },
          title: { display: !!title, text: title }
        },
        scales: {
          x: { title: { display: !!xTitle, text: xTitle } },
          y: { beginAtZero: true, title: { display: !!yTitle, text: yTitle } }
        }
      }
    });

    return series;
  }

  // =========================
  // Render insights into DOM
  // =========================
  function renderPublicInsights(container, series) {
    var res = buildInsights(series);

    var html = '';
    html += '<div class="pubcharts-insights-inner">';
    html += '<div class="pubcharts-insights-header">';
    html += '<span class="dttc-badge dttc-badge--' + escHtml(res.badge.tone) + '">' + escHtml(res.badge.label) + '</span>';
    html += '<span class="pubcharts-insights-headline">' + escHtml(res.headline) + '</span>';
    html += '</div>';
    html += '<div class="pubcharts-insights-body">';
    html += '<p><strong>What\'s happening:</strong> ' + escHtml(res.whats) + '</p>';
    html += '<p><strong>Why this matters:</strong> ' + escHtml(res.why) + '</p>';
    html += '<p><strong>Evidence:</strong></p><ul>';
    for (var i = 0; i < res.evidence.length; i++) {
      html += '<li>' + escHtml(res.evidence[i]) + '</li>';
    }
    html += '</ul>';
    html += '<p><strong>Suggested action:</strong> ' + escHtml(res.action) + '</p>';
    html += '</div></div>';

    container.innerHTML = html;
  }

  // =========================
  // Init on page load
  // =========================
  document.addEventListener('DOMContentLoaded', function () {
    var statesMap = window.DTTC_PUBLIC_STATES || {};
    var canvases = document.querySelectorAll('.dttc-public-canvas');

    for (var i = 0; i < canvases.length; i++) {
      var canvas = canvases[i];
      var chartId = canvas.getAttribute('data-chart-id');
      var state = null;

      // Primary: read from JS global (set by PHP inline script)
      if (chartId && statesMap[chartId]) {
        state = statesMap[chartId];
      }

      // Fallback: read from data attribute (legacy)
      if (!state) {
        var stateJson = canvas.getAttribute('data-dttc-public-state');
        if (stateJson) {
          try { state = JSON.parse(stateJson); } catch (e) { /* skip */ }
        }
      }

      if (!state || !state.rows || !state.rows.length) continue;

      var series = createReadOnlyChart(canvas, state);

      var card = canvas.closest('.pubcharts-card');
      if (card && series) {
        var insightsEl = card.querySelector('[data-dttc-public-insights]');
        if (insightsEl) {
          renderPublicInsights(insightsEl, series);
        }
      }
    }
  });
})();
