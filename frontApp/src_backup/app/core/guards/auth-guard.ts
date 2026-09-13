import { inject } from '@angular/core';
import { Router, CanActivateFn } from '@angular/router';
import { Auth } from '../services/auth';
import { toObservable } from '@angular/core/rxjs-interop';
import { filter, map, take } from 'rxjs/operators';

export const authGuard: CanActivateFn = () => {
   const auth = inject(Auth);
  const router = inject(Router);

  // 1. Cas rapide : La vérification initiale auprès de Laravel est déjà terminée
  if (!auth.isLoggingIn()) {
    if (auth.isAuthenticated()) {
      return true;
    }
    router.navigate(['/login']);
    return false;
  }

  // 2. Cas initial (Asynchrone) : L'application charge et attend la réponse du serveur.
  // On transforme le signal en Observable pour écouter la fin du chargement.
  return toObservable(auth.isLoggingIn).pipe(
    filter(loading => !loading), // Bloque l'exécution tant que 'loading' est vrai (true)
    take(1),                     // Désabonne automatiquement après le premier changement
    map(() => {
      if (auth.isAuthenticated()) {
        return true;
      }
      router.navigate(['/login']);
      return false;
    })
  );
};
