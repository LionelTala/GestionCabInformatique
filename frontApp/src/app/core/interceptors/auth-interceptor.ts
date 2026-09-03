import { HttpInterceptorFn, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { Auth } from '../services/auth';
import { ToastrService } from 'ngx-toastr';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);
  const auth = inject(Auth);
  const toastr = inject(ToastrService);

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      // ✅ AJOUTE CE LOG POUR VOIR LA REQUÊTE COUPABLE
      console.log('🚨 INTERCEPTEUR AUTH : Erreur', error.status, 'sur l\'URL :', req.url);

      if (error.status === 401 || error.status === 419) {
        if (!req.url.includes('/login') && !req.url.includes('/csrf-cookie')) {
          console.log('🚨 DÉCONNEXION FORCÉE PAR L\'INTERCEPTEUR');
          auth.clean(); 
          toastr.error('Votre session a expiré. Veuillez vous reconnecter.', 'Déconnecté');
        }
      }
      return throwError(() => error);
    })
  );
};