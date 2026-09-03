import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class DocumentVerificationService {
  private http = inject(HttpClient);
  // Note: Cette route doit être accessible publiquement (pas de middleware auth:sanctum côté backend)
  private apiUrl = environment.apiUrl; 

  verifyDocument(q: string) {
    return this.http.get<any>(`${this.apiUrl}/verify-document`, {
      params: { q }
    });
  }
}