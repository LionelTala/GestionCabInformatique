<?php

if (!function_exists('generateDocumentSignature')) {
    function generateDocumentSignature($data)
    {
        $secretKey = env('DOCUMENT_SIGNATURE_KEY', 'CAB_S3cur3K3y_2026_!@#$%^&*()_+');
        $dataString = is_array($data) ? json_encode($data) : $data;
        return hash_hmac('sha256', $dataString, $secretKey);
    }
}

if (!function_exists('verifyDocumentSignature')) {
    function verifyDocumentSignature($data, $signature)
    {
        $expectedSignature = generateDocumentSignature($data);
        return hash_equals($expectedSignature, $signature);
    }
}

if (!function_exists('getVerificationUrl')) {
    function getVerificationUrl()
    {
        $appUrl = rtrim(env('APP_URL', 'http://localhost:8000'), '/');
        return $appUrl . '/verify-document';
    }
}

if (!function_exists('generateSecureQRData')) {
    function generateSecureQRData($documentType, $data)
    {
        $verificationUrl = getVerificationUrl();

        $payload = [
            'type' => $documentType,
            'data' => $data,
            'sig' => generateDocumentSignature($data),
            'v' => 1
        ];

        return $verificationUrl . '?q=' . urlencode(base64_encode(json_encode($payload)));
    }
}

if (!function_exists('generateRegistrationQRData')) {
    function generateRegistrationQRData($student, $registration, $formation, $campus)
    {
        // Utilisation des accesseurs du modèle pour avoir les données financières exactes
        $amountPaid = $registration->amount_paid; 
        $tuitionFees = $formation->tuition_fees;
        
        // Calcul du statut financier dynamique
        if ($amountPaid <= 0) {
            $financialStatus = 'unpaid';
        } elseif ($amountPaid >= $tuitionFees) {
            $financialStatus = 'paid';
        } else {
            $financialStatus = 'partial';
        }

        $data = [
            'matricule'         => $student->registration_number,
            'name'              => $student->first_name . ' ' . $student->last_name,
            'formation'         => $formation->name,
            'formation_code'    => $formation->abbreviation,
            'campus'            => $campus?->name ?? 'CAB Informatique',
            'registration_id'   => $registration->id, // Crucial pour la vérification en base
            'registration_date' => $registration->created_at->format('d/m/Y'),
            'academic_year'     => $registration->academicYear->label ?? (date('Y') . '-' . (date('Y') + 1)),
            'tuition_fees'      => $tuitionFees,
            'amount_paid'       => $amountPaid,
            'financial_status'  => $financialStatus,
        ];

        return generateSecureQRData('registration', $data);
    }
    if (!function_exists('generatePaymentQRData')) {
    function generatePaymentQRData($payment, $student, $registration)
    {
        $data = [
            'type' => 'payment_receipt',
            'reference' => $payment->reference,
            'matricule' => $student->registration_number,
            'name' => $student->first_name . ' ' . $student->last_name,
            'amount' => $payment->amount,
            'payment_date' => $payment->payment_date->format('d/m/Y'),
            'registration_id' => $registration->id,
        ];

        return generateSecureQRData('payment', $data);
    }
}
}