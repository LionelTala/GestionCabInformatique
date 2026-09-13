// src/app/core/interceptors/auth.interceptor.ts
import { HttpInterceptorFn, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { Auth } from '../services/auth';
import { ToastrService } from 'ngx-toastr';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(Auth);
  const toastr = inject(ToastrService);

  // 1. OBLIGATOIRE POUR SANCTUM : On force l'envoi des cookies de session et CSRF
  const securedReq = req.clone({
    withCredentials: true,
    setHeaders: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  });

  return next(securedReq).pipe(
    catchError((error: HttpErrorResponse) => {
      // 401 = Session expiré/Non connecté | 419 = Token CSRF expiré
      if (error.status === 401 || error.status === 419) {
        const isProtectedApiUrl = req.url.includes('/api/v1/');
        const isAuthEndpoint =
          req.url.includes('/auth/login') ||
          req.url.includes('/csrf-cookie') ||
          req.url.includes('/auth/me'); 

        // Si l'erreur arrive sur une API protégée (et pas pendant l'authentification)
        if (isProtectedApiUrl && !isAuthEndpoint) {
          
          // ✅ Remplacement par le Signal : on nettoie uniquement si l'application pensait être connectée
          if (auth.isAuthenticated()) {
            console.warn('🚨 Session expirée sur :', req.url);
            
            auth.clean(); // Efface les signaux et redirige vers /login
            toastr.error('Votre session a expiré. Reconnectez-vous.', 'Déconnecté');
          }
        }
      }
      return throwError(() => error);
    })
  );
};
