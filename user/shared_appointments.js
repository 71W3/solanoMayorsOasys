// Shared Appointment Functions
// These functions are used by both userSide.php and userAppointment.php

// Calendar Functions
function updateMonthYearDisplay(currentMonth, currentYear) {
    const monthNames = ["January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"];
    document.getElementById('currentMonthYear').textContent = `${monthNames[currentMonth]} ${currentYear}`;
}

function renderCalendar(currentMonth, currentYear, appointmentCounts, amSlots, pmSlots) {
    const monthNames = ["January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"];
    const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const today = new Date();
    today.setHours(0,0,0,0);
    const maxDate = new Date(today);
    maxDate.setDate(today.getDate() + 13);
    const firstDay = new Date(currentYear, currentMonth, 1).getDay();
    const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
    const isCurrentMonth = today.getMonth() === currentMonth && today.getFullYear() === currentYear;
    let calendarHTML = '';
    for (let i = 0; i < dayNames.length; i++) {
        calendarHTML += `<div class="calendar-day-header">${dayNames[i]}</div>`;
    }
    for (let i = 0; i < firstDay; i++) {
        calendarHTML += `<div class="calendar-day disabled"></div>`;
    }
    for (let i = 1; i <= daysInMonth; i++) {
        const dateObj = new Date(currentYear, currentMonth, i);
        dateObj.setHours(0,0,0,0);
        const isToday = isCurrentMonth && i === today.getDate();
        const dateStr = `${currentYear}-${String(currentMonth+1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
        let isDisabled = dateObj < today || dateObj > maxDate;
        calendarHTML += `<div class="calendar-day${isToday ? ' today' : ''}${isDisabled ? ' disabled' : ''}" data-date="${dateStr}">`;
        calendarHTML += `<div style='font-size:1.08em;line-height:1.1;'>${i}</div>`;
        if (isToday) {
            calendarHTML += `<div style='color:#28a745;font-size:0.65em;font-weight:600;margin-top:2px;line-height:1.1;'>Today</div>`;
        }
        const approvedCount = appointmentCounts[dateStr]?.approved || 0;
        const pendingCount = appointmentCounts[dateStr]?.pending || 0;
        if (approvedCount > 0 || pendingCount > 0) {
            calendarHTML += `<div class="appointment-indicators">`;
            for (let j = 0; j < approvedCount; j++) {
                calendarHTML += `<div class="appointment-indicator indicator-approved"></div>`;
            }
            for (let j = 0; j < pendingCount; j++) {
                calendarHTML += `<div class="appointment-indicator indicator-pending"></div>`;
            }
            calendarHTML += `</div>`;
        }
        calendarHTML += `</div>`;
    }
    const totalCells = 42;
    const daysAdded = firstDay + daysInMonth;
    const remainingCells = totalCells - daysAdded;
    for (let i = 1; i <= remainingCells; i++) {
        calendarHTML += `<div class="calendar-day disabled"></div>`;
    }
    document.querySelector('.calendar-grid').innerHTML = calendarHTML;
}

// Time Slot Functions
function initTimeSlots(selectedSlot, updateSelectedSummary) {
    const timeSlots = document.querySelectorAll('.time-slot.available');
    timeSlots.forEach(slot => {
        slot.addEventListener('click', function() {
            document.querySelectorAll('.time-slot.selected').forEach(s => s.classList.remove('selected'));
            this.classList.add('selected');
            selectedSlot.value = this.textContent.trim();
            if (typeof updateSelectedSummary === 'function') updateSelectedSummary();
        });
    });
    document.querySelectorAll('.time-slot.pending.selected').forEach(slot => slot.classList.remove('selected'));
    if (typeof updateSelectedSummary === 'function') updateSelectedSummary();
}

function renderTimeSlots(unavailableSlots, amSlots, pmSlots, selectedSlot) {
    let slotStatusMap = {};
    if (unavailableSlots.length && typeof unavailableSlots[0] === 'object' && unavailableSlots[0].time) {
        unavailableSlots.forEach(s => {
            slotStatusMap[s.time.toUpperCase().trim()] = s.status;
        });
    } else {
        unavailableSlots.forEach(s => {
            slotStatusMap[s.toUpperCase().trim()] = 'pending';
        });
    }
    const amContainer = document.getElementById('am-slots');
    amContainer.innerHTML = '';
    amSlots.forEach(slot => {
        slot = slot.replace(/\u2714|\u2713|<.*?>/g, '').trim();
        let slotClass = '';
        let icon = '';
        let style = '';
        let isSelected = (slot === selectedSlot.value);
        let tooltip = '';
        let status = slotStatusMap[slot.toUpperCase().trim()];
        let selectedDateStr = document.getElementById('selectedDateInput').value;
        let now = new Date();
        let isToday = false;
        if (selectedDateStr) {
            let todayStr = now.toISOString().slice(0,10);
            isToday = (selectedDateStr === todayStr);
        }
        let slotIsPast = false;
        if (isToday) {
            let [time, meridian] = slot.split(' ');
            let [hour, minute] = time.split(':');
            hour = parseInt(hour, 10);
            minute = parseInt(minute, 10);
            if (meridian === 'PM' && hour !== 12) hour += 12;
            if (meridian === 'AM' && hour === 12) hour = 0;
            let slotDate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), hour, minute, 0, 0);
            if (slotDate < now) slotIsPast = true;
        }
        if (slotIsPast && !status) {
            status = 'unavailable';
        }
        if (status === 'approved') {
            slotClass = 'time-slot approved';
            icon = `<i class="bi bi-check-circle-fill" style="font-size:1.3em;"></i>`;
            style = 'display:flex;align-items:center;justify-content:center;gap:2px;background:#e6f9ed;color:#218838;border:2px solid #28a745;font-weight:600;min-width:100px;padding:6px 12px;white-space:nowrap;';
            tooltip = 'This timeslot is approved.';
        } else if (status === 'pending') {
            slotClass = 'time-slot pending';
            icon = `<i class="bi bi-hourglass-split" style="font-size:1.3em;"></i>`;
            style = 'display:flex;align-items:center;justify-content:center;gap:2px;background:#fff8e1;color:#bfa700;border:2px solid #ffc107;font-weight:600;min-width:100px;padding:6px 12px;white-space:nowrap;';
            tooltip = 'This timeslot is pending, just waiting for approval';
        } else if (status === 'unavailable') {
            slotClass = 'time-slot unavailable';
            icon = `<i class="bi bi-hourglass-bottom" style="font-size:1.3em;"></i>`;
            style = 'display:flex;align-items:center;justify-content:center;gap:2px;background:#eee;color:#aaa;border:1px solid #bbb;min-width:100px;padding:6px 12px;white-space:nowrap;opacity:0.7;cursor:not-allowed;pointer-events:none;';
            tooltip = 'This timeslot is unavailable (time has passed).';
        } else {
            slotClass = 'time-slot available';
            icon = `<i class="bi bi-calendar-plus-fill" style="font-size:1.3em;"></i>`;
            style = 'display:flex;align-items:center;justify-content:center;gap:2px;background:#eaf3fb;color:#2563eb;border:2px solid #2563eb;font-weight:600;min-width:100px;padding:6px 12px;white-space:nowrap;';
            tooltip = 'This timeslot is available';
        }
        const slotHTML = `<div class="${slotClass}${isSelected ? ' selected' : ''}" style="${style}" data-bs-toggle="tooltip" data-bs-placement="top" title="${tooltip}">${icon}<span style="font-weight:600;white-space:nowrap;">${slot}</span></div>`;
        amContainer.innerHTML += slotHTML;
    });
    const pmContainer = document.getElementById('pm-slots');
    pmContainer.innerHTML = '';
    pmSlots.forEach(slot => {
        slot = slot.replace(/\u2714|\u2713|<.*?>/g, '').trim();
        let slotClass = '';
        let icon = '';
        let style = '';
        let isSelected = (slot === selectedSlot.value);
        let tooltip = '';
        let status = slotStatusMap[slot.toUpperCase().trim()];
        let selectedDateStr = document.getElementById('selectedDateInput').value;
        let now = new Date();
        let isToday = false;
        if (selectedDateStr) {
            let todayStr = now.toISOString().slice(0,10);
            isToday = (selectedDateStr === todayStr);
        }
        let slotIsPast = false;
        if (isToday) {
            let [time, meridian] = slot.split(' ');
            let [hour, minute] = time.split(':');
            hour = parseInt(hour, 10);
            minute = parseInt(minute, 10);
            if (meridian === 'PM' && hour !== 12) hour += 12;
            if (meridian === 'AM' && hour === 12) hour = 0;
            let slotDate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), hour, minute, 0, 0);
            if (slotDate < now) slotIsPast = true;
        }
        if (slotIsPast && !status) {
            status = 'unavailable';
        }
        if (status === 'approved') {
            slotClass = 'time-slot approved';
            icon = `<i class="bi bi-check-circle-fill" style="font-size:1.3em;"></i>`;
            style = 'display:flex;align-items:center;justify-content:center;gap:2px;background:#e6f9ed;color:#218838;border:2px solid #28a745;font-weight:600;min-width:100px;padding:6px 12px;white-space:nowrap;';
            tooltip = 'This timeslot is approved.';
        } else if (status === 'pending') {
            slotClass = 'time-slot pending';
            icon = `<i class="bi bi-hourglass-split" style="font-size:1.3em;"></i>`;
            style = 'display:flex;align-items:center;justify-content:center;gap:2px;background:#fff8e1;color:#bfa700;border:2px solid #ffc107;font-weight:600;min-width:100px;padding:6px 12px;white-space:nowrap;';
            tooltip = 'This timeslot is pending, just waiting for approval';
        } else if (status === 'unavailable') {
            slotClass = 'time-slot unavailable';
            icon = `<i class="bi bi-hourglass-bottom" style="font-size:1.3em;"></i>`;
            style = 'display:flex;align-items:center;justify-content:center;gap:2px;background:#eee;color:#aaa;border:1px solid #bbb;min-width:100px;padding:6px 12px;white-space:nowrap;opacity:0.7;cursor:not-allowed;pointer-events:none;';
            tooltip = 'This timeslot is unavailable (time has passed).';
        } else {
            slotClass = 'time-slot available';
            icon = `<i class="bi bi-calendar-plus-fill" style="font-size:1.3em;"></i>`;
            style = 'display:flex;align-items:center;justify-content:center;gap:2px;background:#eaf3fb;color:#2563eb;border:2px solid #2563eb;font-weight:600;min-width:100px;padding:6px 12px;white-space:nowrap;';
            tooltip = 'This timeslot is available';
        }
        const slotHTML = `<div class="${slotClass}${isSelected ? ' selected' : ''}" style="${style}" data-bs-toggle="tooltip" data-bs-placement="top" title="${tooltip}">${icon}<span style="font-weight:600;white-space:nowrap;">${slot}</span></div>`;
        pmContainer.innerHTML += slotHTML;
    });
    initTimeSlots(selectedSlot);
    // Re-initialize Bootstrap tooltips if needed
    if (window.bootstrap && window.bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            const tooltipInstance = new bootstrap.Tooltip(tooltipTriggerEl, { trigger: 'hover' });
            tooltipTriggerEl.addEventListener('click', function() {
                tooltipInstance.hide();
            });
            tooltipTriggerEl.addEventListener('touchstart', function() {
                tooltipInstance.hide();
            });
        });
    }
}

// File Attachment Helpers
function getFileExtension(filename) {
    return filename.slice((filename.lastIndexOf('.') + 1)).toLowerCase();
}
function getFileIcon(extension) {
    const icons = {
        'pdf': 'bi-file-earmark-pdf',
        'doc': 'bi-file-earmark-word',
        'docx': 'bi-file-earmark-word',
        'jpg': 'bi-file-earmark-image',
        'jpeg': 'bi-file-earmark-image',
        'png': 'bi-file-earmark-image'
    };
    return icons[extension] || 'bi-file-earmark';
}
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Custom Dropdown
function setupCustomDropdown(dropdownId, btnId, optionClass, labelId, callback) {
    const dropdown = document.getElementById(dropdownId);
    const btn = document.getElementById(btnId);
    const label = document.getElementById(labelId);
    const options = dropdown.querySelectorAll('.' + optionClass);
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const expanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', !expanded);
        btn.classList.toggle('open');
        dropdown.classList.toggle('open');
    });
    options.forEach(function(option) {
        option.addEventListener('click', function(e) {
            e.preventDefault();
            options.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            label.textContent = this.textContent.trim();
            btn.setAttribute('aria-expanded', 'false');
            btn.classList.remove('open');
            dropdown.classList.remove('open');
            if (callback) callback(this.getAttribute('data-value'), this.textContent.trim());
        });
    });
    document.addEventListener('click', function(e) {
        if (!dropdown.contains(e.target)) {
            btn.setAttribute('aria-expanded', 'false');
            btn.classList.remove('open');
            dropdown.classList.remove('open');
        }
    });
}

// Export to global window for use in inline scripts
window.sharedAppointments = {
    updateMonthYearDisplay,
    renderCalendar,
    initTimeSlots,
    renderTimeSlots,
    getFileExtension,
    getFileIcon,
    formatFileSize,
    setupCustomDropdown
};