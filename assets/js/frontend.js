(function ($) {
    'use strict';

    const root = $('#llsba-booking-form');
    if (!root.length || typeof llsbaData === 'undefined') {
        return;
    }

    if (!llsbaData.license || !llsbaData.license.active) {
        root.find('#llsba-message').text(llsbaData.labels.unlicensed || 'License required').addClass('is-error');
        root.find('button, input').prop('disabled', true);
        return;
    }

    const state = {
        contact: '',
        date: '',
        time: '',
        currentStep: 1,
        currentMonthDate: new Date(),
        monthData: {},
    };

    const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    function ajax(action, payload = {}) {
        return $.post(llsbaData.ajaxUrl, {
            action,
            nonce: llsbaData.nonce,
            ...payload,
        });
    }

    function setMessage(text, isError = false) {
        const messageEl = root.find('#llsba-message');
        messageEl.text(text || '');
        messageEl.toggleClass('is-error', isError);
    }

    function showStep(step) {
        state.currentStep = step;

        root.find('.llsba-step').removeClass('is-active');
        root.find(`.llsba-step[data-step="${step}"]`).addClass('is-active');

        root.find('[data-step-label]').removeClass('is-active');
        root.find(`[data-step-label="${step}"]`).addClass('is-active');
    }

    function renderCalendar(monthDate) {
        const year = monthDate.getFullYear();
        const month = monthDate.getMonth() + 1;

        root.find('#llsba-month-label').text(
            monthDate.toLocaleString([], { month: 'long', year: 'numeric' })
        );

        ajax('llsba_month_data', { year, month }).done(function (response) {
            if (!response.success) {
                setMessage(response.data?.message || 'Error loading calendar', true);
                return;
            }

            state.monthData = response.data.days || {};

            const firstDay = new Date(year, month - 1, 1).getDay();
            const totalDays = new Date(year, month, 0).getDate();
            const grid = [];

            dayNames.forEach((name) => {
                grid.push(`<div class="llsba-cell llsba-head">${name}</div>`);
            });

            for (let i = 0; i < firstDay; i += 1) {
                grid.push('<div class="llsba-cell llsba-empty"></div>');
            }

            for (let day = 1; day <= totalDays; day += 1) {
                const date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const data = state.monthData[date] || { booked: 0, available: 0, enabled: false };
                const isDisabled = !data.enabled || data.available <= 0;
                const selected = state.date === date ? 'is-selected' : '';

                grid.push(`
                    <button type="button" class="llsba-cell llsba-day ${selected}" data-date="${date}" ${isDisabled ? 'disabled' : ''}>
                        <span class="llsba-day-num">${day}</span>
                        <small>${llsbaData.labels.bookingsLabel}: ${data.booked}</small>
                        <small>${llsbaData.labels.availableLabel}: ${data.available}</small>
                    </button>
                `);
            }

            root.find('#llsba-calendar-grid').html(grid.join(''));
        }).fail(function () {
            setMessage('Error loading calendar', true);
        });
    }

    function loadSlots(date) {
        root.find('#llsba-selected-date').text(date);
        root.find('#llsba-slots').html(`<p>${llsbaData.labels.loading}</p>`);

        ajax('llsba_day_slots', { date }).done(function (response) {
            if (!response.success) {
                setMessage(response.data?.message || 'Error loading slots', true);
                return;
            }

            const slots = response.data.slots || [];
            if (!slots.length) {
                root.find('#llsba-slots').html(`<p>${llsbaData.labels.noSlots}</p>`);
                return;
            }

            const html = slots.map((slot) => {
                const selected = state.time === slot.time ? 'is-selected' : '';
                const disabled = !slot.available ? 'disabled' : '';
                return `<button type="button" class="llsba-slot ${selected}" data-time="${slot.time}" ${disabled}>${slot.time}</button>`;
            });

            root.find('#llsba-slots').html(html.join(''));
        }).fail(function () {
            setMessage('Error loading slots', true);
        });
    }

    root.on('click', '[data-next-step="2"]', function () {
        const contact = String(root.find('#llsba-contact').val() || '').trim();
        if (contact.length < 6) {
            setMessage('Please enter a valid contact number', true);
            return;
        }
        state.contact = contact;
        setMessage('');
        showStep(2);
    });

    root.on('click', '[data-next-step="3"]', function () {
        if (!state.date) {
            setMessage(llsbaData.labels.chooseDate, true);
            return;
        }
        setMessage('');
        loadSlots(state.date);
        showStep(3);
    });

    root.on('click', '[data-prev-step]', function () {
        const prev = Number($(this).data('prev-step'));
        setMessage('');
        showStep(prev);
    });

    root.on('click', '.llsba-day', function () {
        state.date = $(this).data('date');
        state.time = '';
        root.find('.llsba-day').removeClass('is-selected');
        $(this).addClass('is-selected');
    });

    root.on('click', '.llsba-slot', function () {
        if ($(this).is(':disabled')) {
            return;
        }
        state.time = $(this).data('time');
        root.find('.llsba-slot').removeClass('is-selected');
        $(this).addClass('is-selected');
    });

    root.find('#llsba-prev-month').on('click', function () {
        state.currentMonthDate = new Date(state.currentMonthDate.getFullYear(), state.currentMonthDate.getMonth() - 1, 1);
        renderCalendar(state.currentMonthDate);
    });

    root.find('#llsba-next-month').on('click', function () {
        state.currentMonthDate = new Date(state.currentMonthDate.getFullYear(), state.currentMonthDate.getMonth() + 1, 1);
        renderCalendar(state.currentMonthDate);
    });

    root.find('#llsba-submit-booking').on('click', function () {
        if (!state.time) {
            setMessage(llsbaData.labels.chooseSlot, true);
            return;
        }

        setMessage(llsbaData.labels.loading);

        ajax('llsba_submit_booking', {
            contact: state.contact,
            date: state.date,
            time: state.time,
        }).done(function (response) {
            if (!response.success) {
                setMessage(response.data?.message || 'Error saving booking', true);
                return;
            }

            setMessage(llsbaData.labels.bookingSaved, false);
            showStep(4);
            renderCalendar(state.currentMonthDate);
        }).fail(function () {
            setMessage('Error saving booking', true);
        });
    });

    renderCalendar(state.currentMonthDate);
})(jQuery);
