<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>404 — Página no encontrada</title>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <meta name="robots" content="noindex">
  <style>
    :root{
      --bg: #0f1724;
      --card: rgba(255,255,255,0.04);
      --glass: rgba(255,255,255,0.03);
      --accent: #0ea5a3;
      --accent-2: #36d1dc;
      --muted: #9aa4b2;
      --text: #e6eef6;
      --success: #34d399;
      --radius: 14px;
      --shadow: 0 10px 30px rgba(2,6,23,0.6);
      --glass-border: rgba(255,255,255,0.045);
    }

    @media (prefers-color-scheme: light){
      :root{
        --bg: #f6f9ff;
        --card: #ffffff;
        --glass: rgba(16,24,40,0.02);
        --accent: #0ea5a3;
        --accent-2: #06b6d4;
        --muted: #475569;
        --text: #0b1220;
        --shadow: 0 8px 26px rgba(16,24,40,0.08);
        --glass-border: rgba(16,24,40,0.06);
      }
    }

    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial;
      background: radial-gradient(1200px 600px at 10% 10%, rgba(124,92,255,0.12), transparent 8%),
                  radial-gradient(800px 400px at 90% 90%, rgba(54,209,220,0.08), transparent 8%),
                  var(--bg);
      color:var(--text);
      -webkit-font-smoothing:antialiased;
      -moz-osx-font-smoothing:grayscale;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:48px 20px;
    }

    .page {
      width:100%;
      max-width:1100px;
      display:grid;
      grid-template-columns: 1fr 420px;
      gap:36px;
      align-items:center;
    }

    @media (max-width:900px){
      .page{grid-template-columns:1fr; padding-top: 20px}
    }

    .hero {
      padding:32px;
      border-radius: var(--radius);
      background: linear-gradient(180deg, rgba(255,255,255,0.02), transparent 40%);
      box-shadow: var(--shadow);
      border: 1px solid var(--glass-border);
    }

    .eyebrow{display:inline-block;padding:6px 10px;border-radius:999px;background:linear-gradient(90deg,var(--accent),var(--accent-2));color:white;font-weight:600;font-size:13px}

    h1{font-size:72px;line-height:0.88;margin:18px 0 6px;font-weight:700;letter-spacing:-1px}
    @media (max-width:700px){h1{font-size:54px}}

    p.lead{margin:0 0 18px;color:var(--muted);font-size:16px}

    .actions{display:flex;gap:12px;margin-top:18px;flex-wrap:wrap}
    .btn{
      display:inline-flex;align-items:center;gap:10px;padding:12px 16px;border-radius:10px;background:linear-gradient(90deg,var(--accent),var(--accent-2));color:white;text-decoration:none;font-weight:600;box-shadow:0 8px 30px rgba(92, 63, 255, 0.18);border:1px solid rgba(255,255,255,0.06);
    }
    .btn.secondary{background:transparent;color:var(--text);border:1px solid var(--glass-border);box-shadow:none}
    .btn:focus{outline:3px solid rgba(124,92,255,0.16);outline-offset:4px}

    .search {
      margin-top:18px;display:flex;gap:10px;align-items:center;
    }
    .search input{flex:1;padding:12px 14px;border-radius:10px;border:1px solid var(--glass-border);background:transparent;color:var(--text);font-size:15px}
    .search button{padding:10px 12px;border-radius:10px;border:1px solid var(--glass-border);background:var(--card);cursor:pointer}

    /* Card con la ilustración / número 404 grande */
    .card {
      padding:28px;border-radius:18px;background:linear-gradient(135deg, rgba(255,255,255,0.025), rgba(255,255,255,0.01));border:1px solid var(--glass-border);box-shadow:var(--shadow);
      display:flex;flex-direction:column;align-items:center;justify-content:center;gap:18px;text-align:center;
    }

    .big-number{
      font-weight:800;font-size:120px;line-height:0.85;color:transparent;background:linear-gradient(90deg,var(--accent),var(--accent-2));-webkit-background-clip:text;background-clip:text;filter:drop-shadow(0 6px 26px rgba(60,40,120,0.25));
    }
    @media (max-width:700px){.big-number{font-size:88px}}

    /* Illustration */
    .illus{width:200px;height:160px;position:relative}
    .illus svg{width:100%;height:100%;display:block}

    /* small helper */
    .meta{font-size:13px;color:var(--muted)}

    /* subtle float animation */
    @media (prefers-reduced-motion: no-preference){
      .illus{animation:float 6s ease-in-out infinite}
      @keyframes float{0%{transform:translateY(0)}50%{transform:translateY(-8px)}100%{transform:translateY(0)}}
      .btn{transition:transform .18s ease, box-shadow .18s ease}
      .btn:hover{transform:translateY(-4px);box-shadow:0 18px 40px rgba(56,20,130,0.12)}
    }

    /* footer small */
    .note{font-size:13px;color:var(--muted);margin-top:12px}

    /* small responsive tweaks */
    @media (max-width:420px){
      .search input{font-size:14px}
      .actions{justify-content:flex-start}
    }

  </style>
