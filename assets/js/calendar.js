/**
 * Preschool Monitoring System - Interactive School Calendar
 */

function initPreschoolCalendar(containerId, eventsData = []) {
    const container = document.getElementById(containerId);
    if (!container) return;

    let currentDate = new Date();

    function renderCalendar() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        const monthNames = [
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        ];

        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const today = new Date();

        let html = `
            <div class="calendar-header d-flex justify-between align-center" style="margin-bottom: 20px;">
                <h3 style="font-size: 1.3rem; margin: 0;">${monthNames[month]} ${year}</h3>
                <div class="d-flex gap-2">
                    <button class="btn btn-secondary btn-sm" id="calPrevBtn">&larr; Previous</button>
                    <button class="btn btn-secondary btn-sm" id="calTodayBtn">Today</button>
                    <button class="btn btn-secondary btn-sm" id="calNextBtn">Next &rarr;</button>
                </div>
            </div>
            <div class="calendar-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px;">
                ${['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(day => `
                    <div style="font-weight: 700; text-align: center; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted); padding: 8px;">
                        ${day}
                    </div>
                `).join('')}
        `;

        // Empty cells before first day
        for (let i = 0; i < firstDay; i++) {
            html += `<div style="background: var(--bg-card-subtle); border-radius: var(--radius-sm); min-height: 90px; opacity: 0.4;"></div>`;
        }

        // Days of month
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const isToday = today.getFullYear() === year && today.getMonth() === month && today.getDate() === day;
            const matchingEvents = eventsData.filter(e => e.date === dateStr);

            html += `
                <div class="calendar-day ${isToday ? 'today' : ''}" style="
                    background: ${isToday ? '#EEF2FF' : '#FFFFFF'};
                    border: ${isToday ? '2px solid var(--primary)' : '1px solid var(--border-color)'};
                    border-radius: var(--radius-md);
                    padding: 8px;
                    min-height: 95px;
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                    box-shadow: var(--shadow-sm);
                ">
                    <div style="font-weight: 700; font-size: 0.85rem; color: ${isToday ? 'var(--primary)' : 'var(--text-primary)'};">
                        ${day}
                    </div>
                    <div style="flex: 1; display: flex; flex-direction: column; gap: 4px;">
                        ${matchingEvents.map(e => `
                            <div class="event-pill" title="${e.description || e.title}" style="
                                background: ${e.type === 'Holiday' ? 'var(--rose-light)' : 'var(--primary-light)'};
                                color: ${e.type === 'Holiday' ? 'var(--rose-dark)' : 'var(--primary)'};
                                font-size: 0.72rem;
                                font-weight: 600;
                                padding: 2px 6px;
                                border-radius: 4px;
                                white-space: nowrap;
                                overflow: hidden;
                                text-overflow: ellipsis;
                                cursor: pointer;
                            ">
                                📅 ${e.title}
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        html += `</div>`;
        container.innerHTML = html;

        // Bind buttons
        document.getElementById('calPrevBtn').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        });
        document.getElementById('calNextBtn').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        });
        document.getElementById('calTodayBtn').addEventListener('click', () => {
            currentDate = new Date();
            renderCalendar();
        });
    }

    renderCalendar();
}
