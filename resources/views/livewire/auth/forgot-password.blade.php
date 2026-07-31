<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar senha — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0a0d;
            color: #ede8e3;
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
        }
        .auth-brand {
            position: relative;
            background: #0f0a10;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: space-between;
            padding: 3rem;
            overflow: hidden;
        }
        .auth-brand__glow {
            position: absolute; top: -100px; left: -100px;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(139,26,59,.3) 0%, transparent 65%);
            pointer-events: none;
        }
        .auth-brand__logo {
            position: relative; z-index: 1;
            display: flex; align-items: center; gap: .6rem;
            text-decoration: none;
        }
        .auth-brand__logo-mark {
            width: 36px; height: 36px; background: #8b1a3b;
            border-radius: 8px; display: flex; align-items: center; justify-content: center;
        }
        .auth-brand__logo-mark span { color: #fff; font-weight: 900; font-size: 1rem; }
        .auth-brand__logo-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem; font-weight: 800; color: #ede8e3; letter-spacing: -.01em;
        }
        .auth-brand__main { position: relative; z-index: 1; }
        .auth-brand__eyebrow { font-size: .7rem; font-weight: 700; letter-spacing: .15em; text-transform: uppercase; color: #a8234a; margin-bottom: 1rem; }
        .auth-brand__headline { font-family: 'Playfair Display', serif; font-size: clamp(2rem, 3vw, 3rem); font-weight: 800; line-height: 1.08; letter-spacing: -.02em; color: #ede8e3; margin-bottom: 1.25rem; }
        .auth-brand__desc { font-size: .9rem; color: rgba(154,148,144,.75); line-height: 1.7; max-width: 36ch; }
        .auth-brand__footer { position: relative; z-index: 1; font-size: .72rem; color: rgba(82,80,78,.8); }
        .auth-form-panel {
            display: flex; align-items: center; justify-content: center;
            padding: 3rem 2rem; background: #0a0a0d;
            border-left: 1px solid #1e1e2a;
        }
        .auth-form-wrap { width: 100%; max-width: 400px; }
        .auth-form-wrap h1 { font-size: 1.5rem; font-weight: 700; letter-spacing: -.02em; color: #ede8e3; margin-bottom: .4rem; }
        .auth-subtitle { font-size: .85rem; color: rgba(154,148,144,.7); margin-bottom: 2rem; line-height: 1.6; }
        .form-group { display: flex; flex-direction: column; gap: .4rem; margin-bottom: 1rem; }
        .form-label { font-size: .78rem; font-weight: 600; color: rgba(154,148,144,.9); letter-spacing: .02em; }
        .form-input {
            width: 100%; padding: .75rem 1rem;
            background: #15151c; border: 1px solid #1e1e2a;
            border-radius: 8px; color: #ede8e3;
            font-size: .9rem; font-family: inherit; outline: none;
            transition: border-color .2s;
        }
        .form-input:focus { border-color: #8b1a3b; box-shadow: 0 0 0 3px rgba(139,26,59,.12); }
        .form-input::placeholder { color: rgba(82,80,78,.8); }
        .form-error { font-size: .75rem; color: #f87171; margin-top: .25rem; }
        .form-success {
            background: rgba(74,222,128,.1); border: 1px solid rgba(74,222,128,.25);
            color: #4ade80; border-radius: 8px; padding: .75rem 1rem;
            font-size: .82rem; margin-bottom: 1.25rem; line-height: 1.5;
        }
        .btn-submit {
            width: 100%; padding: .85rem 1.5rem;
            background: #8b1a3b; color: #fff; border: none;
            border-radius: 8px; font-family: inherit; font-size: .9rem;
            font-weight: 700; letter-spacing: .03em; cursor: pointer;
            transition: background .2s, box-shadow .2s, transform .15s;
            margin-top: 1.25rem;
        }
        .btn-submit:hover { background: #a8234a; box-shadow: 0 4px 20px rgba(139,26,59,.35); transform: translateY(-1px); }
        .auth-link-row { text-align: center; font-size: .82rem; color: rgba(154,148,144,.65); margin-top: 1.25rem; }
        .auth-link { color: #a8234a; font-weight: 600; transition: color .2s; }
        .auth-link:hover { color: #c23258; }
        @media (max-width: 768px) {
            body { grid-template-columns: 1fr; }
            .auth-brand { display: none; }
            .auth-form-panel { border-left: none; }
        }
    </style>
</head>
<body>
    <div class="auth-brand">
        <div class="auth-brand__glow"></div>
        <a href="{{ url('/') }}" class="auth-brand__logo">
            <div class="auth-brand__logo-mark"><span>R</span></div>
            <span class="auth-brand__logo-name">{{ config('app.name') }}</span>
        </a>
        <div class="auth-brand__main">
            <p class="auth-brand__eyebrow">Recuperação</p>
            <h2 class="auth-brand__headline">Esqueceu<br>a senha?</h2>
            <p class="auth-brand__desc">Sem problema. Enviaremos um link para você criar uma nova senha.</p>
        </div>
        <p class="auth-brand__footer">&copy; {{ date('Y') }} {{ config('app.name') }}.</p>
    </div>

    <div class="auth-form-panel">
        <div class="auth-form-wrap">
            <h1>Recuperar senha</h1>
            <p class="auth-subtitle">
                Informe seu e-mail cadastrado e enviaremos um link para redefinir sua senha.
            </p>

            @if (session('status'))
                <div class="form-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label" for="email">E-mail</label>
                    <input id="email" class="form-input" type="email" name="email"
                           value="{{ old('email') }}" required autofocus
                           placeholder="seu@email.com">
                    @error('email')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
                <button type="submit" class="btn-submit">Enviar link de recuperação</button>
            </form>

            <p class="auth-link-row">
                Lembrou a senha?
                <a href="{{ route('login') }}" class="auth-link">Voltar ao login</a>
            </p>
        </div>
    </div>
</body>
</html>