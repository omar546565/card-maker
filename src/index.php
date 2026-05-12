<?php
session_start();

// كلمة المرور الافتراضية (يمكنك تغييرها)
$password = 'itkan@2026';

if (isset($_POST['password']) && $_POST['password'] === $password) {
    $_SESSION['authenticated'] = true;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

if (empty($_SESSION['authenticated'])) {
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - محرر البطاقات</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded-lg shadow-xl w-96 border-t-4 border-indigo-600">
        <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">تسجيل الدخول للمحرر</h2>
        <?php if(isset($_POST['password'])) echo '<p class="text-red-500 mb-4 text-sm text-center font-semibold">كلمة المرور غير صحيحة</p>'; ?>
        <form method="POST">
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">كلمة المرور</label>
                <input type="password" name="password" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2 border" required placeholder="أدخل كلمة المرور...">
            </div>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-md transition duration-200">دخول</button>
        </form>
    </div>
</body>
</html>
<?php
    exit;
}
?>
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
        <div class="flex justify-between items-center border-b pb-4">
            <h1 class="text-2xl font-bold text-indigo-600">محرر القوالب</h1>
            <a href="?logout=1" class="text-sm text-red-500 hover:text-red-700 font-bold px-2 py-1 bg-red-50 rounded">خروج</a>
        </div>
        
        <!-- Sidebar Tabs -->
        <div class="flex border-b mb-4">
            <button class="sidebar-tab flex-1 py-2 text-sm font-bold border-b-2 border-indigo-600 text-indigo-600" data-tab="tab-main">الرئيسية</button>
            <button class="sidebar-tab flex-1 py-2 text-sm font-bold border-b-2 border-transparent text-gray-500 hover:text-indigo-600" data-tab="tab-fonts">الخطوط</button>
            <button class="sidebar-tab flex-1 py-2 text-sm font-bold border-b-2 border-transparent text-gray-500 hover:text-indigo-600" data-tab="tab-saved">المحفوظة</button>
        </div>

        <!-- Main Tab Content -->
        <div id="tab-main" class="tab-content flex flex-col gap-6">
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
        </div>

        <!-- Fonts Tab Content -->
        <div id="tab-fonts" class="tab-content hidden flex flex-col gap-4">
            <h3 class="font-bold text-indigo-600 border-b pb-2">إدارة الخطوط المخصصة</h3>
            <div>
                <label class="block text-sm font-semibold mb-2">رفع خط جديد (.ttf, .otf)</label>
                <input type="file" id="font-upload" accept=".ttf,.otf,.woff,.woff2" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                <p id="font-upload-status" class="text-xs mt-1"></p>
            </div>
            
            <div class="mt-4">
                <label class="text-xs font-bold text-gray-500">الخطوط المتاحة حالياً:</label>
                <div id="fonts-list-preview" class="flex flex-col gap-2 mt-2 max-h-60 overflow-y-auto">
                    <p class="text-xs text-gray-400 text-center">لا توجد خطوط مرفوعة</p>
                </div>
            </div>
        </div>

        <!-- Saved Templates Tab Content -->
        <div id="tab-saved" class="tab-content hidden flex flex-col gap-4">
            <h3 class="font-bold text-indigo-600 border-b pb-2">البطاقات المحفوظة</h3>
            <div id="saved-templates-list" class="flex flex-col gap-2 p-1 max-h-[400px] overflow-y-auto">
                <p class="text-sm text-gray-500 text-center py-2">جاري التحميل...</p>
            </div>
        </div>


        <!-- Controls for selected text -->
        <div id="controls-section" class="hidden flex-col gap-4 border-t pt-4">
            <h3 class="font-semibold text-sm text-gray-700">خصائص النص المحدد</h3>
            
            <label class="text-xs">حجم الخط</label>
            <input type="range" id="ctrl-size" min="10" max="150" class="w-full">
            
            <label class="text-xs">لون الخط</label>
            <input type="color" id="ctrl-color" class="w-full h-10 p-1 border rounded">

            <label class="text-xs">نوع الخط</label>
            <select id="ctrl-font-family" class="w-full border rounded p-1">
                <option value="'Cairo', sans-serif">Cairo (الافتراضي)</option>
                <!-- Uploaded fonts will be added here -->
            </select>

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
