<!-- File Upload Component -->
<!-- Usage: include this file and set $entityType and $entityId variables -->

<div class="file-upload-section" style="margin-top: 24px;">
    <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 16px;">📎 Attachments</h3>

    <!-- Upload Form -->
    <div style="background: var(--bg-tertiary); padding: 20px; border-radius: var(--radius-md); margin-bottom: 16px;">
        <form id="fileUploadForm" enctype="multipart/form-data">
            <input type="hidden" name="entity_type" value="<?php echo $entityType; ?>">
            <input type="hidden" name="entity_id" value="<?php echo $entityId; ?>">

            <div style="display: flex; gap: 12px; align-items: end;">
                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                    <label for="fileInput">Select File (Max 10MB)</label>
                    <input type="file" id="fileInput" name="file" required
                           accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip,.rar">
                </div>
                <button type="submit" class="btn btn-primary">
                    📤 Upload
                </button>
            </div>
        </form>
        <p style="font-size: 12px; color: var(--text-secondary); margin-top: 8px;">
            Supported: Images, PDF, Word, Excel, Text, CSV, ZIP, RAR
        </p>
    </div>

    <!-- Files List -->
    <div id="filesList">
        <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
            Loading files...
        </p>
    </div>
</div>

<style>
.file-item {
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 12px 16px;
    margin-bottom: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s;
}

.file-item:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-sm);
}

.file-info {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.file-icon {
    font-size: 24px;
}

.file-details h4 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 4px;
}

.file-meta {
    font-size: 12px;
    color: var(--text-secondary);
}

.file-actions {
    display: flex;
    gap: 8px;
}
</style>

<script>
(function() {
    const entityType = '<?php echo $entityType; ?>';
    const entityId = <?php echo $entityId; ?>;

    // Load files
    function loadFiles() {
        fetch(`../api/file-upload.php?entity_type=${entityType}&entity_id=${entityId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayFiles(data.files);
                }
            })
            .catch(error => console.error('Error loading files:', error));
    }

    // Display files
    function displayFiles(files) {
        const container = document.getElementById('filesList');

        if (files.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 20px;">No files attached yet.</p>';
            return;
        }

        let html = '';
        files.forEach(file => {
            const icon = getFileIcon(file.file_type);
            const size = formatFileSize(file.file_size);
            const date = new Date(file.created_at).toLocaleDateString();

            html += `
                <div class="file-item" data-file-id="${file.id}">
                    <div class="file-info">
                        <div class="file-icon">${icon}</div>
                        <div class="file-details">
                            <h4>${escapeHtml(file.file_name)}</h4>
                            <div class="file-meta">
                                ${size} • Uploaded by ${escapeHtml(file.uploaded_by_name)} • ${date}
                            </div>
                        </div>
                    </div>
                    <div class="file-actions">
                        <a href="${file.file_path}" download class="btn btn-secondary btn-sm">
                            ⬇️ Download
                        </a>
                        <button onclick="deleteFile(${file.id})" class="btn btn-danger btn-sm">
                            🗑️ Delete
                        </button>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    // Get file icon based on type
    function getFileIcon(mimeType) {
        if (mimeType.startsWith('image/')) return '🖼️';
        if (mimeType === 'application/pdf') return '📄';
        if (mimeType.includes('word')) return '📝';
        if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return '📊';
        if (mimeType.includes('zip') || mimeType.includes('rar')) return '📦';
        return '📎';
    }

    // Format file size
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Upload file
    document.getElementById('fileUploadForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');

        submitBtn.disabled = true;
        submitBtn.textContent = 'Uploading...';

        fetch('../api/file-upload.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('File uploaded successfully!');
                this.reset();
                loadFiles();
            } else {
                alert('Upload failed: ' + data.message);
            }
        })
        .catch(error => {
            alert('Upload error: ' + error);
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = '📤 Upload';
        });
    });

    // Delete file (global function)
    window.deleteFile = function(fileId) {
        if (!confirm('Are you sure you want to delete this file?')) return;

        fetch('../api/file-upload.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ file_id: fileId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('File deleted successfully');
                loadFiles();
            } else {
                alert('Delete failed: ' + data.message);
            }
        })
        .catch(error => {
            alert('Delete error: ' + error);
        });
    };

    // Load files on page load
    loadFiles();
})();
</script>
