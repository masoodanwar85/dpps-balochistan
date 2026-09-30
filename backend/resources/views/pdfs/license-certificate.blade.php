<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 16px; margin-bottom: 0; }
        h2 { font-size: 14px; }
        .note { color: #444; margin-top: 24px; }
    </style>
</head>
<body>
    <h1>Directorate of Plant Protection, Balochistan</h1>
    <h2>License certificate</h2>
    <p>This is a placeholder layout. The official certificate format is not in use yet.</p>
    <p>
        Name: {{ $applicant }}<br>
        Type: {{ $kind }}<br>
        License No: {{ $licenseNo }}<br>
        Valid: {{ $validFrom }} to {{ $validTo }}
    </p>
    <p><img src="{{ $qr }}" width="140" height="140" alt="Verification QR code"></p>
    <p class="note">Scan the code to open the public verification page.</p>
</body>
</html>
