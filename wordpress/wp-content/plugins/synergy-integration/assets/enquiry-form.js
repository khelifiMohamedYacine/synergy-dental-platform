/* Progressive enhancement for [synergy_enquiry_form]. The form works without this script. */
(function () {
  'use strict';

  var form = document.querySelector('.sd-form');
  var summary = document.getElementById('sd-error-summary');
  var confirmation = document.getElementById('sd-confirmation');
  if (summary) summary.focus();
  if (confirmation) confirmation.focus();

  // Copy-reference button (shown only when the clipboard API exists).
  document.querySelectorAll('[data-copy]').forEach(function (button) {
    if (!navigator.clipboard) return;
    button.hidden = false;
    button.addEventListener('click', function () {
      var text = document.querySelector(button.getAttribute('data-copy')).textContent;
      navigator.clipboard.writeText(text).then(function () {
        button.textContent = 'Copied';
      });
    });
  });

  if (!form) return;

  // Only offer treatments available at the chosen clinic.
  var offered = {};
  try {
    offered = JSON.parse(form.getAttribute('data-offered') || '{}');
  } catch (e) {
    offered = {};
  }
  var clinic = form.querySelector('[name="clinic_slug"]');
  var treatment = form.querySelector('[name="treatment_slug"]');
  function filterTreatments() {
    var allowed = offered[clinic.value];
    Array.prototype.forEach.call(treatment.options, function (option) {
      var ok = !option.value || !allowed || allowed.indexOf(option.value) !== -1;
      option.hidden = !ok;
      option.disabled = !ok;
      if (!ok && option.selected) treatment.value = '';
    });
  }
  clinic.addEventListener('change', filterTreatments);
  filterTreatments();

  // Date fields only matter for appointment requests.
  var appointmentOnly = form.querySelector('[data-appointment-only]');
  function toggleType() {
    var checked = form.querySelector('[name="enquiry_type"]:checked');
    var isAppointment = !checked || checked.value === 'APPOINTMENT';
    appointmentOnly.hidden = !isAppointment;
    form.querySelector('[name="preferred_date"]').required = isAppointment;
  }
  form.querySelectorAll('[name="enquiry_type"]').forEach(function (radio) {
    radio.addEventListener('change', toggleType);
  });
  toggleType();

  // Character counter.
  var message = form.querySelector('[name="message"]');
  var count = document.getElementById('sd-message-count');
  var base = count.textContent;
  function updateCount() {
    count.textContent = base + ' ' + message.value.length + '/2000';
  }
  message.addEventListener('input', updateCount);
  updateCount();

  // Prevent double submits (the idempotency key also protects the server side).
  form.addEventListener('submit', function () {
    var button = form.querySelector('[type="submit"]');
    button.disabled = true;
    button.textContent = button.getAttribute('data-sending');
  });
})();
