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
  private campuses = signal<Campus[]>([]);
  private readonly STORAGE_KEY = 'cab_campuses'; // ✅ Clé localStorage

  constructor(private http: HttpClient) {
    // ✅ Charger depuis localStorage au démarrage du service
    this.loadFromStorage();
  }

  getCampuses() {
    return this.campuses.asReadonly();
  }

  /**
   * Charge les campus depuis localStorage (instantané)
   */
  private loadFromStorage() {
    try {
      const stored = localStorage.getItem(this.STORAGE_KEY);
      if (stored) {
        const data = JSON.parse(stored);
        this.campuses.set(data);
      }
    } catch (e) {
      console.error('Erreur lecture localStorage campus:', e);
    }
  }

  /**
   * Sauvegarde les campus dans localStorage
   */
  private saveToStorage(data: Campus[]) {
    try {
      localStorage.setItem(this.STORAGE_KEY, JSON.stringify(data));
    } catch (e) {
      console.error('Erreur écriture localStorage campus:', e);
    }
  }

  /**
   * Charge les campus depuis le serveur (seulement si localStorage vide)
   */
  loadCampuses() {
    // ✅ Si on a déjà les données en localStorage, on ne fait PAS de requête HTTP
    if (this.campuses().length > 0) {
      return;
    }

    this.http.get<{ data: Campus[] }>(`${this.apiUrl}/campuses`).subscribe({
      next: (response) => {
        this.campuses.set(response.data);
        this.saveToStorage(response.data); // ✅ Sauvegarder dans localStorage
      },
      error: (err) => {
        console.error('Erreur chargement campus:', err);
      }
    });
  }

  /**
   * Force le rechargement depuis le serveur et met à jour le localStorage
   * À utiliser après création/modification/suppression d'un campus
   */
  refresh() {
    this.http.get<{ data: Campus[] }>(`${this.apiUrl}/campuses`).subscribe({
      next: (response) => {
        this.campuses.set(response.data);
        this.saveToStorage(response.data); // ✅ Mettre à jour le localStorage
      },
      error: (err) => {
        console.error('Erreur rafraîchissement campus:', err);
      }
    });
  }

  create(data: Partial<Campus>) {
    return this.http.post(`${this.apiUrl}/campuses`, data);
  }

  update(id: number, data: Partial<Campus>) {
    return this.http.put(`${this.apiUrl}/campuses/${id}`, data);
  }

  delete(id: number) {
    return this.http.delete(`${this.apiUrl}/campuses/${id}`);
  }

  toggleStatus(id: number) {
    return this.http.patch(`${this.apiUrl}/campuses/${id}/toggle-status`, {});
  }
}