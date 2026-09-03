<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification de Document - CAB Informatique</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl overflow-hidden border-t-8 
        @if($status === 'valid') border-green-500 
        @elseif($status === 'annulled') border-red-500 
        @else border-yellow-500 @endif">
        
        <!-- En-tête avec Icône -->
        <div class="p-8 text-center">
            @if($status === 'valid')
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Document Authentique</h1>
                <p class="text-gray-600 mt-2">Ce document est officiellement émis par CAB Informatique.</p>
            
            @elseif($status === 'annulled')
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Document Annulé</h1>
                <p class="text-gray-600 mt-2">{{ $message }}</p>
            
            @elseif($status === 'invalid' || $status === 'not_found')
                <div class="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Document Non Valide</h1>
                <p class="text-gray-600 mt-2">{{ $message }}</p>
            
            @else
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Erreur</h1>
                <p class="text-gray-600 mt-2">{{ $message }}</p>
            @endif
        </div>

        <!-- Détails des données (si disponibles) -->
        @if(!empty($data))
            <div class="bg-gray-50 px-8 pb-8">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">
                    @if($status === 'valid') Informations du document
                    @elseif($status === 'annulled') Informations du document annulé
                    @else Informations
                    @endif
                </h2>
                <div class="space-y-3">
                    @foreach($data as $key => $value)
                        <div class="flex justify-between items-start border-b border-gray-200 pb-2 last:border-0">
                            <span class="text-sm font-semibold text-gray-500">{{ $key }}</span>
                            <span class="text-sm font-medium text-gray-900 text-right max-w-[60%] break-words">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- ✅ BLOC D'AIDE POUR DOCUMENT ANNULÉ -->
        @if($status === 'annulled' && isset($campus) && $campus)
            <div class="mx-8 mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
                <h3 class="text-sm font-bold text-blue-900 mb-2 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Que faire ?
                </h3>
                <p class="text-sm text-blue-800 mb-3">
                    Veuillez vous rapprocher de notre campus pour plus d'informations :
                </p>
                <div class="text-sm text-blue-900 space-y-1">
                    <p class="font-semibold">{{ $campus->name }}</p>
                    @if($campus->address)
                        <p class="text-xs">📍 {{ $campus->address }}</p>
                    @endif
                    @if($campus->phone)
                        <p class="text-xs">📞 {{ $campus->phone }}</p>
                    @endif
                    @if($campus->email)
                        <p class="text-xs">✉️ {{ $campus->email }}</p>
                    @endif
                </div>
            </div>
        @endif

        <!-- Pied de page -->
        <div class="p-6 bg-gray-100 text-center border-t border-gray-200">
            <p class="text-xs text-gray-500 mb-4">
                Cette vérification est effectuée en temps réel sur notre base de données officielle.
            </p>
            <p class="text-xs text-gray-400">
                Vérifié le {{ now()->format('d/m/Y à H:i') }}
            </p>
        </div>
    </div>

</body>
</html>