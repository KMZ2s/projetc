{{-- ============================================================
    resources/views/maintenance.blade.php
    ============================================================
    Página estática 503 (Service Unavailable) servida pelo
    middleware MaintenanceMode. Independente de tema — não
    depende do Twig nem de assets do tema ativo. Inline tudo
    pra renderizar mesmo se o tema estiver com problema.

    Variáveis recebidas:
      - $message    string|null  Mensagem custom do operador
      - $store_name string       Nome da loja (Store::current()->name)
============================================================ --}}
@php
    /** @var ?string $message */
    /** @var string $store_name */
    $displayMessage = $message
        ?: 'Estamos fazendo alguns ajustes. Voltamos em instantes — obrigado pela paciência.';
    $storeLabel = $store_name ?: config('app.name', 'Replicantfy');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="refresh" content="60">

    <title>Em manutenção · {{ $storeLabel }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400;1,500&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg:      #f5f2ed;
            --ink:     #151515;
            --muted:   #6b6b6b;
            --accent:  #2b555a;
            --hairline:#cdc7bd;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            min-height: 100%;
            min-height: 100dvh;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            font-weight: 400;
            color: var(--ink);
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.5rem;
            min-height: 100vh;
            min-height: 100dvh;
            letter-spacing: 0.01em;
        }

        /* Subtle grain texture via radial */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse at top, rgba(43, 85, 90, 0.04), transparent 60%),
                radial-gradient(ellipse at bottom, rgba(21, 21, 21, 0.025), transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .wrap {
            position: relative;
            z-index: 1;
            max-width: 540px;
            width: 100%;
            text-align: center;
        }

        /* ───────── Mascote: gato dormindo (loaf pose) ───────── */
        .mascot {
            width: 150px;
            height: auto;
            color: var(--accent);
            margin: 0 auto 2.25rem;
            display: block;
        }
        .mascot__zzz {
            animation: zzz-float 3s ease-in-out infinite;
            transform-origin: center;
        }
        @keyframes zzz-float {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-4px); }
        }

        /* ───────── Eyebrow ───────── */
        .eyebrow {
            font-size: 0.72rem;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 0.85rem;
            font-weight: 500;
        }

        /* ───────── Heading ───────── */
        h1 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: clamp(2.1rem, 5.5vw, 3rem);
            font-weight: 600;
            line-height: 1.1;
            letter-spacing: 0.005em;
            margin-bottom: 1.25rem;
            color: var(--ink);
        }
        h1 em {
            font-style: italic;
            font-weight: 500;
        }

        /* ───────── Message ───────── */
        .message {
            font-size: 1.04rem;
            line-height: 1.65;
            color: var(--muted);
            max-width: 440px;
            margin: 0 auto 2.5rem;
            font-weight: 400;
        }

        /* ───────── Ornament divider ───────── */
        .ornament {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin: 2.5rem auto 1.75rem;
            max-width: 320px;
        }
        .ornament::before,
        .ornament::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--hairline);
        }
        .ornament__mark {
            font-family: 'Playfair Display', serif;
            font-style: italic;
            color: var(--muted);
            font-size: 1rem;
            line-height: 1;
        }

        /* ───────── Footer ───────── */
        .footer-line {
            font-size: 0.7rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 500;
        }

        /* ───────── Reduced motion ───────── */
        @media (prefers-reduced-motion: reduce) {
            .mascot__zzz { animation: none; }
        }
    </style>
</head>
<body>
    <main class="wrap" role="main">

        {{-- ───────── Mascote: gato dormindo em loaf pose ─────────
             Line-art editorial. Z's flutuando com pulso suave. --}}
        <svg class="mascot" viewBox="0 0 140 120" xmlns="http://www.w3.org/2000/svg"
             fill="none" stroke="currentColor"
             aria-hidden="true">
            {{-- Silhueta do corpo (loaf pose, frente) --}}
            <path d="
                M 25 100
                L 115 100
                L 115 92
                Q 115 70 105 58
                Q 100 53 95 51
                L 95 44
                L 90 33
                L 84 25
                L 78 36
                L 78 44
                Q 70 46 62 44
                L 62 36
                L 56 25
                L 50 33
                L 45 44
                L 45 51
                Q 40 53 35 58
                Q 25 70 25 92
                Z"
                stroke-width="1.8"
                stroke-linejoin="round"
                stroke-linecap="round"/>

            {{-- Olhos fechados (dormindo) --}}
            <path d="M 56 58 Q 60 54 64 58"
                stroke-width="1.5" stroke-linecap="round"/>
            <path d="M 76 58 Q 80 54 84 58"
                stroke-width="1.5" stroke-linecap="round"/>

            {{-- Focinho --}}
            <path d="M 67 66 L 73 66 L 70 70 Z" fill="currentColor"/>

            {{-- Boquinha --}}
            <path d="M 70 70 Q 67 74 64 73"
                stroke-width="1" stroke-linecap="round"/>
            <path d="M 70 70 Q 73 74 76 73"
                stroke-width="1" stroke-linecap="round"/>

            {{-- Bigodes --}}
            <g stroke-width="0.6" stroke-linecap="round" opacity="0.55">
                <line x1="55" y1="67" x2="40" y2="65"/>
                <line x1="55" y1="70" x2="40" y2="71"/>
                <line x1="85" y1="67" x2="100" y2="65"/>
                <line x1="85" y1="70" x2="100" y2="71"/>
            </g>

            {{-- Rabinho enrolando do lado direito --}}
            <path d="M 115 100 Q 128 96 128 84 Q 126 72 118 76"
                stroke-width="1.8"
                stroke-linejoin="round"
                stroke-linecap="round"/>

            {{-- Z's flutuando (animados) --}}
            <g class="mascot__zzz">
                <text x="98" y="38" font-family="Playfair Display, Georgia, serif"
                      font-style="italic" font-size="14"
                      fill="currentColor" stroke="none" opacity="0.7">z</text>
                <text x="108" y="26" font-family="Playfair Display, Georgia, serif"
                      font-style="italic" font-size="11"
                      fill="currentColor" stroke="none" opacity="0.5">z</text>
                <text x="116" y="16" font-family="Playfair Display, Georgia, serif"
                      font-style="italic" font-size="9"
                      fill="currentColor" stroke="none" opacity="0.35">z</text>
            </g>
        </svg>

        <p class="eyebrow">503 · Service Unavailable</p>

        <h1>Em <em>manutenção</em></h1>

        <p class="message">{{ $displayMessage }}</p>

        <div class="ornament" aria-hidden="true">
            <span class="ornament__mark">·</span>
        </div>

        <!--<p class="footer-line">{{ $storeLabel }}</p>-->
    </main>
</body>
</html>