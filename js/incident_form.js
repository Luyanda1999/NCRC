// js/incident_form.js - AMENDED VERSION

document.addEventListener('DOMContentLoaded', function() {
    // Get form elements
    const form = document.getElementById('incidentForm');
    const submitBtn = document.getElementById('submitBtn');
    const clearBtn = document.getElementById('clearForm');
    const saveDraftBtn = document.getElementById('saveDraft');
    const successAlert = document.getElementById('successAlert');
    const errorAlert = document.getElementById('errorAlert');
    const progressFill = document.getElementById('formProgress');
    const progressText = document.getElementById('progressText');
    const fileUpload = document.getElementById('fileUpload');
    const fileList = document.getElementById('fileList');
    const statusSelect = document.getElementById('status');
    const statusDot = document.getElementById('statusDot');
    const statusText = document.getElementById('statusText');
    
    // API endpoint
    const API_URL = '../api/incidents.php';
    
    // Initialize variables
    let uploadedFiles = [];
    let autoSaveInterval;
    
    // Status indicator colors
    const statusColors = {
        'open': '#ff4757',
        'investigating': '#ffa502',
        'contained': '#2ed573',
        'resolved': '#1e90ff',
        'closed': '#3742fa'
    };
    
    // Status texts
    const statusMessages = {
        'open': 'Incident is currently open',
        'investigating': 'Incident is under investigation',
        'contained': 'Incident has been contained',
        'resolved': 'Incident has been resolved',
        'closed': 'Incident has been closed'
    };
    
    // Initialize status indicator
    updateStatusIndicator();
    
    // Event Listeners
    if (form) {
        form.addEventListener('submit', handleFormSubmit);
        form.addEventListener('input', updateProgressBar);
        form.addEventListener('change', updateProgressBar);
    }
    
    if (clearBtn) {
        clearBtn.addEventListener('click', clearForm);
    }
    
    if (saveDraftBtn) {
        saveDraftBtn.addEventListener('click', saveDraft);
    }
    
    if (fileUpload) {
        fileUpload.addEventListener('change', handleFileUpload);
    }
    
    if (statusSelect) {
        statusSelect.addEventListener('change', updateStatusIndicator);
    }
    
    // Initialize progress bar
    updateProgressBar();
    
    // Functions
    function handleFormSubmit(event) {
        event.preventDefault();
        console.log('Form submit triggered');
        
        // Validate form
        if (!validateForm()) {
            showError('Please fill in all required fields correctly.');
            return;
        }
        
        // Disable submit button to prevent multiple submissions
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin btn-icon"></i> Submitting...';
        
        // Prepare form data - FIXED: Use correct field names
        const formData = new FormData();
        
        // Add all form fields with correct names
        const formElements = {
            'title': document.getElementById('incidentTitle'),
            'type': document.getElementById('incidentType'),
            'datetime': document.getElementById('datetime'),
            'location': document.getElementById('location'),
            'reported_by': document.getElementById('reportedBy'),
            'description': document.getElementById('description'),
            'priority': document.getElementById('priority'),
            'impact_level': document.getElementById('impactLevel'),
            'evidence': document.getElementById('evidence'),
            'status': document.getElementById('status'),
            'actions_taken': document.getElementById('actions')
        };
        
        // Add form values to FormData
        for (const [key, element] of Object.entries(formElements)) {
            if (element) {
                formData.append(key, element.value);
                console.log(`Added ${key}: ${element.value}`);
            }
        }
        
        // Append uploaded files
        uploadedFiles.forEach((file, index) => {
            formData.append(`file_${index}`, file);
            console.log(`Added file_${index}: ${file.name}`);
        });
        
        // Add metadata
        formData.append('action', 'submit_report');
        formData.append('timestamp', new Date().toISOString());
        formData.append('user_id', 'jdoe');
        
        // Debug: Log what's being sent
        console.log('FormData contents:');
        for (let [key, value] of formData.entries()) {
            console.log(`${key}: ${value instanceof File ? `File: ${value.name} (${value.type})` : value}`);
        }
        
        // Submit to API
        fetch(API_URL, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                showSuccess(data.message || 'Incident report submitted successfully!');
                clearForm();
                updateNotificationCount();
                
                // Clear draft from localStorage on successful submission
                localStorage.removeItem('incident_draft');
                
                // Redirect to incident table after 2 seconds
                setTimeout(() => {
                    window.location.href = 'Incident_table.html';
                }, 2000);
            } else {
                // Show specific error message from server
                let errorMsg = data.message || 'Error submitting report';
                
                // Check for missing fields error
                if (data.missing_fields) {
                    errorMsg += `\nMissing fields: ${data.missing_fields.join(', ')}`;
                    
                    // Highlight missing fields
                    data.missing_fields.forEach(fieldName => {
                        const fieldMap = {
                            'title': 'incidentTitle',
                            'type': 'incidentType',
                            'datetime': 'datetime',
                            'location': 'location',
                            'reported_by': 'reportedBy',
                            'description': 'description',
                            'priority': 'priority',
                            'impact_level': 'impactLevel',
                            'status': 'status'
                        };
                        
                        const fieldId = fieldMap[fieldName];
                        if (fieldId) {
                            const field = document.getElementById(fieldId);
                            if (field) {
                                field.classList.add('error');
                                field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                        }
                    });
                }
                
                throw new Error(errorMsg);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            
            // Handle specific error types
            let errorMessage = error.message || 'Error submitting report. Please try again.';
            
            if (error.message.includes('NetworkError') || error.message.includes('Failed to fetch')) {
                errorMessage = 'Network error. Please check your connection and try again.';
            } else if (error.message.includes('Database')) {
                errorMessage = 'Database connection issue. Please contact administrator.';
            }
            
            showError(errorMessage);
            
            // Log additional error info
            if (error.response) {
                console.error('Response error:', error.response);
            }
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane btn-icon"></i> Submit Report';
        });
    }
    
    function validateForm() {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        // Clear previous validation styles
        requiredFields.forEach(field => {
            field.classList.remove('error');
        });
        
        // Check each required field
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('error');
                isValid = false;
                console.log(`Missing required field: ${field.id || field.name}`);
            }
            
            // Special validation for email fields
            if (field.type === 'email' && field.value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(field.value)) {
                    field.classList.add('error');
                    isValid = false;
                }
            }
            
            // Validate datetime-local field
            if (field.type === 'datetime-local' && field.value) {
                const selectedDate = new Date(field.value);
                const currentDate = new Date();
                const maxFutureDate = new Date();
                maxFutureDate.setDate(currentDate.getDate() + 30); // Allow up to 30 days in future
                
                if (selectedDate > maxFutureDate) {
                    field.classList.add('error');
                    isValid = false;
                    showError('Incident date cannot be more than 30 days in the future.');
                }
                
                if (selectedDate < new Date('2000-01-01')) {
                    field.classList.add('error');
                    isValid = false;
                    showError('Please enter a valid date.');
                }
            }
        });
        
        // Additional validation for file size
        const totalFileSize = uploadedFiles.reduce((total, file) => total + file.size, 0);
        const maxTotalSize = 50 * 1024 * 1024; // 50MB total
        
        if (totalFileSize > maxTotalSize) {
            showError('Total file size exceeds 50MB limit. Please reduce file sizes.');
            isValid = false;
        }
        
        return isValid;
    }
    
    function handleFileUpload(event) {
        const files = Array.from(event.target.files);
        const maxSize = 10 * 1024 * 1024; // 10MB in bytes
        const allowedTypes = [
            'image/jpeg', 'image/jpg', 'image/png', 
            'application/pdf', 
            'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain'
        ];
        
        let hasErrors = false;
        
        files.forEach(file => {
            // Check file size
            if (file.size > maxSize) {
                showError(`File "${file.name}" exceeds maximum size of 10MB`);
                hasErrors = true;
                return;
            }
            
            // Check file type
            if (!allowedTypes.includes(file.type)) {
                showError(`File type not allowed for "${file.name}". Allowed: Images, PDF, DOC, DOCX, TXT`);
                hasErrors = true;
                return;
            }
            
            // Check for duplicate files
            if (uploadedFiles.some(f => f.name === file.name && f.size === file.size)) {
                showError(`File "${file.name}" is already uploaded`);
                hasErrors = true;
                return;
            }
            
            // Add to uploaded files array if not already present
            uploadedFiles.push(file);
            displayFileItem(file);
            
            console.log(`File added: ${file.name} (${formatFileSize(file.size)})`);
        });
        
        // Update progress bar
        updateProgressBar();
        
        // Clear file input to allow selecting same file again
        event.target.value = '';
        
        // Show success message if no errors
        if (!hasErrors && files.length > 0) {
            showSuccess(`${files.length} file(s) uploaded successfully`);
        }
    }
    
    function displayFileItem(file) {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.innerHTML = `
            <div class="file-info">
                <i class="fas ${getFileIcon(file.type)} file-icon"></i>
                <div class="file-details">
                    <span class="file-name">${file.name}</span>
                    <span class="file-size">${formatFileSize(file.size)}</span>
                </div>
            </div>
            <button type="button" class="remove-file" data-name="${file.name}" title="Remove file">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        fileList.appendChild(fileItem);
        
        // Add remove event listener
        fileItem.querySelector('.remove-file').addEventListener('click', function() {
            const fileName = this.getAttribute('data-name');
            removeFile(fileName);
            fileItem.remove();
            showSuccess(`File "${fileName}" removed`);
        });
    }
    
    function getFileIcon(fileType) {
        if (fileType.startsWith('image/')) return 'fa-file-image';
        if (fileType === 'application/pdf') return 'fa-file-pdf';
        if (fileType.includes('word')) return 'fa-file-word';
        if (fileType === 'text/plain') return 'fa-file-alt';
        return 'fa-file';
    }
    
    function removeFile(fileName) {
        uploadedFiles = uploadedFiles.filter(file => file.name !== fileName);
        console.log(`File removed: ${fileName}`);
    }
    
    function saveDraft() {
        const draftData = {
            timestamp: new Date().toISOString(),
            fields: {},
            files: []
        };
        
        // Save form field values
        const formElements = [
            'incidentTitle', 'incidentType', 'datetime', 'location',
            'reportedBy', 'description', 'priority', 'impactLevel',
            'evidence', 'status', 'actions'
        ];
        
        formElements.forEach(fieldId => {
            const element = document.getElementById(fieldId);
            if (element) {
                draftData.fields[fieldId] = element.value;
            }
        });
        
        // Save files metadata (not the actual files)
        draftData.files = uploadedFiles.map(file => ({
            name: file.name,
            size: file.size,
            type: file.type,
            lastModified: file.lastModified
        }));
        
        // Save to localStorage
        try {
            localStorage.setItem('incident_draft', JSON.stringify(draftData));
            showAutoSave();
            console.log('Draft saved to localStorage');
        } catch (error) {
            console.error('Failed to save draft:', error);
            showError('Failed to save draft. Local storage may be full.');
        }
    }
    
    function loadDraft() {
        try {
            const draftData = localStorage.getItem('incident_draft');
            
            if (draftData) {
                const data = JSON.parse(draftData);
                console.log('Loading draft data:', data);
                
                // Populate form fields
                if (data.fields) {
                    Object.keys(data.fields).forEach(fieldId => {
                        const field = document.getElementById(fieldId);
                        if (field) {
                            field.value = data.fields[fieldId];
                        }
                    });
                }
                
                // Note: Cannot reload actual files, but show info about saved files
                if (data.files && data.files.length > 0) {
                    console.log(`Draft contains ${data.files.length} file(s)`);
                    showInfo(`Draft loaded with ${data.files.length} saved file(s). Files will need to be re-selected.`);
                }
                
                // Update UI elements
                updateProgressBar();
                updateStatusIndicator();
                
                console.log('Draft loaded successfully');
            }
        } catch (error) {
            console.error('Failed to load draft:', error);
            // Clear corrupted draft
            localStorage.removeItem('incident_draft');
        }
    }
    
    function clearForm() {
        if (confirm('Are you sure you want to clear the form? All unsaved data will be lost.')) {
            // Reset form
            form.reset();
            
            // Clear uploaded files
            uploadedFiles = [];
            fileList.innerHTML = '';
            
            // Reset specific fields to default values
            document.getElementById('status').selectedIndex = 0;
            document.getElementById('priority').selectedIndex = 0;
            document.getElementById('impactLevel').selectedIndex = 0;
            
            // Update UI
            updateProgressBar();
            updateStatusIndicator();
            
            // Clear validation styles
            form.querySelectorAll('.error').forEach(el => {
                el.classList.remove('error');
            });
            
            // Clear draft from localStorage
            localStorage.removeItem('incident_draft');
            
            console.log('Form cleared and draft removed');
            showSuccess('Form cleared successfully');
        }
    }
    
    function updateProgressBar() {
        if (!form) return;
        
        const requiredFields = form.querySelectorAll('[required]');
        const filledFields = Array.from(requiredFields).filter(field => {
            return field.value.trim() !== '';
        });
        
        // Count files as progress
        const fileProgress = uploadedFiles.length > 0 ? 10 : 0;
        
        const progress = Math.min(100, Math.round((filledFields.length / requiredFields.length) * 90) + fileProgress);
        
        if (progressFill) {
            progressFill.style.width = `${progress}%`;
            
            // Change color based on progress
            if (progress < 30) {
                progressFill.style.backgroundColor = '#ff4757';
            } else if (progress < 70) {
                progressFill.style.backgroundColor = '#ffa502';
            } else {
                progressFill.style.backgroundColor = '#2ed573';
            }
        }
        
        if (progressText) {
            progressText.textContent = `Form Progress: ${progress}%`;
            progressText.style.color = progress === 100 ? '#2ed573' : '#666';
        }
    }
    
    function updateStatusIndicator() {
        const status = statusSelect.value;
        
        if (statusColors[status]) {
            statusDot.style.backgroundColor = statusColors[status];
            statusDot.className = 'status-dot ' + status;
            statusText.textContent = statusMessages[status];
        }
    }
    
    function updateNotificationCount() {
        const notificationCount = document.getElementById('notificationCount');
        const incidentCount = document.getElementById('incidentCount');
        
        if (notificationCount) {
            let count = parseInt(notificationCount.textContent) || 0;
            notificationCount.textContent = count + 1;
            notificationCount.style.display = 'flex';
        }
        
        if (incidentCount) {
            let count = parseInt(incidentCount.textContent) || 0;
            incidentCount.textContent = count + 1;
            incidentCount.style.display = 'flex';
        }
    }
    
    function showSuccess(message) {
        if (!successAlert) return;
        
        successAlert.querySelector('#successMessage').textContent = message;
        successAlert.style.display = 'flex';
        
        if (errorAlert) {
            errorAlert.style.display = 'none';
        }
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            successAlert.style.display = 'none';
        }, 5000);
    }
    
    function showError(message) {
        if (!errorAlert) return;
        
        errorAlert.querySelector('#errorMessage').textContent = message;
        errorAlert.style.display = 'flex';
        
        if (successAlert) {
            successAlert.style.display = 'none';
        }
        
        // Auto-hide after 7 seconds (longer for errors)
        setTimeout(() => {
            errorAlert.style.display = 'none';
        }, 7000);
    }
    
    function showInfo(message) {
        // Create a temporary info message
        const infoDiv = document.createElement('div');
        infoDiv.className = 'alert-message alert-info';
        infoDiv.innerHTML = `
            <i class="fas fa-info-circle"></i>
            <span>${message}</span>
        `;
        
        const container = document.querySelector('.incident-content');
        if (container) {
            container.insertBefore(infoDiv, container.firstChild);
            
            // Auto-remove after 4 seconds
            setTimeout(() => {
                infoDiv.remove();
            }, 4000);
        }
    }
    
    function showAutoSave() {
        const indicator = document.getElementById('autosaveIndicator');
        if (!indicator) return;
        
        indicator.style.display = 'flex';
        indicator.innerHTML = '<i class="fas fa-save"></i> Draft saved successfully';
        
        setTimeout(() => {
            indicator.style.display = 'none';
        }, 3000);
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Test API connection
    function testAPIConnection() {
        console.log('Testing API connection...');
        
        fetch(API_URL + '?action=test_connection')
            .then(response => response.json())
            .then(data => {
                console.log('API Test Result:', data);
                
                if (data.success) {
                    console.log('API connection successful');
                    
                    if (!data.tables_exist) {
                        showInfo('Database tables created successfully');
                    }
                    
                    if (data.upload_dir && !data.upload_dir.writable) {
                        showError('Upload directory is not writable. File uploads may fail.');
                    }
                } else {
                    console.error('API test failed:', data.message);
                    showError('Server connection issue: ' + data.message);
                }
            })
            .catch(error => {
                console.error('API test failed:', error);
                showError('Cannot connect to server. Please check your connection.');
            });
    }
    
    // Initialize form with current date/time
    function initializeForm() {
        // Set default datetime to current time
        const now = new Date();
        const localDateTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000)
            .toISOString()
            .slice(0, 16);
        
        const datetimeInput = document.getElementById('datetime');
        if (datetimeInput && !datetimeInput.value) {
            datetimeInput.value = localDateTime;
        }
        
        // Set reported by from localStorage or default
        const reportedByInput = document.getElementById('reportedBy');
        if (reportedByInput) {
            const savedUser = localStorage.getItem('current_user') || 'John Doe';
            reportedByInput.value = savedUser;
        }
    }
    
    // Initialize
    initializeForm();
    loadDraft();
    testAPIConnection();
    
    // Set up auto-save every 30 seconds
    autoSaveInterval = setInterval(saveDraft, 30000);
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function(e) {
        if (formHasData()) {
            saveDraft();
            
            // For browsers that support it
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
    
    function formHasData() {
        // Check if form has any data
        const formElements = [
            'incidentTitle', 'incidentType', 'datetime', 'location',
            'description', 'priority', 'impactLevel', 'evidence',
            'actions'
        ];
        
        for (const fieldId of formElements) {
            const field = document.getElementById(fieldId);
            if (field && field.value.trim() !== '') {
                return true;
            }
        }
        
        return uploadedFiles.length > 0;
    }
    
    // Initialize time display
    updateTime();
    setInterval(updateTime, 1000);
    
    function updateTime() {
        const timeElement = document.getElementById('currentTime');
        if (timeElement) {
            const now = new Date();
            timeElement.textContent = now.toLocaleTimeString('en-US', {
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            }) + ' CAT';
            
            // Update date/time input if empty
            const datetimeInput = document.getElementById('datetime');
            if (datetimeInput && !datetimeInput.value) {
                const localDateTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000)
                    .toISOString()
                    .slice(0, 16);
                datetimeInput.value = localDateTime;
            }
        }
    }
    
    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + S to save draft
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            saveDraft();
            showSuccess('Draft saved (Ctrl+S)');
        }
        
        // Ctrl + Enter to submit form
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            if (form.checkValidity()) {
                form.dispatchEvent(new Event('submit'));
            } else {
                form.reportValidity();
            }
        }
        
        // Escape to clear form confirmation
        if (e.key === 'Escape') {
            if (formHasData()) {
                if (confirm('Press OK to clear form, Cancel to continue editing')) {
                    clearForm();
                }
            }
        }
    });
    
    // Add form field change indicators
    const formFields = form.querySelectorAll('input, textarea, select');
    formFields.forEach(field => {
        field.addEventListener('change', function() {
            if (this.value.trim() !== '') {
                this.classList.add('field-changed');
            } else {
                this.classList.remove('field-changed');
            }
        });
    });
});