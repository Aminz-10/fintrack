<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::require();
$userId = Auth::id();

$preview = null;
$pasted  = '';
$defaultDate = $_POST['default_date'] ?? date('Y-m-d');
$action = $_POST['action'] ?? '';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::csrfVerify();

    // Source: pasted text wins if filled, else uploaded file.
    $pasted = (string)($_POST['notes'] ?? '');
    $text = $pasted;

    if (trim($text) === '' && !empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        // Validate upload
        if ($_FILES['file']['size'] > 2 * 1024 * 1024) {
            $error = 'File too large (max 2 MB).';
        } else {
            $name = $_FILES['file']['name'];
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['txt','md','markdown'], true)) {
                $error = 'Only .txt and .md files are accepted.';
            } else {
                $text = file_get_contents($_FILES['file']['tmp_name']);
            }
        }
    }

    if (!$error) {
        $parsed = NotesParser::parse($text, $defaultDate);

        if ($action === 'preview') {
            $preview = $parsed;
        } elseif ($action === 'import') {
            // Allow user to override per-row before saving
            $items = $_POST['items'] ?? [];
            $imported = 0;
            $pdo = Database::pdo();
            $pdo->beginTransaction();
            try {
                foreach ($items as $i => $row) {
                    if (empty($row['include'])) continue;
                    Tx::create($userId, [
                        'title'          => $row['title'] ?? 'Untitled',
                        'amount'         => $row['amount'] ?? 0,
                        'type'           => $row['type'] ?? 'expense',
                        'category'       => $row['category'] ?? 'Other',
                        'date'           => $row['date'] ?? $defaultDate,
                        'note'           => 'Imported from notes',
                        'payment_method' => 'Cash',
                    ]);
                    $imported++;
                }
                $pdo->commit();
                flash('success', "$imported transactions imported.");
                header('Location: ' . url('modules/transactions/index.php'));
                exit;
            } catch (Throwable $ex) {
                $pdo->rollBack();
                $error = 'Import failed: ' . $ex->getMessage();
            }
        }
    }
}

$pageTitle = 'Import notes';
$activeNav = 'import';
include __DIR__ . '/../../includes/header.php';
?>
<h4 class="mb-1">Import from notes</h4>
<p class="text-muted small">Paste an Apple Notes / iOS Notes export, upload a <code>.txt</code> or <code>.md</code> file, or scan a screenshot. The parser auto-detects titles, amounts, and likely categories.</p>

