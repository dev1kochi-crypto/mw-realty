{{--
    Shared "Reset Password" modal + success toast, used from both the account
    show page and the accounts listing page. Each trigger element just needs
    class="trigger-reset-password" plus data-id/data-name — this partial is
    self-contained (its own IIFE, own `base` URL) so it works from either page.
--}}
@can('portal-accounts.edit')
<style>
    .password-modal-content { border: none; border-radius: 20px; box-shadow: 0 24px 60px rgba(28,35,64,0.22); }
    .password-modal-icon {
        width: 60px; height: 60px; border-radius: 50%; margin: 0 auto 1rem;
        display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
        background: rgba(4,161,204,0.12); color: var(--primary-color, #04a1cc);
    }
    .password-input-group { position: relative; }
    .password-input-group input { padding-right: 2.6rem; }
    .password-toggle-btn {
        position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%);
        border: none; background: none; color: #9aa1b5; padding: 0.25rem 0.4rem;
    }
    .password-toggle-btn:hover { color: #4b5065; }
    .pw-requirements { margin-top: 0.6rem; }
    .pw-requirement { display: flex; align-items: center; gap: 0.5rem; font-size: 0.78rem; color: #9aa1b5; padding: 0.15rem 0; transition: color 0.15s ease; }
    .pw-requirement i { width: 14px; font-size: 0.75rem; }
    .pw-requirement.is-met { color: #0f9d58; }
    .pw-requirement.is-met i:before { content: "\f058"; }

    .portal-toast-stack { position: fixed; top: 1.25rem; right: 1.25rem; z-index: 2000; display: flex; flex-direction: column; gap: 0.6rem; }
    .portal-toast {
        min-width: 280px; max-width: 360px; background: #fff; border-radius: 12px;
        box-shadow: 0 16px 40px rgba(28,35,64,0.18); padding: 0.85rem 1rem;
        display: flex; align-items: flex-start; gap: 0.7rem;
        border-left: 4px solid #0f9d58; opacity: 0; transform: translateX(20px);
        transition: opacity 0.25s ease, transform 0.25s ease;
    }
    .portal-toast.is-visible { opacity: 1; transform: translateX(0); }
    .portal-toast.is-error { border-left-color: #dc3545; }
    .portal-toast-icon { color: #0f9d58; font-size: 1.1rem; margin-top: 0.1rem; }
    .portal-toast.is-error .portal-toast-icon { color: #dc3545; }
    .portal-toast-body { font-size: 0.85rem; color: #1c2340; }
    .portal-toast-close { margin-left: auto; background: none; border: none; color: #9aa1b5; padding: 0; }
</style>

<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content password-modal-content">
            <form id="resetPasswordForm">
                <div class="modal-body text-center p-4 pb-2">
                    <div class="password-modal-icon"><i class="fas fa-key"></i></div>
                    <h5 class="fw-bold mb-2">Reset Password</h5>
                    <p class="text-muted mb-3" style="font-size: 0.85rem;">
                        Set a new password for <strong id="resetPasswordAccountName">this account</strong>. They'll need to use it on their next login.
                    </p>

                    <div class="text-start mb-3">
                        <label class="form-label fw-bold small">New Password</label>
                        <div class="password-input-group">
                            <input type="password" name="password" id="resetPasswordInput" class="form-control" autocomplete="new-password" required>
                            <button type="button" class="password-toggle-btn toggle-visibility" data-target="resetPasswordInput"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="text-start mb-1">
                        <label class="form-label fw-bold small">Confirm Password</label>
                        <div class="password-input-group">
                            <input type="password" name="password_confirmation" id="resetPasswordConfirmInput" class="form-control" autocomplete="new-password" required>
                            <button type="button" class="password-toggle-btn toggle-visibility" data-target="resetPasswordConfirmInput"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>

                    <div class="pw-requirements text-start">
                        <div class="pw-requirement" data-rule="length"><i class="fas fa-circle"></i> At least 8 characters</div>
                        <div class="pw-requirement" data-rule="mixed"><i class="fas fa-circle"></i> Contains a letter and a number</div>
                        <div class="pw-requirement" data-rule="match"><i class="fas fa-circle"></i> Passwords match</div>
                    </div>

                    <div class="reset-password-error text-danger small mt-2 d-none"></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="resetPasswordSubmitBtn" disabled>Save New Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="portal-toast-stack" id="portalToastStack"></div>

<script>
(function () {
    const base = "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/portal-accounts/";
    const modalEl = document.getElementById('resetPasswordModal');
    if (!modalEl) return;

    let targetId = null;

    function showToast(message, isError) {
        const stack = document.getElementById('portalToastStack');
        const toast = document.createElement('div');
        toast.className = 'portal-toast' + (isError ? ' is-error' : '');
        toast.innerHTML = '<i class="fas ' + (isError ? 'fa-exclamation-circle' : 'fa-check-circle') + ' portal-toast-icon"></i>'
            + '<span class="portal-toast-body">' + message + '</span>'
            + '<button type="button" class="portal-toast-close"><i class="fas fa-times"></i></button>';
        stack.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('is-visible'));

        const remove = () => { toast.classList.remove('is-visible'); setTimeout(() => toast.remove(), 250); };
        toast.querySelector('.portal-toast-close').addEventListener('click', remove);
        setTimeout(remove, 4000);
    }

    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('.trigger-reset-password');
        if (!trigger) return;
        targetId = trigger.dataset.id;
        document.getElementById('resetPasswordAccountName').textContent = trigger.dataset.name || 'this account';
        resetForm.reset();
        updateRequirements();
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });

    modalEl.querySelectorAll('.toggle-visibility').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = document.getElementById(btn.dataset.target);
            const icon = btn.querySelector('i');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !show);
            icon.classList.toggle('fa-eye-slash', show);
        });
    });

    const resetForm = document.getElementById('resetPasswordForm');
    const pwInput = document.getElementById('resetPasswordInput');
    const pwConfirm = document.getElementById('resetPasswordConfirmInput');
    const submitBtn = document.getElementById('resetPasswordSubmitBtn');

    function updateRequirements() {
        const pw = pwInput.value;
        const confirm = pwConfirm.value;
        const rules = {
            length: pw.length >= 8,
            mixed: /[A-Za-z]/.test(pw) && /[0-9]/.test(pw),
            match: pw.length > 0 && pw === confirm,
        };

        Object.entries(rules).forEach(([rule, met]) => {
            modalEl.querySelector('.pw-requirement[data-rule="' + rule + '"]').classList.toggle('is-met', met);
        });

        const allMet = Object.values(rules).every(Boolean);
        submitBtn.disabled = !allMet;
        return allMet;
    }

    pwInput.addEventListener('input', updateRequirements);
    pwConfirm.addEventListener('input', updateRequirements);

    resetForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!updateRequirements() || !targetId) return;

        const errorBox = resetForm.querySelector('.reset-password-error');
        errorBox.classList.add('d-none');

        const formData = new FormData(resetForm);
        formData.append('_token', '{{ csrf_token() }}');

        fetch(base + targetId + '/reset-password', { method: 'POST', body: formData })
            .then(async res => {
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    const message = data.errors ? Object.values(data.errors).flat().join(' ') : 'Could not reset password.';
                    throw new Error(message);
                }
                return res.json();
            })
            .then(() => {
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                resetForm.reset();
                submitBtn.disabled = true;
                showToast('Password reset successfully.', false);
            })
            .catch(err => {
                errorBox.textContent = err.message;
                errorBox.classList.remove('d-none');
                if (window.restoreSubmitButtons) window.restoreSubmitButtons(resetForm);
            });
    });
})();
</script>
@endcan
