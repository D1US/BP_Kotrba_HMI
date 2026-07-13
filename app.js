//Log a auto scroll
const logContent = document.getElementById('log-content');
const logContainer = document.getElementById('log-container');

function isScrolledToBottom(){
    return logContainer.scrollHeight - logContainer.scrollTop - logContainer.clientHeight < 10;
}

function updateLog(){
    const wasAtBottom = isScrolledToBottom();

    fetch(get_log.php)
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

//Tlačítka
document.getElementById('clear-btn').addEventListener('click',() => {
    fetch('clear_log.php')
        .then(Response => Response.text())
        .then(()=>{
            logContent.textContent = ''
        })
        .catch(err => console.error('Clear failed:', err));
});

document.getElementById('start-btn').addEventListener('click', () => {
    fetch('send_command.php?button=start')
        .catch(err => console.error('Start command failed:', err));
});

document.getElementById('stop-btn').addEventListener('click', () => {
    fetch('send_command.php?button=stop')
        .catch(err => console.error('Stop command failed:', err));
});

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
    dropZone.style.borderColor = '#333'
});

dropZone.addEventListener('dragleave', () => {
    dropZone.style.borderColor = '#999'
})

dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.style.borderColor = '#999'

    if (e.dataTransfer.files.length > 0) {
        const file = e.dataTransfer.files[0];
        if(!isValidFile(file)){
            alert('Only .nc files are allowed');
            return;
        }
        selectedFile = file;
        dropZone.textContent = 'Selected: ${selectedFile.name}';
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
        dropZone.textContent = 'Selected: ${selectedFile.name}';
    }
});

document.getElementById('save-btn').addEventListener('click', () => {
    if (!selectedFile) {
        alert('No file selected');
        return;
    }

    const formData = new FormData();
    formData.append('file',selectedFile);

    fetch(save_file.php, {
        method: 'POST',
        body: formData
    })
        .then(Response => Response.text())
        .then(result => {
            alert(result === 'OK' ? 'File saved successfuly' : 'Save Failed' + result)
        })
        .catch(err => console.error('Save request failed:', err));
});
