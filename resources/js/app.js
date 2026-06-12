import './bootstrap';
import Trix from 'trix';
import 'trix/dist/trix.css';

Trix.config.toolbar.getDefaultHTML = () => `
  <div class="trix-button-row">
    <span class="trix-button-group trix-button-group--text-tools" data-trix-button-group="text-tools">
      <button type="button" class="trix-button trix-button--icon trix-button--icon-bold" data-trix-attribute="bold" data-trix-key="b" title="Tebal" tabindex="-1">Tebal</button>
      <button type="button" class="trix-button trix-button--icon trix-button--icon-italic" data-trix-attribute="italic" data-trix-key="i" title="Miring" tabindex="-1">Miring</button>
      <button type="button" class="trix-button trix-button--icon trix-button--icon-link" data-trix-attribute="href" data-trix-action="link" data-trix-key="k" title="Tautan" tabindex="-1">Tautan</button>
    </span>
    <span class="trix-button-group trix-button-group--block-tools" data-trix-button-group="block-tools">
      <button type="button" class="trix-button trix-button--icon trix-button--icon-bullet-list" data-trix-attribute="bullet" title="Daftar poin" tabindex="-1">Daftar poin</button>
      <button type="button" class="trix-button trix-button--icon trix-button--icon-number-list" data-trix-attribute="number" title="Daftar nomor" tabindex="-1">Daftar nomor</button>
    </span>
    <span class="trix-button-group-spacer"></span>
    <span class="trix-button-group trix-button-group--history-tools" data-trix-button-group="history-tools">
      <button type="button" class="trix-button trix-button--icon trix-button--icon-undo" data-trix-action="undo" data-trix-key="z" title="Urungkan" tabindex="-1">Urungkan</button>
      <button type="button" class="trix-button trix-button--icon trix-button--icon-redo" data-trix-action="redo" data-trix-key="shift+z" title="Ulangi" tabindex="-1">Ulangi</button>
    </span>
  </div>
  <div class="trix-dialogs" data-trix-dialogs>
    <div class="trix-dialog trix-dialog--link" data-trix-dialog="href" data-trix-dialog-attribute="href">
      <div class="trix-dialog__link-fields">
        <input type="url" name="href" class="trix-input trix-input--dialog" placeholder="https://contoh.id" aria-label="Alamat tautan" data-trix-validate-href required data-trix-input>
        <div class="trix-button-group">
          <input type="button" class="trix-button trix-button--dialog" value="Pasang" data-trix-method="setAttribute">
          <input type="button" class="trix-button trix-button--dialog" value="Hapus" data-trix-method="removeAttribute">
        </div>
      </div>
    </div>
  </div>
`;

document.addEventListener('trix-file-accept', (event) => {
  event.preventDefault();
});

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
      setStatus('Kamera tidak tersedia di browser ini. Gunakan masukan manual.');
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
          setStatus('Izin kamera ditolak. Aktifkan izin kamera atau masukkan kode secara manual.');
        } else if (err && err.message && err.message.toLowerCase().includes('notfound')) {
          setStatus('Kamera tidak ditemukan. Gunakan masukan manual.');
        } else {
          setStatus('Pemindai gagal dimulai. Coba muat ulang halaman atau gunakan masukan manual.');
        }
      });
    }).catch(() => {
      isStarting = false;
      setStatus('Pemindai gagal dimuat. Gunakan masukan manual.');
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
