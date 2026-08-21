// Log a auto scroll
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

// BTN
const startBtn = document.getElementById('start-btn');
if (startBtn) {
    function sendStart(state) {
        fetch(`send_command.php?button=start&state=${state}`)
            .catch(err => console.error('Start command failed:', err));
    }

    startBtn.addEventListener('mousedown', () => sendStart(1));
    startBtn.addEventListener('mouseup', () => sendStart(0));
    startBtn.addEventListener('mouseleave', () => sendStart(0));
    startBtn.addEventListener('touchstart', (e) => { e.preventDefault(); sendStart(1); });
    startBtn.addEventListener('touchend', (e) => { e.preventDefault(); sendStart(0); });
    startBtn.addEventListener('touchcancel', (e) => { e.preventDefault(); sendStart(0); });
}

// Stop
const stopBtn = document.getElementById('stop-btn');
if (stopBtn) {
    function sendStop(state) {
        fetch(`send_command.php?button=stop&state=${state}`)
            .catch(err => console.error('Stop command failed:', err));
    }

    stopBtn.addEventListener('mousedown', () => sendStop(1));
    stopBtn.addEventListener('mouseup', () => sendStop(0));
    stopBtn.addEventListener('mouseleave', () => sendStop(0));
    stopBtn.addEventListener('touchstart', (e) => { e.preventDefault(); sendStop(1); });
    stopBtn.addEventListener('touchend', (e) => { e.preventDefault(); sendStop(0); });
    stopBtn.addEventListener('touchcancel', (e) => { e.preventDefault(); sendStop(0); });
}

// Drag and drop
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

    fetch('save_file.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(result => {
            alert(result === 'OK' ? 'File saved successfully' : 'Save Failed: ' + result);
        })
        .catch(err => console.error('Save request failed:', err));
});


// Mode Controll
const modeBtn = document.getElementById('mode-btn');
let currentMode = '0'; // '0' = manual, '1' = auto

const autoControls = document.getElementById('auto-controls');
const manualControls = document.getElementById('manual-controls');

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
    // Display mode specific controls
    autoControls.classList.toggle('hidden', !isAuto);
    manualControls.classList.toggle('hidden', isAuto);

    // Keep buttons disabled too as a safety net
    document.getElementById('start-btn').disabled = !isAuto;
    document.getElementById('stop-btn').disabled = !isAuto;

    document.querySelectorAll('.jog-btn, .axis-toggle, .axis-action-btn').forEach(btn => {
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


// Manual jog controls
document.querySelectorAll('.jog-btn').forEach(btn => {
    const axis = btn.dataset.axis;
    const dir = btn.dataset.dir;

    function sendJog(state) {
        fetch(`jog.php?axis=${axis}&dir=${dir}&state=${state}`)
            .catch(err => console.error('Jog command failed:', err));
    }

    btn.addEventListener('mousedown', () => sendJog(1));
    btn.addEventListener('mouseup', () => sendJog(0));
    btn.addEventListener('mouseleave', () => sendJog(0));
    btn.addEventListener('touchstart', (e) => { e.preventDefault(); sendJog(1); });
    btn.addEventListener('touchend', (e) => { e.preventDefault(); sendJog(0); });
    btn.addEventListener('touchcancel', (e) => { e.preventDefault(); sendJog(0); });
});

// Axis toggle buttons
const axisToggleState = { x: '0', y: '0', z: '0' };

function renderAxisToggle(axis) {
    const btn = document.getElementById(`axis-toggle-${axis}`);
    if (!btn) return;
    btn.classList.toggle('active', axisToggleState[axis] === '1');
}

function loadInitialAxisToggles() {
    fetch('get_axis.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) return;
            ['x', 'y', 'z'].forEach(axis => {
                axisToggleState[axis] = data[axis];
                renderAxisToggle(axis);
            });
        })
        .catch(err => console.error('Failed to load axis toggles:', err));
}

document.querySelectorAll('.axis-toggle').forEach(btn => {
    const axis = btn.dataset.axis;

    btn.addEventListener('click', () => {
        const newValue = axisToggleState[axis] === '1' ? '0' : '1';

        fetch(`set_axis.php?axis=${axis}&value=${newValue}`)
            .then(response => response.text())
            .then(result => {
                if (result === 'OK') {
                    axisToggleState[axis] = newValue;
                    renderAxisToggle(axis);
                } else {
                    alert('Failed to set axis: ' + result);
                }
            })
            .catch(err => console.error('Axis toggle failed:', err));
    });
});

loadInitialAxisToggles();

// Per-axis Reset / Stop
document.querySelectorAll('.axis-action-btn').forEach(btn => {
    const axis = btn.dataset.axis;
    const action = btn.dataset.action;

    function sendAxisAction(state) {
        fetch(`axis_action.php?axis=${axis}&action=${action}&state=${state}`)
            .catch(err => console.error('Axis action failed:', err));
    }

    btn.addEventListener('mousedown', () => sendAxisAction(1));
    btn.addEventListener('mouseup', () => sendAxisAction(0));
    btn.addEventListener('mouseleave', () => sendAxisAction(0));
    btn.addEventListener('touchstart', (e) => { e.preventDefault(); sendAxisAction(1); });
    btn.addEventListener('touchend', (e) => { e.preventDefault(); sendAxisAction(0); });
    btn.addEventListener('touchcancel', (e) => { e.preventDefault(); sendAxisAction(0); });
});

// Position display polling 
function updatePosition() {
    fetch('get_position.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('pos-x').textContent = '—';
                document.getElementById('pos-y').textContent = '—';
                document.getElementById('pos-z').textContent = '—';
                return;
            }
            document.getElementById('pos-x').textContent = data.x;
            document.getElementById('pos-y').textContent = data.y;
            document.getElementById('pos-z').textContent = data.z;
        })
        .catch(err => console.error('Position fetch failed:', err));
}

