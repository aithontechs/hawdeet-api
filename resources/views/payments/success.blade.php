<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تمت عملية الشراء بنجاح</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary:   #E8521A;
            --primary-light: #F26A35;
            --text-dark: #1A1A2E;
            --text-muted: #8A8A9A;
            --border: #E0E0EE;
            --bg: #F8F8FC;
            --white: #FFFFFF;
            --success: #E8521A;
            --link-color: #E8521A;
        }

        html, body {
            height: 100%;
            font-family: 'Cairo', sans-serif;
            background: var(--bg);
            color: var(--text-dark);
        }

        /* ── Mobile-first wrapper ── */
        .page-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background: var(--bg);
        }

        .card {
            background: var(--white);
            border-radius: 20px;
            width: 100%;
            max-width: 420px;
            padding: 48px 32px 36px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.08);
            text-align: center;
            animation: slideUp .45s cubic-bezier(.22,1,.36,1) both;
        }

        /* ── Success icon ── */
        .icon-wrap {
            width: 88px;
            height: 88px;
            background: var(--primary);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 28px;
            box-shadow: 0 8px 24px rgba(232,82,26,.30);
            animation: popIn .5s .2s cubic-bezier(.34,1.56,.64,1) both;
        }

        .icon-wrap svg {
            width: 44px;
            height: 44px;
            stroke: #fff;
            stroke-width: 3;
            fill: none;
        }

        /* ── Text ── */
        .title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .subtitle {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.8;
            max-width: 280px;
            margin: 0 auto 40px;
        }

        /* ── Buttons ── */
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 28px;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 15px 24px;
            border-radius: 12px;
            font-family: 'Cairo', sans-serif;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: transform .15s, box-shadow .15s, background .15s;
            border: none;
            outline: none;
        }

        .btn:active { transform: scale(.97); }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 4px 16px rgba(232,82,26,.30);
        }

        .btn-primary:hover {
            background: var(--primary-light);
            box-shadow: 0 6px 20px rgba(232,82,26,.40);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-dark);
            border: 1.5px solid var(--border);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* ── Support line ── */
        .support {
            font-size: 13px;
            color: var(--text-muted);
        }

        .support a {
            color: var(--link-color);
            font-weight: 600;
            text-decoration: none;
        }

        .support a:hover { text-decoration: underline; }

        /* ── Animations ── */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes popIn {
            from { opacity: 0; transform: scale(.4); }
            to   { opacity: 1; transform: scale(1); }
        }

        /* ── Desktop: slightly wider card ── */
        @media (min-width: 600px) {
            .card { padding: 56px 48px 44px; }
        }
    </style>
</head>
<body>
<div class="page-wrapper">
    <div class="card">

        <!-- Success Icon -->
        <div class="icon-wrap">
            <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>

        <!-- Heading -->
        <h1 class="title">تمت عملية الشراء بنجاح</h1>
        <p class="subtitle">
            تم إضافة الكتاب الى مكتبتك الخاصة يمكنك<br>
            الآن الاستمتاع بالقراءة في أي وقت
        </p>

        <!-- Action Buttons -->
        <div class="btn-group">
            {{-- Web variant: only "Go to Library" button --}}
            <a href="#" class="btn btn-primary">
                اذهب الى المكتبة
            </a>
        </div>

    </div>
</div>
</body>
</html>
