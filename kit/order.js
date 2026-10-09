(function () {
    'use strict';
    const form = document.getElementById('kitForm');
    if (!form) return;
    const delivery = document.getElementById('deliveryFields');
    const output = document.getElementById('runningLines');
    const money = pence => '£' + (pence / 100).toFixed(2);

    // One order line per catalogue item. Older server-rendered extra lines are
    // removed here so existing markup degrades safely while the server remains
    // authoritative about the submitted product.
    form.querySelectorAll('.kitProduct').forEach(product => {
        const lines = product.querySelectorAll('.kitLine');
        lines.forEach((line, index) => {
            if (index > 0) line.remove();
        });
        const addButton = product.querySelector('.kitAddLine');
        if (addButton) addButton.remove();
        const firstLine = product.querySelector('.kitLine');
        if (firstLine) {
            firstLine.querySelectorAll('label').forEach(label => {
                label.textContent = label.textContent
                    .replace(/ \(line 1\)/, '')
                    .replace(/ \(line 1, optional\)/, ' (optional)');
            });
        }
    });

    function update() {
        const delivering = form.elements.fulfilment.value === 'delivery';
        delivery.hidden = !delivering;
        delivery.querySelectorAll('input').forEach(control => { control.required = delivering && control.dataset.deliveryRequired === 'yes'; });
        let total = delivering ? Number(document.getElementById('runningOrder').dataset.delivery) : 0;
        let freeApplied = false;
        const claim = document.getElementById('claimFree').checked;
        output.replaceChildren();
        form.querySelectorAll('.kitProduct').forEach(product => {
            const line = product.querySelector('.kitLine');
            if (!line) return;
            const quantity = Number(line.querySelector('input[type="number"]').value);
            const select = line.querySelector('select');
            select.required = quantity > 0;
            if (!Number.isInteger(quantity) || quantity < 1) return;
            const initials = line.querySelector('input[name$="[initials]"]');
            const letters = initials ? initials.value.trim() : '';
            let cost = quantity * (Number(product.dataset.price) + (letters ? Number(product.dataset.initialsPrice) : 0));
            const free = claim && !freeApplied && product.dataset.free === 'yes';
            if (free) { cost -= Number(product.dataset.price); freeApplied = true; }
            total += cost;
            const p = document.createElement('p');
            p.textContent = product.dataset.name + ' · Size: ' + (select.value || 'Choose size') + ' · Qty: ' + quantity + (letters ? ' · Initials: ' + letters : '') + (free ? ' · New member free shirt – £0.00 (one base unit)' : '') + ' · Line total: ' + money(cost);
            output.append(p);
        });
        if (claim && !freeApplied) { const p = document.createElement('p'); p.textContent = 'Please add an eligible club shirt to claim your new-member free shirt.'; output.append(p); }
        const p = document.createElement('p');
        p.textContent = (delivering ? 'Delivery' : 'Club collection') + ' · Estimated order total: ' + money(total) + '. The server will confirm the total on review.';
        output.append(p);
    }
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();
}());