<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<!-- OCR scanner (client-side, Tesseract.js) -->
<div class="card mb-3">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div>
        <h6 class="mb-0"><i class="bi bi-camera"></i> Scan from image</h6>
        <small class="text-muted">Upload a screenshot of your notes or any expense list — we'll read it automatically.</small>
      </div>
      <span class="badge bg-secondary-subtle">Beta · runs in your browser</span>
    </div>

    <div id="ocrDrop" class="mt-3 p-4 text-center" tabindex="0"
         style="border:2px dashed var(--bs-border-color); border-radius:14px; cursor:pointer; transition:background .15s ease, border-color .15s ease;">
      <i class="bi bi-image" style="font-size:1.75rem; color:var(--bs-secondary-color);"></i>
      <div class="mt-2"><strong>Drop, click, or paste</strong> an image</div>
      <small class="text-muted">JPG, PNG, WebP, screenshots — up to 8 MB. Multiple languages supported.</small>
      <input id="ocrFile" type="file" accept="image/*" hidden>
    </div>

    <div id="ocrPreview" class="mt-3 d-none">
      <div class="row g-3 align-items-start">
        <div class="col-sm-auto">
        <img id="ocrImg" alt="Selected image"
          style="max-width:180px; max-height:180px; border-radius:12px; object-fit:cover; box-shadow:var(--ft-shadow-sm);">
         </div>
        <div class="col">
          <div class="d-flex flex-wrap gap-2 mb-2">
            <div class="btn-group btn-group-sm" role="group" aria-label="OCR language">
              <input type="radio" class="btn-check" name="ocrLang" id="lang-eng" value="eng" checked>
              <label class="btn btn-outline-secondary" for="lang-eng">English</label>
              <input type="radio" class="btn-check" name="ocrLang" id="lang-msa" value="msa">
              <label class="btn btn-outline-secondary" for="lang-msa">Malay</label>
              <input type="radio" class="btn-check" name="ocrLang" id="lang-mix" value="eng+msa">
              <label class="btn btn-outline-secondary" for="lang-mix">Mixed</label>
            </div>
          </div>
          <div class="d-flex flex-wrap gap-2 mb-2">
            <button id="ocrRun" type="button" class="btn btn-primary btn-sm"><i class="bi bi-magic"></i> Extract text</button>
            <button id="ocrClear" type="button" class="btn btn-outline-secondary btn-sm">Clear image</button>
          </div>
          <div id="ocrStatus" class="small text-muted"></div>
          <div class="progress mt-2 d-none" id="ocrProgressWrap" style="height:6px;">
            <div class="progress-bar" id="ocrProgress" role="progressbar" style="width:0%"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<form method="post" enctype="multipart/form-data" class="card mb-3">
  <?= Auth::csrfField() ?>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-8">
        <label class="form-label">Paste notes</label>
        <textarea name="notes" class="form-control" rows="10" placeholder="Food - 12&#10;Fuel: 50&#10;Salary RM3000"><?= e($pasted) ?></textarea>
      </div>
      <div class="col-md-4">
        <label class="form-label">…or upload file</label>
        <input type="file" name="file" class="form-control" accept=".txt,.md,.markdown">
        <div class="form-text">.txt or .md (max 2 MB)</div>
        <label class="form-label mt-3">Default date</label>
        <input type="date" name="default_date" class="form-control" value="<?= e($defaultDate) ?>">
        <div class="form-text">Used when a line has no explicit date.</div>
      </div>
    </div>
    <div class="mt-3 d-flex gap-2">
      <button class="btn btn-primary" name="action" value="preview"><i class="bi bi-eye"></i> Preview</button>
    </div>
  </div>
</form>

<?php if (is_array($preview)): ?>
  <?php if (empty($preview)): ?>
    <div class="alert alert-warning">No transactions detected. Try lines like <code>Food - 12</code> or <code>Fuel: 50</code>.</div>
  <?php else: ?>
    <form method="post" class="card">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="import">
      <input type="hidden" name="default_date" value="<?= e($defaultDate) ?>">
      <input type="hidden" name="notes" value="<?= e($pasted) ?>">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">Detected <?= count($preview) ?> transactions</h6>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('.imp-include').forEach(c=>c.checked=true)">Select all</button>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle table-borderless">
            <thead><tr>
              <th><input type="checkbox" checked onclick="document.querySelectorAll('.imp-include').forEach(c=>c.checked=this.checked)"></th>
              <th class="text-muted text-end" style="width:42px;">No.</th>
              <th>Date</th><th>Title</th><th>Type</th><th>Category</th><th class="text-end">Amount</th>
            </tr></thead>
            <tbody>
            <?php foreach ($preview as $i => $p): ?>
              <tr>
                <td><input type="checkbox" class="imp-include" name="items[<?= $i ?>][include]" value="1" checked></td>
                <td class="text-muted text-end small fin-num"><?= $i + 1 ?></td>
                <td><input type="date" class="form-control form-control-sm" name="items[<?= $i ?>][date]" value="<?= e($p['date']) ?>"></td>
                <td><input class="form-control form-control-sm" name="items[<?= $i ?>][title]" value="<?= e($p['title']) ?>"></td>
                <td>
                  <select class="form-select form-select-sm" name="items[<?= $i ?>][type]">
                    <option value="expense" <?= $p['type']==='expense'?'selected':'' ?>>Expense</option>
                    <option value="income"  <?= $p['type']==='income' ?'selected':'' ?>>Income</option>
                  </select>
                </td>
                <td>
                  <select class="form-select form-select-sm" name="items[<?= $i ?>][category]">
                    <?php foreach (Tx::CATEGORIES as $c): ?>
                      <option value="<?= e($c) ?>" <?= $p['category']===$c?'selected':'' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><input type="number" step="0.01" class="form-control form-control-sm text-end"
                       name="items[<?= $i ?>][amount]" value="<?= e((string)$p['amount']) ?>"></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <button class="btn btn-success"><i class="bi bi-check-lg"></i> Import selected</button>
      </div>
    </form>
  <?php endif; ?>
