<?php
$pageTitle = 'Content Manager';
$pageSubtitle = 'Manage homepage hero, about us, contact info, and help center content';
require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
  .cm-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem; }
  .cm-preview { max-width: 200px; border-radius: 10px; border: 1px solid var(--border-color); overflow: hidden; margin-top: 0.5rem; }
  .cm-preview img { width: 100%; height: auto; display: block; }
  .cm-faq-item { background: #f8fafc; border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem; margin-bottom: 0.75rem; }
  .cm-faq-item .form-group { margin-bottom: 0.5rem; }
  .cm-faq-item .form-group:last-child { margin-bottom: 0; }
  .cm-faq-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
  .cm-faq-header span { font-weight: 600; font-size: 0.85rem; color: var(--text-primary); }
  .cm-btn-icon { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border-color); background: white; color: var(--text-muted); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: var(--transition); font-size: 0.8rem; }
  .cm-btn-icon:hover { border-color: var(--danger); color: var(--danger); }
  .cm-loading { text-align: center; padding: 3rem; color: var(--text-muted); }
  .cm-loading i { font-size: 2rem; display: block; margin-bottom: 0.75rem; }
</style>

<div class="cm-toolbar">
  <div class="tabs" id="cm-tabs">
    <button class="tab active" data-section="homepage_hero"><i class="fas fa-home"></i> Homepage Hero</button>
    <button class="tab" data-section="about_us"><i class="fas fa-info-circle"></i> About Us</button>
    <button class="tab" data-section="contact_info"><i class="fas fa-envelope"></i> Contact Info</button>
    <button class="tab" data-section="help_center"><i class="fas fa-question-circle"></i> Help Center</button>
  </div>
</div>

<div id="cm-content">
  <div class="cm-loading"><i class="fas fa-spinner fa-pulse"></i> Loading content...</div>
</div>

