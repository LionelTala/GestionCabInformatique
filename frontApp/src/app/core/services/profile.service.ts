// src/app/core/services/profile.service.ts
import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class ProfileService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/profile`;

  getProfile() {
    return this.http.get<any>(this.apiUrl);
  }

  updateProfile(data: { first_name: string; last_name: string; email: string | null; phone: string | null }) {
    return this.http.put<any>(this.apiUrl, data);
  }

  updatePassword(data: { current_password: string; password: string; password_confirmation: string }) {
    return this.http.patch<any>(`${this.apiUrl}/password`, data);
  }
}