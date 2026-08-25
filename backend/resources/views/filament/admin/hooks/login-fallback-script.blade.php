<script>
(function () {
    const form = document.getElementById('form');
    if (! form) {
        return;
    }

    const errorBox = document.createElement('p');
    errorBox.setAttribute('role', 'alert');
    errorBox.style.cssText = 'color:#dc2626;font-size:0.875rem;margin-top:0.75rem;display:none;';
    form.appendChild(errorBox);

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();

        errorBox.style.display = 'none';

        const email = document.getElementById('form.email')?.value?.trim() ?? '';
        const password = document.getElementById('form.password')?.value ?? '';
        const remember = document.getElementById('form.remember')?.checked ?? false;
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        if (! email || ! password) {
            errorBox.textContent = 'Email and password are required.';
            errorBox.style.display = 'block';

            return;
        }

        const body = new FormData();
        body.append('email', email);
        body.append('password', password);
        body.append('remember', remember ? '1' : '0');
        body.append('_token', token);

        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
        }

        try {
            const response = await fetch(@json(route('admin.session-login')), {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const payload = await response.json().catch(() => ({}));

            if (response.ok) {
                window.location.href = payload.redirect ?? '/admin';

                return;
            }

            const message = payload.message
                ?? payload.errors?.email?.[0]
                ?? 'Login failed. Try admin@checkoutplatform.test with password "password".';
            errorBox.textContent = message;
            errorBox.style.display = 'block';
        } catch (error) {
            errorBox.textContent = 'Network error while signing in. Please try again.';
            errorBox.style.display = 'block';
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    }, true);
})();
</script>
