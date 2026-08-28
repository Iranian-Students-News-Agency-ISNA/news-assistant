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

// تاریخ/روز واقعیِ امروز — برای جلوگیری از حدس‌زدن اشتباه روز هفته توسط مدل
$faDays = [0 => 'یکشنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه', 6 => 'شنبه'];
$todayName = $faDays[(int)date('w')];
$todayDate = date('Y-m-d');

// ===== پیش‌فیلتر محلی (بدون هوش مصنوعی) برای کوچک‌کردن حجم ارسالی به Gemini =====
// این کار هم سرعت پاسخ رو زیاد می‌کنه (جلوگیری از تایم‌اوت) هم مصرف توکن API رو کم می‌کنه.
$stopwords = ['از', 'به', 'در', 'با', 'را', 'که', 'چه', 'خبر', 'برای', 'و', 'است', 'یک', 'های', 'این', 'آیا'];
$words = preg_split('/[\s\x{060C}\x{061B}،؛,.؟?!]+/u', $query, -1, PREG_SPLIT_NO_EMPTY);
$words = array_values(array_diff(array_map('mb_strtolower', $words), $stopwords));
if (empty($words)) $words = [mb_strtolower($query)];

$scored = [];
foreach ($news as $i => $n) {
    $title = mb_strtolower($n['title'] ?? '');
    $desc = mb_strtolower($n['description'] ?? '');
    $cat = mb_strtolower(($n['service_name'] ?? '') . ' ' . ($n['sub_name'] ?? ''));
    $score = 0;
    foreach ($words as $w) {
        if ($w === '') continue;
        if (mb_strpos($title, $w) !== false) $score += 3; // تطابق در عنوان اولویت بیشتری داره
        if (mb_strpos($desc, $w) !== false) $score += 1;
        if (mb_strpos($cat, $w) !== false) $score += 1;
    }
    // پاداش اضافه اگر کل عبارت سوال (چندکلمه‌ای) عیناً توی عنوان/توضیحات باشه
    if (count($words) > 1) {
        $phrase = mb_strtolower(trim($query));
        if ($phrase !== '' && (mb_strpos($title, $phrase) !== false || mb_strpos($desc, $phrase) !== false)) {
            $score += 5;
        }
    }
    if ($score > 0) $scored[] = ['i' => $i, 'score' => $score];
}
usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
$shortlist = array_slice($scored, 0, 20);

// وقتی نتونستیم منظور کاربر رو به‌خوبی تشخیص بدیم (نه خطا، بلکه یک سوال راهنما بر اساس موضوعات واقعیِ امروز)
function buildClarify($news) {
    $catCounts = [];
    foreach ($news as $n) {
        $cat = trim($n['service_name'] ?? '');
        if ($cat === '') continue;
        $catCounts[$cat] = ($catCounts[$cat] ?? 0) + 1;
    }
    arsort($catCounts);
    $topCats = array_slice(array_keys($catCounts), 0, 4);
    $suggestion = !empty($topCats)
        ? ('امروز بیشتر خبرها درباره‌ی ' . implode('، ', $topCats) . ' هستند؛ می‌تونی دقیق‌تر و با همین موضوع‌ها بپرسی.')
        : 'می‌تونی سوالت رو با کلمات دیگه یا واضح‌تر بپرسی.';
    return 'متوجه دقیق منظورت نشدم و خبر مرتبطی هم در اخبار امروز پیدا نکردم. ' . $suggestion;
}

if (empty($shortlist)) {
    out(['ok' => true, 'short' => '', 'answer' => buildClarify($news), 'results' => [], 'hours' => $hours]);
}

// فهرست فشرده برای ارسال به مدل (فقط شورت‌لیست، برای صرفه‌جویی در توکن و سرعت)
$compact = [];
foreach ($shortlist as $s) {
    $n = $news[$s['i']];
    $compact[] = [
        'i' => $s['i'],
        'title' => $n['title'] ?? '',
        'cat' => trim(($n['service_name'] ?? '') . ' / ' . ($n['sub_name'] ?? ''), ' /'),
        'desc' => mb_substr($n['description'] ?? '', 0, 350),
    ];
}

