<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reçu de paiement - {{ $payment->reference }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.2; margin: 8px 6px; color: #333; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; border-bottom: 2px solid #2563EB; padding-bottom: 10px; }
        .header-table td { vertical-align: middle; }
        .logo-cell { width: 100px; text-align: left; }
        .logo { width: 70px; height: 70px; border-radius: 50%; object-fit: contain; border: none; }
        .info-cell { text-align: right; }
        .brand-name { font-size: 18px; font-weight: bold; color: #2563EB; letter-spacing: 0.5px; }
        .campus-name { font-size: 12px; font-weight: bold; color: #1e40af; margin-top: 2px; }
        .school-info { font-size: 10px; color: #666; margin-top: 3px; }
        .title { font-size: 16px; font-weight: bold; text-align: center; margin: 8px 0; color: #1e40af; }
        .subtitle { font-size: 10px; color: #666; }
        .info-grid { width: 100%; margin: 14px 0; border-collapse: collapse; }
        .info-grid td { padding: 8px 4px; vertical-align: top; }
        .info-label { font-weight: bold; width: 110px; color: #555; font-size: 11px; }
        .info-value { color: #333; font-size: 11px; }
        .amount { font-size: 15px; font-weight: bold; color: #e67e22; }
        .signatures-table { width: 100%; margin-top: 20px; margin-bottom: 14px; border-collapse: collapse; }
        .signatures-table td { width: 50%; text-align: center; vertical-align: bottom; padding: 0 20px; }
        .signature-line { border-top: 1px solid #333; margin-top: 22px; padding-top: 8px; font-size: 10px; }
        .qr-section { margin-top: 4px; text-align: center; }
        .qr-code { width: 80px; height: 80px; }
        .qr-text { font-size: 9px; color: #2563EB; margin-top: 5px; }
        .footer { text-align: center; font-size: 8px; color: #999; border-top: 0.5px solid #ccc; padding-top: 8px; margin-top: 10px; }
        .receipt-copy { page-break-after: avoid; margin-bottom: 4px; padding-bottom: 4px; }
        .first-copy { border-bottom: 1px dashed #ccc; }
        @page { margin: 6px 6px; }
    </style>
</head>
<body>

@php
    // ✅ Logo : chemin absolu (obligatoire pour DomPDF)
    $logoPath = public_path('logo.jpg');
    $logoExists = file_exists($logoPath);

    // Fallback si le logo n'existe pas
    $campusName = $campus->name ?? '';

    // 2 copies : Étudiant + Administration
    $copies = [
        ['label' => 'ÉTUDIANT', 'note' => 'Exemplaire à conserver par l\'étudiant'],
        ['label' => 'ADMINISTRATION', 'note' => 'Exemplaire à conserver par le centre'],
    ];

    // ✅ Récupérer le créateur du paiement (pas l'utilisateur qui génère le PDF)
    $creator = $payment->createdBy ?? null;
@endphp

@foreach($copies as $index => $copy)
    <div class="receipt-copy {{ $index == 0 ? 'first-copy' : '' }}">

        {{-- ═══ HEADER ═══ --}}
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    @if($logoExists)
                        <img src="{{ $logoPath }}" alt="Logo" class="logo">
                    @else
                        <div class="logo" style="background: linear-gradient(135deg, #2563EB, #7C3AED); display: flex; align-items: center; justify-content: center; color: white; font-size: 28px; font-weight: bold;">
                            CAB
                        </div>
                    @endif
                </td>
                <td class="info-cell">
                    <div class="brand-name">CAB INFORMATIQUE</div>
                    @if($campusName)
                        <div class="campus-name">{{ $campusName }}</div>
                    @endif
                    <div class="school-info">
                        {{ $campus->address ?? '' }}
                        @if($campus->phone) • Tél: {{ $campus->phone }}@endif
                        @if($campus->email) • {{ $campus->email }}@endif
                    </div>
                </td>
            </tr>
        </table>

        {{-- ═══ TITRE ═══ --}}
        <div class="title">
            REÇU DE PAIEMENT<br>
            <span class="subtitle">{{ $copy['label'] }} - {{ $copy['note'] }}</span>
        </div>

        {{-- ═══ INFOS ═══ --}}
        <table class="info-grid">
            <tr>
                <td class="info-label">N° Reçu :</td>
                <td class="info-value"><strong>{{ $payment->reference }}</strong></td>
                <td class="info-label">Date :</td>
                <td class="info-value">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="info-label">Étudiant :</td>
                <td class="info-value">{{ $student->registration_number }} - {{ $student->last_name }} {{ $student->first_name }}</td>
                <td class="info-label">Enr. par :</td>
                {{-- ✅ Nom puis Prénom du CRÉATEUR du paiement --}}
                <td class="info-value">
                    @if($creator)
                        {{ $creator->last_name }} {{ $creator->first_name }}
                    @else
                        -
                    @endif
                </td>
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

        {{-- ═══ SIGNATURES ═══ --}}
        <table class="signatures-table">
            <tr>
                <td><div class="signature-line">Signature du payeur</div></td>
                <td><div class="signature-line">Cachet et signature du centre</div></td>
            </tr>
        </table>

        {{-- ═══ QR CODE ═══ --}}
        <div class="qr-section">
            @if(isset($qrCodeBase64) && $qrCodeBase64)
                <img src="{{ $qrCodeBase64 }}" class="qr-code" alt="QR Code">
                <div class="qr-text">🔒 Scanner pour vérifier l'authenticité du reçu</div>
            @endif
        </div>

        {{-- ═══ FOOTER ═══ --}}
        <div class="footer">
            {{ $campus->name ?? 'CAB Informatique' }} - Merci de faire confiance à notre établissement.
        </div>
    </div>
@endforeach
</body>
</html>