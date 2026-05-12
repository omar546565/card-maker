document.addEventListener('DOMContentLoaded', () => {
    // Utility to get element safely
    const getEl = (id) => document.getElementById(id);

    const bgUpload = getEl('bg-upload');
    const canvasWrapper = getEl('canvas-wrapper');
    const canvasBg = getEl('canvas-bg');
    const placeholderText = getEl('placeholder-text');
    const fieldsSection = getEl('fields-section');
    const controlsSection = getEl('controls-section');
    const saveBtn = getEl('save-template-btn');
    const addFieldBtn = getEl('add-field-btn');
    const fontUpload = getEl('font-upload');
    const fontUploadStatus = getEl('font-upload-status');
    const ctrlFontFamily = getEl('ctrl-font-family');
    const savedTemplatesList = getEl('saved-templates-list');
    
    let currentImage = null;
    let fields = [];
    let activeFieldId = null;
    let fieldCounter = 0;
    let currentTemplateId = null;
    let availableFonts = [];

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
    async function loadSavedTemplates() {
        if (!savedTemplatesList) return;
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
                    const nameEl = document.getElementById('template-name');
                    if (nameEl) nameEl.value = '';
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
                const nameEl2 = document.getElementById('template-name');
                if (nameEl2) nameEl2.value = tpl.name;
                
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

    // Sidebar Tabs Logic
    document.querySelectorAll('.sidebar-tab').forEach(tabBtn => {
        tabBtn.addEventListener('click', () => {
            const tabId = tabBtn.dataset.tab;
            
            // Update buttons
            document.querySelectorAll('.sidebar-tab').forEach(b => {
                b.classList.remove('border-indigo-600', 'text-indigo-600');
                b.classList.add('border-transparent', 'text-gray-500');
            });
            tabBtn.classList.add('border-indigo-600', 'text-indigo-600');
            tabBtn.classList.remove('border-transparent', 'text-gray-500');
            
            // Update content
            document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
            document.getElementById(tabId).classList.remove('hidden');
            
            if (tabId === 'tab-saved') loadSavedTemplates();
            if (tabId === 'tab-fonts') loadFonts();
        });
    });

    // Font Management
    async function loadFonts() {
        try {
            const res = await fetch('api.php?action=list_fonts');
            const data = await res.json();
            if (data.success) {
                availableFonts = data.data;
                updateFontDropdown();
                availableFonts.forEach(font => {
                    injectFontFace(font.name, font.file_path);
                });
            }
        } catch (e) {
            console.error('Failed to load fonts', e);
        }
    }

    function updateFontDropdown() {
        // Clear existing custom fonts, keep Cairo
        ctrlFontFamily.innerHTML = `<option value="'Cairo', sans-serif">Cairo (الافتراضي)</option>`;
        
        const fontsPreview = document.getElementById('fonts-list-preview');
        if (fontsPreview) fontsPreview.innerHTML = '';

        if (availableFonts.length === 0 && fontsPreview) {
            fontsPreview.innerHTML = '<p class="text-xs text-gray-400 text-center">لا توجد خطوط مرفوعة</p>';
        }

        availableFonts.forEach(font => {
            // Dropdown option
            const opt = document.createElement('option');
            opt.value = `'${font.name}', sans-serif`;
            opt.innerText = font.name;
            ctrlFontFamily.appendChild(opt);

            // Preview list
            if (fontsPreview) {
                const item = document.createElement('div');
                item.className = 'p-2 bg-gray-50 border rounded text-sm flex justify-between items-center';
                item.style.fontFamily = `'${font.name}', sans-serif`;
                item.innerHTML = `
                    <span>${font.name}</span>
                    <span class="text-[10px] text-gray-400">نموذج الخط</span>
                `;
                fontsPreview.appendChild(item);
            }
        });
    }

    function injectFontFace(name, url) {
        const id = 'font-face-' + name.replace(/\s+/g, '-');
        if (document.getElementById(id)) return;
        
        const style = document.createElement('style');
        style.id = id;
        style.innerHTML = `
            @font-face {
                font-family: '${name}';
                src: url('${url}');
                font-display: swap;
            }
        `;
        document.head.appendChild(style);
    }

    fontUpload.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        fontUploadStatus.innerText = 'جاري الرفع...';
        fontUploadStatus.className = 'text-xs mt-1 text-indigo-600';

        const formData = new FormData();
        formData.append('font_file', file);
        formData.append('action', 'upload_font');

        try {
            const res = await fetch('api.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                fontUploadStatus.innerText = 'تم رفع الخط بنجاح!';
                fontUploadStatus.className = 'text-xs mt-1 text-green-600';
                availableFonts.push(data.font);
                updateFontDropdown();
                injectFontFace(data.font.name, data.font.file_path);
            } else {
                fontUploadStatus.innerText = 'فشل الرفع: ' + data.message;
                fontUploadStatus.className = 'text-xs mt-1 text-red-600';
            }
        } catch (e) {
            fontUploadStatus.innerText = 'حدث خطأ أثناء الرفع';
            fontUploadStatus.className = 'text-xs mt-1 text-red-600';
        }
    });

    // Initial load
    loadFonts();

    // Upload Background
    if (bgUpload) {
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
                const nameInp = document.getElementById('template-name');
                if (nameInp) nameInp.value = '';
                
                // Clear existing fields when new image is uploaded
                document.querySelectorAll('.draggable-text').forEach(el => el.remove());
                fields = [];
                fieldCounter = 0;
                
                // Set onload BEFORE src to avoid missing the event
                if (canvasBg) {
                    canvasBg.onload = () => {
                        placeholderText?.classList.add('hidden');
                        canvasWrapper?.classList.remove('hidden');
                        fieldsSection?.classList.remove('hidden');
                        fieldsSection?.classList.add('flex');
                        saveBtn?.classList.remove('hidden');
                    };
                    canvasBg.src = currentImage;
                }

                // Show toast immediately after successful upload (guaranteed)
                showToast('✅ تم الحفظ في الاستديو');
            } else {
                alert('فشل رفع الصورة');
            }
        });
    }

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
            fontFamily: "'Cairo', sans-serif",
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
        el.style.fontFamily = fieldData.fontFamily || "'Cairo', sans-serif";
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
            document.getElementById('ctrl-font-family').value = fData.fontFamily || "'Cairo', sans-serif";
            document.getElementById('ctrl-weight').value = fData.weight;
        } else {
            controlsSection.classList.add('hidden');
            controlsSection.classList.remove('flex');
        }
    }

    // Controls
    const ctrlSize = document.getElementById('ctrl-size');
    if (ctrlSize) {
        ctrlSize.addEventListener('input', (e) => {
            if (!activeFieldId) return;
            const fData = fields.find(f => f.id === activeFieldId);
            fData.fontSize = e.target.value;
            updateElementStyles(document.getElementById(activeFieldId), fData);
        });
    }

    const ctrlColor = document.getElementById('ctrl-color');
    if (ctrlColor) {
        ctrlColor.addEventListener('input', (e) => {
            if (!activeFieldId) return;
            const fData = fields.find(f => f.id === activeFieldId);
            fData.color = e.target.value;
            updateElementStyles(document.getElementById(activeFieldId), fData);
        });
    }

    if (ctrlFontFamily) {
        ctrlFontFamily.addEventListener('change', (e) => {
            if (!activeFieldId) return;
            const fData = fields.find(f => f.id === activeFieldId);
            fData.fontFamily = e.target.value;
            updateElementStyles(document.getElementById(activeFieldId), fData);
        });
    }

    const ctrlWeight = document.getElementById('ctrl-weight');
    if (ctrlWeight) {
        ctrlWeight.addEventListener('change', (e) => {
            if (!activeFieldId) return;
            const fData = fields.find(f => f.id === activeFieldId);
            fData.weight = e.target.value;
            updateElementStyles(document.getElementById(activeFieldId), fData);
        });
    }

    document.querySelectorAll('.ctrl-align').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (!activeFieldId) return;
            const fData = fields.find(f => f.id === activeFieldId);
            fData.align = e.target.dataset.align;
            updateElementStyles(document.getElementById(activeFieldId), fData);
        });
    });

    const deleteFieldBtn = document.getElementById('delete-field-btn');
    if (deleteFieldBtn) {
        deleteFieldBtn.addEventListener('click', () => {
            if (!activeFieldId) return;
            fields = fields.filter(f => f.id !== activeFieldId);
            document.getElementById(activeFieldId).remove();
            selectField(null);
        });
    }

    // Save Template
    if (saveBtn) {
      saveBtn.addEventListener('click', async () => {
        const name = document.getElementById('template-name')?.value;
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
            if (savedTemplatesList && !savedTemplatesList.classList.contains('hidden')) {
                loadSavedTemplates();
            }
        } else {
            alert('خطأ في الحفظ');
        }
      });
    } // end if(saveBtn)

    // Reset Database Logic
    const resetDbBtn = document.getElementById('reset-db-btn');
    if (resetDbBtn) {
        resetDbBtn.addEventListener('click', async () => {
            const confirm1 = confirm('⚠️ تنبيه: سيتم مسح كافة البطاقات والخطوط المرفوعة نهائياً. هل أنت متأكد؟');
            if (!confirm1) return;
            
            const confirm2 = confirm('تأكيد نهائي: هل تريد حقاً تصفير قاعدة البيانات بالكامل؟ لا يمكن التراجع عن هذه الخطوة.');
            if (!confirm2) return;

            try {
                const res = await fetch('api.php?action=reset_db');
                const data = await res.json();
                if (data.success) {
                    alert('تم تصفير البيانات بنجاح. سيتم إعادة تحميل الصفحة.');
                    window.location.reload();
                } else {
                    alert('فشل تصفير البيانات');
                }
            } catch (e) {
                console.error(e);
                alert('حدث خطأ أثناء الاتصال بالسيرفر');
            }
        });
    }
});
