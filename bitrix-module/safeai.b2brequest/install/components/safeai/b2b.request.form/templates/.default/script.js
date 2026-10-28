(function () {
    'use strict';

    document.querySelectorAll('.safeai-b2b-form:not([data-initialized])').forEach(function (form) {
        form.dataset.initialized = '1';
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            const status = form.querySelector('[role="status"]');
            const button = form.querySelector('button[type="submit"]');
            const source = new FormData(form);
            const payload = new FormData();

            ['company', 'contactName', 'email', 'phone', 'comment', 'consent', 'website'].forEach(function (name) {
                payload.append('fields[' + name + ']', source.get(name) || '');
            });
            payload.append('document', source.get('document'));

            button.disabled = true;
            status.textContent = 'Отправляем…';
            try {
                const response = await BX.ajax.runComponentAction('safeai:b2b.request.form', 'submit', {
                    mode: 'class',
                    data: payload
                });
                status.textContent = 'Заявка принята: ' + response.data.requestId;
                form.reset();
            } catch (error) {
                const message = error && error.errors && error.errors[0] && error.errors[0].message;
                status.textContent = message || 'Не удалось отправить заявку.';
            } finally {
                button.disabled = false;
            }
        });
    });
})();

