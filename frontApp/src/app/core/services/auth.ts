import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { environment } from '../../../environments/environment';
import { switchMap, tap } from 'rxjs/operators';

@Injectable({ providedIn: 'root' })
export class Auth {
  private apiUrl = environment.apiUrl;
  private baseUrl = environment.baseUrl;

  private user = signal<any>(null);
  private authenticated = signal(false);

  readonly user$ = this.user.asReadonly();
  readonly isAuthenticated = this.authenticated.asReadonly();

  constructor(private http: HttpClient, private router: Router) {
    const cached = localStorage.getItem('user');
    if (cached) {
      try {
        this.user.set(JSON.parse(cached));
        this.authenticated.set(true);
      } catch (e) {
        this.clean(); // Sécurité au cas où le JSON est corrompu
      }
    }
  }

  login(email: string, password: string) {
    return this.http.get(`${this.baseUrl}/sanctum/csrf-cookie`, { withCredentials: true }).pipe(
      switchMap(() =>
        this.http.post(`${this.apiUrl}/auth/login`, { email, password }, { withCredentials: true })
      ),
      tap((response: any) => {
        this.setUser(response.user);
      })
    );
  }

  me() {
    return this.http.get(`${this.apiUrl}/auth/me`, { withCredentials: true }).pipe(
      tap((response: any) => {
        this.setUser(response);
      })
    );
  }

  logout() {
    return this.http.post(`${this.apiUrl}/auth/logout`, {}, { withCredentials: true }).pipe(
      tap({
        next: () => this.clean(),
        error: () => this.clean() // Force la déconnexion même si le backend échoue
      })
    );
  }

  getUser() {
    return this.user();
  }

  private setUser(data: any) {
    this.user.set(data);
    this.authenticated.set(true);
    localStorage.setItem('user', JSON.stringify(data));
  }

  // ✅ Cette méthode est appelée par l'intercepteur en cas de 401/419
  clean() {
    this.user.set(null);
    this.authenticated.set(false);
    localStorage.removeItem('user');
    this.router.navigate(['/login']);
  }
}