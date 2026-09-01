import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

export interface Formation {
  id: number;
  name: string;
  abbreviation: string;
  tuition_fees: number;
  duration_months: number;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

@Injectable({
  providedIn: 'root'
})
export class FormationService {
  private apiUrl = environment.apiUrl;
  private formations = signal<Formation[]>([]);

  constructor(private http: HttpClient) {}

  getFormations() {
    return this.formations.asReadonly();
  }

  loadFormations() {
    return this.http.get<{ data: Formation[] }>(`${this.apiUrl}/formations`);
  }

  refresh() {
    this.loadFormations().subscribe({
      next: (response) => {
        this.formations.set(response.data);
      },
      error: () => {
        // Géré par l'intercepteur
      }
    });
  }

  create(formation: Partial<Formation>) {
    return this.http.post<{ data: Formation }>(`${this.apiUrl}/formations`, formation);
  }

  update(id: number, formation: Partial<Formation>) {
    return this.http.put<{ data: Formation }>(`${this.apiUrl}/formations/${id}`, formation);
  }

  delete(id: number) {
    return this.http.delete(`${this.apiUrl}/formations/${id}`);
  }

  toggleStatus(id: number) {
    return this.http.patch<{ data: Formation }>(`${this.apiUrl}/formations/${id}/toggle-status`, {});
  }
}