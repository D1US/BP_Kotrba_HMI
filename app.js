//Log a auto scroll
const logContent = document.getElementById('log-content');
const logContainer = document.getElementById('log-container');

function isScrolledToBottom(){
    return logContainer.scrollHeight - logContainer.scrollTop - logContainer.clientHeight < 10;
}

function updateLog(){
    const wasAtBottom = isScrolledToBottom();

    fetch('get_log.php')
        .then(Response => Response.text())
        .then(text => {
            logContent.textContent = text;

            if(wasAtBottom) {
                logContainer.scrollTop = logContainer.scrollHeight;
            }
        })
        .catch(err => console.error('Log fetch failed:', err));
}

updateLog();
setInterval(updateLog , 1000);

//Tlacitka
document.getElementById('clear-btn').addEventListener('click',() => {
    fetch('clear_log.php')
        .then(Response => Response.text())
        .then(()=>{
            logContent.textContent = '';
        })
        .catch(err => console.error('Clear failed:', err));
});

// ===== Momentary/hold buttons: Start, Stop, and Jog all share this one
// wiring function - press sends state=1, release sends state=0. This is
// the single code path for all press/release controls in the whole page.
//
// The two requests are chained through one promise per button so that
// "release" can never reach the server before "press" has finished - on
// a fast click, two independent fetches can race and finish out of
// order, leaving the value stuck at 1. Chaining forces strict ordering. =====
function wireHoldButton(el, onPress, onRelease) {
    let pending = Promise.resolve();

    function queue(action) {
        pending = pending.then(action).catch(() => {});
    }

    el.addEventListener('mousedown', () => queue(onPress));
    el.addEventListener('mouseup', () => queue(onRelease));
    el.addEventListener('mouseleave', () => queue(onRelease));
    el.addEventListener('touchstart', (e) => { e.preventDefault(); queue(onPress); });
    el.addEventListener('touchend', (e) => { e.preventDefault(); queue(onRelease); });
}

// One generic function sends every momentary control (Start, Stop, Jog)
// through the single control.php endpoint, using a "key" that matches
// bridge.py's WRITE_SYMBOLS exactly (e.g. "start", "jog_x_plus").
function sendControl(key, state) {
    return fetch(`control.php?key=${key}&state=${state}`)
        .catch(err => console.error(`${key} command failed:`, err));
}

function wireCommandButton(id, key) {
    const btn = document.getElementById(id);
    wireHoldButton(btn, () => sendControl(key, 1), () => sendControl(key, 0));
}

wireCommandButton('start-btn', 'start');
wireCommandButton('stop-btn', 'stop');

//Drag and drop
const dropZone = document.getElementById('drop-zone');
const fileInput = document.getElementById('file-input');
let selectedFile = null;

function isValidFile(file) {
    return file.name.toLowerCase().endsWith('.nc');
}

dropZone.addEventListener('click', () => {
    fileInput.click();
});

dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('drag-active');
});

dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('drag-active');
});

dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('drag-active');

    if (e.dataTransfer.files.length > 0) {
        const file = e.dataTransfer.files[0];
        if(!isValidFile(file)){
            alert('Only .nc files are allowed');
            return;
        }
        selectedFile = file;
        dropZone.textContent = `Selected: ${selectedFile.name}`;
    }
});

fileInput.addEventListener('change',() => {
    if(fileInput.files.length > 0) {
        const file = fileInput.files[0];
        if (!isValidFile(file)) {
            alert('Only .nc files are allowed');
            fileInput.value = '';
            return;
        }
        selectedFile = file;
        dropZone.textContent = `Selected: ${selectedFile.name}`;
    }
});

document.getElementById('save-btn').addEventListener('click', () => {
    if (!selectedFile) {
        alert('No file selected');
        return;
    }

    const formData = new FormData();
    formData.append('file',selectedFile);

    fetch('save_file.php', {
        method: 'POST',
        body: formData
    })
        .then(Response => Response.text())
        .then(result => {
            alert(result === 'OK' ? 'File saved successfuly' : 'Save Failed' + result);
        })
        .catch(err => console.error('Save request failed:', err));
});


//Mode Controll
const modeBtn = document.getElementById('mode-btn');
let currentMode = '0'; // '0' = manual, '1' = auto

function renderModeButton() {
    const isAuto = currentMode === '1';

    if (isAuto) {
        modeBtn.textContent = 'Mode: AUTO';
        modeBtn.classList.add('mode-auto');
        modeBtn.classList.remove('mode-manual');
    } else {
        modeBtn.textContent = 'Mode: MANUAL';
        modeBtn.classList.add('mode-manual');
        modeBtn.classList.remove('mode-auto');
    }

    document.getElementById('start-btn').disabled = !isAuto;
    document.getElementById('stop-btn').disabled = !isAuto;

    document.querySelectorAll('.jog-btn').forEach(btn => {
        btn.disabled = isAuto;
    });
}

function loadInitialMode() {
    fetch('get_mode.php')
        .then(response => response.text())
        .then(value => {
            currentMode = value.trim();
            renderModeButton();
        })
        .catch(err => console.error('Failed to load mode:', err));
}

modeBtn.addEventListener('click', () => {
    const newMode = currentMode === '1' ? '0' : '1';

    fetch(`set_mode.php?value=${newMode}`)
        .then(response => response.text())
        .then(result => {
            if (result === 'OK') {
                currentMode = newMode;
                renderModeButton();
            } else {
                alert('Failed to change mode: ' + result);
            }
        })
        .catch(err => console.error('Set mode failed:', err));
});

loadInitialMode();


// ===== Manual jog controls (hold to move) — uses the same wireHoldButton
// helper as Start/Stop above, so both behave identically by construction =====
document.querySelectorAll('.jog-btn').forEach(btn => {
    const key = `jog_${btn.dataset.axis}_${btn.dataset.dir}`;
    wireHoldButton(btn, () => sendControl(key, 1), () => sendControl(key, 0));
});

// ===== Position display polling =====
function updatePosition() {
    fetch('get_position.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('pos-x').textContent = data.x;
            document.getElementById('pos-y').textContent = data.y;
            document.getElementById('pos-z').textContent = data.z;
        })
        .catch(err => console.error('Position fetch failed:', err));
}

updatePosition();
setInterval(updatePosition, 1000);

//Download log
document.getElementById('download-btn').addEventListener('click', () => {
    const blob = new Blob([logContent.textContent], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);

    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const timestamp = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}_${pad(now.getHours())}${pad(now.getMinutes())}${pad(now.getSeconds())}`;

    const a = document.createElement('a');
    a.href = url;
    a.download = `machine_log_${timestamp}.log`;
    a.click();

    URL.revokeObjectURL(url);
});