<script>
  let currentSection = 'homepage_hero';
  let allData = {};

  document.querySelectorAll('#cm-tabs .tab').forEach(tab => {
    tab.addEventListener('click', function() {
      document.querySelectorAll('#cm-tabs .tab').forEach(t => t.classList.remove('active'));
      this.classList.add('active');
      currentSection = this.dataset.section;
      renderForm(currentSection);
    });
  });

  async function loadContent() {
    try {
      const res = await fetch('../api/get-content.php');
      const json = await res.json();
      if (json.success) {
        json.data.forEach(item => { allData[item.section_key] = item; });
        renderForm(currentSection);
      } else {
        document.getElementById('cm-content').innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><h3>Failed to load content</h3></div>';
      }
    } catch (e) {
      document.getElementById('cm-content').innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><h3>Network error</h3></div>';
    }
  }

  function renderForm(section) {
    const data = allData[section] || { section_key: section, title: '', subtitle: '', content: '', image_url: '', meta: {} };
    const meta = data.meta || {};
    let html = '<div class="card"><form id="cm-form" onsubmit="saveContent(event)">';
    html += `<input type="hidden" name="section" value="${section}">`;

    if (section === 'homepage_hero') {
      html += `
        <div class="form-grid">
          <div class="form-group">
            <label>Title</label>
            <input type="text" name="title" class="form-input" value="${escapeHtml(data.title || '')}" required>
          </div>
          <div class="form-group">
            <label>Subtitle</label>
            <input type="text" name="subtitle" class="form-input" value="${escapeHtml(data.subtitle || '')}">
          </div>
          <div class="form-group full-width">
            <label>Content / Description</label>
            <textarea name="content" class="form-input" rows="3">${escapeHtml(data.content || '')}</textarea>
          </div>
          <div class="form-group">
            <label>Button Text</label>
            <input type="text" name="meta[button_text]" class="form-input" value="${escapeHtml(meta.button_text || 'View Our Products')}">
          </div>
          <div class="form-group">
            <label>Button Link</label>
            <input type="text" name="meta[button_link]" class="form-input" value="${escapeHtml(meta.button_link || 'customer/store-product.php')}">
          </div>
        </div>
      `;
    } else if (section === 'about_us') {
      html += `
        <div class="form-grid">
          <div class="form-group">
            <label>Title</label>
            <input type="text" name="title" class="form-input" value="${escapeHtml(data.title || '')}" required>
          </div>
          <div class="form-group">
            <label>Subtitle</label>
            <input type="text" name="subtitle" class="form-input" value="${escapeHtml(data.subtitle || '')}">
          </div>
          <div class="form-group full-width">
            <label>Content</label>
            <textarea name="content" class="form-input" rows="8">${escapeHtml(data.content || '')}</textarea>
          </div>
        </div>
      `;
    } else if (section === 'contact_info') {
      html += `
        <div class="form-grid">
          <div class="form-group">
            <label>Title</label>
            <input type="text" name="title" class="form-input" value="${escapeHtml(data.title || '')}" required>
          </div>
          <div class="form-group">
            <label>Subtitle</label>
            <input type="text" name="subtitle" class="form-input" value="${escapeHtml(data.subtitle || '')}">
          </div>
          <div class="form-group full-width">
            <label>Content / Description</label>
            <textarea name="content" class="form-input" rows="3">${escapeHtml(data.content || '')}</textarea>
          </div>
          <div class="form-group">
            <label>Address</label>
            <input type="text" name="meta[address]" class="form-input" value="${escapeHtml(meta.address || '')}">
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="text" name="meta[phone]" class="form-input" value="${escapeHtml(meta.phone || '')}">
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="meta[email]" class="form-input" value="${escapeHtml(meta.email || '')}">
          </div>
          <div class="form-group full-width">
            <label>Google Maps Embed URL</label>
            <input type="text" name="meta[map_url]" class="form-input" value="${escapeHtml(meta.map_url || '')}" placeholder="https://maps.google.com/...">
          </div>
        </div>
      `;
    } else if (section === 'help_center') {
      const faqs = meta.faqs || [];
      html += `
        <div class="form-grid">
          <div class="form-group">
            <label>Title</label>
            <input type="text" name="title" class="form-input" value="${escapeHtml(data.title || '')}" required>
          </div>
          <div class="form-group">
            <label>Subtitle</label>
            <input type="text" name="subtitle" class="form-input" value="${escapeHtml(data.subtitle || '')}">
          </div>
        </div>
        <div style="margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center;">
          <label style="font-size:0.82rem;font-weight:700;color:var(--text-primary);">FAQs</label>
          <button type="button" class="btn btn-outline btn-sm" onclick="addFaq()"><i class="fas fa-plus"></i> Add FAQ</button>
        </div>
        <div id="faq-list">
          ${faqs.map((faq, i) => renderFaqItem(faq, i)).join('')}
        </div>
      `;
    }

    html += `
      <div class="modal-actions" style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--border-color);">
        <button type="submit" class="btn btn-primary" id="cm-save-btn"><i class="fas fa-save"></i> Save Changes</button>
        <button type="button" class="btn btn-outline" onclick="resetForm()"><i class="fas fa-undo"></i> Reset</button>
      </div>
    `;
    html += '</form></div>';
    document.getElementById('cm-content').innerHTML = html;
  }

  function renderFaqItem(faq, i) {
    return `
      <div class="cm-faq-item" data-index="${i}">
        <div class="cm-faq-header">
          <span>FAQ #${i + 1}</span>
          <button type="button" class="cm-btn-icon" onclick="removeFaq(${i})"><i class="fas fa-times"></i></button>
        </div>
        <div class="form-group">
          <label>Question</label>
          <input type="text" name="faqs[${i}][question]" class="form-input" value="${escapeHtml(faq.question || '')}" placeholder="Enter question...">
        </div>
        <div class="form-group">
          <label>Answer</label>
          <textarea name="faqs[${i}][answer]" class="form-input" rows="3" placeholder="Enter answer...">${escapeHtml(faq.answer || '')}</textarea>
        </div>
      </div>
    `;
  }

  function addFaq() {
    const list = document.getElementById('faq-list');
    const items = list.querySelectorAll('.cm-faq-item');
    const i = items.length;
    const div = document.createElement('div');
    div.innerHTML = renderFaqItem({ question: '', answer: '' }, i);
    list.appendChild(div.firstElementChild);
  }

  function removeFaq(index) {
    const item = document.querySelector(`.cm-faq-item[data-index="${index}"]`);
    if (item && confirm('Remove this FAQ?')) {
      item.remove();
      // Re-index remaining items
      document.querySelectorAll('.cm-faq-item').forEach((el, i) => {
        el.dataset.index = i;
        el.querySelector('.cm-faq-header span').textContent = `FAQ #${i + 1}`;
        el.querySelectorAll('input, textarea').forEach(input => {
          const name = input.getAttribute('name');
          if (name) {
            input.setAttribute('name', name.replace(/\[\d+\]/, `[${i}]`));
          }
        });
      });
    }
  }

  async function saveContent(e) {
    e.preventDefault();
    const btn = document.getElementById('cm-save-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Saving...';

    const form = document.getElementById('cm-form');
    const formData = new FormData(form);

    // Build meta JSON from meta fields and FAQs
    const section = formData.get('section');
    let meta = {};

    if (section === 'homepage_hero') {
      meta.button_text = formData.get('meta[button_text]') || '';
      meta.button_link = formData.get('meta[button_link]') || '';
    } else if (section === 'contact_info') {
      meta.address = formData.get('meta[address]') || '';
      meta.phone = formData.get('meta[phone]') || '';
      meta.email = formData.get('meta[email]') || '';
      meta.map_url = formData.get('meta[map_url]') || '';
    } else if (section === 'help_center') {
      const faqs = [];
      const faqItems = document.querySelectorAll('.cm-faq-item');
      faqItems.forEach(item => {
        const q = item.querySelector('[name^="faqs["][name$="[question]"]');
        const a = item.querySelector('[name^="faqs["][name$="[answer]"]');
        if (q && a && q.value.trim()) {
          faqs.push({ question: q.value.trim(), answer: a.value.trim() });
        }
      });
      meta.faqs = faqs;
    }

    // Replace the form meta with our constructed JSON
    // Delete old meta fields from formData and add meta JSON
    for (const key of formData.keys()) {
      if (key.startsWith('meta[') || key.startsWith('faqs[')) {
        formData.delete(key);
      }
    }
    formData.set('meta', JSON.stringify(meta));

    try {
      const res = await fetch('../api/save-content.php', { method: 'POST', body: formData });
      const json = await res.json();
      if (json.success) {
        showToast('Content saved successfully!', 'success');
        // Reload to refresh image
        loadContent();
      } else {
        showToast(json.error || 'Failed to save content', 'error');
      }
    } catch (e) {
      showToast('Network error', 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
    }
  }

  function resetForm() {
    renderForm(currentSection);
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }

  document.addEventListener('DOMContentLoaded', loadContent);
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
