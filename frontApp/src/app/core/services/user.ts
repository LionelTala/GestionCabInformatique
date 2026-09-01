import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ToastrService } from 'ngx-toastr';
import { environment } from '../../../environments/environment';

export interface User {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  role: 'super_admin' | 'admin_global' | 'admin_campus' | 'secretary';
  campus_id?: number;
  campus?: { id: number; name: string; city: string };
  is_active: boolean;
  password?: string;
  created_at?: string;
  updated_at?: string;
}

@Injectable({
  providedIn: 'root'
})
export class UserService {
  private apiUrl = environment.apiUrl;
  private users = signal<User[]>([]);

  constructor(private http: HttpClient, private toastr: ToastrService) {}

  getUsers() {
    return this.users.asReadonly();
  }

  loadUsers() {
    this.http.get<{ data: User[] }>(`${this.apiUrl}/users`).subscribe({
      next: (response) => {
        this.users.set(response.data);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors du chargement des utilisateurs');
      }
    });
  }

  create(user: Partial<User>) {
    return this.http.post<{ data: User }>(`${this.apiUrl}/users`, user);
  }

  update(id: number, user: Partial<User>) {
    return this.http.put<{ data: User }>(`${this.apiUrl}/users/${id}`, user);
  }

  delete(id: number) {
    return this.http.delete(`${this.apiUrl}/users/${id}`);
  }

  toggleStatus(id: number) {
    return this.http.patch<{ data: User }>(`${this.apiUrl}/users/${id}/toggle-status`, {});
  }

  refresh() {
    this.loadUsers();
  }
}