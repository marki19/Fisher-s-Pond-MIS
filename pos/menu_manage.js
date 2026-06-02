// Global Functions
function toggleAddStock(selectElem) {
    const selectedOption = selectElem.options[selectElem.selectedIndex];
    const tracked = parseInt(selectedOption.getAttribute('data-tracked') || 0, 10);
    const stockInput = document.getElementById('add_StockQty');
    
    if (tracked === 1) {
        stockInput.disabled = false;
    } else {
        stockInput.disabled = true;
        stockInput.value = '';
    }
}

function closeStockAdjustModal() {
    const modal = document.getElementById('stockAdjustModal');
    modal.classList.add('hidden');
    modal.style.display = 'none';
}

function filterMenuTable() {
    const filterValue = document.getElementById('menuFilter').value;
    const categoryValue = document.getElementById('categoryFilter').value;
    const searchValue = document.getElementById('menuSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#menuTable tbody tr.menu-row');

    rows.forEach(row => {
        const status = row.getAttribute('data-status');
        const category = row.getAttribute('data-category');
        const rowText = row.innerText.toLowerCase();

        const matchesStatus = (filterValue === 'all') || (filterValue === status);
        const matchesCategory = (categoryValue === 'all') || (categoryValue === category);
        const matchesSearch = rowText.includes(searchValue);

        if (matchesStatus && matchesCategory && matchesSearch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function showToast(message, type = 'default') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerText = message;
    toast.style.cursor = 'pointer';
    toast.addEventListener('click', () => { 
        toast.style.opacity = '0'; 
        setTimeout(() => toast.remove(), 200); 
    });

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

document.addEventListener("DOMContentLoaded", () => {
    // Edit Item Event Listeners
    document.querySelectorAll('.btn-edit-item').forEach(button => {
        button.addEventListener('click', (e) => {
            const btn = e.currentTarget;
            document.getElementById('edit_ItemID').value = btn.getAttribute('data-item-id');
            document.getElementById('edit_ItemName').value = btn.getAttribute('data-item-name');
            document.getElementById('edit_CategoryID').value = btn.getAttribute('data-category-id');
            document.getElementById('edit_Price').value = btn.getAttribute('data-price');
            document.getElementById('edit_IsAvailable').value = btn.getAttribute('data-is-available');
            
            const stockInput = document.getElementById('edit_StockQty');
            const isTracked = parseInt(btn.getAttribute('data-is-tracked') || '0', 10);
            if (isTracked === 1) {
                stockInput.disabled = false;
                stockInput.value = parseInt(btn.getAttribute('data-stock-qty') || '0', 10);
            } else {
                stockInput.disabled = true;
                stockInput.value = '';
            }

            const modal = document.getElementById('editModal');
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        });
    });

    // Edit Category Event Listeners
    document.querySelectorAll('.btn-edit-category').forEach(button => {
        button.addEventListener('click', (e) => {
            const btn = e.currentTarget;
            document.getElementById('edit_CatID').value = btn.getAttribute('data-cat-id');
            document.getElementById('edit_CatName').value = btn.getAttribute('data-cat-name');
            document.getElementById('edit_CatTracked').checked = (btn.getAttribute('data-cat-tracked') == '1');
            
            const modal = document.getElementById('editCatModal');
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        });
    });

    // Adjust Stock Event Listeners
    document.querySelectorAll('.btn-adjust-stock').forEach(button => {
        button.addEventListener('click', (e) => {
            const btn = e.currentTarget;
            document.getElementById('stockAdjustItemID').value = btn.getAttribute('data-item-id');
            document.getElementById('stockAdjustItemName').value = btn.getAttribute('data-item-name');
            document.getElementById('stockAdjustCurrentStock').value = Number(btn.getAttribute('data-stock-qty') || 0).toFixed(2);
            
            const modal = document.getElementById('stockAdjustModal');
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        });
    });

    // Image Previews
    function setupImagePreview(inputId, messageId, previewId) {
        const input = document.getElementById(inputId);
        const message = document.getElementById(messageId);
        const preview = document.getElementById(previewId);

        if (!input) return;

        input.addEventListener("change", function () {
            const file = this.files[0];
            message.textContent = "";
            preview.innerHTML = "";

            if (!file) return;

            const reader = new FileReader();
            reader.onload = e => {
                preview.innerHTML = `<img src="${e.target.result}" style="max-width: 80px; max-height: 80px; border-radius: 4px; border: 1px solid var(--border-color); object-fit: cover;">`;
            };
            reader.readAsDataURL(file);

            const allowed = ["image/jpeg", "image/png", "image/gif", "image/webp"];
            const maxSize = 5 * 1024 * 1024;

            if (!allowed.includes(file.type)) {
                message.textContent = "Invalid file type";
                message.style.color = "red";
                this.value = "";
                preview.innerHTML = "";
                return;
            }

            if (file.size > maxSize) {
                message.textContent = "File too large (max 5MB)";
                message.style.color = "red";
                this.value = "";
                preview.innerHTML = "";
                return;
            }

            message.textContent = "Image looks valid";
            message.style.color = "green";
        });
    }

    setupImagePreview("addImageInput", "addMessage", "addPreview");
    setupImagePreview("editImageInput", "editMessage", "editPreview");
});
