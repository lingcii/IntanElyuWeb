/* ============================================================
   HEADER — Dropdowns, date picker, period selector
   ============================================================ */
(function () {
    'use strict';

    /* ---- Notification dropdown ---- */
    const notifBtn      = document.getElementById('notifBtn');
    const notifDropdown = document.getElementById('notifDropdown');

    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            notifDropdown.classList.toggle('open');
            // Close user dropdown
            userDropdown && userDropdown.classList.remove('open');
            userControl  && userControl.classList.remove('open');
        });
    }

    /* ---- User dropdown ---- */
    const userControl  = document.getElementById('userControl');
    const userDropdown = document.getElementById('userDropdown');

    if (userControl && userDropdown) {
        userControl.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = userDropdown.classList.contains('open');
            userDropdown.classList.toggle('open', !isOpen);
            userControl.classList.toggle('open', !isOpen);
            // Close notification dropdown
            notifDropdown && notifDropdown.classList.remove('open');
        });
    }

    /* ---- Close all dropdowns on outside click ---- */
    document.addEventListener('click', function () {
        notifDropdown && notifDropdown.classList.remove('open');
        userDropdown  && userDropdown.classList.remove('open');
        userControl   && userControl.classList.remove('open');
    });

    /* ---- Reporting period change (demo: refresh page with param) ---- */
    const periodSelect = document.getElementById('reportingPeriod');
    if (periodSelect) {
        periodSelect.addEventListener('change', function () {
            // Emit custom event so page scripts can react without full reload
            document.dispatchEvent(new CustomEvent('cpdo:periodChange', {
                detail: { period: this.value }
            }));
        });
    }

    /* ---- Date change ---- */
    const reportDate = document.getElementById('reportDate');
    if (reportDate) {
        reportDate.addEventListener('change', function () {
            document.dispatchEvent(new CustomEvent('cpdo:dateChange', {
                detail: { date: this.value }
            }));
        });
    }
}());
