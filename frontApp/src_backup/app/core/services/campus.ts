import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
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

@Injectable({ providedIn: 'root' })
export class CampusService {
  private apiUrl = environment.apiUrl;
  
  // Signal pour l'affichage dans les templates
  private campuses = signal<Campus[]>([]);
  private isLoading = signal(false);

  constructor(private http: HttpClient) {}

  getCampuses() {
    return this.campuses.asReadonly();
  }

  getIsLoading() {
    return this.isLoading.asReadonly();
  }

  /**
   * Fait systématiquement un appel API pour récupérer les campus.
   * Aucune vérification, aucun cache localStorage. Données 100% fraîches de la BDD.
   */
  loadCampuses() {
    this.isLoading.set(true);
    
    this.http.get<{ data: Campus[] }>(`${this.apiUrl}/campuses`).subscribe({
      next: (response) => {
        this.campuses.set(response.data);
        this.isLoading.set(false);
      },
      error: (err) => {
        console.error('Erreur chargement campus:', err);
        this.campuses.set([]);
        this.isLoading.set(false);
      }
    });
  }

  // === MÉTHODES CRUD ===

  create(data: Partial<Campus>) {
    return this.http.post(`${this.apiUrl}/campuses`, data);
  }

  update(id: number, data: Partial<Campus>) {
    return this.http.put(`${this.apiUrl}/campuses/${id}`, data);
  }

  delete(id: number) {
    return this.http.delete(`${this.apiUrl}/campuses/${id}`);
  }

  // ✅ MÉTHODE AJOUTÉE : Pour activer/désactiver un campus
  toggleStatus(id: number) {
    return this.http.patch(`${this.apiUrl}/campuses/${id}/toggle-status`, {});
  }
}