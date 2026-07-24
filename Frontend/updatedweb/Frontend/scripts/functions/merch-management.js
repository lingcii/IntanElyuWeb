const backendUrl = 'http://localhost:8000';
let allInventory = [];
let allReservations = [];

function switchTab(tab) {
    document.getElementById('view-inventory').style.display = 'none';
    document.getElementById('view-reservations').style.display = 'none';
    document.getElementById('tab-inventory').classList.remove('active');
    document.getElementById('tab-reservations').classList.remove('active');
    
    document.getElementById(`view-${tab}`).style.display = 'block';
    document.getElementById(`tab-${tab}`).classList.add('active');
    
    if (tab === 'inventory') fetchInventory();
    if (tab === 'reservations') fetchReservations();
}

async function fetchInventory() {
    try {
        const response = await fetch(`${backendUrl}/api/lupto/merch/inventory`, {
            headers: {
                'Authorization': 'Bearer ' + sessionStorage.getItem('token'),
                'ngrok-skip-browser-warning': 'true'
            }
        });
        const result = await response.json();
        if (response.ok) {
            allInventory = result.data;
            renderInventory();
        }
    } catch (error) {
        console.error('Error fetching inventory', error);
    }
}

function renderInventory() {
    const list = document.getElementById('inventory-list');
    list.innerHTML = '';
    if (allInventory.length === 0) {
        list.innerHTML = '<tr><td colspan="6" style="text-align:center;">No items found.</td></tr>';
        return;
    }
    allInventory.forEach(item => {
        const imgUrl = (item.image && !item.image.startsWith('http')) ? backendUrl + '/' + item.image : item.image || 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=400&q=80';
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><img src="${imgUrl}" style="width:40px; height:40px; object-fit:cover; border-radius:4px;"></td>
            <td><strong>${item.title}</strong>${item.badge ? ` <span class="badge-status badge-pending">${item.badge}</span>` : ''}</td>
            <td>${item.category}</td>
            <td>${item.price_xp}</td>
            <td>${item.stock}</td>
            <td>
                <button class="merch-btn merch-btn-primary" onclick='editMerch(${JSON.stringify(item)})'><i class="fas fa-edit"></i></button>
                <button class="merch-btn merch-btn-danger" onclick="deleteMerch(${item.id})"><i class="fas fa-trash"></i></button>
            </td>
        `;
        list.appendChild(tr);
    });
}

function openAddMerchModal() {
    document.getElementById('merch-id').value = '';
    document.getElementById('merch-title').value = '';
    document.getElementById('merch-category').value = 'Apparel';
    document.getElementById('merch-badge').value = '';
    document.getElementById('merch-price').value = '';
    document.getElementById('merch-stock').value = '';
    document.getElementById('merch-image').value = '';
    document.getElementById('modal-title').textContent = 'Add Merchandise';
    document.getElementById('merch-modal').style.display = 'flex';
}

function editMerch(item) {
    document.getElementById('merch-id').value = item.id;
    document.getElementById('merch-title').value = item.title;
    document.getElementById('merch-category').value = item.category;
    document.getElementById('merch-badge').value = item.badge || '';
    document.getElementById('merch-price').value = item.price_xp;
    document.getElementById('merch-stock').value = item.stock;
    document.getElementById('merch-image').value = item.image || '';
    document.getElementById('modal-title').textContent = 'Edit Merchandise';
    document.getElementById('merch-modal').style.display = 'flex';
}

function closeMerchModal() {
    document.getElementById('merch-modal').style.display = 'none';
}

async function saveMerch(e) {
    e.preventDefault();
    const id = document.getElementById('merch-id').value;
    const data = {
        title: document.getElementById('merch-title').value,
        category: document.getElementById('merch-category').value,
        badge: document.getElementById('merch-badge').value,
        price_xp: parseInt(document.getElementById('merch-price').value),
        stock: parseInt(document.getElementById('merch-stock').value),
        image: document.getElementById('merch-image').value
    };
    if (id) data.id = id;

    try {
        const response = await fetch(`${backendUrl}/api/lupto/merch/inventory`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + sessionStorage.getItem('token'),
                'ngrok-skip-browser-warning': 'true'
            },
            body: JSON.stringify(data)
        });
        if (response.ok) {
            closeMerchModal();
            fetchInventory();
        } else {
            alert('Failed to save item');
        }
    } catch(err) {
        console.error(err);
        alert('An error occurred');
    }
}

async function deleteMerch(id) {
    if(!confirm('Are you sure you want to delete this item?')) return;
    try {
        const response = await fetch(`${backendUrl}/api/lupto/merch/inventory/${id}`, {
            method: 'DELETE',
            headers: {
                'Authorization': 'Bearer ' + sessionStorage.getItem('token'),
                'ngrok-skip-browser-warning': 'true'
            }
        });
        if (response.ok) {
            fetchInventory();
        }
    } catch(err) {
        console.error(err);
    }
}


async function fetchReservations() {
    try {
        const response = await fetch(`${backendUrl}/api/lupto/merch/reservations`, {
            headers: {
                'Authorization': 'Bearer ' + sessionStorage.getItem('token'),
                'ngrok-skip-browser-warning': 'true'
            }
        });
        const result = await response.json();
        if (response.ok) {
            allReservations = result.data;
            renderReservations();
        }
    } catch (error) {
        console.error('Error fetching reservations', error);
    }
}

function renderReservations() {
    const list = document.getElementById('reservations-list');
    list.innerHTML = '';
    if (allReservations.length === 0) {
        list.innerHTML = '<tr><td colspan="6" style="text-align:center;">No reservations found.</td></tr>';
        return;
    }
    allReservations.forEach(res => {
        const date = new Date(res.created_at).toLocaleDateString();
        const tr = document.createElement('tr');
        
        let actionBtn = '';
        if (res.status === 'pending') {
            actionBtn = `<button class="merch-btn merch-btn-success" onclick="claimReservation(${res.id})">Mark Claimed</button>`;
        }
        
        tr.innerHTML = `
            <td>${date}</td>
            <td>${res.user ? res.user.name : 'Unknown User'}</td>
            <td>${res.merchandise ? res.merchandise.title : 'Deleted Item'}</td>
            <td>${res.merchandise ? res.merchandise.price_xp : 0}</td>
            <td><span class="badge-status badge-${res.status}">${res.status.toUpperCase()}</span></td>
            <td>${actionBtn}</td>
        `;
        list.appendChild(tr);
    });
}

async function claimReservation(id) {
    if(!confirm('Mark this reservation as claimed by the tourist?')) return;
    try {
        const response = await fetch(`${backendUrl}/api/lupto/merch/reservations/${id}/claim`, {
            method: 'PATCH',
            headers: {
                'Authorization': 'Bearer ' + sessionStorage.getItem('token'),
                'ngrok-skip-browser-warning': 'true'
            }
        });
        if (response.ok) {
            fetchReservations();
        }
    } catch(err) {
        console.error(err);
    }
}

// Initial Load
document.addEventListener('DOMContentLoaded', fetchInventory);
