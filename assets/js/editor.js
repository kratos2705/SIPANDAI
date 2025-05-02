        // Simple navigation functionality
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-link');
            const pages = document.querySelectorAll('main');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all links
                    navLinks.forEach(l => l.classList.remove('active'));
                    
                    // Add active class to clicked link
                    this.classList.add('active');
                    
                    // Hide all pages
                    pages.forEach(page => page.style.display = 'none');
                    
                    // Show targeted page
                    const targetPage = document.querySelector(this.getAttribute('href'));
                    if (targetPage) {
                        targetPage.style.display = 'block';
                    }
                });
            });

            // Editor buttons functionality
            const editorButtons = document.querySelectorAll('.editor-button');
            const editorContent = document.querySelector('.editor-content');
            
            editorButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Simple demonstration of formatting
                    const buttonText = this.textContent;
                    
                    if (buttonText === 'B') {
                        document.execCommand('bold', false, null);
                    } else if (buttonText === 'I') {
                        document.execCommand('italic', false, null);
                    } else if (buttonText === 'U') {
                        document.execCommand('underline', false, null);
                    } else if (buttonText === 'Tautan') {
                        const url = prompt('Masukkan URL:', 'https://');
                        if (url) {
                            document.execCommand('createLink', false, url);
                        }
                    } else if (buttonText === 'Gambar') {
                        alert('Fitur unggah gambar melalui editor akan segera tersedia.');
                    } else if (buttonText === 'Daftar') {
                        document.execCommand('insertUnorderedList', false, null);
                    }
                    
                    editorContent.focus();
                });
            });

            // Publish button functionality
            document.getElementById('publikasi-btn').addEventListener('click', function() {
                const judul = document.getElementById('berita-judul').value;
                if (!judul) {
                    alert('Silakan isi judul berita terlebih dahulu.');
                    return;
                }
                
                alert('Berita "' + judul + '" berhasil dipublikasikan!');
                
                // Reset form after successful submission
                document.getElementById('berita-judul').value = '';
                document.getElementById('berita-kategori').selectedIndex = 0;
                document.getElementById('berita-status').selectedIndex = 0;
                editorContent.innerHTML = 'Tuliskan isi berita atau pengumuman di sini...';
            });

            // Preview button functionality
            document.getElementById('pratinjau-btn').addEventListener('click', function() {
                const judul = document.getElementById('berita-judul').value;
                if (!judul) {
                    alert('Silakan isi judul berita terlebih dahulu.');
                    return;
                }
                
                alert('Menampilkan pratinjau untuk berita "' + judul + '"');
            });

            // Save button functionality
            document.getElementById('simpan-btn').addEventListener('click', function() {
                const judul = document.getElementById('berita-judul').value;
                if (!judul) {
                    alert('Silakan isi judul berita terlebih dahulu.');
                    return;
                }
                
                alert('Berita "' + judul + '" berhasil disimpan sebagai draft.');
            });

            // Edit and Delete buttons functionality
            const editButtons = document.querySelectorAll('.edit-btn');
            const deleteButtons = document.querySelectorAll('.delete-btn');
            
            editButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const row = this.closest('tr');
                    const title = row.cells[0].textContent;
                    
                    document.getElementById('berita-judul').value = title;
                    document.getElementById('berita-judul').focus();
                    
                    alert('Mengedit berita: ' + title);
                });
            });
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const row = this.closest('tr');
                    const title = row.cells[0].textContent;
                    
                    if (confirm('Apakah Anda yakin ingin menghapus berita: ' + title + '?')) {
                        row.remove();
                        alert('Berita berhasil dihapus!');
                    }
                });
            });
        });