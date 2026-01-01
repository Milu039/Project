const modal = document.getElementById("incidentModal");
const modalType = document.getElementById("modalType");
const modalDescription = document.getElementById("modalDescription");
const modalLocation = document.getElementById("modalLocation");
const modalDate = document.getElementById("modalDate");
const modalVillager = document.getElementById("modalVillager");
const modalImage = document.getElementById("modalImage");
const approveBtn = document.getElementById("approveBtn");
const rejectBtn = document.getElementById("rejectBtn");
const closeBtn = document.querySelector(".modal .close");

document.querySelectorAll('.report-item').forEach(item => {
    item.addEventListener('click', () => {
        const id = item.dataset.id;
        const status = item.dataset.status;
        modalType.textContent = item.dataset.type;
        modalDescription.textContent = item.dataset.description;
        modalLocation.textContent = item.dataset.location;
        modalDate.textContent = item.dataset.date;
        modalVillager.textContent = item.dataset.villager;
        modalImage.src = item.dataset.image;
        modal.style.display = "block";

        if (status === 'in progress') {
            approveBtn.disabled = true;
            rejectBtn.disabled = true;
            approveBtn.style.backgroundColor = '#aaa';
            rejectBtn.style.backgroundColor = '#aaa';
        } else {
            approveBtn.disabled = false;
            rejectBtn.disabled = false;
            approveBtn.style.backgroundColor = 'green';
            rejectBtn.style.backgroundColor = 'red';
        }

        approveBtn.onclick = () => {
            updateIncidentStatus(id, 'approve');
        };
        rejectBtn.onclick = () => {
            updateIncidentStatus(id, 'reject');
        };
    });
});

closeBtn.onclick = () => { modal.style.display = "none"; };
window.onclick = (e) => { if (e.target == modal) modal.style.display = "none"; };

function updateIncidentStatus(id, action) {
    fetch('update_incident.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&action=${action}`
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (action === 'approve') {
                    // Update the status badge
                    const item = document.querySelector(`.report-item[data-id="${id}"]`);
                    item.querySelector('.urgency-badge').textContent = data.status;
                    item.dataset.status = data.status.toLowerCase();
                    // Disable buttons
                    approveBtn.disabled = true;
                    rejectBtn.disabled = true;
                    approveBtn.style.backgroundColor = '#aaa';
                    rejectBtn.style.backgroundColor = '#aaa';
                } else if (action === 'reject') {
                    // Remove the rejected report from the list
                    const item = document.querySelector(`.report-item[data-id="${id}"]`);
                    item.remove();
                }
                modal.style.display = 'none';
            } else {
                alert(data.message || 'Error updating incident');
            }
        })
        .catch(err => alert('Request failed'));
}