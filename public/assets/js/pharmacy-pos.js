document.addEventListener('DOMContentLoaded', function () {
    const barcodeInput = document.getElementById('barcodeInput');
    const cartBody = document.getElementById('cartBody');

    if (!barcodeInput || !cartBody) {
        return;
    }

    barcodeInput.addEventListener('keydown', async function (e) {
        if (e.key !== 'Enter') {
            return;
        }

        e.preventDefault();

        const barcode = this.value.trim();

        if (!barcode) {
            return;
        }

        try {
            const response = await fetch('/public/api/pharmacy/barcode-search.php?barcode=' + encodeURIComponent(barcode));
            const result = await response.json();

            if (!result.success) {
                alert(result.message || 'Product not found');
                return;
            }

            const item = result.data;

            const row = document.createElement('tr');

            row.innerHTML = `
                <td>${item.product_name}</td>
                <td>1</td>
                <td>${item.stock_qty || 0}</td>
                <td>${item.sku}</td>
            `;

            cartBody.appendChild(row);
            barcodeInput.value = '';
        } catch (err) {
            console.error(err);
            alert('Failed to scan barcode');
        }
    });
});
