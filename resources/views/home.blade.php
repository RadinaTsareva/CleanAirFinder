<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7f2">
    <title>Clean Air Finder — Историческо сравнение на въздуха</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #172d2a;
            --muted: #667773;
            --green: #176b55;
            --green-dark: #0e493b;
            --mint: #e4f0e9;
            --paper: #f6f7f2;
            --white: #fff;
            --line: #dce4dd;
            --amber: #a05b20;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--paper);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.5;
        }

        a { color: inherit; }

        .shell {
            width: min(1120px, calc(100% - 40px));
            margin: 0 auto;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 25px 0;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            color: var(--ink);
            font-size: 15px;
            font-weight: 750;
            letter-spacing: -.02em;
            text-decoration: none;
        }

        .brand-mark {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 11px;
            background: var(--green);
            color: white;
        }

        .brand-mark svg { width: 19px; height: 19px; }

        .source-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: rgba(255,255,255,.62);
            color: #52645e;
            font-size: 12px;
            font-weight: 650;
        }

        .source-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #3f9a70;
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1.18fr) minmax(330px, .82fr);
            gap: 50px;
            align-items: center;
            padding: 72px 0 58px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 20px;
            color: var(--green);
            font-size: 12px;
            font-weight: 750;
            letter-spacing: .11em;
            text-transform: uppercase;
        }

        .eyebrow::before {
            width: 21px;
            height: 1px;
            background: currentColor;
            content: "";
        }

        h1 {
            max-width: 640px;
            margin: 0;
            font-size: clamp(42px, 6vw, 68px);
            font-weight: 650;
            letter-spacing: -.065em;
            line-height: 1.03;
        }

        h1 span { color: var(--green); }

        .intro {
            max-width: 540px;
            margin: 24px 0 0;
            color: var(--muted);
            font-size: 17px;
            line-height: 1.7;
        }

        .period-card {
            padding: 26px;
            border: 1px solid rgba(23, 45, 42, .08);
            border-radius: 20px;
            background: var(--white);
            box-shadow: 0 18px 50px rgba(27, 52, 44, .08);
        }

        .period-card h2 {
            margin: 0;
            font-size: 18px;
            letter-spacing: -.025em;
        }

        .period-card p {
            margin: 7px 0 22px;
            color: var(--muted);
            font-size: 13px;
        }

        .fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: #465a55;
            font-size: 12px;
            font-weight: 650;
        }

        input[type="date"] {
            width: 100%;
            min-height: 46px;
            padding: 0 11px;
            border: 1px solid var(--line);
            border-radius: 9px;
            background: #fbfcfa;
            color: #53635e;
            font: inherit;
            font-size: 13px;
        }

        .submit {
            width: 100%;
            min-height: 48px;
            margin-top: 16px;
            border: 0;
            border-radius: 10px;
            background: #dfe8e2;
            color: #53645d;
            cursor: not-allowed;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
        }

        .pending-note {
            display: flex;
            gap: 9px;
            align-items: flex-start;
            margin: 13px 0 0 !important;
            color: #727e78 !important;
            font-size: 11px !important;
            line-height: 1.5;
        }

        .pending-note span { color: var(--amber); }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1px;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 15px;
            background: var(--line);
        }

        .stat {
            padding: 23px 25px;
            background: rgba(255,255,255,.72);
        }

        .stat strong {
            display: block;
            margin-bottom: 4px;
            color: var(--green-dark);
            font-size: 28px;
            font-weight: 650;
            letter-spacing: -.05em;
        }

        .stat span { color: var(--muted); font-size: 12px; }

        .section {
            display: grid;
            grid-template-columns: .8fr 1.2fr;
            gap: 55px;
            padding: 62px 0 75px;
        }

        .section h2 {
            margin: 0;
            font-size: 25px;
            font-weight: 630;
            letter-spacing: -.04em;
        }

        .section-lede {
            margin: 11px 0 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .principles { display: grid; gap: 18px; }

        .principle {
            display: grid;
            grid-template-columns: 34px 1fr;
            gap: 14px;
            align-items: start;
        }

        .principle-icon {
            display: grid;
            width: 32px;
            height: 32px;
            place-items: center;
            border-radius: 10px;
            background: var(--mint);
            color: var(--green);
            font-size: 13px;
            font-weight: 750;
        }

        .principle strong { display: block; margin: 1px 0 3px; font-size: 14px; }
        .principle p { margin: 0; color: var(--muted); font-size: 12px; line-height: 1.65; }

        .notice {
            margin: 0 0 70px;
            padding: 18px 20px;
            border: 1px solid #e8d8bd;
            border-radius: 12px;
            background: #fbf6ec;
            color: #765b36;
            font-size: 12px;
            line-height: 1.65;
        }

        .notice strong { color: #664820; }

        footer {
            padding: 19px 0 27px;
            border-top: 1px solid var(--line);
            color: #7b8882;
            font-size: 11px;
        }

        .footer-inner { display: flex; justify-content: space-between; gap: 20px; }

        @media (max-width: 800px) {
            .hero { grid-template-columns: 1fr; gap: 32px; padding: 52px 0 40px; }
            .period-card { max-width: 560px; }
            .section { grid-template-columns: 1fr; gap: 28px; padding: 48px 0 56px; }
        }

        @media (max-width: 520px) {
            .shell { width: min(100% - 28px, 1120px); }
            .topbar { padding: 17px 0; }
            .source-pill { padding: 7px 9px; font-size: 10px; }
            .hero { padding-top: 43px; }
            .intro { font-size: 15px; }
            .period-card { padding: 20px; }
            .fields { grid-template-columns: 1fr; }
            .stats { grid-template-columns: 1fr; }
            .stat { padding: 15px 18px; }
            .stat strong { font-size: 24px; }
            .footer-inner { flex-direction: column; gap: 5px; }
        }
    </style>
</head>
<body>
    <header class="shell topbar">
        <a class="brand" href="{{ route('home') }}" aria-label="Clean Air Finder — начало">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M4 15.5c2.3 0 2.3-2 4.6-2s2.3 2 4.6 2 2.3-2 4.6-2 2.3 2 4.6 2M4 9.5c2.3 0 2.3-2 4.6-2s2.3 2 4.6 2 2.3-2 4.6-2 2.3 2 4.6 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </span>
            Clean Air Finder
        </a>
        <span class="source-pill"><span class="source-dot"></span>Проверени измервания · EEA E1a</span>
    </header>

    <main>
        <section class="shell hero" aria-labelledby="page-title">
            <div>
                <div class="eyebrow">Въздухът във времето</div>
                <h1 id="page-title">Къде въздухът е <span>по-чист?</span></h1>
                <p class="intro">
                    Сравни средногодишните и историческите нива на PM2.5 в българските градове.
                    Всяка бъдеща класация ще показва и колко пълни са данните зад нея.
                </p>
            </div>

            <form class="period-card" aria-describedby="comparison-status">
                <h2>Избери период</h2>
                <p>Посочи начална и крайна дата за сравнението.</p>
                <div class="fields">
                    <div>
                        <label for="date-from">От дата</label>
                        <input id="date-from" name="from" type="date" disabled>
                    </div>
                    <div>
                        <label for="date-to">До дата</label>
                        <input id="date-to" name="to" type="date" disabled>
                    </div>
                </div>
                <button class="submit" type="button" disabled>Сравнението се подготвя</button>
                <p class="pending-note" id="comparison-status"><span aria-hidden="true">●</span>Работим по проверката на данните и правилата за сравнение.</p>
            </form>
        </section>

        <section class="shell" aria-label="Покритие на наличните данни">
            <div class="stats">
                <div class="stat"><strong>7 от 17</strong><span>града с открити E1a PM2.5 файлове</span></div>
                <div class="stat"><strong>8</strong><span>измервателни точки в наличния набор</span></div>
                <div class="stat"><strong>2013–2025</strong><span>период на проверените дневни данни</span></div>
            </div>
        </section>

        <section class="shell section" aria-labelledby="method-title">
            <div>
                <div class="eyebrow">Прозрачно сравнение</div>
                <h2 id="method-title">Данните идват преди класацията.</h2>
                <p class="section-lede">Показваме източника и ограниченията, за да можеш да прецениш доколко резултатът е надежден.</p>
            </div>
            <div class="principles">
                <div class="principle">
                    <span class="principle-icon">01</span>
                    <div><strong>Само EEA E1a PM2.5</strong><p>Първата версия използва дневните исторически измервания и не смесва други замърсители или моделирани стойности.</p></div>
                </div>
                <div class="principle">
                    <span class="principle-icon">02</span>
                    <div><strong>Флагове за качество</strong><p>В изчисленията участват записи с Validity = 1 и Verification = 1. Оригиналните записи и произходът им се запазват.</p></div>
                </div>
                <div class="principle">
                    <span class="principle-icon">03</span>
                    <div><strong>Покритие на периода</strong><p>За участие се изискват поне 75% от календарните дни. Това е правило на проекта, а не стандарт на EEA.</p></div>
                </div>
            </div>
        </section>

        <aside class="shell notice">
            <strong>Важно за обхвата:</strong> EEA инвентаризацията съдържа измервания за 7 от 17-те открити български града.
            Varna има PM2.5 измервания и остава в анализа, но станционните ѝ метаданни не са намерени. Градове без достатъчно данни ще бъдат показвани като недостъпни за избрания период, а не като чисти или замърсени.
        </aside>
    </main>

    <footer>
        <div class="shell footer-inner">
            <span>Clean Air Finder · Исторически данни за PM2.5</span>
            <span>Източник: European Environment Agency (EEA)</span>
        </div>
    </footer>
</body>
</html>
