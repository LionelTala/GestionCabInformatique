import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class ExpenseService {
  private apiUrl = environment.apiUrl;
  private expenses = signal<any[]>([]);
  private meta = signal<any>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
  });
  private loading = signal(false);
  private totalAmount = signal(0);
  private summary = signal<any>(null);

  constructor(private http: HttpClient) {}

  getExpenses() { return this.expenses.asReadonly(); }
  getMeta() { return this.meta.asReadonly(); }
  getLoading() { return this.loading.asReadonly(); }
  getTotalAmount() { return this.totalAmount.asReadonly(); }
  getSummary() { return this.summary.asReadonly(); }

  loadExpenses(page: number = 1, filters: any = {}) {
    this.loading.set(true);
    const params: any = { page, per_page: 15, ...filters };

    return this.http.get<any>(`${this.apiUrl}/expenses`, { params }).subscribe({
      next: (res) => {
        this.expenses.set(res.data.data);
        this.meta.set({
          current_page: res.data.current_page,
          last_page: res.data.last_page,
          per_page: res.data.per_page,
          total: res.data.total,
          from: res.data.from,
          to: res.data.to,
        });
        this.totalAmount.set(res.total_amount);
        this.loading.set(false);
      },
      error: () => this.loading.set(false)
    });
  }

  loadSummary(filters: any = {}) {
    const params: any = { ...filters };
    return this.http.get<any>(`${this.apiUrl}/expenses/summary`, { params }).subscribe({
      next: (res) => this.summary.set(res.data),
      error: () => console.error('Erreur chargement résumé')
    });
  }

  createExpense(data: any) {
    return this.http.post<any>(`${this.apiUrl}/expenses`, data);
  }

  deleteExpense(id: number) {
    return this.http.delete<any>(`${this.apiUrl}/expenses/${id}`);
  }
}