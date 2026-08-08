// ---------- Elements ----------
const logView   = document.getElementById('logView');
const jumpBtn    = document.getElementById('jumpBtn');
const clearBtn   = document.getElementById('clearBtn');
const dropZone   = document.getElementById('dropZone');
const fileInput  = document.getElementById('fileInput');
const saveBtn    = document.getElementById('saveBtn');
const saveStatus = document.getElementById('saveStatus');
const selectedFileName = document.getElementById('selectedFileName');

let selectedFile = null;

// ---------- Live log polling ----------

// How close to the bottom (in px) counts as "still at the bottom".
// A small tolerance so tiny rounding differences don't break auto-scroll.
const BOTTOM_TOLERANCE = 15;

function isScrolledToBottom() {
  return logView.scrollHeight - logView.scrollTop - logView.clientHeight < BOTTOM_TOLERANCE;
}

async function pollLog() {
  try {
    const res = await fetch('get_log.php', { cache: 'no-store' });
    const text = await res.text();

    // Decide BEFORE updating content whether the user was at the bottom.
    const wasAtBottom = isScrolledToBottom();

    logView.textContent = text.length ? text : '(log is empty)';

    if (wasAtBottom) {
      // User was following the log live - keep following it.
      logView.scrollTop = logView.scrollHeight;
      jumpBtn.style.display = 'none';
    } else {
      // User has scrolled up to read history - leave them alone,
      // just show a button so they can jump back down when ready.
      jumpBtn.style.display = 'inline-block';
    }
  } catch (err) {
    console.error('Failed to fetch log:', err);
  }
}

jumpBtn.addEventListener('click', () => {
  logView.scrollTop = logView.scrollHeight;
  jumpBtn.style.display = 'none';
});

clearBtn.addEventListener('click', async () => {
  clearBtn.disabled = true;
  try {
    await fetch('clear_log.php', { method: 'POST' });
    // Reflect the clear immediately instead of waiting for next poll.
    logView.textContent = '(log is empty)';
    jumpBtn.style.display = 'none';
  } catch (err) {
    console.error('Failed to clear log:', err);
  } finally {
    clearBtn.disabled = false;
  }
});

// Poll every 1 second.
setInterval(pollLog, 1000);
pollLog(); // initial load

// ---------- Drag & drop / file picker save ----------

function handleFileSelected(file) {
  selectedFile = file;
  selectedFileName.textContent = 'Selected: ' + file.name;
  saveBtn.disabled = false;
  saveStatus.textContent = '';
}

dropZone.addEventListener('click', () => fileInput.click());

fileInput.addEventListener('change', () => {
  if (fileInput.files.length) {
    handleFileSelected(fileInput.files[0]);
  }
});

dropZone.addEventListener('dragover', (e) => {
  e.preventDefault();
  dropZone.classList.add('dragover');
});

dropZone.addEventListener('dragleave', () => {
  dropZone.classList.remove('dragover');
});

dropZone.addEventListener('drop', (e) => {
  e.preventDefault();
  dropZone.classList.remove('dragover');
  if (e.dataTransfer.files.length) {
    handleFileSelected(e.dataTransfer.files[0]);
  }
});

saveBtn.addEventListener('click', async () => {
  if (!selectedFile) return;

  saveBtn.disabled = true;
  saveStatus.textContent = 'Saving...';

  const formData = new FormData();
  // Field name MUST match what save_log.php expects: "uploadedFile".
  formData.append('uploadedFile', selectedFile);

  try {
    const res = await fetch('save_log.php', { method: 'POST', body: formData });
    const text = await res.text();

    if (res.ok && text.trim() === 'OK') {
      saveStatus.textContent = 'Saved.';
    } else {
      saveStatus.textContent = 'Save failed: ' + text;
    }
  } catch (err) {
    saveStatus.textContent = 'Save failed: ' + err;
  } finally {
    saveBtn.disabled = false;
  }
});
