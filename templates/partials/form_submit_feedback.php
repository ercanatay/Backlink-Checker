<script>
  (() => {
    const appendLoadingSuffix = (label) => /\.\.\.$/.test(label) ? label : `${label}...`;

    const setLoadingState = (form) => {
      const submitter = form.__lastSubmitter && form.contains(form.__lastSubmitter)
        ? form.__lastSubmitter
        : form.querySelector('button[type="submit"], input[type="submit"]');
      if (!submitter) {
        return;
      }

      submitter.disabled = true;
      submitter.setAttribute('aria-disabled', 'true');
      submitter.style.opacity = '0.7';
      submitter.style.cursor = 'wait';

      if (submitter.tagName === 'BUTTON') {
        submitter.textContent = appendLoadingSuffix((submitter.textContent || '').trim());
        return;
      }

      submitter.value = appendLoadingSuffix((submitter.value || '').trim());
    };

    document.addEventListener('click', (event) => {
      const submitter = event.target.closest('button[type="submit"], input[type="submit"]');
      if (submitter && submitter.form) {
        submitter.form.__lastSubmitter = submitter;
      }
    });

    document.addEventListener('submit', (event) => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement)) {
        return;
      }

      const method = (form.getAttribute('method') || 'get').toLowerCase();
      if (method !== 'post') {
        return;
      }

      if (form.dataset.submitting === 'true') {
        event.preventDefault();
        return;
      }

      if (event.defaultPrevented) {
        return;
      }

      form.dataset.submitting = 'true';
      form.setAttribute('aria-busy', 'true');
      setLoadingState(form);
    }, true);
  })();
</script>
