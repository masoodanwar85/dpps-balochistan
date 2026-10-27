<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; }
        .page { position: relative; width: 841.92pt; height: 595.32pt; overflow: hidden; page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .bg { position: absolute; left: 0; top: 0; width: 841.92pt; height: 595.32pt; }
        .cover { position: absolute; background: #fff; }
        .field { position: absolute; }
    </style>
</head>
<body>
    {{-- Positions are the blank spots on the printed A4 landscape sheet. PDF origin is the top left. --}}
    <div class="page">
        @if ($page1)
            <img class="bg" src="{{ $page1 }}" alt="">
            <div class="cover" style="left: 359pt; top: 305.2pt; width: 97pt; height: 16pt;"></div>
            <div class="cover" style="left: 533.6pt; top: 347.4pt; width: 49pt; height: 16pt;"></div>
            <div class="cover" style="left: 596.8pt; top: 347.4pt; width: 58pt; height: 16pt;"></div>
            <div class="cover" style="left: 684pt; top: 46.7pt; width: 90pt; height: 16pt;"></div>
        @endif
        <div class="field" style="left: 157pt; top: 302pt; width: 500pt; text-align: center; font-size: 13pt; font-weight: bold; line-height: 16pt;">{{ $name }}</div>
        <div class="field" style="left: 533.6pt; top: 347.8pt; width: 49pt; text-align: center; font-size: 8pt;">{{ $validFrom }}</div>
        <div class="field" style="left: 596.8pt; top: 347.8pt; width: 58pt; text-align: center; font-size: 8pt;">{{ $validTo }}</div>
        <div class="field" style="left: 226pt; top: 388pt; width: 520pt; font-size: 10pt;">{{ $address }}</div>
        <div class="field" style="left: 319pt; top: 406.5pt; width: 86pt; font-size: 6pt;">{{ $email }}</div>
        <div class="field" style="left: 474pt; top: 404.2pt; width: 200pt; font-size: 9pt;">{{ $phone }}</div>
        <div class="field" style="left: 690pt; top: 47.6pt; width: 85pt; font-size: 8pt; font-weight: bold;">{{ $licenseNo }}</div>
        <img class="field" src="{{ $qr }}" alt="" style="left: 387pt; top: 500pt; width: 68pt; height: 68pt;">
    </div>
    @if ($page2)
        <div class="page">
            <img class="bg" src="{{ $page2 }}" alt="">
        </div>
    @endif
</body>
</html>