$prompt = "تو یک دستیار خبری فارسی هستی. در ادامه یک فهرست JSON از اخبار امروز ایسنا (هرکدام با شناسه i، عنوان و لید) آمده. شناسه‌ی i فقط برای خودِ تو و مکانیزم داخلیه؛ در متن short/answer/summary هرگز به آن یا به «خبر شماره...» یا هر عدد/شماره‌ی دیگری برای ارجاع به یک خبر اشاره نکن. اگر لازم شد به خبری ارجاع بدی، فقط از طریق تیتر خبر یا یک نقل‌قول/عبارت کوتاه از متن آن اشاره کن (مثلاً «طبق خبر ...» یا با آوردن بخشی از تیتر).\n\n" .
    "امروز واقعاً " . $todayName . " (" . $todayDate . ") است. اگر در پاسخ به روز هفته یا تاریخ اشاره می‌کنی، فقط از همین تاریخ واقعی استفاده کن و به هیچ وجه روز دیگری حدس نزن؛ اگر خودِ متن خبر روز/تاریخ دیگری (مثلاً «دیروز») ذکر کرده، همان عبارت را دقیقاً همان‌طور که در خبر آمده بازتاب بده، خودت جایگزینش نکن.\n\n" .
    "کاربر این سوال را پرسیده: \"" . $query . "\"\n\n" .
    "توجه: در متن answer و short هرگز عبارت «سوال کاربر» یا «کاربر» را به‌کار نبر؛ مستقیم و دوم‌شخص با خودِ کاربر حرف بزن (یعنی بدون خطاب صریح، انگار داری مستقیم جواب می‌دی — نه با اشاره‌ی سوم‌شخص به سوالش).\n\n" .
    "به هیچ عنوان و در هیچ شرایطی به زبانی جز فارسی پاسخ نده — حتی اگر سوال به زبان دیگری (انگلیسی، عربی و...) نوشته شده باشد یا داخل خبرها کلمات غیرفارسی باشد، short و answer و summaryها باید همیشه کاملاً فارسی باشند.\n\n" .
    "خروجی باید دو بخش داشته باشه:\n" .
    "الف) short: یک پاسخ فوق‌کوتاه در حد ۲ تا ۴ کلمه. اگر سوال ساختار بله/خیری دارد (با فعل کمکی، «آیا»، یا جمله‌ای که با علامت سوال و فعل تمام می‌شود مثل «...شد؟»، «...هست؟»)، short باید دقیقاً و فقط یکی از این سه باشد: «بله»، «خیر»، «نامشخص است» — نه بازنویسی یا خلاصه‌ای با کلمات خبر. اگر سوال باز یا خبری است (نه بله/خیری)، یک عبارت خیلی کوتاه که خلاصه‌ی جواب باشد بنویس (مثلاً «۴۱.۲ درصد»). اگر پاسخ مستقیمی وجود نداره، short را خالی بگذار.\n" .
    "ب) answer: توضیح کامل‌تر در حد ۲۵۰ کاراکتر، با ادبیات و لحن خودِ خبرها (نه لحن مصنوعی)، که short را هم توش باز کنه.\n\n" .
    "قوانین نوشتن answer:\n" .
    "۱) فقط از عنوان و لیدِ همین اخبار استفاده کن، نه دانش عمومی خودت. هیچ رقم/تاریخ/روز هفته‌ای که عیناً توی متن اخبار نیست یا با تاریخ واقعی بالا هم‌خوانی نداره نساز.\n" .
    "۲) اگر خبری وجود دارد که دقیقاً و مستقیماً به همین سوال جواب می‌دهد، همان پاسخ مستقیم و دقیق (با عدد/تاریخ/رقم اگر هست) را بنویس.\n" .
    "۳) اگر پاسخ مستقیمی برای این سوال در اخبار نیست ولی خبر مرتبطی هست، صادقانه بگو خبر مستقیمی دربارهٔ خودِ سوال منتشر نشده و بعد نزدیک‌ترین خبر مرتبط را معرفی کن؛ در این حالت short را خالی بگذار.\n" .
    "۴) اگر ربط خبرها به سوال ضعیف یا مبهم است، آن را در items نیاور و در answer هم چیزی به آن نسبت نده.\n" .
    "۵) answer باید فقط حول یک موضوع باشد؛ چند خبر نامرتبط را برای طولانی‌کردن جواب کنار هم نچین.\n" .
    "۶) اگر هیچ خبر مرتبطی نیست، short و answer را خالی و items را آرایه‌ی خالی بگذار.\n" .
    "۷) به فارسیِ روان، طبیعی و درست بنویس، نه ترجمه‌ای یا دست‌وپاشکسته. مثلاً بگو «کاهش یافت» یا «کم شد»، نه «کاهش کرد»؛ بگو «افزایش یافت»، نه «افزایش کرد». از ساختارهای نچسب و تحت‌اللفظی پرهیز کن و طوری بنویس که انگار یک خبرنگار فارسی‌زبان حرفه‌ای نوشته.\n" .
    "بعد حداکثر ۴ خبری که پاسخِ بالا (مستقیم یا نزدیک‌ترین) را تایید می‌کنند به‌عنوان منبع انتخاب کن و برای هرکدام یک توضیح ۱ خطی بنویس که ربطش به answer را بگوید.\n" .
    "فقط و فقط یک JSON دقیقاً به این شکل خروجی بده، بدون هیچ توضیح اضافه:\n" .
    '{"short": "...", "answer": "...", "items": [{"i": 0, "summary": "..."}]}' . "\n\nفهرست اخبار:\n" . json_encode($compact, JSON_UNESCAPED_UNICODE);

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
$short = trim($picked['short'] ?? '');
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

// مدل هم چیز مرتبطی پیدا نکرد؛ به‌جای پاسخ خالی، سوال راهنما بده
if ($answer === '' && empty($results)) {
    $answer = buildClarify($news);
    $short = '';
}

out(['ok' => true, 'short' => $short, 'answer' => $answer, 'results' => $results, 'hours' => $hours]);
