<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء البطاقة</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html-to-image/1.11.11/html-to-image.min.js"></script>
    <style>
        body { font-family: 'Cairo', sans-serif; }
        /* The container must match the natural aspect ratio exactly, and be large enough to render high res */
        #result-container {
            position: absolute;
            left: -9999px; /* Hide from viewport but keep rendering */
            top: 0;
            display: inline-block;
        }
        .dynamic-field {
            position: absolute;
            white-space: nowrap;
            transform: translate(-50%, -50%);
        }
        
        /* Preview container scales it down for viewing */
        #preview-wrapper {
            position: relative;
            width: 100%;
            max-width: 500px; /* Mobile friendly */
            margin: 0 auto;
            overflow: hidden;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        #preview-wrapper img.bg { width: 100%; display: block; }
        .preview-field {
            position: absolute;
            white-space: nowrap;
            transform: translate(-50%, -50%);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen p-4 flex justify-center items-center">

<div class="max-w-4xl w-full bg-white rounded-2xl shadow-xl overflow-hidden flex flex-col md:flex-row">
    
    <!-- Form Side -->
    <div class="p-8 md:w-1/2 flex flex-col gap-6" id="form-container">
        <div>
            <h1 class="text-2xl font-bold text-indigo-700" id="title">جاري التحميل...</h1>
            <p class="text-gray-500 text-sm mt-1">يرجى تعبئة البيانات المطلوبة لاستخراج البطاقة</p>
        </div>
        
        <form id="data-form" class="flex flex-col gap-4">
            <!-- Dynamic inputs will be added here based on template fields -->
        </form>

        <button id="generate-btn" class="mt-4 bg-indigo-600 text-white font-bold py-3 rounded-lg shadow-lg hover:bg-indigo-700 transition">إنشاء البطاقة (معاينة)</button>
        <button id="download-btn" class="hidden bg-green-600 text-white font-bold py-3 rounded-lg shadow-lg hover:bg-green-700 transition">تحميل الصورة (PNG)</button>
    </div>

    <!-- Preview Side -->
    <div class="p-8 md:w-1/2 bg-gray-100 flex justify-center items-center border-r">
        <div id="preview-wrapper" class="hidden relative w-full">
            <img id="preview-bg" class="bg" src="">
            <div id="preview-fields-container" class="absolute top-0 left-0 w-full h-full"></div>
        </div>
        <div id="final-image-container" class="hidden w-full max-w-md mx-auto">
            <img id="final-result-img" class="w-full rounded shadow-lg" src="">
        </div>
    </div>
</div>

<!-- Hidden container for High-Res Rendering -->
<div id="result-container" style="position: fixed; left: 0; top: 0; z-index: -100;">
    <div style="position: relative; display: inline-block;">
        <img id="render-bg" src="" style="display:block;" loading="eager">
        <div id="render-fields-container" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const urlParams = new URLSearchParams(window.location.search);
    const id = urlParams.get('id');
    
    if (!id) {
        document.getElementById('title').innerText = 'الرابط غير صالح';
        return;
    }

    // Fetch Template Data
    const res = await fetch(`api.php?action=get_template&id=${id}`);
    const result = await res.json();
    
    if (!result.success) {
        document.getElementById('title').innerText = 'القالب غير موجود';
        return;
    }

    const template = result.data;
    document.getElementById('title').innerText = template.name;
    
    // Set up form fields dynamically based on variables like {{name}}
    const form = document.getElementById('data-form');
    const fields = template.fields; // Array of objects
    
    // Extract unique variables
    const variables = [...new Set(fields.map(f => f.text))];
    
    variables.forEach(v => {
        let label = v.replace(/{{|}}/g, '');
        // Translations
        if(label === 'name') label = 'الاسم';
        if(label === 'date') label = 'التاريخ';
        if(label === 'event_name') label = 'المناسبة';
        if(label === 'custom_text') label = 'اسم المدعو';
        
        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <label class="block text-sm font-semibold text-gray-700 mb-1">${label}</label>
            <input type="text" data-var="${v}" class="w-full border rounded-lg p-2 focus:ring-2 focus:ring-indigo-500 outline-none" required>
        `;
        form.appendChild(wrapper);
    });

    // Set backgrounds
    const previewBg = document.getElementById('preview-bg');
    const renderBg = document.getElementById('render-bg');
    previewBg.src = template.bg_image;
    renderBg.src = template.bg_image; // Same image for high res rendering

    document.getElementById('preview-wrapper').classList.remove('hidden');

    // Generate Preview function
    function buildFields(container, isHighRes) {
        container.innerHTML = '';
        fields.forEach(f => {
            const el = document.createElement('div');
            // get value from input
            const input = document.querySelector(`input[data-var="${f.text}"]`);
            const val = input ? input.value : f.text;
            
            el.innerText = val || ' '; // Keep space to render height
            
            // Set styles
            el.className = isHighRes ? 'dynamic-field' : 'preview-field';
            el.style.left = `${f.x}%`;
            el.style.top = `${f.y}%`;
            el.style.color = f.color;
            el.style.textAlign = f.align;
            el.style.fontWeight = f.weight;
            
            // For preview, we scale font size down relative to preview container width
            // This is complex, better approach: use 'vw' or percentages? 
            // Standard approach: use px, but for preview we will just let it scale if we used % for font or we scale it dynamically
            // For simplicity, we just use the raw fontSize for render, and a scaled down for preview.
            if (isHighRes) {
                el.style.fontSize = `${f.fontSize}px`;
            } else {
                // Approximate scaling for preview
                el.style.fontSize = `calc(${f.fontSize}px * 0.4)`; // Assuming preview is ~40% of original
            }
            
            container.appendChild(el);
        });
    }

    document.getElementById('generate-btn').addEventListener('click', async () => {
        // Build preview
        buildFields(document.getElementById('preview-fields-container'), false);
        
        // Build High-Res Render DOM
        buildFields(document.getElementById('render-fields-container'), true);
        
        document.getElementById('generate-btn').innerText = 'جاري التوليد...';
        
        try {
            // Wait for images to be fully ready
            const resultContainer = document.getElementById('result-container');
            
            // We use html-to-image on the hidden full-size container
            const dataUrl = await htmlToImage.toPng(resultContainer, {
                quality: 1.0,
                pixelRatio: 2 // High resolution export
            });

            // Show final image
            document.getElementById('preview-wrapper').classList.add('hidden');
            document.getElementById('final-image-container').classList.remove('hidden');
            document.getElementById('final-result-img').src = dataUrl;

            // Show download button
            document.getElementById('download-btn').classList.remove('hidden');
            
            // Setup download
            document.getElementById('download-btn').onclick = () => {
                const a = document.createElement('a');
                a.href = dataUrl;
                a.download = `card_${Date.now()}.png`;
                a.click();
            };
        } catch (error) {
            console.error('Error generating image:', error);
            alert('حدث خطأ أثناء التوليد');
        } finally {
            document.getElementById('generate-btn').innerText = 'إعادة التوليد';
        }
    });

    // Initial build (empty)
    buildFields(document.getElementById('preview-fields-container'), false);
});
</script>
</body>
</html>
