<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>No theme installed — Pollora</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #fbfaf8;
            --panel: #ffffff;
            --ink: #1b1a19;
            --ink-2: #55524e;
            --rule: #e4e0da;
            --code-bg: #14131a;
            --code-ink: #f4f2ef;
            --accent: #d1495b;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #131215;
                --panel: #1c1b20;
                --ink: #f4f2ef;
                --ink-2: #a8a39c;
                --rule: #2e2c33;
                --code-bg: #0d0c11;
                --code-ink: #f4f2ef;
                --accent: #ef7d8c;
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 3rem 1.25rem;
            background: var(--bg);
            color: var(--ink);
            font: 16px/1.6 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }

        .wrap { max-width: 44rem; margin: 0 auto; }

        .tag {
            display: inline-block;
            font-size: .75rem;
            letter-spacing: .12em;
            text-transform: uppercase;
            font-weight: 600;
            color: var(--accent);
            margin-bottom: .75rem;
        }

        h1 {
            margin: 0 0 .75rem;
            font-size: clamp(1.6rem, 4vw, 2.25rem);
            line-height: 1.15;
            letter-spacing: -.02em;
        }

        h2 {
            margin: 0 0 .5rem;
            font-size: 1.0625rem;
            letter-spacing: -.01em;
        }

        p { margin: 0 0 1rem; color: var(--ink-2); }

        .lead { font-size: 1.0625rem; color: var(--ink-2); margin-bottom: 2rem; }

        .card {
            background: var(--panel);
            border: 1px solid var(--rule);
            border-radius: .75rem;
            padding: 1.25rem 1.25rem 1rem;
            margin-bottom: 1rem;
        }

        pre {
            margin: .75rem 0 0;
            padding: .875rem 1rem;
            background: var(--code-bg);
            color: var(--code-ink);
            border-radius: .5rem;
            overflow-x: auto;
            font: .875rem/1.5 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        }

        code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }

        p code {
            font-size: .9em;
            background: color-mix(in srgb, var(--ink) 8%, transparent);
            padding: .1em .35em;
            border-radius: .25rem;
        }

        .note {
            margin-top: 2rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--rule);
            font-size: .9375rem;
        }

        a { color: var(--accent); }
    </style>
</head>
<body>
<div class="wrap">
    <span class="tag">Pollora</span>
    <h1>This site has no theme yet</h1>
    <p class="lead">
        WordPress is installed, but no theme has been generated, so there are no templates to render.
        Pollora themes live in <code>themes/</code> and are scaffolded with an Artisan command.
    </p>

    <div class="card">
        <h2>Start from the default theme</h2>
        <p>A minimal starter: Blade, Vite, Tailwind CSS.</p>
        <pre><code>{{ $prefix }}php artisan pollora:make:theme my-theme --repository=pollora/theme-default</code></pre>
    </div>

    <div class="card">
        <h2>Start from the e-commerce theme</h2>
        <p>A WooCommerce storefront built on the same stack.</p>
        <pre><code>{{ $prefix }}php artisan pollora:make:theme my-shop --repository=pollora/theme-apiary</code></pre>
    </div>

    <p class="note">
        The command downloads the theme, fills in its metadata, installs its npm dependencies and builds
        its assets, then activates it in WordPress. Run it from the project root, then reload this page.
        Running the command without <code>--repository</code> lets you pick the template interactively.
    </p>
</div>
</body>
</html>
