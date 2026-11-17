/**
 * Computer Repair Shop - Core Client Logic
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize Multi-Image Upload Previews (if upload container exists)
    const fileInput = document.getElementById('device_images');
    const previewContainer = document.getElementById('image_previews');

    if (fileInput && previewContainer) {
        fileInput.addEventListener('change', (e) => {
            previewContainer.innerHTML = ''; // Clear previous previews
            const files = e.target.files;
            
            if (files.length > 5) {
                alert('You can upload a maximum of 5 images.');
                fileInput.value = '';
                return;
            }

            Array.from(files).forEach((file) => {
                // Ensure it is an image
                if (!file.type.startsWith('image/')) return;

                const reader = new FileReader();
                reader.onload = (event) => {
                    const card = document.createElement('div');
                    card.className = 'image-preview-card';

                    const img = document.createElement('img');
                    img.src = event.target.result;
                    card.appendChild(img);

                    // Add info badge / label
                    const label = document.createElement('span');
                    label.style.position = 'absolute';
                    label.style.bottom = '0';
                    label.style.left = '0';
                    label.style.width = '100%';
                    label.style.background = 'rgba(0,0,0,0.6)';
                    label.style.color = '#fff';
                    label.style.fontSize = '0.65rem';
                    label.style.padding = '2px';
                    label.style.textAlign = 'center';
                    // Trim name
                    label.textContent = file.name.length > 12 ? file.name.substring(0, 10) + '...' : file.name;
                    card.appendChild(label);

                    previewContainer.appendChild(card);
                };
                reader.readAsDataURL(file);
            });
        });
    }

    // 2. Interactive Signature Pads (Canvas)
    const signaturePads = document.querySelectorAll('.sig-canvas');
    signaturePads.forEach(canvas => {
        setupSignaturePad(canvas);
    });
});

/**
 * Sets up custom drawing capabilities on an HTML5 canvas for signatures
 */
function setupSignaturePad(canvas) {
    const ctx = canvas.getContext('2d');
    let drawing = false;
    
    // Set line styles
    ctx.strokeStyle = '#0f172a'; // Deep Navy
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    // Resize canvas to match display size
    function resizeCanvas() {
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width;
        canvas.height = rect.height;
        // Redraw setup
        ctx.strokeStyle = '#0f172a';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    }

    // Run on init and window resize
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    // Mouse Events
    canvas.addEventListener('mousedown', (e) => {
        drawing = true;
        const coords = getCoords(e, canvas);
        ctx.beginPath();
        ctx.moveTo(coords.x, coords.y);
    });

    canvas.addEventListener('mousemove', (e) => {
        if (!drawing) return;
        const coords = getCoords(e, canvas);
        ctx.lineTo(coords.x, coords.y);
        ctx.stroke();
    });

    canvas.addEventListener('mouseup', () => {
        drawing = false;
    });

    canvas.addEventListener('mouseleave', () => {
        drawing = false;
    });

    // Touch Events for Tablets/Mobiles
    canvas.addEventListener('touchstart', (e) => {
        drawing = true;
        const touch = e.touches[0];
        const coords = getCoords(touch, canvas);
        ctx.beginPath();
        ctx.moveTo(coords.x, coords.y);
        e.preventDefault();
    });

    canvas.addEventListener('touchmove', (e) => {
        if (!drawing) return;
        const touch = e.touches[0];
        const coords = getCoords(touch, canvas);
        ctx.lineTo(coords.x, coords.y);
        ctx.stroke();
        e.preventDefault();
    });

    canvas.addEventListener('touchend', () => {
        drawing = false;
    });

    // Handle Clear Button
    const clearBtn = canvas.nextElementSibling;
    if (clearBtn && clearBtn.classList.contains('sig-clear-btn')) {
        clearBtn.addEventListener('click', (e) => {
            e.preventDefault();
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            // Save to input if exists
            const inputId = canvas.getAttribute('data-input');
            if (inputId) {
                const hiddenInput = document.getElementById(inputId);
                if (hiddenInput) hiddenInput.value = '';
            }
            const printImg = document.getElementById(canvas.id + '-print-img');
            if (printImg) printImg.removeAttribute('src');
        });
    }

    // Save signature data on canvas stroke end so it (a) persists in a hidden
    // field and (b) shows up on the printed page, since the live <canvas> is
    // hidden by the .no-print rule when the page is printed.
    function saveSignature() {
        const inputId = canvas.getAttribute('data-input');
        if (!inputId) return;

        const hiddenInput = document.getElementById(inputId);
        // Matching print-only <img> follows the "{canvas-id}-print-img" convention
        const printImg = document.getElementById(canvas.id + '-print-img');

        // Check if canvas is blank
        const blank = document.createElement('canvas');
        blank.width = canvas.width;
        blank.height = canvas.height;
        const isBlank = canvas.toDataURL() === blank.toDataURL();
        const dataUrl = isBlank ? '' : canvas.toDataURL();

        if (hiddenInput) hiddenInput.value = dataUrl;
        if (printImg) printImg.src = dataUrl;
    }

    canvas.addEventListener('mouseup', saveSignature);
    canvas.addEventListener('touchend', saveSignature);
}

/**
 * Gets cursor position relative to canvas
 */
function getCoords(e, canvas) {
    const rect = canvas.getBoundingClientRect();
    // Support mouse or touch events
    const clientX = e.clientX || (e.touches && e.touches[0] ? e.touches[0].clientX : rect.left);
    const clientY = e.clientY || (e.touches && e.touches[0] ? e.touches[0].clientY : rect.top);
    
    return {
        x: clientX - rect.left,
        y: clientY - rect.top
    };
}
