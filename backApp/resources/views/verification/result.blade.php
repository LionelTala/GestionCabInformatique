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

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- EN-TÊTE -->
        <!-- ═══════════════════════════════════════════════════════ -->
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

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- ✅ BADGE : DONNÉES HISTORIQUES (uniquement pour les reçus) -->
        <!-- ═══════════════════════════════════════════════════════ -->
        @if($status === 'valid' && isset($is_frozen) && $is_frozen)
            <div class="mx-8 mb-4 p-3 bg-amber-50 border border-amber-200 rounded-xl flex items-start gap-2">
                <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="text-xs text-amber-800">
                    <p class="font-semibold mb-0.5">Données historiques figées</p>
                    <p>Ce reçu affiche l'état du compte <strong>au moment de sa création</strong>. Pour connaître la situation actuelle, contactez le campus.</p>
                </div>
            </div>
        @endif

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- ✅ SECTION FINANCIÈRE DÉDIÉE (document valide) -->
        <!-- ═══════════════════════════════════════════════════════ -->
        @if($status === 'valid' && !empty($data))
            @php
                // Clés qui vont dans le bloc "Situation financière"
                $financialKeys = [
                    'Frais de scolarité',
                    'Montant versé',
                    'Montant versé (ce reçu)',
                    'Total versé à cette date',
                    'Reste à payer',
                    'Reste à payer à cette date',
                    'Statut financier',
                    'Statut à cette date',
                    'promo',
                ];

                // Toutes les autres clés → Informations générales
                $baseData = array_filter(
                    $data,
                    fn($key) => !in_array($key, $financialKeys),
                    ARRAY_FILTER_USE_KEY
                );

                // Détection du type : reçu (is_frozen) ou inscription
                $isReceipt = isset($is_frozen) && $is_frozen;
            @endphp

            <!-- ═══ INFORMATIONS GÉNÉRALES ═══ -->
            <div class="bg-gray-50 px-8 pb-6">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">
                    {{ $isReceipt ? 'Informations du reçu' : 'Informations du document' }}
                </h2>
                <div class="space-y-3">
                    @foreach($baseData as $key => $value)
                        @if(!is_array($value))
                            <div class="flex justify-between items-start border-b border-gray-200 pb-2 last:border-0">
                                <span class="text-sm font-semibold text-gray-500">{{ $key }}</span>
                                <span class="text-sm font-medium text-gray-900 text-right max-w-[60%] break-words">{{ $value }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            <!-- ═══ SITUATION FINANCIÈRE ═══ -->
            <div class="px-8 pb-6">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">
                    {{ $isReceipt ? 'Détails du paiement' : 'Situation financière' }}
                </h2>

                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-4 border border-blue-100">

                    <!-- Frais de scolarité avec promo (inscription uniquement) -->
                    <div class="flex justify-between items-center mb-3 pb-3 border-b border-blue-200">
                        <span class="text-sm font-semibold text-blue-900">Frais de scolarité</span>
                        <div class="text-right">
                            @if(!empty($data['promo']))
                                {{-- Promo pour inscription --}}
                                <div class="text-xs text-gray-500 line-through">
                                    {{ $data['promo']['catalogue'] }}
                                </div>
                                <div class="text-base font-bold text-blue-900">
                                    {{ $data['Frais de scolarité'] }}
                                </div>
                                <span class="inline-block bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded mt-1">
                                    PROMO -{{ $data['promo']['economie'] }}
                                </span>
                            @else
                                <div class="text-base font-bold text-blue-900">
                                    {{ $data['Frais de scolarité'] ?? '-' }}
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- ═══ CAS REÇU : montants figés ═══ --}}
                    @if($isReceipt)
                        <!-- Ce versement -->
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm text-blue-700 font-medium">Ce versement</span>
                            <span class="text-base font-bold text-blue-700">
                                {{ $data['Montant versé (ce reçu)'] ?? '-' }}
                            </span>
                        </div>

                        <!-- Total versé à cette date -->
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm text-green-700 font-medium">Total versé à cette date</span>
                            <span class="text-sm font-bold text-green-700">
                                {{ $data['Total versé à cette date'] ?? '-' }}
                            </span>
                        </div>

                        <!-- Reste à cette date -->
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-sm text-red-700 font-medium">Reste à cette date</span>
                            <span class="text-sm font-bold text-red-700">
                                {{ $data['Reste à payer à cette date'] ?? '-' }}
                            </span>
                        </div>

                        <!-- Statut -->
                        <div class="flex justify-between items-center pt-3 border-t border-blue-200">
                            <span class="text-sm font-semibold text-blue-900">Statut à cette date</span>
                            <span class="text-sm font-bold
                                @if(str_contains($data['Statut à cette date'] ?? '', 'Soldé')) text-green-700
                                @elseif(str_contains($data['Statut à cette date'] ?? '', 'Partiel')) text-yellow-700
                                @else text-red-700 @endif">
                                {{ $data['Statut à cette date'] ?? '-' }}
                            </span>
                        </div>

                    {{-- ═══ CAS INSCRIPTION : montants actuels ═══ --}}
                    @else
                        <!-- Montant versé actuel -->
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm text-green-700 font-medium">Montant versé</span>
                            <span class="text-sm font-bold text-green-700">
                                {{ $data['Montant versé'] ?? '-' }}
                            </span>
                        </div>

                        <!-- Reste à payer actuel -->
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-sm text-red-700 font-medium">Reste à payer</span>
                            <span class="text-sm font-bold text-red-700">
                                {{ $data['Reste à payer'] ?? '-' }}
                            </span>
                        </div>

                        <!-- Statut -->
                        <div class="flex justify-between items-center pt-3 border-t border-blue-200">
                            <span class="text-sm font-semibold text-blue-900">Statut</span>
                            <span class="text-sm font-bold
                                @if(str_contains($data['Statut financier'] ?? '', 'Soldé')) text-green-700
                                @elseif(str_contains($data['Statut financier'] ?? '', 'Partiel')) text-yellow-700
                                @else text-red-700 @endif">
                                {{ $data['Statut financier'] ?? '-' }}
                            </span>
                        </div>
                    @endif

                </div>
            </div>
        @endif

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- SECTION POUR AUTRES STATUTS (annulé, invalide) -->
        <!-- ═══════════════════════════════════════════════════════ -->
        @if($status !== 'valid' && !empty($data))
            <div class="bg-gray-50 px-8 pb-8">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">
                    @if($status === 'annulled') Informations du document annulé
                    @else Informations
                    @endif
                </h2>
                <div class="space-y-3">
                    @foreach($data as $key => $value)
                        @if(!is_array($value))
                            <div class="flex justify-between items-start border-b border-gray-200 pb-2 last:border-0">
                                <span class="text-sm font-semibold text-gray-500">{{ $key }}</span>
                                <span class="text-sm font-medium text-gray-900 text-right max-w-[60%] break-words">{{ $value }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- BLOC D'AIDE POUR DOCUMENT ANNULÉ -->
        <!-- ═══════════════════════════════════════════════════════ -->
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

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- PIED DE PAGE -->
        <!-- ═══════════════════════════════════════════════════════ -->
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