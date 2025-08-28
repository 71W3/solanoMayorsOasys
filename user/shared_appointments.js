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
    // Try to update either calendar-grid or calendarBody depending on structure
    const calendarGrid = document.querySelector('.calendar-grid');
    const calendarBody = document.getElementById('calendarBody');
    
    if (calendarBody) {
        // Table structure - convert to table rows
        let tableHTML = '';
        const days = calendarHTML.match(/<div class="calendar-day[^>]*>.*?<\/div>/g) || [];
        
        for (let i = 0; i < days.length; i += 7) {
            tableHTML += '<tr>';
            for (let j = 0; j < 7; j++) {
                const dayHTML = days[i + j] || '<td></td>';
                // Convert div to td
                const tdHTML = dayHTML.replace('<div class="calendar-day', '<td class="calendar-date')
                                     .replace('</div>', '</td>')
                                     .replace('data-date=', 'data-date=');
                tableHTML += tdHTML;
            }
            tableHTML += '</tr>';
        }
        calendarBody.innerHTML = tableHTML;
    } else if (calendarGrid) {
        // Grid structure
        calendarGrid.innerHTML = calendarHTML;
    }
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

// Custom Dropdown - Simplified and more reliable
function setupCustomDropdown(dropdownId, btnId, optionClass, labelId, callback) {
    const dropdown = document.getElementById(dropdownId);
    const btn = document.getElementById(btnId);
    const label = document.getElementById(labelId);
    
    if (!dropdown || !btn || !label) {
        console.warn('Dropdown elements not found:', dropdownId, btnId, labelId);
        return false;
    }
    
    // Mark as initialized to prevent duplicate setup
    if (btn.dataset.initialized === 'true') {
        console.log('Dropdown already initialized:', dropdownId);
        return true;
    }
    
    const options = dropdown.querySelectorAll('.' + optionClass);
    console.log('Setting up dropdown:', dropdownId, 'with', options.length, 'options');
    
    // Button click handler
    const handleButtonClick = function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Close all other dropdowns
        document.querySelectorAll('.custom-dropdown.open').forEach(dd => {
            if (dd !== dropdown) {
                dd.classList.remove('open');
                const ddBtn = dd.querySelector('.custom-dropdown-btn');
                if (ddBtn) {
                    ddBtn.setAttribute('aria-expanded', 'false');
                    ddBtn.classList.remove('open');
                }
            }
        });
        
        // Toggle this dropdown
        const isOpen = dropdown.classList.contains('open');
        if (isOpen) {
            dropdown.classList.remove('open');
            btn.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
        } else {
            dropdown.classList.add('open');
            btn.classList.add('open');
            btn.setAttribute('aria-expanded', 'true');
        }
        
        console.log('Dropdown toggled:', dropdownId, 'open:', !isOpen);
    };
    
    // Option click handler
    const handleOptionClick = function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Update selection
        options.forEach(opt => opt.classList.remove('selected'));
        this.classList.add('selected');
        
        // Update label
        label.textContent = this.textContent.trim();
        
        // Close dropdown
        dropdown.classList.remove('open');
        btn.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
        
        // Execute callback
        if (callback) {
            callback(this.getAttribute('data-value'), this.textContent.trim());
        }
    };
    
    // Add event listeners
    btn.addEventListener('click', handleButtonClick);
    
    options.forEach(function(option) {
        option.addEventListener('click', handleOptionClick);
    });
    
    // Close dropdown when clicking outside
    const handleOutsideClick = function(e) {
        if (!dropdown.contains(e.target) && !btn.contains(e.target)) {
            dropdown.classList.remove('open');
            btn.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
        }
    };
    
    document.addEventListener('click', handleOutsideClick);
    
    // Mark as initialized
    btn.dataset.initialized = 'true';
    
    return true;
}

