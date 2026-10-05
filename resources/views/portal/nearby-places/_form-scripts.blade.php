<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.addEventListener('invalid', function (e) {
            const invalidTabPane = e.target.closest('.tab-pane');
            if (invalidTabPane) {
                const tabId = invalidTabPane.id;
                const tabBtn = document.querySelector(`[data-bs-target="#${tabId}"]`);
                if (tabBtn && !tabBtn.classList.contains('active')) {
                    bootstrap.Tab.getOrCreateInstance(tabBtn).show();
                    setTimeout(() => { e.target.focus(); }, 150);
                }
            }
        }, true);
    });
</script>
