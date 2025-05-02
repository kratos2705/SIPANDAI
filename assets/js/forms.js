    // Dokumen pendukung file selection display
    document.getElementById('dokumen_pendukung').addEventListener('change', function(e) {
        const fileList = Array.from(e.target.files)
            .map(file => `${file.name} (${(file.size / 1024).toFixed(1)} KB)`)
            .join(', ');
        document.getElementById('selected-files').textContent = fileList || 'Belum ada file dipilih';
    });

    // Form tabs switching
    const tabs = document.querySelectorAll('.form-tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // In a real application, you would show/hide corresponding forms here
            // For this example, we'll just change the title
            const formTitle = document.querySelector('.form-content h2');
            formTitle.textContent = `Form Pengajuan ${this.textContent}`;
        });
    });

    // Form submission
    document.getElementById('dokumenForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // In a real application, you would handle form submission here
        // For this example, we'll just show an alert
        alert('Formulir pengajuan berhasil dikirim. Anda akan mendapatkan notifikasi status pengajuan melalui email atau SMS.');
        
        // Redirect to notification page
        // window.location.href = 'notifikasi-status.html';
    });