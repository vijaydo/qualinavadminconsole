(function () {
  const MAX_FILES = 10;
  const ALLOWED_EXTENSIONS = ['pdf', 'docx'];
  const ALLOWED_MIME_TYPES = [
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
  ];

  const form = document.getElementById('dtu-form');
  if (!form) return;

  const dropzone = document.getElementById('dtu-dropzone');
  const fileInput = document.getElementById('dtu-file-input');
  const fileListArea = document.getElementById('dtu-file-list-area');
  const uploadBtn = document.getElementById('dtu-upload-btn');

  let filesToUpload = [];

  /* ===============================
     DRAG & DROP + INPUT HANDLING
     =============================== */
  dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.classList.add('active');
  });

  dropzone.addEventListener('dragleave', () => {
    dropzone.classList.remove('active');
  });

  dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('active');
    const droppedFiles = Array.from(e.dataTransfer.files);
    handleFiles(droppedFiles);
  });

  fileInput.addEventListener('change', (e) => {
    const selectedFiles = Array.from(e.target.files);
    handleFiles(selectedFiles);
  });

  function handleFiles(files) {
    const acceptedFiles = files.filter(file => {
      const ext = file.name.split('.').pop().toLowerCase();
      const hasAllowedExt = ALLOWED_EXTENSIONS.includes(ext);
      const hasAllowedMime = !file.type || ALLOWED_MIME_TYPES.includes(file.type);
      return hasAllowedExt && hasAllowedMime;
    });

    if (acceptedFiles.length !== files.length) {
      alert('Only PDF and DOCX files are accepted.');
    }

    // Proper dedupe: same name + same size → considered duplicate
    const newFiles = acceptedFiles.filter(file => {
      return !filesToUpload.some(f => f.name === file.name && f.size === file.size);
    });

    filesToUpload.push(...newFiles);

    // Enforce max
    if (filesToUpload.length > MAX_FILES) {
      alert(`You can upload a maximum of ${MAX_FILES} files.`);
      filesToUpload = filesToUpload.slice(0, MAX_FILES);
    }

    renderFileList();
  }

  function renderFileList() {
    fileListArea.innerHTML = '';

    filesToUpload.forEach((file, index) => {
      const fileRow = createFileRow(file, index);
      fileListArea.appendChild(fileRow);
    });

    uploadBtn.style.display = filesToUpload.length > 0 ? 'block' : 'none';
  }

  function fileFormatIcon(fileName) {
    const ext = fileName.split('.').pop().toLowerCase();
    const isPdf = ext === 'pdf';
    const label = isPdf ? 'PDF file' : 'DOCX file';
    const accent = isPdf ? '#EA4335' : '#4285F4';
    const text = isPdf ? 'PDF' : 'DOCX';

    return `
      <div class="file-icon" aria-label="${label}">
        <svg xmlns="http://www.w3.org/2000/svg" width="42" height="48" viewBox="0 0 42 48" role="img" aria-hidden="true">
          <path d="M8 3h18l10 10v29a3 3 0 0 1-3 3H8a3 3 0 0 1-3-3V6a3 3 0 0 1 3-3z" fill="#fff" stroke="#5f6368" stroke-width="2.6" stroke-linejoin="round"/>
          <path d="M26 3v10h10" fill="none" stroke="#5f6368" stroke-width="2.6" stroke-linejoin="round"/>
          <rect x="7" y="27" width="28" height="14" rx="3" fill="${accent}"/>
          <text x="21" y="37" text-anchor="middle" font-family="Inter, Arial, sans-serif" font-size="${isPdf ? '8.5' : '7'}" font-weight="800" fill="#fff">${text}</text>
        </svg>
      </div>
    `;
  }

  function createFileRow(file, index) {
    const row = document.createElement('div');
    row.className = 'dtu-file-row';
    row.dataset.index = index;

    row.innerHTML = `
      ${fileFormatIcon(file.name)}

      <div class="file-details">
        <span class="file-name">${file.name}</span>
        <div class="file-progress"><div class="file-progress-bar"></div></div>
        <span class="file-status">Ready</span>
      </div>

      <div class="file-result-icon success">
        <svg xmlns="http://www.w3.org/2000/svg" height="22" width="22" fill="#34A853"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
      </div>

      <div class="file-result-icon error">
        <svg xmlns="http://www.w3.org/2000/svg" height="22" width="22" fill="#EA4335"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/></svg>
      </div>
    `;

    return row;
  }

  /* ===============================
     UPLOAD LOGIC
     =============================== */
  uploadBtn.addEventListener('click', async () => {
    uploadBtn.disabled = true;

    for (let i = 0; i < filesToUpload.length; i++) {
      try {
        await uploadFile(filesToUpload[i], i);
      } catch (error) {
        console.error("Upload failed:", filesToUpload[i].name, error);
      }
    }

    // Reset after upload
    setTimeout(() => {
      fetchFiles(); // refresh table automatically
      filesToUpload = [];
      fileListArea.innerHTML = '';
      uploadBtn.style.display = 'none';
      uploadBtn.disabled = false;
      fileInput.value = '';
    }, 800);
  });

  function uploadFile(file, index) {
    return new Promise((resolve, reject) => {
      const row = fileListArea.querySelector(`[data-index="${index}"]`);
      const statusEl = row.querySelector('.file-status');
      const progressBar = row.querySelector('.file-progress-bar');

      const fd = new FormData();
      fd.append('action', 'dtu_upload');
      fd.append('nonce', DTU_AJAX.nonce);
      fd.append('hub', DTU_AJAX.hub);
      fd.append('user', DTU_AJAX.user);
      fd.append('file[]', file, file.name);

      const xhr = new XMLHttpRequest();
      xhr.open('POST', DTU_AJAX.url, true);

      xhr.upload.onprogress = evt => {
        if (evt.lengthComputable) {
          const percent = Math.round((evt.loaded / evt.total) * 100);
          progressBar.style.width = percent + '%';
          statusEl.textContent = `Uploading... ${percent}%`;
        }
      };

      xhr.onreadystatechange = () => {
        if (xhr.readyState !== 4) return;

        if (xhr.status === 200) {
          try {
            const res = JSON.parse(xhr.responseText);

            if (res.success && res.data.results && res.data.results[0].ok) {
              row.classList.add('success');
              statusEl.textContent = 'Success';
              resolve(res);
            } else {
              const errorMsg =
                res?.data?.results?.[0]?.error ||
                res?.data?.message ||
                'Upload failed';

              row.classList.add('error');
              statusEl.textContent = `Error: ${errorMsg}`;
              reject(errorMsg);
            }
          } catch (err) {
            row.classList.add('error');
            statusEl.textContent = 'Error: Invalid response';
            reject(err);
          }
        } else {
          row.classList.add('error');
          statusEl.textContent = `Error: ${xhr.statusText}`;
          reject(xhr.statusText);
        }
      };

      statusEl.textContent = 'Uploading... 0%';
      xhr.send(fd);
    });
  }

  /* ===============================
     FILE LIST TABLE (Fetched files)
     =============================== */
  const fileListTable = document.getElementById('dtu-file-list-table');
  const fileListBody = fileListTable?.querySelector('tbody');
  const loadingIndicator = document.getElementById('dtu-file-list-loading');
  const emptyIndicator = document.getElementById('dtu-file-list-empty');
  const noResultsIndicator = document.getElementById('dtu-file-list-no-results');
  const searchInput = document.getElementById('dtu-file-search-input');
  let uploadedFiles = [];

  function formatBytes(bytes, decimals = 2) {
    if (!bytes) return '0 Bytes';
    const k = 1024;
    const dm = Math.max(decimals, 0);
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
  }

  function formatDate(str) {
    if (!str) return '--';
    const date = new Date(str);
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const year = date.getFullYear();
    return `${month}/${day}/${year}`;
  }

  function formatSearchDate(str) {
    return formatDate(str);
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[char]));
  }

  async function fetchFiles() {
    if (fileListBody) {
      document.getElementById('dtu-overlay-loader').style.display = 'flex';
    }

    const fd = new FormData();
    fd.append('action', 'dtu_list_files');
    fd.append('nonce', DTU_AJAX.list_nonce);
    fd.append('hub', DTU_AJAX.hub);
    fd.append('user', DTU_AJAX.user);

    try {
      const response = await fetch(DTU_AJAX.url, { method: 'POST', body: fd });
      const res = await response.json();

      if (res.success) {
        uploadedFiles = Array.isArray(res.data.files) ? res.data.files : [];
        renderFiles(filterFiles(uploadedFiles), uploadedFiles.length > 0);
      } else {
        throw new Error(res.data?.message || 'Failed');
      }
    } catch (err) {
      console.error("File list load error:", err);
      fileListBody.innerHTML = `<tr><td colspan="7">Error loading files</td></tr>`;
    } finally {
      document.getElementById('dtu-overlay-loader').style.display = 'none';
      if (loadingIndicator) loadingIndicator.style.display = 'none';
    }
  }

  function renderFiles(files, hasAnyFiles = Boolean(files && files.length)) {
    if (!fileListBody) return;

    fileListBody.innerHTML = '';
    if (noResultsIndicator) noResultsIndicator.style.display = 'none';

    if (files && files.length) {
      if (emptyIndicator) emptyIndicator.style.display = 'none';

      files.forEach(file => {
        const row = document.createElement('tr');
        const viewUrl = file.view_url || file.url;
        const downloadUrl = file.download_url || file.url;
        const fileName = escapeHtml(file.name);
        const uploadedBy = escapeHtml(file.uploaded_by || 'Unknown');
        const canPreview = String(file.type || '').toUpperCase() === 'PDF';
        const viewAction = canPreview
          ? `<a href="${viewUrl}" target="_blank" rel="noopener" class="dtu-icon-button" aria-label="View ${fileName}" title="View">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </a>`
          : `<span class="dtu-icon-button dtu-icon-button--disabled" aria-label="Preview unavailable for ${fileName}" title="Preview unavailable for DOCX files">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </span>`;
        row.innerHTML = `
          <td class="file-icon-cell">${fileFormatIcon(file.name)}</td>
          <td class="file-name-cell" title="${fileName}">${fileName}</td>
          <td>${escapeHtml(file.type)}</td>
          <td class="file-uploader-cell" title="${uploadedBy}">${uploadedBy}</td>
          <td>${formatDate(file.created)}</td>
          <td>${formatBytes(file.size)}</td>
          <td class="file-link-cell">
            <div class="dtu-file-actions" aria-label="File actions">
              ${viewAction}
              <a href="${downloadUrl}" class="dtu-icon-button dtu-icon-button--download" aria-label="Download ${fileName}" title="Download" download>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                  <path d="M7 10l5 5 5-5"/>
                  <path d="M12 15V3"/>
                </svg>
              </a>
            </div>
          </td>
        `;
        fileListBody.appendChild(row);
      });
    } else {
      if (emptyIndicator) emptyIndicator.style.display = hasAnyFiles ? 'none' : 'block';
      if (noResultsIndicator) noResultsIndicator.style.display = hasAnyFiles ? 'block' : 'none';
    }
  }

  function filterFiles(files) {
    const query = (searchInput?.value || '').trim().toLowerCase();
    if (!query) return files;

    return files.filter(file => {
      const name = String(file.name || '').toLowerCase();
      const uploadedBy = String(file.uploaded_by || '').toLowerCase();
      const uploadedDate = formatDate(file.created).toLowerCase();
      const uploadedNumericDate = formatSearchDate(file.created).toLowerCase();
      return name.includes(query) || uploadedBy.includes(query) || uploadedDate.includes(query) || uploadedNumericDate.includes(query);
    });
  }

  const refreshBtn = document.getElementById('dtu-refresh-btn');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', fetchFiles);
  }

  if (searchInput) {
    searchInput.addEventListener('input', () => {
      renderFiles(filterFiles(uploadedFiles), uploadedFiles.length > 0);
    });
  }

  document.addEventListener('DOMContentLoaded', fetchFiles);
})();
