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
  // ✅ Signal de chargement pour indiquer si la vérification initiale de session est en cours
  private loading = signal(true); 

  readonly user$ = this.user.asReadonly();
  readonly isAuthenticated = this.authenticated.asReadonly();
  // ✅ Expose le statut de chargement aux Guards en lecture seule
  readonly isLoggingIn = this.loading.asReadonly(); 

  constructor(private http: HttpClient, private router: Router) {
    // ✅ Au lieu du localStorage, on vérifie la vraie session du cookie avec le backend
     queueMicrotask(() => this.checkSession());
  }

private checkSession() {
  this.me().subscribe({
    next: () => this.loading.set(false),
    error: (err) => {
      console.error('❌ ERREUR /auth/me :', err); // <-- ajoute ça
      this.clean();
      this.loading.set(false);
    }
  });
}

  login(loginInput: string, password: string) {
    return this.http.get(`${this.baseUrl}/sanctum/csrf-cookie`, { withCredentials: true }).pipe(
      switchMap(() =>
        this.http.post(`${this.apiUrl}/auth/login`, { login_input: loginInput, password }, { withCredentials: true })
      ),
      tap((response: any) => {
        // La réponse doit idéalement retourner l'objet utilisateur { user: {...} }
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
    return this.user(); // Pratique, mais vous pouvez aussi utiliser directement la propriété en lecture seule `user$()` dans vos templates
  }

  private setUser(data: any) {
    this.user.set(data);
    this.authenticated.set(true);
  }

  // ✅ Parfaitement géré pour être appelé par l'intercepteur en cas de 401/419
  clean() {
    this.user.set(null);
    this.authenticated.set(false);
    this.router.navigate(['/login']);
  }
  // Dans AuthService
updateCurrentUser(data: any) {
  // ✅ Fusionne les nouvelles données avec l'utilisateur actuel
  const current = this.user();
  const updated = { ...current, ...data };
  this.user.set(updated);
  localStorage.setItem('user', JSON.stringify(updated));
}
}
