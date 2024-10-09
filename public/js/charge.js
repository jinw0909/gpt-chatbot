document.addEventListener('DOMContentLoaded', async function() {
    let rejectBtn = document.getElementById('reject-btn');
    rejectBtn.addEventListener('click', function() {
        let chargeModal = document.getElementById('charge-modal');
        chargeModal.style.display = 'none';
    });
    await fetchUserCharge();
});

document.getElementById('add-button').addEventListener('click', async function() {
    console.log('modal clicked');
    let chargeModal = document.getElementById('charge-modal');
    chargeModal.style.display = 'flex';
    // await addUserCharge();
});

document.getElementById('confirm-btn').addEventListener('click', async function() {
   await addUserCharge();
   let chargeModal = document.getElementById('charge-modal');
   chargeModal.style.display = 'none';
   alert("charge added for user 1");
});

async function addUserCharge() {
    try {
        const response = await fetch('/user/1/add-charge', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ amount: 5 }) // Add 10 dollars
        });

        if (response.ok) {
            const data = await response.json();
            console.log('After:', data.after);
            // Fetch the updated token count
            await fetchUserCharge();
        } else {
            console.error('Error adding charge:', response.statusText);
        }
    } catch (error) {
        console.error('Error:', error);
    }
}
async function fetchUserCharge() {
    try {
        const response = await fetch('/user/1/get-charge');
        if (response.ok) {
            const chargeData = await response.json();
            console.log('Current Charge:', chargeData.charge);
            const formattedCharge = parseFloat(chargeData.charge).toFixed(3);
            // Update token display
            document.querySelector('.remaining').textContent = `$${formattedCharge}`;
        } else {
            console.error('Error fetching tokens:', response.statusText);
        }
    } catch (error) {
        console.error('Error:', error);
    }
}


