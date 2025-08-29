document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    // Pre-written message templates
    const messageTemplates = {
        welcome: `Welcome! We're pleased to confirm your appointment with the Solano Mayor's Office. Our team looks forward to assisting you with your needs.`,
        
        preparation: `Please prepare for your appointment:
• Arrive 10 minutes early
• Bring a valid government-issued ID
• Have any relevant documents ready
• Prepare a list of questions you may have`,
        
        documents: `Please bring the following documents:
• Valid government-issued ID (Driver's License, Passport, etc.)
• Proof of residency (if applicable)
• Any previous correspondence related to your request
• Supporting documents mentioned in your original request`,
        
        early_arrival: `Important reminder: Please arrive at our office 10 minutes before your scheduled appointment time. This will allow us to process your visit efficiently and ensure we can give you our full attention during your appointment.`,
        
        contact_info: `If you have any questions or need to make changes to your appointment, please contact us:
• Phone: (078) 123-4567
• Email: info@solanomayor.gov.ph
• Office Hours: Monday - Friday, 8:00 AM - 5:00 PM`
    };

    // Handle approve button clicks
    document.addEventListener('click', function(e) {
        if (e.target.closest('.approve-btn')) {
            const button = e.target.closest('.approve-btn');
            const appointmentId = button.getAttribute('data-appointment-id');
            const appointmentData = JSON.parse(button.getAttribute('data-appointment-data') || '{}');
            
            // Set appointment ID
            document.getElementById('approve_appointment_id').value = appointmentId;
            
            // Populate appointment details preview
            populateAppointmentDetails(appointmentData);
            
            // Reset form
            resetApproveForm();
        }
    });

    // Populate appointment details in modal
    function populateAppointmentDetails(appointment) {
        const detailsContainer = document.getElementById('appointmentDetailsPreview');
        
        const formattedDate = new Date(appointment.date).toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        const formattedTime = new Date('1970-01-01T' + appointment.time).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });

        detailsContainer.innerHTML = `
            <h6 class="fw-bold mb-2">Appointment Details:</h6>
            <p class="mb-1"><strong>Resident:</strong> ${appointment.resident_name || 'N/A'}</p>
            <p class="mb-1"><strong>Date:</strong> ${formattedDate}</p>
            <p class="mb-1"><strong>Time:</strong> ${formattedTime}</p>
            <p class="mb-1"><strong>Purpose:</strong> ${appointment.purpose || 'N/A'}</p>
            <p class="mb-1"><strong>Attendees:</strong> ${appointment.attendees || 'N/A'} person(s)</p>
            ${appointment.other_details ? `<p class="mb-0"><strong>Other Details:</strong> ${appointment.other_details}</p>` : ''}
        `;
    }

    // Handle message template selection
    const messageSelect = document.getElementById('approve_message_select');
    const customContainer = document.getElementById('custom_message_container');
    const messageTextarea = document.getElementById('admin_message');

    if (messageSelect) {
        messageSelect.addEventListener('change', function() {
            const selectedValue = this.value;
            
            if (selectedValue === 'custom') {
                customContainer.style.display = 'block';
                messageTextarea.value = '';
                messageTextarea.focus();
            } else if (selectedValue && messageTemplates[selectedValue]) {
                customContainer.style.display = 'block';
                messageTextarea.value = messageTemplates[selectedValue];
            } else {
                customContainer.style.display = 'none';
                messageTextarea.value = '';
            }
        });
    }

    // Reset form when modal is closed
    const approveModal = document.getElementById('approveModal');
    if (approveModal) {
        approveModal.addEventListener('hidden.bs.modal', function() {
            resetApproveForm();
        });
    }

    function resetApproveForm() {
        if (messageSelect) messageSelect.selectedIndex = 0;
        if (customContainer) customContainer.style.display = 'none';
        if (messageTextarea) messageTextarea.value = '';
    }

    // Handle form submission with better feedback
    const approveForm = document.querySelector('#approveModal form');
    if (approveForm) {
        approveForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Processing...';
            
            // Allow form to submit normally, but provide visual feedback
            setTimeout(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Confirm Approval';
                }
            }, 3000);
        });
    }

    // Auto-dismiss alerts after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (bootstrap.Alert.getInstance(alert)) {
                bootstrap.Alert.getInstance(alert).close();
            } else {
                new bootstrap.Alert(alert).close();
            }
        });
    }, 5000);

    // Handle Reschedule button - removed duplicate handler

    // View details modal logic
    document.querySelectorAll('.view-details-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const app = JSON.parse(this.getAttribute('data-appointment'));
            let attachmentsHtml = '';
            
            if (app.attachments && app.attachments.length > 0) {
                const files = app.attachments.split(',').filter(f => f.trim() !== '');
                if (files.length > 0) {
                    attachmentsHtml = '<div class="mb-3"><div class="fw-medium mb-2">Attachments:</div><div class="d-flex flex-wrap gap-2">' +
                        files.map((file, idx) => {
                            const ext = file.split('.').pop().toLowerCase();
                            let icon = 'bi-file-earmark';
                            if (["jpg","jpeg","png","gif","bmp","webp"].includes(ext)) icon = 'bi-file-earmark-image';
                            else if (ext === "pdf") icon = 'bi-file-earmark-pdf';
                            else if (["doc","docx"].includes(ext)) icon = 'bi-file-earmark-word';
                            return `<button type="button" class="btn btn-outline-secondary btn-sm admin-attachment-btn" data-file="${file}"><i class="bi ${icon}"></i> ${file}</button>`;
                        }).join('') + '</div></div>';
                } else {
                    attachmentsHtml = '<div class="mb-3"><div class="fw-medium mb-2">Attachments:</div><span class="text-muted">No files attached</span></div>';
                }
            } else {
                attachmentsHtml = '<div class="mb-3"><div class="fw-medium mb-2">Attachments:</div><span class="text-muted">No files attached</span></div>';
            }
            
            document.getElementById('viewDetailsBody').innerHTML = `
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded p-3">
                            <div class="fw-medium text-muted mb-1">Resident</div>
                            <div>${app.resident_name}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3">
                            <div class="fw-medium text-muted mb-1">Status</div>
                            <span class="badge bg-warning">${app.status}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3">
                            <div class="fw-medium text-muted mb-1">Date</div>
                            <div>${app.date}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3">
                            <div class="fw-medium text-muted mb-1">Time</div>
                            <div>${app.time}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3">
                            <div class="fw-medium text-muted mb-1">Attendees</div>
                            <div>${app.attendees} person(s)</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3">
                            <div class="fw-medium text-muted mb-1">Appointment ID</div>
                            <div>#${app.appointment_id}</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded p-3">
                            <div class="fw-medium text-muted mb-2">Purpose</div>
                            <div>${app.purpose}</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded p-3">
                            <div class="fw-medium text-muted mb-2">Other Details</div>
                            <div>${app.other_details || 'None provided'}</div>
                        </div>
                    </div>
                </div>
                <div class="mt-4">${attachmentsHtml}</div>
            `;
            new bootstrap.Modal(document.getElementById('viewDetailsModal')).show();
        });
    });

    // Attachment preview modal logic
    document.getElementById('viewDetailsBody').addEventListener('click', function(e) {
        if (e.target.closest('.admin-attachment-btn')) {
            const btn = e.target.closest('.admin-attachment-btn');
            const file = btn.getAttribute('data-file');
            const ext = file.split('.').pop().toLowerCase();
            const previewBody = document.getElementById('adminAttachmentPreviewBody');
            const downloadBtn = document.getElementById('adminDownloadAttachmentBtn');
            
            previewBody.innerHTML = '<div style="color:#888;font-size:1.2rem;">Loading preview...</div>';
            downloadBtn.href = '../user/uploads/' + file;
            downloadBtn.setAttribute('download', file);
            
            if (["jpg","jpeg","png","gif","bmp","webp"].includes(ext)) {
                previewBody.innerHTML = `<img src="../user/uploads/${file}" style="max-width:100%;max-height:70vh;border-radius:8px;box-shadow:0 4px 6px -1px rgb(0 0 0 / 0.1);">`;
            } else if (ext === "pdf") {
                previewBody.innerHTML = `<embed src="../user/uploads/${file}" type="application/pdf" width="100%" height="500px" style="border-radius:8px;box-shadow:0 4px 6px -1px rgb(0 0 0 / 0.1);"/>`;
            } else {
                previewBody.innerHTML = `<div style='color:#888;font-size:1.1rem;'>Cannot preview this file type.<br><a href='../user/uploads/${file}' download class='btn btn-primary mt-3'><i class="bi bi-download"></i> Download File</a></div>`;
            }
            
            new bootstrap.Modal(document.getElementById('adminAttachmentPreviewModal')).show();
        }
    });

    // Print button for attachment preview
    document.getElementById('adminPrintAttachmentBtn').addEventListener('click', function() {
        const previewBody = document.getElementById('adminAttachmentPreviewBody');
        const printWindow = window.open('', '', 'width=900,height=700');
        printWindow.document.write('<html><head><title>Print Attachment</title>');
        printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
        printWindow.document.write('</head><body style="padding:20px;">');
        printWindow.document.write(previewBody.innerHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => { printWindow.print(); printWindow.close(); }, 500);
    });

    // Attachments modal logic
    let currentAttachment = '';
    let allAttachments = [];
    
    document.querySelectorAll('.view-attachments-btn, .approved-view-attachments-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const attachments = this.getAttribute('data-attachments');
            allAttachments = attachments.split(',').filter(f => f.trim() !== '');
            if (allAttachments.length === 0) return;
            showAttachment(allAttachments[0]);
            new bootstrap.Modal(document.getElementById('attachmentsModal')).show();
        });
    });

    function showAttachment(file) {
        currentAttachment = file;
        const ext = file.split('.').pop().toLowerCase();
        const previewBody = document.getElementById('attachmentsModalBody');
        const downloadBtn = document.getElementById('attachmentsDownloadBtn');
        let navHtml = '';
        
        if (allAttachments.length > 1) {
            navHtml = '<div class="mb-3 d-flex flex-wrap gap-2 justify-content-center">' +
                allAttachments.map(f => {
                    const active = (f === file) ? 'btn-primary' : 'btn-outline-primary';
                    return `<button type="button" class="btn ${active} btn-sm attachment-nav-btn" data-file="${f}">${f}</button>`;
                }).join('') + '</div>';
        }
        
        downloadBtn.href = '../user/uploads/' + file;
        downloadBtn.setAttribute('download', file);
        
        if (["jpg","jpeg","png","gif","bmp","webp"].includes(ext)) {
            previewBody.innerHTML = navHtml + `<img src="../user/uploads/${file}" style="max-width:100%;max-height:70vh;border-radius:8px;box-shadow:0 4px 6px -1px rgb(0 0 0 / 0.1);">`;
        } else if (ext === "pdf") {
            previewBody.innerHTML = navHtml + `<embed src="../user/uploads/${file}" type="application/pdf" width="100%" height="500px" style="border-radius:8px;box-shadow:0 4px 6px -1px rgb(0 0 0 / 0.1);"/>`;
        } else {
            previewBody.innerHTML = navHtml + `<div style='color:#888;font-size:1.1rem;'>Cannot preview this file type.<br><a href='../user/uploads/${file}' download class='btn btn-primary mt-3'><i class="bi bi-download"></i> Download File</a></div>`;
        }
        
        document.querySelectorAll('.attachment-nav-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                showAttachment(this.getAttribute('data-file'));
            });
        });
    }

    // Print button for attachments modal
    document.getElementById('attachmentsPrintBtn').addEventListener('click', function() {
        const previewBody = document.getElementById('attachmentsModalBody');
        const printWindow = window.open('', '', 'width=900,height=700');
        printWindow.document.write('<html><head><title>Print Attachment</title>');
        printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
        printWindow.document.write('</head><body style="padding:20px;">');
        
        let content = previewBody.innerHTML;
        if (content.indexOf('attachment-nav-btn') !== -1) {
            content = content.replace(/<div class="mb-3 d-flex flex-wrap gap-2 justify-content-center">[\s\S]*?<\/div>/, '');
        }
        
        printWindow.document.write(content);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => { printWindow.print(); printWindow.close(); }, 500);
    });

    // Decline functionality
    document.querySelectorAll('.decline-btn').forEach(button => {
        button.addEventListener('click', function() {
            const appointmentId = this.getAttribute('data-appointment-id');
            document.getElementById('decline_appointment_id').value = appointmentId;
        });
    });

    const reasonSelect = document.getElementById('decline_reason_select');
    const customReasonContainer = document.getElementById('custom_reason_container');
    const textarea = document.getElementById('decline_reason');

    if (reasonSelect) {
        reasonSelect.addEventListener('change', function() {
            if (this.value === 'others') {
                customReasonContainer.style.display = 'block';
                textarea.required = true;
                textarea.value = '';
            } else {
                customReasonContainer.style.display = 'none';
                textarea.required = false;
                textarea.value = this.value;
            }
        });
    }

    // Reset decline modal when closed
    const declineModal = document.getElementById('declineModal');
    if (declineModal) {
        declineModal.addEventListener('hidden.bs.modal', function() {
            if (reasonSelect) reasonSelect.selectedIndex = 0;
            if (customReasonContainer) customReasonContainer.style.display = 'none';
            if (textarea) {
                textarea.value = '';
                textarea.required = false;
            }
        });
    }

    // Add confirmation to complete buttons
    document.querySelectorAll('.complete-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to mark this appointment as completed?')) {
                e.preventDefault();
            }
        });
    });

    // Helper function to show alerts
    function showAlert(message, type) {
        // Remove any existing alerts first
        document.querySelectorAll('.alert').forEach(alert => alert.remove());
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `
            <i class="bi bi-check-circle-fill me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        const container = document.querySelector('.content') || document.body;
        container.prepend(alertDiv);
        
        setTimeout(() => {
            if (bootstrap.Alert.getInstance(alertDiv)) {
                bootstrap.Alert.getInstance(alertDiv).close();
            } else {
                new bootstrap.Alert(alertDiv).close();
            }
        }, 5000);
    }

    // Set minimum date for date inputs to today
    const today = new Date().toISOString().split('T')[0];
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        input.setAttribute('min', today);
    });

    // Form validation feedback
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // Close sidebar on window resize if mobile
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        }
    });

});
