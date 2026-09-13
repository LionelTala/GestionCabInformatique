// src/app/core/interceptors/credentials.interceptor.ts
import { HttpInterceptorFn } from '@angular/common/http';
import { environment } from '../../../environments/environment';

export const credentialsInterceptor: HttpInterceptorFn = (req, next) => {
  const isBackendRequest =
    req.url.startsWith(environment.apiUrl) ||
    req.url.startsWith(environment.baseUrl) ||
    req.url.includes('/api/') ||
    req.url.includes('/sanctum/');

  if (isBackendRequest) {
    req = req.clone({ withCredentials: true });
  }

  return next(req);
};