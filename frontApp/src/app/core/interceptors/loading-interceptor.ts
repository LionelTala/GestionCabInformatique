// core/interceptors/loading-interceptor.ts
import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { finalize } from 'rxjs';
import { LoadingService } from '../services/loading';

export const loadingInterceptor: HttpInterceptorFn = (req, next) => {
  const loadingService = inject(LoadingService);

  // ⚡ Loader seulement sur les écritures et les listes lourdes
  const showLoader = req.method !== 'GET' 
    || req.url.includes('/inscriptions') 
 
  if (!showLoader) {
    return next(req);
  }

  loadingService.show();

  return next(req).pipe(
    finalize(() => loadingService.hide())
  );
};