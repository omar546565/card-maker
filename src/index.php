<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>محرر البطاقات - الإدارة</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>
    <style>
        body { font-family: 'Cairo', sans-serif; }
        #canvas-wrapper { position: relative; display: inline-block; }
        #canvas-bg { display: block; max-width: 100%; height: auto; }
        .draggable-text {
            position: absolute;
            cursor: move;
            border: 2px dashed transparent;
            padding: 5px;
            white-space: nowrap;
            touch-action: none;
            transform-origin: center center;
        }
        .draggable-text:hover, .draggable-text.active {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

<div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <div class="w-80 bg-white shadow-lg p-6 overflow-y-auto flex flex-col gap-6">
        <h1 class="text-2xl font-bold text-indigo-600 border-b pb-4">محرر القوالب</h1>
        
        <div class="border-b pb-4">
            <button id="toggle-saved-btn" class="w-full bg-gray-50 text-indigo-700 font-semibold py-2 rounded border border-indigo-200 hover:bg-indigo-50 transition">عرض البطاقات المحفوظة ⬇️</button>
            <div id="saved-templates-list" class="hidden flex-col gap-2 mt-3 max-h-48 overflow-y-auto p-1">
                <p class="text-sm text-gray-500 text-center py-2">جاري التحميل...</p>
            </div>
        </div>
        
        <div>
            <label class="block text-sm font-semibold mb-2">1. رفع صورة الخلفية</label>
            <input type="file" id="bg-upload" accept="image/png, image/jpeg" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
        </div>

        <div id="fields-section" class="hidden flex-col gap-4 border-t pt-4">
            <label class="block text-sm font-semibold">2. إضافة حقول (متغيرات)</label>
            <select id="field-type" class="w-full border rounded p-2">
                <option value="{{name}}">الاسم</option>
                <option value="{{date}}">التاريخ</option>
                <option value="{{event_name}}">اسم المناسبة</option>
                <option value="{{custom_text}}">اسم المدعو</option>
            </select>
            <button id="add-field-btn" class="bg-indigo-600 text-white py-2 rounded shadow hover:bg-indigo-700 transition">إضافة الحقل</button>
        </div>

        <!-- Controls for selected text -->
        <div id="controls-section" class="hidden flex-col gap-4 border-t pt-4">
            <h3 class="font-semibold text-sm text-gray-700">خصائص النص المحدد</h3>
            
            <label class="text-xs">حجم الخط</label>
            <input type="range" id="ctrl-size" min="10" max="150" class="w-full">
            
            <label class="text-xs">لون الخط</label>
            <input type="color" id="ctrl-color" class="w-full h-10 p-1 border rounded">

            <label class="text-xs">المحاذاة</label>
            <div class="flex gap-2">
                <button class="ctrl-align flex-1 border p-1 rounded" data-align="right">يمين</button>
                <button class="ctrl-align flex-1 border p-1 rounded" data-align="center">وسط</button>
                <button class="ctrl-align flex-1 border p-1 rounded" data-align="left">يسار</button>
            </div>
            
            <label class="text-xs">السمك (Bold)</label>
            <select id="ctrl-weight" class="w-full border rounded p-1">
                <option value="400">عادي</option>
                <option value="700">عريض</option>
            </select>

            <button id="delete-field-btn" class="bg-red-500 text-white py-2 rounded shadow mt-2">حذف الحقل</button>
        </div>

        <div class="mt-auto border-t pt-4">
            <label class="block text-sm font-semibold mb-2">اسم القالب</label>
            <input type="text" id="template-name" placeholder="مثال: دعوة زفاف" class="w-full border rounded p-2 mb-4">
            <button id="save-template-btn" class="w-full bg-green-600 text-white py-3 rounded-lg shadow-lg font-bold hover:bg-green-700 transition hidden">حفظ واستخراج الرابط</button>
        </div>
    </div>

    <!-- Canvas Area -->
    <div class="flex-1 flex justify-center items-center bg-gray-200 overflow-auto p-8 relative" id="main-area">
        <p id="placeholder-text" class="text-gray-400 text-lg">قم برفع صورة للبدء</p>
        <div id="canvas-wrapper" class="hidden bg-white shadow-2xl">
            <img id="canvas-bg" src="" alt="Background">
            <!-- Fields will be injected here -->
        </div>
    </div>
</div>

<script src="js/editor.js"></script>
</body>
</html>