<?php endif; ?>

<div class="card mt-3">
  <div class="card-body">
    <h6>Tips</h6>
    <ul class="small mb-0">
      <li>Section headers <code># Expenses</code> / <code># Income</code> override auto-detection.</li>
      <li><strong>Date headings</strong> apply to all lines that follow them:
        <ul class="mb-0">
          <li><code>2026-05-09</code> — ISO date</li>
          <li><code>4 May 2026</code> / <code>May 4, 2026</code> — single day</li>
          <li><code>4-10 May 2026</code> / <code>May 4-10, 2026</code> — range (uses the <em>start</em> date)</li>
          <li><code>May 2026</code> — month only (uses the 1st)</li>
          <li><code>4/5/2026</code> — D/M/YYYY</li>
        </ul>
      </li>
      <li>Lines starting with <code>Total</code>, <code>Balance</code>, <code>#</code> are ignored.</li>
      <li>Common transaction formats: <code>Food - 12</code>, <code>Fuel: 50</code>, <code>* Coffee 8</code>, <code>Salary RM3000</code>.</li>
      <li><strong>Image scan:</strong> clearer screenshots work best. After extracting, review the text in the box above before clicking Preview.</li>
    </ul>
  </div>
</div>

<script>
(function () {
  const dropZone     = document.getElementById('ocrDrop');
  const fileInput    = document.getElementById('ocrFile');
  const preview      = document.getElementById('ocrPreview');
  const img          = document.getElementById('ocrImg');
  const runBtn       = document.getElementById('ocrRun');
  const clearBtn     = document.getElementById('ocrClear');
  const statusEl     = document.getElementById('ocrStatus');
  const progressWrap = document.getElementById('ocrProgressWrap');
  const progressBar  = document.getElementById('ocrProgress');
  const textarea     = document.querySelector('textarea[name="notes"]');

  let currentFile = null;
  let tessLoading = null;

  function setStatus(html, kind) {
    statusEl.classList.remove('text-success', 'text-danger', 'text-muted');
    statusEl.classList.add(kind === 'ok' ? 'text-success' : kind === 'err' ? 'text-danger' : 'text-muted');
    statusEl.innerHTML = html;
  }

  function showPreview(file) {
    if (!file) return;
    if (!file.type || !file.type.startsWith('image/')) {
      setStatus('That doesn’t look like an image.', 'err');
      return;
    }
    if (file.size > 8 * 1024 * 1024) {
      setStatus('Image is larger than 8 MB. Try compressing or cropping.', 'err');
      return;
    }
    currentFile = file;
    img.src = URL.createObjectURL(file);
    preview.classList.remove('d-none');
    progressWrap.classList.add('d-none');
    progressBar.style.width = '0%';
    setStatus(file.name + ' · ' + (file.size / 1024).toFixed(0) + ' KB · ready to extract');
  }

  dropZone.addEventListener('click', () => fileInput.click());
  dropZone.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
  });
  fileInput.addEventListener('change', (e) => showPreview(e.target.files[0]));

  ['dragenter', 'dragover'].forEach(ev => dropZone.addEventListener(ev, (e) => {
    e.preventDefault(); e.stopPropagation();
    dropZone.style.background = 'rgba(255, 107, 157, .06)';
    dropZone.style.borderColor = 'var(--bs-primary)';
  }));
  ['dragleave', 'drop'].forEach(ev => dropZone.addEventListener(ev, (e) => {
    e.preventDefault(); e.stopPropagation();
    dropZone.style.background = '';
    dropZone.style.borderColor = '';
  }));
  dropZone.addEventListener('drop', (e) => {
    if (e.dataTransfer.files && e.dataTransfer.files.length) showPreview(e.dataTransfer.files[0]);
  });

  // Clipboard paste
  window.addEventListener('paste', (e) => {
    if (!e.clipboardData || !e.clipboardData.items) return;
    for (const it of e.clipboardData.items) {
      if (it.type && it.type.startsWith('image/')) {
        showPreview(it.getAsFile());
        e.preventDefault();
        return;
      }
    }
  });

  clearBtn.addEventListener('click', () => {
    currentFile = null;
    fileInput.value = '';
    img.src = '';
    preview.classList.add('d-none');
    progressWrap.classList.add('d-none');
    progressBar.style.width = '0%';
    setStatus('');
  });

  function loadTesseract() {
    if (window.Tesseract) return Promise.resolve();
    if (tessLoading) return tessLoading;
    tessLoading = new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
      s.async = true;
      s.onload  = () => resolve();
      s.onerror = () => reject(new Error('Could not load OCR engine. Check your internet connection.'));
      document.head.appendChild(s);
    });
    return tessLoading;
  }

  runBtn.addEventListener('click', async () => {
    if (!currentFile) return;
    const lang = (document.querySelector('input[name="ocrLang"]:checked') || {}).value || 'eng';

    runBtn.disabled = true;
    clearBtn.disabled = true;
    progressWrap.classList.remove('d-none');
    progressBar.style.width = '5%';
    setStatus('Loading OCR engine…');

    try {
      await loadTesseract();
      setStatus('Reading image… 0%');
      const result = await Tesseract.recognize(currentFile, lang, {
        logger: (m) => {
          if (m.status === 'recognizing text') {
            const pct = Math.round(m.progress * 100);
            progressBar.style.width = (10 + m.progress * 90) + '%';
            setStatus('Reading image… ' + pct + '%');
          } else if (m.status) {
            setStatus(m.status.charAt(0).toUpperCase() + m.status.slice(1) + '…');
          }
        }
      });

      const text = (result && result.data && result.data.text ? result.data.text : '').trim();
      if (!text) {
        progressWrap.classList.add('d-none');
        setStatus('No text detected. Try a clearer or larger image.', 'err');
        return;
      }

      // Light clean-up: drop leading whitespace, collapse 3+ newlines, fix common "RM" OCR slips
      const cleaned = text
        .split('\n')
        .map(l => l.replace(/^\s+|\s+$/g, ''))
        .filter(l => l.length > 0)
        .join('\n')
        .replace(/\n{3,}/g, '\n\n')
        .replace(/\bRM\s*([0-9])/g, 'RM$1');

      const existing = textarea.value.trim();
      textarea.value = existing ? existing + '\n\n' + cleaned : cleaned;
      textarea.scrollIntoView({ behavior: 'smooth', block: 'center' });

      progressBar.style.width = '100%';
      const lines = cleaned.split('\n').length;
      setStatus('<i class="bi bi-check-circle"></i> Extracted ' + lines + ' line' + (lines === 1 ? '' : 's')
        + '. Review the box below, then click <strong>Preview</strong>.', 'ok');
    } catch (err) {
      console.error(err);
      progressWrap.classList.add('d-none');
      setStatus('Failed: ' + (err && err.message ? err.message : 'unknown error'), 'err');
    } finally {
      runBtn.disabled = false;
      clearBtn.disabled = false;
    }
  });
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
