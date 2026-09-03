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

  downloadReceipt(paymentId: number) {
    return this.http.get(`${this.apiUrl}/payments/${paymentId}/receipt`, { responseType: 'blob' });
  }
}