import './bootstrap';

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
  });
}

// Camera QR scanner for /scan-iuran.
// Dynamically imported only when the scanner container is present.
function initIuranScanner() {
  const root = document.querySelector('[data-iuran-scanner]');
  if (!root || root.dataset.scannerInit) return;
  root.dataset.scannerInit = '1';

  const viewportEl = document.getElementById('iuran-qr-reader');
  const startBtn   = document.getElementById('iuran-start-camera');
  const stopBtn    = document.getElementById('iuran-stop-camera');
  const statusEl   = document.getElementById('iuran-scanner-status');

  if (!viewportEl || !startBtn || !stopBtn || !statusEl) return;

  let scanner     = null;
  let isSubmitting = false;
  let isStarting = false;

  function setStatus(msg) {
    if (statusEl) statusEl.textContent = msg;
  }

  function showStartBtn() {
    startBtn.classList.remove('hidden');
    stopBtn.classList.add('hidden');
  }

  function showStopBtn() {
    startBtn.classList.add('hidden');
    stopBtn.classList.remove('hidden');
  }

  async function stopScanner() {
    isStarting = false;
    if (!scanner) return;
    try {
      await scanner.stop();
    } catch (_) {
      // ignore cleanup errors
    }
    scanner = null;
    showStartBtn();
    setStatus('Kamera dimatikan.');
  }

  async function startScanner() {
    if (scanner || isStarting) return;
    if (!navigator.mediaDevices?.getUserMedia) {
      setStatus('Kamera tidak tersedia di browser ini. Gunakan input manual.');
      return;
    }

    isStarting = true;
    import('html5-qrcode').then(({ Html5Qrcode, Html5QrcodeSupportedFormats }) => {
      scanner = new Html5Qrcode('iuran-qr-reader');

      const config = {
        fps: 10,
        qrbox: { width: 240, height: 240 },
        formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
        useBarCodeDetectorIfSupported: true,
        showTorchButtonIfSupported: true,
      };

      setStatus('Memulai kamera...');
      showStopBtn();

      scanner.start(
        { facingMode: 'environment' },
        config,
        (decodedText) => {
          // Duplicate-scan guard.
          if (isSubmitting) return;
          const token = (decodedText || '').trim();
          if (!token) return;

          isSubmitting = true;
          setStatus('QR terbaca. Mencatat iuran...');

          stopScanner().then(() => {
            window.dispatchEvent(new CustomEvent('iuran-qr-detected', { detail: { token } }));
            // Re-enable after a short delay to allow next scan.
            setTimeout(() => { isSubmitting = false; }, 2000);
          });
        },
        () => {
          // Per-frame error — ignore, camera keeps scanning.
        }
      )
      .then(() => {
        isStarting = false;
        setStatus('Arahkan kamera ke QR rumah.');
      })
      .catch((err) => {
        isStarting = false;
        scanner = null;
        showStartBtn();
        if (err && err.message && err.message.toLowerCase().includes('permission')) {
          setStatus('Izin kamera ditolak. Aktifkan izin kamera atau input kode manual.');
        } else if (err && err.message && err.message.toLowerCase().includes('notfound')) {
          setStatus('Kamera tidak ditemukan. Gunakan input manual.');
        } else {
          setStatus('Scanner gagal dimulai. Coba refresh halaman atau input manual.');
        }
      });
    }).catch(() => {
      isStarting = false;
      setStatus('Scanner gagal dimuat. Gunakan input manual.');
    });
  }

  startBtn.addEventListener('click', startScanner);
  stopBtn.addEventListener('click', stopScanner);

  // Clean up on page navigation/unload.
  window.addEventListener('beforeunload', stopScanner, { once: true });
  document.addEventListener('livewire:navigating', stopScanner, { once: true });
}

// Initialize on page load.
document.addEventListener('DOMContentLoaded', initIuranScanner);

// Re-initialize after Livewire navigations / DOM updates.
document.addEventListener('livewire:navigated', initIuranScanner);
document.addEventListener('livewire:update', () => {
  // Small delay so Livewire finishes DOM morph before we search for the container.
  setTimeout(initIuranScanner, 50);
});
