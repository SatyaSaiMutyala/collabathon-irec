<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aadhaar — {{ $name ?: $broker->name }}</title>
    <style>
        :root {
            --navy: #0f2540;
            --navy-soft: #eef2f7;
            --gold: #b8873a;
            --ink: #1c2733;
            --ink-2: #55636f;
            --line: #dde3ea;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 32px 16px;
            background: #f4f6f8;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: var(--ink);
        }
        .card {
            max-width: 620px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15, 37, 64, 0.08);
        }
        .card__header {
            background: var(--navy);
            color: #fff;
            padding: 18px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card__header h1 {
            font-size: 15px;
            font-weight: 600;
            margin: 0;
            letter-spacing: 0.02em;
        }
        .card__header span {
            font-size: 11px;
            color: var(--gold);
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .card__body {
            display: flex;
            gap: 20px;
            padding: 22px;
        }
        .photo {
            flex-shrink: 0;
            width: 108px;
            height: 132px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: var(--navy-soft);
            object-fit: cover;
        }
        .fields {
            flex: 1;
            min-width: 0;
        }
        .field {
            margin-bottom: 12px;
        }
        .field:last-child { margin-bottom: 0; }
        .field__label {
            font-size: 10.5px;
            color: var(--ink-2);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin: 0 0 2px;
        }
        .field__value {
            font-size: 14px;
            font-weight: 600;
            margin: 0;
            word-break: break-word;
        }
        .field__value--mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            letter-spacing: 0.03em;
        }
        .card__footer {
            border-top: 1px solid var(--line);
            padding: 12px 22px;
            font-size: 11px;
            color: var(--ink-2);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .card__footer strong { color: var(--ink); }
    </style>
</head>
<body>
    <div class="card">
        <div class="card__header">
            <h1>Aadhaar — verified via DigiLocker</h1>
            <span>UIDAI-signed</span>
        </div>
        <div class="card__body">
            @if($photoBase64)
                <img class="photo" src="data:image/jpeg;base64,{{ $photoBase64 }}" alt="Photo on file">
            @else
                <div class="photo"></div>
            @endif
            <div class="fields">
                <div class="field">
                    <p class="field__label">Name</p>
                    <p class="field__value">{{ $name ?: '—' }}</p>
                </div>
                <div class="field">
                    <p class="field__label">Date of birth</p>
                    <p class="field__value">{{ $dob ?: '—' }}</p>
                </div>
                <div class="field">
                    <p class="field__label">Gender</p>
                    <p class="field__value">{{ $gender ?: '—' }}</p>
                </div>
                <div class="field">
                    <p class="field__label">Aadhaar number</p>
                    <p class="field__value field__value--mono">{{ $maskedAadhaar ?: '—' }}</p>
                </div>
                <div class="field">
                    <p class="field__label">Address</p>
                    <p class="field__value">{{ $address ?: '—' }}</p>
                </div>
            </div>
        </div>
        <div class="card__footer">
            This is a formatted read-out of the actual signed Aadhaar XML on file for
            <strong>{{ $broker->name }}</strong> — not a separately-generated document.
        </div>
    </div>
</body>
</html>
