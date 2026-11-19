const board = document.querySelector('[data-board]');

if (board) {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const dialog = document.querySelector('[data-task-dialog]');
    const editForm = dialog.querySelector('[data-edit-form]');
    const deleteForm = dialog.querySelector('[data-delete-form]');
    const url = (template, id) => template.replace('__ID__', id);
    let dragged = null;
    let didDrag = false;

    const updateCounts = () => {
        board.querySelectorAll('[data-column]').forEach((column) => {
            column.querySelector('[data-count]').textContent = column.querySelectorAll('[data-task-id]').length;
        });
    };

    /** The card the dragged one should be inserted before, based on the pointer's vertical position. */
    const cardAfter = (zone, y) => {
        const cards = [...zone.querySelectorAll('[data-task-id]')].filter((card) => card !== dragged);

        return cards.find((card) => {
            const box = card.getBoundingClientRect();

            return y < box.top + box.height / 2;
        });
    };

    board.addEventListener('dragstart', (event) => {
        const card = event.target.closest('[data-task-id]');
        if (!card) {
            return;
        }
        dragged = card;
        didDrag = true;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', card.dataset.taskId);
        requestAnimationFrame(() => card.classList.add('opacity-40'));
    });

    board.addEventListener('dragover', (event) => {
        const column = event.target.closest('[data-column]');
        if (!dragged || !column) {
            return;
        }
        event.preventDefault();
        const zone = column.querySelector('[data-dropzone]');
        const before = cardAfter(zone, event.clientY);
        if (before) {
            zone.insertBefore(dragged, before);
        } else {
            zone.appendChild(dragged);
        }
        board.querySelectorAll('[data-column]').forEach((c) => c.classList.toggle('border-indigo-500', c === column));
    });

    board.addEventListener('dragend', async () => {
        if (!dragged) {
            return;
        }
        const card = dragged;
        dragged = null;
        card.classList.remove('opacity-40');
        board.querySelectorAll('[data-column]').forEach((c) => c.classList.remove('border-indigo-500'));
        setTimeout(() => (didDrag = false), 0);

        const column = card.closest('[data-column]');
        const position = [...column.querySelectorAll('[data-task-id]')].indexOf(card);
        updateCounts();

        const response = await fetch(url(board.dataset.moveUrl, card.dataset.taskId), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ status: column.dataset.column, position }),
        }).catch(() => null);

        if (!response || !response.ok) {
            alert('Could not save the move. Reloading the board.');
            location.reload();
        }
    });

    board.addEventListener('click', (event) => {
        const card = event.target.closest('[data-task-id]');
        if (!card || didDrag) {
            return;
        }
        const task = JSON.parse(card.dataset.task);
        editForm.action = url(editForm.dataset.updateUrl, task.id);
        deleteForm.action = url(deleteForm.dataset.deleteUrl, task.id);
        ['title', 'description', 'status', 'priority', 'due_on'].forEach((field) => {
            editForm.elements[field].value = task[field] ?? '';
        });
        dialog.showModal();
    });

    dialog.querySelector('[data-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
}
