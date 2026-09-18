<?php
// app/Services/PDFStorageService.php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PDFStorageService
{
    /**
     * ✅ Chemin : {campus_slug}/{année}/{type}/{référence}.pdf
     */
    public function buildPath(string $campusName, string $year, string $type, string $reference): string
    {
        $campusSlug = Str::slug($campusName, '_');
        $reference  = Str::slug($reference, '_');

        return "pdfs/{$campusSlug}/{$year}/{$type}/{$reference}.pdf";
    }

    public function exists(?string $path): bool
    {
        return $path && Storage::disk('private')->exists($path);
    }

    public function store(string $path, string $content): bool
    {
        return Storage::disk('private')->put($path, $content);
    }

    public function get(string $path): ?string
    {
        if (!$this->exists($path)) return null;

        return Storage::disk('private')->get($path);
    }

    public function delete(?string $path): bool
    {
        if (!$path) return false;
        if (!Storage::disk('private')->exists($path)) return false;

        return Storage::disk('private')->delete($path);
    }

    /**
     * ✅ Chemin pour une fiche d'inscription
     */
    public function registrationPath($registration): string
    {
        $campusName = $registration->campus?->name ?? 'default';
        $year       = $registration->created_at?->format('Y') ?? date('Y');
        $reference  = $registration->student?->registration_number ?? "reg_{$registration->id}";

        return $this->buildPath($campusName, $year, 'registrations', $reference);
    }

    /**
     * ✅ Chemin pour un reçu de paiement
     */
    public function receiptPath($payment): string
    {
        $campusName = $payment->campus?->name ?? 'default';
        $year       = $payment->created_at?->format('Y') ?? date('Y');
        $reference  = $payment->reference ?? "pay_{$payment->id}";

        return $this->buildPath($campusName, $year, 'receipts', $reference);
    }

    /**
     * ✅ Supprime le PDF d'une inscription du disque
     */
    public function deleteRegistrationPDF($registration): void
    {
        if ($registration->pdf_path) {
            $this->delete($registration->pdf_path);
        }
    }

    /**
     * ✅ Supprime le PDF d'un paiement du disque
     */
    public function deleteReceiptPDF($payment): void
    {
        if ($payment->receipt_path) {
            $this->delete($payment->receipt_path);
        }
    }
}