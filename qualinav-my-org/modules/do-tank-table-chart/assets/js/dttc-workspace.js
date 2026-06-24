(function () {
  'use strict';

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function api() {
    if (!window.DTTC_API) return null;
    return window.DTTC_API.workspace || null;
  }

  function wsCfg() { return window.DTTC_WORKSPACE || null; }

  function fetchJson(url, opts) {
    opts = opts || {};
    opts.headers = opts.headers || {};
    opts.headers['X-WP-Nonce'] = wsCfg() ? wsCfg().nonce : '';
    if (opts.method && opts.method !== 'GET') {
      opts.headers['Content-Type'] = 'application/json';
    }
    return fetch(url, opts).then(function (r) {
      return r.json().then(function (data) {
        if (!r.ok) {
          var msg = (data && data.message) ? data.message : ('Request failed (' + r.status + ')');
          throw new Error(msg);
        }
        return data;
      });
    });
  }

  function setStatus(text) {
    var el = $('#dttc-save-status');
    if (el) el.textContent = text || '';
  }

  function stateToPayload(state) {
    state = state || {};
    var rows = Array.isArray(state.rows) ? state.rows : [];
    var outRows = rows.map(function (r, idx) {
      return {
        row_index: idx + 1,
        time_label: r && r.label ? String(r.label) : '',
        numerator: (r && r.a !== undefined ? r.a : null),
        denominator: (r && r.b !== undefined ? r.b : null)
      };
    });

    return {
      rows: outRows,
      state: state
    };
  }

  function serverToState(server) {
    if (server && server.state && typeof server.state === 'object') return server.state;

    // Fallback: build minimal state from rows table
    var rows = Array.isArray(server.rows) ? server.rows : [];
    return {
      rows: rows.map(function (r) {
        return { label: r.time_label || '', a: (r.numerator === null ? null : Number(r.numerator)), b: (r.denominator === null ? null : Number(r.denominator)) };
      }),
      legend: { combined: 'Series' },
      colors: { series: '#03283E', average: '#a8dbe6' },
      chart: { title: '', xTitle: '', yTitle: '' },
      toggles: { minmax: false }
    };
  }

  var currentChartId = null;
  var currentChartShared = false;
  var currentChartIsSharedWithMe = false;
  var currentChartOwnerName = '';
  var chartsCache = [];

  function formatChartDate(gmtStr) {
    if (!gmtStr || gmtStr === '0000-00-00 00:00:00') return '';
    // Parse as UTC then display in local time
    var d = new Date(gmtStr.replace(' ', 'T') + 'Z');
    if (isNaN(d.getTime())) return '';
    var now = new Date();
    var diffMs = now - d;
    var diffMin = Math.floor(diffMs / 60000);
    var diffHr = Math.floor(diffMs / 3600000);

    // Less than 1 minute
    if (diffMin < 1) return 'Just now';
    // Less than 60 minutes
    if (diffMin < 60) return diffMin + 'm ago';
    // Less than 24 hours
    if (diffHr < 24) return diffHr + 'h ago';

    // Otherwise show date and time
    var month = d.toLocaleString(undefined, { month: 'short' });
    var day = d.getDate();
    var hours = d.getHours();
    var minutes = d.getMinutes();
    var ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    var minStr = minutes < 10 ? '0' + minutes : String(minutes);

    // If same year, omit year
    if (d.getFullYear() === now.getFullYear()) {
      return month + ' ' + day + ', ' + hours + ':' + minStr + ' ' + ampm;
    }
    return month + ' ' + day + ', ' + d.getFullYear() + ' ' + hours + ':' + minStr + ' ' + ampm;
  }

  function renderList(charts) {
    var list = $('#dttc-chart-list');
    var empty = $('#dttc-no-charts');
    if (!list) return;

    $all('.dttc-ws-item', list).forEach(function (n) { n.remove(); });

    if (!charts || !charts.length) {
      if (empty) empty.style.display = '';
      return;
    }

    if (empty) empty.style.display = 'none';

    charts.forEach(function (c) {
      var wrap = document.createElement('div');
      wrap.className = 'dttc-ws-item' + (currentChartId === c.id ? ' is-active' : '') + (c.is_shared ? ' dttc-ws-item-shared-with-me' : '');
      wrap.setAttribute('data-id', String(c.id));
      wrap.setAttribute('draggable', c.is_shared ? 'false' : 'true'); // Don't allow reordering shared charts

      // Drag Handle (6 dots)
      var handle = document.createElement('div');
      handle.className = 'dttc-ws-drag-handle';
      handle.innerHTML = '<i class="fas fa-grip-vertical"></i>';
      wrap.appendChild(handle);

      var titleBtn = document.createElement('button');
      titleBtn.type = 'button';
      titleBtn.className = 'dttc-ws-item-pick';

      // Show updated date if it differs from created date, otherwise show created date
      var dateStr = '';
      var prefix = '';
      if (c.modified_gmt && c.created_gmt && c.modified_gmt !== c.created_gmt) {
        dateStr = formatChartDate(c.modified_gmt);
        prefix = 'Updated ';
      } else if (c.created_gmt) {
        dateStr = formatChartDate(c.created_gmt);
        prefix = 'Created ';
      }

      // Show owner info for shared charts (where current user is tagged, not owner)
      var ownerHtml = '';
      if (c.is_shared && c.owner_name) {
        ownerHtml = '<div class="dttc-ws-item-owner"><i class="fas fa-user"></i> ' + String(c.owner_name) + '</div>';
      }

      titleBtn.innerHTML = ownerHtml
        + '<div class="dttc-ws-item-title">' + (c.title ? String(c.title) : ('Chart ' + c.id)) + '</div>'
        + (dateStr ? '<div class="dttc-ws-item-date">' + prefix + dateStr + '</div>' : '')
        + (c.shared ? '<div class="dttc-ws-item-shared"><i class="fas fa-share-alt"></i> Shared</div>' : '');
      titleBtn.addEventListener('click', function () {
        selectChart(c.id);
      });

      // Drag and Drop Events
      wrap.addEventListener('dragstart', function (ev) {
        ev.dataTransfer.setData('text/plain', c.id);
        wrap.classList.add('is-dragging');
      });

      wrap.addEventListener('dragend', function () {
        wrap.classList.remove('is-dragging');
        $all('.dttc-ws-item').forEach(function (el) { el.classList.remove('drag-over'); });
      });

      wrap.addEventListener('dragover', function (ev) {
        ev.preventDefault();
        wrap.classList.add('drag-over');
      });

      wrap.addEventListener('dragleave', function () {
        wrap.classList.remove('drag-over');
      });

      wrap.addEventListener('drop', function (ev) {
        ev.preventDefault();
        wrap.classList.remove('drag-over');
        var draggedId = ev.dataTransfer.getData('text/plain');
        if (String(draggedId) === String(c.id)) return;

        var draggedEl = $('.dttc-ws-item[data-id="' + draggedId + '"]');
        if (draggedEl) {
          var list = $('#dttc-chart-list');
          var allItems = $all('.dttc-ws-item', list);
          var draggedIdx = allItems.indexOf(draggedEl);
          var targetIdx = allItems.indexOf(wrap);

          if (draggedIdx < targetIdx) {
            wrap.parentNode.insertBefore(draggedEl, wrap.nextSibling);
          } else {
            wrap.parentNode.insertBefore(draggedEl, wrap);
          }

          sendReorder();
        }
      });

      wrap.appendChild(titleBtn);
      list.appendChild(wrap);
    });
  }

  function sendReorder() {
    var cfg = wsCfg();
    if (!cfg) return;

    var ids = $all('.dttc-ws-item').map(function (el) {
      return parseInt(el.getAttribute('data-id'), 10);
    });

    fetchJson(cfg.restUrl + '/charts/reorder', {
      method: 'POST',
      body: JSON.stringify({ ids: ids })
    }).catch(function (err) {
      setStatus(err.message);
    });
  }

  function loadList() {
    var cfg = wsCfg();
    if (!cfg) return;
    return fetchJson(cfg.restUrl + '/charts').then(function (res) {
      chartsCache = res.charts || [];
      renderList(chartsCache);
    }).catch(function (err) {
      setStatus(err.message);
    });
  }

  function selectChart(id) {
    var cfg = wsCfg();
    if (!cfg) return;
    currentChartId = id;
    setStatus('Loading…');

    renderList(chartsCache);

    fetchJson(cfg.restUrl + '/charts/' + id).then(function (res) {
      var a = api();
      if (!a) {
        setStatus('Chart UI not ready');
        return;
      }

      // Populate title + notes
      var titleInput = $('#dttc-chart-title');
      if (titleInput) titleInput.value = res.title || '';

      var notes = $('#dttc-chart-notes');
      if (notes) notes.value = res.notes || '';

      // Push full state into chart UI
      var st = serverToState(res);
      a.setState(st);

      // Track shared status
      currentChartShared = !!res.shared;
      currentChartIsSharedWithMe = !!res.is_shared;
      currentChartOwnerName = res.owner_name || '';
      updateShareButton();
      updateOwnerBanner();

      setStatus('Loaded');
    }).catch(function (err) {
      setStatus(err.message);
    });
  }

  function createChart() {
    var cfg = wsCfg();
    if (!cfg) return;

    setStatus('Creating…');
    fetchJson(cfg.restUrl + '/charts', {
      method: 'POST',
      body: JSON.stringify({ title: 'New Chart' })
    }).then(function (res) {
      return loadList().then(function () {
        // Select newly created chart
        selectChart(res.id);
      });
    }).catch(function (err) {
      setStatus(err.message);
    });
  }

  function saveChart() {
    if (!currentChartId) {
      setStatus('Create or select a chart first');
      return;
    }

    var cfg = wsCfg();
    if (!cfg) return;

    var a = api();
    if (!a) {
      setStatus('Chart UI not ready');
      return;
    }

    var title = $('#dttc-chart-title') ? String($('#dttc-chart-title').value || '') : '';
    var notes = $('#dttc-chart-notes') ? String($('#dttc-chart-notes').value || '') : '';

    var st = a.getState();
    var payload = stateToPayload(st);
    payload.title = title;
    payload.notes = notes;

    setStatus('Saving…');
    fetchJson(cfg.restUrl + '/charts/' + currentChartId, {
      method: 'POST',
      body: JSON.stringify(payload)
    }).then(function () {
      setStatus('Saved');
      // Redirect to My Data page after successful save
      if (cfg.myDataUrl) {
        window.location.href = cfg.myDataUrl;
      }
    }).catch(function (err) {
      setStatus(err.message);
    });
  }

  function deleteChart(id, title) {
    var cfg = wsCfg();
    if (!cfg) return;

    var name = title ? String(title) : ('Chart ' + id);
    if (!window.confirm('Delete "' + name + '"? This will move it to Trash.')) {
      return;
    }

    setStatus('Deleting…');
    fetchJson(cfg.restUrl + '/charts/' + id, {
      method: 'DELETE'
    }).then(function () {
      // If the deleted chart was selected, clear editor.
      if (currentChartId === id) {
        currentChartId = null;
        var titleInput = $('#dttc-chart-title');
        if (titleInput) titleInput.value = '';
        var notes = $('#dttc-chart-notes');
        if (notes) notes.value = '';
      }
      setStatus('Deleted');
      return loadList();
    }).catch(function (err) {
      setStatus(err.message);
    });
  }

  function updateShareButton() {
    var btn = $('#dttc-share-chart-trigger');
    var label = $('#dttc-share-label');
    if (!btn || !label) return;

    if (currentChartShared) {
      label.textContent = 'Unshare from Public Charts';
      btn.classList.add('dttc-ws-shared');
    } else {
      label.textContent = 'Share to Public Charts';
      btn.classList.remove('dttc-ws-shared');
    }
  }

  function updateOwnerBanner() {
    var banner = $('#dttc-owner-banner');
    var ownerNameEl = $('#dttc-owner-name');

    // Create banner if it doesn't exist
    if (!banner) {
      var editorHeader = $('.dttc-editor-header');
      if (editorHeader) {
        var bannerHtml = '<div id="dttc-owner-banner" class="dttc-owner-banner" style="display:none;">' +
          '<i class="fas fa-user-friends"></i> ' +
          'Shared by <strong id="dttc-owner-name"></strong> — This chart is view-only' +
          '</div>';
        editorHeader.insertAdjacentHTML('afterend', bannerHtml);
        banner = $('#dttc-owner-banner');
        ownerNameEl = $('#dttc-owner-name');
      }
    }

    if (!banner) return;

    if (currentChartIsSharedWithMe && currentChartOwnerName) {
      if (ownerNameEl) ownerNameEl.textContent = currentChartOwnerName;
      banner.style.display = '';
      // Disable editing for shared charts
      disableEditingForSharedChart(true);
    } else {
      banner.style.display = 'none';
      disableEditingForSharedChart(false);
    }
  }

  function disableEditingForSharedChart(disable) {
    var titleInput = $('#dttc-chart-title');
    var notesInput = $('#dttc-chart-notes');
    var saveBtn = $('#dttc-save-chart');
    var menuTrigger = $('#dttc-menu-toggle');

    if (titleInput) titleInput.readOnly = disable;
    if (notesInput) notesInput.readOnly = disable;
    if (saveBtn) saveBtn.style.display = disable ? 'none' : '';
    if (menuTrigger) menuTrigger.style.display = disable ? 'none' : '';

    // Also disable chart data editing via the API
    var a = api();
    if (a && typeof a.setReadOnly === 'function') {
      a.setReadOnly(disable);
    }
  }

  function toggleShare() {
    if (!currentChartId) {
      setStatus('Select a chart first');
      return;
    }

    var cfg = wsCfg();
    if (!cfg) return;

    var action = currentChartShared ? 'Unsharing' : 'Sharing';
    setStatus(action + '…');

    fetchJson(cfg.restUrl + '/charts/' + currentChartId + '/share', {
      method: 'POST',
      body: JSON.stringify({})
    }).then(function (res) {
      currentChartShared = !!res.shared;
      updateShareButton();
      setStatus(currentChartShared ? 'Shared to Public Charts' : 'Removed from Public Charts');
      // Update cache so sidebar reflects the change
      chartsCache.forEach(function (c) {
        if (c.id === currentChartId) c.shared = currentChartShared;
      });
      renderList(chartsCache);
    }).catch(function (err) {
      setStatus(err.message);
    });
  }

  // ── Tag Member Modal ──

  function debounce(fn, ms) {
    var timer;
    return function () {
      var args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(null, args); }, ms);
    };
  }

  function openTagModal() {
    if (!currentChartId) {
      setStatus('Select a chart first');
      return;
    }
    var overlay = $('#dttc-tag-overlay');
    if (overlay) {
      overlay.style.display = 'flex';
      var input = $('#dttc-tag-search');
      if (input) { input.value = ''; input.focus(); }
      $('#dttc-tag-results') && ($('#dttc-tag-results').innerHTML = '');
      loadTaggedUsers();
    }
  }

  function closeTagModal() {
    var overlay = $('#dttc-tag-overlay');
    if (overlay) overlay.style.display = 'none';
  }

  function getInitials(name) {
    if (!name) return '?';
    var parts = name.trim().split(/\s+/);
    if (parts.length > 1) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    return parts[0][0].toUpperCase();
  }

  function loadTaggedUsers() {
    var cfg = wsCfg();
    if (!cfg || !currentChartId) return;

    var list = $('#dttc-tag-list');
    var empty = $('#dttc-tag-empty');
    if (!list) return;

    fetchJson(cfg.restUrl + '/charts/' + currentChartId + '/tags').then(function (res) {
      var users = res.tagged_users || [];
      if (!users.length) {
        list.innerHTML = '';
        if (empty) empty.style.display = 'block';
        return;
      }
      if (empty) empty.style.display = 'none';

      list.innerHTML = users.map(function (u) {
        return '<div class="dttc-tag-chip">' +
          '<span class="dttc-tag-avatar">' + getInitials(u.display_name) + '</span>' +
          '<span class="dttc-tag-chip-name">' + String(u.display_name) + '</span>' +
          (u.assigned_by_name ? '<span class="dttc-tag-chip-by">by ' + String(u.assigned_by_name) + '</span>' : '') +
          '<button type="button" class="dttc-tag-remove" data-uid="' + u.user_id + '" title="Remove">&times;</button>' +
          '</div>';
      }).join('');

      // Bind remove buttons
      $all('.dttc-tag-remove', list).forEach(function (btn) {
        btn.addEventListener('click', function () {
          untagUser(parseInt(btn.getAttribute('data-uid'), 10));
        });
      });
    }).catch(function () {
      list.innerHTML = '<div class="dttc-tag-empty">Could not load tagged members.</div>';
    });
  }

  var debouncedSearch = debounce(function (query) {
    var cfg = wsCfg();
    var results = $('#dttc-tag-results');
    if (!cfg || !results) return;

    if (!query || query.length < 2) {
      results.innerHTML = '';
      return;
    }

    fetchJson(cfg.restUrl + '/users?search=' + encodeURIComponent(query)).then(function (res) {
      var users = res.users || [];
      if (!users.length) {
        results.innerHTML = '<div class="dttc-tag-no-results">No members found</div>';
        return;
      }
      results.innerHTML = users.map(function (u) {
        return '<div class="dttc-tag-result-item" data-uid="' + u.id + '">' +
          '<span class="dttc-tag-avatar">' + getInitials(u.display_name) + '</span>' +
          '<span class="dttc-tag-result-name">' + String(u.display_name) + '</span>' +
          '<button type="button" class="dttc-tag-add-btn">Tag</button>' +
          '</div>';
      }).join('');

      $all('.dttc-tag-add-btn', results).forEach(function (btn) {
        btn.addEventListener('click', function () {
          var row = btn.closest('.dttc-tag-result-item');
          if (row) tagUser(parseInt(row.getAttribute('data-uid'), 10));
        });
      });
    }).catch(function () {
      results.innerHTML = '';
    });
  }, 300);

  function tagUser(userId) {
    var cfg = wsCfg();
    if (!cfg || !currentChartId || !userId) return;

    fetchJson(cfg.restUrl + '/charts/' + currentChartId + '/tag', {
      method: 'POST',
      body: JSON.stringify({ user_id: userId })
    }).then(function () {
      var input = $('#dttc-tag-search');
      var results = $('#dttc-tag-results');
      if (input) input.value = '';
      if (results) results.innerHTML = '';
      loadTaggedUsers();
    }).catch(function (err) {
      setStatus(err.message);
    });
  }

  function untagUser(userId) {
    var cfg = wsCfg();
    if (!cfg || !currentChartId || !userId) return;

    fetchJson(cfg.restUrl + '/charts/' + currentChartId + '/tag/' + userId, {
      method: 'DELETE'
    }).then(function () {
      loadTaggedUsers();
    }).catch(function (err) {
      setStatus(err.message);
    });
  }

  function applySearch() {
    var q = ($('#dttc-chart-search') && $('#dttc-chart-search').value) ? $('#dttc-chart-search').value.toLowerCase() : '';
    var filtered = chartsCache.filter(function (c) {
      return !q || (c.title || '').toLowerCase().indexOf(q) !== -1;
    });
    renderList(filtered);
  }

  function bind() {
    var btnNew = $('#dttc-new-chart');
    if (btnNew) btnNew.addEventListener('click', createChart);

    var btnSave = $('#dttc-save-chart');
    if (btnSave) btnSave.addEventListener('click', saveChart);

    var search = $('#dttc-chart-search');
    if (search) search.addEventListener('input', applySearch);

    // Header Kebab Menu
    var menuToggle = $('#dttc-menu-toggle');
    var menuDropdown = $('#dttc-menu-dropdown');
    if (menuToggle && menuDropdown) {
      menuToggle.addEventListener('click', function (ev) {
        ev.stopPropagation();
        menuDropdown.classList.toggle('is-open');
      });

      document.addEventListener('click', function () {
        menuDropdown.classList.remove('is-open');
      });

      // Menu Actions
      var btnRename = $('#dttc-rename-chart-trigger');
      if (btnRename) {
        btnRename.addEventListener('click', function () {
          var input = $('#dttc-chart-title');
          if (input) {
            input.focus();
            input.select();
          }
        });
      }

      var btnShare = $('#dttc-share-chart-trigger');
      if (btnShare) {
        btnShare.addEventListener('click', function () {
          toggleShare();
        });
      }

      var btnTag = $('#dttc-tag-member-trigger');
      if (btnTag) {
        btnTag.addEventListener('click', function () {
          openTagModal();
        });
      }

      var btnDelete = $('#dttc-delete-chart-trigger');
      if (btnDelete) {
        btnDelete.addEventListener('click', function () {
          if (!currentChartId) return;
          var title = $('#dttc-chart-title') ? String($('#dttc-chart-title').value || '') : '';
          deleteChart(currentChartId, title);
        });
      }
    }

    // Tag modal events
    var tagClose = $('#dttc-tag-close');
    if (tagClose) tagClose.addEventListener('click', closeTagModal);

    var tagOverlay = $('#dttc-tag-overlay');
    if (tagOverlay) {
      tagOverlay.addEventListener('click', function (ev) {
        if (ev.target === tagOverlay) closeTagModal();
      });
    }

    var tagSearch = $('#dttc-tag-search');
    if (tagSearch) {
      tagSearch.addEventListener('input', function () {
        debouncedSearch(tagSearch.value.trim());
      });
    }
  }

  function getUrlParam(name) {
    var params = new URLSearchParams(window.location.search);
    return params.get(name);
  }

  // ── Floating Presence Avatars (Figma-style) ──

  var presencePollingInterval = null;
  var presenceVisible = true;

  function initPresenceTracking() {
    // Create floating presence container if not exists
    if (!$('#dttc-floating-presence')) {
      var container = document.createElement('div');
      container.id = 'dttc-floating-presence';
      container.className = 'dttc-floating-presence';
      container.innerHTML =
        '<div class="dttc-floating-presence-header">' +
          '<span class="dttc-floating-presence-label">Viewing now</span>' +
          '<span class="dttc-floating-presence-live"><i class="fas fa-circle"></i> Live</span>' +
        '</div>' +
        '<div class="dttc-floating-presence-avatars"></div>';
      document.body.appendChild(container);
    }

    // Start polling
    startPresencePolling();

    // Handle visibility changes (pause when tab is hidden)
    document.addEventListener('visibilitychange', function() {
      if (document.hidden) {
        presenceVisible = false;
        stopPresencePolling();
      } else {
        presenceVisible = true;
        startPresencePolling();
      }
    });
  }

  function startPresencePolling() {
    if (presencePollingInterval) return;

    // Register presence immediately
    registerChartPresence();
    fetchChartPresence();

    // Poll every 10 seconds
    presencePollingInterval = setInterval(function() {
      registerChartPresence();
      fetchChartPresence();
    }, 10000);
  }

  function stopPresencePolling() {
    if (presencePollingInterval) {
      clearInterval(presencePollingInterval);
      presencePollingInterval = null;
    }
  }

  function getCmNonce() {
    // Check for cmNonce from workspace config (preferred)
    var cfg = wsCfg();
    if (cfg && cfg.cmNonce) {
      return cfg.cmNonce;
    }
    // Check for cmAjax (from commitment management plugin)
    if (window.cmAjax && window.cmAjax.nonce) {
      return window.cmAjax.nonce;
    }
    // Check for workflowAjax (from workflow page)
    if (window.workflowAjax && window.workflowAjax.nonce) {
      return window.workflowAjax.nonce;
    }
    return '';
  }

  function registerChartPresence() {
    if (!currentChartId) return;

    var nonce = getCmNonce();
    if (!nonce) return;

    // Use the commitment management AJAX for presence (unified system)
    var formData = new FormData();
    formData.append('action', 'cm_register_presence');
    formData.append('nonce', nonce);
    formData.append('items', JSON.stringify([{ type: 'chart', id: currentChartId }]));
    formData.append('editing', 'false');

    fetch(window.location.origin + '/wp-admin/admin-ajax.php', {
      method: 'POST',
      body: formData
    }).catch(function() {});
  }

  function fetchChartPresence() {
    if (!currentChartId) {
      hideChartFloatingPresence();
      return;
    }

    var nonce = getCmNonce();
    if (!nonce) return;

    var formData = new FormData();
    formData.append('action', 'cm_get_presence');
    formData.append('nonce', nonce);
    formData.append('items', JSON.stringify([{ type: 'chart', id: currentChartId }]));

    fetch(window.location.origin + '/wp-admin/admin-ajax.php', {
      method: 'POST',
      body: formData
    }).then(function(res) {
      return res.json();
    }).then(function(res) {
      if (!res.success || !res.data.presence) return;

      var allUsers = [];
      res.data.presence.forEach(function(item) {
        if (item.users && item.users.length > 0) {
          item.users.forEach(function(user) {
            // Avoid duplicates
            var exists = allUsers.some(function(u) { return u.user_id === user.user_id; });
            if (!exists) {
              allUsers.push(user);
            }
          });
        }
      });

      renderChartFloatingPresence(allUsers);
    }).catch(function() {});
  }

  function renderChartFloatingPresence(users) {
    var container = $('#dttc-floating-presence');
    if (!container) return;

    var avatarsContainer = container.querySelector('.dttc-floating-presence-avatars');
    if (!avatarsContainer) return;

    if (users.length === 0) {
      hideChartFloatingPresence();
      return;
    }

    avatarsContainer.innerHTML = '';

    // Show max 5 avatars
    var maxShow = 5;
    var shown = users.slice(0, maxShow);
    var overflow = users.length - maxShow;

    shown.forEach(function(user) {
      var editingClass = user.is_editing ? ' dttc-presence-avatar-editing' : '';
      var avatar = document.createElement('div');
      avatar.className = 'dttc-presence-avatar' + editingClass;
      avatar.title = user.name;
      avatar.innerHTML = '<span class="dttc-presence-initials">' + user.initials + '</span>' +
        (user.is_editing ? '<span class="dttc-presence-edit-indicator"><i class="fas fa-pencil-alt"></i></span>' : '');
      avatarsContainer.appendChild(avatar);
    });

    if (overflow > 0) {
      var overflowEl = document.createElement('div');
      overflowEl.className = 'dttc-presence-overflow';
      overflowEl.textContent = '+' + overflow;
      avatarsContainer.appendChild(overflowEl);
    }

    showChartFloatingPresence();
  }

  function showChartFloatingPresence() {
    var container = $('#dttc-floating-presence');
    if (container && !container.classList.contains('visible')) {
      container.classList.add('visible');
    }
  }

  function hideChartFloatingPresence() {
    var container = $('#dttc-floating-presence');
    if (container) {
      container.classList.remove('visible');
    }
  }

  function ready() {
    // Wait for base chart UI to initialize and expose API.
    var tries = 0;
    var t = setInterval(function () {
      tries++;
      if (api()) {
        clearInterval(t);
        bind();
        loadList().then(function () {
          // Auto-select chart if ?chart=ID or ?open_chart=ID is in the URL
          var chartParam = getUrlParam('chart') || getUrlParam('open_chart');
          if (chartParam) {
            selectChart(parseInt(chartParam, 10));
          }
        });
        setStatus('');

        // Initialize presence tracking
        initPresenceTracking();
      }
      if (tries > 80) {
        clearInterval(t);
        setStatus('Chart UI failed to initialize');
      }
    }, 100);
  }

  document.addEventListener('DOMContentLoaded', ready);
})();
