<?php
set_time_limit(60);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

function out($arr) { echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
$query = trim($body['query'] ?? ($_POST['query'] ?? ''));

if ($query === '') out(['ok' => false, 'error' => 'سوال خالی است.']);
if (mb_strlen($query) > 300) out(['ok' => false, 'error' => 'سوال خیلی طولانی است.']);

if (!is_file(ISNABOT_NEWS_FILE)) {
    out(['ok' => false, 'error' => 'به داده‌های خبری دسترسی نیست. مسیر ISNABOT_NEWS_FILE را در config.php بررسی کن.']);
}
$news = json_decode(file_get_contents(ISNABOT_NEWS_FILE), true);
if (!is_array($news) || count($news) === 0) {
    out(['ok' => false, 'error' => 'در حال حاضر خبری در دسترس نیست.']);
}

// بازه‌ی زمانیِ داده‌ها: از آخرین پاکسازی کش (نیمه‌شب) تا الان
$midnight = strtotime('today');
$hours = round((time() - $midnight) / 3600, 1);

// ===== پیش‌فیلتر محلی (بدون هوش مصنوعی) برای کوچک‌کردن حجم ارسالی به Gemini =====
// این کار هم سرعت پاسخ رو زیاد می‌کنه (جلوگیری از تایم‌اوت) هم مصرف توکن API رو کم می‌کنه.
$stopwords = ['از', 'به', 'در', 'با', 'را', 'که', 'چه', 'خبر', 'برای', 'و', 'است', 'یک', 'های', 'این', 'آیا'];
$words = preg_split('/[\s\x{060C}\x{061B}،؛,.؟?!]+/u', $query, -1, PREG_SPLIT_NO_EMPTY);
$words = array_values(array_diff(array_map('mb_strtolower', $words), $stopwords));
if (empty($words)) $words = [mb_strtolower($query)];

$scored = [];
foreach ($news as $i => $n) {
    $hay = mb_strtolower(($n['title'] ?? '') . ' ' . ($n['description'] ?? '') . ' ' .
        ($n['service_name'] ?? '') . ' ' . ($n['sub_name'] ?? ''));
    $score = 0;
    foreach ($words as $w) {
        if ($w !== '' && mb_strpos($hay, $w) !== false) $score++;
    }
    if ($score > 0) $scored[] = ['i' => $i, 'score' => $score];
}
usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
$shortlist = array_slice($scored, 0, 20);

// اگر با فیلتر کلمه‌کلیدی چیزی پیدا نشد، مستقیم پاسخ خالی برگردون (نیازی به تماس با Gemini نیست)
if (empty($shortlist)) {
    out(['ok' => true, 'results' => [], 'hours' => $hours]);
}

// فهرست فشرده برای ارسال به مدل (فقط شورت‌لیست، برای صرفه‌جویی در توکن و سرعت)
$compact = [];
foreach ($shortlist as $s) {
    $n = $news[$s['i']];
    $compact[] = [
        'i' => $s['i'],
        'title' => $n['title'] ?? '',
        'cat' => trim(($n['service_name'] ?? '') . ' / ' . ($n['sub_name'] ?? ''), ' /'),
        'desc' => mb_substr($n['description'] ?? '', 0, 160),
    ];
}

$prompt = "تو یک دستیار خبری فارسی هستی. در ادامه یک فهرست JSON از اخبار امروز ایسنا (هرکدام با شناسه i) آمده. " .
    "کاربر این سوال را پرسیده: \"" . $query . "\"\n\n" .
    "۱) اول یک پاسخ مستقیم و در حد یک خط به خودِ سوال کاربر بده (اگر رقم، تاریخ یا عدد مشخصی توی اخبار هست حتماً همون رو بیار؛ کلی‌گویی نکن).\n" .
    "۲) بعد حداکثر ۴ خبری که این پاسخ رو تایید/پشتیبانی می‌کنند به‌عنوان منبع انتخاب کن و برای هرکدام یک توضیح ۱ خطی بنویس که بگه این خبر چه ربطی به پاسخ داره.\n" .
    "اگر هیچ خبر مرتبطی نیست، answer را خالی و items را آرایه‌ی خالی بگذار.\n" .
    "فقط و فقط یک JSON دقیقاً به این شکل خروجی بده، بدون هیچ توضیح اضافه:\n" .
    '{"answer": "...", "items": [{"i": 0, "summary": "..."}]}' . "\n\nفهرست اخبار:\n" . json_encode($compact, JSON_UNESCAPED_UNICODE);

function callOpenRouter($prompt) {
    $payload = [
        'model' => OPENROUTER_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => 'خروجی تو فقط باید یک JSON معتبر باشد، دقیقاً طبق فرمت خواسته‌شده، بدون هیچ متن یا توضیح اضافه و بدون ```.'],
            ['role' => 'user', 'content' => $prompt],
        ],
        'response_format' => ['type' => 'json_object'],
        'max_tokens' => 1024,
        'reasoning' => ['enabled' => false],
    ];
    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_TIMEOUT => 25,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENROUTER_API_KEY,
            'HTTP-Referer: ' . SITE_URL,
            'X-Title: ' . SITE_NAME,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($res === false) return [false, $err];
    error_log('news-assistant openrouter raw response: ' . $res);
    $data = json_decode($res, true);
    $text = $data['choices'][0]['message']['content'] ?? null;
    return [$text, $text ? null : ('پاسخ خالی از OpenRouter: ' . ($res ?: ''))];
}

// چون گاهی اتصال هاست به سرویس AI به‌طور موقت قطع/کند می‌شه، با چند تلاش کوتاه مقاوم‌ترش می‌کنیم
$text = false; $err = '';
for ($attempt = 1; $attempt <= 2; $attempt++) {
    [$text, $err] = callOpenRouter($prompt);
    if ($text !== false) break;
    usleep(400000);
}

if ($text === false) out(['ok' => false, 'error' => 'خطا در ارتباط با سرویس هوش مصنوعی: ' . $err]);

// برخی مدل‌ها گاهی JSON را داخل ```json ... ``` می‌فرستند؛ این خط پاکش می‌کند
$cleanText = trim(preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($text)));
$picked = json_decode($cleanText, true);
if (!is_array($picked)) {
    error_log('news-assistant parse fail, raw text: ' . $text);
    out(['ok' => false, 'error' => 'خطا در پردازش پاسخ مدل.', 'debug' => $text]);
}

$answer = trim($picked['answer'] ?? '');
$items = $picked['items'] ?? [];

$results = [];
foreach ($items as $p) {
    $idx = $p['i'] ?? null;
    if ($idx === null || !isset($news[$idx])) continue;
    $n = $news[$idx];
    $results[] = [
        'title' => $n['title'] ?? '',
        'link' => $n['link'] ?? '',
        'cat' => trim(($n['service_name'] ?? '') . ' / ' . ($n['sub_name'] ?? ''), ' /'),
        'summary' => $p['summary'] ?? '',
    ];
}

out(['ok' => true, 'answer' => $answer, 'results' => $results, 'hours' => $hours]);
