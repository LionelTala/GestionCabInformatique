<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rapport de Scolarité</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            line-height: 1.3;
            color: #333;
            padding: 15px 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 3px solid #15157D;
            padding-bottom: 10px;
        }
        .header h1 {
            font-size: 18px;
            color: #15157D;
            margin-bottom: 3px;
        }
        .header .subtitle {
            font-size: 10px;
            color: #6B7280;
        }
        
        .campus-section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .campus-header {
            background: #E8F0FE;
            padding: 6px 10px;
            border-left: 4px solid #15157D;
            margin-bottom: 8px;
        }
        .campus-header h2 {
            font-size: 13px;
            color: #15157D;
        }
        
        .formation-section {
            margin-bottom: 15px;
        }
        .formation-header {
            background: #F5F7FA;
            padding: 5px 8px;
            border-left: 3px solid #6C3CE1;
            margin-bottom: 6px;
        }
        .formation-header h3 {
            font-size: 11px;
            color: #6C3CE1;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        thead {
            background: #F5F7FA;
        }
        th {
            padding: 5px 4px;
            text-align: left;
            font-size: 8px;
            font-weight: 600;
            color: #6B7280;
            text-transform: uppercase;
            border-bottom: 1px solid #E5E7EB;
        }
        td {
            padding: 4px;
            border-bottom: 1px solid #E5E7EB;
            font-size: 8px;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .paid { color: #10B981; font-weight: 600; }
        .unpaid { color: #EF4444; font-weight: 600; }
        
        .formation-summary {
            background: #F9FAFB;
            padding: 6px 8px;
            border-radius: 4px;
            margin-top: 6px;
            font-size: 8px;
        }
        .formation-summary table {
            margin: 0;
        }
        .formation-summary td {
            border: none;
            padding: 2px 6px;
        }
        .formation-summary .label {
            font-weight: 600;
            color: #6B7280;
        }
        
        .campus-summary {
            background: #15157D;
            color: white;
            padding: 8px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 9px;
        }
        .campus-summary h4 {
            font-size: 11px;
            margin-bottom: 6px;
            text-align: center;
        }
        .campus-summary table {
            margin: 0;
        }
        .campus-summary td {
            border: none;
            padding: 3px 8px;
            color: white;
        }
        
        .grand-summary {
            background: #0B1C30;
            color: white;
            padding: 10px;
            border-radius: 6px;
            margin-top: 15px;
            font-size: 10px;
        }
        .grand-summary h3 {
            font-size: 12px;
            margin-bottom: 8px;
            text-align: center;
        }
        .grand-summary table {
            margin: 0;
        }
        .grand-summary td {
            border: none;
            padding: 4px 10px;
            color: white;
        }
        .grand-summary .value {
            font-size: 12px;
            font-weight: bold;
        }
        
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #E5E7EB;
            text-align: center;
            font-size: 7px;
            color: #9CA3AF;
        }
        
        @page {
            margin: 10mm;
            size: A4 portrait;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>RAPPORT D'ÉTAT DE SCOLARITÉ</h1>
        <div class="subtitle">Situation financière des étudiants</div>
    </div>

    @foreach($groupedData as $campusData)
        <div class="campus-section">
            @if($isSuperAdmin)
                <div class="campus-header">
                    <h2>{{ $campusData['campus']->name }}</h2>
                </div>
            @endif

            @foreach($campusData['formations'] as $formationData)
                <div class="formation-section">
                    <div class="formation-header">
                        <h3>{{ $formationData['formation']->name }}</h3>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>Matricule</th>
                                <th>Nom Complet</th>
                                <th class="text-right">Frais Total</th>
                                <th class="text-right">Déjà Payé</th>
                                <th class="text-right">Reste</th>
                                <th class="text-center">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($formationData['registrations'] as $reg)
                                <tr>
                                    <td>{{ $reg->student->registration_number }}</td>
                                    <td>{{ $reg->student->first_name }} {{ $reg->student->last_name }}</td>
                                    <td class="text-right">{{ number_format($reg->formation->tuition_fees, 0, ',', ' ') }}</td>
                                    <td class="text-right paid">{{ number_format($reg->amount_paid, 0, ',', ' ') }}</td>
                                    <td class="text-right unpaid">{{ number_format($reg->balance, 0, ',', ' ') }}</td>
                                    <td class="text-center">
                                        @if($reg->balance == 0)
                                            <span class="paid">Soldé</span>
                                        @elseif($reg->amount_paid > 0)
                                            <span style="color: #F59E0B; font-weight: 600;">Partiel</span>
                                        @else
                                            <span class="unpaid">Non payé</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="formation-summary">
                        <table>
                            <tr>
                                <td class="label">Récap Formation :</td>
                                <td class="text-right">Attendu : <strong>{{ number_format($formationData['total_expected'], 0, ',', ' ') }} FCFA</strong></td>
                                <td class="text-right paid">Perçu : <strong>{{ number_format($formationData['total_paid'], 0, ',', ' ') }} FCFA</strong></td>
                                <td class="text-right unpaid">Reste : <strong>{{ number_format($formationData['total_balance'], 0, ',', ' ') }} FCFA</strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            @endforeach

            <div class="campus-summary">
                <h4>RÉCAPITULATIF CAMPUS - {{ $campusData['campus']->name }}</h4>
                <table>
                    <tr>
                        <td class="label">Total Attendu :</td>
                        <td class="text-right value">{{ number_format($campusData['campus_total_expected'], 0, ',', ' ') }} FCFA</td>
                    </tr>
                    <tr>
                        <td class="label">Total Perçu :</td>
                        <td class="text-right value">{{ number_format($campusData['campus_total_paid'], 0, ',', ' ') }} FCFA</td>
                    </tr>
                    <tr>
                        <td class="label">Total Reste :</td>
                        <td class="text-right value">{{ number_format($campusData['campus_total_balance'], 0, ',', ' ') }} FCFA</td>
                    </tr>
                </table>
            </div>
        </div>
    @endforeach

    <div class="grand-summary">
        <h3>RÉCAPITULATIF GÉNÉRAL</h3>
        <table>
            <tr>
                <td class="label">Total Attendu :</td>
                <td class="text-right value">{{ number_format($grandTotalExpected, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td class="label">Total Perçu :</td>
                <td class="text-right value">{{ number_format($grandTotalPaid, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td class="label">Total Reste :</td>
                <td class="text-right value">{{ number_format($grandTotalBalance, 0, ',', ' ') }} FCFA</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Rapport généré le {{ $generatedAt->format('d/m/Y à H:i') }} par {{ $generatedBy }}</p>
        <p>CAB Informatique - Système de Gestion de Scolarité</p>
    </div>

</body>
</html>