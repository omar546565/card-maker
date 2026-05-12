document.addEventListener('DOMContentLoaded', () => {
    const bgUpload = document.getElementById('bg-upload');
    const canvasWrapper = document.getElementById('canvas-wrapper');
    const canvasBg = document.getElementById('canvas-bg');
    const placeholderText = document.getElementById('placeholder-text');
    const fieldsSection = document.getElementById('fields-section');
    const controlsSection = document.getElementById('controls-section');
    const saveBtn = document.getElementById('save-template-btn');
    const addFieldBtn = document.getElementById('add-field-btn');
    
    let currentImage = null;
    let fields = [];
    let activeFieldId = null;
    let fieldCounter = 0;
    let currentTemplateId = null;

    // Toast Notification
    function showToast(message, type = 'success') {
        // Remove existing toast
        const existing = document.getElementById('toast-notification');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.id = 'toast-notification';
        toast.innerHTML = `<span style="font-size:20px">${type === 'success' ? '✅' : '❌'}</span> ${message}`;

        // Set initial (hidden) position — no transition yet
        toast.style.cssText = `
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(120px);
            background: ${type === 'error' ? '#7f1d1d' : '#1e1e2e'};
            color: #fff;
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Cairo', sans-serif;
            font-weight: 600;
            box-shadow: 0 8px 30px rgba(0,0,0,0.35);
            z-index: 99999;
            display: flex;
            align-items: center;
            gap: 10px;
            border-right: 4px solid ${type === 'error' ? '#ef4444' : '#22c55e'};
            opacity: 0;
            direction: rtl;
            pointer-events: none;
            transition: none;
        `;
        document.body.appendChild(toast);

        // Force browser to paint the hidden state first, then animate
        void toast.offsetHeight;

        toast.style.transition = 'transform 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.35s ease';
        toast.style.transform = 'translateX(-50%) translateY(0)';
        toast.style.opacity = '1';

        // Auto dismiss after 3s
        setTimeout(() => {
            toast.style.transform = 'translateX(-50%) translateY(120px)';
            toast.style.opacity = '0';
            setTimeout(() => { if (toast.parentNode) toast.remove(); }, 500);
        }, 3000);
    }

    // Saved Templates
    const toggleSavedBtn = document.getElementById('toggle-saved-btn');
    const savedTemplatesList = document.getElementById('saved-templates-list');

    async function loadSavedTemplates() {
        try {
            const res = await fetch('api.php?action=list_templates');
            const data = await res.json();
            
            if (data.success) {
                savedTemplatesList.innerHTML = '';
                if (data.data.length === 0) {
                    savedTemplatesList.innerHTML = '<p class="text-sm text-gray-500 text-center py-2">لا توجد بطاقات محفوظة</p>';
                    return;
                }
                
                data.data.forEach(tpl => {
                    const el = document.createElement('div');
                    el.className = 'flex flex-col p-2 bg-white border rounded shadow-sm';
                    
                    const link = window.location.origin + '/view.php?id=' + tpl.id;
                    el.innerHTML = `
                        <div class="flex justify-between items-center mb-1">
                            <span class="font-bold text-sm text-gray-800">${tpl.name}</span>
                            <span class="text-xs text-gray-500">${tpl.created_at.split(' ')[0]}</span>
                        </div>
                        <div class="flex gap-3">
                            <a href="${link}" target="_blank" class="text-xs text-indigo-600 hover:underline">الرابط العام</a>
                            <button onclick="window.editTemplate('${tpl.id}')" class="text-xs text-green-600 hover:underline">تعديل في المحرر</button>
                            <button onclick="window.deleteTemplate('${tpl.id}')" class="text-xs text-red-500 hover:underline mr-auto">حذف</button>
                        </div>
                    `;
                    savedTemplatesList.appendChild(el);
                });
            }
        } catch (e) {
            console.error('Failed to load templates', e);
        }
    }

    // Delete template
    window.deleteTemplate = async (id) => {
        if (!confirm('هل أنت متأكد من رغبتك في حذف هذه البطاقة؟')) return;
        
        try {
            const res = await fetch(`api.php?action=delete_template&id=${id}`, { method: 'GET' });
            const data = await res.json();
            
            if (data.success) {
                if (currentTemplateId === id) {
                    // Reset editor if the active template is deleted
                    currentTemplateId = null;
                    document.getElementById('template-name').value = '';
                }
                loadSavedTemplates();
            } else {
                alert('فشل الحذف: ' + data.message);
            }
        } catch (e) {
            console.error(e);
            alert('حدث خطأ أثناء الحذف');
        }
    };

    // Load template into editor
    window.editTemplate = async (id) => {
        try {
            const res = await fetch(`api.php?action=get_template&id=${id}`);
            const data = await res.json();
            
            if (data.success) {
                const tpl = data.data;
                currentTemplateId = tpl.id;
                document.getElementById('template-name').value = tpl.name;
                
                currentImage = tpl.bg_image;
                canvasBg.src = currentImage;
                
                canvasBg.onload = () => {
                    placeholderText.classList.add('hidden');
                    canvasWrapper.classList.remove('hidden');
                    fieldsSection.classList.remove('hidden');
                    fieldsSection.classList.add('flex');
                    saveBtn.classList.remove('hidden');
                    
                    // Clear current fields
                    document.querySelectorAll('.draggable-text').forEach(el => el.remove());
                    fields = [];
                    fieldCounter = 0;
                    
                    if (tpl.fields && tpl.fields.length > 0) {
                        tpl.fields.forEach(f => {
                            // Extract numbers from id like "field_1" to update counter
                            const num = parseInt(f.id.replace('field_', ''));
                            if (!isNaN(num) && num > fieldCounter) fieldCounter = num;
                            
                            fields.push(f);
                            renderField(f);
                        });
                    }
                };
            }
        } catch (e) {
            console.error(e);
            alert('فشل في تحميل القالب');
        }
    };

    toggleSavedBtn.addEventListener('click', () => {
        if (savedTemplatesList.classList.contains('hidden')) {
            savedTemplatesList.classList.remove('hidden');
            savedTemplatesList.classList.add('flex');
            loadSavedTemplates();
        } else {
            savedTemplatesList.classList.add('hidden');
            savedTemplatesList.classList.remove('flex');
        }
    });

    // Upload Background
    bgUpload.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('bg_image', file);
        formData.append('action', 'upload');

        const res = await fetch('api.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            currentImage = data.url;
            currentTemplateId = null; // Reset to create new template
            document.getElementById('template-name').value = '';
            
            // Clear existing fields when new image is uploaded
            document.querySelectorAll('.draggable-text').forEach(el => el.remove());
            fields = [];
            fieldCounter = 0;
            
            // Set onload BEFORE src to avoid missing the event
            canvasBg.onload = () => {
                placeholderText.classList.add('hidden');
                canvasWrapper.classList.remove('hidden');
                fieldsSection.classList.remove('hidden');
                fieldsSection.classList.add('flex');
                saveBtn.classList.remove('hidden');
            };
            canvasBg.src = currentImage;

            // Show toast immediately after successful upload (guaranteed)
            showToast('✅ تم الحفظ في الاستديو');
        } else {
            alert('فشل رفع الصورة');
        }
    });

    // Add Field
    addFieldBtn.addEventListener('click', () => {
        const type = document.getElementById('field-type').value;
        const id = 'field_' + (++fieldCounter);
        
        const fieldData = {
            id: id,
            text: type,
            x: 50, // center %
            y: 50, // center %
            fontSize: 40, // px initially, but we can make it vw or just px relative to natural size
            color: '#000000',
            align: 'center',
            weight: '400'
        };
        
        fields.push(fieldData);
        renderField(fieldData);
    });

    // Render Field
    function renderField(fieldData) {
        const el = document.createElement('div');
        el.className = 'draggable-text';
        el.id = fieldData.id;
        el.innerText = fieldData.text;
        
        updateElementStyles(el, fieldData);
        
        // Setup Interact.js for this element
        interact(el).draggable({
            listeners: {
                move(event) {
                    const target = event.target;
                    const fData = fields.find(f => f.id === target.id);
                    
                    const wrapperRect = canvasWrapper.getBoundingClientRect();
                    
                    // Convert movement to percentages
                    const dx_pct = (event.dx / wrapperRect.width) * 100;
                    const dy_pct = (event.dy / wrapperRect.height) * 100;
                    
                    fData.x += dx_pct;
                    fData.y += dy_pct;
                    
                    updateElementStyles(target, fData);
                }
            }
        }).on('tap', function(event) {
            selectField(event.target.id);
            event.stopPropagation();
        });

        canvasWrapper.appendChild(el);
        selectField(fieldData.id);
    }

    function updateElementStyles(el, fieldData) {
        el.style.left = `${fieldData.x}%`;
        el.style.top = `${fieldData.y}%`;
        el.style.transform = `translate(-50%, -50%)`; // Center the anchor point
        el.style.fontSize = `${fieldData.fontSize}px`;
        el.style.color = fieldData.color;
        el.style.textAlign = fieldData.align;
        el.style.fontWeight = fieldData.weight;
    }

    // Deselect on wrapper click
    canvasWrapper.addEventListener('click', (e) => {
        if (e.target === canvasWrapper || e.target === canvasBg) {
            selectField(null);
        }
    });

    function selectField(id) {
        document.querySelectorAll('.draggable-text').forEach(el => el.classList.remove('active'));
        activeFieldId = id;

        if (id) {
            document.getElementById(id).classList.add('active');
            controlsSection.classList.remove('hidden');
            controlsSection.classList.add('flex');
            
            const fData = fields.find(f => f.id === id);
            document.getElementById('ctrl-size').value = fData.fontSize;
            document.getElementById('ctrl-color').value = fData.color;
            document.getElementById('ctrl-weight').value = fData.weight;
        } else {
            controlsSection.classList.add('hidden');
            controlsSection.classList.remove('flex');
        }
    }

    // Controls
    document.getElementById('ctrl-size').addEventListener('input', (e) => {
        if (!activeFieldId) return;
        const fData = fields.find(f => f.id === activeFieldId);
        fData.fontSize = e.target.value;
        updateElementStyles(document.getElementById(activeFieldId), fData);
    });

    document.getElementById('ctrl-color').addEventListener('input', (e) => {
        if (!activeFieldId) return;
        const fData = fields.find(f => f.id === activeFieldId);
        fData.color = e.target.value;
        updateElementStyles(document.getElementById(activeFieldId), fData);
    });

    document.getElementById('ctrl-weight').addEventListener('change', (e) => {
        if (!activeFieldId) return;
        const fData = fields.find(f => f.id === activeFieldId);
        fData.weight = e.target.value;
        updateElementStyles(document.getElementById(activeFieldId), fData);
    });

    document.querySelectorAll('.ctrl-align').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (!activeFieldId) return;
            const fData = fields.find(f => f.id === activeFieldId);
            fData.align = e.target.dataset.align;
            updateElementStyles(document.getElementById(activeFieldId), fData);
        });
    });

    document.getElementById('delete-field-btn').addEventListener('click', () => {
        if (!activeFieldId) return;
        fields = fields.filter(f => f.id !== activeFieldId);
        document.getElementById(activeFieldId).remove();
        selectField(null);
    });

    // Save Template
    saveBtn.addEventListener('click', async () => {
        const name = document.getElementById('template-name').value;
        if (!name) return alert('الرجاء إدخال اسم القالب');
        if (!currentImage) return alert('الرجاء رفع صورة');

        const payload = {
            action: 'save_template',
            id: typeof currentTemplateId !== 'undefined' ? currentTemplateId : null,
            name: name,
            bg_image: currentImage,
            fields_json: fields
        };

        const res = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await res.json();
        if (data.success) {
            const link = window.location.origin + '/view.php?id=' + data.id;
            alert('تم الحفظ بنجاح! رابط المشاركة:\n' + link);
            // Update list if it's open
            if (!savedTemplatesList.classList.contains('hidden')) {
                loadSavedTemplates();
            }
        } else {
            alert('خطأ في الحفظ');
        }
    });
});