updatePosition();
setInterval(updatePosition, 1000);

// Clear log
document.getElementById('clear-btn').addEventListener('click', () => {
    fetch('clear_log.php')
        .then(res => res.text())
        .then(result => {
            if (result === 'OK') {
                updateLog();
            } else {
                alert('Failed to clear log: ' + result);
            }
        })
        .catch(err => console.error('Clear log failed:', err));
});

// Settings drawer
const settingsBtn = document.getElementById('settings-btn');
const settingsPanel = document.getElementById('settings-panel');
const settingsBackdrop = document.getElementById('settings-backdrop');
const settingsCloseBtn = document.getElementById('settings-close-btn');

function openSettings() {
    settingsPanel.classList.add('open');
    settingsBackdrop.classList.add('open');
    loadSettings();
}

function closeSettings() {
    settingsPanel.classList.remove('open');
    settingsBackdrop.classList.remove('open');
}

settingsBtn.addEventListener('click', openSettings);
settingsCloseBtn.addEventListener('click', closeSettings);
settingsBackdrop.addEventListener('click', closeSettings);

// Machine settings (Material Thickness / Speed - LREAL values on the PLC).
function loadSettings() {
    fetch('get_settings.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) return;
            document.getElementById('setting-thickness').value = data.thickness;
            document.getElementById('setting-speed').value = data.speed;
        })
        .catch(err => console.error('Failed to load settings:', err));
}

function wireSettingInput(inputId, key) {
    const input = document.getElementById(inputId);

    input.addEventListener('change', () => {
        let value = parseFloat(input.value);

        if (isNaN(value) || value < 0) {
            value = 0;
        }
        input.value = value;

        fetch(`set_settings.php?key=${key}&value=${encodeURIComponent(value)}`)
            .then(r => r.text())
            .then(result => {
                if (result !== 'OK') {
                    console.error(`Failed to save ${key}:`, result);
                }
            })
            .catch(err => console.error(`Save ${key} failed:`, err));
    });
}

wireSettingInput('setting-thickness', 'thickness');
wireSettingInput('setting-speed', 'speed');

// Download log
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
