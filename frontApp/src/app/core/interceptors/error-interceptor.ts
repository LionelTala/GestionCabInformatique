import { HttpInterceptorFn, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { ToastrService } from 'ngx-toastr';
import { catchError, throwError } from 'rxjs';

export const errorInterceptor: HttpInterceptorFn = (req, next) => {
  const toastr = inject(ToastrService);

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      // On affiche un toast pour les erreurs 500, mais on NE BLOQUE PAS les 401/419
      if (error.status === 500) {
        toastr.error('Une erreur interne est survenue.', 'Erreur serveur');
      }
      
      // ✅ TRÈS IMPORTANT : On doit TOUJOURS relancer l'erreur 
      // pour que l'intercepteur suivant (authInterceptor) puisse la traiter.
      return throwError(() => error);
    })
  );
};