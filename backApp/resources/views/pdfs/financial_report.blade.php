<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rapport Financier</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
            /* Forcer les marges de gauche et de droite directement ici */
            padding: 20px 25px; 
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 3px solid #15157D;
            padding-bottom: 15px;
        }
        .header h1 {
            font-size: 20px;
            color: #15157D;
            margin-bottom: 5px;
        }
        .header .subtitle {
            font-size: 12px;
            color: #6B7280;
        }
        .header .filter-info {
            font-size: 11px;
            color: #0B1C30;
            margin-top: 8px;
            font-weight: bold;
        }
        
        .campus-section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        .campus-header {
            background: #E8F0FE;
            padding: 8px 12px;
            border-left: 4px solid #15157D;
            margin-bottom: 10px;
        }
        .campus-header h2 {
            font-size: 14px;
            color: #15157D;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        thead {
            background: #F5F7FA;
        }
        th {
            padding: 8px 6px;
            text-align: left;
            font-size: 9px;
            font-weight: 600;
            color: #6B7280;
            text-transform: uppercase;
            border-bottom: 2px solid #E5E7EB;
        }
        td {
            padding: 6px;
            border-bottom: 1px solid #E5E7EB;
            font-size: 9px;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .income { color: #10B981; font-weight: 600; }
        .expense { color: #EF4444; font-weight: 600; }
        
        .campus-summary {
            background: #F9FAFB;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
        }
        .campus-summary table {
            margin: 0;
        }
        .campus-summary td {
            border: none;
            padding: 4px 8px;
        }
        .campus-summary .label {
            font-weight: 600;
            color: #6B7280;
        }
        
        .global-summary {
            background: #15157D;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .global-summary h3 {
            font-size: 14px;
            margin-bottom: 10px;
            text-align: center;
        }
        .global-summary table {
            margin: 0;
        }
        .global-summary td {
            border: none;
            padding: 6px 10px;
            color: white;
        }
        .global-summary .label {
            font-weight: 600;
        }
        .global-summary .value {
            font-size: 13px;
            font-weight: bold;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #E5E7EB;
            text-align: center;
            font-size: 8px;
            color: #9CA3AF;
        }
        
        @page {
            margin: 0px; /* On enlève les marges par défaut du conteneur de la page */
            size: A4 portrait;
        }

        .balance-positive {
            color: #10B981;
            font-weight: bold;
        }
        .balance-negative {
            color: #EF4444;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>RAPPORT FINANCIER</h1>
        <div class="subtitle">Bilan comptable des mouvements financiers</div>
        <div class="filter-info">{{ $filterInfo }}</div>
    </div>

    @foreach($groupedByCampus as $campusData)
        <div class="campus-section">
            <div class="campus-header">
                <h2>{{ $campusData['campus']->name ?? 'Campus inconnu' }}</h2>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Référence</th>
                        <th>Description</th>
                        <th>Catégorie</th>
                        <th class="text-right">Entrées</th>
                        <th class="text-right">Sorties</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($campusData['movements'] as $movement)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($movement->created_at)->format('d/m/Y') }}</td>
                            <td>{{ $movement->reference }}</td>
                            <td>{{ Str::limit($movement->description, 50) }}</td>
                            <td>{{ ucfirst($movement->category) }}</td>
                            <td class="text-right income">
                                @if($movement->type === 'income')
                                    {{ number_format($movement->amount, 0, ',', ' ') }} FCFA
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-right expense">
                                @if($movement->type === 'expense')
                                    {{ number_format($movement->amount, 0, ',', ' ') }} FCFA
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="campus-summary">
                <table>
                    <tr>
                        <td class="label">Total Entrées :</td>
                        <td class="text-right income">{{ number_format($campusData['income'], 0, ',', ' ') }} FCFA</td>
                        <td class="label">Total Sorties :</td>
                        <td class="text-right expense">{{ number_format($campusData['expense'], 0, ',', ' ') }} FCFA</td>
                        <td class="label">Solde :</td>
                        @if($campusData['balance'] >= 0)
                            <td class="text-right balance-positive">
                                {{ number_format($campusData['balance'], 0, ',', ' ') }} FCFA
                            </td>
                        @else
                            <td class="text-right balance-negative">
                                {{ number_format($campusData['balance'], 0, ',', ' ') }} FCFA
                            </td>
                        @endif
                    </tr>
                </table>
            </div>
        </div>
    @endforeach

    <div class="global-summary">
        <h3>SOLDE GLOBAL</h3>
        <table>
            <tr>
                <td class="label">Total des Entrées :</td>
                <td class="text-right value">{{ number_format($totalIncome, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td class="label">Total des Sorties :</td>
                <td class="text-right value">{{ number_format($totalExpense, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td class="label">SOLDE NET :</td>
                <td class="text-right value" style="font-size: 16px;">
                    {{ number_format($balance, 0, ',', ' ') }} FCFA
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Rapport généré le {{ $generatedAt->format('d/m/Y à H:i') }} par {{ $generatedBy }}</p>
        <p>CAB Informatique - Système de Gestion Financière</p>
    </div>

</body>
</html>