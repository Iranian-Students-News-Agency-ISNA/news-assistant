<?php
// ===== تنظیمات دستیار خبری =====
// این فایل رو کپی کن به config.php و مقادیر واقعی رو جایگزین کن.
// cp config.example.php config.php

// این فایل نباید مستقیماً از بیرون قابل دسترسی باشد.

define('OPENROUTER_API_KEY', 'sk-or-v1-YOUR-API-KEY-HERE');
// لیست مدل‌های رایگان OpenRouter مدام عوض می‌شه؛ روتر خودکار رایگان خودشون همیشه یه مدل در دسترس رو انتخاب می‌کنه
define('OPENROUTER_MODEL', 'openrouter/free');
// define('OPENROUTER_MODEL', 'openai/gpt-4o'); // نمونه‌ی مدل پولی و باکیفیت‌تر

define('SITE_URL', 'https://yourdomain.com');
define('SITE_NAME', 'Your Site Name');

// مسیر فایل news.json پروژه isnabot روی هاست.
// پیش‌فرض: فرض شده isnabot کنار پوشه ai قرار دارد (public_html/isnabot).
// اگر مسیر واقعی فرق دارد، همین یک خط را اصلاح کن:
define('ISNABOT_NEWS_FILE', __DIR__ . '/../../isnabot/data/news.json');