// Appointment Sorting Functions
function sortAppointments(container, sortBy) {
    if (!container) {
        console.error('Container not found for sorting');
        return;
    }
    
    console.log('Container found:', container.id, 'Children:', container.children.length);
    
    const cards = Array.from(container.children).filter(child => 
        child.classList.contains('appointment-card')
    );
    
    console.log('Found', cards.length, 'appointment cards to sort by', sortBy);
    
    if (cards.length === 0) {
        console.warn('No appointment cards found to sort');
        return;
    }
    
    // Debug: log first card's data attributes
    if (cards[0]) {
        console.log('First card data:', {
            date: cards[0].dataset.date,
            requested: cards[0].dataset.requested
        });
    }
    
    cards.sort((a, b) => {
        let valueA, valueB;
        
        switch (sortBy) {
            case 'date-asc':
                valueA = parseInt(a.dataset.date) || 0;
                valueB = parseInt(b.dataset.date) || 0;
                console.log('Sorting date ASC:', valueA, 'vs', valueB);
                return valueA - valueB;
            case 'date-desc':
                valueA = parseInt(a.dataset.date) || 0;
                valueB = parseInt(b.dataset.date) || 0;
                console.log('Sorting date DESC:', valueA, 'vs', valueB);
                return valueB - valueA;
            case 'created-asc':
                valueA = parseInt(a.dataset.requested) || 0;
                valueB = parseInt(b.dataset.requested) || 0;
                console.log('Sorting requested ASC:', valueA, 'vs', valueB);
                return valueA - valueB;
            case 'created-desc':
                valueA = parseInt(a.dataset.requested) || 0;
                valueB = parseInt(b.dataset.requested) || 0;
                console.log('Sorting requested DESC:', valueA, 'vs', valueB);
                return valueB - valueA;
            default:
                console.warn('Unknown sort type:', sortBy);
                return 0;
        }
    });
    
    // Clear container and re-append sorted cards
    const nonCardElements = Array.from(container.children).filter(child => 
        !child.classList.contains('appointment-card')
    );
    
    console.log('Clearing container and re-adding', cards.length, 'sorted cards');
    container.innerHTML = '';
    nonCardElements.forEach(element => container.appendChild(element));
    cards.forEach(card => container.appendChild(card));
    
    console.log('Sorting completed successfully');
}

function setupAppointmentSorting() {
    console.log('Setting up appointment sorting...');
    
    // Setup sort dropdowns for each tab
    const sortConfigs = [
        { id: 'upcomingSortDropdown', btn: 'upcomingSortBtn', option: 'upcoming-sort-option', label: 'upcomingSortLabel', container: 'upcomingAppointmentsContainer' },
        { id: 'pendingSortDropdown', btn: 'pendingSortBtn', option: 'pending-sort-option', label: 'pendingSortLabel', container: 'pendingRequestsContainer' },
        { id: 'lapsedSortDropdown', btn: 'lapsedSortBtn', option: 'lapsed-sort-option', label: 'lapsedSortLabel', container: 'lapsedAppointmentsContainer' },
        { id: 'historySortDropdown', btn: 'historySortBtn', option: 'history-sort-option', label: 'historySortLabel', container: 'historyAppointmentsContainer' },
        { id: 'categorySortDropdown', btn: 'categorySortBtn', option: 'category-sort-option', label: 'categorySortLabel', container: 'categoryAppointments' }
    ];
    
    sortConfigs.forEach(config => {
        const dropdown = document.getElementById(config.id);
        const container = document.getElementById(config.container);
        
        console.log('Checking config:', config.id, 'Dropdown exists:', !!dropdown, 'Container exists:', !!container);
        
        if (dropdown) {
            setupCustomDropdown(config.id, config.btn, config.option, config.label, function(value, text) {
                console.log('Sort callback triggered for:', config.id, 'value:', value);
                const targetContainer = document.getElementById(config.container);
                if (targetContainer) {
                    console.log('Found target container:', config.container);
                    sortAppointments(targetContainer, value);
                } else {
                    console.error('Container not found:', config.container);
                }
            });
        } else {
            console.log('Dropdown not found:', config.id);
        }
    });
    
    // Setup history filter dropdown
    if (document.getElementById('historyFilterDropdown')) {
        setupCustomDropdown('historyFilterDropdown', 'historyFilterBtn', 'history-filter-option', 'historyFilterLabel', function(value, text) {
            const container = document.getElementById('historyAppointmentsContainer');
            const emptyState = document.getElementById('historyEmptyState');
            if (!container) return;
            
            const cards = container.querySelectorAll('.appointment-card');
            let visibleCount = 0;
            
            cards.forEach(card => {
                const status = card.dataset.status;
                if (value === 'all' || status === value) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            if (emptyState) {
                emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        });
    }
}

// Make functions available globally - both ways for compatibility
window.setupAppointmentSorting = setupAppointmentSorting;
window.setupCustomDropdown = setupCustomDropdown;
window.sortAppointments = sortAppointments;
window.updateMonthYearDisplay = updateMonthYearDisplay;

window.sharedAppointments = {
    setupAppointmentSorting: setupAppointmentSorting,
    setupCustomDropdown: setupCustomDropdown,
    sortAppointments: sortAppointments,
    updateMonthYearDisplay: updateMonthYearDisplay
};

// Functions are now available globally - initialization handled by HTML