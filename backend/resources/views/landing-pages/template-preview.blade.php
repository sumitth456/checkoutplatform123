<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preview — {{ $template->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, sans-serif;
            background: #f3f4f6;
            color: #111827;
        }
        .lp-template-preview-bar {
            position: sticky;
            top: 0;
            z-index: 1000;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 20px;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
        }
        .lp-template-preview-bar h1 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }
        .lp-template-preview-bar p {
            margin: 2px 0 0;
            font-size: .8125rem;
            color: #6b7280;
        }
        .lp-template-preview-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .lp-template-preview-actions a,
        .lp-template-preview-actions button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: .875rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .lp-btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
        }
        .lp-btn-secondary {
            background: #fff;
            color: #374151;
            border-color: #d1d5db;
        }
        .lp-template-preview-frame {
            display: block;
            width: 100%;
            min-height: calc(100vh - 72px);
            border: 0;
            background: #fff;
        }
        .lp-template-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            background: #ede9fe;
            color: #5b21b6;
            font-size: .6875rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="lp-template-preview-bar">
        <div>
            <h1>
                <span class="lp-template-badge">Template</span>
                {{ $template->name }}
            </h1>
            @if(filled($template->description))
                <p>{{ $template->description }}</p>
            @endif
        </div>
        <div class="lp-template-preview-actions">
            <a href="{{ $builderUrl }}" class="lp-btn-secondary">Edit template</a>
            <a href="{{ $useTemplateUrl }}" class="lp-btn-primary">Use template</a>
        </div>
    </div>

    <iframe
        class="lp-template-preview-frame"
        title="Template preview"
        srcdoc="{{ e($previewHtml) }}"
    ></iframe>
</body>
</html>
