import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { Auth } from '../services/auth';

export const authGuard = () => {
  const auth = inject(Auth);

  if (auth.isAuthenticated()) {
    return true;
  }

  inject(Router).navigate(['/login']);
  return false;
};