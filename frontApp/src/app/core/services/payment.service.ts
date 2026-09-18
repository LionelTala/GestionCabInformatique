import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class PaymentService {
  private http = inject(HttpClient);
  private apiUrl = environment.apiUrl;

  getRecentPayments(page = 1, filters: any = {}) {
    const params: any = { page, per_page: 15, ...filters };
    return this.http.get<any>(`${this.apiUrl}/payments`, { params });
  }

  searchStudents(query: string) {
    return this.http.get<any>(`${this.apiUrl}/payments/search`, { params: { q: query } });
  }

  createPayment(registrationId: number, data: any) {
    return this.http.post(`${this.apiUrl}/registrations/${registrationId}/payments`, data);
  }

// payment.service.ts

/**
 * ✅ Télécharge un reçu PDF
 * @param url — URL signée retournée par getReceiptDownloadUrl()
 * @returns Observable<Blob>
 */
downloadFromUrl(url: string) {
  return this.http.get(url, {
    responseType: 'blob',
    withCredentials: true,   // ✅ Envoie les cookies de session
  });
}

  // ✅ NOUVEAU : Suppression d'un paiement (avec contre-écriture côté back)
  deletePayment(paymentId: number) {
    return this.http.delete(`${this.apiUrl}/payments/${paymentId}`);
  }
  // payment.service.ts

/**
 * ✅ Récupère l'URL signée du reçu
 */
getReceiptDownloadUrl(paymentId: number) {
  return this.http.get<{ url: string }>(
    `${this.apiUrl}/payments/${paymentId}/receipt-url`,
    { withCredentials: true }   // ✅ Nécessaire pour envoyer les cookies
  );
}
}