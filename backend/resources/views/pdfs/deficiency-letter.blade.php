<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 16px; margin-bottom: 0; }
        h2 { font-size: 14px; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #444; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Directorate of Plant Protection, Quetta</h1>
    <h2>Deficiency letter {{ $letterNo }}</h2>
    <p>Issued {{ $issuedOn }}. Reply due {{ $replyDue }}.</p>
    <p>
        Application {{ $applicationNo }}<br>
        Applicant: {{ $applicant }}<br>
        Type: {{ $applicationType }}
    </p>
    <table>
        <thead>
            <tr>
                <th>Annex</th>
                <th>Item</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item['annex'] }}</td>
                    <td>{{ $item['title'] }}</td>
                    <td>{{ $item['remarks'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
