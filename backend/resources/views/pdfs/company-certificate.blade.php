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
        .bug-row { left: 2.5mm; width: 290mm; height: 7.3mm; }
        .bug-row.top { top: 2.5mm; }
        .bug-row.bottom { bottom: 4.5mm; }
        .bug-col { top: 11mm; width: 7.3mm; height: 186mm; background-repeat: repeat-y; background-size: 7.3mm 7.2308mm; }
        .bug-col.left { left: 2.5mm; }
        .bug-col.right { right: 4.5mm; }
        .ref { position: absolute; left: 11.2mm; top: 11.7mm; width: 274.6mm; border-collapse: collapse; }
        .ref td { font-size: 10.5pt; font-weight: bold; }
        .logo { position: absolute; object-fit: contain; }
        .logo-left { left: 14.2mm; top: 18.2mm; width: 28mm; height: 28mm; }
        .logo-right { left: 252.8mm; top: 17.2mm; width: 30mm; height: 30mm; }
        .arch { position: absolute; left: 11.2mm; top: 18.2mm; width: 274.6mm; height: 68.65mm; }
        .govt {
            position: absolute; left: 11.2mm; top: 65mm; width: 274.6mm;
            text-align: center; font-family: "Cert Black", sans-serif; font-size: 18pt; letter-spacing: 0.15pt;
        }
        .qr { position: absolute; left: 45%; bottom: 35mm; width: 25mm; height: 25mm; }
        .body { position: absolute; left: 11.2mm; top: 85mm; width: 274.6mm; text-align: center; font-size: 15pt;}
        .body p { margin-bottom: 3.2mm; }
        .tight { margin-bottom: 1.2mm;  font-style: italic;}
        .mt-4 { margin-top: 4mm; }
        .bold { font-weight: bold; }
        .gap { margin-left: 4mm; }
        .uS { font-size: 12pt; font-weight: bold; text-decoration: underline; }
        .uL { font-size: 14pt; font-weight: bold; text-decoration: underline; }
        .uL2 { text-decoration: underline; }
        .addr { width: 100%; margin-top: 4mm; margin-bottom: 5mm; border-collapse: collapse; }
        .addr td { font-size: 12pt; font-weight: normal; vertical-align: top; }
        .addr-right { text-align: right; }
        .comp { font-size: 14pt; font-weight: bold; font-style: italic; }
        .company-name { font-weight: 700; text-transform: uppercase;}
        .terms-title {
            position: absolute; left: 19mm; top: 27mm; width: 257mm; text-align: center;
            font-family: "Cert Arial", sans-serif; font-weight: bold; font-size: 12pt;
        }
        .terms { position: absolute; left: 19mm; top: 35mm; width: 257mm; }
        .term { font-family: "Cert Arial", sans-serif; font-size: 11pt; line-height: 4.7mm; text-align: justify; }
        .sign-p1 {
            position: absolute; left: 200mm; top: 179mm; width: 71mm; text-align: center;
            font-family: "Cert Arial", sans-serif; font-weight: bold; font-size: 13pt; line-height: 6mm;
        }
        .sign-p2 {
            position: absolute; left: 200mm; top: 179mm; width: 71mm; text-align: center;
            font-family: "Cert Arial", sans-serif; font-weight: bold; font-size: 13pt; line-height: 6mm;
        }
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
                <td style="width: 50%; text-align: left; white-space: nowrap;">D/No. {{ $dispatchNo }} /F#C-4 (17) CRC</td>
                <td style="width: 50%; text-align: right;">Registration No. {{ $licenseNo }}</td>
            </tr>
        </table>

        <img class="logo logo-left" src="{{ $agri }}" alt="">
        <img class="logo logo-right" src="{{ $roundel }}" alt="">
        <img class="arch" src="{{ $arch }}" alt="">

        <div class="govt">GOVERNMENT OF BALOCHISTAN</div>

        <div class="body">
            <p class="tight">This office Register under Agriculture Pesticides rules 2026 (part IV) Section II-A (1-10)</p>
            <p class="center bold company-name">{{ $name }}</p>
            
            <p>Is registered and licensed as a <span class="uS">PESTICIDE DISTRIBUTOR</span> in Balochistan from <span class="uS">{{ $validFrom }} to {{ $validTo }}</span></p>
            <table class="addr">
                <tr>
                    <td colspan="2" align="center" class="mt-4"><span class="bold">Address:</span> {{ $address }}</td>
                </tr>
                <tr><td colspan="2" style="height: 3mm;"></td></tr>
                <tr>
                    <td align="center" style="width: 50%;">
                        <span class="bold">Email:</span> <span class="uL2">{{ $email }}</span>
                    </td>
                    <td align="center">
                        <span class="bold">Contact #:</span> <span class="uL2">{{ $phone }}</span>
                    </td>
                </tr>
            </table>
            <div style="height: 1mm;"></div>
            <p class="comp">** The firm must comply with the terms and conditions and any updates from the Agriculture Department **</p>
        </div>
        <img class="qr" src="{{ $qr }}" alt="">
        <div class="sign-p1">Director<br>Plant Protection</div>
    </div>
    <div class="page">
        <img class="watermark" src="{{ $watermark }}" alt="">
        <div class="bug-row top"></div>
        <div class="bug-row bottom"></div>
        <div class="bug-col left"></div>
        <div class="bug-col right"></div>

        <div class="terms-title">Terms &amp; Conditions for Company/Firm Registration/Operations:</div>
        <ol class="terms">
            <li class="term">The firm must appoint two agriculture graduates (preferably in Plant Protection) to provide advisory services on Good Agriculture Practices (GAP) and product safety. Failure to do so will incur a RS. 40,000/month penalty per missing staff member. Salaries must be paid via bank accounts, as per government policy. The firm is also required to conduct bi-annual medical checkups for workers and provide free medical treatment when needed.</li>
            <li class="term">The firm must inform the regulator of any changes in staff, office/store locations, or infrastructure. Physical verification of infrastructure and assets by the regulator may be conducted as per Federal Government SRO.</li>
            <li class="term">The firm will promote eco-friendly bio-pesticides and discourage highly hazardous pesticides (HHPs). Only registered products (Form-12) namely Acetochlor, Sulphur, Thiopenate methyle, Spirofur, Glyphosate, Pendimethalin, Thaimethoxam, Acetamepride, Lambda Cylothrin, Chlorpyrifose, Sega Pest Clear, bermuda are allowed for sale, and expired products must be declared timely. Regular reports on stock and sales, as well as staff visibility, must be submitted to the regulator.</li>
            <li class="term">The firm must maintain advisory slips and related records for audit purposes. Monthly stock and sale data must also be furnished in the prescribed format.</li>
            <li class="term">The firm will actively participate in government-led campaigns, meetings, seminars, and exhibitions to protect crops and enhance the capacity of the farming community.</li>
            <li class="term">Adequate office and warehouse facilities must be established for proper distribution of pesticides. The firm must also provide vehicles to technical staff for advisory services.</li>
            <li class="term">The firm must conduct at least one R&amp;D activity, presenting a proposal outlining the objectives, methodology, and expected results to address current agricultural challenges. The results must be shared with the regulator.</li>
            <li class="term">One Corporate Social Responsibility (CSR) initiative will be done to the stack holders of concern areas (Farmers, Pesticide Dealers) annually, with prior approval from the regulator.</li>
            <li class="term">The firm must comply with the Pakistan Agriculture Pesticides Ordinance 2026, Rules 2026, and any directives issued by the department or regulator. Failure to do so, or failure to submit a renewal application at least two months before expiry, will result in penalties or potential suspension/cancellation of registration.</li>
        </ol>
        <div class="sign-p2">Director<br>Plant Protection</div>
    </div>
</body>
</html>
