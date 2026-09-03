<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reçu de paiement - {{ $payment->reference }}</title>
    <style>
        /* ... (Garde tout ton CSS fourni, il est parfait) ... */
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.2; margin: 8px 6px; color: #333; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; border-bottom: 2px solid #2563EB; padding-bottom: 10px; }
        .header-table td { vertical-align: middle; }
        .logo-cell { width: 100px; text-align: left; }
        .logo { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: none; }
        .info-cell { text-align: right; }
        .school-name { font-size: 16px; font-weight: bold; color: #2563EB; }
        .school-info { font-size: 10px; color: #666; }
        .title { font-size: 16px; font-weight: bold; text-align: center; margin: 8px 0; color: #1e40af; }
        .subtitle { font-size: 10px; color: #666; }
        .info-grid { width: 100%; margin: 14px 0; border-collapse: collapse; }
        .info-grid td { padding: 8px 4px; vertical-align: top; }
        .info-label { font-weight: bold; width: 110px; color: #555; font-size: 11px; }
        .info-value { color: #333; font-size: 11px; }
        .amount { font-size: 15px; font-weight: bold; color: #e67e22; }
        .signatures-table { width: 100%; margin-top: 25px; margin-bottom: 18px; border-collapse: collapse; }
        .signatures-table td { width: 50%; text-align: center; vertical-align: bottom; padding: 0 20px; }
        .signature-line { border-top: 1px solid #333; margin-top: 30px; padding-top: 8px; font-size: 10px; }
        .qr-section { margin-top: 10px; text-align: center; }
        .qr-code { width: 80px; height: 80px; }
        .qr-text { font-size: 9px; color: #2563EB; margin-top: 5px; }
        .footer { text-align: center; font-size: 8px; color: #999; border-top: 0.5px solid #ccc; padding-top: 8px; margin-top: 12px; }
        .receipt-copy { page-break-after: avoid; margin-bottom: 12px; padding-bottom: 12px; }
        .first-copy { border-bottom: 1px dashed #ccc; }
        @page { margin: 6px 6px; }
    </style>
</head>
<body>

@php
    $copies = [
        ['label' => 'ÉTUDIANT', 'note' => 'Exemplaire à conserver par l\'étudiant'],
        ['label' => 'ADMINISTRATION', 'note' => 'Exemplaire à conserver par le centre']
    ];
@endphp

@foreach($copies as $index => $copy)
    <div class="receipt-copy {{ $index == 0 ? 'first-copy' : '' }}">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    <div class="logo" style="background: linear-gradient(135deg, #2563EB, #7C3AED); display: flex; align-items: center; justify-content: center; color: white; font-size: 28px; font-weight: bold;">
                        {{ substr($campus->name ?? 'C', 0, 1) }}
                    </div>
                </td>
                <td class="info-cell">
                    <div class="school-name">{{ $campus->name ?? 'CAB Informatique' }}</div>
                    <div class="school-info">
                        {{ $campus->address ?? '' }}<br>
                        Tél: {{ $campus->phone ?? '' }} - Email: {{ $campus->email ?? '' }}
                    </div>
                </td>
            </tr>
        </table>

        <div class="title">
            REÇU DE PAIEMENT<br>
            <span class="subtitle">{{ $copy['label'] }} - {{ $copy['note'] }}</span>
        </div>

        <table class="info-grid">
            <tr>
                <td class="info-label">N° Reçu :</td>
                <td class="info-value"><strong>{{ $payment->reference }}</strong></td>
                <td class="info-label">Date :</td>
                <td class="info-value">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="info-label">Étudiant :</td>
                <td class="info-value">{{ $student->registration_number }} - {{ $student->first_name }} {{ $student->last_name }}</td>
                <td class="info-label">Enr. par :</td>
                <td class="info-value">{{ $user->first_name }} {{ $user->last_name }}</td>
            </tr>
            <tr>
                <td class="info-label">Formation :</td>
                <td class="info-value" colspan="3">{{ $formation->name }}</td>
            </tr>
            <tr>
                <td class="info-label">Montant versé :</td>
                <td class="info-value amount">{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</td>
                <td class="info-label">Reste à payer :</td>
                <td class="info-value">{{ number_format($registration->balance, 0, ',', ' ') }} FCFA</td>
            </tr>
        </table>

        <table class="signatures-table">
            <tr>
                <td><div class="signature-line">Signature du payeur</div></td>
                <td><div class="signature-line">Cachet et signature du centre</div></td>
            </tr>
        </table>

        <div class="qr-section">
            @if(isset($qrCodeBase64) && $qrCodeBase64)
                <img src="{{ $qrCodeBase64 }}" class="qr-code" alt="QR Code">
                <div class="qr-text">🔒 Scanner pour vérifier l'authenticité du reçu</div>
            @endif
        </div>

        <div class="footer">
            Merci de faire confiance à {{ $campus->name ?? 'CAB Informatique' }}
        </div>
    </div>
@endforeach
</body>
</html>