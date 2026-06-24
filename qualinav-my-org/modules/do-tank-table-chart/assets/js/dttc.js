(function(){
  'use strict';

  // Draw Min/Max labels next to highlighted points (series dataset)
  var dttcMinMaxLabelPlugin = {
    id: 'dttcMinMaxLabelPlugin',
    afterDatasetsDraw: function(chart){
      var minIdx = chart.$dttcMinIndex;
      var maxIdx = chart.$dttcMaxIndex;
      if (minIdx === null || maxIdx === null || minIdx === undefined || maxIdx === undefined) return;

      var meta = chart.getDatasetMeta(0);
      if (!meta || !meta.data) return;

      var ctx = chart.ctx;
      ctx.save();
      ctx.font = '600 12px system-ui, -apple-system, Segoe UI, Roboto, Arial';
      ctx.textBaseline = 'middle';

      function draw(idx, text, color){
        var el = meta.data[idx];
        if (!el) return;
        var p = el.getProps(['x','y'], true);
        ctx.fillStyle = color;
        ctx.fillText(text, p.x + 10, p.y - 10);
      }

      if (chart.$dttcShowMinMax) draw(maxIdx, 'Max', '#22c55e'); // green
      if (chart.$dttcShowMinMax) draw(minIdx, 'Min', '#ef4444'); // red
      ctx.restore();
    }
  };



  // =========================
  // Helpers
  // =========================
  function toNum(v){
    if (v === '' || v === null || typeof v === 'undefined') return null;
    var n = Number(v);
    return Number.isFinite(n) ? n : null;
  }

  function escHtml(s){
    return String(s)
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#039;');
  }

  function keyFor(instanceId){
    var postId = (window.DTTC && DTTC.postId) ? DTTC.postId : 0;
    return 'dttc:' + postId + ':' + instanceId;
  }

  function defaultState(rowsCount, defaultSeriesLabel){
    var rows = [];
    for (var i=0; i<rowsCount; i++){
      rows.push({ label: 'Row ' + (i+1), a: null, b: null });
    }
    return {
      rows: rows,
      legend: { combined: defaultSeriesLabel || 'Numerator' },
      colors: { series: '#03283E', average: '#a8dbe6' },
      chart: {
        title: '',
        xTitle: '',
        yTitle: ''
      },
      toggles: {
        minmax: false
      }
    };
  }

  function save(instanceId, state){
    try { localStorage.setItem(keyFor(instanceId), JSON.stringify(state)); } catch(e){}
  }

  function load(instanceId){
    try {
      var raw = localStorage.getItem(keyFor(instanceId));
      if (!raw) return null;
      var parsed = JSON.parse(raw);

      // Backward compatibility: if it's an array, it's old "rows only"
      if (Array.isArray(parsed)){
        return { rows: parsed, legend: { combined: 'Series' }, colors: { series:'#03283E', average:'#a8dbe6' } };
      }

      if (parsed && typeof parsed === 'object'){
        if (!Array.isArray(parsed.rows)) parsed.rows = [];
        if (!parsed.legend || typeof parsed.legend !== 'object') parsed.legend = { combined: 'Series' };
        if (typeof parsed.legend.combined !== 'string') parsed.legend.combined = 'Series';
        if (!parsed.colors || typeof parsed.colors !== 'object') parsed.colors = { series:'#03283E', average:'#a8dbe6' };
        if (typeof parsed.colors.series !== 'string') parsed.colors.series = '#03283E';
        if (typeof parsed.colors.average !== 'string') parsed.colors.average = '#a8dbe6';

        if (!parsed.chart || typeof parsed.chart !== 'object') parsed.chart = { title:'', xTitle:'', yTitle:'' };
        if (typeof parsed.chart.title !== 'string') parsed.chart.title = '';
        if (typeof parsed.chart.xTitle !== 'string') parsed.chart.xTitle = '';
        if (typeof parsed.chart.yTitle !== 'string') parsed.chart.yTitle = '';

        if (!parsed.toggles || typeof parsed.toggles !== 'object') parsed.toggles = { minmax:false };
        parsed.toggles.minmax = !!parsed.toggles.minmax;
        return parsed;
      }

      return null;
    } catch(e){
      return null;
    }
  }

  // =========================
  // Data logic
  // =========================
  function combinedAB(a, b){
    // Chart series should use Numerator only (Denominator is not charted)
    if (a === null) return null;
    return a;
  }


  // Median line: overall median of Column A (Numerator)
  function medianA(rows){
    // Build an array of Numerator (A) values and compute its median.
    var values = rows.map(function(r){
      return (r && r.a !== null) ? r.a : null;
    });
    return median(values);
  }

  function computeStatsA(rows){
    var arr = rows
      .map(function(r){ return (r && r.a !== null) ? r.a : null; })
      .filter(function(v){ return v !== null; })
      .slice()
      .sort(function(a,b){ return a-b; });

    if (!arr.length){
      return { median:null, min:null, max:null };
    }

    var mid = Math.floor(arr.length/2);
    var median = (arr.length % 2) ? arr[mid] : (arr[mid-1] + arr[mid]) / 2;

    return {
      median: median,
      min: arr[0],
      max: arr[arr.length-1]
    };
  }

// =========================
  // Exports
  // =========================
  function downloadCSV(filename, rows){
    var header = ['Time','Numerator','Denominator','Median'];
    var lines = [header.join(',')];

    var medA = medianA(rows);

    rows.forEach(function(r){
      var a = (r.a === null ? '' : r.a);
      var b = (r.b === null ? '' : r.b);
      var medCell = (medA === null ? '' : medA);

      var label = String(r.label || '').replaceAll('"','""');
      lines.push(['"' + label + '"', a, b, medCell].join(','));
    });

    var blob = new Blob([lines.join('\n')], {type:'text/csv;charset=utf-8;'});
    var url = URL.createObjectURL(blob);
    var aTag = document.createElement('a');
    aTag.href = url;
    aTag.download = filename;
    document.body.appendChild(aTag);
    aTag.click();
    setTimeout(function(){
      URL.revokeObjectURL(url);
      aTag.remove();
    }, 250);
  }

  
  function parseCSV(text){
    // Supports comma-separated; also tolerates semicolon if commas absent.
    var lines = text.replace(/\r/g,'').split('\n').filter(function(l){ return l.trim() !== ''; });
    if (!lines.length) return [];
    var delim = (lines[0].indexOf(',') !== -1) ? ',' : ((lines[0].indexOf(';') !== -1) ? ';' : ',');
    var rows = [];
    for (var i=0;i<lines.length;i++){
      var line = lines[i];
      // basic CSV split with quotes
      var out = [];
      var cur = '', inQ = false;
      for (var j=0;j<line.length;j++){
        var ch = line[j];
        if (ch === '"'){
          if (inQ && line[j+1] === '"'){ cur += '"'; j++; }
          else inQ = !inQ;
        } else if (ch === delim && !inQ){
          out.push(cur); cur='';
        } else {
          cur += ch;
        }
      }
      out.push(cur);

      // detect header row
      if (i === 0){
        var h = out.map(function(x){ return x.trim().toLowerCase(); });
        if (h.includes('label') || h.includes('a') || h.includes('b')) continue;
      }

      var label = (out[0] || '').trim();
      var a = toNum((out[1] || '').trim());
      var b = toNum((out[2] || '').trim());
      // If label missing but values present, auto label
      if (!label) label = 'Row ' + (rows.length + 1);
      rows.push({ label: label, a: a, b: b });
    }
    return rows;
  }

  function parsePaste(text){
    // Accepts tab OR comma separated; rows separated by newlines.
    var lines = text.replace(/\r/g,'').split('\n').filter(function(l){ return l.trim() !== ''; });
    var rows = [];
    lines.forEach(function(line){
      var parts = line.split('\t');
      if (parts.length < 2){
        parts = line.split(',');
      }
      var label = (parts[0] || '').trim();
      var a, b;
      if (parts.length >= 3){
        a = toNum((parts[1] || '').trim());
        b = toNum((parts[2] || '').trim());
      } else {
        // if only two columns, treat as A,B with auto label
        a = toNum((parts[0] || '').trim());
        b = toNum((parts[1] || '').trim());
        label = '';
      }
      if (!label) label = 'Row ' + (rows.length + 1);
      rows.push({ label: label, a: a, b: b });
    });
    return rows;
  }

  function togglePanel(panel, show){
    if (!panel) return;
    panel.hidden = !show;
  }
function downloadPNGFromCanvas(canvas, filename){
    var link = document.createElement('a');
    link.download = filename;
    link.href = canvas.toDataURL('image/png', 1.0);
    document.body.appendChild(link);
    link.click();
    link.remove();
  }

  function downloadPDFfromCanvas(canvas, filename){
    if (!window.jspdf || !window.jspdf.jsPDF) return false;

    var imgData = canvas.toDataURL('image/png', 1.0);
    var pdf = new window.jspdf.jsPDF({ orientation:'landscape', unit:'pt', format:'a4' });

    var pageW = pdf.internal.pageSize.getWidth();
    var pageH = pdf.internal.pageSize.getHeight();
    var margin = 24;

    var rect = canvas.getBoundingClientRect();
    var cW = rect.width || canvas.width;
    var cH = rect.height || canvas.height;

    var scale = Math.min((pageW - margin*2) / cW, (pageH - margin*2) / cH);
    var w = cW * scale;
    var h = cH * scale;

    var x = (pageW - w) / 2;
    var y = (pageH - h) / 2;

    pdf.addImage(imgData, 'PNG', x, y, w, h);
    pdf.save(filename);
    return true;
  }

  // =========================
  // Run chart rules (PDF)
  // =========================
  function median(values){
    var arr = values
      .filter(function(v){ return v !== null; })
      .slice()
      .sort(function(a,b){ return a-b; });

    var n = arr.length;
    if (!n) return null;

    var mid = Math.floor(n/2);
    if (n % 2) return arr[mid];
    return (arr[mid-1] + arr[mid]) / 2;
  }

  // Shift: 6+ consecutive points all above or all below the median.
  // Points exactly on the median are skipped and do not break a shift.
  function detectShift(series, med){
    if (med === null) return { len: 0, side: 0 };

    var bestLen = 0;
    var bestSide = 0;

    var curLen = 0;
    var curSide = 0;

    for (var i=0; i<series.length; i++){
      var v = series[i];
      if (v === null){
        curLen = 0; curSide = 0;
        continue;
      }
      if (v === med) continue;

      var side = (v > med) ? 1 : -1;
      if (side === curSide) curLen++;
      else { curSide = side; curLen = 1; }

      if (curLen > bestLen){
        bestLen = curLen;
        bestSide = curSide;
      }
    }

    return { len: bestLen, side: bestSide };
  }

  // Trend: 5+ consecutive increases OR decreases; ties are ignored.
  function detectTrend(series){
    var bestUp = 0, bestDown = 0;
    var up = 1, down = 1;

    for (var i=1; i<series.length; i++){
      var prev = series[i-1], cur = series[i];
      if (prev === null || cur === null){
        up = 1; down = 1;
        continue;
      }
      if (cur === prev) continue;

      if (cur > prev){
        up++; down = 1;
      } else {
        down++; up = 1;
      }

      if (up > bestUp) bestUp = up;
      if (down > bestDown) bestDown = down;
    }

    return { up: bestUp, down: bestDown };
  }

  // Runs limits table (10–60 points)
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

  function countRuns(series, med){
    if (med === null) return { runs: 0, nNonMedian: 0 };

    var sides = [];
    for (var i=0; i<series.length; i++){
      var v = series[i];
      if (v === null) continue;
      if (v === med) continue;
      sides.push(v > med ? 1 : -1);
    }

    var n = sides.length;
    if (!n) return { runs: 0, nNonMedian: 0 };

    var crossings = 0;
    for (var j=1; j<n; j++){
      if (sides[j] !== sides[j-1]) crossings++;
    }

    return { runs: crossings + 1, nNonMedian: n };
  }

  // Astronomical point: flagged using conservative  heuristic (prompt for investigation)
  function quantile(sortedArr, q){
    var pos = (sortedArr.length - 1) * q;
    var base = Math.floor(pos);
    var rest = pos - base;
    if (sortedArr[base+1] !== undefined){
      return sortedArr[base] + rest * (sortedArr[base+1] - sortedArr[base]);
    }
    return sortedArr[base];
  }

  function detectAstronomical(series){
    var arr = series.filter(function(v){ return v !== null; }).slice().sort(function(a,b){ return a-b; });
    if (arr.length < 8) return null;

    var q1 = quantile(arr, 0.25);
    var q3 = quantile(arr, 0.75);
    var iqr = q3 - q1;
    if (iqr === 0) return null;

    var low = q1 - 1.5 * iqr;
    var high = q3 + 1.5 * iqr;

    var min = arr[0];
    var max = arr[arr.length - 1];

    if (min < low) return { type: 'low', value: min };
    if (max > high) return { type: 'high', value: max };
    return null;
  }

  function buildInsights(series){
    var nonNull = series.filter(function(v){ return v !== null; });

    if (nonNull.length < 10){
      return {
        badge: { label: 'Collect more data', tone: 'neutral' },
        headline: 'Not enough data yet',
        whats: 'At least 10 data points are needed to reliably detect non-random patterns.',
        why: 'With fewer points, apparent patterns can be misleading.',
        evidence: [
          'Add more data points to enable full run chart analysis.'
        ],
        action: 'Continue monitoring and collecting data.'
      };
    }

    var med = median(series);
    var shift = detectShift(series, med);  // {len,side}
    var trend = detectTrend(series);       // {up,down}
    var runsInfo = countRuns(series, med); // {runs,nNonMedian}
    var limits = RUN_LIMITS[runsInfo.nNonMedian] || null;
    var astro = detectAstronomical(series);

    var shiftDetected = shift.len >= 6;
    var shiftUp = shiftDetected && shift.side === 1;
    var shiftDown = shiftDetected && shift.side === -1;

    var trendUp = trend.up >= 5;
    var trendDown = trend.down >= 5;

    var runsTooFew = false;
    var runsTooMany = false;
    if (limits){
      runsTooFew = runsInfo.runs < limits.lo;
      runsTooMany = runsInfo.runs > limits.hi;
    }

    // Verdict priority:
    // 1) Deteriorating  2) Improving  3) Unusual event  4) Unstable (too many runs)
    // 5) Non-random (too few runs)  6) Stable
    var badge = { label: 'Stable', tone: 'neutral' };
    var headline = 'Stable performance (random variation)';
    var whats = 'The data fluctuates around a typical level with no strong evidence of sustained change.';
    var why = 'Patterns observed are consistent with normal variation rather than a structural change.';
    var action = 'No immediate action required. Continue to monitor and avoid reacting to single points.';

    if (shiftDown || trendDown){
      badge = { label: 'Deteriorating', tone: 'negative' };
      headline = 'Sustained deterioration detected';
      whats = 'Values show a consistent downward movement and remain below the typical level.';
      why = 'This pattern is unlikely to be caused by chance alone and suggests the process has genuinely worsened.';
      action = 'Investigate what changed around the start of the decline and intervene to stabilise performance.';
    } else if (shiftUp || trendUp){
      badge = { label: 'Improving', tone: 'positive' };
      headline = 'Sustained improvement detected';
      whats = 'The data has moved to a higher level and continues to increase over time.';
      why = 'This pattern is very unlikely to occur by chance alone and suggests a meaningful positive change in the system.';
      action = 'Identify what changed at the start of the improvement and consider reinforcing or standardising those factors.';
    } else if (astro){
      badge = { label: 'Unusual event', tone: 'warning' };
      headline = 'Unusual event detected';
      whats = 'One data point appears unusually extreme compared to the rest of the series.';
      why = 'This may reflect a special cause (e.g., one-off event, incident, data issue, or exceptional performance).';
      action = 'Investigate what happened at that point in time and decide whether it is a one-off or repeatable.';
    } else if (runsTooMany){
      badge = { label: 'Unstable', tone: 'warning' };
      headline = 'Unstable pattern detected';
      whats = 'The series crosses the median very frequently, indicating an unusually high number of runs.';
      why = 'This can suggest over-control or frequent reactive adjustments, which may increase instability.';
      action = 'Reduce frequent changes and focus on sustained interventions rather than reacting to every fluctuation.';
    } else if (runsTooFew){
      badge = { label: 'Possible shift', tone: 'warning' };
      headline = 'Non-random pattern detected';
      whats = 'The series crosses the median less often than expected, indicating clustering on one side.';
      why = 'This may indicate an underlying shift, stratification, or a hidden change in the process.';
      action = 'Check whether the data includes mixed sub-groups and investigate what changed around the clustered period.';
    }

    // Evidence (supporting detail)
    var evidence = [];

    if (shiftDetected){
      evidence.push('Shift: ' + shift.len + ' consecutive points ' + (shift.side === 1 ? 'above' : 'below') + ' the median (rule: 6+).');
    } else {
      evidence.push('Shift: not detected (need 6+ consecutive points above or below the median).');
    }

    if (trendUp){
      evidence.push('Trend: ' + trend.up + ' consecutive increases (rule: 5+; ties are ignored).');
    } else if (trendDown){
      evidence.push('Trend: ' + trend.down + ' consecutive decreases (rule: 5+; ties are ignored).');
    } else {
      evidence.push('Trend: not detected (need 5+ consecutive increases or decreases; ties are ignored).');
    }

    if (limits){
      var expected = limits.lo + '–' + limits.hi;
      if (runsTooFew){
        evidence.push('Runs: too few (' + runsInfo.runs + ' runs; expected ' + expected + ').');
      } else if (runsTooMany){
        evidence.push('Runs: too many (' + runsInfo.runs + ' runs; expected ' + expected + ').');
      } else {
        evidence.push('Runs: within the expected range (' + runsInfo.runs + ' runs; expected ' + expected + ').');
      }
    } else {
      evidence.push('Runs: have ' + runsInfo.nNonMedian + ' non-median points. Runs limits are implemented for 10–60 non-median points.');
    }

    if (astro){
      evidence.push('Outliers: possible astronomical point flagged (' + astro.type + '): ' + astro.value + '.');
    } else {
      evidence.push('Outliers: no unusual points flagged.');
    }

    evidence.push('Reference median used for analysis: ' + (med === null ? 'N/A' : Number(med.toFixed(2))) + '.');

    return { badge: badge, headline: headline, whats: whats, why: why, evidence: evidence, action: action };
  }

  function renderInsights(wrap, series){
    var box = wrap.querySelector('[data-dttc-notes]');
    if (!box) return;

    var titleEl = box.querySelector('.dttc-notes-title');
    var bodyEl = box.querySelector('.dttc-notes-body');
    if (!bodyEl) return;

    var res = buildInsights(series);

    // Title: badge + headline
    if (titleEl){
      var badgeHtml = '<span class="dttc-badge dttc-badge--' + escHtml(res.badge.tone) + '">' + escHtml(res.badge.label) + '</span>';
      titleEl.innerHTML = badgeHtml + '<span class="dttc-insights-headline">' + escHtml(res.headline) + '</span>';
    }

    var html = '';
    html += '<div class="dttc-insights">';
    html +=   '<p><strong>What’s happening:</strong> ' + escHtml(res.whats) + '</p>';
    html +=   '<p><strong>Why this matters:</strong> ' + escHtml(res.why) + '</p>';
    html +=   '<p><strong>Evidence behind this conclusion:</strong></p>';
    html +=   '<ul>';
    for (var i=0; i<res.evidence.length; i++){
      html += '<li>' + escHtml(res.evidence[i]) + '</li>';
    }
    html +=   '</ul>';
    html +=   '<p><strong>Suggested action:</strong> ' + escHtml(res.action) + '</p>';
    html += '</div>';

    bodyEl.innerHTML = html;
  }

  // =========================
  // Chart
  // =========================
  function ensureChart(canvas){
    if (!window.Chart) return null;
    var ctx = canvas.getContext('2d');

    return new Chart(ctx, {
      plugins: [dttcMinMaxLabelPlugin],
      type: 'line',
      data: {
        labels: [],
        datasets: []
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top' },
          title: {
            display: false,
            text: ''
          },
          tooltip: {
            enabled: true,
            callbacks: {
              title: function(items){
                if (!items || !items.length) return '';
                return items[0].label || '';
              },
              label: function(ctx){
                var chart = ctx.chart;
                var idx = ctx.dataIndex;
                var rows = chart && chart.$dttcRows ? chart.$dttcRows : [];
                var stats = chart && chart.$dttcStats ? chart.$dttcStats : null;

                var r = rows[idx] || {};
                var lines = [];
                if (r.a !== null && typeof r.a !== 'undefined') lines.push('Numerator: ' + r.a);
                if (r.b !== null && typeof r.b !== 'undefined') lines.push('Denominator: ' + r.b);
                if (stats && stats.median !== null) lines.push('Median: ' + Number(stats.median.toFixed(4)));

                // Only add extra stats once (on first dataset tooltip line)
                if (ctx.datasetIndex === 0 && stats){
                  if (stats.min !== null && chart.$dttcShowMinMax) lines.push('Min: ' + Number(stats.min.toFixed(4)));
                  if (stats.max !== null && chart.$dttcShowMinMax) lines.push('Max: ' + Number(stats.max.toFixed(4)));
}
                return lines;
              }
            }
          }
        },
        scales: {
          x: {
            title: { display: false, text: '' }
          },
          y: {
            beginAtZero: true,
            title: { display: false, text: '' }
          }
        }
      }
    });
  }

  function applyColors(chart, state){
    if (!chart) return;
    var c1 = state.colors && state.colors.series ? state.colors.series : '#03283E';
    var c2 = state.colors && state.colors.average ? state.colors.average : '#a8dbe6';
    chart.data.datasets.forEach(function(ds){
      if (!ds) return;
      if (ds.dttcKey === 'series'){
        var minC = '#ef4444'; // red
        var maxC = '#22c55e'; // green

        ds.borderColor = c1;
        ds.backgroundColor = c1;

        ds.pointRadius = function(ctx){
          if (!ctx.chart.$dttcShowMinMax) return 3;
          var i = ctx.dataIndex;
          if (i === ctx.chart.$dttcMinIndex || i === ctx.chart.$dttcMaxIndex) return 7;
          return 3;
        };

        ds.pointBackgroundColor = function(ctx){
          if (!ctx.chart.$dttcShowMinMax) return c1;
          var i = ctx.dataIndex;
          if (i === ctx.chart.$dttcMinIndex) return minC;
          if (i === ctx.chart.$dttcMaxIndex) return maxC;
          return c1;
        };

        ds.pointBorderColor = function(ctx){
          if (!ctx.chart.$dttcShowMinMax) return c1;
          var i = ctx.dataIndex;
          if (i === ctx.chart.$dttcMinIndex) return minC;
          if (i === ctx.chart.$dttcMaxIndex) return maxC;
          return c1;
        };

        ds.pointHoverBackgroundColor = ds.pointBackgroundColor;
        ds.pointHoverBorderColor = ds.pointBorderColor;
      }
      if (ds.dttcKey === 'median'){
        ds.borderColor = c2;
        ds.backgroundColor = c2;
        ds.pointBackgroundColor = c2;
        ds.pointBorderColor = c2;
        ds.pointHoverBackgroundColor = c2;
        ds.pointHoverBorderColor = c2;
      }

      // Neutral helper lines / bands
      if (ds.dttcKey === 'min' || ds.dttcKey === 'max'){
        ds.borderColor = ds.borderColor || '#64748b';
        ds.pointRadius = 0;
      }
    });
  }

  function updateChart(wrap, chart, rows, state){
    var labels = rows.map(function(r){ return r.label || ''; });
    var series = rows.map(function(r){ return r.a; });

    // Min/Max indices for the series (first occurrence)
    var minIdx = null, maxIdx = null;
    var minVal = null, maxVal = null;
    series.forEach(function(v,i){
      if (v === null || typeof v === 'undefined') return;
      if (minVal === null || v < minVal){ minVal = v; minIdx = i; }
      if (maxVal === null || v > maxVal){ maxVal = v; maxIdx = i; }
    });

    var stats = computeStatsA(rows);
    var medA = stats.median;
    var medLine = rows.map(function(){ return medA; });

    var showMinMax = !!(state.toggles && state.toggles.minmax);

    renderInsights(wrap, series);

    if (!chart) return;

    chart.$dttcRows = rows;
    chart.$dttcStats = stats;
    chart.$dttcShowMinMax = showMinMax;
    chart.$dttcMinIndex = minIdx;
    chart.$dttcMaxIndex = maxIdx;
    
    chart.data.labels = labels;

    var datasets = [];

    datasets.push({
      dttcKey: 'series',
      label: (state.legend && state.legend.combined) ? state.legend.combined : 'Numerator',
      data: series,
      fill: false,
      tension: 0.25,
      borderWidth: 3,
      pointRadius: 3
    });

    datasets.push({
      dttcKey: 'median',
      label: 'Median',
      data: medLine,
      fill: false,
      tension: 0,
      borderWidth: 2,
      pointRadius: 0
    });

    if (showMinMax && stats.min !== null && stats.max !== null){
      var minLine = rows.map(function(){ return stats.min; });
      var maxLine = rows.map(function(){ return stats.max; });
      datasets.push({
        dttcKey: 'min',
        label: 'Min',
        data: minLine,
        fill: false,
        borderWidth: 1,
        borderDash: [6,6],
        tension: 0,
        pointRadius: 0,
        borderColor: '#ef4444'
      });
      datasets.push({
        dttcKey: 'max',
        label: 'Max',
        data: maxLine,
        fill: false,
        borderWidth: 1,
        borderDash: [6,6],
        tension: 0,
        pointRadius: 0,
        borderColor: '#22c55e'
      });
    }

    chart.data.datasets = datasets;

    // Title + axis titles
    var title = (state.chart && state.chart.title) ? state.chart.title : '';
    chart.options.plugins.title.display = !!title;
    chart.options.plugins.title.text = title;

    var xTitle = (state.chart && state.chart.xTitle) ? state.chart.xTitle : '';
    chart.options.scales.x.title.display = !!xTitle;
    chart.options.scales.x.title.text = xTitle;

    var yTitle = (state.chart && state.chart.yTitle) ? state.chart.yTitle : '';
    chart.options.scales.y.title.display = !!yTitle;
    chart.options.scales.y.title.text = yTitle;

    applyColors(chart, state);
    chart.update();
  }

  // =========================
  // Table render
  // =========================
  function renderRows(tbody, rows){
    tbody.innerHTML = '';
    var medA = medianA(rows);

    rows.forEach(function(r, idx){
      var tr = document.createElement('tr');

      // Label
      var tdLabel = document.createElement('td');
      var inputLabel = document.createElement('input');
      inputLabel.className = 'dttc-input';
      inputLabel.type = 'text';
      inputLabel.value = (r.label ?? ('Row ' + (idx+1)));
      inputLabel.setAttribute('data-dttc-field', 'label');
      inputLabel.setAttribute('data-dttc-index', String(idx));
      tdLabel.appendChild(inputLabel);
      tr.appendChild(tdLabel);

      // A
      var tdA = document.createElement('td');
      var inputA = document.createElement('input');
      inputA.className = 'dttc-input';
      inputA.type = 'number';
      inputA.step = 'any';
      inputA.value = (r.a === null ? '' : r.a);
      inputA.setAttribute('data-dttc-field', 'a');
      inputA.setAttribute('data-dttc-index', String(idx));
      tdA.appendChild(inputA);
      tr.appendChild(tdA);

      // B
      var tdB = document.createElement('td');
      var inputB = document.createElement('input');
      inputB.className = 'dttc-input';
      inputB.type = 'number';
      inputB.step = 'any';
      inputB.value = (r.b === null ? '' : r.b);
      inputB.setAttribute('data-dttc-field', 'b');
      inputB.setAttribute('data-dttc-index', String(idx));
      tdB.appendChild(inputB);
      tr.appendChild(tdB);

      // Median (overall median of Numerator)
      var tdAvg = document.createElement('td');
      var inputAvg = document.createElement('input');
      inputAvg.className = 'dttc-input';
      inputAvg.type = 'number';
      inputAvg.step = 'any';
      inputAvg.readOnly = true;
      inputAvg.value = (medA === null ? '' : Number(medA.toFixed(4)));
      inputAvg.setAttribute('data-dttc-avg', '1');
      tdAvg.appendChild(inputAvg);
      tr.appendChild(tdAvg);

      // Actions
      var tdActions = document.createElement('td');
      tdActions.style.width = '1%';
      tdActions.style.whiteSpace = 'nowrap';

      var wrap = document.createElement('div');
      wrap.className = 'dttc-row-actions';

      var btnDel = document.createElement('button');
      btnDel.type = 'button';
      btnDel.className = 'dttc-mini';
      btnDel.textContent = 'Remove';
      btnDel.setAttribute('data-dttc-action', 'remove-row');
      btnDel.setAttribute('data-dttc-index', String(idx));

      wrap.appendChild(btnDel);
      tdActions.appendChild(wrap);
      tr.appendChild(tdActions);

      tbody.appendChild(tr);
    });
  }

  function updateAverageCells(tbody, rows){
    var avgInputs = tbody.querySelectorAll('input[data-dttc-avg="1"]');
    var medA = medianA(rows);
    avgInputs.forEach(function(inp){
      inp.value = (medA === null ? '' : Number(medA.toFixed(4)));
    });
  }

  // =========================
  // Init
  // =========================
  function initWrap(wrap){
    var instanceId = wrap.getAttribute('data-dttc-id');
    var rowsCount = Number(wrap.getAttribute('data-dttc-rows') || 6);
    var defaultSeriesLabel = wrap.getAttribute('data-dttc-combined-label') || 'Series';

    var tbody = wrap.querySelector('[data-dttc-body]');
    var canvas = wrap.querySelector('[data-dttc-canvas]');
    var legendInput = wrap.querySelector('[data-dttc-legend="combined"]');
    var colorSeries = wrap.querySelector('[data-dttc-color="series"]');
    var colorAverage = wrap.querySelector('[data-dttc-color="average"]');

    var chartTitleInput = wrap.querySelector('[data-dttc-chart-title]');
    var axisXInput = wrap.querySelector('[data-dttc-axis-x]');
    var axisYInput = wrap.querySelector('[data-dttc-axis-y]');
    var toggleMinMax = wrap.querySelector('[data-dttc-toggle="minmax"]');

    if (canvas && canvas.parentElement) canvas.parentElement.style.height = '320px';

    var state = load(instanceId) || defaultState(rowsCount, defaultSeriesLabel);
    if (!state.rows || !state.rows.length) state = defaultState(rowsCount, defaultSeriesLabel);

    // Default chart title to the card title on first run
    var cardTitle = wrap.querySelector('.dttc-title');
    if (state.chart && !state.chart.title && cardTitle && cardTitle.textContent){
      state.chart.title = String(cardTitle.textContent).trim();
    }

    // Initialize UI inputs from state
    if (legendInput){
      legendInput.value = state.legend && state.legend.combined ? state.legend.combined : defaultSeriesLabel;
    }
    if (colorSeries) colorSeries.value = (state.colors && state.colors.series) ? state.colors.series : '#03283E';
    if (colorAverage) colorAverage.value = (state.colors && state.colors.average) ? state.colors.average : '#a8dbe6';

    if (chartTitleInput) chartTitleInput.value = (state.chart && state.chart.title) ? state.chart.title : '';
    if (axisXInput) axisXInput.value = (state.chart && state.chart.xTitle) ? state.chart.xTitle : '';
    if (axisYInput) axisYInput.value = (state.chart && state.chart.yTitle) ? state.chart.yTitle : '';

    if (toggleMinMax) toggleMinMax.checked = !!(state.toggles && state.toggles.minmax);

    var chart = canvas ? ensureChart(canvas) : null;

    renderRows(tbody, state.rows);
    updateChart(wrap, chart, state.rows, state);

    function commit(){
      updateAverageCells(tbody, state.rows);
      updateChart(wrap, chart, state.rows, state);
      save(instanceId, state);
    }

    wrap.addEventListener('input', function(e){
      var el = e.target;

      // Toggles
      if (el && el.matches && el.matches('[data-dttc-toggle="minmax"]')){
        state.toggles = state.toggles || { minmax:false };
        state.toggles.minmax = !!el.checked;
        commit();
        return;
      }

      // Chart title + axis titles
      if (el && el.matches && el.matches('[data-dttc-chart-title]')){
        state.chart = state.chart || { title:'', xTitle:'', yTitle:'' };
        state.chart.title = el.value || '';
        commit();
        return;
      }
      if (el && el.matches && el.matches('[data-dttc-axis-x]')){
        state.chart = state.chart || { title:'', xTitle:'', yTitle:'' };
        state.chart.xTitle = el.value || '';
        commit();
        return;
      }
      if (el && el.matches && el.matches('[data-dttc-axis-y]')){
        state.chart = state.chart || { title:'', xTitle:'', yTitle:'' };
        state.chart.yTitle = el.value || '';
        commit();
        return;
      }

      // Legend text
      if (el && el.matches && el.matches('[data-dttc-legend="combined"]')){
        state.legend = state.legend || {};
        state.legend.combined = el.value || defaultSeriesLabel;
        commit();
        return;
      }

      // Colours
      if (el && el.matches && el.matches('[data-dttc-color="series"]')){
        state.colors = state.colors || {};
        state.colors.series = el.value || '#03283E';
        commit();
        return;
      }
      if (el && el.matches && el.matches('[data-dttc-color="average"]')){
        state.colors = state.colors || {};
        state.colors.average = el.value || '#a8dbe6';
        commit();
        return;
      }

      if (!(el instanceof HTMLInputElement)) return;

      var field = el.getAttribute('data-dttc-field');
      var index = el.getAttribute('data-dttc-index');
      if (!field || index === null) return;

      var idx = Number(index);
      if (!state.rows[idx]) return;

      if (field === 'label') state.rows[idx].label = el.value;
      if (field === 'a') state.rows[idx].a = toNum(el.value);
      if (field === 'b') state.rows[idx].b = toNum(el.value);

      commit();
    });

    
wrap.addEventListener('click', function(e){
      var btn = e.target.closest('button[data-dttc-action]');
      if (!btn) return;

      var action = btn.getAttribute('data-dttc-action');

      if (action === 'add-row'){
        state.rows.push({ label: 'Row ' + (state.rows.length + 1), a: null, b: null });
        renderRows(tbody, state.rows);
        commit();
        return;
      }

      if (action === 'remove-row'){
        var rem = Number(btn.getAttribute('data-dttc-index'));
        if (Number.isFinite(rem) && state.rows[rem]){
          state.rows.splice(rem, 1);
          if (state.rows.length === 0) state.rows = defaultState(1, defaultSeriesLabel).rows;
          renderRows(tbody, state.rows);
          commit();
        }
        return;
      }

      if (action === 'clear'){
        state = defaultState(rowsCount, defaultSeriesLabel);
        if (legendInput) legendInput.value = state.legend.combined;
        if (colorSeries) colorSeries.value = state.colors.series;
        if (colorAverage) colorAverage.value = state.colors.average;
        if (chartTitleInput) chartTitleInput.value = state.chart.title;
        if (axisXInput) axisXInput.value = state.chart.xTitle;
        if (axisYInput) axisYInput.value = state.chart.yTitle;
        if (toggleMinMax) toggleMinMax.checked = !!state.toggles.minmax;
        renderRows(tbody, state.rows);
        commit();
        return;
      }

      
      if (action === 'open-import'){
        togglePanel(wrap.querySelector('[data-dttc-import-panel]'), true);
        togglePanel(wrap.querySelector('[data-dttc-paste-panel]'), false);
        return;
      }
      if (action === 'close-import'){
        togglePanel(wrap.querySelector('[data-dttc-import-panel]'), false);
        return;
      }
      if (action === 'open-paste'){
        togglePanel(wrap.querySelector('[data-dttc-paste-panel]'), true);
        togglePanel(wrap.querySelector('[data-dttc-import-panel]'), false);
        return;
      }
      if (action === 'close-paste'){
        togglePanel(wrap.querySelector('[data-dttc-paste-panel]'), false);
        return;
      }
      if (action === 'import-csv'){
        var fileInput = wrap.querySelector('[data-dttc-file]');
        if (!fileInput || !fileInput.files || !fileInput.files[0]) return;
        var reader = new FileReader();
        reader.onload = function(){
          var rows = parseCSV(String(reader.result || ''));
          if (rows && rows.length){
            state.rows = rows;
            renderRows(tbody, state.rows);
            commit();
            togglePanel(wrap.querySelector('[data-dttc-import-panel]'), false);
          }
        };
        reader.readAsText(fileInput.files[0]);
        return;
      }
      if (action === 'apply-paste'){
        var ta = wrap.querySelector('[data-dttc-paste]');
        var text = ta ? String(ta.value || '') : '';
        var rows = parsePaste(text);
        if (rows && rows.length){
          state.rows = rows;
          renderRows(tbody, state.rows);
          commit();
          togglePanel(wrap.querySelector('[data-dttc-paste-panel]'), false);
        }
        return;
      }

if (action === 'download'){
        downloadCSV('table-data-' + instanceId + '.csv', state.rows);
        return;
      }

      if (action === 'download-png' && canvas){
        downloadPNGFromCanvas(canvas, 'chart-' + instanceId + '.png');
        return;
      }

      if (action === 'download-pdf' && canvas){
        var ok = downloadPDFfromCanvas(canvas, 'chart-' + instanceId + '.pdf');
        if (!ok) downloadPNGFromCanvas(canvas, 'chart-' + instanceId + '.png');
      }
    });


    // Expose a small API for the workspace to load/save from DB.
    window.DTTC_API = window.DTTC_API || {};
    window.DTTC_API[instanceId] = {
      getState: function(){
        try { return JSON.parse(JSON.stringify(state)); } catch(e){ return state; }
      },
      setState: function(next){
        var base = defaultState(rowsCount, defaultSeriesLabel);
        if (next && typeof next === 'object'){
          if (Array.isArray(next.rows)) base.rows = next.rows;
          if (next.legend && typeof next.legend === 'object') base.legend = next.legend;
          if (next.colors && typeof next.colors === 'object') base.colors = next.colors;
          if (next.chart && typeof next.chart === 'object') base.chart = next.chart;
          if (next.toggles && typeof next.toggles === 'object') base.toggles = next.toggles;
        }
        // Pad rows up to the configured minimum row count
        while (base.rows.length < rowsCount) {
          base.rows.push({ label: 'Row ' + (base.rows.length + 1), a: null, b: null });
        }
        state = base;
        // Push state back into inputs
        if (legendInput) legendInput.value = (state.legend && state.legend.combined) ? state.legend.combined : defaultSeriesLabel;
        if (colorSeries) colorSeries.value = (state.colors && state.colors.series) ? state.colors.series : '#03283E';
        if (colorAverage) colorAverage.value = (state.colors && state.colors.average) ? state.colors.average : '#a8dbe6';
        if (chartTitleInput) chartTitleInput.value = (state.chart && state.chart.title) ? state.chart.title : '';
        if (axisXInput) axisXInput.value = (state.chart && state.chart.xTitle) ? state.chart.xTitle : '';
        if (axisYInput) axisYInput.value = (state.chart && state.chart.yTitle) ? state.chart.yTitle : '';
        if (toggleMinMax) toggleMinMax.checked = !!(state.toggles && state.toggles.minmax);
        renderRows(tbody, state.rows);
        commit();
      }
    };
    save(instanceId, state);
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.dttc-wrap').forEach(initWrap);
  });
})();