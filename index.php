<?php
$midnight = strtotime('today');
$hours = round((time() - $midnight) / 3600, 1);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>دستیار خبری | ISNA AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#080b1e; --panel:rgba(255,255,255,.045); --panel-b:rgba(255,255,255,.09);
  --cyan:#3fd8ff; --violet:#a06bff; --white:#f2f5ff;
  --hi:#f2f5ff; --lo:#93a0c9; --lo2:#5c648c;
  --serif:'Vazirmatn',sans-serif; --mono:'JetBrains Mono',monospace;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:var(--serif);color:var(--hi);background:var(--bg);overflow-x:hidden;line-height:1.9;min-height:100vh}
canvas#bg{position:fixed;inset:0;z-index:-2;width:100%;height:100%}
.vignette{position:fixed;inset:0;z-index:-1;pointer-events:none;background:radial-gradient(ellipse at center,transparent 35%,rgba(8,11,30,.55) 100%)}
::selection{background:var(--cyan);color:#04101c}

nav{position:sticky;top:0;z-index:59;display:flex;align-items:center;justify-content:space-between;padding:16px 6vw;background:rgba(6,9,24,.55);backdrop-filter:blur(14px);border-bottom:1px solid var(--panel-b)}
.logo{font-weight:800;font-size:19px;display:flex;align-items:center;gap:8px;text-decoration:none;color:var(--hi)}
.logo .dot{width:7px;height:7px;border-radius:50%;background:var(--cyan);box-shadow:0 0 6px var(--cyan),0 0 16px var(--cyan)}
.logo b{background:linear-gradient(90deg,var(--cyan),var(--violet));-webkit-background-clip:text;background-clip:text;color:transparent}
.back{color:var(--lo);text-decoration:none;font-size:13.5px}
.back:hover{color:var(--cyan)}

section{padding:9vh 6vw}
.wrap{max-width:820px;margin:0 auto}
.eyebrow{font-family:var(--mono);font-size:12px;color:var(--cyan);letter-spacing:.14em;display:flex;align-items:center;gap:10px;margin-bottom:16px}
.eyebrow::before{content:"";width:20px;height:1px;background:var(--cyan)}
h1{font-size:clamp(30px,4.6vw,46px);font-weight:900;line-height:1.35;margin-bottom:12px}
h1 em{font-style:normal;background:linear-gradient(90deg,var(--cyan),var(--violet));-webkit-background-clip:text;background-clip:text;color:transparent}
p.lead{color:var(--lo);font-size:15.5px;max-width:640px}
.fresh{font-family:var(--mono);font-size:11.5px;color:var(--lo2);margin-top:10px}

.searchbox{margin-top:38px;display:flex;gap:10px;background:var(--panel);border:1px solid var(--panel-b);border-radius:14px;padding:8px;backdrop-filter:blur(14px)}
.searchbox input{flex:1;background:transparent;border:none;outline:none;color:var(--hi);font-family:var(--serif);font-size:15px;padding:12px 14px}
.searchbox input::placeholder{color:var(--lo2)}
.btn{font-family:var(--serif);font-weight:700;font-size:14.5px;padding:12px 26px;border-radius:10px;border:none;cursor:pointer;background:linear-gradient(90deg,var(--cyan),#5ec9ff);color:#04101c;box-shadow:0 0 18px rgba(63,216,255,.35);transition:.25s}
.btn:hover{box-shadow:0 0 28px rgba(63,216,255,.6);transform:translateY(-1px)}
.btn:disabled{opacity:.55;cursor:default;transform:none;box-shadow:none}

.status{margin-top:22px;font-family:var(--mono);font-size:13px;color:var(--lo);min-height:20px}
.answer-box{margin-top:20px;background:linear-gradient(155deg,rgba(63,216,255,.08),rgba(160,107,255,.06)),var(--panel);border:1px solid rgba(63,216,255,.3);border-radius:14px;padding:20px 22px}
.answer-box .lbl{font-family:var(--mono);font-size:11px;color:var(--cyan);letter-spacing:.08em;margin-bottom:8px}
.answer-box p{font-size:16px;font-weight:600;color:var(--hi);line-height:1.8}
.sources-lbl{font-family:var(--mono);font-size:11.5px;color:var(--lo2);margin:20px 0 10px}
.results{display:flex;flex-direction:column;gap:14px}
.card{background:var(--panel);border:1px solid var(--panel-b);border-radius:14px;padding:20px;backdrop-filter:blur(14px);transition:.25s}
.card:hover{border-color:rgba(63,216,255,.35)}
.tag{font-family:var(--mono);font-size:10.5px;color:var(--cyan);border:1px solid rgba(63,216,255,.35);padding:3px 9px;border-radius:99px;width:fit-content;margin-bottom:12px}
.card h3{font-size:16.5px;font-weight:700;margin-bottom:8px}
.card h3 a{color:var(--hi);text-decoration:none}
.card h3 a:hover{color:var(--cyan)}
.card p{font-size:13.5px;color:var(--lo)}
</style>
</head>
<body>

<canvas id="bg"></canvas>
<div class="vignette"></div>

<nav>
  <a href="../" class="logo"><span class="dot"></span>ISNA <b>AI</b></a>
  <a href="../#content" class="back">← بازگشت به سایت</a>
</nav>

<section>
  <div class="wrap">
    <div class="eyebrow">دستیار خبری</div>
    <h1>در هر موضوعی بپرس، <em>ایسنا</em> جواب می‌دهد</h1>
    <p class="lead">موضوع موردنظرت را بنویس تا مرتبط‌ترین اخبار ایسنا را پیدا کنیم و برایت خلاصه کنیم.</p>
    <p class="fresh" id="fresh">این پاسخ‌ها بر اساس اخبار ایسنا از ساعت ۰۰:۰۰ امروز تا کنون (حدود <?php echo $hours; ?> ساعت اخیر) است.</p>

    <form class="searchbox" id="f">
      <input type="text" id="q" placeholder="مثلاً: چه خبر از هوش مصنوعی؟" autocomplete="off" required>
      <button class="btn" type="submit" id="btn">بپرس</button>
    </form>

    <div class="status" id="status"></div>
    <div class="answer-box" id="answerBox" style="display:none">
      <div class="lbl">پاسخ</div>
      <p id="answerText"></p>
    </div>
    <div class="sources-lbl" id="sourcesLbl" style="display:none">بر اساس این خبرها:</div>
    <div class="results" id="results"></div>
  </div>
</section>

<script>
const f = document.getElementById('f'), q = document.getElementById('q'),
      btn = document.getElementById('btn'), statusEl = document.getElementById('status'),
      results = document.getElementById('results'), fresh = document.getElementById('fresh'),
      answerBox = document.getElementById('answerBox'), answerText = document.getElementById('answerText'),
      sourcesLbl = document.getElementById('sourcesLbl');

f.addEventListener('submit', async (e) => {
  e.preventDefault();
  const query = q.value.trim();
  if (!query) return;
  btn.disabled = true; results.innerHTML = '';
  answerBox.style.display = 'none'; sourcesLbl.style.display = 'none';
  statusEl.textContent = 'در حال جست‌وجو در اخبار ایسنا…';
  const slowNotice = setTimeout(() => {
    statusEl.textContent = 'کمی بیشتر طول می‌کشد، مدل هوش مصنوعی هنوز در حال پردازشه…';
  }, 8000);
  try {
    const r = await fetch('api.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({query})
    });
    clearTimeout(slowNotice);
    const data = await r.json();
    if (!data.ok) {
      statusEl.textContent = (data.error || 'خطایی رخ داد.') + (data.debug ? ' — خام: ' + JSON.stringify(data.debug).slice(0, 500) : '');
      btn.disabled = false; return;
    }
    if (data.hours !== undefined) {
      fresh.textContent = 'این پاسخ‌ها بر اساس اخبار ایسنا از ساعت ۰۰:۰۰ امروز تا کنون (حدود ' + data.hours + ' ساعت اخیر) است.';
    }
    if (!data.results.length || !data.answer) {
      statusEl.textContent = 'خبر مرتبطی در بازه‌ی امروز پیدا نشد.';
    } else {
      statusEl.textContent = '';
      answerText.textContent = data.answer;
      answerBox.style.display = 'block';
      sourcesLbl.style.display = 'block';
      results.innerHTML = data.results.map(n => `
        <div class="card">
          <div class="tag">${n.cat || 'ایسنا'}</div>
          <h3><a href="${n.link}" target="_blank" rel="noopener">${n.title}</a></h3>
          <p>${n.summary}</p>
        </div>
      `).join('');
    }
  } catch (err) {
    clearTimeout(slowNotice);
    statusEl.textContent = 'ارتباط با سرور برقرار نشد.';
  }
  btn.disabled = false;
});

// پس‌زمینه‌ی متحرک ساده هماهنگ با تم سایت
const canvas=document.getElementById('bg'),ctx=canvas.getContext('2d');
let W,H,DPR=Math.min(window.devicePixelRatio||1,2),pts=[];
function rand(a,b){return a+Math.random()*(b-a)}
function build(){
  W=window.innerWidth;H=window.innerHeight;
  canvas.width=W*DPR;canvas.height=H*DPR;canvas.style.width=W+'px';canvas.style.height=H+'px';
  ctx.setTransform(DPR,0,0,DPR,0,0);
  const n=W<640?30:55;pts=[];
  for(let i=0;i<n;i++)pts.push({x:rand(0,W),y:rand(0,H),vx:rand(-.15,.15),vy:rand(-.15,.15),r:rand(1,2.2)});
}
function loop(){
  ctx.clearRect(0,0,W,H);
  pts.forEach(p=>{p.x+=p.vx;p.y+=p.vy;if(p.x<0||p.x>W)p.vx*=-1;if(p.y<0||p.y>H)p.vy*=-1;});
  for(let i=0;i<pts.length;i++)for(let j=i+1;j<pts.length;j++){
    const dx=pts[i].x-pts[j].x,dy=pts[i].y-pts[j].y,d=Math.hypot(dx,dy);
    if(d<120){ctx.strokeStyle=`rgba(63,216,255,${.12*(1-d/120)})`;ctx.lineWidth=1;
      ctx.beginPath();ctx.moveTo(pts[i].x,pts[i].y);ctx.lineTo(pts[j].x,pts[j].y);ctx.stroke();}
  }
  pts.forEach(p=>{ctx.fillStyle='rgba(242,245,255,.6)';ctx.beginPath();ctx.arc(p.x,p.y,p.r,0,6.28);ctx.fill();});
  requestAnimationFrame(loop);
}
build();loop();window.addEventListener('resize',build);
</script>
</body>
</html>
