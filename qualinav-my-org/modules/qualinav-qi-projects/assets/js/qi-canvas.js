/**
 * Qualinav QI Projects — front-end interactions.
 *
 * Hooks up the renderer's data-* attributes to the REST API:
 *   - tab switching
 *   - add / edit / delete card
 *   - autosave single_textarea on blur
 *
 * Uses cookie auth + X-WP-Nonce. No build step, no framework.
 */
(function () {
	'use strict';

	if (typeof QualinavQI === 'undefined') return;

	function isCanvasReadonly(el) {
		if (!el || !el.closest) return false;
		var c = el.closest('.qi-canvas');
		return !!(c && c.classList.contains('qi-canvas--readonly'));
	}

	// Apply DOM-level guards once on load (textareas, slider disabled attr).
	(function applyReadonlyGuards() {
		document.querySelectorAll('.qi-canvas--readonly').forEach(function (canvas) {
			canvas.querySelectorAll('.qi-textarea').forEach(function (ta) {
				ta.setAttribute('readonly', 'readonly');
			});
			canvas.querySelectorAll('.qi-idea-slider').forEach(function (s) {
				s.setAttribute('disabled', 'disabled');
			});
		});
	})();

	function rest(path, options) {
		options = options || {};
		options.headers = Object.assign(
			{ 'Content-Type': 'application/json', 'X-WP-Nonce': QualinavQI.nonce },
			options.headers || {}
		);
		options.credentials = 'same-origin';
		return fetch(QualinavQI.restUrl + path, options).then(function (r) {
			if (!r.ok) {
				return r.json().then(function (err) {
					throw new Error((err && err.message) || ('HTTP ' + r.status));
				});
			}
			return r.json();
		});
	}

	function escHtml(s) {
		var div = document.createElement('div');
		div.textContent = s == null ? '' : String(s);
		return div.innerHTML;
	}

	// ---------- Tab switching ----------

	function activateTab(canvas, key) {
		if (!canvas || !key) return false;
		var found = false;
		canvas.querySelectorAll('.qi-tab').forEach(function (b) {
			var match = b.getAttribute('data-tab') === key;
			if (match) found = true;
			b.classList.toggle('is-active', match);
		});
		if (!found) return false;
		canvas.querySelectorAll('.qi-tab-panel').forEach(function (p) {
			p.classList.toggle('is-active', p.getAttribute('data-tab') === key);
		});
		return true;
	}

	document.addEventListener('click', function (e) {
		var t = e.target.closest('.qi-tab');
		if (!t) return;
		var canvas = t.closest('.qi-canvas');
		if (!canvas) return;
		var key = t.getAttribute('data-tab');
		if (activateTab(canvas, key)) {
			// Open the newly selected tab scrolled to the top instead of
			// inheriting the previous tab's scroll position. The canvas
			// content scrolls inside .qi-canvas-content, not the window.
			canvas.querySelectorAll('.qi-canvas-content').forEach(function (c) {
				c.scrollTop = 0;
			});
			window.scrollTo(0, 0);
			// Persist active tab in URL hash so reloads stay on the same tab.
			if (history && history.replaceState) {
				history.replaceState(null, '', '#tab=' + encodeURIComponent(key));
			} else {
				location.hash = 'tab=' + encodeURIComponent(key);
			}
		}
	});

	// On load, restore tab from hash (e.g. #tab=matrix_diagram)
	(function restoreTabFromHash() {
		var hash = (location.hash || '').replace(/^#/, '');
		if (!hash) return;
		var m = hash.match(/(?:^|&)tab=([^&]+)/);
		if (!m) return;
		var key = decodeURIComponent(m[1]);
		document.querySelectorAll('.qi-canvas').forEach(function (canvas) {
			activateTab(canvas, key);
		});
	})();

	// ---------- Project ID resolver ----------

	function projectId(el) {
		var canvas = el.closest('.qi-canvas');
		return canvas ? parseInt(canvas.getAttribute('data-project-id'), 10) : 0;
	}

	// ---------- Add card ----------

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.qi-add-card');
		if (!btn) return;
		if (isCanvasReadonly(btn)) return;
		var fieldPath = btn.getAttribute('data-field-path');
		var slot      = btn.getAttribute('data-slot') || '';
		var picker    = btn.getAttribute('data-picker') || '';
		if (picker === 'org_users') {
			openOrgUserPicker(btn, fieldPath, slot);
			return;
		}
		openNewCardEditor(btn, fieldPath, slot);
	});

	// ---------- Org users picker (Team Members fields) ----------
	//
	// Cached at module scope: one fetch per page load is enough — the org
	// roster doesn't change mid-session, and every Team Members field on the
	// canvas reuses the same list.
	var orgUsersPromise = null;
	function getOrgUsers() {
		if (!orgUsersPromise) {
			orgUsersPromise = rest('org-users', { method: 'GET' }).then(function (res) {
				return (res && res.users) || [];
			}).catch(function () {
				// Reset so a transient failure doesn't permanently disable the picker.
				orgUsersPromise = null;
				return [];
			});
		}
		return orgUsersPromise;
	}

	function openOrgUserPicker(button, fieldPath, slot) {
		var container = button.previousElementSibling;
		if (!container || !container.classList.contains('qi-cards')) {
			container = button.parentNode.querySelector('.qi-cards');
		}
		if (!container) return;

		var existing = container.querySelector('.qi-org-picker, .qi-new-card-editor');
		if (existing) {
			var input = existing.querySelector('input, textarea');
			if (input) input.focus();
			return;
		}

		var picker = document.createElement('div');
		picker.className = 'qi-org-picker';
		picker.innerHTML =
			'<div class="qi-org-picker-search">' +
				'<input type="text" placeholder="Search teammates..." autocomplete="off" />' +
			'</div>' +
			'<div class="qi-org-picker-list" role="listbox"></div>' +
			'<div class="qi-org-picker-actions">' +
				'<button type="button" class="qi-org-picker-custom">+ Add custom name</button>' +
				'<button type="button" class="qi-org-picker-cancel">Cancel</button>' +
			'</div>';
		container.appendChild(picker);

		var search = picker.querySelector('input');
		var list   = picker.querySelector('.qi-org-picker-list');
		var cancel = picker.querySelector('.qi-org-picker-cancel');
		var custom = picker.querySelector('.qi-org-picker-custom');

		list.innerHTML = '<div class="qi-org-picker-empty">Loading teammates...</div>';

		// Skip users whose display name already lives in this card list — avoids
		// duplicate-member additions on the same field.
		function existingNames() {
			var names = {};
			container.querySelectorAll('.qi-card .qi-card-content').forEach(function (n) {
				names[(n.textContent || '').trim().toLowerCase()] = true;
			});
			return names;
		}

		getOrgUsers().then(function (users) {
			var taken = existingNames();
			// Hide the current user — they're implicitly a member as the owner
			// of any project they create (rendered as an auto-owner card on the
			// team_members list), so adding themselves is redundant. Same for
			// names already on this list (de-duplicate).
			var available = users.filter(function (u) {
				if (u.is_self) return false;
				return !taken[(u.display_name || '').trim().toLowerCase()];
			});

			function renderList(query) {
				var q = (query || '').trim().toLowerCase();
				var filtered = q
					? available.filter(function (u) {
						return (u.display_name || '').toLowerCase().indexOf(q) !== -1
							|| (u.email || '').toLowerCase().indexOf(q) !== -1;
					})
					: available;
				if (!filtered.length) {
					list.innerHTML = '<div class="qi-org-picker-empty">'
						+ (available.length === 0
							? 'All organisation teammates are already on this list.'
							: 'No matches.') + '</div>';
					return;
				}
				list.innerHTML = filtered.map(function (u) {
					return '<button type="button" class="qi-org-picker-item" data-user-id="' + u.id + '" data-name="' + escHtml(u.display_name) + '">'
						+ '<span class="qi-org-picker-name">' + escHtml(u.display_name) + (u.is_self ? ' <em>(you)</em>' : '') + '</span>'
						+ '<span class="qi-org-picker-email">' + escHtml(u.email) + '</span>'
						+ '</button>';
				}).join('');
			}

			renderList('');
			search.focus();

			search.addEventListener('input', function () { renderList(search.value); });

			list.addEventListener('click', function (ev) {
				var item = ev.target.closest('.qi-org-picker-item');
				if (!item) return;
				var name = item.getAttribute('data-name') || '';
				if (!name) return;
				picker.classList.add('qi-saving');
				saveTeamMemberCard(button, container, picker, fieldPath, slot, name);
			});
		});

		cancel.addEventListener('click', function () { picker.remove(); });
		custom.addEventListener('click', function () {
			picker.remove();
			openNewCardEditor(button, fieldPath, slot);
		});
	}

	function saveTeamMemberCard(button, container, picker, fieldPath, slot, content) {
		rest('projects/' + projectId(button) + '/cards', {
			method: 'POST',
			body: JSON.stringify({
				field_path: fieldPath,
				slot_key:   slot || undefined,
				content:    content
			})
		})
		.then(function (card) {
			var cardEl = renderCard(card);
			container.insertBefore(cardEl, picker);
			picker.remove();
			syncMatrixOnAdd(fieldPath, slot, card);
		})
		.catch(function (err) {
			alert('Could not add teammate: ' + err.message);
			picker.classList.remove('qi-saving');
		});
	}

	function openNewCardEditor(button, fieldPath, slot) {
		// The .qi-cards grid container is the previous sibling of the add button.
		// Insert the editor inside it so the new card lands as a real grid item
		// (matches the post-reload server-rendered layout).
		var container = button.previousElementSibling;
		if (!container || !container.classList.contains('qi-cards')) {
			container = button.parentNode.querySelector('.qi-cards');
		}
		if (!container) return;

		var existing = container.querySelector('.qi-new-card-editor');
		if (existing) {
			existing.querySelector('textarea').focus();
			return;
		}
		var editor = document.createElement('div');
		editor.className = 'qi-new-card-editor';
		editor.innerHTML =
			'<textarea placeholder="Type and click Save..."></textarea>' +
			'<div class="qi-actions">' +
				'<button type="button" class="qi-cancel">Cancel</button>' +
				'<button type="button" class="qi-save">Save</button>' +
			'</div>';
		container.appendChild(editor);
		var ta = editor.querySelector('textarea');
		ta.focus();

		editor.querySelector('.qi-cancel').addEventListener('click', function () {
			editor.remove();
		});
		editor.querySelector('.qi-save').addEventListener('click', function () {
			var content = ta.value.trim();
			if (!content) { ta.focus(); return; }
			editor.classList.add('qi-saving');
			rest('projects/' + projectId(button) + '/cards', {
				method: 'POST',
				body: JSON.stringify({
					field_path: fieldPath,
					slot_key:   slot || undefined,
					content:    content
				})
			})
			.then(function (card) {
				var cardEl = renderCard(card);
				container.insertBefore(cardEl, editor);
				editor.remove();
				syncMatrixOnAdd(fieldPath, slot, card);
			})
			.catch(function (err) {
				alert('Could not save card: ' + err.message);
				editor.classList.remove('qi-saving');
			});
		});
	}

	function renderCard(card) {
		var el = document.createElement('div');
		el.className = 'qi-card';
		el.setAttribute('data-card-id', card.id);
		var when = card.created_at ? new Date(card.created_at.replace(' ', 'T') + 'Z').toLocaleDateString() : '';
		el.innerHTML =
			'<div class="qi-card-content">' + escHtml(card.content) + '</div>' +
			'<div class="qi-card-meta">' +
				'<span class="qi-card-author">You</span>' +
				'<span class="qi-card-date">just now</span>' +
				'<button type="button" class="qi-card-edit"   data-card-id="' + card.id + '">Edit</button>' +
				'<button type="button" class="qi-card-delete" data-card-id="' + card.id + '">Delete</button>' +
			'</div>';
		return el;
	}

	// ---------- Edit card ----------

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.qi-card-edit');
		if (!btn) return;
		if (isCanvasReadonly(btn)) return;
		var card    = btn.closest('.qi-card');
		var content = card.querySelector('.qi-card-content');
		if (card.classList.contains('is-editing')) {
			saveCardEdit(card, content);
			return;
		}
		card.classList.add('is-editing');
		content.setAttribute('contenteditable', 'true');
		btn.textContent = 'Save';
		content.focus();
		// Place caret at end
		var range = document.createRange();
		range.selectNodeContents(content);
		range.collapse(false);
		var sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange(range);
	});

	function saveCardEdit(cardEl, contentEl) {
		var newText = contentEl.innerText.trim();
		if (!newText) { contentEl.focus(); return; }
		var id = cardEl.getAttribute('data-card-id');
		cardEl.classList.add('qi-saving');
		rest('cards/' + id, {
			method: 'PATCH',
			body: JSON.stringify({ content: newText })
		})
		.then(function () {
			cardEl.classList.remove('is-editing', 'qi-saving');
			contentEl.removeAttribute('contenteditable');
			cardEl.querySelector('.qi-card-edit').textContent = 'Edit';
			syncMatrixOnEdit(parseInt(id, 10), newText);
		})
		.catch(function (err) {
			alert('Could not save: ' + err.message);
			cardEl.classList.remove('qi-saving');
		});
	}

	// ---------- Delete card ----------

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.qi-card-delete');
		if (!btn) return;
		if (isCanvasReadonly(btn)) return;
		if (!confirm('Delete this card?')) return;
		var card = btn.closest('.qi-card');
		var id   = btn.getAttribute('data-card-id');
		card.classList.add('qi-saving');
		rest('cards/' + id, { method: 'DELETE' })
		.then(function () {
			card.remove();
			syncMatrixOnDelete(parseInt(id, 10));
		})
		.catch(function (err) {
			alert('Could not delete: ' + err.message);
			card.classList.remove('qi-saving');
		});
	});

	// ---------- Commit: measure rows (outcome / process) ----------

	document.addEventListener('click', function (e) {
		var addBtn = e.target.closest('.qi-measure-add');
		if (addBtn) { if (isCanvasReadonly(addBtn)) return; openMeasureAddRow(addBtn); return; }

		var editBtn = e.target.closest('.qi-measure-edit');
		if (editBtn) { if (isCanvasReadonly(editBtn)) return; toggleMeasureEdit(editBtn); return; }

		var delBtn = e.target.closest('.qi-measure-delete');
		if (delBtn) { if (isCanvasReadonly(delBtn)) return; deleteMeasure(delBtn); return; }
	});

	function openMeasureAddRow(btn) {
		var measuresField = btn.closest('.qi-measures');
		var grid          = measuresField.querySelector('.qi-measures-grid');
		var measureType   = btn.getAttribute('data-measure-type');
		var pid           = projectId(btn);
		if (grid.querySelector('.qi-measure-row.is-new')) return;

		var row = document.createElement('div');
		row.className = 'qi-measure-row is-editing is-new';
		row.innerHTML =
			'<div class="qi-measures-cell qi-measures-cell-desc"><textarea class="qi-measure-input-desc" placeholder="Description" rows="2"></textarea></div>' +
			'<div class="qi-measures-cell qi-measures-cell-current"><input type="text" class="qi-measure-input-current" placeholder="e.g. 0%" /></div>' +
			'<div class="qi-measures-cell qi-measures-cell-target"><input type="text" class="qi-measure-input-target" placeholder="e.g. 50%" /></div>' +
			'<div class="qi-measures-cell qi-measures-cell-actions">' +
				'<button type="button" class="qi-measure-save-new">Save</button>' +
				'<button type="button" class="qi-measure-cancel-new">Cancel</button>' +
			'</div>';
		grid.appendChild(row);
		row.querySelector('textarea').focus();

		row.querySelector('.qi-measure-cancel-new').addEventListener('click', function () {
			row.remove();
		});
		row.querySelector('.qi-measure-save-new').addEventListener('click', function () {
			var desc    = row.querySelector('.qi-measure-input-desc').value.trim();
			var current = row.querySelector('.qi-measure-input-current').value.trim();
			var target  = row.querySelector('.qi-measure-input-target').value.trim();
			if (!desc) { row.querySelector('textarea').focus(); return; }
			row.classList.add('qi-saving');
			rest('projects/' + pid + '/measures', {
				method: 'POST',
				body: JSON.stringify({
					measure_type:  measureType,
					description:   desc,
					current_value: current,
					target_value:  target
				})
			})
			.then(function (saved) {
				row.replaceWith(buildMeasureRow(saved));
			})
			.catch(function (err) {
				alert('Could not save measure: ' + err.message);
				row.classList.remove('qi-saving');
			});
		});
	}

	function buildMeasureRow(data) {
		var row = document.createElement('div');
		row.className = 'qi-measure-row';
		row.setAttribute('data-measure-id', data.id);
		row.innerHTML =
			'<div class="qi-measures-cell qi-measures-cell-desc" data-field="description">' + escHtml(data.description) + '</div>' +
			'<div class="qi-measures-cell qi-measures-cell-current" data-field="current_value">' + escHtml(data.current_value || '') + '</div>' +
			'<div class="qi-measures-cell qi-measures-cell-target" data-field="target_value">' + escHtml(data.target_value || '') + '</div>' +
			'<div class="qi-measures-cell qi-measures-cell-actions">' +
				'<button type="button" class="qi-measure-edit"   data-measure-id="' + data.id + '">Edit</button>' +
				'<button type="button" class="qi-measure-delete" data-measure-id="' + data.id + '">Delete</button>' +
			'</div>';
		return row;
	}

	function toggleMeasureEdit(btn) {
		var row = btn.closest('.qi-measure-row');
		if (row.classList.contains('is-editing')) {
			saveMeasureEdit(row, btn);
			return;
		}
		row.classList.add('is-editing');
		btn.textContent = 'Save';

		row.querySelectorAll('.qi-measures-cell[data-field]').forEach(function (cell) {
			var field = cell.getAttribute('data-field');
			var value = cell.textContent.trim();
			cell.setAttribute('data-original', value);
			if (field === 'description') {
				cell.innerHTML = '<textarea class="qi-measure-input-desc" rows="2">' + escHtml(value) + '</textarea>';
			} else {
				cell.innerHTML = '<input type="text" class="qi-measure-input-' + (field === 'current_value' ? 'current' : 'target') + '" value="' + escHtml(value) + '" />';
			}
		});
	}

	function saveMeasureEdit(row, btn) {
		var id = row.getAttribute('data-measure-id');
		var pid = projectId(row);
		var desc    = row.querySelector('.qi-measure-input-desc').value.trim();
		var current = row.querySelector('.qi-measure-input-current').value.trim();
		var target  = row.querySelector('.qi-measure-input-target').value.trim();
		if (!desc) { row.querySelector('textarea').focus(); return; }

		row.classList.add('qi-saving');
		rest('measures/' + id, {
			method: 'PATCH',
			body: JSON.stringify({
				description:   desc,
				current_value: current,
				target_value:  target
			})
		})
		.then(function (saved) {
			row.replaceWith(buildMeasureRow(saved));
		})
		.catch(function (err) {
			alert('Could not save: ' + err.message);
			row.classList.remove('qi-saving');
		});
	}

	function deleteMeasure(btn) {
		if (!confirm('Delete this measure?')) return;
		var row = btn.closest('.qi-measure-row');
		var id = btn.getAttribute('data-measure-id');
		row.classList.add('qi-saving');
		rest('measures/' + id, { method: 'DELETE' })
		.then(function () { row.remove(); })
		.catch(function (err) {
			alert('Could not delete: ' + err.message);
			row.classList.remove('qi-saving');
		});
	}

	// ---------- Commit: Mark Complete button ----------

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.qi-submit-btn[data-action="complete-project"]');
		if (!btn) return;
		if (btn.disabled) return;
		if (!confirm('Finalize this project? You will not be able to edit cards once it is marked complete.')) return;

		btn.classList.add('is-saving');
		var originalText = btn.textContent;
		btn.textContent = 'Finalizing...';

		rest('projects/' + projectId(btn) + '/complete', { method: 'POST' })
		.then(function () {
			btn.classList.remove('is-saving');
			btn.classList.add('is-completed');
			btn.disabled = true;
			btn.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Project Completed';
			var note = document.createElement('p');
			note.className = 'qi-submit-note';
			note.textContent = 'This project was finalized. Reload to see updated state across the canvas.';
			btn.parentNode.appendChild(note);
		})
		.catch(function (err) {
			btn.classList.remove('is-saving');
			btn.textContent = originalText;
			alert('Could not finalize: ' + err.message);
		});
	});

	// ---------- Improvement Canvas: zone picker (click overlay → choose cards to pin) ----------

	var currentZoneBtn = null;

	document.addEventListener('click', function (e) {
		var overlay = e.target.closest('.qi-canvas-overlay');
		if (!overlay) return;

		// Empty ("grey") zones have no pinned content yet — opening the pin
		// picker on them is a dead end. Send the user down to the matching
		// step instead so they can add content there.
		if (!overlay.classList.contains('has-pins')) {
			scrollToZoneStep(overlay);
			return;
		}

		if (isCanvasReadonly(overlay)) return;
		if (overlay.getAttribute('data-is-textarea') === '1') return;  // textarea zones are read-only
		openZonePicker(overlay);
	});

	// Jump from a canvas zone down to the step/field that feeds it.
	function scrollToZoneStep(overlay) {
		var source = overlay.getAttribute('data-source') || '';
		if (!source) return;
		var field = document.querySelector('.qi-field[data-field-path="' + source + '"]');
		if (!field) return;
		var step = field.closest('.qi-step') || field;
		step.scrollIntoView({ behavior: 'smooth', block: 'start' });
		field.classList.add('qi-zone-flash');
		setTimeout(function () { field.classList.remove('qi-zone-flash'); }, 1600);
	}

	document.addEventListener('click', function (e) {
		if (e.target.matches('[data-qi-zone-close]')) closeZonePicker();
		if (e.target.id === 'qi-zone-save') saveZonePicker();
		if (e.target.id === 'qi-zone-reset') resetZonePicker();
	});

	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') return;
		var modal = document.getElementById('qi-zone-modal');
		if (modal && !modal.hidden) closeZonePicker();
	});

	function openZonePicker(overlay) {
		currentZoneBtn = overlay;
		var modal = document.getElementById('qi-zone-modal');
		if (!modal) return;

		var source = overlay.getAttribute('data-source');
		var slot   = overlay.getAttribute('data-source-slot') || '';
		var number = overlay.getAttribute('data-zone-number') || '';

		var title = modal.querySelector('#qi-zone-title');
		var desc  = modal.querySelector('.qi-zone-modal-desc');
		var list  = modal.querySelector('.qi-zone-cards-list');
		title.textContent = 'Pin cards to zone ' + number;
		desc.textContent  = 'Pick which cards from "' + (slot || source.split('.').pop()) + '" should appear on the canvas. Cards you don\'t check stay off the canvas but remain in their step.';
		list.innerHTML = '<div class="qi-zone-empty">Loading...</div>';

		modal.hidden = false;

		// Fetch all cards for this zone
		var pid   = projectId(overlay);
		var query = 'projects/' + pid + '/cards?field_path=' + encodeURIComponent(source);
		if (slot) query += '&slot_key=' + encodeURIComponent(slot);

		rest(query, { method: 'GET' })
		.then(function (response) {
			renderZoneCards(response.data || [], overlay);
		})
		.catch(function (err) {
			list.innerHTML = '<div class="qi-zone-empty">Could not load cards: ' + escHtml(err.message) + '</div>';
		});
	}

	function renderZoneCards(cards, overlay) {
		var list = document.querySelector('#qi-zone-modal .qi-zone-cards-list');
		if (!cards.length) {
			list.innerHTML = '<div class="qi-zone-empty">No cards yet. Add one in the matching step below first.</div>';
			return;
		}
		var pinnedRaw = overlay.getAttribute('data-pinned-ids');
		var pinned    = [];
		try { pinned = JSON.parse(pinnedRaw) || []; } catch (e) {}
		var pinSet    = {};
		pinned.forEach(function (id) { pinSet[id] = true; });

		// If no pins ever set → default behavior is "show all" → check everything
		var hasNeverBeenPinned = pinned.length === 0;

		var html = '';
		cards.forEach(function (c) {
			var checked = hasNeverBeenPinned || pinSet[c.id];
			html += '<label class="qi-zone-card-option">'
				+ '<input type="checkbox" value="' + c.id + '"' + (checked ? ' checked' : '') + ' />'
				+ '<div>'
				+   '<div class="qi-zone-card-option-content">' + escHtml(c.content) + '</div>'
				+   '<div class="qi-zone-card-option-meta">Card #' + c.id + '</div>'
				+ '</div>'
				+ '</label>';
		});
		list.innerHTML = html;
	}

	function closeZonePicker() {
		var modal = document.getElementById('qi-zone-modal');
		if (modal) modal.hidden = true;
		currentZoneBtn = null;
	}

	function saveZonePicker() {
		if (!currentZoneBtn) return;
		var pinField = currentZoneBtn.getAttribute('data-pin-field');
		var pid      = projectId(currentZoneBtn);
		var checked  = Array.from(document.querySelectorAll('#qi-zone-modal .qi-zone-cards-list input[type="checkbox"]'))
			.filter(function (cb) { return cb.checked; })
			.map(function (cb) { return parseInt(cb.value, 10); });

		var saveBtn = document.getElementById('qi-zone-save');
		saveBtn.disabled = true;
		saveBtn.textContent = 'Saving...';

		rest('projects/' + pid + '/fields', {
			method: 'PUT',
			body: JSON.stringify({
				field_path: pinField,
				field_type: 'json',
				value:      JSON.stringify(checked)
			})
		})
		.then(function () {
			window.location.reload();
		})
		.catch(function (err) {
			alert('Could not save: ' + err.message);
			saveBtn.disabled = false;
			saveBtn.textContent = 'Save';
		});
	}

	function resetZonePicker() {
		if (!currentZoneBtn) return;
		var pinField = currentZoneBtn.getAttribute('data-pin-field');
		var pid      = projectId(currentZoneBtn);

		rest('projects/' + pid + '/fields', {
			method: 'PUT',
			body: JSON.stringify({
				field_path: pinField,
				field_type: 'json',
				value:      null
			})
		})
		.then(function () {
			window.location.reload();
		})
		.catch(function (err) {
			alert('Could not reset: ' + err.message);
		});
	}

	// ---------- Matrix Diagram: live sync from Improvement Canvas change ideas ----------

	function findMatrixForFieldPath(fieldPath) {
		if (!fieldPath) return null;
		var matrices = document.querySelectorAll('.qi-idea-matrix');
		for (var i = 0; i < matrices.length; i++) {
			if (matrices[i].getAttribute('data-ideas-source') === fieldPath) {
				return matrices[i];
			}
		}
		return null;
	}

	function findMatrixContainingCard(cardId) {
		var row = document.querySelector('.qi-idea-score-row[data-idea-card-id="' + cardId + '"]');
		return row ? row.closest('.qi-idea-matrix') : null;
	}

	function buildIdeaScoreRow(card, ideaNum, criteria) {
		var max = criteria.length * 5;
		var row = document.createElement('div');
		row.className = 'qi-idea-score-row';
		row.setAttribute('data-idea-card-id', card.id);
		row.setAttribute('data-idea-num', ideaNum);
		row.setAttribute('data-criteria-max', max);

		var critHtml = '';
		var total = 0;
		criteria.forEach(function (c) {
			total += 5;
			critHtml +=
				'<div class="qi-idea-criterion">' +
					'<label class="qi-idea-criterion-label">' + escHtml(c.label) + '</label>' +
					'<div class="qi-idea-slider-wrap">' +
						'<input type="range" class="qi-idea-slider" min="1" max="5" step="1" value="5"' +
							' data-criterion="' + escHtml(c.key) + '"' +
							' data-card-id="' + card.id + '" />' +
						'<span class="qi-idea-slider-value">5</span>' +
					'</div>' +
				'</div>';
		});

		row.innerHTML =
			'<div class="qi-idea-score-header">' +
				'<span class="qi-idea-num">Idea ' + ideaNum + ':</span>' +
				'<span class="qi-idea-text">' + escHtml(card.content) + '</span>' +
			'</div>' +
			'<div class="qi-idea-score-body">' +
				'<div class="qi-idea-criteria">' + critHtml + '</div>' +
				'<div class="qi-idea-cumulative">' +
					'<div class="qi-idea-cumulative-value" data-card-id="' + card.id + '">' + total + '/' + max + '</div>' +
					'<div class="qi-idea-cumulative-label">Personal Score</div>' +
				'</div>' +
			'</div>';
		return row;
	}

	function insertScoreRowInOrder(matrix, row, ideaNum) {
		var existing = matrix.querySelectorAll('.qi-idea-score-row');
		for (var i = 0; i < existing.length; i++) {
			var n = parseInt(existing[i].getAttribute('data-idea-num'), 10) || 0;
			if (n > ideaNum) {
				matrix.insertBefore(row, existing[i]);
				return;
			}
		}
		matrix.appendChild(row);
	}

	function ensureMatrixEmptyStateRemoved(matrix) {
		var empty = matrix.querySelector('.qi-idea-matrix-empty');
		if (empty) empty.remove();
	}

	function ensureMatrixEmptyStateRestored(matrix) {
		if (matrix.querySelector('.qi-idea-score-row')) return;
		if (matrix.querySelector('.qi-idea-matrix-empty')) return;
		var empty = document.createElement('div');
		empty.className = 'qi-idea-matrix-empty qi-stub';
		empty.innerHTML = '<p class="qi-stub-note">No change ideas yet. Add them on the <strong>Improvement Canvas</strong> tab first.</p>';
		matrix.appendChild(empty);
	}

	function syncMatrixOnAdd(fieldPath, slot, card) {
		var matrix = findMatrixForFieldPath(fieldPath);
		if (!matrix) return;
		var criteria = [];
		try { criteria = JSON.parse(matrix.getAttribute('data-criteria-json') || '[]'); } catch (e) {}
		if (!criteria.length) return;
		var ideaNum = parseInt(((slot || '').match(/\d+/) || [0])[0], 10) || 0;
		ensureMatrixEmptyStateRemoved(matrix);
		var row = buildIdeaScoreRow(card, ideaNum, criteria);
		insertScoreRowInOrder(matrix, row, ideaNum);
	}

	function syncMatrixOnDelete(cardId) {
		var matrix = findMatrixContainingCard(cardId);
		if (!matrix) return;
		var row = matrix.querySelector('.qi-idea-score-row[data-idea-card-id="' + cardId + '"]');
		if (row) row.remove();
		ensureMatrixEmptyStateRestored(matrix);
	}

	function syncMatrixOnEdit(cardId, newContent) {
		var row = document.querySelector('.qi-idea-score-row[data-idea-card-id="' + cardId + '"]');
		if (!row) return;
		var textEl = row.querySelector('.qi-idea-text');
		if (textEl) textEl.textContent = newContent;
	}

	// ---------- Matrix Diagram: idea score sliders ----------

	var sliderTimers = new WeakMap();

	// ---------- Matrix Diagram: lock / unlock scores ----------

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.qi-matrix-lock-btn');
		if (!btn) return;
		if (isCanvasReadonly(btn)) return;
		var action = btn.getAttribute('data-lock-action');
		var path   = btn.getAttribute('data-lock-path');
		if (!path) return;
		if (action === 'lock' && !confirm('Lock these scores? They will become read-only and appear on the Commit canvas. You can unlock again later.')) return;

		btn.disabled = true;
		var originalHtml = btn.innerHTML;
		btn.innerHTML = action === 'lock' ? 'Locking...' : 'Unlocking...';

		rest('projects/' + projectId(btn) + '/fields', {
			method: 'PUT',
			body: JSON.stringify({
				field_path: path,
				field_type: 'textarea',
				value:      action === 'lock' ? '1' : ''
			})
		})
		.then(function () {
			// Find the active tab so the reload stays on it (e.g. matrix_diagram).
			var activeTab = btn.closest('.qi-tab-panel');
			var key = activeTab ? activeTab.getAttribute('data-tab') : '';
			if (key) {
				location.hash = 'tab=' + encodeURIComponent(key);
			}
			window.location.reload();
		})
		.catch(function (err) {
			alert('Could not update lock: ' + err.message);
			btn.disabled = false;
			btn.innerHTML = originalHtml;
		});
	});

	document.addEventListener('input', function (e) {
		var slider = e.target.closest('.qi-idea-slider');
		if (!slider) return;
		var matrix = slider.closest('.qi-idea-matrix');
		if (matrix && matrix.classList.contains('is-locked')) return;
		var row = slider.closest('.qi-idea-score-row');
		// The visible number is the *user's own* score — update it instantly
		// from the slider. The team average is kept on the same element via
		// data-avg / data-count for code paths that need it (commit summary,
		// canvas overlays), but never shown here.
		var valueEl = slider.parentNode.querySelector('.qi-idea-slider-value');
		if (valueEl) valueEl.textContent = String(slider.value);
		recalcCumulative(row);
		clearTimeout(sliderTimers.get(slider));
		sliderTimers.set(slider, setTimeout(function () { saveIdeaScore(slider); }, 400));
	});

	function recalcCumulative(row) {
		if (!row) return;
		var sliders = row.querySelectorAll('.qi-idea-slider');
		var total = 0;
		sliders.forEach(function (s) { total += parseInt(s.value, 10) || 0; });
		var max = parseInt(row.getAttribute('data-criteria-max'), 10) || (sliders.length * 5);
		var cumEl = row.querySelector('.qi-idea-cumulative-value');
		if (cumEl) cumEl.textContent = total + '/' + max;
	}

	function saveIdeaScore(slider) {
		var row     = slider.closest('.qi-idea-score-row');
		var cardId  = parseInt(slider.getAttribute('data-card-id'), 10);
		var crit    = slider.getAttribute('data-criterion');
		var score   = parseInt(slider.value, 10);
		if (!cardId || !crit) return;

		row.classList.add('qi-saving');
		rest('projects/' + projectId(slider) + '/scores', {
			method: 'PUT',
			body: JSON.stringify({
				idea_card_id:  cardId,
				criterion_key: crit,
				score:         score
			})
		})
		.then(function (result) {
			row.classList.remove('qi-saving');
			// Keep the server-computed team avg/count on the element so other
			// views (overlay/commit summary) can read it. The visible text is
			// already showing the user's own pick from the input handler.
			if (result) {
				var valueEl = slider.parentNode.querySelector('.qi-idea-slider-value');
				if (valueEl) {
					if (typeof result.avg !== 'undefined') valueEl.setAttribute('data-avg', result.avg);
					if (typeof result.count !== 'undefined') valueEl.setAttribute('data-count', result.count);
				}
			}
		})
		.catch(function (err) {
			console.error('QI score save failed:', err);
			row.classList.remove('qi-saving');
		});
	}

	// ---------- Single textarea autosave ----------

	var textareaTimers = new WeakMap();

	document.addEventListener('input', function (e) {
		var ta = e.target.closest('.qi-textarea');
		if (!ta) return;
		clearTimeout(textareaTimers.get(ta));
		textareaTimers.set(ta, setTimeout(function () { saveTextarea(ta); }, 800));
	});

	document.addEventListener('blur', function (e) {
		var ta = e.target.closest('.qi-textarea');
		if (!ta) return;
		clearTimeout(textareaTimers.get(ta));
		saveTextarea(ta);
	}, true);

	function saveTextarea(ta) {
		if (isCanvasReadonly(ta)) return;
		var fieldPath = ta.getAttribute('data-field-path');
		ta.classList.add('qi-saving');
		rest('projects/' + projectId(ta) + '/fields', {
			method: 'PUT',
			body: JSON.stringify({
				field_path: fieldPath,
				field_type: 'textarea',
				value:      ta.value
			})
		})
		.then(function () { ta.classList.remove('qi-saving'); })
		.catch(function (err) {
			console.error('QI textarea save failed:', err);
			ta.classList.remove('qi-saving');
		});
	}

	// ---------- Dashboard: Search + Clear Filters ----------

	document.addEventListener('input', function (e) {
		if (e.target.id !== 'qi-search') return;
		applyDashboardFilter(e.target.value);
	});

	function applyDashboardFilter(query) {
		var q       = (query || '').trim().toLowerCase();
		var cards   = document.querySelectorAll('.qi-tile:not(.qi-tile-add)');
		var visible = 0;
		cards.forEach(function (card) {
			var title   = (card.getAttribute('data-qi-title') || '').toLowerCase();
			var matches = !q || title.indexOf(q) !== -1;
			card.style.display = matches ? '' : 'none';
			if (matches) visible++;
		});
		var noResults = document.querySelector('.qi-no-results');
		if (noResults) noResults.hidden = !( q && visible === 0 );
	}

	// ---------- Dashboard: Add project tile + modal ----------

	document.addEventListener('click', function (e) {
		if (e.target.closest('.qi-tile-add')) {
			openCreateModal();
			return;
		}
		if (e.target.matches('[data-qi-close]')) {
			closeCreateModal();
			return;
		}
		if (e.target.closest('#qi-create-submit')) {
			submitCreateModal();
		}
	});

	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') return;
		var modal = document.getElementById('qi-create-modal');
		if (modal && !modal.hidden) closeCreateModal();
	});

	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Enter') return;
		var input = document.getElementById('qi-create-title-input');
		if (!input || document.activeElement !== input) return;
		e.preventDefault();
		submitCreateModal();
	});

	function openCreateModal() {
		var modal = document.getElementById('qi-create-modal');
		if (!modal) return;
		modal.hidden = false;
		var input = document.getElementById('qi-create-title-input');
		if (input) { input.value = ''; setTimeout(function () { input.focus(); }, 50); }
		hideModalError();
	}

	function closeCreateModal() {
		var modal = document.getElementById('qi-create-modal');
		if (modal) modal.hidden = true;
	}

	function hideModalError() {
		var err = document.querySelector('#qi-create-modal .qi-modal-error');
		if (err) { err.hidden = true; err.textContent = ''; }
	}

	function showModalError(msg) {
		var err = document.querySelector('#qi-create-modal .qi-modal-error');
		if (err) { err.textContent = msg; err.hidden = false; }
	}

	function submitCreateModal() {
		var dashboard = document.querySelector('.qi-dashboard');
		var input     = document.getElementById('qi-create-title-input');
		var btn       = document.getElementById('qi-create-submit');
		if (!dashboard || !input || !btn) return;

		var title  = (input.value || '').trim();
		var tplVer = parseInt(dashboard.getAttribute('data-template-version-id'), 10);
		var canvas = dashboard.getAttribute('data-canvas-url');

		if (!title)  { showModalError('Please enter a title.');                 return; }
		if (!tplVer) { showModalError('No template available — contact admin.'); return; }

		btn.disabled    = true;
		btn.textContent = 'Creating...';
		hideModalError();

		rest('projects', {
			method: 'POST',
			body: JSON.stringify({
				title: title,
				template_version_id: tplVer
			})
		})
		.then(function (project) {
			if (canvas) {
				var sep = canvas.indexOf('?') === -1 ? '?' : '&';
				window.location.href = canvas + sep + 'qi=' + project.id;
			} else {
				window.location.reload();
			}
		})
		.catch(function (err) {
			showModalError(err.message || 'Could not create project.');
			btn.disabled    = false;
			btn.textContent = 'Create project';
		});
	}
})();
