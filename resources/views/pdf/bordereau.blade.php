<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Bordereau Livraison #{{ $livraison->id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 10px;
        }

        .container {
            width: 360px;
            border: 2px solid #000;
            padding: 10px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
        }

        .company {
            font-weight: bold;
            font-size: 18px;
            color: #1e40af;
        }

        .badges {
            margin: 5px 0;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            margin: 0 3px;
            background: #3cb44a;
            color: white;
            font-weight: bold;
            border-radius: 3px;
        }

        .badge-sd {
            background: #c62828;
        }

        .qr-code {
            width: 80px;
            height: 80px;
            border: 1px solid #000;
            float: left;
            margin-right: 10px;
        }

        .qr-code img {
            width: 78px;
            height: 78px;
        }

        .sender {
            overflow: hidden;
        }

        .section-title {
            font-weight: bold;
            margin-bottom: 3px;
        }

        .destinataire {
            margin: 10px 0;
            padding: 8px;
            border: 1px dashed #666;
            clear: both;
        }

        .barcode {
            text-align: center;
            margin: 15px 0;
            padding: 10px 0;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
        }

        .barcode img {
            height: 40px;
        }

        .barcode-text {
            font-weight: bold;
            font-size: 14px;
            margin: 5px 0;
        }

        .pin {
            font-size: 16px;
            font-weight: bold;
            color: #c62828;
            margin-top: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 5px;
        }

        th {
            background: #f3f4f6;
        }

        .footer {
            margin-top: 15px;
            font-size: 10px;
        }

        .city-code {
            position: absolute;
            right: 40px;
            top: 100px;
            text-align: center;
        }

        .city-number {
            font-size: 32px;
            font-weight: bold;
            color: #1e40af;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="company">YALIDINE EXPRESS</div>
            <div class="badges">
                <span class="badge">E-COMMERCE</span>
                <span class="badge badge-sd">SD</span>
            </div>
        </div>

        <div class="sender">
            <div class="qr-code">
                <img src="{{ $qrCode }}" alt="QR Code">
            </div>
            <div>
                <div class="section-title">Expéditeur</div>
                <div><strong>{{ $client->prenom ?? '' }} {{ $client->nom ?? '' }}</strong></div>
                <div>{{ $demande->addresse_depot ?? '' }}</div>
                <div>Tél: {{ $client->telephone ?? '' }}</div>
            </div>
        </div>

        <div class="destinataire">
            <div class="section-title">Destinataire</div>
            <div><strong>{{ $destinataire->prenom ?? '' }} {{ $destinataire->nom ?? '' }}</strong></div>
            <div>{{ $demande->addresse_delivery ?? '' }}</div>
            <div>Tél: {{ $destinataire->telephone ?? '' }}</div>
        </div>

        <div class="city-code">
            <div class="city-number">
                @php echo substr(md5($livraison->id), 0, 2); @endphp
            </div>
        </div>

        <div class="barcode">
            <img src="{{ $barcode }}" alt="Code-barres">
            <div class="barcode-text">{{ $colisLabel }}</div>
            <div class="pin">PIN: {{ $livraison->code_pin }}</div>
        </div>

        <table>
            <tr>
                <th>Description</th>
                <th>Valeur (DH)</th>
            </tr>
            <tr>
                <td>{{ $colis->colis_type ?? 'Colis' }}</td>
                <td><strong>{{ $demande->prix ?? '0' }} DH</strong></td>
            </tr>
            @if($colis->poids ?? false)
            <tr>
                <td>Poids: {{ $colis->poids }} kg</td>
                <td></td>
            </tr>
            @endif
        </table>

        <div class="footer">
            <div>Date: {{ $printDate }}</div>
            <div style="margin: 10px 0; padding: 8px; border: 1px solid #ccc; font-style: italic;">
                Je soussigné certifie l'exactitude des informations.
            </div>
            <div style="text-align: center; color: #666;">
                Référence: #{{ $livraison->id }}
            </div>
        </div>
    </div>
</body>

</html>
