// src/app/core/services/attestation.service.ts
import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

export type AttestationStatus = 'pending' | 'ready';

export interface Attestation {
  id: number;
  reference: string;
  registration_id: number;
  student_id: number;
  campus_id: number;
  status: AttestationStatus;
  requested_by: number;
  requested_at: string;
  settled_by: number | null;
  settled_at: string | null;
  created_at: string;
  student?: {
    id: number;
    first_name: string;
    last_name: string;
    registration_number: string;
    email?: string;
    phone?: string;
  };
  registration?: {
    id: number;
    formation?: { id: number; name: string; abbreviation: string };
  };
  campus?: { id: number; name: string; city?: string };
  requested_by_user?: { id: number; first_name: string; last_name: string };
  settled_by_user?: { id: number; first_name: string; last_name: string };
}

@Injectable({ providedIn: 'root' })
export class AttestationService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/attestations`;

  // ═══ LISTE ═══
  getAttestations(page = 1, filters: any = {}) {
    const params: any = { page, per_page: 15, ...filters };
    return this.http.get<any>(this.apiUrl, { params });
  }

  // ═══ CRÉATION ═══
  createAttestation(data: { registration_id: number }) {
    return this.http.post<any>(this.apiUrl, data);
  }

  // ═══ RÉGLER ═══
  settleAttestation(id: number) {
    return this.http.patch<any>(`${this.apiUrl}/${id}/settle`, {});
  }

  // ═══ REMETTRE EN ATTENTE ═══
  unsettleAttestation(id: number) {
    return this.http.patch<any>(`${this.apiUrl}/${id}/unsettle`, {});
  }

  // ═══ RECHERCHE ÉTUDIANT ═══
  searchStudents(query: string) {
    return this.http.get<any>(`${this.apiUrl}/search-students`, { params: { q: query } });
  }
  // attestation.service.ts

// ═══ ANNULER ═══
cancelAttestation(id: number) {
  return this.http.delete<any>(`${this.apiUrl}/${id}`);
}

  // ═══ STATS ═══
  getStats(params: any = {}) {
    return this.http.get<{ data: { pending: number; ready: number; total: number } }>(
      `${this.apiUrl}/stats`, { params }
    );
  }
}