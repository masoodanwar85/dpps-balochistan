<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        @font-face { font-family: "Cert Arial"; src: url("{{ $fontDir }}/Arial.ttf") format("truetype"); }
        @font-face { font-family: "Cert Arial"; src: url("{{ $fontDir }}/Arial-Bold.ttf") format("truetype"); font-weight: bold; }
        @font-face { font-family: "Cert Arial"; src: url("{{ $fontDir }}/Arial-Italic.ttf") format("truetype"); font-style: italic; }
        @font-face { font-family: "Cert Arial"; src: url("{{ $fontDir }}/Arial-BoldItalic.ttf") format("truetype"); font-weight: bold; font-style: italic; }
        @font-face { font-family: "Cert Black"; src: url("{{ $fontDir }}/Arial-Black.ttf") format("truetype"); }
        * { margin: 0; padding: 0; }
        body { font-family: "Cert Arial", sans-serif; color: #111; }
        .page { position: relative; width: 297mm; height: 210mm; overflow: hidden; page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .watermark { position: absolute; left: 73.5mm; top: 36.3mm; width: 150mm; height: 150mm; opacity: 0.14; }
        .bug-row, .bug-col {
            position: absolute;
            background-image: url("{{ $bug }}");
            background-repeat: repeat-x;
            background-size: 7.3mm 7.3mm;
        }
        .bug-row { left: 2.5mm; width: 292mm; height: 7.3mm; }
        .bug-row.top { top: 2.5mm; }
        .bug-row.bottom { bottom: 2.5mm; }
        .bug-col { top: 11mm; width: 7.3mm; height: 188mm; background-repeat: repeat-y; background-size: 7.3mm 7.2308mm; }
        .bug-col.left { left: 2.5mm; }
        .bug-col.right { right: 2.5mm; }
        .ref { position: absolute; left: 11.2mm; top: 11.7mm; width: 274.6mm; border-collapse: collapse; }
        .ref td { font-size: 10.5pt; font-weight: bold; }
        .logo { position: absolute; object-fit: contain; }
        .logo-left { left: 14.2mm; top: 18.2mm; width: 28mm; height: 28mm; }
        .logo-right { left: 252.8mm; top: 17.2mm; width: 30mm; height: 30mm; }
        .arch { position: absolute; left: 11.2mm; top: 18.2mm; width: 274.6mm; height: 68.65mm; }
        .govt {
            position: absolute; left: 11.2mm; top: 52.5mm; width: 274.6mm;
            text-align: center; font-family: "Cert Black", sans-serif; font-size: 17pt; letter-spacing: 0.15pt;
        }
        .photo {
            position: absolute; left: 133mm; top: 66.2mm; width: 31mm; height: 32mm;
            border: 1.1mm solid #000; background: #fff; overflow: hidden;
        }
        /* Center the photo */
        .photo { text-align: center; }
        .photo img { width: auto; height: auto; min-width: 31mm; max-height: 32mm; }
        .qr { position: absolute; left: 200mm; top: 71.2mm; width: 25mm; height: 25mm; }
        .body { position: absolute; left: 11.2mm; top: 101.2mm; width: 274.6mm; text-align: center; font-size: 15pt; }
        .body p { margin-bottom: 3.2mm; }
        .tight { margin-bottom: 1.2mm; }
        .bold { font-weight: bold; }
        .gap { margin-left: 4mm; }
        .uS { font-size: 12pt; font-weight: bold; text-decoration: underline; }
        .uL { font-size: 14pt; font-weight: bold; text-decoration: underline; }
        .addr { width: 100%; margin-top: 4mm; margin-bottom: 5mm; border-collapse: collapse; }
        .addr td { font-size: 12pt; font-weight: normal; vertical-align: top; }
        .addr-right { text-align: right; }
        .comp { font-size: 14pt; font-weight: bold; font-style: italic; }
        .signs { position: absolute; left: 29.2mm; top: 188mm; width: 238.6mm; border-collapse: collapse; }
        .signs td { font-size: 9.5pt; font-weight: bold; line-height: 1.25; vertical-align: bottom; }
        .sign-left { text-align: center; width: 50%; }
        .sign-right { text-align: right; padding-right: 4mm; }
        .terms { position: absolute; left: 0; top: 0; width: 297mm; height: 210mm; }
    </style>
</head>
<body>
    <div class="page">
        <img class="watermark" src="{{ $agri }}" alt="">
        <div class="bug-row top"></div>
        <div class="bug-row bottom"></div>
        <div class="bug-col left"></div>
        <div class="bug-col right"></div>

        <table class="ref">
            <tr>
                <td style="width: 42%; text-align: left; white-space: nowrap;">D/No. {{ $dispatchNo }} /F#D-1(16)/DRC(Dated. {{ $dated }} )</td>
                <td style="width: 16%; text-align: center;">Form-15</td>
                <td style="width: 42%; text-align: right;">Registration No. {{ $licenseNo }}</td>
            </tr>
        </table>

        <img class="logo logo-left" src="{{ $agri }}" alt="">
        <img class="logo logo-right" src="{{ $roundel }}" alt="">
        <img class="arch" src="{{ $arch }}" alt="">

        <div class="govt">GOVERNMENT OF BALOCHISTAN</div>

        <div class="photo">
            @if ($photo)
                <img src="{{ $photo }}" alt="">
            @endif
        </div>
        <img class="qr" src="{{ $qr }}" alt="">

        <div class="body">
            <p class="tight">This office Register under Agriculture Pesticides rules 2026 (part IV) Section II-A (1-10)</p>
            <p>Application NO. {{ $applicationNo }} /F#D1() Dated {{ $applicationDate }} has been Renewed</p>
            <p>
                <span class="bold">{{ $name }}</span>
                <span class="bold gap">S/O</span>
                <span class="bold gap">{{ $father }}</span>
                <span class="bold gap">(CNIC #: {{ $cnic }})</span>
            </p>
            <p>Is registered and licensed as a <span class="uS">PESTICIDE DEALER</span> in <span class="uL">{{ $district }}</span> from <span class="uS">{{ $validFrom }} to {{ $validTo }}</span></p>
            <table class="addr">
                <tr>
                    <td align="center"><span class="bold">Address:</span> {{ $address }}
                    &nbsp;&nbsp;&nbsp;(<span class="bold">Contact #:</span> {{ $phone }})</td>
                </tr>
            </table>
            <div style="height: 1mm;"></div>
            <p class="comp">** The firm must comply with the terms and conditions and any updates from the Agriculture Department **</p>
        </div>

        <table class="signs">
            <tr>
                <td class="sign-left">Deputy Director (PP) /Inspector<br>District {{ $district }}</td>
                <td class="sign-right">Director Plant Protection</td>
            </tr>
        </table>
    </div>
    <div class="page">
        <img class="terms" src="{{ $terms }}" alt="">
    </div>
</body>
</html>
