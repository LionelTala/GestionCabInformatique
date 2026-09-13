import { inject } from '@angular/core';
import { Router, CanActivateFn } from '@angular/router';
import { Auth } from '../services/auth';
import { toObservable } from '@angular/core/rxjs-interop';
import { filter, map, take } from 'rxjs/operators';

export const guestGuard: CanActivateFn = () => {
  const auth = inject(Auth);
  const router = inject(Router);

  // 1. Cas rapide : La vérification initiale auprès de Laravel est déjà terminée
  if (!auth.isLoggingIn()) {
    // Si l'utilisateur N'EST PAS connecté, il a le droit de voir la page (true)
    if (!auth.isAuthenticated()) {
      return true;
    }
    // S'il EST connecté, on lui interdit l'accès au login et on le redirige vers le tableau de bord
    router.navigate(['/dashboard']);
    return false;
  }

  // 2. Cas initial (Asynchrone) : L'application charge et attend la réponse du serveur.
  return toObservable(auth.isLoggingIn).pipe(
    filter(loading => !loading), // Attend que 'loading' passe à false
    take(1),                     // Se désabonne automatiquement
    map(() => {
      if (!auth.isAuthenticated()) {
        return true;
      }
      router.navigate(['/dashboard']);
      return false;
    })
  );
};
