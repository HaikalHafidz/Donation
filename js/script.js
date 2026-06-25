class DonationSystem {
    constructor() {
        this.baseUrl = window.location.origin + '/donasi-bersama';
        this.selectedNominal = null;
        this.selectedPayment = null;
        this.init();
    }
    
    init() {
        this.initEventListeners();
        this.loadDonationHistory();
    }
    
    initEventListeners() {
        document.querySelectorAll('.nominal-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const nominal = parseInt(e.target.dataset.nominal);
                this.selectNominal(nominal, e.target);
            });
        });

      document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', (e) => {
                const methodEl = e.currentTarget;
                const paymentMethod = methodEl.dataset.method;
                this.selectPayment(paymentMethod, methodEl);
            });
        });
        
        // Form submit
        document.getElementById('donationForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.processDonation();
        });
        
        // Nominal manual input
        document.getElementById('nominal')?.addEventListener('input', (e) => {
            this.clearNominalSelection();
        });
    }
    
    selectNominal(nominal, element) {
        this.selectedNominal = nominal;
        document.getElementById('nominal').value = nominal;
        
        // Update UI
        document.querySelectorAll('.nominal-btn').forEach(btn => {
            btn.classList.remove('selected');
        });
        element.classList.add('selected');
    }
    
    clearNominalSelection() {
        this.selectedNominal = null;
        document.querySelectorAll('.nominal-btn').forEach(btn => {
            btn.classList.remove('selected');
        });
    }
    
    selectPayment(method, element) {
        this.selectedPayment = method;
        
        // Update UI
        document.querySelectorAll('.payment-method').forEach(m => {
            m.classList.remove('selected');
        });
        element.classList.add('selected');
    }
    
    async processDonation() {
        // Get form data
        const data = {
            donor_name: document.getElementById('nama').value,
            donor_email: document.getElementById('email').value,
            donor_phone: document.getElementById('phone').value,
            donation_type: 'uang', // For now only uang
            amount: parseInt(document.getElementById('nominal').value),
            payment_method: this.selectedPayment,
            description: document.getElementById('pesan')?.value || ''
        };
        
        // Validate
        if (!this.validateData(data)) {
            return;
        }
        
        // Show loading
        this.showLoading(true);
        
        try {
            const response = await fetch(`${this.baseUrl}/api/donasi.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.status === 'success') {
                // Save to session storage
                sessionStorage.setItem('last_donation', JSON.stringify(result.data));
                
                // Show success message
                this.showInvoice(result.data);
                
                // Redirect to payment simulation
                setTimeout(() => {
                    window.location.href = `${this.baseUrl}/proses_donasi.php?id=${result.data.donation_id}`;
                }, 2000);
            } else {
                throw new Error(result.message);
            }
            
        } catch (error) {
            alert('Error: ' + error.message);
            this.showLoading(false);
        }
    }
    
    validateData(data) {
        if (!data.donor_name || data.donor_name.length < 3) {
            alert('Nama lengkap minimal 3 karakter');
            return false;
        }
        
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(data.donor_email)) {
            alert('Email tidak valid');
            return false;
        }
        
        if (!data.amount || data.amount < 1000) {
            alert('Nominal donasi minimal Rp 1.000');
            return false;
        }
        
        if (!this.selectedPayment) {
            alert('Pilih metode pembayaran');
            return false;
        }
        
        return true;
    }
    
    showLoading(show) {
        const loading = document.getElementById('loading');
        const submitBtn = document.getElementById('submitBtn');
        
        if (show) {
            loading.classList.add('active');
            submitBtn.disabled = true;
        } else {
            loading.classList.remove('active');
            submitBtn.disabled = false;
        }
    }
    
    showInvoice(data) {
        const invoice = document.getElementById('invoice');
        if (!invoice) return;
        
        document.getElementById('invoice-nama').textContent = data.donor_name;
        document.getElementById('invoice-email').textContent = data.donor_email;
        document.getElementById('invoice-nominal').textContent = 
            `Rp ${parseInt(data.amount).toLocaleString('id-ID')}`;
        document.getElementById('invoice-id').textContent = data.donation_id;
        document.getElementById('invoice-waktu').textContent = 
            new Date().toLocaleString('id-ID');
        document.getElementById('invoice-total').textContent = 
            `Rp ${parseInt(data.amount).toLocaleString('id-ID')}`;
        
        invoice.style.display = 'block';
        invoice.scrollIntoView({ behavior: 'smooth' });
    }
    
    async loadDonationHistory() {
        const email = localStorage.getItem('donor_email');
        if (!email) return;
        
        try {
            const response = await fetch(`${this.baseUrl}/api/donasi.php?email=${email}`);
            const result = await response.json();
            
            if (result.status === 'success') {
                this.renderHistory(result.data.donations);
            }
        } catch (error) {
            console.error('Failed to load history:', error);
        }
    }
    
    renderHistory(donations) {
        const historyContainer = document.getElementById('donation-history');
        if (!historyContainer) return;
        
        if (donations.length === 0) {
            historyContainer.innerHTML = '<p>Belum ada riwayat donasi</p>';
            return;
        }
        
        let html = '<h3>Riwayat Donasi Anda</h3><div class="history-list">';
        
        donations.forEach(d => {
            html += `
                <div class="history-item">
                    <div class="history-header">
                        <span class="history-id">${d.donation_id}</span>
                        <span class="history-status status-${d.payment_status}">${d.payment_status}</span>
                    </div>
                    <div class="history-detail">
                        <span>Rp ${parseInt(d.amount).toLocaleString('id-ID')}</span>
                        <span>${new Date(d.created_at).toLocaleDateString('id-ID')}</span>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        historyContainer.innerHTML = html;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.donationSystem = new DonationSystem();
});
