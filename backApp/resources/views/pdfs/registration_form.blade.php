<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Fiche d'inscription - {{ $student->registration_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.3;
            margin: 15px;
            color: #1A1A3E;
        }

        /* ═══ HEADER ═══ */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            border-bottom: 2px solid #2563EB;
            padding-bottom: 10px;
        }
        .header-table td { vertical-align: middle; }
        .logo-cell { width: 100px; text-align: left; }
        .logo {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: contain;
            border: none;
        }
        .info-cell { text-align: right; }
        .brand-name {
            font-size: 18px;
            font-weight: bold;
            color: #2563EB;
            letter-spacing: 0.5px;
        }
        .campus-name {
            font-size: 12px;
            font-weight: bold;
            color: #1e40af;
            margin-top: 2px;
        }
        .school-info {
            font-size: 10px;
            color: #666;
            margin-top: 3px;
        }

        /* ═══ TITRE ═══ */
        .title {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            margin: 10px 0;
            text-decoration: underline;
            color: #3A7BD5;
        }

        /* ═══ SECTIONS ═══ */
        .section { margin-bottom: 12px; page-break-inside: avoid; }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            background-color: #E8F0FE;
            padding: 5px 8px;
            margin-bottom: 8px;
            border-left: 3px solid #4F8FFF;
            color: #1A1A3E;
        }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .info-table td { padding: 4px; vertical-align: top; }
        .info-label { font-weight: bold; width: 120px; color: #6B7280; font-size: 10px; }
        .info-value { color: #1A1A3E; font-size: 10px; }

        /* ═══ PHOTO ═══ */
        .photo-frame { width: 120px; text-align: center; padding: 5px; }
        .student-photo {
            width: 110px;
            height: 130px;
            border-radius: 5px;
            object-fit: cover;
            border: 1px solid #E5E7EB;
        }
        .no-photo {
            width: 110px;
            height: 130px;
            border-radius: 5px;
            background-color: #F5F7FA;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9CA3AF;
            font-size: 11px;
            border: 1px solid #E5E7EB;
        }
        .photo-label { font-size: 8px; color: #9CA3AF; margin-top: 5px; }

        /* ═══ 2 COLONNES ═══ */
        .two-columns { width: 100%; margin-bottom: 12px; }
        .two-columns td { width: 50%; vertical-align: top; }
        .left-col { padding-right: 10px; }
        .right-col { padding-left: 10px; }

        /* ═══ FRAIS ═══ */
        .fees-table { width: 100%; border-collapse: collapse; }
        .fees-table td { padding: 4px; vertical-align: top; }
        .amount-total { color: #1A1A3E; }
        .amount-paid { color: #4F8FFF; }
        .amount-remaining { color: #FF6B6B; }

        /* ═══ SIGNATURES ═══ */
        .signatures-table {
            width: 100%;
            margin-top: 25px;
            margin-bottom: 15px;
            border-collapse: collapse;
        }
        .signatures-table td {
            width: 50%;
            text-align: center;
            vertical-align: bottom;
            padding: 0 20px;
        }
        .signature-line {
            border-top: 1px solid #1A1A3E;
            margin-top: 35px;
            padding-top: 8px;
            font-size: 9px;
        }
        .signature-date { font-size: 8px; color: #6B7280; margin-top: 5px; }

        /* ═══ FOOTER ═══ */
        .footer-info {
            width: 100%;
            margin-top: 15px;
            border-top: 1px solid #E5E7EB;
            padding-top: 10px;
        }
        .footer-table { width: 100%; border-collapse: collapse; }
        .footer-table td { vertical-align: middle; }
        .qr-cell { width: 90px; text-align: center; }
        .qr-code { width: 80px; height: 80px; }
        .verification-text { font-size: 8px; color: #9CA3AF; text-align: center; margin-top: 5px; }
        .footer-text { font-size: 8px; color: #6B7280; text-align: left; }
        .fixed-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #9CA3AF;
            border-top: 1px solid #E5E7EB;
            padding-top: 6px;
            background-color: white;
        }
        @page { margin: 15px; }
    </style>
</head>
<body>

@php
    // ✅ Logo : chemin absolu
    $logoPath = public_path('logo.jpg');
    $logoExists = file_exists($logoPath);

    $campusName = $campus->name ?? '';

    // ✅ Récupérer le créateur de l'inscription
    $creator = $registration->createdBy ?? null;
@endphp

    <!-- ═══ EN-TÊTE (harmonisé avec le reçu) ═══ -->
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

    <!-- Titre -->
    <div class="title">FICHE D'INSCRIPTION</div>

    <!-- Informations étudiant -->
    <div class="section">
        <div class="section-title">INFORMATIONS ÉTUDIANT</div>
        <table class="info-table">
            <tr>
                <td style="width: 65%; vertical-align: top;">
                    <table class="info-table">
                        <tr>
                            <td class="info-label">Matricule :</td>
                            <td class="info-value"><strong>{{ $student->registration_number }}</strong></td>
                        </tr>
                        {{-- ✅ Nom puis Prénom --}}
                        <tr>
                            <td class="info-label">Nom complet :</td>
                            <td class="info-value">{{ $student->last_name }} {{ $student->first_name }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Date naissance :</td>
                            <td class="info-value">{{ $student->date_of_birth ? \Carbon\Carbon::parse($student->date_of_birth)->format('d/m/Y') : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Lieu de résidence :</td>
                            <td class="info-value">{{ $student->residence ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Téléphone :</td>
                            <td class="info-value">{{ $student->phone ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Email :</td>
                            <td class="info-value">{{ $student->email ?? '-' }}</td>
                        </tr>

                        <!-- Champs académiques -->
                        <tr>
                            <td class="info-label">Diplôme le plus élevé :</td>
                            <td class="info-value">{{ $student->highest_diploma ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Année d'obtention :</td>
                            <td class="info-value">{{ $student->diploma_year ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Langues parlées :</td>
                            <td class="info-value">
                                @php
                                    $langs = is_array($student->languages)
                                        ? $student->languages
                                        : json_decode($student->languages, true);
                                @endphp

                                @if(is_array($langs) && count($langs) > 0)
                                    {{ implode(', ', array_map('ucfirst', $langs)) }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 35%; text-align: center; vertical-align: top;">
                    <div class="photo-frame">
                        @if($student->photo && \Illuminate\Support\Facades\Storage::disk('private')->exists($student->photo))
                            @php
                                $photoPath = storage_path('app/private/' . $student->photo);
                                $photoData = base64_encode(file_get_contents($photoPath));
                            @endphp
                            <img src="data:image/jpeg;base64,{{ $photoData }}" class="student-photo" alt="Photo">
                        @else
                            <div class="no-photo">
                                <span>📷<br>Pas de photo</span>
                            </div>
                        @endif
                        <div class="photo-label">Photo d'identité</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Parent et Inscription -->
    <table class="two-columns">
        <tr>
            <td class="left-col">
                <div class="section-title">PARENT / TUTEUR</div>
                <table class="info-table">
                    <tr>
                        <td class="info-label">Nom :</td>
                        <td class="info-value">{{ $student->parent_name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Téléphone :</td>
                        <td class="info-value">{{ $student->parent_phone ?? '-' }}</td>
                    </tr>
                </table>
            </td>
            <td class="right-col">
                <div class="section-title">INFORMATIONS INSCRIPTION</div>
                <table class="info-table">
                    <tr>
                        <td class="info-label">Formation :</td>
                        <td class="info-value"><strong>{{ $formation->name }}</strong> ({{ $formation->abbreviation }})</td>
                    </tr>
                    <tr>
                        <td class="info-label">Durée :</td>
                        <td class="info-value">{{ $formation->duration_months ?? '-' }} mois</td>
                    </tr>
                    <tr>
                        <td class="info-label">Année scolaire :</td>
                        <td class="info-value">{{ $academicYear->label ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Date inscription :</td>
                        <td class="info-value">{{ \Carbon\Carbon::parse($registration->created_at)->format('d/m/Y') }}</td>
                    </tr>
                    {{-- ✅ Inscrit par : créateur de l'inscription --}}
                    <tr>
                        <td class="info-label">Inscrit par :</td>
                        <td class="info-value">
                            @if($creator)
                                <strong>{{ $creator->last_name }} {{ $creator->first_name }}</strong>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="info-label">Statut :</td>
                        <td class="info-value" style="text-transform: uppercase;">{{ $registration->status ?? 'Confirmé' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Frais de scolarité -->
    <div class="section">
        <div class="section-title">FRAIS DE SCOLARITÉ</div>
        <table class="fees-table">
            <tr>
                <td><span class="info-label">Frais de scolarité :</span></td>
                <td class="amount-total">
                    <strong>{{ number_format($tuitionFees, 0, ',', ' ') }} FCFA</strong>
                    @if(isset($hasPromo) && $hasPromo)
                        <span style="font-size: 9px; color: #FF6B6B; font-weight: normal; margin-left: 5px;">(Tarif promo appliqué)</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td><span class="info-label">Montant versé :</span></td>
                <td class="amount-paid"><strong>{{ number_format($amountPaid, 0, ',', ' ') }} FCFA</strong></td>
            </tr>
            <tr>
                <td><span class="info-label">Reste à payer :</span></td>
                <td class="amount-remaining"><strong>{{ number_format($remainingAmount, 0, ',', ' ') }} FCFA</strong></td>
            </tr>
        </table>
    </div>

    <!-- Signatures -->
    <table class="signatures-table">
        <tr>
            <td>
                <div class="signature-line">Signature de l'étudiant</div>
                <div class="signature-date">(Précédé de la mention "lu et approuvé")</div>
            </td>
            <td>
                <div class="signature-line">Cachet et signature du centre</div>
                <div class="signature-date">Fait à {{ $campus->city ?? 'Douala' }}, le {{ \Carbon\Carbon::now()->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <!-- QR Code -->
    <div class="footer-info">
        <table class="footer-table">
            <tr>
                <td class="footer-text">
                    <strong>🔒 Document sécurisé</strong><br>
                    Ce document peut être vérifié en scannant le QR code.
                </td>
                <td class="qr-cell">
                    @if(isset($qrCodeBase64) && $qrCodeBase64)
                        <img src="{{ $qrCodeBase64 }}" class="qr-code" alt="QR Code">
                        <div class="verification-text">📱 Scanner pour vérifier</div>
                    @else
                        <div style="width: 80px; height: 80px; border: 1px solid #ccc; display: flex; align-items: center; justify-content: center; flex-direction: column; background: #f9f9f9; border-radius: 4px;">
                            <span style="font-size: 20px;">🔒</span>
                            <span style="font-size: 7px; text-align: center; color: #6B7280;">Document<br>sécurisé</span>
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <!-- Footer -->
    <div class="fixed-footer">
        {{ $campus->name ?? 'CAB Informatique' }} - Merci de faire confiance à notre établissement.
    </div>

</body>
</html>