</head>
<body>
  <main class="page" role="main">
    <section class="hero" aria-labelledby="title">
      <div class="eyebrow">Página no encontrada</div>
      <h1 id="title">Lo sentimos — 404</h1>
      <p class="lead">La página que buscas no existe o fue movida. Quizás escribiste mal la URL, o el enlace está desactualizado.</p>

      <div class="actions">
        <a class="btn" href="/tarjeta" id="homeBtn" data-go="home">
          <!-- home icon -->
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 11.5L12 4l9 7.5" stroke="white" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Ir a tu tarjeta
        </a>

        <button class="btn secondary" id="backBtn" aria-label="Volver atrás">
          Volver
        </button>
      </div>

      <!-- <form class="search" id="searchForm" role="search" aria-label="Buscar en el sitio">
        <input type="search" id="q" name="q" placeholder="Buscar..." aria-label="Buscar por términos" autocomplete="off">
        <button type="submit" aria-label="Buscar">Buscar</button>
      </form> -->

      <!-- <p class="meta">Si necesitas ayuda, <a href="/contact" style="color:var(--accent);font-weight:600;text-decoration:none">contáctanos</a> y te orientamos.</p> -->
    </section>

    <aside class="card" aria-hidden="true">
      <div class="big-number">404</div>
      <div class="illus" role="img" aria-hidden="true">
        <!-- Simple friendly illustration (SVG) -->
        <svg viewBox="0 0 320 220" xmlns="http://www.w3.org/2000/svg" role="presentation">
          <defs>
            <linearGradient id="g1" x1="0" x2="1">
              <stop offset="0" stop-color="#0ea5a3"/>
              <stop offset="1" stop-color="#50d7e0ff"/>
            </linearGradient>
            <filter id="f1" x="-20%" y="-20%" width="140%" height="140%">
              <feDropShadow dx="0" dy="10" stdDeviation="18" flood-color="#2b1a6f" flood-opacity="0.18"/>
            </filter>
          </defs>

          <g transform="translate(40,20)">
            <rect x="0" y="40" rx="18" ry="18" width="240" height="120" fill="url(#g1)" filter="url(#f1)" opacity="0.95"/>
            <circle cx="180" cy="40" r="22" fill="#fff" opacity="0.12"/>
            <g transform="translate(28,24)">
              <rect x="6" y="40" width="80" height="60" rx="8" fill="#fff" opacity="0.12"/>
              <rect x="110" y="54" width="30" height="30" rx="6" fill="#fff" opacity="0.14"/>
              <path d="M0 30 Q30 4 60 30 T120 30" fill="none" stroke="#fff" stroke-opacity="0.12" stroke-width="6" stroke-linecap="round"/>
            </g>
          </g>
        </svg>
      </div>
      <div class="note">Consejo: revisa la URL o usa el boton para regresar al panel de tu tarjeta</div>
    </aside>
  </main>

  <script>
    (function(){
      const home = document.getElementById('homeBtn');
      const back = document.getElementById('backBtn');
      const form = document.getElementById('searchForm');
      const input = document.getElementById('q');

      // Home always goes to root — keep href for SEO; JS enhances navigation
      if(home){
        home.addEventListener('click', (e)=>{
          // default behavior is fine; but support SPA navigation if data-go
          const go = home.dataset.go;
          if(go === 'home') return; // allow default
        });
      }

      if(back){
        back.addEventListener('click', ()=>{
          if(history.length > 1){ history.back(); }
          else { window.location.href = '/'; }
        });
      }

      if(form){
        form.addEventListener('submit', function(e){
          e.preventDefault();
          const q = (input.value || '').trim();
          if(!q) return input.focus();
          // simple redirect — adapt to your site's search path
          const url = '/search?q=' + encodeURIComponent(q);
          window.location.href = url;
        });
      }

      // keyboard: press Escape to go home
      window.addEventListener('keydown', function(e){
        if(e.key === 'Escape') window.location.href = '/';
      });

      // focus first interactive element for accessibility
      window.addEventListener('load', ()=>{
        input.focus();
      });
    })();
  </script>
</body>
</html>
