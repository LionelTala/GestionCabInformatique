<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rapport État de Scolarité</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.4; color: #333; padding: 15px 20px; }
        
        .header { text-align: center; margin-bottom: 15px; border-bottom: 3px solid #15157D; padding-bottom: 10px; }
        .header h1 { font-size: 18px; color: #15157D; margin-bottom: 3px; }
        .header .subtitle { font-size: 11px; color: #6B7280; }
        .header .filter-info { font-size: 10px; color: #0B1C30; margin-top: 6px; font-weight: bold; background: #F5F7FA; padding: 4px 10px; border-radius: 3px; display: inline-block; }
        
        .campus-section { margin-bottom: 15px; page-break-inside: avoid; }
        .campus-header { background: #15157D; color: white; padding: 8px 12px; border-radius: 4px; margin-bottom: 8px; }
        .campus-header h2 { font-size: 13px; }
        
        .formation-section { margin-bottom: 12px; page-break-inside: avoid; }
        .formation-header { background: #E8F0FE; padding: 6px 10px; border-left: 4px solid #15157D; margin-bottom: 6px; }
        .formation-header h3 { font-size: 11px; color: #15157D; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; font-size: 9px; }
        thead { background: #F5F7FA; }
        th { padding: 5px 4px; text-align: left; font-size: 8px; font-weight: 600; color: #6B7280; text-transform: uppercase; border-bottom: 1px solid #E5E7EB; }
        td { padding: 4px; border-bottom: 1px solid #F5F7FA; font-size: 9px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .status-paid { color: #10B981; font-weight: 600; }
        .status-partial { color: #F59E0B; font-weight: 600; }
        .status-unpaid { color: #EF4444; font-weight: 600; }
        
        .formation-summary { background: #F9FAFB; padding: 6px 10px; border-radius: 4px; margin-top: 4px; font-size: 9px; }
        .formation-summary table { margin: 0; }
        .formation-summary td { border: none; padding: 2px 6px; }
        
        .grand-total { background: #15157D; color: white; padding: 12px; border-radius: 6px; margin-top: 15px; }
        .grand-total h3 { font-size: 13px; margin-bottom: 8px; text-align: center; }
        .grand-total table { margin: 0; }
        .grand-total td { border: none; padding: 4px 8px; color: white; font-size: 10px; }
        .grand-total .label { font-weight: 600; }
        .grand-total .value { font-size: 12px; font-weight: bold; text-align: right; }
        
        .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #E5E7EB; text-align: center; font-size: 8px; color: #9CA3AF; }
        
        @page { margin: 10mm; size: A4 portrait; }
    </style>
</head>
<body>

    <div class="header">
        <h1>RAPPORT ÉTAT DE SCOLARITÉ</h1>
        <div class="subtitle">Situation financière des étudiants</div>
        <div class="filter-info">{{ $filterInfo }}</div>
    </div>

    @foreach($groupedData as $campusData)
        <div class="campus-section">
            @if($userRole === 'super_admin' || $userRole === 'admin_global')
                <div class="campus-header">
                    <!-- ✅ CORRECTION : Syntaxe tableau ['name'] au lieu de ->name -->
                    <h2>{{ $campusData['campus']['name'] ?? 'Campus' }}</h2>
                </div>
            @endif

            @foreach($campusData['formations'] as $form)
                <div class="formation-section">
                    <div class="formation-header">
                        <!-- ✅ CORRECTION : Syntaxe tableau ['name'] au lieu de ->name -->
                        <h3>{{ $form['formation']['name'] ?? 'Formation' }} ({{ $form['student_count'] }} étudiant(s))</h3>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>Matricule</th>
                                <th>Nom complet</th>
                                <th>Téléphone</th>
                                <th class="text-right">Attendu</th>
                                <th class="text-right">Payé</th>
                                <th class="text-right">Reste</th>
                                <th class="text-center">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($form['students'] as $student)
                                <tr>
                                    <td>{{ $student['matricule'] }}</td>
                                    <td>{{ $student['name'] }}</td>
                                    <td>{{ $student['phone'] ?? '-' }}</td>
                                    <td class="text-right">{{ number_format($student['total_expected'], 0, ',', ' ') }}</td>
                                    <td class="text-right status-paid">{{ number_format($student['amount_paid'], 0, ',', ' ') }}</td>
                                    <td class="text-right status-unpaid">{{ number_format($student['balance'], 0, ',', ' ') }}</td>
                                    <td class="text-center">
                                        @if($student['status'] === 'paid')
                                            <span class="status-paid">Soldé</span>
                                        @elseif($student['status'] === 'partial')
                                            <span class="status-partial">Partiel</span>
                                        @else
                                            <span class="status-unpaid">Non payé</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="formation-summary">
                        <table>
                            <tr>
                                <td><strong>Récap Formation :</strong></td>
                                <td class="text-right">Attendu : <strong>{{ number_format($form['total_expected'], 0, ',', ' ') }} FCFA</strong></td>
                                <td class="text-right">Perçu : <strong class="status-paid">{{ number_format($form['total_paid'], 0, ',', ' ') }} FCFA</strong></td>
                                <td class="text-right">Reste : <strong class="status-unpaid">{{ number_format($form['total_balance'], 0, ',', ' ') }} FCFA</strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            @endforeach

            @if($userRole === 'super_admin' || $userRole === 'admin_global')
                <div class="formation-summary" style="background: #E8F0FE;">
                    <table>
                        <tr>
                            <td><strong>Récap Campus :</strong></td>
                            <td class="text-right">{{ $campusData['campus_total_students'] }} étudiants</td>
                            <td class="text-right">Attendu : <strong>{{ number_format($campusData['campus_total_expected'], 0, ',', ' ') }} FCFA</strong></td>
                            <td class="text-right">Perçu : <strong class="status-paid">{{ number_format($campusData['campus_total_paid'], 0, ',', ' ') }} FCFA</strong></td>
                            <td class="text-right">Reste : <strong class="status-unpaid">{{ number_format($campusData['campus_total_balance'], 0, ',', ' ') }} FCFA</strong></td>
                        </tr>
                    </table>
                </div>
            @endif
        </div>
    @endforeach

    <div class="grand-total">
        <h3>RÉCAPITULATIF GÉNÉRAL</h3>
        <table>
            <tr>
                <td class="label">Nombre total d'étudiants :</td>
                <td class="value">{{ $grandTotal['students'] }}</td>
            </tr>
            <tr>
                <td class="label">Montant total attendu :</td>
                <td class="value">{{ number_format($grandTotal['expected'], 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td class="label">Montant total perçu :</td>
                <td class="value">{{ number_format($grandTotal['paid'], 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td class="label">Montant total restant :</td>
                <td class="value">{{ number_format($grandTotal['balance'], 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td class="label">Taux de recouvrement :</td>
                <td class="value">{{ $grandTotal['expected'] > 0 ? round(($grandTotal['paid'] / $grandTotal['expected']) * 100, 1) : 0 }}%</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Rapport généré le {{ $generatedAt->format('d/m/Y à H:i') }} par {{ $generatedBy }}</p>
    </div>

</body>
</html>