import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ToastrService } from 'ngx-toastr';
import { environment } from '../../../environments/environment';

export interface Campus {
  id: number;
  name: string;
  city: string;
  address?: string;
  phone?: string;
  email?: string;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

@Injectable({
  providedIn: 'root'
})
export class CampusService {
  private apiUrl = environment.apiUrl;
  private campuses = signal<Campus[]>([]);

  constructor(private http: HttpClient, private toastr: ToastrService) {}

  getCampuses() {
    return this.campuses.asReadonly();
  }

  loadCampuses() {
    this.http.get<{ data: Campus[] }>(`${this.apiUrl}/campuses`).subscribe({
      next: (response) => {
        this.campuses.set(response.data);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors du chargement des campus');
      }
    });
  }

  create(campus: Partial<Campus>) {
    return this.http.post<{ data: Campus }>(`${this.apiUrl}/campuses`, campus);
  }

  update(id: number, campus: Partial<Campus>) {
    return this.http.put<{ data: Campus }>(`${this.apiUrl}/campuses/${id}`, campus);
  }

  delete(id: number) {
    return this.http.delete(`${this.apiUrl}/campuses/${id}`);
  }

  toggleStatus(id: number) {
    return this.http.patch<{ data: Campus }>(`${this.apiUrl}/campuses/${id}/toggle-status`, {});
  }

  refresh() {
    this.loadCampuses();
  }
}