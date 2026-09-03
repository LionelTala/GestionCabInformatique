<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Liste des Étudiants</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.4; color: #333; padding: 15px 20px; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 3px solid #15157D; padding-bottom: 10px; }
        .header h1 { font-size: 18px; color: #15157D; margin-bottom: 3px; }
        .header .subtitle { font-size: 11px; color: #6B7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        thead { background: #F5F7FA; }
        th { padding: 8px 6px; text-align: left; font-size: 9px; font-weight: 600; color: #6B7280; text-transform: uppercase; border-bottom: 2px solid #E5E7EB; }
        td { padding: 6px; border-bottom: 1px solid #E5E7EB; font-size: 9px; }
        .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #E5E7EB; text-align: center; font-size: 8px; color: #9CA3AF; }
        @page { margin: 10mm; size: A4 portrait; }
    </style>
</head>
<body>
    <div class="header">
        <h1>LISTE DES ÉTUDIANTS</h1>
        <div class="subtitle">{{ $total }} étudiant(s) au total</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>N°</th>
                <th>Matricule</th>
                <th>Nom complet</th>
                <th>Téléphone</th>
                <th>Email</th>
                <th>Formation</th>
                <th>Campus</th>
            </tr>
        </thead>
        <tbody>
            @foreach($students as $index => $student)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $student['matricule'] }}</td>
                    <td>{{ $student['name'] }}</td>
                    <td>{{ $student['phone'] ?? '-' }}</td>
                    <td>{{ $student['email'] ?? '-' }}</td>
                    <td>{{ $student['formation'] }}</td>
                    <td>{{ $student['campus'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Liste générée le {{ $generatedAt->format('d/m/Y à H:i') }} par {{ $generatedBy }}</p>
    </div>
</body>
</html>