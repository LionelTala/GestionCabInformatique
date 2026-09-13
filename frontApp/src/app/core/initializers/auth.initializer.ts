// src/app/core/initializers/auth.initializer.ts
import { Auth } from '../services/auth';

export function initializeAuth(auth: Auth) {
  return () => {
    return new Promise<void>((resolve) => {
      // ✅ Si pas d'user en localStorage → pas connecté → on sort
      if (!localStorage.getItem('user')) {
        resolve();
        return;
      }

      // ✅ Sinon, on vérifie que la session est TOUJOURS valide
      auth.me().subscribe({
        next: () => resolve(),
        error: () => {
          // ✅ Session expirée côté serveur → nettoyage silencieux
          localStorage.removeItem('user');
          resolve();
        },
      });
    });
  };
}