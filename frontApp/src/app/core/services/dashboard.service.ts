import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class DashboardService {
  private apiUrl = environment.apiUrl;
  private stats = signal<any>(null);
  private loading = signal(false);

  constructor(private http: HttpClient) {}

  getStats() { return this.stats.asReadonly(); }
  getLoading() { return this.loading.asReadonly(); }

  loadStats() {
    this.loading.set(true);
    this.http.get<any>(`${this.apiUrl}/dashboard/stats`).subscribe({
      next: (res) => {
        this.stats.set(res.data);
        this.loading.set(false);
      },
      error: () => this.loading.set(false)
    });
  }
}