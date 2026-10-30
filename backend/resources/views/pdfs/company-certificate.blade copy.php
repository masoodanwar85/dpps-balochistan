<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        @font-face { font-family: "Cert Arial"; src: url("{{ $fontDir }}/Arial.ttf") format("truetype"); }
        @font-face { font-family: "Cert Arial"; src: url("{{ $fontDir }}/Arial-Bold.ttf") format("truetype"); font-weight: bold; }
        @font-face { font-family: "Cert Black"; src: url("{{ $fontDir }}/Arial-Black.ttf") format("truetype"); }
        @font-face { font-family: "Cert Times"; src: url("{{ $fontDir }}/TimesNewRoman.ttf") format("truetype"); }
        @font-face { font-family: "Cert Times"; src: url("{{ $fontDir }}/TimesNewRoman-Bold.ttf") format("truetype"); font-weight: bold; }
        @font-face { font-family: "Cert Script"; src: url("{{ $fontDir }}/BrushScript.ttf") format("truetype"); }
        * { margin: 0; padding: 0; }
        body { color: #000; }
        .page { position: relative; width: 297mm; height: 210mm; overflow: hidden; page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .watermark { position: absolute; left: 76.5mm; top: 33mm; width: 144mm; height: 144mm; }
        .bug-row, .bug-col {
            position: absolute;
            background-image: url("{{ $bug }}");
            background-repeat: repeat-x;
            background-size: 8mm 8mm;
        }
        .bug-row { left: 3mm; width: 291mm; height: 8mm; }
        .bug-row.top { top: 3mm; }
        .bug-row.bottom { bottom: 3mm; }
        .bug-col { top: 12.5mm; width: 8mm; height: 185mm; background-repeat: repeat-y; }
        .bug-col.left { left: 3mm; }
        .bug-col.right { left: 286mm; }
        .arch { position: absolute; left: 0; top: 0; width: 297mm; height: 210mm; }
        .logo { position: absolute; }
        .logo-left { left: 20mm; top: 24.5mm; width: 33mm; height: 33mm; }
        .logo-right { left: 238mm; top: 22.5mm; width: 37mm; height: 37mm; }
        .ref-left { position: absolute; left: 17.8mm; top: 15mm; font-family: "Cert Arial", sans-serif; font-size: 12pt; font-weight: bold; }
        .ref-right { position: absolute; left: 150mm; top: 15mm; width: 120.5mm; text-align: right; font-family: "Cert Arial", sans-serif; font-size: 12pt; }
        .govt {
            position: absolute; left: 0; top: 65mm; width: 297mm; text-align: center;
            font-family: "Cert Black", sans-serif; font-size: 25pt;
        }
        .script { font-family: "Cert Script", cursive; font-size: 16pt; }
        .center { position: absolute; left: 12mm; width: 273mm; text-align: center; }
        .line-office { top: 95.6mm; }
        .company-name { top: 107.6mm; font-family: Arial, sans-serif; font-weight: bold; font-size: 16pt; font-style:normal; text-transform: uppercase;}
        .line-licence { top: 122.4mm; font-size: 14pt; }
        .u { font-family: "Cert Times", serif; font-weight: bold; font-size: 11pt; text-decoration: underline; }
        .address { position: absolute; left: 61mm; top: 135.5mm; width: 175mm; font-family: "Cert Script", cursive; font-size: 16pt; }
        .address span { font-family: "Cert Arial", sans-serif; font-size: 11pt; }
        .contact-line { position: absolute; left: 90mm; top: 145mm; width: 185mm; font-family: "Cert Arial", sans-serif; font-size: 11pt; white-space: nowrap; }
        .line-comply { top: 156.6mm; }
        .sign-p1 {
            position: absolute; left: 160mm; top: 180mm; width: 96mm; text-align: right;
            font-family: "Cert Arial", sans-serif; font-weight: bold; font-size: 11.5pt;
        }
        .qr { position: absolute; left: 20mm; top: 172mm; width: 22mm; height: 22mm; }
        .terms-title {
            position: absolute; left: 19mm; top: 27mm; width: 257mm; text-align: center;
            font-family: "Cert Arial", sans-serif; font-weight: bold; font-size: 12pt;
        }
        .terms { position: absolute; left: 19mm; top: 35mm; width: 257mm; }
        .term { font-family: "Cert Arial", sans-serif; font-size: 11pt; line-height: 4.7mm; text-align: justify; }
        .sign-p2 {
            position: absolute; left: 200mm; top: 179mm; width: 71mm; text-align: center;
            font-family: "Cert Arial", sans-serif; font-weight: bold; font-size: 13pt; line-height: 6mm;
        }
    </style>
</head>
<body>
    <div class="page">
        <img class="watermark" src="{{ $watermark }}" alt="">
        <div class="bug-row top"></div>
        <div class="bug-row bottom"></div>
        <div class="bug-col left"></div>
        <div class="bug-col right"></div>
        <img class="logo logo-left" src="{{ $agri }}" alt="">
        <img class="logo logo-right" src="{{ $roundel }}" alt="">
        <img class="arch" src="{{ $arch }}" alt="">

        <div class="ref-left">No: {{ $dispatchNo }}/F# C-4 (17) CRC</div>
        <div class="ref-right"><b>Registration #</b> {{ $licenseNo }}</div>
        <div class="govt">GOVERNMENT OF BALOCHISTAN</div>

        <div class="center script line-office">This office Register under Agriculture Pesticides rules 1973 (part IV) Section II-A(1-10)</div>
        <div class="center company-name">{{ $name }}</div>
        <div class="center script line-licence">
            Is renewed and licensed as a <span class="u">PESTICIDE DISTRIBUTOR</span> in Balochistan from
            <span class="u">{{ $validFrom }} to {{ $validTo }}</span>
        </div>
        <div class="address">Address: <span>{{ $address }}</span></div>
        <div class="contact-line">Email: {{ $email }}&nbsp;&nbsp;&nbsp;(<b>Contact</b> #. {{ $phone }})</div>
        <div class="center script line-comply">The firm must comply with the terms and conditions and any updates from the Agriculture Department.</div>
        <div class="sign-p1">Director Plant Protection</div>
        <img class="qr" src="{{ $qr }}" alt="">
    </div>

    <div class="page">
        <img class="watermark" src="{{ $watermark }}" alt="">
        <div class="bug-row top"></div>
        <div class="bug-row bottom"></div>
        <div class="bug-col left"></div>
        <div class="bug-col right"></div>

        <div class="terms-title">Terms &amp; Conditions for Company/Firm Registration/Operations:</div>
        <div class="terms">
            <div class="term">1. The firm must appoint two agriculture graduates (preferably in Plant Protection) to provide advisory services on Good Agriculture Practices (GAP) and product safety. Failure to do so will incur a RS. 40,000/month penalty per missing staff member. Salaries must be paid via bank accounts, as per government policy. The firm is also required to conduct bi-annual medical checkups for workers and provide free medical treatment when needed.</div>
            <div class="term">2. The firm must inform the regulator of any changes in staff, office/store locations, or infrastructure. Physical verification of infrastructure and assets by the regulator may be conducted as per Federal Government SRO.</div>
            <div class="term">3. The firm will promote eco-friendly bio-pesticides and discourage highly hazardous pesticides (HHPs). Only registered products (Form-12) namely Acetochlor, Sulphur, Thiopenate methyle, Spirofur, Glyphosate, Pendimethalin, Thaimethoxam, Acetamepride, Lambda Cylothrin, Chlorpyrifose, Sega Pest Clear, bermuda are allowed for sale, and expired products must be declared timely. Regular reports on stock and sales, as well as staff visibility, must be submitted to the regulator.</div>
            <div class="term">4. The firm must maintain advisory slips and related records for audit purposes. Monthly stock and sale data must also be furnished in the prescribed format.</div>
            <div class="term">5. The firm will actively participate in government-led campaigns, meetings, seminars, and exhibitions to protect crops and enhance the capacity of the farming community.</div>
            <div class="term">6. Adequate office and warehouse facilities must be established for proper distribution of pesticides. The firm must also provide vehicles to technical staff for advisory services.</div>
            <div class="term">7. The firm must conduct at least one R&amp;D activity, presenting a proposal outlining the objectives, methodology, and expected results to address current agricultural challenges. The results must be shared with the regulator.</div>
            <div class="term">8. One Corporate Social Responsibility (CSR) initiative will be done to the stack holders of concern areas (Farmers, Pesticide Dealers) annually, with prior approval from the regulator.</div>
            <div class="term">9. The firm must comply with the Pakistan Agriculture Pesticides Ordinance 2026, Rules 2026, and any directives issued by the department or regulator. Failure to do so, or failure to submit a renewal application at least two months before expiry, will result in penalties or potential suspension/cancellation of registration.</div>
        </div>
        <div class="sign-p2">Director<br>Plant Protection</div>
    </div>
</body>
</html>
