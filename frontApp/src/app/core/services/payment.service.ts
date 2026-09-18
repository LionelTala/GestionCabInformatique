import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { ToastrService } from 'ngx-toastr';
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
downloadReceipt(paymentId: number) {
  return this.http.get(`${this.apiUrl}/pdf/receipt/${paymentId}`, {
    responseType: 'blob',
    withCredentials: true,
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
getReceiptDownloadUrl(paymentId: number): string {
  return `${this.apiUrl}/pdf/receipt/${paymentId}`;
}
triggerDownload(url: string, toastr: ToastrService, fileLabel: string = 'fichier'): void {
  const iframe: HTMLIFrameElement = document.createElement('iframe');
  iframe.style.display = 'none';
  iframe.src = url;
  document.body.appendChild(iframe);

  toastr.info(
    `Vérifiez votre dossier "Téléchargements" dans quelques secondes.`,
    `Téléchargement du ${fileLabel} lancé`,
    { timeOut: 5000, closeButton: true }
  );

  setTimeout(() => {
    document.body.removeChild(iframe);
  }, 3000);
}
}