import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class FinancialMovementService {
  private apiUrl = environment.apiUrl;
  private movements = signal<any[]>([]);
  private summary = signal<any>({ total_income: 0, total_expense: 0, balance: 0 });
  private loading = signal(false);

  constructor(private http: HttpClient) {}

  getMovements() { return this.movements.asReadonly(); }
  getSummary() { return this.summary.asReadonly(); }
  getLoading() { return this.loading.asReadonly(); }

  loadMovements(filters: any = {}) {
    this.loading.set(true);
    this.http.get<any>(`${this.apiUrl}/financial-movements`, { params: filters }).subscribe({
      next: (res) => {
        this.movements.set(res.data.data);
        this.summary.set(res.summary);
        this.loading.set(false);
      },
      error: () => this.loading.set(false)
    });
  }

  generateReport(filters: any = {}) {
    const params = new URLSearchParams(filters).toString();
    window.open(`${this.apiUrl}/financial-movements/report?${params}`, '_blank');
  }
}